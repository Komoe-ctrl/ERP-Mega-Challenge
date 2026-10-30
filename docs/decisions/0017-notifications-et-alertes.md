# 0017 — Notifications et alertes quotidiennes

- **Statut** : accepté
- **Date** : 2026-10-30

## Décisions

- **Une seule notification générique** (`AppAlert`) avec un type (demande, décision, budget, planning ; stock, engin et échéance viendront avec leurs modules), un titre, un message et un lien. Elle s'affiche dans le centre de notifications (canal `database` de Laravel) et part par e-mail.
- **Canaux paramétrables par utilisateur** : colonnes `notify_app` et `notify_email` (vrai par défaut), réglables depuis l'écran Notifications. Aucun canal activé : rien n'est écrit.
- **Un échec d'envoi ne bloque jamais l'action métier** : l'erreur est rapportée (`report()`) et l'action continue.
- **Envoi synchrone.** Les notifications ne sont pas mises en file : une demande validée ne doit pas dépendre d'un worker. En production, le coût d'un e-mail dans la requête est acceptable à cette échelle ; passer la notification en `ShouldQueue` suffira si Horizon est supervisé.
- **Événements notifiés (V1)** : soumission d'une demande d'achat → conducteurs du chantier (et Direction générale si son circuit l'exige, RG8.7), jamais le demandeur ; décision (validée, refusée, renvoyée) → le demandeur.
- **Tâche planifiée `erp:send-alerts`, chaque jour à 7 h** :
  - planning (RG7.6, RG7.7) : un message par chantier en préparation ou en cours qui compte des tâches en retard, à risque ou non renseignées, au chef de chantier et au conducteur ; la liste des tâches figure dans le message ;
  - budget (RG10.3) : un message par chantier dont la consommation atteint l'avertissement (80 %) ou le seuil critique (100 %), aux conducteurs du chantier et à la Direction technique.
- **Une même alerte n'est pas renvoyée plus d'une fois par jour** : table `sent_alerts` (destinataire, clé d'objet, jour) à contrainte d'unicité ; clés `planning:<chantier>` et `budget:<chantier>`. Tant que la situation persiste, le rappel est quotidien.
- **Visibilité** : chacun ne voit, ne lit et ne règle que ses notifications (404 sur celles des autres). La pastille du menu est une propriété Inertia partagée, calculée à la demande.

## Écarts et suites

- Les alertes de stock, de maintenance d'engin et d'échéance de facture ou de situation seront ajoutées avec les modules V2 à V4.
- Le tableau de bord lit encore les notifications fictives ; il est branché à l'étape suivante.
