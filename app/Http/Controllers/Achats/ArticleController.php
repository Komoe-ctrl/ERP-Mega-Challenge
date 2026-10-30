<?php

declare(strict_types=1);

namespace App\Http\Controllers\Achats;

use App\Domain\Achats\Actions\ImportArticles;
use App\Domain\Achats\Actions\SaveArticle;
use App\Domain\Achats\Actions\SetArticleActive;
use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\ArticlePriceHistory;
use App\Domain\Achats\Models\MissingArticleReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\ImportArticlesRequest;
use App\Http\Requests\Achats\SaveArticleRequest;
use App\Http\Requests\ReasonRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Référentiel des prix (M8.3). Lecture pour tous les profils Achats ; écriture
 * réservée au Service Achats BTP (RG8.13, CA8.3).
 */
final class ArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Article::class);
        $user = $this->user($request);

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'category' => $request->string('category')->toString(),
            'state' => $request->string('state')->toString(),
        ];

        $articles = Article::query()
            ->when($filters['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('label', 'like', "%{$filters['q']}%")->orWhere('reference', 'like', "%{$filters['q']}%"),
            ))
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['state'] === 'actif', fn ($q) => $q->where('is_active', true))
            ->when($filters['state'] === 'inactif', fn ($q) => $q->where('is_active', false))
            ->orderBy('reference')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Article $a): array => $this->present($a));

        $canWrite = $user->can('create', Article::class);

        return Inertia::render('Achats/Referentiel/Index', [
            'articles' => $articles,
            'filters' => $filters,
            'categories' => Article::CATEGORIES,
            'canWrite' => $canWrite,
            'missingReports' => $user->can('viewAny', MissingArticleReport::class)
                ? MissingArticleReport::query()->with(['requester', 'chantier'])
                    ->where('status', MissingArticleReport::STATUS_PENDING)->orderBy('id')->get()
                    ->map(fn (MissingArticleReport $m): array => [
                        'id' => $m->id,
                        'label' => $m->label,
                        'requester' => data_get($m, 'requester.name', '—'),
                        'chantier' => data_get($m, 'chantier.name', '—'),
                        'date' => $m->created_at?->toDateString() ?? '',
                    ])->values()
                : [],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Article::class);

        return Inertia::render('Achats/Referentiel/Form', ['article' => null, ...$this->options()]);
    }

    public function store(SaveArticleRequest $request, SaveArticle $action): RedirectResponse
    {
        $action->create($request->articleData(), $this->user($request));
        $this->toast('Article créé.');

        return to_route('achats.referentiel');
    }

    public function edit(Request $request, Article $article): Response
    {
        $this->authorize('viewAny', Article::class);
        $user = $this->user($request);

        return Inertia::render('Achats/Referentiel/Form', [
            'article' => $this->present($article) + [
                'history' => $article->priceHistory()->with('user')->get()->map(fn (ArticlePriceHistory $h): array => [
                    'date' => $h->created_at?->toDateString() ?? '',
                    'price' => $h->price,
                    'by' => data_get($h, 'user.name', '—'),
                    'reason' => $h->reason,
                ])->values(),
            ],
            'canWrite' => $user->can('update', $article),
            ...$this->options(),
        ]);
    }

    public function update(SaveArticleRequest $request, Article $article, SaveArticle $action): RedirectResponse
    {
        $action->update($article, $request->articleData(), $request->string('reason')->toString(), $this->user($request));
        $this->toast('Article enregistré.');

        return to_route('achats.referentiel');
    }

    public function deactivate(ReasonRequest $request, Article $article, SetArticleActive $action): RedirectResponse
    {
        $this->authorize('update', $article);
        $action->execute($article, false, $this->user($request), $request->string('reason')->toString());
        $this->toast('Article désactivé.');

        return to_route('achats.referentiel');
    }

    public function reactivate(Request $request, Article $article, SetArticleActive $action): RedirectResponse
    {
        $this->authorize('update', $article);
        $action->execute($article, true, $this->user($request));
        $this->toast('Article réactivé.');

        return back();
    }

    public function import(ImportArticlesRequest $request, ImportArticles $action): RedirectResponse
    {
        $file = $request->file('file');
        assert($file instanceof UploadedFile);
        $result = $action->execute($file, $this->user($request));
        $this->toast("Import terminé : {$result['created']} article(s) créé(s), {$result['updated']} mis à jour.");

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Article $a): array
    {
        return [
            'id' => $a->id,
            'reference' => $a->reference,
            'label' => $a->label,
            'category' => $a->category,
            'unit' => $a->unit,
            'price' => $a->price,
            'active' => $a->is_active,
            'updatedAt' => $a->updated_at?->toDateString() ?? '',
        ];
    }

    /**
     * @return array{categories: list<string>, units: list<string>}
     */
    private function options(): array
    {
        return ['categories' => Article::CATEGORIES, 'units' => Article::UNITS];
    }
}
