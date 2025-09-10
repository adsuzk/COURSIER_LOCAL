<?php
// test_simple.php - Test simple de l'API
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Simple API</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>Test API Authentification</h1>
    
    <h2>Test de Connexion</h2>
    <button onclick="testLogin()">Tester la connexion</button>
    <div id="login-result"></div>
    
    <h2>Test de Déconnexion</h2>
    <button onclick="testLogout()">Tester la déconnexion</button>
    <div id="logout-result"></div>
    
    <script>
    function testLogin() {
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
            document.getElementById('login-result').innerHTML = 
                '<h3>Résultat:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('login-result').innerHTML = 
                '<h3>Erreur:</h3><p style="color: red;">' + error.message + '</p>';
        });
    }
    
    function testLogout() {
        const formData = new FormData();
        formData.append('action', 'logout');
        
        fetch('api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('logout-result').innerHTML = 
                '<h3>Résultat:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('logout-result').innerHTML = 
                '<h3>Erreur:</h3><p style="color: red;">' + error.message + '</p>';
        });
    }
    </script>
</body>
</html>
