<?php
// sections/js_chat_support.php - Chat public connecté aux APIs backend
?>
    <script>
    // État global du chat public avec IA
    let isChatOpen = false;
    let activeChatType = 'particulier'; // public: un seul flux par défaut
    let conversationIds = {}; // { [type]: number }
    let pollingTimers = {};    // { [type]: timerId }
    let lastMessageIds = {};   // { [type]: lastMessageId }
    let unreadCount = 0;
    
    // État IA et réclamations
    let aiEnabled = true;
    let complaintWorkflow = null; // Processus de réclamation en cours
    let waitingForAIResponse = false;

    // Helpers
    const apiBase = ROOT_PATH ? (ROOT_PATH.replace(/\/$/, '') + '/api/chat') : '/api/chat';

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    async function fetchJSON(url, options = {}) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            ...options
        });
        return res.json();
    }

    // UI actions
    function toggleChat() {
        const win = document.getElementById('chatWindow');
        if (!win) return;
        isChatOpen = !isChatOpen;
        win.style.display = isChatOpen ? 'flex' : 'none';
        if (isChatOpen) {
            unreadCount = 0;
            updateNotificationBadge();
            setTimeout(scrollChatToBottom, 50);
        }
    }

    function updateNotificationBadge() {
        const badge = document.querySelector('.chat-toggle .chat-notification');
        if (!badge) return;
        if (unreadCount > 0) {
            badge.style.display = 'flex';
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        } else {
            badge.style.display = 'none';
        }
    }

    function scrollChatToBottom() {
        const container = document.getElementById('chatMessages');
        if (container) container.scrollTop = container.scrollHeight;
    }

    function getAvailableTabs() { return ['particulier']; }

    function switchChatTab(tab) {
        const prev = activeChatType;
        activeChatType = tab;
        // Toggle classes
        // plus d’onglets à styler côté public
        // Stop polling for all other types
    getAvailableTabs().forEach(t => { if (t !== tab) stopPolling(t); });
        // Init conversation, load messages, and start polling for the active tab
        ensureConversation(tab).then(() => {
            loadMessages(tab);
            startPolling(tab);
        });
    }

    // Identity
    function getOrCreateGuestId() {
        const key = 'chat_guest_id';
        let id = localStorage.getItem(key);
        if (!id) {
            // 9 digits max to stay within 32-bit signed int
            id = String(Math.floor(Date.now() % 1000000000));
            localStorage.setItem(key, id);
        }
        return parseInt(id, 10);
    }

    function resolveClientIdForType(type) {
        try {
            if (window.isLoggedIn && window.currentUser && Number.isInteger(window.currentUser.id)) {
                return window.currentUser.id;
            }
        } catch (_) {}
        return getOrCreateGuestId();
    }

    // Backend wiring
    async function ensureConversation(type) {
        if (conversationIds[type]) return conversationIds[type];
        let client_id = resolveClientIdForType(type);
        let resp = null;
        try {
            resp = await fetchJSON(apiBase + '/init.php', {
                body: JSON.stringify({ type, client_id })
            });
        } catch (e) {
            console.error('Init error (network):', e);
        }
        if (resp && resp.success && resp.conversation_id) {
            conversationIds[type] = resp.conversation_id;
            localStorage.setItem('chat_conversation_' + type, String(resp.conversation_id));
            return resp.conversation_id;
        }
        // Fallback: régénérer un guest_id et réessayer une fois
        try {
            const freshId = String(Math.floor(Date.now() % 1000000000));
            localStorage.setItem('chat_guest_id', freshId);
            client_id = parseInt(freshId, 10);
            // supprimer éventuelle conversation périmée
            localStorage.removeItem('chat_conversation_' + type);
            const retry = await fetchJSON(apiBase + '/init.php', {
                body: JSON.stringify({ type, client_id })
            });
            if (retry && retry.success && retry.conversation_id) {
                conversationIds[type] = retry.conversation_id;
                localStorage.setItem('chat_conversation_' + type, String(retry.conversation_id));
                return retry.conversation_id;
            }
            console.error('Init chat failed (retry):', retry && retry.message);
        } catch (e) {
            console.error('Init retry error:', e);
        }
        return null;
    }

    function restorePersistedConversations() {
        getAvailableTabs().forEach(t => {
            const val = localStorage.getItem('chat_conversation_' + t);
            if (val) conversationIds[t] = parseInt(val, 10);
        });
    }

    function renderMessages(list) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        container.innerHTML = '';
        let maxId = lastMessageIds[activeChatType] || 0;
        list.forEach(m => {
            const who = (m.sender_type === 'admin') ? 'admin' : 'user';
            const wrapper = document.createElement('div');
            wrapper.className = `chat-message ${who}`;
            const time = m.timestamp ? new Date(m.timestamp) : new Date();
            wrapper.innerHTML = `
                <div class="message-header">
                    <span>${who === 'user' ? 'Vous' : 'Support'}</span>
                    <span>${time.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'})}</span>
                </div>
                <div class="message-content">${escapeHtml(m.message)}</div>
            `;
            container.appendChild(wrapper);
            if (m.id && m.id > maxId) maxId = m.id;
        });
        lastMessageIds[activeChatType] = maxId;
        scrollChatToBottom();
    }

    async function loadMessages(type = activeChatType) {
        const conv = conversationIds[type];
        if (!conv) return;
        try {
            const resp = await fetchJSON(apiBase + '/get_messages.php', {
                body: JSON.stringify({ conversation_id: conv })
            });
            if (resp && resp.success) {
                renderMessages(resp.data || []);
            }
        } catch (err) {
            console.error('loadMessages error', err);
        }
    }

    async function sendMessage() {
        const input = document.getElementById('messageInput');
        if (!input) return;
        const text = input.value.trim();
        if (!text) return;
        const conv = await ensureConversation(activeChatType);
        if (!conv) { alert("Impossible d'initialiser la discussion."); return; }
        const sender_id = resolveClientIdForType(activeChatType);
        // Affichage optimiste côté UI
        const container = document.getElementById('chatMessages');
        const temp = document.createElement('div');
        temp.className = 'chat-message user';
        temp.innerHTML = `
            <div class="message-header">
                <span>Vous</span>
                <span>${new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}</span>
            </div>
            <div class="message-content">${escapeHtml(text)}</div>
        `;
        container.appendChild(temp);
        scrollChatToBottom();
        input.value = '';
        try {
            const resp = await fetchJSON(apiBase + '/send_message.php', {
                body: JSON.stringify({
                    conversation_id: conv,
                    sender_type: 'client',
                    sender_id,
                    message: text
                })
            });
            if (!(resp && resp.success)) {
                alert("Envoi échoué: " + (resp && resp.message ? resp.message : 'Erreur inconnue'));
            } else {
                // Recharge pour récupérer l'ID, l'horodatage serveur et maintenir la synchro
                await loadMessages();
            }
        } catch (err) {
            console.error('sendMessage error', err);
            alert("Erreur réseau pendant l'envoi du message.");
        }
    }

    function startPolling(type) {
        stopPolling(type);
        pollingTimers[type] = setInterval(async () => {
            const before = lastMessageIds[type] || 0;
            await loadMessages(type);
            const after = lastMessageIds[type] || 0;
            if (!isChatOpen && after > before) {
                // New messages arrived while closed
                unreadCount += (after - before);
                updateNotificationBadge();
            }
        }, 4000);
    }

    function stopPolling(type) {
        if (pollingTimers[type]) {
            clearInterval(pollingTimers[type]);
            delete pollingTimers[type];
        }
    }

    // Setup on load
    document.addEventListener('DOMContentLoaded', async () => {
        restorePersistedConversations();
        // Attach Enter key on input
        const input = document.getElementById('messageInput');
        if (input) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
        // Préparer le premier onglet disponible
        const tabs = getAvailableTabs();
        if (tabs.length > 0 && !tabs.includes(activeChatType)) activeChatType = tabs[0];
        await ensureConversation(activeChatType);
        await loadMessages(activeChatType);
        startPolling(activeChatType);
    });
    </script>
