<?php
// Shared helpers for app update config and latest APK overlay
// Ensures admin UI and API use the same source of truth.

function vu_get_paths() {
    // Root is the project root (one level up from lib)
    $root = dirname(__DIR__);
    return [
        'root' => $root,
        'versions_config' => $root . '/data/app_versions.json',
        'upload_meta' => $root . '/admin/uploads/latest_apk.json',
        'uploads_dir' => $root . '/admin/uploads',
    ];
}

function vu_load_versions_config() {
    $p = vu_get_paths();
    $vc = $p['versions_config'];
    if (!file_exists($vc)) {
        if (!is_dir(dirname($vc))) {
            @mkdir(dirname($vc), 0755, true);
        }
        $config = [
            'current_version' => [
                'version_code' => 1,
                'version_name' => '1.0.0',
                'apk_url' => '',
                'apk_size' => 0,
                'release_date' => date('Y-m-d H:i:s'),
                'force_update' => false,
                'min_supported_version' => 1,
                'changelog' => []
            ],
            'update_check_interval' => 3600,
            'auto_install' => true,
            'devices' => []
        ];
        @file_put_contents($vc, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $config;
    }
    $config = json_decode(file_get_contents($vc), true) ?: [];
    // Defaults safety
    if (!isset($config['update_check_interval'])) $config['update_check_interval'] = 3600;
    if (!isset($config['auto_install'])) $config['auto_install'] = true;
    if (!isset($config['devices']) || !is_array($config['devices'])) $config['devices'] = [];
    if (!isset($config['current_version']) || !is_array($config['current_version'])) $config['current_version'] = [];
    return $config;
}

function vu_persist_versions_config($config) {
    $p = vu_get_paths();
    @file_put_contents($p['versions_config'], json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// $appType is optional for forward-compatibility (coursier|client). Currently, a single
// latest_apk.json pointer is used for the "coursier" app; we keep the signature flexible.
function vu_overlay_with_latest_upload(&$config, $persist = false, $appType = 'coursier') {
    $p = vu_get_paths();
    $metaFile = $p['upload_meta'];
    if (!file_exists($metaFile)) return false;
    $latest = json_decode(file_get_contents($metaFile), true);
    if (!is_array($latest) || empty($latest['file'])) return false;

    if (!isset($config['current_version']) || !is_array($config['current_version'])) {
        $config['current_version'] = [];
    }
    $cv = &$config['current_version'];
    $cv['version_code'] = (int)($latest['version_code'] ?? ($cv['version_code'] ?? 1));
    $cv['version_name'] = $latest['version_name'] ?? ($cv['version_name'] ?? '1.0.0');
    $cv['apk_url'] = '/admin/download_apk.php?file=' . rawurlencode($latest['file']);
    $cv['apk_size'] = (int)($latest['apk_size'] ?? ($latest['size'] ?? ($cv['apk_size'] ?? 0)));
    $cv['release_date'] = $latest['uploaded_at'] ?? date('Y-m-d H:i:s');
    // Overlay policy: if latest meta provides these keys, prefer them; else keep existing or default
    if (array_key_exists('force_update', $latest)) {
        $cv['force_update'] = (bool)$latest['force_update'];
    } else if (!isset($cv['force_update'])) {
        $cv['force_update'] = false;
    }
    if (array_key_exists('min_supported_version', $latest)) {
        $cv['min_supported_version'] = (int)$latest['min_supported_version'];
    } else if (!isset($cv['min_supported_version'])) {
        $cv['min_supported_version'] = 1;
    }
    if (isset($latest['changelog']) && is_array($latest['changelog'])) {
        $cv['changelog'] = $latest['changelog'];
    } else if (!isset($cv['changelog']) || !is_array($cv['changelog'])) {
        $cv['changelog'] = [];
    }

    if ($persist) {
        vu_persist_versions_config($config);
    }
    return true;
}

// Update latest_apk.json while preserving previous. Accept optional $appType and
// try locating the uploaded file in uploads/ as well as uploads/<appType>/.
function vu_update_latest_meta_with_previous($filename, $metaExtras = [], $appType = 'coursier') {
    $p = vu_get_paths();
    $uploadsDir = $p['uploads_dir'];
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0775, true);
    }

    $metaPath = $p['upload_meta'];
    $prev = null;
    if (file_exists($metaPath)) {
        $prevData = json_decode(@file_get_contents($metaPath), true);
        if (is_array($prevData)) {
            $prev = [
                'file' => $prevData['file'] ?? null,
                'version_code' => $prevData['version_code'] ?? null,
                'version_name' => $prevData['version_name'] ?? null,
                'apk_size' => $prevData['apk_size'] ?? ($prevData['size'] ?? null),
                'uploaded_at' => $prevData['uploaded_at'] ?? null,
            ];
        }
    }

    // Try multiple candidate locations for the physical file
    $candidates = [
        $uploadsDir . '/' . $filename,
        $uploadsDir . '/coursier/' . $filename,
        $uploadsDir . '/client/' . $filename,
    ];
    $fullPath = null;
    foreach ($candidates as $cand) {
        if (is_file($cand)) { $fullPath = $cand; break; }
    }
    $apkSize = $fullPath ? filesize($fullPath) : (int)($metaExtras['apk_size'] ?? 0);

    $latest = array_merge([
        'file' => $filename,
        'uploaded_at' => date('c'),
        'apk_size' => $apkSize,
    ], $metaExtras);

    if ($prev) {
        $latest['previous'] = $prev;
    }

    // Si aucune version n'est fournie, incrémenter automatiquement depuis la précédente/config
    if (!isset($latest['version_code'])) {
        $autoCode = null;
        if (!empty($prev['version_code']) && is_numeric($prev['version_code'])) {
            $autoCode = (int)$prev['version_code'] + 1;
        } else {
            // fallback: lire la config actuelle
            $cfg = vu_load_versions_config();
            $autoCode = (int)($cfg['current_version']['version_code'] ?? 0) + 1;
        }
        $latest['version_code'] = max(1, (int)$autoCode);
    }
    if (!isset($latest['version_name'])) {
        // Dériver un nom simple si absent, ex: 1.<code-1> ou 1.<code>
        $code = (int)$latest['version_code'];
        $latest['version_name'] = '1.' . max(0, $code - 1);
    }

    @file_put_contents($metaPath, json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $latest;
}
