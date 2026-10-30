<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Activity;
use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;
use App\Http\Controllers\LegacyScreenController;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/** Une page d'accueil par module métier : [module, nom de route]. */
const MODULE_PAGES = [
    ['projets', 'projets.index'],
    ['chantiers', 'chantiers.index'],
    ['achats', 'achats.demandes'],
    ['achats', 'achats.consultations'],
    ['achats', 'achats.commandes'],
    ['achats', 'achats.factures'],
    ['achats', 'achats.receptions'],
    ['achats', 'achats.referentiel'],
    ['fournisseurs', 'fournisseurs.index'],
    ['stocks', 'stocks.index'],
    ['clients', 'clients.index'],
    ['devis', 'devis.index'],
    ['marches', 'marches.index'],
    ['situations', 'situations.index'],
    ['main_oeuvre', 'main_oeuvre.pointage'],
    ['rh', 'rh.index'],
    ['engins', 'engins.index'],
    ['finance', 'finance.rentabilite'],
    ['budget', 'budget.index'],
];

/** Compte du profil, double authentification confirmée pour ne pas être renvoyé vers la sécurité. */
function profileUser(RoleName $role): User
{
    return userWithRole($role, [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

it('refuse à l\'administrateur chaque module métier (403), pas seulement dans le menu', function (string $module, string $route) {
    $this->actingAs(profileUser(RoleName::Administrateur))->get(route($route))->assertForbidden();
})->with(MODULE_PAGES);

it('refuse à l\'administrateur chaque écran repris de l\'ancienne application, sauf ceux de l\'Administration', function () {
    $admin = profileUser(RoleName::Administrateur);

    foreach (LegacyScreenController::permissions() as $slug => $permission) {
        $response = $this->actingAs($admin)->get(route('ecrans', $slug));

        str_starts_with($permission, 'administration.')
            ? $response->assertOk()
            : $response->assertForbidden();
    }
});

it('refuse à l\'administrateur les écritures des modules métier', function () {
    $admin = profileUser(RoleName::Administrateur);

    $this->actingAs($admin)->post(route('projets.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($admin)->post(route('achats.demandes.store'), [])->assertForbidden();
    $this->actingAs($admin)->post(route('fournisseurs.store'), [])->assertForbidden();
});

it('chaque profil n\'accède qu\'aux modules que sa matrice autorise', function (RoleName $role) {
    $user = profileUser($role);

    foreach (MODULE_PAGES as [$module, $route]) {
        $allowed = in_array($module.'.view', $role->defaultPermissions(), true);
        $response = $this->actingAs($user)->get(route($route));

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
})->with(array_map(fn (RoleName $r): array => [$r], RoleName::cases()));

it('chaque profil n\'ouvre que les écrans repris de l\'ancienne application que ses droits autorisent', function (RoleName $role) {
    $user = profileUser($role);

    foreach (LegacyScreenController::permissions() as $slug => $permission) {
        $allowed = in_array($permission, $role->defaultPermissions(), true);
        $response = $this->actingAs($user)->get(route('ecrans', $slug));

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
})->with(array_map(fn (RoleName $r): array => [$r], RoleName::cases()));

it('répond 404 à un écran inconnu', function () {
    $this->actingAs(profileUser(RoleName::DirectionGenerale))->get(route('ecrans', 'inexistant'))->assertNotFound();
});

it('donne à la Direction générale la lecture de tous les modules', function () {
    $dg = profileUser(RoleName::DirectionGenerale);

    foreach (Module::cases() as $module) {
        expect($dg->can($module->permission(PermissionAction::View)))->toBeTrue();
    }
});

it('n\'utilise aucun contournement global des permissions (pas de Gate::before « super-admin »)', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            expect(file_get_contents($file->getPathname()))
                ->not->toContain('Gate::before')
                ->not->toContain('Gate::after');
        }
    }
});

// --- Un administrateur ne s'attribue pas de droits ------------------------------

it('refuse à un administrateur de modifier ses propres rôles', function () {
    $admin = profileUser(RoleName::Administrateur);

    $this->actingAs($admin)->put(route('administration.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'is_active' => true,
        'roles' => [RoleName::Administrateur->value, RoleName::Finance->value],
    ])->assertSessionHasErrors('roles');

    expect($admin->fresh()?->getRoleNames()->all())->toBe([RoleName::Administrateur->value]);
});

it('permet à un administrateur de modifier son propre profil sans toucher à ses rôles', function () {
    $admin = profileUser(RoleName::Administrateur);

    $this->actingAs($admin)->put(route('administration.users.update', $admin), [
        'name' => 'Nouveau nom',
        'email' => $admin->email,
        'is_active' => true,
        'roles' => [RoleName::Administrateur->value],
    ])->assertSessionHasNoErrors();

    expect($admin->fresh()?->name)->toBe('Nouveau nom');
});

it('refuse à un administrateur de modifier les permissions de son propre rôle', function () {
    $admin = profileUser(RoleName::Administrateur);
    $role = Role::findByName(RoleName::Administrateur->value);

    $this->actingAs($admin)->put(route('administration.roles.update', $role), [
        'name' => $role->name,
        'permissions' => ['administration.view', 'finance.view'],
    ])->assertForbidden();

    expect($role->fresh()?->hasPermissionTo('finance.view'))->toBeFalse();
});

// --- Journal et notification de la Direction générale ---------------------------

it('journalise et notifie la Direction générale quand un administrateur change les rôles d\'un compte', function () {
    $admin = profileUser(RoleName::Administrateur);
    $dg = profileUser(RoleName::DirectionGenerale);
    $target = userWithRole(RoleName::ChefChantier);

    $this->actingAs($admin)->put(route('administration.users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'is_active' => true,
        'roles' => [RoleName::ChefChantier->value, RoleName::Magasinier->value],
    ])->assertSessionHasNoErrors();

    $entry = Activity::query()->where('description', 'like', 'Rôles de %')->latest('id')->first();
    expect($entry)->not->toBeNull()
        ->and($entry?->causer_id)->toBe($admin->id)
        ->and((string) $entry?->getProperty('motif'))->toContain('magasinier');

    $notification = DatabaseNotification::query()->where('notifiable_id', $dg->id)->latest()->first();
    expect($notification)->not->toBeNull()
        ->and(data_get($notification?->data, 'kind'))->toBe('securite');
});

it('journalise et notifie la Direction générale quand les permissions d\'un rôle changent', function () {
    $admin = profileUser(RoleName::Administrateur);
    $dg = profileUser(RoleName::DirectionGenerale);
    $role = Role::findByName(RoleName::Magasinier->value);

    $this->actingAs($admin)->put(route('administration.roles.update', $role), [
        'name' => $role->name,
        'permissions' => ['stocks.view', 'stocks.validate'],
    ])->assertRedirect();

    expect(Activity::query()->where('description', 'like', 'Permissions du rôle%')->count())->toBe(1)
        ->and(DatabaseNotification::query()->where('notifiable_id', $dg->id)->count())->toBe(1);
});

it('ne journalise rien quand les rôles ne changent pas', function () {
    $admin = profileUser(RoleName::Administrateur);
    $dg = profileUser(RoleName::DirectionGenerale);
    $target = userWithRole(RoleName::ChefChantier);

    $this->actingAs($admin)->put(route('administration.users.update', $target), [
        'name' => 'Autre nom',
        'email' => $target->email,
        'is_active' => true,
        'roles' => [RoleName::ChefChantier->value],
    ]);

    expect(DatabaseNotification::query()->where('notifiable_id', $dg->id)->count())->toBe(0);
});
