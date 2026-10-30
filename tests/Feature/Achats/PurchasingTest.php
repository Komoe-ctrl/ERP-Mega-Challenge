<?php

declare(strict_types=1);

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\Consultation;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Administration\Models\Setting;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierTariff;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

function purchasingBuyer(): User
{
    return userWithRole(RoleName::ServiceAchatsBtp);
}

/**
 * Demande validée de lignes [prix figé => quantité], sur un chantier donné ou nouveau.
 *
 * @param  array<int, int|float>  $priceToQuantity
 */
function validatedRequest(array $priceToQuantity, ?Chantier $chantier = null, string $category = 'Ciment'): PurchaseRequest
{
    $request = PurchaseRequest::factory()->withStatus('validee')->create($chantier ? ['chantier_id' => $chantier->id] : []);

    foreach ($priceToQuantity as $price => $quantity) {
        $request->lines()->create([
            'article_id' => Article::factory()->create(['price' => $price, 'category' => $category])->id,
            'quantity' => $quantity,
            'need_date' => '2026-12-01',
            'urgency' => 'normale',
            'frozen_price' => $price,
        ]);
    }

    return $request->load('lines.article');
}

function materialsSupplier(?string $name = null): Supplier
{
    return Supplier::factory()->inCategories('Matériaux')->create($name ? ['name' => $name] : []);
}

// ------------------------------------------------------------------ ouverture d'une consultation

it('RG8.11 : une consultation sous le seuil se contente d\'une offre, au-delà il en faut trois', function () {
    $this->seed(SettingsSeeder::class);
    $buyer = purchasingBuyer();
    $small = validatedRequest([10_000 => 100]); // 1 M < 2 M
    $large = validatedRequest([10_000 => 300]); // 3 M > 2 M

    $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $small->id, 'deadline' => now()->addDays(5)->toDateString()])->assertSessionHasNoErrors();
    $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $large->id, 'deadline' => now()->addDays(5)->toDateString()])->assertSessionHasNoErrors();

    $year = now()->year;
    $first = Consultation::query()->orderBy('id')->firstOrFail();
    expect($first->number)->toBe("CS-{$year}-0001")
        ->and($first->min_suppliers)->toBe(1)
        ->and(Consultation::query()->orderByDesc('id')->firstOrFail()->min_suppliers)->toBe(3);
});

it('le seuil et le minimum de fournisseurs viennent des paramètres', function () {
    $this->seed(SettingsSeeder::class);
    Setting::query()->where('key', 'validation.consultation_threshold_fcfa')->update(['value' => '500000']);
    Setting::query()->where('key', 'validation.consultation_min_suppliers')->update(['value' => '4']);
    Cache::forget('erp.settings');
    $request = validatedRequest([10_000 => 100]); // 1 M > 500 000

    $this->actingAs(purchasingBuyer())->post(route('achats.consultations.store'), ['purchase_request_id' => $request->id, 'deadline' => now()->toDateString()]);

    expect(Consultation::query()->firstOrFail()->min_suppliers)->toBe(4);
});

it('n\'ouvre une consultation que sur une demande validée, une seule fois', function () {
    $buyer = purchasingBuyer();
    $draft = PurchaseRequest::factory()->create();
    $validated = validatedRequest([10_000 => 10]);
    $payload = fn (PurchaseRequest $r): array => ['purchase_request_id' => $r->id, 'deadline' => now()->addDay()->toDateString()];

    $this->actingAs($buyer)->post(route('achats.consultations.store'), $payload($draft))->assertSessionHasErrors('purchase_request_id');
    $this->actingAs($buyer)->post(route('achats.consultations.store'), $payload($validated))->assertSessionHasNoErrors();
    $this->actingAs($buyer)->post(route('achats.consultations.store'), $payload($validated))->assertSessionHasErrors('purchase_request_id');
    $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $validated->id, 'deadline' => '2020-01-01'])->assertSessionHasErrors('deadline');

    expect(Consultation::query()->count())->toBe(1);
});

// ------------------------------------------------------------------ offres et fournisseurs éligibles (RG3.1)

it('RG3.1 : seuls les fournisseurs actifs de la bonne catégorie peuvent remettre une offre', function (callable $make, bool $accepted) {
    $request = validatedRequest([10_000 => 10]);
    $consultation = Consultation::query()->create(['number' => 'CS-X', 'purchase_request_id' => $request->id, 'chantier_id' => $request->chantier_id, 'deadline' => now()->addDay(), 'min_suppliers' => 1]);
    $supplier = $make();

    $this->actingAs(purchasingBuyer())->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => $supplier->id, 'total_amount' => 100_000, 'delay_days' => 3])
        ->assertSessionHas($accepted ? 'inertia.flash_data' : 'errors');

    expect($consultation->quotes()->count())->toBe($accepted ? 1 : 0);
})->with([
    'fournisseur de matériaux' => [fn () => Supplier::factory()->inCategories('Matériaux')->create(), true],
    'fournisseur d\'engins' => [fn () => Supplier::factory()->inCategories('Engins')->create(), false],
    'fournisseur désactivé' => [fn () => Supplier::factory()->inCategories('Matériaux')->create(['is_active' => false]), false],
]);

