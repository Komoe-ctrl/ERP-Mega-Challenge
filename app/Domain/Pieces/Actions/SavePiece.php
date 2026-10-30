<?php

declare(strict_types=1);

namespace App\Domain\Pieces\Actions;

use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Pieces\Data\PieceData;
use App\Domain\Pieces\Enums\PieceKind;
use App\Domain\Pieces\Enums\PieceStatus;
use App\Domain\Pieces\Models\LotComponent;
use App\Domain\Pieces\Models\Piece;
use App\Domain\Pieces\Models\ProjectLot;
use App\Domain\Pieces\Models\Rubrique;
use App\Domain\Pieces\Models\ValidationEvent;
use App\Domain\Shared\Support\DocumentNumbers;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et modification d'une pièce de l'auteur, tant qu'elle n'est pas soumise (ou après un rejet).
 * La période d'imputation doit tomber dans un exercice ouvert (RG1.4), l'imputation analytique doit
 * être cohérente (le lot appartient au projet, la composante au lot), le montant de chaque ligne et
 * le total sont calculés ici, jamais reçus du navigateur.
 */
final class SavePiece
{
    public function execute(PieceKind $kind, PieceData $data, User $actor, ?Piece $piece = null): Piece
    {
        if ($piece !== null && (! $piece->status->isEditable() || $piece->requester_id !== $actor->id)) {
            throw ValidationException::withMessages(['piece' => 'Cette pièce n\'est plus modifiable.']);
        }

        $fiscalYear = $this->fiscalYearFor($data->period);
        $this->assertAnalytics($data);
        $lines = $this->computeLines($data);

        return DB::transaction(function () use ($kind, $data, $actor, $piece, $fiscalYear, $lines): Piece {
            $number = $this->number($kind, $data, $piece);

            $attributes = [
                'period' => $data->period,
                'fiscal_year_id' => $fiscalYear->id,
                'title' => $data->title,
                'number' => $number,
                'project_id' => $data->projectId,
                'project_lot_id' => $data->lotId,
                'lot_component_id' => $data->componentId,
                'bc_number' => $data->bcNumber,
                'observation' => $data->observation,
                'total' => array_sum(array_column($lines, 'amount')),
            ];

            if ($piece === null) {
                $piece = Piece::query()->create($attributes + [
                    'kind' => $kind,
                    'requester_id' => $actor->id,
                    'status' => PieceStatus::Saisie,
                ]);

                ValidationEvent::query()->create([
                    'subject_type' => $piece->getMorphClass(),
                    'subject_id' => $piece->id,
                    'action' => 'saisie',
                    'user_id' => $actor->id,
                ]);
            } else {
                $piece->update($attributes);
                $piece->lines()->delete();
            }

            $piece->lines()->createMany($lines);

            return $piece->load('lines');
        });
    }

    /** Exercice ouvert qui contient la période (YYYY-MM). */
    private function fiscalYearFor(string $period): FiscalYear
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m', $period);
        } catch (\Throwable) {
            $day = null;
        }

        if (! $day instanceof CarbonImmutable) {
            throw ValidationException::withMessages(['period' => 'La période d\'imputation est invalide (format AAAA-MM).']);
        }

        $year = FiscalYear::query()
            ->whereDate('starts_on', '<=', $day->toDateString())
            ->whereDate('ends_on', '>=', $day->endOfMonth()->toDateString())
            ->first();

        if ($year === null) {
            throw ValidationException::withMessages(['period' => 'Aucun exercice ne couvre cette période.']);
        }

        if ($year->isClosed()) {
            throw ValidationException::withMessages(['period' => 'L\'exercice de cette période est clôturé : saisie impossible (RG1.4).']);
        }

        return $year;
    }

    private function assertAnalytics(PieceData $data): void
    {
        if ($data->lotId !== null) {
            $lot = ProjectLot::query()->find($data->lotId);

            if ($lot === null || $lot->project_id !== $data->projectId) {
                throw ValidationException::withMessages(['project_lot_id' => 'Ce produit / lot n\'appartient pas au projet choisi.']);
            }
        }

        if ($data->componentId !== null) {
            $component = LotComponent::query()->find($data->componentId);

            if ($data->lotId === null || $component === null || $component->project_lot_id !== $data->lotId) {
                throw ValidationException::withMessages(['lot_component_id' => 'Cette composante n\'appartient pas au produit / lot choisi.']);
            }
        }
    }

    /**
     * @return list<array{designation: string, quantity: float|int|string, unit_price: int, amount: int, rubrique_id: int}>
     */
    private function computeLines(PieceData $data): array
    {
        if ($data->lines === []) {
            throw ValidationException::withMessages(['lines' => 'Saisissez au moins une ligne.']);
        }

        $lines = [];

        foreach ($data->lines as $i => $line) {
            $rubrique = Rubrique::query()->find($line['rubrique_id']);

            if ($rubrique === null || ! $rubrique->is_active) {
                throw ValidationException::withMessages(["lines.{$i}.rubrique_id" => 'Rubrique budgétaire inconnue ou désactivée.']);
            }

            $quantity = (float) $line['quantity'];

            if ($quantity <= 0 || $line['unit_price'] < 0) {
                throw ValidationException::withMessages(["lines.{$i}.quantity" => 'Quantité et prix doivent être positifs.']);
            }

            $lines[] = [
                'designation' => $line['designation'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => (int) round($quantity * $line['unit_price']),
                'rubrique_id' => $line['rubrique_id'],
            ];
        }

        return $lines;
    }

    /** N° saisi (unique par type de pièce) ou numéro automatique (RG1.3). */
    private function number(PieceKind $kind, PieceData $data, ?Piece $piece): string
    {
        if ($data->number === null || trim($data->number) === '') {
            return $piece instanceof Piece ? $piece->number : DocumentNumbers::next('piece_'.$kind->value, $kind->numberFormat());
        }

        $taken = Piece::withTrashed()
            ->where('kind', $kind->value)
            ->where('number', $data->number)
            ->when($piece instanceof Piece, fn ($q) => $q->where('id', '!=', $piece?->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['number' => 'Ce numéro de pièce existe déjà.']);
        }

        return $data->number;
    }
}
