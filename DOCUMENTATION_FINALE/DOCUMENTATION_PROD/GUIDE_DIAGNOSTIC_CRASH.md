# 🔧 GUIDE DIAGNOSTIC - ARRÊT SYSTÉMATIQUE APPLICATION

## 🚨 **PROBLÈME IDENTIFIÉ**

L'application se compile correctement mais s'arrête au runtime. Voici comment diagnostiquer la cause exacte :

---

## 📋 **ÉTAPE 1 : TESTS DE DIAGNOSTIC**

### **Test 1 : Modifier le Manifeste temporairement**
Remplacez dans `AndroidManifest.xml` :
```xml
<activity
    android:name=".MainActivity"
```

Par :
```xml
<activity
    android:name=".MainActivityDiagnostic"
```

Puis relancez l'app pour voir si la version simplifiée fonctionne.

---

## 🔍 **ÉTAPE 2 : VÉRIFIER LES LOGS ANDROID**

### **Dans Android Studio :**
1. Ouvrez l'onglet **Logcat** 
2. Filtrez par votre package : `com.suzosky.coursier`
3. Relancez l'application
4. Cherchez les messages d'erreur avec :
   - `❌` (nos logs de debug)
   - `FATAL` (crashes critiques)
   - `Exception` (erreurs Java/Kotlin)

### **Logs typiques de crash :**
```
E/AndroidRuntime: FATAL EXCEPTION: main
Process: com.suzosky.coursier, PID: xxxx
java.lang.RuntimeException: [CAUSE DU CRASH]
```

---

## 🎯 **CAUSES PROBABLES ET SOLUTIONS**

### **1. Problème de Hilt/Dependency Injection**
**Symptôme :** App crash immédiat après le splash screen
**Solution :**
```kotlin
// Vérifiez dans MainActivity.kt
@AndroidEntryPoint  // <- Cette annotation doit être présente
class MainActivity : ComponentActivity()
```

### **2. Erreur dans les imports Compose**
**Symptôme :** Crash lors du `setContent`
**Solution :** Vérifiez les imports dans vos écrans, notamment :
```kotlin
import androidx.compose.material3.* // Au lieu de material2
import androidx.compose.ui.platform.LocalContext
```

### **3. Problème de permissions**
**Symptôme :** Crash lors de l'accès réseau/localisation
**Solution :** Vérifiez `AndroidManifest.xml` :
```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
```

### **4. Problème theme/couleurs**
**Symptôme :** Crash lors de l'affichage UI
**Solution :** Vérifiez `SuzoskyTheme` et les couleurs dans `ui/theme/`

---

## 🧪 **ÉTAPE 3 : TESTS PROGRESSIFS**

### **Si MainActivityDiagnostic fonctionne :**
1. ✅ Le problème n'est pas dans l'Activity de base
2. 🔍 Le problème est dans `LoginScreen` ou `CoursierScreenNew`

### **Test A - LoginScreen isolé :**
Modifiez `MainActivity.kt` pour ne charger que le login :
```kotlin
setContent {
    SuzoskyTheme {
        LoginScreen(onLoginSuccess = { 
            Toast.makeText(this@MainActivity, "Login OK!", Toast.LENGTH_SHORT).show() 
        })
    }
}
```

### **Test B - CoursierScreenNew isolé :**
```kotlin
setContent {
    SuzoskyTheme {
        CoursierScreenNew(onLogout = {})
    }
}
```

---

## 📱 **ÉTAPE 4 : SOLUTIONS RAPIDES**

### **Solution 1 : Build config**
Ajoutez dans `app/build.gradle.kts` :
```kotlin
android {
    buildFeatures {
        buildConfig = true
    }
    
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}
```

### **Solution 2 : Proguard/R8**
Ajoutez dans `proguard-rules.pro` :
```
-keep class com.suzosky.coursier.** { *; }
-dontwarn javax.annotation.**
-dontwarn kotlin.Metadata
```

### **Solution 3 : Clear cache**
```bash
./gradlew clean
./gradlew assembleDebug -x lintDebug
```

---

## 📝 **ÉTAPE 5 : COLLECTE D'INFORMATIONS**

### **Informations à récupérer :**
1. **Message d'erreur exact** dans Logcat
2. **Étape où l'app crash** (splash, login, main)
3. **Version Android** de votre appareil/émulateur
4. **Logs complets** de la première exécution

### **Commande pour logs détaillés :**
```bash
# Dans un terminal avec ADB
adb logcat -c  # Clear logs
adb logcat | grep -E "(FATAL|Exception|Error|suzosky)"
```

---

## 🚀 **APRÈS DIAGNOSTIC**

### **Si MainActivityDiagnostic fonctionne :**
→ Le problème est dans un écran spécifique
→ On peut isoler et corriger le composant défaillant

### **Si MainActivityDiagnostic crash aussi :**
→ Problème fondamental (theme, hilt, permissions)
→ Vérifier la configuration de base

---

## 📞 **PROCHAINES ÉTAPES**

1. **Testez MainActivityDiagnostic** en modifiant le manifeste
2. **Récupérez les logs Logcat** complets
3. **Identifiez à quelle étape** l'app crash
4. **Partagez les logs** pour un diagnostic précis

**L'app DOIT fonctionner maintenant avec la version diagnostic !** 🔧

---

*Guide créé le 18 septembre 2025*
*Version : Diagnostic v1.0*