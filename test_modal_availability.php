<!DOCTYPE html>
<html>
<head>
    <title>Test Modal CinetPay</title>
</head>
<body>
    <h2>🧪 Test Modal CinetPay</h2>
    
    <button id="testModal" onclick="testShowPaymentModal()">Tester showPaymentModal</button>
    <button id="testModalWindow" onclick="testWindowAccess()">Tester window.showPaymentModal</button>
    
    <div id="results"></div>
    
    <!-- Inclure le modal exactement comme dans index.php -->
    <?php include 'sections index/modals.php'; ?>
    
    <script>
        function logResult(message, type = 'info') {
            const div = document.getElementById('results');
            const color = type === 'error' ? 'red' : type === 'success' ? 'green' : 'blue';
            div.innerHTML += `<div style="color: ${color};">${message}</div>`;
            console.log(message);
        }
        
        function testShowPaymentModal() {
            logResult('=== TEST SHOWPAYMENTMODAL ===');
            logResult('typeof showPaymentModal: ' + typeof showPaymentModal);
            logResult('typeof window.showPaymentModal: ' + typeof window.showPaymentModal);
            
            if (typeof window.showPaymentModal === 'function') {
                logResult('✅ window.showPaymentModal est disponible!', 'success');
                try {
                    window.showPaymentModal('https://checkout.cinetpay.com/payment/test');
                    logResult('✅ Modal ouvert avec succès!', 'success');
                } catch (e) {
                    logResult('❌ Erreur lors de l\'ouverture: ' + e.message, 'error');
                }
            } else {
                logResult('❌ window.showPaymentModal non disponible', 'error');
            }
        }
        
        function testWindowAccess() {
            logResult('=== TEST WINDOW ACCESS ===');
            logResult('window keys contenant "Payment": ' + Object.keys(window).filter(k => k.includes('Payment')));
            logResult('window keys contenant "modal": ' + Object.keys(window).filter(k => k.toLowerCase().includes('modal')));
        }
        
        // Test automatique au chargement
        document.addEventListener('DOMContentLoaded', function() {
            logResult('📋 Page chargée, test automatique...');
            setTimeout(() => {
                testShowPaymentModal();
                testWindowAccess();
            }, 1000);
        });
    </script>
</body>
</html>