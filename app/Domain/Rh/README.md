# Module M13 - Rh

Employés, contrats, congés, avances, paie.

- **Version de livraison** : V4
- **État** : À faire (V4)
- **Spécification** : RG13.1 à RG13.4, CA13 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `rh.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Rh/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Rh/Actions` |
| Policies (droits côté serveur) | `app/Domain/Rh/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Rh/States` |
| Objets de transfert de données | `app/Domain/Rh/Data` |
| Contrôleurs fins | `app/Http/Controllers/Rh` |
| Pages Vue | `resources/js/pages/Rh` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
