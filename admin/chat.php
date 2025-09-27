<?php
// Ne pas relancer session_start si déjà active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
// Les données seront chargées dynamiquement via AJAX
$conversations = [];
$messages = [];
?>

<style>
/* === DESIGN SYSTEM SUZOSKY - INTERFACE CHAT ADMIN === */
:root {
    /* Variables identiques à coursier.php */
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --warning-color: #ffc107;
    --danger-color: #E94560;
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
}

/* === HERO SECTION CHAT SUZOSKY === */
.chat-hero {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.chat-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-gold);
}

.hero-content h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    font-family: 'Montserrat', sans-serif;
}

.hero-content p {
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
    margin-bottom: 20px;
    font-weight: 500;
}

.hero-stats {
    display: flex;
    gap: 30px;
}

.hero-stat {
    text-align: center;
}

.hero-stat .stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
}

.hero-stat .stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero-decoration {
    font-size: 4rem;
    color: var(--primary-gold);
    opacity: 0.3;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* === INTERFACE CHAT PRINCIPAL === */
.chat-interface {
    display: flex;
    gap: 20px;
    height: 80vh;
    min-height: 700px;
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

/* === SIDEBAR CONVERSATIONS (25% de largeur) === */
.chat-sidebar {
    width: 25%;
    min-width: 320px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* === ZONE PRINCIPALE MESSAGES (60% de largeur) === */
.chat-main {
    width: 60%;
    display: flex;
    flex-direction: column;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    overflow: hidden;
}

/* === ZONE DÉTAILS (15% de largeur) === */
.chat-details {
    width: 15%;
    min-width: 250px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

/* === SIDEBAR CONVERSATIONS === */
.chat-sidebar {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--glass-border);
    background: rgba(255,255,255,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 60px;
}

.sidebar-header h3 {
    color: var(--primary-gold);
    font-weight: 700;
    font-size: 1rem;
    font-family: 'Montserrat', sans-serif;
}

.sidebar-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    background: var(--glass-bg);
    border: 2px solid var(--glass-border);
    border-radius: 10px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--primary-gold);
    font-size: 0.9rem;
    backdrop-filter: blur(10px);
}

.action-btn:hover {
    background: rgba(212, 168, 83, 0.15);
    border-color: var(--primary-gold);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.conversations {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
}

/* === ZONE PRINCIPALE MESSAGES === */
.chat-main {
    width: 60%;
    display: flex;
    flex-direction: column;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    overflow: hidden;
}

.chat-tabs {
    display: flex;
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid var(--glass-border);
    flex-shrink: 0;
}

.chat-tab {
    flex: 1;
    padding: 15px 20px;
    background: none;
    border: none;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    text-align: center;
}

.chat-tab.active {
    color: var(--primary-gold);
    background: rgba(212, 168, 83, 0.1);
}

.chat-tab.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-gold);
}

.chat-tab:hover:not(.active) {
    color: rgba(255,255,255,0.9);
    background: rgba(255,255,255,0.03);
}

#messagesContainer {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: rgba(0,0,0,0.1);
    min-height: 400px;
}

/* === ZONE DÉTAILS === */
.chat-details {
    width: 15%;
    min-width: 250px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

/* === CONVERSATIONS ITEMS === */
.conversation-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 10px;
    background: rgba(255,255,255,0.02);
    border: 1px solid transparent;
}

.conversation-item:hover {
    background: rgba(255,255,255,0.08);
    border-color: var(--glass-border);
    transform: translateX(5px);
}

.conversation-item.active {
    background: rgba(212, 168, 83, 0.15);
    border-color: var(--primary-gold);
    border-left: 4px solid var(--primary-gold);
}

.conversation-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--gradient-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: var(--primary-dark);
    font-weight: 700;
    font-size: 1.2rem;
}

.conversation-info {
    flex: 1;
}

.conversation-name {
    color: #FFFFFF;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 3px;
}

