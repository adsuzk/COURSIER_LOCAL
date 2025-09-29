<?php
// Protection anti-sortie parasite : buffer de sortie
ob_start();
// VERSION CORRIGÉE 2025-09-04 21:00 - PROTECTION CLIENT_ID FOREIGN KEY
// Unified JSON response and error handling
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin', '*');
// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
// Loguer la méthode, les headers et le corps brut dès le début
logMessage('diagnostics_errors.log', 'REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
if (function_exists('getallheaders')) {
    logMessage('diagnostics_errors.log', 'REQUEST_HEADERS: ' . json_encode(getallheaders()));
}
$rawInput = file_get_contents('php://input');
logMessage('diagnostics_errors.log', 'RAW_INPUT: ' . $rawInput);
// Convert errors to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
    // Log exception details TRÈS DÉTAILLÉ
    logMessage('diagnostics_errors.log', '🚨 EXCEPTION CRITIQUE: ' . $e->getMessage());
    logMessage('diagnostics_errors.log', '📁 Fichier: ' . $e->getFile() . ' ligne ' . $e->getLine());
    logMessage('diagnostics_errors.log', '📋 Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => 'Exception logged']);
    exit;
});

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../lib/db_maintenance.php';

if (!function_exists('submitOrderLoadPricingConfig')) {
    function submitOrderLoadPricingConfig(\PDO $pdo): array
    {
        $config = [
            'frais_base' => 500.0,
            'prix_kilometre' => 300.0
        ];
        try {
            $stmt = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $config[$row['parametre']] = (float) $row['valeur'];
                }
            }
        } catch (\Throwable $e) {
            logMessage('diagnostics_errors.log', 'PRICING_CONFIG_FALLBACK: ' . $e->getMessage());
        }
        return $config;
    }
}

if (!function_exists('submitOrderEnsurePrice')) {
    function submitOrderEnsurePrice(\PDO $pdo, float $distanceKm, string $priority, float $currentPrice, string $rawDistance): float
    {
        if ($currentPrice > 0) {
            return $currentPrice;
        }

        $priorityKey = strtolower(trim($priority ?: 'normale'));
        $multipliers = [
            'normale' => ['base' => 1.0, 'perKm' => 1.0],
            'urgente' => ['base' => 1.4, 'perKm' => 1.3],
            'express' => ['base' => 1.8, 'perKm' => 1.6]
        ];
        $config = submitOrderLoadPricingConfig($pdo);

        $baseFare = max(0.0, (float) ($config['frais_base'] ?? 500.0));
        $pricePerKm = max(0.0, (float) ($config['prix_kilometre'] ?? 300.0));
        $multiplier = $multipliers[$priorityKey] ?? $multipliers['normale'];

        $baseForPriority = max($baseFare, round($baseFare * $multiplier['base']));
        $perKmForPriority = max($pricePerKm, round($pricePerKm * $multiplier['perKm']));

        $computed = $baseForPriority;
        if ($distanceKm > 0) {
            $computed = max($baseForPriority, $baseForPriority + (int) ceil($distanceKm * $perKmForPriority));
        }

        if ($computed <= 0) {
            $computed = max($baseForPriority, 2000.0);
            logMessage('diagnostics_errors.log', sprintf(
                'PRICING_FALLBACK_MIN_APPLIED: base=%.2f, priority=%s, distance_raw=%s, computed=%.0f',
                $baseFare,
                $priorityKey,
                $rawDistance !== '' ? $rawDistance : 'N/A',
                $computed
            ));
        } else {
            logMessage('diagnostics_errors.log', sprintf(
                'PRICING_FALLBACK_APPLIED: distance=%s (km=%.3f), priority=%s, price recalculé=%.0f',
                $rawDistance !== '' ? $rawDistance : 'N/A',
                $distanceKm,
                $priorityKey,
                $computed
            ));
        }

    return (float) round($computed, 0);
    }
}

// LOG DE DEBUG : Le script démarre
logMessage('diagnostics_errors.log', '🚀 DÉBUT submit_order.php - ' . date('Y-m-d H:i:s'));

// Intégration CinetPay
require_once __DIR__ . '/../cinetpay/cinetpay_integration.php';

// Log db connection
logMessage('diagnostics_db.log', 'Tentative de connexion à la base');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage('diagnostics_errors.log', '❌ Méthode ' . $_SERVER['REQUEST_METHOD'] . ' non autorisée');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

logMessage('diagnostics_errors.log', '✅ Méthode POST validée, traitement en cours...');

