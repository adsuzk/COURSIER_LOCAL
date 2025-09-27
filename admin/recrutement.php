<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_poste') {
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $date_expiration = $_POST['date_expiration'] ?? null;
        if ($titre && $date_expiration) {
            $stmt = $pdo->prepare("INSERT INTO postes (titre, description_courte, date_expiration, statut, date_creation) VALUES (?, ?, ?, 'actif', NOW())");
            $stmt->execute([$titre, $description, $date_expiration]);
            $success = "Nouveau poste ajouté.";
        } else {
            $error = "Le titre et la date d'expiration sont obligatoires.";
        }
    } elseif ($action === 'update_poste_status') {
        $id = intval($_POST['id'] ?? 0);
        $raw = $_POST['statut'] ?? '';
        $isAjax = (($_POST['ajax'] ?? '') === 'true');
        $statut = ($raw === 'actif') ? 'actif' : (($raw === 'inactif') ? 'inactif' : $raw);
        $resp = ['success' => false];
        if ($id) {
            if ($statut === 'delete') {
                try {
                    $stmt = $pdo->prepare("DELETE FROM postes WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Poste supprimé.";
                    $resp = ['success' => true, 'status' => 'deleted'];
                } catch (PDOException $e) {
                    // Si contrainte (candidatures liées), fallback: inactif
                    $stmt = $pdo->prepare("UPDATE postes SET statut = 'inactif' WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Poste désactivé (suppression non possible).";
                    $resp = ['success' => true, 'status' => 'updated', 'statut' => 'inactif'];
                }
            } else {
                $stmt = $pdo->prepare("UPDATE postes SET statut = ? WHERE id = ?");
                $stmt->execute([$statut, $id]);
                $success = "Statut du poste mis à jour.";
                $resp = ['success' => true, 'status' => 'updated', 'statut' => $statut];
            }
        }
        if ($isAjax) {
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resp);
            exit;
        }
    }
    elseif ($action === 'update_candidature_status') {
        $cid = intval($_POST['candidature_id'] ?? 0);
        $new = $_POST['statut'] ?? '';
        $valid = in_array($new, ['en_attente', 'valide', 'refuse'], true) ? $new : 'en_attente';
        if ($cid) {
            $stmt = $pdo->prepare("UPDATE candidatures SET statut = ? WHERE id = ?");
            $stmt->execute([$valid, $cid]);
            $success = "Statut de la candidature mis à jour.";
        }
    }
}

// Fetch postes with fallback if table missing
try {
    $stmt = $pdo->query("SELECT * FROM postes ORDER BY date_expiration DESC");
    $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table `postes` absente ou autre erreur
    $postes = [];
}
// Fetch candidatures with fallback
try {
    $stmt2 = $pdo->query("SELECT c.*, p.titre AS poste_titre FROM candidatures c LEFT JOIN postes p ON c.poste_id = p.id ORDER BY c.id DESC");
    $candidatures = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table `candidatures` ou `postes` absente
    $candidatures = [];
}
?>

<style>
/* === DESIGN SYSTEM SUZOSKY - RECRUTEMENT === */
:root {
    /* Variables identiques à coursier.php et chat.php */
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --warning-color: #ffc107;
    --danger-color: #E94560;
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
}

/* === HERO SECTION RECRUTEMENT === */
.recrutement-hero {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.recrutement-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-gold);
}

.hero-content h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    font-family: 'Montserrat', sans-serif;
}

.hero-content p {
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
    margin-bottom: 20px;
    font-weight: 500;
}

.hero-stats {
    display: flex;
    gap: 30px;
}

.hero-stat .stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
}

.hero-stat .stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero-decoration {
    font-size: 4rem;
    color: var(--primary-gold);
    opacity: 0.3;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* === SECTIONS RECRUTEMENT === */
.recrutement-section {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--glass-border);
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* === FORMULAIRES === */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: var(--primary-gold);
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-family: 'Montserrat', sans-serif;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    background: var(--glass-bg);
    border: 2px solid var(--glass-border);
    border-radius: 10px;
    color: white;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-gold);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
    background: rgba(255,255,255,0.08);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: rgba(255,255,255,0.5);
    font-style: italic;
}

/* === BOUTONS === */
.btn-suzosky {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.btn-suzosky:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
    text-decoration: none;
    color: var(--primary-dark);
}