.conversation-preview {
    color: rgba(255,255,255,0.6);
    font-size: 0.8rem;
    font-weight: 400;
}

.conversation-meta {
    text-align: right;
    font-size: 0.75rem;
}

.conversation-time {
    color: rgba(255,255,255,0.5);
    margin-bottom: 3px;
}

.unread-count {
    background: var(--accent-red);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
}

/* === MESSAGES === */
.message {
    margin-bottom: 20px;
    display: flex;
    align-items: flex-end;
    gap: 12px;
    animation: messageSlideIn 0.3s ease;
}

.message.admin {
    justify-content: flex-end;
}

.message.admin .message-content {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border-radius: 20px 20px 8px 20px;
    max-width: 75%;
}

.message.client .message-content {
    background: rgba(255,255,255,0.12);
    color: #FFFFFF;
    border-radius: 20px 20px 20px 8px;
    max-width: 75%;
}

.message-content {
    padding: 15px 20px;
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    position: relative;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.message-content p {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.5;
    font-weight: 500;
}

.message-time {
    display: block;
    font-size: 0.75rem;
    opacity: 0.7;
    margin-top: 8px;
    font-weight: 400;
}

@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* === ZONE SAISIE MESSAGE === */
.message-input-container {
    padding: 20px;
    border-top: 1px solid var(--glass-border);
    background: rgba(255,255,255,0.03);
    display: flex;
    gap: 15px;
    align-items: end;
    flex-shrink: 0;
}

.message-input {
    flex: 1;
    background: var(--glass-bg);
    border: 2px solid var(--glass-border);
    border-radius: 15px;
    padding: 12px 18px;
    color: #FFFFFF;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.9rem;
    font-weight: 500;
    resize: none;
    min-height: 45px;
    max-height: 120px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.message-input:focus {
    outline: none;
    border-color: var(--primary-gold);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
    background: rgba(255,255,255,0.08);
}

.message-input::placeholder {
    color: rgba(255,255,255,0.5);
    font-style: italic;
}

.send-button {
    background: var(--gradient-gold);
    border: none;
    border-radius: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--primary-dark);
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.send-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
}

/* === RESPONSIVE === */
@media (max-width: 1400px) {
    .chat-interface {
        flex-direction: column;
        height: auto;
        gap: 15px;
    }
    
    .chat-sidebar {
        width: 100%;
        min-width: auto;
        max-height: 250px;
        order: 1;
    }
    
    .chat-main {
        width: 100%;
        min-height: 500px;
        order: 2;
    }
    
    .chat-details {
        width: 100%;
        min-width: auto;
        order: 3;
        max-height: 200px;
    }
}

@media (max-width: 968px) {
    .chat-interface {
        padding: 15px;
        gap: 10px;
    }
    
    .chat-sidebar {
        max-height: 200px;
    }
    
    .chat-main {
        min-height: 400px;
    }
    
    .chat-details {
        display: none;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 15px;
    }
}

@media (max-width: 768px) {
    .chat-hero {
        flex-direction: column;
        text-align: center;
        gap: 20px;
        padding: 20px;
    }
    
    .hero-decoration {
        font-size: 3rem;
    }
    
    .hero-content h1 {
        font-size: 1.5rem;
    }
    
    .chat-interface {
        padding: 15px;
        height: 70vh;
        min-height: 500px;
    }
    
    .chat-main {
        min-height: 350px;
    }
}
</style>

<!-- Hero Section Chat - Style Suzosky -->
<div class="chat-hero">
    <div class="hero-content">
        <h1><i class="fas fa-comments"></i> Centre de Communication Suzosky</h1>
        <p>Gérez toutes vos conversations clients en temps réel avec l'interface de marque Suzosky</p>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="stat-value"><?= count($conversations) ?></span>
                <span class="stat-label">Conversations</span>
            </div>
            <div class="hero-stat">
                <span class="stat-value"><?= array_sum(array_column($conversations, 'unread_count')) ?></span>
                <span class="stat-label">Non Lus</span>
            </div>
        </div>
    </div>
    <div class="hero-decoration">
        <i class="fas fa-message"></i>
    </div>
</div>

<!-- Interface Chat Principal - Style Suzosky -->
<div class="chat-interface">
    <!-- Sidebar des conversations -->
    <div class="chat-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-inbox"></i> Conversations</h3>
            <div class="sidebar-actions">
                <button class="action-btn refresh" onclick="refreshConversations()" title="Actualiser">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="action-btn filter" onclick="toggleFilter()" title="Filtrer">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </div>
        <div class="quick-filters">
            <!-- Les filtres rapides seront ajoutés ici -->
        </div>
        <div class="conversations" id="conversationsList"></div>
    </div>

    <!-- Zone principale des messages -->
    <div class="chat-main">
        <div class="chat-tabs">
            <button class="chat-tab active" data-tab="particulier">Particuliers</button>
            <button class="chat-tab" data-tab="business">Business</button>
            <button class="chat-tab" data-tab="agent">Agents</button>
        </div>
        <div class="messages" id="messagesContainer">
            <!-- Zone d'affichage des messages avec indicateur de type actuel -->
            <div class="chat-type-indicator" style="text-align: center; padding: 15px; margin-bottom: 20px; background: rgba(212, 168, 83, 0.1); border: 1px solid var(--primary-gold); border-radius: 12px;">
                <i class="fas fa-user" style="color: var(--primary-gold); font-size: 1.2rem; margin-right: 8px;"></i>
                <span style="color: var(--primary-gold); font-weight: 700; font-family: 'Montserrat', sans-serif;">
                    Conversations avec les clients particuliers
                </span>
            </div>
            
            <div id="messagesList"></div>
        </div>
        <div class="message-input-container">
            <textarea 
                class="message-input" 
                id="messageInput" 
                placeholder="Tapez votre message..." 
                onkeydown="handleKeyPress(event)"
                oninput="autoResize(this)"></textarea>
            <button class="send-button" onclick="sendMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <!-- Zone détails/actions -->
    <div class="chat-details">
        <div style="text-align: center; color: rgba(255,255,255,0.6); font-size: 0.9rem;">
            <i class="fas fa-info-circle" style="color: var(--primary-gold); margin-bottom: 10px; font-size: 1.5rem; display: block;"></i>
            <p>Sélectionnez une conversation pour voir les détails du client</p>
        </div>
    </div>
</div>

<script>
// === CHAT JAVASCRIPT SUZOSKY === 

let activeConversationId = null; // Conversation active
let convPollingTimer = null;
let msgPollingTimer = null;

async function fetchJSON(url, payload) {
    const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload||{}) });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function getActiveTabType() {
    const t = document.querySelector('.chat-tab.active');
    const val = t ? (t.dataset.tab || 'particulier') : 'particulier';
    return (val === 'agent') ? 'agents' : val; // normaliser pour l'API
}

