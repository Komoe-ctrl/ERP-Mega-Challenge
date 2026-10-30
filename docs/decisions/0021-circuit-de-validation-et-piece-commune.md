# 0021 — Circuit de validation paramétrable et pièce commune

Date : 2026-10-30. Statut : acceptée.

## Contexte

L'ancienne application de Mega Challenge saisit toutes ses pièces (demande d'achat, note de frais,
recette, pièce de caisse) dans le même écran et les fait passer par le même circuit :
saisie → validation du chef de service → approbation du Directeur Mega Challenge.

## Décisions

- **Circuit unique, paramétré dans Administration** (`validation_steps`) : ordre, libellé, rôle habilité,
  libellé du bouton, actif/inactif. Une étape est portée par un **rôle**, jamais par un compte nommé.
  Le circuit est remplacé en bloc (`SaveValidationSteps`) ; il est refusé s'il ne reste aucune étape
  active, si un rôle est inconnu, ou si des pièces attendent une décision (leur position deviendrait
  ambiguë). Chaque étape modifiée est journalisée.
- **Circuit par défaut** (`PiecesSeeder`) : « Validation du chef de service » → rôle Direction technique ;
  « Approbation du Directeur Mega Challenge » → rôle Direction générale.
- **Moteur** (`SubmitPiece`, `AdvancePiece`, `RejectPiece`) : l'auteur soumet sa pièce ; chaque étape est
  franchie par un porteur du rôle de l'étape ; **l'auteur ne valide jamais sa propre pièce**, même s'il
  porte le rôle de l'étape (séparation des tâches) ; une étape ne se saute pas ; une étape désactivée est
  ignorée ; un rejet exige un motif et rend la pièce à l'auteur, qui la corrige et la resoumet. Les décisions
  se font sous verrou de ligne. Le porteur du rôle de l'étape suivante est alerté, puis l'auteur à
  l'approbation ou au rejet.
- **Historique** (`validation_events`, morphe) : qui, quand, quelle étape, quel commentaire. Il alimente
  « Suivi workflow » et pourra servir aux demandes d'achat, qui gardent leur propre machine d'états.
- **Pièce commune** (`pieces` + `piece_lines`) pour la note de frais, la recette et la pièce de caisse :
  période d'imputation (AAAA-MM) qui doit tomber dans un **exercice ouvert** (RG1.4), n° de pièce unique
  par type (saisi, ou numéro automatique `NF-2026-0001` par `DocumentNumbers`), imputation analytique
  Client / Projet → Produit / Lot / Section → Composante (cohérence vérifiée : le lot appartient au projet,
  la composante au lot), justificatif joint (médiathèque), lignes imputées à une rubrique budgétaire.
  Montants entiers en FCFA ; **le montant de chaque ligne et le total sont calculés côté serveur**.
- **Panneau budget** (`BudgetPanel`) calculé côté serveur : budget de la rubrique pour l'exercice,
  consommé (pièces approuvées) + en cours (saisies et en validation), restant, taux, dépassement.
  Le dépassement est signalé, pas bloquant (comme dans l'ancienne application).
- **Droits** (`PiecePolicy`) : le module du type de pièce donne la lecture et la saisie (Achats pour
  les notes de frais, Finance pour les recettes et pièces de caisse) ; le rôle de l'étape donne le droit de
  décider ; un porteur d'un rôle du circuit consulte les pièces à valider. L'Administrateur, sans module
  métier, reçoit 403.

## Reste à faire

- Pages Inertia `Pieces/Index`, `Pieces/Show` et « Suivi workflow » : elles arrivent avec le module
  Notes de frais (V1), premier utilisateur de la pièce commune.
- Rattacher les demandes d'achat existantes à l'historique commun.
- Budget par exercice et par rubrique complet (élaboration, reconduction, réalisés) : V3.
