<?php
// api/get_coursier_availability.php
// Renvoie si des coursiers (device tokens actifs) sont disponibles.
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();

    $thresholdSecondsEnv = getenv('FCM_AVAILABILITY_THRESHOLD_SECONDS');
    $thresholdSeconds = ($thresholdSecondsEnv !== false && is_numeric($thresholdSecondsEnv)) ? (int)$thresholdSecondsEnv : 60;
    if ($thresholdSeconds <= 0) {
        $thresholdSeconds = 60;
    }
    $lockDelaySeconds = $thresholdSeconds;

    $tokensData = null;
    $activeCount = 0;
    $freshCount = 0;
    $available = false;
    $message = '';
    $secondsSinceLastActive = null;
    $lastActiveAt = null;
    $dataSource = 'device_tokens';

    $tableExists = false;
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'device_tokens'");
        $tableExists = $tableCheck && $tableCheck->fetchColumn();
    } catch (Throwable $e) {
        $tableExists = false;
    }

    if ($tableExists) {
        try {
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
            $stmt->execute([':threshold' => $thresholdSeconds]);
            $tokensData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'active_count' => 0,
                'fresh_count' => 0,
                'last_active_at' => null,
                'last_seen_at' => null,
            ];
        } catch (Throwable $e) {
            // If the query fails, fall back to agents table to avoid a hard error.
            $tokensData = null;
        }
    }

    if ($tokensData !== null) {
        $activeCount = (int)($tokensData['active_count'] ?? 0);
        $freshCount = (int)($tokensData['fresh_count'] ?? 0);
        $lastActiveAt = $tokensData['last_active_at'] ?? null;
        $lastSeenAt = $tokensData['last_seen_at'] ?? null;

        $referenceTimestamp = $lastActiveAt ?: $lastSeenAt;
        if (!empty($referenceTimestamp)) {
            $secondsSinceLastActive = max(0, time() - (int)strtotime((string)$referenceTimestamp));
        }

        if ($freshCount > 0) {
            $available = true;
            $message = 'Coursiers disponibles';
        } elseif ($activeCount > 0) {
            $available = false;
            $message = 'Coursiers connectés, synchronisation en cours.';
        } else {
            $available = false;
            if ($secondsSinceLastActive !== null) {
                if ($secondsSinceLastActive < 120) {
                    $message = 'Nos coursiers se reconnectent dans un instant.';
                } else {
                    $minutes = max(1, (int)floor($secondsSinceLastActive / 60));
                    $message = "Aucun coursier actif pour le moment (dernier coursier vu il y a {$minutes} min).";
                }
            } else {
                $message = 'Aucun coursier actif pour le moment.';
            }
        }
    } else {
        // device_tokens table absent or unreadable: fallback to agents table
        $dataSource = 'agents_suzosky';
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
            $stmt->execute();
            $agentsOnline = (int)$stmt->fetchColumn();
            if ($agentsOnline > 0) {
                $available = true;
                $message = 'Coursiers en ligne (fallback agents).';
                $activeCount = $agentsOnline;
            } else {
                $available = false;
                $message = 'Aucun coursier en ligne.';
            }
        } catch (Throwable $e) {
            $available = false;
            $message = 'Échec lecture disponibilité.';
            $dataSource = 'unavailable';
        }
    }

    echo json_encode([
        'success' => true,
        'available' => $available,
        'message' => $message,
        'active_count' => $activeCount,
        'fresh_count' => $freshCount,
        'seconds_since_last_active' => $secondsSinceLastActive,
        'last_active_at' => $lastActiveAt,
        'lock_delay_seconds' => $lockDelaySeconds,
        'data_source' => $dataSource,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
