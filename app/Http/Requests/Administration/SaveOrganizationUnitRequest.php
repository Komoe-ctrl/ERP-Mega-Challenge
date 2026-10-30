<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une agence, d'un service ou d'un site. Le type d'élément vient de
 * la route (`agencies`, `services`, `sites`).
 */
final class SaveOrganizationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', $this->modelClass()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim($this->string('code')->toString()))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $table = $this->table();
        $code = $table === 'sites' ? '/^[A-Z0-9-]{3,12}$/' : '/^[A-Z]{2,6}$/';

        return [
            'code' => ['required', 'string', "regex:{$code}", "unique:{$table},code"],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'city' => $table === 'agencies' ? ['required', 'string', 'max:255'] : ['prohibited'],
            'agency_id' => $table === 'sites' ? ['required', 'integer', 'exists:agencies,id'] : ['prohibited'],
            'type' => $table === 'sites' ? ['required', 'in:'.implode(',', Site::TYPES)] : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => $this->table() === 'sites'
                ? 'Le code compte 3 à 12 caractères (lettres, chiffres, tiret).'
                : 'Le code compte 2 à 6 lettres.',
            'code.unique' => 'Ce code existe déjà.',
            'name.min' => 'Le nom compte au moins 3 caractères.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'code', 'name' => 'nom', 'city' => 'ville', 'agency_id' => 'agence', 'type' => 'type'];
    }

    /** @return class-string<Agency|Service|Site> */
    private function modelClass(): string
    {
        return match ($this->table()) {
            'agencies' => Agency::class,
            'services' => Service::class,
            default => Site::class,
        };
    }

    private function table(): string
    {
        return $this->routeIs('administration.agencies.*') ? 'agencies'
            : ($this->routeIs('administration.services.*') ? 'services' : 'sites');
    }
}
