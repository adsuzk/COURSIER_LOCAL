<?php
// sections index/connexion.php - Contenu du modal de connexion (AJAX)
?>
<!-- Corps du modal chargé dynamiquement -->
<form id="loginForm" class="login-container">
    <h2>🔐 Connexion Particulier</h2>
    <p style="color: #aaa; margin-bottom: 20px;">Connectez-vous pour accéder à vos commandes</p>
            <div class="form-group">
                <label for="loginEmail">📧 Numéro de téléphone ou adresse mail</label>
                <input type="text" id="loginEmail" name="email" placeholder="votre@email.com ou +225 XX XX XX XX XX" required>
            </div>
            
            <div class="form-group">
                <label for="loginPassword">🔒 Mot de passe</label>
                <input type="password" id="loginPassword" name="password" placeholder="Votre mot de passe" required>
            </div>
            
            <button type="submit" class="btn-primary full-width" id="loginButton">
                🚀 Connexion
            </button>
            
            <div class="login-links">
                <p><a href="#" id="openForgotModal" style="color: #D4A853;">Mot de passe oublié ?</a></p>
                <p><a href="#" id="openRegisterModal" style="color: #D4A853;">Créer un compte</a></p>
            </div>
    </form>
    <!-- Removed stray closing div that was breaking modal markup -->
<?php return; ?>
        
        if (result.success) {
            console.log('🎉 Connexion réussie !');
            currentUser = result.client;
            closeLoginModal();
            updateNavigation();
            showNotification(result.message || 'Connexion réussie !', 'success');
            
            // Réinitialiser le formulaire
            loginInput.value = '';
            passwordInput.value = '';
        } else {
            console.error('❌ Échec de connexion:', result.error);
            showNotification(result.error || 'Erreur de connexion', 'error');
        }
    } catch (error) {
        console.error('💥 Erreur fatale:', error);
        showNotification('Erreur de connexion : ' + error.message, 'error');
    } finally {
        // Réactiver le bouton
        loginButton.disabled = false;
        loginButton.innerHTML = originalText;
    }
}

async function performRegister() {
    const nom = document.getElementById('registerNom').value.trim();
    const prenoms = document.getElementById('registerPrenoms').value.trim();
    const email = document.getElementById('registerEmail').value.trim();
    const telephone = document.getElementById('registerTelephone').value.trim();
    const password = document.getElementById('registerPassword').value;
    
    if (!nom || !prenoms || !email || !telephone || !password) {
        showNotification('Veuillez remplir tous les champs', 'error');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('nom', nom);
        formData.append('prenoms', prenoms);
        formData.append('email', email);
        formData.append('telephone', telephone);
        formData.append('password', password);
        
        const response = await fetch(apiUrl, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentUser = result.client;
            closeLoginModal();
            updateNavigation();
            showNotification(result.message || 'Compte créé avec succès !', 'success');
            
            // Réinitialiser le formulaire
            document.getElementById('registerNom').value = '';
            document.getElementById('registerPrenoms').value = '';
            document.getElementById('registerEmail').value = '';
            document.getElementById('registerTelephone').value = '';
            document.getElementById('registerPassword').value = '';
        } else {
            showNotification(result.error || 'Erreur lors de la création du compte', 'error');
        }
    } catch (error) {
        console.error('Erreur inscription:', error);
        showNotification('Erreur lors de la création du compte', 'error');
    }
}

async function performPasswordReset() {
    const email = document.getElementById('forgotEmail').value.trim();
    
    if (!email) {
        showNotification('Veuillez entrer votre email', 'error');
        return;
    }
    
    // Pour l'instant, juste un message
    showNotification('Fonctionnalité en cours de développement', 'info');
}

