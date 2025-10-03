<?php
// MONITORING DEBUG - Log toutes les requêtes
$logFile = __DIR__ . '/debug_requests.log';
$timestamp = date('Y-m-d H:i:s');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$logLine = "$timestamp - $method $uri - IP: $ip - UA: $userAgent\n";
if ($method === 'POST' && !empty($_POST)) {
    $logLine .= "  POST: " . json_encode($_POST) . "\n";
}
file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Charger la configuration et l'accès base de données
require_once __DIR__ . '/config.php';
// Charger l'intégration CinetPay et instancier le client global pour les actions AJAX
require_once __DIR__ . '/cinetpay/cinetpay_integration.php';
if (!isset($cinetpay) || !($cinetpay instanceof SuzoskyCinetPayIntegration)) {
    $cinetpay = new SuzoskyCinetPayIntegration();
}

// Optimisation de trajet (Google Directions API)
function optimizeDeliveryRoute($start_lat, $start_lng, $deliveries) {
    // Build the flat list of waypoint coordinates (same order as before: pickup then delivery)
    $waypoints = [];
    foreach ($deliveries as $delivery) {
        if (!empty($delivery['latitude_pickup']) && !empty($delivery['longitude_pickup'])) {
            $waypoints[] = $delivery['latitude_pickup'] . ',' . $delivery['longitude_pickup'];
        }
        if (!empty($delivery['latitude_delivery']) && !empty($delivery['longitude_delivery'])) {
            $waypoints[] = $delivery['latitude_delivery'] . ',' . $delivery['longitude_delivery'];
        }
    }

    if (empty($waypoints)) {
        return ['route' => [], 'total_distance' => 0, 'estimated_time' => 0];
    }

    $api_key = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : (getenv('GOOGLE_MAPS_API_KEY') ?: null);
    if (!$api_key) {
        return ['route' => $deliveries, 'total_distance' => 0, 'estimated_time' => 0, 'error' => 'Missing Google Maps API key'];
    }

    // Prepare locations: origin + all waypoints
    $locations = [];
    $locations[] = ['lat' => floatval($start_lat), 'lng' => floatval($start_lng)];
    foreach ($waypoints as $wp) {
        list($lat, $lng) = array_map('trim', explode(',', $wp));
        $locations[] = ['lat' => floatval($lat), 'lng' => floatval($lng)];
    }

    // Try Routes API: computeRouteMatrix to get pairwise distances/durations, then do a simple nearest-neighbour ordering.
    $matrixUrl = 'https://routes.googleapis.com/v2:computeRouteMatrix?key=' . urlencode($api_key);
    $matrixBody = [
        'origins' => [],
        'destinations' => [],
        'travelMode' => 'DRIVE',
        'routingPreference' => 'TRAFFIC_AWARE'
    ];
    foreach ($locations as $loc) {
        $matrixBody['origins'][] = ['endpoint' => ['location' => ['latLng' => ['latitude' => $loc['lat'], 'longitude' => $loc['lng']]]]];
        $matrixBody['destinations'][] = ['endpoint' => ['location' => ['latLng' => ['latitude' => $loc['lat'], 'longitude' => $loc['lng']]]]];
    }

    $ch = curl_init($matrixUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($matrixBody));
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $matrixResp = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $distanceMatrix = null; // will be an NxN matrix in meters
    if ($matrixResp && $httpCode >= 200 && $httpCode < 300) {
        $matrixData = json_decode($matrixResp, true);
        // Try to parse common shapes: rows->elements or originDestinationPairs
        if (!empty($matrixData['rows']) && is_array($matrixData['rows'])) {
            $n = count($matrixData['rows']);
            $distanceMatrix = array_fill(0, $n, array_fill(0, $n, PHP_INT_MAX));
            for ($i = 0; $i < $n; $i++) {
                $elements = $matrixData['rows'][$i]['elements'] ?? [];
                for ($j = 0; $j < count($elements); $j++) {
                    $el = $elements[$j];
                    if (isset($el['distance']) && isset($el['distance']['value'])) {
                        $distanceMatrix[$i][$j] = (int)$el['distance']['value'];
                    } elseif (isset($el['distanceMeters'])) {
                        $distanceMatrix[$i][$j] = (int)$el['distanceMeters'];
                    }
                }
            }
        } elseif (!empty($matrixData['originDestinationPairs']) && is_array($matrixData['originDestinationPairs'])) {
            // Some matrix responses present as a flat list
            $pairs = $matrixData['originDestinationPairs'];
            $n = count($locations);
            $distanceMatrix = array_fill(0, $n, array_fill(0, $n, PHP_INT_MAX));
            foreach ($pairs as $pair) {
                $i = $pair['originIndex'] ?? null;
                $j = $pair['destinationIndex'] ?? null;
                $dist = $pair['distanceMeters'] ?? ($pair['distance'] ?? null);
                if ($i !== null && $j !== null && $dist !== null) {
                    $distanceMatrix[(int)$i][(int)$j] = (int)$dist;
                }
            }
        } else {
            // Unknown format — ignore and fallback later
            error_log('Routes matrix: unexpected response format or empty matrix');
        }
    } else {
        error_log('Routes matrix request failed HTTP=' . intval($httpCode) . ' curlErr=' . $curlErr . ' resp=' . substr($matrixResp ?? '', 0, 200));
    }

    // If we got a valid distance matrix, compute a simple greedy route (nearest neighbour)
    if (is_array($distanceMatrix)) {
        $n = count($distanceMatrix);
        // indices: 0 = origin, 1..n-1 = waypoints
        $visited = array_fill(0, $n, false);
        $order = [];
        $current = 0; // start at origin
        $visited[0] = true;
        while (true) {
            $best = null;
            $bestDist = PHP_INT_MAX;
            for ($j = 1; $j < $n; $j++) {
                if ($visited[$j]) continue;
                $d = $distanceMatrix[$current][$j] ?? PHP_INT_MAX;
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $best = $j;
                }
            }
            if ($best === null) break;
            $order[] = $best;
            $visited[$best] = true;
            $current = $best;
        }

        // Map the waypoint indices back to deliveries (best-effort: preserve previous behavior)
        $optimized_deliveries = [];
        $optimized_order = [];
        foreach ($order as $wpIndex) {
            // $wpIndex corresponds to locations index; waypoints start at locations index 1
            $relative = $wpIndex - 1; // index into $waypoints
            $optimized_order[] = $relative;
            // Attempt to map back to a delivery: the original code assumed a direct mapping; keep that for compatibility
            if (isset($deliveries[$relative])) {
                $optimized_deliveries[] = $deliveries[$relative];
            }
        }

        // Compute rough total distance/time by summing chosen legs from the matrix
        $total_distance = 0;
        $estimated_time = 0;
        $prev = 0;
        foreach ($order as $idx) {
            $d = $distanceMatrix[$prev][$idx] ?? 0;
            $total_distance += (int)$d;
            $prev = $idx;
            // duration estimation not always available in matrix; keep 0
        }

        // For polyline/legs, fall back to Directions API to get an overview polyline using the optimized order
        try {
            $ordered_waypoints = [];
            foreach ($order as $idx) {
                $loc = $locations[$idx];
                $ordered_waypoints[] = $loc['lat'] . ',' . $loc['lng'];
            }
            $origin = $locations[0]['lat'] . ',' . $locations[0]['lng'];
            $destination = end($ordered_waypoints);
            $waypoints_str = implode('|', array_slice($ordered_waypoints, 0, -1));
            $directionsUrl = 'https://maps.googleapis.com/maps/api/directions/json?' . http_build_query([
                'origin' => $origin,
                'destination' => $destination,
                'waypoints' => $waypoints_str ? ('|' . $waypoints_str) : '',
                'key' => $api_key,
                'language' => 'fr',
                'region' => 'CI'
            ]);
            $dirResp = @file_get_contents($directionsUrl);
            if ($dirResp !== false) {
                $dirData = json_decode($dirResp, true);
                if (!empty($dirData['routes'][0])) {
                    $poly = $dirData['routes'][0]['overview_polyline']['points'] ?? null;
                    return [
                        'route' => $optimized_deliveries,
                        'total_distance' => $total_distance,
                        'estimated_time' => $estimated_time,
                        'polyline' => $poly,
                        'optimized_order' => $optimized_order,
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('Directions fallback after matrix error: ' . $e->getMessage());
        }

        // If we reached here, return what we have (no polyline)
        return [
            'route' => $optimized_deliveries,
            'total_distance' => $total_distance,
            'estimated_time' => $estimated_time,
            'polyline' => null,
            'optimized_order' => $optimized_order,
        ];
    }

    // Fallback: original Directions API call with optimize:true (keeps previous behavior when Routes API not available)
    $origin = $start_lat . ',' . $start_lng;
    $destination = end($waypoints);
    $waypoints_str = implode('|', array_slice($waypoints, 0, -1));
    $url = 'https://maps.googleapis.com/maps/api/directions/json?' . http_build_query([
        'origin' => $origin,
        'destination' => $destination,
        'waypoints' => 'optimize:true|' . $waypoints_str,
        'key' => $api_key,
        'language' => 'fr',
        'region' => 'CI'
    ]);
    $response = @file_get_contents($url);
    if ($response === false) {
        return ['route' => [], 'total_distance' => 0, 'estimated_time' => 0, 'error' => 'API Google Maps inaccessible'];
    }
    $data = json_decode($response, true);
    if (($data['status'] ?? '') === 'OK' && !empty($data['routes'])) {
        $route = $data['routes'][0];
        $optimized_order = $route['waypoint_order'] ?? [];
        $optimized_deliveries = [];
        foreach ($optimized_order as $index) {
            if (isset($deliveries[$index])) {
                $optimized_deliveries[] = $deliveries[$index];
            }
        }
        $total_distance = 0;
        $estimated_time = 0;
        foreach ($route['legs'] as $leg) {
            $total_distance += (int)($leg['distance']['value'] ?? 0);
            $estimated_time += (int)($leg['duration']['value'] ?? 0);
        }
        return [
            'route' => $optimized_deliveries,
            'total_distance' => $total_distance,
            'estimated_time' => $estimated_time,
            'polyline' => $route['overview_polyline']['points'] ?? null,
            'optimized_order' => $optimized_order,
        ];
    }

    // Final fallback
    return ['route' => $deliveries, 'total_distance' => 0, 'estimated_time' => 0, 'error' => 'Directions API error'];
}

// Vérifier si le coursier est connecté
$isLoggedIn = isset($_SESSION['coursier_logged_in']) && $_SESSION['coursier_logged_in'] === true;

// Redirection douce vers la page de connexion si non connecté (accès direct GET)
// mais laisser passer les tentatives de login GET (compat anciennes versions V7)
$requestedAction = strtolower($_GET['action'] ?? '');
if (
    !$isLoggedIn
    && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')
    && $requestedAction !== 'login'
) {
    header('Location: login_coursier.php');
    exit;
}

// Compatibilité V7 étendue: prise en charge JSON et alias de champs
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Injecter JSON dans $_POST si Content-Type: application/json et $_POST vide
    if (empty($_POST)) {
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) { foreach ($decoded as $k=>$v) { $_POST[$k] = $v; } }
            }
        }
    }
    // Alias possibles pour identifiant et mot de passe
    $idAliases = ['identifier','matricule','username','user','telephone','phone'];
    $pwdAliases = ['password','pass','pwd'];
    $idVal = '';
    foreach ($idAliases as $k) { if (isset($_POST[$k]) && $_POST[$k] !== '') { $idVal = $_POST[$k]; break; } }
    $pwdVal = '';
    foreach ($pwdAliases as $k) { if (isset($_POST[$k]) && $_POST[$k] !== '') { $pwdVal = $_POST[$k]; break; } }
    // Journaliser les clés reçues (sans valeurs sensibles)
    try {
        $keys = array_keys($_POST);
        $keys = array_map(function($k){ return in_array($k, ['password','pwd','pass']) ? $k.'(masked)' : $k; }, $keys);
        error_log('V7_LOGIN_IN: action=' . ($_POST['action'] ?? '') . ' keys=' . implode(',', $keys));
    } catch (Throwable $e) { /* ignore */ }
}

/**
 * Fonction d'authentification unifiée
 * Vérifie d'abord agents_suzosky puis coursiers pour compatibilité
 */
