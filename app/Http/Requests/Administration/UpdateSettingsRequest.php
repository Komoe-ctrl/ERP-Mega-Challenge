<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Domain\Administration\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valeurs des paramètres (RG1.3). Les règles dépendent du type et de la clé :
 * pourcentages entre 0 et 100, montants et durées entiers, numérotation avec
 * un compteur {SEQ:n}, au moins trois fournisseurs consultés (RG8.11).
 *
 * Les clés de paramètres contiennent des points, que le validateur lit comme
 * une imbrication : on les encode en `__` (budget.warning_percent devient
 * budget__warning_percent) avant validation, y compris dans les erreurs.
 */
final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', new Setting) ?? false;
    }

    public static function encode(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach ((array) $this->input('values', []) as $key => $value) {
            $values[self::encode((string) $key)] = $value;
        }

        $this->merge(['values' => $values]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['values' => ['required', 'array']];

        foreach (Setting::query()->get(['key', 'type']) as $setting) {
            $rules['values.'.self::encode($setting->key)] = $this->rulesFor($setting->key, $setting->type);
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $values = (array) $this->input('values', []);
            $warning = $values['budget__warning_percent'] ?? null;
            $critical = $values['budget__critical_percent'] ?? null;

            if (is_numeric($warning) && is_numeric($critical) && (float) $critical < (float) $warning) {
                $validator->errors()->add(
                    'values.budget__critical_percent',
                    'L\'alerte critique doit être supérieure ou égale à l\'avertissement.',
                );
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.*.required' => 'Ce paramètre est obligatoire.',
            'values.*.integer' => 'Saisissez un nombre entier.',
            'values.*.numeric' => 'Saisissez un nombre positif.',
            'values.*.min' => 'La valeur est trop petite.',
            'values.*.max' => 'La valeur est trop grande.',
            'values.*.regex' => 'Le format doit contenir un compteur, ex. {SEQ:5}.',
        ];
    }

    /**
     * Valeurs validées, indexées par clé de paramètre.
     *
     * @return array<string, string>
     */
    public function settings(): array
    {
        $values = (array) $this->input('values', []);

        $out = [];
        foreach (Setting::query()->get(['key']) as $setting) {
            $key = $setting->key;
            if (array_key_exists(self::encode($key), $values) && is_scalar($values[self::encode($key)])) {
                $out[$key] = (string) $values[self::encode($key)];
            }
        }

        return $out;
    }

    /**
     * @return array<int, mixed>
     */
    private function rulesFor(string $key, string $type): array
    {
        if (str_starts_with($key, 'numbering.')) {
            return ['required', 'string', 'max:100', 'regex:/\{SEQ:\d+\}/'];
        }

        $percent = str_ends_with($key, '_percent') || $key === 'tax.vat_rate';
        $base = $type === 'integer' ? ['required', 'integer'] : ['required', 'numeric'];

        return match (true) {
            $percent => [...$base, 'min:0', 'max:100'],
            $key === 'validation.consultation_min_suppliers' => [...$base, 'min:3', 'max:50'],
            $key === 'documents.max_size_mb' => [...$base, 'min:1', 'max:200'],
            default => [...$base, 'min:0'],
        };
    }
}
