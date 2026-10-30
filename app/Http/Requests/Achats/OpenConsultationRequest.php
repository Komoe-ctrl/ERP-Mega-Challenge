<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;

final class OpenConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Consultation::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'purchase_request_id' => ['required', 'integer', 'exists:purchase_requests,id'],
            'deadline' => ['required', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_request_id.required' => 'Choisissez une demande validée.',
            'deadline.required' => 'Indiquez la date limite de réponse.',
            'deadline.after_or_equal' => 'La date limite ne peut pas être dans le passé.',
        ];
    }
}
