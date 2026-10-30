# 0012 — Projets (M4) branchés sur le backend

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Cycle de vie** : `spatie/laravel-model-states`, un état par statut (`app/Domain/Projets/States`). Les transitions autorisées sont le parcours nominal (Étude → … → Clôturé) et l'abandon depuis Étude, Devis ou Appel d'offres (RG4.1). Les règles qui dépassent la transition (RG4.2, motif) sont dans les Actions `AdvanceProject` et `AbandonProject`, qui lèvent une erreur de validation affichée en notification.
- **RG4.2** : « En cours » exige un chantier ayant au moins un conducteur de travaux affecté (responsable) et au moins une ligne de budget (déboursé sec). Un chef de chantier seul ne suffit pas.
- **RG4.3** : un projet clôturé ou abandonné refuse toute modification (policy `update`), y compris l'ajout de documents ; ses chantiers deviennent eux aussi non modifiables (`Chantier::isReadOnly()`, `ChantierPolicy`).
- **Aucune suppression** : pas de route `DELETE`, `ProjectPolicy::delete` renvoie toujours `false`.
- **Numérotation** : `PRJ-AAAA-NNN`, calculée dans une transaction avec verrou, soft deletes inclus. Le format paramétrable de la section RG1.3 ne couvre que les devis, bons de commande et situations.
- **Tables créées en avance** pour que le modèle de données couvre tous les modules dès la V1 : `clients` (fiche minimale, enrichie en V3), `chantiers`, `chantier_user` (affectations RG1.1) et `budget_lines` (déboursé sec). Leurs écrans et leurs règles arrivent avec M7 et M10.
- **Liste** : filtres (statut, client, responsable, agence, recherche) et pagination faits côté serveur (CA4).
- **Documents** : `spatie/laravel-medialibrary`, collection `documents`, 20 Mo par fichier contrôlés par la requête. Pas encore de téléchargement depuis la fiche.

## Écarts et suites

- La fiche affiche `quote` et `contract` à `null` : le devis et le marché liés viendront avec M5 et M6 (V3).
- Les maquettes `projects` de `resources/js/mocks/chantiers.ts` restent utilisées par les écrans Chantiers, Tableau de bord, Clients, Devis et Marchés ; elles disparaîtront avec ces modules.
- Le filtrage des projets par agence selon les droits (section 11) reste à faire.
