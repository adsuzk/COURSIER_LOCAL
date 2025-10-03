<?php
/**
 * Fichier de compatibilité : redirige les anciens appels vers la nouvelle arborescence
 * Les scripts FCM résident désormais dans Scripts/Scripts cron
 *
 * Améliorations:
 *  - Recherche plusieurs chemins candidats (variantes avec/sans espace/underscore)
 *  - Si aucun trouvé, fournit un fallback fonctionnel (calcule la disponibilité via DB)
 */

$candidates = [
    __DIR__ . '/Scripts/Scripts cron/fcm_token_security.php',
    __DIR__ . '/Scripts/Scripts_cron/fcm_token_security.php',
    __DIR__ . '/Scripts/scripts_cron/fcm_token_security.php',
    __DIR__ . '/Scripts/scripts-cron/fcm_token_security.php',
    __DIR__ . '/Scripts/fcm_token_security.php',
];

$loaded = false;
foreach ($candidates as $p) {
    if (file_exists($p)) {
        require_once $p;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    // Fallback si le fichier cible n'existe pas (déploiement incomplet)
    error_log('[COMPAT] fcm_token_security.php redirect failed - targets missing. Tried: ' . implode(' | ', $candidates));

    if (!class_exists('FCMTokenSecurity')) {
        require_once __DIR__ . '/config.php';
        /**
         * Fallback fonctionnel: reproduit la logique essentielle de disponibilité
         * - seuil de fraicheur par défaut 60s (env FCM_AVAILABILITY_THRESHOLD_SECONDS)
         * - mode immédiat via env FCM_IMMEDIATE_DETECTION
         */
        class FCMTokenSecurity {
            private bool $verbose;
            private int $thresholdSeconds = 60;
            private bool $immediateDetection = false;

            public function __construct(array $options = []) {
                $this->verbose = (bool)($options['verbose'] ?? false);
                $envThreshold = getenv('FCM_AVAILABILITY_THRESHOLD_SECONDS');
                if ($envThreshold !== false && is_numeric($envThreshold)) {
                    $this->thresholdSeconds = (int)$envThreshold;
                }
                if (isset($options['immediate_detection'])) {
                    $this->immediateDetection = (bool)$options['immediate_detection'];
                }
                $envImmediate = getenv('FCM_IMMEDIATE_DETECTION');
                if ($envImmediate !== false) {
                    $this->immediateDetection = in_array(strtolower($envImmediate), ['1','true','yes'], true);
                }
            }

            public function enforceTokenSecurity(): array {
                return [
                    'tokens_disabled' => 0,
                    'sessions_cleaned' => 0,
                    'security_violations' => [],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'fallback_mode' => true
                ];
            }

            public function canAcceptNewOrders(): array {
                try {
                    $pdo = getDBConnection();
                    $threshold = $this->thresholdSeconds;
                    $hasLastPing = false; $hasIsActive = false;
                    try { $hasLastPing = (bool)$pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'last_ping'")->fetchColumn(); } catch (\Throwable $e) {}
                    try { $hasIsActive = (bool)$pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'is_active'")->fetchColumn(); } catch (\Throwable $e) {}
                    $timeExpr = $hasLastPing ? "COALESCE(last_ping, updated_at)" : "updated_at";
                    if ($hasIsActive) {
                        $activeSelect = "SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END)";
                        $freshSelect = "SUM(CASE WHEN is_active=1 AND TIMESTAMPDIFF(SECOND, {$timeExpr}, NOW()) <= :t THEN 1 ELSE 0 END)";
                        $lastActiveSelect = "MAX(CASE WHEN is_active=1 THEN {$timeExpr} END)";
                    } else {
                        $activeSelect = "COUNT(*)";
                        $freshSelect = "SUM(CASE WHEN TIMESTAMPDIFF(SECOND, {$timeExpr}, NOW()) <= :t THEN 1 ELSE 0 END)";
                        $lastActiveSelect = "MAX({$timeExpr})";
                    }
                    $sql = "SELECT {$activeSelect} active_count, {$freshSelect} fresh_count, {$lastActiveSelect} last_active_at, MAX({$timeExpr}) last_seen_at FROM device_tokens";
                    $st = $pdo->prepare($sql);
                    $st->execute([':t' => $threshold]);
                    $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $active = (int)($row['active_count'] ?? 0);
                    $fresh = (int)($row['fresh_count'] ?? 0);
                    $la = $row['last_active_at'] ?? $row['last_seen_at'] ?? null;
                    $since = null;
                    if ($la) {
                        try {
                            $sd = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, :x, NOW())');
                            $sd->execute([':x' => $la]);
                            $since = (int)$sd->fetchColumn();
                        } catch (\Throwable $e) { $since = null; }
                    }
                    $can = $this->immediateDetection ? ($active > 0) : ($fresh > 0);
                    return [
                        'can_accept_orders' => $can,
                        'available_coursiers' => $active,
                        'fresh_coursiers' => $fresh,
                        'seconds_since_last_active' => $since,
                        'last_active_at' => $la,
                        'last_seen_at' => $row['last_seen_at'] ?? null,
                        'checked_at' => date('Y-m-d H:i:s'),
                        'fallback_mode' => true,
                        'detection_mode' => $this->immediateDetection ? 'immediate' : 'freshness',
                        'threshold_seconds' => $this->thresholdSeconds,
                    ];
                } catch (\Throwable $e) {
                    return [
                        'can_accept_orders' => false,
                        'available_coursiers' => 0,
                        'error' => $e->getMessage(),
                        'fallback_mode' => true,
                    ];
                }
            }

            public function getUnavailabilityMessage(): string {
                return 'Nos coursiers sont actuellement très sollicités. Restez sur cette page — des coursiers se libèrent dans un instant et le formulaire se rouvrira automatiquement pour vous permettre de commander immédiatement. Merci pour votre patience !';
            }
        }
    }
}