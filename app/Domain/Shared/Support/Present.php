<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

/**
 * Lecture typée d'une valeur d'un modèle par chemin pointé (ex. « order.supplier.name »),
 * pour les données affichées : texte vide ou 0 quand la relation est absente.
 */
final class Present
{
    public static function text(mixed $model, string $path, string $default = ''): string
    {
        $value = data_get($model, $path);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function float(mixed $model, string $path): float
    {
        $value = data_get($model, $path);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function int(mixed $model, string $path): int
    {
        $value = data_get($model, $path);

        return is_numeric($value) ? (int) $value : 0;
    }
}
