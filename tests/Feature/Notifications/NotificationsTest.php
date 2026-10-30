<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\Task;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Notifications\AlertDispatcher;
use App\Domain\Shared\Notifications\AppAlert;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function alertUser(RoleName $role, array $attributes = []): User
{
    return userWithRole($role, $attributes + [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * Chantier avec un conducteur et un chef affectés, et un poste de budget.
 *
 * @return array{0: Chantier, 1: User, 2: User}
 */
function alertSite(int $revised = 100_000_000): array
{
    $chantier = Chantier::factory()->create(['status' => 'en_cours']);
    $conducteur = alertUser(RoleName::ConducteurTravaux);
    $chef = alertUser(RoleName::ChefChantier);
    $chantier->team()->attach($conducteur->id, ['role' => Chantier::ROLE_CONDUCTEUR]);
    $chantier->team()->attach($chef->id, ['role' => Chantier::ROLE_CHEF]);
    BudgetLine::factory()->create(['chantier_id' => $chantier->id, 'nature' => 'materiaux', 'initial_amount' => $revised, 'revised_amount' => $revised]);

    return [$chantier, $conducteur, $chef];
}

function submitRequest(Chantier $chantier, User $requester, int $price = 6_500, int $quantity = 100): PurchaseRequest
{
    test()->actingAs($requester)->post(route('achats.demandes.store'), [
        'chantier_id' => $chantier->id,
        'justification' => 'Ferraillage du plancher haut R+1.',
        'submit' => true,
        'lines' => [['article_id' => Article::factory()->create(['price' => $price])->id, 'quantity' => $quantity, 'need_date' => now()->addDays(3)->toDateString(), 'urgency' => 'normale']],
    ])->assertSessionHasNoErrors();

    return PurchaseRequest::query()->latest('id')->firstOrFail();
}

// ------------------------------------------------------------------ événements métier

it('notifie le valideur du chantier d\'une nouvelle demande à valider, pas le demandeur ni les autres chantiers', function () {
    [$chantier, $conducteur, $chef] = alertSite();
    [, $otherConducteur] = alertSite();

    $request = submitRequest($chantier, $chef);

    $notification = $conducteur->notifications()->firstOrFail();
    expect($notification->data)->toMatchArray([
        'kind' => 'demande',
        'title' => 'Nouvelle demande à valider',
        'href' => route('achats.demandes.show', $request, absolute: false),
    ])->and($notification->data['body'])->toContain($request->number, '650 000 FCFA')
        ->and($chef->notifications()->count())->toBe(0)
        ->and($otherConducteur->notifications()->count())->toBe(0);
});

it('prévient aussi la Direction générale quand la demande dépasse le budget ou le seuil (RG8.7)', function () {
    [$chantier, $conducteur, $chef] = alertSite();
    $dg = alertUser(RoleName::DirectionGenerale);

    submitRequest($chantier, $chef, 100_000, 60); // 6 M > seuil

    expect($conducteur->notifications()->count())->toBe(1)->and($dg->notifications()->count())->toBe(1);
});

it('informe le demandeur de la décision : validée, refusée ou renvoyée', function (string $route, array $payload, string $title, string $fragment) {
    [$chantier, $conducteur, $chef] = alertSite();
    $request = submitRequest($chantier, $chef);

    $this->actingAs($conducteur)->post(route($route, $request), $payload)->assertSessionHasNoErrors();

    $notification = $chef->notifications()->latest()->firstOrFail();
    expect($notification->data['kind'])->toBe('decision')
        ->and($notification->data['title'])->toBe($title)
        ->and($notification->data['body'])->toContain($request->number, $fragment, $conducteur->name);
})->with([
    'validée' => ['achats.demandes.validate', [], 'Demande validée', 'prix figés'],
    'refusée' => ['achats.demandes.refuse', ['reason' => 'Déjà au magasin'], 'Demande refusée', 'Déjà au magasin'],
    'renvoyée' => ['achats.demandes.return', ['reason' => 'Revoir le métré'], 'Demande renvoyée pour correction', 'Revoir le métré'],
]);

// ------------------------------------------------------------------ canaux paramétrables

it('envoie sur les canaux choisis par chaque utilisateur', function (bool $app, bool $email, array $expected) {
    Notification::fake();
    $user = User::factory()->create(['notify_app' => $app, 'notify_email' => $email]);

    app(AlertDispatcher::class)->send([$user], 'demande', 'Titre', 'Corps', '/achats/demandes');

    Notification::assertSentTo($user, AppAlert::class, fn (AppAlert $n, array $channels): bool => $channels === $expected);
})->with([
    'les deux canaux' => [true, true, ['database', 'mail']],
    'application seule' => [true, false, ['database']],
    'e-mail seul' => [false, true, ['mail']],
]);

it('n\'écrit rien pour un utilisateur qui a coupé tous les canaux, ni pour un compte désactivé', function () {
    Notification::fake();
    $silent = User::factory()->create(['notify_app' => false, 'notify_email' => false]);
    $inactive = User::factory()->create(['is_active' => false]);

    app(AlertDispatcher::class)->send([$silent, $inactive], 'demande', 'Titre', 'Corps', '/x');

    Notification::assertNothingSentTo($inactive);
    Notification::assertNothingSentTo($silent);
});

it('l\'e-mail reprend le titre, le message et un lien vers l\'ERP', function () {
    $user = User::factory()->create();
    $mail = (new AppAlert('budget', 'Alerte budgétaire critique', 'Palmiers : 101 % consommé.', '/budget/1'))->toMail($user);

    expect($mail->subject)->toBe('Alerte budgétaire critique')
        ->and($mail->introLines)->toContain('Palmiers : 101 % consommé.')
        ->and($mail->actionUrl)->toBe(url('/budget/1'));
});

// ------------------------------------------------------------------ une alerte par jour et par objet

it('n\'envoie pas deux fois la même alerte le même jour, mais la renvoie le lendemain', function () {
    Carbon::setTestNow('2026-11-10 07:00');
    $user = User::factory()->create();
    $dispatcher = app(AlertDispatcher::class);

    expect($dispatcher->send([$user], 'budget', 'T', 'C', '/b', 'budget:1'))->toBe(1)
        ->and($dispatcher->send([$user], 'budget', 'T', 'C', '/b', 'budget:1'))->toBe(0)
        ->and($dispatcher->send([$user], 'budget', 'T', 'C', '/b', 'budget:2'))->toBe(1);

    Carbon::setTestNow('2026-11-11 07:00');
    expect($dispatcher->send([$user], 'budget', 'T', 'C', '/b', 'budget:1'))->toBe(1)
        ->and($user->notifications()->count())->toBe(3);
});

it('un échec d\'envoi ne bloque jamais l\'action métier', function () {
    $user = new class extends User
    {
        public function notify($instance): void
        {
            throw new RuntimeException('messagerie indisponible');
        }
    };
    $user->forceFill(['id' => 1, 'is_active' => true]);

    expect(app(AlertDispatcher::class)->send([$user], 'demande', 'T', 'C', '/x'))->toBe(0);
});

// ------------------------------------------------------------------ tâche planifiée quotidienne

it('RG7.6 / RG7.7 : alerte le chef et le conducteur d\'un chantier dont des tâches sont en alerte, une fois par jour', function () {
    $this->seed(SettingsSeeder::class);
    Carbon::setTestNow('2026-11-20 07:00');
    [$chantier, $conducteur, $chef] = alertSite();
    Task::factory()->create(['chantier_id' => $chantier->id, 'name' => 'Gros œuvre RDC', 'start_date' => '2026-10-01', 'end_date' => '2026-11-10', 'progress' => 90, 'last_update_on' => '2026-11-19']); // en retard
    Task::factory()->create(['chantier_id' => $chantier->id, 'name' => 'Plancher haut', 'start_date' => '2026-11-01', 'end_date' => '2026-12-10', 'progress' => 5, 'last_update_on' => '2026-11-19']); // à risque
    Task::factory()->create(['chantier_id' => $chantier->id, 'name' => 'Électricité', 'start_date' => '2026-12-01', 'end_date' => '2027-01-10', 'progress' => 0, 'last_update_on' => '2026-11-19']); // à venir, aucune alerte

    $this->artisan('erp:send-alerts')->assertSuccessful();

    foreach ([$chef, $conducteur] as $user) {
        $n = $user->notifications()->firstOrFail();
        expect($n->data['kind'])->toBe('planning')
            ->and($n->data['title'])->toContain('2 tâche(s) en alerte', $chantier->name)
            ->and($n->data['body'])->toContain('Gros œuvre RDC (en retard)', 'Plancher haut (à risque)')
            ->and($n->data['body'])->not->toContain('Électricité');
    }

    $this->artisan('erp:send-alerts')->assertSuccessful();
    expect($chef->notifications()->count())->toBe(1);

    Carbon::setTestNow('2026-11-21 07:00');
    $this->artisan('erp:send-alerts')->assertSuccessful();
    expect($chef->notifications()->count())->toBe(2);
});

it('RG7.7 : signale une tâche non renseignée depuis plus de 7 jours', function () {
    $this->seed(SettingsSeeder::class);
    Carbon::setTestNow('2026-11-20 07:00');
    [$chantier, , $chef] = alertSite();
    Task::factory()->create(['chantier_id' => $chantier->id, 'name' => 'Maçonnerie', 'start_date' => '2026-11-01', 'end_date' => '2026-12-20', 'progress' => 35, 'last_update_on' => '2026-11-10']);

    $this->artisan('erp:send-alerts')->assertSuccessful();

    expect($chef->notifications()->firstOrFail()->data['body'])->toContain('Maçonnerie (non renseignée)');
});

it('n\'alerte pas les chantiers clôturés, suspendus ou sans tâche en alerte', function () {
    $this->seed(SettingsSeeder::class);
    Carbon::setTestNow('2026-11-20 07:00');
    [$closed, , $chefClosed] = alertSite();
    $closed->update(['status' => 'cloture']);
    [$suspended, , $chefSuspended] = alertSite();
    $suspended->update(['status' => 'suspendu']);
    [$healthy, , $chefHealthy] = alertSite();
    foreach ([$closed, $suspended] as $chantier) {
        Task::factory()->create(['chantier_id' => $chantier->id, 'start_date' => '2026-10-01', 'end_date' => '2026-11-01', 'progress' => 10]);
    }
    Task::factory()->create(['chantier_id' => $healthy->id, 'start_date' => '2026-11-01', 'end_date' => '2026-12-10', 'progress' => 60, 'last_update_on' => '2026-11-19']);

    $this->artisan('erp:send-alerts')->assertSuccessful();

    expect($chefClosed->notifications()->count() + $chefSuspended->notifications()->count() + $chefHealthy->notifications()->count())->toBe(0);
});

it('RG10.3 : alerte les conducteurs du chantier et la Direction technique aux seuils de budget', function (int $actual, ?string $title) {
    $this->seed(SettingsSeeder::class);
    [$chantier, $conducteur] = alertSite(100_000_000);
    BudgetLine::query()->where('chantier_id', $chantier->id)->update(['committed_amount' => 20_000_000, 'actual_amount' => $actual]);
    $direction = alertUser(RoleName::DirectionTechnique);
    $bystander = alertUser(RoleName::ServiceAchatsBtp);

    $this->artisan('erp:send-alerts')->assertSuccessful();

    if ($title === null) {
        expect($conducteur->notifications()->count())->toBe(0)->and($direction->notifications()->count())->toBe(0);

        return;
    }

    foreach ([$conducteur, $direction] as $user) {
        $n = $user->notifications()->firstOrFail();
        expect($n->data['kind'])->toBe('budget')->and($n->data['title'])->toBe($title)->and($n->data['href'])->toBe(route('budget.show', $chantier, absolute: false));
    }
    expect($bystander->notifications()->count())->toBe(0);
})->with([
    'sous le seuil' => [50_000_000, null],
    'avertissement à 80 %' => [60_000_000, 'Avertissement budgétaire'],
    'alerte critique à 100 %' => [80_000_000, 'Alerte budgétaire critique'],
]);

it('envoie l\'alerte de budget une seule fois par jour', function () {
    $this->seed(SettingsSeeder::class);
    [$chantier, $conducteur] = alertSite(100_000_000);
    BudgetLine::query()->where('chantier_id', $chantier->id)->update(['committed_amount' => 90_000_000]);

    $this->artisan('erp:send-alerts');
    $this->artisan('erp:send-alerts');

    expect($conducteur->notifications()->count())->toBe(1);
});

it('planifie la commande des alertes chaque jour à 7 h', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'erp:send-alerts'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 7 * * *');
});

// ------------------------------------------------------------------ centre de notifications

it('affiche au centre de notifications seulement les notifications de l\'utilisateur, les plus récentes d\'abord', function () {
    $user = alertUser(RoleName::ChefChantier);
    $other = alertUser(RoleName::ChefChantier);
    $user->notify(new AppAlert('demande', 'Première', 'a', '/a'));
    sleep(1);
    $user->notify(new AppAlert('budget', 'Seconde', 'b', '/b'));
    $other->notify(new AppAlert('demande', 'Autre', 'c', '/c'));

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications', 2)
            ->where('notifications.0.title', 'Seconde')
            ->where('notifications.0.kind', 'budget')
            ->where('notifications.0.read', false)
            ->where('notifications.0.href', '/b')
            ->where('channels', ['app' => true, 'email' => true]));
});

it('partage le nombre de notifications non lues pour la pastille du menu', function () {
    $user = alertUser(RoleName::ChefChantier);
    $user->notify(new AppAlert('demande', 'A', 'a', '/a'));
    $user->notify(new AppAlert('demande', 'B', 'b', '/b'));
    $user->notifications()->first()?->markAsRead();

    $this->actingAs($user)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 1));
});

