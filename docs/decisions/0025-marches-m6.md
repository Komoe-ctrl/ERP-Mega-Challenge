# 0025 — Marchés (M6)

## Contexte
Le cahier (M6) demande qu'un marché naisse d'un devis validé (RG6.1), que les avenants modifient le
montant et le délai (RG6.2) et que les conditions de paiement alimentent les situations (RG6.3).

## Décisions
- **Création** : `CreateContract` n'accepte qu'un devis validé, un seul marché par devis (clé unique
  sur `quote_id`). Le montant initial reprend le total HT du devis ; le numéro est `MAR-{YYYY}-{SEQ:5}`.
  La date de signature ne peut pas être dans le futur.
- **Projet** : la création du marché fait passer le projet au statut « Marché », en franchissant les
  étapes intermédiaires (étude → devis → appel d'offres → marché) par les transitions autorisées.
- **Avenants** : numérotés `AV-01`, `AV-02`… par marché. Un avenant saisi est « en attente » et sans
  effet. Sa validation exige le droit « valider » et une autre personne que son auteur ; elle est
  refusée si le montant actualisé devenait négatif. Un avenant a un montant (positif ou négatif) et/ou
  un délai ; les deux nuls sont refusés.
- **RG6.2** : montant actualisé = montant initial + avenants validés ; délai = délai initial + jours des
  avenants validés. Calcul côté serveur (`Contract::totals`).
- **Droits** : Direction technique (écriture et validation, avec séparation des tâches) ; Direction
  générale (lecture et validation). Un marché n'est jamais supprimé.

## Reste à faire
- Situations de travaux (M11) : elles reprendront les taux du marché (acompte, retenue, délai de
  paiement) ; la fiche marché liste déjà ses situations (vide pour l'instant).
