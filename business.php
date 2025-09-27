<?php
require_once __DIR__ . '/lib/util.php';
/**
 * SUZOSKY BUSINESS - Interface Clients Business
 * Version: 3.0 - Optimisée pour LWS Production
 * Date: Août 2025
 */

// Configuration sécurisée
session_start();
error_reporting(0);
ini_set('display_errors', 0);

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configuration Google Maps API
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A');
define('GOOGLE_PLACES_API_KEY', 'AIzaSyAGKC21fGmY-k6i0dcY8MpBExa5IqqBXbE');

// CONFIGURATION UNIFIÉE - SUPPRESSION DES DOUBLONS DB


// Traitement AJAX pour les opérations business
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDBConnection();
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'upload_business_file':
                // Upload et traitement automatique des fichiers business
                if (!isset($_SESSION['business_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Session expirée']);
                    exit;
                }
                
                $business_id = $_SESSION['business_id'];
                $date_livraison = $_POST['date_livraison'] ?? date('Y-m-d', strtotime('+1 day'));
                
                // Traitement du fichier (simulation)
                if (isset($_FILES['fichier_livraisons']) && $_FILES['fichier_livraisons']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['fichier_livraisons'];
                    $allowed = ['xlsx', 'xls', 'csv'];
                    $filename = $file['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        // Enregistrer le fichier
                        $upload_dir = 'uploads/business/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $new_filename = 'business_' . $business_id . '_' . time() . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            // Insérer le fichier dans la base
                            $stmt = $pdo->prepare("
                                INSERT INTO business_fichiers_livraison 
                                (business_id, nom_original, nom_stockage, taille_fichier, date_upload, statut_traitement) 
                                VALUES (?, ?, ?, ?, NOW(), 'en_traitement')
                            ");
                            $stmt->execute([$business_id, $filename, $new_filename, $file['size']]);
                            $fichier_id = $pdo->lastInsertId();
                            
                            // Traitement automatique du fichier (simulation)
                            $livraisons = processBusinessFile($upload_path, $fichier_id, $date_livraison);
                            
                            if (!empty($livraisons)) {
                                // Affecter automatiquement les coursiers
                                $assigned_count = autoAssignCouriers($livraisons, $pdo);
                                
                                // Mettre à jour le statut du fichier
                                $stmt = $pdo->prepare("
                                    UPDATE business_fichiers_livraison 
                                    SET statut_traitement = 'traite', nombre_lignes = ?
                                    WHERE id_fichier = ?
                                ");
                                $stmt->execute([count($livraisons), $fichier_id]);
                                
                                echo json_encode([
                                    'success' => true, 
                                    'message' => count($livraisons) . ' livraisons créées, ' . $assigned_count . ' assignées automatiquement',
                                    'total_livraisons' => count($livraisons),
                                    'assigned_count' => $assigned_count
                                ]);
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Aucune livraison valide trouvée dans le fichier']);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Format de fichier non supporté']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
                }
                exit;
                
            case 'get_business_stats':
                // Statistiques business en temps réel
                if (!isset($_SESSION['business_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Session expirée']);
                    exit;
                }
                
                $business_id = $_SESSION['business_id'];
                
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_livraisons,
                        COUNT(CASE WHEN bl.statut = 'nouvelle' THEN 1 END) as nouvelles,
                        COUNT(CASE WHEN bl.statut = 'affectee_auto' THEN 1 END) as affectees_auto,
                        COUNT(CASE WHEN bl.statut = 'en_cours' THEN 1 END) as en_cours,
                        COUNT(CASE WHEN bl.statut = 'livree' THEN 1 END) as livrees,
                        SUM(bl.tarif_livraison) as ca_total,
                        SUM(CASE WHEN bl.statut = 'livree' THEN bl.tarif_livraison ELSE 0 END) as ca_realise,
                        AVG(CASE WHEN bl.statut = 'livree' THEN 
                            TIMESTAMPDIFF(MINUTE, bl.date_assignation, bl.date_livraison_reelle) 
                        END) as temps_moyen_livraison
                    FROM business_livraisons bl
                    JOIN business_fichiers_livraison bfl ON bl.fichier_id = bfl.id_fichier
                    WHERE bfl.business_id = ?
                    AND DATE(bl.date_creation) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ");
                
                $stmt->execute([$business_id]);
                $stats = $stmt->fetch();
                
                echo json_encode(['success' => true, 'data' => $stats]);
                exit;
                
            case 'get_couriers_map':
                // Positions des coursiers disponibles
                $pickup_lat = floatval($_POST['pickup_lat'] ?? 0);
                $pickup_lng = floatval($_POST['pickup_lng'] ?? 0);
                $radius = floatval($_POST['radius'] ?? 10); // km
                
                $stmt = $pdo->prepare("
                    SELECT id_coursier, nom, prenoms, telephone, latitude, longitude,
                           statut_connexion, derniere_position,
                           (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                           cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                           sin(radians(latitude)))) AS distance
                    FROM coursiers 
                    WHERE statut = 'actif' 
                    AND latitude IS NOT NULL 
                    AND longitude IS NOT NULL
                    AND derniere_position >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    HAVING distance <= ?
                    ORDER BY distance ASC
                ");
                
                $stmt->execute([$pickup_lat, $pickup_lng, $pickup_lat, $radius]);
                $coursiers = $stmt->fetchAll();
                
                echo json_encode($coursiers);
                exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        exit;
    }
}

// Fonction de traitement automatique des fichiers business
function processBusinessFile($file_path, $fichier_id, $date_livraison) {
    // Simulation du traitement Excel/CSV
    // Dans la vraie implémentation, utiliser PhpSpreadsheet ou similar
    
    $livraisons = [
        [
            'nom_client' => 'CLIENT DEMO 1',
            'telephone_client' => '+225 01 23 45 67 89',
            'email_client' => 'client1@demo.com',
            'adresse_prise_en_charge' => 'Plateau, Abidjan',
            'adresse_livraison' => 'Cocody Riviera, Abidjan',
            'description_colis' => 'Colis business urgent',
            'tarif_livraison' => 5000.00
        ],
        // Ajouter plus de livraisons de démonstration...
    ];
    
    return $livraisons;
}

// Fonction d'affectation automatique des coursiers
function autoAssignCouriers($livraisons, $pdo) {
    $assigned_count = 0;
    
    foreach ($livraisons as $livraison) {
        // Géocoder l'adresse de pickup
        $coords = geocodeAddress($livraison['adresse_prise_en_charge']);
        
        if ($coords['lat'] && $coords['lng']) {
            // Trouver le coursier le plus proche
            $nearest_courier = assignNearestCourier($coords['lat'], $coords['lng']);
            
            if ($nearest_courier) {
                // Insérer la livraison avec affectation automatique
                $stmt = $pdo->prepare("
                    INSERT INTO business_livraisons 
                    (fichier_id, nom_client, telephone_client, email_client, 
                     adresse_prise_en_charge, adresse_livraison, description_colis, 
                     tarif_livraison, coursier_id, statut, date_assignation, 
                     latitude_pickup, longitude_pickup, date_livraison_prevue) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'affectee_auto', NOW(), ?, ?, ?)
                ");
                
                $stmt->execute([
                    $livraison['fichier_id'],
                    $livraison['nom_client'],
                    $livraison['telephone_client'],
                    $livraison['email_client'],
                    $livraison['adresse_prise_en_charge'],
                    $livraison['adresse_livraison'],
                    $livraison['description_colis'],
                    $livraison['tarif_livraison'],
                    $nearest_courier['id_coursier'],
                    $coords['lat'],
                    $coords['lng'],
                    date('Y-m-d')
                ]);
                // Envoi de notification FCM au coursier assigné
                try {
                    $newId = $pdo->lastInsertId();
                    require_once __DIR__ . '/api/lib/fcm_enhanced.php';
                    $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $stTok = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC");
                    $stTok->execute([(int)$nearest_courier['id_coursier']]);
                    $tokens = array_column($stTok->fetchAll(PDO::FETCH_ASSOC), 'token');
                    if (!empty($tokens)) {
                        fcm_send_with_log(
                            $tokens,
                            'Nouvelle livraison business',
                            'Une livraison vous a été affectée',
                            [
                                'type' => 'business_new',
                                'business_livraison_id' => (int)$newId
                            ],
                            (int)$nearest_courier['id_coursier'],
                            (int)$newId
                        );
                    }
                } catch (Throwable $e) { /* ne bloque pas le flux */ }
                $assigned_count++;
            }
        }
    }
    
    return $assigned_count;
}

// Fonction de géocodage d'adresse (réutilisée de coursier.php)
function geocodeAddress($address) {
    // Implémentation simplifiée - dans la vraie version, utiliser Google Maps API
    return ['lat' => 5.3600 + (rand(-100, 100) / 10000), 'lng' => -4.0083 + (rand(-100, 100) / 10000)];
}

// Fonction pour trouver le coursier le plus proche (réutilisée de coursier.php)
function assignNearestCourier($pickup_lat, $pickup_lng) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT id_coursier, nom, prenoms, latitude, longitude,
                   (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                   cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                   sin(radians(latitude)))) AS distance
            FROM coursiers 
            WHERE statut = 'actif' 
            AND statut_connexion = 'en_ligne'
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
            AND derniere_position >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            HAVING distance <= 10
            ORDER BY distance ASC
            LIMIT 1
        ");
        
        $stmt->execute([$pickup_lat, $pickup_lng, $pickup_lat]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        return null;
    }
}

