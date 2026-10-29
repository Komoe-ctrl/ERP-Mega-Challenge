<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function userPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Awa Koné',
        'email' => 'awa.kone@example.com',
        'agency_id' => Agency::factory()->create()->id,
        'service_id' => Service::factory()->create()->id,
        'phone' => '+225 07 00 00 00 00',
        'is_active' => true,
        'roles' => [RoleName::Magasinier->value],
        'password' => 'Secret!12345',
    ], $overrides);
}

it('liste les utilisateurs paginés avec leurs rôles', function () {
    $admin = adminUser();
    User::factory()->count(20)->create();

    $this->actingAs($admin)->get(route('administration.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Administration/Users/Index')
            ->has('users.data', 15)
            ->where('users.total', 21)
            ->where('can.create', true));
});

it('filtre la liste par recherche', function () {
    $admin = adminUser();
    User::factory()->create(['name' => 'Zoumana Traoré']);

    $this->actingAs($admin)->get(route('administration.users.index', ['q' => 'Zoumana']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Zoumana Traoré'));
});

it('crée un utilisateur avec son rôle, son agence et son service', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('administration.users.store'), userPayload())
        ->assertRedirect(route('administration.users.index'));

    $user = User::where('email', 'awa.kone@example.com')->firstOrFail();
    expect($user->hasRole(RoleName::Magasinier->value))->toBeTrue()
        ->and($user->agency_id)->not->toBeNull()
        ->and($user->service_id)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Secret!12345', $user->password))->toBeTrue();
});

it('valide les champs obligatoires et l\'unicité de l\'e-mail', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('administration.users.store'), userPayload([
        'name' => '', 'email' => $admin->email, 'password' => 'court',
    ]))->assertSessionHasErrors(['name', 'email']);
});

it('refuse un rôle qui n\'existe pas', function () {
    $this->actingAs(adminUser())
        ->post(route('administration.users.store'), userPayload(['roles' => ['inconnu']]))
        ->assertSessionHasErrors('roles.0');
});

it('modifie un utilisateur sans changer son mot de passe quand il est laissé vide', function () {
    $admin = adminUser();
    $target = User::factory()->create(['password' => 'Ancien!12345']);
    $hash = $target->password;

    $this->actingAs($admin)->put(route('administration.users.update', $target), userPayload([
        'name' => 'Nouveau nom', 'email' => $target->email, 'password' => null,
    ]))->assertRedirect(route('administration.users.index'));

    expect($target->fresh()->name)->toBe('Nouveau nom')
        ->and($target->fresh()->password)->toBe($hash);
});

it('désactive un compte sans le supprimer (RG : désactivé, jamais supprimé)', function () {
    $admin = adminUser();
    $target = User::factory()->create();

    $this->actingAs($admin)->patch(route('administration.users.deactivate', $target))->assertRedirect();

    expect($target->fresh()->is_active)->toBeFalse()
        ->and(User::withTrashed()->whereKey($target->id)->exists())->toBeTrue();

    $this->actingAs($admin)->patch(route('administration.users.reactivate', $target))->assertRedirect();
    expect($target->fresh()->is_active)->toBeTrue();
});

it('interdit de désactiver son propre compte', function () {
    $admin = adminUser();

    $this->actingAs($admin)->patch(route('administration.users.deactivate', $admin))->assertForbidden();
    expect($admin->fresh()->is_active)->toBeTrue();
});

it('ne propose aucune route de suppression d\'utilisateur', function () {
    $admin = adminUser();
    $target = User::factory()->create();

    $this->actingAs($admin)->delete('/administration/users/'.$target->id)->assertStatus(405);
    expect($admin->can('delete', $target))->toBeFalse();
});

it('journalise la création avec son auteur et sans le mot de passe', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('administration.users.store'), userPayload());

    $activity = Activity::query()->where('event', 'created')->where('subject_type', User::class)->latest('id')->firstOrFail();

    expect($activity->causer_id)->toBe($admin->id)
        ->and(json_encode($activity->attribute_changes))->toContain('awa.kone@example.com')
        ->and(json_encode($activity->attribute_changes))->not->toContain('Secret!12345')
        ->and(json_encode($activity->attribute_changes))->not->toContain('password');
});
