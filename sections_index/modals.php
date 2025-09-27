<?php
// sections/modals.php - Modales de connexion/inscription et compte
?>

    <!-- Modal Connexion AJAX -->
    <div id="connexionModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="connexionModalTitle" aria-hidden="true" style="display: none;">
        <h2 id="connexionModalTitle" class="sr-only">Connexion</h2>
        <div class="modal-content">
            <span id="closeConnexionModal" class="close">&times;</span>
            <div id="connexionModalBody">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>


    <!-- Modal de paiement CinetPay - Style Suzosky Premium -->
    <div id="paymentModal" class="suzosky-payment-modal" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle" aria-hidden="true">
        <div class="payment-modal-overlay">
            <div class="payment-modal-container">
                <!-- Header Modal -->
                <div class="payment-modal-header">
                    <h3 id="paymentModalTitle" class="sr-only">Paiement Sécurisé</h3>
                    <div class="payment-header-content">
                        <div class="payment-brand">
                            <div class="payment-logo-container">
                                <div class="payment-logo-icon">💳</div>
                            </div>
                            <div class="payment-brand-text">
                                <h3>Paiement Sécurisé</h3>
                                <p>Coursier Suzosky • Protection SSL</p>
                            </div>
                        </div>
                        <button class="payment-close-btn" onclick="closePaymentModal()" aria-label="Fermer le paiement">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Corps Modal avec iframe -->
                <div class="payment-modal-body">
                    <iframe id="paymentIframe" src="" frameborder="0" class="payment-iframe"></iframe>
                </div>
                
                <!-- Footer Modal -->
                <div class="payment-modal-footer">
                    <div class="payment-security-info">
                        <div class="security-badge">
                            <span class="security-icon">🔒</span>
                            <span>Paiement 100% sécurisé</span>
                        </div>
                    </div>
                    <button class="payment-cancel-btn" onclick="closePaymentModal()">
                        Annuler le paiement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Modal Paiement Style Suzosky Premium */
    .suzosky-payment-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        background: rgba(26, 26, 46, 0.95);
        backdrop-filter: blur(20px);
        animation: modalFadeIn 0.3s ease-out;
    }

    .payment-modal-overlay {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .payment-modal-container {
        width: 100%;
        max-width: 900px;
        height: 90vh;
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        border: 1px solid rgba(212, 168, 83, 0.2);
    }

    .payment-modal-header {
        background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
        padding: 24px;
        border-bottom: 1px solid rgba(212, 168, 83, 0.2);
    }

    .payment-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .payment-brand {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .payment-logo-container {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 16px rgba(212, 168, 83, 0.3);
    }

    .payment-logo-icon {
        font-size: 24px;
        color: #1A1A2E;
    }

    .payment-brand-text h3 {
        color: #ffffff;
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        letter-spacing: 0.5px;
    }

    .payment-brand-text p {
        color: #D4A853;
        font-size: 14px;
        margin: 4px 0 0 0;
        opacity: 0.9;
    }

    .payment-close-btn {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        color: #ffffff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .payment-close-btn:hover {
        background: rgba(233, 69, 96, 0.2);
        border-color: rgba(233, 69, 96, 0.4);
        transform: scale(1.05);
    }

    .payment-modal-body {
        flex: 1;
        background: #ffffff;
        position: relative;
    }

    .payment-iframe {
        width: 100%;
        height: 100%;
        border: none;
        background: #ffffff;
    }

    .payment-modal-footer {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .payment-security-info {
        display: flex;
        align-items: center;
    }

    .security-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(212, 168, 83, 0.1);
        padding: 8px 16px;
        border-radius: 20px;
        color: #1A1A2E;
        font-size: 14px;
        font-weight: 600;
        border: 1px solid rgba(212, 168, 83, 0.2);
    }

    .security-icon {
        font-size: 16px;
    }

    .payment-cancel-btn {
        background: transparent;
        border: 2px solid #E94560;
        color: #E94560;
        padding: 12px 24px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .payment-cancel-btn:hover {
        background: #E94560;
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(233, 69, 96, 0.3);
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .payment-modal-overlay {
            padding: 10px;
        }
        
        .payment-modal-container {
            height: 95vh;
            border-radius: 16px;
        }
        
        .payment-modal-header {
            padding: 20px;
        }
        
        .payment-brand-text h3 {
            font-size: 18px;
        }
        
        .payment-modal-footer {
            padding: 16px 20px;
            flex-direction: column-reverse;
            gap: 16px;
            align-items: stretch;
        }
        
        .payment-cancel-btn {
            width: 100%;
            justify-content: center;
            display: flex;
        }
    }

    @media (max-width: 480px) {
        .payment-brand {
            gap: 12px;
        }
        
        .payment-logo-container {
            width: 40px;
            height: 40px;
        }
        
        .payment-logo-icon {
            font-size: 20px;
        }
        
        .payment-brand-text h3 {
            font-size: 16px;
        }
        
        .payment-brand-text p {
            font-size: 12px;
        }
    }
    </style>
    
    <style>
    /* Styles pour le modal profil */
    .profile-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }
    
    .tab-button {
        background: none;
        border: none;
        padding: 10px 15px;
        cursor: pointer;
        font-size: 14px;
        color: #666;
        border-bottom: 2px solid transparent;
        transition: all 0.3s ease;
    }
    
    .tab-button.active {
        color: #D4A853;
        border-bottom-color: #D4A853;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 100%);
        color: #1A1A2E;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(212, 168, 83, 0.3);
    }
    
    #ordersList {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .order-item {
        background: #f8f9fa;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        border-left: 4px solid #D4A853;
    }
    </style>
    <script>
        function showPaymentModal(url) {
            const modal = document.getElementById('paymentModal');
            const iframe = document.getElementById('paymentIframe');
            iframe.src = url;
            modal.style.display = 'block';
            // Animation d'entrée
            setTimeout(() => {
                modal.style.opacity = '1';
            }, 10);
            // Désactiver le scroll du body
            document.body.style.overflow = 'hidden';
        }
        
        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            const iframe = document.getElementById('paymentIframe');
            
            // Animation de sortie
            modal.style.opacity = '0';
            modal.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                iframe.src = '';
                modal.style.display = 'none';
                modal.style.transform = 'scale(1)';
                // Réactiver le scroll du body
                document.body.style.overflow = 'auto';
                // Return to index page
                window.location.href = (window.ROOT_PATH || '') + '/index.php';
            }, 300);
        }
        
        // Fermeture par échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('paymentModal');
                if (modal && modal.style.display !== 'none') {
                    closePaymentModal();
                }
            }
        });
        
        // Fermeture par clic sur overlay
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('paymentModal');
            const overlay = modal?.querySelector('.payment-modal-overlay');
            if (e.target === overlay) {
                closePaymentModal();
            }
        });
    </script>
    <script>
        // Ouvrir le modal Profil au clic sur le nom dans la nav
        document.getElementById('openAccountLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            showModal('profileModal');
        });
        document.getElementById('openAccountLinkMobile')?.addEventListener('click', function(e) {
            e.preventDefault();
            showModal('profileModal');
        });
        
        // Gestion des onglets du profil
        function showProfileTab(tabName) {
            // Cacher tous les onglets
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
                tab.style.display = 'none';
            });
            
            // Désactiver tous les boutons d'onglets
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Afficher l'onglet sélectionné
            if (tabName === 'profile') {
                document.getElementById('profileTab').classList.add('active');
                document.getElementById('profileTab').style.display = 'block';
                document.querySelector('[onclick="showProfileTab(\'profile\')"]').classList.add('active');
            } else if (tabName === 'orders') {
                document.getElementById('ordersTab').classList.add('active');
                document.getElementById('ordersTab').style.display = 'block';
                document.querySelector('[onclick="showProfileTab(\'orders\')"]').classList.add('active');
                loadUserOrders();
            }
        }
        
    // Mise à jour du profil (email & téléphone)
    async function handleUpdateProfile(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            // Vérifier la confirmation du mot de passe
            const password = formData.get('password');
            const passwordConfirm = formData.get('password_confirm');
            
            if (password && password !== passwordConfirm) {
                showMessage('Les mots de passe ne correspondent pas', 'error');
                return;
            }
            
            formData.append('action', 'updateProfile');
            
            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage('Profil mis à jour avec succès', 'success');
                    // Recharger la page pour actualiser les données de session
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showMessage(result.error || 'Erreur lors de la mise à jour', 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showMessage('Erreur lors de la mise à jour du profil', 'error');
            }
        }
        
    // Charger les commandes de l'utilisateur
    async function loadUserOrders() {
            try {
                const endpoint = (window.ROOT_PATH || '') + '/api/auth.php?action=orders';
                const response = await fetch(endpoint, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const result = await response.json();
                
                if (result.success && result.orders) {
                    displayOrders(result.orders);
                } else {
                    document.getElementById('ordersList').innerHTML = '<p>Aucune commande trouvée.</p>';
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('ordersList').innerHTML = '<p>Erreur lors du chargement des commandes.</p>';
            }
        }
        
        // Afficher les commandes
        function displayOrders(orders) {
            const ordersList = document.getElementById('ordersList');
            
            if (orders.length === 0) {
                ordersList.innerHTML = '<p>Aucune commande trouvée.</p>';
                return;
            }
            
                const ordersHtml = orders.map(order => `
                <div class="order-item">
                    <strong>Commande #${order.numero_commande}</strong><br>
                    <small>De: ${order.adresse_depart} → Vers: ${order.adresse_arrivee}</small><br>
                    <small>Date: ${order.date_formatted} | Statut: ${order.statut}</small>
                </div>
            `).join('');
            
            ordersList.innerHTML = ordersHtml;
        }
        
        // Changer le mot de passe
        async function handleChangePassword(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const newPwd = formData.get('newPassword');
            const confirmPwd = formData.get('confirmPassword');
            if (newPwd !== confirmPwd) {
                showMessage('Les mots de passe ne correspondent pas', 'error');
                return;
            }
            formData.append('action', 'changePassword');
            try {
                const response = await fetch((window.ROOT_PATH||'') + '/api/auth.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showMessage('Mot de passe changé avec succès', 'success');
                    form.reset();
                } else {
                    showMessage(result.error || 'Erreur lors du changement de mot de passe', 'error');
                }
            } catch (err) {
                console.error('Erreur changePassword:', err);
                showMessage('Erreur réseau lors du changement de mot de passe', 'error');
            }
        }
        
        // Afficher/masquer le formulaire de changement de mot de passe
        function togglePasswordChangeForm() {
            const form = document.getElementById('changePasswordForm');
            if (form.style.display === 'none' || !form.style.display) {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
                form.reset();
            }
        }
    </script>
