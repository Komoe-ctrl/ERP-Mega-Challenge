<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Actions;

use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Création et modification d'un fournisseur, avec ses catégories (RG3.1).
 * Le numéro FRS-NNNN est attribué dans la transaction avec verrou.
 */
final class SaveSupplier
{
    /**
     * @param  array{name: string, contact: string, phone: string, email: string|null, city: string, categories: list<string>}  $data
     */
    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data): Supplier {
            $last = Supplier::query()->lockForUpdate()->orderByDesc('number')->value('number');
            $sequence = is_string($last) ? ((int) substr($last, -4)) + 1 : 1;

            $supplier = Supplier::query()->create([
                'number' => sprintf('FRS-%04d', $sequence),
                'name' => $data['name'],
                'contact' => $data['contact'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'city' => $data['city'],
            ]);
            $this->syncCategories($supplier, $data['categories']);

            return $supplier;
        });
    }

    /**
     * @param  array{name: string, contact: string, phone: string, email: string|null, city: string, categories: list<string>}  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data): Supplier {
            $supplier->update([
                'name' => $data['name'],
                'contact' => $data['contact'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'city' => $data['city'],
            ]);
            $this->syncCategories($supplier, $data['categories']);

            return $supplier;
        });
    }

    /** @param list<string> $categories */
    private function syncCategories(Supplier $supplier, array $categories): void
    {
        $supplier->categories()->whereNotIn('category', $categories)->delete();

        foreach (array_unique($categories) as $category) {
            $supplier->categories()->firstOrCreate(['category' => $category]);
        }

        $supplier->unsetRelation('categories');
    }
}
