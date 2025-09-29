<?php
/**
 * Minimal implementation of FCMTokenSecurity
 * - Immediate detection: any device_tokens row with is_active = 1 is considered available
 * - Returns an array compatible with the shim used by index.php
 * - Safe, minimal, and suitable as a replacement when the original implementation is missing
 */
require_once __DIR__ . '/../../config.php';

class FCMTokenSecurity {
    private bool $verbose;
    // Threshold is not used for immediate detection, but kept for compatibility
    private int $thresholdSeconds = 2;

    public function __construct(array $options = []) {
        $this->verbose = (bool)($options['verbose'] ?? false);
        if (isset($options['threshold_seconds'])) {
            $this->thresholdSeconds = (int)$options['threshold_seconds'];
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
            $sql = "SELECT COUNT(*) AS cnt FROM device_tokens WHERE is_active = 1";
            $stmt = $pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = isset($row['cnt']) ? (int)$row['cnt'] : 0;

            $result = [
                'can_accept_orders' => $count > 0,
                'available_coursiers' => $count,
                'checked_at' => date('Y-m-d H:i:s'),
                'fallback_mode' => false,
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
