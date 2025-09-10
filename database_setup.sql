-- Script de création des tables pour le système de coursier Suzosky
-- Tables nécessaires pour l'intégration index.html -> admin.php

-- Table des clients particuliers (utilisée par admin/clients.php)
CREATE TABLE IF NOT EXISTS clients_particuliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenoms VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(150) NULL,
    ville VARCHAR(100) NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_derniere_commande TIMESTAMP NULL,
    statut ENUM('actif', 'inactif') DEFAULT 'actif',
    INDEX idx_telephone (telephone),
    INDEX idx_date_creation (date_creation)
);
-- Table "clients" pour compatibilité avec contraintes existantes
CREATE TABLE IF NOT EXISTS clients LIKE clients_particuliers;
INSERT IGNORE INTO clients SELECT * FROM clients_particuliers;

-- Table des commandes (version simplifiée)
CREATE TABLE IF NOT EXISTS commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    expediteur_id INT NULL,
    destinataire_id INT NULL,
    adresse_depart TEXT NOT NULL,
    adresse_arrivee TEXT NOT NULL,
    telephone_expediteur VARCHAR(20) NOT NULL,
    telephone_destinataire VARCHAR(20) NOT NULL,
    description_colis TEXT NULL,
    priorite ENUM('normale', 'urgente', 'express') DEFAULT 'normale',
    mode_paiement ENUM('cash', 'orange_money', 'mtn_money', 'moov_money', 'wave', 'card') DEFAULT 'cash',
    prix_estime DECIMAL(10,2) NOT NULL,
    distance_km VARCHAR(50) NULL,
    duree_estimee VARCHAR(50) NULL,
    statut ENUM('nouvelle', 'assignee', 'en_cours', 'livree', 'annulee') DEFAULT 'nouvelle',
    paiement_confirme TINYINT(1) DEFAULT 0,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_numero_commande (numero_commande),
    INDEX idx_client (client_id),
    INDEX idx_statut (statut),
    INDEX idx_paiement (paiement_confirme),
    INDEX idx_date_creation (date_creation),
    FOREIGN KEY (client_id) REFERENCES clients_particuliers(id) ON DELETE CASCADE,
    FOREIGN KEY (expediteur_id) REFERENCES clients_particuliers(id) ON DELETE SET NULL,
    FOREIGN KEY (destinataire_id) REFERENCES clients_particuliers(id) ON DELETE SET NULL
);

-- Table des logs d'activités
CREATE TABLE IF NOT EXISTS logs_activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_activite VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    user_id INT NULL,
    date_activite TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type_activite),
    INDEX idx_date (date_activite)
);

-- Table des clients business (déjà existante probablement)
CREATE TABLE IF NOT EXISTS business_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_business INT UNIQUE NULL,
    nom_entreprise VARCHAR(200) NOT NULL,
    contact_nom VARCHAR(100) NOT NULL,
    contact_email VARCHAR(150) NOT NULL,
    contact_telephone VARCHAR(20) NULL,
    secteur_activite VARCHAR(100) NULL,
    adresse TEXT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_upload TIMESTAMP NULL,
    statut ENUM('actif', 'inactif', 'en_attente') DEFAULT 'actif',
    INDEX idx_nom_entreprise (nom_entreprise),
    INDEX idx_email (contact_email),
    INDEX idx_date_creation (date_creation)
);

-- Insertion de données de test pour vérifier le fonctionnement
INSERT IGNORE INTO clients_particuliers (nom, prenoms, telephone, email) VALUES
('Doe', 'John', '+225 07 12 34 56 78', 'john.doe@example.com'),
('Kouassi', 'Marie', '+225 05 98 76 54 32', 'marie.kouassi@example.com'),
('Test', 'Client', '+225 01 23 45 67 89', 'client.test@example.com');

-- Index optimisés pour les performances
ALTER TABLE commandes ADD INDEX idx_mode_paiement (mode_paiement);
-- Removed duplicate index additions to avoid errors
-- ALTER TABLE commandes ADD INDEX idx_priorite (priorite);
-- ALTER TABLE clients_particuliers ADD INDEX idx_statut (statut);
