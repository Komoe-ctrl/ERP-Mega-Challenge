<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\UserPoste;
use App\Domain\Shared\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;

// Poste (titre + service) du compte connecté ; sélecteur si plusieurs postes.

it('affiche le poste du compte : titre et service viennent du compte', function () {
    $service = Service::factory()->create(['name' => 'Travaux']);
    $user = userWithRole(RoleName::ConducteurTravaux, ['job_title' => 'Conducteur de travaux', 'service_id' => $service->id]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.poste.title', 'Conducteur de travaux')
            ->where('auth.poste.service', 'Travaux')
            ->has('auth.postes', 1));
});

it('à défaut de titre saisi, prend le poste par défaut du profil', function () {
    $user = userWithRole(RoleName::Magasinier);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.poste.title', 'Magasinier'));
});

it('propose un sélecteur quand le compte a plusieurs postes et active le poste par défaut', function () {
    $dg = Service::factory()->create(['name' => 'Direction générale']);
    $user = userWithRole(RoleName::ConducteurTravaux);
    UserPoste::query()->create(['user_id' => $user->id, 'title' => 'Directeur Mega Challenge', 'service_id' => $dg->id, 'is_default' => false]);
    UserPoste::query()->create(['user_id' => $user->id, 'title' => 'Directeur général', 'service_id' => $dg->id, 'is_default' => true]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.postes', 2)
            ->where('auth.poste.title', 'Directeur général'));
});

it('change de poste pour la session', function () {
    $user = userWithRole(RoleName::ConducteurTravaux);
    UserPoste::query()->create(['user_id' => $user->id, 'title' => 'Directeur général', 'is_default' => true]);
    $other = UserPoste::query()->create(['user_id' => $user->id, 'title' => 'Directeur Mega Challenge', 'is_default' => false]);

    $this->actingAs($user)->put(route('poste.update'), ['poste_id' => $other->id])->assertRedirect();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.poste.title', 'Directeur Mega Challenge'));
});

it('refuse un poste qui n\'appartient pas au compte (403)', function () {
    $user = userWithRole(RoleName::ConducteurTravaux);
    $stranger = userWithRole(RoleName::ChefChantier);
    $foreign = UserPoste::query()->create(['user_id' => $stranger->id, 'title' => 'Chef', 'is_default' => true]);

    $this->actingAs($user)->put(route('poste.update'), ['poste_id' => $foreign->id])->assertForbidden();
});
