<?php

declare(strict_types=1);

use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\Reception;
use App\Domain\Achats\Models\SupplierInvoice;
use App\Domain\Fournisseurs\Models\SupplierEvaluation;
use App\Domain\Shared\Enums\RoleName;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// ------------------------------------------------------------------ réception (RG8.9) et solde de l'engagement (RG10.1)

it('RG10.1 : une réception complète solde l\'engagement et le fait passer en coût réel, sans double comptage', function () {
    ['order' => $order, 'line' => $line, 'commitment' => $commitment] = openOrder();

    receive($order, 100)->assertSessionHasNoErrors();

    $year = now()->year;
    $reception = Reception::query()->firstOrFail();
    expect($reception->number)->toBe("BR-{$year}-0001")
        ->and($reception->gap)->toBe('conforme')
        ->and($order->refresh()->status)->toBe('receptionne')
        ->and((float) $order->lines()->first()->received_quantity)->toBe(100.0)
        ->and($commitment->refresh()->status)->toBe('settled')
        ->and($commitment->settled_amount)->toBe(1_000_000);

    $line->refresh();
    expect($line->committed_amount)->toBe(0)->and($line->actual_amount)->toBe(1_000_000)
        ->and($line->committed_amount + $line->actual_amount)->toBe(1_000_000); // un même montant n'est jamais compté deux fois
});

it('RG8.9 : une livraison partielle crée un reliquat, la suivante solde le reste', function () {
    ['order' => $order, 'line' => $line, 'commitment' => $commitment] = openOrder();

    receive($order, 40)->assertSessionHasNoErrors();
    $first = Reception::query()->firstOrFail();
    expect($first->gap)->toBe('reliquat')->and($order->refresh()->status)->toBe('partiel')
        ->and($commitment->refresh()->status)->toBe('active')->and($commitment->settled_amount)->toBe(400_000)
        ->and($line->refresh()->committed_amount)->toBe(600_000)->and($line->actual_amount)->toBe(400_000);

    receive($order, 60)->assertSessionHasNoErrors();
    expect(Reception::query()->orderByDesc('id')->firstOrFail()->gap)->toBe('conforme')
        ->and($order->refresh()->status)->toBe('receptionne')
        ->and($commitment->refresh()->status)->toBe('settled')
        ->and($line->refresh()->committed_amount)->toBe(0)->and($line->actual_amount)->toBe(1_000_000);
});

it('RG8.9 : une quantité renvoyée au fournisseur génère un retour et ne compte pas en coût réel', function () {
    ['order' => $order, 'line' => $line] = openOrder();

    receive($order, 100, 20)->assertSessionHasNoErrors();

    expect(Reception::query()->firstOrFail()->gap)->toBe('retour')
        ->and((float) $order->lines()->first()->received_quantity)->toBe(80.0)
        ->and($order->refresh()->status)->toBe('partiel')
        ->and($line->refresh()->actual_amount)->toBe(800_000)->and($line->committed_amount)->toBe(200_000);
});

it('RG10.1 : un prix de commande négocié sous le prix figé libère l\'engagement au prix figé et impute le coût réel au prix commandé', function () {
    ['order' => $order, 'line' => $line] = openOrder(tariff: 9_000);

    receive($order, 100);

    expect($order->refresh()->amount)->toBe(900_000)
        ->and($line->refresh()->committed_amount)->toBe(0)
        ->and($line->actual_amount)->toBe(900_000);
});

it('refuse des quantités incohérentes : renvoi supérieur à la livraison, excédent, livraison vide', function (float $delivered, float $returned) {
    ['order' => $order, 'line' => $line] = openOrder();

    receive($order, $delivered, $returned)->assertSessionHasErrors('lines');

    expect(Reception::query()->count())->toBe(0)->and($line->refresh()->actual_amount)->toBe(0)->and($order->refresh()->status)->toBe('emis');
})->with([
    'renvoi supérieur à la livraison' => [10, 20],
    'excédent de livraison non renvoyé' => [120, 0],
    'aucune quantité livrée' => [0, 0],
]);

it('accepte un excédent de livraison renvoyé au fournisseur', function () {
    ['order' => $order] = openOrder();

    receive($order, 120, 20)->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe('receptionne')->and(Reception::query()->firstOrFail()->gap)->toBe('retour');
});

