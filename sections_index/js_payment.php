<?php
// sections/js_payment.php - Système de paiement et intégration CinetPay
?>
    <script>
    // Variables globales de paiement
    let currentOrderData = null;
    let paymentInProgress = false;
    let paymentTimeout = null;

    // Base path dynamique (empêche les 404 quand l'app n'est pas à la racine)
    const __BASE_PATH = (window.ROOT_PATH || '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>').replace(/\/$/, '');
    function apiPath(rel) { return __BASE_PATH + rel; }

    // Configuration CinetPay (URLs relatives corrigées)
    const CINETPAY_CONFIG = {
        apikey: 'YOUR_CINETPAY_API_KEY', // À configurer
        site_id: 'YOUR_SITE_ID', // À configurer
        notify_url: window.location.origin + apiPath('/cinetpay/payment_notify.php'),
        return_url: window.location.origin + apiPath('/cinetpay/payment_return.php'),
        currency: 'XOF',
        lang: 'fr'
    };
    
    // Afficher le modal de paiement (RENOMMÉ pour éviter conflit avec modals.php)
    function showPaymentModalOld(orderData) {
        currentOrderData = orderData;
        
        // Calculer le prix final
        const basePrice = calculateDynamicPrice(orderData.distance, orderData.priority);
        const finalPrice = Math.round(basePrice);
        
        // Mettre à jour les informations dans le modal
        document.getElementById('payment-departure').textContent = orderData.departure;
        document.getElementById('payment-destination').textContent = orderData.destination;
        document.getElementById('payment-distance').textContent = orderData.distance || 'Calcul en cours...';
        document.getElementById('payment-priority').textContent = 
            orderData.priority === 'express' ? 'Express (+50%)' : 
            orderData.priority === 'urgent' ? 'Urgent (+100%)' : 'Normale';
        document.getElementById('payment-amount').textContent = finalPrice + ' FCFA';
        
        // Générer l'ID de transaction
        const transactionId = generateTransactionId();
        currentOrderData.transaction_id = transactionId;
        
        showModal('paymentModal');
    }
    
    // Générer un ID de transaction unique
    function generateTransactionId() {
        const timestamp = Date.now();
        const random = Math.floor(Math.random() * 10000);
        return `TXN_${timestamp}_${random}`;
    }
    
    // Traiter le paiement selon la méthode
    function processPayment() {
        if (paymentInProgress) return;
        
        const paymentMethod = currentOrderData.payment_method;
        
        if (paymentMethod === 'mobile-money') {
            processMobileMoneyPayment();
        } else if (paymentMethod === 'especes') {
            processCashPayment();
        }
    }
    
    // Traitement paiement Mobile Money
    function processMobileMoneyPayment() {
        paymentInProgress = true;
        showPaymentLoading(true);
        
        const amount = parseInt(document.getElementById('payment-amount').textContent.replace(/[^\d]/g, ''));
        
        // Préparer les données pour CinetPay
        const paymentData = {
            transaction_id: currentOrderData.transaction_id,
            amount: amount,
            currency: CINETPAY_CONFIG.currency,
            customer_name: currentUser.name,
            customer_email: currentUser.email,
            customer_phone: currentOrderData.phone,
            description: `Livraison ${currentOrderData.departure} → ${currentOrderData.destination}`,
            metadata: JSON.stringify({
                order_data: currentOrderData,
                user_id: currentUser.id
            })
        };
        
        // Appel à l'API de paiement
    fetch(apiPath('/api/initiate_order_payment.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(paymentData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.payment_url) {
                // Rediriger vers la page de paiement CinetPay
                window.location.href = data.payment_url;
            } else {
                throw new Error(data.message || 'Erreur lors de l\'initialisation du paiement');
            }
        })
        .catch(error => {
            console.error('Erreur paiement:', error);
            showMessage('Erreur lors du paiement: ' + error.message, 'error');
            paymentInProgress = false;
            showPaymentLoading(false);
        });
    }
    
    // Traitement paiement espèces
    function processCashPayment() {
        paymentInProgress = true;
        showPaymentLoading(true);
        
        // Simuler la création de commande pour paiement espèces
        setTimeout(() => {
            const orderNumber = generateOrderNumber();
            
            // Sauvegarder la commande
            saveOrder({
                ...currentOrderData,
                order_number: orderNumber,
                payment_status: 'pending_cash',
                status: 'confirmed'
            });
            
            // Afficher le succès
            showPaymentSuccess({
                order_number: orderNumber,
                payment_method: 'Espèces à la livraison',
                amount: parseInt(document.getElementById('payment-amount').textContent.replace(/[^\d]/g, ''))
            });
            
            paymentInProgress = false;
            showPaymentLoading(false);
        }, 2000);
    }
    
    // Générer un numéro de commande
    function generateOrderNumber() {
        const date = new Date();
        const dateStr = date.getFullYear().toString().substr(-2) + 
                       (date.getMonth() + 1).toString().padStart(2, '0') + 
                       date.getDate().toString().padStart(2, '0');
        const timeStr = date.getHours().toString().padStart(2, '0') + 
                       date.getMinutes().toString().padStart(2, '0');
        const random = Math.floor(Math.random() * 100).toString().padStart(2, '0');
        
        return `CMD${dateStr}${timeStr}${random}`;
    }
    
    // Sauvegarder la commande
    function saveOrder(orderData) {
        const orders = JSON.parse(localStorage.getItem('userOrders') || '[]');
        orders.push({
            ...orderData,
            created_at: new Date().toISOString()
        });
        localStorage.setItem('userOrders', JSON.stringify(orders));
        
        // Envoyer aussi au serveur
    fetch(apiPath('/api/submit_order.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Commande sauvegardée:', data);
        })
        .catch(error => {
            console.error('Erreur sauvegarde commande:', error);
        });
    }
    
    // Afficher le succès du paiement
    function showPaymentSuccess(paymentInfo) {
        closeModal('paymentModal');
        
        // Réinitialiser le formulaire
        resetOrderForm();
        
        // Afficher modal de succès
        document.getElementById('success-order-number').textContent = paymentInfo.order_number;
        document.getElementById('success-payment-method').textContent = paymentInfo.payment_method;
        document.getElementById('success-amount').textContent = paymentInfo.amount;
        
        showModal('successModal');
        
        // Notification
        showNotification('Commande confirmée !', {
            body: `Votre commande ${paymentInfo.order_number} a été enregistrée.`,
            icon: apiPath('/assets/icon-192.svg')
        });
        
        // Envoyer notification par email/SMS
        sendOrderNotification(paymentInfo);
    }
    
    // Envoyer notification de commande
    function sendOrderNotification(paymentInfo) {
        const notificationData = {
            user_id: currentUser.id,
            order_number: paymentInfo.order_number,
            phone: currentOrderData.phone,
            email: currentUser.email,
            departure: currentOrderData.departure,
            destination: currentOrderData.destination
        };
        
    fetch(apiPath('/api/send_order_notification.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(notificationData)
        })
        .catch(error => {
            console.error('Erreur notification:', error);
        });
    }
    
    // Gestion du retour de paiement CinetPay
    function handlePaymentReturn() {
        const urlParams = new URLSearchParams(window.location.search);
        const transactionId = urlParams.get('transaction_id');
        const status = urlParams.get('status');
        
        if (transactionId) {
            if (status === 'ACCEPTED') {
                // Paiement réussi
                verifyPayment(transactionId);
            } else {
                // Paiement échoué
                showMessage('Paiement annulé ou échoué', 'error');
                showModal('paymentModal');
            }
        }
    }
    
    // Vérifier le statut du paiement
    function verifyPayment(transactionId) {
        showPaymentLoading(true, 'Vérification du paiement...');
        
    fetch(apiPath('/cinetpay/payment_return.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                transaction_id: transactionId,
                action: 'verify'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPaymentSuccess({
                    order_number: data.order_number,
                    payment_method: 'Mobile Money',
                    amount: data.amount + ' FCFA'
                });
            } else {
                showMessage('Erreur lors de la vérification du paiement', 'error');
            }
            showPaymentLoading(false);
        })
        .catch(error => {
            console.error('Erreur vérification:', error);
            showMessage('Erreur lors de la vérification du paiement', 'error');
            showPaymentLoading(false);
        });
    }
    
    // Afficher/masquer le loading du paiement
    function showPaymentLoading(show, message = 'Traitement du paiement...') {
        const loadingDiv = document.getElementById('paymentLoading');
        const paymentForm = document.getElementById('paymentForm');
        
        if (show) {
            if (!loadingDiv) {
                const loading = document.createElement('div');
                loading.id = 'paymentLoading';
                loading.innerHTML = `
                    <div class="payment-loading">
                        <div class="loading-spinner"></div>
                        <p>${message}</p>
                    </div>
                `;
                document.getElementById('paymentModal').appendChild(loading);
            }
            loadingDiv.style.display = 'flex';
            paymentForm.style.display = 'none';
        } else {
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
            paymentForm.style.display = 'block';
        }
    }
    
    // Annuler le paiement
    function cancelPayment() {
        if (paymentInProgress) {
            if (!confirm('Un paiement est en cours. Êtes-vous sûr de vouloir annuler ?')) {
                return;
            }
        }
        
        paymentInProgress = false;
        currentOrderData = null;
        
        if (paymentTimeout) {
            clearTimeout(paymentTimeout);
        }
        
        closeModal('paymentModal');
        showPaymentLoading(false);
    }
    
    // Fonction pour tester le paiement (développement)
    function testPayment() {
        if (typeof currentOrderData === 'undefined' || !currentOrderData) {
            showMessage('Aucune commande en cours', 'warning');
            return;
        }
        
        // Simuler un paiement réussi
        setTimeout(() => {
            const orderNumber = generateOrderNumber();
            showPaymentSuccess({
                order_number: orderNumber,
                payment_method: 'Test Payment',
                amount: '2500 FCFA'
            });
        }, 2000);
    }
    
    // Gestionnaire pour les erreurs de paiement
    function handlePaymentError(error) {
        console.error('Erreur de paiement:', error);
        
        paymentInProgress = false;
        showPaymentLoading(false);
        
        let errorMessage = 'Une erreur est survenue lors du paiement.';
        
        if (error.message) {
            errorMessage = error.message;
        } else if (typeof error === 'string') {
            errorMessage = error;
        }
        
        showMessage(errorMessage, 'error');
    }
    
    // Initialisation des événements de paiement
    function initializePaymentEvents() {
        // Vérifier si on revient d'un paiement
        if (window.location.search.includes('transaction_id')) {
            handlePaymentReturn();
        }
        
        // Configurer les timeouts de paiement
        paymentTimeout = setTimeout(() => {
            if (paymentInProgress) {
                handlePaymentError('Délai d\'attente du paiement dépassé');
            }
        }, 300000); // 5 minutes
    }
    
    // Initialiser les événements de paiement au chargement
    document.addEventListener('DOMContentLoaded', function() {
        initializePaymentEvents();
    });
    
    console.log('💳 js_payment.php chargé');
    </script>
