<?php
// sections/chat_support.php - Interface et styles du chat support
?>
    <!-- 💬 INTERFACE CHAT SUPPORT -->
    <div class="chat-toggle" onclick="toggleChat()">
        <i class="fas fa-comments"></i>
        <div class="chat-notification">1</div>
    </div>

    <div id="chatWindow" class="chat-window">
        <!-- Onglets de chat -->
        <div class="chat-tabs">
            <button class="chat-tab active" data-tab="particulier" onclick="switchChatTab('particulier')">Clients Particuliers</button>
            <button class="chat-tab" data-tab="business" onclick="switchChatTab('business')">Clients Business</button>
            <button class="chat-tab" data-tab="agents" onclick="switchChatTab('agents')">Support Agents</button>
        </div>
        <div class="chat-header">
            <h4>💬 Support Suzosky</h4>
            <button class="chat-close" onclick="toggleChat()">×</button>
            <div id="chatStatus" class="chat-status">En attente d'un agent...</div>
        </div>
        <div id="chatMessages" class="chat-messages"></div>
        <div class="chat-input">
            <input type="text" id="messageInput" placeholder="Tapez votre message...">
            <button onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <style>
        /* 💬 STYLES CHAT SUPPORT */
        .chat-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: var(--gradient-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--glass-shadow);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .chat-toggle:hover {
            transform: scale(1.1);
        }

        .chat-toggle i {
            color: var(--primary-dark);
            font-size: 1.5rem;
        }

        .chat-notification {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--accent-red);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: none;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        .chat-window {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: var(--primary-dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            display: none;
            flex-direction: column;
            overflow: hidden;
            box-shadow: var(--glass-shadow);
            z-index: 999;
        }

        .chat-header {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            padding: 15px;
            text-align: center;
            position: relative;
        }

        .chat-header h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .chat-close {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            color: var(--primary-dark);
            font-size: 1.5rem;
            cursor: pointer;
            font-weight: bold;
        }

        .chat-status {
            font-size: 0.8rem;
            margin-top: 5px;
            opacity: 0.8;
        }

        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: var(--primary-dark);
        }

        .chat-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 10px;
            max-width: 80%;
        }

        .chat-message.user {
            background: var(--glass-bg);
            border: 1px solid var(--primary-gold);
            margin-left: auto;
            text-align: right;
        }

        .chat-message.admin {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        .chat-message.system {
            background: rgba(212, 168, 83, 0.1);
            border: 1px solid var(--primary-gold);
            text-align: center;
            margin: 0 auto;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.8rem;
            color: var(--primary-gold);
        }

        .message-content {
            color: white;
            line-height: 1.4;
        }

        .chat-input {
            padding: 15px;
            background: var(--secondary-blue);
            display: flex;
            gap: 10px;
        }

        .chat-input input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            background: var(--glass-bg);
            color: white;
            outline: none;
        }

        .chat-input input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .chat-input button {
            background: var(--primary-gold);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            color: var(--primary-dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
    </style>
