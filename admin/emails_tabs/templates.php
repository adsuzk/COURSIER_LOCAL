<?php
/**
 * TEMPLATES - Gestion des templates d'emails
 */
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">📝 Créer un Template</h2>
    </div>
    
    <form method="POST" action="?section=emails&email_tab=templates">
        <input type="hidden" name="action" value="save_template">
        
        <div class="form-group">
            <label class="form-label" for="template_name">
                🏷️ Nom du Template *
            </label>
            <input 
                type="text" 
                id="template_name" 
                name="template_name" 
                class="form-input" 
                placeholder="Ex: Email de Bienvenue Client"
                required
            >
        </div>
        
        <div class="form-group">
            <label class="form-label" for="template_type">
                📂 Type de Template
            </label>
            <select id="template_type" name="template_type" class="form-select">
                <option value="general">Général</option>
                <option value="welcome">Bienvenue</option>
                <option value="order">Commande</option>
                <option value="notification">Notification</option>
                <option value="marketing">Marketing</option>
                <option value="support">Support</option>
                <option value="campaign">Campagne</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="template_subject">
                📝 Sujet du Template *
            </label>
            <input 
                type="text" 
                id="template_subject" 
                name="template_subject" 
                class="form-input" 
                placeholder="Sujet de l'email"
                required
            >
        </div>
        
        <div class="form-group">
            <label class="form-label" for="template_body">
                📄 Contenu du Template *
            </label>
            <textarea 
                id="template_body" 
                name="template_body" 
                class="form-textarea" 
                placeholder="Écrivez le contenu du template... (HTML supporté, utilisez {{variables}} pour personnalisation)"
                rows="15"
                required
            ></textarea>
        </div>
        
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                💾 Sauvegarder le Template
            </button>
            <button type="button" class="btn btn-secondary" onclick="openModal('templatePreviewModal')">
                👁️ Prévisualiser
            </button>
            <button type="button" class="btn btn-secondary" onclick="insertVariable()">
                📌 Insérer une Variable
            </button>
            <button type="reset" class="btn btn-secondary">
                🔄 Réinitialiser
            </button>
        </div>
    </form>
</div>

<!-- VARIABLES DISPONIBLES -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📌 Variables Disponibles</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border);">
            <p style="color: var(--primary-gold); font-weight: 600; margin-bottom: 8px;">Client</p>
            <ul style="color: #CCCCCC; margin-left: 20px; line-height: 2;">
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{nom}}</code> - Nom</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{prenom}}</code> - Prénom</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{email}}</code> - Email</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{telephone}}</code> - Téléphone</li>
            </ul>
        </div>
        
        <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border);">
            <p style="color: var(--primary-gold); font-weight: 600; margin-bottom: 8px;">Commande</p>
            <ul style="color: #CCCCCC; margin-left: 20px; line-height: 2;">
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{commande_id}}</code> - ID Commande</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{montant}}</code> - Montant</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{statut}}</code> - Statut</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{date}}</code> - Date</li>
            </ul>
        </div>
        
        <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border);">
            <p style="color: var(--primary-gold); font-weight: 600; margin-bottom: 8px;">Système</p>
            <ul style="color: #CCCCCC; margin-left: 20px; line-height: 2;">
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{site_url}}</code> - URL du Site</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{site_name}}</code> - Nom du Site</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{support_email}}</code> - Email Support</li>
                <li><code style="background: rgba(212, 168, 83, 0.1); padding: 2px 8px; border-radius: 4px;">{{annee}}</code> - Année</li>
            </ul>
        </div>
    </div>
</div>

<!-- LISTE DES TEMPLATES EXISTANTS -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📚 Templates Existants</h2>
    </div>
    
    <div class="table-container">
        <table class="email-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Type</th>
                    <th>Sujet</th>
                    <th>Créé le</th>
                    <th>Modifié le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            Aucun template créé pour le moment
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><strong>#<?= $template['id'] ?></strong></td>
                            <td class="truncate" style="max-width: 200px;">
                                <?= htmlspecialchars($template['name']) ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php
                                    $typeIcons = [
                                        'general' => '📄',
                                        'welcome' => '👋',
                                        'order' => '📦',
                                        'notification' => '🔔',
                                        'marketing' => '📢',
                                        'support' => '💬',
                                        'campaign' => '📣'
                                    ];
                                    echo ($typeIcons[$template['type']] ?? '📄') . ' ' . htmlspecialchars($template['type']);
                                    ?>
                                </span>
                            </td>
                            <td class="truncate" style="max-width: 250px;">
                                <?= htmlspecialchars($template['subject']) ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?= date('d/m/Y H:i', strtotime($template['created_at'])) ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?= $template['updated_at'] ? date('d/m/Y H:i', strtotime($template['updated_at'])) : '-' ?>
                            </td>
                            <td>
                                <div class="btn-group" style="gap: 5px;">
                                    <button 
                                        type="button" 
                                        class="btn btn-secondary" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        onclick="viewTemplate(<?= $template['id'] ?>)"
                                        title="Voir"
                                    >
                                        👁️
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-success" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        onclick="editTemplate(<?= $template['id'] ?>)"
                                        title="Éditer"
                                    >
                                        ✏️
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-secondary" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        onclick="duplicateTemplate(<?= $template['id'] ?>)"
                                        title="Dupliquer"
                                    >
                                        📋
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-danger" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        onclick="deleteTemplate(<?= $template['id'] ?>)"
                                        title="Supprimer"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL DE PRÉVISUALISATION TEMPLATE -->
