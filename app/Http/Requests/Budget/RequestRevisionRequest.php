<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de révision du budget d'un poste (RG10.2) : montant signé et motif.
 */
final class RequestRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chantier = $this->route('chantier');

        return $chantier instanceof Chantier && ($this->user()?->can('manage', [BudgetLine::class, $chantier]) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'budget_line_id' => ['required', 'integer', 'exists:budget_lines,id'],
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.integer' => 'Le montant est un entier en FCFA, sans décimales.',
            'amount.not_in' => 'Le montant de la révision ne peut pas être nul.',
            'reason.required' => 'Le motif est obligatoire.',
            'reason.min' => 'Le motif est obligatoire (10 caractères minimum).',
        ];
    }

    /**
     * @return array{budget_line_id: int, amount: int, reason: string}
     */
    public function revisionData(): array
    {
        return [
            'budget_line_id' => $this->integer('budget_line_id'),
            'amount' => $this->integer('amount'),
            'reason' => $this->string('reason')->toString(),
        ];
    }
}
