<?php

declare(strict_types=1);

use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Pieces\Enums\PieceKind;
use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\ProjectLot;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Models\RubriqueBudget;
use App\Domain\Pieces\Models\ValidationEvent;
use App\Domain\Pieces\Models\ValidationStep;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

// Circuit de validation paramétrable appliqué aux pièces (note de frais, recette, pièce de caisse).

/** Circuit par défaut : chef de service (Direction technique) puis Directeur (Direction générale). */
function defaultCircuit(): void
{
    ValidationStep::query()->create(['position' => 1, 'label' => 'Validation du chef de service', 'role_name' => RoleName::DirectionTechnique->value, 'button_label' => 'Chef de service', 'is_active' => true]);
    ValidationStep::query()->create(['position' => 2, 'label' => 'Approbation du Directeur', 'role_name' => RoleName::DirectionGenerale->value, 'button_label' => 'Directeur', 'is_active' => true]);
}

/** Exercice courant ouvert, une rubrique budgétée 1 000 000 FCFA. */
function pieceContext(): array
{
    $year = FiscalYear::factory()->create(['label' => (string) now()->year, 'starts_on' => now()->year.'-01-01', 'ends_on' => now()->year.'-12-31']);
    $rubrique = Rubrique::query()->create(['code' => '625', 'label' => 'Déplacements', 'is_active' => true]);
    RubriqueBudget::query()->create(['rubrique_id' => $rubrique->id, 'fiscal_year_id' => $year->id, 'amount' => 1_000_000]);

    return [$year, $rubrique];
}

function pieceUser(RoleName $role): User
{
    return userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function piecePayload(Rubrique $rubrique, array $overrides = []): array
{
    return array_merge([
        'period' => now()->format('Y-m'),
        'title' => 'Mission de suivi — Bouaké',
        'lines' => [
            ['designation' => 'Transport', 'quantity' => 2, 'unit_price' => 18_000, 'rubrique_id' => $rubrique->id],
            ['designation' => 'Hébergement', 'quantity' => 2, 'unit_price' => 35_000, 'rubrique_id' => $rubrique->id],
        ],
    ], $overrides);
}

function createPiece(User $author, Rubrique $rubrique, array $overrides = []): Piece
{
    test()->actingAs($author)->post(route('pieces.store', 'note_frais'), piecePayload($rubrique, $overrides))->assertSessionHasNoErrors();

    return Piece::query()->latest('id')->firstOrFail();
}

it('enregistre une note de frais avec montants calculés côté serveur et numéro automatique', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);

    $piece = createPiece($author, $rubrique);

    expect($piece->total)->toBe(106_000)
        ->and($piece->status)->toBe(PieceStatus::Saisie)
        ->and($piece->number)->toStartWith('NF-'.now()->year.'-')
        ->and($piece->lines()->pluck('amount')->all())->toBe([36_000, 70_000])
        ->and(ValidationEvent::query()->where('subject_id', $piece->id)->pluck('action')->all())->toBe(['saisie']);
});

it('ignore un montant envoyé par le navigateur : le total est recalculé', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);

    $payload = piecePayload($rubrique);
    $payload['total'] = 1;
    $payload['lines'][0]['amount'] = 1;
    $this->actingAs($author)->post(route('pieces.store', 'note_frais'), $payload);

    expect(Piece::query()->firstOrFail()->total)->toBe(106_000);
});

it('refuse un numéro de pièce déjà utilisé pour ce type', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    createPiece($author, $rubrique, ['number' => 'NF-A']);

    $this->actingAs($author)->post(route('pieces.store', 'note_frais'), piecePayload($rubrique, ['number' => 'NF-A']))
        ->assertSessionHasErrors('number');
});

it('refuse une période dans un exercice clôturé ou absent (RG1.4)', function () {
    [$year, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $year->update(['status' => FiscalYear::STATUS_CLOSED, 'closed_at' => now()]);

    $this->actingAs($author)->post(route('pieces.store', 'note_frais'), piecePayload($rubrique))->assertSessionHasErrors('period');
    $this->actingAs($author)->post(route('pieces.store', 'note_frais'), piecePayload($rubrique, ['period' => '1999-05']))->assertSessionHasErrors('period');
});

it('refuse une imputation analytique incohérente (lot d\'un autre projet)', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $project = Project::factory()->create();
    $other = Project::factory()->create();
    $lot = ProjectLot::query()->create(['project_id' => $other->id, 'name' => 'Lot 1']);

    $this->actingAs($author)->post(route('pieces.store', 'note_frais'), piecePayload($rubrique, ['project_id' => $project->id, 'project_lot_id' => $lot->id]))
        ->assertSessionHasErrors('project_lot_id');
});

