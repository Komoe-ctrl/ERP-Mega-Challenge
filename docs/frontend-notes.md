# Notes du frontend (branche `feature/frontend`)

Mission express : un maximum d'écrans navigables et présentables, sur données fictives.
Ce fichier liste les choix faits sans poser de question (option la plus simple retenue).

## Charte graphique

- **Source des couleurs et polices** : dépôt du site `Komoe-ctrl/mega-challenge`, fichier `src/app/(frontend)/globals.css` (bloc `@theme`).
- Couleurs reprises : encre `#1b1e21`, papier `#f7f5f2`, béton `#ece8e2`, ligne `#d6d0c7`, orange d'action `#ff6600`, orange foncé `#b93b0b`, bleu royal `#003366`.
- Polices : **Archivo** (texte) et **Space Grotesk** (titres et chiffres), chargées par le plugin de polices de Laravel Vite (Bunny Fonts).
- Logo : `public/brand/logo-mega-challenge-blanc.svg`, copié du dépôt du site (la barre latérale est sombre).
- Tout est déclaré en **tokens** dans `resources/css/app.css` (`:root` et `.dark`), jamais en dur dans les composants :
  - tokens shadcn-vue (`--primary` = bleu royal, `--background` = papier, sidebar = encre…) ;
  - tokens ajoutés : `--brand` (orange), `--success`, `--warning`, `--danger`, `--info` et leurs variantes `-soft`, exposés comme classes Tailwind (`bg-brand`, `text-success`, `bg-warning-soft`…).
- Le bleu royal est la couleur primaire (boutons, liens) ; l'orange sert aux actions principales sur mobile, à la frise des statuts et à l'onglet actif.

## Données et routes

- Données fictives typées dans `resources/js/mocks/` (Abidjan, FCFA), types dans `resources/js/types/erp.ts`.
- Date « du jour » des données : **29/10/2026** (`TODAY` dans `lib/format.ts`), pour que retards et alertes restent cohérents.
- Routes Inertia **temporaires** (closures groupées et commentées dans `routes/web.php`). Les droits sont vérifiés côté serveur par `can:<module>.view`.
- Les actions (valider, enregistrer…) ne persistent rien : elles font évoluer l'écran en local et affichent une notification « maquette ».
- Seule exception : la **matrice rôles × permissions** lit la vraie base.

## Menu et profils

- Le menu latéral vient du serveur (`ModuleNavigation`) : un module apparaît s'il est activé, si sa route existe et si l'utilisateur a `<module>.view`.
- `ERP_MODULES_ENABLED` vaut maintenant les 15 modules par défaut ; `phpunit.xml` le limite à `administration` pour les tests existants.
- **Sélecteur de profil de démo** (pied de la barre latérale) : visible uniquement si `APP_ENV=local`. Il change le rôle de l'utilisateur connecté (`POST /demo/role`, 404 hors local). Pour parcourir les 10 profils sans configurer la 2FA, mettre `ERP_REQUIRE_TWO_FACTOR=false` dans le `.env` local.

## Composants partagés

`resources/js/components/erp/` : `StatusBadge`, `KpiCard`, `EmptyState`, `ProgressBar` (avec repère « théorique »), `ConfirmDialog` (motif obligatoire), `PageHeader`, `TabsNav`, `StatusTimeline` (frise), `GanttChart` (maison, CSS), `AdminNav`, `RoleSwitcher`.
`components/DataTable.vue` : TanStack Table v9 avec tri, filtres déroulants, recherche globale et pagination côté navigateur ; `paginate=false` pour une liste déjà paginée par le serveur.
Libellés et tons des statuts : `lib/status.ts`. Montants, dates : `lib/format.ts`. Règles de planning RG7.5 à RG7.7 : `lib/planning.ts`.

## Choix simples retenus

- Formulaires en `v-model` local (pas de Form Inertia) : aucun envoi serveur.
- Photos : zone d'ajout (`capture`) sans envoi ; les vignettes sont des emplacements.
- Gantt en CSS, sans bibliothèque (ADR 0007) ; défilement horizontal sur mobile.
- Séparation des tâches rejouée côté écran : l'auteur d'une demande ne voit pas les boutons de décision (comparaison du nom).
- Les écrans non listés ci-dessus ne sont pas commencés : voir le rapport final.
