<?php

declare(strict_types=1);

namespace App\Http\Requests\Projets;

use App\Domain\Projets\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pièces jointes d'un projet : 20 Mo maximum par fichier (section 11).
 */
final class UploadProjectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Choisissez au moins un fichier.',
            'files.*.max' => 'Chaque fichier doit faire 20 Mo au maximum.',
            'files.*.file' => 'Le fichier est invalide.',
        ];
    }
}
