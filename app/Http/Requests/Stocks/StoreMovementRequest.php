<?php

declare(strict_types=1);

namespace App\Http\Requests\Stocks;

use App\Domain\Stocks\Enums\MovementType;
use App\Domain\Stocks\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie d'un mouvement de stock. Les règles métier (stock suffisant, motif des pertes, imputation
 * au chantier) sont dans l'action RecordStockMovement.
 */
final class StoreMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('record', StockMovement::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MovementType::class)],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'article_id' => ['required', 'integer', 'exists:articles,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'unit_cost' => ['nullable', 'integer', 'min:1'],
            'chantier_id' => ['nullable', 'integer', 'exists:chantiers,id'],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{type: string, warehouse_id: int, article_id: int, quantity: float|int|string, destination_warehouse_id: int|null, chantier_id: int|null, unit_cost: int|null, direction: string|null, reason: string|null}
     */
    public function movementData(): array
    {
        return [
            'type' => $this->string('type')->toString(),
            'warehouse_id' => $this->integer('warehouse_id'),
            'article_id' => $this->integer('article_id'),
            'quantity' => $this->string('quantity')->toString(),
            'destination_warehouse_id' => $this->filled('destination_warehouse_id') ? $this->integer('destination_warehouse_id') : null,
            'chantier_id' => $this->filled('chantier_id') ? $this->integer('chantier_id') : null,
            'unit_cost' => $this->filled('unit_cost') ? $this->integer('unit_cost') : null,
            'direction' => $this->filled('direction') ? $this->string('direction')->toString() : null,
            'reason' => $this->filled('reason') ? $this->string('reason')->toString() : null,
        ];
    }
}
