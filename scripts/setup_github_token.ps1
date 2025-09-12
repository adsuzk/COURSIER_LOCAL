# Configuration Token GitHub pour accès IA
# Remplacez YOUR_TOKEN_HERE par votre token personnel

# Étape 1: Définir le token (remplacez par le vrai)
$env:GITHUB_TOKEN = "YOUR_TOKEN_HERE"

# Étape 2: Configurer Git avec le token
git remote set-url origin https://$env:GITHUB_TOKEN@github.com/adsuzk/COURSIER_LOCAL.git

# Étape 3: Pousser vers GitHub
git push -u origin main

Write-Host "✅ Configuration terminée ! Les IA peuvent maintenant accéder à votre historique GitHub" -ForegroundColor Green
