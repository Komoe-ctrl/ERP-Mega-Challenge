# Architecture de l'ERP Mega Challenge

Ce document décrit comment le code est organisé, les conventions à respecter et le cheminement d'une requête. Les choix structurants sont justifiés dans [decisions/](decisions/).

## 1. Vue d'ensemble

Un **monolithe modulaire** : une seule application Laravel 13 qui sert les pages avec **Inertia.js** (Vue 3 + TypeScript). Pas d'API séparée à maintenir : le serveur renvoie des « props » à des pages Vue.

```
Navigateur ──► Laravel (routes, middleware) ──► Contrôleur fin ──► Action (logique métier)
     ▲                                                │                      │
     │                                         FormRequest + Policy      Modèles Eloquent ─► MySQL
     └──── page Vue (Inertia) ◄── props ◄─────────────┘
```

Services : **MySQL 8** (données), **Redis** (cache, sessions, files d'attente, supervisées par **Horizon**), **Mailpit** (e-mails en local).

## 2. Organisation du code

Le code est organisé **par domaine métier**, un domaine par module du cahier des charges (15 modules). Une fonctionnalité se trouve donc au même endroit, au lieu d'être dispersée par type de fichier.

### Backend

```
app/Domain/<Module>/
    Models/      modèles Eloquent du module
    Actions/     cas d'usage : une classe = une opération métier
    Policies/    droits côté serveur
    States/      cycles de vie (spatie/laravel-model-states)
    Data/        objets de transfert immuables (readonly)
app/Domain/Shared/
    Enums/       Module, PermissionAction, RoleName
    Policies/    ModulePolicy (base des policies)
    Support/     BudgetLock, BlueprintMacros, ModuleNavigation
app/Http/Controllers/<Module>/   contrôleurs fins
app/Http/Requests/<Module>/      validation (FormRequest)
app/Http/Middleware/             transversaux (compte actif, 2FA, props Inertia)
```

Modules : `Administration`, `Clients`, `Fournisseurs`, `Projets`, `Devis`, `Marches`, `Chantiers`, `Achats`, `Stocks`, `Budget`, `Situations`, `MainOeuvre`, `Rh`, `Engins`, `Finance`. Chaque dossier contient un `README.md` qui rappelle son périmètre, ses règles de gestion (RG) et ses critères d'acceptation (CA).

Exception : `App\Models\User` reste dans `app/Models`, car c'est le point d'ancrage de l'authentification (`config/auth.php`, Fortify). Voir [ADR 0002](decisions/0002-architecture-par-domaine.md).

### Frontend

```
resources/js/
    pages/<Module>/     une page Inertia = un fichier .vue (ex. pages/Administration/Users/Index.vue)
    components/         composants partagés ; components/ui = shadcn-vue (ne pas modifier à la main)
    composables/        logique Vue réutilisable
    layouts/            mises en page (menu latéral, authentification, paramètres)
    lib/                utilitaires (icônes de modules, tableaux)
    types/              types TypeScript (auth, navigation, pagination)
    __tests__/          tests Vitest
```

Les chemins des contrôleurs et des routes sont **générés** par Wayfinder (`resources/js/actions`, `resources/js/routes`, ignorés par Git). Ils sont typés : renommer une route casse la compilation TypeScript au lieu de casser silencieusement l'écran.

## 3. Règles d'écriture

1. **Contrôleurs fins.** Un contrôleur fait trois choses : valider (FormRequest), autoriser (Policy), appeler **une Action**, puis répondre. Aucune règle de gestion dans un contrôleur.
2. **La logique métier va dans des Actions** (`app/Domain/<Module>/Actions`) : classes `final`, une méthode `execute()`, entrées typées (objets `Data`), testables sans HTTP.
3. `declare(strict_types=1);` en tête de tout fichier PHP applicatif.
4. **Pas de suppression de comptes** ni de données historiques : on désactive. Les utilisateurs portent `is_active` et un soft delete technique.
5. Les textes affichés sont en **français**. Les identifiants du code (classes, colonnes) sont en anglais, sauf les termes métier sans équivalent clair (`Marches`, `Rh`).
6. Toute règle de gestion implémentée référence son numéro (`RG8.6`) en commentaire ou dans le nom du test.

### Conventions de données

| Sujet | Règle |
| --- | --- |
| Montants en FCFA | **BIGINT**, en entier, jamais de FLOAT. Colonne : `$table->fcfa('montant')` |
| Quantités | **DECIMAL(14,3)**. Colonne : `$table->quantity('quantite')` |
| Fuseau horaire | `Africa/Abidjan` (`APP_TIMEZONE`) |
| Langue | `fr` (traductions Laravel via laravel-lang) |
| Dates affichées | JJ/MM/AAAA |
| Base | MySQL 8, InnoDB, utf8mb4 |
| Listes | toujours paginées côté serveur (15 par page par défaut) |

### Budget : toujours sous verrou

Toute validation qui modifie un budget (engagement d'une demande d'achat, réception, révision) doit passer par `App\Domain\Shared\Support\BudgetLock` :

```php
return BudgetLock::run(
    Budget::query()->where('chantier_id', $chantier->id),
    function (Collection $budgets) use ($demande): Demande {
        // $budgets est verrouillé (SELECT ... FOR UPDATE) et relu à jour :
        // vérifier le reste à engager, puis écrire l'engagement.
    },
);
```

`BudgetLock` ouvre une transaction, verrouille les lignes **dans l'ordre de leur clé primaire** (pas d'interblocage) et donne au callback les lignes à jour. Si le callback lève une exception, tout est annulé. Sans cela, deux validations simultanées liraient le même « reste à engager » et dépasseraient le budget (RG8.6, RG10.1).