// Lire les données d'entrée (JSON ou POST form)
$input = file_get_contents('php://input');
$data = null;

if (!empty($input)) {
    // Tentative de décodage JSON
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage('diagnostics_errors.log', '⚠️ JSON invalide, tentative lecture POST form...');
        $data = null;
    } else {
    logMessage('diagnostics_errors.log', '✅ Données JSON décodées avec succès');
    }
}

// Si pas de JSON valide, utiliser $_POST
if ($data === null) {
    if (!empty($_POST)) {
        $data = $_POST;
        logMessage('diagnostics_errors.log', '✅ Données POST form utilisées');
    } else {
        logMessage('diagnostics_errors.log', '❌ Aucune donnée reçue (ni JSON ni POST)');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucune donnée reçue']);
        exit;
    }
}

try {
    $pdo = getDBConnection();
    logMessage('diagnostics_db.log', 'Connexion DB réussie');

    $clientsMaintenance = ensureLegacyClientsTable($pdo);
    if (!($clientsMaintenance['exists'] ?? false)) {
        logMessage('diagnostics_errors.log', 'TABLE_CLIENTS_ABSENTE_APRES_MAINTENANCE');
    }
    if (!empty($clientsMaintenance['warnings'])) {
        logMessage('diagnostics_errors.log', 'CLIENTS_TABLE_WARNINGS: ' . implode(', ', $clientsMaintenance['warnings']));
    }
    if (!empty($clientsMaintenance['errors'])) {
        logMessage('diagnostics_errors.log', 'CLIENTS_TABLE_ERRORS: ' . implode(', ', $clientsMaintenance['errors']));
    }
} catch (Exception $e) {
    logMessage('diagnostics_errors.log', 'DB Error: ' . $e->getMessage());
    throw $e;
}

// Validation des champs

$required = ['departure','destination','senderPhone','receiverPhone','priority','paymentMethod'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        throw new Exception("Le champ $field est requis");
    }
}

// Champs latitude/longitude du point de départ (optionnels mais recommandés)
$departureLat = isset($data['departure_lat']) ? floatval($data['departure_lat']) : null;
$departureLng = isset($data['departure_lng']) ? floatval($data['departure_lng']) : null;
$arrivalLat = isset($data['destination_lat']) ? floatval($data['destination_lat']) : null;
$arrivalLng = isset($data['destination_lng']) ? floatval($data['destination_lng']) : null;

try {
// Préparer les données
$departure = trim($data['departure']);
$destination = trim($data['destination']);
$senderPhone = trim($data['senderPhone']);
$receiverPhone = trim($data['receiverPhone']);
// Normalize phone numbers to digits-only to ensure consistency server-side
$normalizePhone = function(string $p){
    $p = trim($p);
    $digits = preg_replace('/\D+/', '', $p);
    return $digits ?: $p; // fallback to original if nothing left
};
$senderPhone = $normalizePhone($senderPhone);
$receiverPhone = $normalizePhone($receiverPhone);
$packageDescription = trim($data['packageDescription'] ?? ($data['packageDesc'] ?? ''));
$priority = trim($data['priority']);
// Corriger la valeur priority pour correspondre aux ENUM de la DB
$priorityMap = [
    'normal' => 'normale',
    'normale' => 'normale', 
    'urgent' => 'urgente',
    'urgente' => 'urgente',
    'express' => 'express'
];
$priority = $priorityMap[strtolower($priority)] ?? 'normale';
$paymentMethod = trim($data['paymentMethod']);
$priceRaw = $data['price'] ?? 0;
// price may arrive as string with spaces
$price = is_numeric($priceRaw) ? floatval($priceRaw) : floatval(preg_replace('/[^0-9.]/','',$priceRaw));
$distanceRaw = $data['distance'] ?? '';
$distance = is_string($distanceRaw) ? trim($distanceRaw) : (is_numeric($distanceRaw) ? (string) $distanceRaw : '');
$distanceKm = submitOrderParseDistanceKm($distance);
$duration = trim($data['duration'] ?? '');

if (!is_finite($price)) {
    $price = 0.0;
}
if ($price <= 0) {
    $price = submitOrderEnsurePrice($pdo, $distanceKm, $priority, $price, $distance);
}

// Validation du prix pour les paiements électroniques
if ($paymentMethod !== 'cash' && $price <= 0) {
    // Log détaillé pour debug
    logMessage('diagnostics_errors.log', 'Prix invalide reçu: ' . json_encode(['price' => $price, 'data' => $data]));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Prix invalide ou non fourni: ' . $price]);
    exit;
}

// Générer numéro de commande
$orderNumber = 'SZK' . date('Ymd') . bin2hex(random_bytes(3));

// Lire la liste des colonnes pour construire un INSERT compatible avec le schéma prod
$columns = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);

