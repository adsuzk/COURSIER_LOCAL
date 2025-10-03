<?php
/**
 * SCRIPT DE CONSOLIDATION AUTOMATIQUE DE DOCUMENTATION
 * Collecte tous les fichiers .md, les horodate et les intègre dans DOCUMENTATION_FINALE.md
 * 
 * Usage:
 * - CLI: php consolidate_docs.php
 * - Web: /consolidate_docs.php
 * - Cron: 0 2 * * * php /path/to/consolidate_docs.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_DIR', __DIR__);
define('DOCS_DIR', ROOT_DIR . '/DOCUMENTATION_FINALE');
// Fichier cible unique exigé par le besoin: un seul document complet et à jour
define('FINAL_DOC', DOCS_DIR . '/DOCUMENTATION_SUZOSKY_COMPLETE.md');
// Plus d'archives horodatées: on maintient un seul document master à jour

// Créer le dossier DOCUMENTATION_FINALE s'il n'existe pas
if (!is_dir(DOCS_DIR)) {
    mkdir(DOCS_DIR, 0755, true);
}

function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    
    // Log aussi dans un fichier
    $log_file = DOCS_DIR . '/consolidation.log';
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

function scan_md_files($directory, $exclude_dirs = []) {
    $md_files = [];
    $exclude_dirs = array_merge($exclude_dirs, ['.git', 'node_modules', 'vendor', 'Tests']);
    // Fichiers à exclure (obsolètes / archives / backups)
    $exclude_name_patterns = [
        '/(^|\\|\/)FICHIERS_OBSOLETE/i',
        '/(^|\\|\/)FICHIERS_OBSOLETES/i',
        '/(^|\\|\/)OBSOLETE/i',
        '/(^|\\|\/)DEPRECATED/i',
        '/(^|\\|\/)ARCHIVE/i',
        '/(^|\\|\/)BACKUP/i',
        '/(^|\\|\/)OLD/i'
    ];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            // Vérifier si le fichier est dans un dossier exclu
            $relative_path = str_replace(ROOT_DIR . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $skip = false;
            
            foreach ($exclude_dirs as $exclude_dir) {
                if (strpos($relative_path, $exclude_dir . DIRECTORY_SEPARATOR) === 0) {
                    $skip = true;
                    break;
                }
            }
            // Exclure par motif de nom (obsolète)
            if (!$skip) {
                foreach ($exclude_name_patterns as $pat) {
                    if (preg_match($pat, $relative_path)) { $skip = true; break; }
                }
            }
            
            if (!$skip) {
                $md_files[] = [
                    'path' => $file->getPathname(),
                    'relative_path' => $relative_path,
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                    'modified_date' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }
    }
    
    // Trier par priorité puis par date (plus récent d'abord)
    $priorityOrder = [
        'DOCUMENTATION_FINALE/DOCUMENTATION_COMPLETE_SUZOSKY_COURSIER.md',
        'DOCUMENTATION_SYSTEME_SUZOSKY_v2.md',
        'SYSTEME_UNIFIE_COMMANDES.md',
        'SYSTEME_TRACKING_ADMIN_DOCUMENTATION.md',
        'DOCUMENTATION_TRACKING_TEMPS_REEL.md',
        'DOCUMENTATION_COMPTABILITE.md',
        'CONFIGURATION_FCM_URGENT.md',
        'GUIDE_APK_PRODUCTION.md',
        'GUIDE_MISES_A_JOUR_AUTOMATIQUES.md',
        'SCRIPTS_PROTECTION_SYNC_DOCUMENTATION.md',
        'DOCUMENTATION_FINALE/SESSIONS_ET_SECURITE.md',
        'DOCUMENTATION_FINALE/CRON_CONSOLIDATION.md',
    ];
    $priorityIndex = array_flip($priorityOrder);
    
    usort($md_files, function($a, $b) use ($priorityIndex) {
        $ar = $a['relative_path'];
        $br = $b['relative_path'];
        $ap = isset($priorityIndex[$ar]) ? $priorityIndex[$ar] : PHP_INT_MAX;
        $bp = isset($priorityIndex[$br]) ? $priorityIndex[$br] : PHP_INT_MAX;
        if ($ap !== $bp) return $ap - $bp;
        // Préférer les docs déjà dans DOCUMENTATION_FINALE
        $ain = strpos($ar, 'DOCUMENTATION_FINALE/') === 0 ? 0 : 1;
        $bin = strpos($br, 'DOCUMENTATION_FINALE/') === 0 ? 0 : 1;
        if ($ain !== $bin) return $ain - $bin;
        // Ensuite par date desc
        return $b['modified'] - $a['modified'];
    });
    
    return $md_files;
}

function consolidate_documentation() {
    log_message("🚀 Début de la consolidation automatique de documentation");
    
    // Scanner tous les fichiers .md
    $md_files = scan_md_files(ROOT_DIR);
    
    log_message("📁 " . count($md_files) . " fichiers .md trouvés");
    
    // Créer le document consolidé
    $consolidated_content = "# 📚 DOCUMENTATION SUZOSKY COMPLÈTE\n";
    $consolidated_content .= "> Générée automatiquement le " . date('d/m/Y à H:i:s') . " — Ce document unifie toute la documentation utile et supprime l'obsolète.\n\n";
    
    $consolidated_content .= "---\n\n";
    
    // Table des matières
    $consolidated_content .= "## 📋 TABLE DES MATIÈRES\n\n";
    foreach ($md_files as $index => $file) {
        $title = basename($file['relative_path'], '.md');
        $anchor = strtolower(preg_replace('/[^a-z0-9]+/i', '', $title));
        $consolidated_content .= sprintf(
            "%d. [%s](#%s) - *Modifié: %s* - `%s`\n",
            $index + 1,
            $title,
            $anchor,
            $file['modified_date'],
            $file['relative_path']
        );
    }
    
    $consolidated_content .= "\n---\n\n";
    
    // Intégrer chaque fichier
    foreach ($md_files as $index => $file) {
        $title = basename($file['relative_path'], '.md');
        $anchor = strtolower(preg_replace('/[^a-z0-9]+/i', '', $title));
        
        log_message("📄 Traitement: " . $file['relative_path']);
        
        $consolidated_content .= "## " . ($index + 1) . ". " . $title . "\n\n";
        $consolidated_content .= "_Source: `" . $file['relative_path'] . "` — Dernière modif: " . $file['modified_date'] . " — Taille: " . number_format($file['size'] / 1024, 2) . " KB_\n\n";
        
        // Lire et inclure le contenu
        if (is_readable($file['path'])) {
            $content = file_get_contents($file['path']);
            // Normaliser les titres: on évite plusieurs H1 en les abaissant d'un niveau
            $content = preg_replace_callback('/^(#{1,6})\s/m', function($m) {
                $level = strlen($m[1]);
                $newLevel = min(6, $level + 1);
                return str_repeat('#', $newLevel) . ' ';
            }, $content);
            $consolidated_content .= $content . "\n\n";
        } else {
            $consolidated_content .= "*❌ Fichier non accessible pour lecture*\n\n";
            log_message("❌ Impossible de lire: " . $file['path']);
        }
        
        $consolidated_content .= "---\n\n";
    }
    
    // Ajouter un pied de page avec statistiques
    $total_size = array_sum(array_column($md_files, 'size'));
    $consolidated_content .= "## 📊 STATISTIQUES DE CONSOLIDATION\n\n";
    $consolidated_content .= "- **📁 Fichiers traités:** " . count($md_files) . "\n";
    $consolidated_content .= "- **📏 Taille totale:** " . number_format($total_size / 1024, 2) . " KB\n";
    $consolidated_content .= "- **🕐 Généré le:** " . date('d/m/Y à H:i:s') . "\n";
    $consolidated_content .= "- **🤖 Script:** `" . basename(__FILE__) . "`\n";
    $consolidated_content .= "- **🏷️ Version:** 1.0\n\n";
    
    $consolidated_content .= "*Cette documentation est générée automatiquement. Pour des modifications, éditez les fichiers sources individuels.*\n";
    
    // Écrire le fichier consolidé unique et une archive horodatée
    $okFinal = file_put_contents(FINAL_DOC, $consolidated_content) !== false;
    $okArchive = file_put_contents(CONSOLIDATED_DOC, $consolidated_content) !== false;
    if ($okFinal) {
        log_message("✅ Documentation complète écrite: " . basename(FINAL_DOC));
        log_message("📏 Taille: " . number_format(strlen($consolidated_content) / 1024, 2) . " KB");
        // Alias "latest" conservé pour compatibilité
        $latest_doc = DOCS_DIR . '/CONSOLIDATED_DOCS_LATEST.md';
        @copy(FINAL_DOC, $latest_doc);
        log_message("📋 Alias 'latest' mis à jour: " . basename($latest_doc));
        return true;
    }
    log_message("❌ Erreur lors de l'écriture de la documentation complète");
    return false;
}

function cleanup_old_consolidated_docs($keep_count = 5) {
    $pattern = DOCS_DIR . '/CONSOLIDATED_DOCS_*.md';
    $files = glob($pattern);
    
    if (count($files) > $keep_count) {
        // Trier par date de modification
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Supprimer les anciens
        $to_delete = array_slice($files, $keep_count);
        foreach ($to_delete as $file) {
            if (strpos(basename($file), 'LATEST') === false) { // Ne pas supprimer LATEST
                unlink($file);
                log_message("🗑️ Ancien fichier supprimé: " . basename($file));
            }
        }
    }
}

// Exécution principale
try {
    log_message("🎯 Démarrage du script de consolidation");
    
    if (consolidate_documentation()) {
        cleanup_old_consolidated_docs(5);
        log_message("🎉 Consolidation terminée avec succès");
        
        if (php_sapi_name() !== 'cli') {
            // Mode web - rediriger vers le fichier créé
            $latest_url = 'DOCUMENTATION_FINALE/' . basename(FINAL_DOC);
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Documentation Consolidée</title></head><body>";
            echo "<h2>✅ Documentation consolidée avec succès!</h2>";
            echo "<p><a href='$latest_url' target='_blank'>📖 Voir la documentation consolidée</a></p>";
            echo "<p><a href='javascript:history.back()'>🔙 Retour</a></p>";
            echo "</body></html>";
        }
        
        exit(0);
    } else {
        log_message("❌ Échec de la consolidation");
        exit(1);
    }
} catch (Exception $e) {
    log_message("💥 Erreur fatale: " . $e->getMessage());
    exit(1);
}