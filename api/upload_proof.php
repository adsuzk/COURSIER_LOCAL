<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }

    $commandeId = isset($_POST['commande_id']) ? (int)$_POST['commande_id'] : 0;
    $type = isset($_POST['type']) ? strtolower(trim($_POST['type'])) : '';
    $coursierId = isset($_POST['coursier_id']) ? (int)$_POST['coursier_id'] : null;

    if (!$commandeId || !in_array($type, ['photo','signature'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
        exit;
    }

    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Fichier manquant ou invalide']);
        exit;
    }

    $file = $_FILES['proof'];
    $mime = mime_content_type($file['tmp_name']);
    $allowed = $type === 'photo' ? ['image/jpeg','image/png','image/webp'] : ['image/png','image/jpeg','application/octet-stream'];
    if (!in_array($mime, $allowed)) {
        http_response_code(415);
        echo json_encode(['success' => false, 'error' => 'Type de fichier non supporté', 'mime' => $mime]);
        exit;
    }

    $baseDir = __DIR__ . '/../data/proofs/' . $commandeId;
    if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true)) {
        throw new RuntimeException('Impossible de créer le dossier de stockage');
    }

    $ext = '.bin';
    if ($mime === 'image/jpeg') $ext = '.jpg';
    if ($mime === 'image/png') $ext = '.png';
    if ($mime === 'image/webp') $ext = '.webp';

    $safeType = preg_replace('/[^a-z]/','',$type);
    $filename = $safeType . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . $ext;
    $destPath = $baseDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Echec de sauvegarde du fichier');
    }

    $relPath = 'data/proofs/' . $commandeId . '/' . $filename;

    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO delivery_proofs (commande_id, type, file_path, mime_type, size_bytes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$commandeId, $type, $relPath, $mime, (int)$file['size'], $coursierId]);

    echo json_encode(['success' => true, 'path' => $relPath]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
