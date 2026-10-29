# 0010 — Reste à faire pour démarrer la V1

- **Statut** : feuille de route
- **Date** : 2026-10-29

Le socle est posé ; la V1 (« suivi de chantier ») reste à développer, dans cet ordre conseillé :

1. **Administration** (finir) : écrans de gestion des agences, services, sites, paramètres (RG1.3) et exercices (clôture) ; consultation du journal des activités ; filtrage des données par agence.
2. **Projets** (M4) : fiche, statuts (cycle de vie avec `model-states`), motif d'abandon, contrôle du passage « En cours » (RG4.1 à RG4.3).
3. **Chantiers** (M7) : fiche, affectations explicites des conducteurs et chefs (« ne voient que leurs chantiers »), tâches, avancement, alertes retard / risque / non renseigné (RG7.5 à RG7.7), import Excel du planning, photos (medialibrary), rapports journaliers.
4. **Achats — référentiel des prix** (M8.3) : articles, unités, historique des prix, import Excel/CSV, demande d'ajout d'article.
5. **Achats — demandes d'achat** (M8.1) : lignes, prix figés à la validation, cycle de vie en 6 états, validation avec séparation des tâches et seuils (RG8.1 à RG8.7).
6. **Budget** (M10) : déboursé sec par poste, engagements (via `BudgetLock`), reste à engager, seuils d'alerte à 80 % et 100 %.
7. **Notifications** : centre de notifications, e-mails, une alerte au plus par jour et par destinataire.
8. **Tableaux de bord** par profil (terrain, achats, direction) avec les graphiques Chart.js.
9. **Traduction française** des écrans du starter kit (connexion, paramètres), encore en anglais.
10. Mode hors ligne (PWA) pour les écrans terrain : à étudier (section 11).
