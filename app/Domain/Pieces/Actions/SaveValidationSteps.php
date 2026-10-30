<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Actions;

use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\ValidationStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Paramétrage du circuit de validation (Administration > Étapes de validation).
 * Le circuit est remplacé en bloc : l'ordre de la liste devient l'ordre des étapes.
 */
final class SaveValidationSteps
{
    /**
     * @param  list<array{label: string, role_name: string, button_label: string, is_active: bool}>  $steps
     * @return Collection<int, ValidationStep>
     */
    public function execute(array $steps): Collection
    {
        if (! collect($steps)->contains(fn (array $s): bool => $s['is_active'])) {
            throw ValidationException::withMessages(['steps' => 'Au moins une étape doit rester active.']);
        }

        foreach ($steps as $i => $step) {
            if (! Role::query()->where('name', $step['role_name'])->exists()) {
                throw ValidationException::withMessages(["steps.{$i}.role_name" => 'Rôle inconnu.']);
            }
        }

        // Changer le circuit pendant qu'une pièce l'attend rendrait sa position ambiguë.
        if (Piece::query()->where('status', PieceStatus::EnValidation->value)->exists()) {
            throw ValidationException::withMessages(['steps' => 'Des pièces sont en cours de validation : attendez leur décision avant de modifier le circuit.']);
        }

        return DB::transaction(function () use ($steps) {
            ValidationStep::query()->delete();

            foreach ($steps as $i => $step) {
                ValidationStep::query()->create([
                    'position' => $i + 1,
                    'label' => $step['label'],
                    'role_name' => $step['role_name'],
                    'button_label' => $step['button_label'],
                    'is_active' => $step['is_active'],
                ]);
            }

            return ValidationStep::query()->orderBy('position')->get();
        });
    }
}