.btn-secondary {
    background: rgba(255,255,255,0.08);
    color: #FFFFFF;
    border: 1px solid var(--glass-border);
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.15);
    color: #FFFFFF;
}

/* === CARTES POSTES === */
.postes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.poste-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 20px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    position: relative;
    overflow: hidden;
}

.poste-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-gold);
}

.poste-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(212, 168, 83, 0.2);
    border-color: var(--primary-gold);
}

.poste-header {
    margin-bottom: 15px;
}

.poste-title {
    color: #FFFFFF;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 8px;
    font-family: 'Montserrat', sans-serif;
}

.poste-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.poste-expiry {
    color: rgba(255,255,255,0.7);
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-actif {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.status-inactif {
    background: rgba(233, 69, 96, 0.2);
    color: var(--accent-red);
    border: 1px solid var(--accent-red);
}

.poste-description {
    color: rgba(255,255,255,0.8);
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 15px;
}

.poste-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.75rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.btn-activate {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.btn-activate:hover {
    background: var(--success-color);
    color: white;
    transform: translateY(-2px);
}

.btn-deactivate {
    background: rgba(233, 69, 96, 0.2);
    color: var(--accent-red);
    border: 1px solid var(--accent-red);
}

.btn-deactivate:hover {
    background: var(--accent-red);
    color: white;
    transform: translateY(-2px);
}

/* === TABLEAU CANDIDATURES === */
.table-container {
    overflow-x: auto;
    border-radius: 12px;
    background: rgba(255,255,255,0.02);
}

.suzosky-table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Montserrat', sans-serif;
}

.suzosky-table th {
    background: rgba(212, 168, 83, 0.1);
    color: var(--primary-gold);
    padding: 15px 12px;
    text-align: left;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--glass-border);
}

.suzosky-table td {
    padding: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
    font-weight: 500;
}

.suzosky-table tr:hover {
    background: rgba(255,255,255,0.03);
}

/* === STATUS CANDIDATURES === */
.status-en-attente {
    background: rgba(255, 193, 7, 0.2);
    color: var(--warning-color);
    border: 1px solid var(--warning-color);
}

.status-valide {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.status-refuse {
    background: rgba(233, 69, 96, 0.2);
    color: var(--accent-red);
    border: 1px solid var(--accent-red);
}

/* === MESSAGES === */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.9rem;
    border: 2px solid;
    backdrop-filter: blur(10px);
}

.alert-success {
    background: rgba(39, 174, 96, 0.15);
    border-color: var(--success-color);
    color: var(--success-color);
}

.alert-error {
    background: rgba(233, 69, 96, 0.15);
    border-color: var(--accent-red);
    color: var(--accent-red);
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .recrutement-hero {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 15px;
    }
    
    .postes-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .table-container {
        font-size: 0.8rem;
    }
}
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --warning-color: #ffc107;
    --danger-color: #E94560;
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
}

/* === HERO SECTION RECRUTEMENT === */
.recrutement-hero {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.recrutement-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-gold);
}

.recrutement-hero h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    font-family: 'Montserrat', sans-serif;
}

.recrutement-hero p {
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
    font-weight: 500;
    margin: 0;
}

/* === ALERTES SUZOSKY === */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    border: 1px solid;
    backdrop-filter: blur(10px);
}

.alert-success {
    background: rgba(39, 174, 96, 0.15);
    border-color: var(--success-color);
    color: var(--success-color);
}

.alert-error {
    background: rgba(233, 69, 96, 0.15);
    border-color: var(--danger-color);
    color: var(--danger-color);
}

/* === ONGLETS SUZOSKY === */
.sub-tabs {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.sub-tabs-header {
    display: flex;
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid var(--glass-border);
}

.sub-tab-button {
    flex: 1;
    padding: 15px 20px;
    background: none;
    border: none;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.sub-tab-button.active {
    color: var(--primary-gold);
    background: rgba(212, 168, 83, 0.1);
}

.sub-tab-button.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-gold);
}

.sub-tab-button:hover:not(.active) {
    color: rgba(255,255,255,0.9);
    background: rgba(255,255,255,0.03);
}

.sub-tab-content {
    padding: 30px;
}

.sub-tab-section {
    display: none;
}

.sub-tab-section.active {
    display: block;
}

/* === TABLEAU SUZOSKY === */
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--glass-border);
}

.data-table th {
    background: rgba(255,255,255,0.08);
    color: var(--primary-gold);
    padding: 15px;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--glass-border);
}

