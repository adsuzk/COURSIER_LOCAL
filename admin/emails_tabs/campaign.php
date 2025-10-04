<?php
/**
 * CAMPAIGN - Gestion des campagnes d'emails
 */
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">📢 Créer une Campagne Email</h2>
    </div>
    
    <form method="POST" action="?section=emails&email_tab=campaign">
        <input type="hidden" name="action" value="send_campaign">
        
        <div class="form-group">
            <label class="form-label" for="campaign_subject">
                📝 Sujet de la Campagne *
            </label>
            <input 
                type="text" 
                id="campaign_subject" 
                name="campaign_subject" 
                class="form-input" 
                placeholder="Ex: Promotion Exceptionnelle - Coursier Suzosky"
                required
            >
        </div>
        
        <div class="form-group">
            <label class="form-label" for="target">
                🎯 Cible de la Campagne *
            </label>
            <select id="target" name="target" class="form-select" onchange="updateRecipientCount(this.value)" required>
                <option value="">-- Sélectionner une cible --</option>
                <option value="all">📧 Tous les clients</option>
                <option value="particuliers">👤 Clients Particuliers uniquement</option>
                <option value="business">🏢 Clients Business uniquement</option>
            </select>
            <div id="recipientCount" style="margin-top: 10px; color: var(--primary-gold); font-weight: 600;">
                <!-- Nombre de destinataires s'affichera ici -->
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="campaign_body">
                📄 Contenu de la Campagne *
            </label>
            <textarea 
                id="campaign_body" 
                name="campaign_body" 
                class="form-textarea" 
                placeholder="Écrivez le contenu de votre campagne... (HTML supporté)"
                rows="15"
                required
            ></textarea>
        </div>
        
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                📢 Lancer la Campagne
            </button>
            <button type="button" class="btn btn-secondary" onclick="openModal('campaignPreviewModal')">
                👁️ Prévisualiser
            </button>
            <button type="button" class="btn btn-secondary" onclick="openModal('campaignTemplatesModal')">
                📝 Charger un Template
            </button>
            <button type="reset" class="btn btn-secondary">
                🔄 Réinitialiser
            </button>
        </div>
    </form>
</div>

<!-- HISTORIQUE DES CAMPAGNES -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📊 Historique des Campagnes</h2>
    </div>
    
    <div class="table-container">
        <table class="email-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Nom</th>
                    <th>Sujet</th>
                    <th>Cible</th>
                    <th>Total</th>
                    <th>Envoyés</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="9" class="text-center">
                            Aucune campagne créée pour le moment
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td><strong>#<?= $campaign['id'] ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($campaign['created_at'])) ?></td>
                            <td class="truncate" style="max-width: 150px;">
                                <?= htmlspecialchars($campaign['name']) ?>
                            </td>
                            <td class="truncate" style="max-width: 200px;">
                                <?= htmlspecialchars($campaign['subject']) ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php
                                    $targetLabels = [
                                        'all' => '📧 Tous',
                                        'particuliers' => '👤 Particuliers',
                                        'business' => '🏢 Business'
                                    ];
                                    echo $targetLabels[$campaign['target_group']] ?? $campaign['target_group'];
                                    ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?= number_format($campaign['total_recipients']) ?>
                            </td>
                            <td class="text-center">
                                <strong style="color: var(--success);">
                                    <?= number_format($campaign['sent_count']) ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($campaign['status'] === 'sent'): ?>
                                    <span class="badge badge-success">✅ Envoyée</span>
                                <?php elseif ($campaign['status'] === 'draft'): ?>
                                    <span class="badge badge-warning">⏳ Brouillon</span>
                                <?php elseif ($campaign['status'] === 'sending'): ?>
                                    <span class="badge badge-info">🔄 En cours</span>
                                <?php else: ?>
                                    <span class="badge badge-error">❌ Échec</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" style="gap: 5px;">
                                    <button 
                                        type="button" 
                                        class="btn btn-secondary" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        onclick="viewCampaign(<?= $campaign['id'] ?>)"
                                        title="Voir les détails"
                                    >
                                        👁️
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

