# 0009 — Environnement local : Docker (Sail), MySQL 8.4, Redis, Mailpit

- **Statut** : accepté
- **Date** : 2026-10-29

## Décision

- Environnement local par **Laravel Sail** (PHP 8.4, MySQL 8.4 LTS, Redis, Mailpit). Aucun PHP, Node ni Composer requis sur la machine.
- Ports configurables (`FORWARD_*`, `APP_PORT`) pour cohabiter avec d'autres projets Docker.
- Sessions, cache et files d'attente sur **Redis** ; Horizon supervise les files.
- **Rendu côté serveur d'Inertia (SSR) désactivé** par défaut (`INERTIA_SSR_ENABLED=false`) : aucun serveur Node SSR n'est déployé, et l'option activée ralentit chaque rendu quand le serveur est absent. À réactiver pour un besoin réel (premier affichage en 3G).
- Sous Windows, travailler dans WSL 2 (système de fichiers Linux) : les volumes `/mnt/c` sont très lents.

## Point de vigilance

L'image MySQL est la 8.4 (LTS), la plus récente de la branche 8. Le client `mysqldump` du conteneur applicatif sert aux sauvegardes.
