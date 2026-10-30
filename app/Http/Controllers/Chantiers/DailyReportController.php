<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chantiers;

use App\Domain\Chantiers\Actions\SubmitDailyReport;
use App\Domain\Chantiers\Models\Chantier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chantiers\DailyReportRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rapport journalier du chef de chantier (RG7.9).
 */
final class DailyReportController extends Controller
{
    public function create(Chantier $chantier): Response
    {
        $this->authorize('update', $chantier);

        return Inertia::render('Chantiers/Rapport', [
            'chantier' => ['id' => $chantier->id, 'code' => $chantier->code, 'name' => $chantier->name],
            'today' => now()->toDateString(),
            'alreadySent' => $chantier->dailyReports()->whereDate('report_date', now()->toDateString())->exists(),
        ]);
    }

    public function store(DailyReportRequest $request, Chantier $chantier, SubmitDailyReport $action): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $action->execute($chantier, $user, $request->reportData(), $request->photos());
        $this->toast('Rapport journalier enregistré.');

        return to_route('chantiers.show', $chantier);
    }
}
