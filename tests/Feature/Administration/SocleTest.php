<?php

declare(strict_types=1);

use App\Domain\Administration\Actions\CloseFiscalYear;
use App\Domain\Administration\Models\Activity;
use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Administration\Models\Setting;
use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Policies\ModulePolicy;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;

describe('journal des activités (RG1.5)', function () {
    it('enregistre l\'auteur et les valeurs avant / après', function () {
        $admin = adminUser();
        $agency = Agency::factory()->create(['name' => 'Ancien nom']);

        $this->actingAs($admin);
        $agency->update(['name' => 'Nouveau nom']);

        $activity = Activity::query()->where('event', 'updated')->where('subject_type', Agency::class)->latest('id')->firstOrFail();

        expect($activity->causer_id)->toBe($admin->id)
            ->and($activity->attribute_changes['attributes']['name'])->toBe('Nouveau nom')
            ->and($activity->attribute_changes['old']['name'])->toBe('Ancien nom');
    });

    it('n\'est pas modifiable ni supprimable', function () {
        Agency::factory()->create();
        $activity = Activity::query()->firstOrFail();

        expect(fn () => $activity->update(['description' => 'falsifié']))->toThrow(LogicException::class)
            ->and(fn () => $activity->delete())->toThrow(LogicException::class);
    });

    it('trace les changements de droits d\'un rôle sur un utilisateur', function () {
        $admin = adminUser();
        $target = User::factory()->create();

        $this->actingAs($admin);
        $target->update(['is_active' => false]);

        expect(Activity::query()->where('subject_id', $target->id)->where('subject_type', User::class)->where('event', 'updated')->exists())->toBeTrue();
    });
});

describe('paramètres typés (RG1.3)', function () {
    beforeEach(fn () => $this->seed(DatabaseSeeder::class));

    it('convertit la valeur selon son type', function () {
        expect(Setting::get('budget.warning_percent'))->toBe(80)
            ->and(Setting::get('tax.vat_rate'))->toBe(18.0)
            ->and(Setting::get('numbering.devis'))->toBeString()
            ->and(Setting::get('inconnu', 'défaut'))->toBe('défaut');
    });

    it('prend en compte une modification immédiatement (cache vidé)', function () {
        expect(Setting::get('budget.warning_percent'))->toBe(80);

        Setting::where('key', 'budget.warning_percent')->firstOrFail()->update(['value' => '75']);

        expect(Setting::get('budget.warning_percent'))->toBe(75);
    });

    it('ne réinitialise pas une valeur modifiée quand on rejoue le seeder', function () {
        Setting::where('key', 'budget.warning_percent')->firstOrFail()->update(['value' => '70']);
        Cache::flush();

        $this->seed(DatabaseSeeder::class);

        expect(Setting::get('budget.warning_percent'))->toBe(70);
    });
});

describe('exercices comptables (RG1.4)', function () {
    it('passe en lecture seule une fois clôturé', function () {
        $magasinier = userWithRole(RoleName::Magasinier);
        $admin = adminUser();
        $year = FiscalYear::factory()->create();

        expect($admin->can('update', $year))->toBeTrue();

        (new CloseFiscalYear)->execute($year, $admin);

        expect($year->fresh()->isClosed())->toBeTrue()
            ->and($year->fresh()->closed_by)->toBe($admin->id)
            ->and($admin->can('update', $year->fresh()))->toBeFalse()
            ->and($admin->can('delete', $year->fresh()))->toBeFalse()
            ->and($magasinier->can('close', FiscalYear::factory()->create()))->toBeFalse()
            ->and($admin->can('close', FiscalYear::factory()->create()))->toBeTrue();
    });

    it('refuse de clôturer deux fois', function () {
        $year = FiscalYear::factory()->closed()->create();

        expect(fn () => (new CloseFiscalYear)->execute($year, adminUser()))->toThrow(DomainException::class);
    });
});

describe('compte administrateur de démonstration', function () {
    it('est créé depuis le .env avec le rôle Administrateur', function () {
        config(['erp.demo_admin' => ['name' => 'Démo', 'email' => 'demo@example.com', 'password' => 'Secret!12345']]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'demo@example.com')->firstOrFail();
        expect($admin->hasRole(RoleName::Administrateur->value))->toBeTrue()
            ->and($admin->can('administration.update'))->toBeTrue();
    });

    it('refuse de s\'exécuter sans identifiants (jamais en dur)', function () {
        config(['erp.demo_admin' => ['name' => 'Démo', 'email' => null, 'password' => null]]);
        $this->seed(RolesAndPermissionsSeeder::class);

        expect(fn () => $this->seed(AdminUserSeeder::class))->toThrow(RuntimeException::class);
    });
});

describe('séparation des tâches', function () {
    it('empêche l\'auteur de valider sa propre demande', function () {
        $policy = new class extends ModulePolicy
        {
            protected function module(): Module
            {
                return Module::Achats;
            }

            public function validate(User $user, User $record): bool
            {
                return $this->canValidateOthers($user, $record, 'id');
            }
        };

        $valideur = userWithRole(RoleName::DirectionTechnique);
        $autre = User::factory()->create();

        expect($policy->validate($valideur, $valideur))->toBeFalse()
            ->and($policy->validate($valideur, $autre))->toBeTrue();
    });
});