.data-table td {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.9);
    font-weight: 500;
}

.data-table tr:hover {
    background: rgba(255,255,255,0.05);
}

/* === BADGES STATUT === */
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-actif {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
}

.badge-inactif {
    background: rgba(255, 193, 7, 0.2);
    color: var(--warning-color);
}

.badge-en_attente {
    background: rgba(59, 130, 246, 0.2);
    color: #3B82F6;
}

.badge-valide {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
}

.badge-refuse {
    background: rgba(233, 69, 96, 0.2);
    color: var(--danger-color);
}

/* === FORMULAIRES SUZOSKY === */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: var(--primary-gold);
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 15px;
    background: var(--glass-bg);
    border: 2px solid var(--glass-border);
    border-radius: 10px;
    color: #FFFFFF;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-gold);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
    background: rgba(255,255,255,0.08);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

/* === BOUTONS SUZOSKY === */
.btn {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border: none;
    border-radius: 10px;
    padding: 12px 20px;
    font-family: 'Montserrat', sans-serif;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .recrutement-hero h1 {
        font-size: 1.5rem;
    }
    
    .sub-tabs-header {
        flex-direction: column;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px;
    }
}
</style>

<div class="recrutement-hero">
    <h1><i class="fas fa-briefcase"></i> Emploi & Recrutement Suzosky</h1>
    <p>Gérez vos offres d'emploi et candidatures avec l'interface premium Suzosky</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php elseif (!empty($success)): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="sub-tabs">
    <div class="sub-tabs-header">
        <button class="sub-tab-button active" onclick="showTab('list-postes')">
            <i class="fas fa-list"></i> Liste des postes
        </button>
        <button class="sub-tab-button" onclick="showTab('create-poste')">
            <i class="fas fa-plus"></i> Nouveau poste
        </button>
        <button class="sub-tab-button" onclick="showTab('list-candidatures')">
            <i class="fas fa-file-alt"></i> Candidatures
        </button>
    </div>
    <div class="sub-tab-content">
        <div id="list-postes" class="sub-tab-section active">
            <table class="data-table">
                <thead><tr>
                    <th>ID</th><th>Titre</th><th>Expiration</th><th>Statut</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($postes as $poste): ?>
                    <tr data-poste-id="<?= (int)$poste['id'] ?>">
                        <td><?= $poste['id'] ?></td>
                        <td><?= htmlspecialchars($poste['titre']) ?></td>
                        <td><?= htmlspecialchars($poste['date_expiration']) ?></td>
                        <td><span class="badge badge-<?= $poste['statut'] ?>"><?= strtoupper($poste['statut']) ?></span></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="update_poste_status">
                                <input type="hidden" name="id" value="<?= (int)$poste['id'] ?>">
                                <select name="statut" onchange="handlePosteStatusChange(this)" style="background: var(--glass-bg); border: 1px solid var(--glass-border); color: #FFF; border-radius: 6px; padding: 5px;" data-current="<?= htmlspecialchars($poste['statut']) ?>">
                                    <option value="actif" <?= $poste['statut']==='actif'?'selected':'' ?>>Actif</option>
                                    <option value="inactif" <?= $poste['statut']==='inactif'?'selected':'' ?>>Inactif</option>
                                    <option value="delete">Supprimer</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="create-poste" class="sub-tab-section">
            <form method="POST">
                <input type="hidden" name="action" value="create_poste">
                <div class="form-group">
                    <label>Titre du poste</label>
                    <input type="text" name="titre" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label>Date d'expiration</label>
                    <input type="date" name="date_expiration" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Créer</button>
            </form>
        </div>
        <div id="list-candidatures" class="sub-tab-section" style="display:none">
            <table class="data-table">
                <thead><tr>
                    <th>ID</th><th>Poste</th><th>Nom</th><th>Prénoms</th><th>Email</th><th>Téléphone</th><th>CV</th><th>Date Candidature</th><th>Statut</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($candidatures as $cand): ?>
                    <tr>
                        <td><?= $cand['id'] ?></td>
                        <td><?= htmlspecialchars($cand['poste_titre'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($cand['nom']) ?></td>
                        <td><?= htmlspecialchars($cand['prenoms']) ?></td>
                        <td><?= htmlspecialchars($cand['email']) ?></td>
                        <td><?= htmlspecialchars($cand['telephone']) ?></td>
                        <td>
                            <?php if (!empty($cand['cv_filename'])): ?>
                                <a href="uploads/candidatures/<?= rawurlencode($cand['cv_filename']) ?>" target="_blank" download title="Télécharger le CV" style="color: var(--primary-gold); text-decoration: none; font-weight: 600;">
                                    <i class="fas fa-download"></i> CV
                                </a>
                            <?php else: ?>
                                <span class="badge badge-inactif">Aucun</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($cand['date_candidature'])) ?></td>
                        <td><span class="badge badge-<?= $cand['statut'] ?>"><?= strtoupper($cand['statut']) ?></span></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="update_candidature_status">
                                <input type="hidden" name="candidature_id" value="<?= $cand['id'] ?>">
                                <select name="statut" onchange="this.form.submit()">
                                    <option value="en_attente" <?= $cand['statut']==='en_attente'?'selected':'' ?>>En attente</option>
                                    <option value="valide" <?= $cand['statut']==='valide'?'selected':'' ?>>Validée</option>
                                    <option value="refuse" <?= $cand['statut']==='refuse'?'selected':'' ?>>Refusée</option>
                                </select>
                            </form>
                            <button type="button" class="btn" style="margin-left:8px; background: var(--glass-bg); border: 1px solid var(--glass-border); color: #FFF; border-radius: 6px; padding: 5px 8px; cursor: pointer;" 
                                    data-motivation="<?= htmlspecialchars($cand['lettre_motivation'] ?? '', ENT_QUOTES) ?>" 
                                    onclick="openMotivationModal(this.dataset.motivation)">
                                <i class="fas fa-eye"></i> Voir lettre
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showTab(id) {
    document.querySelectorAll('.sub-tab-section').forEach(el => el.style.display = 'none');
    document.getElementById(id).style.display = 'block';
    document.querySelectorAll('.sub-tab-button').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
}

function handlePosteStatusChange(selectEl) {
    const form = selectEl.closest('form');
    const row = selectEl.closest('tr');
    const value = selectEl.value;
    if (value === 'delete') {
        const ok = confirm("Voulez-vous vraiment supprimer ce poste ? Cette action est irréversible.");
        if (!ok) {
            const current = selectEl.getAttribute('data-current') || 'inactif';
            selectEl.value = current;
            return;
        }
    }
    // Mémoriser la valeur actuelle
    selectEl.setAttribute('data-current', value);
    // Ajax submit
    const fd = new FormData(form);
    fd.set('ajax', 'true');
    fetch('admin/recrutement.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data && data.success) {
            if (data.status === 'deleted' && row && row.parentNode) {
                row.parentNode.removeChild(row);
            }
        } else {
            alert('Mise à jour impossible');
        }
      })
      .catch(() => alert('Erreur réseau'));
}

