<?php

declare(strict_types=1);

namespace App\Http\Requests\Chantiers;

use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $chantier = $this->route('chantier');

        return $chantier instanceof Chantier && ($this->user()?->can('update', $chantier) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'role' => ['required', Rule::in(Chantier::TEAM_ROLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['user_id.exists' => 'Choisissez un utilisateur actif.', 'role.in' => 'Rôle d\'affectation inconnu.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['user_id' => 'membre', 'role' => 'rôle'];
    }
}
