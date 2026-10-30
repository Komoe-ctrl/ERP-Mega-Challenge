<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Administration\Models\Setting;
use App\Domain\Budget\Models\BudgetCommitment;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/** Utilisateur d'un profil donné, double authentification confirmée (Direction). */
function actor(RoleName $role): User
{
    return userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * Chantier avec un budget de matériaux, un conducteur (valideur) et un chef (demandeur) affectés.
 *
 * @return array{0: Chantier, 1: User, 2: User, 3: BudgetLine}
 */
function budgetedSite(int $revised = 100_000_000): array
{
    $chantier = Chantier::factory()->create();
    $conducteur = actor(RoleName::ConducteurTravaux);
    $chef = actor(RoleName::ChefChantier);
    $chantier->team()->attach($conducteur->id, ['role' => Chantier::ROLE_CONDUCTEUR]);
    $chantier->team()->attach($chef->id, ['role' => Chantier::ROLE_CHEF]);
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'nature' => 'materiaux', 'initial_amount' => $revised, 'revised_amount' => $revised]);

    return [$chantier, $conducteur, $chef, $line];
}

/** Payload de saisie : une ligne par article, [prix de l'article => quantité]. */
function requestPayload(Chantier $chantier, array $priceToQuantity = [6_500 => 100], array $overrides = []): array
{
    $lines = [];
    foreach ($priceToQuantity as $price => $quantity) {
        $lines[] = [
            'article_id' => Article::factory()->create(['price' => $price])->id,
            'quantity' => $quantity,
            'need_date' => now()->addDays(5)->toDateString(),
            'urgency' => 'normale',
        ];
    }

    return $overrides + ['chantier_id' => $chantier->id, 'justification' => 'Ferraillage du plancher haut R+1.', 'lines' => $lines];
}

/** Demande en attente déposée par le demandeur, via l'application. */
function pendingRequest(Chantier $chantier, User $requester, array $priceToQuantity = [6_500 => 100]): PurchaseRequest
{
    test()->actingAs($requester)->post(route('achats.demandes.store'), requestPayload($chantier, $priceToQuantity, ['submit' => true]))->assertSessionHasNoErrors();

    return PurchaseRequest::query()->latest('id')->firstOrFail();
}

// ------------------------------------------------------------------ saisie (RG8.1 à RG8.3)

it('RG8.1 : crée un brouillon rattaché à un seul chantier, avec ses lignes, sans prix saisi (RG8.2)', function () {
    [$chantier, , $chef] = budgetedSite();
    $payload = requestPayload($chantier, [6_500 => 400, 1_100 => 600]);
    $payload['lines'][0]['price'] = 1; // un prix forgé n'est jamais retenu
    $payload['lines'][0]['frozen_price'] = 1;

    $this->actingAs($chef)->post(route('achats.demandes.store'), $payload + ['justification' => null])->assertRedirect();

    $request = PurchaseRequest::query()->firstOrFail();
    $year = now()->year;
    expect($request->number)->toBe("DA-{$year}-0001")
        ->and($request->status->getValue())->toBe('brouillon')
        ->and($request->requester_id)->toBe($chef->id)
        ->and($request->chantier_id)->toBe($chantier->id)
        ->and($request->lines)->toHaveCount(2)
        ->and($request->lines->pluck('frozen_price')->filter()->all())->toBeEmpty()
        ->and($request->computeTotal())->toBe(6_500 * 400 + 1_100 * 600);
});

it('numérote les demandes de façon séquentielle', function () {
    [$chantier, , $chef] = budgetedSite();

    foreach (range(1, 3) as $i) {
        $this->actingAs($chef)->post(route('achats.demandes.store'), requestPayload($chantier));
    }

    $year = now()->year;
    expect(PurchaseRequest::query()->orderBy('id')->pluck('number')->all())->toBe(["DA-{$year}-0001", "DA-{$year}-0002", "DA-{$year}-0003"]);
});

it('RG8.4 : la soumission passe la demande « En attente » et l\'inscrit à l\'historique', function () {
    [$chantier, , $chef] = budgetedSite();

    $request = pendingRequest($chantier, $chef);

    expect($request->status->getValue())->toBe('en_attente')
        ->and($request->needs_direction)->toBeFalse()
        ->and($request->total_amount)->toBe(650_000)
        ->and($request->events->pluck('action')->all())->toBe(['Création (brouillon)', 'Soumise pour validation']);
});

