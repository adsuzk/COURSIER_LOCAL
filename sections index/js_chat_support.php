<?php
// sections/js_chat_support.php - Système de chat et support client
?>
    <script>
    // Variables globales du chat
    let chatSocket = null;
    let chatMessages = [];
    let isChatOpen = false;
    let unreadMessages = 0;
    let isTyping = false;
    
    // Initialisation du chat
    function initializeChat() {
        // Vérifier si le chat est supporté
        if (!WebSocket) {
            console.warn('WebSocket non supporté, chat désactivé');
            return;
        }
        
        // Charger les messages précédents
        loadChatHistory();
        
        // Configurer les événements
        setupChatEvents();
        
        // Démarrer la connexion WebSocket (simulée pour ce demo)
        // connectChatSocket();
    }
    
    // Afficher/masquer le chat
    function toggleChat() {
        const chatWidget = document.getElementById('chatWidget');
        const chatButton = document.getElementById('chatButton');
        
        if (isChatOpen) {
            chatWidget.style.display = 'none';
            chatButton.innerHTML = '💬 Support';
            isChatOpen = false;
        } else {
            chatWidget.style.display = 'block';
            chatButton.innerHTML = '✖️ Fermer';
            isChatOpen = true;
            unreadMessages = 0;
            updateChatBadge();
            
            // Faire défiler vers le bas
            setTimeout(() => {
                scrollChatToBottom();
            }, 100);
        }
    }
    
    // Envoyer un message
    function sendChatMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Ajouter le message de l'utilisateur
        addChatMessage({
            text: message,
            sender: 'user',
            timestamp: new Date(),
            id: Date.now()
        });
        
        // Vider le champ de saisie
        input.value = '';
        
        // Sauvegarder dans l'historique
        saveChatMessage({
            text: message,
            sender: 'user',
            timestamp: new Date().toISOString(),
            id: Date.now()
        });
        
        // Simuler une réponse automatique
        setTimeout(() => {
            sendAutoReply(message);
        }, 1000 + Math.random() * 2000);
        
        // Envoyer via WebSocket (si connecté)
        if (chatSocket && chatSocket.readyState === WebSocket.OPEN) {
            chatSocket.send(JSON.stringify({
                type: 'message',
                text: message,
                sender: currentUser ? currentUser.id : 'guest',
                timestamp: new Date().toISOString()
            }));
        }
    }
    
    // Ajouter un message au chat
    function addChatMessage(message) {
        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${message.sender}`;
        messageDiv.dataset.messageId = message.id;
        
        const time = new Date(message.timestamp).toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-text">${escapeHtml(message.text)}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
        
        messagesContainer.appendChild(messageDiv);
        chatMessages.push(message);
        
        // Animation d'apparition
        setTimeout(() => {
            messageDiv.classList.add('visible');
        }, 50);
        
        // Faire défiler vers le bas
        scrollChatToBottom();
        
        // Mettre à jour le badge si le chat est fermé
        if (!isChatOpen && message.sender === 'support') {
            unreadMessages++;
            updateChatBadge();
        }
    }
    
    // Réponses automatiques simulées
    function sendAutoReply(userMessage) {
        const replies = [
            "Merci pour votre message ! Un agent va vous répondre rapidement.",
            "Nous avons bien reçu votre demande. Pouvez-vous nous donner plus de détails ?",
            "Notre équipe support est en ligne et va traiter votre demande.",
            "Bonjour ! Comment puis-je vous aider aujourd'hui ?",
            "Merci de nous avoir contactés. Votre demande est importante pour nous."
        ];
        
        // Réponses spécifiques selon le contenu
        let reply = replies[Math.floor(Math.random() * replies.length)];
        
        if (userMessage.toLowerCase().includes('prix') || userMessage.toLowerCase().includes('tarif')) {
            reply = "Nos tarifs démarrent à 1000 FCFA pour les livraisons normales. Le prix exact dépend de la distance et de la priorité.";
        } else if (userMessage.toLowerCase().includes('temps') || userMessage.toLowerCase().includes('délai')) {
            reply = "Les délais de livraison varient de 30 minutes à 2 heures selon la priorité choisie et la distance.";
        } else if (userMessage.toLowerCase().includes('paiement')) {
            reply = "Nous acceptons Mobile Money (Orange, MTN, Moov) et les espèces à la livraison.";
        } else if (userMessage.toLowerCase().includes('suivre') || userMessage.toLowerCase().includes('suivi')) {
            reply = "Vous pouvez suivre votre commande en temps réel depuis votre compte. Un SMS vous sera également envoyé.";
        }
        
        const autoReply = {
            text: reply,
            sender: 'support',
            timestamp: new Date(),
            id: Date.now() + 1
        };
        
        // Afficher l'indicateur de frappe
        showTypingIndicator();
        
        setTimeout(() => {
            hideTypingIndicator();
            addChatMessage(autoReply);
            saveChatMessage({
                ...autoReply,
                timestamp: autoReply.timestamp.toISOString()
            });
        }, 1500);
    }
    
    // Indicateur de frappe
    function showTypingIndicator() {
        const messagesContainer = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chat-message support typing-indicator';
        typingDiv.id = 'typingIndicator';
        
        typingDiv.innerHTML = `
            <div class="message-content">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="message-text">L'agent écrit...</div>
            </div>
        `;
        
        messagesContainer.appendChild(typingDiv);
        scrollChatToBottom();
        isTyping = true;
    }
    
    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
        isTyping = false;
    }
    
    // Badge de messages non lus
    function updateChatBadge() {
        const badge = document.querySelector('.chat-badge');
        if (unreadMessages > 0) {
            badge.style.display = 'block';
            badge.textContent = unreadMessages > 99 ? '99+' : unreadMessages;
        } else {
            badge.style.display = 'none';
        }
    }
    
    // Faire défiler le chat vers le bas
    function scrollChatToBottom() {
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Configurer les événements du chat
    function setupChatEvents() {
        const chatToggle = document.getElementById('chatToggle');
        if (chatToggle) {
            chatToggle.addEventListener('click', toggleChat);
        }
        
        const chatInput = document.getElementById('chatInput');
        const sendButton = document.getElementById('sendChatMessage');
        
        // Envoyer avec Entrée
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
        
        // Envoyer avec le bouton
        sendButton.addEventListener('click', sendChatMessage);
        
        // Auto-redimensionnement du textarea
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
    }
    
    // Sauvegarder l'historique du chat
    function saveChatMessage(message) {
        const chatHistory = JSON.parse(localStorage.getItem('chatHistory') || '[]');
        chatHistory.push(message);
        
        // Garder seulement les 100 derniers messages
        if (chatHistory.length > 100) {
            chatHistory.splice(0, chatHistory.length - 100);
        }
        
        localStorage.setItem('chatHistory', JSON.stringify(chatHistory));
    }
    
    // Charger l'historique du chat
    function loadChatHistory() {
        const chatHistory = JSON.parse(localStorage.getItem('chatHistory') || '[]');
        
        chatHistory.forEach(message => {
            addChatMessage({
                ...message,
                timestamp: new Date(message.timestamp)
            });
        });
        
        if (chatHistory.length > 0) {
            scrollChatToBottom();
        }
    }
    
    // Vider l'historique du chat
    function clearChatHistory() {
        if (confirm('Êtes-vous sûr de vouloir effacer l\'historique du chat ?')) {
            localStorage.removeItem('chatHistory');
            document.getElementById('chatMessages').innerHTML = '';
            chatMessages = [];
            showMessage('Historique du chat effacé', 'info');
        }
    }
    
    // Connexion WebSocket (simulée)
    function connectChatSocket() {
        try {
            // URL WebSocket à adapter selon votre serveur
            chatSocket = new WebSocket('wss://your-websocket-server.com/chat');
            
            chatSocket.onopen = function(event) {
                console.log('Chat WebSocket connecté');
                
                // S'identifier si connecté
                if (isLoggedIn) {
                    chatSocket.send(JSON.stringify({
                        type: 'auth',
                        user_id: currentUser.id,
                        user_name: currentUser.name
                    }));
                }
            };
            
            chatSocket.onmessage = function(event) {
                const data = JSON.parse(event.data);
                
                if (data.type === 'message') {
                    addChatMessage({
                        text: data.text,
                        sender: 'support',
                        timestamp: new Date(data.timestamp),
                        id: data.id
                    });
                }
            };
            
            chatSocket.onerror = function(error) {
                console.error('Erreur WebSocket:', error);
            };
            
            chatSocket.onclose = function(event) {
                console.log('Chat WebSocket fermé');
                
                // Tentative de reconnexion après 5 secondes
                setTimeout(() => {
                    if (!chatSocket || chatSocket.readyState === WebSocket.CLOSED) {
                        connectChatSocket();
                    }
                }, 5000);
            };
            
        } catch (error) {
            console.error('Impossible de se connecter au chat:', error);
        }
    }
    
    // Fonction utilitaire pour échapper le HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Démarrer le chat quand la page est chargée
    document.addEventListener('DOMContentLoaded', function() {
        initializeChat();
    });
    </script>
