<?php
require_once __DIR__ . '/lib/util.php';
// 🧑‍💼 INTERFACE RECRUTEMENT SUZOSKY - VERSION COMPLÈTE
session_start();

// Configuration base de données - SIMPLIFIÉ
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
} catch(Exception $e) {
    $db_error = "Erreur de connexion à la base de données";
    error_log("Erreur recrutement: " . $e->getMessage());
}


// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_application') {
        // Traitement candidature
        $nom = $_POST['nom'] ?? '';
        $prenoms = $_POST['prenoms'] ?? '';
        $date_naissance = $_POST['date_naissance'] ?? '';
        $lieu_naissance = $_POST['lieu_naissance'] ?? '';
        $telephone = $_POST['telephone'] ?? '';
        $email = $_POST['email'] ?? '';
        $residence = $_POST['residence'] ?? '';
        $lettre_motivation = $_POST['lettre_motivation'] ?? '';
        $poste_id = $_POST['poste_id'] ?? '';
        
        // Upload des fichiers
        $uploaded_files = [];
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === 0) {
            $cv_name = 'CV_' . $nom . '_' . time() . '.' . pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['cv']['tmp_name'], 'uploads/candidatures/' . $cv_name);
            $uploaded_files['cv'] = $cv_name;
        }
        
        // Insertion en base
        try {
            $stmt = $pdo->prepare("INSERT INTO candidatures (poste_id, nom, prenoms, date_naissance, lieu_naissance, telephone, email, residence, lettre_motivation, cv_filename, statut, date_candidature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())");
            $stmt->execute([$poste_id, $nom, $prenoms, $date_naissance, $lieu_naissance, $telephone, $email, $residence, $lettre_motivation, $uploaded_files['cv'] ?? null]);
            
            $success_message = "Votre candidature a été envoyée avec succès ! Vous recevrez une réponse sous 48h.";
        } catch(PDOException $e) {
            $error_message = "Erreur lors de l'envoi de la candidature.";
        }
    }
}