it('valide la saisie côté serveur', function (callable $mutate, string $field) {
    [$chantier, , $chef] = budgetedSite();
    $payload = $mutate(requestPayload($chantier));

    $this->actingAs($chef)->post(route('achats.demandes.store'), $payload)->assertSessionHasErrors($field);
    expect(PurchaseRequest::query()->count())->toBe(0);
})->with([
    'aucune ligne' => [fn (array $p) => [...$p, 'lines' => []], 'lines'],
    'quantité nulle' => [function (array $p) {
        $p['lines'][0]['quantity'] = 0;

        return $p;
    }, 'lines.0.quantity'],
    'date de besoin passée' => [function (array $p) {
        $p['lines'][0]['need_date'] = '2020-01-01';

        return $p;
    }, 'lines.0.need_date'],
    'urgence inconnue' => [function (array $p) {
        $p['lines'][0]['urgency'] = 'folle';

        return $p;
    }, 'lines.0.urgency'],
    'article inexistant' => [function (array $p) {
        $p['lines'][0]['article_id'] = 99999;

        return $p;
    }, 'lines.0.article_id'],
]);

it('exige une justification à la soumission mais pas pour un brouillon', function () {
    [$chantier, , $chef] = budgetedSite();

    $this->actingAs($chef)->post(route('achats.demandes.store'), requestPayload($chantier, overrides: ['justification' => 'ok', 'submit' => true]))
        ->assertSessionHasErrors('justification');
    expect(PurchaseRequest::query()->count())->toBe(0);

    $this->actingAs($chef)->post(route('achats.demandes.store'), requestPayload($chantier, overrides: ['justification' => null]))->assertSessionHasNoErrors();
    expect(PurchaseRequest::query()->count())->toBe(1);
});

it('refuse un article désactivé du référentiel (RG8.14)', function () {
    [$chantier, , $chef] = budgetedSite();
    $payload = requestPayload($chantier);
    Article::query()->whereKey($payload['lines'][0]['article_id'])->update(['is_active' => false]);

    $this->actingAs($chef)->post(route('achats.demandes.store'), $payload)->assertSessionHasErrors('lines');
});

it('RG4.3 : refuse une demande sur un chantier en lecture seule', function () {
    [$chantier, , $chef] = budgetedSite();
    $chantier->project->forceFill(['status' => 'cloture'])->saveQuietly();

    $this->actingAs($chef)->post(route('achats.demandes.store'), requestPayload($chantier))->assertSessionHasErrors('chantier_id');
});

it('RG7.2 : refuse une demande sur un chantier où le demandeur n\'est pas affecté', function () {
    [$chantier] = budgetedSite();
    $stranger = actor(RoleName::ChefChantier);

    $this->actingAs($stranger)->post(route('achats.demandes.store'), requestPayload($chantier))->assertSessionHasErrors('chantier_id');
    expect(PurchaseRequest::query()->count())->toBe(0);
});

it('RG8.3 : le formulaire expose le budget disponible de chaque chantier et ses postes', function () {
    [$chantier, , $chef, $line] = budgetedSite(80_000_000);
    $line->update(['committed_amount' => 20_000_000, 'actual_amount' => 10_000_000]);

    $this->actingAs($chef)->get(route('achats.demandes.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achats/Demandes/Create')
            ->where("budgets.{$chantier->id}.remaining", 50_000_000)
            ->where("budgets.{$chantier->id}.lines.0.post", $line->post)
            ->has('articles')
            ->where('threshold', 5_000_000));
});

it('permet de corriger une demande renvoyée puis de la soumettre à nouveau', function () {
    [$chantier, $conducteur, $chef] = budgetedSite();
    $request = pendingRequest($chantier, $chef);

    $this->actingAs($conducteur)->post(route('achats.demandes.return', $request), ['reason' => 'Revoir le métré'])->assertSessionHasNoErrors();
    expect($request->refresh()->status->getValue())->toBe('renvoyee');

    $this->actingAs($chef)->put(route('achats.demandes.update', $request), requestPayload($chantier, [6_500 => 80], ['submit' => true]))->assertSessionHasNoErrors();
    $request->refresh()->load('lines');
    expect($request->status->getValue())->toBe('en_attente')
        ->and($request->lines)->toHaveCount(1)
        ->and((float) $request->lines[0]->quantity)->toBe(80.0);
});

