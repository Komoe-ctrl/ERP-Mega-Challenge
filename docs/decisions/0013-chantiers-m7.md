# 0013 — Chantiers (M7) branchés sur le backend

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Calculs côté serveur.** `PlanningCalculator` calcule l'avancement théorique, l'état de chaque tâche (terminée, en retard, à risque, non renseignée, à venir, dans les temps) et l'avancement global pondéré (RG7.5 à RG7.8). Les seuils (15 points, 7 jours) sont lus dans les paramètres (RG1.3). Les écrans reçoivent `health` et `theoretical` ; `lib/planning.ts` ne sert plus qu'aux tests Vitest existants et de valeur de secours au Gantt.
- **CA7.1** : tâche du 1er au 10, 30 % le 8 → théorique 78 %, « à risque » (test unitaire et test HTTP).
- **RG7.2 (affectations).** Les profils Conducteur de travaux et Chef de chantier ne voient et n'écrivent que sur les chantiers où ils sont affectés (table `chantier_user`) ; les autres profils voient tous les chantiers qu'autorise leur droit `chantiers.view`. Le périmètre est dans `ChantierPolicy` et dans la portée `Chantier::visibleTo()`.
- **Rôles d'affectation** : conducteur, chef de chantier, magasinier. Le formulaire de chantier impose un conducteur et un chef portant bien ces rôles ; l'écran « Affecter » ajoute d'autres membres. Les ouvriers et chefs d'équipe de la maquette (personnel sans compte) arrivent avec RH et Main-d'œuvre (V4).
- **Budget du chantier.** Le champ « budget initial » du formulaire est retiré : le déboursé sec se saisit par poste dans le module Budget (M10), et c'est lui qui rend RG4.2 satisfaisable.
- **Avancement (RG7.4)** : une mise à jour par tâche (pourcentage entier, commentaire, photos), historisée dans `task_updates` avec l'avant/après et l'auteur ; la date de dernière mise à jour alimente RG7.7. Les jalons ne sont pas saisissables. Une saisie sans changement n'écrit rien.
- **Rapport journalier (RG7.9)** : un seul par chantier et par jour (contrainte d'unicité et message clair).
- **Photos** : collection `photos` du chantier (medialibrary), avec en propriétés la légende, l'auteur et le rapport ou la tâche d'origine. Images uniquement, 20 Mo maximum.
- **Import Excel (RG7.3)** : colonnes Tâche, Début, Fin prévue, Pondération, Jalon (oui/non, facultatif) ; première ligne = en-tête ; dates Excel, `JJ/MM/AAAA` ou `AAAA-MM-JJ` ; tout ou rien, avec le numéro de la ligne fautive ; 500 tâches au plus.
- **Aucune suppression** de chantier (pas de route `DELETE`, `ChantierPolicy::delete` toujours faux). Lecture seule (RG4.3) : chantier clôturé ou projet clôturé/abandonné.

## Écarts et suites

- L'onglet « Demandes et commandes » de la fiche lit encore les maquettes d'achats ; il sera branché avec M8.
- Les alertes quotidiennes (retard, risque, non renseignée) et leurs notifications viennent avec l'étape Notifications.
- Pas encore de modification ni de suppression d'une tâche depuis l'écran (ajout et import seulement).
