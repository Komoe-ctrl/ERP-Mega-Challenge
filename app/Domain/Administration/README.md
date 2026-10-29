# Module M1 - Administration

Socle : utilisateurs, rôles, permissions, services, agences, sites, paramètres, exercices comptables, journal des activités.

- **Version de livraison** : V1
- **État** : Implémenté (socle)
- **Spécification** : RG1.1 à RG1.5, CA1 (cahier des charges, `docs/cahier-des-charges.pdf`)
- **Permissions** : `administration.view`, `.create`, `.update`, `.validate`, `.delete`

## Où va le code

| Quoi | Où |
| --- | --- |
| Modèles Eloquent | `app/Domain/Administration/Models` |
| Logique métier (une classe = un cas d'usage) | `app/Domain/Administration/Actions` |
| Policies (droits côté serveur) | `app/Domain/Administration/Policies` |
| Cycles de vie (spatie/laravel-model-states) | `app/Domain/Administration/States` |
| Objets de transfert de données | `app/Domain/Administration/Data` |
| Contrôleurs fins | `app/Http/Controllers/Administration` |
| Pages Vue | `resources/js/pages/Administration` |

Voir `docs/ARCHITECTURE.md` pour les conventions.