it('seul l\'auteur corrige sa demande, et seulement brouillon ou renvoyée', function () {
    [$chantier, $conducteur, $chef] = budgetedSite();
    $pending = pendingRequest($chantier, $chef);
    $other = actor(RoleName::ChefChantier);
    $chantier->team()->attach($other->id, ['role' => Chantier::ROLE_CHEF]);

    $this->actingAs($chef)->get(route('achats.demandes.edit', $pending))->assertForbidden(); // en attente
    $this->actingAs($chef)->put(route('achats.demandes.update', $pending), requestPayload($chantier))->assertForbidden();

    $draft = PurchaseRequest::factory()->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);
    $this->actingAs($other)->get(route('achats.demandes.edit', $draft))->assertForbidden(); // pas l'auteur
    $this->actingAs($chef)->get(route('achats.demandes.edit', $draft))->assertOk();
});

// ------------------------------------------------------------------ validation (RG8.5, RG8.6, CA8.1)

it('RG8.6 / CA8.1 : la validation fige les prix, engage le budget et rend la demande non modifiable', function () {
    [$chantier, $conducteur, $chef, $line] = budgetedSite();
    $request = pendingRequest($chantier, $chef, [6_500 => 100, 1_100 => 200]); // 650 000 + 220 000

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertSessionHasNoErrors();

    $request->refresh()->load('lines.article');
    $commitment = BudgetCommitment::query()->firstOrFail();
    expect($request->status->getValue())->toBe('validee')
        ->and($request->total_amount)->toBe(870_000)
        ->and($request->decided_by)->toBe($conducteur->id)
        ->and($request->lines->pluck('frozen_price')->sort()->values()->all())->toBe([1_100, 6_500])
        ->and($line->refresh()->committed_amount)->toBe(870_000)
        ->and($commitment->amount)->toBe(870_000)
        ->and($commitment->budget_line_id)->toBe($line->id)
        ->and($commitment->source_id)->toBe($request->id)
        ->and($commitment->status)->toBe('active');

    // Une demande validée n'est plus modifiable, ni annulable.
    $this->actingAs($chef)->get(route('achats.demandes.edit', $request))->assertForbidden();
    $this->actingAs($chef)->put(route('achats.demandes.update', $request), requestPayload($chantier))->assertForbidden();
    $this->actingAs($chef)->post(route('achats.demandes.cancel', $request), ['reason' => 'Trop tard'])->assertForbidden();
});

it('RG8.6 : un prix modifié après la validation ne change ni le montant ni l\'engagement', function () {
    [$chantier, $conducteur, $chef, $line] = budgetedSite();
    $request = pendingRequest($chantier, $chef, [6_500 => 100]);
    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request));

    Article::query()->update(['price' => 9_999]);

    $request->refresh()->load('lines.article');
    expect($request->computeTotal())->toBe(650_000)
        ->and($line->refresh()->committed_amount)->toBe(650_000);

    $this->actingAs($conducteur)->get(route('achats.demandes.show', $request))
        ->assertInertia(fn (Assert $page) => $page->where('request.frozen', true)->where('request.total', 650_000)->where('request.lines.0.unitPrice', 6_500));
});

it('RG8.5 : le valideur ajuste les quantités, l\'écart est tracé et seul le montant ajusté est engagé', function () {
    [$chantier, $conducteur, $chef, $line] = budgetedSite();
    $request = pendingRequest($chantier, $chef, [6_500 => 100, 1_100 => 200]);
    $first = $request->lines()->orderBy('id')->first();

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request), ['adjustments' => [$first->id => 60]])->assertSessionHasNoErrors();

    $request->refresh()->load(['lines', 'events']);
    expect((float) $request->lines->firstWhere('id', $first->id)->adjusted_quantity)->toBe(60.0)
        ->and($request->total_amount)->toBe(6_500 * 60 + 220_000)
        ->and($line->refresh()->committed_amount)->toBe(610_000)
        ->and($request->events->pluck('action')->all())->toContain('Quantités ajustées', 'Validée')
        ->and($request->events->firstWhere('action', 'Quantités ajustées')->reason)->toContain('100 → 60');
});

