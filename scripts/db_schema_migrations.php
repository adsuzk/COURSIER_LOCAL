<?php
declare(strict_types=1);

/**
 * MIGRATIONS AUTO-GÉNÉRÉES
 * Fichier mis à jour automatiquement le 2025-09-28 05:49:50
 * Détection automatique des changements de structure
 */

return array (
  0 => 
  array (
    'id' => '2024_10_05_001_device_tokens_structure',
    'description' => 'Sécurise la structure critique de device_tokens (colonnes + normalisation).',
    'steps' => 
    array (
      0 => 
      array (
        'type' => 'ensureColumn',
        'table' => 'device_tokens',
        'column' => 'device_type',
        'definition' => 'VARCHAR(50) NOT NULL DEFAULT \'mobile\' AFTER `token`',
        'onlyIf' => 
        array (
          'tableExists' => 'device_tokens',
        ),
      ),
      1 => 
      array (
        'type' => 'ensureColumn',
        'table' => 'device_tokens',
        'column' => 'is_active',
        'definition' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `device_type`',
        'onlyIf' => 
        array (
          'tableExists' => 'device_tokens',
        ),
      ),
      2 => 
      array (
        'type' => 'ensureColumn',
        'table' => 'device_tokens',
        'column' => 'device_info',
        'definition' => 'TEXT NULL AFTER `is_active`',
        'onlyIf' => 
        array (
          'tableExists' => 'device_tokens',
        ),
      ),
      3 => 
      array (
        'type' => 'ensureColumn',
        'table' => 'device_tokens',
        'column' => 'last_ping',
        'definition' => 'TIMESTAMP NULL DEFAULT NULL AFTER `device_info`',
        'onlyIf' => 
        array (
          'tableExists' => 'device_tokens',
        ),
      ),
      4 => 
      array (
        'type' => 'runSql',
        'label' => 'Normalisation valeurs NULL',
        'sql' => 'UPDATE device_tokens SET is_active = 1, device_type = COALESCE(device_type, \'mobile\'), last_ping = COALESCE(last_ping, NOW()) WHERE is_active IS NULL OR device_type IS NULL OR last_ping IS NULL',
        'onlyIf' => 
        array (
          'tableExists' => 'device_tokens',
        ),
      ),
    ),
  ),
  1 => 
  array (
    'id' => '2024_10_05_002_device_tokens_indexes',
    'description' => 'Ajoute les index critiques sur device_tokens pour la sécurité et la vitesse.',
    'steps' => 
    array (
      0 => 
      array (
        'type' => 'ensureIndex',
        'table' => 'device_tokens',
        'index' => 'idx_device_tokens_coursier_active',
        'columns' => 
        array (
          0 => 'coursier_id',
          1 => 'is_active',
        ),
        'onlyIf' => 
        array (
          'tableExists' => 'device_tokens',
        ),
      ),
      1 => 
      array (
        'type' => 'ensureIndex',
        'table' => 'device_tokens',
        'index' => 'idx_device_tokens_last_ping',
        'columns' => 
        array (
          0 => 'last_ping',
        ),
        'onlyIf' => 
        array (
          'tableExists' => 'device_tokens',
        ),
      ),
    ),
  ),
  2 => 
  array (
    'id' => '2024_10_05_003_agents_connectivity_safety',
    'description' => 'Garantit les champs de suivi de connexion des coursiers.',
    'steps' => 
    array (
      0 => 
      array (
        'type' => 'ensureColumn',
        'table' => 'agents_suzosky',
        'column' => 'current_session_token',
        'definition' => 'VARCHAR(255) NULL DEFAULT NULL AFTER `statut_connexion`',
        'onlyIf' => 
        array (
          'tableExists' => 'agents_suzosky',
        ),
      ),
      1 => 
      array (
        'type' => 'ensureColumn',
        'table' => 'agents_suzosky',
        'column' => 'last_login_at',
        'definition' => 'DATETIME NULL DEFAULT NULL AFTER `current_session_token`',
        'onlyIf' => 
        array (
          'tableExists' => 'agents_suzosky',
        ),
      ),
      2 => 
      array (
        'type' => 'ensureColumn',
        'table' => 'agents_suzosky',
        'column' => 'last_logout_at',
        'definition' => 'DATETIME NULL DEFAULT NULL AFTER `last_login_at`',
        'onlyIf' => 
        array (
          'tableExists' => 'agents_suzosky',
        ),
      ),
      3 => 
      array (
        'type' => 'ensureIndex',
        'table' => 'agents_suzosky',
        'index' => 'idx_agents_statut_connexion',
        'columns' => 
        array (
          0 => 'statut_connexion',
        ),
        'onlyIf' => 
        array (
          'tableExists' => 'agents_suzosky',
        ),
      ),
      4 => 
      array (
        'type' => 'runSql',
        'label' => 'Normalisation statut connexion',
        'sql' => 'UPDATE agents_suzosky SET statut_connexion = COALESCE(statut_connexion, \'hors_ligne\') WHERE statut_connexion IS NULL',
        'onlyIf' => 
        array (
          'tableExists' => 'agents_suzosky',
        ),
      ),
    ),
  ),
  3 => 
  array (
    'id' => '2025_09_28_054950_auto_sync',
    'description' => 'Synchronisation automatique - 1 changements détectés',
    'steps' => 
    array (
      0 => 
      array (
        'type' => 'runSql',
        'label' => 'Création automatique de la table test_auto_migration',
        'sql' => 'SELECT \'Table test_auto_migration détectée lors de la synchronisation\' AS info',
        'onlyIf' => 
        array (
          'tableExists' => 'test_auto_migration',
        ),
      ),
    ),
  ),
);
