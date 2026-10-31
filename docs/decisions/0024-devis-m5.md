# 0024 — Devis (M5)

## Contexte
Les devis étaient des données de démonstration côté interface. Le cahier (M5) demande une bibliothèque
d'ouvrages au prix calculé (RG5.1), une marge prévue (RG5.2), des versions conservées (RG5.3) et un
cycle Brouillon → Soumis → Validé ou Refusé.

## Décisions
- **Versions** : une ligne `quotes` par version ; le numéro `DEV-{YYYY}-{SEQ:5}` est commun, la clé
  unique est (numéro, version). Un devis refusé se duplique en version suivante (brouillon) ; on ne
  peut dupliquer que la dernière version.
- **Ouvrage** : `ouvrages` + `ouvrage_components` (article du référentiel au prix de référence, ou ligne
  libre de main-d'œuvre). Déboursé sec = somme des composants ; prix de vente = déboursé × 1,25 arrondi
  à la centaine de FCFA (coefficient de vente `Ouvrage::SALE_COEFFICIENT`, repris des maquettes).
- **Ajout d'un ouvrage** : la ligne reprend le prix calculé ; le déboursé sec du devis augmente de
  quantité × déboursé unitaire, ce qui alimente la marge prévue (RG5.2 : total HT − déboursé sec).
- **Statuts** : `spatie/laravel-model-states`. Seul le brouillon est modifiable (policy). Soumettre exige
  au moins une ligne valide.
- **Séparation des tâches** : le valideur est un autre utilisateur que l'auteur (policy et action).
  La Direction générale (lecture + validation) valide les devis de la Direction technique.
- **Projet** : un devis ne vise qu'un projet du client choisi, au stade étude, devis ou appel d'offres.
  La validation d'un devis fait passer un projet « Étude » à « Devis ».
- **Totaux** : calculés côté serveur (`QuoteTotals`) et en direct dans l'éditeur (`lib/billing.ts`) avec
  les mêmes arrondis.
- **Journal** : les changements du devis sont consignés (journal `devis`).

## Reste à faire
- Création d'un marché depuis un devis validé (RG6.1) : traité avec le module Marchés (M6).
- Écran de gestion de la bibliothèque d'ouvrages (aujourd'hui alimentée par `OuvragesSeeder` ou en base).
- Aperçu imprimable : navigateur (impression / PDF) ; le choix du moteur PDF reste à trancher.