function authenticateUser($identifier, $password, $pdo) {
    try {
        // DEBUG: Log des paramètres d'entrée
            $masked = $identifier ? substr($identifier, 0, 2) . str_repeat('*', max(0, strlen($identifier) - 4)) . substr($identifier, -2) : '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $msg = "AUTH_DEBUG: attempt from $ip UA='$ua' identifier='$masked' pwd_len=" . strlen($password);
        error_log($msg);
        try {
            $logDir = __DIR__ . '/diagnostic_logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
            @file_put_contents($logDir . '/login_events.log', date('c') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) { /* ignore */ }
        
        // Chercher uniquement dans agents_suzosky (sans condition statut car la colonne n'existe pas)
    // Rendre la comparaison sur le matricule insensible à la casse pour éviter les échecs avec 'CM20250001' vs 'cm20250001'
    $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE (LOWER(matricule) = LOWER(?) OR telephone = ?)");
    $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        // DEBUG: Log du résultat de la recherche
        if ($user) {
            $msg2 = "AUTH_DEBUG: Agent trouvé - Matricule=" . ($user['matricule'] ?? 'N/A');
            error_log($msg2);
            try { @file_put_contents(__DIR__ . '/diagnostic_logs/login_events.log', date('c') . ' ' . $msg2 . "\n", FILE_APPEND | LOCK_EX); } catch (Throwable $e) {}
            error_log("AUTH_DEBUG: Password en base=" . (!empty($user['password']) ? 'SET (longueur=' . strlen($user['password']) . ')' : 'VIDE'));
        } else {
            error_log("AUTH_DEBUG: Aucun agent trouvé pour identifier='$identifier'");
            return false;
        }
        
        if ($user) {
            // 1) Essai avec hash si présent
            if (!empty($user['password'])) {
                error_log("AUTH_DEBUG: Test password_verify avec password fourni");
                if (password_verify($password, $user['password'])) {
                    error_log("AUTH_DEBUG: ✅ password_verify RÉUSSI");
                    return [
                        'table' => 'agents_suzosky', 
                        'user' => $user,
                        'id_field' => 'id',
                        'identifier_field' => 'matricule'
                    ];
                }
                error_log("AUTH_DEBUG: ❌ password_verify ÉCHOUÉ");
            } else {
                error_log("AUTH_DEBUG: Password hash vide en base");
            }

            // 2) Fallback: comparer plain_password si disponible, puis migrer vers hash
            $plain = $user['plain_password'] ?? null;
            if (!empty($plain)) {
                if (hash_equals($plain, $password)) {
                    error_log("AUTH_DEBUG: ✅ Fallback plain_password OK → migration vers hash");
                    try {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = NULL, updated_at = NOW() WHERE id = ?");
                        $upd->execute([$hash, (int)$user['id']]);
                        // Mettre à jour l'objet utilisateur en mémoire
                        $user['password'] = $hash;
                        unset($user['plain_password']);
                    } catch (Throwable $e) {
                        error_log("AUTH_DEBUG: Erreur migration plain→hash: " . $e->getMessage());
                    }
                    return [
                        'table' => 'agents_suzosky', 
                        'user' => $user,
                        'id_field' => 'id',
                        'identifier_field' => 'matricule'
                    ];
                } else {
                    error_log("AUTH_DEBUG: Fallback plain_password non correspondant");
                }
            } else {
                error_log("AUTH_DEBUG: Aucun plain_password disponible pour fallback");
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("AUTH_DEBUG: Erreur authentification: " . $e->getMessage());
        return false;
    }
}

// Traitement de la connexion - SUPPORT GET ET POST
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$identifier = trim(
    $_POST['identifier']
        ?? $_POST['matricule']
        ?? $_GET['identifier']
        ?? $_GET['matricule']
        ?? ''
);
$password = trim(
    $_POST['password']
        ?? $_POST['pass']
        ?? $_GET['password']
        ?? $_GET['pass']
        ?? ''
);

if ($action === 'login') {
    
    // Debug: Log de la tentative de connexion
    error_log("LOGIN_DEBUG: Traitement LOGIN NORMAL - Identifier='$identifier', Password='" . (empty($password) ? 'vide' : $password) . "' (longueur=" . strlen($password) . ")");
    error_log("LOGIN_DEBUG: Ajax parameter: POST=" . ($_POST['ajax'] ?? 'undefined') . " GET=" . ($_GET['ajax'] ?? 'undefined'));
    
    try {
        $pdo = getDBConnection();
        error_log("LOGIN_DEBUG: Connexion PDO réussie");
        
        $authResult = authenticateUser($identifier, $password, $pdo);
        
            if ($authResult) {
                error_log("LOGIN_DEBUG: ✅ Authentification réussie");
                $user = $authResult['user'];
                $table = $authResult['table'];
                $idField = $authResult['id_field'];
            
                $_SESSION['coursier_logged_in'] = true;
                $_SESSION['coursier_id'] = $user[$idField];
                $_SESSION['coursier_table'] = $table;
                $_SESSION['coursier_matricule'] = $table === 'agents_suzosky' ? $user['matricule'] : $user['id_coursier'];
                $_SESSION['coursier_nom'] = $user['nom'] . ' ' . ($user['prenoms'] ?? $user['prenom'] ?? '');

                $sessionToken = null;
                if ($table === 'agents_suzosky') {
                    try {
                        $pdo->exec("ALTER TABLE agents_suzosky
                            ADD COLUMN IF NOT EXISTS current_session_token VARCHAR(100) NULL,
                            ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL,
                            ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(64) NULL,
                            ADD COLUMN IF NOT EXISTS last_login_user_agent VARCHAR(255) NULL
                        ");
                    } catch (Throwable $e) { /* best-effort */ }

                    try {
                        $sessionToken = bin2hex(random_bytes(16));
                    } catch (Throwable $e) {
                        $fallback = function_exists('openssl_random_pseudo_bytes') ? openssl_random_pseudo_bytes(16) : null;
                        if ($fallback === false || $fallback === null) {
                            $fallback = uniqid((string)$user['id'], true) . microtime(true);
                        }
                        $sessionToken = hash('sha256', (string)$fallback);
                    }

                    $loginIp = $_SERVER['REMOTE_ADDR'] ?? null;
                    $loginUa = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    try {
                        $stmtToken = $pdo->prepare("UPDATE agents_suzosky SET current_session_token = ?, last_login_at = NOW(), last_login_ip = ?, last_login_user_agent = ? WHERE id = ?");
                        $stmtToken->execute([
                            $sessionToken,
                            $loginIp,
                            $loginUa ? substr($loginUa, 0, 240) : null,
                            $user['id']
                        ]);
                    } catch (Throwable $e) {
                        error_log("LOGIN_DEBUG: Échec mise à jour session_token: " . $e->getMessage());
                    }

                    $_SESSION['coursier_session_token'] = $sessionToken;
                } else {
                    unset($_SESSION['coursier_session_token']);
                }

                // Mettre à jour dernière connexion selon la table
                if ($table === 'agents_suzosky') {
                    $stmt = $pdo->prepare("UPDATE agents_suzosky SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE agents_suzosky SET derniere_connexion = NOW() WHERE id_coursier = ?");
                    $stmt->execute([$user['id_coursier']]);
                }
            
                $isLoggedIn = true;
            
                // Déterminer l'URL complète pour la redirection V7
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? '';
                // Pour les clients OkHttp (V7), rediriger vers mobile_app.php, sinon vers coursier.php
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $isOkHttp = stripos($ua, 'okhttp') !== false;
                $path = $isOkHttp ? '/mobile_app.php' : ($_SERVER['SCRIPT_NAME'] ?? '/coursier.php');
                $fullUrl = $scheme . '://' . $host . $path;
            
                // Détecter si requête AJAX: paramètre explicite, en-tête X-Requested-With ou Accept JSON
                $isAjax = (strtolower($_POST['ajax'] ?? $_GET['ajax'] ?? '') === 'true')
                    || (strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0)
                    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
            
                // Détection AJAX et OkHttp (non-browser) clients
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $isOkHttp = stripos($ua, 'okhttp') !== false;
                error_log("LOGIN_DEBUG: UA='$ua' -> isOkHttp=" . ($isOkHttp ? '1' : '0') . ", isAjax=" . ($isAjax ? '1' : '0'));
                // IMPORTANT: Pour les clients mobiles (OkHttp) et toute requête AJAX, on renvoie TOUJOURS du JSON
                // afin d'éviter toute redirection vers une page PHP/HTML. La navigation reste 100% native côté Android.
                if ($isAjax || $isOkHttp) {
                    header('Content-Type: application/json');
                    header('Cache-Control: no-store');
                    echo json_encode([
                        'success' => true,
                        'status' => 'ok',
                        'message' => 'Connexion réussie',
                        // Gardé pour compatibilité éventuelle, ignoré côté app native
                        'redirect' => $fullUrl,
                        'agent' => [
                            'id' => $user[$idField],
                            'matricule' => $table === 'agents_suzosky' ? $user['matricule'] : $user['id_coursier'],
                            'session_token' => $sessionToken,
                            'last_login_ip' => $loginIp ?? null
                        ]
                    ]);
                    exit;
                }
                // Sinon (navigateurs), redirection absolue pour l'interface
                header('Location: ' . $fullUrl, true, 302);
                exit;
        } else {
            $loginError = "Identifiants incorrects ou compte non activé";
            error_log("Échec authentification pour: $identifier");
            
            // Pour toute requête POST de login, retourner JSON (compat V7)
            header('Content-Type: application/json');
            header('Cache-Control: no-store');
            echo json_encode(['success' => false, 'status' => 'error', 'error' => $loginError, 'message' => $loginError]);
            exit;
        }
    } catch (Exception $e) {
        $loginError = "Erreur de connexion : " . $e->getMessage();
        error_log("Erreur connexion: " . $e->getMessage());
        
        // Pour toute requête POST de login, retourner JSON (compat V7)
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['success' => false, 'status' => 'error', 'error' => $loginError, 'message' => $loginError]);
        exit;
    }
}

// (* V7 HTML-based login handling removed: unified under POST redirect above *)

// Traitement déconnexion
if ($_GET['action'] ?? '' === 'logout') {
    // Correction : déconnexion propre côté DB
    require_once __DIR__ . '/config.php';
    $pdo = getDBConnection();
    $coursier_id = $_SESSION['coursier_id'] ?? 0;
    if ($coursier_id) {
        try {
            $stmt = $pdo->prepare("UPDATE agents_suzosky SET statut_connexion = 'hors_ligne', current_session_token = NULL WHERE id = ?");
            $stmt->execute([$coursier_id]);
            $stmt2 = $pdo->prepare("UPDATE device_tokens SET is_active = 0 WHERE coursier_id = ?");
            $stmt2->execute([$coursier_id]);
        } catch (Throwable $e) { /* log ou ignorer */ }
    }
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Traitement AJAX
if (($_POST['ajax'] ?? '' === 'true') && !empty($_POST['action'])) {
    
    // Si c'est un login AJAX, ne pas le traiter ici (déjà traité plus haut)
    if ($_POST['action'] === 'login') {
        // Le login a déjà été traité et a fait exit, ceci ne devrait pas s'exécuter
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Login déjà traité']);
        exit;
    }
    
    // Gestion action dashboard/home pour V7 après login
    if ($_POST['action'] === 'dashboard' || $_POST['action'] === 'home') {
        if (!$isLoggedIn) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non connecté']);
            exit;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'status' => 'ok',
            'message' => 'Dashboard chargé',
            'data' => [
                'agent_nom' => $_SESSION['coursier_nom'] ?? 'Agent',
                'agent_matricule' => $_SESSION['coursier_matricule'] ?? '',
                'agent_id' => $_SESSION['coursier_id'] ?? 0
            ]
        ]);
        exit;
    }
    error_log("AJAX_DEBUG: Traitement AJAX détecté - Action: " . ($_POST['action'] ?? 'undefined'));
    header('Content-Type: application/json');
    
    try {
        $pdo = getDBConnection();
        $coursier_id = $_SESSION['coursier_id'] ?? 0;
        
        switch ($_POST['action']) {
            case 'get_assigned_order':
                // Retourner la commande classique active assignée (statuts nouvelle|acceptee|en_cours)
                $pdo2 = getDBConnection();
                $coursier_id_local = $_SESSION['coursier_id'] ?? 0;
                $stmt = $pdo2->prepare("SELECT id, nom_client as clientNom, telephone_client as clientTelephone, adresse_enlevement as adresseEnlevement, adresse_livraison as adresseLivraison, tarif_livraison as prixLivraison, statut FROM commandes_classiques WHERE coursier_id = ? AND statut IN ('nouvelle','acceptee','en_cours') ORDER BY date_creation DESC LIMIT 1");
                $stmt->execute([$coursier_id_local]);
                $order = $stmt->fetch();
                echo json_encode(['success' => true, 'order' => $order ?: null]);
                exit;
            case 'update_location':
                // Mise à jour de la géolocalisation du coursier
                $latitude = floatval($_POST['latitude'] ?? 0);
                $longitude = floatval($_POST['longitude'] ?? 0);
                $accuracy = floatval($_POST['accuracy'] ?? 0);
                
                if ($latitude && $longitude) {
                    $stmt = $pdo->prepare("
                        UPDATE agents_suzosky 
                        SET latitude = ?, longitude = ?, accuracy = ?, derniere_position = NOW() 
                        WHERE id_coursier = ?
                    ");
                    $stmt->execute([$latitude, $longitude, $accuracy, $coursier_id]);
                    echo json_encode(['success' => true, 'message' => 'Position mise à jour']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Coordonnées invalides']);
                }
                exit;
                
            case 'get_business_assignments':
                // Récupérer les affectations business automatiques du jour
                $date_livraison = $_POST['date'] ?? date('Y-m-d');

                // Nettoyage: code Google Directions supprimé; on retourne les lignes SQL uniquement
                $stmt = $pdo->prepare(
                    "SELECT bl.*, bfl.nom_original, bcl.nom_entreprise, bcl.contact_nom,
                            bl.adresse_prise_en_charge, bl.adresse_livraison,
                            bl.description_colis, bl.tarif_livraison,
                            CASE 
                                WHEN bl.statut = 'affectee_auto' THEN 'assigned_auto'
                                WHEN bl.statut = 'en_cours' THEN 'in_progress'
                                WHEN bl.statut = 'livree' THEN 'delivered'
                                ELSE 'pending'
                            END as assignment_status
                     FROM business_livraisons bl
                     JOIN business_fichiers_livraison bfl ON bl.fichier_id = bfl.id_fichier
                     JOIN business_clients bcl ON bfl.business_id = bcl.id_business
                     WHERE DATE(bl.date_livraison_prevue) = ?
                       AND (bl.coursier_id = ? OR bl.coursier_id IS NULL)
                     ORDER BY bl.date_creation DESC
                     LIMIT 50"
                );
                $stmt->execute([$date_livraison, $coursier_id]);
                $rows = $stmt->fetchAll();
                echo json_encode(['success' => true, 'assignments' => $rows]);
                exit;
                
            case 'start_business_delivery':
                // Démarrer une livraison business
                $livraison_id = intval($_POST['livraison_id'] ?? 0);
                
                $stmt = $pdo->prepare("
                    UPDATE business_livraisons 
                    SET statut = 'en_cours', date_debut_livraison = NOW() 
                    WHERE id = ? AND coursier_id = ? AND statut = 'affectee_auto'
                ");
                
                $result = $stmt->execute([$livraison_id, $coursier_id]);
                
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Livraison démarrée']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors du démarrage']);
                }
                exit;
                
            case 'complete_business_delivery':
                // Terminer une livraison business
                $livraison_id = intval($_POST['livraison_id'] ?? 0);
                $commentaire = trim($_POST['commentaire'] ?? '');
                $photo_url = $_POST['photo_url'] ?? '';
                
                $stmt = $pdo->prepare("
                    UPDATE business_livraisons 
                    SET statut = 'livree', date_livraison_reelle = NOW(), 
                        commentaire_coursier = ?, photo_livraison = ?
                    WHERE id = ? AND coursier_id = ? AND statut = 'en_cours'
                ");
                
                $result = $stmt->execute([$commentaire, $photo_url, $livraison_id, $coursier_id]);
                
                if ($result && $stmt->rowCount() > 0) {
                    // Mettre à jour les gains du coursier
                    $stmt = $pdo->prepare("
                        UPDATE agents_suzosky 
                        SET solde_wallet = solde_wallet + (
                            SELECT tarif_livraison FROM business_livraisons WHERE id = ?
                        ) 
                        WHERE id_coursier = ?
                    ");
                    $stmt->execute([$livraison_id, $coursier_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Livraison terminée avec succès']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la finalisation']);
                }
                exit;
                
            case 'check_app_updates':
                // Vérification automatique des mises à jour
                $current_version = $_POST['current_version'] ?? '1.0.0';
                $device_id = $_POST['device_id'] ?? '';
                
                // Récupérer la dernière version
                $stmt = $pdo->prepare("
                    SELECT version, build_number, force_update, apk_url, release_notes 
                    FROM app_versions 
                    WHERE is_active = 1 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute();
                $latest = $stmt->fetch();
                
                if ($latest) {
                    $needs_update = version_compare($current_version, $latest['version'], '<');
                    
                    // Enregistrer la vérification
                    if ($device_id) {
                        $stmt = $pdo->prepare("
                            INSERT INTO update_checks (device_id, current_version, latest_version, needs_update) 
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            current_version = VALUES(current_version),
                            latest_version = VALUES(latest_version),
                            needs_update = VALUES(needs_update),
                            last_check_time = NOW()
                        ");
                        $stmt->execute([$device_id, $current_version, $latest['version'], $needs_update]);
                    }
                    
                    echo json_encode([
                        'needs_update' => $needs_update,
                        'force_update' => $latest['force_update'],
                        'latest_version' => $latest['version'],
                        'download_url' => $latest['apk_url'],
                        'release_notes' => $latest['release_notes']
                    ]);
                } else {
                    echo json_encode(['needs_update' => false]);
                }
                exit;
                
            // ===== NOUVELLES ACTIONS V2.1 - BILLING SYSTEM =====
            case 'get_billing_info':
                // Récupérer les informations de compte du coursier
                try {
                    $account = $billing->getAccountInfo($coursier_id);
                    echo json_encode([
                        'success' => true,
                        'account' => $account
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                exit;
                
            case 'get_billing_details':
                // Récupérer les détails complets de facturation avec solde/crédit séparés
                try {
                    // Informations du compte avec séparation solde/crédit
                    $stmt = $pdo->prepare("
                        SELECT 
                            COALESCE(delivery_earnings, 0) as delivery_earnings,
                            COALESCE(credit_balance, 0) as credit_balance,
                            (COALESCE(delivery_earnings, 0) + COALESCE(credit_balance, 0)) as solde_total
                        FROM agents_suzosky 
                        WHERE id_coursier = ?
                    ");
                    $stmt->execute([$coursier_id]);
                    $account = $stmt->fetch() ?: [
                        'delivery_earnings' => 0, 
                        'credit_balance' => 0, 
                        'solde_total' => 0
                    ];
                    
                    // Statistiques du mois - gains bruts et nets
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as livraisons_mois,
                            COALESCE(SUM(montant_livraison), 0) as gains_bruts_mois,
                            COALESCE(SUM(montant_livraison * 0.9), 0) as gains_nets_mois
                        FROM transactions_coursiers 
                        WHERE coursier_id = ? 
                        AND type_transaction = 'credit' 
                        AND MONTH(date_transaction) = MONTH(CURRENT_DATE()) 
                        AND YEAR(date_transaction) = YEAR(CURRENT_DATE())
                    ");
                    $stmt->execute([$coursier_id]);
                    $stats = $stmt->fetch() ?: [
                        'livraisons_mois' => 0, 
                        'gains_bruts_mois' => 0,
                        'gains_nets_mois' => 0
                    ];
                    
                    // Recharges du mois
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0) as recharges_mois
                        FROM payment_transactions 
                        WHERE coursier_id = ? 
                        AND status = 'completed'
                        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                        AND YEAR(created_at) = YEAR(CURRENT_DATE())
                    ");
                    $stmt->execute([$coursier_id]);
                    $recharges = $stmt->fetch();
                    $stats['recharges_mois'] = $recharges['recharges_mois'] ?? 0;
                    
                    // Commission du mois (10% des gains bruts)
                    $stats['commission_mois'] = $stats['gains_bruts_mois'] * 0.1;
                    
                    // Transactions récentes (10 dernières) avec sources multiples
                    $stmt = $pdo->prepare("
                        (SELECT 
                            'livraison' as source,
                            type_transaction as type, 
                            montant_livraison as montant, 
                            description_transaction as description, 
                            date_transaction 
                        FROM transactions_coursiers 
                        WHERE coursier_id = ?)
                        UNION ALL
                        (SELECT 
                            'recharge' as source,
                            'credit' as type,
                            amount as montant,
                            'Recharge CINETPAY' as description,
                            created_at as date_transaction
                        FROM payment_transactions 
                        WHERE coursier_id = ? AND status = 'completed')
                        ORDER BY date_transaction DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$coursier_id, $coursier_id]);
                    $transactions = $stmt->fetchAll();
                    
                    // Reformater le type pour l'affichage
                    foreach ($transactions as &$transaction) {
                        $transaction['type'] = $transaction['type'] === 'credit' ? 'credit' : 'debit';
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'account' => $account,
                            'stats' => $stats,
                            'transactions' => $transactions
                        ]
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                exit;
                
            case 'recharge_account':
                // Demander une recharge de compte
                $montant = intval($_POST['montant'] ?? 0);
                
                if ($montant < 1000) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Montant minimum: 1000 FCFA'
                    ]);
                    exit;
                }
                
                try {
                    // Enregistrer la demande de recharge
                    $stmt = $pdo->prepare("
                        INSERT INTO demandes_recharge 
                        (coursier_id, montant_demande, statut_demande, date_demande) 
                        VALUES (?, ?, 'en_attente', NOW())
                    ");
                    $stmt->execute([$coursier_id, $montant]);
                    
                    // Récupérer numéro de téléphone pour SMS
                    $stmt = $pdo->prepare("SELECT telephone FROM agents_suzosky WHERE id_coursier = ?");
                    $stmt->execute([$coursier_id]);
                    $coursier = $stmt->fetch();
                    
                    if ($coursier) {
                        // Envoyer notification admin
                        $stmt = $pdo->prepare("
                            INSERT INTO admin_actions 
                            (admin_id, action_type, target_id, description, ip_address) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $description = "Demande recharge - Coursier {$coursier_id}: " . number_format($montant, 0, ',', ' ') . " FCFA";
                        $stmt->execute([
                            'RECHARGE_SYSTEM',
                            'recharge_request', 
                            $coursier_id,
                            $description,
                            $_SERVER['REMOTE_ADDR']
                        ]);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Demande de recharge enregistrée. Vous recevrez les instructions par SMS.'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erreur lors de la demande de recharge'
                    ]);
                }
                exit;
                
            case 'check_billing_status':
                // Vérifier l'état du compte pour recevoir des commandes
                try {
                    $canReceiveOrders = $billing->peutRecevoirCommandes($coursier_id);
                    $account = $billing->getAccountInfo($coursier_id);
                    
                    echo json_encode([
                        'success' => true,
                        'can_receive_orders' => $canReceiveOrders,
                        'balance' => $account['solde'],
                        'status_message' => $canReceiveOrders ? 
                            'Compte actif - Vous pouvez recevoir des commandes' : 
                            'Solde insuffisant - Minimum 3000 FCFA requis'
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                exit;
                
            case 'get_stats':
                // Statistiques du coursier pour commandes classiques et business
                $stats = [
                    'courses_today' => 0,
                    'gains_today' => 0,
                    'courses_total' => 0,
                    'gains_total' => 0,
                    'commandes_classiques_today' => 0,
                    'business_livraisons_today' => 0,
                    'statut' => 'disponible'
                ];
                
                // Commandes classiques du jour
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(tarif_livraison), 0) as gains
                    FROM commandes_classiques 
                    WHERE coursier_id = ? AND DATE(date_creation) = CURDATE() AND statut = 'livree'
                ");
                $stmt->execute([$coursier_id]);
                $classiques_today = $stmt->fetch();
                
                // Livraisons business du jour
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM business_livraisons 
                    WHERE coursier_id = ? AND DATE(date_creation) = CURDATE() AND statut = 'livree'
                ");
                $stmt->execute([$coursier_id]);
                $business_today = $stmt->fetch();
                
                $stats['courses_today'] = $classiques_today['count'] + $business_today['count'];
                $stats['gains_today'] = $classiques_today['gains']; // TODO: Ajouter tarifs business
                $stats['commandes_classiques_today'] = $classiques_today['count'];
                $stats['business_livraisons_today'] = $business_today['count'];
                
                // Total général commandes classiques
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(tarif_livraison), 0) as gains
                    FROM commandes_classiques 
                    WHERE coursier_id = ? AND statut = 'livree'
                ");
                $stmt->execute([$coursier_id]);
                $total_classiques = $stmt->fetch();
                
                // Total business
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM business_livraisons 
                    WHERE coursier_id = ? AND statut = 'livree'
                ");
                $stmt->execute([$coursier_id]);
                $total_business = $stmt->fetch();
                
                $stats['courses_total'] = $total_classiques['count'] + $total_business['count'];
                $stats['gains_total'] = $total_classiques['gains'];
                
                // Statut coursier
                $stmt = $pdo->prepare("SELECT statut_disponibilite FROM agents_suzosky WHERE id_coursier = ?");
                $stmt->execute([$coursier_id]);
                $statut = $stmt->fetchColumn();
                $stats['statut'] = $statut ?: 'disponible';
                
                echo json_encode($stats);
                exit;
                
            case 'get_commandes':
                // Commandes classiques disponibles ou assignées
                $commandes_classiques = [];
                $sql = "
                    SELECT c.*, 
                           'classique' as type_commande,
                           CASE 
                               WHEN c.coursier_id = ? THEN 'assigned'
                               WHEN c.coursier_id IS NULL AND c.statut = 'nouvelle' THEN 'available'
                               ELSE 'unavailable'
                           END as availability
                    FROM commandes_classiques c 
                    WHERE (c.coursier_id = ? OR (c.coursier_id IS NULL AND c.statut = 'nouvelle'))
                    ORDER BY c.date_creation DESC
                    LIMIT 20
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$coursier_id, $coursier_id]);
                $commandes_classiques = $stmt->fetchAll();
                
                // Livraisons business disponibles ou assignées  
                $livraisons_business = [];
                $sql_business = "
                    SELECT bl.*, bcl.nom_entreprise,
                           'business' as type_commande,
                           CASE 
                               WHEN bl.coursier_id = ? THEN 'assigned'
                               WHEN bl.coursier_id IS NULL AND bl.statut = 'nouvelle' THEN 'available'
                               ELSE 'unavailable'
                           END as availability
                    FROM business_livraisons bl
                    JOIN business_fichiers_livraison bfl ON bl.fichier_id = bfl.id_fichier
                    JOIN business_clients bcl ON bfl.business_id = bcl.id_business
                    WHERE (bl.coursier_id = ? OR (bl.coursier_id IS NULL AND bl.statut = 'nouvelle'))
                    ORDER BY bl.date_creation DESC
                    LIMIT 20
                ";
                
                $stmt = $pdo->prepare($sql_business);
                $stmt->execute([$coursier_id, $coursier_id]);
                $livraisons_business = $stmt->fetchAll();
                
                // Combiner les résultats
                $toutes_commandes = array_merge($commandes_classiques, $livraisons_business);
                
                echo json_encode($toutes_commandes);
                exit;
                
            case 'accept_commande':
                $commande_id = $_POST['commande_id'] ?? 0;
                $type_commande = $_POST['type_commande'] ?? 'classique';
                
                if ($type_commande === 'classique') {
                    // Vérifier que la commande classique est disponible
                    $stmt = $pdo->prepare("SELECT id FROM commandes_classiques WHERE id = ? AND coursier_id IS NULL AND statut = 'nouvelle'");
                    $stmt->execute([$commande_id]);
                    
                    if ($stmt->fetch()) {
                        // Assigner la commande au coursier
                        $stmt = $pdo->prepare("UPDATE commandes_classiques SET coursier_id = ?, statut = 'acceptee', date_acceptation = NOW() WHERE id = ?");
                        $stmt->execute([$coursier_id, $commande_id]);
                        
                        echo json_encode(['success' => true, 'message' => 'Commande classique acceptée']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Commande classique non disponible']);
                    }
                } else if ($type_commande === 'business') {
                    // Vérifier que la livraison business est disponible
                    $stmt = $pdo->prepare("SELECT id FROM business_livraisons WHERE id = ? AND coursier_id IS NULL AND statut = 'nouvelle'");
                    $stmt->execute([$commande_id]);
                    
                    if ($stmt->fetch()) {
                        // Assigner la livraison au coursier
                        $stmt = $pdo->prepare("UPDATE business_livraisons SET coursier_id = ?, statut = 'acceptee', date_acceptation = NOW() WHERE id = ?");
                        $stmt->execute([$coursier_id, $commande_id]);
                        
                        echo json_encode(['success' => true, 'message' => 'Livraison business acceptée']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Livraison business non disponible']);
                    }
                }
                exit;
                
            case 'update_statut':
                $nouveau_statut = $_POST['statut'] ?? '';
                
                if (in_array($nouveau_statut, ['disponible', 'indisponible', 'en_course'])) {
                    $stmt = $pdo->prepare("UPDATE agents_suzosky SET statut_disponibilite = ? WHERE id_coursier = ?");
                    $stmt->execute([$nouveau_statut, $coursier_id]);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Statut invalide']);
                }
                exit;
                
            case 'complete_commande':
                $commande_id = $_POST['commande_id'] ?? 0;
                $type_commande = $_POST['type_commande'] ?? 'classique';
                $commentaire = trim($_POST['commentaire'] ?? '');
                
                if ($type_commande === 'classique') {
                    // Marquer la commande classique comme livrée
                    $stmt = $pdo->prepare("UPDATE commandes_classiques SET statut = 'livree', date_livraison_reelle = NOW(), commentaire_coursier = ? WHERE id = ? AND coursier_id = ?");
                    $stmt->execute([$commentaire, $commande_id, $coursier_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Commande classique livrée']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erreur lors de la livraison']);
                    }
                } else if ($type_commande === 'business') {
                    // Marquer la livraison business comme livrée
                    $stmt = $pdo->prepare("UPDATE business_livraisons SET statut = 'livree', date_livraison_reelle = NOW(), commentaire_coursier = ? WHERE id = ? AND coursier_id = ?");
                    $stmt->execute([$commentaire, $commande_id, $coursier_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Livraison business confirmée']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erreur lors de la confirmation']);
                    }
                }
                exit;
                $stmt->execute([$commande_id, $coursier_id]);
                
                echo json_encode(['success' => true, 'message' => 'Commande marquée comme livrée']);
                exit;
                
            case 'get_business_livraisons':
                // Livraisons business disponibles ou assignées au coursier
                $sql = "
                    SELECT bl.*, 
                           bf.nom_original as fichier_nom,
                           bc.nom_entreprise,
                           CASE 
                               WHEN bl.coursier_id = ? THEN 'assigned'
                               WHEN bl.coursier_id IS NULL AND bl.statut = 'nouvelle' THEN 'available'
                               ELSE 'unavailable'
                           END as availability
                    FROM business_livraisons bl
                    JOIN business_fichiers_livraison bf ON bl.fichier_id = bf.id_fichier
                    JOIN business_clients bc ON bf.business_id = bc.id_business
                    WHERE (bl.coursier_id = ? OR (bl.coursier_id IS NULL AND bl.statut = 'nouvelle'))
                    ORDER BY bl.date_creation DESC
                    LIMIT 50
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$coursier_id, $coursier_id]);
                $livraisons = $stmt->fetchAll();
                
                echo json_encode($livraisons);
                exit;
                
            case 'accept_business_livraison':
                $livraison_id = $_POST['livraison_id'] ?? 0;
                
                // Vérifier que la livraison est disponible
                $stmt = $pdo->prepare("SELECT id FROM business_livraisons WHERE id = ? AND coursier_id IS NULL AND statut = 'nouvelle'");
                $stmt->execute([$livraison_id]);
                
                if ($stmt->fetch()) {
                    // Assigner la livraison au coursier
                    $stmt = $pdo->prepare("
                        UPDATE business_livraisons 
                        SET coursier_id = ?, statut = 'acceptee', date_acceptation = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$coursier_id, $livraison_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Livraison business acceptée']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Livraison non disponible']);
                }
                exit;
                
            case 'complete_business_livraison':
                $livraison_id = $_POST['livraison_id'] ?? 0;
                
                // Marquer la livraison business comme terminée
                $stmt = $pdo->prepare("
                    UPDATE business_livraisons 
                    SET statut = 'livree', date_livraison_reelle = NOW() 
                    WHERE id = ? AND coursier_id = ?
                ");
                $stmt->execute([$livraison_id, $coursier_id]);
                
                echo json_encode(['success' => true, 'message' => 'Livraison business terminée']);
                exit;
                
            case 'initiate_cinetpay_recharge':
                // Initier un paiement CINETPAY pour recharge de compte
                $montant = intval($_POST['montant'] ?? 0);
                
                if ($montant < 1000) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Montant minimum: 1000 FCFA'
                    ]);
                    exit;
                }
                
                if ($montant > 500000) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Montant maximum: 500 000 FCFA'
                    ]);
                    exit;
                }
                
                try {
                    global $cinetpay;
                    
                    // Initier le paiement CINETPAY
                    $result = $cinetpay->initiateRecharge($coursier_id, $montant);
                    
                    if ($result['success']) {
                        echo json_encode([
                            'success' => true,
                            'payment_url' => $result['payment_url'],
                            'transaction_id' => $result['transaction_id'],
                            'message' => 'Redirection vers le paiement...'
                        ]);
                        
                        // Log de l'initiation
                        error_log("CINETPAY initiation réussie - Coursier: {$coursier_id}, Montant: {$montant}, Transaction: {$result['transaction_id']}");
                        
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Erreur lors de l\'initiation du paiement: ' . $result['error']
                        ]);
                        
                        // Log de l'erreur
                        error_log("CINETPAY initiation échouée - Coursier: {$coursier_id}, Montant: {$montant}, Erreur: " . $result['error']);
                    }
                    
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erreur système lors de l\'initiation du paiement'
                    ]);
                    
                    error_log("CINETPAY Exception - Coursier: {$coursier_id}, Erreur: " . $e->getMessage());
                }
                exit;
                
            case 'check_payment_status':
                // Vérifier le statut d'un paiement CINETPAY
                $transactionId = $_POST['transaction_id'] ?? '';
                
                if (empty($transactionId)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID de transaction requis'
                    ]);
                    exit;
                }
                
                try {
                    global $cinetpay;
                    
                    $result = $cinetpay->checkPaymentStatus($transactionId);
                    
                    echo json_encode([
                        'success' => true,
                        'status' => $result['status'],
                        'amount' => $result['amount'] ?? 0,
                        'message' => $result['message'] ?? ''
                    ]);
                    
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erreur lors de la vérification du paiement'
                    ]);
                }
                exit;
                
            case 'regenerer_mot_de_passe_agent':
                // Régénération de mot de passe agent depuis l'interface coursier
                if (!$pdo) {
                    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
                    exit;
                }
                
                $agentId = $_POST['agent_id'] ?? '';
                if (empty($agentId)) {
                    echo json_encode(['success' => false, 'error' => 'ID agent manquant']);
                    exit;
                }
                
                try {
                    // Générer un nouveau mot de passe sécurisé de 5 caractères
                    $nouveauMotDePasse = generateUnifiedAgentPassword(5);
                    
                    // Déterminer quelle table utiliser
                    $stmt = $pdo->query("SHOW TABLES LIKE 'agents_suzosky'");
                    $useAgentsSuzosky = $stmt->rowCount() > 0;
                    
                    if ($useAgentsSuzosky) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE id = ?");
                        $stmt->execute([$agentId]);
                        $agentExists = $stmt->fetchColumn() > 0;
                        
                        if ($agentExists) {
                            // Mettre à jour dans agents_suzosky
                            $stmt = $pdo->prepare("UPDATE agents_suzosky SET password = ? WHERE id = ?");
                            $result = $stmt->execute([$nouveauMotDePasse, $agentId]);
                        } else {
                            throw new Exception('Agent non trouvé dans agents_suzosky');
                        }
                    } else {
                        // Fallback vers coursiers
                        $hashedPassword = password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE agents_suzosky SET mot_de_passe = ?, password_plain = ? WHERE id = ?");
                        $result = $stmt->execute([$hashedPassword, $nouveauMotDePasse, $agentId]);
                    }
                    
                    if (!$result) {
                        throw new Exception('Erreur lors de la mise à jour du mot de passe');
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Nouveau mot de passe généré avec succès',
                        'nouveau_mot_de_passe' => $nouveauMotDePasse,
                        'agent_id' => $agentId
                    ]);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            default:
                error_log("COURSIER_DEBUG: Action non reconnue: " . ($_POST['action'] ?? 'undefined'));
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
                exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Récupérer les informations du coursier connecté
