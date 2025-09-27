<?php
/**
 * ============================================================================
 * 📎 API TÉLÉVERSEMENT FICHIERS RÉCLAMATIONS - SUZOSKY
 * ============================================================================
 *
 * Gère le stockage sécurisé des pièces jointes envoyées depuis le chat IA.
 * Les fichiers sont persistés dans storage/reclamations et renvoyés sous
 * forme de chemins relatifs exploitables par l'admin.
 *
 * @version 1.0.0
 * @date 25 septembre 2025
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/SystemSync.php';

try {
    if (empty($_FILES['files'])) {
        throw new RuntimeException('Aucun fichier reçu');
    }

    $storageRoot = __DIR__ . '/../storage/reclamations';
    if (!is_dir($storageRoot) && !@mkdir($storageRoot, 0775, true) && !is_dir($storageRoot)) {
        throw new RuntimeException('Impossible de créer le dossier de stockage');
    }

    $guestId = $_POST['guest_id'] ?? null;
    $conversationId = $_POST['conversation_id'] ?? null;

    $saved = [];
    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5 Mo

    $baseUrl = method_exists('SystemSync', 'snapshot') && function_exists('getAppBaseUrl')
        ? rtrim(getAppBaseUrl(), '/')
        : '';

    foreach ($_FILES['files']['tmp_name'] as $idx => $tmpName) {
        $error = $_FILES['files']['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erreur lors du téléchargement d\'un fichier (code ' . $error . ')');
        }

        $size = (int) ($_FILES['files']['size'][$idx] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            throw new RuntimeException('Fichier trop volumineux (max 5 Mo)');
        }

        $originalName = $_FILES['files']['name'][$idx] ?? 'fichier';
        $mime = mime_content_type($tmpName) ?: $_FILES['files']['type'][$idx] ?? 'application/octet-stream';
        if (!in_array($mime, $allowedMime, true)) {
            throw new RuntimeException('Type de fichier non autorisé');
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!$ext) {
            $ext = $mime === 'application/pdf' ? 'pdf' : 'jpg';
        }

        $subDir = date('Y/m');
        $targetDir = $storageRoot . '/' . $subDir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Impossible de préparer le dossier de destination');
        }

        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destination = $targetDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Echec de l\'enregistrement du fichier');
        }

        $relativePath = 'storage/reclamations/' . $subDir . '/' . $filename;
        $saved[] = [
            'original_name' => $originalName,
            'path' => $relativePath,
            'url' => $baseUrl ? ($baseUrl . '/' . $relativePath) : $relativePath,
            'size' => $size,
            'mime' => $mime,
        ];
    }

    SystemSync::record('ai_chat_upload', 'ok', [
        'guest_id' => $guestId,
        'conversation_id' => $conversationId,
        'files' => array_map(static function ($file) {
            return [
                'path' => $file['path'],
                'mime' => $file['mime'],
                'size' => $file['size'],
            ];
        }, $saved),
    ]);

    echo json_encode([
        'success' => true,
        'files' => $saved,
    ]);
} catch (Throwable $e) {
    SystemSync::record('ai_chat_upload', 'error', [
        'error' => $e->getMessage(),
        'guest_id' => $_POST['guest_id'] ?? null,
    ]);

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
