<?php

declare(strict_types=1);

namespace App\Http\Requests\Chantiers;

use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class RecordProgressRequest extends FormRequest
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
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.task_id' => ['required', 'integer'],
            'entries.*.progress' => ['required', 'integer', 'min:0', 'max:100'],
            'entries.*.comment' => ['nullable', 'string', 'max:1000'],
            'entries.*.photos' => ['array'],
            'entries.*.photos.*' => ['image', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.required' => 'Aucune mise à jour à enregistrer.',
            'entries.*.progress.min' => 'L\'avancement est compris entre 0 et 100 %.',
            'entries.*.progress.max' => 'L\'avancement est compris entre 0 et 100 %.',
            'entries.*.progress.integer' => 'L\'avancement est un nombre entier.',
            'entries.*.photos.*.image' => 'Seules les images sont acceptées.',
            'entries.*.photos.*.max' => 'Chaque photo doit faire 20 Mo au maximum.',
        ];
    }

    /**
     * @return list<array{task_id: int, progress: int, comment: string|null, photos: list<UploadedFile>}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ((array) $this->input('entries', []) as $index => $entry) {
            $entry = (array) $entry;
            $photos = $this->file("entries.{$index}.photos", []);
            $comment = $entry['comment'] ?? null;

            $entries[] = [
                'task_id' => is_numeric($entry['task_id'] ?? null) ? (int) $entry['task_id'] : 0,
                'progress' => is_numeric($entry['progress'] ?? null) ? (int) $entry['progress'] : 0,
                'comment' => is_string($comment) ? $comment : null,
                'photos' => array_values(array_filter((array) $photos, fn ($p): bool => $p instanceof UploadedFile)),
            ];
        }

        return $entries;
    }
}
