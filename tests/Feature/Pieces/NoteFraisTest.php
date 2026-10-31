<?php

declare(strict_types=1);

use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Models\ValidationEvent;
use App\Domain\Pieces\Models\ValidationStep;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// Notes de frais : saisie, validation, facture de remboursement et suivi workflow.

/** Note de frais approuvée par tout le circuit. */
function approvedNote(User $author, Rubrique $rubrique): Piece
{
    $piece = createPiece($author, $rubrique);
    test()->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));
    test()->actingAs(pieceUser(RoleName::DirectionTechnique))->post(route('pieces.advance', ['note_frais', $piece]));
    test()->actingAs(pieceUser(RoleName::DirectionGenerale))->post(route('pieces.advance', ['note_frais', $piece]));

    return $piece->fresh() ?? $piece;
}

it('liste les notes de frais avec la ligne d\'état du circuit', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    $this->actingAs($author)->get(route('pieces.index', 'note_frais'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pieces/Index', false)
            ->where('mode', 'saisie')
            ->has('pieces', 1)
            ->where('waiting.0.count', 1)
            ->where('waiting.1.count', 0)
            ->where('can.create', true));
});

it('la page de validation ne montre que les pièces que le compte peut valider à son étape', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));

    // Étape 1 : le chef de service la voit, le Directeur pas encore.
    $this->actingAs(pieceUser(RoleName::DirectionTechnique))->get(route('pieces.validation', 'note_frais'))
        ->assertInertia(fn (Assert $page) => $page->component('Pieces/Index', false)->has('pieces', 1)->where('pieces.0.can.decide', true));
    $this->actingAs(pieceUser(RoleName::DirectionGenerale))->get(route('pieces.validation', 'note_frais'))
        ->assertInertia(fn (Assert $page) => $page->has('pieces', 0));
    // L'auteur ne valide pas sa propre pièce : elle n'apparaît pas dans sa liste de validation.
    $this->actingAs($author)->get(route('pieces.validation', 'note_frais'))
        ->assertInertia(fn (Assert $page) => $page->has('pieces', 0));
});

it('un porteur d\'un rôle du circuit sans lecture du module ne voit que les pièces en validation et les siennes', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $draft = createPiece($author, $rubrique);
    $submitted = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $submitted]));
    // Rôle de l'étape 1 donné à la Finance (qui a la lecture) → on utilise le Magasinier, sans droit de validation.
    ValidationStep::query()->where('position', 1)->update(['role_name' => RoleName::RessourcesHumaines->value]);
    $rh = pieceUser(RoleName::RessourcesHumaines);

    $this->actingAs($rh)->get(route('pieces.index', 'note_frais'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('pieces', 1)->where('pieces.0.id', $submitted->id));

    expect($draft->status)->toBe(PieceStatus::Saisie);
});

it('un profil sans lien avec le circuit ni droit d\'achats ne voit pas les notes de frais (403)', function () {
    defaultCircuit();

    $this->actingAs(pieceUser(RoleName::RessourcesHumaines))->get(route('pieces.index', 'note_frais'))->assertForbidden();
});

it('rembourse une note de frais approuvée : facture numérotée, trace et alerte à l\'auteur', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = approvedNote($author, $rubrique);
    $compta = pieceUser(RoleName::Comptabilite);

    $this->actingAs($compta)->post(route('pieces.reimburse', ['note_frais', $piece]))->assertSessionHasNoErrors();

    $piece->refresh();
    expect($piece->invoice_number)->toStartWith('FN-'.now()->year.'-')
        ->and($piece->reimbursed_by)->toBe($compta->id)
        ->and($piece->reimbursed_at)->not->toBeNull()
        ->and(ValidationEvent::query()->where('subject_id', $piece->id)->latest('id')->first()?->action)->toBe('remboursee')
        ->and(DatabaseNotification::query()->where('notifiable_id', $author->id)->latest()->first()?->data['title'] ?? null)->toBe('Note de frais remboursée');
});

it('ne rembourse qu\'une fois, et seulement une note approuvée', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $compta = pieceUser(RoleName::Comptabilite);
    $draft = createPiece($author, $rubrique);

    $this->actingAs($compta)->post(route('pieces.reimburse', ['note_frais', $draft]))->assertForbidden();

    $piece = approvedNote($author, $rubrique);
    $this->actingAs($compta)->post(route('pieces.reimburse', ['note_frais', $piece]))->assertSessionHasNoErrors();
    $this->actingAs($compta)->post(route('pieces.reimburse', ['note_frais', $piece]))->assertForbidden();

    expect(Piece::query()->whereNotNull('invoice_number')->count())->toBe(1);
});

it('refuse le remboursement à un profil sans droit d\'écriture sur la Finance', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = approvedNote($author, $rubrique);

    $this->actingAs($author)->post(route('pieces.reimburse', ['note_frais', $piece]))->assertForbidden();
    $this->actingAs(pieceUser(RoleName::DirectionGenerale))->post(route('pieces.reimburse', ['note_frais', $piece]))->assertForbidden();

    expect($piece->fresh()?->invoice_number)->toBeNull();
});

it('affiche les remboursements à la Comptabilité et les refuse aux autres', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    approvedNote($author, $rubrique);

    $this->actingAs(pieceUser(RoleName::Comptabilite))->get(route('pieces.remboursements', 'note_frais'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('mode', 'remboursements')->has('pieces', 1)->where('pieces.0.can.reimburse', true));
    $this->actingAs($author)->get(route('pieces.remboursements', 'note_frais'))->assertForbidden();
    // Les remboursements ne concernent que les notes de frais.
    $this->actingAs(pieceUser(RoleName::Comptabilite))->get(route('pieces.remboursements', 'recette'))->assertNotFound();
});

it('expose le suivi workflow : qui a fait quoi, quand, avec quel commentaire', function () {
    defaultCircuit();
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    $piece = createPiece($author, $rubrique);
    $this->actingAs($author)->post(route('pieces.submit', ['note_frais', $piece]));
    $chef = pieceUser(RoleName::DirectionTechnique);
    $this->actingAs($chef)->post(route('pieces.advance', ['note_frais', $piece]), ['comment' => 'Vu.']);

    $this->actingAs($author)->get(route('pieces.suivi', 'note_frais'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pieces/Suivi', false)
            ->has('pieces', 1)
            ->where('pieces.0.events.0.action', 'saisie')
            ->where('pieces.0.events.1.action', 'soumise')
            ->where('pieces.0.events.2.action', 'validee')
            ->where('pieces.0.events.2.by', $chef->name)
            ->where('pieces.0.events.2.comment', 'Vu.')
            ->has('steps', 2));
});

it('joint un justificatif à la note de frais', function () {
    [, $rubrique] = pieceContext();
    $author = pieceUser(RoleName::ConducteurTravaux);
    Storage::fake('public');

    $payload = piecePayload($rubrique);
    $payload['proof'] = UploadedFile::fake()->create('recu-mission.pdf', 120, 'application/pdf');
    $this->actingAs($author)->post(route('pieces.store', 'note_frais'), $payload)->assertSessionHasNoErrors();

    expect(Piece::query()->firstOrFail()->getFirstMedia('justificatifs')?->file_name)->toBe('recu-mission.pdf');
});
