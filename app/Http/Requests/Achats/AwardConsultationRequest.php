<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\Consultation;
use Illuminate\Foundation\Http\FormRequest;

final class AwardConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $consultation = $this->route('consultation');

        return $consultation instanceof Consultation && ($this->user()?->can('update', $consultation) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['supplier_id' => ['required', 'integer', 'exists:suppliers,id']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['supplier_id.required' => 'Choisissez le fournisseur à retenir.'];
    }
}
