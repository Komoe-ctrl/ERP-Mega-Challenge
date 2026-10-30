<?php

declare(strict_types=1);

namespace App\Domain\Shared\Notifications;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Envoi des alertes aux destinataires. Un destinataire désactivé, ou l'auteur
 * de l'événement, ne reçoit rien. Avec une clé de déduplication, une même
 * alerte n'est pas renvoyée plus d'une fois par jour au même destinataire.
 */
final class AlertDispatcher
{
    /**
     * @param  iterable<User>  $recipients
     * @return int nombre de destinataires notifiés
     */
    public function send(iterable $recipients, string $kind, string $title, string $body, string $href, ?string $dedupKey = null, ?User $except = null): int
    {
        $sent = 0;
        $unique = [];

        foreach ($recipients as $recipient) {
            $unique[$recipient->id] = $recipient;
        }

        foreach ($unique as $user) {
            if (! $user->is_active || ($except instanceof User && $except->id === $user->id)) {
                continue;
            }

            if ($dedupKey !== null && ! $this->claim($user, $dedupKey)) {
                continue;
            }

            try {
                $user->notify(new AppAlert($kind, $title, $body, $href));
                $sent++;
            } catch (Throwable $e) {
                // Un échec d'envoi (messagerie indisponible) ne doit jamais bloquer l'action métier.
                report($e);
            }
        }

        return $sent;
    }

    /** Réserve l'envoi du jour ; faux si l'alerte a déjà été envoyée aujourd'hui. */
    private function claim(User $user, string $dedupKey): bool
    {
        try {
            DB::table('sent_alerts')->insert(['user_id' => $user->id, 'dedup_key' => $dedupKey, 'sent_on' => now()->toDateString()]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
