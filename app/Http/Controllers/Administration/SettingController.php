<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Domain\Administration\Actions\CloseFiscalYear;
use App\Domain\Administration\Actions\UpdateSettings;
use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Administration\Models\Setting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UpdateSettingsRequest;
use App\Http\Requests\ReasonRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paramètres de l'ERP (RG1.3) et exercices comptables (RG1.4).
 */
final class SettingController extends Controller
{
    private const GROUPS = [
        'validation' => 'Seuils de validation',
        'alerts' => 'Alertes',
        'taxes' => 'Taxes',
        'numbering' => 'Numérotation des documents',
        'documents' => 'Documents',
    ];

    public function index(): Response
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::query()->orderBy('id')->get()->groupBy('group');

        return Inertia::render('Administration/Parametres', [
            'settingGroups' => collect(self::GROUPS)
                ->filter(fn (string $label, string $group): bool => $settings->has($group))
                ->map(fn (string $label, string $group): array => [
                    'group' => $label,
                    'items' => $settings->get($group, collect())->map(fn (Setting $s): array => array_filter([
                        'key' => $s->key,
                        'label' => $s->label,
                        'value' => (string) $s->value,
                        'unit' => $this->unit($s->key),
                        'description' => $s->description,
                    ], fn (mixed $v): bool => $v !== null))->values(),
                ])->values(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_on')->get()->map(fn (FiscalYear $f): array => [
                'id' => $f->id,
                'label' => $f->label,
                'startsOn' => $f->starts_on->toDateString(),
                'endsOn' => $f->ends_on->toDateString(),
                'status' => $f->status,
            ])->values(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettings $action): RedirectResponse
    {
        $action->execute($request->settings());
        $this->toast('Paramètres enregistrés.');

        return back();
    }

    public function closeFiscalYear(ReasonRequest $request, FiscalYear $fiscalYear, CloseFiscalYear $action): RedirectResponse
    {
        $this->authorize('close', $fiscalYear);

        $user = $request->user();
        assert($user instanceof User);
        $action->execute($fiscalYear, $user, $request->string('reason')->toString());
        $this->toast("Exercice {$fiscalYear->label} clôturé : il est en lecture seule.");

        return back();
    }

    private function unit(string $key): ?string
    {
        return match (true) {
            str_ends_with($key, '_fcfa') => 'FCFA',
            str_ends_with($key, '_percent'), $key === 'tax.vat_rate' => '%',
            str_ends_with($key, '_points') => 'points',
            str_ends_with($key, '_days') => 'jours',
            str_ends_with($key, '_mb') => 'Mo',
            default => null,
        };
    }
}