// Utilitaires de détection robuste de colonnes
$hasOrderNumber   = in_array('order_number', $columns, true);
$hasCodeCommande  = in_array('code_commande', $columns, true);
$hasNumeroCmd     = in_array('numero_commande', $columns, true);

$identifierCols = [];
if ($hasOrderNumber) {
    $identifierCols['order_number'] = $orderNumber;
}

// Générer systématiquement un code_commande si la colonne existe
$generatedCode = null;
if ($hasCodeCommande) {
    $check = $pdo->prepare('SELECT 1 FROM commandes WHERE code_commande = ? LIMIT 1');
    $attempts = 0;
    do {
        $attempts++;
        $candidate = 'SZK' . date('ymd') . mt_rand(100000, 999999); // 15 chars
        if (!$candidate) {
            $candidate = 'SZK' . date('ymdHis') . mt_rand(100, 999);
        }
        $check->execute([$candidate]);
        $exists = (bool)$check->fetchColumn();
        if (!$exists) {
            $generatedCode = $candidate;
        }
    } while ($exists && $attempts < 10);
    // Sécurité: ne jamais laisser vide
    if (!$generatedCode) {
        $generatedCode = 'SZK' . date('ymdHis') . mt_rand(100, 999);
    }
    $identifierCols['code_commande'] = $generatedCode;
}

if (empty($identifierCols)) {
    // Fallback pour anciens schémas
    $identifierCols['numero_commande'] = $orderNumber;
}

// Mapper le mode de paiement vers les valeurs ENUM de prod si nécessaire
$paymentMap = [
    'cash' => 'especes',
    'orange_money' => 'mobile_money',
    'mobile_money' => 'mobile_money',
    'mtn_money' => 'mobile_money',
    'moov_money' => 'mobile_money',
    'card' => 'carte_bancaire',
    'wave' => 'wave',
    'credit_business' => 'credit_business'
];
$paymentForDb = $paymentMap[$paymentMethod] ?? $paymentMethod;

// Créer ou mettre à jour client expéditeur dans clients_particuliers
    $senderName = 'ClientExp' . substr($senderPhone, -4);
    $stmt = $pdo->prepare(
        "INSERT INTO clients_particuliers (nom, prenoms, telephone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE telephone = VALUES(telephone)"
    );
    $stmt->execute([
        $senderName,
        'Client',
        $senderPhone
    ]);
    // Récupérer l'ID du client expéditeur
    $selectClient = $pdo->prepare("SELECT id FROM clients_particuliers WHERE telephone = ?");
    $selectClient->execute([$senderPhone]);
    $senderId = $selectClient->fetchColumn();
    // Mirror vers table clients pour satisfaire contrainte FK
    try {
        $mirrorStmt = $pdo->prepare(
            "INSERT INTO clients (id, nom, prenoms, telephone) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nom=VALUES(nom), prenoms=VALUES(prenoms), telephone=VALUES(telephone)"
        );
        $mirrorStmt->execute([$senderId, $senderName, 'Client', $senderPhone]);
    } catch (Exception $e) {
        // Ignorer les erreurs de synchronisation
    }
    // Récupérer l'id dans la table clients principale
    try {
        $selectMain = $pdo->prepare("SELECT id FROM clients WHERE telephone = ?");
        $selectMain->execute([$senderPhone]);
        $clientIdMain = $selectMain->fetchColumn();
        
        // Si pas trouvé, créer l'entrée
        if (!$clientIdMain) {
            $insertMain = $pdo->prepare("INSERT INTO clients (nom, prenoms, telephone) VALUES (?, ?, ?)");
            $insertMain->execute([$senderName, 'Client', $senderPhone]);
            $clientIdMain = $pdo->lastInsertId();
        }
    } catch (Exception $e) {
        $clientIdMain = $senderId;
    }

