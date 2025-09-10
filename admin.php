<?php
// Début du script admin
session_start();

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
	header('Location: admin.php?section=clients');
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
	header('Location: admin.php?section=clients');
	exit;
}

// Lancer l'interface admin
require_once __DIR__ . '/admin/admin.php';
exit;
