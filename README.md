# ERP Mega Challenge

ERP web de **Mega Challenge**, entreprise de BTP à Abidjan (Côte d'Ivoire) : suivi de chantier, achats, stocks, budget, puis commercial, ressources et finance.

Monolithe **Laravel 13 + Inertia + Vue 3** : un seul dépôt, un seul déploiement.

- Cahier des charges : [docs/cahier-des-charges.pdf](docs/cahier-des-charges.pdf) (15 modules, 4 versions, 10 profils)
- Architecture et conventions : [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Décisions structurantes : [docs/decisions/](docs/decisions/)

## État d'avancement

Ce dépôt contient l'**architecture** et le **socle** (module Administration). Les fonctionnalités métier de la V1 restent à développer : voir [docs/decisions/0010-reste-a-faire-v1.md](docs/decisions/0010-reste-a-faire-v1.md).

| Version | Modules | État |
| --- | --- | --- |
| V1 — Suivi de chantier | Administration, Projets, Chantiers, Achats (demandes, référentiel), Budget | Socle Administration fait ; le reste est à faire |
| V2 — Achats et stocks | Fournisseurs, processus achat, Stocks | Structure créée |
| V3 — Commercial et facturation | Clients, Devis, Marchés, Situations | Structure créée |
| V4 — Ressources et finance | Main-d'œuvre, RH, Engins, Finance | Structure créée |

## Versions retenues

Vérifiées le 29/10/2026 (Packagist, npm, documentation officielle).

| Composant | Version |
| --- | --- |
| PHP (image Sail) | 8.4 (Laravel 13 exige ≥ 8.3 ; activitylog 5, medialibrary et Pest 5 exigent ≥ 8.4) |
| Laravel | 13.39.0 |
| Starter kit | Vue officiel de Laravel (Inertia 3, Vue 3, TypeScript, Tailwind 4, shadcn-vue / Reka UI) |
| inertiajs/inertia-laravel · @inertiajs/vue3 | 3.5.2 · 3.9.1 |
| Laravel Fortify (2FA, mots de passe, vérification e-mail) | 1.41.0 |
| Laravel Horizon | 5.50.1 |
| Laravel Sail | 1.69.0 |
| Laravel Wayfinder | 0.1.23 |
| spatie/laravel-permission | 8.3.2 |
| spatie/laravel-activitylog | 5.1.3 |
| spatie/laravel-model-states | 2.14.2 |
| spatie/laravel-medialibrary | 11.23.11 |
| spatie/laravel-backup | 10.3.3 |
| maatwebsite/excel | 4.0.6 |
| laravel-lang/lang (traductions françaises) | 15.37.4 |
| MySQL (Sail) | 8.4 LTS (InnoDB, utf8mb4) |
| Redis (Sail) | image `redis:alpine` |
| Node.js | 24 (CI) — 22 minimum dans l'image Sail |
| Vue · TypeScript · Tailwind | 3.5.44 · 5.9.3 · 4.3.3 |
| @tanstack/vue-table | 9.3.1 |
| Chart.js · vue-chartjs | 4.6.1 · 5.3.4 |
| Pest · PHPUnit | 5.3.4 · 13.4.5 |
| Vitest · @vue/test-utils | 5.0.5 · 2.5.1 |
| Larastan · PHPStan | 3.13.2 · 2.3.4 (niveau 9) |
| Laravel Pint | 1.32.1 |
| ESLint · Prettier | 10.13.0 · 3.9.10 |

Choix de la bibliothèque de graphiques : [Chart.js](docs/decisions/0006-chartjs.md). PDF et Gantt : [décision reportée](docs/decisions/0007-pdf-et-gantt-reportes.md).

## Prérequis

Une machine **Windows 10/11, macOS ou Linux** avec :

1. **Git**
2. **Docker Desktop** (ou Docker Engine + Compose v2) démarré
3. Sous **Windows** : **WSL 2** avec une distribution Ubuntu, et l'intégration WSL activée dans Docker Desktop (*Settings → Resources → WSL integration*)

Vous n'avez **besoin ni de PHP, ni de Composer, ni de Node** sur la machine : tout tourne dans les conteneurs Sail.

> **Windows : travaillez dans WSL, pas dans `C:\`.** Clonez le dépôt dans le système de fichiers Linux (`~/projets/...`), jamais sous `/mnt/c/...`. Sur un dossier Windows monté, les conteneurs sont 10 à 50 fois plus lents (les tests passent de quelques secondes à plusieurs minutes). Toutes les commandes ci-dessous se lancent dans le terminal Ubuntu (WSL).

## Installation pas à pas (machine neuve)

```bash
# 1. Récupérer le code
git clone https://github.com/Komoe-ctrl/ERP-Mega-Challenge.git
cd ERP-Mega-Challenge
git checkout develop        # branche d'intégration (main = stable)

# 2. Créer le fichier d'environnement
cp .env.example .env
#    Ouvrir .env et CHANGER ADMIN_EMAIL / ADMIN_PASSWORD (compte administrateur de démo).
#    Si un port est déjà pris sur votre machine, modifier APP_PORT, FORWARD_DB_PORT,
#    FORWARD_REDIS_PORT, FORWARD_MAILPIT_PORT, FORWARD_MAILPIT_DASHBOARD_PORT.

# 3. Installer les dépendances PHP (premier démarrage, sans PHP local)
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# 4. Démarrer les conteneurs (la première fois, l'image est construite : quelques minutes)
./vendor/bin/sail up -d

# 5. Initialiser l'application
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# 6. Installer et compiler le frontend
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

L'application répond sur <http://localhost> (ou `http://localhost:<APP_PORT>`).

**Première connexion** : utilisez `ADMIN_EMAIL` / `ADMIN_PASSWORD` de votre `.env`. L'Administrateur doit activer la **double authentification** (page *Sécurité*) avant d'accéder aux autres écrans : c'est une exigence du cahier des charges pour la Direction, la Finance et l'Administrateur.

Les e-mails envoyés en local (réinitialisation de mot de passe, etc.) sont visibles dans **Mailpit** : <http://localhost:8025>.

Astuce : ajoutez un alias pour éviter de taper le chemin complet.

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

### Développement quotidien

```bash
sail up -d              # démarrer
sail npm run dev        # serveur Vite avec rechargement à chaud (http://localhost:5173)
sail down               # arrêter
```

## Commandes utiles

| Besoin | Commande |
| --- | --- |
| Tous les contrôles (comme la CI) | `sail composer ci:check` |
| Tests backend (Pest) | `sail composer test` ou `sail pest` |
| Un seul test | `sail pest --filter="nom du test"` |
| Tests frontend (Vitest) | `sail npm test` |
| Style PHP (Pint) — corriger / vérifier | `sail composer lint` / `sail composer lint:check` |
| Analyse statique (Larastan, niveau 9) | `sail composer types:check` |
| Lint JS (ESLint) | `sail npm run lint` |
| Format JS (Prettier) | `sail npm run format` / `sail npm run format:check` |
| Types TypeScript | `sail npm run types:check` |
| Build de production | `sail npm run build` |
| Régénérer les routes TypeScript (Wayfinder) | `sail artisan wayfinder:generate --with-form` |
| Repartir d'une base propre | `sail artisan migrate:fresh --seed` |
| Superviser les files d'attente | <http://localhost/horizon> (droits : voir `HorizonServiceProvider`) |
| Lancer un worker de files | `sail artisan horizon` |
| Sauvegarde manuelle (base + fichiers) | `sail artisan backup:run` |
| Console Laravel | `sail artisan tinker` |
| Shell dans le conteneur | `sail shell` |

Les tests utilisent la base `testing`, créée automatiquement par Sail au premier démarrage de MySQL. Si vous aviez démarré MySQL avant d'avoir cette base, créez-la : `sail exec mysql mysql -uroot -ppassword -e "CREATE DATABASE IF NOT EXISTS testing; GRANT ALL ON testing.* TO 'sail'@'%';"`.

## Arborescence principale

```
app/
  Domain/<Module>/          Models, Actions, Policies, States, Data (+ README par module)
  Domain/Shared/            Enums (modules, rôles, actions), BudgetLock, ModulePolicy, macros FCFA
  Http/Controllers/<Module>/  Contrôleurs fins
  Http/Requests/<Module>/     Validation
  Http/Middleware/            Compte actif, 2FA obligatoire, props Inertia
config/erp.php              Modules actifs, 2FA obligatoire, compte admin de démo
database/                   Migrations, factories, seeders
docs/                       ARCHITECTURE.md, decisions/ (ADR), cahier des charges
resources/js/
  pages/<Module>/           Pages Inertia / Vue
  components/               Composants partagés (DataTable, PaginationBar, charts/, ui/ = shadcn-vue)
  types/                    Types TypeScript
tests/                      Pest (Feature, Unit) ; tests Vitest dans resources/js/__tests__
```

## Dépannage

- **`Vite manifest not found`** : lancez `sail npm run build` (ou `sail npm run dev`).
- **Un port est déjà utilisé** (`bind: address already in use`) : changez la variable `FORWARD_*` ou `APP_PORT` correspondante dans `.env`, puis `sail up -d`.
- **Les routes TypeScript (`@/actions/...`, `@/routes/...`) sont introuvables** : `sail artisan wayfinder:generate --with-form`.
- **Les tests sont très lents sous Windows** : le dépôt est sous `/mnt/c`. Reclonez-le dans le système de fichiers WSL.
- **`ADMIN_EMAIL` et `ADMIN_PASSWORD` manquants** au `db:seed` : renseignez-les dans `.env`.

## Git

Branches : `main` (stable) et `develop` (intégration). Commits conventionnels en français (`feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`).
La CI GitHub Actions (`.github/workflows/ci.yml`) exécute : installation, Pint, ESLint, Prettier, Larastan, vue-tsc, Vitest, Pest (MySQL 8.4) et le build Vite.
