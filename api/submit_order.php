

<?php
// Log de debug ultra-précoce (toute requête entrante, avant tout traitement)
if (file_exists(__DIR__ . '/../logger.php')) {
	$debugLog = __DIR__ . '/../diagnostic_logs/diagnostics_errors.log';
	$debugHeaders = json_encode(getallheaders() ?: []);
	$debugBody = file_get_contents('php://input');
	$debugMeta = [
		'date' => date('Y-m-d H:i:s'),
		'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
		'method' => $_SERVER['REQUEST_METHOD'] ?? '',
		'uri' => $_SERVER['REQUEST_URI'] ?? '',
		'headers' => $debugHeaders,
		'body' => $debugBody
	];
	file_put_contents($debugLog, "[DEBUG-REQ] " . json_encode($debugMeta) . "\n", FILE_APPEND);
}
// Réactivation progressive du squelette métier
header('Content-Type: application/json');

// Chargement des dépendances principales
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../lib/db_maintenance.php';
require_once __DIR__ . '/../cinetpay/cinetpay_integration.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$sessionIndicators = [
	$_SESSION['client_id'] ?? null,
	$_SESSION['client_email'] ?? null,
	$_SESSION['client_telephone'] ?? null,
];
$hasClientSession = false;
foreach ($sessionIndicators as $value) {
	if (!empty($value)) {
		$hasClientSession = true;
		break;
	}
}

if (!$hasClientSession) {
	http_response_code(401);
	echo json_encode([
		'success' => false,
		'message' => 'Session client requise. Veuillez vous connecter avant de commander.',
		'code' => 'AUTH_REQUIRED'
	]);
	exit;
}

// Protection anti-sortie parasite : buffer de sortie
ob_start();

// Gestion d'erreur simple (étape 1)
set_error_handler(function($severity, $message, $file, $line) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => $message, 'debug' => 'Erreur PHP']);
	exit;
});

set_exception_handler(function($e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => 'Exception PHP']);
	exit;
});

// Log minimal de la requête
if (function_exists('logMessage')) {
	logMessage('diagnostics_errors.log', 'submit_order.php appelé');
}

// Lecture des données POST (JSON ou x-www-form-urlencoded)
$rawInput = file_get_contents('php://input');
$data = null;
if (!empty($rawInput)) {
	$data = json_decode($rawInput, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		// Si ce n'est pas du JSON, tenter x-www-form-urlencoded
		parse_str($rawInput, $data);
	}
} else {
	$data = $_POST;
}

// --- Rétro-compatibilité: normaliser les clés entrantes (anglais -> français)
require_once __DIR__ . '/field_normalizer.php';
if (is_array($data)) {
	normalize_input_fields($data);
}

// Normaliser différentes variantes de noms pour les coordonnées (pickup/departure)
$latCandidates = ['departure_lat', 'latitude_depart', 'lat_depart', 'latitude_retrait', 'lat_retrait', 'latitude_pickup', 'lat_pickup'];
$lngCandidates = ['departure_lng', 'longitude_depart', 'lng_depart', 'longitude_retrait', 'lng_retrait', 'longitude_pickup', 'lng_pickup'];
foreach ($latCandidates as $k) {
	if (isset($data[$k]) && $data[$k] !== '') {
		$data['departure_lat'] = $data[$k];
		break;
	}
}
foreach ($lngCandidates as $k) {
	if (isset($data[$k]) && $data[$k] !== '') {
		$data['departure_lng'] = $data[$k];
		break;
	}
}


// Validation complète des champs attendus

// Champs obligatoires selon la structure réelle de la table
$requiredFields = [
	'adresse_depart', 'adresse_arrivee', 'telephone_expediteur', 'telephone_destinataire', 'priorite', 'mode_paiement', 'prix_estime'
];
$errors = [];
foreach ($requiredFields as $field) {
	if (empty($data[$field])) {
		$errors[] = "Le champ '$field' est obligatoire.";
	}
}

