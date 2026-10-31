<?php

declare(strict_types=1);

use App\Domain\Clients\Support\ClientBalance;
use App\Domain\Marches\Models\Contract;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Situations\Models\Situation;
use App\Domain\Situations\Support\SituationCalculator;
use Inertia\Testing\AssertableInertia as Assert;

// M11 — Situations de travaux : RG11.1, RG11.2, RG11.3, CA11.

function newSituation(Contract $contract, $by, float $progress, array $overrides = []): Situation
{
    test()->actingAs($by)->post(route('situations.store'), $overrides + [
        'contract_id' => $contract->id, 'situation_date' => now()->toDateString(), 'progress' => $progress,
    ])->assertSessionHasNoErrors();

    return Situation::query()->latest('id')->firstOrFail();
}

function validatedSituation(Contract $contract, $author, $validator, float $progress): Situation
{
    $situation = newSituation($contract, $author, $progress);
    test()->actingAs($validator)->post(route('situations.validate', $situation))->assertSessionHasNoErrors();

    return $situation->refresh();
}

it('CA11 : exemple du cahier — marché de 100 M, avancement de 30 % à 40 %, retenue 5 %, acompte 10 %', function () {
    $calc = SituationCalculator::compute(100_000_000, 40, 30, 5, 10, 0);

    expect($calc['amount'])->toBe(10_000_000)
        ->and($calc['retention'])->toBe(500_000)
        ->and($calc['advanceRefund'])->toBe(1_000_000)
        ->and($calc['netHt'])->toBe(8_500_000)
        ->and(SituationCalculator::compute(100_000_000, 40, 30, 5, 10, 18)['netTtc'])->toBe(10_030_000);
});

it('RG11.2 : une situation reprend les taux et le montant actualisé du marché', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);

    $situation = newSituation($contract, $dt, 30);

    expect($situation->number)->toBe(1)
        ->and($situation->status)->toBe('brouillon')
        ->and($situation->market_amount)->toBe(1_000_000)
        ->and($situation->retention_rate)->toBe('5.00')
        ->and($situation->advance_refund_rate)->toBe('10.00')
        ->and($situation->previous_progress)->toBe('0.00');

    // 30 % de 1 000 000 = 300 000 ; retenue 15 000 ; acompte 30 000 ; net HT 255 000 ; TVA 18 % = 45 900.
    expect($situation->computation())->toMatchArray(['amount' => 300_000, 'netHt' => 255_000, 'vat' => 45_900, 'netTtc' => 300_900]);
});

it('RG6.2 : le montant du marché intègre les avenants validés', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $this->actingAs($dt)->post(route('marches.amendments.store', $contract), ['label' => 'Travaux supplémentaires', 'amount' => 500_000, 'days' => 0, 'amendment_date' => now()->toDateString()]);
    $this->actingAs($dg)->post(route('marches.amendments.validate', [$contract, $contract->amendments()->firstOrFail()]));

    expect(newSituation($contract, $dt, 10)->market_amount)->toBe(1_500_000);
});

it('RG11.1 : l\'avancement cumulé ne diminue pas et ne dépasse pas 100 %', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $first = validatedSituation($contract, $dt, $dg, 40);

    $this->actingAs($dt)->post(route('situations.store'), ['contract_id' => $contract->id, 'situation_date' => now()->toDateString(), 'progress' => 30])
        ->assertSessionHasErrors('progress');
    $this->actingAs($dt)->post(route('situations.store'), ['contract_id' => $contract->id, 'situation_date' => now()->toDateString(), 'progress' => 120])
        ->assertSessionHasErrors('progress');

    $second = newSituation($contract, $dt, 70);
    expect($second->number)->toBe(2)->and($second->previous_progress)->toBe('40.00')
        ->and($second->computation()['amount'])->toBe(300_000)
        ->and($first->fresh()->status)->toBe('validee');
});

it('RG11.1 : l\'avancement se saisit par ouvrage du devis, pondéré par leur montant', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $project = quoteProject();
    $quote = draftQuote($dt, $project);
    saveLines($quote, $dt, [
        ['label' => 'Fondations', 'unit' => 'ens', 'quantity' => 1, 'unit_price' => 600_000],
        ['label' => 'Élévation', 'unit' => 'ens', 'quantity' => 1, 'unit_price' => 400_000],
    ]);
    $this->actingAs($dt)->post(route('devis.submit', $quote));
    $this->actingAs($dg)->post(route('devis.validate', $quote));
    $this->actingAs($dt)->post(route('marches.store'), contractPayload($quote->refresh()))->assertSessionHasNoErrors();
    $contract = Contract::query()->firstOrFail();
    $situation = newSituation($contract, $dt, 0);
    [$a, $b] = $quote->works()->pluck('id')->all();

    $this->actingAs($dt)->put(route('situations.update', $situation), [
        'situation_date' => now()->toDateString(), 'works' => [$a => 100, $b => 25],
        'retention_rate' => 5, 'advance_refund_rate' => 10, 'vat_rate' => 18,
    ])->assertSessionHasNoErrors();

    // (600 000 × 100 + 400 000 × 25) / 1 000 000 = 70 %.
    expect($situation->fresh()->progress_cumul)->toBe('70.00')
        ->and($situation->lines()->count())->toBe(2);

    // Un ouvrage étranger au devis du marché est refusé.
    $this->actingAs($dt)->put(route('situations.update', $situation), [
        'situation_date' => now()->toDateString(), 'works' => [999999 => 10],
        'retention_rate' => 5, 'advance_refund_rate' => 10, 'vat_rate' => 18,
    ])->assertSessionHasErrors('works');
});

