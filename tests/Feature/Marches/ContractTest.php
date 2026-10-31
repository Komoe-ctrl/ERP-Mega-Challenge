<?php

declare(strict_types=1);

use App\Domain\Devis\Models\Quote;
use App\Domain\Marches\Models\Contract;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// M6 — Marchés : RG6.1, RG6.2, RG6.3, CA6.

function contractUser(RoleName $role): User
{
    return userWithRole($role, ['two_factor_secret' => encrypt('s'), 'two_factor_recovery_codes' => encrypt(json_encode(['c'])), 'two_factor_confirmed_at' => now()]);
}

/** Devis validé de 1 000 000 FCFA HT, établi par la Direction technique et validé par la Direction générale. */
function validatedQuote(User $dt, User $dg, string $projectStatus = 'etude'): Quote
{
    $project = Project::factory()->withStatus($projectStatus)->create();
    test()->actingAs($dt)->post(route('devis.store'), ['client_id' => $project->client_id, 'project_id' => $project->id, 'vat_rate' => 18])->assertSessionHasNoErrors();
    $quote = Quote::query()->latest('id')->firstOrFail();
    test()->actingAs($dt)->put(route('devis.update', $quote), [
        'discount_rate' => 0, 'vat_rate' => 18, 'direct_cost' => 800_000,
        'works' => [['label' => 'Gros œuvre complet', 'unit' => 'ens', 'quantity' => 1, 'unit_price' => 1_000_000]],
    ])->assertSessionHasNoErrors();
    test()->actingAs($dt)->post(route('devis.submit', $quote))->assertSessionHasNoErrors();
    test()->actingAs($dg)->post(route('devis.validate', $quote))->assertSessionHasNoErrors();

    return $quote->refresh();
}

function contractPayload(Quote $quote, array $overrides = []): array
{
    return $overrides + [
        'quote_id' => $quote->id, 'signed_at' => now()->toDateString(), 'deadline_days' => 180,
        'advance_rate' => 10, 'retention_rate' => 5, 'payment_days' => 30,
    ];
}

function newContract(User $dt, User $dg): Contract
{
    $quote = validatedQuote($dt, $dg);
    test()->actingAs($dt)->post(route('marches.store'), contractPayload($quote))->assertSessionHasNoErrors();

    return Contract::query()->latest('id')->firstOrFail();
}

it('RG6.1 : un marché naît d\'un devis validé et reprend son montant HT', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $quote = validatedQuote($dt, $dg);

    $this->actingAs($dt)->post(route('marches.store'), contractPayload($quote))->assertSessionHasNoErrors();

    $contract = Contract::query()->firstOrFail();
    $year = now()->year;
    expect($contract->number)->toBe("MAR-{$year}-00001")
        ->and($contract->initial_amount)->toBe(1_000_000)
        ->and($contract->client_id)->toBe($quote->client_id)
        ->and($contract->project_id)->toBe($quote->project_id)
        ->and($contract->retention_rate)->toBe('5.00')
        ->and($contract->project->status->getValue())->toBe('marche');
});

it('RG6.1 : refuse un devis non validé et un second marché pour le même devis', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);

    $project = Project::factory()->withStatus('etude')->create();
    $this->actingAs($dt)->post(route('devis.store'), ['client_id' => $project->client_id, 'project_id' => $project->id, 'vat_rate' => 18]);
    $draft = Quote::query()->latest('id')->firstOrFail();
    $this->actingAs($dt)->post(route('marches.store'), contractPayload($draft))->assertSessionHasErrors('quote_id');

    $quote = validatedQuote($dt, $dg);
    $this->actingAs($dt)->post(route('marches.store'), contractPayload($quote))->assertSessionHasNoErrors();
    $this->actingAs($dt)->post(route('marches.store'), contractPayload($quote))->assertSessionHasErrors('quote_id');

    expect(Contract::query()->count())->toBe(1);
});

