# 0018 — Tableau de bord par profil

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Trois vues, choisies côté serveur** : terrain (chef de chantier, conducteur, magasinier), achats (Service Achats BTP, Comptabilité) et direction (tous les autres profils). L'écran ne devine plus le profil à partir de la liste des rôles.
- **Terrain.** Mes chantiers (affectations, RG7.2), mes demandes d'achat (dont je suis l'auteur), mes tâches en alerte. Les raccourcis « Avancement » et « Rapport du jour » visent mon premier chantier.
- **Achats.** Demandes validées à prendre en charge. Les commandes, factures à contrôler et stocks sous le seuil lisent encore les maquettes : ils seront branchés avec le processus d'achat complet et les stocks (V2).
- **Direction.** Projets en cours, chantiers actifs et suspendus, demandes à valider et montant, budget consolidé et par chantier (graphique), alertes de budget et de planning, file « à valider » (si le droit de validation est détenu) et dernières notifications.
- **Chaque bloc respecte les droits.** Un profil sans droit de lecture sur Projets, Chantiers, Budget ou Achats reçoit `null` pour le bloc correspondant et l'écran ne l'affiche pas (ex. la Finance n'a ni projets ni chantiers). La file « à valider » est vide sans droit de validation.
- **Alertes de planning calculées par le serveur** avec les seuils des paramètres (même calculateur que la fiche chantier et les alertes quotidiennes). La date affichée est celle du serveur.

## Écarts et suites

- Les chiffres de la direction sont calculés à chaque affichage ; sur de gros volumes, ils pourraient être mis en cache quelques minutes.
- Le tableau de bord d'un chantier (CA7.2) reste la fiche chantier, déjà branchée.