it('refuse une nouvelle situation tant que la précédente est en brouillon', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    newSituation($contract, $dt, 20);

    $this->actingAs($dt)->post(route('situations.store'), ['contract_id' => $contract->id, 'situation_date' => now()->toDateString(), 'progress' => 30])
        ->assertSessionHasErrors('contract_id');
    expect(Situation::query()->count())->toBe(1);
});

it('RG11.3 : une situation validée est figée', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $situation = validatedSituation($contract, $dt, $dg, 40);

    expect($situation->validated_by)->toBe($dg->id)->and($situation->validated_at)->not->toBeNull();

    $this->actingAs($dt)->put(route('situations.update', $situation), [
        'situation_date' => now()->toDateString(), 'progress' => 90, 'retention_rate' => 0, 'advance_refund_rate' => 0, 'vat_rate' => 18,
    ])->assertForbidden();
    expect($situation->fresh()->progress_cumul)->toBe('40.00');
});

it('séparation des tâches : l\'auteur ne valide pas sa propre situation', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $situation = newSituation($contract, $dt, 40);

    $this->actingAs($dt)->post(route('situations.validate', $situation))->assertForbidden();
    expect($situation->fresh()->status)->toBe('brouillon');
});

it('enregistre des paiements partiels puis solde la situation, sans dépasser le net à payer', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $situation = validatedSituation($contract, $dt, $dg, 30); // net TTC 300 900

    $pay = fn (int $amount, array $extra = []) => $this->actingAs($dt)->post(route('situations.payments.store', $situation), $extra + [
        'amount' => $amount, 'paid_at' => now()->toDateString(), 'reference' => 'VIR-001',
    ]);

    $pay(100_000)->assertSessionHasNoErrors();
    expect($situation->fresh()->status)->toBe('validee');

    $pay(300_000)->assertSessionHasErrors('amount'); // dépasse le solde de 200 900
    $pay(100_000, ['paid_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('paid_at');

    $pay(200_900)->assertSessionHasNoErrors();
    expect($situation->fresh()->status)->toBe('payee');

    $pay(1)->assertForbidden(); // soldée
});

it('refuse un paiement sur une situation en brouillon', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $situation = newSituation(newContract($dt, $dg), $dt, 30);

    $this->actingAs($dt)->post(route('situations.payments.store', $situation), ['amount' => 1000, 'paid_at' => now()->toDateString()])->assertForbidden();
});

it('RG2.2 : la fiche client agrège le contracté, le facturé, l\'encaissé et le dû', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $client = $contract->client;
    $situation = validatedSituation($contract, $dt, $dg, 30);
    newSituation($contract, $dt, 50); // brouillon : non facturé
    $this->actingAs($dt)->post(route('situations.payments.store', $situation), ['amount' => 100_000, 'paid_at' => now()->toDateString()]);

    expect((new ClientBalance)->for($client))->toMatchArray(['contracted' => 1_000_000, 'invoiced' => 300_900, 'paid' => 100_000, 'due' => 200_900]);

    $this->actingAs($dt)->get(route('clients.show', $client))
        ->assertInertia(fn (Assert $page) => $page->where('summary.invoiced', 300_900)->where('summary.due', 200_900));
});

it('liste les situations avec filtre par statut et affiche le détail', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $first = validatedSituation($contract, $dt, $dg, 30);
    $second = newSituation($contract, $dt, 50);

    $this->actingAs($dt)->get(route('situations.index', ['status' => 'brouillon']))
        ->assertInertia(fn (Assert $page) => $page->component('Situations/Index')->has('situations.data', 1)->where('situations.data.0.id', $second->id));

    $this->actingAs($dt)->get(route('situations.show', $first))
        ->assertInertia(fn (Assert $page) => $page->component('Situations/Show')
            ->where('situation.calc.netTtc', 300_900)->where('situation.remaining', 300_900)
            ->where('can.update', false)->where('can.pay', true));

    $this->actingAs($dt)->get(route('marches.show', $contract))
        ->assertInertia(fn (Assert $page) => $page->has('situations', 2));
});

it('refuse les situations à un profil sans droit et l\'écriture à un profil en lecture', function () {
    $dt = contractUser(RoleName::DirectionTechnique);
    $dg = contractUser(RoleName::DirectionGenerale);
    $contract = newContract($dt, $dg);
    $situation = newSituation($contract, $dt, 30);

    $this->actingAs(contractUser(RoleName::Magasinier))->get(route('situations.index'))->assertForbidden();
    $this->actingAs(contractUser(RoleName::Administrateur))->get(route('situations.show', $situation))->assertForbidden();

    $this->actingAs($dg)->get(route('situations.show', $situation))->assertOk();
    $this->actingAs($dg)->post(route('situations.store'), ['contract_id' => $contract->id, 'situation_date' => now()->toDateString(), 'progress' => 90])->assertForbidden();
});