function updateNavigation() {
    const guestNav = document.getElementById('guestNav');
    const userNav = document.getElementById('userNav');
    
    
    async handleLogin(event) {
        event.preventDefault();
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Désactiver le bouton pendant la requête
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';
        
        try {
            const formData = new FormData(form);
            formData.append('action', 'login');
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.currentUser = result.client;
                this.closeModal('loginModal');
                this.showUserProfile();
                this.showNotification(result.message || 'Connexion réussie !', 'success');
                form.reset();
            } else {
                this.showNotification(result.error || 'Erreur de connexion', 'error');
            }
        } catch (error) {
            console.error('Erreur connexion:', error);
            this.showNotification('Erreur de connexion', 'error');
        } finally {
            // Réactiver le bouton
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Se connecter';
        }
    },
    
    async handleRegister(event) {
        event.preventDefault();
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Désactiver le bouton pendant la requête
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création...';
        
        try {
            const formData = new FormData(form);
            formData.append('action', 'register');
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.currentUser = result.client;
                this.closeModal('registerModal');
                this.showUserProfile();
                this.showNotification(result.message || 'Compte créé avec succès !', 'success');
                form.reset();
            } else {
                this.showNotification(result.error || 'Erreur lors de la création du compte', 'error');
            }
        } catch (error) {
            console.error('Erreur inscription:', error);
            this.showNotification('Erreur lors de la création du compte', 'error');
        } finally {
            // Réactiver le bouton
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Créer le compte';
        }
    },
    
    showAuthButtons() {
        document.getElementById('authButtons').style.display = 'flex';
        document.getElementById('userProfile').style.display = 'none';
        
        // Synchroniser avec le header
        if (document.getElementById('headerAuthButtons')) {
            document.getElementById('headerAuthButtons').style.display = 'flex';
            document.getElementById('headerUserProfile').style.display = 'none';
        }
    },
    
    showUserProfile() {
        if (this.currentUser) {
            document.getElementById('userName').textContent = `${this.currentUser.prenoms} ${this.currentUser.nom}`;
            document.getElementById('authButtons').style.display = 'none';
            document.getElementById('userProfile').style.display = 'flex';
            
            // Synchroniser avec le header
            if (document.getElementById('headerUserName')) {
                document.getElementById('headerUserName').textContent = `${this.currentUser.prenoms} ${this.currentUser.nom}`;
                document.getElementById('headerAuthButtons').style.display = 'none';
                document.getElementById('headerUserProfile').style.display = 'flex';
            }
        }
    },
    
    showProfile() {
        if (this.currentUser) {
            const profileInfo = `
                Profil de ${this.currentUser.prenoms} ${this.currentUser.nom}
                Email: ${this.currentUser.email}
                Téléphone: ${this.currentUser.telephone}
                ID Client: ${this.currentUser.id}
            `;
            alert(profileInfo);
        }
    },
    
    async logout() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=logout'
            });
            
            const result = await response.json();
            
            this.currentUser = null;
            this.showAuthButtons();
            this.showNotification(result.message || 'Déconnexion réussie', 'success');
        } catch (error) {
            console.error('Erreur déconnexion:', error);
            // Déconnecter côté client même en cas d'erreur
            this.currentUser = null;
            this.showAuthButtons();
            this.showNotification('Déconnexion effectuée', 'success');
        }
    },
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `auth-notification auth-notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
};

// Initialiser le système d'authentification
document.addEventListener('DOMContentLoaded', () => {
    authSystem.init();
});
</script>
            </div>
            
            <div class="form-group">
                <label for="registerEmail">📧 Email</label>
                <input type="email" id="registerEmail" placeholder="votre@email.com" required>
            </div>
            
            <div class="form-group">
                <label for="registerPhone">📱 Téléphone</label>
                <input type="tel" id="registerPhone" placeholder="+225 07 12 34 56 78" required>
            </div>
            
            <div class="form-group">
                <label for="registerPassword">🔒 Mot de passe</label>
                <input type="password" id="registerPassword" placeholder="Minimum 6 caractères" required>
            </div>
            
            <div class="form-group">
                <label for="confirmPassword">🔒 Confirmer le mot de passe</label>
                <input type="password" id="confirmPassword" placeholder="Confirmez votre mot de passe" required>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="acceptTerms" required>
                    <span class="checkmark"></span>
                    J'accepte les <a href="cgu.html" target="_blank" style="color: #D4A853;">conditions d'utilisation</a>
                </label>
            </div>
            
            <button type="button" onclick="performRegister()" class="btn-primary full-width">
                ✨ Créer mon compte
            </button>
            
            <div class="login-links">
                <p><a href="#" onclick="switchToLogin()" style="color: #D4A853;">← Retour à la connexion</a></p>
            </div>
        </div>
        
        <!-- Formulaire mot de passe oublié -->
        <div id="forgotPasswordForm" style="display: none;">
            <h2>🔑 Mot de passe oublié</h2>
            <p style="color: #aaa; margin-bottom: 20px;">Entrez votre email pour recevoir un lien de réinitialisation</p>
            
            <div class="form-group">
                <label for="forgotEmail">📧 Email</label>
                <input type="email" id="forgotEmail" placeholder="votre@email.com" required>
            </div>
            
            <button type="button" onclick="performForgotPassword()" class="btn-primary full-width">
                📬 Envoyer le lien
            </button>
            
            <div class="login-links">
                <p><a href="#" onclick="switchToLogin()" style="color: #D4A853;">← Retour à la connexion</a></p>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
    margin: 5% auto;
    padding: 30px;
    border: 1px solid rgba(212, 168, 83, 0.3);
    border-radius: 15px;
    width: 90%;
    max-width: 450px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.close {
    color: #D4A853;
    float: right;
    font-size: 28px;
    font-weight: bold;
    position: absolute;
    top: 15px;
    right: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.close:hover {
    color: #F4E4B8;
    transform: scale(1.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #D4A853;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid rgba(212, 168, 83, 0.3);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 16px;
    transition: all 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #D4A853;
    box-shadow: 0 0 15px rgba(212, 168, 83, 0.3);
}

.form-group input::placeholder {
    color: #aaa;
}

.checkbox-label {
    display: flex;
    align-items: center;
    color: #fff !important;
    cursor: pointer;
    margin-bottom: 0 !important;
}

.checkbox-label input[type="checkbox"] {
    width: auto !important;
    margin-right: 10px;
}

.btn-primary {
    background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    color: #1A1A2E;
    padding: 15px 25px;
    border: none;
    border-radius: 25px;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(212, 168, 83, 0.4);
}

.full-width {
    width: 100%;
}

.login-links {
    text-align: center;
    margin-top: 20px;
}

.login-links p {
    margin: 10px 0;
}

.login-links a {
    color: #D4A853;
    text-decoration: none;
    transition: color 0.3s ease;
}

.login-links a:hover {
    color: #F4E4B8;
}

h2 {
    color: #D4A853;
    text-align: center;
    margin-bottom: 10px;
    font-size: 1.8em;
}

@media (max-width: 768px) {
    .modal-content {
        margin: 10% auto;
        padding: 20px;
        width: 95%;
    }
}
</style>

<script>
// Fonctions de gestion des modals de connexion
function openLoginModal() {
    document.getElementById('loginModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Reset forms
    switchToLogin();
}

function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Clear forms
    clearLoginForms();
}

function switchToLogin() {
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('registerForm').style.display = 'none';
    document.getElementById('forgotPasswordForm').style.display = 'none';
}

function switchToRegister() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('registerForm').style.display = 'block';
    document.getElementById('forgotPasswordForm').style.display = 'none';
}

function switchToForgotPassword() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('registerForm').style.display = 'none';
    document.getElementById('forgotPasswordForm').style.display = 'block';
}

function clearLoginForms() {
    // Clear login form
    document.getElementById('loginEmail').value = '';
    document.getElementById('loginPassword').value = '';
    document.getElementById('rememberMe').checked = false;
    
    // Clear register form
    document.getElementById('registerName').value = '';
    document.getElementById('registerEmail').value = '';
    document.getElementById('registerPhone').value = '';
    document.getElementById('registerPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('acceptTerms').checked = false;
    
    // Clear forgot password form
    document.getElementById('forgotEmail').value = '';
}

function performLogin() {
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const remember = document.getElementById('rememberMe').checked;
    
    if (!email || !password) {
        alert('Veuillez remplir tous les champs');
        return;
    }
    
    // Simulation de connexion (à remplacer par vraie API)
    console.log('Tentative de connexion:', { email, password, remember });
    
    // Pour la démo, accepter tout email/mot de passe
    if (email && password) {
        // Sauvegarder les infos utilisateur
        const userData = {
            name: email.split('@')[0],
            email: email,
            phone: '+225 07 12 34 56 78'
        };
        
        localStorage.setItem('isLoggedIn', 'true');
        localStorage.setItem('userData', JSON.stringify(userData));
        
        // Fermer modal et mettre à jour UI
        closeLoginModal();
        updateAuthenticationUI();
        
        alert('Connexion réussie !');
    } else {
        alert('Email ou mot de passe incorrect');
    }
}

function performRegister() {
    const name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const phone = document.getElementById('registerPhone').value;
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const acceptTerms = document.getElementById('acceptTerms').checked;
    
    if (!name || !email || !phone || !password || !confirmPassword) {
        alert('Veuillez remplir tous les champs');
        return;
    }
    
    if (password !== confirmPassword) {
        alert('Les mots de passe ne correspondent pas');
        return;
    }
    
    if (!acceptTerms) {
        alert('Veuillez accepter les conditions d\'utilisation');
        return;
    }
    
    // Simulation d'inscription (à remplacer par vraie API)
    console.log('Tentative d\'inscription:', { name, email, phone, password });
    
    // Sauvegarder les infos utilisateur
    const userData = {
        name: name,
        email: email,
        phone: phone
    };
    
    localStorage.setItem('isLoggedIn', 'true');
    localStorage.setItem('userData', JSON.stringify(userData));
    
    // Fermer modal et mettre à jour UI
    closeLoginModal();
    updateAuthenticationUI();
    
    alert('Compte créé avec succès !');
}

function performForgotPassword() {
    const email = document.getElementById('forgotEmail').value;
    
    if (!email) {
        alert('Veuillez entrer votre adresse email');
        return;
    }
    
    // Simulation d'envoi d'email (à remplacer par vraie API)
    console.log('Demande de réinitialisation pour:', email);
    
    alert('Un lien de réinitialisation a été envoyé à votre adresse email');
    closeLoginModal();
}

// Fonction pour mettre à jour l'UI selon l'état de connexion
function updateAuthenticationUI() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    
    const guestNav = document.getElementById('guest-nav');
    const loggedNav = document.getElementById('logged-nav');
    const userNameSpan = document.getElementById('user-name');
    
    if (isLoggedIn && userData.name) {
        if (guestNav) guestNav.style.display = 'none';
        if (loggedNav) loggedNav.style.display = 'block';
        if (userNameSpan) userNameSpan.textContent = userData.name;
    } else {
        if (guestNav) guestNav.style.display = 'block';
        if (loggedNav) loggedNav.style.display = 'none';
    }
}

// Vérifier l'état de connexion au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    updateAuthenticationUI();
});

// Fermer modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('loginModal');
    if (event.target === modal) {
        closeLoginModal();
    }
}

// Fonction de compatibilité pour les anciens formulaires
function submitLogin() {
    console.log('🔄 submitLogin() appelée - redirection vers performLogin()');
    return performLogin();
}

// Définir les fonctions globalement pour l'accès depuis HTML
window.submitLogin = submitLogin;
window.performLogin = performLogin;
window.isLoggedIn = isLoggedIn;
window.currentUser = currentUser;
</script>
