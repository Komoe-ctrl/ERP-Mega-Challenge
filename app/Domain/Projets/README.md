# Module M4 - Projets

Projets de l'étude à la clôture. Statuts : Étude, Devis, Appel d'offres, Marché, Préparation, En cours, Réception, Clôturé.

- **Version de livraison** : V1 (fiche et statuts) puis V3
- **État** : À faire (V1)
- **Spécification** : RG4.1 à RG4.3, CA4. Cycle de vie : spatie/laravel-model-states (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `projets.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Projets/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Projets/Actions` |
| Policies (droits côté serveur) | `app/Domain/Projets/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Projets/States` |
| Objets de transfert de données | `app/Domain/Projets/Data` |
| Contrôleurs fins | `app/Http/Controllers/Projets` |
| Pages Vue | `resources/js/pages/Projets` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
