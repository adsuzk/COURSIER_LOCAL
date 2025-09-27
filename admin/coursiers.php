<?php
session_start();
require_once '../auth.php';
require_once '../config.php';
checkAuth();

// Connexion à la base de données
try {
    $pdo = new PDO('mysql:host=localhost;dbname=coursier_prod', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Traitement des actions
$message = '';
$messageType = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_coursier':
                try {
                    $nom = trim($_POST['nom']);
                    $telephone = trim($_POST['telephone']);
                    $email = trim($_POST['email']);
                    $statut = $_POST['statut'] ?? 'actif';
                    $token = bin2hex(random_bytes(32));
                    
                    $stmt = $pdo->prepare("INSERT INTO coursiers (nom, telephone, email, statut, token, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$nom, $telephone, $email, $statut, $token]);
                    
                    $message = "Coursier ajouté avec succès !";
                    $messageType = 'success';
                } catch(PDOException $e) {
                    $message = "Erreur lors de l'ajout : " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'update_coursier':
                try {
                    $id = $_POST['coursier_id'];
                    $nom = trim($_POST['nom']);
                    $telephone = trim($_POST['telephone']);
                    $email = trim($_POST['email']);
                    $statut = $_POST['statut'];
                    
                    $stmt = $pdo->prepare("UPDATE coursiers SET nom = ?, telephone = ?, email = ?, statut = ? WHERE id = ?");
                    $stmt->execute([$nom, $telephone, $email, $statut, $id]);
                    
                    $message = "Coursier mis à jour avec succès !";
                    $messageType = 'success';
                } catch(PDOException $e) {
                    $message = "Erreur lors de la mise à jour : " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'delete_coursier':
                try {
                    $id = $_POST['coursier_id'];
                    
                    // Vérifier s'il a des commandes en cours
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE coursier_id = ? AND statut NOT IN ('livree', 'annulee')");
                    $stmt->execute([$id]);
                    $commandes_actives = $stmt->fetchColumn();
                    
                    if ($commandes_actives > 0) {
                        $message = "Impossible de supprimer : le coursier a $commandes_actives commande(s) en cours.";
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM coursiers WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        $message = "Coursier supprimé avec succès !";
                        $messageType = 'success';
                    }
                } catch(PDOException $e) {
                    $message = "Erreur lors de la suppression : " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Récupération des coursiers avec statistiques
$stmt = $pdo->query("
    SELECT c.*,
           COUNT(co.id) as total_commandes,
           SUM(CASE WHEN co.statut = 'livree' THEN 1 ELSE 0 END) as commandes_livrees,
           SUM(CASE WHEN co.statut IN ('assignee', 'en_course', 'recuperee') THEN 1 ELSE 0 END) as commandes_en_cours
    FROM coursiers c
    LEFT JOIN commandes co ON c.id = co.coursier_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$coursiers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Coursiers - COURSIER LOCAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .nav-links {
            background: #34495e;
            padding: 15px 30px;
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .nav-links a:hover {
            background: #2c3e50;
        }

        .nav-links a.active {
            background: #3498db;
        }

        .content {
            padding: 30px;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .coursiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .coursier-card {
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .coursier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .coursier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .coursier-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-actif {
            background: #d4edda;
            color: #155724;
        }

        .status-inactif {
            background: #f8d7da;
            color: #721c24;
        }

        .status-suspendu {
            background: #fff3cd;
            color: #856404;
        }

        .coursier-info {
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #6c757d;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 15px 0;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .coursier-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-bar {
            margin-bottom: 20px;
        }

        .search-bar input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .coursiers-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
                gap: 15px;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-motorcycle"></i> Gestion des Coursiers</h1>
            <p>Administrez votre équipe de coursiers</p>
        </div>

        <div class="nav-links">
            <a href="../admin.php"><i class="fas fa-home"></i> Accueil</a>
            <a href="commandes.php"><i class="fas fa-box"></i> Commandes</a>
            <a href="coursiers.php" class="active"><i class="fas fa-motorcycle"></i> Coursiers</a>
            <a href="reclamations.php"><i class="fas fa-exclamation-triangle"></i> Réclamations</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?= $messageType ?>">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="actions-bar">
                <div>
                    <h2>Coursiers (<?= count($coursiers) ?>)</h2>
                </div>
                <button class="btn btn-primary" onclick="openModal('addCoursierModal')">
                    <i class="fas fa-plus"></i> Nouveau Coursier
                </button>
            </div>

            <div class="search-bar">
                <input type="text" id="searchCoursiers" placeholder="🔍 Rechercher un coursier..." onkeyup="filterCoursiers()">
            </div>

            <div class="coursiers-grid" id="coursiersGrid">
                <?php foreach ($coursiers as $coursier): ?>
                    <div class="coursier-card" data-coursier-name="<?= strtolower($coursier['nom']) ?>" data-coursier-phone="<?= $coursier['telephone'] ?>">
                        <div class="coursier-header">
                            <div class="coursier-name">
                                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($coursier['nom']) ?>
                            </div>
                            <span class="status-badge status-<?= $coursier['statut'] ?>">
                                <?= ucfirst($coursier['statut']) ?>
                            </span>
                        </div>

                        <div class="coursier-info">
                            <div class="info-row">
                                <i class="fas fa-phone"></i>
                                <span><?= htmlspecialchars($coursier['telephone']) ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($coursier['email'] ?: 'Non renseigné') ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-calendar"></i>
                                <span>Inscrit le <?= date('d/m/Y', strtotime($coursier['created_at'])) ?></span>
                            </div>
                        </div>

                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="stat-number"><?= $coursier['total_commandes'] ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $coursier['commandes_livrees'] ?></div>
                                <div class="stat-label">Livrées</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $coursier['commandes_en_cours'] ?></div>
                                <div class="stat-label">En cours</div>
                            </div>
                        </div>

                        <div class="coursier-actions">
                            <button class="btn btn-warning btn-sm" onclick="editCoursier(<?= htmlspecialchars(json_encode($coursier)) ?>)">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteCoursier(<?= $coursier['id'] ?>, '<?= htmlspecialchars($coursier['nom']) ?>')">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal Ajouter Coursier -->
    <div id="addCoursierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Nouveau Coursier</h3>
                <span class="close" onclick="closeModal('addCoursierModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_coursier">
                
                <div class="form-group">
                    <label for="nom">Nom complet *</label>
                    <input type="text" id="nom" name="nom" required>
                </div>
                
                <div class="form-group">
                    <label for="telephone">Téléphone *</label>
                    <input type="tel" id="telephone" name="telephone" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="statut">Statut</label>
                    <select id="statut" name="statut">
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                        <option value="suspendu">Suspendu</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Modifier Coursier -->
    <div id="editCoursierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Modifier Coursier</h3>
                <span class="close" onclick="closeModal('editCoursierModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_coursier">
                <input type="hidden" id="edit_coursier_id" name="coursier_id">
                
                <div class="form-group">
                    <label for="edit_nom">Nom complet *</label>
                    <input type="text" id="edit_nom" name="nom" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_telephone">Téléphone *</label>
                    <input type="tel" id="edit_telephone" name="telephone" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="edit_statut">Statut</label>
                    <select id="edit_statut" name="statut">
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                        <option value="suspendu">Suspendu</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Supprimer -->
    <div id="deleteCoursierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirmer la suppression</h3>
                <span class="close" onclick="closeModal('deleteCoursierModal')">&times;</span>
            </div>
            <p>Êtes-vous sûr de vouloir supprimer le coursier <strong id="delete_coursier_name"></strong> ?</p>
            <p><em>Cette action est irréversible.</em></p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_coursier">
                <input type="hidden" id="delete_coursier_id" name="coursier_id">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('deleteCoursierModal')" style="background: #6c757d; color: white;">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editCoursier(coursier) {
            document.getElementById('edit_coursier_id').value = coursier.id;
            document.getElementById('edit_nom').value = coursier.nom;
            document.getElementById('edit_telephone').value = coursier.telephone;
            document.getElementById('edit_email').value = coursier.email || '';
            document.getElementById('edit_statut').value = coursier.statut;
            openModal('editCoursierModal');
        }

        function deleteCoursier(id, nom) {
            document.getElementById('delete_coursier_id').value = id;
            document.getElementById('delete_coursier_name').textContent = nom;
            openModal('deleteCoursierModal');
        }

        function filterCoursiers() {
            const searchTerm = document.getElementById('searchCoursiers').value.toLowerCase();
            const cards = document.querySelectorAll('.coursier-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-coursier-name');
                const phone = card.getAttribute('data-coursier-phone');
                
                if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>