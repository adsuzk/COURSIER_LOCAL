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
    private bool $immediateDetection = false;

    public function __construct(array $options = []) {
        $this->verbose = (bool)($options['verbose'] ?? false);
        $this->thresholdSeconds = 60;
        // immediate detection option via constructor or env var
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
            if ($this->immediateDetection) {
                $hasIsActive = false;
                try {
                    $hasIsActive = (bool)$pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'is_active'")->fetchColumn();
                } catch (Throwable $e) {
                    $hasIsActive = false;
                }

                $sql = $hasIsActive
                    ? "SELECT COUNT(*) AS cnt FROM device_tokens WHERE is_active = 1"
                    : "SELECT COUNT(*) AS cnt FROM device_tokens";
                $stmt = $pdo->query($sql);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $count = isset($row['cnt']) ? (int)$row['cnt'] : 0;
            } else {
                // Require recent last_ping (or updated_at fallback) within threshold
                // Use a safe SQL expression to compare timestamps in seconds
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
                $conditions = [];
                if ($hasIsActive) {
                    $conditions[] = 'is_active = 1';
                }
                $conditions[] = "$timeExpr IS NOT NULL";
                $conditions[] = "UNIX_TIMESTAMP($timeExpr) >= UNIX_TIMESTAMP(NOW()) - ?";
                $whereClause = implode(' AND ', $conditions);
                $sql = "SELECT COUNT(*) AS cnt FROM device_tokens WHERE $whereClause";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$threshold]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $count = isset($row['cnt']) ? (int)$row['cnt'] : 0;
            }

            $result = [
                'can_accept_orders' => $count > 0,
                'available_coursiers' => $count,
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
