<?php
// diagnostic_auth.php - Diagnostic complet du système d'authentification
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Système d'Authentification</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section { 
            background: white;
            margin: 20px 0; 
            padding: 20px; 
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        pre { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 4px; 
            overflow-x: auto;
        }
        h2 { 
            border-bottom: 2px solid #007bff; 
            padding-bottom: 10px;
            color: #007bff;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic Système d'Authentification Suzosky</h1>
    
    <div class="test-section">
        <h2>1. Configuration PHP</h2>
        <?php
        echo "<strong>Version PHP:</strong> " . PHP_VERSION;
        echo "<span class='status-badge badge-" . (version_compare(PHP_VERSION, '7.4', '>=') ? 'success' : 'warning') . "'>";
        echo version_compare(PHP_VERSION, '7.4', '>=') ? 'OK' : 'Ancienne version';
        echo "</span><br>";
        
        $extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
        foreach ($extensions as $ext) {
            echo "<strong>Extension $ext:</strong> ";
            echo extension_loaded($ext) ? "✅ Chargée" : "❌ Manquante";
            echo "<br>";
        }
        ?>
    </div>

    <div class="test-section">
        <h2>2. Configuration Base de Données</h2>
        <?php
        try {
            require_once 'config.php';
            $pdo = getDBConnection();
            echo "<span class='success'>✅ Connexion à la base de données réussie</span><br>";
            
            // Informations sur la base de données
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            echo "<strong>Version MySQL:</strong> $version<br>";
            
            $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
            echo "<strong>Base de données active:</strong> $dbName<br>";
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ Erreur de connexion: " . $e->getMessage() . "</span>";
        }
        ?>
    </div>

    <div class="test-section">
        <h2>3. Structure des Tables</h2>
        <?php
        try {
            // Vérifier la table clients_particuliers
            $tables = $pdo->query("SHOW TABLES LIKE 'clients_particuliers'")->fetchAll();
            
            if (!empty($tables)) {
                echo "<span class='success'>✅ Table clients_particuliers existe</span><br>";
                
                // Afficher la structure
                $columns = $pdo->query("DESCRIBE clients_particuliers")->fetchAll(PDO::FETCH_ASSOC);
                echo "<h4>Structure de la table:</h4>";
                echo "<pre>";
                foreach ($columns as $column) {
                    echo sprintf("%-15s %-20s %s\n", 
                        $column['Field'], 
                        $column['Type'], 
                        $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL'
                    );
                }
                echo "</pre>";
                
                // Compter les enregistrements
                $count = $pdo->query("SELECT COUNT(*) FROM clients_particuliers")->fetchColumn();
                echo "<strong>Nombre d'enregistrements:</strong> $count<br>";
                
            } else {
                echo "<span class='warning'>⚠️ Table clients_particuliers n'existe pas</span><br>";
                echo "<span class='info'>💡 Exécutez test_auth.php pour créer la table</span>";
            }
            
        } catch (Exception $e) {
            echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span>";
        }
        ?>
    </div>

    <div class="test-section">
        <h2>4. Test des Fichiers Critiques</h2>
        <?php
        $files = [
            'config.php' => 'Configuration base de données',
            'api/auth.php' => 'API d\'authentification',
            'assets/js/connexion_modal.js' => 'Script modal AJAX',
            'sections index/connexion.php' => 'Formulaire de connexion',
            'sections index/inscription.php' => 'Formulaire d\'inscription'
        ];
        
        foreach ($files as $file => $description) {
            $fullPath = __DIR__ . '/' . $file;
            if (file_exists($fullPath)) {
                $size = filesize($fullPath);
                echo "<span class='success'>✅ $file</span> ($description) - {$size} bytes<br>";
            } else {
                echo "<span class='error'>❌ $file</span> ($description) - Fichier manquant<br>";
            }
        }
        ?>
    </div>

    <div class="test-section">
        <h2>5. Test API d'Authentification</h2>
        <div id="api-test-results">
            <button onclick="testAPI()">🧪 Tester l'API</button>
            <div id="test-output"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>6. Test Modal de Connexion</h2>
        <button onclick="testModal()">🎭 Tester le Modal</button>
        <div id="modal-test-output"></div>
        
        <!-- Modal de test -->
        <div id="connexionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%;">
                <div id="modal-content">
                    <p>Chargement du formulaire...</p>
                </div>
                <button onclick="closeModal()" style="margin-top: 10px;">Fermer</button>
            </div>
        </div>
    </div>

    <script>
    function testAPI() {
        const output = document.getElementById('test-output');
        output.innerHTML = '<p>🔄 Test en cours...</p>';
        
        // Test de connexion avec l'utilisateur de test
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('email', 'test@suzosky.com');
        formData.append('password', 'test123');
        
        fetch('api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            output.innerHTML = `
                <h4>Résultat du test de connexion:</h4>
                <pre>${JSON.stringify(data, null, 2)}</pre>
                <p class="${data.success ? 'success' : 'error'}">
                    ${data.success ? '✅ Connexion réussie' : '❌ Échec de connexion'}
                </p>
            `;
        })
        .catch(error => {
            output.innerHTML = `<p class="error">❌ Erreur: ${error.message}</p>`;
        });
    }

    function testModal() {
        const output = document.getElementById('modal-test-output');
        output.innerHTML = '<p>🔄 Test du modal...</p>';
        
        fetch('sections index/connexion.php')
        .then(response => response.text())
        .then(html => {
            document.getElementById('modal-content').innerHTML = html;
            document.getElementById('connexionModal').style.display = 'block';
            output.innerHTML = '<p class="success">✅ Modal chargé avec succès</p>';
        })
        .catch(error => {
            output.innerHTML = `<p class="error">❌ Erreur: ${error.message}</p>`;
        });
    }

    function closeModal() {
        document.getElementById('connexionModal').style.display = 'none';
    }
    </script>

    <div class="test-section">
        <h2>7. Actions Recommandées</h2>
        <ul>
            <li>✅ <strong>Étape 1:</strong> Exécuter <code>test_auth.php</code> pour créer les tables</li>
            <li>🔧 <strong>Étape 2:</strong> Tester l'API avec le bouton ci-dessus</li>
            <li>🎭 <strong>Étape 3:</strong> Tester le modal avec le bouton ci-dessus</li>
            <li>🌐 <strong>Étape 4:</strong> Tester sur la page principale</li>
        </ul>
    </div>
</body>
</html>
