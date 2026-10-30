<?php

declare(strict_types=1);

namespace App\Http\Requests\Fournisseurs;

use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

final class EvaluateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier && ($this->user()?->can('update', $supplier) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reception_id' => ['required', 'integer', 'exists:receptions,id'],
            'delay' => ['required', 'integer', 'between:1,5'],
            'quality' => ['required', 'integer', 'between:1,5'],
            'price' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reception_id.required' => 'Choisissez la réception évaluée.',
            'delay.between' => 'La note de délais est comprise entre 1 et 5.',
            'quality.between' => 'La note de qualité est comprise entre 1 et 5.',
            'price.between' => 'La note de respect des prix est comprise entre 1 et 5.',
        ];
    }
}
