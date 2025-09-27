// assets/js/reset_password.js
// Gestion de la soumission AJAX du formulaire de réinitialisation de mot de passe

const resetBuildUrl = (typeof window !== 'undefined' && window.suzoskyBuildUrl)
  ? window.suzoskyBuildUrl
  : (relativePath = '') => {
      const path = (typeof window !== 'undefined' && window.location && window.location.pathname)
        ? window.location.pathname
        : '';
      const base = (typeof window !== 'undefined' && typeof window.ROOT_PATH === 'string' && window.ROOT_PATH.length)
        ? window.ROOT_PATH.replace(/\/$/, '')
        : (path ? path.replace(/\\/g, '/').replace(/\/[^\/]*$/, '') : '');
      if (!relativePath) return base || '';
      const normalized = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
      return `${base}${normalized}` || normalized;
    };

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('resetPasswordForm');
  const messageContainer = document.getElementById('resetMessage');
  if (!form || !messageContainer) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();
    // Reset message
    messageContainer.textContent = '';

    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Enregistrement...';

    const fd = new FormData(form);
    try {
      // Utiliser l'endpoint indépendant
      fd.append('action', 'reset_password_do');
  const res = await fetch(resetBuildUrl('/reset_password_api.php'), {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const data = await res.json();
      if (data.success) {
        messageContainer.style.color = 'green';
        messageContainer.textContent = data.message || 'Mot de passe réinitialisé. Vous pouvez vous connecter.';
        // Optionnel: rediriger vers la page d'accueil ou modal login
      } else {
        messageContainer.style.color = 'red';
        messageContainer.textContent = data.message || data.error || 'Erreur lors de la réinitialisation.';
      }
    } catch (err) {
      messageContainer.style.color = 'red';
      messageContainer.textContent = 'Erreur réseau.';
    } finally {
      btn.disabled = false;
      btn.textContent = originalText;
    }
  });
});