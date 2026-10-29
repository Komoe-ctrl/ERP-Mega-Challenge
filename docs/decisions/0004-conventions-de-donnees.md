# 0004 — Conventions de données et verrou de budget

- **Statut** : accepté
- **Date** : 2026-10-29

## Décision

- **Montants en FCFA** : colonne `BIGINT` (entier). Jamais de `FLOAT` ni de `DECIMAL` pour de l'argent. Macro de migration `$table->fcfa('col')`.
- **Quantités** : `DECIMAL(14,3)`. Macro `$table->quantity('col')`.
- Fuseau `Africa/Abidjan`, locale `fr`, traductions via `laravel-lang/lang`. MySQL 8, InnoDB, utf8mb4.
- **Validation touchant un budget** : toujours dans `BudgetLock::run()` — transaction + `SELECT ... FOR UPDATE` sur les lignes de budget, triées par clé primaire pour éviter les interblocages.

## Pourquoi

Les arrondis flottants faussent les totaux (CA10 : « les totaux égalent la somme des opérations ») ; deux validations simultanées pourraient dépasser un budget (RG8.6, RG10.1).
