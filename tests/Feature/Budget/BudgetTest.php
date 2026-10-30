<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Administration\Models\Setting;
use App\Domain\Budget\Models\BudgetCommitment;
use App\Domain\Budget\Models\BudgetExpense;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Models\BudgetRevision;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function budgetUser(RoleName $role): User
{
    return userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * Chantier avec conducteur et chef affectés, sans budget.
 *
 * @return array{0: Chantier, 1: User, 2: User}
 */
function emptySite(): array
{
    $chantier = Chantier::factory()->create();
    $conducteur = budgetUser(RoleName::ConducteurTravaux);
    $chef = budgetUser(RoleName::ChefChantier);
    $chantier->team()->attach($conducteur->id, ['role' => Chantier::ROLE_CONDUCTEUR]);
    $chantier->team()->attach($chef->id, ['role' => Chantier::ROLE_CHEF]);

    return [$chantier, $conducteur, $chef];
}

function receipt(string $name = 'ticket.pdf', int $kb = 50): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, 'application/pdf');
}

// ------------------------------------------------------------------ liste et fiche

it('liste les chantiers avec budget et leurs indicateurs (reste à engager = révisé − engagé − réel)', function () {
    [$chantier, $conducteur] = emptySite();
    BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'initial_amount' => 100, 'revised_amount' => 120_000_000, 'committed_amount' => 30_000_000, 'actual_amount' => 20_000_000]);
    Chantier::factory()->create(); // sans budget : absent de la liste

    $this->actingAs($conducteur)->get(route('budget.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Budget/Index')
            ->has('rows', 1)
            ->where('rows.0.revised', 120_000_000)
            ->where('rows.0.remaining', 70_000_000)
            ->where('rows.0.level', 'ok'));
});

it('RG7.2 : un conducteur ne voit que le budget de ses chantiers', function () {
    [$mine, $conducteur] = emptySite();
    [$other] = emptySite();
    BudgetLine::factory()->create(['chantier_id' => $mine->id]);
    BudgetLine::factory()->create(['chantier_id' => $other->id]);

    $this->actingAs($conducteur)->get(route('budget.index'))->assertInertia(fn (Assert $page) => $page->has('rows', 1));
    $this->actingAs($conducteur)->get(route('budget.show', $mine))->assertOk();
    $this->actingAs($conducteur)->get(route('budget.show', $other))->assertForbidden();
});

it('RG10.3 : avertissement à 80 % et alerte critique à 100 % du budget révisé consommé, seuils paramétrables', function () {
    $this->seed(SettingsSeeder::class);
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'revised_amount' => 100_000_000, 'committed_amount' => 50_000_000, 'actual_amount' => 29_000_000]);

    $level = function () use ($conducteur, $chantier): string {
        $level = '';
        test()->actingAs($conducteur)->get(route('budget.show', $chantier))
            ->assertInertia(function (Assert $page) use (&$level) {
                $level = $page->toArray()['props']['totals']['level'];
            });

        return $level;
    };

    expect($level())->toBe('ok');
    $line->update(['actual_amount' => 30_000_000]);
    expect($level())->toBe('warning');
    $line->update(['actual_amount' => 50_000_000]);
    expect($level())->toBe('critical');

    // Seuils paramétrés (RG1.3) : avertissement à 90 %, critique à 120 %.
    Setting::query()->where('key', 'budget.warning_percent')->update(['value' => '90']);
    Setting::query()->where('key', 'budget.critical_percent')->update(['value' => '120']);
    Cache::forget('erp.settings');
    expect($level())->toBe('warning');
});

