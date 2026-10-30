<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RG8.12 : un article a une référence, un libellé, une catégorie, une unité et
 * un prix unitaire entier en FCFA. Seul le Service Achats BTP écrit (RG8.13).
 */
final class SaveArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article
            ? ($this->user()?->can('update', $article) ?? false)
            : ($this->user()?->can('create', Article::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reference' => strtoupper(trim($this->string('reference')->toString()))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $article = $this->route('article');

        return [
            'reference' => ['required', 'string', 'regex:/^[A-Z]{3}-\d{3}$/', Rule::unique('articles', 'reference')->ignore($article instanceof Article ? $article->id : null)],
            'label' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', Rule::in(Article::CATEGORIES)],
            'unit' => ['required', Rule::in(Article::UNITS)],
            'price' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.regex' => 'Format attendu : trois lettres, un tiret, trois chiffres (ex. CIM-003).',
            'reference.unique' => 'Cette référence existe déjà.',
            'label.min' => 'Le libellé compte au moins 3 caractères.',
            'price.integer' => 'Le prix est un montant entier en FCFA, sans décimales.',
            'price.min' => 'Le prix doit être supérieur à zéro.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reference' => 'référence', 'label' => 'libellé', 'category' => 'catégorie', 'unit' => 'unité', 'price' => 'prix unitaire'];
    }

    /**
     * @return array{reference: string, label: string, category: string, unit: string, price: int}
     */
    public function articleData(): array
    {
        return [
            'reference' => $this->string('reference')->toString(),
            'label' => $this->string('label')->toString(),
            'category' => $this->string('category')->toString(),
            'unit' => $this->string('unit')->toString(),
            'price' => $this->integer('price'),
        ];
    }
}
