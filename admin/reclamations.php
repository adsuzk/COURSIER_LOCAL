<?php
/**
 * ============================================================================
 * 📋 MODULE RÉCLAMATIONS ADMIN - INTERFACE INLINE SUZOSKY
 * ============================================================================
 * 
 * Interface administrative intégrée pour la gestion des réclamations
 * Inclusion du système complet avec IA, historique chat, suivi fichiers
 * 
 * @version 2.0.0 - Interface Admin Intégrée
 * @author Équipe Suzosky
 * @date 25 septembre 2025
 * ============================================================================
 */

// Sécurité : vérifier que le fichier est inclus depuis admin.php et qu'un admin est authentifié
if (!defined('ADMIN_ACCESS')) {
    if (!function_exists('checkAdminAuth')) {
        require_once __DIR__ . '/functions.php';
    }
    if (!checkAdminAuth()) {
        http_response_code(403);
        exit('Accès non autorisé');
    }
}

// Connexion base de données pour le module
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = getPDO();
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Impossible de se connecter à la base de données');
    }
}

// Support des payloads JSON pour les requêtes AJAX modernes
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && empty($_POST)) {
    $rawBody = file_get_contents('php://input');
    if ($rawBody) {
        $jsonBody = json_decode($rawBody, true);
        if (is_array($jsonBody)) {
            foreach ($jsonBody as $key => $value) {
                if (!isset($_POST[$key])) {
                    $_POST[$key] = $value;
                }
            }
        }
    }
}

// Inclure le système de réclamations complet
require_once __DIR__ . '/../reclamation.php';

// Traitement des actions AJAX EXISTANTES (compatibilité)
if (isset($_POST['action']) && $_POST['action'] === 'get_reclamations') {
    header('Content-Type: application/json');
    
    try {
        // Filtres
        $filters = [];
        $params = [];
        
        if (!empty($_POST['statut'])) {
            $filters[] = "statut = ?";
            $params[] = $_POST['statut'];
        }
        
        if (!empty($_POST['type'])) {
            $filters[] = "type_reclamation = ?";
            $params[] = $_POST['type'];
        }
        
        if (!empty($_POST['priorite'])) {
            $filters[] = "priorite = ?";
            $params[] = $_POST['priorite'];
        }
        
        if (!empty($_POST['numero_transaction'])) {
            $filters[] = "numero_transaction LIKE ?";
            $params[] = '%' . $_POST['numero_transaction'] . '%';
        }
        
        $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
        
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   CASE 
                       WHEN r.client_id IS NOT NULL THEN 'Client enregistré'
                       ELSE 'Client invité'
                   END as type_client,
                   c.nom_client, c.telephone as client_telephone
            FROM reclamations r
            LEFT JOIN commandes c ON r.numero_transaction = c.numero_commande
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
        
        echo json_encode([
            'success' => true,
            'reclamations' => $reclamations,
            'total' => count($reclamations)
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'update_reclamation') {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE reclamations 
            SET statut = ?, reponse_admin = ?, admin_id = ?, 
                date_resolution = CASE WHEN ? = 'resolue' THEN NOW() ELSE date_resolution END
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['statut'],
            $_POST['reponse_admin'],
            $_SESSION['admin_id'] ?? 1,
            $_POST['statut'],
            $_POST['id']
        ]);
        
        echo json_encode(['success' => true]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Statistiques pour le dashboard
$stats = [];
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'nouvelle' THEN 1 ELSE 0 END) as nouvelles,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'resolue' THEN 1 ELSE 0 END) as resolues,
            SUM(CASE WHEN priorite = 'urgente' THEN 1 ELSE 0 END) as urgentes
        FROM reclamations
        WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'nouvelles' => 0, 'en_cours' => 0, 'resolues' => 0, 'urgentes' => 0];
}
?>

