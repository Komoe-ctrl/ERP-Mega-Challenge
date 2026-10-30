<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Models;

use App\Domain\Fournisseurs\Policies\SupplierPolicy;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Fournisseur (M3). Jamais supprimé : désactivé, pour conserver son historique.
 *
 * @property int $id
 * @property string $number
 * @property string $name
 * @property string $contact
 * @property string $phone
 * @property string|null $email
 * @property string $city
 * @property bool $is_active
 */
#[Fillable(['number', 'name', 'contact', 'phone', 'email', 'city', 'is_active'])]
#[UsePolicy(SupplierPolicy::class)]
#[UseFactory(SupplierFactory::class)]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, LogsActivity;

    public const CATEGORIES = ['Matériaux', 'Équipements', 'Engins', 'Transport', 'Sous-traitance', 'Prestations techniques'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fournisseurs')
            ->logOnly(['number', 'name', 'contact', 'phone', 'email', 'city', 'is_active'])
            ->logOnlyDirty();
    }

    /**
     * Catégories du fournisseur (RG3.1).
     *
     * @return list<string>
     */
    public function categoryNames(): array
    {
        $names = [];

        foreach ($this->categories as $category) {
            $names[] = $category->category;
        }

        return $names;
    }

    /** @return HasMany<SupplierCategory, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(SupplierCategory::class)->orderBy('id');
    }

    /** @return HasMany<SupplierTariff, $this> */
    public function tariffs(): HasMany
    {
        return $this->hasMany(SupplierTariff::class);
    }
}