<!-- MODAL DE PRÉVISUALISATION CAMPAGNE -->
<div id="campaignPreviewModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">👁️ Prévisualisation de la Campagne</h3>
            <button type="button" class="modal-close" onclick="closeModal('campaignPreviewModal')">&times;</button>
        </div>
        <div style="margin-bottom: 20px;">
            <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <p style="color: #999; margin-bottom: 8px;"><strong>Cible:</strong> <span id="previewTarget">-</span></p>
                <p style="color: #999; margin-bottom: 8px;"><strong>Destinataires:</strong> <span id="previewRecipients">-</span></p>
                <p style="color: #999; margin-bottom: 0;"><strong>Sujet:</strong> <span id="previewCampaignSubject">-</span></p>
            </div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; color: #333; max-height: 400px; overflow-y: auto;">
            <div id="previewCampaignBody">
                Aucun contenu à prévisualiser
            </div>
        </div>
        <div class="btn-group mt-20">
            <button type="button" class="btn btn-secondary" onclick="closeModal('campaignPreviewModal')">
                Fermer
            </button>
        </div>
    </div>
</div>

<!-- MODAL DES TEMPLATES CAMPAGNE -->
<div id="campaignTemplatesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">📝 Templates de Campagne</h3>
            <button type="button" class="modal-close" onclick="closeModal('campaignTemplatesModal')">&times;</button>
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
                                        onclick="loadCampaignTemplate(<?= $template['id'] ?>)"
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
    // Compter les destinataires selon la cible
    const recipientCounts = <?= json_encode([
        'all' => $pdo->query("SELECT COUNT(DISTINCT email) FROM (
            SELECT email FROM clients_particuliers WHERE email IS NOT NULL AND email != ''
            UNION
            SELECT contact_email as email FROM business_clients WHERE contact_email IS NOT NULL AND contact_email != ''
        ) as all_emails")->fetchColumn(),
        'particuliers' => $pdo->query("SELECT COUNT(*) FROM clients_particuliers WHERE email IS NOT NULL AND email != ''")->fetchColumn(),
        'business' => $pdo->query("SELECT COUNT(*) FROM business_clients WHERE contact_email IS NOT NULL AND contact_email != ''")->fetchColumn()
    ]) ?>;
    
    function updateRecipientCount(target) {
        const countDiv = document.getElementById('recipientCount');
        if (target && recipientCounts[target]) {
            const count = recipientCounts[target];
            const targetLabels = {
                'all': '📧 Tous les clients',
                'particuliers': '👤 Clients Particuliers',
                'business': '🏢 Clients Business'
            };
            countDiv.innerHTML = `
                <span style="font-size: 1.1rem;">
                    ${targetLabels[target]}: <strong style="color: var(--success);">${count.toLocaleString()}</strong> destinataires
                </span>
            `;
        } else {
            countDiv.innerHTML = '';
        }
    }
    
    // Prévisualisation campagne
    document.getElementById('campaign_body').addEventListener('input', updateCampaignPreview);
    document.getElementById('campaign_subject').addEventListener('input', updateCampaignPreview);
    document.getElementById('target').addEventListener('change', updateCampaignPreview);
    
    function updateCampaignPreview() {
        const subject = document.getElementById('campaign_subject').value || '-';
        const body = document.getElementById('campaign_body').value || 'Aucun contenu à prévisualiser';
        const target = document.getElementById('target').value;
        const targetLabels = {
            'all': '📧 Tous les clients',
            'particuliers': '👤 Clients Particuliers',
            'business': '🏢 Clients Business'
        };
        
        document.getElementById('previewCampaignSubject').textContent = subject;
        document.getElementById('previewCampaignBody').innerHTML = body;
        document.getElementById('previewTarget').textContent = targetLabels[target] || '-';
        document.getElementById('previewRecipients').textContent = target && recipientCounts[target] 
            ? recipientCounts[target].toLocaleString() + ' destinataires'
            : '-';
    }
    
    // Charger un template pour campagne
    function loadCampaignTemplate(templateId) {
        const templates = <?= json_encode($templates) ?>;
        const template = templates.find(t => t.id == templateId);
        if (template) {
            document.getElementById('campaign_subject').value = template.subject;
            document.getElementById('campaign_body').value = template.body;
            closeModal('campaignTemplatesModal');
            updateCampaignPreview();
            alert('✅ Template chargé avec succès !');
        }
    }
    
    // Voir les détails d'une campagne
    function viewCampaign(campaignId) {
        window.location.href = `?section=emails&email_tab=logs&campaign_id=${campaignId}`;
    }
</script>