$coursierInfo = null;
if ($isLoggedIn && isset($_SESSION['coursier_id'])) {
    try {
        $pdo = getDBConnection();
        $table = $_SESSION['coursier_table'] ?? 'agents_suzosky';
        
        if ($table === 'agents_suzosky') {
            $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE id = ?");
            $stmt->execute([$_SESSION['coursier_id']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE id_coursier = ?");
            $stmt->execute([$_SESSION['coursier_id']]);
        }
        
        $coursierInfo = $stmt->fetch();
    } catch (Exception $e) {
        // Erreur silencieuse - essayer l'autre table en fallback
        try {
            if ($table === 'agents_suzosky') {
                $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE id_coursier = ?");
                $stmt->execute([$_SESSION['coursier_id']]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE id = ?");
                $stmt->execute([$_SESSION['coursier_id']]);
            }
            $coursierInfo = $stmt->fetch();
        } catch (Exception $e2) {
            error_log("Erreur récupération coursierInfo: " . $e2->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Coursier - Suzosky</title>
    <meta name="description" content="Interface coursier Suzosky - Gestion des courses et gains">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Système de notifications sonores V2.1 -->
    <script>
        // Intégration du système de sons Suzosky
        class SuzoskyNotificationSounds {
            constructor() {
                this.sounds = {};
                this.volume = 0.7;
                this.isEnabled = localStorage.getItem('suzosky_sounds_enabled') !== 'false';
                this.initializeSounds();
            }
            
            initializeSounds() {
                this.sounds.newOrder = this.createBeepSequence([
                    {freq: 800, duration: 200, delay: 0},
                    {freq: 1000, duration: 200, delay: 250},
                    {freq: 1200, duration: 300, delay: 500}
                ]);
                
                this.sounds.supportMessage = this.createBeepSequence([
                    {freq: 600, duration: 150, delay: 0},
                    {freq: 800, duration: 150, delay: 200}
                ]);
                
                this.sounds.orderAccepted = this.createBeepSequence([
                    {freq: 500, duration: 100, delay: 0},
                    {freq: 700, duration: 100, delay: 120},
                    {freq: 900, duration: 200, delay: 240}
                ]);
                
                this.sounds.deliveryComplete = this.createBeepSequence([
                    {freq: 400, duration: 200, delay: 0},
                    {freq: 600, duration: 200, delay: 250},
                    {freq: 800, duration: 200, delay: 500},
                    {freq: 1000, duration: 400, delay: 750}
                ]);
                
                this.sounds.lowBalance = this.createBeepSequence([
                    {freq: 300, duration: 300, delay: 0},
                    {freq: 250, duration: 300, delay: 400},
                    {freq: 200, duration: 500, delay: 800}
                ]);
                
                this.sounds.urgentSupport = this.createBeepSequence([
                    {freq: 1200, duration: 100, delay: 0},
                    {freq: 1200, duration: 100, delay: 150},
                    {freq: 1200, duration: 100, delay: 300},
                    {freq: 1500, duration: 200, delay: 450}
                ]);
            }
            
            createBeepSequence(sequence) {
                return () => {
                    if (!this.isEnabled) return;
                    sequence.forEach(beep => {
                        setTimeout(() => this.playBeep(beep.freq, beep.duration), beep.delay);
                    });
                };
            }
            
            playBeep(frequency, duration) {
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = frequency;
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                    gainNode.gain.linearRampToValueAtTime(this.volume, audioContext.currentTime + 0.01);
                    gainNode.gain.linearRampToValueAtTime(0, audioContext.currentTime + duration / 1000);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + duration / 1000);
                } catch (error) {
                    console.log('Son non disponible');
                }
            }
            
            playNewOrder() { this.sounds.newOrder(); }
            playSupportMessage() { this.sounds.supportMessage(); }
            playOrderAccepted() { this.sounds.orderAccepted(); }
            playDeliveryComplete() { this.sounds.deliveryComplete(); }
            playLowBalance() { this.sounds.lowBalance(); }
            playUrgentSupport() { this.sounds.urgentSupport(); }
            
            toggle() {
                this.isEnabled = !this.isEnabled;
                localStorage.setItem('suzosky_sounds_enabled', this.isEnabled);
                return this.isEnabled;
            }
        }
        
        // Instance globale
        window.suzoskySounds = new SuzoskyNotificationSounds();
        
        // Activer l'audio au premier clic
        document.addEventListener('click', function enableAudio() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }
            } catch (e) {}
            document.removeEventListener('click', enableAudio);
        }, { once: true });
    </script>
    
    <style>
        /* DESIGN SYSTEM SUZOSKY - INTERFACE COURSIER */
        :root {
            --primary-gold: #D4A853;
            --primary-dark: #1A1A2E;
            --secondary-blue: #16213E;
            --accent-blue: #0F3460;
            --accent-red: #E94560;
            --success-color: #27AE60;
            --glass-bg: rgba(255,255,255,0.08);
            --glass-border: rgba(255,255,255,0.2);
            --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #E94560;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--gradient-dark);
            color: white;
            min-height: 100vh;
            overflow-x: hidden;
        }

        <?php if (!$isLoggedIn): ?>
        /* === STYLES CONNEXION/INSCRIPTION === */
        .auth-screen {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-name {
            font-size: 2.2rem;
            font-weight: 900;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            letter-spacing: 2px;
        }

        .brand-subtitle {
            color: var(--primary-gold);
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            opacity: 0.9;
        }

        .auth-tabs {
            display: flex;
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 5px;
            margin-bottom: 30px;
        }

        .auth-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            background: none;
            border: none;
            color: rgba(255,255,255,0.7);
        }

        .auth-tab.active {
            background: var(--primary-gold);
            color: var(--primary-dark);
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
        }

        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
        }

        /* === SYSTEME DE FORMULAIRES - CHARTE SUZOSKY === */
        .form-group {
            margin-bottom: 28px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: rgba(255,255,255,0.95);
            font-weight: 700;
            margin-bottom: 12px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            position: relative;
        }
        
        .form-group label::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--gradient-gold);
            border-radius: 2px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 18px 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 2px solid var(--glass-border);
            border-radius: 15px;
            color: white;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            overflow: hidden;
        }
        
        .form-group input::placeholder {
            color: rgba(255,255,255,0.5);
            font-weight: 500;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 8px 35px rgba(212, 168, 83, 0.3);
            background: rgba(255,255,255,0.08);
            transform: translateY(-2px);
        }
        
        .form-group input:hover,
        .form-group select:hover {
            border-color: rgba(212, 168, 83, 0.6);
            background: rgba(255,255,255,0.06);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .file-upload {
            border: 2px dashed var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.05);
        }

        .file-upload:hover {
            border-color: var(--primary-gold);
            background: rgba(212, 168, 83, 0.1);
        }

        .file-upload input {
            display: none;
        }

        .upload-text {
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* === BOUTONS PRINCIPAUX - CHARTE SUZOSKY === */
        .btn-primary {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            box-shadow: 0 8px 25px rgba(212, 168, 83, 0.3);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(212, 168, 83, 0.5);
            filter: brightness(1.1);
        }

        /* === SYSTEME D'ALERTES - CHARTE SUZOSKY === */
        .alert {
            padding: 20px 25px;
            border-radius: 18px;
            margin-bottom: 25px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            letter-spacing: 0.3px;
            border: 2px solid;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* === BOUTONS PRINCIPAUX - CHARTE SUZOSKY === */
        .btn-primary {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            box-shadow: 0 8px 25px rgba(212, 168, 83, 0.3);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(212, 168, 83, 0.5);
            filter: brightness(1.1);
        }

        /* === SYSTEME D'ALERTES - CHARTE SUZOSKY === */
        .alert {
            padding: 20px 25px;
            border-radius: 18px;
            margin-bottom: 25px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            letter-spacing: 0.3px;
            border: 2px solid;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .alert:hover::before {
            left: 100%;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            border-color: #28a745;
            color: #28a745;
            box-shadow: 0 8px 30px rgba(40, 167, 69, 0.2);
        }
        
        .alert-success:hover {
            background: rgba(40, 167, 69, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(40, 167, 69, 0.3);
        }

        .alert-error {
            background: rgba(233, 69, 96, 0.15);
            border-color: var(--accent-red);
            color: var(--accent-red);
            box-shadow: 0 8px 30px rgba(233, 69, 96, 0.2);
            animation: errorPulse 2s infinite;
        }
        
        @keyframes errorPulse {
            0%, 100% { box-shadow: 0 8px 30px rgba(233, 69, 96, 0.2); }
            50% { box-shadow: 0 12px 40px rgba(233, 69, 96, 0.4); }
        }
        
        .alert-error:hover {
            background: rgba(233, 69, 96, 0.25);
            transform: translateY(-2px);
            animation: none;
        }

        <?php else: ?>
        /* === STYLES INTERFACE COURSIER CONNECTÉ === */
        .coursier-interface {
            min-height: 100vh;
            padding: 30px;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px 30px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }

        .welcome-section h1 {
            font-size: 2rem;
            font-weight: 700;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .matricule-display {
            color: var(--primary-gold);
            font-weight: 600;
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* === SOUND TOGGLE SYSTEM - CHARTE SUZOSKY === */
        .sound-toggle {
            background: var(--glass-bg);
            border: 2px solid var(--glass-border);
            color: white;
            padding: 12px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        
        .sound-toggle::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .sound-toggle:hover::before {
            left: 100%;
        }
        
        .sound-toggle:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .sound-toggle.enabled {
            background: rgba(40, 167, 69, 0.15);
            border-color: #28a745;
            color: #28a745;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.25);
            animation: enabledPulse 2s infinite;
        }
        
        @keyframes enabledPulse {
            0%, 100% { box-shadow: 0 4px 20px rgba(40, 167, 69, 0.25); }
            50% { box-shadow: 0 6px 30px rgba(40, 167, 69, 0.4); }
        }
        
        .sound-toggle.enabled:hover {
            background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
            color: white;
            box-shadow: 0 12px 35px rgba(40, 167, 69, 0.5);
        }
        
        .sound-toggle.disabled {
            background: rgba(233, 69, 96, 0.15);
            border-color: var(--accent-red);
            color: var(--accent-red);
            box-shadow: 0 4px 20px rgba(233, 69, 96, 0.25);
        }
        
        .sound-toggle.disabled:hover {
            background: linear-gradient(135deg, var(--accent-red) 0%, #ff6b6b 100%);
            color: white;
            box-shadow: 0 12px 35px rgba(233, 69, 96, 0.5);
        }

        /* === TOGGLES DE STATUT - CHARTE SUZOSKY === */
        .statut-toggle {
            background: var(--glass-bg);
            border: 2px solid var(--glass-border);
            border-radius: 15px;
            padding: 12px 24px;
            color: white;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }
        
        .statut-toggle::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .statut-toggle:hover::before {
            left: 100%;
        }
        
        .statut-toggle:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .statut-disponible { 
            border-color: var(--success-color); 
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.12);
            box-shadow: 0 6px 25px rgba(40, 167, 69, 0.2);
        }
        
        .statut-disponible:hover {
            background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
            color: white;
            box-shadow: 0 12px 35px rgba(40, 167, 69, 0.4);
        }
        
        .statut-en-course { 
            border-color: var(--warning-color); 
            color: var(--warning-color);
            background: rgba(255, 193, 7, 0.12);
            box-shadow: 0 6px 25px rgba(255, 193, 7, 0.2);
            animation: workingPulse 2s infinite;
        }
        
        @keyframes workingPulse {
            0%, 100% { box-shadow: 0 6px 25px rgba(255, 193, 7, 0.2); }
            50% { box-shadow: 0 10px 35px rgba(255, 193, 7, 0.4); }
        }
        
        .statut-en-course:hover {
            background: linear-gradient(135deg, #ffc107 0%, #ffcd39 100%);
            color: var(--primary-dark);
            animation: none;
        }
        .statut-pause { border-color: var(--danger-color); color: var(--danger-color); }

        .logout-btn {
            background: rgba(233, 69, 96, 0.1);
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: var(--accent-red);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px 25px;
            text-align: center;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-gold);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 0 40px rgba(212, 168, 83, 0.2),
                0 20px 50px rgba(0,0,0,0.5);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--primary-gold);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .section-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .commandes-grid {
            display: grid;
            gap: 20px;
        }

        .commande-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .commande-item:hover {
            background: rgba(255,255,255,0.08);
            border-color: var(--primary-gold);
        }

        .commande-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .commande-id {
            font-weight: 600;
            color: var(--primary-gold);
        }

        .commande-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-available {
            background: rgba(40, 167, 69, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .status-assigned {
            background: rgba(255, 193, 7, 0.2);
            color: var(--warning-color);
            border: 1px solid var(--warning-color);
        }

        .commande-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            font-size: 0.9rem;
        }

        .detail-label {
            color: rgba(255,255,255,0.7);
            margin-bottom: 3px;
        }

        .detail-value {
            font-weight: 600;
        }

        .commande-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.8rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-accept {
            background: var(--success-color);
            color: white;
        }

        .btn-complete {
            background: var(--primary-gold);
            color: var(--primary-dark);
        }

        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Styles Business Section */
        .business-info-banner {
            background: rgba(212, 168, 83, 0.1);
            border: 1px solid var(--primary-gold);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .banner-icon {
            font-size: 2.5rem;
            color: var(--primary-gold);
        }

        .banner-text {
            flex: 1;
            color: rgba(255,255,255,0.9);
        }

        .banner-text strong {
            color: var(--primary-gold);
            font-size: 1.1rem;
        }

        .banner-text small {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .btn-refresh {
            background: var(--primary-gold);
            color: var(--primary-dark);
            border: none;
            padding: 10px 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-refresh:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
        }

        .business-commande-card {
            background: linear-gradient(135deg, rgba(212, 168, 83, 0.1) 0%, rgba(26, 26, 46, 0.9) 100%);
            border: 1px solid rgba(212, 168, 83, 0.3);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .business-commande-card::before {
            content: '🏢';
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            opacity: 0.3;
        }

        .business-commande-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(212, 168, 83, 0.2);
            border-color: var(--primary-gold);
        }

        .business-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(212, 168, 83, 0.2);
        }

        .business-name {
            color: var(--primary-gold);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .business-file-id {
            background: rgba(212, 168, 83, 0.2);
            color: var(--primary-gold);
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .business-client-info {
            margin-bottom: 15px;
        }

        .client-name {
            color: #FFFFFF;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .client-address {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .client-phone {
            color: var(--primary-gold);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .business-delivery-time {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 15px;
            text-align: center;
        }

        .delivery-date {
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 3px;
        }

        .delivery-hour {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
        }

        .business-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 15px;
        }

        .status-business-nouvelle {
            background: rgba(39, 174, 96, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .status-business-acceptee {
            background: rgba(255, 193, 7, 0.2);
            color: #FFC107;
            border: 1px solid #FFC107;
        }

        .status-business-en-cours {
            background: rgba(33, 150, 243, 0.2);
            color: #2196F3;
            border: 1px solid #2196F3;
        }

        .status-business-livree {
            background: rgba(212, 168, 83, 0.2);
            color: var(--primary-gold);
            border: 1px solid var(--primary-gold);
        }

        .business-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-business-accept {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-business-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
        }

        .btn-business-complete {
            background: var(--success-color);
            color: #FFFFFF;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-business-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .coursier-interface {
                padding: 15px;
            }
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .commande-details {
                grid-template-columns: 1fr;
            }
        }
        <?php endif; ?>

        .hidden { display: none; }
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--primary-gold);
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- === INTERFACE CONNEXION/INSCRIPTION === -->
<div class="auth-screen">
    <div class="auth-container">
        <div class="auth-header">
            <h1 class="brand-name">SUZOSKY</h1>
            <p class="brand-subtitle">ESPACE COURSIER</p>
        </div>

        <!-- Onglets -->
        <div class="auth-tabs">
            <button class="auth-tab active" onclick="showAuthForm('login')">
                <i class="fas fa-sign-in-alt"></i> Connexion
            </button>
            <button class="auth-tab" onclick="showAuthForm('register')">
                <i class="fas fa-user-plus"></i> Inscription
            </button>
        </div>

        <!-- Messages -->
        <?php if (isset($loginError)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($registerSuccess)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($registerSuccess) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($registerError)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($registerError) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de connexion -->
        <form class="auth-form active" id="loginForm" method="POST">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="identifier">Matricule, Téléphone ou Email</label>
                <input type="text" name="identifier" id="identifier" required 
                       placeholder="Votre matricule ou numéro">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required 
                       placeholder="Votre mot de passe">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        <!-- Formulaire d'inscription -->
        <form class="auth-form" id="registerForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="register">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" name="nom" id="nom" required>
                </div>
                <div class="form-group">
                    <label for="prenoms">Prénoms *</label>
                    <input type="text" name="prenoms" id="prenoms" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date_naissance">Date de naissance</label>
                    <input type="date" name="date_naissance" id="date_naissance">
                </div>
                <div class="form-group">
                    <label for="lieu_naissance">Lieu de naissance</label>
                    <input type="text" name="lieu_naissance" id="lieu_naissance">
                </div>
            </div>

            <div class="form-group">
                <label for="lieu_residence">Lieu de résidence</label>
                <input type="text" name="lieu_residence" id="lieu_residence" 
                       placeholder="Commune, quartier">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="telephone">Téléphone *</label>
                    <input type="tel" name="telephone" id="telephone" required
                           placeholder="07 XX XX XX XX">
                </div>
                <div class="form-group">
                    <label for="type_poste">Type de coursier</label>
                    <select name="type_poste" id="type_poste">
                        <option value="coursier_moto">Coursier Moto</option>
                        <option value="coursier_cargo">Coursier Cargo</option>
                    </select>
                </div>
            </div>

            <!-- Upload documents -->
            <div class="form-group full-width">
                <label>Pièce d'identité (Recto)</label>
                <div class="file-upload" onclick="document.getElementById('piece_recto').click()">
                    <i class="fas fa-camera fa-2x"></i>
                    <div class="upload-text">Cliquez pour prendre une photo</div>
                    <input type="file" id="piece_recto" name="piece_recto" accept="image/*" capture="camera">
                </div>
            </div>

            <div class="form-group full-width">
                <label>Pièce d'identité (Verso)</label>
                <div class="file-upload" onclick="document.getElementById('piece_verso').click()">
                    <i class="fas fa-camera fa-2x"></i>
                    <div class="upload-text">Cliquez pour prendre une photo</div>
                    <input type="file" id="piece_verso" name="piece_verso" accept="image/*" capture="camera">
                </div>
            </div>

            <div class="form-group full-width">
                <label>Permis de conduire (Recto)</label>
                <div class="file-upload" onclick="document.getElementById('permis_recto').click()">
                    <i class="fas fa-camera fa-2x"></i>
                    <div class="upload-text">Cliquez pour prendre une photo</div>
                    <input type="file" id="permis_recto" name="permis_recto" accept="image/*" capture="camera">
                </div>
            </div>

            <div class="form-group full-width">
                <label>Permis de conduire (Verso)</label>
                <div class="file-upload" onclick="document.getElementById('permis_verso').click()">
                    <i class="fas fa-camera fa-2x"></i>
                    <div class="upload-text">Cliquez pour prendre une photo</div>
                    <input type="file" id="permis_verso" name="permis_verso" accept="image/*" capture="camera">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Envoyer candidature
            </button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- === INTERFACE COURSIER CONNECTÉ === -->
<div class="coursier-interface">
    <!-- Header -->
    <div class="top-header">
        <div class="welcome-section">
            <h1>Bonjour <?= htmlspecialchars($_SESSION['coursier_nom']) ?></h1>
            <div class="matricule-display">
                <i class="fas fa-id-card"></i> 
                Matricule: <?= htmlspecialchars($_SESSION['coursier_matricule'] ?? 'En attente') ?>
            </div>
        </div>
        
        <div class="header-actions">
            <div class="statut-toggle" id="statutToggle">
                <i class="fas fa-circle"></i>
                <span id="statutText">Disponible</span>
            </div>
            
            <button class="sound-toggle enabled" id="soundToggle" onclick="toggleSounds()" title="Activer/Désactiver les sons">
                <i class="fas fa-volume-up"></i>
            </button>
            
            <a href="?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-route stat-icon"></i>
            <div class="stat-value" id="coursesToday">-</div>
            <div class="stat-label">Courses aujourd'hui</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-coins stat-icon"></i>
            <div class="stat-value" id="gainsToday">- FCFA</div>
            <div class="stat-label">Gains du jour</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-trophy stat-icon"></i>
            <div class="stat-value" id="coursesTotal">-</div>
            <div class="stat-label">Total courses</div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-wallet stat-icon"></i>
            <div class="stat-value" id="gainsTotal">- FCFA</div>
            <div class="stat-label">Gains totaux</div>
        </div>
    </div>

    <!-- Section Commandes Classiques -->
    <div class="section-card">
        <h2 class="section-title">
            <i class="fas fa-shipping-fast"></i>
            📦 Commandes Classiques
        </h2>
        
        <div class="business-info-banner">
            <div class="banner-icon">🎯</div>
            <div class="banner-text">
                <strong>Commandes de particuliers</strong><br>
                <small>Livraisons directes client - Paiement immédiat</small>
            </div>
            <button class="btn-refresh" onclick="loadCommandes()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        
        <div class="commandes-grid" id="commandesClassiquesContainer">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i> Chargement des commandes classiques...
            </div>
        </div>
    </div>

    <!-- Section Courses Business -->
    <div class="section-card">
        <h2 class="section-title">
            <i class="fas fa-building"></i>
            🏢 Courses Business
        </h2>
        
        <div class="business-info-banner">
            <div class="banner-icon">📋</div>
            <div class="banner-text">
                <strong>Livraisons en masse pour entreprises</strong><br>
                <small>Fichiers Excel traités automatiquement - Suivi temps réel</small>
            </div>
            <button class="btn-refresh" onclick="loadBusinessLivraisons()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
        
        <div class="commandes-grid" id="businessContainer">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i> Chargement des courses business...
            </div>
        </div>
    </div>

    <!-- Section Historique -->
    <div class="section-card">
        <h2 class="section-title">
            <i class="fas fa-history"></i>
            Historique des courses
        </h2>
        
        <div class="commandes-grid" id="historiqueContainer">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i> Chargement...
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if (!$isLoggedIn): ?>
// === SCRIPTS CONNEXION/INSCRIPTION ===
function showAuthForm(formType) {
    // Gérer les onglets
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.auth-form').forEach(form => {
        form.classList.remove('active');
    });
    
    // Activer l'onglet et le formulaire
    event.target.classList.add('active');
    document.getElementById(formType + 'Form').classList.add('active');
}

// Prévisualisation des images uploadées
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const uploadDiv = this.parentElement;
            const uploadText = uploadDiv.querySelector('.upload-text');
            uploadText.textContent = `✓ ${file.name}`;
            uploadDiv.style.borderColor = 'var(--primary-gold)';
            uploadDiv.style.background = 'rgba(212, 168, 83, 0.1)';
        }
    });
});

<?php else: ?>
// === SCRIPTS INTERFACE COURSIER ===
let currentStatut = 'disponible';

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCommandes();
    setupStatutToggle();
    
    // Actualisation automatique
    setInterval(loadStats, 30000); // 30 secondes
    setInterval(loadCommandes, 60000); // 1 minute
});

// Charger les statistiques
async function loadStats() {
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax=true&action=get_stats'
        });
        
        const data = await response.json();
        
        document.getElementById('coursesToday').textContent = data.courses_today;
        document.getElementById('gainsToday').textContent = new Intl.NumberFormat('fr-FR').format(data.gains_today) + ' FCFA';
        document.getElementById('coursesTotal').textContent = data.courses_total;
        document.getElementById('gainsTotal').textContent = new Intl.NumberFormat('fr-FR').format(data.gains_total) + ' FCFA';
        
        // Mettre à jour le statut
        updateStatutDisplay(data.statut);
        
    } catch (error) {
        console.error('Erreur chargement stats:', error);
    }
}

