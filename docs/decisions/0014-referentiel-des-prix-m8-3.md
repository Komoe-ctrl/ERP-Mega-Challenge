# 0014 — Référentiel des prix (M8.3) branché sur le backend

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Écriture réservée au Service Achats BTP (RG8.13, CA8.3).** Le droit `achats.update` est aussi porté par le conducteur, le chef de chantier et le magasinier (matrice de la section 4) : il ne suffit donc pas. `ArticlePolicy` exige en plus le rôle `service_achats_btp` pour créer, modifier, désactiver et importer. La vérification est côté serveur ; testée avec cinq profils (conducteur, chef, magasinier, direction technique, administrateur) qui reçoivent un 403 même en forgeant la requête.
- **Historique des prix (RG8.13).** Table `article_price_histories` (prix, auteur, motif, date). Une ligne à la création (« Création de l'article »), une par changement de prix avec un motif obligatoire ; modifier un autre champ n'ajoute rien.
- **Jamais de suppression (RG8.14).** Aucune route `DELETE`, `ArticlePolicy::delete` toujours faux ; la désactivation exige un motif, inscrit au journal, et se réverse.
- **Listes fermées** : catégories (famille de stocks du cahier + Maçonnerie) et unités sont des constantes de `Article`. Un import avec une catégorie ou une unité inconnue est refusé.
- **Import (RG8.16)** : Excel (.xlsx, .xls) ou CSV (séparateur `;`, `,` ou tabulation, UTF-8 ou Windows-1252) ; première ligne = en-tête ; tout ou rien avec le numéro de la ligne fautive ; prix entier en FCFA (« 6 500 » accepté, décimales refusées) ; 2000 articles au plus. Une référence existante est mise à jour, son nouveau prix est historisé (« Import du référentiel »). La lecture des tableurs est partagée avec l'import du planning (`SheetReader`).
- **Signalement d'article manquant (RG8.15)** : tout profil qui peut créer une demande d'achat signale ; seul le Service Achats BTP voit la liste et la marque comme traitée. Le bouton « Signaler » de l'écran de demande est branché avec M8.1.

## Écarts et suites

- Le référentiel liste 15 articles par page (filtres serveur : recherche, catégorie, état).
- Le lien entre un signalement traité et l'article créé n'est pas encore proposé à l'écran (la colonne `article_id` existe).