it('refuse une ligne d\'un autre bon et un bon déjà entièrement reçu', function () {
    ['order' => $order] = openOrder();
    ['order' => $other] = openOrder();
    $buyer = receiver();

    $this->actingAs($buyer)->post(route('achats.receptions.store', $order), ['lines' => [['purchase_order_line_id' => $other->lines()->first()->id, 'delivered' => 10, 'returned' => 0]]])->assertSessionHasErrors('lines');

    receive($order, 100);
    receive($order, 1)->assertForbidden();
    expect(Reception::query()->count())->toBe(1);
});

it('réserve la réception au Service Achats BTP et au magasinier (403 sinon)', function (RoleName $role, bool $allowed) {
    ['order' => $order] = openOrder();

    $response = receive($order, 100, 0, receiver($role));

    $allowed ? $response->assertSessionHasNoErrors() : $response->assertForbidden();
    expect(Reception::query()->count())->toBe($allowed ? 1 : 0);
})->with([
    'service achats' => [RoleName::ServiceAchatsBtp, true],
    'magasinier' => [RoleName::Magasinier, true],
    'chef de chantier' => [RoleName::ChefChantier, false],
    'conducteur' => [RoleName::ConducteurTravaux, false],
    'comptabilité' => [RoleName::Comptabilite, false],
]);

it('liste les réceptions et propose les bons à réceptionner', function () {
    ['order' => $order] = openOrder();
    receive($order, 30);
    openOrder();

    $this->actingAs(receiver())->get(route('achats.receptions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achats/Receptions/Index')
            ->has('receptions.data', 1)
            ->where('receptions.data.0.gap', 'reliquat')
            ->where('receptions.data.0.lines.0.received', 30)
            ->where('receptions.data.0.evaluated', false)
            ->has('receivable', 2));
});

// ------------------------------------------------------------------ évaluation du fournisseur (RG3.3)

it('RG3.3 : évalue le fournisseur après une réception, une seule fois, et recalcule sa note moyenne', function () {
    ['order' => $order, 'supplier' => $supplier] = openOrder();
    receive($order, 100);
    $reception = Reception::query()->firstOrFail();
    $buyer = receiver();

    $this->actingAs($buyer)->post(route('fournisseurs.evaluations.store', $supplier), ['reception_id' => $reception->id, 'delay' => 4, 'quality' => 5, 'price' => 3, 'comment' => 'Livraison en retard d\'un jour'])->assertSessionHasNoErrors();
    $this->actingAs($buyer)->post(route('fournisseurs.evaluations.store', $supplier), ['reception_id' => $reception->id, 'delay' => 1, 'quality' => 1, 'price' => 1])->assertSessionHasErrors('reception_id');

    expect(SupplierEvaluation::query()->count())->toBe(1);

    $this->actingAs($buyer)->get(route('fournisseurs.show', $supplier))
        ->assertInertia(fn (Assert $page) => $page
            ->where('supplier.rating', 4)
            ->where('supplier.openOrders', 0)
            ->where('evaluations.0.reception', $reception->number)
            ->where('evaluations.0.comment', 'Livraison en retard d\'un jour')
            ->has('orders', 1)
            ->where('evaluable', []));
});

it('RG3.3 : valide les notes de 1 à 5 et le lien entre la réception et le fournisseur', function (array $override, string $field) {
    ['order' => $order, 'supplier' => $supplier] = openOrder();
    receive($order, 100);
    ['supplier' => $other] = openOrder();
    $payload = $override + ['reception_id' => Reception::query()->firstOrFail()->id, 'delay' => 3, 'quality' => 3, 'price' => 3];
    $target = ($override['wrong_supplier'] ?? false) ? $other : $supplier;
    unset($payload['wrong_supplier']);

    $this->actingAs(receiver())->post(route('fournisseurs.evaluations.store', $target), $payload)->assertSessionHasErrors($field);
    expect(SupplierEvaluation::query()->count())->toBe(0);
})->with([
    'note nulle' => [['delay' => 0], 'delay'],
    'note supérieure à 5' => [['quality' => 6], 'quality'],
    'note décimale' => [['price' => 2.5], 'price'],
    'réception d\'un autre fournisseur' => [['wrong_supplier' => true], 'reception_id'],
]);

