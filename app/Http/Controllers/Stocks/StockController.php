<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stocks;

use App\Domain\Achats\Models\Article;
use App\Domain\Stocks\Models\StockLevel;
use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * État des stocks (M9) : quantités par magasin et article, valorisation au coût moyen pondéré (RG9.5),
 * articles sous le seuil minimal (RG9.4).
 */
final class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view', StockMovement::class);
        $warehouseId = $request->integer('magasin');

        $levels = StockLevel::query()
            ->with(['article', 'warehouse'])
            ->when($warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get()
            ->map(fn (StockLevel $l): array => [
                'id' => $l->id,
                'articleId' => $l->article_id,
                'reference' => $l->article instanceof Article ? $l->article->reference : '',
                'label' => $l->article instanceof Article ? $l->article->label : '',
                'family' => $l->article instanceof Article ? $l->article->category : '',
                'unit' => $l->article instanceof Article ? $l->article->unit : '',
                'warehouse' => $l->warehouse instanceof Warehouse ? $l->warehouse->name : '',
                'warehouseId' => $l->warehouse_id,
                'quantity' => $l->qty(),
                'minimum' => (float) $l->minimum,
                'averageCost' => $l->average_cost,
                'value' => $l->value(),
                'low' => $l->isLow(),
            ])
            ->sortBy([['warehouse', 'asc'], ['label', 'asc']])
            ->values();

        return Inertia::render('Stocks/Index', [
            'items' => $levels,
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'filters' => ['magasin' => $warehouseId > 0 ? (string) $warehouseId : ''],
            'totals' => [
                'value' => $levels->sum('value'),
                'low' => $levels->where('low', true)->count(),
                'items' => $levels->count(),
            ],
            'can' => ['configure' => $this->user($request)->can('configure', StockMovement::class)],
        ]);
    }

    /** RG9.4 : seuil minimal par article et par magasin. */
    public function setMinimum(Request $request, StockLevel $level): RedirectResponse
    {
        $this->authorize('configure', StockMovement::class);
        $validated = $request->validate(['minimum' => ['required', 'numeric', 'min:0', 'max:99999999']]);

        $level->update(['minimum' => round((float) $validated['minimum'], 3)]);
        $this->toast('Seuil minimal enregistré.');

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
