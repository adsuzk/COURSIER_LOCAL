<?php
// Common functions for admin interface

if (!function_exists('checkAdminAuth')) {
    function checkAdminAuth() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return false;
        }
        return true;
    }
}

if (!function_exists('renderLoginForm')) {
function renderLoginForm($error = '') {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connexion Admin - Suzosky</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            /* DESIGN SYSTEM SUZOSKY - IDENTIQUE À COURSIER.PHP */
            :root {
                --primary-gold: #D4A853;
                --primary-dark: #1A1A2E;
                --secondary-blue: #16213E;
                --accent-blue: #0F3460;
                --accent-red: #E94560;
                --success-color: #27AE60;
                --glass-bg: rgba(255,255,255,0.08);
                --glass-border: rgba(255,255,255,0.2);
                --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
                --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
                --success-color: #28a745;
                --warning-color: #ffc107;
                --danger-color: #E94560;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            /* Body de base (mode login: l'alignement est géré par .auth-screen, pas par body) */
            body {
                font-family: 'Montserrat', sans-serif;
                background: var(--gradient-dark);
                color: white;
                min-height: 100vh;
                overflow-x: hidden;
            }

            /* === STYLES CONNEXION/INSCRIPTION IDENTIQUE À COURSIER.PHP === */
            .auth-screen {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .auth-container {
                background: var(--glass-bg);
                backdrop-filter: blur(20px);
                border: 1px solid var(--glass-border);
                border-radius: 30px;
                padding: 40px;
                width: 100%;
                max-width: 500px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }

            .auth-header {
                text-align: center;
                margin-bottom: 30px;
            }

            .brand-name {
                font-size: 2.2rem;
                font-weight: 900;
                background: var(--gradient-gold);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin-bottom: 8px;
                letter-spacing: 2px;
            }

            .brand-subtitle {
                color: var(--primary-gold);
                font-size: 0.9rem;
                font-weight: 600;
                letter-spacing: 1.5px;
                opacity: 0.9;
            }
                position: relative;
                z-index: 10;
                overflow: hidden;
                animation: slideIn 0.8s var(--ease-standard);
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(30px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            .login-container::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: var(--gradient-gold);
            }

            /* === HEADER DE CONNEXION === */
            .login-header {
                text-align: center;
                margin-bottom: var(--space-10);
            }

            .logo-container {
                width: 80px;
                height: 80px;
                margin: 0 auto var(--space-5);
                background: var(--gradient-gold);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: var(--shadow-gold);
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% {
                    box-shadow: var(--shadow-gold);
                }
                50% {
                    box-shadow: var(--glow-gold-strong);
                }
            }

            .logo-container i {
                font-size: 2.5rem;
                color: var(--primary-dark);
            }

            .login-header h1 {
                color: var(--primary-gold);
                font-size: 2.25rem;
                font-weight: 700;
                margin-bottom: var(--space-2);
                text-shadow: 0 0 20px rgba(212, 168, 83, 0.3);
            }

            .login-header p {
                color: rgba(255, 255, 255, 0.8);
                font-size: 1.1rem;
                font-weight: 400;
            }

            /* === SYSTEME DE FORMULAIRES - CHARTE SUZOSKY IDENTIQUE === */
            .form-group {
                margin-bottom: 28px;
                position: relative;
            }

            .form-group label {
                display: block;
                color: rgba(255,255,255,0.95);
                font-weight: 700;
                margin-bottom: 12px;
                font-size: 14px;
                font-family: 'Montserrat', sans-serif;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                position: relative;
            }
            
            .form-group label::after {
                content: '';
                position: absolute;
                bottom: -4px;
                left: 0;
                width: 30px;
                height: 2px;
                background: var(--gradient-gold);
                border-radius: 2px;
            }

            .form-group input,
            .form-group select {
                width: 100%;
                padding: 18px 20px;
                background: var(--glass-bg);
                backdrop-filter: blur(20px);
                border: 2px solid var(--glass-border);
                border-radius: 15px;
                color: white;
                font-family: 'Montserrat', sans-serif;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
                position: relative;
                overflow: hidden;
            }
            
            .form-group input::placeholder {
                color: rgba(255,255,255,0.5);
                font-weight: 500;
            }

            .form-group input:focus,
            .form-group select:focus {
                outline: none;
                border-color: var(--primary-gold);
                box-shadow: 0 8px 35px rgba(212, 168, 83, 0.3);
                background: rgba(255,255,255,0.08);
                transform: translateY(-2px);
            }
            
            .form-group input:hover,
            .form-group select:hover {
                border-color: rgba(212, 168, 83, 0.6);
                background: rgba(255,255,255,0.06);
            }

            /* === BOUTONS PRINCIPAUX - CHARTE SUZOSKY IDENTIQUE === */
            .btn {
                width: 100%;
                padding: 15px;
                border: none;
                border-radius: 12px;
                font-family: 'Montserrat', sans-serif;
                font-weight: 600;
                font-size: 1rem;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }

            .btn-primary {
                background: var(--gradient-gold);
                color: var(--primary-dark);
                box-shadow: 0 8px 25px rgba(212, 168, 83, 0.3);
                border: 2px solid transparent;
                position: relative;
                overflow: hidden;
                font-family: 'Montserrat', sans-serif;
                font-weight: 700;
                letter-spacing: 0.5px;
                transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            }
            
            .btn-primary::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                transition: left 0.5s ease;
            }
            
            .btn-primary:hover::before {
                left: 100%;
            }

            .btn-primary:hover {
                transform: translateY(-3px) scale(1.02);
                box-shadow: 0 15px 40px rgba(212, 168, 83, 0.5);
                filter: brightness(1.1);
            }

            /* === SYSTEME D'ALERTES - CHARTE SUZOSKY IDENTIQUE === */
            .alert {
                padding: 20px 25px;
                border-radius: 18px;
                margin-bottom: 25px;
                font-weight: 600;
                font-family: 'Montserrat', sans-serif;
                font-size: 14px;
                letter-spacing: 0.3px;
                border: 2px solid;
                backdrop-filter: blur(20px);
                position: relative;
                overflow: hidden;
                transition: all 0.4s ease;
            }
            
            .alert::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
                transition: left 0.5s ease;
            }
            
            .alert:hover::before {
                left: 100%;
            }

            .alert-error {
                background: rgba(233, 69, 96, 0.15);
                border-color: var(--accent-red);
                color: var(--accent-red);
                box-shadow: 0 8px 30px rgba(233, 69, 96, 0.2);
                animation: errorPulse 2s infinite;
            }
            
            @keyframes errorPulse {
                0%, 100% { box-shadow: 0 8px 30px rgba(233, 69, 96, 0.2); }
                50% { box-shadow: 0 12px 40px rgba(233, 69, 96, 0.4); }
            }
            
            .alert-error:hover {
                background: rgba(233, 69, 96, 0.25);
                transform: translateY(-2px);
                animation: none;
            }

            /* === FOOTER === */
            .login-footer {
                text-align: center;
                margin-top: var(--space-8);
                padding-top: var(--space-6);
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }

            .login-footer p {
                color: rgba(255, 255, 255, 0.6);
                font-size: 0.85rem;
            }

            /* === RESPONSIVE === */
            @media (max-width: 480px) {
                .login-container {
                    margin: var(--space-5);
                    padding: var(--space-8);
                }
                
                .login-header h1 {
                    font-size: 1.875rem;
                }
                
                .logo-container {
                    width: 70px;
                    height: 70px;
                }
                
                .logo-container i {
                    font-size: 2rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="auth-screen">
            <div class="auth-container">
                <div class="auth-header">
                    <div class="brand-name">SUZOSKY</div>
                    <div class="brand-subtitle">ADMINISTRATION</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo routePath('admin.php'); ?>" class="auth-form active">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" placeholder="Entrez votre nom d'utilisateur" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" placeholder="Entrez votre mot de passe" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Se connecter
                    </button>
                </form>
            </div>
        </div>

        <script>
            // Animation au chargement - Style Suzosky
            document.addEventListener('DOMContentLoaded', function() {
                // Animation des champs de formulaire
                const inputs = document.querySelectorAll('input');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.style.transform = 'scale(1.02)';
                    });
                    
                    input.addEventListener('blur', function() {
                        this.style.transform = 'scale(1)';
                    });
                });

                // Effet de lueur sur le bouton
                const loginBtn = document.querySelector('.btn-primary');
                if (loginBtn) {
                    loginBtn.addEventListener('mousemove', function(e) {
                        const rect = this.getBoundingClientRect();
                        const x = e.clientX - rect.left;
                        const y = e.clientY - rect.top;
                        
                        this.style.background = `radial-gradient(circle at ${x}px ${y}px, #E8C468, #D4A853)`;
                    });
                    
                    loginBtn.addEventListener('mouseleave', function() {
                        this.style.background = 'var(--gradient-gold)';
                    });
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
}

if (!function_exists('renderHeader')) {
function renderHeader() {
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
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
                overflow: hidden;
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
                max-height: calc(100vh - 200px);
                scrollbar-width: thin;
                scrollbar-color: var(--primary-gold) transparent;
            }

            .sidebar-nav::-webkit-scrollbar {
                width: 6px;
            }

            .sidebar-nav::-webkit-scrollbar-track {
                background: rgba(255,255,255,0.05);
                border-radius: 3px;
            }

            .sidebar-nav::-webkit-scrollbar-thumb {
                background: var(--primary-gold);
                border-radius: 3px;
            }

            .sidebar-nav::-webkit-scrollbar-thumb:hover {
                background: #E8C468;
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
                margin-left: 300px;
                min-height: 100vh;
                overflow-x: hidden;
                overflow-y: auto;
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

            .top-bar h1 {
                color: var(--primary-gold);
                font-size: 2rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: var(--space-3);
                text-shadow: 0 0 15px rgba(212, 168, 83, 0.3);
            }

            .top-bar h1 i {
                font-size: 1.8rem;
                backdrop-filter: var(--glass-blur);
                border-right: 1px solid var(--glass-border);
                box-shadow: var(--glass-shadow);
                position: relative;
                z-index: 100;
                transition: all var(--duration-normal) var(--ease-standard);
            }

            .sidebar::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 4px;
                height: 100%;
                background: var(--gradient-gold);
                opacity: 0.8;
            }

            .sidebar-header {
                padding: var(--space-8);
                text-align: center;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                position: relative;
                overflow: hidden;
            }

            .sidebar-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: var(--gradient-gold);
            }

            .logo-admin {
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
                }
                50% {
                    box-shadow: var(--glow-gold);
                }
            }

            .logo-admin i {
                font-size: 2.5rem;
                color: var(--primary-dark);
            }

            .sidebar-header h2 {
                font-size: 1.75rem;
                font-weight: 700;
                color: var(--primary-gold);
                margin-bottom: var(--space-2);
                text-shadow: 0 0 15px rgba(212, 168, 83, 0.3);
            }

            .sidebar-header p {
                color: rgba(255, 255, 255, 0.8);
                font-size: 0.95rem;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            /* === MENU SIDEBAR === */
            .sidebar-menu {
                padding: var(--space-6) 0;
                flex: 1;
                overflow-y: auto;
                height: calc(100vh - 280px);
                scrollbar-width: thin;
                scrollbar-color: var(--primary-gold) transparent;
            }

            .sidebar-menu::-webkit-scrollbar {
                width: 6px;
            }

            .sidebar-menu::-webkit-scrollbar-track {
                background: rgba(255,255,255,0.05);
                border-radius: 3px;
            }

            .sidebar-menu::-webkit-scrollbar-thumb {
                background: var(--primary-gold);
                border-radius: 3px;
            }

            .sidebar-menu::-webkit-scrollbar-thumb:hover {
                background: #E8C468;
            }

            .menu-item {
                display: flex;
                align-items: center;
                padding: var(--space-4) var(--space-8);
                color: rgba(255, 255, 255, 0.8);
                text-decoration: none;
                font-weight: 500;
                font-size: 1rem;
                transition: all var(--duration-normal) var(--ease-standard);
                border-left: 4px solid transparent;
                position: relative;
                margin: var(--space-1) 0;
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

            .top-bar h1 {
                color: var(--primary-gold);
                font-size: 2rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: var(--space-3);
                text-shadow: 0 0 15px rgba(212, 168, 83, 0.3);
            }

            .top-bar h1 i {
                font-size: 1.8rem;
                color: var(--primary-gold);
                filter: drop-shadow(0 0 10px rgba(212, 168, 83, 0.4));
            }

            /* === USER INFO === */
            .user-info {
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

            .user-info:hover {
                background: rgba(255, 255, 255, 0.08);
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }

            .user-avatar {
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

            .user-details {
                display: flex;
                flex-direction: column;
            }

            .user-name {
                color: #FFFFFF;
                font-weight: 600;
                font-size: 1rem;
            }

            .user-role {
                color: var(--primary-gold);
                font-size: 0.85rem;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* === CONTENT AREA === */
            .content-area {
                flex: 1;
                padding: var(--space-8);
                overflow-y: auto;
                position: relative;
                z-index: 1;
            }

            /* === TABS SUZOSKY === */
            .sub-tabs {
                background: var(--glass-bg);
                backdrop-filter: var(--glass-blur);
                border-radius: 20px;
                margin-bottom: var(--space-8);
                overflow: hidden;
                box-shadow: var(--glass-shadow);
                border: 1px solid var(--glass-border);
            }

            .sub-tabs-header {
                background: rgba(255, 255, 255, 0.05);
                padding: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                display: flex;
                gap: 0;
            }

            .sub-tab-button {
                flex: 1;
                padding: var(--space-5) var(--space-6);
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.7);
                font-family: 'Montserrat', sans-serif;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all var(--duration-normal) var(--ease-standard);
                border-bottom: 3px solid transparent;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: var(--space-2);
            }

            .sub-tab-button::before {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: var(--gradient-gold);
                transform: scaleX(0);
                transition: transform var(--duration-normal) var(--ease-standard);
            }

            .sub-tab-button:hover {
                color: var(--primary-gold);
                background: rgba(212, 168, 83, 0.1);
                transform: translateY(-2px);
            }

            .sub-tab-button.active {
                color: var(--primary-gold);
                background: rgba(212, 168, 83, 0.15);
                text-shadow: 0 0 10px rgba(212, 168, 83, 0.3);
            }

            .sub-tab-button.active::before {
                transform: scaleX(1);
            }

            .sub-tab-button i {
                font-size: 1.1rem;
                color: var(--primary-gold);
            }

            .sub-tab-content {
                padding: var(--space-8);
            }

            /* === BOUTONS SUZOSKY === */
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

            .btn-success {
                background: linear-gradient(135deg, #27AE60, #2ECC71);
                color: white;
                box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
            }

            .btn-success:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
            }

            .btn-danger {
                background: linear-gradient(135deg, #E94560, #F27474);
                color: white;
                box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
            }

            .btn-danger:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(233, 69, 96, 0.4);
            }

            .btn-info {
                background: linear-gradient(135deg, #3B82F6, #60A5FA);
                color: white;
                box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            }

            .btn-warning {
                background: linear-gradient(135deg, #FFC107, #FFD93D);
                color: var(--primary-dark);
                box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
            }

            /* === DATA TABLES === */
            .data-table {
                width: 100%;
                background: var(--glass-bg);
                backdrop-filter: var(--glass-blur);
                border-radius: 16px;
                overflow: hidden;
                border: 1px solid var(--glass-border);
                box-shadow: var(--glass-shadow);
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

            /* === RESPONSIVE === */
            @media (max-width: 1024px) {
                .sidebar {
                    width: 280px;
                }
                
                .main-content {
                    margin-left: 280px;
                }
                
                .content-area {
                    padding: var(--space-5);
                }
                
                .top-bar {
                    padding: var(--space-4) var(--space-5);
                }
                
                .top-bar h1 {
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
                    position: relative;
                }
                
                .main-content {
                    margin-left: 0;
                }
                
                .sidebar-menu {
                    display: flex;
                    overflow-x: auto;
                    padding: var(--space-3);
                    height: auto;
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
                
                .user-info {
                    order: -1;
                }
            }

            /* === ANIMATIONS GLOBALES === */
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
        <div class="sidebar slide-in-left">
            <div class="sidebar-header">
                <div class="admin-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="sidebar-title">SUZOSKY</h2>
                <p class="sidebar-subtitle">Administration</p>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <a href="admin.php?section=dashboard" class="menu-item <?php echo ($_GET['section'] ?? 'dashboard') === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i><span>Tableau de bord</span>
                    </a>
                    <a href="admin.php?section=commandes" class="menu-item <?php echo ($_GET['section'] ?? '') === 'commandes' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i><span>Commandes</span>
                    </a>
                    <a href="admin.php?section=agents" class="menu-item <?php echo ($_GET['section'] ?? '') === 'agents' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i><span>Agents</span>
                    </a>
                    <a href="admin.php?section=chat" class="menu-item <?php echo ($_GET['section'] ?? '') === 'chat' ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i><span>Chat</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Gestion clients</div>
                    <a href="admin.php?section=clients" class="menu-item <?php echo ($_GET['section'] ?? '') === 'clients' ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i><span>Clients</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Finances</div>
                    <a href="admin.php?section=finances" class="menu-item <?php echo ($_GET['section'] ?? '') === 'finances' ? 'active' : ''; ?>">
                        <i class="fas fa-coins"></i><span>Gestion financière</span>
                    </a>
                    <a href="admin.php?section=finances_audit" class="menu-item <?php echo ($_GET['section'] ?? '') === 'finances_audit' ? 'active' : ''; ?>">
                        <i class="fas fa-search-dollar"></i><span>Audit livraisons</span>
                    </a>
                    <a href="admin.php?section=comptabilite" class="menu-item <?php echo ($_GET['section'] ?? '') === 'comptabilite' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice-dollar"></i><span>Comptabilité</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Communications</div>
                    <a href="admin.php?section=emails" class="menu-item <?php echo ($_GET['section'] ?? '') === 'emails' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i><span>Gestion d'Emails</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Applications</div>
                    <a href="admin.php?section=applications" class="menu-item <?php echo ($_GET['section'] ?? '') === 'applications' ? 'active' : ''; ?>">
                        <i class="fas fa-mobile-android"></i><span>Applications</span>
                    </a>
                    <a href="admin.php?section=app_updates" class="menu-item <?php echo ($_GET['section'] ?? '') === 'app_updates' ? 'active' : ''; ?>">
                        <i class="fas fa-upload"></i><span>Mises à jour</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Système</div>
                    <a href="admin.php?section=reseau" class="menu-item <?php echo ($_GET['section'] ?? '') === 'reseau' ? 'active' : ''; ?>">
                        <i class="fas fa-network-wired"></i><span>Réseau & APIs</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Ressources humaines</div>
                    <a href="admin.php?section=recrutement" class="menu-item <?php echo ($_GET['section'] ?? '') === 'recrutement' ? 'active' : ''; ?>">
                        <i class="fas fa-id-badge"></i><span>Recrutement</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="admin.php?section=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-<?php 
                            $icons = [
                                'dashboard' => 'tachometer-alt',
                                'commandes' => 'shipping-fast',
                                'agents' => 'users',
                                'chat' => 'comments',
                                'clients' => 'address-book',
                                'finances' => 'coins',
                                'finances_audit' => 'search-dollar',
                                'comptabilite' => 'file-invoice-dollar',
                                'recrutement' => 'briefcase'
                            ];
                            echo $icons[$_GET['section'] ?? 'dashboard'] ?? 'cog';
                        ?>"></i>
                        <?php 
                        $titles = [
                            'dashboard' => 'Tableau de bord',
                            'commandes' => 'Gestion des commandes',
                            'agents' => 'Gestion des agents',
                            'chat' => 'Support Chat',
                            'clients' => 'Gestion des clients',
                            'finances' => 'Gestion financière',
                            'finances_audit' => 'Audit des livraisons',
                            'comptabilite' => 'Comptabilité',
                            'recrutement' => 'Emploi & Recrutement'
                        ];
                        echo $titles[$_GET['section'] ?? 'dashboard'] ?? 'Administration';
                        ?>
                    </h1>
                    <p class="page-subtitle">Interface d'administration SUZOSKY</p>
                </div>
                
            </div>
            
            <div class="content-area">
    <?php
}
}

if (!function_exists('renderFooter')) {
function renderFooter() {
    ?>
            </div>
        </div>
        <script>
        // admin.js minimal inline pour éviter 404
        // (Les fonctions de tracking sont définies dans admin_commandes_enhanced.php)
        console.log('✅ Admin footer chargé');
        </script>
        <?php
        // Injecter l'auto-refresh pour les pages critiques de synchronisation APK
        $section = $_GET['section'] ?? '';
        if (in_array($section, ['app_updates', 'applications'], true)) {
            echo '<script src="assets/js/auto_refresh_app_updates.js"></script>';
        }
        ?>
        <?php 
        // Inclure le module de calcul de prix détaillé pour l'admin
        if (file_exists(__DIR__ . '/js_price_calculation_admin.php')) {
            include __DIR__ . '/js_price_calculation_admin.php';
        }
        ?>
    </body>
    </html>
    <?php
}
}