it('refuse de valider si toutes les quantités sont ramenées à zéro', function () {
    [$chantier, $conducteur, $chef, $line] = budgetedSite();
    $request = pendingRequest($chantier, $chef);

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request), ['adjustments' => [$request->lines()->first()->id => 0]])
        ->assertSessionHasErrors('adjustments');
    expect($request->refresh()->status->getValue())->toBe('en_attente')->and($line->refresh()->committed_amount)->toBe(0);
});

it('RG8.6 : sans budget renseigné sur le chantier, rien n\'est engagé et la demande reste en attente', function () {
    $chantier = Chantier::factory()->create();
    $conducteur = actor(RoleName::ConducteurTravaux);
    $chef = actor(RoleName::ChefChantier);
    $chantier->team()->attach($conducteur->id, ['role' => Chantier::ROLE_CONDUCTEUR]);
    $chantier->team()->attach($chef->id, ['role' => Chantier::ROLE_CHEF]);
    $request = pendingRequest($chantier, $chef, [1_000 => 10]);
    $dg = actor(RoleName::DirectionGenerale);

    $this->actingAs($dg)->post(route('achats.demandes.validate', $request))->assertSessionHasErrors('status');
    expect($request->refresh()->status->getValue())->toBe('en_attente')
        ->and(BudgetCommitment::query()->count())->toBe(0)
        ->and($request->lines()->first()->frozen_price)->toBeNull();
});

it('engage le poste choisi par le demandeur', function () {
    [$chantier, $conducteur, $chef, $line] = budgetedSite();
    $second = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'post' => 'Second œuvre', 'nature' => 'materiaux', 'initial_amount' => 5_000_000, 'revised_amount' => 5_000_000]);
    $this->actingAs($chef)->post(route('achats.demandes.store'), requestPayload($chantier, [1_000 => 100], ['submit' => true, 'budget_line_id' => $second->id]))->assertSessionHasNoErrors();
    $request = PurchaseRequest::query()->firstOrFail();

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertSessionHasNoErrors();

    expect($second->refresh()->committed_amount)->toBe(100_000)->and($line->refresh()->committed_amount)->toBe(0);
});

it('refuse un poste qui n\'appartient pas au chantier', function () {
    [$chantier, , $chef] = budgetedSite();
    $foreign = BudgetLine::factory()->create();

    $this->actingAs($chef)->post(route('achats.demandes.store'), requestPayload($chantier, overrides: ['budget_line_id' => $foreign->id]))->assertSessionHasErrors('budget_line_id');
});

// ------------------------------------------------------------------ RG8.7 : validation Direction

it('RG8.7 : au-delà du seuil, seule la Direction valide, et le seuil est paramétrable', function () {
    $this->seed(SettingsSeeder::class);
    [$chantier, $conducteur, $chef, $line] = budgetedSite();
    $request = pendingRequest($chantier, $chef, [100_000 => 60]); // 6 000 000 > 5 000 000
    $dg = actor(RoleName::DirectionGenerale);

    expect($request->needs_direction)->toBeTrue();
    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertSessionHasErrors('status');
    expect($request->refresh()->status->getValue())->toBe('en_attente')->and($line->refresh()->committed_amount)->toBe(0);

    // Le seuil est un paramètre (RG1.3) : relevé à 10 M, le conducteur peut valider.
    Setting::query()->where('key', 'validation.direction_threshold_fcfa')->update(['value' => '10000000']);
    Cache::forget('erp.settings');
    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertSessionHasNoErrors();
    expect($request->refresh()->status->getValue())->toBe('validee');
});

it('RG8.7 : la Direction valide une demande au-delà du seuil', function () {
    [$chantier, , $chef, $line] = budgetedSite();
    $request = pendingRequest($chantier, $chef, [100_000 => 60]);

    $this->actingAs(actor(RoleName::DirectionGenerale))->post(route('achats.demandes.validate', $request))->assertSessionHasNoErrors();

    expect($request->refresh()->status->getValue())->toBe('validee')
        ->and($request->decided_by)->not->toBeNull()
        ->and($line->refresh()->committed_amount)->toBe(6_000_000);
});

