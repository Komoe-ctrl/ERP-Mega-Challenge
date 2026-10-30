<?php

declare(strict_types=1);

namespace App\Http\Controllers\Achats;

use App\Domain\Achats\Actions\ManageMissingArticleReport;
use App\Domain\Achats\Models\MissingArticleReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\ReportMissingArticleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Signalements d'articles manquants (RG8.15).
 */
final class MissingArticleReportController extends Controller
{
    public function store(ReportMissingArticleRequest $request, ManageMissingArticleReport $action): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $chantier = $request->integer('chantier_id');
        $action->report($request->string('label')->toString(), $chantier > 0 ? $chantier : null, $user);
        $this->toast('Signalement envoyé au Service Achats BTP.');

        return back();
    }

    public function resolve(Request $request, MissingArticleReport $report, ManageMissingArticleReport $action): RedirectResponse
    {
        $this->authorize('update', $report);
        $user = $request->user();
        assert($user instanceof User);
        $action->resolve($report, $user, null);
        $this->toast('Demande marquée comme traitée.');

        return back();
    }
}
