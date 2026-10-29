# 0005 — Journal des activités non modifiable

- **Statut** : accepté
- **Date** : 2026-10-29

## Décision

`spatie/laravel-activitylog` sur les modèles du socle (utilisateurs, rôles, agences, services, sites, paramètres, exercices). Le modèle `Activity` du projet lève une exception sur toute modification ou suppression (RG1.5). Les champs sensibles (mot de passe, secrets 2FA) ne sont jamais tracés : chaque modèle liste explicitement les attributs journalisés.

## Limites connues

- Le blocage s'applique via Eloquent ; un accès SQL direct reste possible. Pour une garantie forte en production, retirer les droits `UPDATE` et `DELETE` sur `activity_log` à l'utilisateur MySQL de l'application.
- La commande `activitylog:clean` (suppression après 365 jours par défaut) n'est **pas planifiée** : la durée de conservation du journal est à décider avec le métier.
