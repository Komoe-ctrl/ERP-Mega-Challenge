<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\Article;
use Illuminate\Foundation\Http\FormRequest;

final class ImportArticlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Article::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:20480']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier Excel ou CSV.',
            'file.extensions' => 'Le fichier doit être au format Excel (.xlsx) ou CSV.',
            'file.max' => 'Le fichier doit faire 20 Mo au maximum.',
        ];
    }
}
