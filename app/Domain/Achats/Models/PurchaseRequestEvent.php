<?php

declare(strict_types=1);

namespace App\Domain\Achats\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'historique d'une demande (création, soumission, décision motivée).
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $reason
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['purchase_request_id', 'user_id', 'action', 'reason'])]
class PurchaseRequestEvent extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
