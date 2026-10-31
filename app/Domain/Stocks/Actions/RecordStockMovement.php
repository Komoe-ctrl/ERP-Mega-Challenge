<?php

declare(strict_types=1);

namespace App\Domain\Stocks\Actions;

use App\Domain\Achats\Models\Article;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Notifications\AlertDispatcher;
use App\Domain\Shared\Notifications\AlertRecipients;
use App\Domain\Shared\Support\BudgetLock;
use App\Domain\Stocks\Enums\MovementType;
use App\Domain\Stocks\Models\StockLevel;
use App\Domain\Stocks\Models\StockMovement;
use App\Domain\Stocks\Models\Warehouse;
use App\Domain\Stocks\Support\StockLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisie d'un mouvement de stock (RG9.1 à RG9.5).
 *
 *  - entrée / retour : crédite le magasin, recalcule le coût moyen pondéré ;
 *  - sortie : débite au coût moyen pondéré ; imputée en coût réel sur le chantier quand elle part
 *    d'un dépôt central (RG9.2) — un magasin de chantier a déjà vu son coût imputé à la réception ;
 *  - transfert : débit du magasin d'origine, crédit du magasin de destination, mouvement tracé ;
 *  - perte / ajustement : exigent un motif et attendent une validation (RG9.3), le stock ne bouge pas avant.
 * Le seuil minimal déclenche une alerte de réapprovisionnement (RG9.4).
 */