<style>
/* === VARIABLES SUZOSKY === */
:root {
    --primary-gold: #D4A853;
    --primary-dark: #1a1a2e;
    --secondary-blue: #16213e;
    --accent-purple: #8b5a96;
    --success-green: #28a745;
    --warning-orange: #ffc107;
    --danger-red: #dc3545;
    --info-blue: #17a2b8;
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #f4e4bc 100%);
    --gradient-dark: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    --shadow-gold: 0 8px 32px rgba(212, 168, 83, 0.3);
    --shadow-dark: 0 8px 32px rgba(0, 0, 0, 0.3);
}

/* === LAYOUT RÉCLAMATIONS === */
.reclamations-container {
    padding: 20px;
    background: var(--gradient-dark);
    min-height: 100vh;
    color: white;
}

.reclamations-hero {
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

.hero-content h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 10px 0;
}

.hero-content p {
    font-size: 1.1rem;
    margin: 0;
    opacity: 0.8;
}

.hero-stats {
    display: flex;
    gap: 20px;
}

.hero-stat {
    text-align: center;
    background: rgba(26, 26, 46, 0.1);
    padding: 15px 20px;
    border-radius: 15px;
    backdrop-filter: blur(10px);
}

.stat-value {
    display: block;
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary-dark);
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    opacity: 0.7;
    margin-top: 5px;
}