it('CA10 : la fiche détaille par poste initial, révisé, engagé, réel, reste et les totaux égalent la somme des opérations', function () {
    [$chantier, $conducteur, $chef] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'post' => 'Gros œuvre', 'initial_amount' => 100_000_000, 'revised_amount' => 100_000_000]);
    $article = Article::factory()->create(['price' => 10_000]);

    // Deux demandes validées (engagements) et une dépense diverse (coût réel).
    foreach ([100, 250] as $quantity) {
        $request = PurchaseRequest::factory()->withStatus('en_attente')->create(['chantier_id' => $chantier->id, 'requester_id' => $chef->id]);
        $request->lines()->create(['article_id' => $article->id, 'quantity' => $quantity, 'need_date' => '2026-12-01', 'urgency' => 'normale']);
        test()->actingAs($conducteur)->post(route('achats.demandes.validate', $request))->assertSessionHasNoErrors();
    }

    Storage::fake('public');
    $this->actingAs($conducteur)->post(route('budget.expenses.store', $chantier), ['label' => 'Transport sable', 'budget_line_id' => $line->id, 'amount' => 75_000, 'receipt' => receipt()])
        ->assertSessionHasNoErrors();

    $line->refresh();
    expect($line->committed_amount)->toBe((int) BudgetCommitment::query()->where('budget_line_id', $line->id)->sum('amount'))
        ->and($line->committed_amount)->toBe(3_500_000)
        ->and($line->actual_amount)->toBe((int) BudgetExpense::query()->where('budget_line_id', $line->id)->sum('amount'));

    $this->actingAs($conducteur)->get(route('budget.show', $chantier))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lines.0.initial', 100_000_000)
            ->where('lines.0.committed', 3_500_000)
            ->where('lines.0.actual', 75_000)
            ->where('totals.committed', 3_500_000)
            ->where('totals.actual', 75_000)
            ->where('totals.remaining', 100_000_000 - 3_500_000 - 75_000));
});

// ------------------------------------------------------------------ postes du déboursé sec

it('crée un poste du déboursé sec : budget révisé égal au budget initial, puis chantier prêt à démarrer (RG4.2)', function () {
    [$chantier, $conducteur] = emptySite();
    $project = $chantier->project;
    $project->forceFill(['status' => 'preparation'])->saveQuietly();
    $director = budgetUser(RoleName::DirectionTechnique);

    $this->actingAs($director)->post(route('projets.advance', $project))->assertSessionHasErrors('status'); // pas de budget

    $this->actingAs($conducteur)->post(route('budget.lines.store', $chantier), ['post' => 'Gros œuvre', 'nature' => 'materiaux', 'amount' => 120_000_000])->assertSessionHasNoErrors();

    $line = BudgetLine::query()->firstOrFail();
    expect($line->initial_amount)->toBe(120_000_000)->and($line->revised_amount)->toBe(120_000_000);

    $this->actingAs($director)->post(route('projets.advance', $project))->assertSessionHasNoErrors();
    expect($project->refresh()->status->getValue())->toBe('en_cours');
});

it('valide les postes et refuse un doublon', function () {
    [$chantier, $conducteur] = emptySite();
    BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'post' => 'Gros œuvre', 'nature' => 'materiaux']);

    foreach ([
        ['post' => 'Gros œuvre', 'nature' => 'materiaux', 'amount' => 1_000],
        ['post' => 'ab', 'nature' => 'materiaux', 'amount' => 1_000],
        ['post' => 'Second œuvre', 'nature' => 'bijoux', 'amount' => 1_000],
        ['post' => 'Second œuvre', 'nature' => 'materiaux', 'amount' => 0],
        ['post' => 'Second œuvre', 'nature' => 'materiaux', 'amount' => 12.5],
    ] as $payload) {
        $this->actingAs($conducteur)->post(route('budget.lines.store', $chantier), $payload)->assertSessionHasErrors();
    }

    expect(BudgetLine::query()->count())->toBe(1);
});

it('réserve la structure du budget aux profils qui écrivent sur le chantier, hors chef de chantier', function (RoleName $role) {
    [$chantier] = emptySite();
    $user = budgetUser($role);

    $this->actingAs($user)->post(route('budget.lines.store', $chantier), ['post' => 'Gros œuvre', 'nature' => 'materiaux', 'amount' => 1_000_000])->assertForbidden();
    expect(BudgetLine::query()->count())->toBe(0);
})->with([RoleName::ServiceAchatsBtp, RoleName::Comptabilite, RoleName::Finance, RoleName::DirectionGenerale]);

