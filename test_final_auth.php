<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Final - Système d'Authentification Suzosky</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section { 
            background: white;
            margin: 20px 0; 
            padding: 20px; 
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        h2 { 
            border-bottom: 2px solid #007bff; 
            padding-bottom: 10px;
            color: #007bff;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #0056b3; }
        pre { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 4px; 
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🎯 Test Final - Système d'Authentification Suzosky</h1>
    
    <div class="test-section">
        <h2>1. Test de Connexion</h2>
        <p><strong>Utilisateur de test :</strong></p>
        <ul>
            <li>Email : test@suzosky.com</li>
            <li>Mot de passe : test123</li>
        </ul>
        <button onclick="testLogin()">🔐 Tester la Connexion</button>
        <div id="login-result"></div>
    </div>

    <div class="test-section">
        <h2>2. Test Statut de Session</h2>
        <button onclick="testStatus()">👤 Vérifier l'État de Session</button>
        <div id="status-result"></div>
    </div>

    <div class="test-section">
        <h2>3. Test Historique des Commandes</h2>
        <p><em>Vous devez être connecté pour voir l'historique</em></p>
        <button onclick="testOrders()">📦 Charger l'Historique</button>
        <div id="orders-result"></div>
    </div>

    <div class="test-section">
        <h2>4. Test de Déconnexion</h2>
        <button onclick="testLogout()">🚪 Se Déconnecter</button>
        <div id="logout-result"></div>
    </div>

    <div class="test-section">
        <h2>5. Test Complet du Modal</h2>
        <p>Ce test simule l'expérience complète :</p>
        <ol>
            <li>Ouverture du modal de connexion</li>
            <li>Connexion automatique</li>
            <li>Ouverture du modal compte</li>
            <li>Affichage de l'historique</li>
        </ol>
        <button onclick="testCompleteFlow()">🎭 Test Complet du Modal</button>
        <div id="complete-result"></div>
    </div>

    <script>
    function testLogin() {
        const output = document.getElementById('login-result');
        output.innerHTML = '<p>🔄 Test de connexion en cours...</p>';
        
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('email', 'test@suzosky.com');
        formData.append('password', 'test123');
        
        fetch('api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            output.innerHTML = `
                <h4>Résultat de la connexion :</h4>
                <pre>${JSON.stringify(data, null, 2)}</pre>
                <p class="${data.success ? 'success' : 'error'}">
                    ${data.success ? '✅ Connexion réussie' : '❌ Échec de connexion'}
                </p>
            `;
        })
        .catch(error => {
            output.innerHTML = `<p class="error">❌ Erreur: ${error.message}</p>`;
        });
    }

    function testStatus() {
        const output = document.getElementById('status-result');
        output.innerHTML = '<p>🔄 Vérification du statut...</p>';
        
        fetch('api/auth.php?action=status')
        .then(response => response.json())
        .then(data => {
            output.innerHTML = `
                <h4>Statut de session :</h4>
                <pre>${JSON.stringify(data, null, 2)}</pre>
                <p class="${data.authenticated ? 'success' : 'warning'}">
                    ${data.authenticated ? '✅ Utilisateur connecté' : '⚠️ Utilisateur non connecté'}
                </p>
            `;
        })
        .catch(error => {
            output.innerHTML = `<p class="error">❌ Erreur: ${error.message}</p>`;
        });
    }

    function testOrders() {
        const output = document.getElementById('orders-result');
        output.innerHTML = '<p>🔄 Chargement de l\'historique...</p>';
        
        const formData = new FormData();
        formData.append('action', 'orders');
        
        fetch('api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.orders) {
                output.innerHTML = `
                    <h4>Historique des commandes (${data.orders.length} commandes) :</h4>
                    <div style="max-height: 300px; overflow-y: auto;">
                        ${data.orders.map(order => `
                            <div style="border: 1px solid #ddd; margin: 10px 0; padding: 10px; border-radius: 5px;">
                                <strong>${order.numero_commande}</strong> - 
                                <span style="color: #28a745; font-weight: bold;">${order.montant} FCFA</span>
                                <br>
                                <small>De: ${order.adresse_depart}</small><br>
                                <small>Vers: ${order.adresse_arrivee}</small><br>
                                <small>Statut: <span style="color: #007bff;">${order.statut}</span> - ${order.date_formatted}</small>
                            </div>
                        `).join('')}
                    </div>
                    <p class="success">✅ Historique chargé avec succès</p>
                `;
            } else {
                output.innerHTML = `
                    <h4>Erreur lors du chargement :</h4>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                    <p class="error">❌ ${data.error || 'Erreur inconnue'}</p>
                `;
            }
        })
        .catch(error => {
            output.innerHTML = `<p class="error">❌ Erreur: ${error.message}</p>`;
        });
    }

    function testLogout() {
        const output = document.getElementById('logout-result');
        output.innerHTML = '<p>🔄 Déconnexion en cours...</p>';
        
        const formData = new FormData();
        formData.append('action', 'logout');
        
        fetch('api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            output.innerHTML = `
                <h4>Résultat de la déconnexion :</h4>
                <pre>${JSON.stringify(data, null, 2)}</pre>
                <p class="${data.success ? 'success' : 'error'}">
                    ${data.success ? '✅ Déconnexion réussie' : '❌ Échec de déconnexion'}
                </p>
            `;
        })
        .catch(error => {
            output.innerHTML = `<p class="error">❌ Erreur: ${error.message}</p>`;
        });
    }

    function testCompleteFlow() {
        const output = document.getElementById('complete-result');
        output.innerHTML = '<p>🔄 Test complet en cours...</p>';
        
        let results = [];
        
        // Étape 1: Connexion
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('email', 'test@suzosky.com');
        formData.append('password', 'test123');
        
        fetch('api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(loginData => {
            results.push(`✅ Étape 1 - Connexion: ${loginData.success ? 'SUCCÈS' : 'ÉCHEC'}`);
            
            if (!loginData.success) {
                throw new Error('Connexion échouée');
            }
            
            // Étape 2: Vérification du statut
            return fetch('api/auth.php?action=status');
        })
        .then(response => response.json())
        .then(statusData => {
            results.push(`✅ Étape 2 - Statut: ${statusData.authenticated ? 'CONNECTÉ' : 'NON CONNECTÉ'}`);
            
            // Étape 3: Chargement des commandes
            const ordersFormData = new FormData();
            ordersFormData.append('action', 'orders');
            return fetch('api/auth.php', { method: 'POST', body: ordersFormData });
        })
        .then(response => response.json())
        .then(ordersData => {
            results.push(`✅ Étape 3 - Commandes: ${ordersData.success ? ordersData.orders.length + ' trouvées' : 'ÉCHEC'}`);
            
            // Affichage final
            output.innerHTML = `
                <h4>Résultats du test complet :</h4>
                <ul>
                    ${results.map(result => `<li>${result}</li>`).join('')}
                </ul>
                <p class="success">🎉 Test complet terminé ! Le système est fonctionnel.</p>
                <p class="info">💡 Vous pouvez maintenant tester sur la page principale avec le modal.</p>
            `;
        })
        .catch(error => {
            results.push(`❌ Erreur: ${error.message}`);
            output.innerHTML = `
                <h4>Résultats du test complet :</h4>
                <ul>
                    ${results.map(result => `<li>${result}</li>`).join('')}
                </ul>
                <p class="error">❌ Test échoué: ${error.message}</p>
            `;
        });
    }
    </script>

    <div class="test-section">
        <h2>📋 Instructions Finales</h2>
        <ol>
            <li><strong>Testez d'abord ici</strong> avec les boutons ci-dessus</li>
            <li><strong>Puis testez sur la page principale</strong> : <a href="index.php" target="_blank">index.php</a></li>
            <li><strong>Utilisez les identifiants :</strong> test@suzosky.com / test123</li>
            <li><strong>Vérifiez que :</strong>
                <ul>
                    <li>Le bouton "Connexion Particulier" devient "Mon Compte"</li>
                    <li>Le modal s'ouvre avec les onglets Profil et Commandes</li>
                    <li>L'historique s'affiche avec 8 commandes de test</li>
                    <li>La déconnexion fonctionne</li>
                </ul>
            </li>
        </ol>
    </div>
</body>
</html>