// Traitement du formulaire de contact business
if ($_POST['action'] ?? '' === 'contact_business') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $solution = $_POST['solution'] ?? '';
        $message = trim($_POST['message'] ?? '');
        
        if (!$email || !$company || !$phone) {
            $response['message'] = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            $pdo = getDBConnection();
            if ($pdo) {
                // Créer la table si elle n'existe pas
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS business_leads (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(100) NOT NULL,
                        company VARCHAR(200) NOT NULL,
                        phone VARCHAR(20) NOT NULL,
                        solution VARCHAR(50) NOT NULL,
                        message TEXT,
                        status ENUM('nouveau', 'contacte', 'converti') DEFAULT 'nouveau',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_email (email),
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB
                ");
                
                // Insérer le nouveau lead
                $stmt = $pdo->prepare("
                    INSERT INTO business_leads (email, company, phone, solution, message) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$email, $company, $phone, $solution, $message]);
                
                $response['success'] = true;
                $response['message'] = 'Merci pour votre demande ! Nous vous contacterons sous 24h.';
            } else {
                $response['message'] = 'Erreur technique. Veuillez réessayer.';
            }
        }
    } catch (Exception $e) {
        error_log("Business Contact Error: " . $e->getMessage());
        $response['message'] = 'Erreur lors de l\'envoi. Veuillez réessayer.';
    }
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Récupérer les statistiques business
$stats = ['total_companies' => 0, 'total_deliveries' => 0, 'avg_rating' => 4.8];
try {
    $pdo = getDBConnection();
    if ($pdo) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM business_leads WHERE status = 'converti'");
        $stats['total_companies'] = $stmt->fetchColumn() ?: 0;
        
        // Simulated stats for demo
        $stats['total_deliveries'] = $stats['total_companies'] * 150;
    }
} catch (Exception $e) {
    error_log("Stats Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solutions Business - Suzosky</title>
    <meta name="description" content="Solutions de livraison professionnelles pour entreprises. Tarifs préférentiels, API, tableau de bord dédié.">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .logo {
            font-size: 1.8em;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 80px;
            text-align: center;
        }

        .hero-title {
            font-size: 3.5em;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.3em;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .hero-features {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .hero-feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1em;
        }

        .hero-feature i {
            font-size: 1.5em;
            color: #28a745;
        }

        /* Stats Section */
        .stats-section {
            background: #f8f9fa;
            padding: 60px 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1em;
            color: #6c757d;
        }

        /* Solutions Grid */
        .solutions-section {
            padding: 80px 0;
            background: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.2em;
            color: #6c757d;
            margin-bottom: 60px;
        }

        .solutions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
        }

        .solution-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 20px;
            padding: 40px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .solution-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .solution-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
        }

        .solution-icon i {
            font-size: 2em;
            color: white;
        }

        .solution-title {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .solution-description {
            color: #6c757d;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .solution-features {
            list-style: none;
            margin-bottom: 30px;
        }

        .solution-features li {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #495057;
        }

        .solution-features i {
            color: #28a745;
            margin-right: 10px;
            width: 16px;
        }

        /* Pricing Section */
        .pricing-section {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .pricing-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            position: relative;
            transition: all 0.3s ease;
        }

        .pricing-card.featured {
            border-color: #667eea;
            transform: scale(1.05);
        }

        .pricing-card.featured::before {
            content: 'RECOMMANDÉ';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #667eea;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .pricing-title {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .pricing-price {
            font-size: 3em;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
        }

        .pricing-period {
            color: #6c757d;
            margin-bottom: 30px;
        }

        .pricing-features {
            list-style: none;
            margin-bottom: 30px;
            text-align: left;
        }

        .pricing-features li {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #495057;
        }

        .pricing-features i {
            color: #28a745;
            margin-right: 10px;
            width: 16px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            font-size: 1em;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .btn-white {
            background: white;
            color: #667eea;
        }

        .btn-white:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .btn-full {
            width: 100%;
            margin-bottom: 15px;
        }

        /* Contact Section */
        .contact-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        .contact-form {
            max-width: 600px;
            margin: 40px auto 0;
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: rgba(255,255,255,0.9);
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-message {
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-weight: 500;
        }

        .form-message.success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }

        .form-message.error {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }

        /* Footer */
        .footer {
            background: #343a40;
            color: white;
            padding: 40px 0;
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .footer-links a:hover {
            opacity: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5em;
            }
            
            .hero-features {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
            
            .nav-links {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .solutions-grid {
                grid-template-columns: 1fr;
            }
            
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            
            .pricing-card.featured {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <div class="logo">🚀 Suzosky Business</div>
            <ul class="nav-links">
                <li><a href="index.html">Accueil</a></li>
                <li><a href="#solutions">Solutions</a></li>
                <li><a href="#pricing">Tarifs</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="admin.php">Admin</a></li>
            </ul>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1 class="hero-title">Solutions de Livraison pour Entreprises</h1>
            <p class="hero-subtitle">
                Optimisez vos livraisons avec nos solutions professionnelles. Tarifs préférentiels, API d'intégration et support dédié.
            </p>
            
            <div class="hero-features">
                <div class="hero-feature">
                    <i class="fas fa-bolt"></i>
                    <span>Livraisons Express</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-chart-line"></i>
                    <span>Tableau de Bord</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-code"></i>
                    <span>API Intégrée</span>
                </div>
                <div class="hero-feature">
                    <i class="fas fa-headset"></i>
                    <span>Support 24/7</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_companies']) ?>+</div>
                <div class="stat-label">Entreprises Partenaires</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_deliveries']) ?>+</div>
                <div class="stat-label">Livraisons Réalisées</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['avg_rating'] ?>/5</div>
                <div class="stat-label">Satisfaction Client</div>
            </div>
        </div>
    </section>

    <!-- Solutions Section -->
    <section id="solutions" class="solutions-section">
        <div class="container">
            <h2 class="section-title">Nos Solutions Business</h2>
            <p class="section-subtitle">
                Des solutions adaptées à chaque type d'entreprise, de la startup à la multinationale.
            </p>
            
            <div class="solutions-grid">
                <div class="solution-card">
                    <div class="solution-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="solution-title">PME & Startups</h3>
                    <p class="solution-description">
                        Solution parfaite pour les petites et moyennes entreprises qui souhaitent optimiser leurs livraisons.
                    </p>
                    <ul class="solution-features">
                        <li><i class="fas fa-check"></i> Jusqu'à 50 livraisons/mois</li>
                        <li><i class="fas fa-check"></i> Tarifs préférentiels</li>
                        <li><i class="fas fa-check"></i> Tableau de bord en ligne</li>
                        <li><i class="fas fa-check"></i> Facturation mensuelle</li>
                        <li><i class="fas fa-check"></i> Support par email</li>
                    </ul>
                    <a href="#contact" class="btn btn-primary btn-full">En savoir plus</a>
                </div>

                <div class="solution-card">
                    <div class="solution-icon">
                        <i class="fas fa-industry"></i>
                    </div>
                    <h3 class="solution-title">Grandes Entreprises</h3>
                    <p class="solution-description">
                        Solution complète pour les grandes entreprises avec besoins logistiques importants.
                    </p>
                    <ul class="solution-features">
                        <li><i class="fas fa-check"></i> Livraisons illimitées</li>
                        <li><i class="fas fa-check"></i> Remises jusqu'à 30%</li>
                        <li><i class="fas fa-check"></i> API d'intégration</li>
                        <li><i class="fas fa-check"></i> Reporting avancé</li>
                        <li><i class="fas fa-check"></i> Account manager dédié</li>
                    </ul>
                    <a href="#contact" class="btn btn-primary btn-full">Demander un devis</a>
                </div>

                <div class="solution-card">
                    <div class="solution-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="solution-title">E-commerce</h3>
                    <p class="solution-description">
                        Intégrez facilement nos services à votre boutique en ligne pour automatiser vos livraisons.
                    </p>
                    <ul class="solution-features">
                        <li><i class="fas fa-check"></i> Plugin WooCommerce</li>
                        <li><i class="fas fa-check"></i> Intégration Shopify</li>
                        <li><i class="fas fa-check"></i> Suivi automatique</li>
                        <li><i class="fas fa-check"></i> Notifications clients</li>
                        <li><i class="fas fa-check"></i> Retours gérés</li>
                    </ul>
                    <a href="#contact" class="btn btn-primary btn-full">Découvrir</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing-section">
        <div class="container">
            <h2 class="section-title">Tarifs Transparents</h2>
            <p class="section-subtitle">
                Choisissez la formule qui correspond à vos besoins. Pas de frais cachés, pas d'engagement.
            </p>
            
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3 class="pricing-title">Starter</h3>
                    <div class="pricing-price">49€</div>
                    <div class="pricing-period">par mois</div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Jusqu'à 20 livraisons/mois</li>
                        <li><i class="fas fa-check"></i> Tableau de bord basique</li>
                        <li><i class="fas fa-check"></i> Support email</li>
                        <li><i class="fas fa-check"></i> Facture mensuelle</li>
                    </ul>
                    <a href="#contact" class="btn btn-secondary btn-full">Commencer</a>
                </div>

                <div class="pricing-card featured">
                    <h3 class="pricing-title">Professional</h3>
                    <div class="pricing-price">149€</div>
                    <div class="pricing-period">par mois</div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Jusqu'à 100 livraisons/mois</li>
                        <li><i class="fas fa-check"></i> Tableau de bord avancé</li>
                        <li><i class="fas fa-check"></i> API d'intégration</li>
                        <li><i class="fas fa-check"></i> Support prioritaire</li>
                        <li><i class="fas fa-check"></i> Rapports détaillés</li>
                    </ul>
                    <a href="#contact" class="btn btn-primary btn-full">Choisir Pro</a>
                </div>

                <div class="pricing-card">
                    <h3 class="pricing-title">Enterprise</h3>
                    <div class="pricing-price">Sur devis</div>
                    <div class="pricing-period">solution personnalisée</div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i> Livraisons illimitées</li>
                        <li><i class="fas fa-check"></i> Solution sur-mesure</li>
                        <li><i class="fas fa-check"></i> Account manager dédié</li>
                        <li><i class="fas fa-check"></i> SLA garantie</li>
                        <li><i class="fas fa-check"></i> Formation équipe</li>
                    </ul>
                    <a href="#contact" class="btn btn-secondary btn-full">Nous contacter</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <h2 class="section-title">Démarrez Votre Projet</h2>
            <p class="section-subtitle">
                Parlons de vos besoins et trouvons ensemble la solution idéale pour votre entreprise.
            </p>
            
            <form class="contact-form" id="businessContactForm">
                <div class="form-grid">
                    <div class="form-group">
                        <input type="email" name="email" class="form-input" placeholder="Email professionnel *" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="company" class="form-input" placeholder="Nom de l'entreprise *" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <input type="tel" name="phone" class="form-input" placeholder="Téléphone *" required>
                    </div>
                    <div class="form-group">
                        <select name="solution" class="form-select" required>
                            <option value="">Type de solution recherchée *</option>
                            <option value="starter">Starter (PME)</option>
                            <option value="professional">Professional</option>
                            <option value="enterprise">Enterprise</option>
                            <option value="ecommerce">E-commerce</option>
                            <option value="custom">Solution personnalisée</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <textarea name="message" class="form-textarea" placeholder="Décrivez vos besoins (optionnel)"></textarea>
                </div>
                
                <button type="submit" class="btn btn-white btn-full">
                    <i class="fas fa-paper-plane"></i>
                    Envoyer la demande
                </button>
                
                <div id="contactMessage" class="form-message" style="display: none;"></div>
            </form>
            
            <div style="margin-top: 40px;">
                <p>Ou contactez-nous directement :</p>
                <a href="mailto:business@suzosky.com" class="btn btn-white" style="margin-top: 15px;">
                    <i class="fas fa-envelope"></i>
                    business@suzosky.com
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <a href="index.html">Accueil</a>
                <a href="#solutions">Solutions</a>
                <a href="#pricing">Tarifs</a>
                <a href="recrutement.html">Recrutement</a>
                <a href="#contact">Contact</a>
                <a href="admin.php">Administration</a>
            </div>
            <p>&copy; 2025 Suzosky Business Solutions. Tous droits réservés.</p>
        </div>
    </footer>

    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Business contact form handling
        document.getElementById('businessContactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'contact_business');
            formData.append('ajax', '1');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const messageDiv = document.getElementById('contactMessage');
            
            // Loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
            submitBtn.disabled = true;
            
            fetch('business.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.textContent = data.message;
                messageDiv.className = 'form-message ' + (data.success ? 'success' : 'error');
                messageDiv.style.display = 'block';
                
                if (data.success) {
                    this.reset();
                }
            })
            .catch(error => {
                messageDiv.textContent = 'Erreur de connexion. Veuillez réessayer.';
                messageDiv.className = 'form-message error';
                messageDiv.style.display = 'block';
            })
            .finally(() => {
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer la demande';
                submitBtn.disabled = false;
            });
        });

        // Animation counter for stats
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
                const increment = target / 100;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    
                    if (counter.textContent.includes('+')) {
                        counter.textContent = Math.floor(current).toLocaleString() + '+';
                    } else if (counter.textContent.includes('/5')) {
                        counter.textContent = (current / 1000).toFixed(1) + '/5';
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 20);
            });
        }

        // Trigger counter animation when stats section is visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        });

        observer.observe(document.querySelector('.stats-section'));
    </script>
</body>
</html>