it('RG3.3 : réserve l\'évaluation au Service Achats BTP', function () {
    ['order' => $order, 'supplier' => $supplier] = openOrder();
    receive($order, 100);

    $this->actingAs(receiver(RoleName::Finance))->post(route('fournisseurs.evaluations.store', $supplier), ['reception_id' => Reception::query()->firstOrFail()->id, 'delay' => 3, 'quality' => 3, 'price' => 3])->assertForbidden();
});

// ------------------------------------------------------------------ factures et contrôle à trois voies (RG8.10)

/** Facture saisie par la Comptabilité sur le bon donné. */
function recordInvoice(PurchaseOrder $order, int $amount, string $number = 'FAC-F-001', ?User $by = null): User
{
    $author = $by ?? receiver(RoleName::Comptabilite);
    test()->actingAs($author)->post(route('achats.factures.store'), [
        'number' => $number,
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'amount' => $amount,
    ])->assertSessionHasNoErrors();

    return $author;
}

it('RG8.10 : une facture qui concorde avec le bon, la réception et les prix est contrôlée puis payable', function () {
    ['order' => $order] = openOrder();
    receive($order, 100);
    recordInvoice($order, 1_000_000);
    $invoice = SupplierInvoice::query()->firstOrFail();
    $controller = receiver(RoleName::Finance);

    expect($invoice->status)->toBe('a_controler');

    $this->actingAs($controller)->post(route('achats.factures.control', $invoice))->assertSessionHasNoErrors();
    $invoice->refresh();
    expect($invoice->status)->toBe('controlee')
        ->and([$invoice->match_order, $invoice->match_reception, $invoice->match_price])->toBe([true, true, true])
        ->and($order->refresh()->status)->toBe('facture');

    $this->actingAs($controller)->post(route('achats.factures.pay', $invoice))->assertSessionHasNoErrors();
    expect($invoice->refresh()->status)->toBe('payee')->and($invoice->paid_by)->toBe($controller->id)->and($order->refresh()->status)->toBe('paye');
});

it('RG8.10 : une facture sans réception ou au-dessus des prix commandés est en écart, donc non payable', function (int $received, int $amount, array $expected) {
    ['order' => $order] = openOrder();
    if ($received > 0) {
        receive($order, $received);
    }
    recordInvoice($order, $amount);
    $invoice = SupplierInvoice::query()->firstOrFail();
    $finance = receiver(RoleName::Finance);

    $this->actingAs($finance)->post(route('achats.factures.control', $invoice));
    $invoice->refresh();
    expect($invoice->status)->toBe('ecart')->and([$invoice->match_order, $invoice->match_reception, $invoice->match_price])->toBe($expected);

    $this->actingAs($finance)->post(route('achats.factures.pay', $invoice))->assertForbidden();
    expect($invoice->refresh()->status)->toBe('ecart')->and($invoice->paid_on)->toBeNull();
})->with([
    'rien n\'est reçu' => [0, 1_000_000, [true, false, false]],
    'facturé au-dessus de ce qui est reçu' => [50, 800_000, [true, true, false]],
    'facturé au-dessus du bon de commande' => [100, 1_200_000, [false, true, false]],
]);

it('RG8.10 : la facturation partielle consomme le reste à facturer du bon', function () {
    ['order' => $order] = openOrder();
    receive($order, 100);
    $finance = receiver(RoleName::Finance);

    recordInvoice($order, 600_000, 'F-1');
    $this->actingAs($finance)->post(route('achats.factures.control', SupplierInvoice::query()->where('number', 'F-1')->firstOrFail()));
    expect($order->refresh()->status)->toBe('receptionne'); // pas encore intégralement facturé

    recordInvoice($order, 600_000, 'F-2');
    $second = SupplierInvoice::query()->where('number', 'F-2')->firstOrFail();
    $this->actingAs($finance)->post(route('achats.factures.control', $second));
    expect($second->refresh()->status)->toBe('ecart'); // 600 000 + 600 000 > 1 000 000

    recordInvoice($order, 400_000, 'F-3');
    $third = SupplierInvoice::query()->where('number', 'F-3')->firstOrFail();
    $this->actingAs($finance)->post(route('achats.factures.control', $third));
    expect($third->refresh()->status)->toBe('controlee')->and($order->refresh()->status)->toBe('facture');
});

