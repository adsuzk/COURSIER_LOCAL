/**
 * ============================================================================
 * 🔄 CLIENT SYNCHRONISATION TEMPS RÉEL SUZOSKY
 * ============================================================================
 * 
 * Client JavaScript pour la synchronisation en temps réel
 * Compatible avec toutes les interfaces (admin, business, coursier, concierge)
 * 
 * @version 1.0.0 - Client unifié
 * @author Équipe Suzosky  
 * @date 26 août 2025
 * ============================================================================
 */

class SuzoskySyncClient {
    
    constructor(interfaceType = 'admin') {
        this.interfaceType = interfaceType;
        this.clientId = this.generateClientId();
        this.eventSource = null;
        this.lastSync = new Date().toISOString();
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 5000; // 5 secondes
        
        // Callbacks pour les événements
        this.callbacks = {
            onConnect: [],
            onDisconnect: [],
            onAgentsUpdate: [],
            onChatUpdate: [],
            onCommandesUpdate: [],
            onStatsUpdate: [],
            onError: []
        };
        
        // Initialiser
        this.init();
    }
    
    /**
     * Initialisation du client
     */
    init() {
        this.log(`🚀 Initialisation client sync (${this.interfaceType})`);
        this.connect();
        
        // Gérer la fermeture de la page
        window.addEventListener('beforeunload', () => {
            this.disconnect();
        });
        
        // Gérer la perte de focus/regain
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && !this.isConnected) {
                this.log('🔄 Page redevenue visible, reconnexion...');
                setTimeout(() => this.connect(), 1000);
            }
        });
    }
    
    /**
     * Connexion au serveur SSE
     */
    connect() {
        if (this.eventSource) {
            this.disconnect();
        }
        
        const url = `sync_realtime.php?interface=${this.interfaceType}&client_id=${this.clientId}&last_sync=${encodeURIComponent(this.lastSync)}`;
        
        this.log(`🔌 Connexion à: ${url}`);
        
        this.eventSource = new EventSource(url);
        
        // Événement de connexion
        this.eventSource.onopen = () => {
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.log('✅ Connexion établie');
            this.triggerCallbacks('onConnect');
        };
        
        // Événement de synchronisation
        this.eventSource.addEventListener('sync', (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleSyncData(data);
                this.lastSync = data.timestamp;
            } catch (e) {
                this.log('❌ Erreur parsing sync:', e);
            }
        });
        
        // Heartbeat
        this.eventSource.addEventListener('heartbeat', (event) => {
            try {
                const data = JSON.parse(event.data);
                this.log(`💓 Heartbeat: ${data.timestamp}`);
            } catch (e) {
                this.log('❌ Erreur parsing heartbeat:', e);
            }
        });
        
        // Erreurs
        this.eventSource.addEventListener('error', (event) => {
            try {
                const data = JSON.parse(event.data);
                this.log('🚨 Erreur serveur:', data.message);
                this.triggerCallbacks('onError', data);
            } catch (e) {
                // Erreur de connexion générale
                this.handleConnectionError();
            }
        });
        
        // Fermeture de connexion
        this.eventSource.onerror = () => {
            this.handleConnectionError();
        };
    }
    
    /**
     * Gérer les erreurs de connexion
     */
    handleConnectionError() {
        this.isConnected = false;
        this.triggerCallbacks('onDisconnect');
        
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = this.reconnectDelay * this.reconnectAttempts;
            
            this.log(`🔄 Tentative reconnexion ${this.reconnectAttempts}/${this.maxReconnectAttempts} dans ${delay/1000}s`);
            
            setTimeout(() => {
                this.connect();
            }, delay);
        } else {
            this.log('❌ Nombre maximum de tentatives de reconnexion atteint');
            this.triggerCallbacks('onError', {
                error: true,
                message: 'Impossible de se reconnecter au serveur'
            });
        }
    }
    
    /**
     * Traiter les données de synchronisation
     */
    handleSyncData(data) {
        this.log('📊 Données sync reçues:', data);
        
        // Agents mis à jour
        if (data.agents) {
            this.triggerCallbacks('onAgentsUpdate', data.agents);
        }
        
        // Chat mis à jour
        if (data.chat) {
            this.triggerCallbacks('onChatUpdate', data.chat);
        }
        
        // Commandes mises à jour
        if (data.commandes) {
            this.triggerCallbacks('onCommandesUpdate', data.commandes);
        }
        
        // Statistiques mises à jour
        if (data.stats) {
            this.triggerCallbacks('onStatsUpdate', data.stats);
        }
    }
    
    /**
     * Déconnexion
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        this.log('🔌 Déconnecté');
    }
    
    /**
     * Ajouter un callback
     */
    on(event, callback) {
        if (this.callbacks[event]) {
            this.callbacks[event].push(callback);
        }
    }
    
    /**
     * Déclencher les callbacks
     */
    triggerCallbacks(event, data = null) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (e) {
                    this.log('❌ Erreur callback:', e);
                }
            });
        }
    }
    
    /**
     * Générer un ID client unique
     */
    generateClientId() {
        return 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * Log avec préfixe
     */
    log(message, data = null) {
        const timestamp = new Date().toLocaleTimeString();
        console.log(`[${timestamp}] [SuzoskySync] ${message}`, data || '');
    }
    
    /**
     * Statut de connexion
     */
    getStatus() {
        return {
            connected: this.isConnected,
            clientId: this.clientId,
            interface: this.interfaceType,
            lastSync: this.lastSync,
            reconnectAttempts: this.reconnectAttempts
        };
    }
}

