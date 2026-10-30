<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dépense diverse (RG10.4) : libellé, poste imputé, montant et justificatif obligatoire.
 */
final class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chantier = $this->route('chantier');

        return $chantier instanceof Chantier && ($this->user()?->can('recordExpense', [BudgetLine::class, $chantier]) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:3', 'max:255'],
            'budget_line_id' => ['required', 'integer', 'exists:budget_lines,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.min' => 'Le libellé compte au moins 3 caractères.',
            'amount.integer' => 'Le montant est un entier en FCFA, sans décimales.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
            'receipt.required' => 'Le justificatif est obligatoire (facture, reçu ou ticket).',
            'receipt.mimes' => 'Le justificatif doit être un PDF ou une image.',
            'receipt.max' => 'Le justificatif doit faire 20 Mo au maximum.',
        ];
    }

    /**
     * @return array{budget_line_id: int, label: string, amount: int}
     */
    public function expenseData(): array
    {
        return [
            'budget_line_id' => $this->integer('budget_line_id'),
            'label' => trim($this->string('label')->toString()),
            'amount' => $this->integer('amount'),
        ];
    }
}
