// assets/js/connexion_modal.js
// Async modal loader for Connexion Particulier and user profile modal

const __resolveBasePath = () => {
  if (typeof window !== 'undefined') {
    if (typeof window.ROOT_PATH === 'string' && window.ROOT_PATH.length) {
      return window.ROOT_PATH.replace(/\/$/, '');
    }
    const path = (window.location && window.location.pathname) ? window.location.pathname : '';
    if (!path) return '';
    return path.replace(/\\/g, '/').replace(/\/[^\/]*$/, '') || '';
  }
  return '';
};

if (typeof window !== 'undefined') {
  if (!window.__SUZOSKY_BASE_PATH) {
    window.__SUZOSKY_BASE_PATH = __resolveBasePath();
  }
  if (!window.suzoskyBuildUrl) {
    window.suzoskyBuildUrl = function(relativePath = '') {
      const base = window.__SUZOSKY_BASE_PATH || '';
      if (!relativePath) return base || '';
      const normalized = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
      return `${base}${normalized}` || normalized;
    };
  }
}

const buildUrl = (typeof window !== 'undefined' && window.suzoskyBuildUrl)
  ? window.suzoskyBuildUrl
  : (relativePath = '') => {
      const base = __resolveBasePath();
      if (!relativePath) return base || '';
      const normalized = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
      return `${base}${normalized}` || normalized;
    };