// Gestion des onglets de chat
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets chat
    document.querySelectorAll('.chat-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Retirer la classe active de tous les onglets
            document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
            
            // Ajouter la classe active à l'onglet cliqué
            this.classList.add('active');
            
            const tabType = this.dataset.tab;
            loadTabContent(tabType);
            loadConversations((tabType === 'agent') ? 'agents' : tabType);
        });
    });
    
    // Auto-resize du textarea
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            autoResize(this);
        });
    }
    
    // Charger les conversations du type actif
    loadConversations();

    // Polling périodique des conversations
    if (convPollingTimer) clearInterval(convPollingTimer);
    convPollingTimer = setInterval(() => {
        loadConversations();
    }, 4000);
});

function loadTabContent(tabType) {
    const indicator = document.querySelector('.chat-type-indicator');
    const iconMap = {
        'particulier': { icon: 'fa-user', text: 'Conversations avec les clients particuliers' },
        'business': { icon: 'fa-building', text: 'Conversations avec les clients business' },
        'agent': { icon: 'fa-users', text: 'Conversations avec les agents' }
    };
    
    if (indicator && iconMap[tabType]) {
        const config = iconMap[tabType];
        indicator.innerHTML = `
            <i class="fas ${config.icon}" style="color: var(--primary-gold); font-size: 1.2rem; margin-right: 8px;"></i>
            <span style="color: var(--primary-gold); font-weight: 700; font-family: 'Montserrat', sans-serif;">
                ${config.text}
            </span>
        `;
    }
    
    // Animation de changement
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.style.opacity = '0.7';
        setTimeout(() => {
            messagesContainer.style.opacity = '1';
        }, 200);
    }
    
    console.log('Chargement du contenu pour:', tabType);
    // Ici vous chargerez le contenu spécifique selon le type
}

