<?php

declare(strict_types=1);

namespace App\Domain\Administration\Support;

use App\Domain\Administration\Models\Role;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Notifications\AlertDispatcher;
use App\Domain\Shared\Notifications\AlertRecipients;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Toute modification d'un rôle, d'une permission ou de l'attribution d'un rôle est
 * journalisée (journal des activités) et notifiée à la Direction générale.
 */
final class AccessChangeAudit
{
    public function __construct(private readonly AlertDispatcher $alerts) {}

    /**
     * @param  array<int, string>  $before  noms de rôles avant
     * @param  array<int, string>  $after  noms de rôles après
     */
    public function userRolesChanged(User $actor, User $target, array $before, array $after): void
    {
        $this->record(
            $actor,
            $target,
            'Rôles de '.$target->name,
            $before,
            $after,
        );
    }

    /**
     * @param  array<int, string>  $before  permissions avant
     * @param  array<int, string>  $after  permissions après
     */
    public function rolePermissionsChanged(User $actor, Role $role, array $before, array $after): void
    {
        $this->record($actor, $role, 'Permissions du rôle « '.$role->name.' »', $before, $after);
    }

    /**
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    private function record(User $actor, Model $subject, string $what, array $before, array $after): void
    {
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        $detail = trim(
            ($added === [] ? '' : 'Ajouté : '.implode(', ', $added).'. ')
            .($removed === [] ? '' : 'Retiré : '.implode(', ', $removed).'.'),
        );

        activity('administration')
            ->performedOn($subject)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['motif' => $what.' — '.$detail])
            ->log($what.' modifiés');

        $this->alerts->send(
            AlertRecipients::withRoles(RoleName::DirectionGenerale),
            'securite',
            'Droits modifiés par '.$actor->name,
            $what.' : '.$detail,
            '/administration/journal',
        );
    }
}
