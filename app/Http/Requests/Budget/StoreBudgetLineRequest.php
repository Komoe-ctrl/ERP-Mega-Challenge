<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Poste du déboursé sec : nom, nature et budget initial en FCFA entiers.
 */
final class StoreBudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chantier = $this->route('chantier');

        return $chantier instanceof Chantier && ($this->user()?->can('manage', [BudgetLine::class, $chantier]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'post' => ['required', 'string', 'min:3', 'max:100'],
            'nature' => ['required', Rule::in(BudgetLine::NATURES)],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'post.min' => 'Le nom du poste compte au moins 3 caractères.',
            'amount.integer' => 'Le montant est un entier en FCFA, sans décimales.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['post' => 'poste', 'nature' => 'nature', 'amount' => 'montant'];
    }

    /**
     * @return array{post: string, nature: string, amount: int}
     */
    public function lineData(): array
    {
        return [
            'post' => trim($this->string('post')->toString()),
            'nature' => $this->string('nature')->toString(),
            'amount' => $this->integer('amount'),
        ];
    }
}
