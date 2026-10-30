# 0020 — Connexion, pointage, postes et séparation des droits

Date : 2026-10-30. Statut : acceptée.

## Contexte

Les écrans repris de l'ancienne application de Mega Challenge changent trois choses côté serveur :
la connexion ne demande plus ni service ni titre, le pointage d'arrivée passe après la connexion,
et l'Administrateur ne gère plus que l'Administration.

## Décisions

- **Connexion** : identifiant + mot de passe. Le service, le titre et les droits viennent du compte.
  Après connexion (et après le défi de double authentification), `LoginResponse` envoie l'utilisateur
  sur la page de son rôle (`RoleName::home()`), jamais sur les paramètres de sécurité. Le pointage
  n'est plus une action de la page de connexion.
- **Pointage** : table `attendances` (un pointage par utilisateur et par jour, la première heure est
  conservée), action `ClockIn`, `POST /pointage` pour l'utilisateur authentifié uniquement (aucun
  identifiant d'utilisateur n'est accepté). Le pointage du jour est partagé à l'interface
  (`attendance`). Un rappel s'affiche à la première connexion de la journée.
- **Postes** : un compte a un poste (`users.job_title` + service) et peut en occuper plusieurs
  (`user_postes`). Le poste actif est choisi pour la session (`PUT /poste`, refusé s'il n'appartient pas
  au compte). Le titre n'est qu'un libellé : le compte reste nominatif et les droits viennent des rôles.
- **Administrateur** : uniquement le module Administration (utilisateurs, rôles, étapes de validation,
  paramètres, journal). Aucun accès aux modules métier, ni dans le menu ni côté serveur (403). Une
  migration aligne les bases existantes (le seeder ne touche pas aux rôles déjà créés).
  Cela déroge au cahier (section 4), qui donnait à l'Administrateur la lecture de tous les modules.
- **Direction générale** : lecture sur tous les modules, plus ses validations (matrice inchangée).
- **Pas de contournement global** : aucun `Gate::before` « super-admin » (un test le vérifie).
- **Pas d'auto-attribution** : un administrateur ne peut pas modifier ses propres rôles (erreur de
  validation, et refus dans l'action), ni les permissions d'un rôle qu'il porte (`RolePolicy::update`).
- **Journal et alerte** : toute modification de rôle, de permission ou d'attribution de rôle est
  journalisée et notifiée à la Direction générale (`AccessChangeAudit`, notification de type `securite`).
- **Écrans de l'ancienne application** (`/ecrans/{slug}`, encore sur données fictives) : chaque écran
  est protégé côté serveur par la permission du module qu'il représente (`LegacyScreenController`),
  sinon un administrateur les ouvrirait en tapant l'adresse. Ces routes disparaissent module par module.

## Conséquences

- Les tests Pest vérifient : 403 de l'administrateur sur chaque page et écriture des modules métier,
  accès de chaque profil limité à sa matrice (modules et écrans), absence de `Gate::before`,
  interdiction d'auto-attribution, journalisation et notification, pointage et postes.
- Reste à faire : écran d'administration des postes d'un utilisateur (le sélecteur lit déjà
  `user_postes`), et unicité d'un rôle d'« étapes de validation » (voir le circuit paramétrable).
