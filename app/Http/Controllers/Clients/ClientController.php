<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clients;

use App\Domain\Administration\Data\ActivityEntryData;
use App\Domain\Administration\Models\Activity;
use App\Domain\Clients\Actions\ManageClientContact;
use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientContact;
use App\Domain\Clients\Support\ClientBalance;
use App\Domain\Projets\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\SaveClientRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Clients (M2) : liste, création avec détection de doublon (RG2.1), fiche agrégée (RG2.2, CA2),
 * contacts et documents.
 */
final class ClientController extends Controller
{
    public function index(Request $request, ClientBalance $balance): Response
    {
        $this->authorize('viewAny', Client::class);
        $q = trim($request->string('q')->toString());
        $type = $request->string('type')->toString();

        $clients = Client::query()
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('number', 'like', "%{$q}%")->orWhere('contact', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%"),
            ))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Client $c) use ($balance): array {
                $summary = $balance->for($c);

                return [
                    'id' => $c->id,
                    'number' => $c->number,
                    'name' => $c->name,
                    'type' => $c->type,
                    'contact' => $c->contact ?? '',
                    'phone' => $c->phone ?? '',
                    'city' => $c->city ?? '',
                    'projectsInProgress' => $summary['projectsInProgress'],
                    'invoiced' => $summary['invoiced'],
                    'paid' => $summary['paid'],
                ];
            });

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => ['q' => $q, 'type' => $type],
            'types' => Client::TYPES,
            'can' => ['create' => $request->user()?->can('create', Client::class) ?? false],
        ]);
    }

    public function store(SaveClientRequest $request, SaveClient $action): RedirectResponse
    {
        $client = $action->create($request->clientData(), $request->boolean('confirm_duplicate'));

        $this->toast("Client {$client->number} créé.");

        return to_route('clients.show', $client);
    }

    public function show(Request $request, Client $client, ClientBalance $balance): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'number' => $client->number,
                'name' => $client->name,
                'type' => $client->type,
                'contact' => $client->contact,
                'phone' => $client->phone,
                'email' => $client->email,
                'city' => $client->city,
                'address' => $client->address,
            ],
            // RG2.2 / CA2 : projets en cours et créances en un écran.
            'summary' => $balance->for($client),
            'projects' => Project::query()->where('client_id', $client->id)->orderByDesc('id')->get()
                ->map(fn (Project $p): array => [
                    'id' => $p->id,
                    'number' => $p->number,
                    'name' => $p->name,
                    'status' => $p->status->getValue(),
                    'budget' => $p->budget,
                ])->values(),
            'contacts' => $client->contacts->map(fn (ClientContact $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'role' => $c->role,
                'phone' => $c->phone,
                'email' => $c->email,
            ])->values(),
            'documents' => $client->getMedia('documents')->map(fn (Media $m): array => [
                'id' => $m->id,
                'name' => $m->file_name,
                'size' => $m->human_readable_size,
                'date' => $m->created_at?->toDateString() ?? '',
            ])->values(),
            // Historique : modifications de la fiche (journal des activités).
            'history' => Activity::query()->with('causer')
                ->where('subject_type', $client->getMorphClass())->where('subject_id', $client->id)
                ->latest('id')->limit(15)->get()
                ->map(fn (Activity $a): ActivityEntryData => ActivityEntryData::from($a))->values(),
            'can' => [
                'update' => $request->user()?->can('update', $client) ?? false,
            ],
        ]);
    }

    public function update(SaveClientRequest $request, Client $client, SaveClient $action): RedirectResponse
    {
        $action->update($client, $request->clientData());

        $this->toast('Fiche client mise à jour.');

        return back();
    }

    public function storeContact(Request $request, Client $client, ManageClientContact $action): RedirectResponse
    {
        $this->authorize('update', $client);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $action->add($client, [
            'name' => $validated['name'],
            'role' => $validated['role'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);
        $this->toast('Contact ajouté.');

        return back();
    }

    public function destroyContact(Client $client, ClientContact $contact, ManageClientContact $action): RedirectResponse
    {
        $this->authorize('update', $client);
        abort_unless($contact->client_id === $client->id, 404);
        $action->remove($contact);
        $this->toast('Contact supprimé.');

        return back();
    }

    public function storeDocument(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $request->validate(['document' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx']]);

        $client->addMediaFromRequest('document')->toMediaCollection('documents');
        $this->toast('Document ajouté.');

        return back();
    }

    public function document(Client $client, int $media): HttpResponse
    {
        $this->authorize('view', $client);
        $file = $client->getMedia('documents')->firstWhere('id', $media);
        abort_if($file === null, 404);

        return $file->toResponse(request());
    }

    public function destroyDocument(Client $client, int $media): RedirectResponse
    {
        $this->authorize('update', $client);
        $file = $client->getMedia('documents')->firstWhere('id', $media);
        abort_if($file === null, 404);
        $file->delete();
        $this->toast('Document supprimé.');

        return back();
    }
}