it('refuse une rubrique désactivée', function () {
    [, $rubrique] = pieceContext();
    $rubrique->update(['is_active' => false]);

    $this->actingAs(pieceUser(RoleName::ConducteurTravaux))->post(route('pieces.store', 'note_frais'), piecePayload($rubrique))
        ->assertSessionHasErrors('lines.0.rubrique_id');
});

it('suit le circuit : soumission, validation du chef de service, approbation du Directeur', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $chef = pieceUser(RoleName::DirectionTechnique);
    $dg = pieceUser(RoleName::DirectionGenerale);
    $piece = createPiece($author, $rubrique);

    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]))->assertSessionHasNoErrors();
    expect($piece->fresh()?->status)->toBe(PieceStatus::EnValidation)->and($piece->fresh()?->current_step)->toBe(1);

    $this->actingAs($chef)->post(route('pieces.advance', ['note_frais', $piece]), ['comment' => 'Conforme.'])->assertSessionHasNoErrors();
    expect($piece->fresh()?->status)->toBe(PieceStatus::EnValidation)->and($piece->fresh()?->current_step)->toBe(2);

    $this->actingAs($dg)->post(route('pieces.advance', ['note_frais', $piece]))->assertSessionHasNoErrors();
    expect($piece->fresh()?->status)->toBe(PieceStatus::Approuvee)->and($piece->fresh()?->current_step)->toBeNull();

    $events = ValidationEvent::query()->where('subject_id', $piece->id)->orderBy('id')->get();
    expect($events->pluck('action')->all())->toBe(['saisie', 'soumise', 'validee', 'approuvee'])
        ->and($events[2]->user_id)->toBe($chef->id)
        ->and($events[2]->comment)->toBe('Conforme.')
        ->and($events[3]->user_id)->toBe($dg->id);
});

it('refuse de valider une étape à un autre rôle que celui de l\'étape', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    // Le Directeur n'est pas à l'étape 1 : l'étape ne se saute pas.
    $this->actingAs(pieceUser(RoleName::DirectionGenerale))->post(route('pieces.advance', ['note_frais', $piece]))->assertForbidden();
    // Un autre rôle quelconque non plus.
    $this->actingAs(pieceUser(RoleName::Magasinier))->post(route('pieces.advance', ['note_frais', $piece]))->assertForbidden();

    expect($piece->fresh()?->current_step)->toBe(1);
});

it('interdit à l\'auteur de valider sa propre pièce (séparation des tâches)', function () {
    defaultCircuit();
    // L'étape 1 est portée par le rôle de l'auteur : même ainsi, il ne valide pas sa propre pièce.
    ValidationStep::query()->where('position', 1)->update(['role_name' => RoleName::ConducteurTravaux->value]);
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    $this->actingAs($author)->post(route('pieces.advance', ['note_frais', $piece]))->assertForbidden();
    // Un autre porteur du même rôle, lui, peut valider.
    $this->actingAs(pieceUser(RoleName::ConducteurTravaux))->post(route('pieces.advance', ['note_frais', $piece]))->assertSessionHasNoErrors();

    expect($piece->fresh()?->current_step)->toBe(2);
});

it('rejette avec un motif obligatoire, puis laisse l\'auteur corriger et resoumettre', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $chef = pieceUser(RoleName::DirectionTechnique);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    $this->actingAs($chef)->post(route('pieces.reject', ['note_frais', $piece]), ['comment' => ''])->assertSessionHasErrors('comment');
    $this->actingAs($chef)->post(route('pieces.reject', ['note_frais', $piece]), ['comment' => 'Justificatif manquant.'])->assertSessionHasNoErrors();
    expect($piece->fresh()?->status)->toBe(PieceStatus::Rejetee);

    $this->actingAs($author)->put(route('pieces.update', ['note_frais', $piece]), piecePayload($rubrique, ['title' => 'Corrigée']))->assertSessionHasNoErrors();
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]))->assertSessionHasNoErrors();

    expect($piece->fresh()?->title)->toBe('Corrigée')
        ->and($piece->fresh()?->status)->toBe(PieceStatus::EnValidation)
        ->and($piece->fresh()?->current_step)->toBe(1);
});

