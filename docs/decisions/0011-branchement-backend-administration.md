# 0011 — Branchement du backend : Administration (M1)

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Désactivation motivée.** Agences, services et sites ne sont jamais supprimés (aucune route `DELETE`, vérifiée par un test) : on les désactive, avec un motif obligatoire inscrit au journal (`event = deactivation`, propriété `motif`). Ce motif n'est pas stocké sur la ligne, seulement au journal.
- **Clôture d'exercice.** Motif obligatoire, inscrit au journal (`event = validation`). Un exercice clôturé refuse `update` et `delete` (policy) ; il ne peut pas être clôturé deux fois.
- **Clés de paramètres avec des points.** Le validateur de Laravel lit `budget.warning_percent` comme une imbrication. La requête encode donc les points en `__` avant validation (`values.budget__warning_percent`) ; l'écran refait la conversion inverse pour afficher les erreurs.
- **Règles de validation des paramètres** : pourcentages entre 0 et 100, montants et durées entiers positifs, numérotation contenant un compteur `{SEQ:n}`, au moins 3 fournisseurs consultés (RG8.11), alerte critique supérieure ou égale à l'avertissement (RG10.3).
- **Unités et groupes des paramètres** déduits de la clé côté serveur (`_fcfa`, `_percent`, `_points`, `_days`, `_mb`) : pas de colonne supplémentaire en base.
- **Journal** : paginé et filtré (événement, auteur) côté serveur. Les événements de l'écran (création, modification, validation, suppression) viennent de la colonne `event` ; une désactivation s'affiche comme « suppression ».
- **Comptes de démonstration.** `ERP_SEED_DEMO_USERS=true` crée un compte par profil (noms des maquettes, mot de passe = `ADMIN_PASSWORD`). Par défaut `false` : jamais en production.

## Écart avec le cahier

- Le filtrage des données par agence « selon les droits » (section 11, Multi-sites) n'est pas encore appliqué : il le sera avec les modules qui portent des données par agence (projets, chantiers).
