<?php
/**
 * LOGS - Historique complet des emails envoyés
 */

// Filtres
$filterCampaign = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
$filterStatus = $_GET['filter_status'] ?? 'all';
$filterType = $_GET['filter_type'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Construire la requête avec filtres
$where = ['1=1'];
$params = [];

if ($filterCampaign) {
    $where[] = 'campaign_id = ?';
    $params[] = $filterCampaign;
}

if ($filterStatus !== 'all') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}

if ($filterType !== 'all') {
    $where[] = 'type = ?';
    $params[] = $filterType;
}

if ($searchTerm) {
    $where[] = '(recipient LIKE ? OR subject LIKE ?)';
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
}

$whereClause = implode(' AND ', $where);

// Compter le total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE $whereClause");
$countStmt->execute($params);
$totalEmails = $countStmt->fetchColumn();
$totalPages = ceil($totalEmails / $perPage);

// Récupérer les emails
$stmt = $pdo->prepare("
    SELECT * FROM email_logs 
    WHERE $whereClause
    ORDER BY sent_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">📋 Historique des Emails</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: #999;">
                Total: <strong style="color: var(--primary-gold);"><?= number_format($totalEmails) ?></strong>
            </span>
        </div>
    </div>
    
    <!-- FILTRES -->
    <form method="GET" action="?section=emails&email_tab=logs" style="margin-bottom: 20px;">
        <input type="hidden" name="section" value="emails">
        <input type="hidden" name="email_tab" value="logs">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="search">🔍 Recherche</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    class="form-input" 
                    placeholder="Email ou sujet..."
                    value="<?= htmlspecialchars($searchTerm) ?>"
                >
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="filter_status">📊 Statut</label>
                <select id="filter_status" name="filter_status" class="form-select">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : '' ?>>✅ Envoyés</option>
                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>❌ Échoués</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>⏳ En attente</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="filter_type">🏷️ Type</label>
                <select id="filter_type" name="filter_type" class="form-select">
                    <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="general" <?= $filterType === 'general' ? 'selected' : '' ?>>Général</option>
                    <option value="welcome" <?= $filterType === 'welcome' ? 'selected' : '' ?>>Bienvenue</option>
                    <option value="order" <?= $filterType === 'order' ? 'selected' : '' ?>>Commande</option>
                    <option value="campaign" <?= $filterType === 'campaign' ? 'selected' : '' ?>>Campagne</option>
                    <option value="notification" <?= $filterType === 'notification' ? 'selected' : '' ?>>Notification</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="opacity: 0;">Action</label>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        🔍 Filtrer
                    </button>
                    <a href="?section=emails&email_tab=logs" class="btn btn-secondary">
                        🔄 Réinitialiser
                    </a>
                </div>
            </div>
        </div>
    </form>
    
    <!-- TABLEAU DES EMAILS -->
    <div class="table-container">
        <table class="email-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Destinataire</th>
                    <th>Sujet</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Ouvert</th>
                    <th>Campagne</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($emails)): ?>
                    <tr>
                        <td colspan="9" class="text-center">
                            Aucun email trouvé avec ces critères
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($emails as $email): ?>
                        <tr>
                            <td><strong>#<?= $email['id'] ?></strong></td>
                            <td style="white-space: nowrap;">
                                <?= date('d/m/Y', strtotime($email['sent_at'])) ?><br>
                                <small style="color: #999;"><?= date('H:i', strtotime($email['sent_at'])) ?></small>
                            </td>
                            <td class="truncate" style="max-width: 200px;">
                                <?= htmlspecialchars($email['recipient']) ?>
                            </td>
                            <td class="truncate" style="max-width: 250px;">
                                <?= htmlspecialchars($email['subject']) ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($email['type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($email['status'] === 'sent'): ?>
                                    <span class="badge badge-success">✅ Envoyé</span>
                                <?php elseif ($email['status'] === 'failed'): ?>
                                    <span class="badge badge-error">❌ Échec</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">⏳ En attente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($email['opened']): ?>
                                    <span title="Ouvert le <?= date('d/m/Y H:i', strtotime($email['opened_at'])) ?>">
                                        ✅
                                    </span>
                                <?php else: ?>
                                    ⬜
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($email['campaign_id']): ?>
                                    <a href="?section=emails&email_tab=logs&campaign_id=<?= $email['campaign_id'] ?>" 
                                       style="color: var(--primary-gold); text-decoration: none;"
                                       title="Voir la campagne">
                                        #<?= $email['campaign_id'] ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" style="gap: 5px;">
                                    <button 
                                        type="button" 
                                        class="btn btn-secondary" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        onclick="viewEmail(<?= $email['id'] ?>)"
                                        title="Voir le contenu"
                                    >
                                        👁️
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-danger" 
                                        style="padding: 6px 12px; font-size: 0.85rem;"
                                        onclick="confirmDelete(<?= $email['id'] ?>, 'email')"
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
    
    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--glass-border);">
            <?php if ($page > 1): ?>
                <a href="?section=emails&email_tab=logs&page=<?= $page - 1 ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?= $filterStatus !== 'all' ? '&filter_status=' . $filterStatus : '' ?><?= $filterType !== 'all' ? '&filter_type=' . $filterType : '' ?>" 
                   class="btn btn-secondary">
                    ← Précédent
                </a>
            <?php endif; ?>
            
            <span style="color: #CCCCCC; font-weight: 600;">
                Page <?= $page ?> / <?= $totalPages ?>
            </span>
            
            <?php if ($page < $totalPages): ?>
                <a href="?section=emails&email_tab=logs&page=<?= $page + 1 ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?= $filterStatus !== 'all' ? '&filter_status=' . $filterStatus : '' ?><?= $filterType !== 'all' ? '&filter_type=' . $filterType : '' ?>" 
                   class="btn btn-secondary">
                    Suivant →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- STATISTIQUES RAPIDES DES LOGS -->
<?php
$logStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
        COUNT(DISTINCT recipient) as unique_recipients
    FROM email_logs
    WHERE $whereClause
", PDO::FETCH_ASSOC)->execute($params);
$logStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
        COUNT(DISTINCT recipient) as unique_recipients
    FROM email_logs
    WHERE $whereClause
")->fetch(PDO::FETCH_ASSOC);
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📧</div>
        <div class="stat-value"><?= number_format($logStats['total']) ?></div>
        <div class="stat-label">Total Emails</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= number_format($logStats['sent']) ?></div>
        <div class="stat-label">Envoyés</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= number_format($logStats['failed']) ?></div>
        <div class="stat-label">Échoués</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👁️</div>
        <div class="stat-value"><?= number_format($logStats['opened']) ?></div>
        <div class="stat-label">Ouverts</div>
        <div class="stat-detail">
            <?php
            $openRate = $logStats['sent'] > 0 
                ? round(($logStats['opened'] / $logStats['sent']) * 100, 1) 
                : 0;
            echo "Taux: {$openRate}%";
            ?>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= number_format($logStats['unique_recipients']) ?></div>
        <div class="stat-label">Destinataires Uniques</div>
    </div>
</div>

<!-- MODAL DE VISUALISATION D'EMAIL -->
<div id="emailViewModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title">📧 Détails de l'Email</h3>
            <button type="button" class="modal-close" onclick="closeModal('emailViewModal')">&times;</button>
        </div>
        <div id="emailViewContent">
            <!-- Contenu chargé dynamiquement -->
        </div>
    </div>
</div>

<script>
    function viewEmail(emailId) {
        // Charger les détails de l'email
        const emails = <?= json_encode($emails) ?>;
        const email = emails.find(e => e.id == emailId);
        
        if (email) {
            const content = `
                <div style="margin-bottom: 20px;">
                    <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <p style="color: #999; margin-bottom: 8px;"><strong>ID:</strong> #${email.id}</p>
                        <p style="color: #999; margin-bottom: 8px;"><strong>Date:</strong> ${new Date(email.sent_at).toLocaleString('fr-FR')}</p>
                        <p style="color: #999; margin-bottom: 8px;"><strong>Destinataire:</strong> ${email.recipient}</p>
                        <p style="color: #999; margin-bottom: 8px;"><strong>Type:</strong> ${email.type}</p>
                        <p style="color: #999; margin-bottom: 8px;"><strong>Statut:</strong> ${email.status}</p>
                        <p style="color: #999; margin-bottom: 0;"><strong>Ouvert:</strong> ${email.opened ? 'Oui (' + new Date(email.opened_at).toLocaleString('fr-FR') + ')' : 'Non'}</p>
                    </div>
                    <div style="background: var(--glass-bg); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <p style="color: var(--primary-gold); margin-bottom: 8px; font-size: 1.1rem;"><strong>Sujet:</strong></p>
                        <p style="color: #E5E5E5;">${email.subject}</p>
                    </div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; color: #333; max-height: 400px; overflow-y: auto;">
                    <p style="color: var(--primary-gold); margin-bottom: 15px; font-size: 1.1rem;"><strong>Contenu:</strong></p>
                    ${email.body}
                </div>
                <div class="btn-group mt-20">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('emailViewModal')">
                        Fermer
                    </button>
                </div>
            `;
            
            document.getElementById('emailViewContent').innerHTML = content;
            openModal('emailViewModal');
        }
    }
</script>
