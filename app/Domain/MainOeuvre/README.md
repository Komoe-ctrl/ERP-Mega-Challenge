# Module M12 - MainOeuvre

Vue chantier du personnel : affectations, présences, heures, coût par chantier.

- **Version de livraison** : V4
- **État** : À faire (V4)
- **Spécification** : RG12.1 à RG12.3, CA12 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `main_oeuvre.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/MainOeuvre/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/MainOeuvre/Actions` |
| Policies (droits côté serveur) | `app/Domain/MainOeuvre/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/MainOeuvre/States` |
| Objets de transfert de données | `app/Domain/MainOeuvre/Data` |
| Contrôleurs fins | `app/Http/Controllers/MainOeuvre` |
| Pages Vue | `resources/js/pages/MainOeuvre` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