it('refuse une seconde offre du même fournisseur et des montants invalides', function () {
    $request = validatedRequest([10_000 => 10]);
    $consultation = Consultation::query()->create(['number' => 'CS-X', 'purchase_request_id' => $request->id, 'chantier_id' => $request->chantier_id, 'deadline' => now()->addDay(), 'min_suppliers' => 1]);
    $supplier = materialsSupplier();
    $buyer = purchasingBuyer();

    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => $supplier->id, 'total_amount' => 100_000, 'delay_days' => 3])->assertSessionHasNoErrors();
    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => $supplier->id, 'total_amount' => 90_000, 'delay_days' => 2])->assertSessionHasErrors('supplier_id');
    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => materialsSupplier()->id, 'total_amount' => 0, 'delay_days' => 2])->assertSessionHasErrors('total_amount');
    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => materialsSupplier()->id, 'total_amount' => 10.5, 'delay_days' => 2])->assertSessionHasErrors('total_amount');

    expect($consultation->quotes()->count())->toBe(1);
});

it('passe la consultation à « comparée » quand le nombre minimal d\'offres est atteint', function () {
    $this->seed(SettingsSeeder::class);
    $request = validatedRequest([10_000 => 300]);
    $buyer = purchasingBuyer();
    $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $request->id, 'deadline' => now()->addDay()->toDateString()]);
    $consultation = Consultation::query()->firstOrFail();

    foreach ([1, 2] as $i) {
        $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => materialsSupplier()->id, 'total_amount' => 3_000_000 + $i, 'delay_days' => 3]);
    }
    expect($consultation->refresh()->status)->toBe('ouverte');

    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => materialsSupplier()->id, 'total_amount' => 2_900_000, 'delay_days' => 4]);
    expect($consultation->refresh()->status)->toBe('comparee');
});

// ------------------------------------------------------------------ attribution (RG8.11)

it('RG8.11 : refuse d\'attribuer sans le nombre minimal d\'offres, sans fournisseur sans offre', function () {
    $this->seed(SettingsSeeder::class);
    $request = validatedRequest([10_000 => 300]);
    $buyer = purchasingBuyer();
    $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $request->id, 'deadline' => now()->addDay()->toDateString()]);
    $consultation = Consultation::query()->firstOrFail();
    $a = materialsSupplier();
    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => $a->id, 'total_amount' => 3_000_000, 'delay_days' => 3]);

    $this->actingAs($buyer)->post(route('achats.consultations.award', $consultation), ['supplier_id' => $a->id])->assertSessionHasErrors('supplier_id');
    expect(PurchaseOrder::query()->count())->toBe(0)->and($consultation->refresh()->status)->not->toBe('attribuee');

    foreach ([2_950_000, 3_100_000] as $total) {
        $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => materialsSupplier()->id, 'total_amount' => $total, 'delay_days' => 4]);
    }
    $this->actingAs($buyer)->post(route('achats.consultations.award', $consultation), ['supplier_id' => materialsSupplier()->id])->assertSessionHasErrors('supplier_id');
    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('attribue la consultation : le bon de commande reprend les quantités et le prix de l\'offre retenue', function () {
    $this->seed(SettingsSeeder::class);
    $request = validatedRequest([10_000 => 200, 5_000 => 200]); // 3 M
    $buyer = purchasingBuyer();
    $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $request->id, 'deadline' => now()->addDay()->toDateString()]);
    $consultation = Consultation::query()->firstOrFail();
    $winner = materialsSupplier('Gagnant SA');

    foreach ([[$winner, 2_700_000], [materialsSupplier(), 2_900_000], [materialsSupplier(), 3_100_000]] as [$supplier, $total]) {
        $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => $supplier->id, 'total_amount' => $total, 'delay_days' => 3]);
    }

    $this->actingAs($buyer)->post(route('achats.consultations.award', $consultation), ['supplier_id' => $winner->id])->assertSessionHasNoErrors();

    $order = PurchaseOrder::query()->with('lines')->firstOrFail();
    $year = now()->year;
    expect($order->number)->toBe("BC-{$year}-00001")
        ->and($order->supplier_id)->toBe($winner->id)
        ->and($order->consultation_id)->toBe($consultation->id)
        ->and($order->status)->toBe('emis')
        ->and($order->amount)->toBe(2_700_000)
        ->and($order->lines)->toHaveCount(2)
        ->and((float) $order->lines[0]->quantity)->toBe(200.0)
        ->and($consultation->refresh()->status)->toBe('attribuee')
        ->and($consultation->quotes()->where('is_selected', true)->value('supplier_id'))->toBe($winner->id);

    $this->actingAs($buyer)->post(route('achats.consultations.award', $consultation), ['supplier_id' => $winner->id])->assertForbidden();
});

