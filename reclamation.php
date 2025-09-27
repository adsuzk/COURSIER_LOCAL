<?php
/**
 * ============================================================================
 * 📋 SYSTÈME RÉCLAMATIONS COMPLET - SUZOSKY
 * ============================================================================
 * 
 * Interface inline pour admin.php avec gestion complète des réclamations
 * Intégration chat IA, historique, fichiers, suivi client
 * 
 * @version 2.0.0 - Système complet
 * @author Équipe Suzosky  
 * @date 25 septembre 2025
 * ============================================================================
 */

// Traitement des actions AJAX pour réclamations
if (isset($_POST['action']) && strpos($_POST['action'], 'reclamation_') === 0) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'reclamation_get_list':
                echo json_encode(getReclamationsList($_POST));
                break;
                
            case 'reclamation_get_details':
                echo json_encode(getReclamationDetails($_POST['id']));
                break;
                
            case 'reclamation_update_status':
                echo json_encode(updateReclamationStatus($_POST));
                break;
                
            case 'reclamation_add_response':
                echo json_encode(addAdminResponse($_POST));
                break;
                
            case 'reclamation_create_manual':
                echo json_encode(createManualReclamation($_POST));
                break;
                
            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/**
 * Récupération de la liste des réclamations avec filtres
 */
function getReclamationsList($filters = []) {
    global $pdo;
    
    $whereClause = 'WHERE 1=1';
    $params = [];
    
    // Filtres
    if (!empty($filters['statut'])) {
        $whereClause .= ' AND r.statut = ?';
        $params[] = $filters['statut'];
    }
    
    if (!empty($filters['type'])) {
        $whereClause .= ' AND r.type_reclamation = ?';
        $params[] = $filters['type'];
    }
    
    if (!empty($filters['priorite'])) {
        $whereClause .= ' AND r.priorite = ?';
        $params[] = $filters['priorite'];
    }
    
    if (!empty($filters['search'])) {
        $whereClause .= ' AND (r.numero_suivi LIKE ? OR r.numero_transaction LIKE ? OR r.client_nom LIKE ?)';
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    $stmt = $pdo->prepare("
        SELECT r.*,
               c.prix_total as montant_commande_db,
               c.adresse_livraison as adresse_db,
               c.date_commande,
               c.statut as statut_commande,
               a.nom as coursier_nom_db,
               a.telephone as coursier_tel_db
        FROM reclamations r
        LEFT JOIN commandes c ON r.numero_transaction = c.numero_commande
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        $whereClause
        ORDER BY 
            CASE r.priorite 
                WHEN 'urgente' THEN 1
                WHEN 'haute' THEN 2
                WHEN 'normale' THEN 3
                WHEN 'basse' THEN 4
            END,
            r.date_creation DESC
        LIMIT 100
    ");
    
    $stmt->execute($params);
    $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enrichir les données
    foreach ($reclamations as &$rec) {
        // Utiliser les données de la commande si pas remplies
        if (empty($rec['montant_commande']) && !empty($rec['montant_commande_db'])) {
            $rec['montant_commande'] = $rec['montant_commande_db'];
        }
        if (empty($rec['adresse_livraison']) && !empty($rec['adresse_db'])) {
            $rec['adresse_livraison'] = $rec['adresse_db'];
        }
        if (empty($rec['coursier_nom']) && !empty($rec['coursier_nom_db'])) {
            $rec['coursier_nom'] = $rec['coursier_nom_db'];
            $rec['coursier_telephone'] = $rec['coursier_tel_db'];
        }
        
        // Parser les fichiers JSON
        $rec['fichiers_joints_array'] = $rec['fichiers_joints'] ? json_decode($rec['fichiers_joints'], true) : [];
    }
    
    return [
        'success' => true,
        'reclamations' => $reclamations,
        'total' => count($reclamations)
    ];
}

/**
 * Récupération des détails complets d'une réclamation
 */
function getReclamationDetails($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT r.*,
               c.prix_total, c.adresse_livraison, c.date_commande, c.statut as statut_commande,
               c.nom_client, c.telephone as client_tel,
               a.nom as coursier_nom, a.telephone as coursier_tel, a.matricule
        FROM reclamations r
        LEFT JOIN commandes c ON r.numero_transaction = c.numero_commande
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE r.id = ?
    ");
    
    $stmt->execute([$id]);
    $reclamation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reclamation) {
        throw new Exception('Réclamation introuvable');
    }
    
    // Parser les données JSON
    $reclamation['fichiers_joints_array'] = $reclamation['fichiers_joints'] ? 
        json_decode($reclamation['fichiers_joints'], true) : [];
    $reclamation['metadata_array'] = $reclamation['metadata'] ? 
        json_decode($reclamation['metadata'], true) : [];
    
    // Historique du chat si disponible
    $chatHistorique = [];
    if ($reclamation['chat_historique']) {
        $chatHistorique = json_decode($reclamation['chat_historique'], true) ?: [];
    }
    
    return [
        'success' => true,
        'reclamation' => $reclamation,
        'chat_historique' => $chatHistorique
    ];
}

/**
 * Mise à jour du statut d'une réclamation
 */
function updateReclamationStatus($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE reclamations 
        SET statut = ?, 
            admin_id = ?, 
            date_modification = NOW(),
            date_resolution = CASE WHEN ? IN ('resolue', 'fermee') THEN NOW() ELSE date_resolution END
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['statut'],
        $_SESSION['admin_id'] ?? 1,
        $data['statut'],
        $data['id']
    ]);
    
    return ['success' => true, 'message' => 'Statut mis à jour avec succès'];
}