/* === FILTRES === */
.filters-container {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    backdrop-filter: blur(15px);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.filters-header h3 {
    color: var(--primary-gold);
    font-weight: 700;
    margin: 0;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    color: var(--primary-gold);
    font-weight: 600;
    font-size: 0.9rem;
}

.filter-group select,
.filter-group input {
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
    background: rgba(255, 255, 255, 0.08);
}

.filter-actions {
    display: flex;
    gap: 15px;
    align-items: end;
}

.btn-filter {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border: none;
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
}

.btn-reset {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid var(--glass-border);
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-reset:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

/* === TABLEAU RÉCLAMATIONS === */
.reclamations-table-container {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 25px;
    backdrop-filter: blur(15px);
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.table-header h3 {
    color: var(--primary-gold);
    font-weight: 700;
    margin: 0;
}

.reclamations-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.reclamations-table th {
    background: var(--secondary-blue);
    color: var(--primary-gold);
    padding: 15px 12px;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    border-bottom: 2px solid var(--primary-gold);
}

.reclamations-table td {
    padding: 15px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    vertical-align: middle;
}

.reclamations-table tr:hover {
    background: rgba(212, 168, 83, 0.05);
}

/* === BADGES ET STATUTS === */
.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-nouvelle {
    background: var(--info-blue);
    color: white;
}

.badge-en-cours {
    background: var(--warning-orange);
    color: var(--primary-dark);
}

.badge-resolue {
    background: var(--success-green);
    color: white;
}

.badge-fermee {
    background: var(--danger-red);
    color: white;
}

.badge-urgente {
    background: var(--danger-red);
    color: white;
    animation: pulse 2s infinite;
}

.badge-haute {
    background: var(--warning-orange);
    color: var(--primary-dark);
}

.badge-normale {
    background: var(--info-blue);
    color: white;
}

.badge-basse {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* === ACTIONS === */
.action-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    margin: 0 5px;
}

.btn-voir {
    background: var(--primary-gold);
    color: var(--primary-dark);
}

.btn-traiter {
    background: var(--success-green);
    color: white;
}

.btn-fermer {
    background: var(--danger-red);
    color: white;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

/* === MODAL === */
.reclamation-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
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
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: var(--shadow-dark);
}

.modal-header {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    padding: 25px;
    border-radius: 20px 20px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-weight: 800;
}

.modal-close {
    background: none;
    border: none;
    color: var(--primary-dark);
    font-size: 1.5rem;
    cursor: pointer;
    font-weight: bold;
}

.modal-body {
    padding: 30px;
    color: white;
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .reclamations-hero {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .hero-stats {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .reclamations-table {
        font-size: 0.8rem;
    }
    
    .action-btn {
        padding: 6px 10px;
        font-size: 0.75rem;
        margin: 2px;
    }
}
</style>

<div class="reclamations-container">
    <!-- Hero Section -->
    <div class="reclamations-hero">
        <div class="hero-content">
            <h1><i class="fas fa-exclamation-triangle"></i> Gestion des Réclamations</h1>
            <p>Interface premium pour le traitement des réclamations clients avec synchronisation parfaite</p>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="stat-value"><?= $stats['total'] ?? 0 ?></span>
                <span class="stat-label">Total (30j)</span>
            </div>
            <div class="hero-stat">
                <span class="stat-value"><?= $stats['nouvelles'] ?? 0 ?></span>
                <span class="stat-label">Nouvelles</span>
            </div>
            <div class="hero-stat">
                <span class="stat-value"><?= $stats['en_cours'] ?? 0 ?></span>
                <span class="stat-label">En cours</span>
            </div>
            <div class="hero-stat">
                <span class="stat-value"><?= $stats['urgentes'] ?? 0 ?></span>
                <span class="stat-label">Urgentes</span>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="filters-container">
        <div class="filters-header">
            <h3><i class="fas fa-filter"></i> Filtres Avancés</h3>
        </div>
        
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
                <label for="filterType">Type</label>
                <select id="filterType">
                    <option value="">Tous les types</option>
                    <option value="commande">Commande</option>
                    <option value="livraison">Livraison</option>
                    <option value="paiement">Paiement</option>
                    <option value="coursier">Coursier</option>
                    <option value="technique">Technique</option>
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
                <label for="filterTransaction">N° Transaction</label>
                <input type="text" id="filterTransaction" placeholder="Rechercher...">
            </div>
            
            <div class="filter-actions">
                <button class="btn-filter" onclick="ReclamationsManager.filterReclamations()">
                    <i class="fas fa-search"></i> Filtrer
                </button>
                <button class="btn-reset" onclick="ReclamationsManager.resetFilters()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tableau des réclamations -->
    <div class="reclamations-table-container">
        <div class="table-header">
            <h3><i class="fas fa-list-alt"></i> Liste des Réclamations</h3>
            <button class="btn-filter" onclick="ReclamationsManager.refreshTable()">
                <i class="fas fa-sync"></i> Actualiser
            </button>
        </div>
        
        <table class="reclamations-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Transaction</th>
                    <th>Type</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="reclamationsTableBody">
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-gold);"></i>
                        <div style="margin-top: 15px;">Chargement des réclamations...</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de détail/traitement -->
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
 * Gestionnaire des réclamations
 */
class ReclamationsManager {
    static currentReclamation = null;
    
    static init() {
        this.loadReclamations();
        
        // Actualisation automatique toutes les 30 secondes
        setInterval(() => {
            this.loadReclamations();
        }, 30000);
    }
    
    static async loadReclamations(filters = {}) {
        try {
            const response = await fetch('admin.php?section=reclamations', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_reclamations',
                    ...filters
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.renderTable(result.reclamations);
            } else {
                throw new Error(result.error);
            }
            
        } catch (error) {
            console.error('Erreur chargement réclamations:', error);
            this.showError('Erreur lors du chargement des réclamations');
        }
    }
    
    static renderTable(reclamations) {
        const tbody = document.getElementById('reclamationsTableBody');
        const list = Array.isArray(reclamations) ? reclamations : [];
        const unique = [];
        const seenIds = new Set();

        for (const item of list) {
            if (item && typeof item.id !== 'undefined') {
                if (seenIds.has(item.id)) {
                    continue;
                }
                seenIds.add(item.id);
            }
            unique.push(item);
        }

        if (unique.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 2rem; color: var(--primary-gold);"></i>
                        <div style="margin-top: 15px;">Aucune réclamation trouvée</div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = unique.map(rec => `
            <tr>
                <td><strong>#${rec.id}</strong></td>
                <td><code>${rec.numero_transaction}</code></td>
                <td><span class="badge badge-${rec.type_reclamation}">${this.formatType(rec.type_reclamation)}</span></td>
                <td><span class="badge badge-${rec.priorite}">${this.formatPriorite(rec.priorite)}</span></td>
                <td><span class="badge badge-${rec.statut}">${this.formatStatut(rec.statut)}</span></td>
                <td>
                    ${rec.client_nom || 'Client invité'}<br>
                    <small style="opacity: 0.7;">${rec.client_telephone || ''}</small>
                </td>
                <td>
                    ${new Date(rec.date_creation).toLocaleDateString('fr-FR')}<br>
                    <small style="opacity: 0.7;">${new Date(rec.date_creation).toLocaleTimeString('fr-FR')}</small>
                </td>
                <td>
                    <button class="action-btn btn-voir" onclick="ReclamationsManager.viewReclamation(${rec.id})">
                        <i class="fas fa-eye"></i> Voir
                    </button>
                    ${rec.statut !== 'resolue' && rec.statut !== 'fermee' ? `
                        <button class="action-btn btn-traiter" onclick="ReclamationsManager.treatReclamation(${rec.id})">
                            <i class="fas fa-cog"></i> Traiter
                        </button>
                    ` : ''}
                </td>
            </tr>
        `).join('');
    }
    
    static formatType(type) {
        const types = {
            'commande': 'Commande',
            'livraison': 'Livraison', 
            'paiement': 'Paiement',
            'coursier': 'Coursier',
            'technique': 'Technique',
            'autre': 'Autre'
        };
        return types[type] || type;
    }
    
    static formatPriorite(priorite) {
        const priorites = {
            'urgente': 'Urgente',
            'haute': 'Haute',
            'normale': 'Normale',
            'basse': 'Basse'
        };
        return priorites[priorite] || priorite;
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
    
    static filterReclamations() {
        const filters = {
            statut: document.getElementById('filterStatut').value,
            type: document.getElementById('filterType').value,
            priorite: document.getElementById('filterPriorite').value,
            numero_transaction: document.getElementById('filterTransaction').value
        };
        
        this.loadReclamations(filters);
    }
    
    static resetFilters() {
        document.getElementById('filterStatut').value = '';
        document.getElementById('filterType').value = '';
        document.getElementById('filterPriorite').value = '';
        document.getElementById('filterTransaction').value = '';
        
        this.loadReclamations();
    }
    
    static refreshTable() {
        this.loadReclamations();
        this.showSuccess('Table actualisée avec succès !');
    }
    
    static async viewReclamation(id) {
        // Logique pour afficher le détail de la réclamation
        const modal = document.getElementById('reclamationModal');
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalBody');
        
        title.textContent = `Réclamation #${id}`;
        body.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-gold);"></i>
                <div style="margin-top: 15px;">Chargement des détails...</div>
            </div>
        `;
        
        modal.style.display = 'flex';
        
        // Ici on chargerait les détails de la réclamation
        // Pour l'instant, contenu d'exemple
        setTimeout(() => {
            body.innerHTML = `
                <div style="color: white;">
                    <h3>Détails de la réclamation #${id}</h3>
                    <p>Fonctionnalité en cours d'implémentation...</p>
                </div>
            `;
        }, 1000);
    }
    
    static treatReclamation(id) {
        this.viewReclamation(id);
        // Logique pour traiter la réclamation
    }
    
    static closeModal() {
        document.getElementById('reclamationModal').style.display = 'none';
    }
    
    static showSuccess(message) {
        // Notification de succès
        console.log('Success:', message);
    }
    
    static showError(message) {
        // Notification d'erreur
        console.error('Error:', message);
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    ReclamationsManager.init();
});
</script>