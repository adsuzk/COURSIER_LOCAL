<?php
// sections/js_initialization.php - Initialisation générale et fonctions utilitaires
?>
    <script>
    // === Global JS Error Handling ===
    window.onerror = function(message, source, lineno, colno, error) {
        console.error('❌ Global error caught:', message, 'at', source + ':' + lineno + ':' + colno, error);
        // TODO: envoyer ce log au serveur (/api/log_client_error.php)
        return false; // laisser le traitement par défaut
    };
    window.onunhandledrejection = function(event) {
        console.error('❌ Unhandled promise rejection:', event.reason);
        // TODO: envoyer ce log au serveur
        return false;
    };
    // === Service Integration Checks ===
    // Firebase Web SDK non nécessaire - FCM géré par application Android uniquement
    // Application mobile génère tokens FCM et communique via mobile_sync_api.php
    console.info('✅ Firebase configuré côté Android - Système FCM opérationnel');
    // Google Maps detection
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        console.error('⚠️ Google Maps non détecté : vérifiez la clé API et le chargement du script');
    }
    // CinetPay config validation
    if (typeof CINETPAY_CONFIG !== 'undefined') {
        if (!CINETPAY_CONFIG.apikey || CINETPAY_CONFIG.apikey.includes('YOUR_')) {
            console.warn('⚠️ CinetPay API key non configurée dans CINETPAY_CONFIG');
        }
    } else {
        console.warn('⚠️ Configuration CINETPAY_CONFIG manquante');
    }
    
    // VARIABLES GLOBALES - Déclarations explicites AVANT les stubs
    window.map = null;
    window.currentUser = null;
    window.isLoggedIn = false;
    window.markerA = null;
    window.markerB = null;
    window.directionsService = null;
    window.directionsRenderer = null;
    window.skipBeforeUnload = false;
    window._skipBeforeUnloadCheck = false;
    window.orderFormDirty = false;
    
    // FONCTIONS GLOBALES - Déclarations immédiates
    window.openConnexionModal = function() { console.log('openConnexionModal stub'); };
    window.toggleMobileMenu = function() { console.log('toggleMobileMenu stub'); };
    window.submitLogin = function() { console.log('submitLogin stub'); };
    window.openLoginModal = function() { console.log('openLoginModal stub'); };
    window.switchToLogin = function() { console.log('switchToLogin stub'); };
    // Stub global pour openAccountModal (profil) afin d'éviter les ReferenceError si le script assets/connexion_modal.js n'est pas encore chargé
    window.openAccountModal = window.openAccountModal || function() {
        try {
            // Utiliser la modale de connexion/profil AJAX centralisée
            if (typeof window.openConnexionModal === 'function') {
                window.openConnexionModal();
                return;
            }
        } catch (e) { /* ignore */ }
        console.error('openAccountModal indisponible (scripts non chargés)');
    };
    
    // STUBS pour éviter les erreurs JS legacy
    window.logError = window.logError || function(){};
    window.clearRoute = window.clearRoute || function(){};
    window.clearMarkers = window.clearMarkers || function(){};
    window.calculateRoute = window.calculateRoute || function(){};
    window.updateAddressFromCoordinates = window.updateAddressFromCoordinates || function(pos, field){};
    
    // VÉRIFICATIONS CONDITIONNELLES (gardées pour compatibilité)
    if (typeof window.map === 'undefined') {
        window.map = null; // Variable pour Google Maps
    }
    if (typeof window.currentUser === 'undefined') {
        window.currentUser = null; // Variable pour l'utilisateur connecté
    }
    if (typeof window.markerA === 'undefined') {
        window.markerA = null; // Marqueur point de départ
    }
    if (typeof window.markerB === 'undefined') {
        window.markerB = null; // Marqueur point d'arrivée
    }
    if (typeof window.directionsService === 'undefined') {
        window.directionsService = null; // Service Google Maps
    }
    if (typeof window.directionsRenderer === 'undefined') {
        window.directionsRenderer = null; // Renderer Google Maps
    }
    
    // FONCTIONS GLOBALES MANQUANTES - correctifs des erreurs ReferenceError
    window.openLoginModal = function() {
        console.log('🔗 openLoginModal() appelée (legacy)');
        if (typeof window.openConnexionModal === 'function') {
            window.openConnexionModal();
        } else {
            console.error('❌ openConnexionModal non disponible');
        }
    };
    
    window.toggleMobileMenu = function(forceState) {
        console.log('📱 toggleMobileMenu() appelée');
        const mobileMenu = document.getElementById('mobileMenu');
        if (!mobileMenu) {
            console.error('❌ Element mobileMenu introuvable');
            return false;
        }
        const isOpen = mobileMenu.classList.contains('active') || mobileMenu.classList.contains('open');
        const nextOpen = typeof forceState === 'boolean' ? forceState : !isOpen;
        mobileMenu.classList.toggle('active', nextOpen);
        mobileMenu.classList.toggle('open', nextOpen); // compat styles @media
        document.body.style.overflow = nextOpen ? 'hidden' : '';
        console.log(`📱 Menu mobile ${nextOpen ? 'ouvert' : 'fermé'}`);
        return nextOpen;
    };

    // Fermer le menu en cliquant sur l'overlay (zone hors contenu)
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuEl = document.getElementById('mobileMenu');
        if (mobileMenuEl) {
            mobileMenuEl.addEventListener('click', function(e) {
                // Si on clique directement sur le conteneur (pas sur le contenu), fermer
                if (e.target === mobileMenuEl) {
                    window.toggleMobileMenu(false);
                }
            });
        }
        // ESC pour fermer
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') {
                window.toggleMobileMenu(false);
            }
        });
    });
    
    window.switchToLogin = function() {
        console.log('🔄 switchToLogin() appelée');
        // Logic pour basculer vers l'onglet login
        const loginTab = document.querySelector('[data-tab="login"]');
        const registerTab = document.querySelector('[data-tab="register"]');
        if (loginTab) {
            loginTab.click();
        } else {
            console.error('❌ Onglet login introuvable');
        }
    };
    
    window.submitLogin = function() {
        console.log('📤 submitLogin() appelée');
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.dispatchEvent(new Event('submit', {cancelable: true}));
        } else {
            console.error('❌ Formulaire loginForm introuvable');
        }
    };
    
    // FONCTION GLOBALE: openConnexionModal - compatibilité avec les appels directs
    window.openConnexionModal = function() {
        console.log('🔗 openConnexionModal() appelée');
        const modal = document.getElementById('connexionModal');
        const openBtn = document.getElementById('openConnexionLink');
        
        if (!modal) {
            console.error('❌ Modal connexionModal introuvable');
            return;
        }
        
        if (openBtn) {
            console.log('🎯 Déclenchement du click sur openConnexionLink');
            openBtn.click();
        } else {
            console.error('❌ Bouton openConnexionLink introuvable');
        }
    };
