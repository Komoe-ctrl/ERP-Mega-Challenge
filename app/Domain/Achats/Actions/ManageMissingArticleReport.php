<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Achats\Models\MissingArticleReport;
use App\Models\User;

/**
 * RG8.15 : un demandeur signale un article manquant ; le Service Achats BTP
 * reçoit la demande puis la marque traitée (éventuellement avec l'article créé).
 */
final class ManageMissingArticleReport
{
    public function report(string $label, ?int $chantierId, User $by): MissingArticleReport
    {
        return MissingArticleReport::query()->create([
            'label' => $label,
            'chantier_id' => $chantierId,
            'requester_id' => $by->id,
            'status' => MissingArticleReport::STATUS_PENDING,
        ]);
    }

    public function resolve(MissingArticleReport $report, User $by, ?int $articleId): MissingArticleReport
    {
        $report->update([
            'status' => MissingArticleReport::STATUS_DONE,
            'handled_by' => $by->id,
            'article_id' => $articleId,
        ]);

        return $report;
    }
}
