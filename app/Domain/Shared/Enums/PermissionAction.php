<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Actions de la convention de permissions `<module>.<action>`.
 */
enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Validate = 'validate';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Consulter',
            self::Create => 'Créer',
            self::Update => 'Modifier',
            self::Validate => 'Valider',
            self::Delete => 'Supprimer',
        };
    }
}