// Helper: Debounce function to limit function calls
function debounce(func, wait, immediate) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}
    // Variables globales pour l'application
    const APP_VERSION = '2.1.0'; // Version de l'application Suzosky Coursier
    const APP_BUILD = '20250905'; // Build date
    const APP_ENV = 'production'; // Environment
    
    let isPageLoaded = false;
    let _initialOnlineFired = false; // flag to ignore first online event
    let isMobileDevice = false;
    let notificationPermission = 'default';
    
    // Initialisation principale de l'application
    function initializeApp() {
        console.log(`🚀 Initialisation Suzosky Coursier v${APP_VERSION} (${APP_BUILD})...`);
        
        // TEST CONSOLE IMMÉDIAT
        console.log('=== TEST CONSOLE ===');
        console.log('✅ Console fonctionne !');
        console.log('📅 Date:', new Date().toLocaleString());
        console.log('🌐 URL:', window.location.href);
        console.log('===================');
        
        // AUDIT DOM COMPLET
        auditDOMElements();
        
        // Détecter le type d'appareil
        detectDeviceType();
        
        // Vérifier l'état d'authentification du client et mettre à jour la navigation
        checkAuthState();
        
        // Initialiser les composants
        initializeComponents();
        
        // Configurer les événements globaux
        setupGlobalEvents();
        
        // Demander les permissions
        requestPermissions();
        
        // Marquer comme chargé
        isPageLoaded = true;
        
        console.log('✅ Application initialisée avec succès');
        
        // Test immédiat du modal de connexion
        setTimeout(() => {
            const openBtn = document.getElementById('openConnexionLink');
            const modal = document.getElementById('connexionModal');
            console.log('🔍 Test éléments modal:', {
                openBtn: openBtn ? 'FOUND' : 'NOT FOUND',
                modal: modal ? 'FOUND' : 'NOT FOUND'
            });
        }, 1000);

        // Start polling backend availability for coursiers as a fallback when FCM web is not present
        try {
            if (typeof startCoursierAvailabilityPoll !== 'function') {
                const defaultPollInterval = (typeof window.COURSIER_POLL_INTERVAL_MS === 'number' && window.COURSIER_POLL_INTERVAL_MS > 0)
                    ? window.COURSIER_POLL_INTERVAL_MS
                    : 15000;
                window.COURSER_POLL_INTERVAL = defaultPollInterval;
                window._coursierPollTimer = null;

                window.getCoursierAvailability = async function() {
                    try {
                        const resp = await fetch((window.ROOT_PATH || '') + '/api/get_coursier_availability.php', { cache: 'no-store' });
                        const j = await resp.json();
                        if (j && j.success) {
                            if (typeof window.setFCMCoursierStatus === 'function') {
                                window.setFCMCoursierStatus(Boolean(j.available), j.message || '', {
                                    origin: 'poll',
                                    lockDelayMs: (typeof j.lock_delay_seconds === 'number' && j.lock_delay_seconds > 0) ? j.lock_delay_seconds * 1000 : undefined,
                                    meta: {
                                        activeCount: typeof j.active_count === 'number' ? j.active_count : null,
                                        freshCount: typeof j.fresh_count === 'number' ? j.fresh_count : null,
                                        secondsSinceLastActive: typeof j.seconds_since_last_active === 'number' ? j.seconds_since_last_active : null
                                    }
                                });
                            } else {
                                // If setFCM API missing, create a temporary banner
                                if (!j.available) {
                                    console.warn('Coursier indisponible (fallback)', j.message || 'Aucun coursier disponible');
                                }
                            }
                        }
                    } catch (err) {
                        console.warn('Erreur getCoursierAvailability:', err);
                    }
                };

                window.startCoursierAvailabilityPoll = function() {
                    if (window._coursierPollTimer) return;
                    // Run immediately and then interval
                    window.getCoursierAvailability();
                    window._coursierPollTimer = setInterval(window.getCoursierAvailability, window.COURSER_POLL_INTERVAL);
                };

                window.stopCoursierAvailabilityPoll = function() {
                    if (window._coursierPollTimer) {
                        clearInterval(window._coursierPollTimer);
                        window._coursierPollTimer = null;
                    }
                };
            }
            // Start polling by default so the order form receives availability info
            window.startCoursierAvailabilityPoll();
        } catch (e) {
            console.warn('⚠️ Impossible de démarrer le poller de disponibilité coursiers', e);
        }
    }

    // Mettre à jour les éléments d'authentification, notamment dans le menu mobile
    function checkAuthState() {
        try {
            const mobileAuth = document.getElementById('mobileNavAuth');
            if (!mobileAuth) {
                return;
            }

            // Déterminer l'état via le rendu serveur (présence de #userNav côté desktop)
            const userNav = document.getElementById('userNav');
            const guestNav = document.getElementById('guestNav');

            // Nettoyer d'abord
            mobileAuth.innerHTML = '';

            if (userNav) {
                // Utilisateur connecté: afficher compte + déconnexion
                const userNameEl = userNav.querySelector('.user-name');
                const userName = userNameEl ? (userNameEl.textContent || 'Mon compte') : 'Mon compte';
                const wrapper = document.createElement('div');
                wrapper.className = 'auth-state';
                wrapper.id = 'mobileUserNav';
                wrapper.innerHTML = `
                    <a href="#" class="mobile-nav-link" onclick="openAccountModal(); toggleMobileMenu(false)">👤 ${userName}</a>
                    <a href="logout.php" class="mobile-nav-link">🚪 Déconnexion</a>
                `;
                mobileAuth.appendChild(wrapper);
            } else {
                // Visiteur: afficher Connexion + Espace Business
                const wrapper = document.createElement('div');
                wrapper.className = 'auth-state';
                wrapper.id = 'mobileGuestNav';
                wrapper.innerHTML = `
                    <a href="#" id="openConnexionLinkMobile" class="mobile-nav-link">🔐 Connexion Particulier</a>
                    <a href="business.html" class="mobile-nav-link">🏢 Espace Business</a>
                `;
                mobileAuth.appendChild(wrapper);

                // Brancher le bouton de connexion mobile pour ouvrir la modale puis fermer le menu
                const openBtnMobile = document.getElementById('openConnexionLinkMobile');
                if (openBtnMobile) {
                    openBtnMobile.addEventListener('click', function(e) {
                        e.preventDefault();
                        // Fermer le menu mobile avant d'ouvrir la modale
                        try { window.toggleMobileMenu(false); } catch {}
                        // Ouvrir la modale de connexion
                        if (typeof openConnexionModal === 'function') {
                            openConnexionModal();
                        }
                    }, { once: false });
                }
            }
        } catch (err) {
            console.error('❌ Erreur checkAuthState:', err);
        }
    }
    
    // Détection du type d'appareil
    function detectDeviceType() {
        isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        if (isMobileDevice) {
            document.body.classList.add('mobile-device');
            console.log('📱 Appareil mobile détecté');
        } else {
            document.body.classList.add('desktop-device');
            console.log('🖥️ Appareil desktop détecté');
        }

        // nothing here, auth state handled globally
    }
    
    // Audit complet des éléments DOM critiques
    function auditDOMElements() {
        console.log('🔍 === AUDIT DOM COMPLET ===');
        
        const criticalElements = [
            'openConnexionLink',
            'openConnexionLinkMobile',
            'connexionModal',
            'closeConnexionModal',
            'connexionModalBody'
        ];
        
        const results = {};
        let allFound = true;
        
        criticalElements.forEach(id => {
            const element = document.getElementById(id);
            results[id] = {
                found: !!element,
                element: element,
                visible: element ? (element.offsetWidth > 0 && element.offsetHeight > 0) : false,
                style: element ? window.getComputedStyle(element).display : 'N/A'
            };
            
            if (!element) {
                allFound = false;
                console.error(`❌ Élément manquant: ${id}`);
            } else {
                console.log(`✅ Élément trouvé: ${id} (display: ${results[id].style})`);
            }
        });
        
        // Test des fonctions critiques
        console.log('🔧 Test des fonctions critiques:');
        
        if (typeof window.openConnexionModal === 'function') {
            console.log('✅ window.openConnexionModal existe');
        } else {
            console.error('❌ window.openConnexionModal manquante');
            allFound = false;
        }
        
        // Résumé de l'audit
        if (allFound) {
            console.log('🎉 AUDIT DOM: TOUS LES ÉLÉMENTS TROUVÉS !');
        } else {
            console.error('⚠️  AUDIT DOM: ÉLÉMENTS MANQUANTS DÉTECTÉS !');
        }
        
        console.log('🔍 === FIN AUDIT DOM ===');
        return results;
    }
    
    // Initialisation des composants
    function initializeComponents() {
        try {
            // Initialiser la carte Google Maps
            if (typeof initMap === 'function') {
                console.log('🗺️ Initialisation de Google Maps...');
                // initMap sera appelée automatiquement par l'API Google Maps
            }
            
            // Initialiser l'autocomplétion
            if (typeof setupAutocomplete === 'function') {
                console.log('🔍 Configuration de l\'autocomplétion...');
                // setupAutocomplete sera appelée après le chargement de la carte
            }
            
            // Initialiser les améliorations de formulaire
            if (typeof setupFormEnhancements === 'function') {
                console.log('📝 Configuration des formulaires...');
                setupFormEnhancements();
            }
            
            // Charger le brouillon du formulaire
            if (typeof loadFormDraft === 'function') {
                setTimeout(loadFormDraft, 1000);
            }
            
            console.log('✅ Composants initialisés');
        } catch (error) {
            console.error('❌ Erreur lors de l\'initialisation des composants:', error);
        }
    }
    
    // Configuration des événements globaux
    function setupGlobalEvents() {
        // Gestion des erreurs JavaScript globales
        window.addEventListener('error', function(event) {
            console.error('Erreur JavaScript:', event.error);
            logError('JavaScript Error', event.error.message, event.filename, event.lineno);
        });
        
        // Gestion des erreurs de promesses non capturées
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Promesse rejetée:', event.reason);
            logError('Unhandled Promise Rejection', event.reason);
        });
        
        // Gestion du changement de taille d'écran
        window.addEventListener('resize', debounce(handleWindowResize, 250));
        
        // Gestion de la perte/récupération de connexion
        window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    window.addEventListener('keydown', handleRefreshKey, { passive: true });
        
        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Prévenir la fermeture accidentelle avec des données non sauvegardées
        window.addEventListener('beforeunload', handleBeforeUnload);
        
        // SETUP MODAL EVENTS - Événements pour les modales de connexion
        setupModalEvents();
        
        console.log('🎯 Événements globaux configurés');
    }
    
    // Configuration des événements des modales
    function setupModalEvents() {
        console.log('🪟 Configuration des événements de modales...');
        
        const openBtns = [
            document.getElementById('openConnexionLink'),
            document.getElementById('openConnexionLinkMobile')
        ].filter(Boolean);
        
        const modal = document.getElementById('connexionModal');
        const closeBtn = document.getElementById('closeConnexionModal');
        const body = document.getElementById('connexionModalBody');
        
        console.log('🔍 Éléments modal trouvés:', {
            buttons: openBtns.length,
            modal: !!modal,
            closeBtn: !!closeBtn,
            body: !!body
        });
        
        if (openBtns.length === 0 || !modal || !closeBtn || !body) {
            console.error('❌ Éléments de modal manquants');
            return;
        }
        
        // Event listeners pour ouvrir le modal
        openBtns.forEach((btn, index) => {
            if (btn) {
                console.log(`🔗 Attachement event listener sur ${btn.id}`);
                btn.addEventListener('click', async (e) => {
                    console.log(`🎯 Click détecté sur ${btn.id}`);
                    e.preventDefault();
                    await openConnexionModalHandler();
                });
            }
        });
        
        // Event listener pour fermer le modal
        closeBtn.addEventListener('click', () => {
            console.log('❌ Fermeture du modal');
            modal.style.display = 'none';
        });
        
        // Fermer en cliquant à l'extérieur
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                console.log('❌ Fermeture du modal (click extérieur)');
                modal.style.display = 'none';
            }
        });
        
        console.log('✅ Événements de modal configurés');
    }
    
    // Handler pour ouvrir le modal de connexion
    async function openConnexionModalHandler() {
        console.log('🚀 Ouverture du modal de connexion...');
        
        const modal = document.getElementById('connexionModal');
        const body = document.getElementById('connexionModalBody');
        
        if (!modal || !body) {
            console.error('❌ Éléments de modal manquants');
            return;
        }
        
        try {
            const APP_ROOT = window.ROOT_PATH || '';
            const res = await fetch(encodeURI(APP_ROOT + '/sections_index/connexion.php'));
            const html = await res.text();
            
            body.innerHTML = html;
            modal.style.display = 'flex';
            
            console.log('✅ Modal de connexion ouvert');
            
            // Setup form handling
            const loginForm = body.querySelector('#loginForm');
            if (loginForm) {
                setupLoginForm(loginForm);
            }
        } catch (error) {
            console.error('❌ Erreur lors de l\'ouverture du modal:', error);
            alert('Erreur de connexion au serveur');
        }
    }
    
    // Setup du formulaire de connexion
    function setupLoginForm(loginForm) {
        loginForm.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const btn = loginForm.querySelector('button[type="submit"]');
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Connexion...';
            
            const fd = new FormData(loginForm);
            fd.append('action', 'login');
            
            try {
                const APP_ROOT = window.ROOT_PATH || '';
                const apiRes = await fetch(APP_ROOT + '/api/auth.php?action=login', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await apiRes.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Erreur de connexion');
                }
            } catch (err) {
                console.error('Login error:', err);
                const message = err && err.message ? err.message : 'Veuillez réessayer plus tard';
                alert('Erreur réseau : ' + message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = orig;
            }
        });
    }
    
    // Gestion du redimensionnement
    function handleWindowResize() {
        if (map) {
            // Redessiner la carte
            google.maps.event.trigger(map, 'resize');
        }
        
        // Ajuster le chat si ouvert
        if (isChatOpen) {
            adjustChatSize();
        }
    }
    
    // Gestion de la connexion
    function handleOnline() {
        console.log('🌐 Connexion rétablie');
        if (!_initialOnlineFired) {
            _initialOnlineFired = true;
            return; // ignore the first online event on page load
        }
        showMessage('Connexion rétablie', 'success');
        
        // Reconnecter le chat si nécessaire
        if (typeof connectChatSocket === 'function') {
            connectChatSocket();
        }
    }
    
    function handleOffline() {
        console.log('📵 Connexion perdue');
        showMessage('Connexion internet perdue. Certaines fonctionnalités peuvent être limitées.', 'warning');
    }
    
    // Gestion de la visibilité de la page
    function handleVisibilityChange() {
        if (document.hidden) {
            console.log('👋 Page cachée');
        } else {
            console.log('👁️ Page visible');
            
            // Vérifier les nouveaux messages si connecté
            if (isLoggedIn && typeof checkNewMessages === 'function') {
                checkNewMessages();
            }
        }
    }
    
    // Prévenir la fermeture accidentelle
    function handleBeforeUnload(event) {
        if (window.skipBeforeUnload === true || window._skipBeforeUnloadCheck === true) {
            return;
        }

        const hasUnsavedData = checkUnsavedData();

        if (!hasUnsavedData) {
            return;
        }

        const message = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
        event.returnValue = message;
        return message;
    }
    
    function checkUnsavedData() {
        if (window.skipBeforeUnload === true || window._skipBeforeUnloadCheck === true) {
            return false;
        }

        return window.orderFormDirty === true;
    }

    function handleRefreshKey(evt) {
        const key = evt.key || '';
        const normalizedKey = key.toLowerCase();
        const isRefreshKey =
            key === 'F5' ||
            normalizedKey === 'f5' ||
            normalizedKey === 'r' && (evt.ctrlKey || evt.metaKey);

        if (isRefreshKey) {
            window.skipBeforeUnload = true;
            window._skipBeforeUnloadCheck = true;
        }
    }
    
    // Demander les permissions nécessaires
    function requestPermissions() {
        // Permission de géolocalisation
        if ('geolocation' in navigator) {
            console.log('📍 Géolocalisation disponible');
        }
        
        // Permission de notifications
        if ('Notification' in window) {
            if (Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    notificationPermission = permission;
                    console.log('🔔 Permission de notification:', permission);
                });
            } else {
                notificationPermission = Notification.permission;
            }
        }
        
        // Service Worker registration removed to avoid 404
    }
    
    // Système de notifications
    function showNotification(title, options = {}) {
        if (notificationPermission === 'granted') {
            new Notification(title, {
                icon: '/assets/favicon.svg',
                badge: '/assets/icon-72.svg',
                ...options
            });
        }
    }
    
    // Gestion des modals
    function showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Focus sur le premier champ de saisie
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }
    }
    
    // Fermer les modals en cliquant en dehors
    function setupModalEvents() {
        console.log('🔧 Configuration des événements modals...');
        
        const modals = document.querySelectorAll('.modal');
        console.log('Found modals:', modals.length);
        
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    console.log('Fermeture modal par clic extérieur:', this.id);
                    closeModal(this.id);
                }
            });
        });
        
        // Fermer avec Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    console.log('Fermeture modal par Échap:', openModal.id);
                    closeModal(openModal.id);
                }
            }
        });
        
        console.log('✅ Événements modals configurés');
    }
    
    // Système de messages toast
    function showMessage(message, type = 'info', duration = 5000) {
        const messageContainer = getOrCreateMessageContainer();
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `toast-message ${type}`;
        messageDiv.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${getMessageIcon(type)}</span>
                <span class="toast-text">${message}</span>
                <button class="toast-close" onclick="closeMessage(this)">&times;</button>
            </div>
        `;
        
        messageContainer.appendChild(messageDiv);
        
        // Animation d'apparition
        setTimeout(() => messageDiv.classList.add('show'), 100);
        
        // Suppression automatique
        if (duration > 0) {
            setTimeout(() => {
                closeMessage(messageDiv.querySelector('.toast-close'));
            }, duration);
        }
    }
    
    function getOrCreateMessageContainer() {
        let container = document.getElementById('messageContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'messageContainer';
            container.className = 'message-container';
            document.body.appendChild(container);
        }
        return container;
    }
    
    function getMessageIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || icons.info;
    }
    
    function closeMessage(button) {
        const message = button.closest('.toast-message');
        if (message) {
            message.classList.remove('show');
            setTimeout(() => message.remove(), 300);
        }
    }
    
    // Ajuster la taille du chat
    function adjustChatSize() {
        const chatWidget = document.getElementById('chatWidget');
        if (chatWidget && isMobileDevice) {
            const vh = window.innerHeight * 0.01;
            chatWidget.style.setProperty('--vh', `${vh}px`);
        }
    }
    
    // Logger d'erreurs
    function logError(type, message, file = '', line = '') {
        const errorData = {
            type: type,
            message: message,
            file: file,
            line: line,
            url: window.location.href,
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString(),
            userId: isLoggedIn ? currentUser.id : null
        };
        
        console.error('Error logged:', errorData);
        
        // Envoyer à votre service de logging
        // fetch('/api/log_js_error.php', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(errorData)
        // });
    }
    
    // Polyfills et compatibilité
    function setupPolyfills() {
        // Polyfill pour CustomEvent
        if (typeof window.CustomEvent !== 'function') {
            function CustomEvent(event, params) {
                params = params || { bubbles: false, cancelable: false, detail: undefined };
                const evt = document.createEvent('CustomEvent');
                evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
                return evt;
            }
            window.CustomEvent = CustomEvent;
        }
    }
    
    // Attach initialization to DOMContentLoaded or fire immediately if already loaded
    function onDocumentReady() {
        setupPolyfills();
        setupModalEvents();
        initializeApp();

        // Sécuriser la présence des coordonnées de départ au submit si disponibles
        try {
            const form = document.getElementById('orderForm');
            if (form) {
                form.addEventListener('submit', function() {
                    try {
                        if (window.markerA) {
                            const pos = window.markerA.getPosition();
                            const lat = (typeof pos.lat === 'function') ? pos.lat() : pos.lat;
                            const lng = (typeof pos.lng === 'function') ? pos.lng() : pos.lng;
                            const latEl = document.getElementById('departure_lat');
                            const lngEl = document.getElementById('departure_lng');
                            if (latEl && !latEl.value) latEl.value = lat;
                            if (lngEl && !lngEl.value) lngEl.value = lng;
                        }
                    } catch (e) { /* ignore */ }
                });
            }
        } catch (e) { /* ignore */ }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onDocumentReady);
    } else {
        onDocumentReady();
    }
    
    // Initialisation supplémentaire quand la page est complètement chargée
    window.addEventListener('load', function() {
        console.log('🎉 Page complètement chargée');
        
        // Cacher le loader si présent
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.style.display = 'none';
        }
        
        // Précharger les images importantes
        preloadImages();
    });
    
    // Préchargement des images
    function preloadImages() {
        const buildAssetPath = (relativePath) => {
            const root = (typeof window.ROOT_PATH === 'string' ? window.ROOT_PATH : '') || '';
            const cleanedRoot = root.replace(/\/$/, '');
            const cleanedRelative = String(relativePath || '').replace(/^\/+/, '');
            if (!cleanedRelative) {
                return cleanedRoot || '';
            }
            if (!cleanedRoot) {
                return '/' + cleanedRelative;
            }
            return cleanedRoot + '/' + cleanedRelative;
        };

        const imagesToPreload = [
            buildAssetPath('assets/logo-suzosky-new.svg'),
            buildAssetPath('assets/icon-192.svg')
        ];
        
        imagesToPreload.forEach(src => {
            const img = new Image();
            img.src = src;
        });
    }
    
    // =============================
    // 📞 Améliorations Téléphone CI
    // =============================
    // Contrat:
    // - Cible: #senderPhone et #receiverPhone (et tout input[name="client_telephone"]) s'ils existent
    // - Format d'affichage: "+225 xx xx xx xx xx"
    // - Persistance: localStorage ('suzosky.phones.recent') max 5 éléments, déduplication
    // - Readonly: on met uniquement au bon format, pas d'UI de rappel
    (function setupPhoneEnhancements(){
        // Styles minimalistes (thème Suzosky)
        const styleId = 'suzosky-phone-enhancements-css';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
            .phone-suggestions{margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
            .phone-suggestions .label{font-size:12px;color:var(--primary-dark);opacity:.8;margin-right:4px}
            .phone-chip{cursor:pointer;user-select:none;background:var(--glass-bg);border:1px solid var(--glass-border);color:#fff;padding:6px 10px;border-radius:14px;transition:.2s}
            .phone-chip:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,.15)}
            .phone-chip .del{margin-left:8px;color:#f5c16c;font-weight:bold}
            .phone-actions{margin-left:auto}
            .phone-clear-all{background:transparent;border:none;color:#f5c16c;cursor:pointer;font-size:12px;text-decoration:underline}
            `;
            document.head.appendChild(style);
        }

        const STORAGE_KEY = 'suzosky.phones.recent';
        const MAX_RECENTS = 5;

        // Utils formatage
        function digitsOnly(v){ return (v||'').replace(/\D+/g,''); }
        function stripCiPrefix(d){
            // Supprimer 00225 ou 225 en tête
            if (d.startsWith('00225')) return d.slice(5);
            if (d.startsWith('225')) return d.slice(3);
            return d;
        }
        function formatCiDisplayFromDigits(d){
            // d est une chaîne de chiffres (sans indicatif 225)
            const parts = [];
            for (let i=0; i<Math.min(d.length,10); i+=2){
                parts.push(d.slice(i, i+2));
            }
            const body = parts.join(' ').trim();
            return body ? `+225 ${body}` : '+225 ';
        }
        function toCiDisplay(v){
            const d = stripCiPrefix(digitsOnly(v));
            return formatCiDisplayFromDigits(d);
        }
        function ciDigits(v){
            // retourne au plus 10 chiffres côté CI (sans indicatif)
            return stripCiPrefix(digitsOnly(v)).slice(0,10);
        }
        function isCompleteCi(d){ return d && d.length === 10; }

        // Storage helpers
        function getRecents(){
            try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch { return []; }
        }
        function setRecents(list){ localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); }
        function saveRecent(displayValue){
            const d = ciDigits(displayValue);
            if (!isCompleteCi(d)) return;
            const display = formatCiDisplayFromDigits(d);
            const list = getRecents().filter(x => ciDigits(x) !== d);
            list.unshift(display);
            setRecents(list.slice(0, MAX_RECENTS));
        }
        function removeRecent(displayValue){
            const d = ciDigits(displayValue);
            setRecents(getRecents().filter(x => ciDigits(x) !== d));
        }
        function clearAll(){ setRecents([]); }

        // UI
        function ensureSuggestionsContainer(input){
            const parent = input.closest('.input-with-icon') || input.parentElement;
            if (!parent) return null;
            let box = parent.querySelector('.phone-suggestions');
            if (!box){
                box = document.createElement('div');
                box.className = 'phone-suggestions';
                parent.appendChild(box);
            }
            return box;
        }
        function renderSuggestions(input){
            if (input.readOnly) return; // pas de rappel pour readonly
            const box = ensureSuggestionsContainer(input);
            if (!box) return;
            const recents = getRecents();
            if (!recents.length){ box.innerHTML = ''; return; }
            box.innerHTML = '';
            const label = document.createElement('span');
            label.className = 'label';
            label.textContent = 'Rappel:';
            box.appendChild(label);
            recents.forEach(r => {
                const chip = document.createElement('span');
                chip.className = 'phone-chip';
                chip.innerHTML = `${r} <span class="del" title="Supprimer" aria-label="Supprimer">×</span>`;
                chip.addEventListener('click', (e) => {
                    if (e.target && e.target.classList.contains('del')){
                        e.stopPropagation();
                        removeRecent(r);
                        renderSuggestions(input);
                    } else {
                        input.value = r + ' ';
                        input.dispatchEvent(new Event('input', {bubbles:true}));
                        saveRecent(input.value);
                    }
                });
                box.appendChild(chip);
            });
            const actions = document.createElement('div');
            actions.className = 'phone-actions';
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'phone-clear-all';
            clearBtn.textContent = 'Tout effacer';
            clearBtn.addEventListener('click', () => { clearAll(); renderSuggestions(input); });
            actions.appendChild(clearBtn);
            box.appendChild(actions);
        }

        function setCaretToEnd(input){
            const len = input.value.length;
            input.setSelectionRange(len, len);
        }

        function attachMask(input){
            // Préformatage valeur initiale
            if (input.value){
                input.value = toCiDisplay(input.value);
            } else if (!input.readOnly) {
                // Préfixe à vide
                input.placeholder = input.placeholder || '+225 XX XX XX XX XX';
            }

            // UI rappel
            renderSuggestions(input);

            input.addEventListener('focus', () => {
                if (!input.value || input.value.trim() === ''){
                    input.value = '+225 ';
                    setTimeout(() => setCaretToEnd(input), 0);
                }
            });
            input.addEventListener('input', () => {
                const v = input.value;
                input.value = toCiDisplay(v);
            });
            input.addEventListener('blur', () => {
                const d = ciDigits(input.value);
                if (!d) { input.value = ''; return; }
                input.value = formatCiDisplayFromDigits(d);
                saveRecent(input.value);
                renderSuggestions(input);
            });
            input.addEventListener('paste', () => {
                setTimeout(() => { input.value = toCiDisplay(input.value); }, 0);
            });
        }

        function init(){
            const targets = [];
            const byId1 = document.getElementById('senderPhone'); if (byId1) targets.push(byId1);
            const byId2 = document.getElementById('receiverPhone'); if (byId2) targets.push(byId2);
            // fallback par name si besoin
            if (!byId1) document.querySelectorAll('input[name="client_telephone"]').forEach(el => targets.push(el));

            targets.forEach(input => attachMask(input));
        }

        if (document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', init);
        } else { init(); }
    })();
    
    console.log('📋 js_initialization.php chargé');
    </script>
