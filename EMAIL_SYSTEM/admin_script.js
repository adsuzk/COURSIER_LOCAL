/**
 * EMAIL ADMIN PANEL JAVASCRIPT (refonte locale)
 * Gestion des interactions du panneau email compatible environnement XAMPP local.
 */
(function () {
  const resolveBasePath = () => {
    if (typeof window === 'undefined') return '';
    if (typeof window.ROOT_PATH === 'string' && window.ROOT_PATH.length) {
      return window.ROOT_PATH.replace(/\/$/, '');
    }
    const path = window.location && window.location.pathname ? window.location.pathname : '';
    if (!path) return '';
    return path.replace(/\\/g, '/').replace(/\/[^\/]*$/, '') || '';
  };

  if (typeof window !== 'undefined') {
    if (!window.__SUZOSKY_BASE_PATH) {
      window.__SUZOSKY_BASE_PATH = resolveBasePath();
    }
    if (!window.suzoskyBuildUrl) {
      window.suzoskyBuildUrl = function (relativePath = '') {
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
        const base = resolveBasePath();
        if (!relativePath) return base || '';
        const normalized = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
        return `${base}${normalized}` || normalized;
      };

  class EmailAdminJS {
    constructor() {
      this.chartInstance = null;
      this.initializeChart();
      this.bindEvents();
      this.refreshStats();
    }

    initializeChart() {
      const canvas = document.getElementById('emailChart');
      if (!canvas || typeof Chart === 'undefined') return;

      const ctx = canvas.getContext('2d');
      const chartData = window.emailChartData || {};
      const { labels, sentData, failedData, openedData } = this.extractChartArrays(chartData);

      this.chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels.map(date => {
            const d = new Date(date);
            return d.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
          }),
          datasets: this.buildDatasets(sentData, failedData, openedData)
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'top' }
          },
          scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
          }
        }
      });
    }

    extractChartArrays(chartData = {}) {
      const labels = Object.keys(chartData).sort();
      return {
        labels,
        sentData: labels.map(date => chartData[date]?.sent || 0),
        failedData: labels.map(date => chartData[date]?.failed || 0),
        openedData: labels.map(date => chartData[date]?.opened || 0)
      };
    }

    bindEvents() {
      setInterval(() => this.refreshStats(), 30000);
      document.addEventListener('click', (event) => {
        const target = event.target.closest('[data-confirm-action]');
        if (!target) return;
        const message = target.dataset.confirmMessage || 'Êtes-vous sûr ?';
        if (!confirm(message)) {
          event.preventDefault();
        }
      });
    }

    async refreshStats() {
      try {
  const response = await fetch(buildUrl('/EMAIL_SYSTEM/admin_api.php?action=get_stats'), {
          credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.success) {
          this.updateStatsDisplay(data.stats);
          if (data.chart) {
            this.updateChart(data.chart);
          }
        }
      } catch (error) {
        console.error('Erreur refresh stats:', error);
      }
    }

    updateStatsDisplay(stats = {}) {
      const cards = document.querySelectorAll('.stat-value');
      if (!cards.length) return;
      const { sent = 0, failed = 0, openRate = 0, clickRate = 0 } = stats;
      if (cards[0]) cards[0].textContent = sent;
      if (cards[1]) cards[1].textContent = failed;
      if (cards[2]) cards[2].textContent = `${openRate}%`;
      if (cards[3]) cards[3].textContent = `${clickRate}%`;
    }

    updateChart(chartMap = {}) {
      if (!this.chartInstance) {
        const canvas = document.getElementById('emailChart');
        if (!canvas || typeof Chart === 'undefined') return;
        const ctx = canvas.getContext('2d');
        const { labels, sentData, failedData, openedData } = this.extractChartArrays(chartMap);
        this.chartInstance = new Chart(ctx, {
          type: 'line',
          data: {
            labels: labels.map(date => {
              const d = new Date(date);
              return d.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
            }),
            datasets: this.buildDatasets(sentData, failedData, openedData)
          },
          options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
          }
        });
        return;
      }

      const { labels, sentData, failedData, openedData } = this.extractChartArrays(chartMap);
      this.chartInstance.data.labels = labels.map(date => {
        const d = new Date(date);
        return d.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
      });
      const datasets = this.buildDatasets(sentData, failedData, openedData);
      this.chartInstance.data.datasets = datasets;
      this.chartInstance.update();
    }

    buildDatasets(sentData, failedData, openedData) {
      return [
        {
          label: '📤 Envoyés',
          data: sentData,
          borderColor: '#28a745',
          backgroundColor: 'rgba(40, 167, 69, 0.1)',
          tension: 0.4
        },
        {
          label: '❌ Échecs',
          data: failedData,
          borderColor: '#dc3545',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
          tension: 0.4
        },
        {
          label: '👀 Ouverts',
          data: openedData,
          borderColor: '#17a2b8',
          backgroundColor: 'rgba(23, 162, 184, 0.1)',
          tension: 0.4
        }
      ];
    }

    async viewEmail(emailId) {
      try {
  const response = await fetch(buildUrl(`/EMAIL_SYSTEM/admin_api.php?action=get_email&id=${emailId}`), {
          credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.success) {
          this.showEmailModal(data.email);
        } else {
          this.showAlert(`Erreur: ${data.message}`, 'error');
        }
      } catch (error) {
        this.showAlert('Erreur lors du chargement de l\'email', 'error');
      }
    }

    showEmailModal(email) {
      if (!email) return;
      const overlay = document.createElement('div');
      overlay.className = 'email-modal-overlay';
      overlay.innerHTML = `
        <div class="email-modal">
          <div class="email-modal-header">
            <h3>📧 Détails de l'email</h3>
            <button type="button" class="close-modal">&times;</button>
          </div>
          <div class="email-modal-body">
            <div class="email-info-grid">
              <div><strong>Destinataire :</strong> ${email.recipient_email || ''}</div>
              <div><strong>Sujet :</strong> ${email.subject || ''}</div>
              <div><strong>Type :</strong> ${email.email_type || ''}</div>
              <div><strong>Statut :</strong> ${email.status || ''}</div>
              <div><strong>Envoyé :</strong> ${email.created_at ? new Date(email.created_at).toLocaleString('fr-FR') : 'N/A'}</div>
              ${email.opened_at ? `<div><strong>Ouvert :</strong> ${new Date(email.opened_at).toLocaleString('fr-FR')}</div>` : ''}
              ${email.clicked_at ? `<div><strong>Cliqué :</strong> ${new Date(email.clicked_at).toLocaleString('fr-FR')}</div>` : ''}
            </div>
            ${email.error_message ? `<div class="email-error"><strong>Erreur :</strong> ${email.error_message}</div>` : ''}
          </div>
        </div>
      `;
      overlay.addEventListener('click', (event) => {
        if (event.target.classList.contains('close-modal') || event.target === overlay) {
          overlay.remove();
        }
      });
      document.body.appendChild(overlay);
    }

    async retryEmail(emailId) {
      if (!confirm('Réessayer l\'envoi de cet email ?')) return;
      try {
  const response = await fetch(buildUrl('/EMAIL_SYSTEM/admin_api.php'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'retry_email', email_id: emailId })
        });
        const data = await response.json();
        if (data.success) {
          this.showAlert('Email remis en file d\'attente', 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          this.showAlert(`Erreur: ${data.message}`, 'error');
        }
      } catch (error) {
        this.showAlert('Erreur lors de la remise en file d\'attente', 'error');
      }
    }

    createCampaign() {
      this.showCampaignModal();
    }

    async editCampaign(campaignId) {
      try {
  const response = await fetch(buildUrl(`/EMAIL_SYSTEM/admin_api.php?action=get_campaign&id=${campaignId}`), {
          credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.success) {
          this.showCampaignModal(data.campaign);
        } else {
          this.showAlert(`Erreur: ${data.message}`, 'error');
        }
      } catch (error) {
        this.showAlert('Erreur lors du chargement de la campagne', 'error');
      }
    }

    showCampaignModal(campaign = null) {
      const overlay = document.createElement('div');
      overlay.className = 'campaign-modal-overlay';
      overlay.innerHTML = `
        <div class="campaign-modal">
          <div class="campaign-modal-header">
            <h3>${campaign ? '✏️ Modifier' : '➕ Créer'} une campagne</h3>
            <button type="button" class="close-modal">&times;</button>
          </div>
          <div class="campaign-modal-body">
            <form id="campaignForm">
              <input type="hidden" name="campaign_id" value="${campaign?.id || ''}">
              <label>Nom de la campagne</label>
              <input type="text" name="name" value="${campaign?.name || ''}" required>
              <label>Sujet</label>
              <input type="text" name="subject" value="${campaign?.subject || ''}" required>
              <label>Template</label>
              <select name="template_id" required>
                <option value="">Sélectionner un template</option>
              </select>
              <label>Destinataires</label>
              <select name="recipient_type">
                <option value="all" ${campaign?.recipient_type === 'all' ? 'selected' : ''}>Tous les clients</option>
                <option value="active" ${campaign?.recipient_type === 'active' ? 'selected' : ''}>Clients actifs</option>
                <option value="custom" ${campaign?.recipient_type === 'custom' ? 'selected' : ''}>Liste personnalisée</option>
              </select>
              <div class="campaign-actions">
                <button type="button" class="btn-secondary close-modal">Annuler</button>
                <button type="submit" class="btn-primary">${campaign ? 'Modifier' : 'Créer'}</button>
              </div>
            </form>
          </div>
        </div>
      `;
      overlay.addEventListener('click', (event) => {
        if (event.target.classList.contains('close-modal') || event.target === overlay) {
          overlay.remove();
        }
      });
      document.body.appendChild(overlay);

      const form = overlay.querySelector('#campaignForm');
      const templateSelect = form.querySelector('select[name="template_id"]');
      this.loadTemplateOptions(templateSelect, campaign?.template_id || null);

      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        formData.append('action', campaign ? 'update_campaign' : 'create_campaign');
        this.saveCampaign(formData, overlay);
      });
    }

    async loadTemplateOptions(selectEl, selectedId = null) {
      if (!selectEl) return;
      try {
  const response = await fetch(buildUrl('/EMAIL_SYSTEM/admin_api.php?action=get_templates'), {
          credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.success && Array.isArray(data.templates)) {
          data.templates.forEach(template => {
            const option = document.createElement('option');
            option.value = template.id;
            option.textContent = template.name;
            if (selectedId && Number(selectedId) === Number(template.id)) {
              option.selected = true;
            }
            selectEl.appendChild(option);
          });
        }
      } catch (error) {
        console.error('Erreur chargement templates:', error);
      }
    }

    async saveCampaign(formData, overlay) {
      try {
  const response = await fetch(buildUrl('/EMAIL_SYSTEM/admin_api.php'), {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
        const data = await response.json();
        if (data.success) {
          this.showAlert('Campagne sauvegardée !', 'success');
          overlay.remove();
          setTimeout(() => location.reload(), 1000);
        } else {
          this.showAlert(`Erreur: ${data.message}`, 'error');
        }
      } catch (error) {
        this.showAlert('Erreur lors de la sauvegarde', 'error');
      }
    }

    async sendCampaign(campaignId) {
      const confirmed = confirm('Envoyer cette campagne maintenant ? Cette action est irréversible.');
      if (!confirmed) return;
      try {
  const response = await fetch(buildUrl('/EMAIL_SYSTEM/admin_api.php'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'send_campaign', campaign_id: campaignId })
        });
        const data = await response.json();
        if (data.success) {
          this.showAlert('Campagne lancée avec succès !', 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          this.showAlert(`Erreur: ${data.message}`, 'error');
        }
      } catch (error) {
        this.showAlert('Erreur lors du lancement de la campagne', 'error');
      }
    }

    createTemplate() {
      this.showTemplateModal();
    }

    showTemplateModal(template = null) {
      const overlay = document.createElement('div');
      overlay.className = 'template-modal-overlay';
      overlay.innerHTML = `
        <div class="template-modal">
          <div class="template-modal-header">
            <h3>${template ? '✏️ Modifier' : '📝 Nouveau'} template</h3>
            <button type="button" class="close-modal">&times;</button>
          </div>
          <div class="template-modal-body">
            <form id="templateForm">
              <input type="hidden" name="template_id" value="${template?.id || ''}">
              <label>Nom du template</label>
              <input type="text" name="name" value="${template?.name || ''}" required>
              <label>Sujet par défaut</label>
              <input type="text" name="default_subject" value="${template?.default_subject || ''}">
              <label>Contenu HTML</label>
              <textarea name="html_content" rows="15" required>${template?.html_content || ''}</textarea>
              <div class="template-actions">
                <button type="button" class="btn-secondary close-modal">Annuler</button>
                <button type="submit" class="btn-primary">${template ? 'Modifier' : 'Créer'}</button>
              </div>
            </form>
          </div>
        </div>
      `;
      overlay.addEventListener('click', (event) => {
        if (event.target.classList.contains('close-modal') || event.target === overlay) {
          overlay.remove();
        }
      });
      document.body.appendChild(overlay);

      const form = overlay.querySelector('#templateForm');
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        formData.append('action', template ? 'update_template' : 'create_template');
        this.saveTemplate(formData, overlay);
      });
    }

    async saveTemplate(formData, overlay) {
      try {
  const response = await fetch(buildUrl('/EMAIL_SYSTEM/admin_api.php'), {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
        const data = await response.json();
        if (data.success) {
          this.showAlert('Template sauvegardé !', 'success');
          overlay.remove();
          setTimeout(() => location.reload(), 1000);
        } else {
          this.showAlert(`Erreur: ${data.message}`, 'error');
        }
      } catch (error) {
        this.showAlert('Erreur lors de la sauvegarde du template', 'error');
      }
    }

    showAlert(message, type = 'info') {
      const alert = document.createElement('div');
      alert.className = `email-alert email-alert-${type}`;
      alert.textContent = message;
      document.body.appendChild(alert);
      requestAnimationFrame(() => alert.classList.add('show'));
      setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
      }, 3500);
    }
  }

  const injectStyles = () => {
    if (document.getElementById('email-admin-styles')) return;
    const style = document.createElement('style');
    style.id = 'email-admin-styles';
    style.textContent = `
      .email-modal-overlay,
      .campaign-modal-overlay,
      .template-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 20px;
      }
      .email-modal,
      .campaign-modal,
      .template-modal {
        background: #ffffff;
        border-radius: 12px;
        width: 100%;
        max-width: 640px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
      }
      .email-modal-header,
      .campaign-modal-header,
      .template-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
        color: #fff;
      }
      .email-modal-body,
      .campaign-modal-body,
      .template-modal-body {
        padding: 24px;
      }
      .email-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
      }
      .email-error {
        margin-top: 16px;
        padding: 12px;
        border-radius: 8px;
        background: #f8d7da;
        color: #721c24;
      }
      .campaign-modal form label,
      .template-modal form label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
      }
      .campaign-modal form input,
      .campaign-modal form select,
      .template-modal form input,
      .template-modal form textarea {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 18px;
        font-size: 0.95rem;
      }
      .campaign-actions,
      .template-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
      }
      .btn-primary {
        background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 100%);
        color: #1A1A2E;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
      }
      .btn-secondary,
      .close-modal {
        background: transparent;
        border: 2px solid #dc3545;
        color: #dc3545;
        padding: 10px 16px;
        border-radius: 8px;
        cursor: pointer;
      }
      .email-alert {
        position: fixed;
        top: 24px;
        right: 24px;
        padding: 14px 18px;
        border-radius: 10px;
        color: #fff;
        font-weight: 600;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 10000;
      }
      .email-alert.show {
        opacity: 1;
        transform: translateY(0);
      }
      .email-alert-info { background: #17a2b8; }
      .email-alert-success { background: #28a745; }
      .email-alert-error { background: #dc3545; }
    `;
    document.head.appendChild(style);
  };

  document.addEventListener('DOMContentLoaded', () => {
    injectStyles();
    window.emailAdmin = new EmailAdminJS();
  });

  window.viewEmail = (id) => window.emailAdmin?.viewEmail(id);
  window.retryEmail = (id) => window.emailAdmin?.retryEmail(id);
  window.createCampaign = () => window.emailAdmin?.createCampaign();
  window.editCampaign = (id) => window.emailAdmin?.editCampaign(id);
  window.sendCampaign = (id) => window.emailAdmin?.sendCampaign(id);
  window.createTemplate = () => window.emailAdmin?.createTemplate();
})();