function formatTime(ts) {
    if (!ts) return '';
    try {
        const d = new Date(ts);
        return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    } catch { return ''; }
}

async function loadConversations(type = getActiveTabType()) {
    const list = document.getElementById('conversationsList');
    if (!list) return;
    list.innerHTML = '<div style="color:#bbb; padding:10px;">Chargement des conversations...</div>';
    try {
        const data = await fetchJSON('../api/chat/get_conversations.php', { type });
        if (!data.success) throw new Error(data.message||'Erreur API');
        const items = data.data || [];
        if (!items.length) {
            list.innerHTML = '<div style="color:#bbb; padding:10px;">Aucune conversation</div>';
            return;
        }
        list.innerHTML = '';
        items.forEach(c => {
            const div = document.createElement('div');
            div.className = 'conversation-item' + (c.id === activeConversationId ? ' active' : '');
            div.dataset.conversationId = c.id;
            div.onclick = () => openConversation(c.id, type);
            div.innerHTML = `
                <div class="conversation-avatar">${(c.avatar||'').slice(0,2)}</div>
                <div class="conversation-info">
                    <div class="conversation-name">${escapeHtml(c.client_name||('ID '+c.client_id))}</div>
                    <div class="conversation-preview">${escapeHtml((c.last_message||'').slice(0,60))}</div>
                </div>
                <div class="conversation-meta">
                    <div class="conversation-time">${formatTime(c.timestamp)}</div>
                    ${c.unread_count>0 ? `<div class="unread-count">${c.unread_count}</div>` : ''}
                </div>
            `;
            list.appendChild(div);
        });
    } catch (e) {
        list.innerHTML = '<div style="color:#f88; padding:10px;">Erreur de chargement</div>';
    }
}

async function openConversation(conversationId, type = getActiveTabType()) {
    activeConversationId = conversationId;
    // Retirer la classe active de toutes les conversations
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Ajouter la classe active à la conversation sélectionnée
    const conversationElement = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (conversationElement) {
        conversationElement.classList.add('active');
    }
    
    // Mettre à jour la zone de détails
    updateChatDetails(conversationId);
    // Charger messages
    await loadMessages(conversationId, type);
    // Démarrer le polling des messages pour la conversation active
    if (msgPollingTimer) clearInterval(msgPollingTimer);
    msgPollingTimer = setInterval(() => {
        if (activeConversationId) loadMessages(activeConversationId, type);
    }, 4000);
    // Marquer comme lu côté serveur et rafraîchir la liste
    try {
        await fetchJSON('../api/chat/mark_read.php', { conversation_id: conversationId });
        await loadConversations(type);
    } catch (e) { console.warn('mark_read failed', e); }
}

