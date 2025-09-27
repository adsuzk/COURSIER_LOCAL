<?php
/**
 * ============================================================================
 * 🤖 CHAT SUPPORT AVEC INTELLIGENCE ARTIFICIELLE - SUZOSKY
 * ============================================================================
 * 
 * Chat public connecté aux APIs backend avec IA avancée
 * Reconnaissance d'intention et gestion automatique des réclamations
 * 
 * @version 2.0.0 - IA intégrée
 * @author Équipe Suzosky  
 * @date 25 septembre 2025
 * ============================================================================
 */
?>
    <script>
    // État global du chat public avec IA
    let isChatOpen = false;
    let activeChatType = 'particulier';
    let conversationIds = {};
    let pollingTimers = {};
    let lastMessageIds = {};
    let unreadCount = 0;
    
    // État IA et réclamations
    let aiEnabled = true;
    let complaintWorkflow = null;
    let waitingForAIResponse = false;
    let isFirstMessage = true;
    let lastUserMessage = '';
    let complaintExplicit = false; // Ne montrer le formulaire que si l'utilisateur l'indique clairement

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
            
            // Message d'accueil de l'IA au premier ouverture
            if (isFirstMessage) {
                setTimeout(() => {
                    displayWelcomeMessage();
                    isFirstMessage = false;
                }, 500);
            }
            
            setTimeout(scrollChatToBottom, 50);
        }
    }
    
    function displayWelcomeMessage() {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        const welcomeDiv = document.createElement('div');
        welcomeDiv.className = 'chat-message ai welcome-message';
        welcomeDiv.innerHTML = `
            <div class="message-header">
                <span>🤖 Assistant IA Suzosky</span>
                <span>${new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}</span>
            </div>
            <div class="message-content">
                <div class="welcome-content">
                    <div class="welcome-title">👋 Bonjour ! Bienvenue sur Suzosky !</div>
                    <div class="welcome-text">
                        Je suis votre assistant virtuel intelligent. Je peux vous aider avec :
                        <ul>
                            <li>🚚 <strong>Suivi de commandes</strong> - Vérifier le statut de vos livraisons</li>
                            <li>📋 <strong>Réclamations</strong> - Traiter vos problèmes de commande</li>
                        </ul>
                        <div class="welcome-cta">Comment puis-je vous aider aujourd'hui ? 😊</div>
                    </div>
                    <div class="ai-suggestions" style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="ai-action-btn" onclick="openOrderTracking()">🔎 Suivre ma commande</button>
                        <button class="ai-action-btn" onclick="openOrderTracking()">📦 État de ma commande</button>
                        <button class="ai-action-btn" onclick="startComplaint()">📝 Déposer une réclamation</button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(welcomeDiv);
        scrollChatToBottom();
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

    // Identity
    function getOrCreateGuestId() {
        const key = 'chat_guest_id';
        let id = localStorage.getItem(key);
        if (!id) {
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

    // Conversation management
    async function ensureConversation(type) {
        if (conversationIds[type]) return conversationIds[type];
        
        const client_id = resolveClientIdForType(type);
        try {
            const res = await fetchJSON(apiBase + '/init.php', {
                body: JSON.stringify({ type, client_id })
            });
            if (res && res.success && res.conversation_id) {
                conversationIds[type] = res.conversation_id;
                localStorage.setItem('chat_conversation_' + type, String(res.conversation_id));
                return res.conversation_id;
            }
            console.error('Init chat failed:', res && res.message);
        } catch (err) {
            console.error('Init error:', err);
        }
        return null;
    }

    // Message handling avec IA
    async function sendMessage() {
        const input = document.getElementById('messageInput');
        if (!input) return;
        const text = input.value.trim();
        if (!text || waitingForAIResponse) return;
        
        try {
            waitingForAIResponse = true;
            input.value = '';
            lastUserMessage = text;
            // Reset intention explicite de réclamation sauf si le texte le mentionne clairement
            complaintExplicit = /\b(réclam|plainte|réclamer)\b/i.test(text);
            
            // Affichage du message utilisateur
            displayUserMessage(text);
            
            // Si l'IA est activée, traiter d'abord avec l'IA
            if (aiEnabled) {
                await processWithAI(text);
            } else {
                // Envoi normal au support humain
                await sendToHumanSupport(text);
            }
            
        } catch (err) {
            console.error('Erreur sendMessage:', err);
            displaySystemMessage('Erreur lors de l\'envoi. Réessayez.');
        } finally {
            waitingForAIResponse = false;
        }
    }
    
    function displayUserMessage(text) {
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
    }
    
    function displayAIMessage(text, actions = null) {
        const container = document.getElementById('chatMessages');
        const aiDiv = document.createElement('div');
        aiDiv.className = 'chat-message ai';
        
        let actionsHtml = '';
        if (actions && actions.length > 0) {
            actionsHtml = `
                <div class="ai-actions">
                    ${actions.map(action => `
                        <button class="ai-action-btn" onclick="${action.onclick}">${action.label}</button>
                    `).join('')}
                </div>
            `;
        }
        
        aiDiv.innerHTML = `
            <div class="message-header">
                <span>🤖 Assistant IA Suzosky</span>
                <span>${new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}</span>
            </div>
            <div class="message-content">${escapeHtml(text)}${actionsHtml}</div>
        `;
        container.appendChild(aiDiv);
        scrollChatToBottom();
    }
    
    function displaySystemMessage(text) {
        const container = document.getElementById('chatMessages');
        const sysDiv = document.createElement('div');
        sysDiv.className = 'chat-message system';
        sysDiv.innerHTML = `
            <div class="message-content">ℹ️ ${escapeHtml(text)}</div>
        `;
        container.appendChild(sysDiv);
        scrollChatToBottom();
    }
    
    async function processWithAI(message) {
        try {
            displayAIThinking();
            
            const response = await fetch('api/ai_chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'analyze_message',
                    message: message,
                    conversation_id: conversationIds[activeChatType],
                    complaint_workflow: complaintWorkflow,
                    guest_id: getOrCreateGuestId()
                })
            });
            
            const result = await response.json();
            
            removeAIThinking();
            
            if (result.success) {
                // Afficher la réponse de l'IA
                const aiType = result.ai_response && result.ai_response.type ? result.ai_response.type : null;
                // Si ce n'est pas lié aux commandes ou réclamations, rediriger vers les options commandes
                if (aiType !== 'commande' && aiType !== 'reclamation') {
                    displayAIMessage(
                        "Pour le moment, je peux vous aider uniquement au sujet de vos commandes. Que souhaitez-vous faire ?",
                        [
                            { label: '🔎 Suivre une commande', onclick: 'openOrderTracking()' },
                            { label: '📝 Déposer une réclamation', onclick: 'startComplaint()' }
                        ]
                    );
                    return;
                }

                displayAIMessage(result.ai_response.message, result.ai_response.actions);
                
                // Traiter les actions spéciales
                if (aiType === 'reclamation') {
                    // Ne montrer le formulaire de réclamation que si c'est explicitement demandé
                    complaintWorkflow = result.workflow;
                    const userAskedComplaint = complaintExplicit || /\b(réclam|plainte|réclamer)\b/i.test(lastUserMessage || '');
                    if (userAskedComplaint) {
                        if (result.workflow && result.workflow.next_step) {
                            setTimeout(() => showComplaintForm(result.workflow), 800);
                        }
                    } else {
                        displayAIMessage(
                            "Souhaitez-vous déposer une réclamation ?",
                            [
                                { label: 'Oui, déposer', onclick: 'startComplaint()' },
                                { label: 'Non, suivre une commande', onclick: 'openOrderTracking()' }
                            ]
                        );
                    }
                } else if (aiType === 'commande') {
                    setTimeout(() => showOrderTrackingForm(), 1000);
                }
                
                // Si besoin d'escalade vers humain
                if (result.escalate_to_human) {
                    setTimeout(() => {
                        displaySystemMessage('Transfert vers un agent humain spécialisé...');
                        aiEnabled = false; // Désactiver l'IA pour cette session
                    }, 2000);
                }
            } else {
                throw new Error(result.error);
            }
            
        } catch (error) {
            console.error('Erreur IA:', error);
            displaySystemMessage('Transfert vers un agent humain...');
            aiEnabled = false;
            await sendToHumanSupport(message);
        }
    }
    
    async function sendToHumanSupport(message) {
        const conv = await ensureConversation(activeChatType);
        if (!conv) { 
            displaySystemMessage("Impossible d'initialiser la discussion.");
            return; 
        }
        
        const sender_id = resolveClientIdForType(activeChatType);
        
        try {
            const resp = await fetchJSON(apiBase + '/send_message.php', {
                body: JSON.stringify({
                    conversation_id: conv,
                    sender_type: 'client',
                    sender_id,
                    message: message
                })
            });
            if (!(resp && resp.success)) {
                displaySystemMessage("Envoi échoué: " + (resp && resp.message ? resp.message : 'Erreur inconnue'));
            }
        } catch (err) {
            console.error('sendMessage error', err);
            displaySystemMessage("Erreur réseau lors de l'envoi.");
        }
    }
    
    function displayAIThinking() {
        const container = document.getElementById('chatMessages');
        const thinkingDiv = document.createElement('div');
        thinkingDiv.className = 'ai-thinking';
        thinkingDiv.id = 'aiThinking';
        thinkingDiv.innerHTML = `
            <div class="ai-thinking-content">
                <div class="ai-avatar">🤖</div>
                <div class="thinking-animation">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
                <div class="thinking-text">Analyse en cours...</div>
            </div>
        `;
        container.appendChild(thinkingDiv);
        scrollChatToBottom();
    }
    
    function removeAIThinking() {
        const thinkingDiv = document.getElementById('aiThinking');
        if (thinkingDiv) {
            thinkingDiv.remove();
        }
    }
    
    // Formulaire de réclamation dynamique
    function showComplaintForm(workflow) {
        const container = document.getElementById('chatMessages');
        const formDiv = document.createElement('div');
        formDiv.className = 'chat-message ai complaint-form';
        
        let formContent = '';
        
        switch(workflow.current_step) {
            case 'ask_transaction_number':
                formContent = `
                    <div class="form-group">
                        <label>Numéro de transaction :</label>
                        <input type="text" id="complaintTransaction" placeholder="Ex: CM20250001" maxlength="20">
                        <button onclick="submitComplaintStep('transaction')" class="form-btn">Valider</button>
                    </div>
                `;
                break;
                
            case 'ask_problem_type':
                formContent = `
                    <div class="form-group">
                        <label>Type de problème :</label>
                        <select id="complaintType">
                            <option value="commande">Problème avec ma commande</option>
                            <option value="livraison">Problème de livraison</option>
                            <option value="paiement">Problème de paiement</option>
                            <option value="coursier">Problème avec le coursier</option>
                            <option value="technique">Problème technique</option>
                            <option value="autre">Autre problème</option>
                        </select>
                        <button onclick="submitComplaintStep('type')" class="form-btn">Continuer</button>
                    </div>
                `;
                break;
                
            case 'ask_description':
                formContent = `
                    <div class="form-group">
                        <label>Description détaillée :</label>
                        <textarea id="complaintDescription" rows="4" placeholder="Décrivez précisément votre problème..."></textarea>
                        <button onclick="submitComplaintStep('description')" class="form-btn">Continuer</button>
                    </div>
                `;
                break;
                
            case 'ask_screenshot':
                formContent = `
                    <div class="form-group">
                        <label>Capture d'écran (optionnel) :</label>
                        <input type="file" id="complaintFile" accept="image/*,.pdf" multiple>
                        <div class="form-actions">
                            <button onclick="submitComplaintStep('file')" class="form-btn">Finaliser</button>
                            <button onclick="submitComplaintStep('skip_file')" class="form-btn-secondary">Passer</button>
                        </div>
                    </div>
                `;
                break;
        }
        
        formDiv.innerHTML = `
            <div class="message-header">
                <span>🤖 Assistant IA Suzosky</span>
                <span>${new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}</span>
            </div>
            <div class="message-content">
                <div class="complaint-form-container">
                    ${formContent}
                </div>
            </div>
        `;
        
        container.appendChild(formDiv);
        scrollChatToBottom();
    }
    
    async function submitComplaintStep(step) {
        let data = {};
        
        switch(step) {
            case 'transaction':
                data.transaction_number = document.getElementById('complaintTransaction').value.trim();
                if (!data.transaction_number) {
                    alert('Veuillez saisir le numéro de transaction');
                    return;
                }
                break;
                
            case 'type':
                data.problem_type = document.getElementById('complaintType').value;
                break;
                
            case 'description':
                data.description = document.getElementById('complaintDescription').value.trim();
                if (!data.description) {
                    alert('Veuillez décrire votre problème');
                    return;
                }
                break;
                
            case 'file':
                const fileInput = document.getElementById('complaintFile');
                if (fileInput.files.length > 0) {
                    const formData = new FormData();
                    Array.from(fileInput.files).forEach((file, index) => {
                        formData.append('files[]', file, file.name);
                    });
                    formData.append('guest_id', getOrCreateGuestId());
                    if (conversationIds[activeChatType]) {
                        formData.append('conversation_id', conversationIds[activeChatType]);
                    }

                    try {
                        const uploadResponse = await fetch('api/ai_chat_upload.php', {
                            method: 'POST',
                            body: formData
                        });
                        const uploadResult = await uploadResponse.json();
                        if (!uploadResult.success) {
                            alert(uploadResult.error || "Téléversement des fichiers impossible");
                            return;
                        }
                        data.attachments = uploadResult.files || [];
                    } catch (e) {
                        console.error('Upload error', e);
                        alert("Une erreur est survenue lors de l'envoi des fichiers. Réessayez ou passez cette étape.");
                        return;
                    }
                }
                break;
                
            case 'skip_file':
                data.skip_files = true;
                break;
        }
        
        try {
            const response = await fetch('api/ai_chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'process_complaint_step',
                    step: step,
                    data: data,
                    workflow: complaintWorkflow,
                    guest_id: getOrCreateGuestId(),
                    conversation_id: conversationIds[activeChatType] || null
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                complaintWorkflow = result.workflow;
                
                if (result.workflow.completed) {
                    displayAIMessage(`✅ Parfait ! Votre réclamation #${result.complaint_id} a été créée avec succès. Notre équipe va la traiter dans les plus brefs délais. Vous pouvez fermer cette conversation ou poser d'autres questions.`);
                    complaintWorkflow = null; // Reset
                } else {
                    displayAIMessage(result.ai_response.message);
                    if (result.workflow.next_step) {
                        setTimeout(() => showComplaintForm(result.workflow), 1000);
                    }
                }
            } else {
                displaySystemMessage('Erreur lors du traitement: ' + result.error);
            }
            
        } catch (error) {
            console.error('Erreur soumission:', error);
            displaySystemMessage('Erreur lors du traitement de votre réclamation');
        }
    }
    
    // Formulaire de suivi de commande
    function showOrderTrackingForm() {
        const container = document.getElementById('chatMessages');
        const formDiv = document.createElement('div');
        formDiv.className = 'chat-message ai order-form';
        
        formDiv.innerHTML = `
            <div class="message-header">
                <span>🤖 Assistant IA Suzosky</span>
                <span>${new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}</span>
            </div>
            <div class="message-content">
                <div class="order-form-container">
                    <div class="form-group">
                        <label>Numéro de transaction :</label>
                        <input type="text" id="trackingNumber" placeholder="Ex: CM20250001" maxlength="20">
                        <button onclick="trackOrder()" class="form-btn">Rechercher</button>
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(formDiv);
        scrollChatToBottom();
    }

    // Suggestions/intent helpers (cliquables)
    function openOrderTracking() {
        displayUserMessage('Je veux suivre ma commande');
        lastUserMessage = 'Je veux suivre ma commande';
        complaintExplicit = false;
        showOrderTrackingForm();
    }

    function startComplaint() {
        complaintExplicit = true;
        const text = 'Je souhaite déposer une réclamation';
        displayUserMessage(text);
        lastUserMessage = text;
        // Lancer l'analyse IA pour initialiser correctement le workflow côté backend
        processWithAI('réclamation');
    }
    
    async function trackOrder() {
        const number = document.getElementById('trackingNumber').value.trim();
        if (!number) {
            alert('Veuillez saisir le numéro de transaction');
            return;
        }
        
        try {
            displayAIThinking();
            
            const response = await fetch('api/ai_chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'track_order',
                    transaction_number: number
                })
            });
            
            const result = await response.json();
            removeAIThinking();
            
            if (result.success && result.order) {
                const order = result.order;
                displayAIMessage(`
                    📦 <strong>Statut de votre commande ${number}</strong><br><br>
                    <strong>Status :</strong> ${order.statut}<br>
                    <strong>Coursier :</strong> ${order.coursier_nom || 'En attente d\'attribution'}<br>
                    <strong>Date :</strong> ${new Date(order.date_commande).toLocaleString('fr-FR')}<br>
                    <strong>Montant :</strong> ${order.prix_total} FCFA<br><br>
                    ${order.statut === 'livre' ? '✅ Votre commande a été livrée !' : 
                      order.statut === 'en_course' ? '🚚 Votre commande est en cours de livraison !' :
                      '⏳ Votre commande est en préparation.'}
                `);
            } else {
                displayAIMessage(`❌ Je n'ai pas trouvé de commande avec le numéro ${number}. Vérifiez le numéro ou contactez notre support si vous êtes certain qu'il est correct.`);
            }
            
        } catch (error) {
            removeAIThinking();
            console.error('Erreur tracking:', error);
            displaySystemMessage('Erreur lors de la recherche de commande');
        }
    }
    
    // Handlers pour les touches
    function handleChatKeyPress(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }
    
    // Polling pour messages admin (si humain prend le relais)
    function startPolling(type) {
        if (pollingTimers[type]) return;
        pollingTimers[type] = setInterval(async () => {
            if (!aiEnabled) { // Seulement si on est passé en mode humain
                await loadMessages(type);
            }
        }, 3000);
    }
    
    function stopPolling(type) {
        if (pollingTimers[type]) {
            clearInterval(pollingTimers[type]);
            delete pollingTimers[type];
        }
    }
    
    async function loadMessages(type) {
        const convId = conversationIds[type];
        if (!convId) return;
        
        try {
            const resp = await fetchJSON(`${apiBase}/get_messages.php`, {
                body: JSON.stringify({
                    conversation_id: convId,
                    last_id: lastMessageIds[type] || 0
                })
            });
            
            if (resp && resp.success && resp.data) {
                renderNewMessages(resp.data, type);
            }
        } catch (err) {
            console.error('loadMessages error', err);
        }
    }
    
    function renderNewMessages(messages, type) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        messages.forEach(m => {
            if (m.sender_type === 'admin') {
                const adminDiv = document.createElement('div');
                adminDiv.className = 'chat-message admin';
                adminDiv.innerHTML = `
                    <div class="message-header">
                        <span>👨‍💼 Support Suzosky</span>
                        <span>${new Date(m.timestamp).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}</span>
                    </div>
                    <div class="message-content">${escapeHtml(m.message)}</div>
                `;
                container.appendChild(adminDiv);
                
                if (!isChatOpen) {
                    unreadCount++;
                    updateNotificationBadge();
                }
            }
            
            if (m.id > (lastMessageIds[type] || 0)) {
                lastMessageIds[type] = m.id;
            }
        });
        
        scrollChatToBottom();
    }
    
    // Init
    document.addEventListener('DOMContentLoaded', function() {
        // Setup event listeners
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('keypress', handleChatKeyPress);
        }
        
        // Restore conversations si nécessaire
        if (!aiEnabled) {
            getAvailableTabs().forEach(t => {
                const val = localStorage.getItem('chat_conversation_' + t);
                if (val) conversationIds[t] = parseInt(val, 10);
            });
        }
    });
    </script>