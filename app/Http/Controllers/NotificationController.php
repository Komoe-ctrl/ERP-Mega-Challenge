<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centre de notifications de l'utilisateur connecté. Chacun ne voit, ne lit et
 * ne règle que ses propres notifications.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Notifications/Index', [
            'notifications' => $user->notifications()->latest()->limit(50)->get()->map(
                fn (DatabaseNotification $n): array => [
                    'id' => $n->id,
                    'kind' => data_get($n->data, 'kind', 'demande'),
                    'title' => data_get($n->data, 'title', ''),
                    'body' => data_get($n->data, 'body', ''),
                    'date' => $n->created_at?->format('Y-m-d H:i') ?? '',
                    'read' => $n->read_at !== null,
                    'href' => data_get($n->data, 'href', '/notifications'),
                ],
            )->values(),
            'channels' => ['app' => $user->notify_app, 'email' => $user->notify_email],
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $this->user($request)->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function channels(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app' => ['required', 'boolean'],
            'email' => ['required', 'boolean'],
        ], [
            'app.required' => 'Précisez si vous souhaitez recevoir les notifications dans l\'application.',
            'email.required' => 'Précisez si vous souhaitez recevoir les notifications par e-mail.',
        ]);

        $this->user($request)->forceFill([
            'notify_app' => (bool) $validated['app'],
            'notify_email' => (bool) $validated['email'],
        ])->save();
        $this->toast('Préférences de notification enregistrées.');

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
