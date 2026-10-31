# 0026 — Situations de travaux (M11) et soldes client

## Contexte
Une situation est une facture intermédiaire calculée sur l'avancement réel d'un marché. Le cahier fixe
la formule (RG11.1, RG11.2), le gel après validation (RG11.3) et un exemple chiffré (CA11).

## Décisions
- **Formule** : net HT = (cumul n − cumul n−1) − retenue de garantie − remboursement d'acompte ; la TVA
  s'applique au net HT. `SituationCalculator` (serveur) et `computeSituation` (interface) donnent les
  mêmes arrondis ; l'exemple du cahier (marché de 100 M, 30 % → 40 %, retenue 5 %, acompte 10 %, net
  HT 8 500 000) est testé.
- **Avancement** : saisi globalement, ou ouvrage par ouvrage du devis du marché (`situation_lines`) ;
  dans ce cas le cumul est la moyenne pondérée par le montant des ouvrages, à une décimale. Il ne
  diminue jamais d'une situation à l'autre et ne dépasse pas 100 %.
- **Taux** : repris du marché à la création (retenue, acompte), TVA à 18 %, modifiables en brouillon.
  Le montant du marché est le montant actualisé (avenants validés compris) ; il est re-figé à la
  validation.
- **Une situation à la fois** : une nouvelle situation exige que la précédente soit validée.
- **RG11.3** : validée, la situation est figée (policy `update` limitée au brouillon). Elle est validée
  par une autre personne que son auteur (droit « valider »). Aucune suppression.
- **Paiements** : `situation_payments`, sur une situation validée seulement ; un paiement ne dépasse pas
  le solde restant ni ne porte une date future ; la situation passe à « Payée » quand elle est soldée.
- **Soldes client (RG2.2)** : `ClientBalance` calcule le contracté (montants actualisés des marchés), le
  facturé (net TTC des situations validées ou payées), l'encaissé et le dû.

## Reste à faire
- Les factures clients (Recettes) reprendront les situations validées comme pièces à recouvrer.
- Impression : navigateur (imprimer / PDF) ; le moteur PDF reste à trancher.
