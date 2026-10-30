<?php

declare(strict_types=1);

namespace App\Http\Requests\Chantiers;

use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;

final class ImportPlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chantier = $this->route('chantier');

        return $chantier instanceof Chantier && ($this->user()?->can('update', $chantier) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier Excel.',
            'file.mimes' => 'Le fichier doit être au format Excel (.xlsx) ou CSV.',
            'file.max' => 'Le fichier doit faire 20 Mo au maximum.',
        ];
    }
}
