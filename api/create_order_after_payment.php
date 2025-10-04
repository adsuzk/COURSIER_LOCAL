<?php
/**
 * create_order_after_payment.php
 * Enregistre la commande APRÈS confirmation du paiement CinetPay
 * Cette API est appelée uniquement si le paiement a réussi
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

error_log("[ORDER_AFTER_PAYMENT] === Enregistrement commande après paiement confirmé ===");

try {
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Méthode non autorisée");
    }
    
    // Vérifier l'authentification client
    if (empty($_SESSION['client_id']) && empty($_SESSION['client_telephone'])) {
        throw new Exception("Client non authentifié");
    }
    
    // Récupérer les données du formulaire
    $clientId = $_SESSION['client_id'] ?? null;
    $clientTelephone = $_SESSION['client_telephone'] ?? $_POST['client_phone'] ?? '';
    $clientNom = $_SESSION['client_nom'] ?? $_POST['client_name'] ?? 'Client';
    $clientEmail = $_SESSION['client_email'] ?? $_POST['client_email'] ?? '';
    
    // Données de la course
    $transactionId = $_POST['transaction_id'] ?? '';
    $departure = $_POST['departure'] ?? '';
    $destination = $_POST['destination'] ?? '';
    $latitudeRetrait = floatval($_POST['latitude_retrait'] ?? 0);
    $longitudeRetrait = floatval($_POST['longitude_retrait'] ?? 0);
    $latitudeLivraison = floatval($_POST['latitude_livraison'] ?? 0);
    $longitudeLivraison = floatval($_POST['longitude_livraison'] ?? 0);
    $distance = floatval($_POST['distance'] ?? 0);
    $prixLivraison = floatval($_POST['prix_livraison'] ?? 1500);
    $telephoneDestinataire = $_POST['telephone_destinataire'] ?? '';
    $nomDestinataire = $_POST['nom_destinataire'] ?? '';
    $notesSpeciales = $_POST['notes_speciales'] ?? '';
    $modePaiement = 'cinetpay'; // Paiement en ligne confirmé
    
    // Validation des données essentielles
    if (empty($departure) || empty($destination)) {
        throw new Exception("Adresses de départ et destination requises");
    }
    if (empty($transactionId)) {
        throw new Exception("Identifiant de transaction manquant");
    }

    // Vérifier le statut de paiement auprès de CinetPay avant toute insertion
    require_once __DIR__ . '/../cinetpay/config.php';
    $clientCfg = getClientCinetPayConfig();
    $checkPayload = [
        'apikey' => $clientCfg['apikey'],
        'site_id' => $clientCfg['site_id'],
        'transaction_id' => $transactionId,
    ];
    $ch = curl_init(($clientCfg['check_endpoint'] ?? 'https://api-checkout.cinetpay.com/v2/payment/check'));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) { throw new Exception('Erreur vérification CinetPay: ' . $err); }
    $cp = json_decode($resp, true) ?: [];
    $cpStatus = $cp['data']['status'] ?? ($cp['status'] ?? '');
    if (!in_array($cpStatus, ['ACCEPTED','COMPLETED','SUCCEEDED','SUCCESS'], true)) {
        throw new Exception('Paiement non confirmé: statut=' . $cpStatus);
    }
    
    error_log("[ORDER_AFTER_PAYMENT] Client: $clientNom ($clientTelephone), De: $departure, À: $destination");
    
    // Connexion BDD
    $pdo = getDB();
    
    // Générer le numéro de commande
    $numeroCommande = 'SZK' . time() . rand(100, 999);
    
    // Insérer la commande
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            client_id,
            client_nom,
            client_telephone,
            client_email,
            adresse_depart,
            adresse_destination,
            latitude_retrait,
            longitude_retrait,
            latitude_livraison,
            longitude_livraison,
            distance_km,
            prix_livraison,
            telephone_destinataire,
            nom_destinataire,
            notes_speciales,
            mode_paiement,
            statut_paiement,
            numero_commande,
            statut,
            date_creation,
            transaction_id
        ) VALUES (
            :client_id,
            :client_nom,
            :client_telephone,
            :client_email,
            :adresse_depart,
            :adresse_destination,
            :latitude_retrait,
            :longitude_retrait,
            :latitude_livraison,
            :longitude_livraison,
            :distance_km,
            :prix_livraison,
            :telephone_destinataire,
            :nom_destinataire,
            :notes_speciales,
            :mode_paiement,
            :statut_paiement,
            :numero_commande,
            :statut,
            NOW(),
            :transaction_id
        )
    ");
        INSERT INTO commandes (
            client_id,
            client_nom,
            client_telephone,
            client_email,
            adresse_depart,
            adresse_destination,
            latitude_retrait,
            longitude_retrait,
            latitude_livraison,
            longitude_livraison,
            distance_km,
            prix_livraison,
            telephone_destinataire,
            nom_destinataire,
            notes_speciales,
            mode_paiement,
            statut_paiement,
            numero_commande,
            statut,
            date_creation
        ) VALUES (
            :client_id,
            :client_nom,
            :client_telephone,
            :client_email,
            :adresse_depart,
            :adresse_destination,
            :latitude_retrait,
            :longitude_retrait,
            :latitude_livraison,
            :longitude_livraison,
            :distance_km,
            :prix_livraison,
            :telephone_destinataire,
            :nom_destinataire,
            :notes_speciales,
            :mode_paiement,
            :statut_paiement,
            :numero_commande,
            :statut,
            NOW()
        )
    ");
    
    $result = $stmt->execute([
        ':client_id' => $clientId,
        ':client_nom' => $clientNom,
        ':client_telephone' => $clientTelephone,
        ':client_email' => $clientEmail,
        ':adresse_depart' => $departure,
        ':adresse_destination' => $destination,
        ':latitude_retrait' => $latitudeRetrait,
        ':longitude_retrait' => $longitudeRetrait,
        ':latitude_livraison' => $latitudeLivraison,
        ':longitude_livraison' => $longitudeLivraison,
        ':distance_km' => $distance,
        ':prix_livraison' => $prixLivraison,
        ':telephone_destinataire' => $telephoneDestinataire,
        ':nom_destinataire' => $nomDestinataire,
        ':notes_speciales' => $notesSpeciales,
        ':mode_paiement' => $modePaiement,
        ':statut_paiement' => 'paye', // Paiement confirmé
        ':numero_commande' => $numeroCommande,
        ':statut' => 'nouvelle', // Commande en attente d'assignation
        ':transaction_id' => $transactionId
    ]);
    
    if (!$result) {
        throw new Exception("Erreur lors de l'enregistrement de la commande");
    }
    
    $commandeId = $pdo->lastInsertId();
    error_log("[ORDER_AFTER_PAYMENT] ✅ Commande #$commandeId enregistrée : $numeroCommande");
    
    // Lancer la recherche de coursier automatique
    require_once __DIR__ . '/../attribution_intelligente.php';
    
    try {
        $attribution = assignerCoursierIntelligent($commandeId);
        
        if ($attribution['success']) {
            error_log("[ORDER_AFTER_PAYMENT] ✅ Coursier assigné : " . $attribution['coursier_nom']);
            
            // Notifier le coursier via FCM
            require_once __DIR__ . '/../fcm_manager.php';
            $fcm = new FCMManager();
            $fcm->notifierNouvelleCommande($attribution['coursier_id'], $commandeId);
        } else {
            error_log("[ORDER_AFTER_PAYMENT] ⚠️ Aucun coursier disponible immédiatement");
        }
    } catch (Exception $e) {
        error_log("[ORDER_AFTER_PAYMENT] ⚠️ Erreur attribution: " . $e->getMessage());
    }
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Commande enregistrée avec succès',
        'order_id' => $commandeId,
        'order_number' => $numeroCommande,
        'redirect_url' => '/index.php?order_success=' . $numeroCommande
    ]);
    
} catch (Exception $e) {
    error_log("[ORDER_AFTER_PAYMENT] ❌ ERREUR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
