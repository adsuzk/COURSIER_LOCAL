document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('agentsTable')) {
        loadAgents();
    }
});

function loadAgents() {
    const tbody = document.querySelector('#agentsTable tbody');
    fetch('admin/agents.php', {
        method: 'POST',
        body: new URLSearchParams({ajax:'true', action:'get_agents'})
    })
    .then(res=>res.json())
    .then(data=>{
        tbody.innerHTML = data.map(agent => `
            <tr>
                <td>${agent.nom} ${agent.prenoms}</td>
                <td>${agent.telephone}</td>
                <td>${agent.email}</td>
                <td id="pwd-${agent.id}">${agent.plain_password}</td>
                <td>
                    <button onclick="copyToClipboard('pwd-${agent.id}')">Copier</button>
                    <button onclick="regenerateAgentPassword(${agent.id})">Régénérer</button>
                </td>
            </tr>
        `).join('');
    });
}

function copyToClipboard(id) {
    const text = document.getElementById(id).textContent;
    navigator.clipboard.writeText(text);
    alert('Copié');
}

function regenerateAgentPassword(id) {
    fetch('admin/agents.php', {
        method: 'POST',
        body: new URLSearchParams({ajax:'true', action:'change_agent_password', agent_id:id, new_password:Math.random().toString(36).substr(-5)})
    })
    .then(res=>res.json())
    .then(data=>{
        if (data.success) loadAgents();
    });
}
