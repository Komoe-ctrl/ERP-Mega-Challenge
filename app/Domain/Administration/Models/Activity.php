<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use LogicException;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Entrée du journal des activités. Le journal n'est pas modifiable (RG1.5) :
 * toute tentative de modification ou de suppression via Eloquent est refusée.
 * Déclaré dans config/activitylog.php.
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Le journal des activités n\'est pas modifiable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Le journal des activités n\'est pas modifiable.');
        });
    }
}
