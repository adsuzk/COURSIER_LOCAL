# Documentation - Gestion des Sessions et Sécurité Multi-Appareils

## Vue d'ensemble

Le système de gestion des sessions Suzosky Coursier implémente une politique de **"dernière connexion prioritaire"** tout en étant tolérant aux reconnexions du même appareil.

## Principe de fonctionnement

### 1. Connexion (Login)
Lors de chaque connexion successful:
- Un nouveau token de session unique est généré (`bin2hex(random_bytes(16))`)
- L'ancien token de session est automatiquement invalidé
- Les informations de connexion sont enregistrées:
  - `current_session_token`: nouveau token
  - `last_login_at`: timestamp de connexion
  - `last_login_ip`: adresse IP de l'appareil
  - `last_login_user_agent`: informations du navigateur/app

### 2. Vérification de session (check_session)
À chaque vérification, le système applique cette logique:

#### Étape 1: Vérification du token
- Compare le token en session avec celui en base de données
- Si les tokens correspondent → **Session valide**

#### Étape 2: Tolérance même appareil  
Si les tokens ne correspondent pas:
- Vérifie si l'IP est identique à `last_login_ip`
- Si même IP → **Session maintenue** (reconnexion du même appareil)
- Si IP différente → **Session révoquée** (autre appareil)

#### Étape 3: Première connexion
- Si aucun token en base → **Session valide** (première utilisation)

### 3. Surveillance côté application Android
- Vérification toutes les **30 secondes** (moins agressive qu'avant)
- Déconnexion uniquement sur erreur `SESSION_REVOKED` 
- Nécessite **2 erreurs consécutives** pour éviter les faux positifs
- Ignore les erreurs temporaires (`NO_SESSION`, erreurs réseau)

### 4. Liaison avec l'assignation de courses
**CRITIQUE** : La session est directement liée à la disponibilité pour les courses :
- **Token valide** → `statut_connexion = 'en_ligne'` → **Peut recevoir des courses**
- **Pas de token/token invalide** → `statut_connexion = 'hors_ligne'` → **Pas de courses**
- **Déconnexion/révocation** → Automatiquement `hors_ligne` → **Arrêt des assignations**

## Avantages du système

### ✅ Sécurité
- Empêche l'utilisation simultanée depuis plusieurs appareils
- Chaque nouvelle connexion invalide automatiquement les précédentes
- Tokens de session cryptographiquement sécurisés
- **Cohérence session ↔ disponibilité courses**

### ✅ Tolérance utilisateur
- Reconnexions automatiques du même appareil sans interruption
- Résistance aux erreurs réseau temporaires  
- Pas de déconnexions intempestives lors d'instabilités réseau
- **Maintien automatique du statut 'en_ligne' si session valide**

### ✅ Expérience utilisateur
- Transitions transparentes lors des reconnexions
- Messages clairs en cas de connexion depuis un autre appareil
- Surveillance de session non-intrusive
- **Pas de courses perdues par incohérence de statut**

## Configuration technique

### Côté serveur (PHP)
```php
// Dans agent_auth.php
case 'login':
    // Génération nouveau token → invalide l'ancien
    $newToken = bin2hex(random_bytes(16));
    
case 'check_session':  
    // Logique de tolérance même appareil
    $sameDevice = ($currentIp && $row['last_login_ip'] && $currentIp === $row['last_login_ip']);
```

### Côté application (Android)
```kotlin
// Dans MainActivity.kt
LaunchedEffect(isLoggedIn) {
    kotlinx.coroutines.delay(30000) // 30s entre vérifications
    // Déconnexion après 2 erreurs SESSION_REVOKED consécutives
}
```

## Cas d'usage

### Cas 1: Utilisateur normal
1. Se connecte sur son téléphone → **Connexion réussie**
2. L'app vérifie périodiquement → **Session maintenue**
3. Reconnexion après perte réseau → **Reconnexion automatique**

### Cas 2: Tentative d'accès concurrent
1. Utilisateur A connecté sur appareil 1 → **Session active**
2. Utilisateur B tente de se connecter avec même compte sur appareil 2 → **Connexion réussie**
3. Appareil 1 détecte la révocation → **Déconnexion avec message explicite**

### Cas 3: Problème réseau temporaire
1. Application perd temporairement la connexion → **Pas de déconnexion**
2. Erreurs `NO_SESSION` ignorées → **Session préservée**
3. Reconnexion réseau → **Fonctionnement normal restauré**

## Messages d'erreur

| Code erreur | Cause | Action utilisateur |
|-------------|-------|-------------------|
| `SESSION_REVOKED` | Connexion depuis autre appareil | Reconnexion nécessaire |
| `NO_SESSION` | Erreur temporaire/première connexion | Automatiquement géré |
| `INVALID_CREDENTIALS` | Mauvais identifiants | Vérifier login/mot de passe |

## Maintenance et dépannage

### Réinitialisation manuelle des sessions
```sql
-- Forcer déconnexion d'un utilisateur
UPDATE agents_suzosky SET current_session_token = NULL WHERE matricule = 'CM20250003';

-- Vider toutes les sessions PHP
-- Supprimer les fichiers dans C:\xampp\tmp\sess_*
```

### Logs de débogage
- Sessions PHP: fichiers `sess_*` dans `/tmp`
- Logs de connexion: table `agents_suzosky` colonnes `last_login_*`
- Logs Apache: `access.log` et `error.log`

## Évolutions futures possibles

1. **Multi-sessions contrôlées**: permettre X appareils simultanés par utilisateur
2. **Géolocalisation**: validation basée sur la proximité géographique
3. **Durée de session configurable**: sessions longue durée pour appareils de confiance
4. **Audit trail**: historique complet des connexions/déconnexions

---

**Dernière mise à jour**: 27 septembre 2025  
**Version**: 2.1.0  
**Auteur**: Système de gestion Suzosky Coursier