it('séparation des tâches : l\'auteur d\'une facture ne la contrôle pas lui-même', function () {
    ['order' => $order] = openOrder();
    receive($order, 100);
    $author = recordInvoice($order, 1_000_000, by: receiver(RoleName::Finance));
    $invoice = SupplierInvoice::query()->firstOrFail();

    $this->actingAs($author)->post(route('achats.factures.control', $invoice))->assertForbidden();
    $this->actingAs(receiver(RoleName::Comptabilite))->post(route('achats.factures.control', $invoice))->assertSessionHasNoErrors();
    expect($invoice->refresh()->status)->toBe('controlee');
});

it('seule la Finance paie une facture : la Comptabilité contrôle sans payer', function () {
    ['order' => $order] = openOrder();
    receive($order, 100);
    recordInvoice($order, 1_000_000, by: receiver(RoleName::Finance));
    $invoice = SupplierInvoice::query()->firstOrFail();
    $accountant = receiver(RoleName::Comptabilite);

    $this->actingAs($accountant)->post(route('achats.factures.control', $invoice))->assertSessionHasNoErrors();
    $this->actingAs($accountant)->post(route('achats.factures.pay', $invoice))->assertForbidden();
    expect($invoice->refresh()->status)->toBe('controlee');
});

it('refuse une facture en double, des montants invalides et les profils sans droit', function () {
    ['order' => $order] = openOrder();
    receive($order, 100);
    recordInvoice($order, 1_000_000, 'F-9');
    $accountant = receiver(RoleName::Comptabilite);
    $payload = fn (array $o = []): array => $o + ['number' => 'F-10', 'purchase_order_id' => $order->id, 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDay()->toDateString(), 'amount' => 1_000];

    $this->actingAs($accountant)->post(route('achats.factures.store'), $payload(['number' => 'F-9']))->assertSessionHasErrors('number');
    $this->actingAs($accountant)->post(route('achats.factures.store'), $payload(['amount' => 10.5]))->assertSessionHasErrors('amount');
    $this->actingAs($accountant)->post(route('achats.factures.store'), $payload(['due_date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('due_date');
    $this->actingAs(receiver(RoleName::ServiceAchatsBtp))->post(route('achats.factures.store'), $payload())->assertForbidden();
    $this->actingAs(receiver(RoleName::ChefChantier))->post(route('achats.factures.store'), $payload())->assertForbidden();

    expect(SupplierInvoice::query()->count())->toBe(1);
});

it('liste les factures avec le résultat du contrôle et ce que chacun peut faire', function () {
    ['order' => $order] = openOrder();
    receive($order, 100);
    recordInvoice($order, 1_000_000, by: receiver(RoleName::Finance));

    $this->actingAs(receiver(RoleName::Comptabilite))->get(route('achats.factures'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Achats/Factures/Index')
            ->where('invoices.data.0.status', 'a_controler')
            ->where('invoices.data.0.canControl', true)
            ->where('invoices.data.0.canPay', false)
            ->where('canCreate', true)
            ->has('orders', 1));
});

// ------------------------------------------------------------------ CA8.2 et CA3

it('CA8.2 : depuis la demande, on retrouve ses bons de commande, réceptions, factures et leur état de paiement', function () {
    ['order' => $order, 'request' => $request] = openOrder();
    receive($order, 100);
    recordInvoice($order, 1_000_000);
    $finance = receiver(RoleName::Finance);
    $this->actingAs($finance)->post(route('achats.factures.control', SupplierInvoice::query()->firstOrFail()));

    $this->actingAs(receiver())->get(route('achats.demandes.show', $request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('purchasing.orders.0.number', $order->number)
            ->where('purchasing.orders.0.status', 'facture')
            ->where('purchasing.receptions.0.gap', 'conforme')
            ->where('purchasing.invoices.0.status', 'controlee'));

    $this->actingAs(receiver())->get(route('achats.commandes.show', $order))
        ->assertInertia(fn (Assert $page) => $page->has('receptions', 1)->has('invoices', 1)->where('canReceive', false));
});

it('CA3 : la fiche du fournisseur affiche ses commandes, ses factures non payées et sa note moyenne', function () {
    ['order' => $order, 'supplier' => $supplier] = openOrder();
    recordInvoice($order, 1_000_000);

    $this->actingAs(receiver())->get(route('fournisseurs.show', $supplier))
        ->assertInertia(fn (Assert $page) => $page
            ->where('supplier.openOrders', 1)
            ->where('supplier.unpaidInvoices', 1)
            ->where('unpaidInvoices.0.amount', 1_000_000)
            ->where('orders.0.number', $order->number));
});
