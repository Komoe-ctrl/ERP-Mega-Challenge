# Module M6 - Marches

Marchés, contrats, avenants, conditions de paiement.

- **Version de livraison** : V3
- **État** : Branché au serveur (V3)
- **Spécification** : RG6.1 à RG6.3, CA6 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `marches.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Marches/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Marches/Actions` |
| Policies (droits côté serveur) | `app/Domain/Marches/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Marches/States` |
| Objets de transfert de données | `app/Domain/Marches/Data` |
| Contrôleurs fins | `app/Http/Controllers/Marches` |
| Pages Vue | `resources/js/pages/Marches` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
