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
define('MAIN_DOC', ROOT_DIR . '/DOCUMENTATION_FINALE.md');
define('CONSOLIDATED_DOC', DOCS_DIR . '/CONSOLIDATED_DOCS_' . date('Y-m-d_H-i-s') . '.md');

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
    
    // Trier par date de modification (plus récent d'abord)
    usort($md_files, function($a, $b) {
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
    $consolidated_content = "# 📚 DOCUMENTATION CONSOLIDÉE AUTOMATIQUE\n";
    $consolidated_content .= "## 🕐 Générée automatiquement le " . date('d/m/Y à H:i:s') . "\n";
    $consolidated_content .= "## 🏠 Projet: SUZOSKY COURSIER - Version Consolidée\n\n";
    
    $consolidated_content .= "---\n\n";
    
    // Table des matières
    $consolidated_content .= "## 📋 TABLE DES MATIÈRES\n\n";
    foreach ($md_files as $index => $file) {
        $title = basename($file['relative_path'], '.md');
        $anchor = strtolower(str_replace([' ', '_', '-'], '', $title));
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
        $anchor = strtolower(str_replace([' ', '_', '-'], '', $title));
        
        log_message("📄 Traitement: " . $file['relative_path']);
        
        $consolidated_content .= "## 📖 " . ($index + 1) . ". " . strtoupper($title) . " {#" . $anchor . "}\n\n";
        $consolidated_content .= "**📍 Fichier source:** `" . $file['relative_path'] . "`  \n";
        $consolidated_content .= "**📅 Dernière modification:** " . $file['modified_date'] . "  \n";
        $consolidated_content .= "**📏 Taille:** " . number_format($file['size'] / 1024, 2) . " KB  \n\n";
        
        // Lire et inclure le contenu
        if (is_readable($file['path'])) {
            $content = file_get_contents($file['path']);
            
            // Nettoyer et adapter le contenu
            $content = preg_replace('/^# /', '### ', $content); // Réduire les niveaux de titre
            $content = preg_replace('/^## /', '#### ', $content, 1); // Premier h2 devient h4
            $content = preg_replace('/^## /', '##### ', $content); // Autres h2 deviennent h5
            
            $consolidated_content .= "```markdown\n" . $content . "\n```\n\n";
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
    
    // Écrire le fichier consolidé
    if (file_put_contents(CONSOLIDATED_DOC, $consolidated_content)) {
        log_message("✅ Documentation consolidée créée: " . basename(CONSOLIDATED_DOC));
        log_message("📏 Taille du fichier consolidé: " . number_format(strlen($consolidated_content) / 1024, 2) . " KB");
        
        // Créer aussi une copie "latest"
        $latest_doc = DOCS_DIR . '/CONSOLIDATED_DOCS_LATEST.md';
        copy(CONSOLIDATED_DOC, $latest_doc);
        log_message("📋 Copie 'latest' créée: " . basename($latest_doc));
        
        return true;
    } else {
        log_message("❌ Erreur lors de l'écriture du fichier consolidé");
        return false;
    }
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
            $latest_url = 'DOCUMENTATION_FINALE/CONSOLIDATED_DOCS_LATEST.md';
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