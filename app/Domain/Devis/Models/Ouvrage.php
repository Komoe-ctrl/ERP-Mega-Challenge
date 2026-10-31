<?php

declare(strict_types=1);

namespace App\Domain\Devis\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ouvrage de la bibliothèque (RG5.1) : son prix se calcule depuis sa composition.
 *
 * @property int $id
 * @property string $label
 * @property string $unit
 * @property bool $is_active
 */
#[Fillable(['label', 'unit', 'is_active'])]
class Ouvrage extends Model
{
    /** Coefficient de vente : frais généraux et marge appliqués au déboursé sec. */
    public const SALE_COEFFICIENT = 1.25;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<OuvrageComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(OuvrageComponent::class);
    }

    /** Déboursé sec unitaire : somme du coût de ses composants. */
    public function cost(): int
    {
        return (int) $this->components->sum(fn (OuvrageComponent $c): int => $c->cost());
    }

    /** Prix de vente unitaire, arrondi à la centaine de FCFA. */
    public function price(): int
    {
        return (int) (round($this->cost() * self::SALE_COEFFICIENT / 100) * 100);
    }

    public function composition(): string
    {
        return $this->components
            ->map(fn (OuvrageComponent $c): string => $c->article !== null ? explode(' — ', $c->article->label)[0] : (string) $c->label)
            ->implode(', ');
    }
}
