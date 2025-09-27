<?php
/**
 * CRÉATEUR FICHIERS CSS/JS/TEMPLATES
 * Partie 2 - Fichiers d'interface
 */

echo "🎨 CRÉATION FICHIERS INTERFACE\n";
echo "=============================\n\n";

// 1. CSS
echo "📝 Création admin_styles.css...\n";
$cssContent = '/* EMAIL ADMIN PANEL STYLES */

.email-admin-container {
    padding: 20px;
    background: transparent;
    min-height: auto;
}

.email-tabs {
    display: flex;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 4px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
}

.email-tab {
    flex: 1;
    padding: 12px 16px;
    text-decoration: none;
    color: #CCCCCC;
    text-align: center;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.email-tab:hover {
    color: #495057;
    background: #e9ecef;
}

.email-tab.active {
    background: linear-gradient(135deg, #D4A853 0%, #E8C468 100%);
    color: #1A1A2E;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(212, 168, 83, 0.3);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    border-left: 4px solid #dee2e6;
}

.stat-card.success { border-left-color: #28a745; }
.stat-card.danger { border-left-color: #dc3545; }
.stat-card.info { border-left-color: #17a2b8; }
.stat-card.warning { border-left-color: #ffc107; }

.stat-icon {
    font-size: 2rem;
    margin-right: 15px;
    opacity: 0.8;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #FFFFFF;
}

.stat-label {
    color: #CCCCCC;
    font-size: 0.9rem;
}

.email-table-container {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
}

.email-table {
    width: 100%;
    border-collapse: collapse;
}

.email-table th,
.email-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.email-table th {
    background: rgba(212, 168, 83, 0.2);
    color: #D4A853;
    font-weight: 600;
    position: sticky;
    top: 0;
}

.email-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.05);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.success {
    background: #d4edda;
    color: #155724;
}

.status-badge.error {
    background: #f8d7da;
    color: #721c24;
}

.btn-primary {
    background: linear-gradient(135deg, #D4A853 0%, #E8C468 100%);
    color: #1A1A2E;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(212, 168, 83, 0.3);
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 30px 0;
}

.page-title {
    color: #D4A853;
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 0 0 20px rgba(212, 168, 83, 0.3);
    margin-bottom: 10px;
}

.quick-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin: 30px 0;
    flex-wrap: wrap;
}

.quick-action {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 15px 25px;
    border-radius: 8px;
    text-decoration: none;
    color: #CCCCCC;
    transition: all 0.3s ease;
}

.quick-action:hover {
    background: #D4A853;
    color: #1A1A2E;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(212, 168, 83, 0.3);
}';

file_put_contents('EMAIL_SYSTEM/admin_styles.css', $cssContent);
echo "✅ admin_styles.css créé dans EMAIL_SYSTEM/\n";

// 2. JavaScript basique
echo "📝 Création admin_script.js...\n";
$jsContent = '/**
 * EMAIL ADMIN SCRIPT
 */

// Test email modal
function showTestEmailModal() {
    const email = prompt("Adresse email pour le test :", "admin@conciergerie-privee-suzosky.com");
    if (email && email.includes("@")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="action" value="test_email">
            <input type="hidden" name="test_email" value="${email}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonctions globales pour compatibilité
function viewEmail(id) {
    alert("Fonction viewEmail - ID: " + id);
}

function retryEmail(id) {
    if (confirm("Réessayer l\'envoi de cet email ?")) {
        location.reload();
    }
}

console.log("📧 Email Admin System loaded");';

file_put_contents('EMAIL_SYSTEM/admin_script.js', $jsContent);
echo "✅ admin_script.js créé dans EMAIL_SYSTEM/\n";

// 3. Template HTML
echo "📝 Création template HTML...\n";
$templateContent = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation mot de passe</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #D4A853 0%, #E8C468 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #1A1A2E; margin: 0; font-size: 28px;">🔐 Réinitialisation</h1>
        <p style="color: #1A1A2E; margin: 10px 0 0 0; opacity: 0.9;">Conciergerie Privée Suzosky</p>
    </div>
    
    <div style="background: #ffffff; padding: 40px; border: 1px solid #ddd; border-radius: 0 0 10px 10px;">
        <h2 style="color: #1A1A2E; margin-bottom: 20px;">Bonjour {{nom}},</h2>
        
        <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte Conciergerie Privée Suzosky.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{reset_url}}" style="background: linear-gradient(135deg, #D4A853 0%, #E8C468 100%); color: #1A1A2E; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                🔄 Réinitialiser mon mot de passe
            </a>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: #dc3545; margin-top: 0;">⚠️ Important :</h3>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Ce lien expire dans <strong>1 heure</strong></li>
                <li>Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email</li>
                <li>Utilisez un mot de passe sécurisé (8+ caractères, majuscules, chiffres)</li>
            </ul>
        </div>
        
        <p style="font-size: 14px; color: #666; text-align: center; margin-top: 30px;">
            Cet email a été envoyé automatiquement, merci de ne pas y répondre.<br>
            Pour toute question, contactez notre support client.
        </p>
    </div>
    
    <div style="text-align: center; padding: 20px; font-size: 12px; color: #888;">
        © 2025 Conciergerie Privée Suzosky - Tous droits réservés
    </div>
</body>
</html>';

file_put_contents('EMAIL_SYSTEM/templates/password_reset_default.html', $templateContent);
echo "✅ Template HTML créé dans EMAIL_SYSTEM/templates/\n";

echo "\n🎊 TOUS LES FICHIERS INTERFACE CRÉÉS !\n";
<parameter name="newString">echo "📧 Le système email est maintenant complet dans EMAIL_SYSTEM/ !\n";
?>