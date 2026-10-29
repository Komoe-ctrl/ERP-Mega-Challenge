<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\Module;
use App\Domain\Shared\Enums\PermissionAction;
use App\Domain\Shared\Enums\RoleName;

it('définit 15 modules répartis sur 4 versions', function () {
    expect(Module::cases())->toHaveCount(15);

    $parVersion = collect(Module::cases())->groupBy(fn (Module $m) => $m->version())->map->count();

    expect($parVersion->keys()->sort()->values()->all())->toEqual([1, 2, 3, 4]);
});

it('nomme les permissions <module>.<action>', function () {
    expect(Module::Achats->permission(PermissionAction::Validate))->toBe('achats.validate')
        ->and(Module::MainOeuvre->permissions())->toContain('main_oeuvre.view', 'main_oeuvre.delete')
        ->and(Module::allPermissions())->toHaveCount(75);
});

it('ne définit dans la matrice des profils que des permissions de la convention', function () {
    foreach (RoleName::cases() as $role) {
        expect(array_diff($role->defaultPermissions(), Module::allPermissions()))->toBe([]);
    }
});

it('a une structure de dossiers pour chacun des 15 modules', function () {
    foreach (Module::cases() as $module) {
        $dir = app_path('Domain/'.str($module->name)->studly());
        expect(is_dir($dir))->toBeTrue("Dossier manquant : {$dir}");
        expect(is_file($dir.'/README.md'))->toBeTrue("README manquant : {$dir}");
    }
});
