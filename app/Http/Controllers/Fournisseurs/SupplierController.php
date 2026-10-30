<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fournisseurs;

use App\Domain\Achats\Models\Article;
use App\Domain\Fournisseurs\Actions\SaveSupplier;
use App\Domain\Fournisseurs\Actions\SetSupplierActive;
use App\Domain\Fournisseurs\Actions\SetSupplierTariff;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierTariff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fournisseurs\SaveSupplierRequest;
use App\Http\Requests\Fournisseurs\SetTariffRequest;
use App\Http\Requests\ReasonRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fournisseurs (M3) : fiche, catégories (RG3.1), tarifs (RG3.2). Jamais de
 * suppression : désactivation motivée. Les commandes, factures et évaluations
 * (RG3.3, CA3) arrivent avec le processus d'achat complet.
 */
final class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'category' => $request->string('category')->toString(),
            'state' => $request->string('state')->toString(),
        ];

        $suppliers = Supplier::query()
            ->with('categories')
            ->when($filters['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$filters['q']}%")->orWhere('city', 'like', "%{$filters['q']}%")->orWhere('contact', 'like', "%{$filters['q']}%"),
            ))
            ->when($filters['category'] !== '', fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('category', $filters['category'])))
            ->when($filters['state'] === 'actif', fn ($q) => $q->where('is_active', true))
            ->when($filters['state'] === 'inactif', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Supplier $s): array => $this->present($s));

        return Inertia::render('Fournisseurs/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'categories' => Supplier::CATEGORIES,
        ]);
    }

    public function store(SaveSupplierRequest $request, SaveSupplier $action): RedirectResponse
    {
        $supplier = $action->create($request->supplierData());
        $this->toast('Fournisseur créé.');

        return to_route('fournisseurs.show', $supplier);
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);
        $supplier->load('categories');
        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('Fournisseurs/Show', [
            'supplier' => $this->present($supplier),
            'tariffs' => $supplier->tariffs()->with('article')->get()
                ->map(fn (SupplierTariff $t): array => [
                    'id' => $t->id,
                    'articleId' => $t->article_id,
                    'label' => $this->articleText($t, 'label'),
                    'unit' => $this->articleText($t, 'unit'),
                    'referencePrice' => $t->article instanceof Article ? $t->article->price : 0,
                    'price' => $t->price,
                ])->sortBy('label')->values(),
            'articles' => Article::query()->where('is_active', true)->orderBy('label')->get()
                ->map(fn (Article $a): array => ['value' => (string) $a->id, 'label' => $a->label.' ('.$a->unit.')'])->values(),
            'categories' => Supplier::CATEGORIES,
            'canUpdate' => $user->can('update', $supplier),
        ]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier, SaveSupplier $action): RedirectResponse
    {
        $action->update($supplier, $request->supplierData());
        $this->toast('Fournisseur mis à jour.');

        return back();
    }

    public function deactivate(ReasonRequest $request, Supplier $supplier, SetSupplierActive $action): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $action->execute($supplier, false, $this->user($request), $request->string('reason')->toString());
        $this->toast('Fournisseur désactivé.');

        return back();
    }

    public function reactivate(Request $request, Supplier $supplier, SetSupplierActive $action): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $action->execute($supplier, true, $this->user($request));
        $this->toast('Fournisseur réactivé.');

        return back();
    }

    public function setTariff(SetTariffRequest $request, Supplier $supplier, SetSupplierTariff $action): RedirectResponse
    {
        $action->execute($supplier, $request->integer('article_id'), $request->integer('price'));
        $this->toast('Tarif enregistré.');

        return back();
    }

    private function articleText(SupplierTariff $tariff, string $attribute): string
    {
        $value = data_get($tariff, "article.{$attribute}");

        return is_string($value) ? $value : '';
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Supplier $s): array
    {
        return [
            'id' => $s->id,
            'number' => $s->number,
            'name' => $s->name,
            'categories' => $s->categoryNames(),
            'contact' => $s->contact,
            'phone' => $s->phone,
            'email' => $s->email,
            'city' => $s->city,
            'active' => $s->is_active,
            // Note moyenne, commandes en cours et factures impayées : branchées avec le processus d'achat (V2).
            'rating' => 0,
            'openOrders' => 0,
            'unpaidInvoices' => 0,
        ];
    }
}