// Mapping des champs pour correspondre à la table 'commandes'
$fields = [
	'adresse_depart' => $data['adresse_depart'] ?? '',
	'adresse_arrivee' => $data['adresse_arrivee'] ?? '',
	'telephone_expediteur' => $data['telephone_expediteur'] ?? '',
	'telephone_destinataire' => $data['telephone_destinataire'] ?? '',
	'description_colis' => $data['description_colis'] ?? '', // optionnel
	'priorite' => $data['priorite'] ?? 'normale',
	'mode_paiement' => $data['mode_paiement'] ?? 'especes',
	'prix_estime' => $data['prix_estime'] ?? 0,
	'latitude_depart' => isset($data['departure_lat']) && $data['departure_lat'] !== '' ? floatval($data['departure_lat']) : null,
	'longitude_depart' => isset($data['departure_lng']) && $data['departure_lng'] !== '' ? floatval($data['departure_lng']) : null,
	'distance_estimee' => $data['distance_estimee'] ?? null,
	'dimensions' => $data['dimensions'] ?? null,
	'poids_estime' => $data['poids_estime'] ?? null,
	'fragile' => $data['fragile'] ?? 0,
	'created_at' => date('Y-m-d H:i:s'),
];

// Générer un code_commande unique (court, <=20 chars) si la table requiert une valeur unique
try {
	$rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
} catch (Throwable $e) {
	$rand = strtoupper(substr(md5(uniqid('', true)), 0, 3));
}
$fields['code_commande'] = 'SZ' . date('ymdHis') . $rand; // ex: SZ250930123045A1B

// Générer order_number unique (format lisible, utilisé ailleurs)
try {
	$uniq = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
} catch (Throwable $e) {
	$uniq = strtoupper(substr(md5(uniqid('', true)), 0, 6));
}
$fields['order_number'] = 'SZK' . date('ymd') . $uniq; // ex: SZK250930A1B2C3
$fields['statut'] = 'nouvelle';

// Log des données reçues
if (function_exists('logMessage')) {
	logMessage('diagnostics_errors.log', 'submit_order.php DATA: ' . json_encode($data));
}

if (!empty($errors)) {
	echo json_encode(["success" => false, "message" => "Erreur de validation", "errors" => $errors, "debug" => $data]);
	exit;
}


// Connexion à la base de données (mode développement)
try {
	$dbConf = $config['db']['development'];
	$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbConf['host'], $dbConf['port'], $dbConf['name']);
	$pdo = new PDO($dsn, $dbConf['user'], $dbConf['password'], [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	]);
} catch (Throwable $e) {
	if (function_exists('logMessage')) {
		logMessage('diagnostics_errors.log', 'Erreur connexion DB: ' . $e->getMessage());
	}
	echo json_encode(["success" => false, "message" => "Erreur connexion base de données", "error" => $e->getMessage()]);
	exit;
}




