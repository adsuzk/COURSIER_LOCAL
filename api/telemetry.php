<?php
// api/telemetry.php
// Endpoints pour la télémétrie et le monitoring des applications Android
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../geolocation_helper.php';
require_once __DIR__ . '/../lib/version_helpers.php';

// Headers CORS et sécurité
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Version, X-Device-ID');

// Réponse aux preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authentification basique par API key (à améliorer)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
if (!$apiKey || $apiKey !== 'suzosky_telemetry_2025') {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

/**
 * Fonction d'analyse automatique des problèmes de compatibilité Android
 * Détecte tous les patterns de crash connus pour toutes versions Android
 */
function analyzeAndroidCompatibility($exceptionMessage, $stackTrace, $exceptionClass, $androidVersion) {
    $message = strtolower($exceptionMessage . ' ' . $stackTrace);
    $androidVersionInt = (int)filter_var($androidVersion, FILTER_SANITIZE_NUMBER_INT);
    
    // Détection ultra-précise par patterns et version Android
    if (strpos($message, 'receiver_exported') !== false || strpos($message, 'receiver_not_exported') !== false) {
        return [
            'category' => 'RECEIVER_EXPORT_ANDROID14',
            'crash_type' => 'COMPATIBILITY',
            'criticality' => 'CRITIQUE',
            'solution' => 'Ajouter Context.RECEIVER_NOT_EXPORTED dans registerReceiver() pour Android 14+',
            'affected_versions' => ['14+'],
            'auto_fix_available' => true
        ];
    }
    
    if ((strpos($message, 'write_external_storage') !== false || strpos($message, 'read_external_storage') !== false) && $androidVersionInt >= 11) {
        return [
            'category' => 'STORAGE_PERMISSION_ANDROID11+',
            'crash_type' => 'PERMISSION',
            'criticality' => 'ELEVEE',
            'solution' => 'Migrer vers Scoped Storage API (MediaStore/SAF)',
            'affected_versions' => ['11+'],
            'auto_fix_available' => false
        ];
    }
    
    if (strpos($message, 'query_all_packages') !== false && $androidVersionInt >= 11) {
        return [
            'category' => 'PACKAGE_VISIBILITY_ANDROID11+', 
            'crash_type' => 'PERMISSION',
            'criticality' => 'ELEVEE',
            'solution' => 'Ajouter <queries> dans AndroidManifest.xml ou QUERY_ALL_PACKAGES permission',
            'affected_versions' => ['11+'],
            'auto_fix_available' => false
        ];
    }
    
    if ((strpos($message, 'foreground_service') !== false || strpos($message, 'startforegroundservice') !== false) && $androidVersionInt >= 8) {
        return [
            'category' => 'FOREGROUND_SERVICE_ANDROID8+',
            'crash_type' => 'SERVICE',
            'criticality' => 'ELEVEE',
            'solution' => 'Appeler startForeground() dans les 5 secondes après startForegroundService()',
            'affected_versions' => ['8+'],
            'auto_fix_available' => false
        ];
    }
    
    if (strpos($message, 'fileuriexposedexception') !== false && $androidVersionInt >= 7) {
        return [
            'category' => 'FILE_URI_ANDROID7+',
            'crash_type' => 'SECURITY',
            'criticality' => 'ELEVEE', 
            'solution' => 'Utiliser FileProvider au lieu de file:// URIs',
            'affected_versions' => ['7+'],
            'auto_fix_available' => false
        ];
    }
    
    if (strpos($message, 'networkonmainthread') !== false) {
        return [
            'category' => 'NETWORK_MAIN_THREAD',
            'crash_type' => 'THREADING',
            'criticality' => 'ELEVEE',
            'solution' => 'Déplacer appels réseau vers AsyncTask/Thread/Executor',
            'affected_versions' => ['ALL'],
            'auto_fix_available' => true
        ];
    }
    
    if (strpos($exceptionClass, 'securityexception') !== false && $androidVersionInt >= 14) {
        return [
            'category' => 'SECURITY_ANDROID14',
            'crash_type' => 'SECURITY',
            'criticality' => 'CRITIQUE',
            'solution' => 'Vérifier permissions runtime et export flags pour Android 14',
            'affected_versions' => ['14+'],
            'auto_fix_available' => false
        ];
    }
    
    if (strpos($message, 'activitynotfound') !== false) {
        return [
            'category' => 'MISSING_INTENT_HANDLER',
            'crash_type' => 'INTENT',
            'criticality' => 'MOYENNE',
            'solution' => 'Vérifier availability avec resolveActivity() avant startActivity()',
            'affected_versions' => ['ALL'],
            'auto_fix_available' => true
        ];
    }
    
    if (strpos($message, 'outofmemory') !== false) {
        return [
            'category' => 'MEMORY_LEAK',
            'crash_type' => 'MEMORY',
            'criticality' => 'ELEVEE',
            'solution' => 'Analyser avec LeakCanary et optimiser gestion mémoire',
            'affected_versions' => ['ALL'],
            'auto_fix_available' => false
        ];
    }
    
    // Pattern générique pour crashes fréquents 
    return [
        'category' => 'OTHER_COMPATIBILITY',
        'crash_type' => 'UNKNOWN',
        'criticality' => 'MOYENNE',
        'solution' => 'Analyser stack trace et tester sur appareil cible',
        'affected_versions' => ['UNKNOWN'],
        'auto_fix_available' => false
    ];
}

$pdo = null;
try {
    $pdo = getPDO();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed', 'message' => $e->getMessage()]);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';
$deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? $_POST['device_id'] ?? $_GET['device_id'] ?? null;

try {
    switch ($endpoint) {
        
        case 'register_device':
            if ($method !== 'POST') {
                throw new Exception('POST required for device registration');
            }
            
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $required = ['device_id', 'app_version_code', 'app_version_name'];
            
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
            
            // Enregistrer ou mettre à jour l'appareil
            $stmt = $pdo->prepare("
                INSERT INTO app_devices (
                    device_id, device_model, device_brand, android_version, 
                    app_version_code, app_version_name
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    device_model = VALUES(device_model),
                    device_brand = VALUES(device_brand),
                    android_version = VALUES(android_version),
                    app_version_code = VALUES(app_version_code),
                    app_version_name = VALUES(app_version_name),
                    last_seen = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $data['device_id'],
                $data['device_model'] ?? null,
                $data['device_brand'] ?? null,
                $data['android_version'] ?? null,
                (int)$data['app_version_code'],
                $data['app_version_name']
            ]);

            // Géolocalisation automatique basée sur l'IP
            $clientIP = getRealClientIP();
            if ($clientIP && $clientIP !== '127.0.0.1') {
                $geoResult = updateDeviceGeolocation($data['device_id'], $clientIP);
                // Log géolocalisation pour debug (optionnel)
                error_log("Geolocation for device {$data['device_id']} from IP {$clientIP}: " . 
                         ($geoResult['success'] ? 'SUCCESS' : $geoResult['error']));
            }
            
            echo json_encode(['success' => true, 'message' => 'Device registered']);
            break;
            
        case 'heartbeat':
            if ($method !== 'POST' || !$deviceId) {
                throw new Exception('POST with device_id required');
            }
            
            // Mettre à jour last_seen
            $stmt = $pdo->prepare("UPDATE app_devices SET last_seen = CURRENT_TIMESTAMP WHERE device_id = ?");
            $stmt->execute([$deviceId]);

            // Mise à jour géolocalisation périodique (toutes les 24h)
            $stmt = $pdo->prepare("SELECT geolocation_updated FROM app_devices WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $needsGeoUpdate = !$device['geolocation_updated'] || 
                             strtotime($device['geolocation_updated']) < (time() - 86400); // 24h
            
            if ($needsGeoUpdate) {
                $clientIP = getRealClientIP();
                if ($clientIP && $clientIP !== '127.0.0.1') {
                    updateDeviceGeolocation($deviceId, $clientIP);
                }
            }
            
            // Vérifier si une mise à jour est disponible
            // Dernière version depuis la config JSON (source de vérité commune)
            $cfg = vu_load_versions_config();
            vu_overlay_with_latest_upload($cfg, false);
            $latest = $cfg['current_version'] ?? [];

            // Récupérer version actuelle de l'appareil
            $stmt = $pdo->prepare("SELECT app_version_code FROM app_devices WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            $currentVersion = (int)($stmt->fetchColumn() ?: 0);

            $latestCode = (int)($latest['version_code'] ?? 0);
            $updateAvailable = $latestCode > $currentVersion;
            $releaseNotes = '';
            if (!empty($latest['changelog']) && is_array($latest['changelog'])) {
                $releaseNotes = implode("\n", $latest['changelog']);
            }

            echo json_encode([
                'success' => true,
                'update_available' => $updateAvailable,
                'update_info' => $updateAvailable ? [
                    'version_name' => $latest['version_name'] ?? null,
                    'version_code' => $latestCode,
                    'download_url' => $latest['apk_url'] ?? '/admin/download_apk.php?latest=1',
                    'is_mandatory' => (bool)($latest['force_update'] ?? false),
                    'release_notes' => $releaseNotes
                ] : null
            ]);
            break;
            
        case 'report_crash':
            if ($method !== 'POST' || !$deviceId) {
                throw new Exception('POST with device_id required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            // Récupérer informations appareil pour enrichir le contexte
            $stmt = $pdo->prepare("SELECT device_model, device_brand, android_version, app_version_code FROM app_devices WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            $deviceInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Analyse automatique avancée du crash
            $exceptionMessage = $data['exception_message'] ?? '';
            $stackTrace = $data['stack_trace'] ?? '';
            $exceptionClass = $data['exception_class'] ?? '';
            $androidVersion = $deviceInfo['android_version'] ?? '';
            
            // Classification automatique étendue
            $autoAnalysis = analyzeAndroidCompatibility($exceptionMessage, $stackTrace, $exceptionClass, $androidVersion);
            
            // Générer un hash unique pour grouper les crashes similaires
            $crashData = [
                $exceptionClass,
                $exceptionMessage,
                $data['screen_name'] ?? '',
                substr($stackTrace, 0, 500) // Premiers 500 chars du stack trace
            ];
            $crashHash = hash('sha256', implode('|', $crashData));
            
            // Vérifier si ce crash existe déjà
            $stmt = $pdo->prepare("
                SELECT id, occurrence_count FROM app_crashes 
                WHERE device_id = ? AND crash_hash = ?
            ");
            $stmt->execute([$deviceId, $crashHash]);
            $existingCrash = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingCrash) {
                // Incrémenter le compteur avec contexte mis à jour
                $stmt = $pdo->prepare("
                    UPDATE app_crashes 
                    SET occurrence_count = occurrence_count + 1, 
                        last_occurred = CURRENT_TIMESTAMP,
                        android_version = COALESCE(android_version, ?),
                        device_model = COALESCE(device_model, ?),
                        user_action = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $androidVersion,
                    ($deviceInfo['device_brand'] ?? '') . ' ' . ($deviceInfo['device_model'] ?? ''),
                    $data['user_action'] ?? null,
                    $existingCrash['id']
                ]);
            } else {
                // Nouveau crash - insertion avec analyse automatique
                $stmt = $pdo->prepare("
                    INSERT INTO app_crashes (
                        device_id, crash_hash, app_version_code, android_version, device_model,
                        crash_type, exception_class, exception_message, stack_trace, screen_name,
                        user_action, memory_usage, battery_level, network_type, is_resolved,
                        occurrence_count, first_occurred, last_occurred
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                
                $stmt->execute([
                    $deviceId,
                    $crashHash,
                    $deviceInfo['app_version_code'] ?? 1,
                    $androidVersion,
                    ($deviceInfo['device_brand'] ?? '') . ' ' . ($deviceInfo['device_model'] ?? ''),
                    $autoAnalysis['crash_type'],
                    $exceptionClass,
                    $exceptionMessage,
                    $stackTrace,
                    $data['screen_name'] ?? null,
                    $data['user_action'] ?? null,
                    $data['memory_usage'] ?? null,
                    $data['battery_level'] ?? null,
                    $data['network_type'] ?? null
                ]);
            }
            
            // Log automatique pour surveillance proactive
            error_log("🚨 CRASH AUTO-DETECTED: " . $autoAnalysis['category'] . 
                     " | Device: " . ($deviceInfo['device_brand'] ?? '') . ' ' . ($deviceInfo['device_model'] ?? '') . 
                     " | Android: " . $androidVersion . 
                     " | Criticality: " . $autoAnalysis['criticality']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Crash reported and analyzed',
                'auto_analysis' => $autoAnalysis
            ]); 
                    WHERE id = ?
                ");
                $stmt->execute([$existingCrash['id']]);
            } else {
                // Nouveau crash
                $stmt = $pdo->prepare("
                    INSERT INTO app_crashes (
                        device_id, crash_hash, app_version_code, android_version, 
                        device_model, crash_type, exception_class, exception_message, 
                        stack_trace, screen_name, user_action, memory_usage, 
                        battery_level, network_type
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $deviceId,
                    $crashHash,
                    (int)($data['app_version_code'] ?? 1),
                    $data['android_version'] ?? null,
                    $data['device_model'] ?? null,
                    $data['crash_type'] ?? 'EXCEPTION',
                    $data['exception_class'] ?? null,
                    $data['exception_message'] ?? null,
                    $data['stack_trace'] ?? null,
                    $data['screen_name'] ?? null,
                    $data['user_action'] ?? null,
                    isset($data['memory_usage']) ? (int)$data['memory_usage'] : null,
                    isset($data['battery_level']) ? (int)$data['battery_level'] : null,
                    $data['network_type'] ?? null
                ]);
            }
            
            echo json_encode(['success' => true, 'crash_hash' => $crashHash]);
            break;
            
        case 'start_session':
            if ($method !== 'POST' || !$deviceId) {
                throw new Exception('POST with device_id required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $sessionId = $data['session_id'] ?? uniqid('sess_', true);
            
            $stmt = $pdo->prepare("
                INSERT INTO app_sessions (device_id, session_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$deviceId, $sessionId]);
            
            echo json_encode(['success' => true, 'session_id' => $sessionId]);
            break;
            
        case 'end_session':
            if ($method !== 'POST' || !$deviceId) {
                throw new Exception('POST with device_id required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $sessionId = $data['session_id'] ?? null;
            
            if ($sessionId) {
                $stmt = $pdo->prepare("
                    UPDATE app_sessions 
                    SET ended_at = CURRENT_TIMESTAMP,
                        duration_seconds = TIMESTAMPDIFF(SECOND, started_at, CURRENT_TIMESTAMP),
                        screens_visited = ?,
                        actions_performed = ?,
                        crashed = ?
                    WHERE session_id = ? AND device_id = ?
                ");
                
                $stmt->execute([
                    (int)($data['screens_visited'] ?? 0),
                    (int)($data['actions_performed'] ?? 0),
                    (int)($data['crashed'] ?? 0),
                    $sessionId,
                    $deviceId
                ]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'track_event':
            if ($method !== 'POST' || !$deviceId) {
                throw new Exception('POST with device_id required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $stmt = $pdo->prepare("
                INSERT INTO app_events (
                    device_id, event_type, event_name, screen_name, 
                    event_data, session_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $deviceId,
                $data['event_type'] ?? 'CUSTOM',
                $data['event_name'] ?? 'unknown',
                $data['screen_name'] ?? null,
                isset($data['event_data']) ? json_encode($data['event_data']) : null,
                $data['session_id'] ?? null
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'check_notifications':
            if ($method !== 'GET' || !$deviceId) {
                throw new Exception('GET with device_id required');
            }
            
            // Récupérer notifications non lues
            $stmt = $pdo->prepare("
                SELECT id, notification_type, title, message, action_url, priority
                FROM app_notifications 
                WHERE (device_id = ? OR device_id IS NULL)
                  AND sent_at IS NULL
                  AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY priority DESC, created_at ASC
                LIMIT 10
            ");
            $stmt->execute([$deviceId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Marquer comme envoyées
            if (!empty($notifications)) {
                $ids = array_column($notifications, 'id');
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE app_notifications SET sent_at = CURRENT_TIMESTAMP WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
            }
            
            echo json_encode(['notifications' => $notifications]);
            break;
            
        case 'stats':
            if ($method !== 'GET') {
                throw new Exception('GET required');
            }
            
            // Stats globales pour debug
            $stats = [];
            
            $stmt = $pdo->query("SELECT COUNT(*) as total_devices FROM app_devices WHERE is_active = 1");
            $stats['total_devices'] = (int)$stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) as active_today FROM app_devices WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stats['active_today'] = (int)$stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) as total_crashes FROM app_crashes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['crashes_week'] = (int)$stmt->fetchColumn();
            
            echo json_encode($stats);
            break;
            
        default:
            throw new Exception("Unknown endpoint: {$endpoint}");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'endpoint' => $endpoint,
        'method' => $method
    ]);
}
?>