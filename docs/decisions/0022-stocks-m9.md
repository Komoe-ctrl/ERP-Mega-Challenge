# 0022 — Stocks BTP (M9)

Date : 2026-10-31. Statut : acceptée.

## Décisions

- **Magasins** (RG9.1) : dépôts centraux (`StocksSeeder`) et magasins de chantier, créés à la première
  réception du chantier. Chaque magasin a son propre stock (`stock_levels` : quantité, coût moyen pondéré,
  seuil minimal). Un transfert est un mouvement tracé (débit de l'origine au coût moyen, crédit de la
  destination au même coût).
- **Valorisation** (RG9.5) : coût moyen pondéré recalculé à chaque entrée ; les sorties se valorisent
  au coût moyen courant ; une entrée manuelle exige un coût unitaire.
- **Pas de stock négatif** : une sortie, un transfert ou une perte supérieurs au stock sont refusés,
  sous verrou de ligne.
- **Sortie imputée au chantier** (RG9.2) : une sortie exige un chantier. Depuis un **dépôt central**, son
  coût (quantité × coût moyen) est imputé en coût réel sur le poste Matériaux du chantier, sous
  `BudgetLock` ; un retour au dépôt annule cette imputation. Depuis le **magasin d'un chantier**, aucune
  imputation : le coût a déjà été compté à la réception (RG10.1, « un même montant n'est jamais compté deux
  fois »). Un magasin de chantier ne sert que son chantier ; pour en alimenter un autre, on transfère.
- **Entrée à la réception** : les quantités acceptées d'une réception entrent dans le magasin du chantier,
  au prix de la commande (`EnterReceptionIntoStock`, appelé par `RecordReception`).
- **Pertes et ajustements** (RG9.3) : motif obligatoire ; ils sont créés « en attente » et le stock ne
  bouge qu'à la validation par un tiers ayant le droit `stocks.validate` (Direction générale dans la matrice
  actuelle) ; rejet motivé possible ; le valideur n'est jamais l'auteur. Mouvements et inventaires sont
  journalisés (journal « stocks »).
- **Seuil minimal** (RG9.4) : réglé par article et par magasin (droit d'écriture) ; passer sous le seuil
  alerte le magasinier et le Service Achats, une fois par jour et par article (déduplication).
- **Inventaire** (CA9) : l'ouverture fige le stock théorique de tous les articles du magasin ; le stock
  compté se saisit par article ; l'écart (compté − théorique) est affiché ; la validation, par un tiers
  (jamais celui qui a ouvert), ramène le stock à la quantité comptée par un mouvement « inventaire ».
  L'ajustement se calcule sur le stock courant à la validation, pour ne pas écraser un mouvement survenu
  après l'ouverture. Un seul inventaire ouvert par magasin.
- **Droits** (`StockPolicy`) : lecture `stocks.view`, saisie `stocks.create`, seuils `stocks.update`,
  validation `stocks.validate`. L'Administrateur, sans module métier, reçoit 403.

## Conséquences

- Les tests Pest couvrent chaque règle (coût moyen, imputation, double comptage, transferts, pertes,
  seuil, inventaire, droits). `e2e/stocks.mjs` rejoue entrée, transfert, perte validée et inventaire dans
  le navigateur.
- Reste : une sortie d'un dépôt vers un chantier se fait depuis l'écran des mouvements (le chantier doit
  être visible de l'utilisateur) ; une fiche article de stock détaillée et des familles d'articles
  paramétrables restent à faire.
