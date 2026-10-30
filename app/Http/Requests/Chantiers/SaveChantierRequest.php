<?php

declare(strict_types=1);

namespace App\Http\Requests\Chantiers;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class SaveChantierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chantier = $this->route('chantier');

        return $chantier instanceof Chantier
            ? ($this->user()?->can('update', $chantier) ?? false)
            : ($this->user()?->can('create', Chantier::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $chantier = $this->route('chantier');
        $current = $chantier instanceof Chantier ? $chantier->id : null;

        $rules = [
            // RG7.1 : code unique, au format CH-001.
            'code' => ['required', 'string', 'regex:/^CH-\d{3,4}$/', Rule::unique('chantiers', 'code')->ignore($current)],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Chantier::STATUSES)],
            'conducteur_id' => ['required', 'integer', $this->userWithRole(RoleName::ConducteurTravaux)],
            'chef_id' => ['required', 'integer', $this->userWithRole(RoleName::ChefChantier)],
        ];

        // Le projet n'est choisi qu'à la création ; il ne change plus ensuite.
        if (! $chantier instanceof Chantier) {
            $rules['project_id'] = ['required', 'integer', 'exists:projects,id'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Format attendu : CH-001.',
            'code.unique' => 'Ce code est déjà utilisé (RG7.1).',
            'end_date.after_or_equal' => 'La fin doit être postérieure au début.',
            'conducteur_id.exists' => 'Choisissez un conducteur de travaux actif.',
            'chef_id.exists' => 'Choisissez un chef de chantier actif.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'project_id' => 'projet',
            'location' => 'localisation',
            'start_date' => 'début',
            'end_date' => 'fin prévue',
            'status' => 'statut',
            'conducteur_id' => 'conducteur de travaux',
            'chef_id' => 'chef de chantier',
        ];
    }

    /**
     * @return array{code: string, name: string, project_id: int, location: string, start_date: string, end_date: string, status: string, conducteur_id: int, chef_id: int}
     */
    public function chantierData(): array
    {
        $chantier = $this->route('chantier');
        /** @var array{code: string, name: string, project_id?: int, location: string, start_date: string, end_date: string, status: string, conducteur_id: int, chef_id: int} $data */
        $data = $this->validated();
        $data['project_id'] ??= $chantier instanceof Chantier ? $chantier->project_id : 0;

        return $data;
    }

    /** Un utilisateur actif portant le rôle demandé. */
    private function userWithRole(RoleName $role): Exists
    {
        return Rule::exists('users', 'id')
            ->where('is_active', true)
            ->whereIn('id', User::query()->role($role->value)->pluck('id')->all());
    }
}
