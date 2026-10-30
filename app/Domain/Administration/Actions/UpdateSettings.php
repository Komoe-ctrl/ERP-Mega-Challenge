<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre les paramètres modifiés (RG1.3). Seules les valeurs qui changent
 * sont écrites, donc seules elles apparaissent au journal.
 */
final class UpdateSettings
{
    /** @param array<string, string> $values valeurs par clé de paramètre */
    public function execute(array $values): void
    {
        DB::transaction(function () use ($values): void {
            Setting::query()->whereIn('key', array_keys($values))->get()->each(
                function (Setting $setting) use ($values): void {
                    $setting->update(['value' => $values[$setting->key]]);
                },
            );
        });
    }
}
