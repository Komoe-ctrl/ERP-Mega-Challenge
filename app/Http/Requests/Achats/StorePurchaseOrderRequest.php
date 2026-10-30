<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bon de commande direct : soit une demande entière (toutes ses quantités encore
 * commandables), soit des lignes précises avec leurs quantités (commande partielle).
 */
final class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_request_id' => ['required_without:lines', 'nullable', 'integer', 'exists:purchase_requests,id'],
            'lines' => ['required_without:purchase_request_id', 'nullable', 'array', 'min:1'],
            'lines.*.request_line_id' => ['required', 'integer', 'exists:purchase_request_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Choisissez un fournisseur.',
            'purchase_request_id.required_without' => 'Choisissez une demande validée.',
            'lines.required_without' => 'Ajoutez au moins une ligne.',
            'lines.*.quantity.gt' => 'La quantité doit être supérieure à 0.',
        ];
    }
}
