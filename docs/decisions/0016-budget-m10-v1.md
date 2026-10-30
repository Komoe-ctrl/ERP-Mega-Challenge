# 0016 — Budget et dépenses d'un chantier (M10, version V1)

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Indicateurs.** Par chantier et par poste : budget initial (déboursé sec), révisé, engagé, coût réel, reste à engager (révisé − engagé − réel) et taux de consommation (engagé + réel) / révisé. Calculés par `BudgetSummary`, côté serveur.
- **Seuils (RG10.3).** Avertissement à 80 %, alerte critique à 100 % du budget révisé consommé, lus dans les paramètres (`budget.warning_percent`, `budget.critical_percent`). Les notifications arrivent avec l'étape dédiée.
- **Structure du budget.** Un poste (nom, nature, budget initial) se crée par `CreateBudgetLine`, sous `BudgetLock` ; le budget révisé démarre égal à l'initial. C'est ce qui rend RG4.2 satisfaisable (testé de bout en bout : projet « Préparation » → « En cours »).
- **Droits.** La matrice du cahier donne la lecture du budget à la plupart des profils et aucune écriture dédiée. Les écritures s'appuient donc sur le droit d'écrire sur le chantier (affectation RG7.2, lecture seule RG4.3, tous deux testés) : créer un poste ou demander une révision exige ce droit hors chef de chantier ; saisir une dépense diverse (RG10.4) l'exige sans exclure le chef.
- **Révisions (RG10.2).** Table `budget_revisions` : montant signé, motif (10 caractères minimum), auteur, statut (en attente, validée, rejetée), décideur. Une révision en attente ne change rien. La validation est réservée au droit `budget.validate` (Direction générale), jamais à l'auteur, une seule fois, sous verrou de budget ; elle refuse de rendre le budget d'un poste négatif. Un rejet exige un motif.
- **Dépenses diverses (RG10.4).** Table `budget_expenses` avec justificatif obligatoire (PDF ou image, 20 Mo) ; elles s'ajoutent au coût réel du poste choisi, sous verrou. Une dépense peut dépasser le budget (c'est un coût constaté) : le seuil d'alerte joue alors.
- **CA10.** Les montants engagés d'un poste sont la somme des engagements actifs du registre ; le coût réel de ses dépenses diverses : un test le vérifie avec deux demandes validées et une dépense.
- **Données de démonstration.** Les postes de la maquette (17), deux révisions validées et trois dépenses avec justificatif factice. Les engagements semés incluent des commandes antérieures sans ligne au registre (V2).

## Écarts et suites

- L'édition ou la suppression d'un poste n'est pas prévue : le budget d'un poste évolue seulement par révision validée.
- Le coût réel issu des réceptions, des sorties de stock, de la main-d'œuvre et des engins (M10 complet) arrive avec les versions V2 à V4 ; la marge prévue et la marge réelle avec le module Situations (V3).
