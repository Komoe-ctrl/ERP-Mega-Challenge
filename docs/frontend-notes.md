# Notes du frontend (branche `feature/frontend`)

Mode express : un maximum d'écrans navigables et présentables, sur données fictives, sans logique métier backend.
Ce fichier liste les choix faits sans poser de question (option la plus simple retenue), l'état de chaque écran et ce qu'il reste à brancher.

## Charte graphique

- **Source des couleurs et polices** : dépôt du site `Komoe-ctrl/mega-challenge`, fichier `src/app/(frontend)/globals.css` (bloc `@theme`).
- Couleurs reprises : encre `#1b1e21`, papier `#f7f5f2`, béton `#ece8e2`, ligne `#d6d0c7`, orange d'action `#ff6600`, orange foncé `#b93b0b`, bleu royal `#003366`.
- Polices : **Archivo** (texte) et **Space Grotesk** (titres et chiffres), chargées par le plugin de polices de Laravel Vite (Bunny Fonts).
- Logo : `public/brand/logo-mega-challenge-blanc.svg` (barre latérale sombre) et `logo-mega-challenge.svg` (documents imprimables), copiés du dépôt du site.
- Tout est déclaré en **tokens** dans `resources/css/app.css` (`:root` et `.dark`), jamais en dur dans les composants :
  - tokens shadcn-vue (`--primary` = bleu royal, `--background` = papier, sidebar = encre…) ;
  - tokens ajoutés : `--brand` (orange), `--success`, `--warning`, `--danger`, `--info` et leurs variantes `-soft`, exposés comme classes Tailwind (`bg-brand`, `text-success`, `bg-warning-soft`…).
- Exceptions assumées : l'aperçu imprimable du devis utilise le bleu et le noir de la charte en valeurs fixes (`#003366`, `#1b1e21`) pour rester identique à l'impression, quel que soit le thème clair ou sombre.

## Données, routes et calculs

- Données fictives typées dans `resources/js/mocks/` (Abidjan, FCFA), types dans `resources/js/types/` (`erp.ts`, `forms.ts`, `pagination.ts`).
- Date « du jour » des données : **29/10/2026** (`TODAY` dans `lib/format.ts`), pour que retards et alertes restent cohérents.
- Routes Inertia **temporaires** (closures groupées et commentées dans `routes/web.php`). Les droits restent vérifiés côté serveur par `can:<module>.view`.
- Les actions (valider, enregistrer…) ne persistent rien : elles font évoluer l'écran en local (listes, statuts, totaux recalculés) et affichent une notification « maquette ». Seule la **matrice rôles × permissions** lit la vraie base.
- Calculs centralisés et testés : `lib/planning.ts` (RG7.5 à RG7.8), `lib/billing.ts` (devis, marchés, situations), `mocks/achats.ts` (budget : engagé, réel, reste à engager, seuils 80 % / 100 %).
- **Formule du net à payer** (cahier M11) : `(cumul n − cumul n−1) − retenue de garantie − remboursement d'acompte + taxes`. Choix : la TVA s'applique au **net hors taxes** ; le cahier ne le précise pas.

## Menu et profils

- Le menu latéral vient du serveur (`ModuleNavigation`) : un module apparaît s'il est activé, si sa route existe et si l'utilisateur a `<module>.view` (matrice de la section 4).
- `ERP_MODULES_ENABLED` vaut les 15 modules par défaut ; `phpunit.xml` le limite à `administration` pour les tests existants.
- **Sélecteur de profil de démo** (pied de la barre latérale) : visible uniquement si `APP_ENV=local`. Il change le rôle de l'utilisateur connecté (`POST /demo/role`, 404 hors local). Pour parcourir les 10 profils sans configurer la 2FA, mettre `ERP_REQUIRE_TWO_FACTOR=false` dans le `.env` local.
- Les boutons d'action se masquent selon les permissions (`usePermissions().can`) ; la séparation des tâches est rejouée à l'écran (l'auteur d'une demande ne voit pas les boutons de décision).

## Composants partagés

`resources/js/components/erp/` : `StatusBadge`, `KpiCard`, `EmptyState`, `ProgressBar` (repère « théorique »), `ConfirmDialog` (motif obligatoire), `PageHeader`, `TabsNav`, `SubNav`, `StatusTimeline`, `GanttChart` (maison, CSS), `AdminNav`, `RoleSwitcher`, **`SchemaForm`** et **`FormDialog`** (formulaires décrits par un schéma de champs, validation en français).
`components/DataTable.vue` : TanStack Table v9 avec tri, filtres déroulants, recherche globale et pagination côté navigateur ; `paginate=false` pour une liste déjà paginée par le serveur.
Libellés et tons des statuts : `lib/status.ts`. Montants, dates : `lib/format.ts`. Validation : `lib/validation.ts`.

## État des écrans

Légende : **Terminé** = liste (tri, recherche, filtres, pagination, état vide) + fiche + formulaire validé + calculs + droits. **Partiel** = voir la raison.

