<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Domain\Administration\Data\RoleData;
use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('role');

        return $target instanceof Role
            ? ($this->user()?->can('update', $target) ?? false)
            : ($this->user()?->can('create', Role::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->ignore($target instanceof Role ? $target->id : null),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Module::allPermissions())],
        ];
    }

    public function toData(): RoleData
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        return new RoleData(
            name: $v['name'],
            permissions: array_values($v['permissions'] ?? []),
        );
    }
}
