<?php

declare(strict_types=1);

namespace App\Http\Requests\Devis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edition des lignes et des taux d'un brouillon de devis. L'autorisation (brouillon + droit de
 * modification) est vérifiée par la policy dans le contrôleur.
 */
final class SaveQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'direct_cost' => ['required', 'integer', 'min:0'],
            'works' => ['present', 'array'],
            'works.*.label' => ['required', 'string', 'min:3', 'max:200'],
            'works.*.unit' => ['required', 'string', 'max:20'],
            'works.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'works.*.unit_price' => ['required', 'integer', 'min:0'],
            'works.*.components' => ['nullable', 'string', 'max:1000'],
            'works.*.ouvrage_id' => ['nullable', 'integer', 'exists:ouvrages,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'works.*.label.min' => 'Libellé trop court.',
            'works.*.quantity.gt' => 'La quantité doit être supérieure à 0.',
            'works.*.unit_price.min' => 'Le prix ne peut pas être négatif.',
            'discount_rate.max' => 'La remise doit être comprise entre 0 et 100 %.',
        ];
    }
}
