<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Budget\Models\BudgetCommitment;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Chantiers\Models\Chantier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demandes d'achat de démonstration (reprises des maquettes), à tous les
 * statuts. Les demandes validées ont leurs prix figés et un engagement au
 * registre (déjà compté dans les montants engagés semés par BudgetSeeder).
 */
class PurchaseRequestsSeeder extends Seeder
{
    /** Référence d'article par identifiant des maquettes. */
    private const REFERENCES = [
        1 => 'CIM-001', 2 => 'CIM-002', 5 => 'FER-012', 6 => 'FER-014', 7 => 'FER-020', 11 => 'GRA-002',
        12 => 'BOI-001', 13 => 'BOI-002', 14 => 'BOI-003', 17 => 'PLO-001', 18 => 'PLO-002', 19 => 'PLO-003',
        23 => 'QUI-001', 24 => 'QUI-002', 25 => 'MAC-001', 26 => 'MAC-002',
    ];

    public function run(): void
    {
        foreach ($this->requests() as [$number, $code, $requester, $date, $status, $justification, $lines, $events]) {
            $chantier = Chantier::query()->where('code', $code)->first();
            $requesterId = User::query()->where('name', $requester)->value('id');

            if ($chantier === null || $requesterId === null || PurchaseRequest::query()->where('number', $number)->exists()) {
                continue;
            }

            $request = PurchaseRequest::query()->create([
                'number' => $number, 'chantier_id' => $chantier->id, 'requester_id' => $requesterId, 'justification' => $justification,
            ]);
            $request->forceFill(['created_at' => $date, 'status' => $status])->saveQuietly();

            foreach ($lines as [$articleId, $quantity, $needDate, $urgency]) {
                $article = Article::query()->where('reference', self::REFERENCES[$articleId])->firstOrFail();
                $request->lines()->create([
                    'article_id' => $article->id, 'quantity' => $quantity, 'need_date' => $needDate, 'urgency' => $urgency,
                    'frozen_price' => $status === 'validee' ? $article->price : null,
                ]);
            }

            foreach ($events as [$at, $actor, $action, $reason]) {
                $event = $request->events()->create(['user_id' => User::query()->where('name', $actor)->value('id'), 'action' => $action, 'reason' => $reason]);
                $event->forceFill(['created_at' => $at])->save();
            }

            $request->load('lines.article');
            $total = $request->computeTotal();
            $request->forceFill(['total_amount' => $total, 'needs_direction' => $number === 'DA-2026-0141'])->saveQuietly();

            if ($status === 'validee') {
                $line = BudgetLine::query()->where('chantier_id', $chantier->id)->where('nature', 'materiaux')->orderBy('id')->first();

                if ($line !== null) {
                    $request->forceFill(['budget_line_id' => $line->id, 'decided_at' => $date])->saveQuietly();
                    BudgetCommitment::query()->create([
                        'chantier_id' => $chantier->id, 'budget_line_id' => $line->id, 'source_type' => $request->getMorphClass(),
                        'source_id' => $request->id, 'amount' => $total, 'status' => BudgetCommitment::STATUS_ACTIVE,
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array{string, string, string, string, string, string, list<array{int, int, string, string}>, list<array{string, string, string, string|null}>}>
     */
    private function requests(): array
    {
        return [
            ['DA-2026-0142', 'CH-001', 'Séraphin Konan', '2026-10-27', 'en_attente', 'Ferraillage du plancher haut R+1, zone nord.',
                [[5, 400, '2026-11-02', 'urgente'], [7, 600, '2026-11-02', 'normale'], [1, 300, '2026-11-03', 'normale']],
                [['2026-10-27 08:12', 'Séraphin Konan', 'Création (brouillon)', null], ['2026-10-27 08:40', 'Séraphin Konan', 'Soumise pour validation', null]]],
            ['DA-2026-0141', 'CH-002', 'Lanciné Touré', '2026-10-26', 'en_attente', 'Béton de propreté et poteaux RDC.',
                [[11, 120, '2026-11-04', 'normale'], [2, 900, '2026-11-04', 'normale'], [6, 700, '2026-11-05', 'urgente']],
                [['2026-10-26 10:02', 'Lanciné Touré', 'Création (brouillon)', null], ['2026-10-26 10:30', 'Lanciné Touré', 'Soumise pour validation', 'Validation de la Direction requise (budget ou seuil dépassé)']]],
            ['DA-2026-0140', 'CH-001', 'Séraphin Konan', '2026-10-22', 'validee', 'Maçonnerie des cloisons RDC.',
                [[25, 4000, '2026-10-28', 'normale'], [26, 1500, '2026-10-28', 'normale'], [1, 150, '2026-10-28', 'normale']],
                [['2026-10-22 09:00', 'Séraphin Konan', 'Création (brouillon)', null], ['2026-10-22 09:20', 'Séraphin Konan', 'Soumise pour validation', null], ['2026-10-23 11:45', 'Ibrahim Coulibaly', 'Validée', 'Prix figés, engagement de 3 675 000 FCFA inscrit au budget']]],
            ['DA-2026-0139', 'CH-002', 'Lanciné Touré', '2026-10-20', 'renvoyee', 'Coffrage poutres.',
                [[12, 300, '2026-11-02', 'normale'], [13, 90, '2026-11-02', 'normale'], [23, 40, '2026-11-02', 'normale']],
                [['2026-10-20 14:00', 'Lanciné Touré', 'Soumise pour validation', null], ['2026-10-21 09:30', 'Ibrahim Coulibaly', 'Renvoyée pour correction', 'Quantité de chevrons surévaluée : revoir le métré du coffrage.']]],
            ['DA-2026-0138', 'CH-001', 'Séraphin Konan', '2026-10-19', 'refusee', 'Brouette supplémentaire.',
                [[24, 4, '2026-10-25', 'normale']],
                [['2026-10-19 16:00', 'Séraphin Konan', 'Soumise pour validation', null], ['2026-10-20 08:15', 'Ibrahim Coulibaly', 'Refusée', 'Article désactivé du référentiel ; 6 brouettes déjà disponibles au magasin.']]],
            ['DA-2026-0137', 'CH-003', 'Konan Yeboua', '2026-10-28', 'brouillon', 'Installation de chantier : base-vie.',
                [[12, 80, '2026-11-02', 'normale'], [23, 20, '2026-11-02', 'normale'], [14, 10, '2026-11-02', 'normale']],
                [['2026-10-28 17:20', 'Konan Yeboua', 'Création (brouillon)', null]]],
            ['DA-2026-0136', 'CH-001', 'Séraphin Konan', '2026-10-15', 'validee', 'Plomberie — réseaux EU/EV.',
                [[17, 120, '2026-11-02', 'normale'], [18, 200, '2026-11-02', 'normale'], [19, 30, '2026-11-02', 'normale']],
                [['2026-10-15 09:00', 'Séraphin Konan', 'Soumise pour validation', null], ['2026-10-16 10:00', 'Ibrahim Coulibaly', 'Validée', null]]],
            ['DA-2026-0135', 'CH-002', 'Lanciné Touré', '2026-10-12', 'annulee', 'Doublon de la demande DA-2026-0133.',
                [[1, 100, '2026-10-20', 'normale']],
                [['2026-10-12 11:00', 'Lanciné Touré', 'Soumise pour validation', null], ['2026-10-12 11:30', 'Lanciné Touré', 'Annulée par son auteur', 'Doublon']]],
        ];
    }
}