it('ne modifie plus une pièce soumise ni approuvée', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    $this->actingAs($author)->put(route('pieces.update', ['note_frais', $piece]), piecePayload($rubrique))->assertForbidden();
    $this->actingAs($author)->delete(route('pieces.destroy', ['note_frais', $piece]))->assertForbidden();
});

it('seul l\'auteur modifie, supprime ou soumet sa pièce', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $other = pieceUser(RoleName::ChefChantier);
    $piece = createPiece($author, $rubrique);

    $this->actingAs($other)->put(route('pieces.update', ['note_frais', $piece]), piecePayload($rubrique))->assertForbidden();
    $this->actingAs($other)->post(route('pieces.submit', ['note_frais', $piece]))->assertForbidden();
    $this->actingAs($other)->delete(route('pieces.destroy', ['note_frais', $piece]))->assertForbidden();
});

it('ne soumet pas sans étape paramétrée', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);

    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]))->assertSessionHasErrors('piece');
});

it('saute une étape désactivée', function () {
    defaultCircuit();
    ValidationStep::query()->where('position', 1)->update(['is_active' => false]);
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    expect($piece->fresh()?->current_step)->toBe(2);

    $this->actingAs(pieceUser(RoleName::DirectionGenerale))->post(route('pieces.advance', ['note_frais', $piece]));
    expect($piece->fresh()?->status)->toBe(PieceStatus::Approuvee);
});

it('notifie les porteurs du rôle de l\'étape à valider, puis l\'auteur à l\'approbation', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $chef = pieceUser(RoleName::DirectionTechnique);
    $dg = pieceUser(RoleName::DirectionGenerale);
    $piece = createPiece($author, $rubrique);

    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));
    expect(DatabaseNotification::query()->where('notifiable_id', $chef->id)->count())->toBe(1);

    $this->actingAs($chef)->post(route('pieces.advance', ['note_frais', $piece]));
    expect(DatabaseNotification::query()->where('notifiable_id', $dg->id)->count())->toBe(1);

    $this->actingAs($dg)->post(route('pieces.advance', ['note_frais', $piece]));
    expect(DatabaseNotification::query()->where('notifiable_id', $author->id)->count())->toBe(1);
});

it('applique le même circuit aux recettes et aux pièces de caisse', function (PieceKind $kind) {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::Finance);
    $this->actingAs($author)->post(route('pieces.store', $kind->value), piecePayload($rubrique))->assertSessionHasNoErrors();
    $piece = Piece::query()->latest('id')->firstOrFail();

    $this->actingAs($author)->post(route('pieces.submit', [$kind->value, $piece]));
    $this->actingAs(pieceUser(RoleName::DirectionTechnique))->post(route('pieces.advance', [$kind->value, $piece]));
    $this->actingAs(pieceUser(RoleName::DirectionGenerale))->post(route('pieces.advance', [$kind->value, $piece]));

    expect($piece->fresh()?->status)->toBe(PieceStatus::Approuvee);
})->with([PieceKind::Recette, PieceKind::Caisse]);

// --- Panneau budget -------------------------------------------------------------

it('calcule le panneau budget côté serveur : budget, consommé + en cours, restant, taux', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);

    $approved = createPiece($author, $rubrique, ['lines' => [['designation' => 'Mission', 'quantity' => 1, 'unit_price' => 400_000, 'rubrique_id' => $rubrique->id]]]);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $approved]));
    $this->actingAs(pieceUser(RoleName::DirectionTechnique))->post(route('pieces.advance', ['note_frais', $approved]));
    $this->actingAs(pieceUser(RoleName::DirectionGenerale))->post(route('pieces.advance', ['note_frais', $approved]));
    createPiece($author, $rubrique, ['lines' => [['designation' => 'Hôtel', 'quantity' => 1, 'unit_price' => 150_000, 'rubrique_id' => $rubrique->id]]]);

    $this->actingAs($author)->getJson(route('pieces.budget', ['note_frais', 'rubrique_id' => $rubrique->id, 'period' => now()->format('Y-m')]))
        ->assertOk()
        ->assertJson([
            'budget' => 1_000_000,
            'consumed' => 400_000,
            'inProgress' => 150_000,
            'used' => 550_000,
            'remaining' => 450_000,
            'rate' => 55.0,
            'overrun' => false,
        ]);
});

