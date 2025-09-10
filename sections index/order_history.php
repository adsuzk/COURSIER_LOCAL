<?php
// sections index/order_history.php - Historique des commandes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['client_id'])) {
    echo '<div class="error-message">Veuillez vous connecter pour accéder à votre historique.</div>';
    exit;
}
?>

<div class="order-history-container">
    <div class="history-header">
        <h3><i class="fas fa-history"></i> Historique des commandes</h3>
        <div class="history-stats">
            <div class="stat-item">
                <span class="stat-number" id="totalOrders">0</span>
                <span class="stat-label">Commandes</span>
            </div>
            <div class="stat-item">
                <span class="stat-number" id="completedOrders">0</span>
                <span class="stat-label">Livrées</span>
            </div>
        </div>
    </div>

    <div class="history-filters">
        <select id="statusFilter" onchange="filterOrders()">
            <option value="">Tous les statuts</option>
            <option value="nouvelle">Nouvelle</option>
            <option value="assignee">Assignée</option>
            <option value="en_cours">En cours</option>
            <option value="livree">Livrée</option>
            <option value="annulee">Annulée</option>
        </select>
        <input type="month" id="monthFilter" onchange="filterOrders()" />
    </div>

    <div id="ordersLoading" class="loading-spinner" style="display: none;">
        <div class="spinner"></div>
        <p>Chargement de votre historique...</p>
    </div>

    <div id="ordersContainer" class="orders-list">
        <!-- Les commandes seront chargées ici -->
    </div>

    <div id="noOrders" class="no-orders" style="display: none;">
        <div class="no-orders-icon">
            <i class="fas fa-box-open"></i>
        </div>
        <h4>Aucune commande trouvée</h4>
        <p>Vous n'avez pas encore passé de commande ou aucune commande ne correspond aux filtres sélectionnés.</p>
        <button class="btn-new-order" onclick="closeAccountModal()">
            <i class="fas fa-plus"></i> Passer une nouvelle commande
        </button>
    </div>
</div>

<!-- Modal de détails de commande -->
<div id="orderDetailsModal" class="order-modal" style="display: none;">
    <div class="order-modal-content">
        <div class="order-modal-header">
            <h3 id="orderDetailsTitle">Détails de la commande</h3>
            <span class="close-order-modal" onclick="closeOrderDetails()">&times;</span>
        </div>
        <div id="orderDetailsBody" class="order-modal-body">
            <!-- Détails chargés dynamiquement -->
        </div>
    </div>
</div>

<style>
.order-history-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 20px;
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 215, 0, 0.05));
    border-radius: 12px;
    border: 1px solid rgba(255, 215, 0, 0.3);
}

.history-header h3 {
    margin: 0;
    color: #333;
    font-size: 24px;
}

.history-stats {
    display: flex;
    gap: 20px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #FFD700;
}

.stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.history-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.history-filters select,
.history-filters input {
    padding: 8px 12px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.history-filters select:focus,
.history-filters input:focus {
    outline: none;
    border-color: #FFD700;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(255, 215, 0, 0.3);
    border-top: 4px solid #FFD700;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.order-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    backdrop-filter: blur(10px);
}

.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-color: #FFD700;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.order-number {
    font-weight: bold;
    color: #333;
    font-size: 16px;
}

.order-date {
    color: #666;
    font-size: 14px;
}

.order-route {
    margin-bottom: 10px;
}

.route-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
}

.route-icon {
    margin-right: 10px;
    color: #FFD700;
    width: 20px;
}

.route-address {
    color: #555;
}

