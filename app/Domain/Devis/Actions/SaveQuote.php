<?php

declare(strict_types=1);

namespace App\Domain\Devis\Actions;

use App\Domain\Devis\Models\Ouvrage;
use App\Domain\Devis\Models\Quote;
use App\Domain\Projets\Models\Project;
use App\Domain\Shared\Support\DocumentNumbers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création et édition d'un devis en brouillon (M5).
 *
 * Un devis se rattache à un client et à un projet du même client, encore au stade étude, devis ou
 * appel d'offres. Le numéro DEV-AAAA-NNNNN est attribué à la création ; il reste le même pour toutes
 * les versions (RG5.3).
 */
final class SaveQuote
{
    /** Statuts de projet pour lesquels on peut établir un devis. */
    public const QUOTABLE_PROJECT_STATUSES = ['etude', 'devis', 'appel_offres'];

    /**
     * @param  array{client_id: int, project_id: int, discount_rate?: float|int|string|null, vat_rate?: float|int|string|null}  $data
     */
    public function create(array $data, User $by): Quote
    {
        $project = Project::query()->findOrFail($data['project_id']);

        if ($project->client_id !== (int) $data['client_id']) {
            throw ValidationException::withMessages(['project_id' => 'Ce projet n\'appartient pas au client choisi.']);
        }

        if (! in_array($project->status->getValue(), self::QUOTABLE_PROJECT_STATUSES, true)) {
            throw ValidationException::withMessages(['project_id' => 'Seuls les projets au stade étude, devis ou appel d\'offres peuvent recevoir un devis.']);
        }

        return DB::transaction(fn (): Quote => Quote::query()->create([
            'number' => DocumentNumbers::next('devis', 'DEV-{YYYY}-{SEQ:5}'),
            'version' => 1,
            'client_id' => $data['client_id'],
            'project_id' => $data['project_id'],
            'quote_date' => now()->toDateString(),
            'discount_rate' => $data['discount_rate'] ?? 0,
            'vat_rate' => $data['vat_rate'] ?? 18,
            'direct_cost' => 0,
            'created_by' => $by->id,
        ]));
    }

    /**
     * Remplace les lignes et les taux d'un brouillon.
     *
     * @param  array{discount_rate: float|int|string, vat_rate: float|int|string, direct_cost: int, works: list<array{label: string, unit: string, quantity: float|int|string, unit_price: int, components?: string|null, ouvrage_id?: int|null}>}  $data
     */
    public function update(Quote $quote, array $data): Quote
    {
        $this->assertDraft($quote);

        return DB::transaction(function () use ($quote, $data): Quote {
            $quote->update([
                'discount_rate' => $data['discount_rate'],
                'vat_rate' => $data['vat_rate'],
                'direct_cost' => $data['direct_cost'],
            ]);

            $quote->works()->delete();

            foreach ($data['works'] as $position => $work) {
                $quote->works()->create([
                    'position' => $position,
                    'ouvrage_id' => $work['ouvrage_id'] ?? null,
                    'label' => $work['label'],
                    'unit' => $work['unit'],
                    'quantity' => $work['quantity'],
                    'unit_price' => $work['unit_price'],
                    'components' => $work['components'] ?? null,
                ]);
            }

            return $quote->unsetRelation('works');
        });
    }

    /**
     * RG5.1 : ajoute un ouvrage de la bibliothèque, au prix calculé depuis sa composition. Son déboursé sec
     * (quantité × coût unitaire) s'ajoute au déboursé sec du devis, pour la marge prévue (RG5.2).
     */
    public function addOuvrage(Quote $quote, Ouvrage $ouvrage, float $quantity): Quote
    {
        $this->assertDraft($quote);

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité doit être supérieure à 0.']);
        }

        $ouvrage->loadMissing('components.article');

        return DB::transaction(function () use ($quote, $ouvrage, $quantity): Quote {
            $quote->works()->create([
                'position' => (is_numeric($last = $quote->works()->max('position')) ? (int) $last : 0) + 1,
                'ouvrage_id' => $ouvrage->id,
                'label' => $ouvrage->label,
                'unit' => $ouvrage->unit,
                'quantity' => $quantity,
                'unit_price' => $ouvrage->price(),
                'components' => $ouvrage->composition(),
            ]);
            $quote->update(['direct_cost' => $quote->direct_cost + (int) round($ouvrage->cost() * $quantity)]);

            return $quote->unsetRelation('works');
        });
    }

    private function assertDraft(Quote $quote): void
    {
        if ($quote->status->getValue() !== 'brouillon') {
            throw ValidationException::withMessages(['status' => 'Seul un devis en brouillon est modifiable.']);
        }
    }
}