// Charger les commandes
async function loadCommandes() {
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax=true&action=get_commandes'
        });
        
        const commandes = await response.json();
        
        // Séparer commandes classiques et business
        const commandesClassiques = commandes.filter(c => c.type_commande === 'classique');
        const commandesBusiness = commandes.filter(c => c.type_commande === 'business');
        
        displayCommandesClassiques(commandesClassiques);
        displayCommandesBusiness(commandesBusiness);
        
    } catch (error) {
        console.error('Erreur chargement commandes:', error);
        document.getElementById('commandesClassiquesContainer').innerHTML = 
            '<div style="text-align: center; color: #E94560;">Erreur de chargement</div>';
        document.getElementById('businessContainer').innerHTML = 
            '<div style="text-align: center; color: #E94560;">Erreur de chargement</div>';
    }
}

// Afficher commandes classiques
function displayCommandesClassiques(commandes) {
    const container = document.getElementById('commandesClassiquesContainer');
    
    if (commandes.length > 0) {
        container.innerHTML = commandes.map(commande => `
            <div class="commande-item">
                <div class="commande-header">
                    <div class="commande-id">${commande.numero_commande}</div>
                    <div class="commande-status ${getStatusClass(commande.availability)}">
                        ${getStatusText(commande.statut, commande.availability)}
                    </div>
                </div>
                
                <div class="commande-details">
                    <div class="detail-item">
                        <div class="detail-label">Client</div>
                        <div class="detail-value">${commande.client_nom} ${commande.client_prenoms || ''}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Téléphone</div>
                        <div class="detail-value">${commande.client_telephone}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Destination</div>
                        <div class="detail-value">${commande.ville}, ${commande.commune}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Adresse</div>
                        <div class="detail-value">${commande.adresse_complete}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tarif</div>
                        <div class="detail-value" style="color: var(--primary-gold); font-weight: 700;">
                            ${new Intl.NumberFormat('fr-FR').format(commande.tarif_livraison || 0)} FCFA
                        </div>
                    </div>
                    ${commande.description_colis ? `
                        <div class="detail-item">
                            <div class="detail-label">Description</div>
                            <div class="detail-value">${commande.description_colis}</div>
                        </div>
                    ` : ''}
                    ${commande.instructions_speciales ? `
                        <div class="detail-item">
                            <div class="detail-label">Instructions</div>
                            <div class="detail-value">${commande.instructions_speciales}</div>
                        </div>
                    ` : ''}
                </div>
                
                <div class="commande-actions">
                    ${commande.availability === 'available' ? 
                        `<button class="btn-sm btn-accept" onclick="accepterCommande(${commande.id}, 'classique')">
                            <i class="fas fa-check"></i> Accepter
                        </button>` : 
                        (commande.statut === 'acceptee' ?
                            `<button class="btn-sm btn-complete" onclick="terminerCommande(${commande.id}, 'classique')">
                                <i class="fas fa-flag-checkered"></i> Terminer
                            </button>` : '')
                    }
                </div>
            </div>
        `).join('');
    } else {
        container.innerHTML = '<div style="text-align: center; padding: 20px; opacity: 0.7;">Aucune commande classique disponible</div>';
    }
}