// Modale simple pour afficher la lettre de motivation
function openMotivationModal(text) {
    // créer le conteneur si absent
    let modal = document.getElementById('motivation-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'motivation-modal';
        modal.style.position = 'fixed';
        modal.style.inset = '0';
        modal.style.background = 'rgba(0,0,0,0.6)';
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.zIndex = '9999';
        modal.innerHTML = `
            <div style="max-width:800px; width:90%; background: var(--glass-bg); border:1px solid var(--glass-border); border-radius:12px; color:#FFF;">
                <div style="padding:12px 16px; border-bottom:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center;">
                    <strong style="color: var(--primary-gold);">Lettre de motivation</strong>
                    <button id="motivation-close" style="background:none; border:0; color:#FFF; font-size:18px; cursor:pointer;">×</button>
                </div>
                <div id="motivation-content" style="padding:16px; max-height:60vh; overflow:auto; white-space:pre-wrap; line-height:1.5;"></div>
                <div style="padding:12px 16px; border-top:1px solid var(--glass-border); text-align:right;">
                    <button id="motivation-ok" style="background: var(--gradient-gold); color: var(--primary-dark); border:0; padding:8px 14px; border-radius:8px; font-weight:700; cursor:pointer;">Fermer</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
        const close = () => modal.remove();
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        modal.querySelector('#motivation-close').addEventListener('click', close);
        modal.querySelector('#motivation-ok').addEventListener('click', close);
    }
    const content = modal.querySelector('#motivation-content');
    content.textContent = text || '—';
    modal.style.display = 'flex';
}
</script>
