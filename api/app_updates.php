<?php
/**
 * API de gestion des versions APK - Système de mise à jour automatique
 * Endpoint: /api/app_updates.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/version_helpers.php';

// Headers pour API REST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Charger la config et surcoucher avec la dernière APK uploadée
$config = vu_load_versions_config();
// Overlay latest; no need to persist on GET requests, config is used for response only
vu_overlay_with_latest_upload($config, false);

// Fonction pour logger les vérifications de version
function logVersionCheck($device_id, $current_version, $action) {
    $log_file = __DIR__ . '/../data/version_checks.log';
    $log_entry = date('Y-m-d H:i:s') . " | Device: $device_id | Version: $current_version | Action: $action\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Fonction pour enregistrer un appareil
function registerDevice($config, $device_info, $versions_config) {
    $device_id = $device_info['device_id'];
    $config['devices'][$device_id] = [
        'device_id' => $device_id,
        'current_version_code' => $device_info['version_code'],
        'last_check' => date('Y-m-d H:i:s'),
        'device_model' => $device_info['device_model'] ?? 'Unknown',
        'android_version' => $device_info['android_version'] ?? 'Unknown',
        'app_version' => $device_info['version_name'] ?? 'Unknown',
        'status' => 'active'
    ];
    file_put_contents($versions_config, json_encode($config, JSON_PRETTY_PRINT));
    return $config;
}

// Traitement des requêtes
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
    // Vérification de mise à jour
    $device_id = $_GET['device_id'] ?? ($_SERVER['HTTP_X_DEVICE_ID'] ?? '');
    $current_version_code = (int)($_GET['version_code'] ?? ($_SERVER['HTTP_X_APP_VERSION_CODE'] ?? 0));
    $device_model = $_GET['device_model'] ?? ($_SERVER['HTTP_X_DEVICE_MODEL'] ?? null);
    $device_brand = $_GET['device_brand'] ?? ($_SERVER['HTTP_X_DEVICE_BRAND'] ?? null);
    $android_version = $_GET['android_version'] ?? ($_SERVER['HTTP_X_ANDROID_VERSION'] ?? null);
    $app_version_name = $_GET['version_name'] ?? ($_SERVER['HTTP_X_APP_VERSION_NAME'] ?? null);
        
        if (empty($device_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'device_id required']);
            exit;
        }
        
    // Enregistrer la vérification
        logVersionCheck($device_id, $current_version_code, 'version_check');
        
        // Vérifier si une mise à jour est disponible
        $latest_version = $config['current_version'];
        $update_available = $current_version_code < $latest_version['version_code'];
        $force_update = $current_version_code < $config['current_version']['min_supported_version'];
        
        $response = [
            'update_available' => $update_available,
            'force_update' => $force_update,
            'latest_version' => $latest_version,
            'auto_install' => $config['auto_install'],
            'check_interval' => $config['update_check_interval']
        ];
        
        if ($update_available) {
            $response['download_url'] = 'https://' . $_SERVER['HTTP_HOST'] . $latest_version['apk_url'];
            logVersionCheck($device_id, $current_version_code, 'update_available');
        }
        
        // Mettre à jour les infos de l'appareil (JSON de versions) et BDD télémétrie
        $paths = vu_get_paths();
        if (!isset($config['devices'][$device_id])) {
            $config['devices'][$device_id] = [
                'device_id' => $device_id,
                'current_version_code' => $current_version_code,
                'device_model' => $device_model,
                'android_version' => $android_version,
                'app_version' => $app_version_name,
                'update_status' => 'active'
            ];
        }
        $config['devices'][$device_id]['last_check'] = date('Y-m-d H:i:s');
        file_put_contents($paths['versions_config'], json_encode($config, JSON_PRETTY_PRINT));

        // Seed/Upsert côté BDD télémétrie (pour affichage en temps réel)
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("INSERT INTO app_devices (device_id, device_model, device_brand, android_version, app_version_code, app_version_name) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE device_model=VALUES(device_model), device_brand=VALUES(device_brand), android_version=VALUES(android_version), app_version_code=VALUES(app_version_code), app_version_name=VALUES(app_version_name), last_seen=CURRENT_TIMESTAMP");
            $stmt->execute([$device_id, $device_model, $device_brand, $android_version, $current_version_code, $app_version_name]);
        } catch (Throwable $e) {
            // Fallback silencieux: ne pas casser l'API si la BDD télémetrie est temporairement indisponible
            error_log('telemetry upsert failed: ' . $e->getMessage());
        }
        
        echo json_encode($response);
        break;
        
    case 'POST':
        // Enregistrement d'appareil ou rapport de statut
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'register_device':
                $paths = vu_get_paths();
                $config = registerDevice($config, $input, $paths['versions_config']);
                logVersionCheck($input['device_id'], $input['version_code'], 'device_registered');
                echo json_encode(['success' => true, 'message' => 'Device registered']);
                break;
                
            case 'update_status':
                $device_id = $input['device_id'];
                $status = $input['status']; // downloading, installing, installed, failed
                
                if (isset($config['devices'][$device_id])) {
                    $config['devices'][$device_id]['update_status'] = $status;
                    $config['devices'][$device_id]['last_update_attempt'] = date('Y-m-d H:i:s');
                    
                    if ($status === 'installed') {
                        $config['devices'][$device_id]['current_version_code'] = $config['current_version']['version_code'];
                        // Mettre à jour la BDD télémétrie pour refléter l'installation
                        try {
                            $pdo = getPDO();
                            $latest = $config['current_version'] ?? [];
                            $stmt = $pdo->prepare("INSERT INTO app_devices (device_id, app_version_code, app_version_name, last_seen) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE app_version_code=VALUES(app_version_code), app_version_name=VALUES(app_version_name), last_seen=CURRENT_TIMESTAMP");
                            $stmt->execute([$device_id, (int)($latest['version_code'] ?? 0), $latest['version_name'] ?? null]);
                        } catch (Throwable $e) { error_log('telemetry update_status installed upsert failed: ' . $e->getMessage()); }
                    }
                    
                    $paths = vu_get_paths();
                    file_put_contents($paths['versions_config'], json_encode($config, JSON_PRETTY_PRINT));
                    logVersionCheck($device_id, 0, "update_status_$status");
                }
                
                echo json_encode(['success' => true]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>