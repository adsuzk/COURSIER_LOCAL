<?php
// sections/js_initialization.php - Initialisation générale et fonctions utilitaires
?>
    <script>
    // CONFIGURATION GLOBALE
    window.ROOT_PATH = '/COURSIER_LOCAL';  // Chemin racine pour les appels API
    
    // STUBS pour éviter les erreurs JS legacy
    window.isLoggedIn = window.isLoggedIn || false;
    window.logError = window.logError || function(){};
    window.clearRoute = window.clearRoute || function(){};
    window.clearMarkers = window.clearMarkers || function(){};
    window.calculateRoute = window.calculateRoute || function(){};
    window.updateAddressFromCoordinates = window.updateAddressFromCoordinates || function(pos, field){};
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
    let isMobileDevice = false;
    let notificationPermission = 'default';
    
    // Initialisation principale de l'application
    function initializeApp() {
        console.log(`🚀 Initialisation Suzosky Coursier v${APP_VERSION} (${APP_BUILD})...`);
        
        // Détecter le type d'appareil
        detectDeviceType();
        
        // Vérifier l'état d'authentification
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
        
        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Prévenir la fermeture accidentelle avec des données non sauvegardées
        window.addEventListener('beforeunload', handleBeforeUnload);
        
        console.log('🎯 Événements globaux configurés');
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
        // Vérifier s'il y a des données non sauvegardées
        const hasUnsavedData = checkUnsavedData();
        
        if (hasUnsavedData) {
            const message = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
            event.returnValue = message;
            return message;
        }
    }
    
    function checkUnsavedData() {
        // Vérifier si le formulaire a été modifié
        const form = document.getElementById('orderForm');
        if (form) {
            const inputs = form.querySelectorAll('input, select, textarea');
            for (let input of inputs) {
                if (input.value && input.value.trim() !== '') {
                    // Ignorer le téléphone si l'utilisateur est connecté
                    if (input.id === 'phone' && isLoggedIn) continue;
                    return true;
                }
            }
        }
        return false;
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
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
        
        // Fermer avec Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    closeModal(openModal.id);
                }
            }
        });
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
    
    // Initialisation quand le DOM est prêt
    document.addEventListener('DOMContentLoaded', function() {
        setupPolyfills();
        setupModalEvents();
        initializeApp();
    });
    
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
        const imagesToPreload = [
            '/assets/logo-suzosky-new.svg',
            '/assets/icon-192.svg'
        ];
        
        imagesToPreload.forEach(src => {
            const img = new Image();
            img.src = src;
        });
    }
    
    console.log('📋 js_initialization.php chargé');
    </script>