// ------------------------------------------------------------------ bon de commande direct (RG8.8, RG8.11)

it('émet un bon de commande direct sous le seuil, au tarif du fournisseur quand il existe', function () {
    $this->seed(SettingsSeeder::class);
    $request = validatedRequest([10_000 => 100]); // 1 M
    $supplier = materialsSupplier();
    SupplierTariff::query()->create(['supplier_id' => $supplier->id, 'article_id' => $request->lines[0]->article_id, 'price' => 9_000]);

    $this->actingAs(purchasingBuyer())->post(route('achats.commandes.store'), ['supplier_id' => $supplier->id, 'purchase_request_id' => $request->id])->assertSessionHasNoErrors();

    $order = PurchaseOrder::query()->with('lines')->firstOrFail();
    expect($order->amount)->toBe(900_000)->and($order->lines[0]->unit_price)->toBe(9_000)->and($order->consultation_id)->toBeNull();
});

it('RG8.11 : refuse une commande directe au-delà du seuil, une consultation est obligatoire', function () {
    $this->seed(SettingsSeeder::class);
    $request = validatedRequest([10_000 => 300]); // 3 M

    $this->actingAs(purchasingBuyer())->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'purchase_request_id' => $request->id])->assertSessionHasErrors('lines');
    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('RG8.8 : une demande est servie par plusieurs commandes partielles, jamais au-delà de sa quantité', function () {
    $this->seed(SettingsSeeder::class);
    $request = validatedRequest([1_000 => 100]);
    $line = $request->lines[0];
    $buyer = purchasingBuyer();

    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'lines' => [['request_line_id' => $line->id, 'quantity' => 60]]])->assertSessionHasNoErrors();
    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'lines' => [['request_line_id' => $line->id, 'quantity' => 50]]])->assertSessionHasErrors('lines');
    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'purchase_request_id' => $request->id])->assertSessionHasNoErrors();

    $quantities = PurchaseOrder::query()->orderBy('id')->get()->map(fn (PurchaseOrder $o): float => (float) $o->lines()->first()->quantity)->all();
    expect($quantities)->toBe([60.0, 40.0]);

    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'purchase_request_id' => $request->id])->assertSessionHasErrors('lines');
});

it('RG8.8 : un bon regroupe plusieurs demandes d\'un même chantier, pas de chantiers différents', function () {
    $this->seed(SettingsSeeder::class);
    $chantier = Chantier::factory()->create();
    $a = validatedRequest([1_000 => 10], $chantier);
    $b = validatedRequest([2_000 => 10], $chantier);
    $other = validatedRequest([1_000 => 10]);
    $supplier = materialsSupplier();
    $buyer = purchasingBuyer();
    $mixed = fn (array $requests): array => ['supplier_id' => $supplier->id, 'lines' => collect($requests)->map(fn ($r): array => ['request_line_id' => $r->lines[0]->id, 'quantity' => 10])->all()];

    $this->actingAs($buyer)->post(route('achats.commandes.store'), $mixed([$a, $other]))->assertSessionHasErrors('lines');
    $this->actingAs($buyer)->post(route('achats.commandes.store'), $mixed([$a, $b]))->assertSessionHasNoErrors();

    $order = PurchaseOrder::query()->with('lines')->firstOrFail();
    expect($order->lines)->toHaveCount(2)->and($order->amount)->toBe(30_000)->and($order->chantier_id)->toBe($chantier->id);

    $this->actingAs($buyer)->get(route('achats.commandes.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.requestNumbers', [$a->number, $b->number])->where('order.amount', 30_000));
});

