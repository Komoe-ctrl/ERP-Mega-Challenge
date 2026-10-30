<?php

declare(strict_types=1);

namespace App\Domain\Chantiers\Actions;

use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Chantiers\Models\DailyReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RG7.9 : rapport journalier du chef de chantier (travaux, effectif, matériaux
 * reçus et consommés, incidents, photos). Un seul rapport par chantier et par jour.
 */
final class SubmitDailyReport
{
    /**
     * @param  array{works: string, headcount: int, materials_received: string|null, materials_used: string|null, incidents: string|null}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function execute(Chantier $chantier, User $by, array $data, array $photos): DailyReport
    {
        $today = now()->toDateString();

        if ($chantier->dailyReports()->whereDate('report_date', $today)->exists()) {
            throw ValidationException::withMessages(['works' => 'Un rapport a déjà été envoyé aujourd\'hui pour ce chantier.']);
        }

        return DB::transaction(function () use ($chantier, $by, $data, $photos, $today): DailyReport {
            $report = $chantier->dailyReports()->create($data + ['author_id' => $by->id, 'report_date' => $today]);

            foreach ($photos as $photo) {
                $chantier->addMedia($photo)
                    ->withCustomProperties(['caption' => 'Rapport du '.now()->format('d/m/Y'), 'report_id' => $report->id, 'author' => $by->name])
                    ->toMediaCollection('photos');
            }

            return $report;
        });
    }
}