it('RG8.7 : au-delà du budget restant, la Direction doit valider, même sous le seuil', function () {
    [$chantier, $conducteur, $chef, $line] = budgetedSite(3_000_000);
    $request = pendingRequest($chantier, $chef, [100_000 => 40]); // 4 M < seuil 5 M mais > restant 3 M

    expect($request->needs_direction)->toBeTrue();
    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertSessionHasErrors('status');
    $this->actingAs(actor(RoleName::DirectionGenerale))->post(route('achats.demandes.validate', $request))->assertSessionHasNoErrors();
    expect($line->refresh()->committed_amount)->toBe(4_000_000);
});

it('RG8.7 : un ajustement sous le seuil ne contourne pas la Direction', function () {
    [$chantier, $conducteur, $chef] = budgetedSite();
    $request = pendingRequest($chantier, $chef, [100_000 => 60]); // 6 M demandés

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request), ['adjustments' => [$request->lines()->first()->id => 10]])
        ->assertSessionHasErrors('status');
    expect($request->refresh()->status->getValue())->toBe('en_attente');
});

it('relit le budget restant à chaque validation : la seconde demande ne dépasse jamais le budget sans la Direction', function () {
    $this->seed(SettingsSeeder::class);
    Setting::query()->where('key', 'validation.direction_threshold_fcfa')->update(['value' => '50000000']);
    Cache::forget('erp.settings');
    [$chantier, $conducteur, $chef, $line] = budgetedSite(10_000_000);
    $a = pendingRequest($chantier, $chef, [100_000 => 60]); // 6 M
    $b = pendingRequest($chantier, $chef, [100_000 => 60]); // 6 M : chacune tient dans 10 M, pas les deux

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $a))->assertSessionHasNoErrors();
    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $b))->assertSessionHasErrors('status');

    expect($line->refresh()->committed_amount)->toBe(6_000_000)->and($b->refresh()->status->getValue())->toBe('en_attente');

    $this->actingAs(actor(RoleName::DirectionGenerale))->post(route('achats.demandes.validate', $b))->assertSessionHasNoErrors();
    expect($line->refresh()->committed_amount)->toBe(12_000_000)
        ->and((int) BudgetCommitment::query()->sum('amount'))->toBe(12_000_000);
});

it('ne valide jamais deux fois la même demande', function () {
    [$chantier, $conducteur, $chef, $line] = budgetedSite();
    $request = pendingRequest($chantier, $chef);

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertSessionHasNoErrors();
    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertForbidden();

    expect($line->refresh()->committed_amount)->toBe(650_000)->and(BudgetCommitment::query()->count())->toBe(1);
});

// ------------------------------------------------------------------ séparation des tâches et droits

it('séparation des tâches : l\'auteur ne valide jamais sa propre demande, même avec le droit de validation', function () {
    [$chantier, $conducteur] = budgetedSite();
    $this->actingAs($conducteur)->post(route('achats.demandes.store'), requestPayload($chantier, overrides: ['submit' => true]))->assertSessionHasNoErrors();
    $own = PurchaseRequest::query()->firstOrFail();

    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $own))->assertForbidden();
    $this->actingAs($conducteur)->post(route('achats.demandes.refuse', $own), ['reason' => 'test'])->assertForbidden();
    $this->actingAs($conducteur)->post(route('achats.demandes.return', $own), ['reason' => 'test'])->assertForbidden();
    expect($own->refresh()->status->getValue())->toBe('en_attente');

    $this->actingAs($conducteur)->get(route('achats.demandes.show', $own))
        ->assertInertia(fn (Assert $page) => $page->where('isAuthor', true)->where('canDecide', false)->where('canValidate', false));
});

