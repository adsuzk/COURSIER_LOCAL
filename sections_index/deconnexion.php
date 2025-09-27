<?php
// sections index/deconnexion.php - Fonctions de déconnexion
?>

<script>
// Fonction de déconnexion
function logout() {
    // Demander confirmation
    if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
        // Effacer les données de session
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userData');
        
        // Effacer aussi sessionStorage si utilisé
        sessionStorage.removeItem('isLoggedIn');
        sessionStorage.removeItem('userData');
        
        // Mettre à jour l'interface utilisateur
        updateAuthenticationUI();
        
        // Afficher message de confirmation
        showMessage('Vous avez été déconnecté avec succès', 'success');
        
        // Rediriger vers la page d'accueil si nécessaire
        if (window.location.pathname !== '/index.php' && window.location.pathname !== '/') {
            window.location.href = '/';
        }
    }
}

// Fonction pour vérifier si l'utilisateur est connecté
function isUserLoggedIn() {
    return localStorage.getItem('isLoggedIn') === 'true';
}

// Fonction pour obtenir les données utilisateur
function getUserData() {
    const userData = localStorage.getItem('userData');
    return userData ? JSON.parse(userData) : null;
}

// Fonction pour forcer la déconnexion (en cas d'erreur de session)
function forceLogout() {
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userData');
    sessionStorage.removeItem('isLoggedIn');
    sessionStorage.removeItem('userData');
    updateAuthenticationUI();
    window.location.href = '/';
}

// Fonction pour afficher des messages
function showMessage(message, type = 'info') {
    // Créer ou réutiliser un élément de message
    let messageEl = document.getElementById('auth-message');
    
    if (!messageEl) {
        messageEl = document.createElement('div');
        messageEl.id = 'auth-message';
        messageEl.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 9999;
            max-width: 300px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        `;
        document.body.appendChild(messageEl);
    }
    
    // Définir le style selon le type
    switch(type) {
        case 'success':
            messageEl.style.background = 'linear-gradient(135deg, #4CAF50, #45a049)';
            break;
        case 'error':
            messageEl.style.background = 'linear-gradient(135deg, #f44336, #da190b)';
            break;
        case 'warning':
            messageEl.style.background = 'linear-gradient(135deg, #ff9800, #f57c00)';
            break;
        default:
            messageEl.style.background = 'linear-gradient(135deg, #2196F3, #1976D2)';
    }
    
    messageEl.textContent = message;
    messageEl.style.display = 'block';
    messageEl.style.opacity = '1';
    
    // Masquer automatiquement après 4 secondes
    setTimeout(() => {
        messageEl.style.opacity = '0';
        setTimeout(() => {
            if (messageEl.parentNode) {
                messageEl.parentNode.removeChild(messageEl);
            }
        }, 300);
    }, 4000);
}

// Fonction pour vérifier la session périodiquement
function checkAuthSession() {
    // Vérifier toutes les 5 minutes si la session est toujours valide
    setInterval(() => {
        if (isUserLoggedIn()) {
            // Ici vous pourriez faire un appel API pour vérifier si la session est toujours valide
            // fetch('/api/check-session').then(response => {
            //     if (!response.ok) {
            //         forceLogout();
            //     }
            // });
        }
    }, 5 * 60 * 1000); // 5 minutes
}

// Initialiser la vérification de session au chargement
document.addEventListener('DOMContentLoaded', function() {
    checkAuthSession();
});

// Fonction pour protéger les pages nécessitant une authentification
function requireAuth() {
    if (!isUserLoggedIn()) {
        showMessage('Vous devez être connecté pour accéder à cette fonctionnalité', 'warning');
        openLoginModal();
        return false;
    }
    return true;
}

// Fonction pour obtenir le nom d'affichage de l'utilisateur
function getDisplayName() {
    const userData = getUserData();
    if (userData && userData.name) {
        return userData.name;
    }
    return 'Utilisateur';
}

// Fonction pour obtenir l'email de l'utilisateur
function getUserEmail() {
    const userData = getUserData();
    if (userData && userData.email) {
        return userData.email;
    }
    return null;
}

// Fonction pour obtenir le téléphone de l'utilisateur
function getUserPhone() {
    const userData = getUserData();
    if (userData && userData.phone) {
        return userData.phone;
    }
    return null;
}
</script>