/**
 * Ajout d'une réponse admin
 */
function addAdminResponse($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE reclamations 
        SET reponse_admin = ?, 
            admin_id = ?,
            statut = CASE WHEN statut = 'nouvelle' THEN 'en_cours' ELSE statut END,
            date_modification = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['reponse'],
        $_SESSION['admin_id'] ?? 1,
        $data['id']
    ]);
    
    return ['success' => true, 'message' => 'Réponse ajoutée avec succès'];
}

/**
 * Création manuelle d'une réclamation
 */
function createManualReclamation($data) {
    global $pdo;
    
    // Générer numéro de suivi
    $numeroSuivi = 'REC' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
    
    // Vérifier si la transaction existe
    $commande = null;
    if (!empty($data['numero_transaction'])) {
        $stmt = $pdo->prepare("
            SELECT c.*, a.nom as coursier_nom, a.telephone as coursier_tel
            FROM commandes c
            LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
            WHERE c.numero_commande = ?
        ");
        $stmt->execute([$data['numero_transaction']]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO reclamations (
            numero_suivi, numero_transaction, type_reclamation, priorite,
            sujet, description, client_nom, client_telephone,
            commande_id, coursier_id, coursier_nom, coursier_telephone,
            montant_commande, adresse_livraison, statut, admin_id, metadata
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $metadata = json_encode([
        'created_manually' => true,
        'admin_creator' => $_SESSION['admin_id'] ?? 1,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    $stmt->execute([
        $numeroSuivi,
        $data['numero_transaction'] ?? null,
        $data['type_reclamation'],
        $data['priorite'] ?? 'normale',
        $data['sujet'],
        $data['description'],
        $data['client_nom'] ?? ($commande['nom_client'] ?? null),
        $data['client_telephone'] ?? ($commande['telephone'] ?? null),
        $commande['id'] ?? null,
        $commande['coursier_id'] ?? null,
        $commande['coursier_nom'] ?? null,
        $commande['coursier_tel'] ?? null,
        $commande['prix_total'] ?? null,
        $commande['adresse_livraison'] ?? null,
        'nouvelle',
        $_SESSION['admin_id'] ?? 1,
        $metadata
    ]);
    
    return [
        'success' => true, 
        'message' => 'Réclamation créée avec succès',
        'numero_suivi' => $numeroSuivi,
        'id' => $pdo->lastInsertId()
    ];
}

// Statistiques pour le dashboard
$statsReclamations = [];
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'nouvelle' THEN 1 ELSE 0 END) as nouvelles,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'resolue' THEN 1 ELSE 0 END) as resolues,
            SUM(CASE WHEN priorite = 'urgente' THEN 1 ELSE 0 END) as urgentes,
            SUM(CASE WHEN DATE(date_creation) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui,
            SUM(CASE WHEN date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as cette_semaine
        FROM reclamations
    ");
    $statsReclamations = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $statsReclamations = [
        'total' => 0, 'nouvelles' => 0, 'en_cours' => 0, 'resolues' => 0, 
        'urgentes' => 0, 'aujourdhui' => 0, 'cette_semaine' => 0
    ];
}
?>

<style>
/* === VARIABLES SUZOSKY RÉCLAMATIONS === */
:root {
    --primary-gold: #D4A853;
    --primary-dark: #1a1a2e;
    --secondary-blue: #16213e;
    --glass-bg: rgba(255, 255, 255, 0.08);
    --glass-border: rgba(255, 255, 255, 0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #f4e4bc 100%);
    --gradient-dark: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    --shadow-gold: 0 8px 32px rgba(212, 168, 83, 0.3);
}

/* === CONTENEUR PRINCIPAL === */
.reclamations-system {
    background: var(--gradient-dark);
    min-height: 100vh;
    padding: 20px;
    color: white;
}

/* === HEADER RÉCLAMATIONS === */
.reclamations-header {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-gold);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-content h1 {
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-content p {
    font-size: 1.1rem;
    margin: 0;
    opacity: 0.8;
}

.header-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat-card {
    background: rgba(26, 26, 46, 0.1);
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-dark);
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.7;
    margin-top: 5px;
}

/* === ACTIONS RAPIDES === */
.quick-actions {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.action-btn {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    color: var(--primary-gold);
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(15px);
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-btn:hover {
    background: var(--primary-gold);
    color: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
}

.action-btn.primary {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border: none;
}

/* === FILTRES === */
.filters-panel {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    backdrop-filter: blur(15px);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group label {
    display: block;
    color: var(--primary-gold);
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    background: var(--glass-bg);
    color: white;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: var(--primary-gold);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

/* === LISTE RÉCLAMATIONS === */
.reclamations-list {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    backdrop-filter: blur(15px);
    overflow: hidden;
}

.list-header {
    background: var(--secondary-blue);
    color: var(--primary-gold);
    padding: 20px 25px;
    font-weight: 700;
    border-bottom: 2px solid var(--primary-gold);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reclamation-item {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
}

.reclamation-item:hover {
    background: rgba(212, 168, 83, 0.05);
}

.item-content {
    padding: 20px 25px;
    display: grid;
    grid-template-columns: auto 1fr auto auto;
    gap: 20px;
    align-items: center;
}

.item-priority {
    width: 4px;
    height: 60px;
    border-radius: 2px;
}

.priority-urgente { background: #E94560; animation: pulse 2s infinite; }
.priority-haute { background: #FFC107; }
.priority-normale { background: #3B82F6; }
.priority-basse { background: rgba(255, 255, 255, 0.3); }

.item-info {
    flex: 1;
}

.item-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--primary-gold);
    margin-bottom: 8px;
}

.item-details {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.item-detail {
    display: flex;
    align-items: center;
    gap: 5px;
}

.item-badges {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: end;
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-nouvelle { background: #3B82F6; color: white; }
.badge-en_cours { background: #FFC107; color: var(--primary-dark); }
.badge-resolue { background: #27AE60; color: white; }
.badge-fermee { background: #6B7280; color: white; }

.type-badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    background: rgba(212, 168, 83, 0.2);
    color: var(--primary-gold);
    border: 1px solid var(--primary-gold);
}

/* === MODAL DÉTAILS === */
.reclamation-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(5px);
}

.modal-content {
    background: var(--gradient-dark);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    max-width: 1200px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
}

.modal-header {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    padding: 25px;
    border-radius: 20px 20px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1;
}

.modal-header h2 {
    margin: 0;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 15px;
}

.modal-close {
    background: none;
    border: none;
    color: var(--primary-dark);
    font-size: 1.8rem;
    cursor: pointer;
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(26, 26, 46, 0.2);
}

.modal-body {
    padding: 30px;
    color: white;
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .reclamations-header {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .header-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .item-content {
        grid-template-columns: auto 1fr;
        gap: 15px;
    }
    
    .item-badges {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: start;
        margin-top: 10px;
    }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
</style>

<div class="reclamations-system">
    <!-- Header avec statistiques -->
    <div class="reclamations-header">
        <div class="header-content">
            <h1><i class="fas fa-exclamation-triangle"></i> Centre de Réclamations Suzosky</h1>
            <p>Gestion complète des réclamations clients avec suivi intégré et historique chat IA</p>
        </div>
        <div class="header-stats">
            <div class="stat-card">
                <span class="stat-value"><?= $statsReclamations['nouvelles'] ?? 0 ?></span>
                <span class="stat-label">Nouvelles</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $statsReclamations['en_cours'] ?? 0 ?></span>
                <span class="stat-label">En cours</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= $statsReclamations['urgentes'] ?? 0 ?></span>
                <span class="stat-label">Urgentes</span>
            </div>
        </div>
    </div>
    
    <!-- Actions rapides -->
    <div class="quick-actions">
        <button class="action-btn primary" onclick="ReclamationsManager.showCreateModal()">
            <i class="fas fa-plus"></i> Créer Réclamation Manuelle
        </button>
        <button class="action-btn" onclick="ReclamationsManager.exportReclamations()">
            <i class="fas fa-download"></i> Exporter Données
        </button>
        <button class="action-btn" onclick="ReclamationsManager.refreshList()">
            <i class="fas fa-sync"></i> Actualiser
        </button>
        <button class="action-btn" onclick="ReclamationsManager.showStats()">
            <i class="fas fa-chart-bar"></i> Statistiques Détaillées
        </button>
    </div>
    
    <!-- Filtres -->
    <div class="filters-panel">
        <div class="filters-grid">
            <div class="filter-group">
                <label for="filterStatut">Statut</label>
                <select id="filterStatut">
                    <option value="">Tous les statuts</option>
                    <option value="nouvelle">Nouvelles</option>
                    <option value="en_cours">En cours</option>
                    <option value="en_attente">En attente</option>
                    <option value="resolue">Résolues</option>
                    <option value="fermee">Fermées</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filterType">Type de réclamation</label>
                <select id="filterType">
                    <option value="">Tous les types</option>
                    <option value="paiement_non_passe">Paiement non passé</option>
                    <option value="retard_commande">Retard de commande</option>
                    <option value="colis_non_livre">Colis non livré</option>
                    <option value="comportement_coursier">Comportement coursier</option>
                    <option value="colis_endommage">Colis endommagé</option>
                    <option value="erreur_adresse">Erreur d'adresse</option>
                    <option value="probleme_contact">Problème de contact</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filterPriorite">Priorité</label>
                <select id="filterPriorite">
                    <option value="">Toutes priorités</option>
                    <option value="urgente">Urgente</option>
                    <option value="haute">Haute</option>
                    <option value="normale">Normale</option>
                    <option value="basse">Basse</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filterSearch">Recherche</label>
                <input type="text" id="filterSearch" placeholder="N° suivi, transaction, client...">
            </div>
        </div>
        
        <div class="quick-actions">
            <button class="action-btn" onclick="ReclamationsManager.applyFilters()">
                <i class="fas fa-search"></i> Filtrer
            </button>
            <button class="action-btn" onclick="ReclamationsManager.resetFilters()">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </div>
    
    <!-- Liste des réclamations -->
    <div class="reclamations-list">
        <div class="list-header">
            <h3><i class="fas fa-list-alt"></i> Réclamations Active (<span id="reclamationsCount">0</span>)</h3>
            <div class="list-controls">
                <select id="sortOrder" onchange="ReclamationsManager.sortList()">
                    <option value="date_desc">Plus récentes</option>
                    <option value="priority">Par priorité</option>
                    <option value="status">Par statut</option>
                </select>
            </div>
        </div>
        
        <div id="reclamationsList">
            <!-- Contenu chargé dynamiquement -->
            <div style="padding: 40px; text-align: center; color: rgba(255,255,255,0.6);">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-gold);"></i>
                <div style="margin-top: 15px;">Chargement des réclamations...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de détails/traitement -->
<div id="reclamationModal" class="reclamation-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-alt"></i> <span id="modalTitle">Détail Réclamation</span></h2>
            <button class="modal-close" onclick="ReclamationsManager.closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Contenu dynamique -->
        </div>
    </div>
</div>

<script>
/**
 * Gestionnaire avancé des réclamations
 */
class ReclamationsManager {
    static currentReclamation = null;
    static filters = {};
    
    static init() {
        this.loadReclamations();
        
        // Actualisation automatique
        setInterval(() => {
            this.loadReclamations();
        }, 30000);
        
        // Events listeners
        document.getElementById('filterSearch').addEventListener('input', 
            this.debounce(() => this.applyFilters(), 500));
    }
    
    static async loadReclamations(filters = {}) {
        try {
            const response = await fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'reclamation_get_list',
                    ...filters
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.renderReclamations(result.reclamations);
                document.getElementById('reclamationsCount').textContent = result.total;
            } else {
                throw new Error(result.error);
            }
            
        } catch (error) {
            console.error('Erreur chargement réclamations:', error);
            this.showError('Erreur lors du chargement des réclamations');
        }
    }
    
    static renderReclamations(reclamations) {
        const container = document.getElementById('reclamationsList');
        
        if (reclamations.length === 0) {
            container.innerHTML = `
                <div style="padding: 40px; text-align: center; color: rgba(255,255,255,0.6);">
                    <i class="fas fa-inbox" style="font-size: 2rem; color: var(--primary-gold);"></i>
                    <div style="margin-top: 15px;">Aucune réclamation trouvée</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = reclamations.map(rec => `
            <div class="reclamation-item" onclick="ReclamationsManager.openReclamation(${rec.id})">
                <div class="item-content">
                    <div class="item-priority priority-${rec.priorite}"></div>
                    
                    <div class="item-info">
                        <div class="item-title">
                            ${rec.sujet} 
                            <span style="color: rgba(255,255,255,0.6); font-weight: 500; font-size: 0.9rem;">
                                #${rec.numero_suivi}
                            </span>
                        </div>
                        
                        <div class="item-details">
                            <div class="item-detail">
                                <i class="fas fa-user"></i>
                                ${rec.client_nom || 'Client anonyme'}
                            </div>
                            <div class="item-detail">
                                <i class="fas fa-shopping-bag"></i>
                                ${rec.numero_transaction || 'N/A'}
                            </div>
                            <div class="item-detail">
                                <i class="fas fa-calendar"></i>
                                ${new Date(rec.date_creation).toLocaleDateString('fr-FR')}
                            </div>
                            ${rec.coursier_nom ? `
                                <div class="item-detail">
                                    <i class="fas fa-motorcycle"></i>
                                    ${rec.coursier_nom}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="item-badges">
                        <span class="badge badge-${rec.statut}">${this.formatStatut(rec.statut)}</span>
                        <span class="type-badge">${this.formatType(rec.type_reclamation)}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    static formatType(type) {
        const types = {
            'paiement_non_passe': 'Paiement',
            'retard_commande': 'Retard',
            'colis_non_livre': 'Non livré',
            'comportement_coursier': 'Comportement',
            'colis_endommage': 'Endommagé',
            'erreur_adresse': 'Adresse',
            'probleme_contact': 'Contact',
            'autre': 'Autre'
        };
        return types[type] || type;
    }
    
    static formatStatut(statut) {
        const statuts = {
            'nouvelle': 'Nouvelle',
            'en_cours': 'En cours',
            'en_attente': 'En attente', 
            'resolue': 'Résolue',
            'fermee': 'Fermée'
        };
        return statuts[statut] || statut;
    }
    
    static async openReclamation(id) {
        try {
            const response = await fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'reclamation_get_details',
                    id: id
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showReclamationDetails(result.reclamation, result.chat_historique);
            } else {
                throw new Error(result.error);
            }
            
        } catch (error) {
            console.error('Erreur ouverture réclamation:', error);
            this.showError('Erreur lors de l\'ouverture de la réclamation');
        }
    }
    
    static showReclamationDetails(reclamation, chatHistorique) {
        this.currentReclamation = reclamation;
        
        const modal = document.getElementById('reclamationModal');
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalBody');
        
        title.textContent = `Réclamation #${reclamation.numero_suivi}`;
        
        // Construction du contenu détaillé
        body.innerHTML = this.buildDetailedView(reclamation, chatHistorique);
        
        modal.style.display = 'flex';
    }
    
    static buildDetailedView(rec, chatHistorique) {
        const fichiers = rec.fichiers_joints_array || [];
        const metadata = rec.metadata_array || {};
        
        return `
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- Colonne principale -->
                <div>
                    <!-- Informations générales -->
                    <div style="background: var(--glass-bg); padding: 25px; border-radius: 15px; margin-bottom: 25px;">
                        <h3 style="color: var(--primary-gold); margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> Informations Générales
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <label style="color: var(--primary-gold); font-weight: 600; display: block; margin-bottom: 5px;">Type</label>
                                <div>${this.formatType(rec.type_reclamation)}</div>
                            </div>
                            <div>
                                <label style="color: var(--primary-gold); font-weight: 600; display: block; margin-bottom: 5px;">Priorité</label>
                                <span class="badge badge-${rec.priorite}">${rec.priorite}</span>
                            </div>
                            <div>
                                <label style="color: var(--primary-gold); font-weight: 600; display: block; margin-bottom: 5px;">Statut</label>
                                <select id="statusSelect" style="background: var(--glass-bg); color: white; border: 1px solid var(--glass-border); padding: 8px; border-radius: 8px;">
                                    <option value="nouvelle" ${rec.statut === 'nouvelle' ? 'selected' : ''}>Nouvelle</option>
                                    <option value="en_cours" ${rec.statut === 'en_cours' ? 'selected' : ''}>En cours</option>
                                    <option value="en_attente" ${rec.statut === 'en_attente' ? 'selected' : ''}>En attente</option>
                                    <option value="resolue" ${rec.statut === 'resolue' ? 'selected' : ''}>Résolue</option>
                                    <option value="fermee" ${rec.statut === 'fermee' ? 'selected' : ''}>Fermée</option>
                                </select>
                            </div>
                            <div>
                                <label style="color: var(--primary-gold); font-weight: 600; display: block; margin-bottom: 5px;">Date création</label>
                                <div>${new Date(rec.date_creation).toLocaleString('fr-FR')}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div style="background: var(--glass-bg); padding: 25px; border-radius: 15px; margin-bottom: 25px;">
                        <h3 style="color: var(--primary-gold); margin-bottom: 15px;">
                            <i class="fas fa-align-left"></i> Description du problème
                        </h3>
                        <div style="line-height: 1.6; white-space: pre-wrap;">${rec.description}</div>
                    </div>
                    
                    <!-- Fichiers joints -->
                    ${fichiers.length > 0 ? `
                        <div style="background: var(--glass-bg); padding: 25px; border-radius: 15px; margin-bottom: 25px;">
                            <h3 style="color: var(--primary-gold); margin-bottom: 15px;">
                                <i class="fas fa-paperclip"></i> Fichiers joints (${fichiers.length})
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                                ${fichiers.map(f => `
                                    <div style="border: 1px solid var(--glass-border); border-radius: 10px; padding: 15px; text-align: center;">
                                        <i class="fas fa-file" style="font-size: 2rem; color: var(--primary-gold); margin-bottom: 10px;"></i>
                                        <div style="font-size: 0.9rem; word-break: break-all;">${f.name}</div>
                                        <button onclick="ReclamationsManager.downloadFile('${f.path}')" 
                                                style="background: var(--primary-gold); color: var(--primary-dark); border: none; padding: 8px 15px; border-radius: 5px; margin-top: 10px; cursor: pointer;">
                                            Télécharger
                                        </button>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Historique chat IA -->
                    ${chatHistorique && chatHistorique.length > 0 ? `
                        <div style="background: var(--glass-bg); padding: 25px; border-radius: 15px; margin-bottom: 25px;">
                            <h3 style="color: var(--primary-gold); margin-bottom: 15px;">
                                <i class="fas fa-robot"></i> Historique Chat IA
                            </h3>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--glass-border); border-radius: 10px; padding: 15px;">
                                ${chatHistorique.map(msg => `
                                    <div style="margin-bottom: 15px; padding: 10px; border-radius: 8px; background: ${msg.type === 'user' ? 'rgba(212,168,83,0.1)' : 'rgba(255,255,255,0.05)'};">
                                        <div style="font-weight: 600; margin-bottom: 5px; color: var(--primary-gold);">
                                            ${msg.type === 'user' ? '👤 Client' : '🤖 IA Suzosky'}
                                            <span style="float: right; font-weight: 400; font-size: 0.8rem;">${msg.time}</span>
                                        </div>
                                        <div>${msg.message}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Réponse admin -->
                    <div style="background: var(--glass-bg); padding: 25px; border-radius: 15px;">
                        <h3 style="color: var(--primary-gold); margin-bottom: 15px;">
                            <i class="fas fa-reply"></i> Réponse Administrative
                        </h3>
                        
                        ${rec.reponse_admin ? `
                            <div style="background: rgba(39,174,96,0.1); border: 1px solid #27AE60; border-radius: 10px; padding: 15px; margin-bottom: 15px;">
                                <div style="color: #27AE60; font-weight: 600; margin-bottom: 10px;">Réponse existante :</div>
                                <div style="line-height: 1.6;">${rec.reponse_admin}</div>
                            </div>
                        ` : ''}
                        
                        <textarea id="adminResponse" rows="4" placeholder="Saisir votre réponse..." 
                                  style="width: 100%; background: var(--glass-bg); color: white; border: 1px solid var(--glass-border); border-radius: 10px; padding: 15px; resize: vertical;">
                        </textarea>
                        
                        <div style="margin-top: 15px; display: flex; gap: 15px;">
                            <button onclick="ReclamationsManager.saveResponse()" 
                                    style="background: var(--gradient-gold); color: var(--primary-dark); border: none; padding: 12px 25px; border-radius: 10px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-save"></i> Enregistrer Réponse
                            </button>
                            <button onclick="ReclamationsManager.updateStatus()" 
                                    style="background: var(--primary-gold); color: var(--primary-dark); border: none; padding: 12px 25px; border-radius: 10px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-check"></i> Mettre à jour Statut
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Colonne latérale -->
                <div>
                    <!-- Infos client -->
                    <div style="background: var(--glass-bg); padding: 20px; border-radius: 15px; margin-bottom: 20px;">
                        <h4 style="color: var(--primary-gold); margin-bottom: 15px;">
                            <i class="fas fa-user"></i> Client
                        </h4>
                        <div style="font-size: 0.9rem; line-height: 1.8;">
                            <div><strong>Nom:</strong> ${rec.client_nom || 'N/A'}</div>
                            <div><strong>Tél:</strong> ${rec.client_telephone || 'N/A'}</div>
                            <div><strong>Type:</strong> ${rec.client_id ? 'Enregistré' : 'Invité'}</div>
                        </div>
                    </div>
                    
                    <!-- Infos commande -->
                    ${rec.numero_transaction ? `
                        <div style="background: var(--glass-bg); padding: 20px; border-radius: 15px; margin-bottom: 20px;">
                            <h4 style="color: var(--primary-gold); margin-bottom: 15px;">
                                <i class="fas fa-shopping-bag"></i> Commande
                            </h4>
                            <div style="font-size: 0.9rem; line-height: 1.8;">
                                <div><strong>N°:</strong> ${rec.numero_transaction}</div>
                                <div><strong>Montant:</strong> ${rec.montant_commande ? rec.montant_commande + ' FCFA' : 'N/A'}</div>
                                <div><strong>Date:</strong> ${rec.date_commande ? new Date(rec.date_commande).toLocaleDateString('fr-FR') : 'N/A'}</div>
                                <div><strong>Statut:</strong> ${rec.statut_commande || 'N/A'}</div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Infos coursier -->
                    ${rec.coursier_nom ? `
                        <div style="background: var(--glass-bg); padding: 20px; border-radius: 15px; margin-bottom: 20px;">
                            <h4 style="color: var(--primary-gold); margin-bottom: 15px;">
                                <i class="fas fa-motorcycle"></i> Coursier
                            </h4>
                            <div style="font-size: 0.9rem; line-height: 1.8;">
                                <div><strong>Nom:</strong> ${rec.coursier_nom}</div>
                                <div><strong>Tél:</strong> ${rec.coursier_telephone || 'N/A'}</div>
                                <div><strong>Matricule:</strong> ${rec.matricule || 'N/A'}</div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Actions rapides -->
                    <div style="background: var(--glass-bg); padding: 20px; border-radius: 15px;">
                        <h4 style="color: var(--primary-gold); margin-bottom: 15px;">
                            <i class="fas fa-bolt"></i> Actions Rapides
                        </h4>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <button onclick="ReclamationsManager.callClient('${rec.client_telephone}')" 
                                    style="background: var(--glass-bg); color: var(--primary-gold); border: 1px solid var(--primary-gold); padding: 10px; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-phone"></i> Appeler Client
                            </button>
                            <button onclick="ReclamationsManager.sendSMS('${rec.client_telephone}')" 
                                    style="background: var(--glass-bg); color: var(--primary-gold); border: 1px solid var(--primary-gold); padding: 10px; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-sms"></i> Envoyer SMS
                            </button>
                            <button onclick="ReclamationsManager.printReclamation()" 
                                    style="background: var(--glass-bg); color: var(--primary-gold); border: 1px solid var(--primary-gold); padding: 10px; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-print"></i> Imprimer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    static async saveResponse() {
        const response = document.getElementById('adminResponse').value.trim();
        if (!response) {
            alert('Veuillez saisir une réponse');
            return;
        }
        
        try {
            const result = await fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'reclamation_add_response',
                    id: this.currentReclamation.id,
                    reponse: response
                })
            });
            
            const data = await result.json();
            if (data.success) {
                this.showSuccess('Réponse enregistrée avec succès');
                this.loadReclamations();
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            this.showError('Erreur lors de l\'enregistrement: ' + error.message);
        }
    }
    
    static async updateStatus() {
        const status = document.getElementById('statusSelect').value;
        
        try {
            const result = await fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'reclamation_update_status',
                    id: this.currentReclamation.id,
                    statut: status
                })
            });
            
            const data = await result.json();
            if (data.success) {
                this.showSuccess('Statut mis à jour avec succès');
                this.loadReclamations();
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            this.showError('Erreur lors de la mise à jour: ' + error.message);
        }
    }
    
    static applyFilters() {
        const filters = {
            statut: document.getElementById('filterStatut').value,
            type: document.getElementById('filterType').value,
            priorite: document.getElementById('filterPriorite').value,
            search: document.getElementById('filterSearch').value
        };
        
        this.filters = filters;
        this.loadReclamations(filters);
    }
    
    static resetFilters() {
        document.getElementById('filterStatut').value = '';
        document.getElementById('filterType').value = '';
        document.getElementById('filterPriorite').value = '';
        document.getElementById('filterSearch').value = '';
        
        this.filters = {};
        this.loadReclamations();
    }
    
    static refreshList() {
        this.loadReclamations(this.filters);
        this.showSuccess('Liste actualisée');
    }
    
    static closeModal() {
        document.getElementById('reclamationModal').style.display = 'none';
        this.currentReclamation = null;
    }
    
    static showSuccess(message) {
        // Toast notification de succès
        console.log('Success:', message);
    }
    
    static showError(message) {
        // Toast notification d'erreur
        console.error('Error:', message);
        alert('Erreur: ' + message);
    }
    
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Autres méthodes utilitaires
    static showCreateModal() {
        // TODO: Modal de création manuelle
        alert('Fonctionnalité en développement');
    }
    
    static callClient(phone) {
        if (phone) {
            window.open(`tel:${phone}`);
        }
    }
    
    static sendSMS(phone) {
        if (phone) {
            window.open(`sms:${phone}`);
        }
    }
    
    static printReclamation() {
        window.print();
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    ReclamationsManager.init();
});
</script>