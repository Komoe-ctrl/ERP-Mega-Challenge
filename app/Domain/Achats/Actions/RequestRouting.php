<?php

declare(strict_types=1);

namespace App\Domain\Achats\Actions;

use App\Domain\Administration\Models\Setting;

/**
 * RG8.7 : au-delà du budget restant ou du seuil paramétré, la demande requiert
 * la validation de la Direction. Le seuil vient des paramètres (RG1.3).
 *
 * Le seuil s'applique au plus grand des deux montants (demandé ou ajusté), afin
 * qu'un ajustement de quantités ne serve pas à contourner la Direction.
 */
final class RequestRouting
{
    public const DEFAULT_THRESHOLD = 5_000_000;

    public function threshold(): int
    {
        $value = Setting::get('validation.direction_threshold_fcfa', self::DEFAULT_THRESHOLD);

        return is_int($value) ? $value : self::DEFAULT_THRESHOLD;
    }

    public function needsDirection(int $requestedTotal, int $adjustedTotal, int $remaining): bool
    {
        return max($requestedTotal, $adjustedTotal) > $this->threshold() || $adjustedTotal > $remaining;
    }
}
