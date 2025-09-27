<?php
// sections/js_authentication.php - Fonctions d'authentification et gestion utilisateur
?>
    <script>
    // Variables globales d'authentification
    // Variables d'authentification - Namespace global pour éviter les conflits
    if (!window.AuthConfig) {
        window.AuthConfig = {
            currentUser: null,
            isLoggedIn: false
        };
    }
    
    // Fonction de connexion
    function login() {
        // Désactiver l'avertissement beforeunload pour la connexion
        window.onbeforeunload = null;
        window._skipBeforeUnloadCheck = true;
        
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        if (!email || !password) {
            showMessage('Veuillez remplir tous les champs', 'error');
            return;
        }
        
        // Afficher le chargement
        showLoginLoading(true);
        
        // Simulation d'appel API (à remplacer par votre vraie API)
        setTimeout(() => {
            // Ici vous feriez un appel AJAX à votre API de connexion
            const loginData = {
                email: email,
                password: password
            };
            
            // Simulation de réponse API
            if (email === 'test@example.com' && password === 'password') {
                currentUser = {
                    id: 1,
                    name: 'Utilisateur Test',
                    email: email,
                    phone: '+225 07 12 34 56 78'
                };
                isLoggedIn = true;
                
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
                localStorage.setItem('isLoggedIn', 'true');
                
                updateUIAfterLogin();
                closeModal('loginModal');
                showMessage('Connexion réussie !', 'success');
            } else {
                showMessage('Email ou mot de passe incorrect', 'error');
            }
            
            showLoginLoading(false);
        }, 1000);
    }
    
    function showLoginLoading(show) {
        const loginBtn = document.querySelector('#loginModal .btn-primary');
        if (show) {
            loginBtn.innerHTML = '🔄 Connexion...';
            loginBtn.disabled = true;
        } else {
            loginBtn.innerHTML = 'Se connecter';
            loginBtn.disabled = false;
        }
    }
    
    // Fonction d'inscription
    function register() {
        // Désactiver l'avertissement beforeunload pour l'inscription
        window.onbeforeunload = null;
        window._skipBeforeUnloadCheck = true;
        
        const name = document.getElementById('register-name').value;
        const email = document.getElementById('register-email').value;
        const phone = document.getElementById('register-phone').value;
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('register-confirm-password').value;
        
        if (!name || !email || !phone || !password || !confirmPassword) {
            showMessage('Veuillez remplir tous les champs', 'error');
            return;
        }
        
        if (password !== confirmPassword) {
            showMessage('Les mots de passe ne correspondent pas', 'error');
            return;
        }
        
        if (password.length < 6) {
            showMessage('Le mot de passe doit contenir au moins 6 caractères', 'error');
            return;
        }
        
        if (!isValidEmail(email)) {
            showMessage('Format d\'email invalide', 'error');
            return;
        }
        
        if (!isValidIvorianPhone(phone)) {
            showMessage('Format de numéro ivoirien invalide', 'error');
            return;
        }
        
        showRegisterLoading(true);
        
        // Simulation d'appel API d'inscription
        setTimeout(() => {
            const userData = {
                name: name,
                email: email,
                phone: phone,
                password: password
            };
            
            // Simulation de création de compte
            currentUser = {
                id: Date.now(),
                name: name,
                email: email,
                phone: phone
            };
            isLoggedIn = true;
            
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            localStorage.setItem('isLoggedIn', 'true');
            
            updateUIAfterLogin();
            closeModal('signupModal');
            showMessage('Inscription réussie ! Bienvenue !', 'success');
            showRegisterLoading(false);
        }, 1500);
    }
    
    function showRegisterLoading(show) {
        const registerBtn = document.querySelector('#signupModal .btn-primary');
        if (show) {
            registerBtn.innerHTML = '🔄 Inscription...';
            registerBtn.disabled = true;
        } else {
            registerBtn.innerHTML = 'S\'inscrire';
            registerBtn.disabled = false;
        }
    }
    
    // Fonction de déconnexion
    function logout() {
        // Désactiver l'avertissement beforeunload pour la déconnexion
        window.onbeforeunload = null;
        window._skipBeforeUnloadCheck = true;
        
        if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
            // Appel API pour détruire la session côté serveur
            fetch(ROOT_PATH + '/api/auth.php?action=logout', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Nettoyage local
                    currentUser = null;
                    isLoggedIn = false;
                    localStorage.removeItem('currentUser');
                    localStorage.removeItem('isLoggedIn');
                    updateUIAfterLogout();
                    showMessage('Vous avez été déconnecté', 'info');
                    // Rechargement pour refléter la session PHP
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showMessage('Erreur de déconnexion', 'error');
                }
            })
            .catch(() => {
                showMessage('Erreur réseau', 'error');
            });
        }
    }
    
    // Mise à jour de l'interface après connexion
    function updateUIAfterLogin() {
        const authButtons = document.querySelector('.auth-buttons');
        const userMenu = document.querySelector('.user-menu');
        
        if (authButtons) authButtons.style.display = 'none';
        if (userMenu) {
            userMenu.style.display = 'block';
            document.querySelector('.user-name').textContent = currentUser.name;
        }
        
        // Pré-remplir les champs du formulaire
        if (currentUser.phone) {
            document.getElementById('phone').value = currentUser.phone;
        }
        
        // Mettre à jour les onglets
        updateOrderTabs();
    }
    
    // Mise à jour de l'interface après déconnexion
    function updateUIAfterLogout() {
        const authButtons = document.querySelector('.auth-buttons');
        const userMenu = document.querySelector('.user-menu');
        
        if (authButtons) authButtons.style.display = 'block';
        if (userMenu) userMenu.style.display = 'none';
        
        // Vider les champs du formulaire
        document.getElementById('phone').value = '';
        
        // Mettre à jour les onglets
        updateOrderTabs();
    }
    
    // Vérification de l'état de connexion au chargement
    function checkAuthState() {
        const savedUser = localStorage.getItem('currentUser');
        const savedLoginState = localStorage.getItem('isLoggedIn');
        
        if (savedUser && savedLoginState === 'true') {
            currentUser = JSON.parse(savedUser);
            isLoggedIn = true;
            updateUIAfterLogin();
        }
    }
    
    // Fonction pour afficher le modal de compte
    function showAccount() {
        if (!isLoggedIn) {
            showModal('loginModal');
            return;
        }
        
        // Pré-remplir les informations dans le modal de compte
        document.getElementById('account-name').value = currentUser.name || '';
        document.getElementById('account-email').value = currentUser.email || '';
        document.getElementById('account-phone').value = currentUser.phone || '';
        
        showModal('accountModal');
    }
    
    // Fonction pour sauvegarder les modifications du compte
    function saveAccount() {
        const name = document.getElementById('account-name').value;
        const email = document.getElementById('account-email').value;
        const phone = document.getElementById('account-phone').value;
        
        if (!name || !email || !phone) {
            showMessage('Veuillez remplir tous les champs', 'error');
            return;
        }
        
        if (!isValidEmail(email)) {
            showMessage('Format d\'email invalide', 'error');
            return;
        }
        
        if (!isValidIvorianPhone(phone)) {
            showMessage('Format de numéro ivoirien invalide', 'error');
            return;
        }
        
        // Mettre à jour les données utilisateur
        currentUser.name = name;
        currentUser.email = email;
        currentUser.phone = phone;
        
        localStorage.setItem('currentUser', JSON.stringify(currentUser));
        
        // Mettre à jour l'interface
        document.querySelector('.user-name').textContent = currentUser.name;
        document.getElementById('phone').value = currentUser.phone;
        
        closeModal('accountModal');
        showMessage('Informations mises à jour avec succès', 'success');
    }
    
    // Fonction pour récupérer le mot de passe
    function forgotPassword() {
        const email = document.getElementById('forgot-email').value;
        
        if (!email) {
            showMessage('Veuillez saisir votre adresse email', 'error');
            return;
        }
        
        if (!isValidEmail(email)) {
            showMessage('Format d\'email invalide', 'error');
            return;
        }
        
        showForgotLoading(true);
        
        // Simulation d'envoi d'email de récupération
        setTimeout(() => {
            showMessage('Un email de récupération a été envoyé à ' + email, 'success');
            closeModal('forgotModal');
            showForgotLoading(false);
        }, 2000);
    }
    
    function showForgotLoading(show) {
        const forgotBtn = document.querySelector('#forgotModal .btn-primary');
        if (show) {
            forgotBtn.innerHTML = '🔄 Envoi...';
            forgotBtn.disabled = true;
        } else {
            forgotBtn.innerHTML = 'Envoyer';
            forgotBtn.disabled = false;
        }
    }
    
    // Fonction pour basculer entre les onglets de connexion/inscription
    function showTab(tabName) {
        const tabs = document.querySelectorAll('.tab-content');
        const tabButtons = document.querySelectorAll('.tab-button');
        
        tabs.forEach(tab => {
            tab.classList.remove('active');
        });
        
        tabButtons.forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.getElementById(tabName + 'Tab').classList.add('active');
        document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
    }
    </script>
