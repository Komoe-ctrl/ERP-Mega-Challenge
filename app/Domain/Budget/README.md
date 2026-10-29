# Module M10 - Budget

Budget par chantier : initial, révisé, engagements, coût réel, reste à engager, marges.

- **Version de livraison** : V1 (DS, engagé, restant) puis V3
- **État** : À faire (V1)
- **Spécification** : RG10.1 à RG10.4, CA10. Toute écriture passe par BudgetLock (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `budget.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Budget/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Budget/Actions` |
| Policies (droits côté serveur) | `app/Domain/Budget/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Budget/States` |
| Objets de transfert de données | `app/Domain/Budget/Data` |
| Contrôleurs fins | `app/Http/Controllers/Budget` |
| Pages Vue | `resources/js/pages/Budget` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
