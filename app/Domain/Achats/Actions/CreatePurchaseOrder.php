<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\Consultation;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\Models\PurchaseRequestLine;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierTariff;
use App\Domain\Shared\Support\DocumentNumbers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création d'un bon de commande (étape 7 du processus d'achat).
 *
 * Un bon peut regrouper plusieurs demandes validées d'un même chantier, et une
 * demande peut être servie par plusieurs commandes (RG8.8). Au-delà du seuil
 * paramétré, une commande directe est refusée : il faut une consultation (RG8.11).
 */
final class CreatePurchaseOrder
{
    /**
     * Commande directe, sans consultation (montant sous le seuil).
     *
     * @param  list<array{request_line_id: int, quantity: float|int|string}>  $lines
     */
    public function direct(Supplier $supplier, array $lines, User $by): PurchaseOrder
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Ajoutez au moins une ligne.']);
        }

        $requestLines = PurchaseRequestLine::query()->with(['request.chantier', 'article'])
            ->whereIn('id', array_column($lines, 'request_line_id'))->get()->keyBy('id');

        if ($requestLines->count() !== count(array_unique(array_column($lines, 'request_line_id')))) {
            throw ValidationException::withMessages(['lines' => 'Une ligne de demande est introuvable.']);
        }

        $chantierIds = $requestLines->map(fn (PurchaseRequestLine $l): int => $l->request()->firstOrFail()->chantier_id)->unique();

        // RG8.8 : un bon regroupe des demandes d'un même chantier.
        if ($chantierIds->count() > 1) {
            throw ValidationException::withMessages(['lines' => 'Un bon de commande ne regroupe que des demandes d\'un même chantier (RG8.8).']);
        }

        $tariffs = SupplierTariff::query()->where('supplier_id', $supplier->id)->pluck('price', 'article_id');
        $prepared = [];

        foreach ($lines as $line) {
            $requestLine = $requestLines->get($line['request_line_id']);
            assert($requestLine instanceof PurchaseRequestLine);
            $quantity = (float) $line['quantity'];
            $request = $requestLine->request()->firstOrFail();

            if ($request->status->getValue() !== 'validee') {
                throw ValidationException::withMessages(['lines' => "La demande {$request->number} n'est pas validée."]);
            }

            if ($quantity <= 0 || $quantity > Purchasing::orderable($requestLine) + 0.0005) {
                throw ValidationException::withMessages(['lines' => "Quantité invalide pour « {$requestLine->item()->label} » : au plus ".Purchasing::orderable($requestLine).' restent à commander.']);
            }

            if (Consultation::query()->where('purchase_request_id', $request->id)->where('status', '!=', Consultation::AWARDED)->exists()) {
                throw ValidationException::withMessages(['lines' => "La demande {$request->number} est en cours de consultation."]);
            }

            // Tarif du fournisseur s'il existe (RG3.2), sinon prix figé de la demande.
            $tariff = $tariffs->get($requestLine->article_id);

            $prepared[] = [
                'request_line' => $requestLine,
                'quantity' => $quantity,
                'unit_price' => is_numeric($tariff) ? (int) $tariff : $requestLine->unitPrice(),
            ];
        }

        $this->assertSupplierEligible($supplier, $prepared);

        $amount = (int) array_sum(array_map(fn (array $p): int => (int) round($p['unit_price'] * $p['quantity']), $prepared));

        // RG8.11 : au-delà du seuil, une consultation est obligatoire avant commande.
        if ($amount > Purchasing::consultationThreshold()) {
            throw ValidationException::withMessages([
                'lines' => 'Au-delà de '.number_format(Purchasing::consultationThreshold(), 0, ',', ' ').' FCFA, une consultation d\'au moins '.Purchasing::minimumSuppliers().' fournisseurs est obligatoire avant commande (RG8.11).',
            ]);
        }

        return $this->persist($supplier, (int) $chantierIds->first(), null, $prepared, $by);
    }

    /**
     * Bon de commande issu d'une consultation attribuée : les quantités encore
     * commandables de la demande, au prix de l'offre retenue (réparti au prorata
     * des prix figés de la demande).
     */
    public function fromQuote(PurchaseRequest $request, Supplier $supplier, int $quoteTotal, Consultation $consultation, User $by): PurchaseOrder
    {
        $remaining = $request->lines->filter(fn (PurchaseRequestLine $l): bool => Purchasing::orderable($l) > 0.0);

        if ($remaining->isEmpty()) {
            throw ValidationException::withMessages(['supplier_id' => 'Tout a déjà été commandé pour cette demande.']);
        }

        $base = $remaining->sum(fn (PurchaseRequestLine $l): float => $l->unitPrice() * Purchasing::orderable($l));

        if ($base <= 0) {
            throw ValidationException::withMessages(['supplier_id' => 'Le montant de la demande est nul.']);
        }

        $prepared = [];
        foreach ($remaining as $line) {
            $prepared[] = [
                'request_line' => $line,
                'quantity' => Purchasing::orderable($line),
                'unit_price' => (int) round($line->unitPrice() * $quoteTotal / $base),
            ];
        }

        return $this->persist($supplier, $request->chantier_id, $consultation->id, $prepared, $by);
    }

    /**
     * @param  list<array{request_line: PurchaseRequestLine, quantity: float, unit_price: int}>  $prepared
     */
    private function persist(Supplier $supplier, int $chantierId, ?int $consultationId, array $prepared, User $by): PurchaseOrder
    {
        return DB::transaction(function () use ($supplier, $chantierId, $consultationId, $prepared, $by): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'number' => DocumentNumbers::next('bon_commande', 'BC-{YYYY}-{SEQ:5}'),
                'supplier_id' => $supplier->id,
                'chantier_id' => $chantierId,
                'consultation_id' => $consultationId,
                'status' => PurchaseOrder::ISSUED,
                'ordered_on' => now()->toDateString(),
                'amount' => 0,
                'created_by' => $by->id,
            ]);

            $amount = 0;

            foreach ($prepared as $p) {
                $line = $order->lines()->create([
                    'purchase_request_line_id' => $p['request_line']->id,
                    'article_id' => $p['request_line']->article_id,
                    'quantity' => $p['quantity'],
                    'unit_price' => $p['unit_price'],
                    'received_quantity' => 0,
                ]);
                $amount += $line->amount();
            }

            $order->update(['amount' => $amount]);

            return $order;
        });
    }

    /**
     * RG3.1 : le fournisseur est actif et de la catégorie des articles commandés.
     *
     * @param  list<array{request_line: PurchaseRequestLine, quantity: float, unit_price: int}>  $prepared
     */
    private function assertSupplierEligible(Supplier $supplier, array $prepared): void
    {
        if (! $supplier->is_active) {
            throw ValidationException::withMessages(['supplier_id' => 'Ce fournisseur est désactivé.']);
        }

        $categories = $supplier->categoryNames();

        foreach ($prepared as $p) {
            if (! in_array($p['request_line']->item()->supplierCategory(), $categories, true)) {
                throw ValidationException::withMessages(['supplier_id' => "Ce fournisseur n'est pas de la catégorie « {$p['request_line']->item()->supplierCategory()} »."]);
            }
        }
    }
}
