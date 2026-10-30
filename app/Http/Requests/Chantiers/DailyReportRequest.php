<?php

declare(strict_types=1);

namespace App\Http\Requests\Chantiers;

use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class DailyReportRequest extends FormRequest
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
        return [
            'works' => ['required', 'string', 'max:5000'],
            'headcount' => ['required', 'integer', 'min:0', 'max:5000'],
            'materials_received' => ['nullable', 'string', 'max:2000'],
            'materials_used' => ['nullable', 'string', 'max:2000'],
            'incidents' => ['nullable', 'string', 'max:2000'],
            'photos' => ['array'],
            'photos.*' => ['image', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'works.required' => 'Décrivez les travaux réalisés.',
            'headcount.required' => 'Indiquez l\'effectif présent.',
            'headcount.integer' => 'L\'effectif est un nombre entier.',
            'photos.*.image' => 'Seules les images sont acceptées.',
            'photos.*.max' => 'Chaque photo doit faire 20 Mo au maximum.',
        ];
    }

    /**
     * @return array{works: string, headcount: int, materials_received: string|null, materials_used: string|null, incidents: string|null}
     */
    public function reportData(): array
    {
        return [
            'works' => $this->string('works')->toString(),
            'headcount' => $this->integer('headcount'),
            'materials_received' => $this->filled('materials_received') ? $this->string('materials_received')->toString() : null,
            'materials_used' => $this->filled('materials_used') ? $this->string('materials_used')->toString() : null,
            'incidents' => $this->filled('incidents') ? $this->string('incidents')->toString() : null,
        ];
    }

    /** @return list<UploadedFile> */
    public function photos(): array
    {
        return array_values(array_filter((array) $this->file('photos', []), fn ($p): bool => $p instanceof UploadedFile));
    }
}
