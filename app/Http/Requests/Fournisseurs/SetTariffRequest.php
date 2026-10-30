<?php

declare(strict_types=1);

namespace App\Http\Requests\Fournisseurs;

use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier && ($this->user()?->can('update', $supplier) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', Rule::exists('articles', 'id')->where('is_active', true)],
            'price' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'article_id.required' => 'Choisissez un article.',
            'article_id.exists' => 'Cet article n\'existe pas ou est désactivé.',
            'price.integer' => 'Le tarif est un montant entier en FCFA, sans décimales.',
            'price.min' => 'Le tarif doit être supérieur à zéro.',
        ];
    }
}
