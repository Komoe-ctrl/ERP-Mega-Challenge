<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écrans repris de l'ancienne application, encore sur données fictives (`/ecrans/{slug}`).
 * Chacun est protégé côté serveur par la permission du module qu'il représente : sans elle,
 * 403, quel que soit ce que l'interface affiche. Ces routes disparaissent, module par module,
 * à mesure que de vrais contrôleurs les remplacent.
 */
final class LegacyScreenController extends Controller
{
    /** Permission requise par écran. */
    private const PERMISSIONS = [
        // Notes de frais (module Achats)
        'note-de-frais' => 'achats.view',
        'validation-nf' => 'achats.validate',
        'facture-nf' => 'achats.view',
        'suivi-workflow-nf' => 'achats.view',
        // Demandes d'achats
        'suivi-workflow-da' => 'achats.view',
        'approbation-pieces-engagees' => 'achats.validate',
        // Projets et chantiers
        'planning' => 'chantiers.view',
        // Recettes
        'recette' => 'finance.view',
        'validation-recette' => 'finance.validate',
        'recouvrement' => 'finance.view',
        // Trésorerie
        'entree-de-caisse' => 'finance.view',
        'bons-caisse' => 'finance.view',
        'creances-fournisseurs' => 'finance.view',
        'cloture-de-caisse' => 'finance.view',
        'situation-caisse' => 'finance.view',
        'engagements-bancaires' => 'finance.view',
        // Comptabilité générale
        'comptabilite-generale' => 'finance.view',
        'export-recettes-saari' => 'finance.view',
        'export-charges-saari' => 'finance.view',
        'justifier-piece-caisse' => 'finance.view',
        // Menu Fichier : budget
        'exercice-budgetaire' => 'budget.view',
        'elaboration-budgetaire' => 'budget.view',
        'reconduire-budget' => 'budget.view',
        'realise-comme-budget' => 'budget.view',
        'valeurs-realisees' => 'budget.view',
        'valeurs-realisees-detaillees' => 'budget.view',
        'taux-realise-detaille' => 'budget.view',
        'ratio-ecart' => 'budget.view',
        // Menu Fichier : consultations
        'debourse-par-cout' => 'budget.view',
        'debourse-par-quantite' => 'budget.view',
        'notes-par-projet' => 'budget.view',
        'commandes-par-fournisseur' => 'achats.view',
        'situation-clients-projets' => 'finance.view',
        // Menu Fichier : imports / exports (Administration)
        'parametres-import' => 'administration.view',
        'importation' => 'administration.view',
        'donnees-importees' => 'administration.view',
        'valeurs-importees' => 'administration.view',
        'ratio-importees' => 'administration.view',
        'exportation' => 'administration.view',
        // Menu Fichier : analytique
        'livre-analytique' => 'finance.view',
        'livre-analytique-produits' => 'finance.view',
        // Menu du haut
        'tableau-de-bord-mensuel' => 'budget.view',
        'situation-des-projets' => 'projets.view',
        'tableau-de-bord-des-projets' => 'projets.view',
    ];

    public function __invoke(Request $request, string $slug): Response
    {
        $permission = self::PERMISSIONS[$slug] ?? null;

        abort_if($permission === null, 404);

        $user = $request->user();
        assert($user instanceof User);
        abort_unless($user->can($permission), 403);

        return Inertia::render('Legacy/Ecran', ['slug' => $slug]);
    }

    /**
     * @return array<string, string>
     */
    public static function permissions(): array
    {
        return self::PERMISSIONS;
    }
}
