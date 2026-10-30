<?php

declare(strict_types=1);

namespace App\Http\Requests\Projets;

use App\Domain\Projets\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? ($this->user()?->can('update', $project) ?? false)
            : ($this->user()?->can('create', Project::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:5', 'max:255'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'location' => ['required', 'string', 'max:255'],
            'manager_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'budget' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Le nom compte au moins 5 caractères.',
            'budget.integer' => 'Le budget est un montant entier en FCFA, sans décimales.',
            'budget.min' => 'Le budget doit être supérieur à zéro.',
            'end_date.after_or_equal' => 'La fin doit être postérieure au début.',
            'manager_id.exists' => 'Le responsable doit être un utilisateur actif.',
            'agency_id.exists' => 'L\'agence doit être active.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'client_id' => 'client',
            'location' => 'localisation',
            'manager_id' => 'responsable',
            'agency_id' => 'agence',
            'budget' => 'budget',
            'start_date' => 'début',
            'end_date' => 'fin prévue',
        ];
    }

    /**
     * @return array{name: string, client_id: int, location: string, manager_id: int, agency_id: int, budget: int, start_date: string, end_date: string}
     */
    public function projectData(): array
    {
        /** @var array{name: string, client_id: int, location: string, manager_id: int, agency_id: int, budget: int, start_date: string, end_date: string} $data */
        $data = $this->validated();

        return $data;
    }
}
