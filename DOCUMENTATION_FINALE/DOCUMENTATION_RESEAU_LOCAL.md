# Guide de Configuration Réseau Local - Coursier App

## 🌐 Configuration Réseau Local (XAMPP + Android)

### 1. Configuration Serveur Local (XAMPP)

#### A. Vérifier l'IP locale du serveur
```powershell
# Dans PowerShell, obtenir l'IP locale
ipconfig | findstr "IPv4"
```
Exemple de résultat : `192.168.1.100`

#### B. Configuration Apache (XAMPP)
1. Ouvrir `C:\xampp\apache\conf\httpd.conf`
2. Chercher la ligne `Listen 80`
3. Ajouter après :
```apache
Listen 192.168.1.100:80
```

#### C. Configuration PHP (config.php)
Modifier `c:\xampp\htdocs\COURSIER_LOCAL\config.php` :
```php
// Configuration automatique IP locale
function getLocalServerIP() {
    // En local, détecter l'IP automatiquement
    $localIP = '192.168.1.100'; // ⚠️ REMPLACER par ton IP locale
    return $localIP;
}

$config = [
    'db' => [
        'host' => 'localhost', // Ne pas changer
        'dbname' => 'coursier_prod',
        'username' => 'root',
        'password' => ''
    ],
    'app' => [
        'base_url' => 'http://' . getLocalServerIP() . '/COURSIER_LOCAL/',
        'api_base' => 'http://' . getLocalServerIP() . '/COURSIER_LOCAL/api/',
        // ...
    ]
];
```

### 2. Configuration Application Android

#### A. Fichier de configuration réseau
Dans ton app Android, créer/modifier `NetworkConfig.kt` :
```kotlin
object NetworkConfig {
    // ⚠️ REMPLACER 192.168.1.100 par l'IP de ton PC XAMPP
    private const val SERVER_IP = "192.168.1.100"
    private const val SERVER_PORT = "80"
    
    const val BASE_URL = "http://$SERVER_IP:$SERVER_PORT/COURSIER_LOCAL/"
    const val API_BASE_URL = "${BASE_URL}api/"
    
    // URLs spécifiques
    const val LOGIN_URL = "${API_BASE_URL}agent_auth.php"
    const val ORDERS_URL = "${API_BASE_URL}get_coursier_orders_simple.php"
    const val UPDATE_STATUS_URL = "${API_BASE_URL}update_order_status.php"
}
```

#### B. Configuration Retrofit/OkHttp
```kotlin
class ApiClient {
    companion object {
        fun getClient(): Retrofit {
            val logging = HttpLoggingInterceptor()
            logging.level = HttpLoggingInterceptor.Level.BODY
            
            val client = OkHttpClient.Builder()
                .addInterceptor(logging)
                .connectTimeout(30, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .cookieJar(JavaNetCookieJar(CookieManager()))
                .build()
                
            return Retrofit.Builder()
                .baseUrl(NetworkConfig.API_BASE_URL)
                .client(client)
                .addConverterFactory(GsonConverterFactory.create())
                .build()
        }
    }
}
```

### 3. Tests de Connectivité

#### A. Test depuis Windows (PowerShell)
```powershell
# Test 1: Ping du serveur
ping 192.168.1.100

# Test 2: Test HTTP
Invoke-WebRequest -Uri "http://192.168.1.100/COURSIER_LOCAL/api/agent_auth.php?action=check_session" -UseBasicParsing

# Test 3: Vérifier que XAMPP écoute
netstat -an | findstr :80
```

#### B. Test depuis Android (ADB)
```bash
# Vérifier la connectivité réseau depuis l'émulateur/device
adb shell ping 192.168.1.100

# Test HTTP depuis l'appareil
adb shell "curl -I http://192.168.1.100/COURSIER_LOCAL/"
```

### 4. Résolution des Erreurs Courantes

#### ❌ "Network Error" / "Connection refused"
**Cause** : IP incorrecte ou serveur non accessible
**Solution** :
1. Vérifier l'IP locale : `ipconfig`
2. Tester l'accès : `http://IP_LOCALE/COURSIER_LOCAL/`
3. Redémarrer Apache (XAMPP Control Panel)

#### ❌ "404 Not Found"
**Cause** : Chemin incorrect
**Solution** :
1. Vérifier que le dossier existe : `C:\xampp\htdocs\COURSIER_LOCAL\`
2. Tester l'URL complète : `http://IP/COURSIER_LOCAL/index.php`

#### ❌ "CORS Error" sur navigateur mobile
**Cause** : Headers CORS manquants
**Solution** : Headers déjà ajoutés dans `api/agent_auth.php`

#### ❌ "Unknown column 'description'"
**Cause** : Colonnes manquantes en base
**Solution** : Exécuter les scripts de réparation :
```powershell
C:\xampp\php\php.exe -f c:\xampp\htdocs\COURSIER_LOCAL\emergency_add_description_columns.php
C:\xampp\php\php.exe -f c:\xampp\htdocs\COURSIER_LOCAL\install_legacy_compat.php
```

### 5. Checklist de Vérification Rapide

#### ✅ Avant de lancer l'app Android :
1. [ ] XAMPP Apache démarré
2. [ ] MySQL démarré  
3. [ ] IP locale identifiée (`ipconfig`)
4. [ ] URL test fonctionne : `http://IP_LOCALE/COURSIER_LOCAL/`
5. [ ] API test fonctionne : `http://IP_LOCALE/COURSIER_LOCAL/api/agent_auth.php?action=check_session`

#### ✅ Dans l'app Android :
1. [ ] `NetworkConfig.SERVER_IP` = IP locale correcte
2. [ ] Permissions réseau dans `AndroidManifest.xml`
3. [ ] Device/émulateur sur le même réseau WiFi

### 6. Script de Test Automatique

Créé un script `test_network_setup.php` pour validation rapide :
```php
<?php
// Test complet connectivité réseau
echo "=== TEST CONNECTIVITÉ RÉSEAU LOCAL ===\n";

// 1. IP du serveur
$serverIP = $_SERVER['SERVER_ADDR'] ?? 'localhost';
echo "IP Serveur: $serverIP\n";

// 2. Test DB
try {
    require_once __DIR__ . '/config.php';
    $pdo = getDBConnection();
    echo "✅ Base de données OK\n";
} catch (Exception $e) {
    echo "❌ Base de données: " . $e->getMessage() . "\n";
}

// 3. Test API auth
$testURL = "http://$serverIP/COURSIER_LOCAL/api/agent_auth.php?action=check_session";
echo "URL Test: $testURL\n";

$result = @file_get_contents($testURL);
if ($result) {
    echo "✅ API accessible\n";
} else {
    echo "❌ API non accessible\n";
}

echo "\n=== CONFIGURATION ANDROID ===\n";
echo "À configurer dans NetworkConfig.kt:\n";
echo "SERVER_IP = \"$serverIP\"\n";
echo "BASE_URL = \"http://$serverIP/COURSIER_LOCAL/\"\n";
?>
```

### 7. Support et Dépannage

**En cas de problème persistant** :
1. Redémarrer XAMPP complètement
2. Vérifier le firewall Windows (autoriser Apache)
3. Tester avec un autre device sur le même réseau
4. Utiliser l'IP `10.0.2.2` si émulateur Android Studio

**Logs utiles** :
- Apache : `C:\xampp\apache\logs\error.log`
- PHP : `C:\xampp\php\logs\php_error_log`
- Android : `adb logcat | grep CoursierApp`