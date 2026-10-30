<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RG8.15 : tout utilisateur qui peut créer une demande d'achat peut signaler un article manquant.
 */
final class ReportMissingArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('achats.create') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:3', 'max:255'],
            'chantier_id' => ['nullable', 'integer', 'exists:chantiers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['label.required' => 'Décrivez l\'article manquant.', 'label.min' => 'La description est trop courte.'];
    }
}