## 4. Flux d'une requête

Exemple : un administrateur crée un utilisateur (`POST /administration/users`).

1. **Routage** (`routes/administration.php`) : middleware `auth`, `verified`, puis **`can:administration.view`** → 403 si le droit manque (CA1).
2. **Middleware globaux web** : `EnsureAccountIsActive` (un compte désactivé est déconnecté), `RequireTwoFactorForPrivilegedRoles` (Direction, Finance, Administrateur sans 2FA sont renvoyés vers la page de sécurité), `HandleInertiaRequests` (partage utilisateur, rôles, permissions et menu).
3. **FormRequest** `SaveUserRequest` : `authorize()` interroge la **Policy** (`create`), `rules()` valide les champs.
4. **Contrôleur** `UserController::store` : appelle l'Action `CreateUser` avec un objet `UserData`.
5. **Action** `CreateUser` : transaction, création de l'utilisateur, attribution des rôles.
6. **Journal** : le trait `LogsActivity` du modèle enregistre l'auteur, la date et les valeurs avant/après (jamais le mot de passe).
7. **Réponse** : redirection avec une notification (toast) ; Inertia rend la page `Administration/Users/Index` avec ses props.

## 5. Gestion des droits

Les droits sont vérifiés **côté serveur** ; l'interface ne fait que masquer ce qui est interdit.

- **Permissions** : convention `<module>.<action>`, avec les actions `view`, `create`, `update`, `validate`, `delete`. 15 modules × 5 actions = 75 permissions, semées par `RolesAndPermissionsSeeder` à partir de l'enum `Module`.
- **Rôles** : les 10 profils du cahier des charges (enum `RoleName`), avec une matrice de droits initiale (`RoleName::matrix()`). La matrice n'est appliquée qu'à la **création** du rôle : un administrateur peut ensuite ajuster les droits depuis l'écran *Administration → Rôles* sans développement (RG1.2), et rejouer le seeder ne les écrase pas.
- **Policies** : une par modèle, qui héritent de `ModulePolicy` et déclarent leur module. Elles sont rattachées au modèle par l'attribut `#[UsePolicy]`. `ModulePolicy::allows()` exige aussi que le compte soit actif.
- **Séparation des tâches** : `ModulePolicy::canValidateOthers()` empêche l'auteur d'un document de le valider lui-même. Toute policy de validation (`validate`) doit l'utiliser.
- **Menu** : `ModuleNavigation` ne propose un module que s'il est activé (`ERP_MODULES_ENABLED`), qu'il a une page d'entrée et que l'utilisateur a `<module>.view`.
- **Double authentification** : obligatoire pour Administrateur, Direction générale, Direction technique et Finance (`RoleName::requiresTwoFactor()`), désactivable uniquement par `ERP_REQUIRE_TWO_FACTOR=false` en local.
- **Comptes** : jamais supprimés (la policy `UserPolicy::delete` renvoie toujours `false`) ; un administrateur ne peut pas désactiver son propre compte. Il n'y a pas d'inscription publique : les comptes sont créés par un administrateur.
- **Périmètre chantier** (à venir en V1) : « un utilisateur de terrain ne voit que ses chantiers » (RG1.1, RG7.2) se traduira par une portée (scope) appliquée dans les policies et les requêtes du module Chantiers.