function updateChatDetails(conversationId) {
    const detailsContainer = document.querySelector('.chat-details');
    
    // Contenu détails selon la conversation
    const clientDetails = {
        1: {
            name: 'Jean Dupont',
            phone: '+225 07 12 34 56 78',
            email: 'jean.dupont@email.com',
            orders: 5,
            status: 'Client VIP'
        },
        2: {
            name: 'Marie Bernard',
            phone: '+225 05 98 76 54 32',
            email: 'marie.bernard@email.com',
            orders: 2,
            status: 'Client Standard'
        }
    };
    
    const client = clientDetails[conversationId];
    if (client) {
        detailsContainer.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 60px; height: 60px; background: var(--gradient-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: var(--primary-dark); font-weight: 700; font-size: 1.5rem;">
                    ${client.name.split(' ').map(n => n[0]).join('')}
                </div>
                <h3 style="color: var(--primary-gold); margin-bottom: 5px; font-weight: 700; font-family: 'Montserrat', sans-serif;">${client.name}</h3>
                <span style="color: rgba(255,255,255,0.6); font-size: 0.8rem; padding: 3px 8px; background: rgba(212, 168, 83, 0.2); border-radius: 10px; font-weight: 600;">${client.status}</span>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 8px; backdrop-filter: blur(10px);">
                    <i class="fas fa-phone" style="color: var(--primary-gold); width: 16px;"></i>
                    <span style="font-size: 0.85rem; color: rgba(255,255,255,0.8); font-weight: 500;">${client.phone}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 8px; backdrop-filter: blur(10px);">
                    <i class="fas fa-envelope" style="color: var(--primary-gold); width: 16px;"></i>
                    <span style="font-size: 0.85rem; color: rgba(255,255,255,0.8); font-weight: 500;">${client.email}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 8px; backdrop-filter: blur(10px);">
                    <i class="fas fa-shopping-bag" style="color: var(--primary-gold); width: 16px;"></i>
                    <span style="font-size: 0.85rem; color: rgba(255,255,255,0.8); font-weight: 500;">${client.orders} commandes</span>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <button onclick="callClient()" style="background: var(--gradient-gold); color: var(--primary-dark); border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; font-size: 0.85rem; font-family: 'Montserrat', sans-serif; box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);">
                    <i class="fas fa-phone"></i> Appeler Client
                </button>
                <button onclick="viewOrderHistory()" style="background: rgba(255,255,255,0.08); color: #FFFFFF; border: 1px solid var(--glass-border); padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 0.85rem; font-family: 'Montserrat', sans-serif; backdrop-filter: blur(10px);">
                    <i class="fas fa-history"></i> Historique
                </button>
                <button onclick="attachFile()" style="background: rgba(255,255,255,0.08); color: #FFFFFF; border: 1px solid var(--glass-border); padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 0.85rem; font-family: 'Montserrat', sans-serif; backdrop-filter: blur(10px);">
                    <i class="fas fa-paperclip"></i> Joindre Fichier
                </button>
            </div>
        `;
    }
}

async function loadMessages(conversationId, type = getActiveTabType()) {
    const container = document.getElementById('messagesContainer');
    const box = document.getElementById('messagesList');
    if (!box) return;
    box.innerHTML = '<div style="color:#bbb; padding:10px;">Chargement des messages...</div>';
    try {
        const data = await fetchJSON('../api/chat/get_messages.php', { conversation_id: conversationId, type });
        if (!data.success) throw new Error(data.message||'Erreur API');
        box.innerHTML = '';
        (data.data||[]).forEach(m => {
            const who = (m.sender_type === 'admin' || m.sender_type === 'agent' || m.sender_type === 'support') ? 'admin' : 'client';
            const time = m.timestamp ? new Date(m.timestamp).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'}) : '';
            const row = document.createElement('div');
            row.className = `message ${who}`;
            row.innerHTML = `<div class="message-content"><p>${escapeHtml(m.message||'')}</p><span class="message-time">${time}</span></div>`;
            box.appendChild(row);
        });
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    } catch (e) {
        box.innerHTML = '<div style=\"color:#f88; padding:10px;\">Erreur de chargement</div>';
    }
}

async function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    try {
        const resp = await fetchJSON('../api/chat/send_message.php', {
            conversation_id: activeConversationId,
            sender_type: 'admin',
            sender_id: 1,
            message
        });
        if (!resp.success) throw new Error(resp.message||'Erreur API');
        messageInput.value = '';
        autoResize(messageInput);
        await loadMessages(activeConversationId);
        await loadConversations();
    } catch (e) {
        alert('Erreur envoi message');
    }
}

function addMessageToChat(senderType, message) {
    const messagesContainer = document.getElementById('messagesContainer');
    const messageElement = document.createElement('div');
    messageElement.className = `message ${senderType}`;
    
    const currentTime = new Date().toLocaleTimeString('fr-FR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    
    messageElement.innerHTML = `
        <div class="message-content">
            <p>${message}</p>
            <span class="message-time">${currentTime}</span>
        </div>
    `;
    
    messagesContainer.appendChild(messageElement);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    // Animation d'apparition
    messageElement.style.opacity = '0';
    messageElement.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        messageElement.style.transition = 'all 0.3s ease';
        messageElement.style.opacity = '1';
        messageElement.style.transform = 'translateY(0)';
    }, 10);
}

function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
}

function refreshConversations() {
    // Animation de rafraîchissement style Suzosky
    const refreshBtn = document.querySelector('.action-btn.refresh i');
    if (refreshBtn) {
        refreshBtn.style.animation = 'spin 1s linear';
        refreshBtn.style.color = 'var(--primary-gold)';
        
        setTimeout(() => {
            refreshBtn.style.animation = '';
            refreshBtn.style.color = '';
        }, 1000);
    }
    
    console.log('Rafraîchissement des conversations Suzosky...');
}

function toggleFilter() {
    console.log('Basculer les filtres...');
}

function viewClientInfo() {
    console.log('Voir les informations du client...');
}

function viewOrderHistory() {
    // Animation de feedback
    event.target.style.transform = 'scale(0.95)';
    setTimeout(() => {
        event.target.style.transform = 'scale(1)';
    }, 150);
    
    alert('📊 Historique des commandes - Fonctionnalité à implémenter\n\nCette section affichera :\n• Toutes les commandes passées\n• Statuts de livraison\n• Évaluations client');
}

function callClient() {
    // Animation de feedback
    event.target.style.transform = 'scale(0.95)';
    setTimeout(() => {
        event.target.style.transform = 'scale(1)';
    }, 150);
    
    alert('📞 Appel client - Fonctionnalité à implémenter\n\nCette fonction permettra :\n• Lancement d\'appel direct\n• Intégration téléphonie\n• Historique des appels');
}

function attachFile() {
    // Animation de feedback
    event.target.style.transform = 'scale(0.95)';
    setTimeout(() => {
        event.target.style.transform = 'scale(1)';
    }, 150);
    
    alert('📎 Joindre un fichier - Fonctionnalité à implémenter\n\nTypes de fichiers supportés :\n• Images (PNG, JPG)\n• Documents (PDF)\n• Fichiers audio');
}

// Animation CSS keyframes style Suzosky
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .conversation-item.active {
        background: rgba(212, 168, 83, 0.15) !important;
        border-left: 4px solid var(--primary-gold) !important;
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
    }
    
    .chat-details button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(0,0,0,0.2) !important;
    }
    
    .send-button:active {
        transform: translateY(0) !important;
    }
    
    .action-btn:hover {
        transform: translateY(-2px) scale(1.05) !important;
    }
    
    /* Amélioration du scroll */
    .conversations::-webkit-scrollbar {
        width: 6px;
    }
    
    .conversations::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 3px;
    }
    
    .conversations::-webkit-scrollbar-thumb {
        background: var(--primary-gold);
        border-radius: 3px;
    }
    
    .conversations::-webkit-scrollbar-thumb:hover {
        background: #E8C468;
    }
    
    #messagesContainer::-webkit-scrollbar {
        width: 6px;
    }
    
    #messagesContainer::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 3px;
    }
    
    #messagesContainer::-webkit-scrollbar-thumb {
        background: var(--primary-gold);
        border-radius: 3px;
    }
    
    #messagesContainer::-webkit-scrollbar-thumb:hover {
        background: #E8C468;
    }
`;
document.head.appendChild(style);
</script>
