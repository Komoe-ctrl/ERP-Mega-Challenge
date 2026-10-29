# Module M5 - Devis

Devis composés d'ouvrages et d'articles ; versions conservées ; marge prévue.

- **Version de livraison** : V3
- **État** : À faire (V3)
- **Spécification** : RG5.1 à RG5.3, CA5. Cycle de vie : Brouillon, Soumis, Validé ou Refusé (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `devis.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Devis/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Devis/Actions` |
| Policies (droits côté serveur) | `app/Domain/Devis/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Devis/States` |
| Objets de transfert de données | `app/Domain/Devis/Data` |
| Contrôleurs fins | `app/Http/Controllers/Devis` |
| Pages Vue | `resources/js/pages/Devis` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
