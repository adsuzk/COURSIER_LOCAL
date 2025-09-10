<?php
// sections/modals.php - Modales de connexion/inscription et compte
?>

    <!-- Modal Connexion AJAX -->
    <div id="connexionModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span id="closeConnexionModal" class="close">&times;</span>
            <div id="connexionModalBody">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Modal de paiement CinetPay intégré -->
    <div id="paymentModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:10000;">
        <div class="modal-content" style="position:relative;width:90%;height:90%;background:#fff;border-radius:8px;overflow:hidden;">
            <span onclick="closePaymentModal()" style="position:absolute;top:10px;right:20px;font-size:24px;cursor:pointer;z-index:10001;">&times;</span>
            <iframe id="paymentIframe" src="" frameborder="0" style="width:100%;height:100%;"></iframe>
        </div>
    </div>
    <script>
        function showPaymentModal(url) {
            const modal = document.getElementById('paymentModal');
            const iframe = document.getElementById('paymentIframe');
            iframe.src = url;
            modal.style.display = 'flex';
        }
        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            const iframe = document.getElementById('paymentIframe');
            iframe.src = '';
            modal.style.display = 'none';
        }
