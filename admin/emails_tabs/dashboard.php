<?php
/**
 * DASHBOARD - Vue d'ensemble des statistiques emails
 */
?>

<!-- STATISTIQUES RAPIDES -->
<div class="stats-grid">
    <!-- Aujourd'hui -->
    <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value"><?= number_format($statsToday['total']) ?></div>
        <div class="stat-label">Aujourd'hui</div>
        <div class="stat-detail">
            ✅ <?= $statsToday['sent'] ?? 0 ?> envoyés | 
            ❌ <?= $statsToday['failed'] ?? 0 ?> échoués | 
            👁️ <?= $statsToday['opened'] ?? 0 ?> ouverts
        </div>
    </div>
    
    <!-- Cette semaine -->
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?= number_format($statsWeek['total']) ?></div>
        <div class="stat-label">Cette Semaine</div>
        <div class="stat-detail">
            ✅ <?= $statsWeek['sent'] ?? 0 ?> envoyés | 
            ❌ <?= $statsWeek['failed'] ?? 0 ?> échoués | 
            👁️ <?= $statsWeek['opened'] ?? 0 ?> ouverts
        </div>
    </div>
    
    <!-- Ce mois -->
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-value"><?= number_format($statsMonth['total']) ?></div>
        <div class="stat-label">Ce Mois</div>
        <div class="stat-detail">
            ✅ <?= $statsMonth['sent'] ?? 0 ?> envoyés | 
            ❌ <?= $statsMonth['failed'] ?? 0 ?> échoués | 
            👁️ <?= $statsMonth['opened'] ?? 0 ?> ouverts
        </div>
    </div>
    
    <!-- Taux d'ouverture -->
    <div class="stat-card">
        <div class="stat-icon">👁️</div>
        <div class="stat-value">
            <?php 
            $openRate = $statsMonth['sent'] > 0 
                ? round(($statsMonth['opened'] / $statsMonth['sent']) * 100, 1) 
                : 0;
            echo $openRate . '%';
            ?>
        </div>
        <div class="stat-label">Taux d'Ouverture</div>
        <div class="stat-detail">
            Basé sur les <?= number_format($statsMonth['sent'] ?? 0) ?> emails envoyés ce mois
        </div>
    </div>
</div>

<!-- ACTIONS RAPIDES -->
<div class="quick-actions">
    <a href="?section=emails&email_tab=send" class="quick-action">
        ✉️ Envoyer un email
    </a>
    <a href="?section=emails&email_tab=campaign" class="quick-action">
        📢 Créer une campagne
    </a>
    <a href="?section=emails&email_tab=templates" class="quick-action">
        📝 Gérer les templates
    </a>
    <a href="?section=emails&email_tab=logs" class="quick-action">
        📋 Voir l'historique complet
    </a>
</div>

<!-- GRAPHIQUE DES 7 DERNIERS JOURS -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📈 Évolution des 7 derniers jours</h2>
    </div>
    <div class="chart-container">
        <canvas id="emailChart"></canvas>
    </div>
</div>

<!-- EMAILS RÉCENTS -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📧 Emails Récents</h2>
        <a href="?section=emails&email_tab=logs" class="btn btn-secondary">
            Voir tout
        </a>
    </div>
    <div class="table-container">
        <table class="email-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Destinataire</th>
                    <th>Sujet</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Ouvert</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentEmails)): ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            Aucun email envoyé récemment
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach (array_slice($recentEmails, 0, 10) as $email): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($email['sent_at'])) ?></td>
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
                                <?php else: ?>
                                    <span class="badge badge-error">❌ Échec</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?= $email['opened'] ? '✅' : '⬜' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CAMPAGNES RÉCENTES -->
<?php if (!empty($campaigns)): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📢 Campagnes Récentes</h2>
        <a href="?section=emails&email_tab=campaign" class="btn btn-secondary">
            Gérer
        </a>
    </div>
    <div class="table-container">
        <table class="email-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Nom</th>
                    <th>Cible</th>
                    <th>Destinataires</th>
                    <th>Envoyés</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($campaigns, 0, 5) as $campaign): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($campaign['created_at'])) ?></td>
                        <td class="truncate" style="max-width: 200px;">
                            <?= htmlspecialchars($campaign['name']) ?>
                        </td>
                        <td>
                            <span class="badge badge-info">
                                <?= htmlspecialchars($campaign['target_group']) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= number_format($campaign['total_recipients']) ?></td>
                        <td class="text-center"><?= number_format($campaign['sent_count']) ?></td>
                        <td>
                            <?php if ($campaign['status'] === 'sent'): ?>
                                <span class="badge badge-success">✅ Envoyée</span>
                            <?php elseif ($campaign['status'] === 'draft'): ?>
                                <span class="badge badge-warning">⏳ Brouillon</span>
                            <?php else: ?>
                                <span class="badge badge-info">🔄 <?= $campaign['status'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- SCRIPT POUR LE GRAPHIQUE -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Données du graphique
    const chartData = <?= json_encode($chartData) ?>;
    const labels = chartData.map(d => {
        const date = new Date(d.date);
        return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
    });
    const totals = chartData.map(d => parseInt(d.total));
    const sent = chartData.map(d => parseInt(d.sent));
    const opened = chartData.map(d => parseInt(d.opened));
    
    // Configuration du graphique
    const ctx = document.getElementById('emailChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total',
                    data: totals,
                    borderColor: '#D4A853',
                    backgroundColor: 'rgba(212, 168, 83, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Envoyés',
                    data: sent,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Ouverts',
                    data: opened,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#CCCCCC',
                        font: {
                            size: 14,
                            weight: '600'
                        },
                        padding: 20
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(26, 26, 26, 0.95)',
                    titleColor: '#D4A853',
                    bodyColor: '#E5E5E5',
                    borderColor: '#D4A853',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#CCCCCC',
                        font: {
                            size: 12
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#CCCCCC',
                        font: {
                            size: 12
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
</script>
