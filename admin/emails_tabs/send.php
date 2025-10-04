<?php
/**
 * SEND - Formulaire d'envoi d'email unique
 */
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">✉️ Envoyer un Email</h2>
    </div>
    
    <form method="POST" action="?section=emails&email_tab=send">
        <input type="hidden" name="action" value="send_email">
        
        <div class="form-group">
            <label class="form-label" for="to">
                📧 Destinataire *
            </label>
            <input 
                type="email" 
                id="to" 
                name="to" 
                class="form-input" 
                placeholder="exemple@email.com"
                required
            >
        </div>
        
        <div class="form-group">
            <label class="form-label" for="subject">
                📝 Sujet *
            </label>
            <input 
                type="text" 
                id="subject" 
                name="subject" 
                class="form-input" 
                placeholder="Entrez le sujet de l'email"
                required
            >
        </div>
        
        <div class="form-group">
            <label class="form-label" for="type">
                🏷️ Type d'email
            </label>
            <select id="type" name="type" class="form-select">
                <option value="general">Général</option>
                <option value="welcome">Bienvenue</option>
                <option value="order">Commande</option>
                <option value="notification">Notification</option>
                <option value="marketing">Marketing</option>
                <option value="support">Support</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="body">
                📄 Contenu *
            </label>
            <textarea 
                id="body" 
                name="body" 
                class="form-textarea" 
                placeholder="Écrivez le contenu de votre email ici... (HTML supporté)"
                rows="12"
                required
            ></textarea>
        </div>
        
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                ✉️ Envoyer l'Email
            </button>
            <button type="button" class="btn btn-secondary" onclick="openModal('previewModal')">
                👁️ Prévisualiser
            </button>
            <button type="button" class="btn btn-secondary" onclick="openModal('templatesModal')">
                📝 Charger un Template
            </button>
            <button type="reset" class="btn btn-secondary">
                🔄 Réinitialiser
            </button>
        </div>
    </form>
</div>

<!-- AIDE ET CONSEILS -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">💡 Conseils pour un Email Efficace</h2>
    </div>
    <div style="color: #CCCCCC; line-height: 1.8;">
        <p style="margin-bottom: 15px;">
            <strong style="color: var(--primary-gold);">✅ Bonnes Pratiques:</strong>
        </p>
        <ul style="margin-left: 20px; margin-bottom: 20px;">
            <li>✉️ Utilisez un sujet clair et accrocheur (50-70 caractères max)</li>
            <li>📱 Assurez-vous que le contenu est responsive (mobile-friendly)</li>
            <li>🎨 Utilisez HTML pour une meilleure présentation</li>
            <li>🔗 Incluez des liens clairs vers vos actions principales</li>
            <li>📊 Testez votre email avant l'envoi massif</li>
            <li>⚡ Évitez les mots spam (GRATUIT, URGENT, GAGNEZ, etc.)</li>
        </ul>
        
        <p style="margin-bottom: 15px;">
            <strong style="color: var(--primary-gold);">📝 Variables disponibles:</strong>
        </p>
        <ul style="margin-left: 20px;">
            <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 6px; border-radius: 4px;">{{nom}}</code> - Nom du destinataire</li>
            <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 6px; border-radius: 4px;">{{prenom}}</code> - Prénom du destinataire</li>
            <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 6px; border-radius: 4px;">{{email}}</code> - Email du destinataire</li>
            <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 6px; border-radius: 4px;">{{date}}</code> - Date actuelle</li>
        </ul>
    </div>
</div>

<!-- MODAL DE PRÉVISUALISATION -->
<div id="previewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">👁️ Prévisualisation</h3>
            <button type="button" class="modal-close" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div id="previewContent" style="background: white; padding: 20px; border-radius: 8px; color: #333;">
            <p style="color: #666; margin-bottom: 15px;">
                <strong>Sujet:</strong> <span id="previewSubject">-</span>
            </p>
            <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
            <div id="previewBody">
                Aucun contenu à prévisualiser
            </div>
        </div>
        <div class="btn-group mt-20">
            <button type="button" class="btn btn-secondary" onclick="closeModal('previewModal')">
                Fermer
            </button>
        </div>
    </div>
</div>

<!-- MODAL DES TEMPLATES -->
<div id="templatesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">📝 Charger un Template</h3>
            <button type="button" class="modal-close" onclick="closeModal('templatesModal')">&times;</button>
        </div>
        <div class="table-container">
            <table class="email-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="3" class="text-center">
                                Aucun template disponible
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><?= htmlspecialchars($template['name']) ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= htmlspecialchars($template['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button 
                                        type="button" 
                                        class="btn btn-success" 
                                        style="padding: 6px 12px; font-size: 0.9rem;"
                                        onclick="loadTemplate(<?= $template['id'] ?>)"
                                    >
                                        📥 Charger
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Prévisualisation en temps réel
    document.getElementById('body').addEventListener('input', updatePreview);
    document.getElementById('subject').addEventListener('input', updatePreview);
    
    function updatePreview() {
        const subject = document.getElementById('subject').value || '-';
        const body = document.getElementById('body').value || 'Aucun contenu à prévisualiser';
        
        document.getElementById('previewSubject').textContent = subject;
        document.getElementById('previewBody').innerHTML = body;
    }
    
    // Charger un template
    function loadTemplate(templateId) {
        fetch(`?section=emails&action=get_template&id=${templateId}`)
            .then(response => {
                if (!response.ok) {
                    // Si l'API n'existe pas, utiliser les données locales
                    const templates = <?= json_encode($templates) ?>;
                    const template = templates.find(t => t.id == templateId);
                    if (template) {
                        document.getElementById('subject').value = template.subject;
                        document.getElementById('body').value = template.body;
                        document.getElementById('type').value = template.type;
                        closeModal('templatesModal');
                        updatePreview();
                        alert('✅ Template chargé avec succès !');
                    }
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (data) {
                    document.getElementById('subject').value = data.subject;
                    document.getElementById('body').value = data.body;
                    document.getElementById('type').value = data.type;
                    closeModal('templatesModal');
                    updatePreview();
                    alert('✅ Template chargé avec succès !');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
    }
</script>
