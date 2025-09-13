<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Debug CinetPay Modal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; }
        .error { border-left-color: #dc3545; }
        .warning { border-left-color: #ffc107; }
        button { padding: 15px 30px; margin: 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
    </style>
</head>
<body>
    <h1>🧪 Test Debug Modal CinetPay</h1>
    
    <div class="test-box">
        <h2>1. Test des éléments DOM</h2>
        <button class="btn-primary" onclick="testDOMElements()">🔍 Vérifier DOM</button>
        <div id="dom-results"></div>
    </div>
    
    <div class="test-box">
        <h2>2. Test fonction showPaymentModal</h2>
        <button class="btn-success" onclick="testShowPaymentModal()">🚀 Tester Modal</button>
        <div id="modal-results"></div>
    </div>
    
    <div class="test-box">
        <h2>3. Test API de paiement</h2>
        <button class="btn-warning" onclick="testPaymentAPI()">📡 Tester API</button>
        <div id="api-results"></div>
    </div>
    
    <div class="test-box">
        <h2>4. Test processOrder</h2>
        <button class="btn-primary" onclick="testProcessOrder()">🛵 Simuler Commander</button>
        <div id="process-results"></div>
    </div>

    <!-- Inclure les modaux -->
    <?php include 'sections index/modals.php'; ?>

    <script>
        function testDOMElements() {
            const results = document.getElementById('dom-results');
            let html = '<h3>Résultats vérification DOM :</h3>';
            
            const elements = [
                'paymentModal',
                'paymentIframe',
                'orderForm'
            ];
            
            elements.forEach(id => {
                const element = document.getElementById(id);
                const exists = element !== null;
                html += `<p>${exists ? '✅' : '❌'} ${id} : ${exists ? 'Trouvé' : 'Manquant'}</p>`;
            });
            
            results.innerHTML = html;
        }
        
        function testShowPaymentModal() {
            const results = document.getElementById('modal-results');
            let html = '<h3>Test fonction showPaymentModal :</h3>';
            
            try {
                if (typeof window.showPaymentModal === 'function') {
                    html += '<p>✅ Fonction showPaymentModal existe</p>';
                    
                    // Test avec URL fictive
                    const testUrl = 'https://secure.cinetpay.com/payment/test123';
                    window.showPaymentModal(testUrl);
                    
                    html += '<p>✅ Modal appelé avec succès</p>';
                    html += `<p>🔗 URL test utilisée : ${testUrl}</p>`;
                    
                    // Vérifier si le modal est visible
                    const modal = document.getElementById('paymentModal');
                    if (modal && modal.style.display === 'flex') {
                        html += '<p>✅ Modal est maintenant visible !</p>';
                        
                        // Fermer après 3 secondes
                        setTimeout(() => {
                            window.closePaymentModal();
                        }, 3000);
                    }
                    
                } else {
                    html += '<p>❌ Fonction showPaymentModal introuvable</p>';
                }
            } catch (error) {
                html += `<p>❌ Erreur : ${error.message}</p>`;
            }
            
            results.innerHTML = html;
        }
        
        function testPaymentAPI() {
            const results = document.getElementById('api-results');
            results.innerHTML = '<p>⏳ Test API en cours...</p>';
            
            const testData = new FormData();
            testData.append('order_number', 'TEST_' + Date.now());
            testData.append('amount', '1500');
            
            fetch('/COURSIER_LOCAL/api/initiate_order_payment.php', {
                method: 'POST',
                body: testData
            })
            .then(response => response.text())
            .then(text => {
                let html = '<h3>Réponse API :</h3>';
                html += `<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow: auto;">${text}</pre>`;
                
                try {
                    const data = JSON.parse(text);
                    if (data.success && data.payment_url) {
                        html += '<p>✅ API retourne une URL de paiement valide</p>';
                        html += `<p>URL : ${data.payment_url}</p>`;
                    } else {
                        html += '<p>⚠️ API ne retourne pas d\'URL de paiement</p>';
                    }
                } catch (e) {
                    html += '<p>❌ Réponse API non-JSON</p>';
                }
                
                results.innerHTML = html;
            })
            .catch(error => {
                results.innerHTML = `<p>❌ Erreur API : ${error.message}</p>`;
            });
        }
        
        function testProcessOrder() {
            const results = document.getElementById('process-results');
            results.innerHTML = '<p>🛵 Simulation processOrder en cours...</p>';
            
            // Simuler les variables globales
            window.currentClient = true;
            window.ROOT_PATH = '/COURSIER_LOCAL';
            
            // Créer un FormData simulé
            const formData = new FormData();
            formData.append('departure', 'Cocody');
            formData.append('destination', 'Plateau');
            formData.append('order_number', 'SZK' + Date.now());
            formData.append('amount', '1500');
            
            fetch('/COURSIER_LOCAL/api/initiate_order_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                let html = '<h3>Résultat simulation processOrder :</h3>';
                
                if (data.success && data.payment_url) {
                    html += '<p>✅ API OK, tentative ouverture modal...</p>';
                    html += `<p>URL : ${data.payment_url}</p>`;
                    
                    // Appeler showPaymentModal comme le vrai code
                    if (typeof window.showPaymentModal === 'function') {
                        window.showPaymentModal(data.payment_url);
                        html += '<p>✅ Modal CinetPay ouvert avec succès !</p>';
                    } else {
                        html += '<p>❌ Fonction showPaymentModal introuvable</p>';
                    }
                } else {
                    html += '<p>❌ Erreur API : ' + (data.message || 'Inconnue') + '</p>';
                }
                
                results.innerHTML = html;
            })
            .catch(error => {
                results.innerHTML = `<p>❌ Erreur : ${error.message}</p>`;
            });
        }
        
        // Test automatique au chargement
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔧 Page de test chargée');
            setTimeout(testDOMElements, 500);
        });
    </script>
</body>
</html>