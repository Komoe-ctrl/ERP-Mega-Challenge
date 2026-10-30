<?php

declare(strict_types=1);

namespace App\Http\Requests\Pieces;

use App\Domain\Pieces\Data\PieceData;
use App\Domain\Pieces\Enums\PieceKind;
use App\Domain\Pieces\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saisie d'une pièce (note de frais, recette, pièce de caisse). L'autorisation vient de la policy :
 * droit de création du module du type de pièce, ou auteur de la pièce pour une modification.
 */
final class SavePieceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $piece = $this->route('piece');
        $kind = $this->route('kind');

        if (! $user instanceof User) {
            return false;
        }

        if ($piece instanceof Piece) {
            return $user->can('update', $piece);
        }

        return $kind instanceof PieceKind && $user->can('create', [Piece::class, $kind]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'title' => ['required', 'string', 'max:200'],
            'number' => ['nullable', 'string', 'max:40'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_lot_id' => ['nullable', 'integer', 'exists:project_lots,id'],
            'lot_component_id' => ['nullable', 'integer', 'exists:lot_components,id'],
            'bc_number' => ['nullable', 'string', 'max:40'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'max:10240'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.designation' => ['required', 'string', 'max:250'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0'],
            'lines.*.rubrique_id' => ['required', 'integer', 'exists:rubriques,id'],
        ];
    }

    public function toData(): PieceData
    {
        $lines = [];

        foreach ((array) $this->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $quantity = $line['quantity'] ?? 0;
            $designation = $line['designation'] ?? '';
            $unitPrice = $line['unit_price'] ?? 0;
            $rubriqueId = $line['rubrique_id'] ?? 0;

            $lines[] = [
                'designation' => is_string($designation) ? $designation : '',
                'quantity' => is_int($quantity) || is_float($quantity) || is_string($quantity) ? $quantity : 0,
                'unit_price' => is_numeric($unitPrice) ? (int) $unitPrice : 0,
                'rubrique_id' => is_numeric($rubriqueId) ? (int) $rubriqueId : 0,
            ];
        }

        return new PieceData(
            period: $this->string('period')->toString(),
            title: $this->string('title')->toString(),
            number: $this->filled('number') ? $this->string('number')->toString() : null,
            projectId: $this->filled('project_id') ? $this->integer('project_id') : null,
            lotId: $this->filled('project_lot_id') ? $this->integer('project_lot_id') : null,
            componentId: $this->filled('lot_component_id') ? $this->integer('lot_component_id') : null,
            bcNumber: $this->filled('bc_number') ? $this->string('bc_number')->toString() : null,
            observation: $this->filled('observation') ? $this->string('observation')->toString() : null,
            lines: $lines,
        );
    }
}
