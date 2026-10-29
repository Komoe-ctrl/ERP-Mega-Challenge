# 0001 — Monolithe Laravel + Inertia + Vue, un seul dépôt

- **Statut** : accepté
- **Date** : 2026-10-29

## Contexte

L'ERP couvre 15 modules, une équipe réduite et un usage terrain sur smartphone (3G). Le cahier des charges demande une application modulaire dont le modèle de données couvre tous les modules dès la V1.

## Décision

Une seule application **Laravel 13** qui sert des pages **Inertia.js / Vue 3 / TypeScript** (starter kit Vue officiel), dans **un seul dépôt**. Les modules sont des dossiers (`app/Domain/<Module>`), pas des services.

## Conséquences

- Un déploiement, une CI, une base de données, des transactions simples (indispensable pour les verrous de budget).
- Pas d'API REST séparée à versionner : le contrat serveur/client est typé par Wayfinder et les props Inertia.
- Un second dépôt (frontend séparé, application mobile, service de rapports) n'est **pas nécessaire**. Il ne se justifierait que si une application mobile native était décidée (hors périmètre, section 3) ou si un module devait être déployé indépendamment ; il serait alors créé à ce moment-là.
