<?php
// VERSION CORRIGÉE 2025-09-04 21:00 - PROTECTION CLIENT_ID FOREIGN KEY
// Unified JSON response and error handling
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin', '*');
// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
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
    // Assurer l'existence de la table clients pour compatibilité FK
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS clients LIKE clients_particuliers");
        $pdo->exec("INSERT IGNORE INTO clients SELECT * FROM clients_particuliers");
        logMessage('diagnostics_db.log', 'Table clients vérifiée/créée avec succès');
    } catch (Exception $e) {
        logMessage('diagnostics_errors.log', 'Erreur vérification/création table clients: ' . $e->getMessage());
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

try {
// Préparer les données
$departure = trim($data['departure']);
$destination = trim($data['destination']);
$senderPhone = trim($data['senderPhone']);
$receiverPhone = trim($data['receiverPhone']);
$packageDescription = trim($data['packageDescription'] ?? '');
$priority = trim($data['priority']);
$paymentMethod = trim($data['paymentMethod']);
$price = floatval($data['price'] ?? 0);
$distance = trim($data['distance'] ?? '');
$duration = trim($data['duration'] ?? '');

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
    'cash' => 'cash',
    'orange_money' => 'orange_money',
    'mobile_money' => 'orange_money',
    'mtn_money' => 'mtn_money',
    'moov_money' => 'moov_money',
    'card' => 'card',
    'wave' => 'wave'
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
$cols = array_merge($cols, [
    'client_id', 'expediteur_id', 'destinataire_id',
    'adresse_depart', 'adresse_arrivee',
    'telephone_expediteur', 'telephone_destinataire',
    'description_colis', 'priorite', 'mode_paiement',
    'prix_estime'
]);
// Valeurs communes
$vals = array_merge($vals, [
    $finalClientId,  // client_id referencing `clients` - SÉCURISÉ
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
echo json_encode([
    'success' => true,
    'data' => $responseData
]);

} catch (Exception $e) {
    logMessage('diagnostics_errors.log', 'Exception submit_order: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
