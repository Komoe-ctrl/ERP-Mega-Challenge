# Module M7 - Chantiers

Fiche chantier, affectations, planning, avancement, alertes de retard, rapports journaliers, photos.

- **Version de livraison** : V1
- **État** : À faire (V1)
- **Spécification** : RG7.1 à RG7.9, CA7.1, CA7.2. Un utilisateur de terrain ne voit que ses chantiers (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `chantiers.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Chantiers/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Chantiers/Actions` |
| Policies (droits côté serveur) | `app/Domain/Chantiers/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Chantiers/States` |
| Objets de transfert de données | `app/Domain/Chantiers/Data` |
| Contrôleurs fins | `app/Http/Controllers/Chantiers` |
| Pages Vue | `resources/js/pages/Chantiers` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
