<?php
// sections/js_form_handling.php - Fonctions de gestion des formulaires et validation
?>

<script>
    // Initialize currentClient flag from PHP session
    window.currentClient = <?php echo (
        !empty($_SESSION['client_id']) ||
        !empty($_SESSION['client_email']) ||
        !empty($_SESSION['client_telephone'])
    ) ? 'true' : 'false'; ?>;

    console.log('🚀 js_form_handling.php - Chargement des fonctions modales');

    // FCM-driven control: expose a function for FCM code to    // Fonction closePaymentModal
    if (typeof window.closePaymentModal !== 'function') {
        window.closePaymentModal = function(forceCancel = false) {
            console.log('🚺 closePaymentModal appelée, forceCancel:', forceCancel);
            
            const modal = document.getElementById('payment-modal');
            if (modal) {
                modal.style.animation = 'fadeOut 0.3s ease-in';
                
                setTimeout(() => {
                    modal.remove();
                    document.body.style.overflow = '';
                    console.log('✅ Modal fermé et scroll restauré');
                    
                    // Si fermé manuellement sans succès, notifier échec
                    if (forceCancel && typeof window._paymentCompleteCallback === 'function') {
                        window._paymentCompleteCallback(false);
                        delete window._paymentCompleteCallback;
                    }
                }, 300);
            }
        };
    }
    
    // Fonction helper pour gérer la fermeture du paiement
    if (typeof window.handlePaymentClose !== 'function') {
        window.handlePaymentClose = function(success) {
            console.log('🔒 handlePaymentClose appelée, succès:', success);
            closePaymentModal(!success);
            if (typeof window._paymentCompleteCallback === 'function') {
                window._paymentCompleteCallback(success);
                delete window._paymentCompleteCallback;
            }
        };
    }oursiers are available.
    // Usage from FCM layer: window.setFCMCoursierStatus(true|false, optionalMessage)
    if (typeof window.setFCMCoursierStatus !== 'function') {
        window.fcmCoursierAvailable = undefined;
        window.fcmCoursierMessage = '';

        const defaultLockDelay = (typeof window.COURSIER_LOCK_DELAY_MS === 'number' && window.COURSIER_LOCK_DELAY_MS > 0)
            ? window.COURSIER_LOCK_DELAY_MS
            : 60000;

        const formatSecondsAgo = (seconds) => {
            if (!Number.isFinite(seconds)) return '';
            const totalSeconds = Math.max(0, Math.round(seconds));
            if (totalSeconds < 60) {
                return `Dernier coursier actif il y a ${totalSeconds}s.`;
            }
            const totalMinutes = Math.floor(totalSeconds / 60);
            const remainingSeconds = totalSeconds % 60;
            if (totalMinutes < 60) {
                return remainingSeconds > 0
                    ? `Dernier coursier actif il y a ${totalMinutes} min ${remainingSeconds}s.`
                    : `Dernier coursier actif il y a ${totalMinutes} min.`;
            }
            const totalHours = Math.floor(totalMinutes / 60);
            const remainingMinutes = totalMinutes % 60;
            if (totalHours < 24) {
                const parts = [`${totalHours} h`];
                if (remainingMinutes > 0) parts.push(`${remainingMinutes} min`);
                return `Dernier coursier actif il y a ${parts.join(' ')}.`;
            }
            const totalDays = Math.floor(totalHours / 24);
            const remHours = totalHours % 24;
            const parts = [`${totalDays} j`];
            if (remHours > 0) parts.push(`${remHours} h`);
            if (remainingMinutes > 0) parts.push(`${remainingMinutes} min`);
            return `Dernier coursier actif il y a ${parts.join(' ')}.`;
        };

        const initialAvailability = (typeof window.initialCoursierAvailability === 'undefined')
            ? undefined
            : Boolean(window.initialCoursierAvailability);

        const state = window.__coursierAvailabilityState = {
            available: initialAvailability,
            lockDelayMs: defaultLockDelay,
            lockTimer: null,
            countdownInterval: null,
            countdownEndsAt: null,
            pendingMessage: '',
            isLocked: initialAvailability === undefined ? false : !initialAvailability,
            lastAvailableAt: initialAvailability ? Date.now() : null,
            meta: null,
            latestPayload: null
        };

        const getOrderFormElements = () => {
            return {
                container: document.getElementById('orderFormContainer'),
                form: document.getElementById('orderForm'),
                locker: document.getElementById('orderFormLocker'),
                lockerMessage: document.getElementById('orderFormLockerMessage'),
                lockerMeta: document.getElementById('orderFormLockerMeta'),
                countdown: document.getElementById('orderFormLockCountdown'),
                countdownValue: document.getElementById('orderFormLockCountdownValue')
            };
        };

        const toggleFormDisabled = (disabled) => {
            const { form } = getOrderFormElements();
            if (!form) return;
            const fields = form.querySelectorAll('input, select, textarea, button');
            fields.forEach((field) => {
                if (disabled) {
                    field.setAttribute('disabled', 'disabled');
                } else {
                    field.removeAttribute('disabled');
                }
            });
        };

        const stopCountdown = () => {
            if (state.countdownInterval) {
                clearInterval(state.countdownInterval);
                state.countdownInterval = null;
            }
            state.countdownEndsAt = null;
            const { countdown, countdownValue } = getOrderFormElements();
            if (countdown) {
                countdown.classList.remove('order-form-warning--visible');
            }
            if (countdownValue) {
                const baseSeconds = Math.ceil((state.lockDelayMs || defaultLockDelay) / 1000);
                countdownValue.textContent = String(baseSeconds);
            }
        };

        const updateCountdown = () => {
            const { countdownValue } = getOrderFormElements();
            if (!countdownValue) return;
            if (!state.countdownEndsAt) {
                const baseSeconds = Math.ceil((state.lockDelayMs || defaultLockDelay) / 1000);
                countdownValue.textContent = String(baseSeconds);
                return;
            }
            const remainingMs = state.countdownEndsAt - Date.now();
            if (remainingMs <= 0) {
                countdownValue.textContent = '0';
                stopCountdown();
                return;
            }
            const seconds = Math.max(0, Math.ceil(remainingMs / 1000));
            countdownValue.textContent = String(seconds);
        };

        const startCountdown = (delayMs) => {
            const { countdown } = getOrderFormElements();
            state.countdownEndsAt = Date.now() + delayMs;
            if (!countdown) {
                if (state.countdownInterval) {
                    clearInterval(state.countdownInterval);
                    state.countdownInterval = null;
                }
                return;
            }
            countdown.classList.add('order-form-warning--visible');
            updateCountdown();
            if (state.countdownInterval) {
                clearInterval(state.countdownInterval);
            }
            state.countdownInterval = setInterval(updateCountdown, 1000);
        };

        const updateLockerMeta = () => {
            const { lockerMeta } = getOrderFormElements();
            if (!lockerMeta) return;
            let seconds = null;
            if (state.meta && typeof state.meta.secondsSinceLastActive === 'number') {
                const base = state.meta.secondsSinceLastActive;
                const offset = state.meta.receivedAt ? Math.max(0, Math.round((Date.now() - state.meta.receivedAt) / 1000)) : 0;
                seconds = base + offset;
            } else if (state.lastAvailableAt) {
                seconds = Math.max(0, Math.round((Date.now() - state.lastAvailableAt) / 1000));
            }

            if (!Number.isFinite(seconds) || seconds === null) {
                lockerMeta.style.display = 'none';
                lockerMeta.textContent = '';
                return;
            }

            lockerMeta.style.display = '';
            lockerMeta.textContent = formatSecondsAgo(seconds);
        };

        const lockOrderForm = (message) => {
            stopCountdown();
            const { container, form, locker, lockerMessage } = getOrderFormElements();
            if (container) {
                container.classList.add('order-form--locked');
            }
            if (form) {
                form.classList.add('order-form-hidden');
            }
            toggleFormDisabled(true);
            if (locker) {
                locker.classList.add('order-form-locker--visible');
            }
            if (lockerMessage) {
                lockerMessage.innerHTML = (message || '').replace(/\n/g, '<br>');
            }
            updateLockerMeta();
            state.isLocked = true;
        };

        const unlockOrderForm = (opts = {}) => {
            const { container, form, locker, lockerMeta } = getOrderFormElements();
            if (container) {
                container.classList.remove('order-form--locked');
            }
            if (form) {
                form.classList.remove('order-form-hidden');
            }
            toggleFormDisabled(false);
            if (locker) {
                locker.classList.remove('order-form-locker--visible');
            }
            if (lockerMeta) {
                lockerMeta.style.display = 'none';
                lockerMeta.textContent = '';
            }
            state.isLocked = false;
            if (!opts.preserveMeta) {
                state.meta = null;
            }
            if (!opts.preserveMessage) {
                state.pendingMessage = '';
            }
        };

        const updateCoursierBanner = (available, text) => {
            try {
                const banner = document.getElementById('coursier-unavailable-banner');
                if (!available) {
                    const messageText = (text && String(text).trim() !== '')
                        ? String(text)
                        : (window.COMMERCIAL_FALLBACK_MESSAGE || 'Aucun coursier disponible pour le moment.');
                    if (!banner) {
                        const b = document.createElement('div');
                        b.id = 'coursier-unavailable-banner';
                        b.style = 'position:fixed;top:0;left:0;right:0;z-index:99999;padding:10px;text-align:center;background:linear-gradient(90deg,#D9534F,#F0AD4E);color:#fff;font-weight:700;';
                        b.textContent = messageText;
                        document.body.appendChild(b);
                    } else {
                        banner.textContent = messageText;
                        banner.style.display = '';
                    }
                } else if (banner) {
                    banner.style.display = 'none';
                }
            } catch (e) {
                console.warn('⚠️ setFCMCoursierStatus banner update failed', e);
            }
        };

        const applyCoursierPayload = (payload) => {
            if (!payload) return;
            const localOptions = payload.options ? { ...payload.options } : {};

            if (localOptions.meta && typeof localOptions.meta === 'object') {
                state.meta = { ...localOptions.meta, receivedAt: Date.now() };
            } else {
                state.meta = null;
            }

            if (typeof localOptions.lockDelayMs === 'number' && localOptions.lockDelayMs > 0) {
                state.lockDelayMs = localOptions.lockDelayMs;
            } else if (typeof window.COURSIER_LOCK_DELAY_MS === 'number' && window.COURSIER_LOCK_DELAY_MS > 0) {
                state.lockDelayMs = window.COURSIER_LOCK_DELAY_MS;
            }

            window.fcmCoursierAvailable = payload.available;
            window.fcmCoursierMessage = payload.message;
            updateCoursierBanner(payload.available, payload.message);

            console.log('📡 FCM coursier status set:', window.fcmCoursierAvailable, window.fcmCoursierMessage, localOptions);

            const forceImmediate = Boolean(localOptions.forceImmediate || localOptions.immediate);

            if (payload.available) {
                state.available = true;
                state.lastAvailableAt = Date.now();
                if (state.lockTimer) {
                    clearTimeout(state.lockTimer);
                    state.lockTimer = null;
                }
                stopCountdown();

                if (!localOptions.preventUnlock) {
                    const { container, form } = getOrderFormElements();
                    const formHidden = form && form.classList.contains('order-form-hidden');
                    const containerLocked = container && container.classList.contains('order-form--locked');
                    if (state.isLocked || formHidden || containerLocked) {
                        unlockOrderForm();
                    } else {
                        toggleFormDisabled(false);
                    }
                }
                state.isLocked = false;
                state.pendingMessage = '';
                return;
            }

            state.available = false;
            const effectiveMessage = payload.message;

            if (forceImmediate) {
                if (state.lockTimer) {
                    clearTimeout(state.lockTimer);
                    state.lockTimer = null;
                }
                lockOrderForm(effectiveMessage);
                return;
            }

            if (state.isLocked) {
                lockOrderForm(effectiveMessage);
                return;
            }

            const delay = (typeof localOptions.lockDelayMs === 'number' && localOptions.lockDelayMs > 0)
                ? localOptions.lockDelayMs
                : state.lockDelayMs;

            state.pendingMessage = effectiveMessage;

            if (!state.lockTimer) {
                startCountdown(delay);
                state.lockTimer = setTimeout(() => {
                    state.lockTimer = null;
                    lockOrderForm(state.pendingMessage || effectiveMessage);
                    state.pendingMessage = '';
                }, delay);
            }
        };

        window.setFCMCoursierStatus = function(isAvailable, message, options) {
            const rawOptions = options && typeof options === 'object' ? { ...options } : {};
            if (rawOptions.meta && typeof rawOptions.meta === 'object') {
                rawOptions.meta = { ...rawOptions.meta };
            }

            const normalizedMessage = (message && String(message).trim() !== '')
                ? String(message)
                : (window.COMMERCIAL_FALLBACK_MESSAGE || window.initialCoursierMessage || 'Nos coursiers sont momentanément indisponibles.');

            const payload = {
                available: Boolean(isAvailable),
                message: normalizedMessage,
                options: rawOptions
            };

            state.latestPayload = payload;

            if (!window.currentClient && !rawOptions.applyForGuests) {
                console.log('👤 Visiteur non connecté : formulaire maintenu ouvert, statut FCM mémorisé.');
                state.available = payload.available;
                state.pendingMessage = normalizedMessage;
                if (rawOptions.meta && typeof rawOptions.meta === 'object') {
                    state.meta = { ...rawOptions.meta, receivedAt: Date.now() };
                } else {
                    state.meta = null;
                }
                if (state.lockTimer) {
                    clearTimeout(state.lockTimer);
                    state.lockTimer = null;
                }
                stopCountdown();
                updateCoursierBanner(payload.available, payload.message);
                unlockOrderForm({ preserveMeta: true, preserveMessage: true });
                return;
            }

            applyCoursierPayload(payload);
        };

        window.refreshCoursierAvailabilityForClient = function() {
            if (!window.currentClient) return;
            if (state.latestPayload) {
                applyCoursierPayload(state.latestPayload);
            } else if (state.available === false) {
                lockOrderForm(state.pendingMessage || window.COMMERCIAL_FALLBACK_MESSAGE);
            }
        };

        window.forceCoursierAvailabilityForGuests = function() {
            if (window.currentClient) return;
            if (state.lockTimer) {
                clearTimeout(state.lockTimer);
                state.lockTimer = null;
            }
            stopCountdown();
            unlockOrderForm({ preserveMeta: true, preserveMessage: true });
            updateCoursierBanner(state.available ?? true, state.pendingMessage || window.initialCoursierMessage);
        };

        window.showCoursierUnavailableMessage = function(msg) {
            window.setFCMCoursierStatus(false, msg, { forceImmediate: true });
        };
    }

    if (typeof window.__cashFlowEnhanced === 'undefined') {
        window.__cashFlowEnhanced = true;
        console.info('⚙️  __cashFlowEnhanced absent, valeur par défaut activée.');
    }
    if (typeof window.__cashFlowEnhancedCash === 'undefined') {
        window.__cashFlowEnhancedCash = true;
        console.info('⚙️  __cashFlowEnhancedCash absent, activation automatique du mode enrichi.');
    }

    // Fonction showPaymentModal avec callback de succès/échec
    if (typeof window.showPaymentModal !== 'function') {
        window.showPaymentModal = function(paymentUrl, onComplete) {
            console.log('🚀 showPaymentModal appelée avec URL:', paymentUrl);
            
            // Callback par défaut si non fourni
            if (typeof onComplete !== 'function') {
                onComplete = function(success) {
                    console.log('⚠️ Aucun callback fourni, succès:', success);
                };
            }
            
            // Stocker le callback globalement
            window._paymentCompleteCallback = onComplete;
            
            if (!paymentUrl || typeof paymentUrl !== 'string') {
                console.error('❌ URL de paiement invalide');
                onComplete(false);
                return;
            }

            // Supprimer modal existant si présent
            const existingModal = document.getElementById('payment-modal');
            if (existingModal) {
                existingModal.remove();
                console.log('🗑️ Modal existant supprimé');
            }
            
            // Listener pour détecter le retour CinetPay
            window.addEventListener('message', function paymentListener(event) {
                console.log('📨 Message reçu:', event.data);
                
                // Vérifier si c'est un message de CinetPay
                if (event.data && typeof event.data === 'object') {
                    if (event.data.status === 'success' || event.data.payment_status === 'ACCEPTED') {
                        console.log('✅ Paiement réussi détecté!');
                        window.removeEventListener('message', paymentListener);
                        closePaymentModal();
                        onComplete(true);
                    } else if (event.data.status === 'failed' || event.data.status === 'cancelled') {
                        console.log('❌ Paiement échoué/annulé');
                        window.removeEventListener('message', paymentListener);
                        closePaymentModal();
                        onComplete(false);
                    }
                }
            });

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
        if (!window.currentClient && typeof window.forceCoursierAvailabilityForGuests === 'function') {
            window.forceCoursierAvailabilityForGuests();
        }
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
            
            // Si le client n'est pas connecté, ouvrir la modale de connexion existante.
            if (!window.currentClient) {
                e.preventDefault();
                window.__orderFlowHandled = false;
                const trigger = document.getElementById('openConnexionLink') || document.getElementById('openConnexionLinkMobile');
                if (trigger) {
                    trigger.click();
                } else if (typeof window.openConnexionModal === 'function') {
                    window.openConnexionModal();
                } else if (typeof window.showModal === 'function' && document.getElementById('connexionModal')) {
                    showModal('connexionModal');
                } else {
                    alert('Veuillez vous connecter pour commander.');
                }
                return;
            }
            
            if (!validateForm()) return;
            
            // Remove hold on enhanced cash flow to allow cash payment submission
            // if (delegatedToEnhancedCash) {
            //     console.log('⏭️ Cash géré par le flux amélioré (timeline client)');
            //     return;
            // }
            
            if (paymentMethod === 'cash') {
                // Paiement en espèces : soumettre directement
                console.log('💵 Paiement espèces : soumission directe');
                window.orderFormDirty = false;
                window.skipBeforeUnload = true;
                window._skipBeforeUnloadCheck = true;
                form.submit();
            } else {
                // Paiement en ligne : D'ABORD ouvrir le modal CinetPay
                console.log('💳 Paiement en ligne : ouverture modal CinetPay AVANT enregistrement');
                
                const formData = new FormData(form);
                const orderNumber = 'SZK' + Date.now();
                const priceElement = document.getElementById('total-price');
                const priceText = priceElement ? priceElement.textContent : '';
                const amount = priceText.match(/(\d+)/)?.[1] || '1500';
                
                formData.append('order_number', orderNumber);
                formData.append('amount', amount);
                
                // ÉTAPE 1 : Initier le paiement CinetPay (sans enregistrer la commande)
                const endpoint = `${ROOT_PATH}/api/initiate_payment_only.php`;
                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.payment_url) {
                        console.log('✅ URL paiement générée, ouverture modal');
                        
                        // Sauvegarder les données du formulaire pour après le paiement
                        window._pendingOrderData = Object.fromEntries(formData.entries());
                        
                        // Ouvrir le modal CinetPay
                        window.showPaymentModal(data.payment_url, function(paymentSuccess) {
                            if (paymentSuccess) {
                                // ÉTAPE 2 : Paiement confirmé, MAINTENANT enregistrer la commande
                                console.log('✅ Paiement confirmé ! Enregistrement de la commande...');
                                
                                const saveEndpoint = `${ROOT_PATH}/api/create_order_after_payment.php`;
                                fetch(saveEndpoint, {
                                    method: 'POST',
                                    body: new FormData(form)
                                })
                                .then(res => res.json())
                                .then(saveData => {
                                    if (saveData.success) {
                                        console.log('✅ Commande enregistrée ! Lancement recherche coursier...');
                                        window.orderFormDirty = false;
                                        window.skipBeforeUnload = true;
                                        window._skipBeforeUnloadCheck = true;
                                        
                                        // Rediriger vers la page de suivi
                                        if (saveData.redirect_url) {
                                            window.location.href = saveData.redirect_url;
                                        } else {
                                            alert('✅ Commande validée ! Recherche de coursier en cours...');
                                            window.location.reload();
                                        }
                                    } else {
                                        console.error('❌ Erreur enregistrement commande:', saveData);
                                        alert('❌ Paiement accepté mais erreur enregistrement : ' + (saveData.message || 'Erreur inconnue'));
                                    }
                                })
                                .catch(err => {
                                    console.error('❌ Erreur enregistrement:', err);
                                    alert('❌ Paiement accepté mais erreur système. Contactez le support.');
                                });
                            } else {
                                console.log('❌ Paiement annulé ou échoué');
                                alert('❌ Paiement non complété. Vous pouvez réessayer.');
                            }
                        });
                    } else {
                        console.error('❌ Erreur initialisation paiement:', data);
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

        if (typeof window.initialCoursierAvailability !== 'undefined' && typeof window.setFCMCoursierStatus === 'function') {
            const initialMessage = window.initialCoursierMessage || window.COMMERCIAL_FALLBACK_MESSAGE || 'Nos coursiers sont momentanément indisponibles.';
            window.setFCMCoursierStatus(window.initialCoursierAvailability, initialMessage, { forceImmediate: true, origin: 'initial' });
        }
        
        console.log('✅ Événements du formulaire attachés');
    });
    
    console.log('🏁 js_form_handling.php chargé complètement');
</script>