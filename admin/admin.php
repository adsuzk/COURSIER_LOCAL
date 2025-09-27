<?php
// Anti-cache strict pour l'interface d'administration
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
// Gestion upload APK avant tout output HTML
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_apk') {
    $appType = $_POST['app_type'] ?? 'coursier'; // coursier ou client
    $uploadDir = __DIR__ . '/uploads/' . $appType;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }
    
    $err = null;
    if (!isset($_FILES['apk_file']) || ($_FILES['apk_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $err = "Aucun fichier APK reçu.";
    } else {
        $file = $_FILES['apk_file'];
        $size = (int)$file['size'];
        $name = (string)$file['name'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        // Limite 200 Mo
        $maxSize = 200 * 1024 * 1024;
        if ($ext !== 'apk') {
            $err = "Le fichier doit être une APK (.apk).";
        } elseif ($size <= 0 || $size > $maxSize) {
            $err = "Taille invalide (max 200 Mo).";
        }
        if (!$err) {
            $ts = date('Ymd-His');
            $safeBase = 'suzosky-' . $appType . '-' . $ts . '.apk';
            $destPath = $uploadDir . '/' . $safeBase;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $err = "Impossible d'enregistrer le fichier sur le serveur.";
            } else {
                // Traiter optionnellement un JSON de métadonnées joint
                $metaExtras = [];
                if (isset($_FILES['apk_meta']) && ($_FILES['apk_meta']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $metaTmp = $_FILES['apk_meta']['tmp_name'];
                    $jsonStr = @file_get_contents($metaTmp);
                    $json = json_decode($jsonStr, true);
                    if (is_array($json)) {
                        // Support output-metadata.json d'Android Gradle
                        if (isset($json['elements'][0])) {
                            $el = $json['elements'][0];
                            if (isset($el['versionCode'])) $metaExtras['version_code'] = (int)$el['versionCode'];
                            if (isset($el['versionName'])) $metaExtras['version_name'] = (string)$el['versionName'];
                        }
                        // Support JSON simple {version_code, version_name, changelog, force_update, min_supported_version}
                        if (isset($json['version_code'])) $metaExtras['version_code'] = (int)$json['version_code'];
                        if (isset($json['version_name'])) $metaExtras['version_name'] = (string)$json['version_name'];
                        if (isset($json['changelog']) && is_array($json['changelog'])) $metaExtras['changelog'] = $json['changelog'];
                        if (isset($json['force_update'])) $metaExtras['force_update'] = (bool)$json['force_update'];
                        if (isset($json['min_supported_version'])) $metaExtras['min_supported_version'] = (int)$json['min_supported_version'];
                    }
                }

                // Écrire/mettre à jour latest_apk.json (conserve la version précédente)
                require_once __DIR__ . '/../lib/version_helpers.php';
                $latestMeta = vu_update_latest_meta_with_previous($safeBase, $metaExtras, $appType);

                // Si aucune méta fournie, tenter une détection automatique complémentaire
                if (empty($metaExtras)) {
                    $updateScript = __DIR__ . '/update_apk_metadata.php';
                    if (file_exists($updateScript)) {
                        ob_start();
                        include $updateScript;
                        ob_end_clean();
                    }
                }

                // Synchroniser immédiatement la config des versions (utilisée par l'API et le dashboard)
                $cfg = vu_load_versions_config();
                vu_overlay_with_latest_upload($cfg, true, $appType);

                // Automatiquement créer/mettre à jour l'entrée app_versions
                try {
                    require_once __DIR__ . '/../config.php';
                    $pdo = getPDO();

                    // Extraire version du nom de fichier ou incrémenter
                    $currentMaxVersion = $pdo->query("SELECT COALESCE(MAX(version_code), 0) as max_version FROM app_versions WHERE app_type = '$appType'")->fetchColumn();
                    $newVersionCode = $currentMaxVersion + 1;
                    $versionName = "1." . ($newVersionCode - 1); // 1.0, 1.1, 1.2...

                    // Détecter si le nom de fichier contient une version
                    if (preg_match('/v?(\d+\.\d+)/', $name, $matches)) {
                        $versionName = $matches[1];
                    }

                    // Insérer nouvelle version
                    $stmt = $pdo->prepare("
                        INSERT INTO app_versions (
                            version_code, version_name, apk_filename, apk_size, 
                            release_notes, is_active, app_type
                        ) VALUES (?, ?, ?, ?, ?, 1, ?)
                        ON DUPLICATE KEY UPDATE
                            apk_filename = VALUES(apk_filename),
                            apk_size = VALUES(apk_size),
                            uploaded_at = CURRENT_TIMESTAMP,
                            is_active = 1
                    ");

                    $stmt->execute([
                        $newVersionCode,
                        $versionName, 
                        $safeBase,
                        $size,
                        "Upload automatique $appType du " . date('d/m/Y à H:i'),
                        $appType
                    ]);

                    // Désactiver les anciennes versions du même type
                    $pdo->prepare("UPDATE app_versions SET is_active = 0 WHERE version_code < ? AND app_type = ?")->execute([$newVersionCode, $appType]);

                } catch (Exception $e) {
                    error_log("Erreur création version: " . $e->getMessage());
                }

                header('Location: ' . routePath('admin.php?section=applications&uploaded=1&type=' . urlencode($appType)));
                exit;
            }
        }
    }
    if ($err) {
        header('Location: ' . routePath('admin.php?section=applications&error=' . urlencode($err)));
        exit;
    }
}

require_once __DIR__ . '/functions.php';

// Gestion de la connexion
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Vérification simple (à remplacer par votre système d'auth)
    if ($username === 'admin' && $password === 'suzosky2024') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        // Redirect to admin dashboard at root
        header('Location: /admin.php?section=dashboard');
        exit;
    } else {
        renderLoginForm('Identifiants incorrects');
        exit;
    }
}

// Gestion de la déconnexion
if (($_GET['section'] ?? '') === 'logout') {
    session_destroy();
    header('Location: ' . routePath('admin.php'));
    exit;
}

// Vérification de l'authentification
if (!checkAdminAuth()) {
    renderLoginForm();
    exit;
}

// Important: Traiter les POST spécifiques aux sections qui renvoient des en-têtes/JSON
// avant tout rendu HTML pour éviter "headers already sent"
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (($_GET['section'] ?? '') === 'finances')) {
    // Le fichier finances.php gère les actions POST (redirect/JSON) et exit;
    require_once __DIR__ . '/finances.php';
    exit;
}

renderHeader();
$section = $_GET['section'] ?? 'dashboard';
switch ($section) {
    case 'agents': include __DIR__ . '/agents.php'; break;
    case 'chat': include __DIR__ . '/chat.php'; break;
    case 'clients': include __DIR__ . '/clients.php'; break;
    case 'emails': include __DIR__ . '/emails.php'; break;
    case 'recrutement': include __DIR__ . '/recrutement.php'; break;
    case 'commandes': include __DIR__ . '/commandes.php'; break;
    case 'applications': include __DIR__ . '/applications.php'; break;
    case 'app_updates': include __DIR__ . '/app_updates.php'; break;
    case 'finances': include __DIR__ . '/finances.php'; break;
    case 'finances_audit': include __DIR__ . '/finances_audit.php'; break;
    case 'notifications': include __DIR__ . '/notifications_admin.php'; break;
    case 'reseau': include __DIR__ . '/../reseau.php'; break;
    case 'dashboard': include __DIR__ . '/dashboard_suzosky_modern.php'; break;
    default: include __DIR__ . '/dashboard_suzosky_modern.php';
}
renderFooter();