// CORRECTION CRITIQUE : Vérifier que client_id existe vraiment
$finalClientId = 1; // Valeur par défaut sécurisée
 logMessage('diagnostics_errors.log', "CLIENT_ID_CHECK: clientIdMain=$clientIdMain, tentative de vérification...");

 if ($clientIdMain && $clientIdMain > 0) {
    // Vérifier que ce client existe réellement dans la table clients, avec gestion d'erreur
    try {
        $verifyClient = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
        $verifyClient->execute([$clientIdMain]);
        if ($verifyClient->fetchColumn()) {
            $finalClientId = $clientIdMain;
            logMessage('diagnostics_errors.log', "CLIENT_ID_CHECK: ✅ Client ID $clientIdMain trouvé, utilisation confirmée");
        } else {
            logMessage('diagnostics_errors.log', "CLIENT_ID_CHECK: ❌ Client ID $clientIdMain introuvable, fallback vers ID=1");
        }
    } catch (Exception $e) {
        logMessage('diagnostics_errors.log', 'CLIENT_ID_CHECK ERREUR: ' . $e->getMessage());
        logMessage('diagnostics_errors.log', 'CLIENT_ID_CHECK: fallback vers ID=1');
        $finalClientId = 1;
    }
} else {
    logMessage('diagnostics_errors.log', "CLIENT_ID_CHECK: ⚠️ clientIdMain invalide ($clientIdMain), utilisation ID=1 par défaut");
}

logMessage('diagnostics_errors.log', "CLIENT_ID_FINAL: Utilisation client_id=$finalClientId pour l'INSERT");
// Créer ou mettre à jour client destinataire
if ($receiverPhone !== $senderPhone) {
    $receiverName = 'ClientDest' . substr($receiverPhone, -4);
        $stmt = $pdo->prepare(
            "INSERT INTO clients_particuliers (nom, prenoms, telephone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE telephone = VALUES(telephone)"
        );
        $stmt->execute([
            $receiverName,
            'Client',
            $receiverPhone
        ]);
        // Récupérer l'ID du client destinataire
        $selectClient->execute([$receiverPhone]);
        $receiverId = $selectClient->fetchColumn();
    } else {
        $receiverId = $senderId;
}

