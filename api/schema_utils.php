<?php
declare(strict_types=1);

if (!function_exists('findColumn')) {
    /**
     * Retourne le nom de colonne effectif (sensible à la casse) parmi une liste de candidats.
     */
    function findColumn(array $columns, array $candidates): ?string
    {
        if (!$columns || !$candidates) {
            return null;
        }
        $lookup = [];
        foreach ($columns as $name => $_) {
            $lookup[strtolower($name)] = $name;
        }
        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($lookup[$key])) {
                return $lookup[$key];
            }
        }
        return null;
    }
}

if (!function_exists('columnExpression')) {
    /**
     * Construit l'expression SQL sécurisée ("`col`" ou valeur par défaut) pour une colonne potentielle.
     */
    function columnExpression(array $columns, array $candidates, string $default = 'NULL'): string
    {
        $col = findColumn($columns, $candidates);
        return $col ? sprintf('`%s`', $col) : $default;
    }
}

if (!function_exists('ensureCommandesStructure')) {
    /**
     * Garantit la présence des colonnes requises dans la table commandes et renvoie sa structure.
     */
    function ensureCommandesStructure(PDO $pdo): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $exists = $pdo->query("SHOW TABLES LIKE 'commandes'");
            if (!$exists || $exists->rowCount() === 0) {
                return $cache = [];
            }
        } catch (Throwable $e) {
            return $cache = [];
        }

        $columns = [];
        try {
            $res = $pdo->query('SHOW COLUMNS FROM commandes');
            foreach ($res as $row) {
                $columns[$row['Field']] = $row;
            }
        } catch (Throwable $e) {
            return $cache = [];
        }

        // Colonne coursier_id
        if (!isset($columns['coursier_id'])) {
            try { $pdo->exec('ALTER TABLE commandes ADD COLUMN coursier_id INT NULL AFTER client_id'); } catch (Throwable $e) {}
            try { $pdo->exec('ALTER TABLE commandes ADD INDEX idx_coursier_id (coursier_id)'); } catch (Throwable $e) {}
        }

        // Colonne statut -> VARCHAR(32)
        if (!isset($columns['statut'])) {
            try { $pdo->exec("ALTER TABLE commandes ADD COLUMN statut VARCHAR(32) DEFAULT 'nouvelle'"); } catch (Throwable $e) {}
        } else {
            $type = strtolower((string)($columns['statut']['Type'] ?? ''));
            if (strpos($type, 'enum') !== false) {
                try { $pdo->exec("ALTER TABLE commandes MODIFY statut VARCHAR(32) DEFAULT 'nouvelle'"); } catch (Throwable $e) {}
            }
        }

        // Colonne heure_acceptation
        if (!isset($columns['heure_acceptation'])) {
            try { $pdo->exec('ALTER TABLE commandes ADD COLUMN heure_acceptation DATETIME NULL AFTER statut'); } catch (Throwable $e) {}
        }

        // Colonne updated_at
        if (!isset($columns['updated_at'])) {
            try { $pdo->exec('ALTER TABLE commandes ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'); } catch (Throwable $e) {}
        }

        // Colonnes géolocalisation pickup/delivery
        $geoAlters = [
            'latitude_depart' => 'ALTER TABLE commandes ADD COLUMN latitude_depart DECIMAL(10,7) NULL AFTER adresse_depart',
            'longitude_depart' => 'ALTER TABLE commandes ADD COLUMN longitude_depart DECIMAL(10,7) NULL AFTER latitude_depart',
            'latitude_arrivee' => 'ALTER TABLE commandes ADD COLUMN latitude_arrivee DECIMAL(10,7) NULL AFTER adresse_arrivee',
            'longitude_arrivee' => 'ALTER TABLE commandes ADD COLUMN longitude_arrivee DECIMAL(10,7) NULL AFTER latitude_arrivee',
        ];
        foreach ($geoAlters as $field => $ddl) {
            if (!isset($columns[$field])) {
                try { $pdo->exec($ddl); } catch (Throwable $e) {}
            }
        }

        // Rafraîchir la structure après éventuelles modifications
        try {
            $columns = [];
            $res = $pdo->query('SHOW COLUMNS FROM commandes');
            foreach ($res as $row) {
                $columns[$row['Field']] = $row;
            }
        } catch (Throwable $e) {
            // ignore
        }

        return $cache = $columns;
    }
}

