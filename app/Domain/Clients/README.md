# Module M2 - Clients

Clients (particuliers, entreprises, promoteurs, administrations...), contacts, documents, historique, créances.

- **Version de livraison** : V3
- **État** : À faire (V3)
- **Spécification** : RG2.1, RG2.2, CA2 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `clients.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Clients/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Clients/Actions` |
| Policies (droits côté serveur) | `app/Domain/Clients/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Clients/States` |
| Objets de transfert de données | `app/Domain/Clients/Data` |
| Contrôleurs fins | `app/Http/Controllers/Clients` |
| Pages Vue | `resources/js/pages/Clients` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
