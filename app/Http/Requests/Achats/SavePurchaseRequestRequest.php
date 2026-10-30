<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\Models\PurchaseRequestLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Saisie d'une demande d'achat (RG8.1 à RG8.3). Aucun prix n'est accepté : il
 * vient toujours du référentiel (RG8.2).
 */
final class SavePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('purchaseRequest');

        return $request instanceof PurchaseRequest
            ? ($this->user()?->can('update', $request) ?? false)
            : ($this->user()?->can('create', PurchaseRequest::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'budget_line_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'submit' => ['boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.article_id' => ['required', 'integer', 'exists:articles,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.need_date' => ['required', 'date', 'after_or_equal:today'],
            'lines.*.urgency' => ['required', Rule::in(PurchaseRequestLine::URGENCIES)],
        ];

        if (! $this->route('purchaseRequest') instanceof PurchaseRequest) {
            $rules['chantier_id'] = ['required', 'integer', 'exists:chantiers,id'];
        }

        return $rules;
    }

    /**
     * RG7.2 : on ne crée une demande que sur un chantier visible par le demandeur.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();

            if ($this->route('purchaseRequest') instanceof PurchaseRequest || ! $user instanceof User || $validator->errors()->has('chantier_id')) {
                return;
            }

            $chantier = Chantier::query()->find($this->integer('chantier_id'));

            if ($chantier instanceof Chantier && ! $chantier->isVisibleTo($user)) {
                $validator->errors()->add('chantier_id', 'Vous n\'êtes pas affecté à ce chantier.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Ajoutez au moins une ligne.',
            'lines.min' => 'Ajoutez au moins une ligne.',
            'lines.*.article_id.required' => 'Choisissez un article.',
            'lines.*.article_id.exists' => 'Cet article n\'existe pas dans le référentiel.',
            'lines.*.quantity.gt' => 'La quantité doit être supérieure à 0.',
            'lines.*.quantity.numeric' => 'La quantité doit être un nombre.',
            'lines.*.need_date.after_or_equal' => 'La date de besoin ne peut pas être dans le passé.',
            'chantier_id.exists' => 'Ce chantier n\'existe pas.',
        ];
    }

    public function shouldSubmit(): bool
    {
        return $this->boolean('submit');
    }

    /**
     * @return array{chantier_id: int, budget_line_id: int|null, justification: string|null, lines: list<array{article_id: int, quantity: float|int|string, need_date: string, urgency: string}>}
     */
    public function requestData(): array
    {
        $existing = $this->route('purchaseRequest');
        $lines = [];

        foreach ((array) $this->input('lines', []) as $line) {
            $line = (array) $line;
            $quantity = $line['quantity'] ?? 0;
            $lines[] = [
                'article_id' => is_numeric($line['article_id'] ?? null) ? (int) $line['article_id'] : 0,
                'quantity' => is_numeric($quantity) ? $quantity : 0,
                'need_date' => is_string($line['need_date'] ?? null) ? $line['need_date'] : '',
                'urgency' => is_string($line['urgency'] ?? null) ? $line['urgency'] : 'normale',
            ];
        }

        return [
            'chantier_id' => $existing instanceof PurchaseRequest ? $existing->chantier_id : $this->integer('chantier_id'),
            'budget_line_id' => $this->filled('budget_line_id') ? $this->integer('budget_line_id') : null,
            'justification' => $this->filled('justification') ? trim($this->string('justification')->toString()) : null,
            'lines' => $lines,
        ];
    }
}
