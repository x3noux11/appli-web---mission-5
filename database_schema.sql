-- Schema de base de données pour le système de gestion de crèche RAM

-- Table des utilisateurs (parents et personnel de la crèche)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    adresse TEXT,
    role ENUM('admin', 'personnel', 'parent') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des enfants
CREATE TABLE enfants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE NOT NULL,
    informations_medicales TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table de liaison parents-enfants
CREATE TABLE parents_enfants (
    parent_id INT,
    enfant_id INT,
    relation ENUM('pere', 'mere', 'tuteur') NOT NULL,
    PRIMARY KEY (parent_id, enfant_id),
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (enfant_id) REFERENCES enfants(id) ON DELETE CASCADE
);

-- Table des groupes d'âge
CREATE TABLE groupes_age (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    age_min INT NOT NULL, -- âge minimum en mois
    age_max INT NOT NULL, -- âge maximum en mois
    capacite INT NOT NULL -- nombre maximum d'enfants
);

-- Table des créneaux horaires disponibles
CREATE TABLE creneaux (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jour_semaine ENUM('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    groupe_age_id INT,
    places_disponibles INT NOT NULL,
    FOREIGN KEY (groupe_age_id) REFERENCES groupes_age(id)
);

-- Table des inscriptions régulières (annuelles)
CREATE TABLE inscriptions_regulieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enfant_id INT,
    creneau_id INT,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('active', 'inactive', 'terminee') DEFAULT 'active',
    FOREIGN KEY (enfant_id) REFERENCES enfants(id) ON DELETE CASCADE,
    FOREIGN KEY (creneau_id) REFERENCES creneaux(id)
);

-- Table des inscriptions occasionnelles
CREATE TABLE inscriptions_occasionnelles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enfant_id INT,
    date_garde DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    groupe_age_id INT,
    statut ENUM('demande', 'confirmee', 'refusee', 'terminee') DEFAULT 'demande',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enfant_id) REFERENCES enfants(id) ON DELETE CASCADE,
    FOREIGN KEY (groupe_age_id) REFERENCES groupes_age(id)
);

-- Table de suivi de présence (pour la facturation future)
CREATE TABLE presences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enfant_id INT,
    date DATE NOT NULL,
    heure_arrivee TIME,
    heure_depart TIME,
    type ENUM('regulier', 'occasionnel') NOT NULL,
    inscription_reguliere_id INT NULL,
    inscription_occasionnelle_id INT NULL,
    FOREIGN KEY (enfant_id) REFERENCES enfants(id) ON DELETE CASCADE,
    FOREIGN KEY (inscription_reguliere_id) REFERENCES inscriptions_regulieres(id) ON DELETE SET NULL,
    FOREIGN KEY (inscription_occasionnelle_id) REFERENCES inscriptions_occasionnelles(id) ON DELETE SET NULL
);

-- Table des paramètres de l'application
CREATE TABLE parametres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT NOT NULL,
    description TEXT
);

-- Insertion des données initiales
INSERT INTO parametres (cle, valeur, description)
VALUES 
('delai_inscription_min', '24', 'Délai minimum en heures pour les inscriptions occasionnelles'),
('heures_ouverture', '08:00', 'Heure d\'ouverture de la crèche'),
('heures_fermeture', '18:30', 'Heure de fermeture de la crèche');

-- Insertion des groupes d'âge par défaut
INSERT INTO groupes_age (nom, age_min, age_max, capacite)
VALUES 
('Bébés', 3, 12, 10),
('Trotteurs', 13, 24, 15),
('Grands', 25, 36, 20);