// Afficher commandes business
function displayCommandesBusiness(livraisons) {
    const container = document.getElementById('businessContainer');
    
    if (livraisons.length > 0) {
        container.innerHTML = livraisons.map(livraison => `
            <div class="commande-item">
                <div class="commande-header">
                    <div class="commande-id">Business #${livraison.id}</div>
                    <div class="commande-status ${getStatusClass(livraison.availability)}">
                        ${getStatusText(livraison.statut, livraison.availability)}
                    </div>
                </div>
                
                <div class="commande-details">
                    <div class="detail-item">
                        <div class="detail-label">Entreprise</div>
                        <div class="detail-value">${livraison.nom_entreprise}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Client</div>
                        <div class="detail-value">${livraison.client_nom} ${livraison.client_prenoms || ''}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Téléphone</div>
                        <div class="detail-value">${livraison.telephone}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Destination</div>
                        <div class="detail-value">${livraison.ville}, ${livraison.commune}</div>
                    </div>
                    ${livraison.adresse_complete ? `
                        <div class="detail-item">
                            <div class="detail-label">Adresse</div>
                            <div class="detail-value">${livraison.adresse_complete}</div>
                        </div>
                    ` : ''}
                    ${livraison.date_livraison_souhaitee ? `
                        <div class="detail-item">
                            <div class="detail-label">Date souhaitée</div>
                            <div class="detail-value">${new Date(livraison.date_livraison_souhaitee).toLocaleDateString('fr-FR')}</div>
                        </div>
                    ` : ''}
                    ${livraison.heure_livraison_souhaitee ? `
                        <div class="detail-item">
                            <div class="detail-label">Heure souhaitée</div>
                            <div class="detail-value">${livraison.heure_livraison_souhaitee}</div>
                        </div>
                    ` : ''}
                    ${livraison.instructions_speciales ? `
                        <div class="detail-item">
                            <div class="detail-label">Instructions</div>
                            <div class="detail-value">${livraison.instructions_speciales}</div>
                        </div>
                    ` : ''}
                </div>
                
                <div class="commande-actions">
                    ${livraison.availability === 'available' ? 
                        `<button class="btn-sm btn-accept" onclick="accepterCommande(${livraison.id}, 'business')">
                            <i class="fas fa-check"></i> Accepter
                        </button>` : 
                        (livraison.statut === 'acceptee' ?
                            `<button class="btn-sm btn-complete" onclick="terminerCommande(${livraison.id}, 'business')">
                                <i class="fas fa-flag-checkered"></i> Confirmer livraison
                            </button>` : '')
                    }
                </div>
            </div>
        `).join('');
    } else {
        container.innerHTML = '<div style="text-align: center; padding: 20px; opacity: 0.7;">Aucune livraison business disponible</div>';
    }
}

// Fonctions helper pour les statuts
function getStatusClass(availability) {
    switch(availability) {
        case 'available': return 'status-available';
        case 'assigned': return 'status-assigned';
        default: return 'status-unavailable';
    }
}

function getStatusText(statut, availability) {
    if (availability === 'available') return 'Disponible';
    if (availability === 'assigned') {
        switch(statut) {
            case 'acceptee': return 'Acceptée';
            case 'en_cours': return 'En cours';
            case 'livree': return 'Livrée';
            default: return 'Assignée';
        }
    }
    return 'Non disponible';
}
    const historique = commandes.filter(c => c.statut === 'livree');
    
    // Commandes disponibles/assignées
    if (commandesDisponibles.length > 0) {
        container.innerHTML = commandesDisponibles.map(commande => `
            <div class="commande-item">
                <div class="commande-header">
                    <div class="commande-id">#${commande.id}</div>
                    <div class="commande-status ${commande.availability === 'available' ? 'status-available' : 'status-assigned'}">
                        ${commande.availability === 'available' ? 'Disponible' : 'Assignée'}
                    </div>
                </div>
                
                <div class="commande-details">
                    <div class="detail-item">
                        <div class="detail-label">Client</div>
                        <div class="detail-value">${commande.nom_client || 'Non renseigné'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Téléphone</div>
                        <div class="detail-value">${commande.telephone_client || 'Non renseigné'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Départ</div>
                        <div class="detail-value">${commande.adresse_depart || 'Non renseigné'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Destination</div>
                        <div class="detail-value">${commande.adresse_arrivee || 'Non renseigné'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Montant</div>
                        <div class="detail-value">${new Intl.NumberFormat('fr-FR').format(commande.montant_total || 0)} FCFA</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date</div>
                        <div class="detail-value">${new Date(commande.date_commande).toLocaleDateString('fr-FR')}</div>
                    </div>
                </div>
                
                <div class="commande-actions">
                    ${commande.availability === 'available' ? 
                        `<button class="btn-sm btn-accept" onclick="accepterCommande(${commande.id})">
                            <i class="fas fa-check"></i> Accepter
                        </button>` : 
                        (commande.statut === 'en_cours' ?
                            `<button class="btn-sm btn-complete" onclick="terminerCommande(${commande.id})">
                                <i class="fas fa-flag-checkered"></i> Terminer
                            </button>` : '')
                    }
                </div>
            </div>
        `).join('');
    } else {
        container.innerHTML = '<div style="text-align: center; padding: 20px; opacity: 0.7;">Aucune commande disponible</div>';
    }
    
    // Historique
    if (historique.length > 0) {
        historiqueContainer.innerHTML = historique.map(commande => `
            <div class="commande-item">
                <div class="commande-header">
                    <div class="commande-id">#${commande.id}</div>
                    <div class="commande-status" style="background: rgba(40, 167, 69, 0.2); color: var(--success-color); border: 1px solid var(--success-color);">
                        Livrée
                    </div>
                </div>
                
                <div class="commande-details">
                    <div class="detail-item">
                        <div class="detail-label">Client</div>
                        <div class="detail-value">${commande.nom_client || 'Non renseigné'}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Montant gagné</div>
                        <div class="detail-value" style="color: var(--primary-gold); font-weight: 700;">
                            ${new Intl.NumberFormat('fr-FR').format(commande.montant_coursier || 0)} FCFA
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date</div>
                        <div class="detail-value">${new Date(commande.date_commande).toLocaleDateString('fr-FR')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Livré le</div>
                        <div class="detail-value">${commande.date_livraison ? new Date(commande.date_livraison).toLocaleDateString('fr-FR') : '-'}</div>
                    </div>
                </div>
            </div>
        `).join('');
    } else {
        historiqueContainer.innerHTML = '<div style="text-align: center; padding: 20px; opacity: 0.7;">Aucun historique</div>';
    }
}

// Accepter une commande
async function accepterCommande(commandeId, typeCommande = 'classique') {
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=true&action=accept_commande&commande_id=${commandeId}&type_commande=${typeCommande}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Commande acceptée !', 'success');
            loadCommandes();
            loadStats();
        } else {
            showNotification(result.message || 'Erreur', 'error');
        }
        
    } catch (error) {
        showNotification('Erreur de connexion', 'error');
    }
}

// Terminer une commande
async function terminerCommande(commandeId, typeCommande = 'classique') {
    const message = typeCommande === 'business' ? 
        'Confirmer la livraison de cette commande business ?' : 
        'Confirmer la livraison de cette commande ?';
        
    if (!confirm(message)) return;
    
    // Demander un commentaire optionnel
    const commentaire = prompt('Commentaire de livraison (optionnel) :') || '';
    
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=true&action=complete_commande&commande_id=${commandeId}&type_commande=${typeCommande}&commentaire=${encodeURIComponent(commentaire)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message || 'Livraison confirmée !', 'success');
            loadCommandes();
            loadStats();
        } else {
            showNotification(result.message || 'Erreur', 'error');
        }
        
    } catch (error) {
        showNotification('Erreur de connexion', 'error');
    }
}

// Fonction spécifique pour charger les livraisons business
async function loadBusinessLivraisons() {
    // Cette fonction utilise la même logique que loadCommandes 
    // mais pourrait être étendue pour des filtres spécifiques business
    loadCommandes();
}

// Gestion du statut
function setupStatutToggle() {
    const statutToggle = document.getElementById('statutToggle');
    
    statutToggle.addEventListener('click', function() {
        const statuts = ['disponible', 'en_course', 'pause'];
        const currentIndex = statuts.indexOf(currentStatut);
        const nextStatut = statuts[(currentIndex + 1) % statuts.length];
        
        updateStatut(nextStatut);
    });
}

async function updateStatut(nouveauStatut) {
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=true&action=update_statut&statut=${nouveauStatut}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            updateStatutDisplay(nouveauStatut);
            showNotification(`Statut changé: ${getStatutLabel(nouveauStatut)}`, 'success');
        }
        
    } catch (error) {
        showNotification('Erreur changement statut', 'error');
    }
}

function updateStatutDisplay(statut) {
    currentStatut = statut;
    const statutToggle = document.getElementById('statutToggle');
    const statutText = document.getElementById('statutText');
    
    // Nettoyer les classes
    statutToggle.className = 'statut-toggle';
    
    // Ajouter la classe appropriée
    statutToggle.classList.add(`statut-${statut}`);
    statutText.textContent = getStatutLabel(statut);
}

function getStatutLabel(statut) {
    const labels = {
        'disponible': 'Disponible',
        'en_course': 'En course',
        'pause': 'En pause'
    };
    return labels[statut] || statut;
}

