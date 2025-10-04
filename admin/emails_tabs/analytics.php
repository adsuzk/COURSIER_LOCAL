<?php
/**
 * ANALYTICS - Statistiques avancées et analyses
 */

// Récupérer les statistiques avancées
$analyticsData = [
    'daily' => $pdo->query("
        SELECT 
            DATE(sent_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
            ROUND(AVG(CASE WHEN opened = 1 THEN TIMESTAMPDIFF(MINUTE, sent_at, opened_at) ELSE NULL END), 1) as avg_open_time
        FROM email_logs
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(sent_at)
        ORDER BY date
    ")->fetchAll(PDO::FETCH_ASSOC),
    
    'by_type' => $pdo->query("
        SELECT 
            type,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
            ROUND(SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0), 1) as open_rate
        FROM email_logs
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY type
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC),
    
    'hourly' => $pdo->query("
        SELECT 
            HOUR(sent_at) as hour,
            COUNT(*) as total,
            SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
            ROUND(SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as open_rate
        FROM email_logs
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(sent_at)
        ORDER BY hour
    ")->fetchAll(PDO::FETCH_ASSOC),
    
    'top_recipients' => $pdo->query("
        SELECT 
            recipient,
            COUNT(*) as total_emails,
            SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
            MAX(sent_at) as last_email
        FROM email_logs
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY recipient
        ORDER BY total_emails DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC)
];

// Statistiques globales pour la période
$periodStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
        ROUND(SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0), 1) as open_rate,
        ROUND(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as fail_rate,
        COUNT(DISTINCT recipient) as unique_recipients,
        ROUND(AVG(CASE WHEN opened = 1 THEN TIMESTAMPDIFF(MINUTE, sent_at, opened_at) ELSE NULL END), 1) as avg_open_time
    FROM email_logs
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);
?>

<!-- STATISTIQUES GLOBALES 30 JOURS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📧</div>
        <div class="stat-value"><?= number_format($periodStats['total']) ?></div>
        <div class="stat-label">Total Emails (30j)</div>
        <div class="stat-detail">
            ✅ <?= number_format($periodStats['sent']) ?> envoyés | 
            ❌ <?= number_format($periodStats['failed']) ?> échoués
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👁️</div>
        <div class="stat-value"><?= $periodStats['open_rate'] ?>%</div>
        <div class="stat-label">Taux d'Ouverture</div>
        <div class="stat-detail">
            <?= number_format($periodStats['opened']) ?> emails ouverts
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">⚡</div>
        <div class="stat-value"><?= $periodStats['avg_open_time'] ?? 0 ?>m</div>
        <div class="stat-label">Temps Moyen d'Ouverture</div>
        <div class="stat-detail">
            Temps entre envoi et ouverture
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= number_format($periodStats['unique_recipients']) ?></div>
        <div class="stat-label">Destinataires Uniques</div>
        <div class="stat-detail">
            Sur les 30 derniers jours
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= $periodStats['fail_rate'] ?>%</div>
        <div class="stat-label">Taux d'Échec</div>
        <div class="stat-detail">
            <?= number_format($periodStats['failed']) ?> échecs
        </div>
    </div>
</div>

<!-- GRAPHIQUE ÉVOLUTION 30 JOURS -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📈 Évolution sur 30 Jours</h2>
    </div>
    <div class="chart-container" style="height: 400px;">
        <canvas id="dailyChart"></canvas>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px;">
    <!-- GRAPHIQUE PAR TYPE -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏷️ Performance par Type</h2>
        </div>
        <div class="chart-container" style="height: 350px;">
            <canvas id="typeChart"></canvas>
        </div>
        <div class="table-container mt-20">
            <table class="email-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Envoyés</th>
                        <th>Ouverts</th>
                        <th>Taux</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyticsData['by_type'] as $type): ?>
                        <tr>
                            <td>
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($type['type']) ?>
                                </span>
                            </td>
                            <td><?= number_format($type['total']) ?></td>
                            <td><?= number_format($type['sent']) ?></td>
                            <td><?= number_format($type['opened']) ?></td>
                            <td>
                                <strong style="color: <?= $type['open_rate'] >= 50 ? 'var(--success)' : ($type['open_rate'] >= 25 ? 'var(--warning)' : 'var(--error)') ?>;">
                                    <?= $type['open_rate'] ?>%
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- GRAPHIQUE PAR HEURE -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🕐 Performance par Heure (7j)</h2>
        </div>
        <div class="chart-container" style="height: 350px;">
            <canvas id="hourlyChart"></canvas>
        </div>
        <div style="margin-top: 20px; padding: 15px; background: var(--glass-bg); border-radius: 8px;">
            <p style="color: var(--primary-gold); font-weight: 600; margin-bottom: 10px;">💡 Meilleur moment pour envoyer:</p>
            <p style="color: #CCCCCC;">
                <?php
                $bestHour = null;
                $bestRate = 0;
                foreach ($analyticsData['hourly'] as $hour) {
                    if ($hour['open_rate'] > $bestRate) {
                        $bestRate = $hour['open_rate'];
                        $bestHour = $hour['hour'];
                    }
                }
                if ($bestHour !== null) {
                    echo "🎯 <strong>{$bestHour}h00</strong> avec un taux d'ouverture de <strong style='color: var(--success);'>{$bestRate}%</strong>";
                } else {
                    echo "Pas assez de données pour déterminer";
                }
                ?>
            </p>
        </div>
    </div>
</div>

<!-- TOP DESTINATAIRES -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">👥 Top 10 Destinataires (30j)</h2>
    </div>
    <div class="table-container">
        <table class="email-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Destinataire</th>
                    <th>Emails Reçus</th>
                    <th>Ouverts</th>
                    <th>Taux d'Ouverture</th>
                    <th>Dernier Email</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($analyticsData['top_recipients'])): ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            Aucune donnée disponible
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($analyticsData['top_recipients'] as $index => $recipient): ?>
                        <?php
                        $openRate = $recipient['total_emails'] > 0 
                            ? round(($recipient['opened'] / $recipient['total_emails']) * 100, 1) 
                            : 0;
                        ?>
                        <tr>
                            <td><strong><?= $index + 1 ?></strong></td>
                            <td class="truncate" style="max-width: 300px;">
                                <?= htmlspecialchars($recipient['recipient']) ?>
                            </td>
                            <td class="text-center">
                                <strong><?= number_format($recipient['total_emails']) ?></strong>
                            </td>
                            <td class="text-center">
                                <?= number_format($recipient['opened']) ?>
                            </td>
                            <td class="text-center">
                                <strong style="color: <?= $openRate >= 50 ? 'var(--success)' : ($openRate >= 25 ? 'var(--warning)' : 'var(--error)') ?>;">
                                    <?= $openRate ?>%
                                </strong>
                            </td>
                            <td style="white-space: nowrap;">
                                <?= date('d/m/Y H:i', strtotime($recipient['last_email'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- INSIGHTS ET RECOMMANDATIONS -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">💡 Insights & Recommandations</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <?php
        // Générer des insights basés sur les données
        $insights = [];
        
        // Taux d'ouverture
        if ($periodStats['open_rate'] >= 50) {
            $insights[] = [
                'icon' => '🎉',
                'type' => 'success',
                'title' => 'Excellent taux d\'ouverture !',
                'message' => "Votre taux d'ouverture de {$periodStats['open_rate']}% est excellent. Continuez sur cette lancée !"
            ];
        } elseif ($periodStats['open_rate'] >= 25) {
            $insights[] = [
                'icon' => '👍',
                'type' => 'info',
                'title' => 'Bon taux d\'ouverture',
                'message' => "Votre taux d'ouverture de {$periodStats['open_rate']}% est correct. Vous pouvez l'améliorer en optimisant vos sujets."
            ];
        } else {
            $insights[] = [
                'icon' => '⚠️',
                'type' => 'warning',
                'title' => 'Taux d\'ouverture à améliorer',
                'message' => "Votre taux d'ouverture de {$periodStats['open_rate']}% est bas. Travaillez sur des sujets plus accrocheurs."
            ];
        }
        
        // Taux d'échec
        if ($periodStats['fail_rate'] > 10) {
            $insights[] = [
                'icon' => '❌',
                'type' => 'error',
                'title' => 'Taux d\'échec élevé',
                'message' => "{$periodStats['fail_rate']}% d'échec. Vérifiez vos listes d'emails et la configuration SMTP."
            ];
        }
        
        // Meilleur type
        if (!empty($analyticsData['by_type'])) {
            $bestType = $analyticsData['by_type'][0];
            $insights[] = [
                'icon' => '🏆',
                'type' => 'success',
                'title' => 'Meilleur type d\'email',
                'message' => "Le type \"{$bestType['type']}\" performe le mieux avec {$bestType['open_rate']}% d'ouverture."
            ];
        }
        
        // Engagement
        $avgEmailsPerRecipient = $periodStats['unique_recipients'] > 0 
            ? round($periodStats['total'] / $periodStats['unique_recipients'], 1) 
            : 0;
        if ($avgEmailsPerRecipient > 5) {
            $insights[] = [
                'icon' => '📊',
                'type' => 'info',
                'title' => 'Fréquence d\'envoi élevée',
                'message' => "Vous envoyez en moyenne {$avgEmailsPerRecipient} emails par destinataire. Attention à ne pas spam."
            ];
        }
        
        foreach ($insights as $insight):
        ?>
            <div style="background: var(--glass-bg); padding: 20px; border-radius: 12px; border-left: 4px solid <?= 
                $insight['type'] === 'success' ? 'var(--success)' : 
                ($insight['type'] === 'error' ? 'var(--error)' : 
                ($insight['type'] === 'warning' ? 'var(--warning)' : 'var(--info)')) 
            ?>;">
                <div style="font-size: 2rem; margin-bottom: 10px;"><?= $insight['icon'] ?></div>
                <h3 style="color: var(--primary-gold); margin-bottom: 10px; font-size: 1.1rem;">
                    <?= $insight['title'] ?>
                </h3>
                <p style="color: #CCCCCC; margin: 0;">
                    <?= $insight['message'] ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- SCRIPTS POUR LES GRAPHIQUES -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Données pour les graphiques
    const dailyData = <?= json_encode($analyticsData['daily']) ?>;
    const typeData = <?= json_encode($analyticsData['by_type']) ?>;
    const hourlyData = <?= json_encode($analyticsData['hourly']) ?>;
    
    // Configuration globale Chart.js
    Chart.defaults.color = '#CCCCCC';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
    
    // GRAPHIQUE ÉVOLUTION JOURNALIÈRE
    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: dailyData.map(d => new Date(d.date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })),
            datasets: [
                {
                    label: 'Total',
                    data: dailyData.map(d => parseInt(d.total)),
                    borderColor: '#D4A853',
                    backgroundColor: 'rgba(212, 168, 83, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Envoyés',
                    data: dailyData.map(d => parseInt(d.sent)),
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Ouverts',
                    data: dailyData.map(d => parseInt(d.opened)),
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Échoués',
                    data: dailyData.map(d => parseInt(d.failed)),
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
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
                        font: { size: 14, weight: '600' },
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
                    padding: 12
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#CCCCCC', font: { size: 12 } }
                },
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#CCCCCC', font: { size: 12 } }
                }
            }
        }
    });
    
    // GRAPHIQUE PAR TYPE (Doughnut)
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: typeData.map(t => t.type),
            datasets: [{
                data: typeData.map(t => parseInt(t.total)),
                backgroundColor: [
                    'rgba(212, 168, 83, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(168, 85, 247, 0.8)'
                ],
                borderColor: '#1A1A1A',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: '#CCCCCC',
                        font: { size: 13, weight: '600' },
                        padding: 15
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(26, 26, 26, 0.95)',
                    titleColor: '#D4A853',
                    bodyColor: '#E5E5E5',
                    borderColor: '#D4A853',
                    borderWidth: 1,
                    padding: 12
                }
            }
        }
    });
    
    // GRAPHIQUE PAR HEURE (Bar)
    new Chart(document.getElementById('hourlyChart'), {
        type: 'bar',
        data: {
            labels: hourlyData.map(h => h.hour + 'h'),
            datasets: [
                {
                    label: 'Emails Envoyés',
                    data: hourlyData.map(h => parseInt(h.total)),
                    backgroundColor: 'rgba(212, 168, 83, 0.7)',
                    borderColor: '#D4A853',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Taux d\'Ouverture (%)',
                    data: hourlyData.map(h => parseFloat(h.open_rate)),
                    type: 'line',
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    yAxisID: 'y1'
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
                        font: { size: 13, weight: '600' },
                        padding: 15
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
                    padding: 12
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#CCCCCC', font: { size: 12 } }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    grid: { display: false },
                    ticks: { 
                        color: '#10B981', 
                        font: { size: 12 },
                        callback: function(value) { return value + '%'; }
                    }
                },
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#CCCCCC', font: { size: 11 } }
                }
            }
        }
    });
</script>
