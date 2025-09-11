<!DOCTYPE html>
<html>
<head>
    <title>Test Production-like Payment</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .result { background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 4px solid #ccc; }
        .error { border-left-color: #f44336; background: #ffebee; }
        .success { border-left-color: #4caf50; background: #e8f5e9; }
    </style>
</head>
<body>
    <h1>Test Production-like Payment Flow</h1>
    
    <div>
        <h2>Configuration actuelle</h2>
        <div id="config-info"></div>
    </div>
    
    <div>
        <h2>Test 1: Diagnostic Endpoint</h2>
        <button onclick="testDiagnostic()">Test Diagnostic</button>
        <div id="diagnostic-result"></div>
    </div>
    
    <div>
        <h2>Test 2: Real Payment Endpoint</h2>
        <button onclick="testRealPayment()">Test Real Payment</button>
        <div id="payment-result"></div>
    </div>

    <script>
        // Configuration ROOT_PATH comme dans l'application
        const ROOT_PATH = '<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>';
        
        // Afficher la configuration
        document.getElementById('config-info').innerHTML = `
            <div class="result">
                <strong>ROOT_PATH:</strong> "${ROOT_PATH}"<br>
                <strong>Current URL:</strong> ${window.location.href}<br>
                <strong>Pathname:</strong> ${window.location.pathname}<br>
                <strong>Origin:</strong> ${window.location.origin}
            </div>
        `;

        function logResult(containerId, message, isError = false) {
            const container = document.getElementById(containerId);
            const div = document.createElement('div');
            div.className = 'result ' + (isError ? 'error' : 'success');
            div.innerHTML = message;
            container.appendChild(div);
        }

        async function testDiagnostic() {
            const container = document.getElementById('diagnostic-result');
            container.innerHTML = '';
            
            // Créer FormData comme dans l'application
            const formData = new FormData();
            formData.append('departure', 'Cocody Riviera');
            formData.append('destination', 'Plateau Centre Ville');
            formData.append('senderPhone', '+225 01 02 03 04 05');
            formData.append('receiverPhone', '+225 06 07 08 09 10');
            formData.append('priority', 'normale');
            formData.append('paymentMethod', 'orange_money');
            formData.append('price', '1500');
            
            const endpoint = `${ROOT_PATH}/diagnostic_payment_endpoint.php`;
            logResult('diagnostic-result', `🛵 Test diagnostic endpoint: ${endpoint}`);
            
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });
                
                logResult('diagnostic-result', `Status: ${response.status} ${response.statusText}`);
                
                const text = await response.text();
                logResult('diagnostic-result', `Réponse reçue (${text.length} chars)`);
                
                try {
                    const json = JSON.parse(text);
                    logResult('diagnostic-result', `✅ JSON valide: <pre>${JSON.stringify(json, null, 2)}</pre>`);
                } catch (e) {
                    logResult('diagnostic-result', `❌ JSON invalide: ${e.message}<br>Réponse: <pre>${text}</pre>`, true);
                }
                
            } catch (error) {
                logResult('diagnostic-result', `❌ Erreur fetch: ${error.message}`, true);
            }
        }

        async function testRealPayment() {
            const container = document.getElementById('payment-result');
            container.innerHTML = '';
            
            // Créer FormData comme dans l'application
            const formData = new FormData();
            formData.append('departure', 'Cocody Riviera');
            formData.append('destination', 'Plateau Centre Ville');
            formData.append('senderPhone', '+225 01 02 03 04 05');
            formData.append('receiverPhone', '+225 06 07 08 09 10');
            formData.append('priority', 'normale');
            formData.append('paymentMethod', 'orange_money');
            formData.append('price', '1500');
            
            const endpoint = `${ROOT_PATH}/api/initiate_order_payment.php`;
            logResult('payment-result', `🛵 Test payment endpoint: ${endpoint}`);
            
            try {
                console.log('🛵 Initiating payment at', endpoint, 'current pathname=', window.location.pathname);
                
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });
                
                logResult('payment-result', `Status: ${response.status} ${response.statusText}`);
                
                const text = await response.text();
                logResult('payment-result', `Réponse reçue (${text.length} chars)`);
                
                try {
                    const json = JSON.parse(text);
                    logResult('payment-result', `✅ JSON valide: <pre>${JSON.stringify(json, null, 2)}</pre>`);
                    
                    if (json.success && json.payment_url) {
                        logResult('payment-result', `✅ Payment URL reçue: ${json.payment_url}`);
                    } else {
                        logResult('payment-result', `⚠️ Payment failed: ${json.message || 'Unknown error'}`, true);
                    }
                } catch (e) {
                    logResult('payment-result', `❌ JSON invalide: ${e.message}<br>Réponse: <pre>${text}</pre>`, true);
                }
                
            } catch (error) {
                logResult('payment-result', `❌ Erreur fetch: ${error.message}`, true);
                console.error('Paiement init error:', error);
            }
        }
    </script>
</body>
</html>
