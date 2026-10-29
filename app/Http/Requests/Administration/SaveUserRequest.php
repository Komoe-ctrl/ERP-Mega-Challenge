<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Domain\Administration\Data\UserData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Création et modification d'un utilisateur. Sur une modification, la route
 * porte le paramètre `user` et le mot de passe devient facultatif.
 */
final class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? ($this->user()?->can('update', $target) ?? false)
            : ($this->user()?->can('create', User::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $isUpdate = $target instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($isUpdate ? $target->id : null),
            ],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', Password::default()],
        ];
    }

    public function toData(): UserData
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        return new UserData(
            name: $v['name'],
            email: $v['email'],
            agencyId: isset($v['agency_id']) ? (int) $v['agency_id'] : null,
            serviceId: isset($v['service_id']) ? (int) $v['service_id'] : null,
            phone: $v['phone'] ?? null,
            isActive: (bool) $v['is_active'],
            roles: array_values($v['roles'] ?? []),
            password: $v['password'] ?? null,
        );
    }
}