/**
 * ============================================================================
 * HELPERS POUR LES INTERFACES
 * ============================================================================
 */

/**
 * Helper pour admin.php
 */
class AdminSyncHelper {
    constructor(syncClient) {
        this.sync = syncClient;
        this.setupAdminSync();
    }
    
    setupAdminSync() {
        // Gestion agents
        this.sync.on('onAgentsUpdate', (data) => {
            this.updateAgentsDisplay(data);
            this.showNotification(`${data.nouveaux} nouveaux agents, ${data.modifies} modifiés`);
        });
        
        // Gestion chat
        this.sync.on('onChatUpdate', (data) => {
            this.updateChatBadges(data);
            if (data.nouveaux_messages.length > 0) {
                this.showChatNotification(data.nouveaux_messages);
            }
        });
        
        // Gestion stats
        this.sync.on('onStatsUpdate', (data) => {
            this.updateDashboardStats(data);
        });
    }
    
    updateAgentsDisplay(data) {
        // Mettre à jour le tableau des agents
        const agentsTable = document.getElementById('agents-table');
        if (agentsTable && data.details) {
            // Recharger la section agents
            this.refreshAgentsSection();
        }
    }
    
    updateChatBadges(data) {
        // Mettre à jour les badges de chat
        const chatBadge = document.querySelector('.chat-badge');
        if (chatBadge) {
            chatBadge.textContent = data.messages_non_lus;
            chatBadge.style.display = data.messages_non_lus > 0 ? 'inline' : 'none';
        }
    }
    
    updateDashboardStats(data) {
        // Mettre à jour les statistiques temps réel
        const elements = {
            'server-load': data.server_load,
            'memory-usage': this.formatBytes(data.memory_usage),
            'timestamp': data.timestamp
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }
    
    showNotification(message) {
        // Créer notification discrète
        const notification = document.createElement('div');
        notification.className = 'sync-notification';
        notification.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #4CAF50;
                color: white;
                padding: 12px 20px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 10000;
                font-size: 14px;
            ">
                🔄 ${message}
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
    
    showChatNotification(messages) {
        messages.forEach(msg => {
            this.showNotification(`💬 Nouveau message de ${msg.sender_name}`);
        });
    }
    
    refreshAgentsSection() {
        // Recharger la section agents via AJAX
        // (à implémenter selon la structure HTML de admin.php)
    }
    
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

/**
 * Helper pour business.php
 */
class BusinessSyncHelper {
    constructor(syncClient) {
        this.sync = syncClient;
        this.setupBusinessSync();
    }
    
    setupBusinessSync() {
        this.sync.on('onCommandesUpdate', (data) => {
            this.updateCommandesDisplay(data);
        });
        
        this.sync.on('onChatUpdate', (data) => {
            this.updateChatStatus(data);
        });
    }
    
    updateCommandesDisplay(data) {
        // Logique spécifique business
        console.log('Business - Commandes mises à jour:', data);
    }
    
    updateChatStatus(data) {
        // Logique spécifique business
        console.log('Business - Chat mis à jour:', data);
    }
}

/**
 * ============================================================================
 * INITIALISATION AUTOMATIQUE
 * ============================================================================
 */

// Détecter l'interface actuelle
function detectInterface() {
    const path = window.location.pathname;
    if (path.includes('admin.php')) return 'admin';
    if (path.includes('business.php')) return 'business';
    if (path.includes('coursier.php')) return 'coursier';
    if (path.includes('concierge.php')) return 'concierge';
    return 'admin'; // Par défaut
}

// Auto-initialisation
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.suzoskySyncDisabled === 'undefined') {
        const interfaceType = detectInterface();
        window.suzoskySync = new SuzoskySyncClient(interfaceType);
        
        // Helper spécifique selon l'interface
        if (interfaceType === 'admin') {
            window.adminSyncHelper = new AdminSyncHelper(window.suzoskySync);
        } else if (interfaceType === 'business') {
            window.businessSyncHelper = new BusinessSyncHelper(window.suzoskySync);
        }
        
        console.log(`🚀 Suzosky Sync initialisé pour ${interfaceType}`);
    }
});