// Déterminer dynamiquement la bonne référence pour client_id selon la FK
$clientIdForInsert = $finalClientId; // par défaut, compatible schéma avec table `clients`
try {
    $fkStmt = $pdo->prepare("SELECT REFERENCED_TABLE_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commandes' AND COLUMN_NAME = 'client_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
    $fkStmt->execute();
    $refTable = $fkStmt->fetchColumn();
    if ($refTable === 'clients_particuliers') {
        $clientIdForInsert = $senderId; // schéma qui réfère directement clients_particuliers
        logMessage('diagnostics_errors.log', "CLIENT_ID_ROUTE: FK vers clients_particuliers, utilisation senderId=$senderId");
    } else if ($refTable === 'clients') {
        $clientIdForInsert = $finalClientId; // schéma qui réfère table clients
        logMessage('diagnostics_errors.log', "CLIENT_ID_ROUTE: FK vers clients, utilisation finalClientId=$finalClientId");
    } else {
        // Si pas de FK détectée, conserver la valeur par défaut (plus tolérante)
        logMessage('diagnostics_errors.log', "CLIENT_ID_ROUTE: aucune FK explicite détectée, fallback finalClientId=$finalClientId");
    }
} catch (Exception $e) {
    logMessage('diagnostics_errors.log', 'CLIENT_ID_ROUTE ERREUR: ' . $e->getMessage());
}

// Construire l'INSERT dynamique
$cols = array_keys($identifierCols);
$vals = array_values($identifierCols);

// Ne forcer code_commande que si la colonne existe réellement
if ($hasCodeCommande && !in_array('code_commande', $cols, true)) {
    $generatedCodeFallback = 'SZK' . date('ymd') . mt_rand(100000, 999999);
    array_unshift($cols, 'code_commande');
    array_unshift($vals, $generatedCode ?: $generatedCodeFallback);
}
// Colonnes communes
// Ajout des colonnes pour latitude/longitude si elles existent en base
$hasLat = in_array('departure_lat', $columns, true);
$hasLng = in_array('departure_lng', $columns, true);
$cols = array_merge($cols, [
    'client_id', 'expediteur_id', 'destinataire_id',
    'adresse_depart', 'adresse_arrivee',
    'telephone_expediteur', 'telephone_destinataire',
    'description_colis', 'priorite', 'mode_paiement',
    'prix_estime'
]);
// Ajouter statut initial si colonne présente
if (in_array('statut', $columns, true)) $cols[] = 'statut';
if ($hasLat) $cols[] = 'departure_lat';
if ($hasLng) $cols[] = 'departure_lng';
if (in_array('client_telephone', $columns, true)) $cols[] = 'client_telephone';
if (in_array('client_type', $columns, true)) $cols[] = 'client_type';
if (in_array('client_nom', $columns, true)) $cols[] = 'client_nom';
if (in_array('client_business_id', $columns, true)) $cols[] = 'client_business_id';
if (in_array('adresse_retrait', $columns, true)) $cols[] = 'adresse_retrait';
if (in_array('adresse_livraison', $columns, true)) $cols[] = 'adresse_livraison';
if (in_array('prix_base', $columns, true)) $cols[] = 'prix_base';
if (in_array('prix_total', $columns, true)) $cols[] = 'prix_total';
if (in_array('latitude_retrait', $columns, true)) $cols[] = 'latitude_retrait';
if (in_array('longitude_retrait', $columns, true)) $cols[] = 'longitude_retrait';
if (in_array('latitude_livraison', $columns, true)) $cols[] = 'latitude_livraison';
if (in_array('longitude_livraison', $columns, true)) $cols[] = 'longitude_livraison';
// Valeurs communes
$vals = array_merge($vals, [
    $clientIdForInsert,  // client_id adapté au schéma (FK vers clients ou clients_particuliers)
    $senderId,
    $receiverId,
    $departure,
    $destination,
    $senderPhone,
    $receiverPhone,
    $packageDescription,
    $priority,
    $paymentForDb,
    $price
]);
if (in_array('statut', $columns, true)) $vals[] = 'nouvelle';
if ($hasLat) $vals[] = $departureLat;
if ($hasLng) $vals[] = $departureLng;
if (in_array('client_telephone', $columns, true)) $vals[] = $senderPhone;
if (in_array('client_type', $columns, true)) $vals[] = 'particulier';
if (in_array('client_nom', $columns, true)) $vals[] = $senderName;
if (in_array('client_business_id', $columns, true)) $vals[] = null;
if (in_array('adresse_retrait', $columns, true)) $vals[] = $departure;
if (in_array('adresse_livraison', $columns, true)) $vals[] = $destination;
if (in_array('prix_base', $columns, true)) $vals[] = $price;
if (in_array('prix_total', $columns, true)) $vals[] = $price;
if (in_array('latitude_retrait', $columns, true)) $vals[] = $departureLat;
if (in_array('longitude_retrait', $columns, true)) $vals[] = $departureLng;
if (in_array('latitude_livraison', $columns, true)) $vals[] = $arrivalLat;
if (in_array('longitude_livraison', $columns, true)) $vals[] = $arrivalLng;
// Assurer que code_commande n'est pas vide si présent (double sécurité)
if ($hasCodeCommande) {
    $idx = array_search('code_commande', $cols, true);
    if ($idx === false) {
        // Si pour une raison quelconque la colonne n'a pas été ajoutée, l'ajouter maintenant
        $cols = array_merge(['code_commande'], $cols);
        $vals = array_merge([$generatedCode ?: ('SZK' . date('ymd') . mt_rand(100000, 999999))], $vals);
    } else if (!isset($vals[$idx]) || $vals[$idx] === null || $vals[$idx] === '') {
        $vals[$idx] = $generatedCode ?: ('SZK' . date('ymd') . mt_rand(100000, 999999));
    }
}
// Avant d'exécuter l'INSERT
logMessage('diagnostics_sql_commands.log', 'INSERT Colonnes: ' . implode(', ', $cols));
logMessage('diagnostics_sql_commands.log', 'INSERT Valeurs: ' . json_encode($vals));
$placeholders = implode(', ', array_fill(0, count($cols), '?'));
$sql = 'INSERT INTO commandes (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
$stmt = $pdo->prepare($sql);
logMessage('diagnostics_sql_commands.log', 'SQL: ' . $sql);

try {
    $stmt->execute($vals);
    logMessage('diagnostics_db.log', 'Execution INSERT réussie');
} catch (PDOException $ex) {
    logMessage('diagnostics_errors.log', 'SQL Error: ' . $ex->getMessage());
    throw $ex;
}
// Récupération de l'ID de la commande
$orderId = $pdo->lastInsertId();
// Initier le paiement CinetPay pour les modes électroniques
$paymentUrl = null;
$transactionId = null;
if ($paymentForDb !== 'cash') {
    $cinetpay = new SuzoskyCinetPayIntegration();
    $paymentResult = $cinetpay->initiateOrderPayment($orderNumber, $price);
    logMessage('diagnostics_cinetpay.log', 'Init paiement (order ' . $orderNumber . '): ' . json_encode($paymentResult));
    if (!empty($paymentResult['success'])) {
        $paymentUrl = $paymentResult['payment_url'] ?? null;
        $transactionId = $paymentResult['transaction_id'] ?? null;
    }
}
// Exposer aussi code_commande si présent
$codeCommande = $identifierCols['code_commande'] ?? ($generatedCode ?? null);

// Construire réponse
$responseData = [
    'order_id' => $orderId,
    'order_number' => $orderNumber,
    'code_commande' => $codeCommande,
    'price' => $price,
    'payment_method' => $paymentMethod
];
if ($paymentUrl) {
    $responseData['payment_url'] = $paymentUrl;
    $responseData['transaction_id'] = $transactionId;
}

// Appel attribution automatique du coursier le plus proche
if (!empty($departureLat) && !empty($departureLng) && !empty($orderId)) {
    // Construire une URL robuste vers l'API en se basant sur l'hôte courant (HTTP/HTTPS + host + base path)
    // Utiliser le helper centralisé pour construire l'URL
    if (!function_exists('appUrl')) { require_once __DIR__ . '/../config.php'; }
    $assignUrl = appUrl('api/assign_nearest_coursier.php');
    logMessage('diagnostics_errors.log', 'Attribution URL: ' . $assignUrl);
    $assignPayload = json_encode([
        'order_id' => $orderId,
        'departure_lat' => $departureLat,
        'departure_lng' => $departureLng
    ]);
    // Log the payload for debugging
    logMessage('diagnostics_errors.log', 'Attribution payload: ' . $assignPayload);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $assignPayload,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);

    // Use cURL for robust internal POST
    $ch = curl_init($assignUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $assignPayload);
    $curlResult = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    logMessage('diagnostics_errors.log', "Attribution cURL response code: $httpCode, error: $curlError");

    $assignResult = null;
    $assignData = null;

    if ($curlResult !== false && $httpCode >= 200 && $httpCode < 300) {
        $assignResult = $curlResult;
    } else {
        $reason = $curlResult === false ? 'cURL returned false' : "HTTP $httpCode";
        logMessage('diagnostics_errors.log', 'Attribution via cURL indisponible (' . $reason . ')');

        // Fallback uniquement si cURL n'a retourné aucune réponse (code HTTP nul)
        if ($curlResult === false || $httpCode === 0) {
            logMessage('diagnostics_errors.log', 'Tentative fallback file_get_contents sur assign_nearest_coursier.php');
            try {
                $assignResult = @file_get_contents($assignUrl, false, $context);
            } catch (Throwable $fallbackError) {
                logMessage('diagnostics_errors.log', 'Attribution fallback error: ' . $fallbackError->getMessage());
                $assignResult = null;
            }
        }
    }

    if (is_string($assignResult) && $assignResult !== '') {
        $assignData = json_decode($assignResult, true);
    }
    logMessage('diagnostics_errors.log', 'Attribution result: ' . var_export($assignResult, true));
    
    // Vérifier que la réponse est un tableau avant d'accéder aux clés
    if (is_array($assignData) && !empty($assignData['coursier_id'])) {
        $responseData['coursier_id'] = $assignData['coursier_id'];
        $responseData['distance_km'] = $assignData['distance_km'];
    } else {
        // Pas de coursier disponible ou réponse invalide, non bloquant
        logMessage('diagnostics_errors.log', 'Aucun coursier disponible ou réponse d\'attribution invalide');
    }
} else {
    logMessage('diagnostics_errors.log', 'Attribution skipped: missing coordinates or order_id');
}
// Nettoyer toute sortie parasite avant d'envoyer la réponse JSON
$output = ob_get_clean();
if (trim($output) !== '') {
    // Loguer la sortie parasite pour debug
    logMessage('diagnostics_errors.log', '⚠️ Sortie parasite détectée dans submit_order.php : ' . substr($output, 0, 500));
}
// Ajout d'un header personnalisé pour debug
header('X-Debug-Api-Submit-Order: OK');
// Générer la réponse JSON
$jsonResponse = json_encode([
    'success' => true,
    'data' => $responseData,
    'debug' => [
        'date' => date('Y-m-d H:i:s'),
        'php_sapi' => php_sapi_name(),
        'output_buffer' => isset($output) ? substr($output, 0, 200) : null
    ]
]);
// Loguer la réponse JSON pour analyse
logMessage('diagnostics_errors.log', 'RETOUR_JSON: ' . $jsonResponse);
echo $jsonResponse;

} catch (Exception $e) {
    logMessage('diagnostics_errors.log', 'Exception submit_order: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
