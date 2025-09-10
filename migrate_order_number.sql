-- Migration pour renommer la colonne "numero_commande" en "order_number"
ALTER TABLE `commandes`
  ADD COLUMN `order_number` VARCHAR(50) UNIQUE NOT NULL AFTER `id`;

UPDATE `commandes` SET `order_number` = `numero_commande`;

ALTER TABLE `commandes`
  DROP COLUMN `numero_commande`;
