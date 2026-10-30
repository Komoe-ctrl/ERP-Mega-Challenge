<?php

declare(strict_types=1);

namespace App\Domain\Fournisseurs\Actions;

use App\Domain\Achats\Models\Reception;
use App\Domain\Fournisseurs\Models\Supplier;
use App\Domain\Fournisseurs\Models\SupplierEvaluation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * RG3.3 : l'évaluation porte sur les délais de livraison, la qualité et le
 * respect des prix ; elle est notée après chaque réception, une seule fois.
 */
final class EvaluateSupplier
{
    public function execute(Supplier $supplier, int $receptionId, int $delay, int $quality, int $price, ?string $comment, User $by): SupplierEvaluation
    {
        $reception = Reception::query()->with('order')->find($receptionId);

        if ($reception === null || $reception->order()->value('supplier_id') !== $supplier->id) {
            throw ValidationException::withMessages(['reception_id' => 'Cette réception ne concerne pas ce fournisseur.']);
        }

        if (SupplierEvaluation::query()->where('reception_id', $reception->id)->exists()) {
            throw ValidationException::withMessages(['reception_id' => 'Cette réception a déjà été évaluée.']);
        }

        return SupplierEvaluation::query()->create([
            'supplier_id' => $supplier->id,
            'reception_id' => $reception->id,
            'delay_score' => $delay,
            'quality_score' => $quality,
            'price_score' => $price,
            'comment' => $comment,
            'user_id' => $by->id,
        ]);
    }

    /** Note moyenne du fournisseur sur 5 (moyenne des évaluations), ou 0 s'il n'est pas évalué. */
    public static function average(Supplier $supplier): float
    {
        $evaluations = SupplierEvaluation::query()->where('supplier_id', $supplier->id)->get();

        if ($evaluations->isEmpty()) {
            return 0.0;
        }

        return round($evaluations->sum(fn (SupplierEvaluation $e): float => $e->average()) / $evaluations->count(), 1);
    }
}