// Événement principal
document.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('openConnexionLink');
  const modal = document.getElementById('connexionModal'); // Ensure modal is defined
  const closeBtn = document.getElementById('closeConnexionModal');
  const body = document.getElementById('connexionModalBody');
  // Le bouton openConnexionLink peut ne pas exister (utilisateur connecté)
  // Ne pas retourner dans ce cas: on veut quand même initialiser le modal et exposer openAccountModal
  if (!modal || !closeBtn || !body) return;

  if (openBtn) openBtn.addEventListener('click', async e => {
    e.preventDefault();
    try {
      // Load the login modal content dynamically
  const modalUrl = buildUrl('/sections_index/connexion.php');
      const res = await fetch(modalUrl);
      const html = await res.text();
      body.innerHTML = html;
      modal.style.display = 'flex';
      const loginForm = body.querySelector('#loginForm');
      if (loginForm) {
        // Submit login
        loginForm.addEventListener('submit', async ev => {
          ev.preventDefault();
          const btn = loginForm.querySelector('button[type="submit"]');
          const orig = btn.innerHTML;
          btn.disabled = true; btn.innerHTML = 'Connexion...';
          const fd = new FormData(loginForm);
          fd.append('action', 'login');
          try {
            const apiRes = await fetch((window.ROOT_PATH || '') + '/api/auth.php?action=login', {
              method: 'POST',
              credentials: 'same-origin',
              body: fd
            });
            const data = await apiRes.json();
            if (data.success) {
                window.skipBeforeUnload = true;
                window.location.reload();
            } else {
                alert(data.error || 'Erreur de connexion');
            }
          } catch (err) {
            console.error('Login error:', err);
            const message = err && err.message ? err.message : 'Veuillez réessayer plus tard';
            alert('Erreur réseau : ' + message);
          } finally {
            btn.disabled = false; btn.innerHTML = orig;
          }
        });
      }
      // Load registration form on click
      const registerLink = body.querySelector('#openRegisterModal');
      if (registerLink) {
        registerLink.addEventListener('click', async ev => {
          ev.preventDefault();
          try {
            const resReg = await fetch(buildUrl('/sections_index/inscription.php'));
            const htmlReg = await resReg.text();
            body.innerHTML = htmlReg;
            // Back to login link
            const backLogin = body.querySelector('#backToLoginLink');
            if (backLogin) backLogin.addEventListener('click', e2 => { e2.preventDefault(); openBtn.click(); });
            // Bind register submission
            const registerForm = body.querySelector('#registerForm');
            if (registerForm) registerForm.addEventListener('submit', ev2 => {
              ev2.preventDefault();
              handleRegister(registerForm);
            });
          } catch (err) {
            console.error('Erreur chargement modal register:', err);
          }
        });
      }
      // Load forgot password form on click
      const forgotLink = body.querySelector('#openForgotModal');
      if (forgotLink) {
        forgotLink.addEventListener('click', async ev => {
          ev.preventDefault();
          try {
            const resForgot = await fetch(buildUrl('/sections_index/forgot_password.php'));
            const htmlForgot = await resForgot.text();
            body.innerHTML = htmlForgot;
            const backLogin2 = body.querySelector('#backToLoginLink');
            if (backLogin2) backLogin2.addEventListener('click', e2 => { e2.preventDefault(); openBtn.click(); });
            const forgotForm = body.querySelector('#forgotForm');
            if (forgotForm) forgotForm.addEventListener('submit', ev2 => { ev2.preventDefault(); handleForgot(forgotForm); });
          } catch (err) {
            console.error('Erreur chargement modal forgot:', err);
          }
        });
      }
    } catch (err) {
      console.error('Erreur chargement modal login:', err);
    }
  });

  closeBtn.addEventListener('click', () => { modal.style.display = 'none'; body.innerHTML = ''; });
  window.addEventListener('click', e => { if (e.target === modal) { modal.style.display = 'none'; body.innerHTML = ''; } });

  // --------------------------------
  // Mon Compte: Modal de profil simplifié
  // --------------------------------
  function loadAccount(client) {
    window.currentClient = client;
      body.innerHTML = `
      <div class="user-profile-modal">
        <h2><i class="fas fa-user-circle"></i> Mon Profil</h2>
        <div id="profileContent"></div>
      </div>
    `;
      showModal('connexionModal');
      renderProfile(window.currentClient);
  }
  window.openAccountModal = () => {
  fetch(buildUrl('/api/auth.php?action=check_session'), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => d.success ? loadAccount(d.client) : loadLogin())
      .catch(console.error);
  };

  // --------------------------------
  // Rendu Profil - Affichage des données utilisateur
  // --------------------------------
  function renderProfile(client) {
    document.getElementById('profileContent').innerHTML = `
      <div class="profile-info">
        <div class="profile-field">
          <label>Nom :</label>
          <span>${client.nom}</span>
        </div>
        <div class="profile-field">
          <label>Prénom(s) :</label>
          <span>${client.prenoms}</span>
        </div>
        <div class="profile-field">
          <label>Numéro de téléphone :</label>
          <span>${client.telephone}</span>
        </div>
        <div class="profile-field">
          <label>Adresse mail :</label>
          <span>${client.email}</span>
        </div>
      </div>
      <div class="profile-actions">
        <button id="profileEditBtn" class="btn-primary">Modifier le profil</button>
      </div>
    `;
  }

  // --------------------------------
  // Commandes
  // --------------------------------
  function loadOrders() {
    const tgt = document.getElementById('ordersTab');
    tgt.innerHTML = '<p>Chargement...</p>';
  fetch(buildUrl('/api/auth.php'), { method:'POST', credentials: 'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=orders' })
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
  // Interactions - Gestion des clics sur les boutons
  // --------------------------------
  function handleClick(e) {
      // Gère les interactions globales du modal
      switch(e.target.id) {
      case 'openRegisterModal': e.preventDefault(); loadRegister(); break;
      case 'openForgotModal':   e.preventDefault(); loadForgot();   break;
      case 'backToLoginLink':   e.preventDefault(); loadLogin();    break;
      case 'profileEditBtn':    e.preventDefault(); startEditProfile(); break;
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
  function showNotification(message, type = 'info') {
    if (!message) return;
    const resolvedType = ['success', 'error', 'info'].includes(type) ? type : 'info';
    const icons = {
      success: 'check-circle',
      error: 'exclamation-circle',
      info: 'info-circle'
    };

    const notification = document.createElement('div');
    notification.className = `auth-notification auth-notification-${resolvedType}`;

    const iconEl = document.createElement('i');
    iconEl.className = `fas fa-${icons[resolvedType]}`;
    const textEl = document.createElement('span');
    textEl.textContent = message;

    notification.append(iconEl, textEl);

    document.body.appendChild(notification);

    requestAnimationFrame(() => {
      notification.classList.add('show');
    });

    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 3200);
  }
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
  fetch(buildUrl('/api/auth.php'), { method: 'POST', credentials: 'same-origin', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          hideModal();
          window.currentClient = data.client;
          updateUIForLoggedInUser(data.client);
          if (typeof window.refreshCoursierAvailabilityForClient === 'function') {
            window.refreshCoursierAvailabilityForClient();
          }
          showNotification(data.message || 'Connexion réussie', 'success');
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
  function handleRegister(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';

    const nom = (form.nom?.value || '').trim();
    const prenoms = (form.prenoms?.value || '').trim();
    const email = (form.email?.value || '').trim();
    const telephone = (form.telephone?.value || '').trim();
    const password = (form.password?.value || '').trim();
    const confirmPassword = (form.confirmPassword?.value || '').trim();

    if (!nom || !prenoms || !email || !telephone || !password) {
      alert('Veuillez remplir tous les champs requis.');
      return;
    }

    if (password !== confirmPassword) {
      alert('Les mots de passe ne correspondent pas.');
      return;
    }

    if (password.length && password.length !== 5) {
      alert('Le mot de passe doit contenir exactement 5 caractères.');
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Création en cours...';
    }

    const fd = new FormData(form);
    fd.set('nom', nom);
    fd.set('prenoms', prenoms);
    fd.set('email', email);
    fd.set('telephone', telephone);
    fd.set('password', password);
    fd.set('action', 'register');

  fetch(buildUrl('/api/auth.php'), {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          window.currentClient = data.client;
          updateUIForLoggedInUser(data.client);
          if (modal) {
            modal.style.display = 'none';
          }
          body.innerHTML = '';
          alert('Compte créé avec succès !');
          // Vérifie la session côté serveur pour synchroniser les données locales
          return fetch(buildUrl('/api/auth.php?action=check_session'), { credentials: 'same-origin' });
        }
        throw new Error(data.error || 'Erreur lors de la création du compte');
      })
      .then(res => res ? res.json() : null)
      .then(sessionData => {
        if (sessionData && sessionData.success && sessionData.client) {
          window.currentClient = sessionData.client;
          updateUIForLoggedInUser(sessionData.client);
        }
      })
      .catch(err => {
        alert(err.message || 'Erreur réseau');
      })
      .finally(() => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText || 'Créer mon compte';
        }
      });
  }
  function handleForgot(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    // Retrieve input by name login or forgotEmail
    const loginValue = (form.login?.value || form.forgotEmail?.value || '').trim();
    if (!loginValue) {
      alert('Veuillez fournir votre email ou téléphone.');
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Envoi en cours...';
    }
    
    // Utiliser l'endpoint indépendant au lieu de l'API principale
    const formData = new FormData();
    formData.append('action', 'reset_password_request');
    formData.append('email_or_phone', loginValue);
    
  fetch(buildUrl('/EMAIL_SYSTEM/api.php'), {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          alert(data.message || 'Lien de réinitialisation envoyé si le compte existe.');
          // Fermer et nettoyer le modal
          const modal = document.getElementById('connexionModal');
          const body = document.getElementById('connexionModalBody');
          modal.style.display = 'none';
          body.innerHTML = '';
        } else {
          alert(data.message || data.error || 'Erreur lors de l\'envoi.');
        }
      })
      .catch(() => alert('Erreur réseau'))
      .finally(() => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText || 'Envoyer le lien de réinitialisation';
        }
      });
  }
  function handleEditProfile(form) {
    const fd = new FormData(form);
    fd.append('action', 'updateProfile');
  fetch(buildUrl('/api/auth.php'), { method: 'POST', body: fd })
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
  fetch(buildUrl('/api/auth.php'), { method: 'POST', body: fd })
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
  // Modifier le profil - Formulaire avec tous les champs pré-remplis
  // Seuls email, téléphone et mot de passe sont modifiables
  window.startEditProfile = function() {
    const c = window.currentClient || {};
    const container = document.getElementById('profileContent');
    container.innerHTML = `
      <h3>Modifier mon profil</h3>
      <form id="editProfileForm">
        <div class="form-group">
          <label>Nom :</label>
          <input type="text" name="nom" value="${c.nom}" readonly class="readonly-field" />
        </div>
        <div class="form-group">
          <label>Prénom(s) :</label>
          <input type="text" name="prenoms" value="${c.prenoms}" readonly class="readonly-field" />
        </div>
        <div class="form-group">
          <label>Adresse mail :</label>
          <input type="email" name="email" value="${c.email}" required />
        </div>
        <div class="form-group">
          <label>Numéro de téléphone :</label>
          <input type="tel" name="telephone" value="${c.telephone}" required class="smart-phone-input" />
        </div>
        <div class="form-group">
          <label>Nouveau mot de passe (5 caractères) :</label>
          <input type="password" name="password" minlength="5" maxlength="5" placeholder="Laisser vide si pas de changement" />
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Enregistrer les modifications</button>
          <button type="button" class="btn-secondary" onclick="renderProfile(window.currentClient)">Annuler</button>
        </div>
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
        const phoneUtils = window.SuzoskyPhoneUtils;
        if (phoneUtils && typeof phoneUtils.applySessionNumber === 'function') {
          phoneUtils.applySessionNumber(senderInput, client.telephone || '');
        } else {
          senderInput.value = client.telephone || '';
          senderInput.readOnly = true;
          senderInput.dataset.origin = 'session';
          senderInput.classList.add('readonly');
        }
      }
    }
  }

  function updateUIForGuestUser() {
    const guestNav = document.getElementById('guestNav');
    const userNav  = document.getElementById('userNav');
    if (guestNav) guestNav.style.display = 'block';
    if (userNav) userNav.style.display = 'none';
    window.currentClient = null;
    if (typeof window.forceCoursierAvailabilityForGuests === 'function') {
      window.forceCoursierAvailabilityForGuests();
    }
    const senderInput = document.getElementById('senderPhone');
    if (senderInput) {
      const phoneUtils = window.SuzoskyPhoneUtils;
      if (phoneUtils && typeof phoneUtils.releaseSessionNumber === 'function') {
        phoneUtils.releaseSessionNumber(senderInput);
      } else {
        senderInput.readOnly = false;
        senderInput.classList.remove('readonly');
        senderInput.removeAttribute('data-origin');
      }
      if (!window.currentClient) {
        senderInput.value = '';
      }
    }
  }

  function logout() {
    // Appel à l'API pour fermer la session
  fetch((window.ROOT_PATH || '') + '/api/auth.php?action=logout', {
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
  fetch(buildUrl('/api/auth.php?action=check_session'), { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        window.currentClient = d.client;
        updateUIForLoggedInUser(d.client);
      }
    })
    .catch(console.error);
});

// Gestion déléguée du formulaire Mot de passe oublié
document.body.addEventListener('submit', function(e) {
  if (e.target && e.target.id === 'forgotForm') {
    e.preventDefault();
    handleForgot(e.target);
  }
});