| Module | Écrans | État |
| --- | --- | --- |
| Tableau de bord | `/dashboard` (vues terrain, achats, direction) · `/notifications` | Terminé |
| M1 Administration | utilisateurs, rôles (formulaires serveur), matrice des droits, organisation (agences, services, sites), paramètres + exercices, journal | Terminé |
| M4 Projets | liste, fiche (frise, passage de statut RG4.2, abandon avec motif, documents), formulaire | Terminé |
| M7 Chantiers | liste, fiche à 7 onglets (Gantt, planning, avancement, demandes et commandes, budget, photos, rapports), formulaire, saisie d'avancement mobile, rapport journalier mobile | Terminé |
| M8 Achats V1 | demandes (liste, création mobile avec budget en direct, correction, détail, décision motivée, documents liés), à valider, référentiel des prix (liste, formulaire, historique, import) | Terminé |
| M10 Budget | liste, détail par poste (seuils), révisions motivées, dépenses diverses avec justificatif | Terminé |
| M3 Fournisseurs | liste, fiche, création, évaluation après réception | Terminé |
| M8 Achats V2 | consultations (+ offres, comparatif), bons de commande, réceptions, factures (contrôle à trois voies) | Terminé |
| M9 Stocks | état valorisé, mouvements (création), inventaire | Partiel : pas d'écran de réglage des seuils minimaux par article ni de fiche article de stock |
| M2 Clients | liste, création (doublons), fiche (contacts, projets, marchés, situations, paiements, documents) | Terminé |
| M5 Devis | liste, création, éditeur d'ouvrages, bibliothèque d'ouvrages (prix calculé), aperçu imprimable | Partiel : la création ouvre le devis n°3 de démonstration au lieu d'un nouveau devis ; pas de gestion des versions (la duplication est seulement annoncée) |
| M6 Marchés | liste, création depuis un devis validé, fiche, avenants (validation recalcule montant et délai) | Terminé |
| M11 Situations | liste, création, détail par ouvrage, net à payer (exemple CA11 vérifié) | Terminé |
| M12 Main-d'œuvre | pointage mobile du jour, coût de la semaine | Partiel : le blocage « pas deux chantiers aux mêmes heures » (RG12.3) n'est pas simulé |
| M13 RH | employés (création, fiche, historique), bulletin de paie, congés (chevauchement), avances | Terminé |
| M14 Engins | liste, création, fiche (affectation, utilisation, maintenance, réparation) | Terminé |
| M15 Finance | tableau de bord, caisse (solde jamais négatif), banque (rapprochement), rentabilité par chantier, trésorerie | Partiel : pas de lettrage des encaissements sur les situations ni de plan comptable SYSCOHADA |
| Transverse | notifications (lecture, canaux) | Partiel : pas d'envoi d'e-mail ; paramètres de canaux non persistés |

## Choix simples retenus

- Formulaires décrits par un schéma (`SchemaForm`) plutôt que des formulaires Inertia : aucun envoi serveur ; validation côté client uniquement (messages en français sous chaque champ).
- Création rapide en fenêtre (`FormDialog`) depuis les listes ; les enregistrements créés s'ajoutent à la liste pendant la session seulement.
- Photos et pièces jointes : champ fichier sans envoi (20 Mo maximum contrôlé pour les documents de projet).
- Gantt en CSS, sans bibliothèque (ADR 0007) ; défilement horizontal sur mobile.
- Impression : `window.print()` avec la zone `.print-area` (devis, situations, bulletin) ; pas de génération PDF (décision reportée).

## Points à reprendre au moment du backend

- Remplacer chaque closure de `routes/web.php` par un contrôleur fin + Action, et chaque donnée de `resources/js/mocks` par des props Inertia (les types de `types/erp.ts` sont déjà alignés sur les futurs modèles).
- Rejouer côté serveur **toutes** les validations du client (`lib/validation.ts` et règles dans les pages) : le client n'est qu'un confort.
- Séparation des tâches, seuils de validation et verrous de budget : à implémenter dans les Actions (`BudgetLock`) ; l'écran ne fait que les afficher.
- Les quantités de stock acceptent les décimales (m³, kg) : colonnes `DECIMAL`, montants en `BIGINT`.
- L'**aperçu imprimable** devra être remplacé par une vraie génération PDF une fois la bibliothèque choisie.
- Numérotation des documents (devis, bons de commande, situations) : générée à l'écran avec un compteur local ; à confier à un service serveur transactionnel.
- Internationalisation : les écrans d'authentification et de paramètres du starter kit sont encore en anglais.
- Mode hors ligne (PWA) pour le pointage et l'avancement : à étudier.

## Limites connues

- Aucune persistance : tout est maquette.
- Les listes créées localement disparaissent au rechargement de la page.
- Aucun test ajouté sur les écrans eux-mêmes (hors composants partagés et fonctions de calcul) ; le parcours de contrôle a été fait avec un navigateur piloté (voir le rapport de la PR).
