<?php
// geolocation_helper.php
// Fonctions utilitaires pour la géolocalisation IP

/**
 * Récupère les informations de géolocalisation pour une adresse IP
 * Utilise l'API ipapi.co (gratuite: 1000 requêtes/jour)
 */
function getGeolocationFromIP($ipAddress) {
    global $pdo;
    
    // Vérifier le cache d'abord
    $stmt = $pdo->prepare("SELECT * FROM ip_geolocation_cache WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$ipAddress]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cached) {
        return [
            'success' => true,
            'source' => 'cache',
            'data' => $cached
        ];
    }
    
    // IPs locales/privées
    if (isPrivateIP($ipAddress)) {
        return [
            'success' => false,
            'error' => 'IP privée - géolocalisation non applicable',
            'data' => null
        ];
    }
    
    try {
        // Appel API ipapi.co
        $url = "http://ipapi.co/{$ipAddress}/json/";
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => 'User-Agent: SuzoSky-Coursier-App/1.0'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Échec requête API');
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || isset($data['error'])) {
            throw new Exception('Réponse API invalide: ' . ($data['reason'] ?? 'Erreur inconnue'));
        }
        
        // Sauvegarder en cache
        $cacheData = [
            'ip_address' => $ipAddress,
            'country_code' => $data['country'] ?? null,
            'country_name' => $data['country_name'] ?? null,
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'latitude' => isset($data['latitude']) ? floatval($data['latitude']) : null,
            'longitude' => isset($data['longitude']) ? floatval($data['longitude']) : null,
            'timezone' => $data['timezone'] ?? null,
            'isp' => $data['org'] ?? null,
            'api_response' => json_encode($data)
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO ip_geolocation_cache 
            (ip_address, country_code, country_name, region, city, latitude, longitude, timezone, isp, api_response) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            country_code = VALUES(country_code),
            country_name = VALUES(country_name),
            region = VALUES(region),
            city = VALUES(city),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            timezone = VALUES(timezone),
            isp = VALUES(isp),
            api_response = VALUES(api_response),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $cacheData['ip_address'],
            $cacheData['country_code'],
            $cacheData['country_name'],
            $cacheData['region'],
            $cacheData['city'],
            $cacheData['latitude'],
            $cacheData['longitude'],
            $cacheData['timezone'],
            $cacheData['isp'],
            $cacheData['api_response']
        ]);
        
        return [
            'success' => true,
            'source' => 'api',
            'data' => $cacheData
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Met à jour la géolocalisation d'un appareil
 */
function updateDeviceGeolocation($deviceId, $ipAddress) {
    global $pdo;
    
    $geoResult = getGeolocationFromIP($ipAddress);
    
    if ($geoResult['success']) {
        $data = $geoResult['data'];
        
        $stmt = $pdo->prepare("
            UPDATE app_devices SET 
            ip_address = ?,
            country_code = ?,
            country_name = ?,
            region = ?,
            city = ?,
            latitude = ?,
            longitude = ?,
            timezone = ?,
            geolocation_updated = CURRENT_TIMESTAMP
            WHERE device_id = ?
        ");
        
        $stmt->execute([
            $ipAddress,
            $data['country_code'],
            $data['country_name'],
            $data['region'],
            $data['city'],
            $data['latitude'],
            $data['longitude'],
            $data['timezone'],
            $deviceId
        ]);
        
        return ['success' => true, 'source' => $geoResult['source']];
    }
    
    return ['success' => false, 'error' => $geoResult['error']];
}

/**
 * Vérifie si une IP est privée/locale
 */
function isPrivateIP($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return true;
    }
    
    // IPs locales
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }
    
    return false;
}

/**
 * Récupère l'IP réelle du client (même derrière proxy/CDN)
 */
function getRealClientIP() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Proxy standard
        'HTTP_X_REAL_IP',            // Nginx
        'HTTP_X_ORIGINAL_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'                // IP directe
    ];
    
    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]); // Premier IP de la liste
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Statistiques géographiques pour l'admin
 */
function getGeographicStats() {
    global $pdo;
    
    $query = "
        SELECT 
            d.country_name,
            d.country_code,
            COUNT(DISTINCT d.device_id) as total_devices,
            COUNT(DISTINCT CASE WHEN d.last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN d.device_id END) as active_7d,
            COUNT(DISTINCT CASE WHEN d.last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN d.device_id END) as active_1d,
            GROUP_CONCAT(DISTINCT d.city ORDER BY d.city SEPARATOR ', ') as cities
        FROM app_devices d 
        WHERE d.is_active = 1 
          AND d.country_code IS NOT NULL
        GROUP BY d.country_code, d.country_name
        ORDER BY total_devices DESC
    ";
    
    return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Carte de chaleur des villes
 */
function getCityHeatmapData() {
    global $pdo;
    
    $query = "
        SELECT 
            d.city,
            d.region,
            d.country_name,
            d.latitude,
            d.longitude,
            COUNT(DISTINCT d.device_id) as device_count,
            COUNT(DISTINCT CASE WHEN d.last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN d.device_id END) as active_today
        FROM app_devices d 
        WHERE d.is_active = 1 
          AND d.latitude IS NOT NULL 
          AND d.longitude IS NOT NULL
        GROUP BY d.city, d.region, d.country_name, d.latitude, d.longitude
        HAVING device_count > 0
        ORDER BY device_count DESC
        LIMIT 100
    ";
    
    return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
}
?>