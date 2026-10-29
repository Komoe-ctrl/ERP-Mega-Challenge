# Module M11 - Situations

Situations de travaux calculées automatiquement : cumul, retenue de garantie, acompte, net à payer.

- **Version de livraison** : V3
- **État** : À faire (V3)
- **Spécification** : RG11.1 à RG11.4, CA11 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `situations.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Situations/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Situations/Actions` |
| Policies (droits côté serveur) | `app/Domain/Situations/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Situations/States` |
| Objets de transfert de données | `app/Domain/Situations/Data` |
| Contrôleurs fins | `app/Http/Controllers/Situations` |
| Pages Vue | `resources/js/pages/Situations` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