if (!function_exists('ensureCommandesClassiquesStructure')) {
    /**
     * Garantit la structure minimale de commandes_classiques (si elle existe) et renvoie ses colonnes.
     */
    function ensureCommandesClassiquesStructure(PDO $pdo): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $exists = $pdo->query("SHOW TABLES LIKE 'commandes_classiques'");
            if (!$exists || $exists->rowCount() === 0) {
                return $cache = [];
            }
        } catch (Throwable $e) {
            return $cache = [];
        }

        $columns = [];
        try {
            $res = $pdo->query('SHOW COLUMNS FROM commandes_classiques');
            foreach ($res as $row) {
                $columns[$row['Field']] = $row;
            }
        } catch (Throwable $e) {
            return $cache = [];
        }

        if (!isset($columns['statut'])) {
            try { $pdo->exec("ALTER TABLE commandes_classiques ADD COLUMN statut VARCHAR(32) DEFAULT 'nouvelle'"); } catch (Throwable $e) {}
        } else {
            $type = strtolower((string)($columns['statut']['Type'] ?? ''));
            if (strpos($type, 'enum') !== false) {
                try { $pdo->exec("ALTER TABLE commandes_classiques MODIFY statut VARCHAR(32) DEFAULT 'nouvelle'"); } catch (Throwable $e) {}
            }
        }

        if (!isset($columns['updated_at'])) {
            try { $pdo->exec('ALTER TABLE commandes_classiques ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'); } catch (Throwable $e) {}
        }

        $geoAlters = [
            'latitude_enlevement' => 'ALTER TABLE commandes_classiques ADD COLUMN latitude_enlevement DECIMAL(10,7) NULL AFTER adresse_enlevement',
            'longitude_enlevement' => 'ALTER TABLE commandes_classiques ADD COLUMN longitude_enlevement DECIMAL(10,7) NULL AFTER latitude_enlevement',
            'latitude_livraison' => 'ALTER TABLE commandes_classiques ADD COLUMN latitude_livraison DECIMAL(10,7) NULL AFTER adresse_livraison',
            'longitude_livraison' => 'ALTER TABLE commandes_classiques ADD COLUMN longitude_livraison DECIMAL(10,7) NULL AFTER latitude_livraison',
        ];
        foreach ($geoAlters as $field => $ddl) {
            if (!isset($columns[$field])) {
                try { $pdo->exec($ddl); } catch (Throwable $e) {}
            }
        }

        try {
            $columns = [];
            $res = $pdo->query('SHOW COLUMNS FROM commandes_classiques');
            foreach ($res as $row) {
                $columns[$row['Field']] = $row;
            }
        } catch (Throwable $e) {
            // ignore
        }

        return $cache = $columns;
    }
}

if (!function_exists('commandeCoordinateExpressions')) {
    /**
     * Retourne les expressions SQL pour les colonnes de coordonnées (pickup/dropoff) dans commandes.
     */
    function commandeCoordinateExpressions(PDO $pdo): array
    {
        $columns = ensureCommandesStructure($pdo);
        if (!$columns) {
            return [
                'pickup_lat' => 'NULL',
                'pickup_lng' => 'NULL',
                'drop_lat' => 'NULL',
                'drop_lng' => 'NULL',
            ];
        }

        return [
            'pickup_lat' => columnExpression($columns, [
                'latitude_depart', 'lat_depart', 'latitude_retrait', 'lat_retrait',
                'pickup_lat', 'pickup_latitude', 'latitude_enlevement', 'lat_enlevement'
            ]),
            'pickup_lng' => columnExpression($columns, [
                'longitude_depart', 'lng_depart', 'longitude_retrait', 'lng_retrait',
                'pickup_lng', 'pickup_longitude', 'longitude_enlevement', 'lng_enlevement'
            ]),
            'drop_lat' => columnExpression($columns, [
                'latitude_arrivee', 'lat_arrivee', 'latitude_livraison', 'lat_livraison',
                'drop_lat', 'drop_latitude', 'latitude_destination', 'lat_destination'
            ]),
            'drop_lng' => columnExpression($columns, [
                'longitude_arrivee', 'lng_arrivee', 'longitude_livraison', 'lng_livraison',
                'drop_lng', 'drop_longitude', 'longitude_destination', 'lng_destination'
            ]),
        ];
    }
}

if (!function_exists('syncCommandeStatus')) {
    /**
     * Synchronise le statut (et méta) dans la table commandes après mise à jour côté commandes_classiques.
     */
    function syncCommandeStatus(PDO $pdo, int $commandeId, string $statut, ?int $coursierId = null): void
    {
        $columns = ensureCommandesStructure($pdo);
        if (!$columns) {
            return;
        }

        $statutCol = findColumn($columns, ['statut']);
        if (!$statutCol) {
            return;
        }

        $sets = [sprintf('`%s` = :statut', $statutCol)];
        $params = [
            'statut' => $statut,
            'id' => $commandeId,
        ];

        if ($coursierId && ($coursierCol = findColumn($columns, ['coursier_id', 'id_coursier', 'livreur_id']))) {
            $sets[] = sprintf('`%s` = :coursier_id', $coursierCol);
            $params['coursier_id'] = $coursierId;
        }

        if ($updatedAt = findColumn($columns, ['updated_at', 'date_modification', 'modified_at', 'date_update'])) {
            $sets[] = sprintf('`%s` = NOW()', $updatedAt);
        }

        if ($statut === 'acceptee' && ($acceptCol = findColumn($columns, ['heure_acceptation', 'date_acceptation', 'accepted_at']))) {
            $sets[] = sprintf('`%s` = COALESCE(`%s`, NOW())', $acceptCol, $acceptCol);
        }

        if ($statut === 'picked_up' && ($pickupCol = findColumn($columns, ['pickup_time', 'date_ramassage', 'date_retrait', 'heure_retrait']))) {
            $sets[] = sprintf('`%s` = COALESCE(`%s`, NOW())', $pickupCol, $pickupCol);
        }

        if ($statut === 'livree' && ($deliveredCol = findColumn($columns, ['delivered_time', 'date_livraison', 'heure_livraison', 'date_livraison_reelle']))) {
            $sets[] = sprintf('`%s` = COALESCE(`%s`, NOW())', $deliveredCol, $deliveredCol);
        }

        $sql = sprintf('UPDATE commandes SET %s WHERE id = :id', implode(', ', $sets));
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable $e) {
            // La synchronisation ne doit pas bloquer le flux principal.
        }
    }
}
