<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Fetch API Payment</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ccc; padding: 15px; margin: 10px 0; }
        .result { background: #f5f5f5; padding: 10px; margin: 10px 0; }
        .error { background: #ffebee; color: #c62828; }
        .success { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <h1>Test Fetch API - Payment Endpoint</h1>
    
    <div class="test-section">
        <h2>1. Vérification ROOT_PATH</h2>
        <?php
        $rootPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        echo "<p>ROOT_PATH défini: <strong>$rootPath</strong></p>";
        echo "<p>URL courante: <strong>" . $_SERVER['REQUEST_URI'] . "</strong></p>";
        echo "<p>Document Root: <strong>" . $_SERVER['DOCUMENT_ROOT'] . "</strong></p>";
        ?>
        <script>
            const ROOT_PATH = '<?php echo $rootPath; ?>';
            document.write('<p>ROOT_PATH en JS: <strong>' + ROOT_PATH + '</strong></p>');
            document.write('<p>URL courante JS: <strong>' + window.location.href + '</strong></p>');
            document.write('<p>Pathname JS: <strong>' + window.location.pathname + '</strong></p>');
        </script>
    </div>

    <div class="test-section">
        <h2>2. Test avec FormData (comme dans l'application)</h2>
        <button onclick="testWithFormData()">Tester avec FormData</button>
        <div id="formdata-result" class="result"></div>
    </div>

    <div class="test-section">
        <h2>3. Test URLs alternatives</h2>
        <button onclick="testAlternativeUrls()">Tester différentes URLs</button>
        <div id="alternative-result" class="result"></div>
    </div>

    <script>
        const ROOT_PATH = '<?php echo $rootPath; ?>';
        
        function logResult(containerId, message, isError = false) {
            const container = document.getElementById(containerId);
            const div = document.createElement('div');
            div.className = isError ? 'error' : 'success';
            div.innerHTML = message;
            container.appendChild(div);
        }

        async function testWithFormData() {
            const container = document.getElementById('formdata-result');
            container.innerHTML = '';
            
            const formData = new FormData();
            formData.append('order_number', 'TEST' + Date.now());
            formData.append('amount', '1500');
            formData.append('senderPhone', '+225 01 02 03 04 05');
            formData.append('email', 'test@example.com');
            
            const endpoint = `${ROOT_PATH}/api/initiate_order_payment.php`;
            logResult('formdata-result', `🛵 Test endpoint: ${endpoint}`);
            
            try {
                console.log('🛵 Initiating payment at', endpoint, 'current pathname=', window.location.pathname);
                
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });
                
                logResult('formdata-result', `Status: ${response.status} ${response.statusText}`);
                logResult('formdata-result', `Headers: ${response.headers.get('content-type')}`);
                
                const text = await response.text();
                logResult('formdata-result', `Réponse brute: <pre>${text.substring(0, 1000)}${text.length > 1000 ? '...' : ''}</pre>`);
                
                // Essayer de parser en JSON
                try {
                    const json = JSON.parse(text);
                    logResult('formdata-result', `✅ JSON parsé: <pre>${JSON.stringify(json, null, 2)}</pre>`);
                } catch (e) {
                    logResult('formdata-result', `❌ Erreur parsing JSON: ${e.message}`, true);
                }
                
            } catch (error) {
                logResult('formdata-result', `❌ Erreur fetch: ${error.message}`, true);
                console.error('Paiement init error:', error);
            }
        }

        async function testAlternativeUrls() {
            const container = document.getElementById('alternative-result');
            container.innerHTML = '';
            
            const urls = [
                'api/initiate_order_payment.php',
                './api/initiate_order_payment.php',
                '/api/initiate_order_payment.php',
                `${ROOT_PATH}/api/initiate_order_payment.php`,
                window.location.origin + '/api/initiate_order_payment.php',
                window.location.origin + ROOT_PATH + '/api/initiate_order_payment.php'
            ];
            
            for (const url of urls) {
                try {
                    logResult('alternative-result', `Test: ${url}`);
                    const response = await fetch(url, {
                        method: 'POST',
                        body: (() => {
                            const fd = new FormData();
                            fd.append('order_number', 'TEST123');
                            fd.append('amount', '1000');
                            return fd;
                        })()
                    });
                    
                    logResult('alternative-result', `✅ ${url} → Status: ${response.status} (${response.statusText})`);
                    
                } catch (error) {
                    logResult('alternative-result', `❌ ${url} → Error: ${error.message}`, true);
                }
            }
        }
    </script>
</body>
</html>
