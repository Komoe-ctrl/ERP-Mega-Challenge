<?php

declare(strict_types=1);

namespace App\Http\Requests\Fournisseurs;

use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fiche fournisseur : raison sociale unique, contact, téléphone, ville et au
 * moins une catégorie (RG3.1).
 */
final class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            ? ($this->user()?->can('update', $supplier) ?? false)
            : ($this->user()?->can('create', Supplier::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim($this->string('name')->toString())]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier instanceof Supplier ? $supplier->id : null)],
            'contact' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[\d\s]{8,18}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'categories' => ['required', 'array', 'min:1', 'max:'.count(Supplier::CATEGORIES)],
            'categories.*' => ['string', Rule::in(Supplier::CATEGORIES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ce fournisseur existe déjà.',
            'name.min' => 'La raison sociale compte au moins 3 caractères.',
            'phone.regex' => 'Numéro invalide (ex. +225 07 00 00 00 00).',
            'categories.required' => 'Choisissez au moins une catégorie.',
            'categories.min' => 'Choisissez au moins une catégorie.',
            'categories.*.in' => 'Catégorie inconnue.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'raison sociale', 'contact' => 'contact', 'phone' => 'téléphone', 'city' => 'ville', 'email' => 'e-mail'];
    }

    /**
     * @return array{name: string, contact: string, phone: string, email: string|null, city: string, categories: list<string>}
     */
    public function supplierData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'contact' => trim($this->string('contact')->toString()),
            'phone' => trim($this->string('phone')->toString()),
            'email' => $this->filled('email') ? trim($this->string('email')->toString()) : null,
            'city' => trim($this->string('city')->toString()),
            'categories' => array_values(array_unique(array_filter((array) $this->input('categories', []), 'is_string'))),
        ];
    }
}
