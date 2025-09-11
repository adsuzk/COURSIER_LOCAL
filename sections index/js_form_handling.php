<?php
// sections/js_form_handling.php - Fonctions de gestion des formulaires et validation
?>
    <script>
    // Initialize currentClient flag from PHP session
    window.currentClient = <?php echo !empty($_SESSION['client_id']) ? 'true' : 'false'; ?>;
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('orderForm');

        // Format Ivorian phone number: remove non-digits, strip 225, pad leading 0, add spaces every 2 digits
        function formatPhone(v) {
            // Remove non-digit characters and strip leading country code if pasted
            let d = v.replace(/\D/g, '');
            if (d.startsWith('225')) d = d.slice(3);
            // Limit to 10 local digits
            d = d.slice(0, 10);
            // Split into pairs of 1 or 2 digits
            const parts = d.match(/\d{1,2}/g) || [];
            if (parts.length === 0) return '';
            // Build final string with country code and grouped digits
            return '+225 ' + parts.join(' ');
        }

        // Validate phone number: supports +225, 225 or 0 prefix and 8-9 digits
        function validatePhone(v) {
            const c = v.replace(/\s/g, '');
            // Expect +225 followed by exactly 10 digits
            return /^\+225\d{10}$/.test(c);
        }

        // Attach formatting to phone inputs
        const sender = document.getElementById('senderPhone');
        if (sender) {
            // Auto-format any prefilled value
            if (sender.value) sender.value = formatPhone(sender.value);
            sender.addEventListener('input', e => e.target.value = formatPhone(e.target.value));
        }

        const receiver = document.getElementById('receiverPhone');
        if (receiver) {
            // Auto-format any prefilled value
            if (receiver.value) receiver.value = formatPhone(receiver.value);
            receiver.addEventListener('input', e => e.target.value = formatPhone(e.target.value));
        }

        // Validate form fields
        function validateForm() {
            const dep = document.getElementById('departure');
            const dst = document.getElementById('destination');
            const phS = document.getElementById('senderPhone');
            const phR = document.getElementById('receiverPhone');
            const pr = document.querySelector('input[name="priority"]:checked');
            let errors = [];

            if (!dep.value.trim()) errors.push('Départ requis');
            if (!dst.value.trim()) errors.push('Destination requise');
            if (!phS.value.trim() || !validatePhone(phS.value)) errors.push('Téléphone Expéditeur invalide');
            if (!phR.value.trim() || !validatePhone(phR.value)) errors.push('Téléphone Destinataire invalide');
            if (!pr) errors.push('Priorité requise');

            if (errors.length) {
                alert(errors.join('\n'));
                return false;
            }
            return true;
        }

        // Handle order submission
        function processOrder(e) {
            console.log('🛵 processOrder called, currentClient=', window.currentClient);
            e.preventDefault();
            // Enforce login: show modal if not connected
            if (!window.currentClient) {
                const openLink = document.getElementById('openConnexionLink');
                if (openLink) openLink.click();
                return;
            }
            if (!validateForm()) return;
            const paymentMethod = (document.querySelector('input[name="paymentMethod"]:checked') || { value: 'cash' }).value;
            if (paymentMethod === 'cash') {
                form.submit();
            } else {
                const formData = new FormData(form);
                
                // Ajouter les données requises pour l'API de paiement
                const orderNumber = 'SZK' + Date.now(); // Générer un numéro de commande temporaire
                const priceElement = document.getElementById('total-price');
                const priceText = priceElement ? priceElement.textContent : '';
                const amount = priceText.match(/(\d+)/)?.[1] || '1500'; // Extraire le prix ou utiliser 1500 par défaut
                
                formData.append('order_number', orderNumber);
                formData.append('amount', amount);
                
                console.log('🛵 Payment data:', {orderNumber, amount});
                
                // Initiate payment via API using ROOT_PATH for correct base path
                const endpoint = `${ROOT_PATH}/api/initiate_order_payment.php`;
                console.log('🛵 Initiating payment at', endpoint, \
                    'current pathname=', window.location.pathname);
                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.payment_url) {
                        showPaymentModal(data.payment_url);
                    } else {
                        alert('Erreur lors de l\'initialisation du paiement.');
                    }
                })
                .catch(err => {
                    console.error('Paiement init error:', err);
                    alert('Impossible d\'initier le paiement.');
                });
            }
        }

    // Expose for invocation
    window.processOrder = processOrder;

    // Attach to submit button
    const btn = document.querySelector('.submit-btn');
    if (btn) btn.addEventListener('click', processOrder);
    });
    </script>
