<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic Final - Payment Endpoint</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .section { border: 1px solid #ddd; margin: 10px 0; padding: 15px; }
        .success { background: #e8f5e9; border-color: #4caf50; }
        .error { background: #ffebee; border-color: #f44336; }
        .warning { background: #fff3e0; border-color: #ff9800; }
        .info { background: #e3f2fd; border-color: #2196f3; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic Final - Payment Endpoint</h1>

    <?php
    $issues = [];
    $successes = [];

    // Test 1: Fichiers requis
    echo '<div class="section info"><h2>1. Vérification des fichiers</h2>';
    
    $files = [
        'API Endpoint' => __DIR__ . '/api/initiate_order_payment.php',
        'CinetPay Integration' => __DIR__ . '/cinetpay/cinetpay_integration.php',
        'Logger' => __DIR__ . '/logger.php',
        'Config' => __DIR__ . '/config.php'
    ];
    
    foreach ($files as $name => $file) {
        if (file_exists($file)) {
            echo "✅ $name: OK<br>";
            $successes[] = "$name disponible";
        } else {
            echo "❌ $name: MANQUANT ($file)<br>";
            $issues[] = "$name manquant";
        }
    }
    echo '</div>';

    // Test 2: Configuration ROOT_PATH
    echo '<div class="section info"><h2>2. Configuration ROOT_PATH</h2>';
    $rootPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    echo "ROOT_PATH: <strong>$rootPath</strong><br>";
    echo "URL API calculée: <strong>http://localhost:8000{$rootPath}/api/initiate_order_payment.php</strong><br>";
    echo "URL actuelle: <strong>" . $_SERVER['REQUEST_URI'] . "</strong><br>";
    echo '</div>';

    // Test 3: Test direct de l'endpoint avec FormData simulé
    echo '<div class="section info"><h2>3. Test de l\'endpoint avec FormData</h2>';
    
    // Préparer les données de test
    $testData = [
        'order_number' => 'TEST' . date('YmdHis'),
        'amount' => '1500',
        'senderPhone' => '+225 01 02 03 04 05',
        'email' => 'test@example.com'
    ];
    
    echo "Données de test:<br><pre>" . print_r($testData, true) . "</pre>";
    
    // Simuler l'appel
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/initiate_order_payment.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo '<div class="error">❌ Erreur cURL: ' . $error . '</div>';
        $issues[] = "Erreur cURL: $error";
    } else {
        echo "Code de réponse: <strong>$httpCode</strong><br>";
        echo "Réponse:<br><pre>" . htmlspecialchars($response) . "</pre>";
        
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo '<div class="success">✅ JSON valide reçu</div>';
            $successes[] = "Endpoint retourne du JSON valide";
            
            if (isset($json['success'])) {
                if ($json['success']) {
                    echo '<div class="success">✅ Payment initiation successful</div>';
                    $successes[] = "Initiation de paiement réussie";
                } else {
                    echo '<div class="warning">⚠️ Payment failed: ' . ($json['message'] ?? 'Unknown error') . '</div>';
                    $issues[] = "Échec du paiement: " . ($json['message'] ?? 'Unknown error');
                }
            }
        } else {
            echo '<div class="error">❌ Réponse n\'est pas du JSON valide</div>';
            $issues[] = "Réponse JSON invalide";
        }
    }
    echo '</div>';

    // Test 4: Test JavaScript fetch simulation
    echo '<div class="section info"><h2>4. Test Fetch JavaScript</h2>';
    ?>
    
    <button onclick="testJavaScriptFetch()">Tester Fetch JavaScript</button>
    <div id="js-test-result"></div>
    
    <script>
        const ROOT_PATH = '<?php echo $rootPath; ?>';
        
        async function testJavaScriptFetch() {
            const container = document.getElementById('js-test-result');
            container.innerHTML = '<p>Test en cours...</p>';
            
            try {
                const formData = new FormData();
                formData.append('order_number', 'JSTEST' + Date.now());
                formData.append('amount', '1500');
                formData.append('senderPhone', '+225 01 02 03 04 05');
                formData.append('email', 'test@example.com');
                
                const endpoint = `${ROOT_PATH}/api/initiate_order_payment.php`;
                console.log('🛵 Testing endpoint:', endpoint);
                
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                
                container.innerHTML = `
                    <div class="${response.ok ? 'success' : 'error'}">
                        <strong>Status:</strong> ${response.status} ${response.statusText}<br>
                        <strong>Response:</strong><br>
                        <pre>${text}</pre>
                    </div>
                `;
                
            } catch (error) {
                container.innerHTML = `<div class="error">❌ Erreur: ${error.message}</div>`;
            }
        }
    </script>
    
    <?php
    // Résumé final
    echo '<div class="section ' . (empty($issues) ? 'success' : 'warning') . '"><h2>📋 Résumé</h2>';
    
    if (!empty($successes)) {
        echo '<h3>✅ Points positifs:</h3><ul>';
        foreach ($successes as $success) {
            echo "<li>$success</li>";
        }
        echo '</ul>';
    }
    
    if (!empty($issues)) {
        echo '<h3>⚠️ Points à améliorer:</h3><ul>';
        foreach ($issues as $issue) {
            echo "<li>$issue</li>";
        }
        echo '</ul>';
    } else {
        echo '<div class="success"><strong>🎉 Tous les tests sont réussis!</strong><br>L\'endpoint de paiement fonctionne correctement.</div>';
    }
    
    echo '</div>';
    ?>

    <div class="section info">
        <h2>📝 Instructions de test</h2>
        <ol>
            <li>Cliquer sur "Tester Fetch JavaScript" ci-dessus</li>
            <li>Ouvrir la console du navigateur (F12)</li>
            <li>Aller sur <a href="index.php">la page principale</a></li>
            <li>Remplir le formulaire de commande</li>
            <li>Sélectionner un mode de paiement électronique</li>
            <li>Cliquer sur "Commander"</li>
            <li>Vérifier dans la console les logs "🛵 Initiating payment at..."</li>
        </ol>
    </div>
</body>
</html>
