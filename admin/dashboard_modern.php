<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - COURSIER LOCAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #ecf0f1;
            --dark-bg: #34495e;
            --text-color: #2c3e50;
            --border-color: #bdc3c7;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --radius: 8px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-color);
        }

        /* Header */
        .admin-header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 15px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .logo i {
            background: linear-gradient(135deg, var(--secondary-color), var(--success-color));
            color: white;
            padding: 10px;
            border-radius: 50%;
            font-size: 1.2rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-color);
        }

        .btn-logout {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius);
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #c0392b;
        }

        /* Navigation */
        .admin-nav {
            background: var(--primary-color);
            padding: 0;
            box-shadow: var(--shadow);
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            overflow-x: auto;
        }

        .nav-item {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background: var(--dark-bg);
            border-bottom-color: var(--secondary-color);
        }

        /* Main Content */
        .admin-main {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .page-subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .card-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .card-subtitle {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .card-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Status Cards */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .status-card {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--secondary-color);
        }

        .status-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .status-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .quick-actions h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .nav-content {
                flex-direction: column;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .status-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card, .status-card, .quick-actions {
            animation: fadeInUp 0.6s ease-out;
        }

        .dashboard-card:nth-child(1) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.2s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.3s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-motorcycle"></i>
                <span>COURSIER LOCAL</span>
                <span style="font-size: 0.8rem; background: var(--success-color); color: white; padding: 4px 8px; border-radius: 4px;">ADMIN</span>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span>Administrateur</span>
                </div>
                <a href="?section=logout" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>
        </div>
    </header>

    <nav class="admin-nav">
        <div class="nav-content">
            <a href="?" class="nav-item <?= (!isset($_GET['section']) || $_GET['section'] === 'dashboard') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                Tableau de bord
            </a>
            <a href="commandes.php" class="nav-item">
                <i class="fas fa-box"></i>
                Commandes
            </a>
            <a href="coursiers.php" class="nav-item">
                <i class="fas fa-motorcycle"></i>
                Coursiers
            </a>
            <a href="reclamations.php" class="nav-item">
                <i class="fas fa-exclamation-triangle"></i>
                Réclamations
            </a>
            <a href="../agent.php" class="nav-item">
                <i class="fas fa-users"></i>
                Agents
            </a>
            <a href="../business.php" class="nav-item">
                <i class="fas fa-building"></i>
                Entreprises
            </a>
            <a href="../reclamation.php" class="nav-item">
                <i class="fas fa-robot"></i>
                Chat IA
            </a>
        </div>
    </nav>

    <main class="admin-main">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-tachometer-alt"></i>
                Tableau de bord
            </h1>
            <p class="page-subtitle">Vue d'ensemble de votre plateforme de coursier</p>
        </div>

        <?php
        // Connexion à la base de données pour les statistiques
        try {
            include '../config.php';
            $pdo = new PDO('mysql:host=localhost;dbname=coursier_prod', 'root', '');
            
            // Statistiques principales
            $stats = [];
            
            // Total commandes
            $stmt = $pdo->query('SELECT COUNT(*) FROM commandes');
            $stats['commandes'] = $stmt->fetchColumn();
            
            // Commandes aujourd'hui
            $stmt = $pdo->query('SELECT COUNT(*) FROM commandes WHERE DATE(created_at) = CURDATE()');
            $stats['commandes_today'] = $stmt->fetchColumn();
            
            // Commandes en cours
            $stmt = $pdo->query('SELECT COUNT(*) FROM commandes WHERE statut IN ("nouvelle", "assignee", "en_course", "recuperee")');
            $stats['commandes_en_cours'] = $stmt->fetchColumn();
            
            // Total coursiers
            $stmt = $pdo->query('SELECT COUNT(*) FROM coursiers');
            $stats['coursiers'] = $stmt->fetchColumn();
            
            // Coursiers actifs
            $stmt = $pdo->query('SELECT COUNT(*) FROM coursiers WHERE statut = "actif"');
            $stats['coursiers_actifs'] = $stmt->fetchColumn();
            
            // Réclamations
            $stmt = $pdo->query('SELECT COUNT(*) FROM reclamations');
            $stats['reclamations'] = $stmt->fetchColumn();
            
            // Chiffre d'affaires du mois (exemple)
            $stats['ca_mois'] = rand(15000, 25000);
            
        } catch(Exception $e) {
            $stats = [
                'commandes' => 0,
                'commandes_today' => 0,
                'commandes_en_cours' => 0,
                'coursiers' => 0,
                'coursiers_actifs' => 0,
                'reclamations' => 0,
                'ca_mois' => 0
            ];
        }
        ?>

        <div class="status-grid">
            <div class="status-card">
                <div class="status-number"><?= number_format($stats['commandes']) ?></div>
                <div class="status-label">Total commandes</div>
            </div>
            <div class="status-card">
                <div class="status-number"><?= $stats['commandes_today'] ?></div>
                <div class="status-label">Commandes aujourd'hui</div>
            </div>
            <div class="status-card">
                <div class="status-number"><?= $stats['commandes_en_cours'] ?></div>
                <div class="status-label">Commandes en cours</div>
            </div>
            <div class="status-card">
                <div class="status-number"><?= $stats['coursiers'] ?></div>
                <div class="status-label">Total coursiers</div>
            </div>
            <div class="status-card">
                <div class="status-number"><?= $stats['coursiers_actifs'] ?></div>
                <div class="status-label">Coursiers actifs</div>
            </div>
            <div class="status-card">
                <div class="status-number"><?= $stats['reclamations'] ?></div>
                <div class="status-label">Réclamations</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: var(--secondary-color);">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="card-title">Gestion des commandes</div>
                </div>
                <div class="card-value"><?= number_format($stats['commandes']) ?></div>
                <div class="card-subtitle">Commandes totales dans le système</div>
                <div class="card-actions">
                    <a href="commandes.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i> Voir toutes
                    </a>
                    <a href="commandes.php?filter=en_cours" class="btn btn-warning">
                        <i class="fas fa-clock"></i> En cours (<?= $stats['commandes_en_cours'] ?>)
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: var(--success-color);">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <div class="card-title">Équipe de coursiers</div>
                </div>
                <div class="card-value"><?= $stats['coursiers'] ?></div>
                <div class="card-subtitle"><?= $stats['coursiers_actifs'] ?> coursiers actifs</div>
                <div class="card-actions">
                    <a href="coursiers.php" class="btn btn-success">
                        <i class="fas fa-users"></i> Gérer l'équipe
                    </a>
                    <a href="coursiers.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nouveau coursier
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: var(--warning-color);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="card-title">Support client</div>
                </div>
                <div class="card-value"><?= $stats['reclamations'] ?></div>
                <div class="card-subtitle">Réclamations à traiter</div>
                <div class="card-actions">
                    <a href="reclamations.php" class="btn btn-warning">
                        <i class="fas fa-headset"></i> Support
                    </a>
                    <a href="../reclamation.php" class="btn btn-primary">
                        <i class="fas fa-robot"></i> Chat IA
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: var(--danger-color);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-title">Performance</div>
                </div>
                <div class="card-value"><?= number_format($stats['ca_mois']) ?>€</div>
                <div class="card-subtitle">Chiffre d'affaires mensuel</div>
                <div class="card-actions">
                    <a href="#" class="btn btn-primary">
                        <i class="fas fa-analytics"></i> Rapports
                    </a>
                    <a href="#" class="btn btn-success">
                        <i class="fas fa-download"></i> Exporter
                    </a>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Actions rapides</h3>
            <div class="action-buttons">
                <a href="commandes.php?action=new" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouvelle commande
                </a>
                <a href="coursiers.php?action=add" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Ajouter coursier
                </a>
                <a href="../reclamation.php" class="btn btn-warning">
                    <i class="fas fa-robot"></i> Chat IA Support
                </a>
                <a href="reclamations.php" class="btn btn-primary">
                    <i class="fas fa-headset"></i> Voir réclamations
                </a>
                <a href="#" class="btn btn-success">
                    <i class="fas fa-bell"></i> Notifications
                </a>
                <a href="#" class="btn btn-warning">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
            </div>
        </div>
    </main>

    <script>
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card, .status-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Mise à jour automatique des statistiques (optionnel)
        function updateStats() {
            // Appel AJAX pour mettre à jour les stats en temps réel
            fetch('api/get_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Mettre à jour les chiffres
                    console.log('Stats updated:', data);
                })
                .catch(error => console.log('Erreur:', error));
        }

        // Mise à jour toutes les 30 secondes
        setInterval(updateStats, 30000);
    </script>
</body>
</html>