## 6. Journal des activités

`spatie/laravel-activitylog` est branché sur `User`, `Role`, `Agency`, `Service`, `Site`, `Setting` et `FiscalYear` (trait `LogsActivity`). Chaque création, modification ou suppression enregistre l'auteur, la date et les valeurs avant/après (`attribute_changes`). Le modèle `Activity` du projet **refuse toute modification ou suppression** (RG1.5). Tout nouveau modèle métier doit utiliser le même trait et déclarer précisément les attributs à tracer (jamais les secrets).

## 7. Cycles de vie

Les cycles de vie (demande d'achat : *Brouillon → En attente → Validée / Refusée / Renvoyée*, devis, projet) utilisent `spatie/laravel-model-states` : un dossier `States/` par module, une classe par état, les transitions autorisées déclarées explicitement. Seul le passage à l'état *Validée* fige les prix et impute le budget (RG8.6), dans une Action appelant `BudgetLock`.

## 8. Fichiers, imports, sauvegardes

- **Pièces jointes** : `spatie/laravel-medialibrary`, 20 Mo maximum par fichier (`config/media-library.php`).
- **Import / export Excel** : `maatwebsite/excel` (chargement du référentiel de prix, export de toute liste).
- **Sauvegardes** : `spatie/laravel-backup` sauvegarde la base et `storage/app` chaque jour à 01h30, conserve 30 jours (`BACKUP_RETENTION_DAYS`) ; l'échec est notifié par e-mail. Nécessite le planificateur Laravel en production (`php artisan schedule:work` ou une tâche cron).
- **Files d'attente** : Redis, supervisées par Horizon (`/horizon`, réservé aux utilisateurs ayant `administration.update`).

## 9. Tests et qualité

| Outil | Rôle | Commande |
| --- | --- | --- |
| Pest | tests backend (droits, socle, conventions) sur MySQL réel | `sail composer test` |
| Vitest | tests frontend (composants, utilitaires) | `sail npm test` |
| Larastan (niveau 9) | analyse statique PHP | `sail composer types:check` |
| Laravel Pint | style PHP | `sail composer lint` |
| ESLint + Prettier | style et qualité JS/TS/Vue | `sail npm run lint`, `sail npm run format` |
| vue-tsc | vérification des types TypeScript | `sail npm run types:check` |

La CI ([.github/workflows/ci.yml](../.github/workflows/ci.yml)) exécute tout cela, plus le build Vite, sur chaque push vers `main` / `develop` et chaque pull request. **Toute fonctionnalité s'accompagne de tests sur les droits** : au minimum un test « autorisé » et un test « refusé (403) » par action sensible.

## 10. Ajouter un module (marche à suivre)

1. Lire son `README.md` (`app/Domain/<Module>/README.md`) et les RG du cahier des charges.
2. Migrations dans `database/migrations` (colonnes `fcfa()` / `quantity()` pour les montants et quantités).
3. Modèles dans `Domain/<Module>/Models` avec `LogsActivity` et `#[UsePolicy]`.
4. Policy héritant de `ModulePolicy`.
5. Actions (+ `Data`, + `States` si cycle de vie) ; tout ce qui touche un budget passe par `BudgetLock`.
6. FormRequests, contrôleurs fins, routes sous `can:<module>.view`.
7. Pages dans `resources/js/pages/<Module>/`.
8. Dans `Module::entryRoute()`, déclarer la route d'entrée ; ajouter le module à `ERP_MODULES_ENABLED`.
9. Tests Pest (droits inclus) et, si besoin, Vitest.
