<?php

declare(strict_types=1);

namespace App\Http\Requests\Chantiers;

use App\Domain\Chantiers\Models\Chantier;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTaskRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required_unless:is_milestone,true,1', 'nullable', 'date', 'after_or_equal:start_date'],
            'weight' => ['required', 'integer', 'min:0', 'max:100'],
            'is_milestone' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['end_date.after_or_equal' => 'La fin doit être postérieure au début.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'tâche', 'start_date' => 'début', 'end_date' => 'fin prévue', 'weight' => 'pondération'];
    }

    /**
     * @return array{name: string, start_date: string, end_date: string, weight: int, is_milestone: bool}
     */
    public function taskData(): array
    {
        $milestone = $this->boolean('is_milestone');

        return [
            'name' => $this->string('name')->toString(),
            'start_date' => $this->string('start_date')->toString(),
            'end_date' => $this->string($milestone ? 'start_date' : 'end_date')->toString(),
            'weight' => $this->integer('weight'),
            'is_milestone' => $milestone,
        ];
    }
}
