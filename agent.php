<?php
require_once __DIR__ . '/../lib/util.php';
/**
 * Interface Agent Suzosky - Chat Support
 * =====================================
 * Interface pour les agents internes avec système de chat support
 */

// Configuration et sécurité
session_start();
require_once 'config_secure.php';

// Vérification de l'authentification agent
if (!isset($_SESSION['agent_id']) || !isset($_SESSION['agent_logged_in'])) {
    header('Location: login_agent.php');
    exit;
}

$agent_id = $_SESSION['agent_id'];
$agent_name = $_SESSION['agent_name'] ?? "Agent #$agent_id";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suzosky Agent - Interface</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    
    <style>
        :root {
            --primary-dark: #1a1a1a;
            --secondary-dark: #2d2d2d;
            --primary-gold: #D4A853;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --accent-blue: #4FC3F7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-dark) 100%);
            color: white;
            min-height: 100vh;
        }

        .agent-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .agent-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .agent-avatar {
            width: 50px;
            height: 50px;
            background: var(--primary-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--primary-dark);
        }

        .agent-details h2 {
            color: var(--primary-gold);
            margin-bottom: 5px;
        }

        .agent-status {
            color: #4CAF50;
            font-size: 0.9rem;
        }

        .main-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            height: calc(100vh - 90px);
        }

        .sidebar {
            background: var(--glass-bg);
            border-right: 1px solid var(--glass-border);
            padding: 20px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(212, 168, 83, 0.2);
            color: var(--primary-gold);
        }

        .nav-item i {
            margin-right: 12px;
            width: 20px;
        }

        .main-content {
            padding: 30px;
            overflow-y: auto;
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        .section-header {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary-gold), #F4C65A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        /* Chat Styles */
        .chat-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--primary-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(212, 168, 83, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .chat-toggle:hover {
            transform: scale(1.1);
        }

        .chat-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4444;
            color: white;
            font-size: 0.7rem;
            padding: 3px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        .chat-window {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 350px;
            height: 500px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            display: none;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 999;
        }

        .chat-header {
            padding: 15px;
            background: var(--primary-gold);
            color: var(--primary-dark);
            border-radius: 15px 15px 0 0;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
        }

        .chat-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 10px;
            max-width: 80%;
        }

        .chat-message.user {
            background: var(--accent-blue);
            margin-left: auto;
            color: white;
        }

        .chat-message.admin {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        .chat-input-container {
            padding: 15px;
            border-top: 1px solid var(--glass-border);
        }

        .chat-input-row {
            display: flex;
            gap: 10px;
        }

        .chat-input-row input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            background: var(--glass-bg);
            color: white;
        }

        .chat-send-btn {
            padding: 10px 15px;
            background: var(--primary-gold);
            color: var(--primary-dark);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        .quick-actions {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }

        .quick-btn {
            background: rgba(212, 168, 83, 0.2);
            color: var(--primary-gold);
            border: 1px solid var(--primary-gold);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }

        .card-icon {
            font-size: 3rem;
            color: var(--primary-gold);
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--primary-gold);
        }

        .logout-btn {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b7a;
            border: 1px solid #ff6b7a;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff6b7a;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header Agent -->
    <div class="agent-header">
        <div class="agent-info">
            <div class="agent-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="agent-details">
                <h2><?php echo htmlspecialchars($agent_name); ?></h2>
                <div class="agent-status">
                    <i class="fas fa-circle"></i> En ligne
                </div>
            </div>
        </div>
        <a href="logout_agent.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </div>

    <!-- Container Principal -->
    <div class="main-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <nav>
                <a href="#" class="nav-item active" onclick="showSection('dashboard')">
                    <i class="fas fa-tachometer-alt"></i>
                    Tableau de bord
                </a>
                <a href="#" class="nav-item" onclick="showSection('tasks')">
                    <i class="fas fa-tasks"></i>
                    Mes tâches
                </a>
                <a href="#" class="nav-item" onclick="showSection('support')">
                    <i class="fas fa-headset"></i>
                    Support
                </a>
                <a href="#" class="nav-item" onclick="showSection('profile')">
                    <i class="fas fa-user-cog"></i>
                    Mon profil
                </a>
            </nav>
        </div>

        <!-- Contenu Principal -->
        <div class="main-content">
            <!-- Dashboard -->
            <div id="dashboard" class="section active">
                <div class="section-header">
                    <h1 class="section-title">Tableau de bord Agent</h1>
                    <p>Bienvenue dans votre espace de travail Suzosky</p>
                </div>

                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h3 class="card-title">Tâches en cours</h3>
                        <p id="taskCount">Chargement...</p>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="card-title">Messages support</h3>
                        <p id="supportCount">Chargement...</p>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="card-title">Temps de travail</h3>
                        <p id="workTime">Chargement...</p>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="card-title">Performance</h3>
                        <p id="performance">Chargement...</p>
                    </div>
                </div>
            </div>

            <!-- Support -->
            <div id="support" class="section">
                <div class="section-header">
                    <h1 class="section-title">Support & Assistance</h1>
                    <p>Contactez l'administration pour toute question</p>
                </div>

                <div class="card">
                    <h3>Besoin d'aide ?</h3>
                    <p>Utilisez le chat support en bas à droite pour contacter l'administration en temps réel.</p>
                </div>
            </div>

            <!-- Autres sections -->
            <div id="tasks" class="section">
                <div class="section-header">
                    <h1 class="section-title">Mes Tâches</h1>
                </div>
                <div class="card">
                    <p>Module de gestion des tâches en développement...</p>
                </div>
            </div>

            <div id="profile" class="section">
                <div class="section-header">
                    <h1 class="section-title">Mon Profil</h1>
                </div>
                <div class="card">
                    <p>Module de gestion du profil en développement...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Support -->
    <div id="chatSupportContainer" class="chat-support-container">
        <div id="chatToggle" class="chat-toggle" onclick="agentChat.toggleChat()">
            <i class="fas fa-headset"></i>
            <span id="chatBadge" class="chat-badge" style="display: none;">0</span>
        </div>
        
        <div id="chatWindow" class="chat-window">
            <div class="chat-header">
                <h4><i class="fas fa-user-headset"></i> Support Administration</h4>
                <button onclick="agentChat.closeChat()" style="background: none; border: none; color: inherit; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="chatMessages" class="chat-messages">
                <div class="chat-loading">Initialisation du support...</div>
            </div>
            
            <div class="chat-input-container">
                <div class="quick-actions">
                    <button onclick="agentChat.sendQuickMessage('Besoin d\'aide')" class="quick-btn">
                        <i class="fas fa-question"></i> Aide
                    </button>
                    <button onclick="agentChat.sendQuickMessage('Problème technique')" class="quick-btn">
                        <i class="fas fa-bug"></i> Technique
                    </button>
                    <button onclick="agentChat.sendQuickMessage('Question RH')" class="quick-btn">
                        <i class="fas fa-user-tie"></i> RH
                    </button>
                </div>
                <div class="chat-input-row">
                    <input type="text" id="chatMessageInput" placeholder="Tapez votre message..." 
                           onkeypress="agentChat.handleKeyPress(event)" maxlength="1000">
                    <button onclick="agentChat.sendMessage()" class="chat-send-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Navigation entre sections
        function showSection(sectionName) {
            // Masquer toutes les sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Réinitialiser navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Afficher la section demandée
            document.getElementById(sectionName).classList.add('active');
            
            // Activer l'élément de navigation
            event.target.classList.add('active');
        }

        // Système de chat support agent
        let agentChat = {
            chatId: null,
            isOpen: false,
            unreadCount: 0,
            
            init() {
                this.loadNotifications();
                this.startPolling();
            },
            
            async initializeChat() {
                if (this.chatId) return this.chatId;
                
                try {
                    const response = await fetch('admin.php?section=chat_agents', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'init_agent_chat',
                            agent_id: <?php echo $agent_id; ?>,
                            chat_category: 'general'
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.chatId = result.chat_id;
                        this.loadMessages();
                        return this.chatId;
                    } else {
                        throw new Error(result.error);
                    }
                    
                } catch (error) {
                    console.error('Erreur initialisation chat agent:', error);
                    this.showChatError('Impossible de contacter le support');
                }
            },
            
            async sendMessage(message = null) {
                const input = document.getElementById('chatMessageInput');
                const messageText = message || input.value.trim();
                
                if (!messageText || !this.chatId) return;
                
                try {
                    const response = await fetch('admin.php?section=chat_agents', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'send_agent_message',
                            chat_id: this.chatId,
                            agent_id: <?php echo $agent_id; ?>,
                            message: messageText
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        if (!message) input.value = '';
                        this.addMessageToChat(messageText, 'user');
                    } else {
                        throw new Error(result.error);
                    }
                    
                } catch (error) {
                    console.error('Erreur envoi message agent:', error);
                }
            },
            
            async sendQuickMessage(message) {
                await this.sendMessage(message);
            },
            
            async loadMessages() {
                if (!this.chatId) return;
                
                try {
                    const response = await fetch(`admin.php?section=chat_agents&action=get_messages&chat_id=${this.chatId}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        const messagesContainer = document.getElementById('chatMessages');
                        messagesContainer.innerHTML = '';
                        
                        result.messages.forEach(msg => {
                            this.addMessageToChat(msg.message, msg.sender_type, msg.sender_name, msg.sent_at);
                        });
                    }
                } catch (error) {
                    console.error('Erreur chargement messages:', error);
                }
            },
            
            addMessageToChat(message, type, senderName = null, timestamp = null) {
                const messagesContainer = document.getElementById('chatMessages');
                const messageDiv = document.createElement('div');
                messageDiv.className = `chat-message ${type}`;
                
                const time = timestamp ? new Date(timestamp).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) : 
                            new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
                
                if (type === 'admin') {
                    messageDiv.innerHTML = `
                        <div style="font-weight: bold; margin-bottom: 5px;">${senderName || 'Administration'}</div>
                        <div>${message}</div>
                        <div style="font-size: 0.8rem; opacity: 0.7; margin-top: 5px;">${time}</div>
                    `;
                } else {
                    messageDiv.innerHTML = `
                        <div>${message}</div>
                        <div style="font-size: 0.8rem; opacity: 0.7; margin-top: 5px;">${time}</div>
                    `;
                }
                
                messagesContainer.appendChild(messageDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            },
            
            toggleChat() {
                const chatWindow = document.getElementById('chatWindow');
                this.isOpen = !this.isOpen;
                
                if (this.isOpen) {
                    chatWindow.style.display = 'flex';
                    if (!this.chatId) {
                        this.initializeChat();
                    } else {
                        this.loadMessages();
                    }
                } else {
                    chatWindow.style.display = 'none';
                }
            },
            
            closeChat() {
                this.isOpen = false;
                document.getElementById('chatWindow').style.display = 'none';
            },
            
            handleKeyPress(event) {
                if (event.key === 'Enter') {
                    this.sendMessage();
                }
            },
            
            showChatError(message) {
                const messagesContainer = document.getElementById('chatMessages');
                messagesContainer.innerHTML = `
                    <div class="chat-message admin" style="color: #ff6b7a;">
                        <div>⚠️ ${message}</div>
                    </div>
                `;
            },
            
            async loadNotifications() {
                // Charger les notifications non lues
                try {
                    const response = await fetch('admin.php?section=chat_agents&action=get_stats');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.updateChatBadge(result.stats.unread_messages);
                    }
                } catch (error) {
                    console.error('Erreur notifications:', error);
                }
            },
            
            updateChatBadge(count) {
                const badge = document.getElementById('chatBadge');
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            },
            
            startPolling() {
                setInterval(() => {
                    this.loadNotifications();
                    if (this.isOpen && this.chatId) {
                        this.loadMessages();
                    }
                }, 5000);
            }
        };

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            agentChat.init();
            
            // Charger les données du dashboard
            setTimeout(() => {
                document.getElementById('taskCount').textContent = '3 tâches';
                document.getElementById('supportCount').textContent = '0 messages';
                document.getElementById('workTime').textContent = '7h 30min';
                document.getElementById('performance').textContent = '95%';
            }, 1000);
        });
    </script>
</body>
</html>