// Notifications
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? 'var(--success-color)' : 'var(--accent-red)'};
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        font-weight: 600;
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
    `;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// === FONCTIONS BUSINESS LIVRAISONS ===

// Charger les livraisons business
async /*
// FONCTION DUPLIQUÉE SUPPRIMÉE - loadBusinessLivraisons (doublon #1)

/*
// FONCTION DUPLIQUÉE SUPPRIMÉE - loadBusinessLivraisons #1
// 2025-08-26 08:57:33
function loadBusinessLivraisons() {
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax=true&action=get_business_livraisons'
        });
        
        const data = await response.json();
        renderBusinessLivraisons(data);
        
    } catch (error) {
        console.error('Erreur chargement livraisons business:', error);
        document.getElementById('businessContainer').innerHTML = 
            '<div style="text-align: center; padding: 20px; color: var(--accent-red);">Erreur de chargement</div>';
    }
}
*/

*/

// Afficher les livraisons business
function renderBusinessLivraisons(livraisons) {
    const container = document.getElementById('businessContainer');
    
    if (!livraisons || livraisons.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; opacity: 0.7;">
                <i class="fas fa-inbox fa-3x" style="margin-bottom: 20px; color: var(--primary-gold);"></i>
                <div>Aucune livraison business disponible</div>
                <small style="opacity: 0.6;">Les nouvelles livraisons apparaîtront ici</small>
            </div>
        `;
        return;
    }
    
    container.innerHTML = livraisons.map(livraison => {
        const availability = livraison.availability;
        const statusClass = getBusinessStatusClass(livraison.statut);
        const statusText = getBusinessStatusText(livraison.statut);
        
        return `
            <div class="commande-card business-commande-card">
                <div class="business-header">
                    <div class="business-name">
                        <i class="fas fa-building"></i> ${livraison.nom_entreprise}
                    </div>
                    <div class="business-file-id">
                        ${livraison.fichier_id}
                    </div>
                </div>
                
                <div class="business-client-info">
                    <div class="client-name">
                        👤 ${livraison.client_nom} ${livraison.client_prenoms || ''}
                    </div>
                    <div class="client-address">
                        📍 ${livraison.ville}, ${livraison.commune}
                    </div>
                    <div class="client-phone">
                        📞 ${livraison.telephone}
                    </div>
                </div>
                
                ${livraison.date_livraison_souhaitee ? `
                <div class="business-delivery-time">
                    <div class="delivery-date">
                        📅 ${new Date(livraison.date_livraison_souhaitee).toLocaleDateString('fr-FR')}
                    </div>
                    ${livraison.heure_livraison_souhaitee ? `
                    <div class="delivery-hour">
                        ⏰ ${livraison.heure_livraison_souhaitee}
                    </div>
                    ` : ''}
                </div>
                ` : ''}
                
                <div class="business-status ${statusClass}">
                    ${statusText}
                </div>
                
                ${livraison.instructions_speciales ? `
                <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem;">
                    <strong>Instructions:</strong> ${livraison.instructions_speciales}
                </div>
                ` : ''}
                
                <div class="business-actions">
                    ${availability === 'available' ? `
                        <button class="btn-business-accept" onclick="accepterBusinessLivraison(${livraison.id})">
                            <i class="fas fa-check"></i> Accepter
                        </button>
                    ` : availability === 'assigned' && livraison.statut === 'acceptee' ? `
                        <button class="btn-business-complete" onclick="terminerBusinessLivraison(${livraison.id})">
                            <i class="fas fa-check-double"></i> Marquer livrée
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

// Accepter une livraison business
async function accepterBusinessLivraison(livraisonId) {
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=true&action=accept_business_livraison&livraison_id=${livraisonId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Livraison business acceptée !', 'success');
            loadBusinessLivraisons();
            loadStats();
        } else {
            showNotification(result.message || 'Erreur', 'error');
        }
        
    } catch (error) {
        showNotification('Erreur de connexion', 'error');
    }
}

// Terminer une livraison business
async function terminerBusinessLivraison(livraisonId) {
    if (!confirm('Confirmer la livraison ? Cette action est irréversible.')) {
        return;
    }
    
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=true&action=complete_business_livraison&livraison_id=${livraisonId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Livraison business terminée !', 'success');
            loadBusinessLivraisons();
            loadStats();
        } else {
            showNotification(result.message || 'Erreur', 'error');
        }
        
    } catch (error) {
        showNotification('Erreur de connexion', 'error');
    }
}

// Helpers pour les statuts business
function getBusinessStatusClass(statut) {
    const statusMap = {
        'nouvelle': 'status-business-nouvelle',
        'acceptee': 'status-business-acceptee', 
        'en_cours': 'status-business-en-cours',
        'livree': 'status-business-livree'
    };
    return statusMap[statut] || 'status-business-nouvelle';
}

function getBusinessStatusText(statut) {
    const statusMap = {
        'nouvelle': '🆕 Nouvelle',
        'acceptee': '👍 Acceptée',
        'en_cours': '🚚 En cours',
        'livree': '✅ Livrée'
    };
    return statusMap[statut] || statut;
}

// Ajouter le chargement des livraisons business à l'initialisation
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCommandes();
    loadBusinessLivraisons(); // Nouvelle fonction
    setupStatutToggle();
    
    // Nouvelles fonctionnalités V2.1
    initChatSupport();
    initBillingSystem();
    
    // Actualisation automatique
    setInterval(loadStats, 30000); // 30 secondes
    setInterval(loadCommandes, 60000); // 1 minute
    setInterval(loadBusinessLivraisons, 60000); // 1 minute pour business aussi
});

// ===== SYSTÈME CHAT SUPPORT V2.1 =====
let chatSupport = {
    chatId: null,
    isOpen: false,
    unreadCount: 0,
    lastMessageId: 0,
    
    init() {
        this.createChatInterface();
        this.loadNotifications();
        this.startPolling();
    },
    
    createChatInterface() {
        const chatHtml = `
            <!-- Chat Support Coursier -->
            <div id="chatSupportContainer" class="chat-support-container">
                <div id="chatToggle" class="chat-toggle" onclick="chatSupport.toggleChat()">
                    <i class="fas fa-headset"></i>
                    <span id="chatBadge" class="chat-badge" style="display: none;">0</span>
                </div>
                
                <div id="chatWindow" class="chat-window" style="display: none;">
                    <div class="chat-header">
                        <h4><i class="fas fa-user-headset"></i> Support Coursier</h4>
                        <button onclick="chatSupport.closeChat()" class="chat-close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div id="chatMessages" class="chat-messages">
                        <div class="chat-loading">Initialisation du support...</div>
                    </div>
                    
                    <div class="chat-input-container">
                        <div class="chat-quick-actions">
                            <button onclick="chatSupport.sendQuickMessage('Problème GPS')" class="quick-btn">
                                <i class="fas fa-location-arrow"></i> GPS
                            </button>
                            <button onclick="chatSupport.sendQuickMessage('Problème avec un client')" class="quick-btn">
                                <i class="fas fa-user-times"></i> Client
                            </button>
                            <button onclick="chatSupport.sendLocationHelp()" class="quick-btn">
                                <i class="fas fa-map-marker-alt"></i> Position
                            </button>
                        </div>
                        <div class="chat-input-row">
                            <input type="text" id="chatMessageInput" placeholder="Tapez votre message..." 
                                   onkeypress="chatSupport.handleKeyPress(event)" maxlength="1000">
                            <button onclick="chatSupport.sendMessage()" class="chat-send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Système de facturation V2.1 -->
            <div id="billingModal" class="billing-modal" style="display: none;">
                <div class="billing-content">
                    <div class="billing-header">
                        <h3><i class="fas fa-wallet"></i> Mon Compte Coursier</h3>
                        <button onclick="billingSystem.closeBilling()" class="modal-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="billingDetails" class="billing-details">
                        <div class="billing-loading">Chargement des informations...</div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', chatHtml);
    },
    
    async initializeChat() {
        if (this.chatId) return this.chatId;
        
        try {
            const position = await this.getCurrentPosition();
            
            const response = await fetch('admin.php?section=chat_coursiers', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'init_coursier_chat',
                    coursier_id: <?php echo $coursier_id; ?>,
                    device_id: this.getDeviceId(),
                    location: position,
                    app_version: '2.1.0'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.chatId = result.chat_id;
                this.loadMessages();
                return this.chatId;
            } else {
                throw new Error(result.error);
            }
            
        } catch (error) {
            console.error('Erreur initialisation chat:', error);
            this.showChatError('Impossible de contacter le support');
        }
    },
    
    async sendMessage(message = null) {
        const input = document.getElementById('chatMessageInput');
        const messageText = message || input.value.trim();
        
        if (!messageText || !this.chatId) return;
        
        try {
            const position = await this.getCurrentPosition();
            
            const response = await fetch('admin.php?section=chat_coursiers', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'send_coursier_message',
                    chat_id: this.chatId,
                    coursier_id: <?php echo $coursier_id; ?>,
                    message: messageText,
                    location: position
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (!message) input.value = '';
                this.addMessageToChat(messageText, 'user');
                
                if (result.urgent_notification) {
                    showNotification('Message urgent envoyé - Support alerté', 'warning');
                }
            } else {
                throw new Error(result.error);
            }
            
        } catch (error) {
            showNotification('Erreur envoi message: ' + error.message, 'error');
        }
    },
    
    async sendQuickMessage(message) {
        await this.sendMessage(message);
    },
    
    async sendLocationHelp() {
        try {
            const position = await this.getCurrentPosition();
            
            const response = await fetch('admin.php?section=chat_coursiers', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'send_location_help',
                    coursier_id: <?php echo $coursier_id; ?>,
                    location: position,
                    problem_type: 'location_issue',
                    description: 'Demande d\'aide géolocalisation depuis l\'app'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Demande d\'aide géolocalisation envoyée - Code: ' + result.emergency_code, 'success');
            }
            
        } catch (error) {
            showNotification('Erreur demande d\'aide: ' + error.message, 'error');
        }
    },
    
    async loadMessages() {
        if (!this.chatId) return;
        
        try {
            const response = await fetch('admin.php?section=chat_coursiers', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_coursier_messages',
                    chat_id: this.chatId,
                    coursier_id: <?php echo $coursier_id; ?>,
                    last_message_id: this.lastMessageId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                result.messages.forEach(msg => {
                    this.addMessageToChat(msg.message, 'admin', msg.sender_name, msg.sent_at);
                    this.lastMessageId = Math.max(this.lastMessageId, msg.id);
                });
                
                this.updateAdminStatus(result.admin_online);
            }
            
        } catch (error) {
            console.error('Erreur chargement messages:', error);
        }
    },
    
    async loadNotifications() {
        try {
            const response = await fetch('admin.php?section=chat_coursiers', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_coursier_notifications',
                    coursier_id: <?php echo $coursier_id; ?>
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updateChatBadge(result.total_unread);
            }
            
        } catch (error) {
            console.error('Erreur notifications:', error);
        }
    },
    
    toggleChat() {
        const chatWindow = document.getElementById('chatWindow');
        this.isOpen = !this.isOpen;
        
        if (this.isOpen) {
            chatWindow.style.display = 'block';
            if (!this.chatId) {
                this.initializeChat();
            } else {
                this.loadMessages();
            }
            this.markMessagesRead();
        } else {
            chatWindow.style.display = 'none';
        }
    },
    
    closeChat() {
        this.isOpen = false;
        document.getElementById('chatWindow').style.display = 'none';
    },
    
    addMessageToChat(message, type, senderName = null, timestamp = null) {
        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message chat-message-${type}`;
        
        const time = timestamp ? new Date(timestamp).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) : 
                    new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
        
        if (type === 'admin') {
            messageDiv.innerHTML = `
                <div class="message-sender">${senderName || 'Support'}</div>
                <div class="message-content">${message}</div>
                <div class="message-time">${time}</div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="message-content">${message}</div>
                <div class="message-time">${time}</div>
            `;
        }
        
        // Supprimer le message de chargement s'il existe
        const loading = messagesContainer.querySelector('.chat-loading');
        if (loading) loading.remove();
        
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    },
    
    updateChatBadge(count) {
        const badge = document.getElementById('chatBadge');
        this.unreadCount = count;
        
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    },
    
    updateAdminStatus(isOnline) {
        const chatHeader = document.querySelector('.chat-header h4');
        if (chatHeader) {
            const statusIcon = isOnline ? '<i class="fas fa-circle" style="color: #28a745; font-size: 8px;"></i>' : 
                                         '<i class="fas fa-circle" style="color: #6c757d; font-size: 8px;"></i>';
            chatHeader.innerHTML = `<i class="fas fa-user-headset"></i> Support Coursier ${statusIcon}`;
        }
    },
    
    async markMessagesRead() {
        if (!this.chatId) return;
        
        try {
            await fetch('admin.php?section=chat_coursiers', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'mark_messages_read',
                    chat_id: this.chatId,
                    coursier_id: <?php echo $coursier_id; ?>
                })
            });
            
            this.updateChatBadge(0);
            
        } catch (error) {
            console.error('Erreur marquage lecture:', error);
        }
    },
    
    handleKeyPress(event) {
        if (event.key === 'Enter') {
            this.sendMessage();
        }
    },
    
    startPolling() {
        setInterval(() => {
            this.loadNotifications();
            if (this.isOpen && this.chatId) {
                this.loadMessages();
            }
        }, 15000); // Poll toutes les 15 secondes
    },
    
    showChatError(message) {
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.innerHTML = `<div class="chat-error">${message}</div>`;
    },
    
    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    }),
                    error => resolve({latitude: null, longitude: null})
                );
            } else {
                resolve({latitude: null, longitude: null});
            }
        });
    },
    
    getDeviceId() {
        let deviceId = localStorage.getItem('suzosky_device_id');
        if (!deviceId) {
            deviceId = 'COURSIER_' + Date.now() + '_' + Math.random().toString(36).substring(2);
            localStorage.setItem('suzosky_device_id', deviceId);
        }
        return deviceId;
    }
};

