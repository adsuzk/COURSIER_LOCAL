<?php
/**
 * ============================================================================
 * 🔄 SYSTEME DE SYNCHRONISATION GLOBAL - SUZOSKY
 * ============================================================================
 *
 * Point centralisé pour enregistrer les battements de coeur (heartbeats)
 * des différentes composantes (index, APIs, FCM, admin) et mesurer la santé
 * de la synchronisation entre les modules critiques.
 *
 * @version 1.0.0
 * @date 25 septembre 2025
 * ============================================================================
 */

class SystemSync
{
    private const TABLE = 'system_sync_heartbeats';
    private static bool $tableChecked = false;

    /**
     * Enregistre ou met à jour le heartbeat d'un composant
     */
    public static function record(string $component, string $status = 'ok', array $metrics = []): void
    {
        try {
            $pdo = self::getPdo();
            self::ensureTable($pdo);

            $payload = json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            $stmt = $pdo->prepare("
                INSERT INTO " . self::TABLE . " (component, status, metrics_json, last_seen_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    metrics_json = VALUES(metrics_json),
                    last_seen_at = NOW()
            ");
            $stmt->execute([$component, $status, $payload]);
        } catch (Throwable $e) {
            error_log('[SystemSync] record error for component ' . $component . ': ' . $e->getMessage());
        }
    }

    /**
     * Retourne un instantané de la santé système (heartbeats + métriques)
     */
    public static function snapshot(): array
    {
        $result = [
            'components' => [],
            'metrics' => [],
            'health' => 'unknown',
            'generated_at' => date('c'),
        ];

        try {
            $pdo = self::getPdo();
            self::ensureTable($pdo);

            $components = $pdo->query('SELECT component, status, metrics_json, last_seen_at FROM ' . self::TABLE)->fetchAll(PDO::FETCH_ASSOC);
            $now = new DateTimeImmutable('now');
            $worstScore = 0;

            foreach ($components as $component) {
                $ageSeconds = 999999;
                try {
                    $lastSeen = new DateTimeImmutable($component['last_seen_at'] ?? 'now');
                    $ageSeconds = max(0, $now->getTimestamp() - $lastSeen->getTimestamp());
                } catch (Throwable $ignored) {
                }
                $metrics = json_decode($component['metrics_json'] ?? '[]', true) ?? [];
                $result['components'][$component['component']] = [
                    'status' => $component['status'],
                    'last_seen_at' => $component['last_seen_at'],
                    'age_seconds' => $ageSeconds,
                    'metrics' => $metrics,
                ];

                $score = self::scoreFromStatus($component['status'], $ageSeconds);
                if ($score > $worstScore) {
                    $worstScore = $score;
                }
            }

            $result['metrics'] = self::collectOperationalMetrics($pdo);
            $result['health'] = self::scoreToHealth($worstScore, $result['metrics']);
        } catch (Throwable $e) {
            $result['health'] = 'error';
            $result['components'] = [];
            $result['metrics'] = ['error' => $e->getMessage()];
            error_log('[SystemSync] snapshot error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Collecte des métriques opérationnelles supplémentaires
     */
    private static function collectOperationalMetrics(PDO $pdo): array
    {
        $metrics = [
            'commandes' => [
                'total' => 0,
                'by_status' => [],
                'last_update' => null,
            ],
            'fcm_tokens' => [
                'active_tokens' => 0,
                'agents' => 0,
                'stale_tokens' => 0,
            ],
            'chat' => [
                'open_conversations' => 0,
                'waiting_messages' => 0,
            ],
        ];

        // Commandes
        try {
            $metrics['commandes']['total'] = (int) $pdo->query('SELECT COUNT(*) FROM commandes')->fetchColumn();
            $byStatus = $pdo->query('SELECT statut, COUNT(*) AS total FROM commandes GROUP BY statut')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($byStatus as $row) {
                $metrics['commandes']['by_status'][$row['statut'] ?? 'inconnu'] = (int) $row['total'];
            }
            $metrics['commandes']['last_update'] = $pdo->query('SELECT MAX(updated_at) FROM commandes')->fetchColumn();
        } catch (Throwable $ignored) {
        }

        // Tokens FCM
        try {
            $activeTokens = (int) $pdo->query('SELECT COUNT(*) FROM agent_tokens WHERE is_active = 1')->fetchColumn();
            $activeAgents = (int) $pdo->query('SELECT COUNT(DISTINCT agent_id) FROM agent_tokens WHERE is_active = 1')->fetchColumn();
            $staleTokens = (int) $pdo->query("
                SELECT COUNT(*) FROM agent_tokens 
                WHERE is_active = 1 AND last_used < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ")->fetchColumn();

            if ($activeTokens === 0) {
                $totalTokens = (int) $pdo->query('SELECT COUNT(*) FROM agent_tokens')->fetchColumn();
                if ($totalTokens > 0) {
                    $activeTokens = $totalTokens;
                    $activeAgents = (int) $pdo->query('SELECT COUNT(DISTINCT agent_id) FROM agent_tokens')->fetchColumn();
                    $staleTokens = (int) $pdo->query("
                        SELECT COUNT(*) FROM agent_tokens 
                        WHERE last_used < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                    ")->fetchColumn();
                }
            }

            $metrics['fcm_tokens']['active_tokens'] = $activeTokens;
            $metrics['fcm_tokens']['agents'] = $activeAgents;
            $metrics['fcm_tokens']['stale_tokens'] = $staleTokens;
        } catch (Throwable $ignored) {
        }

        // Chat
        try {
            $metrics['chat']['open_conversations'] = (int) $pdo->query('SELECT COUNT(*) FROM chat_conversations')->fetchColumn();
            $metrics['chat']['waiting_messages'] = (int) $pdo->query('SELECT SUM(unread_count) FROM chat_conversations')->fetchColumn();
        } catch (Throwable $ignored) {
        }

        return $metrics;
    }

    private static function scoreFromStatus(string $status, int $ageSeconds): int
    {
        $normalizedStatus = strtolower($status);
        $base = match ($normalizedStatus) {
            'ok', 'healthy' => 0,
            'warning' => 1,
            'degraded' => 2,
            'error', 'critical' => 3,
            default => 2,
        };

        if ($ageSeconds > 86400 && in_array($normalizedStatus, ['ok', 'healthy'], true)) {
            return 3;
        }

        if ($ageSeconds > 1800) {
            if (in_array($normalizedStatus, ['ok', 'healthy'], true)) {
                $base = max($base, 2);
            } else {
                $base = max($base, 3);
            }
            return $base;
        }

        if ($ageSeconds > 600) {
            if (in_array($normalizedStatus, ['ok', 'healthy'], true)) {
                $base = max($base, 1);
            } else {
                $base = max($base, 2);
            }
        }

        return $base;
    }

    private static function scoreToHealth(int $score, array $metrics): string
    {
        if ($score >= 3) {
            return 'critical';
        }
        if ($score === 2) {
            return 'degraded';
        }
        if ($score === 1) {
            return 'warning';
        }

        // Vérifications supplémentaires : si beaucoup de tokens inactifs -> warning
        if (($metrics['fcm_tokens']['stale_tokens'] ?? 0) > 10) {
            return 'warning';
        }

        if (($metrics['chat']['waiting_messages'] ?? 0) > 20) {
            return 'warning';
        }

        return 'healthy';
    }

    private static function ensureTable(PDO $pdo): void
    {
        if (self::$tableChecked) {
            return;
        }

        $tableExists = $pdo->query("SHOW TABLES LIKE '" . self::TABLE . "'")->fetchColumn();
        if (!$tableExists) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
                    component VARCHAR(80) NOT NULL PRIMARY KEY,
                    status VARCHAR(32) NOT NULL DEFAULT 'ok',
                    metrics_json JSON NULL,
                    last_seen_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        self::$tableChecked = true;
    }

    private static function getPdo(): PDO
    {
        if (function_exists('getPDO')) {
            return getPDO();
        }
        if (function_exists('getDBConnection')) {
            return getDBConnection();
        }
        throw new RuntimeException('Aucune connexion PDO disponible');
    }
}
