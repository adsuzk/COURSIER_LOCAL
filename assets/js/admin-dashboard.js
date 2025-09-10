// Main JavaScript for Admin Dashboard (not developer-needed details)
(function(){
    // Helper for API calls
    async function apiFetch(action, params = {}) {
        const url = new URL('api_centrale.php', window.location.origin);
        url.searchParams.set('action', action);
        url.searchParams.set('role', 'admin');
        // Add params for POST if needed
        const response = await fetch(url.toString(), {method: 'GET'});
        return response.json();
    }

    // Load and display agents as cards
    async function loadAgents() {
        const container = document.getElementById('agentsList');
        if (!container) return;
        container.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Chargement des agents...</div>';
        try {
            const data = await apiFetch('agents_list');
            if (data.success && data.data && Array.isArray(data.data.agents)) {
                displayAgents(data.data.agents);
            } else {
                container.innerHTML = '<div class="error-state">❌ Erreur chargement agents</div>';
            }
        } catch (e) {
            container.innerHTML = '<div class="error-state">❌ API non disponible</div>';
        }
    }

    // Generate agent cards
    function displayAgents(agents) {
        const container = document.getElementById('agentsList');
        if (!container) return;
        if (!agents.length) {
            container.innerHTML = '<div class="loading-state">📭 Aucun agent trouvé</div>';
            return;
        }
        container.innerHTML = agents.map(agent => {
            const name = `${agent.nom || ''} ${agent.prenoms || ''}`.trim() || 'N/A';
            const phone = agent.telephone || 'N/A';
            const post = agent.type_poste || 'N/A';
            const dispo = agent.disponible ? 'Actif' : 'Inactif';
            const css = agent.disponible ? 'success' : 'danger';
            const pwd = agent.password || '****';
            return `
            <div class="agent-card">
                <div class="agent-card-header"><h4>${name}</h4></div>
                <div class="agent-card-body">
                    <p><strong>ID:</strong> ${agent.id}</p>
                    <p><strong>Téléphone:</strong> ${phone}</p>
                    <p><strong>Poste:</strong> ${post}</p>
                    <p><strong>Statut:</strong> <span class="badge badge-${css}">${dispo}</span></p>
                </div>
                <div class="agent-card-footer">
                    <button class="btn btn-sm btn-warning" onclick="alert('Mot de passe: '+${JSON.stringify(pwd)});">
                        <i class="fas fa-key"></i> Voir MDP
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    // Business clients
    async function loadClientsBusiness() {
        const container = document.getElementById('clientsBusinessList');
        if (!container) return;
        container.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Chargement clients business...</div>';
        try {
            const data = await apiFetch('business_client_list');
            if (data.success && Array.isArray(data.data)) displayBusinessClients(data.data);
            else container.innerHTML='<div class="error-state">Erreur business clients</div>';
        } catch {
            container.innerHTML='<div class="error-state">API indisponible</div>';
        }
    }
    function displayBusinessClients(clients) {
        const container = document.getElementById('clientsBusinessList');
        if (!clients.length) {
            container.innerHTML = '<div class="loading-state">Aucun client business</div>';
            return;
        }
        container.innerHTML = '<ul>' + clients.map(c => `<li>${c.nom_entreprise} - ${c.contact_nom}</li>`).join('') + '</ul>';
    }

    // Particuliers
    async function loadClientsParticulier() {
        const container = document.getElementById('clientsParticulierList');
        if (!container) return;
        container.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Chargement clients particuliers...</div>';
        try {
            const data = await apiFetch('particulier_list');
            if (data.success && Array.isArray(data.data)) displayParticlient(data.data);
            else container.innerHTML='<div class="error-state">Erreur clients particuliers</div>';
        } catch {
            container.innerHTML='<div class="error-state">API indisponible</div>';
        }
    }
    function displayParticlient(users) {
        const container = document.getElementById('clientsParticulierList');
        if (!users.length) {
            container.innerHTML = '<div class="loading-state">Aucun client particulier</div>';
            return;
        }
        container.innerHTML = '<ul>' + users.map(u => `<li>${u.username} (ID:${u.id})</li>`).join('') + '</ul>';
    }

    // WebSocket for real-time
    const protocol = window.location.protocol==='https:'?'wss:':'ws:';
    const ws = new WebSocket(protocol + '//' + window.location.host + ':8080');
    ws.onmessage = () => {
        loadAgents(); loadClientsBusiness(); loadClientsParticulier();
    };

    // Expose for inline calls
    window.loadAgents = loadAgents;
    window.loadClientsBusiness = loadClientsBusiness;
    window.loadClientsParticulier = loadClientsParticulier;
})();
