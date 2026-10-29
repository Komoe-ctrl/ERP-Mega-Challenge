# 0002 — Architecture du code par domaine métier

- **Statut** : accepté
- **Date** : 2026-10-29

## Décision

- `app/Domain/<Module>/{Models,Actions,Policies,States,Data}` pour chacun des 15 modules ; `app/Domain/Shared` pour ce qui est transversal.
- Contrôleurs fins dans `app/Http/Controllers/<Module>/` ; la logique métier vit dans des classes **Action** testables (une classe, un cas d'usage).
- Pages Vue dans `resources/js/pages/<Module>/`.
- **Exception** : `App\Models\User` reste dans `app/Models`. Il est référencé par `config/auth.php`, Fortify et le starter kit ; le déplacer apporterait du bruit sans bénéfice.
- Le **modèle de données** et la **structure des dossiers** couvrent les 15 modules dès maintenant ; seul le module Administration est implémenté.

## Conséquences

Une fonctionnalité est localisée en un seul endroit. Le prix : davantage de dossiers, vides pour l'instant (balisés par un `README.md` et un `.gitkeep`).