<div id="templatePreviewModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">👁️ Prévisualisation du Template</h3>
            <button type="button" class="modal-close" onclick="closeModal('templatePreviewModal')">&times;</button>
        </div>
        <div style="margin-bottom: 20px;">
            <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <p style="color: #999; margin-bottom: 8px;"><strong>Nom:</strong> <span id="previewTemplateName">-</span></p>
                <p style="color: #999; margin-bottom: 8px;"><strong>Type:</strong> <span id="previewTemplateType">-</span></p>
                <p style="color: #999; margin-bottom: 0;"><strong>Sujet:</strong> <span id="previewTemplateSubject">-</span></p>
            </div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; color: #333; max-height: 400px; overflow-y: auto;">
            <div id="previewTemplateBody">
                Aucun contenu à prévisualiser
            </div>
        </div>
        <div class="btn-group mt-20">
            <button type="button" class="btn btn-secondary" onclick="closeModal('templatePreviewModal')">
                Fermer
            </button>
        </div>
    </div>
</div>

<!-- MODAL DE VISUALISATION TEMPLATE -->
<div id="templateViewModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title">📝 Détails du Template</h3>
            <button type="button" class="modal-close" onclick="closeModal('templateViewModal')">&times;</button>
        </div>
        <div id="templateViewContent">
            <!-- Contenu chargé dynamiquement -->
        </div>
    </div>
</div>

<script>
    const templatesData = <?= json_encode($templates) ?>;
    
    // Prévisualisation en temps réel
    document.getElementById('template_body').addEventListener('input', updateTemplatePreview);
    document.getElementById('template_subject').addEventListener('input', updateTemplatePreview);
    document.getElementById('template_name').addEventListener('input', updateTemplatePreview);
    document.getElementById('template_type').addEventListener('change', updateTemplatePreview);
    
    function updateTemplatePreview() {
        const name = document.getElementById('template_name').value || '-';
        const type = document.getElementById('template_type').value || '-';
        const subject = document.getElementById('template_subject').value || '-';
        const body = document.getElementById('template_body').value || 'Aucun contenu à prévisualiser';
        
        document.getElementById('previewTemplateName').textContent = name;
        document.getElementById('previewTemplateType').textContent = type;
        document.getElementById('previewTemplateSubject').textContent = subject;
        document.getElementById('previewTemplateBody').innerHTML = body;
    }
    
    // Insérer une variable
    function insertVariable() {
        const variables = [
            '{{nom}}', '{{prenom}}', '{{email}}', '{{telephone}}',
            '{{commande_id}}', '{{montant}}', '{{statut}}', '{{date}}',
            '{{site_url}}', '{{site_name}}', '{{support_email}}', '{{annee}}'
        ];
        
        const variable = prompt('Variables disponibles:\n\n' + variables.join('\n') + '\n\nEntrez la variable à insérer:');
        
        if (variable && variables.includes(variable)) {
            const textarea = document.getElementById('template_body');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            textarea.value = text.substring(0, start) + variable + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + variable.length, start + variable.length);
        }
    }
    
    // Voir un template
    function viewTemplate(templateId) {
        const template = templatesData.find(t => t.id == templateId);
        
        if (template) {
            const content = `
                <div style="margin-bottom: 20px;">
                    <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <p style="color: #999; margin-bottom: 8px;"><strong>ID:</strong> #${template.id}</p>
                        <p style="color: #999; margin-bottom: 8px;"><strong>Nom:</strong> ${template.name}</p>
                        <p style="color: #999; margin-bottom: 8px;"><strong>Type:</strong> ${template.type}</p>
                        <p style="color: #999; margin-bottom: 8px;"><strong>Créé le:</strong> ${new Date(template.created_at).toLocaleString('fr-FR')}</p>
                        ${template.updated_at ? '<p style="color: #999; margin-bottom: 8px;"><strong>Modifié le:</strong> ' + new Date(template.updated_at).toLocaleString('fr-FR') + '</p>' : ''}
                    </div>
                    <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <p style="color: var(--primary-gold); margin-bottom: 8px; font-size: 1.1rem;"><strong>Sujet:</strong></p>
                        <p style="color: #E5E5E5;">${template.subject}</p>
                    </div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; color: #333; max-height: 400px; overflow-y: auto;">
                    <p style="color: var(--primary-gold); margin-bottom: 15px; font-size: 1.1rem;"><strong>Contenu:</strong></p>
                    ${template.body}
                </div>
                <div class="btn-group mt-20">
                    <button type="button" class="btn btn-success" onclick="editTemplate(${template.id})">
                        ✏️ Éditer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('templateViewModal')">
                        Fermer
                    </button>
                </div>
            `;
            
            document.getElementById('templateViewContent').innerHTML = content;
            openModal('templateViewModal');
        }
    }
    
    // Éditer un template
    function editTemplate(templateId) {
        const template = templatesData.find(t => t.id == templateId);
        
        if (template) {
            document.getElementById('template_name').value = template.name;
            document.getElementById('template_type').value = template.type;
            document.getElementById('template_subject').value = template.subject;
            document.getElementById('template_body').value = template.body;
            
            closeModal('templateViewModal');
            
            // Scroller vers le formulaire
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            alert('✏️ Template chargé dans le formulaire. Modifiez-le et sauvegardez.');
        }
    }
    
    // Dupliquer un template
    function duplicateTemplate(templateId) {
        const template = templatesData.find(t => t.id == templateId);
        
        if (template) {
            document.getElementById('template_name').value = template.name + ' (Copie)';
            document.getElementById('template_type').value = template.type;
            document.getElementById('template_subject').value = template.subject;
            document.getElementById('template_body').value = template.body;
            
            // Scroller vers le formulaire
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            alert('📋 Template dupliqué. Modifiez le nom et sauvegardez.');
        }
    }
    
    // Supprimer un template
    function deleteTemplate(templateId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce template ?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_template">
                <input type="hidden" name="template_id" value="${templateId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
