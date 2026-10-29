# Module M8 - Achats

Service Achats BTP : demandes d'achat, validation, référentiel des prix ; V2 : consultation, bons de commande, réceptions, factures.

- **Version de livraison** : V1 (demandes, validation, référentiel des prix) puis V2
- **État** : À faire (V1)
- **Spécification** : RG8.1 à RG8.16, CA8.1 à CA8.3. Cycle de vie de la demande : spatie/laravel-model-states. Toute validation touchant un budget passe par BudgetLock (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `achats.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Achats/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Achats/Actions` |
| Policies (droits côté serveur) | `app/Domain/Achats/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Achats/States` |
| Objets de transfert de données | `app/Domain/Achats/Data` |
| Contrôleurs fins | `app/Http/Controllers/Achats` |
| Pages Vue | `resources/js/pages/Achats` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
