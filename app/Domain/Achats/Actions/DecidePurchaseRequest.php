<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\States\Refusee;
use App\Domain\Achats\States\Renvoyee;
use App\Domain\Achats\States\RequestState;
use App\Domain\Achats\States\Validee;
use App\Domain\Budget\Models\BudgetCommitment;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Support\BudgetSummary;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Support\BudgetLock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Décision du valideur sur une demande en attente (RG8.4 à RG8.7).
 *
 * La validation est le seul moment où les prix sont figés et où le budget du
 * chantier est engagé : tout se passe dans une transaction qui verrouille les
 * postes du chantier (BudgetLock), pour que deux validations simultanées ne
 * lisent jamais le même « reste à engager ».
 */
final class DecidePurchaseRequest
{
    public function __construct(private readonly RequestRouting $routing) {}

    /**
     * @param  array<int, float|int|string>  $adjustments  quantités ajustées par ligne de demande (RG8.5)
     */
    public function validate(PurchaseRequest $request, User $by, array $adjustments = []): PurchaseRequest
    {
        return BudgetLock::run(
            BudgetLine::query()->where('chantier_id', $request->chantier_id),
            function (Collection $lines) use ($request, $by, $adjustments): PurchaseRequest {
                // Relecture sous verrou : une décision concurrente a pu intervenir.
                $request->refresh()->load('lines.article');

                if ($request->status->getValue() !== 'en_attente') {
                    throw ValidationException::withMessages(['status' => 'Cette demande a déjà reçu une décision.']);
                }

                $gaps = $this->applyAdjustments($request, $adjustments);
                $total = $request->computeTotal();

                if ($total <= 0) {
                    throw ValidationException::withMessages(['adjustments' => 'Au moins une ligne doit conserver une quantité.']);
                }

                // RG8.7 : budget restant relu sous verrou, donc à jour.
                $remaining = BudgetSummary::fromLines($lines)->remaining();
                $needsDirection = $this->routing->needsDirection($request->requestedTotal(), $total, $remaining);

                if ($needsDirection && ! $by->hasRole(RoleName::DirectionGenerale->value)) {
                    throw ValidationException::withMessages([
                        'status' => 'Cette demande dépasse le budget restant ou le seuil de validation : la Direction doit la valider (RG8.7).',
                    ]);
                }

                $line = $this->target($request, $lines);

                // RG8.6 : prix figés à la validation.
                foreach ($request->lines as $l) {
                    $l->update(['frozen_price' => $l->item()->price]);
                }

                // RG8.6 / RG10.1 : engagement inscrit sur le poste du chantier.
                $line->update(['committed_amount' => $line->committed_amount + $total]);
                BudgetCommitment::query()->create([
                    'chantier_id' => $request->chantier_id,
                    'budget_line_id' => $line->id,
                    'source_type' => $request->getMorphClass(),
                    'source_id' => $request->id,
                    'amount' => $total,
                    'status' => BudgetCommitment::STATUS_ACTIVE,
                ]);

                $request->needs_direction = $needsDirection;
                $request->total_amount = $total;
                $request->budget_line_id = $line->id;
                $request->decided_by = $by->id;
                $request->decided_at = now();
                $request->status->transitionTo(Validee::class);

                if ($gaps !== []) {
                    $request->events()->create(['user_id' => $by->id, 'action' => 'Quantités ajustées', 'reason' => implode(' ; ', $gaps)]);
                }

                $request->events()->create([
                    'user_id' => $by->id,
                    'action' => 'Validée',
                    'reason' => 'Prix figés, engagement de '.number_format($total, 0, ',', ' ').' FCFA inscrit au budget',
                ]);

                return $request;
            },
        );
    }

    public function refuse(PurchaseRequest $request, User $by, string $reason): PurchaseRequest
    {
        return $this->close($request, $by, Refusee::class, 'Refusée', $reason);
    }

    public function sendBack(PurchaseRequest $request, User $by, string $reason): PurchaseRequest
    {
        return $this->close($request, $by, Renvoyee::class, 'Renvoyée pour correction', $reason);
    }

    /** @param class-string<RequestState> $target */
    private function close(PurchaseRequest $request, User $by, string $target, string $action, string $reason): PurchaseRequest
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Un refus ou un renvoi exige un motif (RG8.5).']);
        }

        $request->decided_by = $by->id;
        $request->decided_at = now();
        $request->status->transitionTo($target);
        $request->events()->create(['user_id' => $by->id, 'action' => $action, 'reason' => trim($reason)]);

        return $request;
    }

    /**
     * RG8.5 : le valideur peut ajuster les quantités ; l'écart est tracé.
     *
     * @param  array<int, float|int|string>  $adjustments
     * @return list<string> écarts, pour l'historique
     */
    private function applyAdjustments(PurchaseRequest $request, array $adjustments): array
    {
        $gaps = [];

        foreach ($request->lines as $line) {
            $value = $adjustments[$line->id] ?? null;

            if ($value === null || ! is_numeric($value) || (float) $value < 0) {
                continue;
            }

            if ((float) $value !== (float) $line->quantity) {
                $line->update(['adjusted_quantity' => $value]);
                $gaps[] = $line->item()->label.' : '.(float) $line->quantity.' → '.(float) $value.' '.$line->item()->unit;
            }
        }

        return $gaps;
    }

    /**
     * Poste engagé : celui choisi par le demandeur, sinon le poste de matériaux le
     * moins consommé du chantier. Sans budget renseigné, aucun engagement possible.
     *
     * @param  Collection<int, BudgetLine>  $lines
     */
    private function target(PurchaseRequest $request, Collection $lines): BudgetLine
    {
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'Aucun budget (déboursé sec) n\'est renseigné pour ce chantier : l\'engagement ne peut pas être inscrit.',
            ]);
        }

        $chosen = $request->budget_line_id !== null ? $lines->firstWhere('id', $request->budget_line_id) : null;

        if ($chosen instanceof BudgetLine) {
            return $chosen;
        }

        $materials = $lines->where('nature', 'materiaux');
        $pool = $materials->isNotEmpty() ? $materials : $lines;

        /** @var BudgetLine $line */
        $line = $pool->sortByDesc(fn (BudgetLine $l): int => $l->revised_amount - $l->committed_amount - $l->actual_amount)->first();

        return $line;
    }
}