it('refuse une commande sur une demande non validée, en cours de consultation, ou chez un mauvais fournisseur', function () {
    $this->seed(SettingsSeeder::class);
    $buyer = purchasingBuyer();
    $draft = PurchaseRequest::factory()->withLines([1_000 => 10])->create();
    $consulted = validatedRequest([1_000 => 10]);
    Consultation::query()->create(['number' => 'CS-Y', 'purchase_request_id' => $consulted->id, 'chantier_id' => $consulted->chantier_id, 'deadline' => now()->addDay(), 'min_suppliers' => 1]);
    $plain = validatedRequest([1_000 => 10]);

    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'lines' => [['request_line_id' => $draft->lines()->first()->id, 'quantity' => 1]]])->assertSessionHasErrors('lines');
    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'purchase_request_id' => $consulted->id])->assertSessionHasErrors('lines');
    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => Supplier::factory()->inCategories('Engins')->create()->id, 'purchase_request_id' => $plain->id])->assertSessionHasErrors('supplier_id');
    $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => Supplier::factory()->inCategories('Matériaux')->create(['is_active' => false])->id, 'purchase_request_id' => $plain->id])->assertSessionHasErrors('supplier_id');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('RG1.3 : numérote les bons de commande selon le format paramétré, sans doublon', function () {
    $this->seed(SettingsSeeder::class);
    Setting::query()->where('key', 'numbering.bon_commande')->update(['value' => 'BCX-{YYYY}-{SEQ:3}']);
    Cache::forget('erp.settings');
    $buyer = purchasingBuyer();

    foreach ([1, 2, 3] as $i) {
        $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'purchase_request_id' => validatedRequest([1_000 => 10])->id]);
    }

    $year = now()->year;
    expect(PurchaseOrder::query()->orderBy('id')->pluck('number')->all())->toBe(["BCX-{$year}-001", "BCX-{$year}-002", "BCX-{$year}-003"]);
});

// ------------------------------------------------------------------ droits et périmètre

it('réserve les consultations et les bons de commande au Service Achats BTP (403 sinon)', function (RoleName $role) {
    $request = validatedRequest([1_000 => 10]);
    $user = userWithRole($role, ['two_factor_secret' => encrypt('s'), 'two_factor_recovery_codes' => encrypt(json_encode(['c'])), 'two_factor_confirmed_at' => now()]);

    $this->actingAs($user)->post(route('achats.consultations.store'), ['purchase_request_id' => $request->id, 'deadline' => now()->addDay()->toDateString()])->assertForbidden();
    $this->actingAs($user)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'purchase_request_id' => $request->id])->assertForbidden();

    expect(Consultation::query()->count())->toBe(0)->and(PurchaseOrder::query()->count())->toBe(0);
})->with([RoleName::ConducteurTravaux, RoleName::ChefChantier, RoleName::Magasinier, RoleName::DirectionTechnique, RoleName::Comptabilite]);

it('RG7.2 : un conducteur ne voit que les consultations et commandes de ses chantiers', function () {
    $this->seed(SettingsSeeder::class);
    $conducteur = userWithRole(RoleName::ConducteurTravaux);
    $mine = Chantier::factory()->create();
    $mine->team()->attach($conducteur->id, ['role' => Chantier::ROLE_CONDUCTEUR]);
    $buyer = purchasingBuyer();
    $mineRequest = validatedRequest([1_000 => 10], $mine);
    $otherRequest = validatedRequest([1_000 => 10]);

    foreach ([$mineRequest, $otherRequest] as $r) {
        $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $r->id, 'deadline' => now()->addDay()->toDateString()]);
        $this->actingAs($buyer)->post(route('achats.commandes.store'), ['supplier_id' => materialsSupplier()->id, 'purchase_request_id' => $r->id])->assertSessionHasErrors();
    }
    $consultation = Consultation::query()->where('purchase_request_id', $otherRequest->id)->firstOrFail();

    $this->actingAs($conducteur)->get(route('achats.consultations'))->assertInertia(fn (Assert $page) => $page->where('consultations.total', 1));
    $this->actingAs($conducteur)->get(route('achats.consultations.show', $consultation))->assertForbidden();
});

it('liste les consultations avec leur comparatif et propose les demandes consultables', function () {
    $this->seed(SettingsSeeder::class);
    $request = validatedRequest([10_000 => 100]);
    $buyer = purchasingBuyer();
    validatedRequest([1_000 => 5]);

    $this->actingAs($buyer)->get(route('achats.consultations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Achats/Consultations/Index')->has('consultable', 2)->where('threshold', 2_000_000)->where('minSuppliers', 3));

    $this->actingAs($buyer)->post(route('achats.consultations.store'), ['purchase_request_id' => $request->id, 'deadline' => now()->addDay()->toDateString()]);
    $consultation = Consultation::query()->firstOrFail();
    $a = materialsSupplier('Cheap SA');
    $b = materialsSupplier('Dear SA');
    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => $b->id, 'total_amount' => 1_100_000, 'delay_days' => 2]);
    $this->actingAs($buyer)->post(route('achats.consultations.quotes.store', $consultation), ['supplier_id' => $a->id, 'total_amount' => 950_000, 'delay_days' => 5]);

    $this->actingAs($buyer)->get(route('achats.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achats/Consultations/Show')
            ->where('consultation.quotes.0.supplierName', 'Cheap SA')
            ->where('consultation.quotes.0.unitTotal', 950_000)
            ->has('suppliers')
            ->where('canManage', true));
});