// Récupération des postes disponibles
$postes = [];
    if (isset($pdo)) {
    try {
        // Only show active postings that have not expired
        $stmt = $pdo->query("SELECT * FROM postes WHERE statut = 'actif' AND date_expiration >= CURDATE() ORDER BY date_expiration DESC");
        $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $postes = [];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recrutement Suzosky - Rejoignez notre équipe</title>
    <meta name="description" content="Rejoignez l'équipe Suzosky ! Postes disponibles : coursiers, chauffeurs, agents. Candidature en ligne simple et rapide.">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* 🎨 CHARTE GRAPHIQUE SUZOSKY */
        :root {
            /* Couleurs principales */
            --primary-gold: #D4A853;
            --primary-dark: #1A1A2E;
            --secondary-blue: #16213E;
            --accent-blue: #0F3460;
            --accent-red: #E94560;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --error-color: #dc3545;
            
            /* Effets Glass Morphism */
            --glass-bg: rgba(255,255,255,0.08);
            --glass-border: rgba(255,255,255,0.2);
            --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            
            /* Gradients signatures */
            --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--gradient-dark);
            color: white;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-name {
            font-size: 2rem;
            font-weight: 900;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 2px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-links a {
            color: var(--primary-gold);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            color: white;
            text-shadow: 0 0 10px var(--primary-gold);
        }

        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .page-title h1 {
            font-size: 3rem;
            font-weight: 900;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .page-title p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Sections */
        .section {
            margin-bottom: 60px;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Postes disponibles */
        .postes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .poste-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .poste-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glass-shadow);
            border-color: var(--primary-gold);
        }

        .poste-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .poste-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 5px;
        }

        .poste-voyant {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .voyant-vert { background: var(--success-color); }
        .voyant-orange { background: var(--warning-color); }
        .voyant-rouge { background: var(--error-color); }

        .poste-description {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .poste-details {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--glass-border);
        }

        .poste-details.active {
            display: block;
        }

        .detail-item {
            margin-bottom: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--primary-gold);
            margin-bottom: 5px;
        }

        .detail-value {
            color: rgba(255, 255, 255, 0.9);
        }

        .btn-candidater {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        .btn-candidater:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 168, 83, 0.4);
        }

        /* Formulaire de candidature */
        .formulaire-candidature {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
            margin-top: 40px;
            display: none;
        }

        .formulaire-candidature.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 15px;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            background: var(--glass-bg);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 10px rgba(212, 168, 83, 0.3);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-file {
            background: var(--secondary-blue);
            border: 2px dashed var(--glass-border);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-file:hover {
            border-color: var(--primary-gold);
            background: rgba(212, 168, 83, 0.1);
        }

        .btn-submit {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(212, 168, 83, 0.4);
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 600;
        }

        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .page-title h1 {
                font-size: 2rem;
            }

            .postes-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <?php require_once __DIR__ . '/config.php'; ?>
            <a href="<?= htmlspecialchars(getAppBaseUrl(), ENT_QUOTES) ?>" class="brand-name" style="text-decoration:none;color:inherit;">SUZOSKY</a>
            <nav class="nav-links">
                <!-- Menu vide -->
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Page Title -->
        <div class="page-title">
            <h1><i class="fas fa-users"></i> Rejoignez notre équipe</h1>
            <p>Découvrez nos opportunités d'emploi et postulez en ligne. Suzosky recrute des talents passionnés pour révolutionner la livraison en Côte d'Ivoire.</p>
        </div>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Postes disponibles -->
        <section class="section">
            <h2 class="section-title">
                <i class="fas fa-briefcase"></i>
                Postes disponibles
            </h2>

            <div class="postes-grid">
                <?php if (empty($postes)): ?>
                    <p>Aucun poste disponible pour le moment. Revenez plus tard.</p>
                <?php else: ?>
                    <?php foreach ($postes as $poste): ?>
                        <?php
                        $jours_restants = (strtotime($poste['date_expiration']) - time()) / (60 * 60 * 24);
                        $voyant_class = $jours_restants > 14 ? 'voyant-vert' : ($jours_restants > 7 ? 'voyant-orange' : 'voyant-rouge');
                        ?>
                        <div class="poste-card" onclick="togglePosteDetails(this)" data-poste-id="<?php echo $poste['id']; ?>">
                            <div class="poste-voyant <?php echo $voyant_class; ?>"></div>
                            <div class="poste-header">
                                <div>
                                    <div class="poste-title"><?php echo htmlspecialchars($poste['titre']); ?></div>
                                    <div class="poste-description"><?php echo htmlspecialchars($poste['description_courte'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="poste-details">
                                <div class="detail-item">
                                    <div class="detail-label">Description du poste :</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($poste['description_complete'] ?? ''); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Qualités requises :</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($poste['qualites_requises'] ?? ''); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Expérience :</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($poste['experience_requise'] ?? ''); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Date d'expiration :</div>
                                    <div class="detail-value"><?php echo date('d/m/Y', strtotime($poste['date_expiration'])); ?></div>
                                </div>
                            </div>
                            <button class="btn-candidater" onclick="showCandidatureForm(<?php echo $poste['id']; ?>, '<?php echo htmlspecialchars($poste['titre']); ?>')">
                                <i class="fas fa-paper-plane"></i> Postuler maintenant
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Formulaire de candidature -->
        <section id="formulaire-candidature" class="formulaire-candidature">
            <h2 class="section-title">
                <i class="fas fa-file-alt"></i>
                Formulaire de candidature
            </h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_application">
                <input type="hidden" name="poste_id" id="candidature_poste_id">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="nom">Nom *</label>
                        <input type="text" class="form-input" id="nom" name="nom" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prenoms">Prénoms *</label>
                        <input type="text" class="form-input" id="prenoms" name="prenoms" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="date_naissance">Date de naissance *</label>
                        <input type="date" class="form-input" id="date_naissance" name="date_naissance" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="lieu_naissance">Lieu de naissance *</label>
                        <input type="text" class="form-input" id="lieu_naissance" name="lieu_naissance" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="telephone">Téléphone *</label>
                        <input type="tel" class="form-input" id="telephone" name="telephone" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email *</label>
                        <input type="email" class="form-input" id="email" name="email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="residence">Résidence actuelle *</label>
                    <input type="text" class="form-input" id="residence" name="residence" placeholder="Commune, quartier..." required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="lettre_motivation">Lettre de motivation *</label>
                    <textarea class="form-input form-textarea" id="lettre_motivation" name="lettre_motivation" placeholder="Expliquez-nous pourquoi vous souhaitez rejoindre notre équipe..." required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="cv">CV (PDF, DOC, DOCX) *</label>
                    <input type="file" class="form-input" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Envoyer ma candidature
                </button>
            </form>
        </section>
    </main>

    <script>
        function togglePosteDetails(card) {
            const details = card.querySelector('.poste-details');
            const isActive = details.classList.contains('active');
            
            // Fermer tous les autres détails
            document.querySelectorAll('.poste-details.active').forEach(detail => {
                detail.classList.remove('active');
            });
            
            // Ouvrir/fermer celui-ci
            if (!isActive) {
                details.classList.add('active');
            }
        }

        function showCandidatureForm(posteId, posteTitle) {
            document.getElementById('candidature_poste_id').value = posteId;
            document.getElementById('formulaire-candidature').classList.add('active');
            
            // Scroll vers le formulaire
            document.getElementById('formulaire-candidature').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Validation du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.style.borderColor = 'var(--error-color)';
                            isValid = false;
                        } else {
                            field.style.borderColor = 'var(--glass-border)';
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs obligatoires.');
                    }
                });
            }
        });
    </script>
</body>
</html>
