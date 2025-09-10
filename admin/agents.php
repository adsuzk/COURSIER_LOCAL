<?php
// Server-side listing of agents selon charte Suzosky
require_once __DIR__ . '/../config.php';
$pdo = getPDO();
// Récupérer la liste des agents, avec fallback si table manquante en local
try {
    $agents = $pdo->query("SELECT * FROM agents_suzosky ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table absente ou autre erreur, retourner liste vide
    $agents = [];
}

// Séparer les agents par type de poste
$coursiers = array_filter($agents, function($agent) {
    return in_array($agent['type_poste'] ?? '', ['coursier_moto', 'coursier_velo', 'coursier']);
});
$concierges = array_filter($agents, function($agent) {
    return in_array($agent['type_poste'] ?? '', ['concierge', 'conciergerie']);
});

// Generate and update a new password for an agent (AJAX)
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'generate_password' && is_numeric($_GET['id'])) {
    $newPwd = generatePassword();
    $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
    // Mise à jour du mot de passe haché et stockage du mot de passe en clair
    $stmt = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = ? WHERE id = ?");
    $stmt->execute([$hashed, $newPwd, (int)$_GET['id']]);
    // Journal automatique : log réinitialisation mot de passe agent
    getJournal()->logMaxDetail(
        'AGENT_PASSWORD_RESET',
        "Mot de passe réinitialisé pour l'agent #{$_GET['id']}",
        ['agent_id' => (int)$_GET['id'], 'new_password' => $newPwd]
    );
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'password' => $newPwd, 'hashed' => $hashed]);
    exit;
}

// JSON endpoint for real-time agent listing (without action)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true' && !isset($_GET['action'])) {
    // Journal automatique : log chargement liste agents
    getJournal()->logMaxDetail(
        'AGENTS_LIST_LOADED',
        'Liste des agents récupérée via AJAX',
        []
    );
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($agents);
    exit;
}

