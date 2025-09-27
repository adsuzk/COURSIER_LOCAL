<?php
// sections/chat_support.php - Interface et styles du chat support
?>
    <!-- 💬 INTERFACE CHAT SUPPORT -->
    <div class="chat-toggle" onclick="toggleChat()">
        <i class="fas fa-comments"></i>
        <div class="chat-notification">1</div>
    </div>

    <div id="chatWindow" class="chat-window">
        <!-- Onglets supprimés sur le public pour simplifier l'UX -->
        <div class="chat-header">
            <h4>💬 Support Suzosky</h4>
            <button class="chat-close" onclick="toggleChat()">×</button>
            <div id="chatStatus" class="chat-status">Laissez votre message, un agent vous répondra dès que possible.</div>
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
            z-index: 10001;
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
            max-height: calc(100vh - 180px); /* conserver un espace visuel avec le header */
            background: var(--primary-dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            display: none;
            flex-direction: column;
            overflow: hidden;
            box-shadow: var(--glass-shadow);
            z-index: 10000; /* au-dessus du header */
        }

        @media (max-width: 480px) {
            .chat-window {
                right: 10px;
                left: 10px;
                width: auto;
                height: auto;
                max-height: calc(100vh - 150px);
                bottom: 80px;
            }
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
        
        .chat-message.ai {
            background: linear-gradient(135deg, rgba(212, 168, 83, 0.15) 0%, rgba(212, 168, 83, 0.05) 100%);
            border: 1px solid var(--primary-gold);
            position: relative;
        }
        
        .chat-message.ai::before {
            content: "";
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(45deg, var(--primary-gold), transparent, var(--primary-gold));
            border-radius: 10px;
            z-index: -1;
            animation: ai-glow 3s ease-in-out infinite;
        }
        
        @keyframes ai-glow {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        
        .welcome-message {
            border: 2px solid var(--primary-gold);
            box-shadow: 0 8px 25px rgba(212, 168, 83, 0.3);
        }
        
        .welcome-content {
            padding: 10px 0;
        }
        
        .welcome-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary-gold);
            margin-bottom: 15px;
        }
        
        .welcome-text ul {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .welcome-text li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .welcome-cta {
            background: var(--primary-gold);
            color: var(--primary-dark);
            padding: 15px;
            border-radius: 10px;
            font-weight: 700;
            text-align: center;
            margin-top: 15px;
        }

        .chat-message.system {
            background: rgba(212, 168, 83, 0.1);
            border: 1px solid var(--primary-gold);
            text-align: center;
            margin: 0 auto;
        }
        
        /* Styles IA */
        .ai-thinking {
            background: rgba(212, 168, 83, 0.05);
            border: 1px solid var(--primary-gold);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
        }
        
        .ai-thinking-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            color: var(--primary-gold);
        }
        
        .ai-avatar {
            font-size: 1.5rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .thinking-animation {
            display: flex;
            gap: 5px;
        }
        
        .thinking-animation .dot {
            width: 8px;
            height: 8px;
            background: var(--primary-gold);
            border-radius: 50%;
            animation: pulse 1.4s ease-in-out infinite both;
        }
        
        .thinking-animation .dot:nth-child(1) { animation-delay: -0.32s; }
        .thinking-animation .dot:nth-child(2) { animation-delay: -0.16s; }
        .thinking-animation .dot:nth-child(3) { animation-delay: 0s; }
        
        @keyframes pulse {
            0%, 80%, 100% {
                transform: scale(0);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .thinking-text {
            font-size: 0.9rem;
            font-style: italic;
        }
        
        /* Formulaires IA */
        .complaint-form-container,
        .order-form-container {
            background: rgba(26, 26, 46, 0.3);
            border: 1px solid var(--primary-gold);
            border-radius: 15px;
            padding: 20px;
            margin: 10px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            background: var(--glass-bg);
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
        }
        
        .form-btn {
            background: var(--primary-gold);
            color: var(--primary-dark);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .form-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
        }
        
        .form-btn-secondary {
            background: transparent;
            color: var(--primary-gold);
            border: 1px solid var(--primary-gold);
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            margin-left: 10px;
        }
        
        .form-btn-secondary:hover {
            background: var(--primary-gold);
            color: var(--primary-dark);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .ai-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .ai-action-btn {
            background: rgba(212, 168, 83, 0.2);
            color: var(--primary-gold);
            border: 1px solid var(--primary-gold);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .ai-action-btn:hover {
            background: var(--primary-gold);
            color: var(--primary-dark);
            transform: translateY(-2px);
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
