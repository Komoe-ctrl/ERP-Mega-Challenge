# Module M14 - Engins

Parc d'engins et équipements : affectation, carburant, maintenance, coûts.

- **Version de livraison** : V4
- **État** : À faire (V4)
- **Spécification** : RG14.1 à RG14.4, CA14 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `engins.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Engins/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Engins/Actions` |
| Policies (droits côté serveur) | `app/Domain/Engins/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Engins/States` |
| Objets de transfert de données | `app/Domain/Engins/Data` |
| Contrôleurs fins | `app/Http/Controllers/Engins` |
| Pages Vue | `resources/js/pages/Engins` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