it('un profil sans droit de validation ne peut pas décider', function (RoleName $role) {
    [$chantier, , $chef] = budgetedSite();
    $request = pendingRequest($chantier, $chef);
    $user = actor($role);

    $this->actingAs($user)->post(route('achats.demandes.validate', $request))->assertForbidden();
    $this->actingAs($user)->post(route('achats.demandes.refuse', $request), ['reason' => 'test'])->assertForbidden();
    expect($request->refresh()->status->getValue())->toBe('en_attente');
})->with([RoleName::ChefChantier, RoleName::Magasinier, RoleName::Comptabilite, RoleName::Finance]);

it('un conducteur ne décide que sur ses chantiers (RG7.2)', function () {
    [$chantier, , $chef] = budgetedSite();
    $request = pendingRequest($chantier, $chef);
    $otherConducteur = actor(RoleName::ConducteurTravaux);

    $this->actingAs($otherConducteur)->post(route('achats.demandes.validate', $request))->assertForbidden();
    $this->actingAs($otherConducteur)->get(route('achats.demandes.show', $request))->assertForbidden();
});

it('seuls les profils autorisés créent une demande (403 sinon)', function (RoleName $role) {
    [$chantier] = budgetedSite();

    $this->actingAs(actor($role))->post(route('achats.demandes.store'), requestPayload($chantier))->assertForbidden();
    expect(PurchaseRequest::query()->count())->toBe(0);
})->with([RoleName::DirectionGenerale, RoleName::Comptabilite, RoleName::Finance, RoleName::RessourcesHumaines]);

// ------------------------------------------------------------------ refus, renvoi, annulation (RG8.4, RG8.5)

it('RG8.5 : un refus ou un renvoi exige un motif et l\'inscrit à l\'historique', function (string $route, string $status, string $action) {
    [$chantier, $conducteur, $chef] = budgetedSite();
    $request = pendingRequest($chantier, $chef);

    $this->actingAs($conducteur)->post(route($route, $request), [])->assertSessionHasErrors('reason');
    expect($request->refresh()->status->getValue())->toBe('en_attente');

    $this->actingAs($conducteur)->post(route($route, $request), ['reason' => 'Article déjà disponible au magasin'])->assertSessionHasNoErrors();
    $request->refresh()->load('events');
    expect($request->status->getValue())->toBe($status)
        ->and($request->events->last()->action)->toBe($action)
        ->and($request->events->last()->reason)->toBe('Article déjà disponible au magasin')
        ->and(BudgetCommitment::query()->count())->toBe(0);
})->with([
    'refus' => ['achats.demandes.refuse', 'refusee', 'Refusée'],
    'renvoi' => ['achats.demandes.return', 'renvoyee', 'Renvoyée pour correction'],
]);

it('RG8.4 : l\'auteur annule sa demande tant qu\'elle n\'est pas validée, avec un motif', function (string $status) {
    [$chantier, , $chef] = budgetedSite();
    $request = PurchaseRequest::factory()->withStatus($status)->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);

    $this->actingAs($chef)->post(route('achats.demandes.cancel', $request), [])->assertSessionHasErrors('reason');
    $this->actingAs($chef)->post(route('achats.demandes.cancel', $request), ['reason' => 'Doublon'])->assertSessionHasNoErrors();

    expect($request->refresh()->status->getValue())->toBe('annulee');
})->with(['brouillon', 'en_attente', 'renvoyee']);

it('RG8.4 : seul l\'auteur annule, et jamais une demande déjà décidée', function () {
    [$chantier, $conducteur, $chef] = budgetedSite();
    $request = PurchaseRequest::factory()->withStatus('en_attente')->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);

    $this->actingAs($conducteur)->post(route('achats.demandes.cancel', $request), ['reason' => 'Pas la mienne'])->assertForbidden();

    foreach (['validee', 'refusee', 'annulee'] as $status) {
        $decided = PurchaseRequest::factory()->withStatus($status)->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);
        $this->actingAs($chef)->post(route('achats.demandes.cancel', $decided), ['reason' => 'Trop tard'])->assertForbidden();
    }
});

// ------------------------------------------------------------------ listes, journal

