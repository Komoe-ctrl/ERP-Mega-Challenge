<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stocks;

use App\Domain\Achats\Models\Article;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Stocks\Actions\DecideStockMovement;
use App\Domain\Stocks\Actions\RecordStockMovement;
use App\Domain\Stocks\Enums\MovementType;
use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stocks\StoreMovementRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mouvements de stock (M9) : liste, saisie, validation des pertes et ajustements (RG9.3).
 */
final class MovementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view', StockMovement::class);
        $user = $this->user($request);
        $type = $request->string('type')->toString();
        $warehouse = $request->integer('magasin');

        $movements = StockMovement::query()
            ->with(['article', 'warehouse', 'destination', 'chantier', 'author'])
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($warehouse > 0, fn ($q) => $q->where(fn ($w) => $w->where('warehouse_id', $warehouse)->orWhere('destination_warehouse_id', $warehouse)))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StockMovement $m): array => [
                'id' => $m->id,
                'date' => $m->created_at?->toDateString() ?? '',
                'type' => $m->type->value,
                'item' => $m->article instanceof Article ? $m->article->label : '',
                'unit' => $m->article instanceof Article ? $m->article->unit : '',
                'quantity' => $m->qty(),
                'direction' => $m->direction,
                'warehouse' => $m->warehouse instanceof Warehouse ? $m->warehouse->name : '',
                'destination' => $m->destination instanceof Warehouse ? $m->destination->name : null,
                'chantier' => $m->chantier instanceof Chantier ? $m->chantier->name : null,
                'reason' => $m->reason,
                'status' => $m->status,
                'author' => $m->author instanceof User ? $m->author->name : 'Système',
                'unitCost' => $m->unit_cost,
                'canDecide' => $m->status === StockMovement::PENDING && $m->created_by !== $user->id && $user->can('validate', StockMovement::class),
            ]);

        $canRecord = $user->can('record', StockMovement::class);

        return Inertia::render('Stocks/Mouvements', [
            'movements' => $movements,
            'filters' => ['type' => $type, 'magasin' => $warehouse > 0 ? (string) $warehouse : ''],
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'articles' => $canRecord ? Article::query()->where('is_active', true)->orderBy('label')->get(['id', 'label', 'unit']) : [],
            'chantiers' => $canRecord ? Chantier::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']) : [],
            'types' => array_values(array_map(fn (MovementType $t): array => ['value' => $t->value, 'label' => $t->label()], array_filter(MovementType::cases(), fn (MovementType $t): bool => $t !== MovementType::Inventaire))),
            'can' => ['record' => $canRecord],
        ]);
    }

    public function store(StoreMovementRequest $request, RecordStockMovement $action): RedirectResponse
    {
        $movement = $action->execute($request->movementData(), $this->user($request));

        $this->toast($movement->status === StockMovement::PENDING
            ? $movement->type->label().' enregistrée : en attente de validation (RG9.3).'
            : $movement->type->label().' enregistrée.');

        return back();
    }

    public function validateMovement(Request $request, StockMovement $movement, DecideStockMovement $action): RedirectResponse
    {
        $this->authorize('validate', StockMovement::class);
        $action->validate($movement, $this->user($request));

        $this->toast('Mouvement validé : le stock est ajusté.');

        return back();
    }

    public function reject(Request $request, StockMovement $movement, DecideStockMovement $action): RedirectResponse
    {
        $this->authorize('validate', StockMovement::class);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $action->reject($movement, $this->user($request), $validated['reason']);

        $this->toast('Mouvement rejeté.');

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
