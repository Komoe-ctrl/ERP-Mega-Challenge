<?php

declare(strict_types=1);

namespace App\Domain\Devis\Actions;

use App\Domain\Devis\Models\Quote;
use App\Domain\Devis\Models\QuoteWork;
use App\Domain\Devis\States\Refuse;
use App\Domain\Devis\States\Soumis;
use App\Domain\Devis\States\Valide;
use App\Domain\Projets\States\Devis;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soumission, validation et refus d'un devis (M5).
 *
 * La validation et le refus sont réservés à une autre personne que l'auteur. Un devis validé fait
 * passer son projet de « Étude » à « Devis ».
 */
final class DecideQuote
{
    public function submit(Quote $quote): Quote
    {
        $quote->loadMissing('works');

        if ($quote->works->isEmpty()) {
            throw ValidationException::withMessages(['works' => 'Ajoutez au moins un ouvrage avant de soumettre le devis.']);
        }

        $invalid = $quote->works->filter(fn (QuoteWork $w): bool => mb_strlen(trim($w->label)) < 3 || (float) $w->quantity <= 0 || $w->unit_price < 0);

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages(['works' => 'Corrigez les lignes invalides (libellé, quantité positive, prix) avant de soumettre le devis.']);
        }

        $quote->status->transitionTo(Soumis::class);

        return $quote;
    }

    public function validate(Quote $quote, User $by): Quote
    {
        $this->assertNotAuthor($quote, $by);

        return DB::transaction(function () use ($quote, $by): Quote {
            $quote->status->transitionTo(Valide::class);
            $quote->forceFill(['decided_by' => $by->id, 'decided_at' => now()])->save();

            $project = $quote->project;

            if ($project !== null && $project->status->getValue() === 'etude') {
                $project->status->transitionTo(Devis::class);
            }

            return $quote;
        });
    }

    public function refuse(Quote $quote, User $by, string $reason): Quote
    {
        $this->assertNotAuthor($quote, $by);

        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif du refus.']);
        }

        return DB::transaction(function () use ($quote, $by, $reason): Quote {
            $quote->status->transitionTo(Refuse::class);
            $quote->forceFill(['decided_by' => $by->id, 'decided_at' => now(), 'refusal_reason' => trim($reason)])->save();

            return $quote;
        });
    }

    /**
     * RG5.3 : duplique un devis refusé en nouvelle version (brouillon) ; la version refusée est conservée.
     */
    public function newVersion(Quote $quote, User $by): Quote
    {
        if ($quote->status->getValue() !== 'refuse') {
            throw ValidationException::withMessages(['status' => 'Seul un devis refusé se duplique en nouvelle version.']);
        }

        return DB::transaction(function () use ($quote, $by): Quote {
            $max = Quote::query()->where('number', $quote->number)->lockForUpdate()->max('version');
            $latest = is_numeric($max) ? (int) $max : 0;

            if ($latest !== $quote->version) {
                throw ValidationException::withMessages(['status' => 'Une version plus récente de ce devis existe déjà.']);
            }

            $copy = Quote::query()->create([
                'number' => $quote->number,
                'version' => $latest + 1,
                'client_id' => $quote->client_id,
                'project_id' => $quote->project_id,
                'quote_date' => now()->toDateString(),
                'discount_rate' => $quote->discount_rate,
                'vat_rate' => $quote->vat_rate,
                'direct_cost' => $quote->direct_cost,
                'created_by' => $by->id,
            ]);

            foreach ($quote->works as $work) {
                $copy->works()->create($work->only(['ouvrage_id', 'position', 'label', 'unit', 'quantity', 'unit_price', 'components']));
            }

            return $copy;
        });
    }

    private function assertNotAuthor(Quote $quote, User $by): void
    {
        if ($quote->created_by === $by->id) {
            throw ValidationException::withMessages(['status' => 'L\'auteur du devis ne peut pas le valider ni le refuser.']);
        }
    }
}
