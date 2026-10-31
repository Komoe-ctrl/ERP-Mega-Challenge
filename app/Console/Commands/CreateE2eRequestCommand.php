<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Achats\Models\Article;
use App\Domain\Achats\Models\PurchaseRequest;
use App\Domain\Chantiers\Models\Chantier;
use App\Domain\Fournisseurs\Models\Supplier;
use Illuminate\Console\Command;

/**
 * Prépare, pour le script e2e/achats.mjs, une demande d'achat validée sous le seuil de consultation
 * (donc commandable directement) et un fournisseur de la catégorie Matériaux. À chaque appel, une
 * nouvelle demande : le parcours peut être rejoué. Local uniquement (utilise les fabriques de données).
 */
final class CreateE2eRequestCommand extends Command
{
    protected $signature = 'erp:e2e-demande';

    protected $description = 'Crée une demande d\'achat validée pour le parcours e2e des achats (local uniquement)';

    public function handle(): int
    {
        if (! app()->isLocal() || ! class_exists(Chantier::factory()::class)) {
            $this->error('Commande réservée à l\'environnement local.');

            return self::FAILURE;
        }

        $chantier = Chantier::factory()->create();
        $request = PurchaseRequest::factory()->withStatus('validee')->create(['chantier_id' => $chantier->id]);

        $request->lines()->create([
            'article_id' => Article::factory()->create(['price' => 10_000, 'category' => 'Ciment'])->id,
            'quantity' => 50,
            'need_date' => now()->addDays(10)->toDateString(),
            'urgency' => 'normale',
            'frozen_price' => 10_000,
        ]);

        Supplier::factory()->inCategories('Matériaux')->create();

        $this->info("Demande {$request->number} prête (500 000 FCFA, chantier {$chantier->name}).");

        return self::SUCCESS;
    }
}
