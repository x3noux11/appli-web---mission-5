# Système de Gestion de Crèche pour le RAM

Ce projet est un système complet de gestion de crèche pour le RAM (Relais d'Assistantes Maternelles), permettant de gérer deux types d'accueil :
- Accueil régulier pour les enfants inscrits annuellement
- Accueil occasionnel dans les cas où il reste des places disponibles

## Architecture du système

Le système se compose de deux applications :

1. **Application Web (lourde)** : Pour la gestion des plannings des accueils réguliers et occasionnels
   - Interface d'administration pour le personnel de la crèche
   - Consultation et gestion des inscriptions
   - Gestion des enfants et des groupes d'âge

2. **Application Android (légère)** : Pour permettre aux parents d'inscrire leurs enfants si des places sont disponibles
   - Inscription au moins 24 heures à l'avance
   - Consultation des places disponibles
   - Suivi des inscriptions

## Prérequis techniques

### Application Web
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache ou Nginx)
- Extension PDO pour PHP

### Application Android
- Android Studio
- JDK 8 ou supérieur
- API REST pour communiquer avec le serveur

## Installation de l'application Web

1. Cloner le dépôt ou extraire les fichiers dans le répertoire web
2. Créer une base de données MySQL nommée `ram_creche`
3. Importer le fichier `database_schema.sql` dans la base de données
4. Configurer les paramètres de connexion à la base de données dans `config/database.php`
5. Assurez-vous que les répertoires `upload` et `temp` sont accessibles en écriture par le serveur web
6. Créer un compte administrateur avec les accès par défaut (admin@ram-creche.fr / admin123)

## Structure de l'application Web

```
appli web/
├── actions/           # Scripts de traitement des formulaires
├── assets/            # Ressources statiques (CSS, JS, images)
├── config/            # Fichiers de configuration
├── includes/          # Fichiers d'inclusion PHP
├── pages/             # Pages principales de l'application
├── upload/            # Répertoire pour les fichiers téléchargés
├── index.php          # Point d'entrée de l'application
├── database_schema.sql # Schéma de la base de données
└── README.md          # Documentation
```

## Fonctionnalités principales

### Application Web

#### Gestion des utilisateurs
- Inscription et connexion des utilisateurs (parents, personnel, administrateurs)
- Gestion des profils utilisateurs
- Contrôle d'accès basé sur les rôles

#### Gestion des enfants
- Ajout, modification et consultation des enfants
- Gestion des informations médicales et personnelles
- Attribution des enfants aux parents

#### Gestion des plannings
- Définition des créneaux horaires par groupe d'âge
- Visualisation du planning hebdomadaire
- Gestion des places disponibles

#### Gestion des inscriptions
- Inscriptions régulières (annuelles)
- Inscriptions occasionnelles (à la demande)
- Validation des inscriptions occasionnelles
- Suivi des présences

### Application Android

#### Authentification
- Connexion sécurisée
- Récupération de mot de passe

#### Consultation des disponibilités
- Affichage des places disponibles par date et groupe d'âge
- Filtrage par groupe d'âge

#### Gestion des inscriptions
- Demande d'inscription occasionnelle
- Suivi des inscriptions en cours
- Notification de confirmation ou refus

## Aperçu de l'application Web

L'application web comprend plusieurs pages principales :
- Accueil : Présentation du service et connexion
- Tableau de bord : Résumé des informations importantes
- Planning : Vue hebdomadaire des inscriptions
- Enfants : Gestion des enfants
- Inscriptions : Gestion des inscriptions régulières et occasionnelles
- Administration : Paramétrages (réservé aux administrateurs)

## Développement futur

- Intégration d'un système de facturation
- Mise en place d'un système de notification par email et SMS
- Ajout d'un module statistique pour analyser l'occupation de la crèche
- Génération de rapports d'activité

## Auteurs

Ce projet a été développé pour le RAM dans le cadre de l'informatisation de ses services de crèche.

## Licence

Tous droits réservés © RAM Crèche
