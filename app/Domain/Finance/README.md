# Module M15 - Finance

Caisse, banque, paiements, rapprochement, rapports de direction, trésorerie.

- **Version de livraison** : V4
- **État** : À faire (V4)
- **Spécification** : RG15.1 à RG15.5, CA15 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `finance.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Finance/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Finance/Actions` |
| Policies (droits côté serveur) | `app/Domain/Finance/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Finance/States` |
| Objets de transfert de données | `app/Domain/Finance/Data` |
| Contrôleurs fins | `app/Http/Controllers/Finance` |
| Pages Vue | `resources/js/pages/Finance` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
