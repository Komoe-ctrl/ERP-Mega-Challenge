<?php

declare(strict_types=1);

use App\Domain\Administration\Models\Attendance;
use App\Domain\Shared\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;

// Pointage d'arrivée : enregistré pour l'utilisateur authentifié, une fois par jour.

it('enregistre le pointage de l\'utilisateur authentifié', function () {
    $user = userWithRole(RoleName::ConducteurTravaux);

    $this->actingAs($user)->post(route('attendance.store'))->assertRedirect();

    $attendance = Attendance::query()->where('user_id', $user->id)->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance?->work_date->toDateString())->toBe(now()->toDateString());
});

it('ne crée qu\'un pointage par jour et garde la première heure', function () {
    $user = userWithRole(RoleName::ConducteurTravaux);

    $this->travelTo(now()->setTime(7, 58));
    $this->actingAs($user)->post(route('attendance.store'));
    $this->travelTo(now()->setTime(14, 30));
    $this->actingAs($user)->post(route('attendance.store'));

    expect(Attendance::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Attendance::query()->where('user_id', $user->id)->first()?->arrived_at->format('H:i'))->toBe('07:58');
});

it('refuse le pointage à un visiteur non connecté', function () {
    $this->post(route('attendance.store'))->assertRedirect(route('login'));

    expect(Attendance::query()->count())->toBe(0);
});

it('un utilisateur ne pointe que pour lui-même', function () {
    $a = userWithRole(RoleName::ConducteurTravaux);
    $b = userWithRole(RoleName::ChefChantier);

    $this->actingAs($a)->post(route('attendance.store'), ['user_id' => $b->id]);

    expect(Attendance::query()->where('user_id', $a->id)->count())->toBe(1)
        ->and(Attendance::query()->where('user_id', $b->id)->count())->toBe(0);
});

it('expose le pointage du jour à l\'interface', function () {
    $user = userWithRole(RoleName::ConducteurTravaux);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attendance.arrivedAt', null)->where('attendance.today', now()->toDateString()));

    $this->actingAs($user)->post(route('attendance.store'));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->whereNot('attendance.arrivedAt', null));
});
