<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Motif obligatoire pour une désactivation ou une clôture d'exercice.
 * L'autorisation fine est faite par la policy dans le contrôleur.
 */
final class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => 'Le motif est obligatoire.', 'reason.min' => 'Le motif est trop court.'];
    }
}
