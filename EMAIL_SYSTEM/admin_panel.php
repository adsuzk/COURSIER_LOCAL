<?php
/**
 * SECTION ADMIN - GESTION D'EMAILS
 * À intégrer dans admin.php existant
 */

// Assurer que EmailManager est disponible
require_once __DIR__ . '/EmailManager.php';

class EmailAdminPanel {
    private $emailManager;
    private $pdo;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->emailManager = new EmailManager($pdo, $config);
    }
    
    /**
     * Rendre le contenu de la section emails
     */
    public function renderEmailSection() {
        $activeTab = $_GET['email_tab'] ?? 'dashboard';
        
        echo '<div class="email-admin-container">';
        $this->renderEmailTabs($activeTab);
        
        switch ($activeTab) {
            case 'dashboard':
                $this->renderDashboard();
                break;
            case 'logs':
                $this->renderEmailLogs();
                break;
            case 'campaigns':
                $this->renderCampaigns();
                break;
            case 'templates':
                $this->renderTemplates();
                break;
            case 'settings':
                $this->renderSettings();
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Onglets de navigation
     */
    private function renderEmailTabs($activeTab) {
        $tabs = [
            'dashboard' => ['📊', 'Tableau de bord'],
            'logs' => ['📧', 'Logs d\'emails'],
            'campaigns' => ['📢', 'Campagnes'],
            'templates' => ['📝', 'Templates'],
            'settings' => ['⚙️', 'Paramètres']
        ];
        
        echo '<div class="email-tabs">';
        foreach ($tabs as $key => [$icon, $label]) {
            $active = $key === $activeTab ? 'active' : '';
            echo "<a href='?section=emails&email_tab=$key' class='email-tab $active'>$icon $label</a>";
        }
        echo '</div>';
    }
    
    /**
     * Dashboard principal
     */
    private function renderDashboard() {
        $stats = $this->emailManager->getEmailStats(7); // 7 derniers jours
        $recentEmails = $this->emailManager->getRecentEmails(10);
        
        echo '<div class="email-dashboard">';
        
        // Statistiques rapides
        echo '<div class="stats-grid">';
        $this->renderQuickStats($stats);
        echo '</div>';
        
        // Graphiques
        echo '<div class="charts-container">';
        $this->renderEmailChart($stats);
        echo '</div>';
        
        // Emails récents
        echo '<div class="recent-emails">';
        echo '<h3>📧 Emails récents</h3>';
        $this->renderEmailTable($recentEmails, true);
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Statistiques rapides
     */
    private function renderQuickStats($stats) {
        $totals = [
            'sent' => 0,
            'failed' => 0,
            'opened' => 0,
            'clicked' => 0
        ];
        
        foreach ($stats as $stat) {
            if (isset($totals[$stat['status']])) {
                $totals[$stat['status']] += $stat['count'];
            }
        }
        
        $openRate = $totals['sent'] > 0 ? round(($totals['opened'] / $totals['sent']) * 100, 1) : 0;
        $clickRate = $totals['opened'] > 0 ? round(($totals['clicked'] / $totals['opened']) * 100, 1) : 0;
        
        $statCards = [
            ['📤', 'Emails envoyés', $totals['sent'], 'success'],
            ['❌', 'Echecs', $totals['failed'], 'danger'],
            ['👀', 'Taux ouverture', $openRate . '%', 'info'],
            ['🖱️', 'Taux clic', $clickRate . '%', 'warning']
        ];
        
        foreach ($statCards as [$icon, $label, $value, $type]) {
            echo "<div class='stat-card $type'>";
            echo "<div class='stat-icon'>$icon</div>";
            echo "<div class='stat-content'>";
            echo "<div class='stat-value'>$value</div>";
            echo "<div class='stat-label'>$label</div>";
            echo "</div>";
            echo "</div>";
        }
    }
    
    /**
     * Graphique des emails
     */
    private function renderEmailChart($stats) {
        echo '<div class="chart-container">';
        echo '<h3>📈 Évolution des emails (7 derniers jours)</h3>';
        echo '<canvas id="emailChart" width="400" height="200"></canvas>';
        echo '</div>';
        
        // Données pour le graphique
        $chartData = [];
        foreach ($stats as $stat) {
            if (!isset($chartData[$stat['date']])) {
                $chartData[$stat['date']] = ['sent' => 0, 'failed' => 0, 'opened' => 0];
            }
            $chartData[$stat['date']][$stat['status']] = $stat['count'];
        }
        
        echo '<script>';
        echo 'var emailChartData = ' . json_encode($chartData) . ';';
        echo '</script>';
    }
    
    /**
     * Logs d'emails
     */
    private function renderEmailLogs() {
        $page = $_GET['page'] ?? 1;
        $filter = $_GET['filter'] ?? '';
        
        echo '<div class="email-logs">';
        echo '<h2>📧 Logs d\'emails</h2>';
        
        // Filtres
        echo '<div class="logs-filters">';
        echo '<form method="GET" class="filter-form">';
        echo '<input type="hidden" name="section" value="emails">';
        echo '<input type="hidden" name="email_tab" value="logs">';
        echo '<select name="filter" onchange="this.form.submit()">';
        echo '<option value="">Tous les emails</option>';
        echo '<option value="password_reset"' . ($filter === 'password_reset' ? ' selected' : '') . '>Reset password</option>';
        echo '<option value="campaign"' . ($filter === 'campaign' ? ' selected' : '') . '>Campagnes</option>';
        echo '<option value="failed"' . ($filter === 'failed' ? ' selected' : '') . '>Échecs seulement</option>';
        echo '</select>';
        echo '</form>';
        echo '</div>';
        
        // Table des emails
        $emails = $this->getFilteredEmails($filter, $page);
        $this->renderEmailTable($emails);
        
        echo '</div>';
    }
    
    /**
     * Table des emails
     */
    private function renderEmailTable($emails, $compact = false) {
        echo '<div class="email-table-container">';
        echo '<table class="email-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>📅 Date</th>';
        echo '<th>📧 Destinataire</th>';
        echo '<th>📝 Sujet</th>';
        echo '<th>🏷️ Type</th>';
        echo '<th>📊 Statut</th>';
        if (!$compact) {
            echo '<th>👀 Ouvert</th>';
            echo '<th>🖱️ Cliqué</th>';
            echo '<th>⚙️ Actions</th>';
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($emails as $email) {
            echo '<tr>';
            echo '<td>' . date('d/m H:i', strtotime($email['created_at'])) . '</td>';
            echo '<td>' . htmlspecialchars($email['recipient_email']) . '</td>';
            echo '<td>' . htmlspecialchars(substr($email['subject'], 0, 50)) . '...</td>';
            echo '<td>' . $this->getTypeLabel($email['email_type']) . '</td>';
            echo '<td>' . $this->getStatusBadge($email['status']) . '</td>';
            
            if (!$compact) {
                echo '<td>' . ($email['opened_at'] ? '✅' : '⏸️') . '</td>';
                echo '<td>' . ($email['clicked_at'] ? '✅' : '⏸️') . '</td>';
                echo '<td>';
                echo "<button onclick='viewEmail({$email['id']})' class='btn-sm'>👁️</button>";
                if ($email['status'] === 'failed') {
                    echo "<button onclick='retryEmail({$email['id']})' class='btn-sm'>🔄</button>";
                }
                echo '</td>';
            }
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Section campagnes
     */
    private function renderCampaigns() {
        echo '<div class="email-campaigns">';
        echo '<div class="campaigns-header">';
        echo '<h2>📢 Campagnes email</h2>';
        echo '<button onclick="createCampaign()" class="btn-primary">➕ Nouvelle campagne</button>';
        echo '</div>';
        
        // Liste des campagnes
        $campaigns = $this->getCampaigns();
        
        echo '<div class="campaigns-grid">';
        foreach ($campaigns as $campaign) {
            $this->renderCampaignCard($campaign);
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Carte de campagne
     */
    private function renderCampaignCard($campaign) {
        $statusClass = strtolower($campaign['status']);
        $statusIcon = [
            'draft' => '📝',
            'scheduled' => '⏰',
            'sending' => '📤',
            'sent' => '✅',
            'paused' => '⏸️'
        ][$campaign['status']] ?? '❓';
        
        echo "<div class='campaign-card $statusClass'>";
        echo "<div class='campaign-header'>";
        echo "<h4>{$campaign['name']}</h4>";
        echo "<span class='campaign-status'>$statusIcon " . ucfirst($campaign['status']) . "</span>";
        echo "</div>";
        echo "<div class='campaign-stats'>";
        echo "<div>👥 {$campaign['total_recipients']} destinataires</div>";
        echo "<div>📤 {$campaign['emails_sent']} envoyés</div>";
        echo "<div>👀 {$campaign['emails_opened']} ouverts</div>";
        echo "</div>";
        echo "<div class='campaign-actions'>";
        echo "<button onclick='editCampaign({$campaign['id']})'>✏️ Modifier</button>";
        if ($campaign['status'] === 'draft') {
            echo "<button onclick='sendCampaign({$campaign['id']})'>🚀 Envoyer</button>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    /**
     * Section templates
     */
    private function renderTemplates() {
        echo '<div class="email-templates">';
        echo '<div class="templates-header">';
        echo '<h2>📝 Templates d\'emails</h2>';
        echo '<button onclick="createTemplate()" class="btn-primary">➕ Nouveau template</button>';
        echo '</div>';
        
        $templates = $this->getTemplates();
        
        echo '<div class="templates-list">';
        foreach ($templates as $template) {
            $this->renderTemplateCard($template);
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Paramètres
     */
    private function renderSettings() {
        echo '<div class="email-settings">';
        echo '<h2>⚙️ Paramètres Email</h2>';
        
        echo '<form method="POST" action="?section=emails&email_tab=settings">';
        
        echo '<div class="settings-group">';
        echo '<h3>📧 Configuration SMTP</h3>';
        echo '<label>Email expéditeur</label>';
        echo '<input type="email" name="smtp_from" value="reply@conciergerie-privee-suzosky.com" readonly>';
        echo '<small>Configuré automatiquement pour votre domaine</small>';
        echo '</div>';
        
        echo '<div class="settings-group">';
        echo '<h3>🛡️ Anti-spam</h3>';
        echo '<label><input type="checkbox" checked> Headers SPF/DKIM</label>';
        echo '<label><input type="checkbox" checked> Rate limiting (max 100/heure)</label>';
        echo '<label><input type="checkbox" checked> Validation domaines</label>';
        echo '</div>';
        
        echo '<div class="settings-group">';
        echo '<h3>📊 Tracking</h3>';
        echo '<label><input type="checkbox" checked> Pixel de tracking ouverture</label>';
        echo '<label><input type="checkbox" checked> Tracking des clics</label>';
        echo '<label><input type="checkbox"> Géolocalisation IP</label>';
        echo '</div>';
        
        echo '<button type="submit" class="btn-primary">💾 Sauvegarder</button>';
        echo '</form>';
        
        echo '</div>';
    }
    
    // Méthodes utilitaires
    private function getTypeLabel($type) {
        $labels = [
            'password_reset' => '🔐 Reset MDP',
            'campaign' => '📢 Campagne',
            'notification' => '🔔 Notification',
            'welcome' => '👋 Bienvenue'
        ];
        return $labels[$type] ?? $type;
    }
    
    private function getStatusBadge($status) {
        $badges = [
            'pending' => '<span class="status-badge pending">⏳ En attente</span>',
            'sent' => '<span class="status-badge success">✅ Envoyé</span>',
            'failed' => '<span class="status-badge error">❌ Échec</span>',
            'opened' => '<span class="status-badge info">👀 Ouvert</span>',
            'clicked' => '<span class="status-badge warning">🖱️ Cliqué</span>'
        ];
        return $badges[$status] ?? '<span class="status-badge">' . $status . '</span>';
    }
    
    private function getFilteredEmails($filter, $page = 1) {
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if ($filter === 'failed') {
            $whereClause = "WHERE status = 'failed'";
        } elseif ($filter) {
            $whereClause = "WHERE email_type = ?";
            $params[] = $filter;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_logs 
            $whereClause
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getCampaigns() {
        $stmt = $this->pdo->prepare("SELECT * FROM email_campaigns ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTemplates() {
        $stmt = $this->pdo->prepare("SELECT * FROM email_templates ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>