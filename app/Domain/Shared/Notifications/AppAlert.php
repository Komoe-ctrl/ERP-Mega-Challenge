<?php

declare(strict_types=1);

namespace App\Domain\Shared\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte ou notification métier de l'ERP : une même classe, des types
 * (demande, décision, budget, planning…) qui s'affichent dans le centre de
 * notifications et partent par e-mail selon les canaux choisis par chacun.
 */
final class AppAlert extends Notification
{
    public const KINDS = ['demande', 'decision', 'budget', 'planning', 'stock', 'engin', 'echeance'];

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $href,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (! $notifiable instanceof User) {
            return ['database'];
        }

        if ($notifiable->notify_app) {
            $channels[] = 'database';
        }

        if ($notifiable->notify_email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array{kind: string, title: string, body: string, href: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'href' => $this->href];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Bonjour,')
            ->line($this->body)
            ->action('Ouvrir dans l\'ERP', url($this->href));
    }
}