it('un chef de chantier ne structure pas le budget et un conducteur non affecté non plus', function () {
    [$chantier, , $chef] = emptySite();
    $stranger = budgetUser(RoleName::ConducteurTravaux);

    foreach ([$chef, $stranger] as $user) {
        $this->actingAs($user)->post(route('budget.lines.store', $chantier), ['post' => 'Gros œuvre', 'nature' => 'materiaux', 'amount' => 1_000_000])->assertForbidden();
    }
    expect(BudgetLine::query()->count())->toBe(0);
});

it('RG4.3 : le budget d\'un chantier en lecture seule ne se modifie plus', function () {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id]);
    $chantier->project->forceFill(['status' => 'cloture'])->saveQuietly();

    $this->actingAs($conducteur)->post(route('budget.lines.store', $chantier), ['post' => 'Second œuvre', 'nature' => 'materiaux', 'amount' => 1_000_000])->assertForbidden();
    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => 1_000_000, 'reason' => 'Révision après clôture'])->assertForbidden();
    $this->actingAs($conducteur)->post(route('budget.expenses.store', $chantier), ['label' => 'Ticket', 'budget_line_id' => $line->id, 'amount' => 1_000, 'receipt' => receipt()])->assertForbidden();
    $this->actingAs($conducteur)->get(route('budget.show', $chantier))->assertOk();
});

// ------------------------------------------------------------------ révisions (RG10.2)

it('RG10.2 : une révision demandée ne change rien tant que la Direction ne l\'a pas validée', function () {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'revised_amount' => 50_000_000]);
    $dg = budgetUser(RoleName::DirectionGenerale);

    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => 8_000_000, 'reason' => 'Avenant n°1 : voile de contreventement.'])
        ->assertSessionHasNoErrors();

    $revision = BudgetRevision::query()->firstOrFail();
    expect($revision->status)->toBe('en_attente')->and($revision->requested_by)->toBe($conducteur->id)->and($line->refresh()->revised_amount)->toBe(50_000_000);

    $this->actingAs($dg)->post(route('budget.revisions.validate', $revision))->assertSessionHasNoErrors();

    $revision->refresh();
    expect($revision->status)->toBe('validee')->and($revision->decided_by)->toBe($dg->id)->and($line->refresh()->revised_amount)->toBe(58_000_000);
});

it('RG10.2 : une révision à la baisse est possible mais ne rend jamais le budget négatif', function () {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'revised_amount' => 10_000_000]);
    $dg = budgetUser(RoleName::DirectionGenerale);

    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => -4_000_000, 'reason' => 'Réduction du périmètre du lot.']);
    $this->actingAs($dg)->post(route('budget.revisions.validate', BudgetRevision::query()->firstOrFail()))->assertSessionHasNoErrors();
    expect($line->refresh()->revised_amount)->toBe(6_000_000);

    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => -9_000_000, 'reason' => 'Réduction excessive du lot.']);
    $this->actingAs($dg)->post(route('budget.revisions.validate', BudgetRevision::query()->latest('id')->firstOrFail()))->assertSessionHasErrors('amount');
    expect($line->refresh()->revised_amount)->toBe(6_000_000);
});

it('RG10.2 : la demande exige un motif, un montant non nul et un poste du chantier', function (array $override, string $field) {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id]);
    $foreign = BudgetLine::factory()->create();
    $payload = $override + ['budget_line_id' => $line->id, 'amount' => 1_000_000, 'reason' => 'Ajout d\'un voile de contreventement.'];
    if ($override['budget_line_id'] ?? null === 'foreign') {
        $payload['budget_line_id'] = $foreign->id;
    }

    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), $payload)->assertSessionHasErrors($field);
    expect(BudgetRevision::query()->count())->toBe(0);
})->with([
    'motif trop court' => [['reason' => 'court'], 'reason'],
    'montant nul' => [['amount' => 0], 'amount'],
    'montant décimal' => [['amount' => 10.5], 'amount'],
    'poste d\'un autre chantier' => [['budget_line_id' => 'foreign'], 'budget_line_id'],
]);

