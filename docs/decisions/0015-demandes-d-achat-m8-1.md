# 0015 — Demandes d'achat (M8.1) branchées sur le backend

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Cycle de vie (RG8.4)** : `spatie/laravel-model-states`, six états. Brouillon → En attente → Validée / Refusée / Renvoyée ; Renvoyée → En attente après correction ; annulation par l'auteur depuis Brouillon, En attente ou Renvoyée. Validée, Refusée et Annulée sont définitives.
- **Pas de prix saisi (RG8.2).** La ligne ne porte ni prix ni montant : le prix est celui du référentiel jusqu'à la validation, puis `frozen_price` (RG8.6). Un prix forgé dans la requête est ignoré.
- **Validation (RG8.6, CA8.1)** : une seule transaction `BudgetLock` qui verrouille les postes du chantier, relit le statut de la demande (un double clic ne valide pas deux fois), applique les ajustements de quantités, fige les prix, engage le poste et inscrit l'engagement au registre `budget_commitments`. Tout est annulé en cas d'erreur.
- **Poste engagé** : celui choisi par le demandeur (RG8.1) ; sinon le poste de matériaux le moins consommé. Un chantier sans aucun poste de budget ne peut pas recevoir d'engagement : la validation est refusée avec un message (même pour la Direction), et rien n'est écrit.
- **Registre des engagements** : table `budget_commitments` (source polymorphe, montant, statut actif/soldé). La colonne `committed_amount` des postes en est la somme maintenue sous verrou. Le solde à la réception (RG10.1) viendra avec le processus d'achat (V2).
- **Validation Direction (RG8.7)** : requise quand le plus grand des montants (demandé ou ajusté) dépasse le seuil paramétré (5 000 000 FCFA par défaut, `validation.direction_threshold_fcfa`), ou quand le montant ajusté dépasse le budget restant relu sous verrou. Seul le profil Direction générale valide alors ; les autres profils peuvent renvoyer ou refuser. Le plus grand des deux montants empêche de contourner la Direction en ajustant les quantités à la baisse.
- **Séparation des tâches** : `PurchaseRequestPolicy::decide` s'appuie sur `canValidateOthers` : l'auteur ne valide, ne refuse ni ne renvoie jamais sa demande, même avec le droit de validation.
- **Périmètre (RG7.2)** : un conducteur ou un chef ne voit, ne crée et ne décide que sur ses chantiers. Le conducteur valide les demandes de ses chefs ; un autre conducteur reçoit un 403.
- **Motifs** : refus, renvoi et annulation exigent un motif, inscrit à l'historique visible de la demande (`purchase_request_events`) et au journal des activités. Les écarts de quantités sont tracés (« Quantités ajustées », ancien → nouveau).
- **Soumission** : exige une justification de 5 caractères ; un brouillon n'en exige pas. Les dates de besoin ne sont pas dans le passé ; les articles désactivés sont refusés (RG8.14) ; aucune demande sur un chantier en lecture seule (RG4.3).
- **Numérotation** : `DA-AAAA-NNNN`, attribuée dans la transaction avec verrou. Le format paramétrable de la section RG1.3 ne couvre que devis, bons de commande et situations.

## Écarts et suites

- La suite du parcours d'achat sur la fiche (bons de commande, réceptions, factures, CA8.2) lit encore les maquettes ; elle sera branchée avec le processus d'achat complet (V2).
- Les notifications (nouvelle demande à valider, décision au demandeur) arrivent avec l'étape Notifications ; les événements sont déjà enregistrés.
- Les montants engagés semés pour la démonstration (BudgetSeeder) incluent des commandes antérieures qui n'ont pas de ligne au registre.
- Le test de concurrence réelle (deux validations simultanées) n'est pas automatisé : le test séquentiel prouve que le reste à engager est relu à chaque validation, et le verrou est celui de `BudgetLock`.
