<?php
// sections/js_form_handling.php - Fonctions de gestion des formulaires et validation
?>

<script>
    // Initialize currentClient flag from PHP session
    window.currentClient = <?php echo !empty($_SESSION['client_id']) ? 'true' : 'false'; ?>;

    console.log('🚀 js_form_handling.php - Chargement des fonctions modales');

    if (typeof window.__cashFlowEnhanced === 'undefined') {
        window.__cashFlowEnhanced = true;
        console.info('⚙️  __cashFlowEnhanced absent, valeur par défaut activée.');
    }
    if (typeof window.__cashFlowEnhancedCash === 'undefined') {
        window.__cashFlowEnhancedCash = true;
        console.info('⚙️  __cashFlowEnhancedCash absent, activation automatique du mode enrichi.');
    }

    // Fonction showPaymentModal
    if (typeof window.showPaymentModal !== 'function') {
        window.showPaymentModal = function(paymentUrl) {
            console.log('🚀 showPaymentModal appelée avec URL:', paymentUrl);
            
            if (!paymentUrl || typeof paymentUrl !== 'string') {
                console.error('❌ URL de paiement invalide');
                return;
            }

            // Supprimer modal existant si présent
            const existingModal = document.getElementById('payment-modal');
            if (existingModal) {
                existingModal.remove();
                console.log('🗑️ Modal existant supprimé');
            }

            // Créer le modal avec design Suzosky élégant (ANCIEN VERSION QUI MARCHAIT)
            const modal = document.createElement('div');
            modal.id = 'payment-modal';
            modal.innerHTML = `
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100vw;
                    height: 100vh;
                    background: rgba(26, 26, 46, 0.95);
                    backdrop-filter: blur(15px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 999999;
                    animation: fadeIn 0.3s ease-out;
                ">
                    <div style="
                        background: linear-gradient(135deg, rgba(212, 168, 83, 0.1), rgba(26, 26, 46, 0.9));
                        border: 2px solid #D4A853;
                        border-radius: 20px;
                        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.8), 0 0 30px rgba(212, 168, 83, 0.3);
                        width: 85vw;
                        height: 85vh;
                        max-width: 900px;
                        max-height: 700px;
                        position: relative;
                        overflow: hidden;
                        animation: modalSlideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                    ">
                        <div style="
                            background: linear-gradient(90deg, #D4A853, #E94560);
                            padding: 20px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            border-radius: 18px 18px 0 0;
                        ">
                            <h3 style="
                                color: white;
                                margin: 0;
                                font-size: 1.4rem;
                                font-weight: 600;
                                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                            ">💳 Paiement Sécurisé Coursier Suzosky</h3>
                            <button onclick="closePaymentModal()" style="
                                background: rgba(255,255,255,0.2);
                                border: none;
                                color: white;
                                font-size: 1.8rem;
                                width: 40px;
                                height: 40px;
                                border-radius: 50%;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            " onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='rotate(90deg)'" 
                               onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='rotate(0deg)'">×</button>
                        </div>
                        <iframe 
                            src="${paymentUrl}" 
                            style="
                                width: 100%;
                                height: calc(100% - 80px);
                                border: none;
                                background: white;
                            "
                            title="Interface de paiement CinetPay"
                            allow="payment"
                        ></iframe>
                    </div>
                </div>
                
                <style>
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes modalSlideIn {
                    from { 
                        opacity: 0;
                        transform: scale(0.7) translateY(-50px);
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
                </style>
            `;

            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';
            
            console.log('✅ Modal CinetPay créé avec design Suzosky');
        };
    }

    // Fonction closePaymentModal
    if (typeof window.closePaymentModal !== 'function') {
        window.closePaymentModal = function() {
            console.log('🚪 closePaymentModal appelée');
            
            const modal = document.getElementById('payment-modal');
            if (modal) {
                modal.style.animation = 'fadeOut 0.3s ease-in';
                
                setTimeout(() => {
                    modal.remove();
                    document.body.style.overflow = '';
                    console.log('✅ Modal fermé et scroll restauré');
                }, 300);
            }
        };
    }

    console.log('✅ Fonctions modales définies:', {
        'showPaymentModal': typeof window.showPaymentModal,
        'closePaymentModal': typeof window.closePaymentModal
    });

    // DOM Content Loaded pour les événements du formulaire
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('orderForm');
        if (typeof window.orderFormDirty === 'undefined') {
            window.orderFormDirty = false;
        }

        const markOrderFormDirty = () => {
            window.orderFormDirty = true;
            window.skipBeforeUnload = false;
            window._skipBeforeUnloadCheck = false;
        };
        
        function processOrder(e) {
            console.log('🛵 processOrder appelée, currentClient=', window.currentClient);
            if (window.__orderFlowHandled) {
                console.log('⏭️ processOrder ignorée (flux amélioré déjà pris en charge)');
                window.__orderFlowHandled = false;
                e.preventDefault();
                return;
            }

            const paymentMethod = (document.querySelector('input[name="paymentMethod"]:checked') || { value: 'cash' }).value;
            const delegatedToEnhancedCash = paymentMethod === 'cash' && window.__cashFlowEnhancedCash;
            if (!delegatedToEnhancedCash) {
                e.preventDefault();
                window.skipBeforeUnload = false;
                window._skipBeforeUnloadCheck = false;
            }
            
            // If the client is not logged in, defer UI handling to FCM-driven status if available.
            // The FCM layer should call `window.setFCMCoursierStatus(isAvailable, message)` to control
            // whether the order form is visible. If FCM status is present we respect it; otherwise
            // fall back to the previous behaviour (prompt for login).
            if (!window.currentClient) {
                if (typeof window.fcmCoursierAvailable !== 'undefined') {
                    if (!window.fcmCoursierAvailable) {
                        // Let FCM show a "no coursier available" message (or fallback to alert)
                        if (typeof window.showCoursierUnavailableMessage === 'function') {
                            window.showCoursierUnavailableMessage(window.fcmCoursierMessage || 'Aucun coursier disponible pour le moment.');
                        } else {
                            alert(window.fcmCoursierMessage || 'Aucun coursier disponible pour le moment.');
                        }
                        return;
                    }
                    // If FCM says coursier available, allow the flow to continue (no login modal forced here)
                } else {
                    // fallback: try to open connexion modal as before
                    const openLink = document.getElementById('openConnexionLink') || document.getElementById('openConnexionLinkMobile');
                    if (openLink) {
                        openLink.click();
                        return;
                    } else if (typeof window.openConnexionModal === 'function') {
                        window.openConnexionModal();
                        return;
                    } else if (typeof window.showModal === 'function' && document.getElementById('connexionModal')) {
                        showModal('connexionModal');
                        return;
                    } else {
                        alert('Veuillez vous connecter pour commander.');
                        return;
                    }
                }
            }
            
            if (!validateForm()) return;
            
            // Remove hold on enhanced cash flow to allow cash payment submission
            // if (delegatedToEnhancedCash) {
            //     console.log('⏭️ Cash géré par le flux amélioré (timeline client)');
            //     return;
            // }
            
            if (paymentMethod === 'cash') {
                window.orderFormDirty = false;
                window.skipBeforeUnload = true;
                window._skipBeforeUnloadCheck = true;
                form.submit();
            } else {
                const formData = new FormData(form);
                const orderNumber = 'SZK' + Date.now();
                const priceElement = document.getElementById('total-price');
                const priceText = priceElement ? priceElement.textContent : '';
                const amount = priceText.match(/(\d+)/)?.[1] || '1500';
                
                formData.append('order_number', orderNumber);
                formData.append('amount', amount);
                
                console.log('💳 Initiation paiement CinetPay:', {orderNumber, amount});
                
                const endpoint = `${ROOT_PATH}/api/initiate_order_payment.php`;
                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then data => {
                    if (data.success && data.payment_url) {
                        console.log('✅ Paiement initié, ouverture modal');
                        window.orderFormDirty = false;
                        window.skipBeforeUnload = true;
                        window._skipBeforeUnloadCheck = true;
                        window.showPaymentModal(data.payment_url);
                    } else {
                        console.error('❌ Erreur paiement:', data);
                        alert('Erreur lors de l\'initialisation du paiement: ' + (data.message || 'Erreur inconnue'));
                    }
                })
                .catch(err => {
                    console.error('❌ Erreur fetch:', err);
                    alert('Impossible d\'initier le paiement.');
                });
            }
        }
        
        function validateForm() {
            const departure = document.getElementById('departure');
            const destination = document.getElementById('destination');
            
            if (!departure || !departure.value.trim()) {
                alert('Veuillez saisir l\'adresse de départ');
                return false;
            }
            
            if (!destination || !destination.value.trim()) {
                alert('Veuillez saisir l\'adresse de destination');
                return false;
            }
            
            return true;
        }

        // Attacher les événements
        window.processOrder = processOrder;
        
        if (form) {
            form.addEventListener('submit', processOrder);
            const trackedFields = form.querySelectorAll('input, select, textarea');
            trackedFields.forEach(field => {
                field.addEventListener('input', markOrderFormDirty);
                field.addEventListener('change', markOrderFormDirty);
            });
        }
        
        const submitBtn = document.querySelector('.submit-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', processOrder);
        }
        
        console.log('✅ Événements du formulaire attachés');
    });
    
    console.log('🏁 js_form_handling.php chargé complètement');
</script>