it('RG10.2 : la décision est réservée à la Direction, jamais à l\'auteur, et ne se prend qu\'une fois', function () {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'revised_amount' => 10_000_000]);
    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => 1_000_000, 'reason' => 'Ajustement après métré.']);
    $revision = BudgetRevision::query()->firstOrFail();

    foreach ([$conducteur, budgetUser(RoleName::DirectionTechnique), budgetUser(RoleName::ServiceAchatsBtp)] as $user) {
        $this->actingAs($user)->post(route('budget.revisions.validate', $revision))->assertForbidden();
    }
    expect($revision->refresh()->status)->toBe('en_attente');

    $dg = budgetUser(RoleName::DirectionGenerale);
    $this->actingAs($dg)->post(route('budget.revisions.validate', $revision))->assertSessionHasNoErrors();
    $this->actingAs($dg)->post(route('budget.revisions.validate', $revision))->assertForbidden();
    expect($line->refresh()->revised_amount)->toBe(11_000_000);
});

it('RG10.2 : le rejet exige un motif et laisse le budget intact', function () {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'revised_amount' => 10_000_000]);
    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => 1_000_000, 'reason' => 'Ajustement après métré.']);
    $revision = BudgetRevision::query()->firstOrFail();
    $dg = budgetUser(RoleName::DirectionGenerale);

    $this->actingAs($dg)->post(route('budget.revisions.reject', $revision), [])->assertSessionHasErrors('reason');
    $this->actingAs($dg)->post(route('budget.revisions.reject', $revision), ['reason' => 'Non justifié par le marché'])->assertSessionHasNoErrors();

    $revision->refresh();
    expect($revision->status)->toBe('rejetee')->and($revision->decision_reason)->toBe('Non justifié par le marché')->and($line->refresh()->revised_amount)->toBe(10_000_000);
});

it('affiche l\'historique des révisions avec auteur et validation, et indique qui peut décider', function () {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'post' => 'Gros œuvre']);
    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => 2_000_000, 'reason' => 'Heures supplémentaires du plancher.']);
    $dg = budgetUser(RoleName::DirectionGenerale);

    $this->actingAs($dg)->get(route('budget.show', $chantier))
        ->assertInertia(fn (Assert $page) => $page
            ->where('revisions.0.post', 'Gros œuvre')
            ->where('revisions.0.author', $conducteur->name)
            ->where('revisions.0.status', 'en_attente')
            ->where('revisions.0.canDecide', true)
            ->where('canManage', false));
    $this->actingAs($conducteur)->get(route('budget.show', $chantier))
        ->assertInertia(fn (Assert $page) => $page->where('revisions.0.canDecide', false)->where('canManage', true));
});

it('trace les révisions au journal des activités', function () {
    [$chantier, $conducteur] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'revised_amount' => 10_000_000]);
    $this->actingAs($conducteur)->post(route('budget.revisions.store', $chantier), ['budget_line_id' => $line->id, 'amount' => 1_000_000, 'reason' => 'Ajustement après métré.']);
    $this->actingAs(budgetUser(RoleName::DirectionGenerale))->post(route('budget.revisions.validate', BudgetRevision::query()->firstOrFail()));

    expect(Activity::query()->where('subject_type', BudgetRevision::class)->where('event', 'created')->exists())->toBeTrue()
        ->and(Activity::query()->where('subject_type', BudgetLine::class)->where('event', 'updated')->latest('id')->first()?->attribute_changes?->get('attributes'))->toMatchArray(['revised_amount' => 11_000_000]);
});

// ------------------------------------------------------------------ dépenses diverses (RG10.4)