// View individual agent profile
if (isset($_GET['view_agent']) && is_numeric($_GET['view_agent'])) {
    $id = (int)$_GET['view_agent'];
    $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE id = ?");
    $stmt->execute([$id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($agent) {
        ?>
        <style>
        /* === PROFIL AGENT - DESIGN SYSTEM SUZOSKY === */
        .agent-profile-hero {
            background: var(--glass-bg);
            padding: 20px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .detail-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-gold);
        }

        .detail-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(212, 168, 83, 0.2);
            border-color: var(--primary-gold);
        }

        .detail-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }

        .detail-title {
            color: var(--primary-gold);
            font-size: 1.1rem;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }

        .detail-value {
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .password-section {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
        }

        .password-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, #E94560 0%, #FF6B9D 100%);
        }

        .password-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .password-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #E94560 0%, #FF6B9D 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .password-title {
            color: #E94560;
            font-size: 1.3rem;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }

        .password-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .password-field {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 15px;
        }

        .password-field-label {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .password-field-value {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            font-weight: 500;
            word-break: break-all;
        }

        .password-plain {
            color: var(--primary-gold) !important;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .password-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-generate-password {
            background: linear-gradient(135deg, #27AE60 0%, #2ECC71 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }

        .btn-generate-password:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-generate-password:active {
            transform: translateY(0);
        }

        .profile-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: center;
        }

        .btn-back {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border: none;
            padding: 15px 25px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
        }

        .btn-back:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(212, 168, 83, 0.4);
            text-decoration: none;
            color: var(--primary-dark);
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-details-grid {
                grid-template-columns: 1fr;
            }
            
            .password-fields {
                grid-template-columns: 1fr;
            }
            
            .profile-actions {
                flex-direction: column;
            }
        }
        </style>

        <!-- Hero Section Profil Agent -->
        <div class="agent-profile-hero">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($agent['prenoms'] ?? 'A', 0, 1) . substr($agent['nom'] ?? 'G', 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h1><i class="fas fa-user-circle"></i> <?= htmlspecialchars(($agent['prenoms'] ?? '') . ' ' . ($agent['nom'] ?? '')) ?></h1>
                    <div class="profile-matricule">
                        <i class="fas fa-id-badge"></i> Matricule: <?= htmlspecialchars($agent['matricule']) ?>
                    </div>
                </div>
            </div>

            <!-- Grille des détails -->
            <div class="profile-details-grid">
                <div class="detail-card">
                    <div class="detail-card-header">
                        <div class="detail-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="detail-title">Téléphone</div>
                    </div>
                    <div class="detail-value"><?= htmlspecialchars($agent['telephone'] ?? 'Non renseigné') ?></div>
                </div>

                <div class="detail-card">
                    <div class="detail-card-header">
                        <div class="detail-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="detail-title">Date de création</div>
                    </div>
                    <div class="detail-value"><?= date('d/m/Y à H:i', strtotime($agent['created_at'] ?? 'now')) ?></div>
                </div>

                <div class="detail-card">
                    <div class="detail-card-header">
                        <div class="detail-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="detail-title">Type de poste</div>
                    </div>
                    <div class="detail-value"><?= ucfirst(htmlspecialchars($agent['type_poste'] ?? 'Non défini')) ?></div>
                </div>

                <div class="detail-card">
                    <div class="detail-card-header">
                        <div class="detail-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="detail-title">Lieu de résidence</div>
                    </div>
                    <div class="detail-value"><?= htmlspecialchars($agent['lieu_residence'] ?? 'Non renseigné') ?></div>
                </div>
            </div>

            <!-- Section mot de passe -->
            <div class="password-section">
                <div class="password-header">
                    <div class="password-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="password-title">Gestion des mots de passe</div>
                </div>
                <div class="password-fields">
                    <div class="password-field">
                        <div class="password-field-label">Mot de passe (haché)</div>
                        <div class="password-field-value" id="hashed-password">
                            <?= !empty($agent['password']) ? htmlspecialchars($agent['password']) : 'Aucun mot de passe défini' ?>
                        </div>
                    </div>
                    <div class="password-field">
                        <div class="password-field-label">Mot de passe (clair)</div>
                        <div class="password-field-value password-plain" id="plain-password">Non généré</div>
                    </div>
                </div>

                <div class="password-actions">
                    <button id="btn-generate-password" class="btn-generate-password">
                        <i class="fas fa-sync-alt"></i> Générer nouveau mot de passe
                    </button>
                    <span style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">
                        <i class="fas fa-info-circle"></i> Mots de passe de 5 caractères
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="profile-actions">
                <a href="?section=agents" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
            </div>
        </div>

    <script>
    // ID de l'agent courant pour chargement en AJAX
    const currentAgentId = <?= json_encode($agent['id']) ?>;
            document.addEventListener('DOMContentLoaded', function() {
                // Animation d'entrée progressive des cartes
                const cards = document.querySelectorAll('.detail-card');
                cards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        card.style.transition = 'all 0.6s cubic-bezier(0, 0, 0.2, 1)';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 150);
                });

                // Gestion du bouton de génération de mot de passe
                document.getElementById('btn-generate-password').addEventListener('click', function(){
                    const button = this;
                    const originalText = button.innerHTML;
                    
                    // Animation du bouton pendant le chargement
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération...';
                    button.disabled = true;
                    
                    // AJAX: générer nouveau mot de passe via endpoint dédié
                    // AJAX: générer un nouveau mot de passe via admin.php
                    // Appel AJAX vers admin.php pour générer et récupérer JSON
                    // Appel AJAX vers le script admin/agents.php pour générer le mot de passe
                    fetch('admin/agents.php?action=generate_password&id=' + currentAgentId + '&ajax=true')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Mise à jour du mot de passe clair
                                const plainField = document.getElementById('plain-password');
                                plainField.style.transform = 'scale(1.1)';
                                plainField.style.color = '#27AE60';
                                plainField.innerText = data.password;
                                // Mise à jour du mot de passe haché
                                const hashedField = document.getElementById('hashed-password');
                                if (hashedField && data.hashed) {
                                    hashedField.innerText = data.hashed;
                                }
                                // Animation de retour
                                setTimeout(() => {
                                    plainField.style.transform = 'scale(1)';
                                    plainField.style.color = 'var(--primary-gold)';
                                }, 300);
                                // Message de succès
                                showSuccessMessage('Mot de passe généré avec succès !');
                            } else {
                                showErrorMessage('Erreur lors de la génération du mot de passe');
                            }
                        })
                        .catch(() => {
                            showErrorMessage('Erreur de connexion réseau');
                        })
                        .finally(() => {
                            // Restaurer le bouton
                            setTimeout(() => {
                                button.innerHTML = originalText;
                                button.disabled = false;
                            }, 1000);
                        });
                });

                // Fonctions de notification
                function showSuccessMessage(message) {
                    createNotification(message, 'success');
                }

                function showErrorMessage(message) {
                    createNotification(message, 'error');
                }

                function createNotification(message, type) {
                    const notification = document.createElement('div');
                    notification.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: ${type === 'success' ? 'linear-gradient(135deg, #27AE60, #2ECC71)' : 'linear-gradient(135deg, #E94560, #FF6B9D)'};
                        color: white;
                        padding: 15px 20px;
                        border-radius: 10px;
                        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                        z-index: 10000;
                        font-family: 'Montserrat', sans-serif;
                        font-weight: 600;
                        transform: translateX(100%);
                        transition: transform 0.3s ease;
                    `;
                    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
                    
                    document.body.appendChild(notification);
                    
                    // Animation d'entrée
                    setTimeout(() => {
                        notification.style.transform = 'translateX(0)';
                    }, 100);
                    
                    // Suppression automatique
                    setTimeout(() => {
                        notification.style.transform = 'translateX(100%)';
                        setTimeout(() => {
                            document.body.removeChild(notification);
                        }, 300);
                    }, 3000);
                }
            });
        </script>
        <?php
    } else {
        ?>
        <div class="agent-profile-hero">
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 4rem; color: var(--accent-red); margin-bottom: 20px;">
                    <i class="fas fa-user-slash"></i>
                </div>
                <h2 style="color: var(--accent-red); margin-bottom: 15px;">Agent introuvable</h2>
                <p style="color: rgba(255,255,255,0.7); margin-bottom: 30px;">L'agent demandé n'existe pas ou a été supprimé.</p>
                <a href="?section=agents" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
            </div>
        </div>
        <?php
    }
    return;
}

// Statistiques
$totalAgents = count($agents);
$totalCoursiers = count($coursiers);
$totalConcierges = count($concierges);
$newAgentsThisMonth = count(array_filter($agents, fn($a) => isset($a['created_at']) && date('Y-m', strtotime($a['created_at'])) === date('Y-m')));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Suzosky</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* === VARIABLES CSS SUZOSKY === */
        :root {
            /* COULEURS OFFICIELLES */
            --primary-gold: #D4A853;
            --primary-dark: #1A1A2E;
            --secondary-dark: #16213E;
            --accent-blue: #0F3460;
            --success-green: #27AE60;
            --warning-orange: #FFC107;
            --danger-red: #E94560;
            --info-blue: #3B82F6;
            
            /* DÉGRADÉS */
            --gradient-gold: linear-gradient(135deg, #D4A853 0%, #E8C468 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            --gradient-deep: linear-gradient(135deg, #0F3460 0%, #1A1A2E 100%);
            
            /* GLASS MORPHISM */
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            --glass-blur: blur(20px);
            
            /* OMBRES ET LUEURS */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
            --glow-gold: 0 0 20px rgba(212, 168, 83, 0.3);
            --glow-gold-strong: 0 0 40px rgba(212, 168, 83, 0.5);
            --shadow-gold: 0 8px 25px rgba(212, 168, 83, 0.3);
            
            /* ESPACEMENTS */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;
            --space-16: 4rem;
            --space-20: 5rem;
            
            /* TRANSITIONS */
            --duration-fast: 0.15s;
            --duration-normal: 0.3s;
            --duration-slow: 0.5s;
            --ease-standard: cubic-bezier(0.4, 0, 0.2, 1);
            --ease-enter: cubic-bezier(0, 0, 0.2, 1);
            --ease-exit: cubic-bezier(0.4, 0, 1, 1);
        }

        /* === RESET ET BASE === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            font-weight: 400;
            line-height: 1.6;
            background: var(--gradient-dark);
            color: #FFFFFF;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* === SIDEBAR SUZOSKY === */
        .sidebar {
            width: 300px;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-right: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 20;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 3px;
            background: var(--gradient-gold);
            opacity: 0.6;
        }

        /* === SIDEBAR HEADER === */
        .sidebar-header {
            padding: var(--space-8);
            border-bottom: 1px solid var(--glass-border);
            text-align: center;
            background: rgba(212, 168, 83, 0.05);
        }

        .admin-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-4);
            background: var(--gradient-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-gold);
            animation: pulse 3s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: var(--shadow-gold);
                transform: scale(1);
            }
            50% {
                box-shadow: var(--glow-gold-strong);
                transform: scale(1.05);
            }
        }

        .admin-logo i {
            font-size: 2.5rem;
            color: var(--primary-dark);
        }

        .sidebar-title {
            color: var(--primary-gold);
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: var(--space-2);
            text-shadow: 0 0 15px rgba(212, 168, 83, 0.3);
        }

        .sidebar-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* === NAVIGATION MENU === */
        .sidebar-nav {
            flex: 1;
            padding: var(--space-6) 0;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: var(--space-6);
        }

        .nav-section-title {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0 var(--space-6) var(--space-3);
            margin-bottom: var(--space-3);
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: var(--space-4) var(--space-6);
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all var(--duration-normal) var(--ease-standard);
            position: relative;
            border-left: 3px solid transparent;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: var(--gradient-gold);
            transition: width var(--duration-normal) var(--ease-standard);
        }

        .menu-item:hover, .menu-item.active {
            color: var(--primary-gold);
            background: rgba(212, 168, 83, 0.1);
            border-left-color: var(--primary-gold);
            transform: translateX(8px);
            text-shadow: 0 0 10px rgba(212, 168, 83, 0.3);
            text-decoration: none;
        }

        .menu-item:hover::before, .menu-item.active::before {
            width: 4px;
        }

        .menu-item i {
            margin-right: var(--space-4);
            width: 24px;
            text-align: center;
            font-size: 1.2rem;
            color: var(--primary-gold);
            transition: all var(--duration-normal) var(--ease-standard);
        }

        .menu-item:hover i, .menu-item.active i {
            transform: scale(1.2);
            filter: drop-shadow(0 0 8px rgba(212, 168, 83, 0.5));
        }

        .menu-item span {
            font-weight: 600;
        }

        /* === SIDEBAR FOOTER === */
        .sidebar-footer {
            padding: var(--space-6);
            border-top: 1px solid var(--glass-border);
            background: rgba(233, 69, 96, 0.05);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: var(--space-4);
            background: rgba(233, 69, 96, 0.1);
            border: 2px solid rgba(233, 69, 96, 0.3);
            border-radius: 12px;
            color: #E94560;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all var(--duration-normal) var(--ease-standard);
            backdrop-filter: blur(10px);
        }

        .logout-btn:hover {
            background: #E94560;
            color: white;
            border-color: #E94560;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 69, 96, 0.3);
            text-decoration: none;
        }

        .logout-btn i {
            margin-right: var(--space-2);
            font-size: 1.1rem;
        }

        /* === MAIN CONTENT === */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--gradient-dark);
            position: relative;
            overflow: hidden;
        }

        .main-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 30% 70%, rgba(212, 168, 83, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 70% 30%, rgba(15, 52, 96, 0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        /* === TOP BAR === */
        .top-bar {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-bottom: 1px solid var(--glass-border);
            padding: var(--space-6) var(--space-8);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 10;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gradient-gold);
            opacity: 0.6;
        }

        .page-title {
            color: var(--primary-gold);
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-shadow: 0 0 15px rgba(212, 168, 83, 0.3);
        }

        .page-title i {
            font-size: 1.8rem;
            color: var(--primary-gold);
            filter: drop-shadow(0 0 10px rgba(212, 168, 83, 0.4));
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: var(--space-1);
        }

        /* === ADMIN INFO === */
        .admin-card {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            background: rgba(255, 255, 255, 0.05);
            padding: var(--space-3) var(--space-5);
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all var(--duration-normal) var(--ease-standard);
        }

        .admin-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            font-weight: 800;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .admin-details {
            display: flex;
            flex-direction: column;
        }

        .admin-name {
            color: #FFFFFF;
            font-weight: 600;
            font-size: 1rem;
        }

        .admin-role {
            color: var(--primary-gold);
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success-green);
            box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
        }

        /* === CONTENT AREA === */
        .content-area {
            flex: 1;
            padding: var(--space-8);
            overflow-y: auto;
            position: relative;
            z-index: 1;
        }

        /* === DESIGN SYSTEM AGENTS === */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-8);
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: var(--space-6);
            box-shadow: var(--glass-shadow);
        }

        .section-title {
            color: var(--primary-gold);
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            text-shadow: 0 0 15px rgba(212, 168, 83, 0.3);
        }

        .section-title i {
            font-size: 1.5rem;
            color: var(--primary-gold);
        }

        .header-actions {
            display: flex;
            gap: var(--space-3);
        }

        /* === BOUTONS === */
        .btn {
            padding: var(--space-3) var(--space-5);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all var(--duration-normal) var(--ease-standard);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* === STATISTIQUES === */
        .agents-stats {
            margin-bottom: var(--space-8);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-6);
        }

        .stat-item {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-4);
            transition: all var(--duration-normal) var(--ease-standard);
            box-shadow: var(--glass-shadow);
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
            border-color: var(--primary-gold);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.actif {
            background: linear-gradient(135deg, #27AE60, #2ECC71);
            color: white;
        }

        .stat-icon.moto {
            background: linear-gradient(135deg, #3B82F6, #60A5FA);
            color: white;
        }

        .stat-icon.car {
            background: linear-gradient(135deg, #8B5CF6, #A78BFA);
            color: white;
        }

        .stat-icon.cargo {
            background: linear-gradient(135deg, #F59E0B, #FBBF24);
            color: white;
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-gold);
            font-family: 'Montserrat', sans-serif;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: var(--space-1);
        }

        /* === ONGLETS === */
        .tab-buttons {
            display: flex;
            gap: var(--space-2);
            margin-bottom: var(--space-8);
            background: rgba(255, 255, 255, 0.05);
            padding: var(--space-2);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .tab-btn {
            flex: 1;
            background: transparent;
            border: none;
            padding: var(--space-4) var(--space-6);
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.8);
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-standard);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
        }

        .tab-btn:hover {
            color: var(--primary-gold);
            background: rgba(212, 168, 83, 0.1);
            transform: translateY(-2px);
        }

        .tab-btn.active {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* === TABLEAUX === */
        .table-container {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--glass-shadow);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: rgba(212, 168, 83, 0.15);
            color: var(--primary-gold);
            padding: var(--space-4) var(--space-5);
            text-align: left;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .data-table td {
            padding: var(--space-4) var(--space-5);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.9);
            transition: all var(--duration-normal) var(--ease-standard);
        }

        .data-table tr:hover td {
            background: rgba(255, 255, 255, 0.05);
            color: #FFFFFF;
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.2);
            color: #3B82F6;
            border: 1px solid #3B82F6;
        }

        .btn-edit:hover {
            background: #3B82F6;
            color: white;
            transform: translateY(-2px);
            text-decoration: none;
        }

        .btn-activate {
            background: rgba(39, 174, 96, 0.2);
            color: var(--success-green);
            border: 1px solid var(--success-green);
        }

        .btn-activate:hover {
            background: var(--success-green);
            color: white;
            transform: translateY(-2px);
        }

        /* === RESPONSIVE === */
        @media (max-width: 1024px) {
            .sidebar {
                width: 280px;
            }
            
            .content-area {
                padding: var(--space-5);
            }
            
            .top-bar {
                padding: var(--space-4) var(--space-5);
            }
            
            .page-title {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .sidebar-nav {
                display: flex;
                overflow-x: auto;
                padding: var(--space-3);
            }
            
            .nav-section {
                display: flex;
                gap: var(--space-2);
                margin-bottom: 0;
            }
            
            .menu-item {
                white-space: nowrap;
                min-width: 150px;
                justify-content: center;
            }
            
            .content-area {
                padding: var(--space-3);
            }
            
            .top-bar {
                padding: var(--space-3);
                flex-direction: column;
                gap: var(--space-3);
            }
            
            .admin-card {
                order: -1;
            }

            .section-header {
                flex-direction: column;
                gap: var(--space-4);
                text-align: center;
            }

            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .tab-buttons {
                flex-direction: column;
            }

            .data-table {
                font-size: 0.8rem;
            }

            .data-table th,
            .data-table td {
                padding: var(--space-2) var(--space-3);
            }
        }

        /* === ANIMATIONS === */
        .fade-in {
            animation: fadeIn 0.6s var(--ease-enter);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in-left {
            animation: slideInLeft 0.8s var(--ease-enter);
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Inline sidebar removed to avoid duplicate primary navigation -->
    <!-- Rely on global navigation sidebar loaded elsewhere -->
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-users"></i>
                    Gestion des agents
                </h1>
                <p class="page-subtitle">Interface d'administration SUZOSKY</p>
            </div>
            
        </div>
        
        <div class="content-area fade-in">

    <div id="agents" class="content-section">
    <!-- HEADER AVEC ACTIONS (TITRE SUPPRIMÉ, évite doublon) -->
    <div class="section-header">
        <div class="header-actions">
      <button onclick="showAddAgentModal()" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Nouvel Agent
      </button>
      <button onclick="exportAgents()" class="btn btn-secondary">
        <i class="fas fa-download"></i> Exporter
      </button>
      <button onclick="loadAgents()" class="btn btn-secondary">
        <i class="fas fa-sync-alt"></i> Actualiser
      </button>
    </div>
  </div>

  <!-- STATISTIQUES AGENTS -->
  <div class="agents-stats">
    <div class="stats-row">
      <div class="stat-item">
        <div class="stat-icon actif"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
          <div class="stat-number" id="agentsActifsCount"><?= $totalAgents ?></div>
          <div class="stat-label">Agents actifs</div>
        </div>
      </div>
      <div class="stat-item">
        <div class="stat-icon moto"><i class="fas fa-motorcycle"></i></div>
        <div class="stat-info">
          <div class="stat-number" id="coursiersMotosCount"><?= $totalCoursiers ?></div>
          <div class="stat-label">Coursiers</div>
        </div>
      </div>
      <div class="stat-item">
        <div class="stat-icon car"><i class="fas fa-briefcase"></i></div>
        <div class="stat-info">
          <div class="stat-number" id="conciergesCount"><?= $totalConcierges ?></div>
          <div class="stat-label">Concierges</div>
        </div>
      </div>
      <div class="stat-item">
        <div class="stat-icon cargo"><i class="fas fa-calendar-plus"></i></div>
        <div class="stat-info">
          <div class="stat-number" id="newAgentsCount"><?= $newAgentsThisMonth ?></div>
          <div class="stat-label">Nouveaux ce mois</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ONGLETS AGENTS -->
  <div class="tab-buttons">
    <button class="tab-btn active" onclick="showTab('coursiers')">
      <i class="fas fa-motorcycle"></i> Coursiers
    </button>
    <button class="tab-btn" onclick="showTab('concierges')">
      <i class="fas fa-concierge-bell"></i> Concierges
    </button>
  </div>

  <!-- TABLEAU COURSIEURS -->
  <div id="coursiers" class="tab-content active">
    <div class="table-container">
      <table class="data-table" id="coursiersTable">
        <thead>
          <tr>
            <th>ID</th><th>Matricule</th><th>Nom complet</th>
            <th>Téléphone</th><th>Type de poste</th>
            <th>Date création</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="coursiersTableBody">
          <?php foreach ($coursiers as $agent): ?>
          <tr>
            <td><?= htmlspecialchars($agent['id']) ?></td>
            <td><?= htmlspecialchars($agent['matricule']) ?></td>
            <td><?= htmlspecialchars(($agent['prenoms'] ?? '') . ' ' . ($agent['nom'] ?? '')) ?></td>
            <td><?= htmlspecialchars($agent['telephone']) ?></td>
            <td><?= htmlspecialchars($agent['type_poste']) ?></td>
            <td><?= date('d/m/Y', strtotime($agent['created_at'])) ?></td>
            <td>
              <a href="?section=agents&view_agent=<?= $agent['id'] ?>" class="btn-sm btn-edit">
                <i class="fas fa-eye"></i> Voir
              </a>
              <button data-id="<?= $agent['id'] ?>" class="btn-sm btn-activate btn-generate-password">
                <i class="fas fa-key"></i> Nouveau MDP
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- TABLEAU CONCIÈRGES -->
  <div id="concierges" class="tab-content">
    <div class="table-container">
      <table class="data-table" id="conciergesTable">
        <thead>
          <tr>
            <th>ID</th><th>Matricule</th><th>Nom complet</th>
            <th>Téléphone</th><th>Type de poste</th>
            <th>Date création</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="conciergesTableBody">
          <?php foreach ($concierges as $agent): ?>
          <tr>
            <td><?= htmlspecialchars($agent['id']) ?></td>
            <td><?= htmlspecialchars($agent['matricule']) ?></td>
            <td><?= htmlspecialchars(($agent['prenoms'] ?? '') . ' ' . ($agent['nom'] ?? '')) ?></td>
            <td><?= htmlspecialchars($agent['telephone']) ?></td>
            <td><?= htmlspecialchars($agent['type_poste']) ?></td>
            <td><?= date('d/m/Y', strtotime($agent['created_at'])) ?></td>
            <td>
              <a href="?section=agents&view_agent=<?= $agent['id'] ?>" class="btn-sm btn-edit">
                <i class="fas fa-eye"></i> Voir
              </a>
              <button data-id="<?= $agent['id'] ?>" class="btn-sm btn-activate btn-generate-password">
                <i class="fas fa-key"></i> Nouveau MDP
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

<script>
// === GESTION DES TABS ===
function showTab(tabId) {
    // Masquer tous les contenus
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
        content.classList.remove('active');
    });
    
    // Désactiver tous les boutons
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.classList.remove('active');
    });
    
    // Afficher le contenu sélectionné
    document.getElementById(tabId).style.display = 'block';
    document.getElementById(tabId).classList.add('active');
    
    // Activer le bouton correspondant
    event.target.classList.add('active');
}

document.addEventListener('DOMContentLoaded', function(){
    // Handler for password generation
    document.addEventListener('click', function(e){
        if(e.target.classList.contains('btn-generate-password')){
            // Liste: génération mot de passe via endpoint dédié
            const id = e.target.getAttribute('data-id');
            // Appel AJAX vers le script admin/agents.php pour la liste
            fetch('admin/agents.php?action=generate_password&id=' + id + '&ajax=true')
            .then(res=>res.json())
            .then(data=>{
                if(data.success){ alert('Nouveau mot de passe: '+data.password); }
            });
        }
    });

    // Refresh function
    function refreshAgents(){
        fetch('?section=agents&ajax=true')
            .then(res=>res.json())
            .then(data=>{
                // Refresh coursiers table
                var coursiersBody = document.getElementById('coursiersTableBody');
                if(coursiersBody) {
                    coursiersBody.innerHTML = '';
                    data.filter(agent => ['coursier_moto', 'coursier_velo', 'coursier'].includes(agent.type_poste)).forEach(function(agent){
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td>'+agent.id+'</td>' +
                        '<td>'+agent.matricule+'</td>' +
                        '<td>'+(agent.prenoms||'')+' '+(agent.nom||'')+'</td>' +
                        '<td>'+agent.telephone+'</td>' +
                        '<td>'+agent.type_poste+'</td>' +
                        '<td>'+new Date(agent.created_at).toLocaleDateString()+'</td>' +
                        '<td><a href="?section=agents&view_agent='+agent.id+'" class="btn-sm btn-edit"><i class="fas fa-eye"></i> Voir</a> ' +
                        '<button data-id="'+agent.id+'" class="btn-sm btn-activate btn-generate-password"><i class="fas fa-key"></i> Nouveau MDP</button></td>';
                        coursiersBody.appendChild(tr);
                    });
                }
                
                // Refresh concierges table
                var conciergesBody = document.getElementById('conciergesTableBody');
                if(conciergesBody) {
                    conciergesBody.innerHTML = '';
                    data.filter(agent => ['concierge', 'conciergerie'].includes(agent.type_poste)).forEach(function(agent){
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td>'+agent.id+'</td>' +
                        '<td>'+agent.matricule+'</td>' +
                        '<td>'+(agent.prenoms||'')+' '+(agent.nom||'')+'</td>' +
                        '<td>'+agent.telephone+'</td>' +
                        '<td>'+agent.type_poste+'</td>' +
                        '<td>'+new Date(agent.created_at).toLocaleDateString()+'</td>' +
                        '<td><a href="?section=agents&view_agent='+agent.id+'" class="btn-sm btn-edit"><i class="fas fa-eye"></i> Voir</a> ' +
                        '<button data-id="'+agent.id+'" class="btn-sm btn-activate btn-generate-password"><i class="fas fa-key"></i> Nouveau MDP</button></td>';
                        conciergesBody.appendChild(tr);
                    });
                }
            });
    }
    
    // Auto-refresh every 30 seconds
    setInterval(refreshAgents, 30000);
    
    // Animation des cartes au chargement
    const cards = document.querySelectorAll('.stat-item');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0, 0, 0.2, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

</body>
</html>

