<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;

final class AddQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $consultation = $this->route('consultation');

        return $consultation instanceof Consultation && ($this->user()?->can('update', $consultation) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'total_amount' => ['required', 'integer', 'min:1'],
            'delay_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Choisissez un fournisseur.',
            'total_amount.integer' => 'Le total est un montant entier en FCFA, sans décimales.',
            'total_amount.min' => 'Le total doit être supérieur à zéro.',
            'delay_days.required' => 'Indiquez le délai de livraison.',
            'delay_days.integer' => 'Le délai est un nombre entier de jours.',
        ];
    }
}