it('RG10.4 : une dépense diverse avec justificatif est imputée au poste et s\'ajoute au coût réel', function () {
    Storage::fake('public');
    [$chantier, , $chef] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'actual_amount' => 1_000_000]);

    $this->actingAs($chef)->post(route('budget.expenses.store', $chantier), ['label' => 'Transport de sable', 'budget_line_id' => $line->id, 'amount' => 85_000, 'receipt' => receipt('facture.pdf')])
        ->assertSessionHasNoErrors();

    $expense = BudgetExpense::query()->firstOrFail();
    expect($line->refresh()->actual_amount)->toBe(1_085_000)
        ->and($expense->amount)->toBe(85_000)
        ->and($expense->user_id)->toBe($chef->id)
        ->and($expense->getFirstMedia('receipts')?->file_name)->toBe('facture.pdf');

    $this->actingAs($chef)->get(route('budget.show', $chantier))
        ->assertInertia(fn (Assert $page) => $page->where('expenses.0.label', 'Transport de sable')->where('expenses.0.receipt', 'facture.pdf')->where('expenses.0.author', $chef->name)->where('canExpense', true));
});

it('RG10.4 : refuse une dépense sans justificatif, avec un justificatif invalide ou sur un autre chantier', function (array $override, string $field) {
    Storage::fake('public');
    [$chantier, , $chef] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id]);
    $foreign = BudgetLine::factory()->create();
    $payload = $override + ['label' => 'Ticket de péage', 'budget_line_id' => $line->id, 'amount' => 5_000, 'receipt' => receipt()];
    if (($override['budget_line_id'] ?? null) === 'foreign') {
        $payload['budget_line_id'] = $foreign->id;
    }
    if (array_key_exists('receipt', $override) && $override['receipt'] === null) {
        unset($payload['receipt']);
    }

    $response = $this->actingAs($chef)->post(route('budget.expenses.store', $chantier), $payload);
    $response->assertSessionHasErrors($field);
    expect(BudgetExpense::query()->count())->toBe(0)->and($line->refresh()->actual_amount)->toBe(0);
})->with([
    'sans justificatif' => [['receipt' => null], 'receipt'],
    'justificatif exécutable' => [['receipt' => UploadedFile::fake()->create('virus.exe', 10)], 'receipt'],
    'justificatif trop lourd' => [['receipt' => UploadedFile::fake()->create('scan.pdf', 21_000, 'application/pdf')], 'receipt'],
    'montant décimal' => [['amount' => 10.5], 'amount'],
    'montant négatif' => [['amount' => -5], 'amount'],
    'poste d\'un autre chantier' => [['budget_line_id' => 'foreign'], 'budget_line_id'],
]);

it('refuse la saisie d\'une dépense aux profils sans droit d\'écriture sur le chantier', function (RoleName $role) {
    [$chantier] = emptySite();
    $line = BudgetLine::factory()->create(['chantier_id' => $chantier->id]);

    $this->actingAs(budgetUser($role))->post(route('budget.expenses.store', $chantier), ['label' => 'Ticket', 'budget_line_id' => $line->id, 'amount' => 1_000, 'receipt' => receipt()])->assertForbidden();
    expect(BudgetExpense::query()->count())->toBe(0);
})->with([RoleName::Comptabilite, RoleName::Finance, RoleName::DirectionGenerale, RoleName::ServiceAchatsBtp]);

it('refuse le budget aux profils sans accès au module (403)', function () {
    [$chantier] = emptySite();

    $this->actingAs(budgetUser(RoleName::RessourcesHumaines))->get(route('budget.index'))->assertForbidden();
    $this->actingAs(budgetUser(RoleName::RessourcesHumaines))->get(route('budget.show', $chantier))->assertForbidden();
});

it('s\'appuie sur des projets et chantiers cohérents', function () {
    [$chantier] = emptySite();

    expect($chantier->project)->toBeInstanceOf(Project::class);
});
