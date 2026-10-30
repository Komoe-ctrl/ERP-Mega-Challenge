<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budget;

use App\Domain\Budget\Actions\CreateBudgetLine;
use App\Domain\Budget\Actions\ManageBudgetRevision;
use App\Domain\Budget\Actions\RecordExpense;
use App\Domain\Budget\Models\BudgetExpense;
use App\Domain\Budget\Models\BudgetLine;
use App\Domain\Budget\Models\BudgetRevision;
use App\Domain\Budget\Support\BudgetSummary;
use App\Domain\Chantiers\Models\Chantier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\RequestRevisionRequest;
use App\Http\Requests\Budget\StoreBudgetLineRequest;
use App\Http\Requests\Budget\StoreExpenseRequest;
use App\Http\Requests\ReasonRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Budget et dépenses d'un chantier (M10, version V1) : déboursé sec par poste,
 * engagé, coût réel, reste à engager, révisions motivées et dépenses diverses.
 */
final class BudgetController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BudgetLine::class);
        $user = $this->user($request);

        $rows = Chantier::query()->visibleTo($user)->whereHas('budgetLines')->with('budgetLines')->orderBy('code')->get()
            ->map(fn (Chantier $c): array => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
            ] + BudgetSummary::fromLines($c->budgetLines)->toArray())->values();

        return Inertia::render('Budget/Index', ['rows' => $rows]);
    }

    public function show(Request $request, Chantier $chantier): Response
    {
        $this->authorize('viewBudget', [BudgetLine::class, $chantier]);
        $user = $this->user($request);

        $lines = $chantier->budgetLines()->orderBy('id')->get();
        /** @var Collection<int, string> $postNames */
        $postNames = $lines->mapWithKeys(fn (BudgetLine $l): array => [$l->id => $l->post]);

        return Inertia::render('Budget/Show', [
            'chantier' => ['id' => $chantier->id, 'code' => $chantier->code, 'name' => $chantier->name],
            'lines' => $lines->map(fn (BudgetLine $l): array => [
                'id' => $l->id,
                'chantierId' => $l->chantier_id,
                'post' => $l->post,
                'nature' => $l->nature,
                'initial' => $l->initial_amount,
                'revised' => $l->revised_amount,
                'committed' => $l->committed_amount,
                'actual' => $l->actual_amount,
            ])->values(),
            'totals' => BudgetSummary::fromLines($lines)->toArray(),
            'revisions' => BudgetRevision::query()->where('chantier_id', $chantier->id)->with(['requester', 'decider'])->orderByDesc('id')->get()
                ->map(fn (BudgetRevision $r): array => [
                    'id' => $r->id,
                    'date' => $r->created_at?->toDateString() ?? '',
                    'post' => $this->postName($postNames, $r->budget_line_id),
                    'amount' => $r->amount,
                    'reason' => $r->reason,
                    'author' => data_get($r, 'requester.name', '—'),
                    'status' => $r->status,
                    'decidedBy' => data_get($r, 'decider.name'),
                    'decisionReason' => $r->decision_reason,
                    'canDecide' => $user->can('decideRevision', [BudgetLine::class, $r]),
                ])->values(),
            'expenses' => BudgetExpense::query()->where('chantier_id', $chantier->id)->with(['user', 'media'])->orderByDesc('id')->get()
                ->map(fn (BudgetExpense $e): array => [
                    'id' => $e->id,
                    'date' => $e->spent_on->toDateString(),
                    'label' => $e->label,
                    'post' => $this->postName($postNames, $e->budget_line_id),
                    'amount' => $e->amount,
                    'receipt' => data_get($e->getFirstMedia('receipts'), 'file_name', ''),
                    'receiptUrl' => $e->getFirstMedia('receipts')?->getUrl() ?? '',
                    'author' => data_get($e, 'user.name', '—'),
                ])->values(),
            'canManage' => $user->can('manage', [BudgetLine::class, $chantier]),
            'canExpense' => $user->can('recordExpense', [BudgetLine::class, $chantier]),
        ]);
    }

    public function storeLine(StoreBudgetLineRequest $request, Chantier $chantier, CreateBudgetLine $action): RedirectResponse
    {
        $action->execute($chantier, $request->lineData());
        $this->toast('Poste ajouté au déboursé sec.');

        return back();
    }

    public function requestRevision(RequestRevisionRequest $request, Chantier $chantier, ManageBudgetRevision $action): RedirectResponse
    {
        $action->request($chantier, $request->revisionData(), $this->user($request));
        $this->toast('Révision soumise à validation.');

        return back();
    }

    public function validateRevision(Request $request, BudgetRevision $revision, ManageBudgetRevision $action): RedirectResponse
    {
        $this->authorize('decideRevision', [BudgetLine::class, $revision]);
        $action->validate($revision, $this->user($request));
        $this->toast('Révision validée : le budget révisé est mis à jour.');

        return back();
    }

    public function rejectRevision(ReasonRequest $request, BudgetRevision $revision, ManageBudgetRevision $action): RedirectResponse
    {
        $this->authorize('decideRevision', [BudgetLine::class, $revision]);
        $action->reject($revision, $this->user($request), $request->string('reason')->toString());
        $this->toast('Révision rejetée.');

        return back();
    }

    public function storeExpense(StoreExpenseRequest $request, Chantier $chantier, RecordExpense $action): RedirectResponse
    {
        $receipt = $request->file('receipt');
        assert($receipt instanceof UploadedFile);
        $action->execute($chantier, $request->expenseData(), $receipt, $this->user($request));
        $this->toast('Dépense enregistrée et imputée au poste.');

        return back();
    }

    /** @param Collection<int, string> $names */
    private function postName(Collection $names, int $lineId): string
    {
        return $names->get($lineId, '—');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
