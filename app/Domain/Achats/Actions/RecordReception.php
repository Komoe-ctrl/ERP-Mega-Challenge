<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\PurchaseOrder;
use App\Domain\Achats\Models\PurchaseOrderLine;
use App\Domain\Achats\Models\Reception;
use App\Domain\Budget\Models\BudgetCommitment;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Shared\Support\BudgetLock;
use App\Domain\Shared\Support\DocumentNumbers;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Réception d'une livraison (étape 8 du processus d'achat, RG8.9).
 *
 * Elle enregistre les quantités réellement livrées et celles renvoyées au
 * fournisseur ; un écart avec la commande génère un reliquat ou un retour. Les
 * quantités acceptées soldent l'engagement de la demande et passent en coût
 * réel du chantier, sans double comptage (RG10.1), le tout sous verrou de budget.
 */
final class RecordReception
{
    private const EPSILON = 0.0005;

    /**
     * @param  list<array{purchase_order_line_id: int, delivered: float|int|string, returned: float|int|string}>  $lines
     */
    public function execute(PurchaseOrder $order, array $lines, ?string $note, User $by): Reception
    {
        return BudgetLock::run(
            BudgetLine::query()->where('chantier_id', $order->chantier_id),
            function (Collection $budgetLines) use ($order, $lines, $note, $by): Reception {
                $order->refresh()->load('lines.requestLine.request');

                if (! in_array($order->status, [PurchaseOrder::ISSUED, PurchaseOrder::PARTIAL], true)) {
                    throw ValidationException::withMessages(['lines' => 'Ce bon de commande ne peut plus recevoir de livraison.']);
                }

                $prepared = $this->prepare($order, $lines);

                $reception = Reception::query()->create([
                    'number' => DocumentNumbers::next('reception', 'BR-{YYYY}-{SEQ:4}'),
                    'purchase_order_id' => $order->id,
                    'received_on' => now()->toDateString(),
                    'gap' => Reception::CONFORM,
                    'note' => $note,
                    'received_by' => $by->id,
                ]);

                $hasReturn = false;

                foreach ($prepared as [$line, $delivered, $returned, $accepted]) {
                    $reception->lines()->create([
                        'purchase_order_line_id' => $line->id,
                        'delivered_quantity' => $delivered,
                        'returned_quantity' => $returned,
                        'accepted_quantity' => $accepted,
                    ]);
                    $hasReturn = $hasReturn || $returned > self::EPSILON;

                    if ($accepted > self::EPSILON) {
                        $line->update(['received_quantity' => (float) $line->received_quantity + $accepted]);
                        $this->settle($line, $accepted, $budgetLines);
                    }
                }

                $order->load('lines');
                $complete = $order->lines->every(fn (PurchaseOrderLine $l): bool => $l->remaining() <= self::EPSILON);

                $reception->update(['gap' => $hasReturn ? Reception::RETURNED : ($complete ? Reception::CONFORM : Reception::BALANCE)]);
                $order->update(['status' => $complete ? PurchaseOrder::RECEIVED : PurchaseOrder::PARTIAL]);

                return $reception;
            },
        );
    }

    /**
     * Contrôle les quantités saisies (RG8.9).
     *
     * @param  list<array{purchase_order_line_id: int, delivered: float|int|string, returned: float|int|string}>  $lines
     * @return list<array{0: PurchaseOrderLine, 1: float, 2: float, 3: float}>
     */
    private function prepare(PurchaseOrder $order, array $lines): array
    {
        $prepared = [];
        $any = false;

        foreach ($lines as $input) {
            /** @var PurchaseOrderLine|null $line */
            $line = $order->lines->firstWhere('id', $input['purchase_order_line_id']);
            $delivered = (float) $input['delivered'];
            $returned = (float) $input['returned'];

            if ($line === null) {
                throw ValidationException::withMessages(['lines' => 'Une ligne n\'appartient pas à ce bon de commande.']);
            }

            if ($delivered < 0 || $returned < 0 || $returned > $delivered + self::EPSILON) {
                throw ValidationException::withMessages(['lines' => 'Les quantités livrées et renvoyées sont invalides : on ne peut pas renvoyer plus que ce qui est livré.']);
            }

            $accepted = $delivered - $returned;

            // Un excédent de livraison doit être renvoyé : on ne reçoit jamais plus que la commande.
            if ($accepted > $line->remaining() + self::EPSILON) {
                throw ValidationException::withMessages(['lines' => "Quantité acceptée supérieure au reste à recevoir pour « {$line->article?->label} » : renvoyez l'excédent."]);
            }

            $any = $any || $delivered > self::EPSILON;
            $prepared[] = [$line, $delivered, $returned, $accepted];
        }

        if (! $any) {
            throw ValidationException::withMessages(['lines' => 'Saisissez au moins une quantité livrée.']);
        }

        return $prepared;
    }

    /**
     * RG10.1 : solde l'engagement de la demande à hauteur du prix figé des
     * quantités reçues et impute au coût réel le prix de la commande.
     *
     * @param  Collection<int, BudgetLine>  $budgetLines  postes du chantier, verrouillés
     */
    private function settle(PurchaseOrderLine $line, float $accepted, Collection $budgetLines): void
    {
        $requestLine = $line->requestLine;
        $cost = (int) round($line->unit_price * $accepted);

        if ($requestLine === null) {
            return;
        }

        $request = $requestLine->request()->firstOrFail();
        $commitment = BudgetCommitment::query()
            ->where('source_type', $request->getMorphClass())->where('source_id', $request->id)
            ->where('status', BudgetCommitment::STATUS_ACTIVE)->first();

        /** @var BudgetLine|null $budgetLine */
        $budgetLine = $budgetLines->firstWhere('id', $commitment instanceof BudgetCommitment ? $commitment->budget_line_id : $request->budget_line_id);

        if ($budgetLine === null) {
            return;
        }

        $released = 0;

        if ($commitment !== null) {
            $released = min((int) round(($requestLine->frozen_price ?? $line->unit_price) * $accepted), $commitment->amount - $commitment->settled_amount);
            $settled = $commitment->settled_amount + $released;
            $commitment->update([
                'settled_amount' => $settled,
                'status' => $settled >= $commitment->amount ? BudgetCommitment::STATUS_SETTLED : BudgetCommitment::STATUS_ACTIVE,
                'settled_at' => $settled >= $commitment->amount ? now() : null,
            ]);
        }

        $budgetLine->update([
            'committed_amount' => $budgetLine->committed_amount - $released,
            'actual_amount' => $budgetLine->actual_amount + $cost,
        ]);
    }
}
