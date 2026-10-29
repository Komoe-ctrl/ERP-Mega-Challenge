# Module M3 - Fournisseurs

Fiches fournisseurs, catégories, tarifs, historique des commandes, évaluation.

- **Version de livraison** : V2
- **État** : À faire (V2)
- **Spécification** : RG3.1 à RG3.3, CA3 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `fournisseurs.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Fournisseurs/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Fournisseurs/Actions` |
| Policies (droits côté serveur) | `app/Domain/Fournisseurs/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Fournisseurs/States` |
| Objets de transfert de données | `app/Domain/Fournisseurs/Data` |
| Contrôleurs fins | `app/Http/Controllers/Fournisseurs` |
| Pages Vue | `resources/js/pages/Fournisseurs` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
