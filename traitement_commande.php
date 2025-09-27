<?php
require_once __DIR__ . '/../lib/util.php';
session_start();
require_once 'config_db.php';

// Traitement de la commande
if ($_POST['action'] ?? '' === 'nouvelle_commande') {
    try {
        $pdo = getDBConnection();
        
        // Récupération des données
        $client_nom = trim($_POST['client_nom'] ?? '');
        $client_prenoms = trim($_POST['client_prenoms'] ?? '');
        $client_telephone = trim($_POST['client_telephone'] ?? '');
        // If client session exists, enforce session telephone
        if (!empty($_SESSION['client_telephone'])) {
            $client_telephone = $_SESSION['client_telephone'];
        }
        $ville = trim($_POST['ville'] ?? '');
        $commune = trim($_POST['commune'] ?? '');
        $adresse_complete = trim($_POST['adresse_complete'] ?? '');
        $description_colis = trim($_POST['description_colis'] ?? '');
        $valeur_colis = floatval($_POST['valeur_colis'] ?? 0);
        $instructions_speciales = trim($_POST['instructions_speciales'] ?? '');
        
        // Validation
        if (empty($client_nom) || empty($client_telephone) || empty($ville) || empty($commune) || empty($adresse_complete)) {
            throw new Exception("Veuillez remplir tous les champs obligatoires");
        }
        
        // Générer numéro de commande unique
        $numero_commande = 'CMD' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Calculer tarif de base (simulation)
        $tarif_base = 2000; // Tarif de base
        if ($valeur_colis > 50000) $tarif_base += 1000; // Supplément valeur élevée
        if (in_array(strtolower($ville), ['abidjan', 'grand-bassam', 'bingerville'])) {
            $tarif_livraison = $tarif_base;
        } else {
            $tarif_livraison = $tarif_base + 1500; // Supplément hors Abidjan
        }
        
        // Insertion de la commande
        $stmt = $pdo->prepare("
            INSERT INTO commandes_classiques 
            (numero_commande, client_nom, client_prenoms, client_telephone, ville, commune, 
             adresse_complete, description_colis, valeur_colis, instructions_speciales, tarif_livraison, statut) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nouvelle')
        ");
        
        $stmt->execute([
            $numero_commande, $client_nom, $client_prenoms, $client_telephone, 
            $ville, $commune, $adresse_complete, $description_colis, 
            $valeur_colis, $instructions_speciales, $tarif_livraison
        ]);
        
        // Redirection vers page de confirmation
        header('Location: index.php?success=1&numero=' . $numero_commande . '&tarif=' . $tarif_livraison);
        exit;
        
    } catch (Exception $e) {
        // Redirection vers page d'erreur
        header('Location: index.php?error=1&message=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Redirection si accès direct
    header('Location: index.php');
    exit;
}
?>
