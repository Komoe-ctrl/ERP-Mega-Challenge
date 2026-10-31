# 0023 — Clients (M2)

## Contexte
Le module Clients était alimenté par des données de démonstration. Le cahier (M2) demande un numéro
unique, une détection de doublon (RG2.1) et une fiche agrégée projets / créances (RG2.2, CA2).

## Décisions
- **Numérotation** : `CLI-{YYYY}-{SEQ:4}` via `DocumentNumbers`.
- **Doublon** : même nom et même contact (casse et espaces ignorés). La création est refusée avec une
  erreur sur `name` commençant par « Doublon possible » ; l'utilisateur peut confirmer
  (`confirm_duplicate`) pour créer quand même.
- **Contacts et documents** : table `client_contacts` ; documents dans la collection médias `documents`
  (pdf, images, Office, 20 Mo).
- **Soldes** : `ClientBalance` renvoie les projets réels ; facturé / payé / dû valent 0 tant que les
  situations de travaux et paiements (M11) ne sont pas branchés.
- **Droits** : la Direction technique reçoit lecture/création/modification des clients, ainsi que les
  marchés et situations (migration dédiée), car elle porte le cycle commercial.

## Limite connue
En développement, `artisan serve` met parfois 3 à 55 s à traiter un envoi multipart (mesuré avec curl
sur `/up`, indépendant du code applicatif). Les tests Pest ne sont pas concernés ; le script e2e
attend jusqu'à 120 s.
