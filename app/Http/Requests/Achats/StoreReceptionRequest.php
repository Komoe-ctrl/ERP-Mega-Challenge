<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réception d'une livraison (RG8.9) : par ligne, la quantité livrée et celle renvoyée.
 */
final class StoreReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof PurchaseOrder && ($this->user()?->can('receive', $order) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.delivered' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.returned' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Saisissez les quantités livrées.',
            'lines.*.delivered.required' => 'Indiquez la quantité livrée (0 si rien n\'est arrivé).',
            'lines.*.delivered.numeric' => 'La quantité livrée doit être un nombre.',
            'lines.*.delivered.min' => 'La quantité livrée ne peut pas être négative.',
            'lines.*.returned.numeric' => 'La quantité renvoyée doit être un nombre.',
            'lines.*.returned.min' => 'La quantité renvoyée ne peut pas être négative.',
        ];
    }

    /**
     * @return list<array{purchase_order_line_id: int, delivered: float|int|string, returned: float|int|string}>
     */
    public function receptionLines(): array
    {
        $lines = [];

        foreach ((array) $this->input('lines', []) as $line) {
            $line = (array) $line;
            $lines[] = [
                'purchase_order_line_id' => is_numeric($line['purchase_order_line_id'] ?? null) ? (int) $line['purchase_order_line_id'] : 0,
                'delivered' => is_numeric($line['delivered'] ?? null) ? $line['delivered'] : 0,
                'returned' => is_numeric($line['returned'] ?? null) ? $line['returned'] : 0,
            ];
        }

        return $lines;
    }
}