// ===== SYSTÈME DE FACTURATION V2.1 =====
let billingSystem = {
    init() {
        this.createBillingButton();
        this.loadAccountInfo();
    },
    
    createBillingButton() {
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            const billingBtn = document.createElement('button');
            billingBtn.className = 'billing-toggle-btn';
            billingBtn.innerHTML = '<i class="fas fa-wallet"></i> Mon Compte';
            billingBtn.onclick = () => this.showBilling();
            headerActions.insertBefore(billingBtn, headerActions.lastElementChild);
        }
    },
    
    async loadAccountInfo() {
        try {
            const response = await fetch('coursier.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ajax=true&action=get_billing_info'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updateAccountDisplay(result.account);
            }
            
        } catch (error) {
            console.error('Erreur chargement compte:', error);
        }
    },
    
    showBilling() {
        document.getElementById('billingModal').style.display = 'block';
        this.loadBillingDetails();
    },
    
    closeBilling() {
        document.getElementById('billingModal').style.display = 'none';
    },
    
    async loadBillingDetails() {
        const container = document.getElementById('billingDetails');
        container.innerHTML = '<div class="billing-loading">Chargement...</div>';
        
        try {
            const response = await fetch('coursier.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ajax=true&action=get_billing_details'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.displayBillingDetails(result.data);
            } else {
                container.innerHTML = '<div class="billing-error">Erreur chargement données</div>';
            }
            
        } catch (error) {
            container.innerHTML = '<div class="billing-error">Erreur de connexion</div>';
        }
    },
    
    displayBillingDetails(data) {
        const container = document.getElementById('billingDetails');
        
        // Calculer le solde disponible total
        const soldeTotal = (data.account.delivery_earnings || 0) + (data.account.credit_balance || 0);
        const isAccountActive = soldeTotal >= 3000;
        
        const html = `
            <div class="account-balance">
                <!-- Solde total avec indicateur visuel -->
                <div class="balance-card ${isAccountActive ? 'balance-ok' : 'balance-low'}">
                    <i class="fas fa-wallet"></i>
                    <div class="balance-info">
                        <div class="balance-label">Solde Total Disponible</div>
                        <div class="balance-amount">${new Intl.NumberFormat('fr-FR').format(soldeTotal)} FCFA</div>
                        <div class="balance-status">
                            ${isAccountActive ? 
                                '<i class="fas fa-check-circle"></i> Compte actif - Peut recevoir des commandes' : 
                                '<i class="fas fa-exclamation-triangle"></i> Recharge requise (minimum 3000 FCFA)'}
                        </div>
                    </div>
                </div>
                
                <!-- Détail des sources de solde -->
                <div class="balance-breakdown">
                    <div class="balance-source">
                        <div class="source-card earnings">
                            <i class="fas fa-truck"></i>
                            <div class="source-info">
                                <div class="source-label">Gains Livraisons</div>
                                <div class="source-amount">${new Intl.NumberFormat('fr-FR').format(data.account.delivery_earnings || 0)} FCFA</div>
                                <div class="source-note">Commission 10% déduite</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="balance-source">
                        <div class="source-card credit">
                            <i class="fas fa-credit-card"></i>
                            <div class="source-info">
                                <div class="source-label">Crédit Rechargé</div>
                                <div class="source-amount">${new Intl.NumberFormat('fr-FR').format(data.account.credit_balance || 0)} FCFA</div>
                                <div class="source-note">Via Mobile Money/Wave/Carte</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="billing-stats">
                <div class="stat-item">
                    <i class="fas fa-delivery-truck"></i>
                    <div class="stat-info">
                        <div class="stat-label">Livraisons ce mois</div>
                        <div class="stat-value">${data.stats.livraisons_mois || 0}</div>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-coins"></i>
                    <div class="stat-info">
                        <div class="stat-label">Gains Bruts du mois</div>
                        <div class="stat-value">${new Intl.NumberFormat('fr-FR').format(data.stats.gains_bruts_mois || 0)} FCFA</div>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-percentage"></i>
                    <div class="stat-info">
                        <div class="stat-label">Commission 10%</div>
                        <div class="stat-value">${new Intl.NumberFormat('fr-FR').format((data.stats.gains_bruts_mois || 0) * 0.1)} FCFA</div>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-info">
                        <div class="stat-label">Recharges ce mois</div>
                        <div class="stat-value">${new Intl.NumberFormat('fr-FR').format(data.stats.recharges_mois || 0)} FCFA</div>
                    </div>
                </div>
            </div>
            
            ${!isAccountActive ? `
                <div class="recharge-section">
                    <h4><i class="fas fa-plus-circle"></i> Recharger le compte</h4>
                    <p>Minimum requis: 3000 FCFA pour recevoir des commandes</p>
                    <div class="recharge-options">
                        <button onclick="billingSystem.rechargeAccount(3000)" class="recharge-btn">
                            Recharger 3000 FCFA
                        </button>
                        <button onclick="billingSystem.rechargeAccount(5000)" class="recharge-btn">
                            Recharger 5000 FCFA
                        </button>
                        <button onclick="billingSystem.rechargeAccount(10000)" class="recharge-btn">
                            Recharger 10000 FCFA
                        </button>
                    </div>
                </div>
            ` : ''}
            
            <div class="recent-transactions">
                <h4><i class="fas fa-history"></i> Transactions récentes</h4>
                ${data.transactions.length > 0 ? 
                    data.transactions.map(t => `
                        <div class="transaction-item">
                            <div class="transaction-type">
                                <i class="fas fa-${t.type === 'debit' ? 'minus' : 'plus'}-circle"></i>
                                ${t.description}
                            </div>
                            <div class="transaction-amount ${t.type}">
                                ${t.type === 'debit' ? '-' : '+'}${new Intl.NumberFormat('fr-FR').format(t.montant)} FCFA
                            </div>
                            <div class="transaction-date">${new Date(t.date_transaction).toLocaleDateString('fr-FR')}</div>
                        </div>
                    `).join('') : 
                    '<div class="no-transactions">Aucune transaction récente</div>'
                }
            </div>
        `;
        
        container.innerHTML = html;
    },
    
    async rechargeAccount(montant) {
        if (!confirm(`Confirmer la recharge de ${new Intl.NumberFormat('fr-FR').format(montant)} FCFA via CINETPAY ?\n\nVous serez redirigé vers la page de paiement sécurisée.`)) {
            return;
        }
        
        // Afficher le loading
        const loadingOverlay = this.showLoadingOverlay('Initialisation du paiement CINETPAY...');
        
        try {
            const response = await fetch('coursier.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=true&action=initiate_cinetpay_recharge&montant=${montant}`
            });
            
            const result = await response.json();
            
            if (result.success && result.payment_url) {
                // Fermer le modal de facturation
                this.closeBilling();
                
                // Notification de succès d'initiation
                showNotification('Redirection vers CINETPAY...', 'success');
                
                // Jouer le son de notification
                if (window.notificationSounds && window.notificationSounds.isEnabled) {
                    window.notificationSounds.playNewOrder();
                }
                
                // Sauvegarder l'ID de transaction pour suivi
                localStorage.setItem('suzosky_pending_payment', result.transaction_id);
                
                // Rediriger vers CINETPAY après 2 secondes
                setTimeout(() => {
                    this.hideLoadingOverlay(loadingOverlay);
                    window.location.href = result.payment_url;
                }, 2000);
                
                // Log pour le debugging
                console.log('CINETPAY Payment initiated:', {
                    transaction_id: result.transaction_id,
                    amount: montant,
                    payment_url: result.payment_url
                });
                
            } else {
                this.hideLoadingOverlay(loadingOverlay);
                showNotification(result.message || 'Erreur lors de l\'initiation du paiement', 'error');
                
                // Son d'erreur
                if (window.notificationSounds && window.notificationSounds.isEnabled) {
                    window.notificationSounds.playLowBalance();
                }
            }
            
        } catch (error) {
            this.hideLoadingOverlay(loadingOverlay);
            showNotification('Erreur de connexion lors de l\'initiation du paiement', 'error');
            console.error('CINETPAY Error:', error);
            
            // Son d'erreur
            if (window.notificationSounds && window.notificationSounds.isEnabled) {
                window.notificationSounds.playLowBalance();
            }
        }
    },
    
    // Nouvelle méthode pour afficher le loading overlay
    showLoadingOverlay(message) {
        const overlay = document.createElement('div');
        overlay.className = 'cinetpay-loading-overlay';
        overlay.innerHTML = `
            <div class="cinetpay-loading-content">
                <div class="cinetpay-loading-spinner"></div>
                <div class="cinetpay-loading-text">${message}</div>
                <div class="cinetpay-loading-subtitle">Veuillez patienter...</div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        // Animation d'apparition
        setTimeout(() => overlay.classList.add('show'), 100);
        
        return overlay;
    },
    
    // Nouvelle méthode pour masquer le loading overlay
    hideLoadingOverlay(overlay) {
        if (overlay && overlay.parentNode) {
            overlay.classList.remove('show');
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 300);
        }
    },
    
    // Nouvelle méthode pour vérifier le statut des paiements en attente
    async checkPendingPayments() {
        const pendingPayment = localStorage.getItem('suzosky_pending_payment');
        if (!pendingPayment) return;
        
        try {
            const response = await fetch('coursier.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=true&action=check_payment_status&transaction_id=${pendingPayment}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.status === 'completed') {
                    // Paiement réussi
                    localStorage.removeItem('suzosky_pending_payment');
                    showNotification(`Recharge de ${new Intl.NumberFormat('fr-FR').format(result.amount)} FCFA effectuée avec succès !`, 'success');
                    
                    // Actualiser les informations du compte
                    this.loadAccountInfo();
                    this.loadBillingDetails();
                    
                    // Son de succès
                    if (window.notificationSounds && window.notificationSounds.isEnabled) {
                        window.notificationSounds.playDeliveryComplete();
                    }
                    
                } else if (result.status === 'failed') {
                    // Paiement échoué
                    localStorage.removeItem('suzosky_pending_payment');
                    showNotification('Votre paiement a échoué. Veuillez réessayer.', 'error');
                }
                // Si status = 'pending', on ne fait rien et on vérifiera plus tard
            }
            
        } catch (error) {
            console.error('Erreur vérification paiement:', error);
        }
    },
    
    updateAccountDisplay(account) {
        // Mettre à jour l'affichage du solde dans l'interface principale
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            let balanceDisplay = headerActions.querySelector('.balance-display');
            if (!balanceDisplay) {
                balanceDisplay = document.createElement('div');
                balanceDisplay.className = 'balance-display';
                headerActions.insertBefore(balanceDisplay, headerActions.firstChild);
            }
            
            const balanceClass = account.solde >= 3000 ? 'balance-ok' : 'balance-low';
            balanceDisplay.innerHTML = `
                <div class="balance-mini ${balanceClass}">
                    <i class="fas fa-wallet"></i>
                    <span>${new Intl.NumberFormat('fr-FR').format(account.solde)} FCFA</span>
                </div>
            `;
        }
    }
};

// Fonctions d'initialisation V2.1
function initChatSupport() {
    chatSupport.init();
}

function initBillingSystem() {
    billingSystem.init();
}

// ===== CONTRÔLE SONS V2.1 =====
function toggleSounds() {
    const isEnabled = suzoskySounds.toggle();
    const toggleBtn = document.getElementById('soundToggle');
    const icon = toggleBtn.querySelector('i');
    
    if (isEnabled) {
        toggleBtn.className = 'sound-toggle enabled';
        icon.className = 'fas fa-volume-up';
        toggleBtn.title = 'Désactiver les sons';
        showNotification('Sons activés', 'success');
    } else {
        toggleBtn.className = 'sound-toggle disabled';
        icon.className = 'fas fa-volume-mute';
        toggleBtn.title = 'Activer les sons';
        showNotification('Sons désactivés', 'info');
    }
}

// Intégrer les sons aux notifications existantes
/*
// FONCTION DUPLIQUÉE SUPPRIMÉE - showNotification (doublon #1)

/*
// FONCTION DUPLIQUÉE SUPPRIMÉE - showNotification #1
// 2025-08-26 08:57:33
function showNotification(message, type, playSound = true) {
    // Fonction notification existante...
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animation d'apparition
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Sons selon le type de notification
    if (playSound && suzoskySounds) {
        if (message.includes('Commande acceptée') || message.includes('acceptée')) {
            suzoskySounds.playOrderAccepted();
        } else if (message.includes('terminée') || message.includes('Livraison terminée')) {
            suzoskySounds.playDeliveryComplete();
        } else if (message.includes('solde') || message.includes('recharge')) {
            if (message.includes('Solde insuffisant') || message.includes('3000 FCFA')) {
                suzoskySounds.playLowBalance();
            } else {
                suzoskySounds.playOrderAccepted();
            }
        } else if (message.includes('urgent') || message.includes('Support alerté')) {
            suzoskySounds.playUrgentSupport();
        } else if (type === 'success') {
            suzoskySounds.playOrderAccepted();
        }
    }
    
    // Suppression automatique
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}
*/

*/

// Améliorer les fonctions de commandes avec sons
async /*
// FONCTION DUPLIQUÉE SUPPRIMÉE - accepterCommande (doublon #1)

/*
// FONCTION DUPLIQUÉE SUPPRIMÉE - accepterCommande #1
// 2025-08-26 08:57:33
function accepterCommande(commandeId, type = 'classique') {
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=true&action=accept_commande&commande_id=${commandeId}&type=${type}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Son de succès
            suzoskySounds.playOrderAccepted();
            showNotification('Commande acceptée avec succès !', 'success', false);
            loadCommandes();
            loadStats();
        } else {
            showNotification(result.message || 'Erreur lors de l\'acceptation', 'error', false);
        }
        
    } catch (error) {
        showNotification('Erreur de connexion', 'error', false);
    }
}
*/

*/

async /*
// FONCTION DUPLIQUÉE SUPPRIMÉE - terminerCommande (doublon #1)

/*
// FONCTION DUPLIQUÉE SUPPRIMÉE - terminerCommande #1
// 2025-08-26 08:57:33
function terminerCommande(commandeId, type = 'classique') {
    if (!confirm('Confirmer la livraison ? Cette action est irréversible.')) {
        return;
    }
    
    try {
        const response = await fetch('coursier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax=true&action=complete_commande&commande_id=${commandeId}&type=${type}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Son de livraison terminée
            suzoskySounds.playDeliveryComplete();
            showNotification('Livraison terminée avec succès !', 'success', false);
            loadCommandes();
            loadStats();
        } else {
            showNotification(result.message || 'Erreur lors de la finalisation', 'error', false);
        }
        
    } catch (error) {
        showNotification('Erreur de connexion', 'error', false);
    }
}
*/

*/

// Améliorer le chat support avec sons
const originalChatLoadMessages = chatSupport.loadMessages;
chatSupport.loadMessages = async function() {
    const previousCount = this.lastMessageId;
    await originalChatLoadMessages.call(this);
    
    // Si nouveaux messages reçus, jouer le son
    if (this.lastMessageId > previousCount && this.isOpen) {
        suzoskySounds.playSupportMessage();
    }
};

// Améliorer le système de facturation avec sons
const originalUpdateAccountDisplay = billingSystem.updateAccountDisplay;
billingSystem.updateAccountDisplay = function(account) {
    const wasLowBalance = this.lastBalance && this.lastBalance < 3000;
    const isLowBalance = account.solde < 3000;
    
    // Si passage en solde faible, jouer le son d'alerte
    if (!wasLowBalance && isLowBalance) {
        suzoskySounds.playLowBalance();
        showNotification('⚠️ Solde insuffisant ! Minimum 3000 FCFA requis pour recevoir des commandes.', 'warning', false);
    }
    
    this.lastBalance = account.solde;
    originalUpdateAccountDisplay.call(this, account);
};

// Initialiser l'état du bouton son au chargement
document.addEventListener('DOMContentLoaded', function() {
    const soundToggle = document.getElementById('soundToggle');
    if (soundToggle && suzoskySounds) {
        const isEnabled = suzoskySounds.isEnabled;
        const icon = soundToggle.querySelector('i');
        
        if (isEnabled) {
            soundToggle.className = 'sound-toggle enabled';
            icon.className = 'fas fa-volume-up';
        } else {
            soundToggle.className = 'sound-toggle disabled';
            icon.className = 'fas fa-volume-mute';
        }
    }
    
    // Vérifier les paiements CINETPAY en attente
    if (typeof billingSystem !== 'undefined') {
        // Vérification immédiate
        billingSystem.checkPendingPayments();
        
        // Vérification périodique toutes les 30 secondes
        setInterval(() => {
            billingSystem.checkPendingPayments();
        }, 30000);
    }
    
    // Gérer les paramètres de retour de paiement
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('recharge_success') === '1') {
        showNotification('Votre recharge a été effectuée avec succès !', 'success');
        
        // Nettoyer l'URL
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
        
        // Actualiser les informations du compte
        if (typeof billingSystem !== 'undefined') {
            setTimeout(() => {
                billingSystem.loadAccountInfo();
                billingSystem.loadBillingDetails();
            }, 1000);
        }
    }
});

<?php endif; ?>
</script>

<!-- Styles V2.1 Chat Support et Billing -->
<style>
    /* === CHAT SUPPORT STYLES - CHARTE SUZOSKY === */
    .chat-support-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
    }
    
    .chat-toggle {
        width: 65px;
        height: 65px;
        background: var(--gradient-gold);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 8px 32px rgba(212, 168, 83, 0.4);
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        position: relative;
        border: 2px solid rgba(212, 168, 83, 0.3);
    }
    
    .chat-toggle:hover {
        transform: scale(1.15) rotate(5deg);
        box-shadow: 0 12px 40px rgba(212, 168, 83, 0.6);
        border-color: rgba(212, 168, 83, 0.8);
    }
    
    .chat-toggle i {
        font-size: 26px;
        color: var(--primary-dark);
        font-weight: 600;
    }
    
    .chat-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: linear-gradient(135deg, var(--accent-red) 0%, #ff6b6b 100%);
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        box-shadow: 0 4px 12px rgba(233, 69, 96, 0.4);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .chat-window {
        position: absolute;
        bottom: 85px;
        right: 0;
        width: 380px;
        height: 550px;
        background: var(--glass-bg);
        backdrop-filter: blur(25px);
        border: 1px solid var(--glass-border);
        border-radius: 25px;
        box-shadow: 0 25px 80px rgba(0,0,0,0.4);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: translateY(20px) scale(0.95);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    
    .chat-window.show {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    
    .chat-header {
        background: var(--gradient-gold);
        color: var(--primary-dark);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
        font-size: 16px;
        letter-spacing: 0.5px;
        border-radius: 25px 25px 0 0;
    }
    
    .chat-close-btn {
        background: rgba(26, 26, 46, 0.1);
        border: none;
        color: var(--primary-dark);
        cursor: pointer;
        font-size: 18px;
        padding: 8px;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .chat-close-btn:hover {
        background: rgba(26, 26, 46, 0.2);
        transform: rotate(90deg);
    }
    
    .chat-messages {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background: rgba(255,255,255,0.02);
    }
    
    .chat-message {
        margin-bottom: 18px;
        padding: 15px 18px;
        border-radius: 20px;
        max-width: 85%;
        word-wrap: break-word;
        font-size: 14px;
        line-height: 1.5;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .chat-message-user {
        background: var(--gradient-gold);
        color: var(--primary-dark);
        margin-left: auto;
        border-bottom-right-radius: 8px;
        box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
    }
    
    .chat-message-admin {
        background: rgba(255,255,255,0.12);
        border-bottom-left-radius: 8px;
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .message-sender {
        font-size: 12px;
        font-weight: 700;
        color: var(--primary-gold);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .message-content {
        line-height: 1.6;
        font-weight: 500;
    }
    
    .message-time {
        font-size: 11px;
        opacity: 0.7;
        margin-top: 8px;
        text-align: right;
        font-weight: 600;
    }
    
    .chat-input-container {
        border-top: 1px solid var(--glass-border);
        padding: 20px;
        background: rgba(255,255,255,0.03);
    }
    
    .chat-quick-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .quick-btn {
        background: rgba(255,255,255,0.08);
        border: 1px solid var(--glass-border);
        color: white;
        padding: 10px 15px;
        border-radius: 25px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        letter-spacing: 0.3px;
    }
    
    .quick-btn:hover {
        background: var(--gradient-gold);
        color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(212, 168, 83, 0.4);
    }
    
    .chat-input-row {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    
    .chat-input-row input {
        flex: 1;
        padding: 15px 20px;
        background: rgba(255,255,255,0.9);
        border: 2px solid rgba(255,255,255,0.1);
        border-radius: 30px;
        color: #333;
        font-size: 14px;
        font-weight: 500;
        font-family: 'Montserrat', sans-serif;
        transition: all 0.3s ease;
    }
    
    .chat-input-row input:focus {
        outline: none;
        background: white;
        border-color: var(--primary-gold);
        box-shadow: 0 0 25px rgba(212, 168, 83, 0.3);
    }
    
    .chat-send-btn {
        background: var(--gradient-gold);
        border: none;
        color: var(--primary-dark);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(212, 168, 83, 0.4);
    }
    
    .chat-send-btn:hover {
        transform: scale(1.1) rotate(15deg);
        box-shadow: 0 8px 25px rgba(212, 168, 83, 0.6);
    }
    
    .chat-loading, .chat-error {
        text-align: center;
        padding: 30px 20px;
        color: rgba(255,255,255,0.6);
        font-style: italic;
        font-weight: 500;
    }
    
    .chat-error {
        color: var(--accent-red);
        font-weight: 600;
    }
    
    /* === BILLING SYSTEM STYLES - CHARTE SUZOSKY === */
    .billing-toggle-btn {
        background: rgba(40, 167, 69, 0.12);
        border: 2px solid #28a745;
        color: #28a745;
        padding: 12px 24px;
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        font-family: 'Montserrat', sans-serif;
        font-weight: 700;
        font-size: 14px;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .billing-toggle-btn:hover {
        background: linear-gradient(135deg, #28a745 0%, #34ce57 100%);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(40, 167, 69, 0.4);
    }
    
    .billing-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.85);
        backdrop-filter: blur(10px);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        transition: all 0.4s ease;
    }
    
    .billing-modal.show {
        opacity: 1;
    }
    
    .billing-content {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        border: 2px solid var(--glass-border);
        border-radius: 30px;
        width: 100%;
        max-width: 650px;
        max-height: 85vh;
        overflow-y: auto;
        transform: scale(0.9) translateY(50px);
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        box-shadow: 0 25px 80px rgba(0,0,0,0.4);
    }
    
    .billing-modal.show .billing-content {
        transform: scale(1) translateY(0);
    }
    
    .billing-header {
        background: var(--gradient-gold);
        color: var(--primary-dark);
        padding: 25px 35px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
        font-size: 18px;
        letter-spacing: 0.5px;
        border-radius: 30px 30px 0 0;
    }
    
    .modal-close {
        background: rgba(26, 26, 46, 0.1);
        border: none;
        color: var(--primary-dark);
        cursor: pointer;
        font-size: 20px;
        padding: 10px;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        background: rgba(26, 26, 46, 0.2);
        transform: rotate(90deg);
    }
    
    .billing-details {
        padding: 35px;
    }
    
    .account-balance {
        margin-bottom: 35px;
    }
    
    .balance-card {
        background: var(--glass-bg);
        border-radius: 20px;
        padding: 30px;
        display: flex;
        align-items: center;
        gap: 25px;
        border: 2px solid;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .balance-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s ease;
    }
    
    .balance-card:hover::before {
        left: 100%;
    }
    
    .balance-card.balance-ok {
        border-color: #28a745;
        box-shadow: 0 8px 32px rgba(40, 167, 69, 0.2);
    }
    
    .balance-card.balance-low {
        border-color: var(--accent-red);
        box-shadow: 0 8px 32px rgba(233, 69, 96, 0.3);
        animation: lowBalancePulse 2s infinite;
    }
    
    @keyframes lowBalancePulse {
        0%, 100% { box-shadow: 0 8px 32px rgba(233, 69, 96, 0.3); }
        50% { box-shadow: 0 12px 40px rgba(233, 69, 96, 0.5); }
    }
    
    .balance-card i {
        font-size: 45px;
        background: var(--gradient-gold);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .balance-amount {
        font-size: 32px;
        font-weight: 900;
        color: white;
        letter-spacing: 1px;
    }
    
    .balance-label {
        font-size: 14px;
        color: rgba(255,255,255,0.8);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }
    
    .balance-status {
        font-size: 14px;
        margin-top: 8px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    /* === BALANCE BREAKDOWN STYLES - CHARTE SUZOSKY === */
    .balance-breakdown {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 25px;
    }
    
    .balance-source {
        flex: 1;
    }
    
    .source-card {
        background: rgba(255,255,255,0.04);
        border-radius: 18px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        border: 2px solid;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(15px);
    }
    
    .source-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
        transition: left 0.5s ease;
    }
    
    .source-card:hover::before {
        left: 100%;
    }
    
    .source-card:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 35px rgba(0,0,0,0.3);
    }
    
    .source-card.earnings {
        border-color: #28a745;
        box-shadow: 0 8px 30px rgba(40, 167, 69, 0.2);
    }
    
    .source-card.earnings:hover {
        box-shadow: 0 15px 40px rgba(40, 167, 69, 0.4);
        background: rgba(40, 167, 69, 0.08);
    }
    
    .source-card.credit {
        border-color: #007bff;
        box-shadow: 0 8px 30px rgba(0, 123, 255, 0.2);
    }
    
    .source-card.credit:hover {
        box-shadow: 0 15px 40px rgba(0, 123, 255, 0.4);
        background: rgba(0, 123, 255, 0.08);
    }
    
    .source-card i {
        font-size: 35px;
        min-width: 35px;
    }
    
    .source-card.earnings i {
        color: #28a745;
    }
    
    .source-card.credit i {
        color: #007bff;
    }
    
    .source-info {
        flex: 1;
    }
    
    .source-label {
        font-size: 12px;
        color: rgba(255,255,255,0.7);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 8px;
    }
    
    .source-amount {
        font-size: 22px;
        font-weight: 800;
        color: white;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .source-note {
        font-size: 11px;
        color: rgba(255,255,255,0.5);
        font-weight: 600;
        font-style: italic;
    }
    
    /* Responsive pour balance breakdown */
    @media (max-width: 768px) {
        .balance-breakdown {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .source-card {
            padding: 20px;
            gap: 15px;
        }
        
        .source-card i {
            font-size: 28px;
        }
        
        .source-amount {
            font-size: 18px;
        }
    }
    
    .billing-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 25px;
        margin-bottom: 35px;
    }
    
    .stat-item {
        background: rgba(255,255,255,0.06);
        border-radius: 18px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 18px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stat-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        background: rgba(255,255,255,0.1);
    }
    
    .stat-item i {
        font-size: 28px;
        background: var(--gradient-gold);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .stat-value {
        font-size: 20px;
        font-weight: 700;
        color: white;
        letter-spacing: 0.5px;
    }
    
    .stat-label {
        font-size: 12px;
        color: rgba(255,255,255,0.7);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .recharge-section {
        background: rgba(233, 69, 96, 0.12);
        border: 2px solid var(--accent-red);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 35px;
        position: relative;
        overflow: hidden;
    }
    
    .recharge-section::before {
        content: '⚠️';
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 24px;
        opacity: 0.3;
    }
    
    .recharge-section h4 {
        color: var(--accent-red);
        font-weight: 700;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .recharge-options {
        display: flex;
        gap: 15px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .recharge-btn {
        background: linear-gradient(135deg, var(--accent-red) 0%, #ff6b6b 100%);
        color: white;
        border: none;
        padding: 15px 25px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 700;
        font-size: 14px;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
    }
    
    .recharge-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(233, 69, 96, 0.5);
        background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
    }
    
    .recent-transactions {
        background: rgba(255,255,255,0.04);
        border-radius: 20px;
        padding: 25px;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .recent-transactions h4 {
        color: white;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
    }
    
    .transaction-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 0;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        transition: all 0.3s ease;
    }
    
    .transaction-item:hover {
        background: rgba(255,255,255,0.03);
        margin: 0 -15px;
        padding: 18px 15px;
        border-radius: 10px;
    }
    
    .transaction-item:last-child {
        border-bottom: none;
    }
    
    .transaction-type {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
    }
    
    .transaction-amount {
        font-weight: 700;
        font-size: 16px;
        letter-spacing: 0.5px;
    }
    
    .transaction-amount.debit {
        color: var(--accent-red);
    }
    
    .transaction-amount.credit {
        color: #28a745;
    }
    
    .transaction-date {
        font-size: 12px;
        color: rgba(255,255,255,0.6);
        font-weight: 600;
    }
    
    .balance-display {
        margin-right: 20px;
    }
    
    .balance-mini {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 18px;
        border-radius: 15px;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
    }
    
    .balance-mini:hover {
        transform: translateY(-2px);
    }
    
    .balance-mini.balance-ok {
        background: rgba(40, 167, 69, 0.15);
        border: 2px solid #28a745;
        color: #28a745;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
    }
    
    .balance-mini.balance-low {
        background: rgba(233, 69, 96, 0.15);
        border: 2px solid var(--accent-red);
        color: var(--accent-red);
        box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
        animation: lowBalanceShake 3s infinite;
    }
    
    @keyframes lowBalanceShake {
        0%, 98%, 100% { transform: translateX(0); }
        1%, 3% { transform: translateX(-2px); }
        2%, 4% { transform: translateX(2px); }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .chat-window {
            width: 300px;
            height: 400px;
        }
        
        .billing-content {
            margin: 20px;
            max-height: 90vh;
        }
        
        .recharge-options {
            flex-direction: column;
        }
        
        .billing-stats {
            grid-template-columns: 1fr;
        }
    }
    
    /* === NOTIFICATIONS STYLES === */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 15px 20px;
        color: white;
        z-index: 3000;
        transform: translateX(100%);
        transition: all 0.3s ease;
        max-width: 350px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .notification-success {
        border-left: 4px solid #28a745;
    }
    
    .notification-error {
        border-left: 4px solid var(--accent-red);
    }
    
    .notification-warning {
        border-left: 4px solid #ffc107;
    }
    
    .notification-info {
        border-left: 4px solid #17a2b8;
    }
    
    .notification i {
        font-size: 20px;
    }
    
    .notification-success i {
        color: #28a745;
    }
    
    .notification-error i {
        color: var(--accent-red);
    }
    
    .notification-warning i {
        color: #ffc107;
    }
    
    .notification-info i {
        color: #17a2b8;
    }
    
    /* === CINETPAY LOADING OVERLAY - CHARTE SUZOSKY === */
    .cinetpay-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(15px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .cinetpay-loading-overlay.show {
        opacity: 1;
    }
    
    .cinetpay-loading-content {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        border: 2px solid var(--glass-border);
        border-radius: 25px;
        padding: 50px;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 25px 80px rgba(0,0,0,0.5);
        position: relative;
        overflow: hidden;
    }
    
    .cinetpay-loading-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    
    .cinetpay-loading-spinner {
        width: 80px;
        height: 80px;
        border: 4px solid rgba(212, 168, 83, 0.2);
        border-top: 4px solid var(--primary-gold);
        border-radius: 50%;
        animation: cinetpaySpinner 1s linear infinite;
        margin: 0 auto 25px;
        position: relative;
    }
    
    .cinetpay-loading-spinner::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 20px;
        height: 20px;
        background: var(--gradient-gold);
        border-radius: 50%;
        animation: cinetpayPulse 1.5s ease-in-out infinite;
    }
    
    @keyframes cinetpaySpinner {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @keyframes cinetpayPulse {
        0%, 100% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.5; }
        50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
    }
    
    .cinetpay-loading-text {
        font-size: 18px;
        font-weight: 700;
        color: white;
        margin-bottom: 10px;
        font-family: 'Montserrat', sans-serif;
        letter-spacing: 0.5px;
    }
    
    .cinetpay-loading-subtitle {
        font-size: 14px;
        color: rgba(255,255,255,0.7);
        font-weight: 500;
        font-style: italic;
    }
    
    /* Responsive pour CINETPAY loading */
    @media (max-width: 768px) {
        .cinetpay-loading-content {
            padding: 30px 20px;
            margin: 20px;
        }
        
        .cinetpay-loading-spinner {
            width: 60px;
            height: 60px;
        }
        
        .cinetpay-loading-text {
            font-size: 16px;
        }
        
        .cinetpay-loading-subtitle {
            font-size: 12px;
        }
    }
</style>

<!-- OVERLAY COMMANDE ATTRIBUÉE (WEB) -->
<div id="assigned-order-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:6000;backdrop-filter:blur(6px);">
    <div style="max-width:520px;margin:40px auto 0 auto;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:28px;padding:30px 32px;color:#fff;position:relative;box-shadow:0 15px 60px rgba(0,0,0,0.45);font-family:'Montserrat',sans-serif;">
        <h2 style="text-align:center;margin:0 0 10px 0;font-size:22px;font-weight:800;letter-spacing:1px;background:var(--gradient-gold);-webkit-background-clip:text;color:transparent;">NOUVELLE COMMANDE</h2>
        <div id="assigned-order-content" style="line-height:1.5;font-size:14px;"></div>
        <div style="display:flex;gap:10px;margin-top:22px;">
            <button id="btn-start-nav" style="flex:1;background:#1565c0;border:none;padding:14px 18px;border-radius:14px;color:#fff;font-weight:700;cursor:pointer;font-size:14px;">Navigation</button>
            <button id="btn-pickup" style="flex:1;background:#2e7d32;border:none;padding:14px 18px;border-radius:14px;color:#fff;font-weight:700;cursor:pointer;font-size:14px;">Prendre en charge</button>
            <button id="btn-picked" style="flex:1;display:none;background:#ef6c00;border:none;padding:14px 18px;border-radius:14px;color:#fff;font-weight:700;cursor:pointer;font-size:14px;">Colis récupéré</button>
            <button id="btn-deliver" style="flex:1;display:none;background:#8e24aa;border:none;padding:14px 18px;border-radius:14px;color:#fff;font-weight:700;cursor:pointer;font-size:14px;">Livré</button>
            <button id="btn-deliver-cash" style="flex:1;display:none;background:#b71c1c;border:none;padding:14px 18px;border-radius:14px;color:#fff;font-weight:700;cursor:pointer;font-size:14px;">Cash + Livré</button>
        </div>
    </div>
</div>

<script>
(function(){
    const overlay = document.getElementById('assigned-order-overlay');
    const content = document.getElementById('assigned-order-content');
    const btnNav = document.getElementById('btn-start-nav');
    const btnPickup = document.getElementById('btn-pickup');
    const btnPicked = document.getElementById('btn-picked');
    const btnDeliver = document.getElementById('btn-deliver');
    const btnDeliverCash = document.getElementById('btn-deliver-cash');
    let currentOrderId = null;
    let currentStatut = null; // nouvelle|acceptee|en_cours|livree
    let isCash = false;

    function render(o){
        if(!o){ overlay.style.display='none'; return; }
        overlay.style.display='block';
        currentOrderId = o.id; currentStatut = o.statut;
        isCash = (o.mode_paiement && o.mode_paiement.toLowerCase() === 'cash');
        content.innerHTML = `
            <strong>Client:</strong> ${o.clientNom || '-'}<br>
            <strong>Téléphone:</strong> ${o.clientTelephone || '-'}<br>
            <strong>Enlèvement:</strong> ${o.adresseEnlevement || ''}<br>
            <strong>Livraison:</strong> ${o.adresseLivraison || ''}<br>
            <strong>Tarif:</strong> ${(o.prixLivraison||0)} FCFA<br>
            <strong>Mode paiement:</strong> ${(o.mode_paiement||'-')}<br>
            <strong>Statut:</strong> ${o.statut}
        `;
        // Logique boutons
        btnPickup.style.display = (o.statut === 'nouvelle' || o.statut === 'acceptee') ? 'block' : 'none';
        btnPicked.style.display = (o.statut === 'en_cours') ? 'block' : 'none';
    btnDeliver.style.display = (!isCash && o.statut === 'picked_up') ? 'block' : 'none';
    btnDeliverCash.style.display = (isCash && o.statut === 'picked_up') ? 'block' : 'none';
    }

    function poll(){
        fetch('coursier.php', {method:'POST', body: new URLSearchParams({ajax:'true', action:'get_assigned_order'})})
            .then(r=>r.json())
            .then(j=>{
                if(j && j.order){
                    if(j.order.id !== currentOrderId || j.order.statut !== currentStatut){
                        try { new Audio('https://actions.google.com/sounds/v1/alarms/alarm_clock.ogg').play().catch(()=>{}); } catch(e){}
                    }
                    render(j.order);
                } else {
                    // aucun ordre actif -> masquer overlay
                    // overlay.style.display='none';
                }
            })
            .catch(()=>{});
    }
    setInterval(poll, 3000);
    poll();

    function updateStatus(next, cashFlag=false, cashAmount=null){
        if(!currentOrderId) return;
        const payload = {commande_id: currentOrderId, statut: next};
        if(cashFlag){ payload.cash_collected = true; if(cashAmount!==null) payload.cash_amount = cashAmount; }
        fetch('api/update_order_status.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)})
            .then(r=>r.json()).then(j=>{ if(j.success){ currentStatut = next;
                if(next==='en_cours'){ btnPickup.style.display='none'; btnPicked.style.display='block'; }
                if(next==='picked_up'){ btnPicked.style.display='none'; btnDeliver.style.display='block'; }
                if(next==='livree'){ btnDeliver.style.display='none'; }
            }}).catch(()=>{});
    }

    btnNav.addEventListener('click', ()=>{
        if(!content) return; // simple
        const addr = (content.innerText.match(/Enlèvement: (.*)\n/)||[])[1];
        if(addr){ window.open('https://www.google.com/maps/dir/?api=1&destination='+encodeURIComponent(addr), '_blank'); }
    });
    btnPickup.addEventListener('click', ()=> updateStatus('en_cours'));
    btnPicked.addEventListener('click', ()=> updateStatus('picked_up'));
    btnDeliver.addEventListener('click', ()=> updateStatus('livree'));
    btnDeliverCash.addEventListener('click', () => {
        const amt = prompt('Montant cash reçu (laisser vide pour tarif affiché)');
        let val = null; if(amt && !isNaN(parseFloat(amt))) val = parseFloat(amt);
        updateStatus('livree', true, val);
    });
})();
</script>

</body>
</html>
