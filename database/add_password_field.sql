-- Migration pour ajouter le champ password à la table clients_particuliers
-- Date: <?= date('Y-m-d H:i:s') ?>

-- Ajout du champ password si il n'existe pas
ALTER TABLE clients_particuliers 
ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL AFTER email;

-- Ajout d'un index sur email pour optimiser les connexions
ALTER TABLE clients_particuliers 
ADD INDEX IF NOT EXISTS idx_email (email);

-- Mise à jour de la table clients (copie de clients_particuliers)
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL AFTER email;

ALTER TABLE clients 
ADD INDEX IF NOT EXISTS idx_email (email);

-- Note: Les mots de passe seront hashés avec password_hash() en PHP
-- Les utilisateurs existants devront créer un mot de passe lors de leur première connexion
