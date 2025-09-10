<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Final - Système d'Authentification Suzosky</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 1000px; 
            margin: 0 auto; 
            padding: 20px;
            background: #f8f9fa;
        }
        .test-card { 
            background: white;
            margin: 20px 0; 
            padding: 25px; 
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #FFD700;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .warning { color: #ffc107; }
        h1 { 
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        h2 { 
            color: #FFD700;
            border-bottom: 2px solid #FFD700;
            padding-bottom: 10px;
        }
        button {
            background: #FFD700;
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin: 5px;
            transition: all 0.3s ease;
        }
        button:hover {
            background: #FFC700;
            transform: translateY(-1px);
        }
        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 6px;
            font-size: 14px;
        }
        .checklist {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .checklist li {
            margin: 8px 0;
            list-style: none;
        }
        .checklist li:before {
            content: "✅ ";
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🎯 Test Final - Système d'Authentification Suzosky</h1>
    
    <div class="test-card">
        <h2>🔐 Test de Connexion API</h2>
        <p>Tester la connexion avec l'utilisateur de test</p>
        <button onclick="testLogin()">Tester la connexion</button>
        <div id="loginResult" class="result" style="display: none;"></div>
    </div>

    <div class="test-card">
        <h2>📋 Test Vérification Session</h2>
        <p>Vérifier l'état de la session actuelle</p>
        <button onclick="testSession()">Vérifier la session</button>
        <div id="sessionResult" class="result" style="display: none;"></div>
    </div>

    <div class="test-card">
        <h2>📦 Test Historique Commandes</h2>
        <p>Récupérer l'historique des commandes de test</p>
        <button onclick="testOrders()">Charger l'historique</button>
        <div id="ordersResult" class="result" style="display: none;"></div>
    </div>

    <div class="test-card">
        <h2>🎭 Test Modal Interface</h2>
        <p>Ouvrir l'interface de connexion complète</p>
        <button onclick="openTestPage()">Ouvrir la page principale</button>
        <button onclick="testLogout()">Test de déconnexion</button>
        <div id="interfaceResult" class="result" style="display: none;"></div>
    </div>

    <div class="checklist">
        <h3>✅ Checklist de Fonctionnalités</h3>
        <ul>
            <li>API d'authentification fonctionnelle</li>
            <li>Modal AJAX avec chargement dynamique</li>
            <li>Formatage automatique téléphone ivoirien</li>
            <li>Gestion d'état connexion/déconnexion</li>
            <li>Basculement "Connexion particulier" ↔ "Mon compte"</li>
            <li>Profil utilisateur avec modification</li>
            <li>Historique des commandes avec filtres</li>
            <li>Détails de commandes avec numéros uniques</li>
            <li>Sécurité avec hachage des mots de passe</li>
            <li>Design premium glass morphism</li>
        </ul>
    </div>

    <div class="test-card">
        <h2>🚀 Instructions Finales</h2>
        <div class="info">
            <p><strong>1. Testez la connexion :</strong></p>
            <ul>
                <li>Email : test@suzosky.com</li>
                <li>Mot de passe : test123</li>
            </ul>
            
            <p><strong>2. Vérifiez l'interface :</strong></p>
            <ul>
                <li>Le bouton "Connexion particulier" devient "Mon compte"</li>
                <li>L'historique affiche 5 commandes de test</li>
                <li>Le profil permet la modification des données</li>
            </ul>
            
            <p><strong>3. Testez toutes les fonctionnalités :</strong></p>
            <ul>
                <li>Connexion/Déconnexion</li>
                <li>Inscription d'un nouveau compte</li>
                <li>Formatage du téléphone ivoirien</li>
                <li>Navigation dans l'historique</li>
                <li>Détails des commandes</li>
            </ul>
        </div>
    </div>

    <script>
        function testLogin() {
            const resultDiv = document.getElementById('loginResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="info">🔄 Test en cours...</div>';
            
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
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h4>✅ Connexion réussie !</h4>
                            <p><strong>Utilisateur :</strong> ${data.client.nom} ${data.client.prenoms}</p>
                            <p><strong>Email :</strong> ${data.client.email}</p>
                            <p><strong>Téléphone :</strong> ${data.client.telephone}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="error">❌ Erreur : ${data.error}</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="error">❌ Erreur de connexion : ${error.message}</div>`;
            });
        }

        function testSession() {
            const resultDiv = document.getElementById('sessionResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="info">🔄 Vérification...</div>';
            
            fetch('api/auth.php?action=check_session')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h4>✅ Session active !</h4>
                            <p><strong>Utilisateur connecté :</strong> ${data.client.nom} ${data.client.prenoms}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="warning">⚠️ Aucune session active</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="error">❌ Erreur : ${error.message}</div>`;
            });
        }

        function testOrders() {
            const resultDiv = document.getElementById('ordersResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="info">🔄 Chargement...</div>';
            
            fetch('api/orders.php?action=get_history')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h4>✅ Historique chargé !</h4>
                            <p><strong>Total commandes :</strong> ${data.total}</p>
                            <p><strong>Dernière commande :</strong> ${data.orders[0]?.numero_commande || 'N/A'}</p>
                            <p><strong>Statuts variés :</strong> ${data.orders.map(o => o.statut).join(', ')}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="error">❌ Erreur : ${data.error}</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="error">❌ Erreur : ${error.message}</div>`;
            });
        }

        function testLogout() {
            const resultDiv = document.getElementById('interfaceResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="info">🔄 Déconnexion...</div>';
            
            fetch('api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=logout'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `<div class="success">✅ Déconnexion réussie</div>`;
                } else {
                    resultDiv.innerHTML = `<div class="error">❌ Erreur de déconnexion</div>`;
                }
            });
        }

        function openTestPage() {
            window.open('index.php', '_blank');
        }
    </script>
</body>
</html>