it('liste les demandes paginées et filtrées par statut, chantier et recherche', function () {
    [$chantier, , $chef] = budgetedSite();
    PurchaseRequest::factory()->count(16)->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);
    PurchaseRequest::factory()->withStatus('validee')->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id, 'justification' => 'Béton spécial']);

    $this->actingAs($chef)->get(route('achats.demandes'))
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 15)->where('requests.total', 17));
    $this->actingAs($chef)->get(route('achats.demandes', ['status' => 'validee']))
        ->assertInertia(fn (Assert $page) => $page->where('requests.total', 1));
    $this->actingAs($chef)->get(route('achats.demandes', ['q' => 'spécial']))
        ->assertInertia(fn (Assert $page) => $page->where('requests.total', 1));
    $this->actingAs($chef)->get(route('achats.demandes', ['chantier' => $chantier->id + 100]))
        ->assertInertia(fn (Assert $page) => $page->where('requests.total', 0));
});

it('RG7.2 : un utilisateur de terrain ne voit que les demandes de ses chantiers', function () {
    [$chantier, , $chef] = budgetedSite();
    [$otherChantier, , $otherChef] = budgetedSite();
    PurchaseRequest::factory()->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);
    $foreign = PurchaseRequest::factory()->create(['chantier_id' => $otherChantier->id, 'requester_id' => $otherChef->id]);

    $this->actingAs($chef)->get(route('achats.demandes'))->assertInertia(fn (Assert $page) => $page->where('requests.total', 1));
    $this->actingAs($chef)->get(route('achats.demandes.show', $foreign))->assertForbidden();
});

it('la page « À valider » ne propose que les demandes que l\'utilisateur peut décider', function () {
    [$chantier, $conducteur, $chef] = budgetedSite();
    pendingRequest($chantier, $chef);
    $this->actingAs($conducteur)->post(route('achats.demandes.store'), requestPayload($chantier, overrides: ['submit' => true]));
    PurchaseRequest::factory()->withStatus('validee')->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);

    $this->actingAs($conducteur)->get(route('achats.a-valider'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achats/Demandes/AValider')
            ->has('pending', 1) // sa propre demande est exclue
            ->where('pending.0.requester', $chef->name)
            ->where('pending.0.remaining', 100_000_000)
            ->where('pending.0.direction', false));

    $this->actingAs($chef)->get(route('achats.a-valider'))->assertForbidden();
});

it('trace la décision et l\'engagement au journal des activités', function () {
    [$chantier, $conducteur, $chef] = budgetedSite();
    $request = pendingRequest($chantier, $chef);
    $this->actingAs($conducteur)->post(route('achats.demandes.validate', $request));

    $entry = Activity::query()->where('subject_type', PurchaseRequest::class)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->causer_id)->toBe($conducteur->id)
        ->and($entry->attribute_changes?->get('old'))->toMatchArray(['status' => 'en_attente'])
        ->and($entry->attribute_changes?->get('attributes'))->toMatchArray(['status' => 'validee']);
    expect(Activity::query()->where('subject_type', BudgetCommitment::class)->where('event', 'created')->exists())->toBeTrue();
});

it('ne permet jamais de supprimer une demande d\'achat', function () {
    $deletable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('DELETE', $r->methods(), true) && str_contains($r->uri(), 'demandes'));

    [$chantier, , $chef] = budgetedSite();
    expect($deletable)->toBeEmpty()
        ->and($chef->can('delete', PurchaseRequest::factory()->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id])))->toBeFalse();
});

it('la fiche d\'une demande à valider indique si la Direction est requise', function () {
    [$chantier, $conducteur, $chef] = budgetedSite();
    $request = pendingRequest($chantier, $chef, [100_000 => 60]);

    $this->actingAs($conducteur)->get(route('achats.demandes.show', $request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('directionRequired', true)
            ->where('canDecide', true)
            ->where('canValidate', false)
            ->where('budget.remaining', 100_000_000));
    $this->actingAs(actor(RoleName::DirectionGenerale))->get(route('achats.demandes.show', $request))
        ->assertInertia(fn (Assert $page) => $page->where('canValidate', true));
});

it('crée des projets, chantiers et demandes cohérents avec la fabrique', function () {
    $request = PurchaseRequest::factory()->withLines([1_000 => 3, 2_000 => 5])->create();
    $request->load('lines.article');

    expect($request->computeTotal())->toBe(13_000)
        ->and($request->chantier->project)->toBeInstanceOf(Project::class);
});
