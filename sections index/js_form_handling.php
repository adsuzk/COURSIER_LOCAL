<?php
// sections/js_form_handling.php - Fonctions de gestion des formulaires et validation
?>
    <script>
    // Initialize currentClient flag from PHP session
    window.currentClient = <?php echo !empty($_SESSION['client_id']) ? 'true' : 'false'; ?>;
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('orderForm');

        // Format Ivorian phone number: show +225 format ONLY when exactly 10 digits are entered
        function formatPhone(v) {
            // Remove non-digit characters
            let d = v.replace(/\D/g, '');

            // Strip leading country code if pasted
            if (d.startsWith('225')) d = d.slice(3);

            // Limit to maximum 10 digits
            d = d.slice(0, 10);

            // If no digits, return empty
            if (!d) return '';

            // Group digits in pairs for display
            const grouped = d.replace(/(\d{2})(?=\d)/g, '$1 ');

            // Prefix with country code if exactly 10 digits
            if (d.length === 10) {
                return '+225 ' + grouped;
            }
            return grouped;
        }

        // Validate phone number: supports +225, 225 or 0 prefix and 8-9 digits
        function validatePhone(v) {
            const c = v.replace(/\s/g, '');
            // Accept +225XXXXXXXXXX or XXXXXXXXXX (10 digits)
            return /^\+225\d{10}$/.test(c) || /^\d{10}$/.test(c);
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

            // SEULS DÉPART, DESTINATION ET PRIORITÉ SONT OBLIGATOIRES
            if (!dep.value.trim()) errors.push('Départ requis');
            if (!dst.value.trim()) errors.push('Destination requise');
            if (!pr) errors.push('Priorité requise');
            
            // TÉLÉPHONES OPTIONNELS - Valider seulement s'ils sont remplis
            if (phS.value.trim() && !validatePhone(phS.value)) {
                errors.push('Téléphone Expéditeur invalide (si rempli)');
            }
            if (phR.value.trim() && !validatePhone(phR.value)) {
                errors.push('Téléphone Destinataire invalide (si rempli)');
            }

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
                console.log('🛵 Initiating payment at', endpoint, 'current pathname=', window.location.pathname);
                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                    .then(data => {
                    if (data.success && data.payment_url) {
                        console.log('✅ Paiement initié avec succès, URL:', data.payment_url);
                        
                        // Vérifier que showPaymentModal existe avant de l'appeler
                        if (typeof window.showPaymentModal === 'function') {
                            window.showPaymentModal(data.payment_url);
                        } else {
                            console.error('❌ showPaymentModal non disponible');
                            // Fallback: ouvrir dans une nouvelle fenêtre
                            window.open(data.payment_url, '_blank', 'width=800,height=600');
                        }
                    } else {
                        console.error('❌ Erreur API paiement:', data);
                        alert('Erreur lors de l\'initialisation du paiement: ' + (data.message || 'Erreur inconnue'));
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
