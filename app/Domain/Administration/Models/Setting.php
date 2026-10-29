<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Policies\SettingPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Paramètre applicatif clé/valeur typé (RG1.3).
 *
 * Lecture : `Setting::get('budget.warning_percent')` renvoie la valeur déjà
 * convertie (int, bool, float, array ou string). La valeur est mise en cache
 * et le cache est vidé à chaque modification.
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string $label
 * @property string|null $description
 */
#[Fillable(['group', 'key', 'value', 'type', 'label', 'description'])]
#[UsePolicy(SettingPolicy::class)]
class Setting extends Model
{
    use LogsActivity;

    public const TYPES = ['string', 'integer', 'decimal', 'boolean', 'json'];

    private const CACHE_KEY = 'erp.settings';

    protected static function booted(): void
    {
        $flush = static fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('administration')
            ->logOnly(['key', 'value'])
            ->logOnlyDirty();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var array<string, array{value: string|null, type: string}> $all */
        $all = Cache::rememberForever(self::CACHE_KEY, static fn (): array => self::query()
            ->get(['key', 'value', 'type'])
            ->mapWithKeys(static fn (self $s): array => [$s->key => ['value' => $s->value, 'type' => $s->type]])
            ->all());

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        return self::cast($all[$key]['value'], $all[$key]['type']);
    }

    public static function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            'string' => $value,
            default => throw new InvalidArgumentException("Type de paramètre inconnu : {$type}"),
        };
    }

    /** Valeur typée du paramètre courant. */
    public function typedValue(): mixed
    {
        return self::cast($this->value, $this->type);
    }
}
