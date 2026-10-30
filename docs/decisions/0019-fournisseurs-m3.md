# 0019 — Fournisseurs (M3) branchés sur le backend

- **Statut** : accepté
- **Date** : 2026-10-31

## Décisions

- **Fiche** : numéro `FRS-NNNN` attribué dans une transaction avec verrou, raison sociale unique (doublon refusé sans tenir compte des espaces en bordure), contact, téléphone (format contrôlé), e-mail facultatif, ville.
- **Catégories (RG3.1)** : une ou plusieurs parmi les six du cahier (matériaux, équipements, engins, transport, sous-traitance, prestations techniques), table `supplier_categories`. La liste se filtre par catégorie ; la consultation (processus d'achat) ne proposera que les fournisseurs actifs de la bonne catégorie.
- **Tarifs (RG3.2)** : table `supplier_tariffs`, un tarif par fournisseur et par article actif, en FCFA entiers, mis à jour sans doublon. Le référentiel des prix reste le prix de référence interne ; l'écran compare les deux.
- **Jamais de suppression** : désactivation motivée (motif au journal), réversible. Aucune route `DELETE`.
- **Droits** : lecture pour le Service Achats BTP, la Finance, la Direction générale et l'Administrateur ; écriture (créer, modifier, désactiver, tarifs) réservée au Service Achats BTP, d'après la matrice de la section 4. La Direction technique n'a pas de droit sur ce module dans la matrice : elle reçoit un 403.
- **Liste** paginée côté serveur (15 par page) avec recherche (nom, ville, contact), catégorie et état.

## Écarts et suites

- **Évaluation (RG3.3), note moyenne, commandes en cours et factures non payées (CA3)** : ils dépendent des réceptions, des commandes et des factures et sont branchés avec le processus d'achat complet. En attendant, la fiche affiche les commandes et factures des maquettes (par raison sociale), « Non évalué » et un message explicite ; la saisie d'évaluation fictive a été retirée.
- Un seul contact par fournisseur pour l'instant (le cahier évoque « contacts » au pluriel).
