# Écrans de l'application actuelle — correspondance

Branche `feature/ecrans-existants` (front seul, données fictives dans `resources/js/mocks/legacy.ts`,
routes temporaires `/ecrans/{slug}` dans `routes/web.php`, aucun test ajouté).

## Principes

- Libellés, rubriques et circuit d'origine ; composants et couleurs de l'ERP actuel.
- Aucun module existant supprimé ; les écrans existants reçoivent des liens (sous-navigation Achats et Finance).
- Un titre (« Technicien 1 », « Directeur Mega Challenge »…) est un **poste** affiché ; chaque compte reste nominatif.
- Filtrage par rôle : chaque entrée de menu exige une permission existante (`achats.view`, `achats.validate`, `finance.view`, `budget.view`, `projets.view`, `administration.view`).

## Correspondance

| Écran d'origine | Statut | Où |
|---|---|---|
| Identification | Remplacé : connexion simple (identifiant, mot de passe, mot de passe oublié) ; service, titre et droits viennent du compte ; sélecteur de poste dans l'en-tête ; « Pointer mon arrivée » sur le tableau de bord | `Login.vue`, `useLegacySession.ts`, `LegacyHeader.vue`, `Dashboard.vue` |
| En-tête permanent (titre, service, exercice modifiable, logo) | Créé | `LegacyHeader.vue`, dans `AppSidebarLayout` |
| Menu Tableau de bord / mensuel / Situation / TdB projets | Réutilisé + créé | Tableau de bord existant ; 3 écrans `Tableaux.vue` |
| Menu Fichier (23 entrées + Quitter) | Créé | `LegacyHeader.vue`, `lib/legacyMenu.ts` |
| Accordéon des 5 rubriques | Créé | `LegacyAccordion.vue`, sous les modules |
| Composant « pièce » commun | Créé | `PieceEntry.vue` |
| Demande d'achat, Validation | Créé (enrichit Achats) | `PieceEntry` kind `da` ; sous-nav Achats |
| Élaborer / valider une cotation, Bon de commande, Convertir une D.A. | Créé (parallèle aux Consultations/Commandes existantes) | `lib/legacyScreens.ts` |
| Approbation des comptes des pièces engagées | Créé | idem |
| Bon de livraison, Facture | Créé (parallèle aux Réceptions/Factures existantes) | idem |
| Suivi « workflow » (D.A., notes de frais) | Créé | `SuiviWorkflow.vue` |
| Notes de frais, Validation, Facture | Créé | `PieceEntry` kind `nf` |
| Recettes, Validation, Recouvrement | Créé | `PieceEntry` kind `recette` + table |
| Trésorerie (6 écrans) | Créé (complète Finance existant) | `PieceEntry` caisse + tables |
| Comptabilité générale, exports SAARI (aperçu), justifier une pièce de caisse | Créé | tables |
| Étapes de validation (paramétrage) | Créé | `EtapesValidation.vue` |
| Exercice, élaboration, reconduction du budget | Créé (complète Budget existant) | `Budget.vue` |
| Valeurs réalisées, taux, ratio écart, déboursé sec, listes, livres analytiques | Créé | tables |
| Paramètres, importation, données importées, exportation | Créé (maquette) | tables |

## Non fait / limites

- Aucune persistance : tout est en mémoire ; le pointage et le poste sont gardés dans le navigateur.
- Le mot de passe de l'identification n'est pas vérifié (la vraie authentification reste la connexion).
- Les exports SAARI sont des aperçus ; pas de génération de fichier.
- Les cotations/bons/factures « poste de travail » ne sont pas encore fusionnés avec les écrans V2 existants : à unifier au branchement du backend.
- Pas de tests (consigne du mode express).
