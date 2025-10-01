

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
	
	// ⚡ ÉTAPE 1: NOTIFICATION FCM (réveiller l'app AVANT l'attribution)
	try {
		// Rechercher coursiers avec tokens FCM actifs
		$stmtCoursiers = $pdo->query("
			SELECT a.id, a.nom, a.prenoms, a.matricule, a.telephone, a.statut_connexion,
			       dt.token, a.last_login_at
			FROM agents_suzosky a
			INNER JOIN device_tokens dt ON dt.coursier_id = a.id AND dt.is_active = 1
			WHERE a.status = 'actif'
			ORDER BY 
			    CASE WHEN a.statut_connexion = 'en_ligne' THEN 1 ELSE 2 END,
			    a.last_login_at DESC
			LIMIT 5
		");
		$coursiersDisponibles = $stmtCoursiers->fetchAll(PDO::FETCH_ASSOC);
		
		$notificationsSent = [];
		$notificationsFailed = [];
		
		if (count($coursiersDisponibles) > 0) {
			// Charger le système FCM
			if (file_exists(__DIR__ . '/../fcm_manager.php')) {
				require_once __DIR__ . '/../fcm_manager.php';
				$fcm = new FCMManager();
				
				$title = "🚚 Nouvelle commande #{$fields['code_commande']}";
				$body = "De: {$fields['adresse_depart']}\nVers: {$fields['adresse_arrivee']}\nPrix: {$fields['prix_estime']} FCFA";
				
				$notifData = [
					'type' => 'new_order',
					'commande_id' => (string)$commande_id,
					'code_commande' => $fields['code_commande'],
					'adresse_depart' => $fields['adresse_depart'],
					'adresse_arrivee' => $fields['adresse_arrivee'],
					'prix_estime' => (string)$fields['prix_estime'],
					'priorite' => $fields['priorite'],
					'action' => 'open_order_details'
				];
				
				// ÉTAPE 2: Envoyer aux coursiers disponibles
				foreach ($coursiersDisponibles as $coursier) {
					$fcmResult = $fcm->envoyerNotification($coursier['token'], $title, $body, $notifData);
					
					if ($fcmResult['success']) {
						$notificationsSent[] = [
							'coursier_id' => $coursier['id'],
							'nom' => $coursier['nom'] . ' ' . $coursier['prenoms'],
							'statut_connexion' => $coursier['statut_connexion']
						];
						
						if (function_exists('logMessage')) {
							logMessage('diagnostics_errors.log', "✅ Notification FCM envoyée à coursier #{$coursier['id']} ({$coursier['nom']}) - statut: {$coursier['statut_connexion']}");
						}
					} else {
						$notificationsFailed[] = [
							'coursier_id' => $coursier['id'],
							'nom' => $coursier['nom'] . ' ' . $coursier['prenoms'],
							'error' => $fcmResult['message'] ?? 'Erreur inconnue'
						];
						
						if (function_exists('logMessage')) {
							logMessage('diagnostics_errors.log', "❌ Échec notification FCM coursier #{$coursier['id']}: " . ($fcmResult['message'] ?? 'inconnu'));
						}
					}
				}
			}
		}
		
		// ÉTAPE 3: DIAGNOSTIC ET LOGGING DÉTAILLÉ
		$diagnosticInfo = [
			'coursiers_disponibles' => count($coursiersDisponibles),
			'notifications_envoyees' => count($notificationsSent),
			'notifications_echouees' => count($notificationsFailed),
			'commande_id' => $commande_id,
			'timestamp' => date('Y-m-d H:i:s')
		];
		
		if (function_exists('logMessage')) {
			logMessage('diagnostics_errors.log', "📊 DIAGNOSTIC ATTRIBUTION: " . json_encode($diagnosticInfo, JSON_UNESCAPED_UNICODE));
		}
		
		// ÉTAPE 4: ATTRIBUTION (prendre le premier coursier notifié avec succès)
		$coursierAttribue = null;
		if (count($notificationsSent) > 0) {
			$coursierAttribue = $coursiersDisponibles[0]; // Premier de la liste (meilleure priorité)
			
			$stmtAssign = $pdo->prepare("UPDATE commandes SET coursier_id = ?, statut = 'nouvelle', updated_at = NOW() WHERE id = ?");
			$stmtAssign->execute([$coursierAttribue['id'], $commande_id]);
			
			if (function_exists('logMessage')) {
				logMessage('diagnostics_errors.log', "✅ Commande #{$commande_id} attribuée à coursier #{$coursierAttribue['id']} ({$coursierAttribue['nom']} {$coursierAttribue['prenoms']})");
			}
			
			$assignationInfo = [
				'coursier_assigne' => true,
				'coursier_id' => $coursierAttribue['id'],
				'coursier_nom' => $coursierAttribue['nom'] . ' ' . $coursierAttribue['prenoms'],
				'matricule' => $coursierAttribue['matricule'],
				'statut_connexion' => $coursierAttribue['statut_connexion'],
				'notifications_envoyees' => count($notificationsSent),
				'diagnostic' => $diagnosticInfo
			];
		} else {
			if (function_exists('logMessage')) {
				logMessage('diagnostics_errors.log', "⚠️ Aucun coursier disponible ou notifications échouées pour commande #{$commande_id}");
			}
			
			$assignationInfo = [
				'coursier_assigne' => false,
				'message' => 'Commande créée, en attente de coursier disponible',
				'coursiers_trouves' => count($coursiersDisponibles),
				'notifications_echouees' => count($notificationsFailed),
				'diagnostic' => $diagnosticInfo
			];
		}
		
	} catch (Throwable $eAttr) {
		if (function_exists('logMessage')) {
			logMessage('diagnostics_errors.log', '❌ Erreur attribution/notification: ' . $eAttr->getMessage());
		}
		$assignationInfo = [
			'attribution_error' => $eAttr->getMessage(),
			'trace' => $eAttr->getTraceAsString()
		];
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
