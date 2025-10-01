<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Diagnostic Google Maps - Suzosky</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            color: #d4a853;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        
        .diagnostic-section {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(212,168,83,0.3);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        
        .diagnostic-section h2 {
            color: #d4a853;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin: 8px 0;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            border-left: 4px solid #666;
        }
        
        .status-item.success {
            border-left-color: #00ff00;
        }
        
        .status-item.error {
            border-left-color: #ff0000;
        }
        
        .status-item.warning {
            border-left-color: #ffaa00;
        }
        
        .icon {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }
        
        .code-block {
            background: #000;
            color: #0f0;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #d4a853;
            color: #1a1a2e;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            margin: 5px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212,168,83,0.4);
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        
        #console-log {
            background: #000;
            color: #0f0;
            padding: 15px;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 15px 0;
        }
        
        .log-entry {
            margin: 5px 0;
            padding: 5px;
            border-bottom: 1px solid rgba(0,255,0,0.2);
        }
        
        .log-error {
            color: #ff0000;
        }
        
        .log-warning {
            color: #ffaa00;
        }
        
        .log-success {
            color: #00ff00;
        }
        
        .log-info {
            color: #00aaff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic Complet Google Maps</h1>
        
        <?php
        require_once __DIR__ . '/config.php';
        
        // Récupération des clés API
        $configKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : 'NON DÉFINIE';
        $envKey = getenv('GOOGLE_MAPS_API_KEY') ?: 'NON DÉFINIE';
        
        // Clé utilisée dans index.php (fallback)
        $indexFallbackKey = 'AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A';
        
        // Clé finale utilisée
        $finalKey = $envKey !== 'NON DÉFINIE' ? $envKey : $configKey;
        if ($finalKey === 'NON DÉFINIE') {
            $finalKey = $indexFallbackKey;
        }
        
        $keysMatch = ($configKey === $finalKey);
        ?>
        
        <!-- Section 1: Configuration des clés API -->
        <div class="diagnostic-section">
            <h2>🔑 Configuration des Clés API</h2>
            
            <div class="status-item <?php echo $keysMatch ? 'success' : 'warning'; ?>">
                <span class="icon"><?php echo $keysMatch ? '✅' : '⚠️'; ?></span>
                <div>
                    <strong>Clé dans config.php:</strong><br>
                    <code><?php echo substr($configKey, 0, 20); ?>...</code>
                </div>
            </div>
            
            <div class="status-item <?php echo $envKey !== 'NON DÉFINIE' ? 'success' : 'warning'; ?>">
                <span class="icon"><?php echo $envKey !== 'NON DÉFINIE' ? '✅' : '⚠️'; ?></span>
                <div>
                    <strong>Variable d'environnement GOOGLE_MAPS_API_KEY:</strong><br>
                    <code><?php echo $envKey === 'NON DÉFINIE' ? 'Non définie' : substr($envKey, 0, 20) . '...'; ?></code>
                </div>
            </div>
            
            <div class="status-item warning">
                <span class="icon">⚠️</span>
                <div>
                    <strong>Clé fallback dans index.php:</strong><br>
                    <code><?php echo substr($indexFallbackKey, 0, 20); ?>...</code>
                    <br><small style="color: #ffaa00;">Cette clé est différente de config.php!</small>
                </div>
            </div>
            
            <div class="status-item <?php echo $keysMatch ? 'success' : 'error'; ?>">
                <span class="icon"><?php echo $keysMatch ? '✅' : '❌'; ?></span>
                <div>
                    <strong>Statut de cohérence:</strong>
                    <?php if ($keysMatch): ?>
                        Les clés correspondent
                    <?php else: ?>
                        <span style="color: #ff0000;">INCOHÉRENCE DÉTECTÉE!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Test de l'API -->
        <div class="diagnostic-section">
            <h2>🌐 Test de l'API Google Maps</h2>
            
            <div class="status-item">
                <span class="icon">🔄</span>
                <div>
                    <strong>Clé utilisée pour le test:</strong><br>
                    <code><?php echo substr($finalKey, 0, 35); ?>...</code>
                </div>
            </div>
            
            <div id="api-test-results">
                <div class="status-item">
                    <span class="icon">⏳</span>
                    <div>Test de l'API en cours...</div>
                </div>
            </div>
        </div>
        
        <!-- Section 3: Vérification des fichiers -->
        <div class="diagnostic-section">
            <h2>📁 Vérification des Fichiers</h2>
            
            <?php
            $files = [
                'config.php' => 'Configuration principale',
                'index.php' => 'Page d\'accueil',
                'sections_index/js_google_maps.php' => 'Script Google Maps',
                'sections_index/order_form.php' => 'Formulaire de commande'
            ];
            
            foreach ($files as $file => $description):
                $exists = file_exists(__DIR__ . '/' . $file);
                $size = $exists ? filesize(__DIR__ . '/' . $file) : 0;
            ?>
                <div class="status-item <?php echo $exists ? 'success' : 'error'; ?>">
                    <span class="icon"><?php echo $exists ? '✅' : '❌'; ?></span>
                    <div>
                        <strong><?php echo $description; ?> (<?php echo $file; ?>)</strong><br>
                        <?php if ($exists): ?>
                            Taille: <?php echo number_format($size); ?> octets
                        <?php else: ?>
                            <span style="color: #ff0000;">FICHIER MANQUANT!</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Section 4: Console JavaScript -->
        <div class="diagnostic-section">
            <h2>📋 Console JavaScript</h2>
            <div id="console-log">
                <div class="log-entry log-info">🔍 En attente des résultats du test...</div>
            </div>
        </div>
        
        <!-- Section 5: Solutions -->
        <div class="diagnostic-section">
            <h2>💡 Solutions Recommandées</h2>
            
            <?php if (!$keysMatch): ?>
            <div class="status-item warning">
                <span class="icon">⚠️</span>
                <div>
                    <strong>PROBLÈME: Incohérence des clés API</strong><br>
                    La clé dans index.php est différente de celle dans config.php.<br>
                    <strong>Solution:</strong> Uniformiser les clés API dans tous les fichiers.
                </div>
            </div>
            <?php endif; ?>
            
            <div class="code-block">
// Pour corriger dans index.php, remplacez:
$mapsApiKey = 'AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A';

// Par:
$mapsApiKey = getenv('GOOGLE_MAPS_API_KEY');
if (!$mapsApiKey && defined('GOOGLE_MAPS_API_KEY')) {
    $mapsApiKey = GOOGLE_MAPS_API_KEY;
}
            </div>
            
            <div class="status-item success">
                <span class="icon">✅</span>
                <div>
                    <strong>VÉRIFICATION:</strong> Assurez-vous que la clé API a les bibliothèques activées:<br>
                    <code>libraries=places,geometry</code>
                </div>
            </div>
            
            <div class="status-item success">
                <span class="icon">✅</span>
                <div>
                    <strong>VÉRIFICATION:</strong> Assurez-vous que les domaines autorisés incluent:<br>
                    <code>localhost, 127.0.0.1, votre-domaine.com</code>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="action-buttons">
            <a href="test_google_maps.php" class="btn">🧪 Test Simple</a>
            <a href="index.php" class="btn">🏠 Retour Index</a>
            <button onclick="location.reload()" class="btn">🔄 Rafraîchir</button>
        </div>
    </div>
    
    <script>
        // Configuration
        const API_KEY = '<?php echo $finalKey; ?>';
        const consoleLog = document.getElementById('console-log');
        const apiTestResults = document.getElementById('api-test-results');
        
        // Fonction pour logger
        function log(message, type = 'info') {
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            consoleLog.appendChild(entry);
            consoleLog.scrollTop = consoleLog.scrollHeight;
            console.log(message);
        }
        
        // Interception des logs console
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;
        
        console.log = function(...args) {
            log(args.join(' '), 'info');
            originalLog.apply(console, args);
        };
        
        console.error = function(...args) {
            log('ERROR: ' + args.join(' '), 'error');
            originalError.apply(console, args);
        };
        
        console.warn = function(...args) {
            log('WARNING: ' + args.join(' '), 'warning');
            originalWarn.apply(console, args);
        };
        
        // Test de l'API Google Maps
        log('🔍 Début du diagnostic...', 'info');
        log('📍 Clé API: ' + API_KEY.substring(0, 20) + '...', 'info');
        
        // Callback pour l'API
        window.testGoogleMaps = function() {
            log('✅ Callback Google Maps appelé!', 'success');
            
            if (typeof google === 'undefined') {
                log('❌ Objet google non défini', 'error');
                updateTestResults(false, 'Objet google non défini');
                return;
            }
            
            if (!google.maps) {
                log('❌ google.maps non disponible', 'error');
                updateTestResults(false, 'google.maps non disponible');
                return;
            }
            
            log('✅ API Google Maps chargée avec succès!', 'success');
            log('✅ google.maps.version: ' + google.maps.version, 'success');
            
            // Test des bibliothèques
            if (google.maps.places) {
                log('✅ Bibliothèque Places chargée', 'success');
            } else {
                log('⚠️ Bibliothèque Places non chargée', 'warning');
            }
            
            if (google.maps.geometry) {
                log('✅ Bibliothèque Geometry chargée', 'success');
            } else {
                log('⚠️ Bibliothèque Geometry non chargée', 'warning');
            }
            
            updateTestResults(true, 'API opérationnelle');
        };
        
        // Gestion des erreurs d'authentification
        window.gm_authFailure = function() {
            log('❌ ERREUR D\'AUTHENTIFICATION API!', 'error');
            log('La clé API est invalide ou les restrictions empêchent son utilisation', 'error');
            updateTestResults(false, 'Erreur d\'authentification - Clé invalide ou restrictions');
        };
        
        // Mise à jour des résultats
        function updateTestResults(success, message) {
            apiTestResults.innerHTML = `
                <div class="status-item ${success ? 'success' : 'error'}">
                    <span class="icon">${success ? '✅' : '❌'}</span>
                    <div>
                        <strong>Résultat du test:</strong><br>
                        ${message}
                    </div>
                </div>
            `;
        }
        
        // Timeout
        setTimeout(function() {
            if (typeof google === 'undefined') {
                log('❌ TIMEOUT: API non chargée après 10 secondes', 'error');
                updateTestResults(false, 'Timeout - API non chargée en 10 secondes');
            }
        }, 10000);
        
        log('⏳ Chargement de l\'API...', 'info');
    </script>
    
    <!-- Chargement de l'API Google Maps -->
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($finalKey, ENT_QUOTES); ?>&libraries=places,geometry&callback=testGoogleMaps"
        onerror="log('❌ Échec du chargement du script API', 'error'); updateTestResults(false, 'Échec du chargement du script');">
    </script>
</body>
</html>
