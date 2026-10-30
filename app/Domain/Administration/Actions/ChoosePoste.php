<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\UserPoste;
use App\Domain\Shared\Enums\RoleName;
use App\Domain\Shared\Support\Present;
use App\Models\User;

/**
 * Postes d'un utilisateur et poste actif de la session.
 */
final class ChoosePoste
{
    public const SESSION_KEY = 'poste_id';

    /**
     * Postes proposés : ceux du compte, ou à défaut le poste unique du compte
     * (`job_title` + service).
     *
     * @return list<array{id: int|null, title: string, service: string}>
     */
    public function postesOf(User $user): array
    {
        $postes = UserPoste::query()->with('service')->where('user_id', $user->id)->orderByDesc('is_default')->orderBy('id')->get();

        if ($postes->isEmpty()) {
            return [[
                'id' => null,
                'title' => $user->job_title ?? $this->defaultTitle($user),
                'service' => Present::text($user, 'service.name', '—'),
            ]];
        }

        $list = [];

        foreach ($postes as $poste) {
            $list[] = [
                'id' => $poste->id,
                'title' => $poste->title,
                'service' => Present::text($poste, 'service.name', '—'),
            ];
        }

        return $list;
    }

    /**
     * Poste actif : celui choisi pour la session s'il appartient au compte,
     * sinon le poste par défaut (le premier).
     *
     * @return array{id: int|null, title: string, service: string}
     */
    public function active(User $user, ?int $chosenId): array
    {
        $postes = $this->postesOf($user);

        foreach ($postes as $poste) {
            if ($poste['id'] === $chosenId) {
                return $poste;
            }
        }

        return $postes[0];
    }

    /** Choisit le poste de la session ; faux si ce poste n'appartient pas au compte. */
    public function choose(User $user, int $posteId): bool
    {
        if (! UserPoste::query()->where('user_id', $user->id)->whereKey($posteId)->exists()) {
            return false;
        }

        session([self::SESSION_KEY => $posteId]);

        return true;
    }

    /** Poste par défaut du profil quand le compte n'a pas de titre saisi. */
    private function defaultTitle(User $user): string
    {
        $name = $user->getRoleNames()->first();

        return (is_string($name) ? RoleName::tryFrom($name)?->defaultJobTitle() : null) ?? 'Collaborateur';
    }
}