final class RecordStockMovement
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly AlertDispatcher $alerts,
    ) {}

    /**
     * @param  array{type: string, warehouse_id: int, article_id: int, quantity: float|int|string, destination_warehouse_id?: int|null, chantier_id?: int|null, unit_cost?: int|null, direction?: string|null, reason?: string|null}  $data
     */
    public function execute(array $data, User $by): StockMovement
    {
        $type = MovementType::from($data['type']);
        $quantity = round((float) $data['quantity'], 3);

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité doit être supérieure à zéro.']);
        }

        if ($type === MovementType::Inventaire) {
            throw ValidationException::withMessages(['type' => 'Un inventaire se saisit depuis l\'écran d\'inventaire.']);
        }

        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
        $article = Article::query()->findOrFail($data['article_id']);
        $reason = isset($data['reason']) ? trim((string) $data['reason']) : '';

        if ($type->needsValidation() && mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Un motif est obligatoire pour une perte ou un ajustement (RG9.3).']);
        }

        $direction = match ($type) {
            MovementType::Entree, MovementType::Retour => 'in',
            MovementType::Ajustement => in_array($data['direction'] ?? null, ['in', 'out'], true) ? (string) $data['direction'] : throw ValidationException::withMessages(['direction' => 'Indiquez si l\'ajustement augmente ou diminue le stock.']),
            default => 'out',
        };

        return DB::transaction(function () use ($data, $by, $type, $quantity, $warehouse, $article, $reason, $direction): StockMovement {
            $movement = StockMovement::query()->create([
                'type' => $type,
                'warehouse_id' => $warehouse->id,
                'destination_warehouse_id' => $data['destination_warehouse_id'] ?? null,
                'article_id' => $article->id,
                'quantity' => $quantity,
                'direction' => $direction,
                'unit_cost' => 0,
                'chantier_id' => $data['chantier_id'] ?? null,
                'reason' => $reason !== '' ? $reason : null,
                'status' => $type->needsValidation() ? StockMovement::PENDING : StockMovement::VALID,
                'created_by' => $by->id,
            ]);

            // RG9.3 : perte et ajustement attendent la validation d'un tiers ; le stock ne bouge pas.
            if ($type->needsValidation()) {
                return $movement;
            }

            return $this->apply($movement, $warehouse, $article, $data['unit_cost'] ?? null);
        });
    }

    /**
     * Applique le mouvement au stock (immédiat, ou à la validation d'une perte / d'un ajustement).
     */
    public function apply(StockMovement $movement, Warehouse $warehouse, Article $article, ?int $unitCost = null): StockMovement
    {
        $quantity = $movement->qty();

        switch ($movement->type) {
            case MovementType::Entree:
                if ($unitCost === null || $unitCost <= 0) {
                    throw ValidationException::withMessages(['unit_cost' => 'Indiquez le coût unitaire de l\'entrée (valorisation au coût moyen pondéré).']);
                }

                $this->ledger->credit($warehouse, $article, $quantity, $unitCost);
                $movement->update(['unit_cost' => $unitCost]);
                break;

            case MovementType::Retour:
                $cost = $this->ledger->averageCost($warehouse, $article) ?: $article->price;
                $this->ledger->credit($warehouse, $article, $quantity, $cost);
                $movement->update(['unit_cost' => $cost]);

                if ($movement->chantier_id !== null && $warehouse->type === Warehouse::CENTRAL) {
                    $this->impute($movement, -1);
                }
                break;

            case MovementType::Sortie:
                $this->guardExit($movement, $warehouse);
                $cost = $this->ledger->averageCost($warehouse, $article);
                $level = $this->ledger->debit($warehouse, $article, $quantity);
                $movement->update(['unit_cost' => $cost]);

                if ($warehouse->type === Warehouse::CENTRAL) {
                    $this->impute($movement, 1);
                }

                $this->alertIfLow($level, $warehouse, $article);
                break;

            case MovementType::Transfert:
                $destination = $movement->destination_warehouse_id === null ? null : Warehouse::query()->find($movement->destination_warehouse_id);

                if ($destination === null || $destination->id === $warehouse->id) {
                    throw ValidationException::withMessages(['destination_warehouse_id' => 'Choisissez un magasin de destination différent.']);
                }

                $cost = $this->ledger->averageCost($warehouse, $article);
                $level = $this->ledger->debit($warehouse, $article, $quantity);
                $this->ledger->credit($destination, $article, $quantity, $cost);
                $movement->update(['unit_cost' => $cost]);
                $this->alertIfLow($level, $warehouse, $article);
                break;

            case MovementType::Perte:
                $cost = $this->ledger->averageCost($warehouse, $article);
                $level = $this->ledger->debit($warehouse, $article, $quantity);
                $movement->update(['unit_cost' => $cost]);
                $this->alertIfLow($level, $warehouse, $article);
                break;

            case MovementType::Ajustement:
            case MovementType::Inventaire:
                $cost = $this->ledger->averageCost($warehouse, $article) ?: $article->price;

                if ($movement->direction === 'in') {
                    $this->ledger->credit($warehouse, $article, $quantity, $cost);
                } else {
                    $level = $this->ledger->debit($warehouse, $article, $quantity);
                    $this->alertIfLow($level, $warehouse, $article);
                }

                $movement->update(['unit_cost' => $cost]);
                break;
        }

        return $movement->refresh();
    }

    /** Une sortie est imputée à un chantier (RG9.2) ; un magasin de chantier ne sert que son chantier. */
    private function guardExit(StockMovement $movement, Warehouse $warehouse): void
    {
        if ($movement->chantier_id === null) {
            throw ValidationException::withMessages(['chantier_id' => 'Une sortie est imputée à un chantier (coût réel, RG9.2).']);
        }

        if ($warehouse->type === Warehouse::SITE && $warehouse->chantier_id !== $movement->chantier_id) {
            throw ValidationException::withMessages(['chantier_id' => 'Un magasin de chantier ne sert que son chantier : utilisez un transfert vers l\'autre magasin.']);
        }
    }

    /**
     * RG9.2 : imputation du coût réel sur le poste « matériaux » du chantier, sous verrou de budget.
     * `$sign` = 1 pour une sortie, -1 pour un retour (le coût est annulé).
     */
    private function impute(StockMovement $movement, int $sign): void
    {
        $chantier = Chantier::query()->findOrFail($movement->chantier_id);
        $amount = (int) round($movement->qty() * $movement->unit_cost) * $sign;

        BudgetLock::run(
            BudgetLine::query()->where('chantier_id', $chantier->id)->where('nature', 'materiaux'),
            function (Collection $lines) use ($amount, $chantier): void {
                $line = $lines->first();

                if (! $line instanceof BudgetLine) {
                    throw ValidationException::withMessages(['chantier_id' => "Le chantier « {$chantier->name} » n'a pas de poste budgétaire Matériaux : impossible d'imputer la sortie."]);
                }

                $line->update(['actual_amount' => max(0, $line->actual_amount + $amount)]);
            },
        );
    }

    /** RG9.4 : alerte de réapprovisionnement sous le seuil minimal, une fois par jour et par article. */
    private function alertIfLow(StockLevel $level, Warehouse $warehouse, Article $article): void
    {
        if (! $level->isLow()) {
            return;
        }

        $this->alerts->send(
            AlertRecipients::withRoles(RoleName::Magasinier, RoleName::ServiceAchatsBtp),
            'stock',
            'Stock sous le seuil minimal',
            "{$article->label} : {$level->qty()} en stock dans {$warehouse->name} (seuil {$level->minimum}).",
            '/stocks',
            "stock-low:{$warehouse->id}:{$article->id}",
        );
    }
}
