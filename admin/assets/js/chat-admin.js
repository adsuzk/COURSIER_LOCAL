/**
 * Chat Admin Logic: Load and manage chat conversations/messages across three channels.
 * Usage: Include this script in admin/chat.php after including necessary HTML elements.
 */

const chatBuildUrl = (typeof window !== 'undefined' && window.suzoskyBuildUrl)
    ? window.suzoskyBuildUrl
    : (relativePath = '') => {
            const path = (typeof window !== 'undefined' && window.location && window.location.pathname)
                ? window.location.pathname
                : '';
            const base = (typeof window !== 'undefined' && typeof window.ROOT_PATH === 'string' && window.ROOT_PATH.length)
                ? window.ROOT_PATH.replace(/\/$/, '')
                : (path ? path.replace(/\\/g, '/').replace(/\/[^\/]*$/, '') : '');
            if (!relativePath) return base || '';
            const normalized = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
            return `${base}${normalized}` || normalized;
        };

document.addEventListener('DOMContentLoaded', () => {
    let activeConversationId = null;
    let activeType = 'particulier';

    const listEl = document.getElementById('conversationsList');
    const messagesEl = document.getElementById('messagesContainer');
    const tabs = document.querySelectorAll('.chat-tab');

    function loadConversations() {
    fetch(chatBuildUrl('/api/chat/get_conversations.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: activeType })
        })
        .then(res => res.json())
        .then(data => {
            listEl.innerHTML = '';
            if (data.success) {
                data.data.forEach(conv => {
                    const item = document.createElement('div');
                    item.className = 'conversation-item' + (conv.unread_count ? ' unread' : '');
                    item.dataset.conversationId = conv.id;
                    item.innerHTML = `
                        <div class="conversation-avatar">${conv.avatar}<div class="status-indicator"></div></div>
                        <div class="conversation-content"><h4>${conv.client_name}</h4><p>${new Date(conv.timestamp).toLocaleString()}</p></div>
                    `;
                    item.onclick = () => openConversation(conv.id);
                    listEl.appendChild(item);
                });
            }
        })
        .catch(err => console.error('Error loading conversations:', err));
    }

    function openConversation(id) {
        activeConversationId = id;
        document.querySelectorAll('.conversation-item').forEach(el => el.classList.toggle('active', el.dataset.conversationId == id));
        loadMessages();
    }

    function loadMessages() {
        if (!activeConversationId) return;
    fetch(chatBuildUrl('/api/chat/get_messages.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: activeConversationId, type: activeType })
        })
        .then(res => res.json())
        .then(data => {
            messagesEl.innerHTML = '';
            if (data.success) {
                data.data.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'message ' + msg.sender_type;
                    div.innerHTML = `<div class="message-content"><p>${msg.message}</p><span class="message-time">${new Date(msg.timestamp).toLocaleTimeString()}</span></div>`;
                    messagesEl.appendChild(div);
                });
            }
        })
        .catch(err => console.error('Error loading messages:', err));
    }

    function switchChatTab(e) {
        tabs.forEach(btn => btn.classList.toggle('active', btn === e.target));
        activeType = e.target.dataset.tab;
        activeConversationId = null;
        messagesEl.innerHTML = '';
        loadConversations();
    }

    tabs.forEach(btn => btn.addEventListener('click', switchChatTab));

    // Polling new messages
    setInterval(() => {
        if (activeConversationId) loadMessages();
    }, 5000);

    // Initial load
    loadConversations();
});
