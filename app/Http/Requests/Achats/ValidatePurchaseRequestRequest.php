<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'une demande, avec ajustement éventuel des quantités (RG8.5).
 */
final class ValidatePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('purchaseRequest');

        return $request instanceof PurchaseRequest && ($this->user()?->can('decide', $request) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'adjustments' => ['nullable', 'array'],
            'adjustments.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['adjustments.*.numeric' => 'La quantité ajustée doit être un nombre.', 'adjustments.*.min' => 'La quantité ajustée ne peut pas être négative.'];
    }

    /**
     * @return array<int, float|int|string>
     */
    public function adjustments(): array
    {
        $out = [];

        foreach ((array) $this->input('adjustments', []) as $lineId => $value) {
            if (is_numeric($value) && is_numeric($lineId)) {
                $out[(int) $lineId] = $value;
            }
        }

        return $out;
    }
}