.order-details {
    display: flex;
    justify-content: between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.order-amount {
    font-weight: bold;
    color: #28a745;
    font-size: 16px;
}

.order-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.status-new { background: #e3f2fd; color: #1976d2; }
.status-assigned { background: #fff3e0; color: #f57c00; }
.status-progress { background: #f3e5f5; color: #7b1fa2; }
.status-delivered { background: #e8f5e8; color: #388e3c; }
.status-cancelled { background: #ffebee; color: #d32f2f; }

.order-priority {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    margin-left: 8px;
}

.priority-normal { background: #f5f5f5; color: #666; }
.priority-urgent { background: #fff3e0; color: #f57c00; }
.priority-express { background: #ffebee; color: #d32f2f; }

.no-orders {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-orders-icon {
    font-size: 64px;
    color: #ddd;
    margin-bottom: 20px;
}

.no-orders h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.btn-new-order {
    background: #FFD700;
    color: #000;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    margin-top: 20px;
    transition: all 0.3s ease;
}

.btn-new-order:hover {
    background: #FFC700;
    transform: translateY(-1px);
}

/* Modal des détails */
.order-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.order-modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.order-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.order-modal-header h3 {
    margin: 0;
    color: #333;
}

.close-order-modal {
    font-size: 24px;
    cursor: pointer;
    color: #666;
    transition: color 0.3s ease;
}

.close-order-modal:hover {
    color: #333;
}

.order-modal-body {
    padding: 20px;
}

@media (max-width: 768px) {
    .history-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .history-stats {
        justify-content: center;
    }
    
    .order-details {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
let allOrders = [];

// Charger l'historique au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    loadOrderHistory();
});

function loadOrderHistory() {
    document.getElementById('ordersLoading').style.display = 'block';
    document.getElementById('ordersContainer').style.display = 'none';
    document.getElementById('noOrders').style.display = 'none';
    
    fetch('api/orders.php?action=get_history')
        .then(response => response.json())
        .then(data => {
            document.getElementById('ordersLoading').style.display = 'none';
            
            if (data.success) {
                allOrders = data.orders;
                displayOrders(allOrders);
                updateStats(allOrders);
            } else {
                console.error('Erreur:', data.error);
                document.getElementById('noOrders').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('ordersLoading').style.display = 'none';
            document.getElementById('noOrders').style.display = 'block';
        });
}

function displayOrders(orders) {
    const container = document.getElementById('ordersContainer');
    
    if (orders.length === 0) {
        container.style.display = 'none';
        document.getElementById('noOrders').style.display = 'block';
        return;
    }
    
    container.style.display = 'block';
    document.getElementById('noOrders').style.display = 'none';
    
    container.innerHTML = orders.map(order => `
        <div class="order-card" onclick="showOrderDetails(${order.id})">
            <div class="order-header">
                <div class="order-number">#${order.numero_commande}</div>
                <div class="order-date">${order.date}</div>
            </div>
            
            <div class="order-route">
                <div class="route-item">
                    <i class="fas fa-map-marker-alt route-icon"></i>
                    <span class="route-address">${order.depart}</span>
                </div>
                <div class="route-item">
                    <i class="fas fa-flag-checkered route-icon"></i>
                    <span class="route-address">${order.arrivee}</span>
                </div>
            </div>
            
            <div class="order-details">
                <div class="order-amount">${order.montant}</div>
                <div>
                    <span class="order-status ${order.statut_class}">${order.statut}</span>
                    <span class="order-priority ${order.priorite_class}">${order.priorite}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function updateStats(orders) {
    const total = orders.length;
    const completed = orders.filter(order => order.statut === 'Livrée').length;
    
    document.getElementById('totalOrders').textContent = total;
    document.getElementById('completedOrders').textContent = completed;
}

function filterOrders() {
    const statusFilter = document.getElementById('statusFilter').value;
    const monthFilter = document.getElementById('monthFilter').value;
    
    let filteredOrders = allOrders;
    
    if (statusFilter) {
        filteredOrders = filteredOrders.filter(order => 
            order.statut.toLowerCase() === statusFilter
        );
    }
    
    if (monthFilter) {
        filteredOrders = filteredOrders.filter(order => {
            const orderDate = new Date(order.date.split(' ')[0].split('/').reverse().join('-'));
            const filterDate = new Date(monthFilter + '-01');
            return orderDate.getFullYear() === filterDate.getFullYear() && 
                   orderDate.getMonth() === filterDate.getMonth();
        });
    }
    
    displayOrders(filteredOrders);
    updateStats(filteredOrders);
}

function showOrderDetails(orderId) {
    fetch(`api/orders.php?action=get_order_details&order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const order = data.order;
                document.getElementById('orderDetailsTitle').textContent = `Commande #${order.numero_commande}`;
                
                document.getElementById('orderDetailsBody').innerHTML = `
                    <div class="order-detail-section">
                        <h4><i class="fas fa-info-circle"></i> Informations générales</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Numéro de commande :</label>
                                <span>${order.numero_commande}</span>
                            </div>
                            <div class="detail-item">
                                <label>Date de création :</label>
                                <span>${new Date(order.date_creation).toLocaleString('fr-FR')}</span>
                            </div>
                            <div class="detail-item">
                                <label>Statut :</label>
                                <span class="order-status ${getStatusClass(order.statut)}">${getStatusLabel(order.statut)}</span>
                            </div>
                            <div class="detail-item">
                                <label>Priorité :</label>
                                <span class="order-priority ${getPriorityClass(order.priorite)}">${getPriorityLabel(order.priorite)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-detail-section">
                        <h4><i class="fas fa-route"></i> Itinéraire</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Point de départ :</label>
                                <span>${order.adresse_depart}</span>
                            </div>
                            <div class="detail-item">
                                <label>Point d'arrivée :</label>
                                <span>${order.adresse_arrivee}</span>
                            </div>
                            <div class="detail-item">
                                <label>Distance :</label>
                                <span>${order.distance_km || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Durée estimée :</label>
                                <span>${order.duree_estimee || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-detail-section">
                        <h4><i class="fas fa-box"></i> Colis</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Description :</label>
                                <span>${order.description_colis || 'Non spécifié'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-detail-section">
                        <h4><i class="fas fa-credit-card"></i> Paiement</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Montant :</label>
                                <span class="order-amount">${Number(order.prix_estime).toLocaleString('fr-FR')} FCFA</span>
                            </div>
                            <div class="detail-item">
                                <label>Mode de paiement :</label>
                                <span>${getPaymentMethodLabel(order.mode_paiement)}</span>
                            </div>
                            <div class="detail-item">
                                <label>Paiement confirmé :</label>
                                <span class="${order.paiement_confirme ? 'text-success' : 'text-warning'}">
                                    ${order.paiement_confirme ? '✅ Oui' : '⏳ En attente'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('orderDetailsModal').style.display = 'flex';
            } else {
                showMessage(data.error || 'Erreur lors du chargement', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showMessage('Erreur de connexion', 'error');
        });
}

function closeOrderDetails() {
    document.getElementById('orderDetailsModal').style.display = 'none';
}

// Fonctions utilitaires
function getStatusClass(status) {
    const classes = {
        'nouvelle': 'status-new',
        'assignee': 'status-assigned',
        'en_cours': 'status-progress',
        'livree': 'status-delivered',
        'annulee': 'status-cancelled'
    };
    return classes[status] || 'status-unknown';
}

function getStatusLabel(status) {
    const labels = {
        'nouvelle': 'Nouvelle',
        'assignee': 'Assignée',
        'en_cours': 'En cours',
        'livree': 'Livrée',
        'annulee': 'Annulée'
    };
    return labels[status] || status;
}

function getPriorityClass(priority) {
    const classes = {
        'normale': 'priority-normal',
        'urgente': 'priority-urgent',
        'express': 'priority-express'
    };
    return classes[priority] || 'priority-normal';
}

function getPriorityLabel(priority) {
    const labels = {
        'normale': 'Normale',
        'urgente': 'Urgente',
        'express': 'Express'
    };
    return labels[priority] || priority;
}

function getPaymentMethodLabel(method) {
    const labels = {
        'cash': 'Espèces',
        'orange_money': 'Orange Money',
        'mtn_money': 'MTN Money',
        'moov_money': 'Moov Money',
        'wave': 'Wave',
        'card': 'Carte bancaire'
    };
    return labels[method] || method;
}

function showMessage(message, type) {
    if (typeof window.showMessage === 'function') {
        window.showMessage(message, type);
    } else {
        alert(message);
    }
}
</script>

<style>
.order-detail-section {
    margin-bottom: 25px;
    padding: 15px;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 8px;
}

.order-detail-section h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
    border-bottom: 2px solid #FFD700;
    padding-bottom: 8px;
}

.detail-grid {
    display: grid;
    gap: 10px;
}

.detail-item {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item label {
    font-weight: 600;
    color: #555;
    min-width: 140px;
    margin-right: 15px;
}

.detail-item span {
    color: #333;
    flex: 1;
}

.text-success { color: #28a745; }
.text-warning { color: #ffc107; }
</style>
