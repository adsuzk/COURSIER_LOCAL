// assets/js/connexion_modal.js
// Gestionnaire unifié du modal : Connexion / Inscription / Mot de passe oublié / Mon Compte

document.addEventListener('DOMContentLoaded', () => {
  // Sélecteurs principaux
  const btnOpenConnexion = document.getElementById('openConnexionLink');
  // Chemin de base de l'application (ex: /COURSIER_LOCAL)
  const BASE_PATH = '/' + window.location.pathname.split('/')[1];
  const modal            = document.getElementById('connexionModal');
  const body             = document.getElementById('connexionModalBody');
  const btnCloseModal    = document.getElementById('closeConnexionModal');

  // Affiche/masque le modal
  function showModal() { modal.style.display = 'block'; }
  function hideModal() { modal.style.display = 'none'; body.innerHTML = ''; }

  // Événements globaux pour ouvrir/fermer
  if (btnOpenConnexion) btnOpenConnexion.addEventListener('click', e => { e.preventDefault(); loadLogin(); });
  if (btnCloseModal)   btnCloseModal  .addEventListener('click', hideModal);
  window.addEventListener('click', e => { if (e.target === modal) hideModal(); });

  // Délégation click et submit dans le body du modal
  body.addEventListener('click',  handleClick);
  body.addEventListener('submit', handleSubmit);

  // --------------------------------
  // Chargement des partials PHP
  // --------------------------------
  function loadView(path) {
    // Construire l’URL absolue de la vue
    const url = BASE_PATH + '/' + path;
    fetch(encodeURI(url))
      .then(r => r.text())
      .then(html => { body.innerHTML = html; showModal(); initPhoneFormatting(); })
      .catch(console.error);
  }
  function loadLogin()    { loadView('sections index/connexion.php'); }
  function loadRegister() { loadView('sections index/inscription.php'); }
  function loadForgot()   { loadView('sections index/forgot_password.php'); }
  // Fonctions globales pour liens externes
  window.openConnexionModal = loadLogin;
  window.openRegisterModal = loadRegister;
  window.openForgotModal = loadForgot;

  // --------------------------------
  // Mon Compte: onglets Profil/Commandes
  // --------------------------------
  function loadAccount(client) {
    window.currentClient = client;
    body.innerHTML = `
      <div class="account-modal">
        <h2><i class="fas fa-user-circle"></i> Mon Compte</h2>
        <div class="account-tabs">
          <button id="profileTabBtn" class="tab-btn active">Mon Profil</button>
          <button id="ordersTabBtn"  class="tab-btn">Mes Commandes</button>
        </div>
        <div id="profileTab" class="tab-content"></div>
        <div id="ordersTab"  class="tab-content" style="display:none;"></div>
      </div>
    `;
    showModal(); renderProfile(window.currentClient); loadOrders();
  }
  window.openAccountModal = () => {
    fetch('api/auth.php?action=check_session', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => d.success ? loadAccount(d.client) : loadLogin())
      .catch(console.error);
  };

  // --------------------------------
  // Rendu Profil
  // --------------------------------
  function renderProfile(client) {
    document.getElementById('profileTab').innerHTML = `
      <div class="profile-info">
        <p><strong>Nom :</strong> ${client.nom}</p>
        <p><strong>Prénom(s) :</strong> ${client.prenoms}</p>
        <p><strong>Email :</strong> ${client.email}</p>
        <p><strong>Téléphone :</strong> ${client.telephone}</p>
      </div>
      <div class="profile-actions">
        <button id="profileEditBtn"      class="btn-action">Modifier le profil</button>
        <button id="changePasswordBtn"   class="btn-action">Changer le mot de passe</button>
        <button id="logoutBtn"           class="btn-action">Se déconnecter</button>
      </div>
    `;
  }

  // --------------------------------
  // Commandes
  // --------------------------------
  function loadOrders() {
    const tgt = document.getElementById('ordersTab');
    tgt.innerHTML = '<p>Chargement...</p>';
  fetch('api/auth.php', { method:'POST', credentials: 'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=orders' })
      .then(r => r.json())
      .then(d => d.success ? displayOrders(d.orders) : tgt.innerHTML = '<p>Erreur de chargement</p>')
      .catch(()=>tgt.innerHTML = '<p>Erreur réseau</p>');
  }
  function displayOrders(orders) {
    const tgt = document.getElementById('ordersTab');
    if (!orders.length) return tgt.innerHTML = '<p>Aucune commande.</p>';
    tgt.innerHTML = orders.map(o => `
      <div class="order-item">
        <strong>#${o.numero_commande} - ${getStatusText(o.statut)}</strong>
        <p>${o.date_formatted}</p>
        <p>${o.adresse_depart} → ${o.adresse_arrivee}</p>
        <p>${o.montant} FCFA</p>
      </div>
    `).join('');
  }

  // --------------------------------
  // Interactions
  // --------------------------------
  function handleClick(e) {
    switch(e.target.id) {
      case 'openRegisterModal': e.preventDefault(); loadRegister(); break;
      case 'openForgotModal':   e.preventDefault(); loadForgot();   break;
      case 'backToLoginLink':   e.preventDefault(); loadLogin();    break;
      case 'profileTabBtn':     switchTab('profile');               break;
      case 'ordersTabBtn':      switchTab('orders');                break;
      case 'profileEditBtn':    e.preventDefault(); startEditProfile(); break;
      case 'changePasswordBtn': e.preventDefault(); startChangePassword(); break;
      case 'logoutBtn':         e.preventDefault(); logout();       break;
    }
  }
  function handleSubmit(e) {
    e.preventDefault(); const f = e.target;
    switch(f.id) {
      case 'loginForm':          handleLogin(f);           break;
      case 'registerForm':       handleRegister(f);        break;
      case 'forgotForm':         handleForgot(f);          break;
      case 'editProfileForm':    handleEditProfile(f);     break;
      case 'changePasswordForm': handleChangePassword(f);  break;
    }
  }

  // --------------------------------
  // Utilitaires
  // --------------------------------
  function switchTab(t) {
    document.getElementById('profileTab') .style.display = t==='profile'?'block':'none';
    document.getElementById('ordersTab') .style.display = t==='orders' ?'block':'none';
    document.getElementById('profileTabBtn').classList.toggle('active', t==='profile');
    document.getElementById('ordersTabBtn') .classList.toggle('active', t==='orders');
  }
  function initPhoneFormatting() { /*...*/ }
  function showNotification(msg, type) { /*...*/ }
  function getStatusText(s) { /*...*/ }

  // --------------------------------
  // Formulaires API
  // --------------------------------
  // Gère la connexion d’un utilisateur
  function handleLogin(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Connexion...';
    const fd = new FormData(form);
    fd.append('action', 'login');
    fetch('api/auth.php', { method: 'POST', credentials: 'same-origin', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          hideModal();
          window.currentClient = data.client;
          updateUIForLoggedInUser(data.client);
          alert('Connexion réussie');
        } else {
          alert(data.error || 'Erreur de connexion');
        }
      })
      .catch(() => alert('Erreur réseau'))
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      });
  }
  function handleRegister(f) { /*...*/ }
  function handleForgot(f)   { /*...*/ }
  function handleEditProfile(form) {
    const fd = new FormData(form);
    fd.append('action', 'updateProfile');
    fetch('api/auth.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          window.currentClient = d.client;
          showNotification('Profil mis à jour avec succès', 'success');
          loadAccount(d.client);
        } else {
          showNotification(d.error || 'Erreur lors de la mise à jour', 'error');
        }
      })
      .catch(() => showNotification('Erreur réseau', 'error'));
  }
  function handleChangePassword(f) {
    const current = f.currentPassword.value;
    const nw = f.newPassword.value;
    const confirmVal = f.confirmPassword.value;
    if (nw !== confirmVal) {
      showNotification('Les mots de passe ne correspondent pas', 'error');
      return;
    }
    const fd = new FormData();
    fd.append('action', 'changePassword');
    fd.append('currentPassword', current);
    fd.append('newPassword', nw);
    fetch('api/auth.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          showNotification('Mot de passe changé avec succès', 'success');
          loadAccount(window.currentClient);
        } else {
          showNotification(d.error || 'Erreur lors du changement', 'error');
        }
      })
      .catch(() => showNotification('Erreur réseau', 'error'));
  }

  // ------------ FONCTIONS SOUS-FORMS ------------
  // Modifier le profil
  window.startEditProfile = function() {
    const c = window.currentClient || {};
    const tab = document.getElementById('profileTab');
    tab.innerHTML = `
      <h3>Modifier mon profil</h3>
      <form id="editProfileForm">
        <label>Email</label>
        <input type="email"    name="email" value="${c.email}" required />
        <label>Téléphone</label>
        <input type="text"     name="telephone" class="smart-phone-input" value="${c.telephone}" required />
        <label>Mot de passe actuel</label>
        <input type="password" name="currentPassword" minlength="5" maxlength="5" required />
        <button type="submit">Enregistrer</button>
        <button type="button" onclick="loadAccount(window.currentClient)">Annuler</button>
      </form>
    `;
    initPhoneFormatting();
  };
  // Changer de mot de passe
  window.startChangePassword = function() {
    const tab = document.getElementById('profileTab');
    tab.innerHTML = `
      <h3>Changer le mot de passe</h3>
      <form id="changePasswordForm">
        <label>Actuel (5 car.)</label>
        <input type="password" name="currentPassword" minlength="5" maxlength="5" required />
        <label>Nouveau (5 car.)</label>
        <input type="password" name="newPassword" minlength="5" maxlength="5" required />
        <label>Confirmer</label>
        <input type="password" name="confirmPassword" minlength="5" maxlength="5" required />
        <button type="submit">Valider</button>
        <button type="button" onclick="loadAccount(window.currentClient)">Annuler</button>
      </form>
    `;
  };

  // --------------------------------
  // Mise à jour de la navigation
  // --------------------------------
  function updateUIForLoggedInUser(client) {
    const guestNav = document.getElementById('guestNav');
    const userNav  = document.getElementById('userNav');
    if (guestNav) guestNav.style.display = 'none';
    if (userNav) {
      userNav.style.display = 'block';
      const nameEl = userNav.querySelector('.user-name');
      if (nameEl) nameEl.textContent = client.prenoms + ' ' + client.nom;
      // Préremplir le champ téléphone de l'expéditeur et le rendre non modifiable
      const senderInput = document.getElementById('senderPhone');
      if (senderInput) {
        senderInput.value = client.telephone || '';
        senderInput.readOnly = true;
      }
    }
  }

  function updateUIForGuestUser() {
    const guestNav = document.getElementById('guestNav');
    const userNav  = document.getElementById('userNav');
    if (guestNav) guestNav.style.display = 'block';
    if (userNav) userNav.style.display = 'none';
  }

  function logout() {
    // Appel à l'API pour fermer la session
    fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=logout'
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        hideModal();
        updateUIForGuestUser();
        showNotification(data.message || 'Déconnexion réussie', 'success');
        // Rediriger vers la page d'accueil
        window.location.href = 'index.php';
      } else {
        showNotification(data.error || 'Erreur de déconnexion', 'error');
      }
    })
    .catch(() => showNotification('Erreur réseau', 'error'));
  }
  // Exposer la fonction logout globalement pour le lien de header
  window.logout = logout;
  // À l'initialisation, vérifier la session ouverte et pré-remplir le téléphone expéditeur
  fetch('api/auth.php?action=check_session', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        window.currentClient = d.client;
        updateUIForLoggedInUser(d.client);
      }
    })
    .catch(console.error);
});
