<?php
require_once __DIR__ . '/../lib/util.php';
/**
 * GESTIONNAIRE DE CANDIDATURES - SUZOSKY RECRUTEMENT
 * ================================================
 * Traitement des candidatures reçues via le formulaire de recrutement
 */

header('Content-Type: application/json');

require_once 'config_db.php';

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Récupérer les données du formulaire
    $job_id = intval($_POST['job_id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $prenoms = trim($_POST['prenoms'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? '';
    $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $lieu_residence = trim($_POST['lieu_residence'] ?? '');
    $lettre_motivation = trim($_POST['lettre_motivation'] ?? '');
    
    // Validation des champs requis
    $required_fields = [
        'nom' => $nom,
        'prenoms' => $prenoms,
        'date_naissance' => $date_naissance,
        'lieu_naissance' => $lieu_naissance,
        'telephone' => $telephone,
        'email' => $email,
        'lieu_residence' => $lieu_residence,
        'lettre_motivation' => $lettre_motivation
    ];
    
    foreach ($required_fields as $field => $value) {
        if (empty($value)) {
            echo json_encode(['success' => false, 'message' => "Le champ {$field} est requis"]);
            exit;
        }
    }
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Adresse email invalide']);
        exit;
    }
    
    // Validation de la date de naissance (candidat doit avoir au moins 18 ans)
    $birth_date = new DateTime($date_naissance);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    
    if ($age < 18) {
        echo json_encode(['success' => false, 'message' => 'Vous devez avoir au moins 18 ans pour postuler']);
        exit;
    }
    
    // Créer le répertoire de stockage des candidatures s'il n'existe pas
    $upload_dir = 'uploads/candidatures/' . date('Y/m/');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Traitement du CV (obligatoire)
    $cv_path = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $cv_file = $_FILES['cv'];
        
        // Vérifier le type de fichier
        $allowed_types = ['application/pdf'];
        if (!in_array($cv_file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Le CV doit être au format PDF']);
            exit;
        }
        
        // Vérifier la taille (max 5MB)
        if ($cv_file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Le CV ne doit pas dépasser 5MB']);
            exit;
        }
        
        // Générer un nom de fichier unique
        $cv_filename = 'cv_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $cv_file['name']);
        $cv_path = $upload_dir . $cv_filename;
        
        if (!move_uploaded_file($cv_file['tmp_name'], $cv_path)) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement du CV']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Le CV est obligatoire']);
        exit;
    }
    
    // Traitement des autres documents (optionnels)
    $autres_documents = [];
    if (isset($_FILES['autres_documents']) && is_array($_FILES['autres_documents']['name'])) {
        $allowed_doc_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
        
        foreach ($_FILES['autres_documents']['name'] as $key => $filename) {
            if ($_FILES['autres_documents']['error'][$key] === UPLOAD_ERR_OK) {
                $file_type = $_FILES['autres_documents']['type'][$key];
                $file_size = $_FILES['autres_documents']['size'][$key];
                $tmp_name = $_FILES['autres_documents']['tmp_name'][$key];
                
                // Vérifications
                if (!in_array($file_type, $allowed_doc_types)) {
                    continue; // Ignorer les types non autorisés
                }
                
                if ($file_size > 5 * 1024 * 1024) {
                    continue; // Ignorer les fichiers trop volumineux
                }
                
                // Télécharger le fichier
                $doc_filename = 'doc_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                $doc_path = $upload_dir . $doc_filename;
                
                if (move_uploaded_file($tmp_name, $doc_path)) {
                    $autres_documents[] = $doc_path;
                }
            }
        }
    }
    
    // Insérer la candidature en base de données
    $stmt = $pdo->prepare("
        INSERT INTO candidatures (
            job_id, nom, prenoms, date_naissance, lieu_naissance, 
            telephone, email, lieu_residence, lettre_motivation, 
            cv_path, autres_documents, statut, date_candidature
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
    ");
    
    $result = $stmt->execute([
        $job_id,
        $nom,
        $prenoms,
        $date_naissance,
        $lieu_naissance,
        $telephone,
        $email,
        $lieu_residence,
        $lettre_motivation,
        $cv_path,
        json_encode($autres_documents)
    ]);
    
    if ($result) {
        $candidature_id = $pdo->lastInsertId();
        
        // Envoyer une notification à l'admin (optionnel - peut être implémenté plus tard)
        // sendAdminNotification($candidature_id, $nom, $prenoms, $job_id);
        
        // Log de la candidature
        error_log("Nouvelle candidature reçue - ID: {$candidature_id}, Nom: {$nom} {$prenoms}, Email: {$email}, Job ID: {$job_id}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Candidature envoyée avec succès',
            'candidature_id' => $candidature_id
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la candidature']);
    }
    
} catch (Exception $e) {
    error_log("Erreur candidature: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système. Veuillez réessayer plus tard.']);
}

// Fonction pour créer la table candidatures si elle n'existe pas
function createCandidaturesTable() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS candidatures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        nom VARCHAR(100) NOT NULL,
        prenoms VARCHAR(100) NOT NULL,
        date_naissance DATE NOT NULL,
        lieu_naissance VARCHAR(150) NOT NULL,
        telephone VARCHAR(20) NOT NULL,
        email VARCHAR(150) NOT NULL,
        lieu_residence VARCHAR(200) NOT NULL,
        lettre_motivation TEXT NOT NULL,
        cv_path VARCHAR(300) NOT NULL,
        autres_documents JSON,
        statut ENUM('en_attente', 'accepte', 'refuse', 'archive') DEFAULT 'en_attente',
        date_candidature TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        date_rdv DATETIME NULL,
        commentaire_admin TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_statut (statut),
        INDEX idx_job_id (job_id),
        INDEX idx_email (email),
        INDEX idx_date (date_candidature)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

// Créer la table si nécessaire
try {
    createCandidaturesTable();
} catch (Exception $e) {
    error_log("Erreur création table candidatures: " . $e->getMessage());
}

// Fonction pour envoyer une notification à l'admin (à implémenter si nécessaire)
function sendAdminNotification($candidature_id, $nom, $prenoms, $job_id) {
    // Cette fonction peut être implémentée pour envoyer des emails ou notifications
    // aux administrateurs lors de nouvelles candidatures
    
    // Exemple d'implémentation future :
    // - Email à l'admin
    // - Notification dans le dashboard admin
    // - SMS si nécessaire
    
    error_log("Notification admin: Nouvelle candidature #{$candidature_id} de {$nom} {$prenoms} pour le poste #{$job_id}");
}
?>
