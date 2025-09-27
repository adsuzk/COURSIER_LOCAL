# 🚀 GUIDE CONNEXION RÉSEAU LOCAL - CONFIGURATION RAPIDE

## ⚡ Configuration Express pour ton Setup

### 📍 **TON IP LOCALE DÉTECTÉE : `192.168.1.11`**

---

## 1. 📱 Configuration Android (NetworkConfig.kt)

```kotlin
object NetworkConfig {
    // ✅ IP de ton PC XAMPP
    private const val SERVER_IP = "192.168.1.11"
    private const val SERVER_PORT = "80"
    
    const val BASE_URL = "http://$SERVER_IP:$SERVER_PORT/COURSIER_LOCAL/"
    const val API_BASE_URL = "${BASE_URL}api/"
    
    // URLs principales
    const val LOGIN_URL = "${API_BASE_URL}agent_auth.php"
    const val ORDERS_URL = "${API_BASE_URL}get_coursier_orders_simple.php"
    const val UPDATE_STATUS_URL = "${API_BASE_URL}update_order_status.php"
}
```

---

## 2. 🧪 Tests de Vérification

### A. Test depuis Windows (PowerShell)
```powershell
# Tester l'API auth
Invoke-WebRequest -Uri "http://192.168.1.11/COURSIER_LOCAL/api/agent_auth.php?action=check_session" -UseBasicParsing

# Tester le login
Invoke-WebRequest -Uri "http://192.168.1.11/COURSIER_LOCAL/api/agent_auth.php?action=login&identifier=CM20250001&password=g4mKU" -UseBasicParsing
```

### B. Test depuis Android (ADB)
```bash
# Ping du serveur
adb shell ping 192.168.1.11

# Test HTTP
adb shell "curl -I http://192.168.1.11/COURSIER_LOCAL/"
```

---

## 3. 🔧 Script de Validation Automatique

**Lance ce script pour tout vérifier en une commande :**
```powershell
C:\xampp\php\php.exe -f c:\xampp\htdocs\COURSIER_LOCAL\test_network_setup.php
```

---

## 4. ❌ Dépannage Erreurs Courantes

### "Connection Refused"
- ✅ XAMPP Apache démarré ?
- ✅ Firewall Windows autorise Apache ?
- ✅ Même réseau WiFi Android/PC ?

### "404 Not Found"
- ✅ URL correcte : `http://192.168.1.11/COURSIER_LOCAL/`
- ✅ Dossier existe : `C:\xampp\htdocs\COURSIER_LOCAL\`

### "Unknown column 'description'" 
```powershell
# Réparation automatique
C:\xampp\php\php.exe -f C:\xampp\htdocs\COURSIER_LOCAL\emergency_add_description_columns.php
C:\xampp\php\php.exe -f C:\xampp\htdocs\COURSIER_LOCAL\install_legacy_compat.php
```

---

## 5. ✅ Checklist Rapide

**Avant de lancer l'app :**
- [ ] XAMPP Apache ✅ ON
- [ ] XAMPP MySQL ✅ ON
- [ ] Android NetworkConfig.SERVER_IP = `"192.168.1.11"`
- [ ] Test URL : http://192.168.1.11/COURSIER_LOCAL/ ✅ fonctionne
- [ ] Login CM20250001/g4mKU ✅ fonctionne

---

## 6. 🎯 Credentials de Test

```
Identifiant : CM20250001
Mot de passe : g4mKU
```

**URLs de test directes :**
- Login : http://192.168.1.11/COURSIER_LOCAL/api/agent_auth.php?action=login&identifier=CM20250001&password=g4mKU
- Session : http://192.168.1.11/COURSIER_LOCAL/api/agent_auth.php?action=check_session

---

**🔥 PLUS JAMAIS D'ERREUR RÉSEAU avec cette config !**