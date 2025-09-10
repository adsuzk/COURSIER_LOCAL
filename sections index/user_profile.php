<?php
// sections index/user_profile.php - Profil utilisateur pour le modal de compte
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['client_id'])) {
    echo '<div class="error-message">Veuillez vous connecter pour accéder à votre profil.</div>';
    exit;
}
?>

<div class="user-profile-container">
    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="profile-info">
            <h3 id="userName">Chargement...</h3>
            <p id="userEmail">email@exemple.com</p>
        </div>
        <button class="edit-profile-btn" onclick="toggleEditMode()">
            <i class="fas fa-edit"></i> Modifier
        </button>
    </div>

    <!-- Mode Affichage -->
    <div id="profileDisplay" class="profile-content">
        <div class="info-group">
            <label>Nom :</label>
            <span id="displayNom">-</span>
        </div>
        <div class="info-group">
            <label>Prénoms :</label>
            <span id="displayPrenoms">-</span>
        </div>
        <div class="info-group">
            <label>Email :</label>
            <span id="displayEmail">-</span>
        </div>
        <div class="info-group">
            <label>Téléphone :</label>
            <span id="displayTelephone">-</span>
        </div>
    </div>

    <!-- Mode Édition -->
    <div id="profileEdit" class="profile-content" style="display: none;">
        <form id="updateProfileForm">
            <div class="form-group">
                <label for="editNom">Nom :</label>
                <input type="text" id="editNom" name="nom" required>
            </div>
            <div class="form-group">
                <label for="editPrenoms">Prénoms :</label>
                <input type="text" id="editPrenoms" name="prenoms" required>
            </div>
            <div class="form-group">
                <label for="editEmail">Email :</label>
                <input type="email" id="editEmail" name="email" required>
            </div>
            <div class="form-group">
                <label for="editTelephone">Téléphone :</label>
                <input type="tel" id="editTelephone" name="telephone" class="smart-phone-input" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
                <button type="button" class="btn-cancel" onclick="cancelEdit()">
                    <i class="fas fa-times"></i> Annuler
                </button>
            </div>
        </form>
    </div>

    <!-- Section Mot de Passe -->
    <div class="password-section">
        <h4>Changer le mot de passe</h4>
        <form id="changePasswordForm">
            <div class="form-group">
                <label for="currentPassword">Mot de passe actuel :</label>
                <input type="password" id="currentPassword" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="newPassword">Nouveau mot de passe :</label>
                <input type="password" id="newPassword" name="new_password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirmer le mot de passe :</label>
                <input type="password" id="confirmPassword" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="btn-change-password">
                <i class="fas fa-key"></i> Changer le mot de passe
            </button>
        </form>
    </div>
</div>

<style>
.user-profile-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
    padding: 20px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 215, 0, 0.3);
}

.profile-avatar {
    font-size: 60px;
    color: #FFD700;
}

.profile-info h3 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 24px;
}

.profile-info p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.edit-profile-btn {
    margin-left: auto;
    background: #FFD700;
    color: #000;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.edit-profile-btn:hover {
    background: #FFC700;
    transform: translateY(-1px);
}

.profile-content {
    background: rgba(255, 255, 255, 0.8);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
}

.info-group {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.info-group:last-child {
    border-bottom: none;
}

.info-group label {
    font-weight: 600;
    color: #333;
    min-width: 120px;
}

.info-group span {
    color: #555;
    flex: 1;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #FFD700;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn-save {
    background: #28a745;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-save:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.password-section {
    background: rgba(255, 255, 255, 0.8);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
}

.password-section h4 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #FFD700;
    padding-bottom: 10px;
}

.btn-change-password {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
    margin-top: 10px;
}

.btn-change-password:hover {
    background: #0056b3;
    transform: translateY(-1px);
}

.error-message {
    color: #dc3545;
    text-align: center;
    padding: 20px;
    background: rgba(220, 53, 69, 0.1);
    border-radius: 6px;
    border: 1px solid rgba(220, 53, 69, 0.3);
}
</style>

<script>
// Charger les données du profil au chargement
document.addEventListener('DOMContentLoaded', function() {
    loadUserProfile();
});

function loadUserProfile() {
    fetch('api/auth.php?action=check_session')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.client) {
                const client = data.client;
                document.getElementById('userName').textContent = `${client.nom} ${client.prenoms}`;
                document.getElementById('userEmail').textContent = client.email;
                
                document.getElementById('displayNom').textContent = client.nom;
                document.getElementById('displayPrenoms').textContent = client.prenoms;
                document.getElementById('displayEmail').textContent = client.email;
                document.getElementById('displayTelephone').textContent = client.telephone;
                
                document.getElementById('editNom').value = client.nom;
                document.getElementById('editPrenoms').value = client.prenoms;
                document.getElementById('editEmail').value = client.email;
                document.getElementById('editTelephone').value = client.telephone;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
}

function toggleEditMode() {
    const displayMode = document.getElementById('profileDisplay');
    const editMode = document.getElementById('profileEdit');
    
    if (displayMode.style.display === 'none') {
        // Retour au mode affichage
        displayMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        // Mode édition
        displayMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function cancelEdit() {
    loadUserProfile(); // Recharger les données originales
    toggleEditMode(); // Retour au mode affichage
}

// Gestion du formulaire de mise à jour du profil
document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_profile');
    
    fetch('api/profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Profil mis à jour avec succès', 'success');
            loadUserProfile();
            toggleEditMode();
        } else {
            showMessage(data.error || 'Erreur lors de la mise à jour', 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showMessage('Erreur de connexion', 'error');
    });
});

// Gestion du formulaire de changement de mot de passe
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        showMessage('Les mots de passe ne correspondent pas', 'error');
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'change_password');
    
    fetch('api/profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Mot de passe changé avec succès', 'success');
            this.reset();
        } else {
            showMessage(data.error || 'Erreur lors du changement', 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showMessage('Erreur de connexion', 'error');
    });
});

function showMessage(message, type) {
    // Utiliser le système de notification existant du site
    if (typeof window.showMessage === 'function') {
        window.showMessage(message, type);
    } else {
        alert(message);
    }
}
</script>
