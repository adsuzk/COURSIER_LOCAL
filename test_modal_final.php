<!DOCTYPE html>
<html>
<head>
    <title>Test Modal Validation</title>
</head>
<body>
    <h2>🧪 Validation Modal CinetPay</h2>
    
    <p>Ce test valide que le modal fonctionne avec l'URL de paiement réelle.</p>
    
    <button onclick="testRealPayment()">Tester avec URL CinetPay réelle</button>
    <button onclick="testWithGoogle()">Tester avec Google (debug)</button>
    
    <div id="log"></div>
    
    <!-- Inclure le modal exactement comme dans index.php -->
    <?php include 'sections index/modals.php'; ?>
    
    <script>
        function log(msg) {
            document.getElementById('log').innerHTML += '<div>' + msg + '</div>';
            console.log(msg);
        }
        
        function testRealPayment() {
            log('=== TEST AVEC URL CINETPAY RÉELLE ===');
            
            // Utiliser l'API pour générer une URL de test
            const formData = new FormData();
            formData.append('order_number', 'TEST_MODAL_' + Date.now());
            formData.append('amount', '1000');
            
            fetch('/COURSIER_LOCAL/api/initiate_order_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.payment_url) {
                    log('✅ URL de paiement générée: ' + data.payment_url);
                    log('🎯 Tentative d\'ouverture du modal...');
                    
                    if (typeof window.showPaymentModal === 'function') {
                        window.showPaymentModal(data.payment_url);
                        log('✅ Modal ouvert avec succès!');
                    } else {
                        log('❌ showPaymentModal non disponible');
                    }
                } else {
                    log('❌ Erreur API: ' + JSON.stringify(data));
                }
            })
            .catch(err => {
                log('❌ Erreur réseau: ' + err.message);
            });
        }
        
        function testWithGoogle() {
            log('=== TEST AVEC GOOGLE ===');
            if (typeof window.showPaymentModal === 'function') {
                window.showPaymentModal('https://www.google.com');
                log('✅ Modal Google ouvert');
            } else {
                log('❌ showPaymentModal non disponible');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            log('📋 Page de test chargée');
            log('État showPaymentModal: ' + typeof window.showPaymentModal);
        });
    </script>
</body>
</html>