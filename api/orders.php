<?php
// api/orders.php - API pour la gestion des commandes clients
require_once __DIR__ . '/../config.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion base de données: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_history':
        getOrderHistory();
        break;
    case 'get_order_details':
        getOrderDetails();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Action non spécifiée']);
}

function getOrderHistory() {
    global $pdo;
    $client_id = $_SESSION['client_id'];
    
    try {
        // Récupérer l'historique des commandes du client
        $stmt = $pdo->prepare("
            SELECT 
                id,
                numero_commande,
                adresse_depart,
                adresse_arrivee,
                description_colis,
                priorite,
                mode_paiement,
                prix_estime,
                distance_km,
                duree_estimee,
                statut,
                paiement_confirme,
                date_creation,
                date_modification
            FROM commandes 
            WHERE client_id = ? 
            ORDER BY date_creation DESC
        ");
        $stmt->execute([$client_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les données pour l'affichage
        $formattedOrders = [];
        foreach ($orders as $order) {
            $formattedOrders[] = [
                'id' => $order['id'],
                'numero_commande' => $order['numero_commande'],
                'date' => date('d/m/Y H:i', strtotime($order['date_creation'])),
                'depart' => truncateAddress($order['adresse_depart']),
                'arrivee' => truncateAddress($order['adresse_arrivee']),
                'description' => $order['description_colis'] ?? 'Non spécifié',
                'montant' => number_format($order['prix_estime'], 0, ',', ' ') . ' FCFA',
                'statut' => getStatusLabel($order['statut']),
                'statut_class' => getStatusClass($order['statut']),
                'priorite' => getPriorityLabel($order['priorite']),
                'priorite_class' => getPriorityClass($order['priorite']),
                'mode_paiement' => getPaymentMethodLabel($order['mode_paiement']),
                'paiement_confirme' => $order['paiement_confirme'],
                'distance' => $order['distance_km'] ?? 'N/A',
                'duree' => $order['duree_estimee'] ?? 'N/A'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'orders' => $formattedOrders,
            'total' => count($formattedOrders)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération: ' . $e->getMessage()]);
    }
}

function getOrderDetails() {
    global $pdo;
    $client_id = $_SESSION['client_id'];
    $order_id = $_POST['order_id'] ?? $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        echo json_encode(['success' => false, 'error' => 'ID de commande requis']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                *
            FROM commandes 
            WHERE id = ? AND client_id = ?
        ");
        $stmt->execute([$order_id, $client_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Commande non trouvée']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération: ' . $e->getMessage()]);
    }
}

// Fonctions utilitaires pour le formatage
function truncateAddress($address) {
    return strlen($address) > 50 ? substr($address, 0, 47) . '...' : $address;
}

function getStatusLabel($status) {
    $labels = [
        'nouvelle' => 'Nouvelle',
        'assignee' => 'Assignée',
        'en_cours' => 'En cours',
        'livree' => 'Livrée',
        'annulee' => 'Annulée'
    ];
    return $labels[$status] ?? $status;
}

function getStatusClass($status) {
    $classes = [
        'nouvelle' => 'status-new',
        'assignee' => 'status-assigned',
        'en_cours' => 'status-progress',
        'livree' => 'status-delivered',
        'annulee' => 'status-cancelled'
    ];
    return $classes[$status] ?? 'status-unknown';
}

function getPriorityLabel($priority) {
    $labels = [
        'normale' => 'Normale',
        'urgente' => 'Urgente',
        'express' => 'Express'
    ];
    return $labels[$priority] ?? $priority;
}

function getPriorityClass($priority) {
    $classes = [
        'normale' => 'priority-normal',
        'urgente' => 'priority-urgent',
        'express' => 'priority-express'
    ];
    return $classes[$priority] ?? 'priority-normal';
}

function getPaymentMethodLabel($method) {
    $labels = [
        'cash' => 'Espèces',
        'orange_money' => 'Orange Money',
        'mtn_money' => 'MTN Money',
        'moov_money' => 'Moov Money',
        'wave' => 'Wave',
        'card' => 'Carte bancaire'
    ];
    return $labels[$method] ?? $method;
}
?>
