<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\Consultation;
use App\Domain\Achats\Models\ConsultationQuote;
use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Shared\Support\DocumentNumbers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consultation de fournisseurs (étapes 4 à 6 du processus d'achat) :
 * ouverture sur une demande validée, saisie des offres, comparatif et choix du
 * fournisseur qui crée le bon de commande. Au-delà du seuil paramétré, au moins
 * trois fournisseurs doivent avoir répondu (RG8.11).
 */
final class ManageConsultation
{
    public function __construct(private readonly CreatePurchaseOrder $orders) {}

    public function open(PurchaseRequest $request, string $deadline, ?string $note, User $by): Consultation
    {
        if ($request->status->getValue() !== 'validee') {
            throw ValidationException::withMessages(['purchase_request_id' => 'Seule une demande validée peut faire l\'objet d\'une consultation.']);
        }

        if (Consultation::query()->where('purchase_request_id', $request->id)->exists()) {
            throw ValidationException::withMessages(['purchase_request_id' => 'Cette demande a déjà une consultation.']);
        }

        $request->loadMissing('lines.article');

        if ($request->lines->every(fn ($l): bool => Purchasing::orderable($l) <= 0.0)) {
            throw ValidationException::withMessages(['purchase_request_id' => 'Tout a déjà été commandé pour cette demande.']);
        }

        // RG8.11 : au-delà du seuil, au moins trois fournisseurs ; en deçà, une offre suffit.
        $min = $request->computeTotal() > Purchasing::consultationThreshold() ? Purchasing::minimumSuppliers() : 1;

        return DB::transaction(fn (): Consultation => Consultation::query()->create([
            'number' => DocumentNumbers::next('consultation', 'CS-{YYYY}-{SEQ:4}'),
            'purchase_request_id' => $request->id,
            'chantier_id' => $request->chantier_id,
            'status' => Consultation::OPEN,
            'deadline' => $deadline,
            'note' => $note,
            'min_suppliers' => $min,
            'created_by' => $by->id,
        ]));
    }

    public function addQuote(Consultation $consultation, int $supplierId, int $totalAmount, int $delayDays): ConsultationQuote
    {
        if ($consultation->status === Consultation::AWARDED) {
            throw ValidationException::withMessages(['supplier_id' => 'Cette consultation est attribuée.']);
        }

        $request = $consultation->demand();
        $eligible = Purchasing::eligibleSuppliers($request)->pluck('id')->all();

        // RG3.1 : seuls les fournisseurs actifs de la bonne catégorie sont proposés.
        if (! in_array($supplierId, $eligible, true)) {
            throw ValidationException::withMessages(['supplier_id' => 'Ce fournisseur n\'est pas de la bonne catégorie ou est désactivé.']);
        }

        if ($consultation->quotes()->where('supplier_id', $supplierId)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => 'Ce fournisseur a déjà remis une offre.']);
        }

        return DB::transaction(function () use ($consultation, $supplierId, $totalAmount, $delayDays): ConsultationQuote {
            $quote = $consultation->quotes()->create(['supplier_id' => $supplierId, 'total_amount' => $totalAmount, 'delay_days' => $delayDays]);

            if ($consultation->quotes()->count() >= $consultation->min_suppliers) {
                $consultation->update(['status' => Consultation::COMPARED]);
            }

            return $quote;
        });
    }

    /**
     * Retient un fournisseur et crée le bon de commande sur les quantités encore
     * commandables de la demande, au prix de l'offre (RG8.11).
     */
    public function award(Consultation $consultation, int $supplierId, User $by): PurchaseOrder
    {
        return DB::transaction(function () use ($consultation, $supplierId, $by): PurchaseOrder {
            $consultation->refresh();

            if ($consultation->status === Consultation::AWARDED) {
                throw ValidationException::withMessages(['supplier_id' => 'Cette consultation est déjà attribuée.']);
            }

            if ($consultation->quotes()->count() < $consultation->min_suppliers) {
                throw ValidationException::withMessages([
                    'supplier_id' => "Au moins {$consultation->min_suppliers} fournisseurs doivent avoir remis une offre avant de commander (RG8.11).",
                ]);
            }

            /** @var ConsultationQuote|null $quote */
            $quote = $consultation->quotes()->where('supplier_id', $supplierId)->first();

            if ($quote === null) {
                throw ValidationException::withMessages(['supplier_id' => 'Ce fournisseur n\'a pas remis d\'offre.']);
            }

            $supplier = Supplier::query()->findOrFail($supplierId);
            $request = $consultation->demand();
            $request->loadMissing('lines.article');

            $order = $this->orders->fromQuote($request, $supplier, $quote->total_amount, $consultation, $by);
            $quote->update(['is_selected' => true]);
            $consultation->update(['status' => Consultation::AWARDED]);

            return $order;
        });
    }
}
