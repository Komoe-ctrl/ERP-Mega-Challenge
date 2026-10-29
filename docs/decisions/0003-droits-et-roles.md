# 0003 — Droits : permissions `<module>.<action>` et rôles = profils

- **Statut** : accepté
- **Date** : 2026-10-29

## Décision

- `spatie/laravel-permission`. Permissions nommées `<module>.<action>` avec les actions `view`, `create`, `update`, `validate`, `delete`, semées pour les 15 modules (75 permissions).
- Les **10 profils** du cahier des charges (section 4) sont des rôles, avec une matrice de droits initiale dans l'enum `RoleName`. Elle n'est appliquée qu'à la création du rôle : les ajustements faits par un administrateur (RG1.2) survivent à un nouveau `db:seed`.
- Vérification **côté serveur** : middleware `can:` sur les routes, Policies par modèle (héritant de `ModulePolicy`) et `authorize()` dans les FormRequests. Le menu n'est qu'un confort d'affichage (CA1).
- **Pas d'inscription publique**, **pas de suppression de compte** (désactivation), double authentification obligatoire pour Administrateur, Direction générale, Direction technique et Finance.
- Un utilisateur a une agence et un service (colonnes `agency_id` et `service_id`, RG1.1). Ses chantiers lui seront affectés explicitement dans le module Chantiers.

## Écart assumé

La matrice initiale est une **interprétation** du tableau de la section 4 (par exemple, le Conducteur de travaux peut valider les demandes d'achat de ses chefs de chantier). À valider avec le métier ; elle se corrige depuis l'écran des rôles, sans développement.