it('marque une notification comme lue, la sienne seulement', function () {
    $user = alertUser(RoleName::ChefChantier);
    $other = alertUser(RoleName::ChefChantier);
    $user->notify(new AppAlert('demande', 'A', 'a', '/a'));
    $other->notify(new AppAlert('demande', 'B', 'b', '/b'));
    $mine = $user->notifications()->firstOrFail();
    $theirs = $other->notifications()->firstOrFail();

    $this->actingAs($user)->post(route('notifications.read', $theirs->id))->assertNotFound();
    expect($theirs->refresh()->read_at)->toBeNull();

    $this->actingAs($user)->post(route('notifications.read', $mine->id))->assertRedirect();
    expect($mine->refresh()->read_at)->not->toBeNull();
});

it('marque toutes ses notifications comme lues sans toucher à celles des autres', function () {
    $user = alertUser(RoleName::ChefChantier);
    $other = alertUser(RoleName::ChefChantier);
    $user->notify(new AppAlert('demande', 'A', 'a', '/a'));
    $user->notify(new AppAlert('demande', 'B', 'b', '/b'));
    $other->notify(new AppAlert('demande', 'C', 'c', '/c'));

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)->and($other->unreadNotifications()->count())->toBe(1);
});

it('enregistre les canaux de réception de chaque utilisateur', function () {
    $user = alertUser(RoleName::ChefChantier);

    $this->actingAs($user)->put(route('notifications.channels'), ['app' => true, 'email' => false])->assertSessionHasNoErrors();
    expect($user->refresh()->notify_app)->toBeTrue()->and($user->notify_email)->toBeFalse();

    $this->actingAs($user)->put(route('notifications.channels'), ['app' => true])->assertSessionHasErrors('email');
    expect($user->refresh()->notify_email)->toBeFalse();
});

it('redirige un visiteur non connecté vers la connexion', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
});
