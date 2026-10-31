<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stocks;

use App\Domain\Achats\Models\Article;
use App\Domain\Stocks\Actions\ManageInventory;
use App\Domain\Stocks\Models\Inventory;
use App\Domain\Stocks\Models\InventoryLine;
use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inventaires (CA9) : écart entre stock théorique et stock compté, par article, ajusté après validation.
 */
final class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view', StockMovement::class);
        $user = $this->user($request);

        $inventories = Inventory::query()->with('warehouse')->latest('id')->limit(30)->get();
        $selectedId = $request->integer('inventaire') ?: $inventories->first()?->id;
        $selected = $selectedId ? Inventory::query()->with(['lines.article', 'warehouse', 'opener'])->find($selectedId) : null;

        return Inertia::render('Stocks/Inventaire', [
            'inventories' => $inventories->map(fn (Inventory $i): array => [
                'id' => $i->id,
                'number' => $i->number,
                'warehouse' => $i->warehouse instanceof Warehouse ? $i->warehouse->name : '',
                'status' => $i->status,
            ])->values(),
            'selected' => $selected === null ? null : [
                'id' => $selected->id,
                'number' => $selected->number,
                'warehouse' => $selected->warehouse instanceof Warehouse ? $selected->warehouse->name : '',
                'status' => $selected->status,
                'openedBy' => $selected->opener instanceof User ? $selected->opener->name : '',
                'canValidate' => $selected->status === Inventory::OPEN && $selected->opened_by !== $user->id && $user->can('validate', StockMovement::class),
                'lines' => $selected->lines->map(fn (InventoryLine $l): array => [
                    'id' => $l->id,
                    'label' => $l->article instanceof Article ? $l->article->label : '',
                    'unit' => $l->article instanceof Article ? $l->article->unit : '',
                    'theoretical' => (float) $l->theoretical,
                    'counted' => $l->counted === null ? null : (float) $l->counted,
                    'gap' => $l->counted === null ? null : $l->gap(),
                ])->values(),
            ],
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can' => ['record' => $user->can('record', StockMovement::class)],
        ]);
    }

    public function store(Request $request, ManageInventory $action): RedirectResponse
    {
        $this->authorize('record', StockMovement::class);
        $validated = $request->validate(['warehouse_id' => ['required', 'integer', 'exists:warehouses,id']]);

        $inventory = $action->open(Warehouse::query()->findOrFail((int) $validated['warehouse_id']), $this->user($request));
        $this->toast("Inventaire {$inventory->number} ouvert : le stock théorique est figé.");

        return to_route('stocks.inventaire', ['inventaire' => $inventory->id]);
    }

    public function count(Request $request, Inventory $inventory, ManageInventory $action): RedirectResponse
    {
        $this->authorize('record', StockMovement::class);
        $validated = $request->validate(['counted' => ['required', 'array'], 'counted.*' => ['nullable', 'numeric', 'min:0', 'max:99999999']]);

        /** @var array<int, float|int|string|null> $counted */
        $counted = $validated['counted'];
        $action->count($inventory, $counted);
        $this->toast('Quantités comptées enregistrées.');

        return back();
    }

    public function validateInventory(Request $request, Inventory $inventory, ManageInventory $action): RedirectResponse
    {
        $this->authorize('validate', StockMovement::class);
        $action->validate($inventory, $this->user($request));

        $this->toast('Inventaire validé : le stock est ajusté sur les écarts.');

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
