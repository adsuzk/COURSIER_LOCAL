<?php
declare(strict_types=1);

/**
 * Déclarations de migrations pour l'automate de mise à jour SQL.
 * Chaque migration est décrite via un tableau associatif contenant :
 *   - id (string) identifiant unique, ordre assuré par tri alphabétique
 *   - description (string) résumé humain de l'évolution
 *   - steps (array) liste ordonnée des opérations à exécuter
 *
 * Types de step pris en charge :
 *   • ensureTable   : crée la table si elle n'existe pas (requires table & createStatement)
 *   • ensureColumn  : ajoute une colonne si absente (requires table, column, definition)
 *   • ensureIndex   : ajoute un index si absent (requires table, index, columns[], unique=false)
 *   • runSql        : exécute une requête brute (requires sql, optionnels skipIf/onlyIf)
 *
 * Les conditions skipIf/onlyIf acceptent les clés suivantes :
 *   - tableExists => 'nom_table'
 *   - columnExists => ['table' => 'nom', 'column' => 'colonne']
 *   - indexExists => ['table' => 'nom', 'index' => 'index_name']
 *
 * Toutes les requêtes doivent être idempotentes pour éviter les effets de bord.
 */

return [
    [
        'id' => '2024_10_05_001_device_tokens_structure',
        'description' => 'Sécurise la structure critique de device_tokens (colonnes + normalisation).',
        'steps' => [
            [
                'type' => 'ensureColumn',
                'table' => 'device_tokens',
                'column' => 'device_type',
                'definition' => "VARCHAR(50) NOT NULL DEFAULT 'mobile' AFTER `token`",
            ],
            [
                'type' => 'ensureColumn',
                'table' => 'device_tokens',
                'column' => 'is_active',
                'definition' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `device_type`",
            ],
            [
                'type' => 'ensureColumn',
                'table' => 'device_tokens',
                'column' => 'device_info',
                'definition' => 'TEXT NULL AFTER `is_active`',
            ],
            [
                'type' => 'ensureColumn',
                'table' => 'device_tokens',
                'column' => 'last_ping',
                'definition' => 'TIMESTAMP NULL DEFAULT NULL AFTER `device_info`',
            ],
            [
                'type' => 'runSql',
                'label' => 'Normalisation valeurs NULL',
                'sql' => "UPDATE device_tokens SET is_active = 1, device_type = COALESCE(device_type, 'mobile'), last_ping = COALESCE(last_ping, NOW()) WHERE is_active IS NULL OR device_type IS NULL OR last_ping IS NULL",
            ],
        ],
    ],
    [
        'id' => '2024_10_05_002_device_tokens_indexes',
        'description' => 'Ajoute les index critiques sur device_tokens pour la sécurité et la vitesse.',
        'steps' => [
            [
                'type' => 'ensureIndex',
                'table' => 'device_tokens',
                'index' => 'idx_device_tokens_coursier_active',
                'columns' => ['coursier_id', 'is_active'],
            ],
            [
                'type' => 'ensureIndex',
                'table' => 'device_tokens',
                'index' => 'idx_device_tokens_last_ping',
                'columns' => ['last_ping'],
            ],
        ],
    ],
    [
        'id' => '2024_10_05_003_agents_connectivity_safety',
        'description' => 'Garantit les champs de suivi de connexion des coursiers.',
        'steps' => [
            [
                'type' => 'ensureColumn',
                'table' => 'agents_suzosky',
                'column' => 'current_session_token',
                'definition' => 'VARCHAR(255) NULL DEFAULT NULL AFTER `statut_connexion`',
            ],
            [
                'type' => 'ensureColumn',
                'table' => 'agents_suzosky',
                'column' => 'last_login_at',
                'definition' => 'DATETIME NULL DEFAULT NULL AFTER `current_session_token`',
            ],
            [
                'type' => 'ensureColumn',
                'table' => 'agents_suzosky',
                'column' => 'last_logout_at',
                'definition' => 'DATETIME NULL DEFAULT NULL AFTER `last_login_at`',
            ],
            [
                'type' => 'ensureIndex',
                'table' => 'agents_suzosky',
                'index' => 'idx_agents_statut_connexion',
                'columns' => ['statut_connexion'],
            ],
            [
                'type' => 'runSql',
                'label' => 'Normalisation statut connexion',
                'sql' => "UPDATE agents_suzosky SET statut_connexion = COALESCE(statut_connexion, 'hors_ligne') WHERE statut_connexion IS NULL",
            ],
        ],
    ],
];
