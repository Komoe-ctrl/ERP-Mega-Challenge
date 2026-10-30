<?php

declare(strict_types=1);

namespace App\Domain\Administration\Data;

use App\Domain\Administration\Models\Activity;
use App\Domain\Administration\Models\Agency;
use App\Domain\Administration\Models\FiscalYear;
use App\Domain\Administration\Models\Role;
use App\Domain\Administration\Models\Service;
use App\Domain\Administration\Models\Setting;
use App\Domain\Administration\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Entrée du journal telle que l'affiche l'écran (type TypeScript ActivityEntry).
 */
final readonly class ActivityEntryData
{
    /** Événements du journal → événements de l'écran. */
    public const EVENTS = [
        'created' => 'creation',
        'updated' => 'modification',
        'validation' => 'validation',
        'deactivation' => 'suppression',
        'deleted' => 'suppression',
    ];

    private const SUBJECTS = [
        User::class => 'Utilisateur',
        Role::class => 'Rôle',
        Agency::class => 'Agence',
        Service::class => 'Service',
        Site::class => 'Site',
        Setting::class => 'Paramètre',
        FiscalYear::class => 'Exercice',
    ];

    /**
     * @param  list<array{field: string, before: string, after: string}>  $changes
     */
    private function __construct(
        public int $id,
        public string $date,
        public string $actor,
        public string $event,
        public string $subject,
        public array $changes,
    ) {}

    /**
     * Valeurs de la colonne `event` qui correspondent à un événement d'écran.
     *
     * @return list<string>
     */
    public static function storedEvents(string $screenEvent): array
    {
        return array_keys(array_filter(self::EVENTS, fn (string $e): bool => $e === $screenEvent));
    }

    public static function from(Activity $activity): self
    {
        $changes = [];
        $attributes = (array) ($activity->attribute_changes?->get('attributes') ?? []);
        $old = (array) ($activity->attribute_changes?->get('old') ?? []);

        foreach ($attributes as $field => $after) {
            $changes[] = [
                'field' => (string) $field,
                'before' => array_key_exists($field, $old) ? self::show($old[$field]) : '—',
                'after' => self::show($after),
            ];
        }

        if (is_string($motif = $activity->getProperty('motif'))) {
            $changes[] = ['field' => 'Motif', 'before' => '—', 'after' => $motif];
        }

        return new self(
            id: (int) $activity->id,
            date: $activity->created_at?->format('Y-m-d H:i') ?? '',
            actor: $activity->causer instanceof User ? $activity->causer->name : 'Système',
            event: self::EVENTS[$activity->event ?? ''] ?? 'modification',
            subject: self::subjectLabel($activity),
            changes: $changes,
        );
    }

    private static function subjectLabel(Activity $activity): string
    {
        $type = (string) $activity->subject_type;
        $noun = self::SUBJECTS[$type] ?? class_basename($type);
        $subject = $activity->subject;

        if (! $subject instanceof Model) {
            return "{$noun} #{$activity->subject_id}";
        }

        $name = $subject->getAttribute('name') ?? $subject->getAttribute('key') ?? $subject->getAttribute('label');

        return is_scalar($name) ? "{$noun} « {$name} »" : "{$noun} #{$activity->subject_id}";
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'Oui' : 'Non',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
