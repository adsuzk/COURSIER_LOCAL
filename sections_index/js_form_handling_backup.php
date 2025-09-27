<?php<?php

// sections/js_form_handling.php - Fonctions de gestion des formulaires et validation// sections/js_form_handling.php - Fonctions de gestion des formulaires et validation

?>?>

<script>    <script>

    // Initialize currentClient flag from PHP session    // Initialize currentClient flag from            document.body.appendChild(modal);

    window.currentClient = <?php echo !empty($_SESSION['client_id']) ? 'true' : 'false'; ?>;            document.body.style.overflow = 'hidden';

                

    // DÉFINITION SIMPLE ET FONCTIONNELLE de showPaymentModal            console.log('✅ Modal ÉLÉGANT créé avec design Suzosky premium');

    if (typeof window.showPaymentModal !== 'function') {            console.log('🎨 Dimensions: 85vw x 85vh (max 900x700px) - Taille parfaite');

        console.log('🔧 DÉFINITION de showPaymentModal - Version propre');            console.log('✨ Animations et effets premium ajoutés');

                    

        window.showPaymentModal = function(url) {            // VÉRIFIER QUE TOUT EST PARFAIT

            console.log('🎯 showPaymentModal appelée avec URL:', url);            setTimeout(() => {

                            const closeBtn = modal.querySelector('button');

            // Supprimer modal existant                if (closeBtn) {

            const existing = document.getElementById('paymentModal');                    console.log('✅ Bouton fermeture ÉLÉGANT trouvé et fonctionnel');

            if (existing) existing.remove();                } else {

                                console.log('❌ Erreur: Bouton fermeture manquant');

            // Créer le modal                }

            const modal = document.createElement('div');                

            modal.id = 'paymentModal';                // S'assurer que les modes de paiement restent visibles en arrière-plan

            modal.style.cssText = `                const paymentMethods = document.getElementById('paymentMethods');

                display: flex !important;                if (paymentMethods && paymentMethods.style.display !== 'block') {

                position: fixed !important;                    console.log('🔧 Correction: réaffichage des modes de paiement');

                top: 0 !important;                    paymentMethods.style.display = 'block';

                left: 0 !important;                }

                width: 100vw !important;            }, 100);

                height: 100vh !important;        };

                background: rgba(26, 26, 46, 0.85) !important;        

                z-index: 999999 !important;        window.closePaymentModal = function() {

                justify-content: center !important;        console.log('🔧 DÉFINITION ÉLÉGANTE de showPaymentModal dans js_form_handling.php [v2025-09-13-06-00]');

                align-items: center !important;        window.showPaymentModal = function(url) {

            `;            console.log('🎯 showPaymentModal ÉLÉGANTE appelée avec URL:', url);

                        

            modal.innerHTML = `            // SUPPRIMER TOUT MODAL EXISTANT PROPREMENT

                <div style="            const existingModals = document.querySelectorAll('#paymentModal, .modal-backdrop, [id*="payment"]');

                    position: relative !important;            existingModals.forEach(modal => modal.remove());

                    width: 85vw !important;            

                    height: 85vh !important;            // CRÉER LE MODAL ÉLÉGANT SUZOSKY

                    max-width: 900px !important;            const modal = document.createElement('div');

                    max-height: 700px !important;            modal.id = 'paymentModal';

                    background: white !important;            modal.style.cssText = `

                    border-radius: 20px !important;                display: flex !important;

                    overflow: hidden !important;                position: fixed !important;

                    box-shadow: 0 25px 80px rgba(0,0,0,0.4) !important;                top: 0 !important;

                ">                left: 0 !important;

                    <button onclick="window.closePaymentModal()" style="                width: 100vw !important;

                        position: absolute !important;                height: 100vh !important;

                        top: 20px !important;                background: rgba(26, 26, 46, 0.85) !important;

                        right: 20px !important;                backdrop-filter: blur(8px) !important;

                        width: 45px !important;                z-index: 999999 !important;

                        height: 45px !important;                justify-content: center !important;

                        background: #E94560 !important;                align-items: center !important;

                        color: white !important;                animation: fadeIn 0.3s ease-out !important;

                        border: none !important;            `;

                        border-radius: 50% !important;            

                        cursor: pointer !important;            modal.innerHTML = `

                        z-index: 1000001 !important;                <div style="

                        font-size: 20px !important;                    position: relative !important;

                        font-weight: 600 !important;                    width: 85vw !important;

                        display: flex !important;                    height: 85vh !important;

                        align-items: center !important;                    max-width: 900px !important;

                        justify-content: center !important;                    max-height: 700px !important;

                    ">✕</button>                    background: white !important;

                                        border-radius: 20px !important;

                    <div style="                    overflow: hidden !important;

                        background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 100%) !important;                    box-shadow: 0 25px 80px rgba(0,0,0,0.4), 0 0 40px rgba(212, 168, 83, 0.3) !important;

                        padding: 25px !important;                    border: 2px solid transparent !important;

                        color: #1A1A2E !important;                    background-clip: padding-box !important;

                        font-weight: 700 !important;                    animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) !important;

                        font-size: 18px !important;                ">

                    ">                    <!-- BOUTON FERMETURE ÉLÉGANT -->

                        🔒 Paiement Sécurisé - Coursier Suzosky                    <button onclick="event.stopPropagation(); window.closePaymentModal(); return false;" style="

                    </div>                        position: absolute !important;

                                            top: 20px !important;

                    <iframe src="${url}" frameborder="0" style="                        right: 20px !important;

                        width: 100% !important;                        width: 45px !important;

                        height: calc(100% - 80px) !important;                        height: 45px !important;

                        border: none !important;                        background: linear-gradient(135deg, #E94560, #C73650) !important;

                    "></iframe>                        color: white !important;

                </div>                        border: none !important;

            `;                        border-radius: 50% !important;

                                    cursor: pointer !important;

            document.body.appendChild(modal);                        z-index: 1000001 !important;

            document.body.style.overflow = 'hidden';                        font-size: 20px !important;

            console.log('✅ Modal créé et affiché');                        font-weight: 600 !important;

        };                        box-shadow: 0 6px 20px rgba(233, 69, 96, 0.4) !important;

                                display: flex !important;

        window.closePaymentModal = function() {                        align-items: center !important;

            console.log('🔒 Fermeture modal');                        justify-content: center !important;

            const modal = document.getElementById('paymentModal');                        transition: all 0.2s ease !important;

            if (modal) {                    " onmouseover="this.style.transform='scale(1.1) rotate(90deg)'; this.style.boxShadow='0 8px 25px rgba(233, 69, 96, 0.6)'" onmouseout="this.style.transform='scale(1) rotate(0deg)'; this.style.boxShadow='0 6px 20px rgba(233, 69, 96, 0.4)'">✕</button>

                modal.remove();                    

                document.body.style.overflow = 'auto';                    <!-- EN-TÊTE ÉLÉGANT SUZOSKY -->

                                    <div style="

                // Réafficher les modes de paiement                        background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 30%, #E8D07A 70%, #D4A853 100%) !important;

                const paymentMethods = document.getElementById('paymentMethods');                        padding: 25px 70px 25px 30px !important;

                if (paymentMethods) {                        color: #1A1A2E !important;

                    paymentMethods.style.display = 'block';                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;

                }                        font-weight: 700 !important;

                                        font-size: 18px !important;

                console.log('✅ Modal fermé proprement');                        border-bottom: none !important;

            }                        position: relative !important;

        };                    ">

    }                        <div style="display: flex; align-items: center; gap: 12px;">

                                <div style="

    console.log('🔧 js_form_handling.php chargé - Version propre');                                width: 40px;

</script>                                height: 40px;
                                background: rgba(26, 26, 46, 0.1);
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 20px;
                            ">🔒</div>
                            <div>
                                <div style="font-size: 18px; font-weight: 700;">Paiement Sécurisé</div>
                                <div style="font-size: 14px; opacity: 0.8; font-weight: 500;">Coursier Suzosky</div>
                            </div>
                        </div>
                        <!-- Décoration dorée -->
                        <div style="
                            position: absolute;
                            top: 0;
                            right: 0;
                            width: 100px;
                            height: 100%;
                            background: linear-gradient(90deg, transparent 0%, rgba(212, 168, 83, 0.2) 100%);
                            pointer-events: none;
                        "></div>
                    </div>
                    
                    <!-- IFRAME CINETPAY ÉLÉGANT -->
                    <iframe id="paymentIframe" src="${url}" frameborder="0" style="
                        width: 100% !important;
                        height: calc(100% - 100px) !important;
                        border: none !important;
                        background: white !important;
                    "></iframe>
                </div>
                
                <!-- STYLES CSS POUR ANIMATIONS -->
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideIn {
                        from { 
                            opacity: 0;
                            transform: scale(0.8) translateY(-50px);
                        }
                        to { 
                            opacity: 1;
                            transform: scale(1) translateY(0);
                        }
                    }
                    @keyframes fadeOut {
                        from { opacity: 1; }
                        to { opacity: 0; }
                    }
                    @keyframes slideOut {
                        from { 
                            opacity: 1;
                            transform: scale(1) translateY(0);
                        }
                        to { 
                            opacity: 0;
                            transform: scale(0.9) translateY(-30px);
                        }
                    }
                </style>
            `;
            
            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';
            
            console.log('✅ Modal ÉLÉGANT créé avec design Suzosky premium');
            console.log('� Dimensions: 85vw x 85vh (max 900x700px) - Taille parfaite');
            console.log('✨ Animations et effets premium ajoutés');
            
            // VÉRIFIER QUE TOUT EST PARFAIT
            setTimeout(() => {
                const closeBtn = modal.querySelector('button');
                if (closeBtn) {
                    console.log('✅ Bouton fermeture ÉLÉGANT trouvé et fonctionnel');
                } else {
                    console.log('❌ Erreur: Bouton fermeture manquant');
                }
            }, 100);
        };
        
        window.closePaymentModal = function() {
            console.log('🎨 Fermeture ÉLÉGANTE du modal de paiement');
            
            // Marquer qu'on ferme le modal pour éviter les alertes beforeunload
            window.closingPaymentModal = true;
            
            // RESTAURER LE SCROLL IMMÉDIATEMENT
            document.body.style.overflow = 'auto';
            document.body.style.overflowX = 'auto';
            document.body.style.overflowY = 'auto';
            console.log('🔄 Scroll restauré immédiatement');
            
            const modal = document.getElementById('paymentModal');
            const iframe = document.getElementById('paymentIframe');
            
            if (modal) {
                // Animation de fermeture élégante
                modal.style.animation = 'fadeOut 0.2s ease-out';
                const content = modal.querySelector('div');
                if (content) {
                    content.style.animation = 'slideOut 0.2s ease-in';
                }
                
                // Supprimer après animation
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.remove();
                    }
                    
                    // DOUBLE VÉRIFICATION DU SCROLL
                    document.body.style.overflow = 'auto';
                    
                    // Retirer le flag après fermeture
                    window.closingPaymentModal = false;
                    
                    // RÉAFFICHER LA SECTION MODES DE PAIEMENT après fermeture
                    const paymentMethods = document.getElementById('paymentMethods');
                    if (paymentMethods) {
                        paymentMethods.style.display = 'block';
                        console.log('💳 Section modes de paiement réaffichée après fermeture modal');
                    }
                    
                    // Réactiver la vérification des champs
                    if (typeof checkFormCompleteness === 'function') {
                        checkFormCompleteness();
                        console.log('🔄 Vérification formulaire réactivée');
                    }
                    
                    console.log('✅ Modal fermé avec élégance - Retour au formulaire');
                }, 200);
                
            } else {
                // Si pas de modal, restaurer quand même le scroll
                document.body.style.overflow = 'auto';
                window.closingPaymentModal = false;
                console.log('ℹ️ Aucun modal à fermer - Scroll restauré');
            }
        };
        
        // FONCTION DE SECOURS POUR RESTAURER LE SCROLL
        window.forceRestoreScroll = function() {
            console.log('🚨 FORCE: Restauration du scroll');
            document.body.style.overflow = 'auto';
            document.body.style.overflowX = 'auto';
            document.body.style.overflowY = 'auto';
            
            // Supprimer tous les modals qui traînent
            const modals = document.querySelectorAll('#paymentModal, [id*="modal"], .modal-backdrop');
            modals.forEach(modal => modal.remove());
            
            console.log('✅ Scroll forcé à être restauré');
        };
        
        // AUTO-VÉRIFICATION AU CHARGEMENT
        setTimeout(() => {
            if (document.body.style.overflow === 'hidden' && !document.getElementById('paymentModal')) {
                console.log('🔧 AUTO-FIX: Scroll bloqué détecté, restauration...');
                window.forceRestoreScroll();
            }
        }, 1000);
    }
    
    // Diagnostic: vérifier les fonctions modales au chargement
    console.log('🔧 js_form_handling.php chargé');
    console.log('🔧 État des fonctions modales au chargement:', {
        'typeof showPaymentModal': typeof window.showPaymentModal,
        'typeof closePaymentModal': typeof window.closePaymentModal
    });
    
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
                        console.log('🎯 Appel direct de showPaymentModal');
                        
                        // Appel direct simple
                        if (typeof window.showPaymentModal === 'function') {
                            window.showPaymentModal(data.payment_url);
                        } else {
                            console.error('❌ showPaymentModal TOUJOURS non disponible - fallback');
                            window.open(data.payment_url, '_blank', 'width=800,height=600,resizable=yes,scrollbars=yes');
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

    // Attach to form submit AND submit button
    if (form) form.addEventListener('submit', processOrder);
    const btn = document.querySelector('.submit-btn');
    if (btn) btn.addEventListener('click', processOrder);
    });
    </script>