// Insertion réelle en base de données (table 'commandes')
try {
	$sql = "INSERT INTO commandes (order_number, code_commande, adresse_depart, adresse_arrivee, telephone_expediteur, telephone_destinataire, description_colis, priorite, mode_paiement, prix_estime, latitude_depart, longitude_depart, distance_estimee, dimensions, poids_estime, fragile, statut, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([
	$fields['order_number'],
	$fields['code_commande'],
	$fields['adresse_depart'],
	$fields['adresse_arrivee'],
	$fields['telephone_expediteur'],
	$fields['telephone_destinataire'],
	$fields['description_colis'],
	$fields['priorite'],
	$fields['mode_paiement'],
	$fields['prix_estime'],
	$fields['latitude_depart'],
	$fields['longitude_depart'],
	$fields['distance_estimee'],
	$fields['dimensions'],
	$fields['poids_estime'],
	$fields['fragile'],
	$fields['statut'],
	$fields['created_at']
	]);
	$commande_id = $pdo->lastInsertId();
	if (function_exists('logMessage')) {
		logMessage('diagnostics_errors.log', 'Commande insérée: ' . json_encode($fields) . ' | id=' . $commande_id);
	}
	
	// ⚡ ATTRIBUTION AUTOMATIQUE + NOTIFICATION FCM
	try {
		// Rechercher un coursier connecté et disponible
		$stmtCoursier = $pdo->query("
			SELECT id, nom, prenoms, matricule, telephone
			FROM agents_suzosky 
			WHERE statut_connexion = 'en_ligne' 
			AND COALESCE(solde_wallet, 0) >= 100
			ORDER BY COALESCE(solde_wallet, 0) DESC, last_login_at DESC
			LIMIT 1
		");
		$coursier = $stmtCoursier->fetch(PDO::FETCH_ASSOC);
		
		if ($coursier) {
			// Assigner le coursier à la commande
			$stmtAssign = $pdo->prepare("UPDATE commandes SET coursier_id = ?, statut = 'attribuee', updated_at = NOW() WHERE id = ?");
			$stmtAssign->execute([$coursier['id'], $commande_id]);
			
			if (function_exists('logMessage')) {
				logMessage('diagnostics_errors.log', "Coursier #{$coursier['id']} ({$coursier['nom']}) attribué à commande #{$commande_id}");
			}
			
			// Envoyer notification FCM
			$stmtToken = $pdo->prepare("
				SELECT token FROM device_tokens 
				WHERE coursier_id = ? AND is_active = 1 
				ORDER BY updated_at DESC LIMIT 1
			");
			$stmtToken->execute([$coursier['id']]);
			$tokenData = $stmtToken->fetch(PDO::FETCH_ASSOC);
			
			if ($tokenData && !empty($tokenData['token'])) {
				// Charger le système FCM
				require_once __DIR__ . '/lib/fcm_enhanced.php';
				
				$title = "🚚 Nouvelle commande #{$fields['code_commande']}";
				$body = "De: {$fields['adresse_depart']}\nVers: {$fields['adresse_arrivee']}\nPrix: {$fields['prix_estime']} FCFA";
				
				$notifData = [
					'type' => 'new_order',
					'commande_id' => $commande_id,
					'code_commande' => $fields['code_commande'],
					'adresse_depart' => $fields['adresse_depart'],
					'adresse_arrivee' => $fields['adresse_arrivee'],
					'prix_estime' => $fields['prix_estime'],
					'priorite' => $fields['priorite']
				];
				
				$fcmResult = fcm_send_with_log(
					[$tokenData['token']], 
					$title, 
					$body, 
					$notifData,
					$coursier['id'],
					'SUBMIT_ORDER_AUTO'
				);
				
				if (function_exists('logMessage')) {
					$notifStatus = $fcmResult['success'] ? 'envoyée' : 'échec';
					logMessage('diagnostics_errors.log', "Notification FCM $notifStatus pour coursier #{$coursier['id']}");
				}
			} else {
				if (function_exists('logMessage')) {
					logMessage('diagnostics_errors.log', "Aucun token FCM actif pour coursier #{$coursier['id']}");
				}
			}
			
			$assignationInfo = [
				'coursier_assigne' => true,
				'coursier_id' => $coursier['id'],
				'coursier_nom' => $coursier['nom'] . ' ' . $coursier['prenoms'],
				'matricule' => $coursier['matricule'],
				'notification_envoyee' => !empty($tokenData)
			];
		} else {
			if (function_exists('logMessage')) {
				logMessage('diagnostics_errors.log', 'Aucun coursier disponible pour commande #' . $commande_id);
			}
			$assignationInfo = [
				'coursier_assigne' => false,
				'message' => 'Commande créée, en attente de coursier disponible'
			];
		}
	} catch (Throwable $eAttr) {
		if (function_exists('logMessage')) {
			logMessage('diagnostics_errors.log', 'Erreur attribution: ' . $eAttr->getMessage());
		}
		$assignationInfo = ['attribution_error' => $eAttr->getMessage()];
	}
	
	echo json_encode([
		"success" => true, 
		"message" => "Commande insérée en base", 
		"commande_id" => $commande_id, 
		"commande" => $fields,
		"assignation" => $assignationInfo ?? null
	]);
} catch (Throwable $e) {
	if (function_exists('logMessage')) {
		logMessage('diagnostics_errors.log', 'Erreur insertion commande: ' . $e->getMessage());
	}
	echo json_encode(["success" => false, "message" => "Erreur lors de l'insertion en base", "error" => $e->getMessage(), "commande" => $fields]);
}
