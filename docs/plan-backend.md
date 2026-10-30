# Plan du backend (mis à jour après l'intégration des écrans de l'ancienne application)

Branche `feature/backend`. Règles inchangées : classes Action, Form Requests, Policies, tests Pest sur
chaque règle de gestion, commits conventionnels en français, push après chaque module, aucune attribution
à Claude. On ne s'arrête qu'à la fin d'un module complet.

## Fait

| Étape | Contenu | Décision |
| --- | --- | --- |
| Synchronisation | `feature/backend` à jour avec le front (écrans repris, menu par catégories, bandeau). Aucun conflit : `main` avait déjà fusionné `feature/backend`. | — |
| Connexion, pointage, droits | Arrivée par rôle, poste du compte, pointage enregistré, sélecteur de poste, Administrateur limité à l'Administration, écrans `/ecrans/*` protégés, pas d'auto-attribution, journal + alerte Direction générale. | ADR 0020 |
| V1 | Administration, Projets, Chantiers, Budget chantier (V1), Demandes d'achat, Référentiel des prix, Fournisseurs, Notifications, Tableau de bord. | ADR 0011 à 0019 |
| V2 (backend seul) | Cotations, bons de commande, réceptions, factures fournisseurs (contrôle à trois voies), évaluation des fournisseurs. Les pages ne sont pas encore reliées. | ADR 0019 |

## À faire, dans l'ordre

### 3. Circuit de validation paramétrable et pièce commune  ← reprise ici
- **Étapes de validation** (Administration) : table `validation_steps` (ordre, libellé, rôle habilité,
  libellé du bouton, actif), Policy `administration.*`, écran branché (aujourd'hui `Legacy/EtapesValidation`
  sur données fictives). Une étape = un rôle, jamais un compte nommé.
- **Moteur de circuit** : une classe Action `AdvanceValidation` appliquée à toutes les pièces
  (D.A., notes de frais, recettes, pièces de caisse) : refuse si le rôle n'est pas celui de l'étape
  suivante, si l'auteur valide sa propre pièce (séparation des tâches), ou si une étape est sautée.
  Historique `piece_workflow_events` (qui, quand, étape, commentaire) lu par « Suivi workflow ».
- **Pièce commune** : tables `pieces` + `piece_lines` (type, période d'imputation, n° de pièce unique par
  type et période, intitulé, imputation analytique Client/Projet → Produit/Lot/Section → Composante,
  lignes avec rubrique budgétaire). Panneau budget calculé côté serveur (montant budget, consommé + en
  cours, restant, taux) avec `BudgetLock`. Les D.A. existantes gardent leurs tables et branchent leur
  historique sur le même moteur.
- Tests : ordre des étapes, rôle refusé, auto-validation refusée, numéro unique, budget dépassé,
  historique complet.

### 4. Reprise du plan V1 → V4
1. **V1** : Notes de frais (module neuf sur la pièce commune : validation, facture de remboursement,
   suivi workflow) avec les D.A.
2. **V2** : relier les pages Cotations, Commandes, Réceptions, Factures, Fournisseurs (commandes,
   factures, évaluations) ; puis **Stocks** (M9).
3. **V3** : Clients, Devis, Marchés, Situations ; **Recettes** (factures clients, validation,
   recouvrement des créances) ; budget par exercice et par rubrique (élaboration, reconduction d'une
   année sur l'autre, valeurs réalisées, taux, écarts) ; choix du PDF.
4. **V4** : Main-d'œuvre, RH, Engins ; **Trésorerie** (caisse, bons à décaisser / encaisser, clôture,
   situation, engagements bancaires) ; **Comptabilité générale** avec exports recettes et charges vers
   SAARI.
5. Fin : retirer les données fictives (`resources/js/mocks`) et les routes temporaires
   (`/ecrans/*`, closures de `routes/web.php`), ouvrir la PR `feature/backend` → `develop`.

## Points à demander à Mega Challenge
- **Format des exports SAARI** : ne pas l'inventer. On génère un CSV paramétrable (colonnes, séparateur,
  comptes) et on consigne dans `docs/decisions/` qu'un fichier d'exemple de Mega Challenge est nécessaire.
- Écran d'administration des postes d'un utilisateur (le sélecteur lit déjà `user_postes`).
- Changement de mot de passe imposé à la première connexion (non fait : le mécanisme n'existe pas).