it('contrôle la date de signature et les conditions de paiement', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $quote = validatedQuote($dt, $dg);

    $this->actingAs($dt)->post(route('marches.store'), contractPayload($quote, ['signed_at' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('signed_at');
    $this->actingAs($dt)->post(route('marches.store'), contractPayload($quote, ['retention_rate' => 120, 'deadline_days' => 0]))
        ->assertSessionHasErrors(['retention_rate', 'deadline_days']);
});

it('RG6.2 : un avenant n\'a d\'effet qu\'une fois validé, par une autre personne que son auteur', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);

    $this->actingAs($dt)->post(route('marches.amendments.store', $contract), [
        'label' => 'Travaux supplémentaires de voirie', 'amount' => 250_000, 'days' => 15, 'amendment_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $amendment = $contract->amendments()->firstOrFail();
    expect($amendment->number)->toBe('AV-01')
        ->and($contract->fresh()->load('amendments')->totals())->toBe(['amendmentsAmount' => 0, 'updatedAmount' => 1_000_000, 'contractualDays' => 180]);

    $this->actingAs($dt)->post(route('marches.amendments.validate', [$contract, $amendment]))->assertSessionHasErrors('amendment');

    $this->actingAs($dg)->post(route('marches.amendments.validate', [$contract, $amendment]))->assertSessionHasNoErrors();

    expect($contract->fresh()->load('amendments')->totals())->toBe(['amendmentsAmount' => 250_000, 'updatedAmount' => 1_250_000, 'contractualDays' => 195]);

    // Déjà validé.
    $this->actingAs($dg)->post(route('marches.amendments.validate', [$contract, $amendment]))->assertSessionHasErrors('amendment');
});

it('l\'auteur d\'un avenant ne le valide pas même avec le droit de validation (action)', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);

    // La Direction technique a le droit « valider » sur les marchés : seul l'auteur est bloqué par l'action.
    $this->actingAs($dt)->post(route('marches.amendments.store', $contract), [
        'label' => 'Moins-value sur la peinture', 'amount' => -100_000, 'days' => 0, 'amendment_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $amendment = $contract->amendments()->firstOrFail();

    $this->actingAs($dt)->post(route('marches.amendments.validate', [$contract, $amendment]))->assertSessionHasErrors('amendment');
    expect($amendment->fresh()->is_validated)->toBeFalse();
});

it('refuse un avenant sans effet et une moins-value qui rendrait le marché négatif', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);

    $this->actingAs($dt)->post(route('marches.amendments.store', $contract), [
        'label' => 'Avenant sans effet', 'amount' => 0, 'days' => 0, 'amendment_date' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    $this->actingAs($dt)->post(route('marches.amendments.store', $contract), [
        'label' => 'Moins-value excessive', 'amount' => -2_000_000, 'days' => 0, 'amendment_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $amendment = $contract->amendments()->firstOrFail();

    $this->actingAs($dg)->post(route('marches.amendments.validate', [$contract, $amendment]))->assertSessionHasErrors('amendment');
    expect($amendment->fresh()->is_validated)->toBeFalse();
});

it('un avenant ne se valide que sur son propre marché', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $first = newContract($dt, $dg);
    $second = newContract($dt, $dg);
    $this->actingAs($dt)->post(route('marches.amendments.store', $first), [
        'label' => 'Avenant du premier marché', 'amount' => 10_000, 'days' => 1, 'amendment_date' => now()->toDateString(),
    ]);

    $this->actingAs($dg)->post(route('marches.amendments.validate', [$second, $first->amendments()->firstOrFail()]))->assertNotFound();
});

it('CA6 : la fiche affiche montant initial, avenants validés, montant actualisé et conditions de paiement', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);

    $this->actingAs($dt)->get(route('marches.show', $contract))
        ->assertInertia(fn (Assert $page) => $page->component('Marches/Show')
            ->where('contract.initialAmount', 1_000_000)->where('contract.updated', 1_000_000)
            ->where('contract.retentionRate', 5)->where('contract.advanceRate', 10)->where('contract.paymentDays', 30)
            ->where('contract.days', 180)->where('contract.quoteNumber', $contract->quote?->number));
});

it('liste les marchés, propose les devis validés sans marché et filtre par recherche', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $orphan = validatedQuote($dt, $dg);

    $this->actingAs($dt)->get(route('marches.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Marches/Index')->has('contracts.data', 1)
            ->has('signableQuotes', 1)->where('signableQuotes.0.id', $orphan->id));

    $this->actingAs($dt)->get(route('marches.index', ['q' => 'introuvable']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 0));
    $this->actingAs($dt)->get(route('marches.index', ['q' => $contract->number]))
        ->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1));
});

it('refuse les marchés à un profil sans droit et l\'écriture à un profil en lecture', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);

    $this->actingAs(contractUser(RoleName::Magasinier))->get(route('marches.index'))->assertForbidden();
    $this->actingAs(contractUser(RoleName::Administrateur))->get(route('marches.show', $contract))->assertForbidden();

    $this->actingAs($dg)->get(route('marches.show', $contract))->assertOk();
    $this->actingAs($dg)->post(route('marches.amendments.store', $contract), [
        'label' => 'Avenant interdit', 'amount' => 1, 'days' => 0, 'amendment_date' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($dg)->post(route('marches.store'), contractPayload(validatedQuote($dt, $dg)))->assertForbidden();
});
