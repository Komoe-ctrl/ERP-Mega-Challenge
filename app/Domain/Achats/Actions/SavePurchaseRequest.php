<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Achats\States\EnAttente;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Support\BudgetSummary;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisie d'une demande d'achat par un demandeur (RG8.1 à RG8.4) : brouillon,
 * correction après renvoi, puis soumission. Le demandeur ne saisit jamais de
 * prix (RG8.2) : seuls l'article et la quantité comptent.
 */
final class SavePurchaseRequest
{
    public function __construct(private readonly RequestRouting $routing) {}

    /**
     * @param  array{chantier_id: int, budget_line_id: int|null, justification: string|null, lines: list<array{article_id: int, quantity: float|int|string, need_date: string, urgency: string}>}  $data
     */
    public function create(array $data, User $by, bool $submit): PurchaseRequest
    {
        $chantier = $this->chantier($data['chantier_id']);
        $this->check($data, $chantier, $submit);

        return DB::transaction(function () use ($data, $by, $submit, $chantier): PurchaseRequest {
            $request = new PurchaseRequest([
                'number' => $this->nextNumber(),
                'chantier_id' => $chantier->id,
                'budget_line_id' => $data['budget_line_id'],
                'requester_id' => $by->id,
                'justification' => $data['justification'],
            ]);
            $request->save();
            $this->replaceLines($request, $data['lines']);
            $request->events()->create(['user_id' => $by->id, 'action' => 'Création (brouillon)']);

            if ($submit) {
                $this->submit($request, $by);
            }

            return $request;
        });
    }

    /**
     * @param  array{chantier_id: int, budget_line_id: int|null, justification: string|null, lines: list<array{article_id: int, quantity: float|int|string, need_date: string, urgency: string}>}  $data
     */
    public function update(PurchaseRequest $request, array $data, User $by, bool $submit): PurchaseRequest
    {
        $chantier = $this->chantier($request->chantier_id);
        $this->check($data, $chantier, $submit);

        return DB::transaction(function () use ($request, $data, $by, $submit): PurchaseRequest {
            $request->update([
                'budget_line_id' => $data['budget_line_id'],
                'justification' => $data['justification'],
            ]);
            $this->replaceLines($request, $data['lines']);

            if ($submit) {
                $this->submit($request, $by);
            }

            return $request;
        });
    }

    /** Passage à « En attente » : route la demande (Direction ou valideur du chantier) et fige le montant affiché. */
    private function submit(PurchaseRequest $request, User $by): void
    {
        $request->load('lines.article');
        $summary = BudgetSummary::fromLines(BudgetLine::query()->where('chantier_id', $request->chantier_id)->get());
        $total = $request->computeTotal();

        $request->needs_direction = $this->routing->needsDirection($request->requestedTotal(), $total, $summary->remaining());
        $request->total_amount = $total;
        $request->status->transitionTo(EnAttente::class);
        $request->events()->create([
            'user_id' => $by->id,
            'action' => 'Soumise pour validation',
            'reason' => $request->needs_direction ? 'Validation de la Direction requise (budget ou seuil dépassé)' : null,
        ]);
    }

    /**
     * Règles de gestion vérifiées par le serveur, quel que soit ce que l'écran affiche.
     *
     * @param  array{chantier_id: int, budget_line_id: int|null, justification: string|null, lines: list<array{article_id: int, quantity: float|int|string, need_date: string, urgency: string}>}  $data
     */
    private function check(array $data, Chantier $chantier, bool $submit): void
    {
        // RG4.3 : aucune demande sur un chantier en lecture seule.
        if ($chantier->isReadOnly()) {
            throw ValidationException::withMessages(['chantier_id' => 'Ce chantier est en lecture seule : aucune demande n\'est possible.']);
        }

        // RG8.1 : le poste visé appartient au chantier de la demande.
        if ($data['budget_line_id'] !== null && ! BudgetLine::query()->whereKey($data['budget_line_id'])->where('chantier_id', $chantier->id)->exists()) {
            throw ValidationException::withMessages(['budget_line_id' => 'Ce poste n\'appartient pas au chantier choisi.']);
        }

        // Seuls les articles actifs du référentiel sont proposés (RG8.14).
        $inactive = Article::query()->whereIn('id', array_column($data['lines'], 'article_id'))->where('is_active', false)->exists();

        if ($inactive) {
            throw ValidationException::withMessages(['lines' => 'Un article désactivé du référentiel ne peut plus être demandé.']);
        }

        if ($submit && mb_strlen(trim((string) $data['justification'])) < 5) {
            throw ValidationException::withMessages(['justification' => 'La justification est obligatoire (5 caractères minimum).']);
        }
    }

    /** @param list<array{article_id: int, quantity: float|int|string, need_date: string, urgency: string}> $lines */
    private function replaceLines(PurchaseRequest $request, array $lines): void
    {
        $request->lines()->delete();

        foreach ($lines as $line) {
            $request->lines()->create([
                'article_id' => $line['article_id'],
                'quantity' => $line['quantity'],
                'need_date' => $line['need_date'],
                'urgency' => $line['urgency'],
            ]);
        }

        $request->unsetRelation('lines');
    }

    private function chantier(int $id): Chantier
    {
        return Chantier::query()->with('project')->findOrFail($id);
    }

    /** Numéro DA-AAAA-NNNN, attribué dans la transaction avec verrou pour éviter les doublons. */
    private function nextNumber(): string
    {
        $year = now()->year;
        $last = PurchaseRequest::query()->where('number', 'like', "DA-{$year}-%")->lockForUpdate()->orderByDesc('number')->value('number');
        $sequence = is_string($last) ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('DA-%d-%04d', $year, $sequence);
    }
}
