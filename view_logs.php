<?php
/**
 * VIEW_LOGS.PHP - CENTRE DE CONTRÔLE UNIQUE POUR TOUS LES LOGS - VERSION SIMPLIFIÉE
 * Affiche tous les logs du système Suzosky Coursier en un seul endroit
 * 
 * @version 2.1.3
 * @author Suzosky Development Team
 * @date 2025-09-05
 */

// Activer l'affichage des erreurs pour le debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration des dossiers de logs
$logDirectories = [
    'diagnostic_logs' => __DIR__ . '/diagnostic_logs',
    'cinetpay_logs' => __DIR__ . '/cinetpay/logs',
    'journal_sessions' => __DIR__ . '/JOURNAL/sessions_detaillees',
    'journal_lisible' => __DIR__ . '/JOURNAL'
];

// Fonction pour scanner récursivement tous les fichiers de logs
function getAllLogFiles($directories) {
    $allFiles = [];
    
    foreach ($directories as $category => $dir) {
        if (is_dir($dir)) {
            $files = @scandir($dir);
            if ($files === false) continue;
            
            foreach ($files as $file) {
                if (in_array($file, ['.', '..'])) continue;
                
                $fullPath = $dir . '/' . $file;
                if (is_file($fullPath)) {
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    if (in_array($extension, ['log', 'txt', 'html', 'json'])) {
                        $allFiles[] = [
                            'category' => $category,
                            'name' => $file,
                            'path' => $fullPath,
                            'size' => @filesize($fullPath) ?: 0,
                            'modified' => @filemtime($fullPath) ?: 0,
                            'type' => $extension
                        ];
                    }
                }
            }
        }
    }
    
    // Trier par date de modification (plus récent en premier)
    usort($allFiles, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return $allFiles;
}

// Fonction pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Fonction pour détecter les erreurs dans les logs
function detectErrors($content) {
    $errorKeywords = ['ERROR', 'FATAL', 'EXCEPTION', 'FAILED', 'ERREUR', 'ÉCHEC', 'CRITICAL', 'DEPLOYMENT ERROR', 'UNCAUGHT', 'Call to undefined'];
    $errors = [];
    $lines = explode("\n", $content);
    
    foreach ($lines as $lineNum => $line) {
        foreach ($errorKeywords as $keyword) {
            if (stripos($line, $keyword) !== false) {
                $errors[] = [
                    'line' => $lineNum + 1,
                    'content' => trim($line),
                    'keyword' => $keyword
                ];
            }
        }
    }
    
    return $errors;
}

try {
    // Récupération de tous les fichiers de logs
    $allLogFiles = getAllLogFiles($logDirectories);

    // Paramètres de la requête
    $selected = $_GET['file'] ?? null;
    $showAll = isset($_GET['all']);
    $showErrors = isset($_GET['errors']);

    // Validation du fichier sélectionné
    $selectedFile = null;
    if ($selected) {
        foreach ($allLogFiles as $file) {
            if ($file['name'] === basename($selected) && file_exists($file['path'])) {
                $selectedFile = $file;
                break;
            }
        }
    }

} catch (Exception $e) {
    die("Erreur lors de l'initialisation: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Centre de Contrôle Logs - Suzosky Coursier</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        .header {
            background: rgba(212, 168, 83, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(212, 168, 83, 0.3);
        }
        .header h1 {
            color: #D4A853;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .btn {
            background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            color: #1A1A2E;
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-block;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 168, 83, 0.4);
        }
        .btn.danger {
            background: linear-gradient(135deg, #E94560 0%, #FF6B6B 100%);
            color: white;
        }
        .container {
            display: flex;
            gap: 20px;
            min-height: 70vh;
        }
        .sidebar {
            width: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-height: 80vh;
            overflow-y: auto;
        }
        .sidebar h3 {
            color: #D4A853;
            margin-bottom: 15px;
            border-bottom: 2px solid #D4A853;
            padding-bottom: 5px;
        }
        .file-item {
            background: rgba(255, 255, 255, 0.03);
            margin: 8px 0;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #D4A853;
            transition: all 0.3s ease;
        }
        .file-item:hover {
            background: rgba(212, 168, 83, 0.1);
            transform: translateX(5px);
        }
        .file-item.selected {
            background: rgba(212, 168, 83, 0.2);
            border-left-color: #F4E4B8;
        }
        .file-item a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
        }
        .file-meta {
            font-size: 0.8em;
            color: #aaa;
            margin-top: 5px;
        }
        .content {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .log-content {
            background: #000;
            color: #00ff00;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            max-height: 70vh;
            overflow: auto;
            white-space: pre-wrap;
            border: 1px solid #333;
        }
        .error-highlight {
            background: rgba(233, 69, 96, 0.3);
            color: #FFB3B3;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #D4A853;
        }
        .category-badge {
            background: rgba(212, 168, 83, 0.2);
            color: #D4A853;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: bold;
        }
        .search-box {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            margin-bottom: 15px;
        }
        .search-box::placeholder {
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Centre de Contrôle Logs</h1>
        <p>Supervision complète du système Suzosky Coursier - Tous les logs en temps réel</p>
    </div>

    <div class="controls">
        <a href="?" class="btn">🏠 Accueil</a>
        <a href="?all=1" class="btn">📋 Tous les logs</a>
        <a href="?errors=1" class="btn danger">⚠️ Erreurs uniquement</a>
        <a href="javascript:location.reload()" class="btn">🔄 Actualiser</a>
        <button onclick="copyLogContent()" class="btn" style="background: linear-gradient(135deg, #4CAF50 0%, #81C784 100%);">📋 Copier les résultats</button>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($allLogFiles); ?></div>
            <div>Fichiers de logs</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($logDirectories); ?></div>
            <div>Catégories</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php 
                $totalSize = array_sum(array_column($allLogFiles, 'size'));
                echo formatFileSize($totalSize);
                ?>
            </div>
            <div>Taille totale</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php echo count($allLogFiles) > 0 ? date('H:i', max(array_column($allLogFiles, 'modified'))) : 'N/A'; ?>
            </div>
            <div>Dernière activité</div>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3>📁 Fichiers de logs</h3>
            
            <input type="text" class="search-box" placeholder="Rechercher un fichier..." id="searchBox">
            
            <?php 
            $categories = [];
            foreach ($allLogFiles as $file) {
                $categories[$file['category']][] = $file;
            }
            
            foreach ($categories as $cat => $files): ?>
                <h4 style="color: #D4A853; margin: 15px 0 10px 0; text-transform: capitalize;">
                    📂 <?php echo str_replace('_', ' ', $cat); ?> (<?php echo count($files); ?>)
                </h4>
                
                <?php foreach ($files as $file): ?>
                    <div class="file-item <?php echo $selectedFile && $selectedFile['name'] === $file['name'] ? 'selected' : ''; ?>" data-name="<?php echo strtolower($file['name']); ?>">
                        <a href="?file=<?php echo urlencode($file['name']); ?>">
                            📄 <?php echo htmlspecialchars($file['name']); ?>
                        </a>
                        <div class="file-meta">
                            <span class="category-badge"><?php echo $file['type']; ?></span>
                            <?php echo formatFileSize($file['size']); ?> • 
                            <?php echo date('d/m/Y H:i', $file['modified']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <div class="content">
            <?php if ($showAll): ?>
                <h2>📋 Tous les logs combinés</h2>
                <div class="log-content">
                    <?php
                    foreach ($allLogFiles as $file) {
                        if (is_readable($file['path'])) {
                            echo "=== " . strtoupper($file['name']) . " (Catégorie: " . $file['category'] . ") ===\n";
                            echo "📅 Modifié: " . date('Y-m-d H:i:s', $file['modified']) . "\n";
                            echo "📊 Taille: " . formatFileSize($file['size']) . "\n";
                            echo str_repeat("=", 80) . "\n";
                            
                            $content = file_get_contents($file['path']);
                            echo htmlspecialchars($content) . "\n\n";
                            echo str_repeat("-", 80) . "\n\n";
                        }
                    }
                    ?>
                </div>
                
            <?php elseif ($showErrors): ?>
                <h2>⚠️ Erreurs détectées dans tous les logs</h2>
                <div class="log-content">
                    <?php
                    $totalErrors = 0;
                    foreach ($allLogFiles as $file) {
                        if (is_readable($file['path'])) {
                            $content = file_get_contents($file['path']);
                            $errors = detectErrors($content);
                            
                            if (!empty($errors)) {
                                echo "=== ERREURS DANS " . strtoupper($file['name']) . " ===\n";
                                echo "📁 Catégorie: " . $file['category'] . "\n";
                                echo "🔢 Nombre d'erreurs: " . count($errors) . "\n\n";
                                
                                foreach ($errors as $error) {
                                    echo "🚨 Ligne " . $error['line'] . ": " . htmlspecialchars($error['content']) . "\n";
                                }
                                echo "\n" . str_repeat("-", 80) . "\n\n";
                                $totalErrors += count($errors);
                            }
                        }
                    }
                    
                    if ($totalErrors === 0) {
                        echo "✅ Aucune erreur détectée dans les logs !";
                    } else {
                        echo "\n🔥 TOTAL: " . $totalErrors . " erreurs détectées dans le système.";
                    }
                    ?>
                </div>
                
            <?php elseif ($selectedFile): ?>
                <h2>📄 <?php echo htmlspecialchars($selectedFile['name']); ?></h2>
                <p>
                    <strong>Catégorie:</strong> <span class="category-badge"><?php echo $selectedFile['category']; ?></span> • 
                    <strong>Taille:</strong> <?php echo formatFileSize($selectedFile['size']); ?> • 
                    <strong>Modifié:</strong> <?php echo date('d/m/Y H:i:s', $selectedFile['modified']); ?>
                </p>
                
                <?php
                $content = file_get_contents($selectedFile['path']);
                $errors = detectErrors($content);
                
                if (!empty($errors)) {
                    echo '<p style="color: #E94560; font-weight: bold;">⚠️ ' . count($errors) . ' erreur(s) détectée(s) dans ce fichier</p>';
                }
                ?>
                
                <div class="log-content">
                    <?php
                    if ($selectedFile['type'] === 'html') {
                        echo $content;
                    } else {
                        // Highlighting des erreurs
                        $lines = explode("\n", $content);
                        foreach ($lines as $lineNum => $line) {
                            $highlighted = htmlspecialchars($line);
                            
                            // Highlight des erreurs
                            foreach (['ERROR', 'FATAL', 'EXCEPTION', 'FAILED', 'ERREUR', 'ÉCHEC', 'CRITICAL', 'DEPLOYMENT ERROR', 'UNCAUGHT', 'Call to undefined'] as $keyword) {
                                if (stripos($line, $keyword) !== false) {
                                    $highlighted = '<span class="error-highlight">' . $highlighted . '</span>';
                                    break;
                                }
                            }
                            
                            echo $highlighted . "\n";
                        }
                    }
                    ?>
                </div>
                
            <?php else: ?>
                <h2>🎯 Tableau de bord des logs</h2>
                <p>Sélectionnez un fichier de log dans la barre latérale pour l'afficher, ou utilisez les boutons ci-dessus pour voir tous les logs ou uniquement les erreurs.</p>
                
                <h3>📊 Résumé par catégorie</h3>
                <div style="margin-top: 20px;">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat => $files): ?>
                            <div style="background: rgba(255,255,255,0.05); padding: 15px; margin: 10px 0; border-radius: 8px;">
                                <h4 style="color: #D4A853;"><?php echo ucfirst(str_replace('_', ' ', $cat)); ?></h4>
                                <p><?php echo count($files); ?> fichier(s) • 
                                <?php echo formatFileSize(array_sum(array_column($files, 'size'))); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Aucun fichier de log trouvé.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Fonction pour copier le contenu des logs
        function copyLogContent() {
            const logContent = document.querySelector('.log-content');
            if (logContent) {
                const textContent = logContent.innerText || logContent.textContent;
                navigator.clipboard.writeText(textContent).then(function() {
                    // Feedback visuel
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '✅ Copié !';
                    btn.style.background = 'linear-gradient(135deg, #4CAF50 0%, #81C784 100%)';
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.background = 'linear-gradient(135deg, #4CAF50 0%, #81C784 100%)';
                    }, 2000);
                }).catch(function(err) {
                    alert('Erreur lors de la copie: ' + err);
                });
            } else {
                alert('Aucun contenu de log à copier');
            }
        }
        
        // Recherche en temps réel
        document.getElementById('searchBox').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const fileItems = document.querySelectorAll('.file-item');
            
            fileItems.forEach(item => {
                const fileName = item.getAttribute('data-name');
                if (fileName.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Auto-refresh toutes les 30 secondes
        setInterval(() => {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
