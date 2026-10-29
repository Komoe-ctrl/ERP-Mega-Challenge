# Module M9 - Stocks

Magasins, mouvements de stock, inventaires, coût moyen pondéré.

- **Version de livraison** : V2
- **État** : À faire (V2)
- **Spécification** : RG9.1 à RG9.5, CA9 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `stocks.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Stocks/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Stocks/Actions` |
| Policies (droits côté serveur) | `app/Domain/Stocks/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Stocks/States` |
| Objets de transfert de données | `app/Domain/Stocks/Data` |
| Contrôleurs fins | `app/Http/Controllers/Stocks` |
| Pages Vue | `resources/js/pages/Stocks` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
