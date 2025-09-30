<?php
/**
 * FCMTokenSecurity
 * - Combines `is_active = 1` + freshness of `last_ping` (or `updated_at`) to determine availability (default 60s)
 * - Supports optional immediate detection mode via env `FCM_IMMEDIATE_DETECTION` or constructor option
 * - Returns an array compatible with the shim used by index.php
 */
require_once __DIR__ . '/../../config.php';
// Optional: use validator/helper if available
require_once __DIR__ . '/../../lib/fcm_helper.php';

class FCMTokenSecurity {
    private bool $verbose;
    // By default require a recent ping to consider a token available (in seconds)
    // Default: 60 seconds. Can be overridden via constructor option
    // or environment variable FCM_AVAILABILITY_THRESHOLD_SECONDS.
    private int $thresholdSeconds = 60;
    // If true, ignore freshness and consider any token immediately available.
    private bool $immediateDetection = true;

    public function __construct(array $options = []) {
        $this->verbose = (bool)($options['verbose'] ?? false);
        $this->thresholdSeconds = 60;
        if (isset($options['immediate_detection'])) {
            $this->immediateDetection = (bool)$options['immediate_detection'];
        }
        $envThreshold = getenv('FCM_AVAILABILITY_THRESHOLD_SECONDS');
        if ($envThreshold !== false) {
            $this->thresholdSeconds = (int)$envThreshold;
        }
        $envImmediate = getenv('FCM_IMMEDIATE_DETECTION');
        if ($envImmediate !== false) {
            $this->immediateDetection = in_array(strtolower($envImmediate), ['1', 'true', 'yes'], true);
        }
    }

    /**
     * Return availability summary.
     * will consider any token with is_active=1 as available (immediate detection).
     * @return array
     */
    public function canAcceptNewOrders(): array {
        try {
            $pdo = getDBConnection();
            $threshold = (int)$this->thresholdSeconds;
            $hasLastPing = false;
            $hasIsActive = false;

            try {
                $hasLastPing = (bool)$pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'last_ping'")->fetchColumn();
            } catch (Throwable $e) {
                $hasLastPing = false;
            }

            try {
                $hasIsActive = (bool)$pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'is_active'")->fetchColumn();
            } catch (Throwable $e) {
                $hasIsActive = false;
            }

            $timeExpr = $hasLastPing ? "COALESCE(last_ping, updated_at)" : "updated_at";

            if ($hasIsActive) {
                $activeSelect = "SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)";
                $freshSelect = "SUM(CASE WHEN is_active = 1 AND TIMESTAMPDIFF(SECOND, {$timeExpr}, NOW()) <= :threshold THEN 1 ELSE 0 END)";
                $lastActiveSelect = "MAX(CASE WHEN is_active = 1 THEN {$timeExpr} END)";
            } else {
                $activeSelect = "COUNT(*)";
                $freshSelect = "SUM(CASE WHEN TIMESTAMPDIFF(SECOND, {$timeExpr}, NOW()) <= :threshold THEN 1 ELSE 0 END)";
                $lastActiveSelect = "MAX({$timeExpr})";
            }

            $sql = "SELECT
                        {$activeSelect} AS active_count,
                        {$freshSelect} AS fresh_count,
                        {$lastActiveSelect} AS last_active_at,
                        MAX({$timeExpr}) AS last_seen_at
                    FROM device_tokens";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':threshold' => $threshold]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $activeCount = isset($row['active_count']) ? (int)$row['active_count'] : 0;
            $freshCount = isset($row['fresh_count']) ? (int)$row['fresh_count'] : 0;
            $lastActiveAt = $row['last_active_at'] ?? null;
            $lastSeenAt = $row['last_seen_at'] ?? null;

            $referenceTimestamp = $lastActiveAt ?: $lastSeenAt;
            $secondsSinceLastActive = null;
            if (!empty($referenceTimestamp)) {
                $secondsSinceLastActive = max(0, time() - (int)strtotime((string)$referenceTimestamp));
            }

            $canAccept = $this->immediateDetection ? ($activeCount > 0) : ($freshCount > 0);

            $result = [
                'can_accept_orders' => $canAccept,
                'available_coursiers' => $activeCount,
                'fresh_coursiers' => $freshCount,
                'seconds_since_last_active' => $secondsSinceLastActive,
                'last_active_at' => $lastActiveAt,
                'last_seen_at' => $lastSeenAt,
                'checked_at' => date('Y-m-d H:i:s'),
                'fallback_mode' => false,
                'detection_mode' => $this->immediateDetection ? 'immediate' : 'freshness',
                'threshold_seconds' => $this->thresholdSeconds,
            ];

            if ($this->verbose) {
                error_log('[FCMTokenSecurity] Immediate check: ' . json_encode($result));
            }

            return $result;
        } catch (Throwable $e) {
            if ($this->verbose) error_log('[FCMTokenSecurity] Error: ' . $e->getMessage());
            return [
                'can_accept_orders' => false,
                'available_coursiers' => 0,
                'error' => $e->getMessage(),
                'fallback_mode' => true,
            ];
        }
    }

    public function getUnavailabilityMessage(): string {
        if (isset($GLOBALS['commercialFallbackMessage']) && trim((string)$GLOBALS['commercialFallbackMessage']) !== '') {
            return (string)$GLOBALS['commercialFallbackMessage'];
        }
        return 'Service momentanément indisponible.';
    }
}

// If executed from CLI, output a small report
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0])) {
    $s = new FCMTokenSecurity(['verbose' => true]);
    $res = $s->canAcceptNewOrders();
    echo "FCMTokenSecurity report:\n";
    print_r($res);
}