it('signale un dépassement de la rubrique', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    createPiece($author, $rubrique, ['lines' => [['designation' => 'Gros achat', 'quantity' => 1, 'unit_price' => 1_200_000, 'rubrique_id' => $rubrique->id]]]);

    $this->actingAs($author)->getJson(route('pieces.budget', ['note_frais', 'rubrique_id' => $rubrique->id, 'period' => now()->format('Y-m')]))
        ->assertJson(['remaining' => -200_000, 'overrun' => true]);
});

it('exclut la pièce en cours de modification du panneau budget (pas de double compte)', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique, ['lines' => [['designation' => 'Mission', 'quantity' => 1, 'unit_price' => 300_000, 'rubrique_id' => $rubrique->id]]]);

    $this->actingAs($author)->getJson(route('pieces.budget', ['note_frais', 'rubrique_id' => $rubrique->id, 'period' => now()->format('Y-m'), 'piece' => $piece->id]))
        ->assertJson(['inProgress' => 0, 'used' => 0]);
});

// --- Paramétrage des étapes (Administration) ------------------------------------

it('paramètre le circuit dans Administration et refuse à un autre profil', function () {
    $admin = pieceUser(RoleName::Administrateur);
    $payload = ['steps' => [
        ['label' => 'Validation du chef de service', 'role_name' => RoleName::DirectionTechnique->value, 'button_label' => 'Chef de service', 'is_active' => true],
        ['label' => 'Approbation du Directeur', 'role_name' => RoleName::DirectionGenerale->value, 'button_label' => 'Directeur', 'is_active' => true],
    ]];

    $this->actingAs(pieceUser(RoleName::Magasinier))->put(route('administration.validation-steps.update'), $payload)->assertForbidden();
    $this->actingAs($admin)->put(route('administration.validation-steps.update'), $payload)->assertSessionHasNoErrors();

    expect(ValidationStep::query()->orderBy('position')->pluck('role_name')->all())->toBe([RoleName::DirectionTechnique->value, RoleName::DirectionGenerale->value]);
});

it('refuse un circuit sans étape active ou avec un rôle inconnu', function () {
    $admin = pieceUser(RoleName::Administrateur);

    $this->actingAs($admin)->put(route('administration.validation-steps.update'), ['steps' => [
        ['label' => 'X', 'role_name' => RoleName::DirectionGenerale->value, 'button_label' => 'X', 'is_active' => false],
    ]])->assertSessionHasErrors('steps');

    $this->actingAs($admin)->put(route('administration.validation-steps.update'), ['steps' => [
        ['label' => 'X', 'role_name' => 'inconnu', 'button_label' => 'X', 'is_active' => true],
    ]])->assertSessionHasErrors('steps.0.role_name');
});

it('refuse de modifier le circuit pendant qu\'une pièce l\'attend', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    $this->actingAs(pieceUser(RoleName::Administrateur))->put(route('administration.validation-steps.update'), ['steps' => [
        ['label' => 'Seule étape', 'role_name' => RoleName::DirectionGenerale->value, 'button_label' => 'Directeur', 'is_active' => true],
    ]])->assertSessionHasErrors('steps');

    expect(ValidationStep::query()->count())->toBe(2);
});

it('affiche l\'écran des étapes de validation à l\'administrateur', function () {
    defaultCircuit();

    $this->actingAs(pieceUser(RoleName::Administrateur))->get(route('administration.validation-steps'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Administration/EtapesValidation', false)->has('steps', 2)->has('roles'));
});

it('l\'administrateur n\'accède pas aux pièces métier (403)', function () {
    [, $rubrique] = pieceContext();

    $this->actingAs(pieceUser(RoleName::Administrateur))->get(route('pieces.index', 'note_frais'))->assertForbidden();
    $this->actingAs(pieceUser(RoleName::Administrateur))->post(route('pieces.store', 'note_frais'), piecePayload($rubrique))->assertForbidden();
});

it('un profil sans droit de saisie ne crée pas de pièce (403)', function () {
    [, $rubrique] = pieceContext();

    // La Direction générale lit et valide, mais ne saisit pas.
    $this->actingAs(pieceUser(RoleName::DirectionGenerale))->post(route('pieces.store', 'note_frais'), piecePayload($rubrique))->assertForbidden();
    // Un type de pièce inconnu répond 404.
    $this->actingAs(pieceUser(RoleName::ConducteurTravaux))->get('/pieces/inconnu')->assertNotFound();
});
