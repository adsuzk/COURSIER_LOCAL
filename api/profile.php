<?php
// api/profile.php - Profil coursier (nom, prenoms, téléphone, stats)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

function json_ok($data) { echo json_encode(['success' => true, 'data' => $data]); exit; }
function json_err($msg, $code = 400, $error = 'INVALID_REQUEST') { http_response_code($code); echo json_encode(['success' => false, 'error' => $error, 'message' => $msg]); exit; }

try {
	$coursierId = intval($_GET['coursier_id'] ?? $_POST['coursier_id'] ?? 0);
	if ($coursierId <= 0) {
		json_err('ID coursier requis', 400, 'MISSING_COURSIER_ID');
	}

	$pdo = getPDO();

	// Essayer plusieurs schémas connus
	$profile = null;
	// 1) agents_suzosky (nom + prenoms + telephone + email + matricule)
	try {
		$st = $pdo->prepare("SELECT id, nom, prenoms, matricule, telephone, email, created_at FROM agents_suzosky WHERE id = ? LIMIT 1");
		$st->execute([$coursierId]);
		$row = $st->fetch();
		if ($row) { $profile = $row; }
	} catch (Throwable $e) { /* table absente */ }

	// 2) agents (nom + telephone)
	if (!$profile) {
		try {
			$st = $pdo->prepare("SELECT id, nom, '' AS prenoms, '' AS matricule, telephone, NULL AS email, created_at FROM agents WHERE id = ? LIMIT 1");
			$st->execute([$coursierId]);
			$row = $st->fetch();
			if ($row) { $profile = $row; }
		} catch (Throwable $e) { }
	}

	// 3) coursiers (nom + statut)
	if (!$profile) {
		try {
			$st = $pdo->prepare("SELECT id, nom, '' AS prenoms, '' AS matricule, '' AS telephone, NULL AS email, created_at FROM coursiers WHERE id = ? LIMIT 1");
			$st->execute([$coursierId]);
			$row = $st->fetch();
			if ($row) { $profile = $row; }
		} catch (Throwable $e) { }
	}

	if (!$profile) {
		json_err('Coursier non trouvé', 404, 'COURSIER_NOT_FOUND');
	}

	$nom = trim(($profile['nom'] ?? ''));
	$prenoms = trim(($profile['prenoms'] ?? ''));
	$matricule = trim(($profile['matricule'] ?? ''));
	$telephone = trim(($profile['telephone'] ?? ''));
	$email = trim(($profile['email'] ?? ''));
	$dateInscription = null;
	if (!empty($profile['created_at'])) {
		try { $dateInscription = date('Y-m-d', strtotime($profile['created_at'])); } catch (Throwable $e) { $dateInscription = null; }
	}

	// Stats rapides depuis commandes
	$totalCommandes = 0;
	$noteGlobale = 0.0;
	try {
		$st = $pdo->prepare("SELECT COUNT(*) AS total FROM commandes WHERE coursier_id = ?");
		$st->execute([$coursierId]);
		$totalCommandes = intval($st->fetch()['total'] ?? 0);
	} catch (Throwable $e) { $totalCommandes = 0; }
	try {
		$st = $pdo->prepare("SELECT AVG(note_client) AS avg_note FROM commandes WHERE coursier_id = ? AND note_client IS NOT NULL");
		$st->execute([$coursierId]);
		$avg = $st->fetch()['avg_note'] ?? null;
		$noteGlobale = $avg !== null ? round((float)$avg, 1) : 0.0;
	} catch (Throwable $e) { $noteGlobale = 0.0; }

	json_ok([
		'id' => $coursierId,
		'nom' => $nom,
		'prenoms' => $prenoms,
		'matricule' => $matricule,
		'telephone' => $telephone,
		'email' => $email,
		'date_inscription' => $dateInscription,
		'total_commandes' => $totalCommandes,
		'note_globale' => $noteGlobale
	]);

} catch (Throwable $e) {
	json_err('Erreur interne: ' . $e->getMessage(), 500, 'SYSTEM_ERROR');
}
?>
