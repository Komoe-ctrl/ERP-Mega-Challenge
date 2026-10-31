<?php

declare(strict_types=1);

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'un client. L'autorisation vient de la ClientPolicy.
 */
final class SaveClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $client = $this->route('client');

        if (! $user instanceof User) {
            return false;
        }

        return $client instanceof Client ? $user->can('update', $client) : $user->can('create', Client::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'type' => ['required', Rule::in(Client::TYPES)],
            'contact' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s]{8,18}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Numéro invalide (ex. +225 07 00 00 00 00).',
            'name.min' => 'Le nom doit comporter au moins 3 caractères.',
        ];
    }

    /**
     * @return array{name: string, type: string, contact: string|null, phone: string|null, email: string|null, city: string|null, address: string|null}
     */
    public function clientData(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->toString()) : null;

        return [
            'name' => trim($this->string('name')->toString()),
            'type' => $this->string('type')->toString(),
            'contact' => $text('contact'),
            'phone' => $text('phone'),
            'email' => $text('email'),
            'city' => $text('city'),
            'address' => $text('address'),
        ];
    }
}
