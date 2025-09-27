<?php
// Début du script admin
session_start();
// Démarrage du buffer pour permettre les header() après output
ob_start();
// Désactiver le cache pour l'interface admin
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
// Backfill silencieux des comptes coursiers à chaque ouverture de l'admin
try {
	require_once __DIR__ . '/lib/finances_sync.php';
	$pdo_backfill = getDBConnection();
	backfillCourierAccounts($pdo_backfill);
} catch (Throwable $e) {
	// Ne pas bloquer l'admin, journaliser si nécessaire
	// error_log('Backfill comptes coursiers: ' . $e->getMessage());
}

// Mettre à jour le statut d'un client (actif/inactif)
if (isset($_GET['section'], $_GET['action'], $_GET['id'], $_GET['status'], $_GET['type'])
	&& $_GET['section'] === 'clients'
	&& $_GET['action'] === 'update_status'
	&& is_numeric($_GET['id'])
	&& in_array($_GET['status'], ['actif','inactif'], true)
	&& in_array($_GET['type'], ['private','business'], true)
) {
	require_once __DIR__ . '/config.php';
	$pdo = getPDO();
	$table = $_GET['type'] === 'private' ? 'clients_particuliers' : 'business_clients';
	$stmt = $pdo->prepare("UPDATE {$table} SET statut = ? WHERE id = ?");
	$stmt->execute([$_GET['status'], (int)$_GET['id']]);
	getJournal()->logMaxDetail(
		'UPDATE_CLIENT_STATUS',
		"Mise à jour du statut client #{$_GET['id']} en {$_GET['status']}",
		['id' => (int)$_GET['id'], 'status' => $_GET['status'], 'type' => $_GET['type']]
	);
	header('Location: ' . routePath('admin.php?section=clients'));
	exit;
}

// Suppression d'un client particulier si demandé
if (isset($_GET['section'], $_GET['action'], $_GET['id'])
	&& $_GET['section'] === 'clients'
	&& $_GET['action'] === 'delete_client'
	&& is_numeric($_GET['id'])) {
	require_once __DIR__ . '/config.php';
	$pdo = getPDO();
	$stmt = $pdo->prepare('DELETE FROM clients_particuliers WHERE id = ?');
	$stmt->execute([(int)$_GET['id']]);
	// Journalisation de la suppression de client
	getJournal()->logMaxDetail(
		'DELETE_CLIENT',
		"Suppression du client particulier #{$_GET['id']}",
		[
			'id'   => (int)$_GET['id'],
			'file' => __FILE__,
			'line' => __LINE__
		]
	);
	header('Location: ' . routePath('admin.php?section=clients'));
	exit;
}

// Lancer l'interface admin
require_once __DIR__ . '/admin/admin.php';
exit;
// Fin du script admin: vider le buffer pour envoyer tout le contenu
ob_end_flush();
