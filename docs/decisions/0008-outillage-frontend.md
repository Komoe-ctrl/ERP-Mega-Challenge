# 0008 — Outillage frontend : Vite+ du starter kit, ESLint + Prettier

- **Statut** : accepté
- **Date** : 2026-10-29

## Contexte

Le starter kit Vue de Laravel 13 est livré avec **Vite+** (`vp build`, `vp dev`) et ses outils intégrés (`vp check` pour le lint et le format). La consigne du projet impose **ESLint + Prettier** et **Vitest**.

## Décision

- On garde **Vite+ pour le build et le serveur de développement** : c'est le socle du starter kit officiel.
- On ajoute **ESLint 10 + Prettier 3** (scripts `npm run lint` et `npm run format:check`) et on retire les scripts `check` et `check:fix` du kit. Les règles de style purement cosmétiques d'`eslint-plugin-vue` sont désactivées (Prettier s'en charge).
- **Vitest** a sa propre configuration (`vitest.config.ts`) : les tests n'ont besoin ni de Laravel, ni de Wayfinder.
- Les composants `components/ui/` (shadcn-vue) sont exclus du lint et du format : ce sont des sources de bibliothèque.
- `@tanstack/vue-table` est en version **9** (API `useTable` + `tableFeatures`, différente de la v8).

## À surveiller

Vite+ est récent : suivre ses mises à jour dans `package-lock.json`.
