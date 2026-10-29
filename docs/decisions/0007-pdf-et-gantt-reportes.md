# 0007 — Génération PDF et diagramme de Gantt : décision reportée

- **Statut** : reporté
- **Date** : 2026-10-29

## Décision

Aucune bibliothèque de génération PDF ni de Gantt n'est installée pour l'instant, comme demandé.

## À trancher avant les modules concernés

- **PDF** (devis, bons de commande, situations, exports — V2/V3) : critères = rendu fidèle à la charte Mega Challenge, polices, coût mémoire, exécution en file d'attente. Pistes : `spatie/laravel-pdf` (Chromium) ou dompdf.
- **Gantt** (planning de chantier — V1, RG7.3) : la V1 peut démarrer par une **liste de tâches** avec avancement, suffisante pour les alertes RG7.5 à RG7.7. Un composant Gantt n'est à choisir que si la vue calendrier est jugée indispensable.
