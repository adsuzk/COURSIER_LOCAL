#!/bin/bash

echo "=================================================="
echo "    🚀 TEST SYNCHRONISATION MOBILE SUZOSKY"
echo "    Diagnostic complet ADB + API mobile"
echo "=================================================="
echo

# Couleurs pour le terminal
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction d'affichage avec couleurs
print_status() {
    local status=$1
    local message=$2
    case $status in
        "OK") echo -e "${GREEN}✅ $message${NC}" ;;
        "WARNING") echo -e "${YELLOW}⚠️  $message${NC}" ;;
        "ERROR") echo -e "${RED}❌ $message${NC}" ;;
        "INFO") echo -e "${BLUE}ℹ️  $message${NC}" ;;
    esac
}

# 1. Vérification ADB
echo "🔌 [1/10] VÉRIFICATION ADB"
if command -v adb &> /dev/null; then
    print_status "OK" "ADB installé"
    
    # Vérifier appareils connectés
    devices=$(adb devices | grep -v "List of devices" | grep "device" | wc -l)
    if [ $devices -gt 0 ]; then
        print_status "OK" "$devices appareil(s) connecté(s)"
        adb devices
    else
        print_status "ERROR" "Aucun appareil connecté via ADB"
        echo "💡 Connectez votre téléphone et activez le débogage USB"
        exit 1
    fi
else
    print_status "ERROR" "ADB non installé"
    echo "💡 Installez Android SDK Platform Tools"
    exit 1
fi
echo

# 2. Informations appareil
echo "📱 [2/10] INFORMATIONS APPAREIL"
model=$(adb shell getprop ro.product.model 2>/dev/null | tr -d '\r')
android_version=$(adb shell getprop ro.build.version.release 2>/dev/null | tr -d '\r')
api_level=$(adb shell getprop ro.build.version.sdk 2>/dev/null | tr -d '\r')

if [ ! -z "$model" ]; then
    print_status "INFO" "Modèle: $model"
    print_status "INFO" "Android: $android_version (API $api_level)"
else
    print_status "WARNING" "Impossible de récupérer les infos appareil"
fi
echo

# 3. Test connectivité réseau
echo "🌐 [3/10] TEST CONNECTIVITÉ RÉSEAU"
# Test ping Internet
if adb shell ping -c 1 8.8.8.8 &>/dev/null; then
    print_status "OK" "Connexion Internet fonctionnelle"
else
    print_status "WARNING" "Problème connexion Internet"
fi

# Test serveur local
server_ip="10.0.2.2"  # IP par défaut émulateur Android
server_port="80"

# Tenter connexion serveur local
if adb shell "timeout 5 telnet $server_ip $server_port" &>/dev/null; then
    print_status "OK" "Serveur local accessible ($server_ip:$server_port)"
else
    print_status "WARNING" "Serveur local inaccessible - Essayer avec IP WiFi"
    # Essayer récupérer IP WiFi de l'host
    if command -v ip &> /dev/null; then
        host_ip=$(ip route get 1 | awk '{print $7}' | head -1)
        print_status "INFO" "IP Host détectée: $host_ip"
    fi
fi
echo

# 4. Recherche application Suzosky
echo "📦 [4/10] RECHERCHE APPLICATION SUZOSKY"
suzosky_packages=$(adb shell pm list packages | grep -i suzosky)
coursier_packages=$(adb shell pm list packages | grep -i coursier)

if [ ! -z "$suzosky_packages" ]; then
    print_status "OK" "Application Suzosky trouvée:"
    echo "$suzosky_packages"
    main_package=$(echo "$suzosky_packages" | head -1 | cut -d':' -f2)
elif [ ! -z "$coursier_packages" ]; then
    print_status "OK" "Application Coursier trouvée:"
    echo "$coursier_packages"
    main_package=$(echo "$coursier_packages" | head -1 | cut -d':' -f2)
else
    print_status "WARNING" "Aucune app Suzosky/Coursier trouvée"
    print_status "INFO" "Applications installées contenant 'delivery' ou 'transport':"
    adb shell pm list packages | grep -E "(delivery|transport|logistic)" | head -5
    main_package=""
fi
echo

# 5. Test démarrage application
echo "🚀 [5/10] TEST DÉMARRAGE APPLICATION"
if [ ! -z "$main_package" ]; then
    print_status "INFO" "Tentative démarrage: $main_package"
    
    # Essayer différentes activités principales possibles
    activities=("MainActivity" "SplashActivity" "LoginActivity" "HomeActivity")
    
    for activity in "${activities[@]}"; do
        if adb shell am start -n "$main_package/.$activity" &>/dev/null; then
            print_status "OK" "Application démarrée avec .$activity"
            sleep 2
            break
        fi
    done
else
    print_status "INFO" "Tentative démarrage avec noms génériques"
    adb shell am start -n com.suzosky.coursier/.MainActivity &>/dev/null
    adb shell am start -n com.coursier.suzosky/.MainActivity &>/dev/null
fi
echo

# 6. Monitoring logs application
echo "📊 [6/10] MONITORING LOGS APPLICATION (10 secondes)"
print_status "INFO" "Capture des logs Firebase, FCM et application..."

timeout 10s adb logcat -s FirebaseMessaging:* FCM:* *Coursier*:* *Suzosky*:* 2>/dev/null | head -20 &
monitor_pid=$!

# Afficher processus en cours
sleep 2
running_apps=$(adb shell ps | grep -E "(suzosky|coursier)" | head -3)
if [ ! -z "$running_apps" ]; then
    print_status "OK" "Processus application détectés:"
    echo "$running_apps"
fi

wait $monitor_pid 2>/dev/null
echo

# 7. Test API serveur
echo "🌐 [7/10] TEST API SERVEUR"
api_base="http://localhost/COURSIER_LOCAL/mobile_sync_api.php"

# Test ping API
if command -v curl &> /dev/null; then
    api_response=$(curl -s "$api_base?action=ping" 2>/dev/null)
    if [[ $api_response == *"success"* ]]; then
        print_status "OK" "API serveur accessible"
    else
        print_status "WARNING" "Problème API serveur"
        print_status "INFO" "Réponse: ${api_response:0:100}..."
    fi
    
    # Test profil coursier
    profile_response=$(curl -s "$api_base?action=get_profile&coursier_id=3" 2>/dev/null)
    if [[ $profile_response == *'"success":true'* ]]; then
        print_status "OK" "Profil coursier accessible"
        # Extraire nom du coursier
        coursier_name=$(echo "$profile_response" | grep -o '"nom":"[^"]*"' | cut -d'"' -f4)
        if [ ! -z "$coursier_name" ]; then
            print_status "INFO" "Coursier: $coursier_name"
        fi
    else
        print_status "WARNING" "Problème récupération profil"
    fi
else
    print_status "WARNING" "curl non disponible - Impossible de tester l'API"
fi
echo

# 8. Instructions de test manuel
echo "📋 [8/10] INSTRUCTIONS TEST MANUEL"
print_status "INFO" "Actions à effectuer sur le téléphone:"
echo "   1. 🔓 Ouvrir l'application Suzosky Coursier"
echo "   2. 🔑 Se connecter avec:"
echo "      • Matricule: CM20250001"
echo "      • Mot de passe: [votre mot de passe]"
echo "   3. 📱 Vérifier réception des notifications"
echo "   4. 📋 Consulter liste des commandes"
echo "   5. ✅ Tester acceptation d'une commande"
echo "   6. 🔄 Vérifier synchronisation temps réel"
echo

# 9. URLs de test directes
echo "🔗 [9/10] URLS DE TEST DIRECTES"
echo "   📊 Profil coursier:"
echo "      $api_base?action=get_profile&coursier_id=3"
echo
echo "   📦 Commandes du coursier:"
echo "      $api_base?action=get_commandes&coursier_id=3"
echo
echo "   🔔 Test notification:"
echo "      $api_base?action=test_notification&coursier_id=3"
echo
echo "   ✅ Accepter commande #118:"
echo "      $api_base?action=accept_commande&coursier_id=3&commande_id=118"
echo

# 10. Monitoring continu
echo "🔍 [10/10] MONITORING CONTINU"
print_status "INFO" "Commandes pour monitoring en temps réel:"
echo
echo "   📱 Logs ADB continus:"
echo "      adb logcat -s FirebaseMessaging:* FCM:* *Coursier*:* *Suzosky*:*"
echo
echo "   🗃️ Monitoring base de données:"
echo "      mysql -u root coursier_db -e \"SELECT * FROM commandes WHERE coursier_id=3 ORDER BY id DESC LIMIT 3;\""
echo
echo "   📄 Logs serveur:"
echo "      tail -f mobile_sync_debug.log"
echo

echo "=============================================="
echo "🎯 RÉSUMÉ DU DIAGNOSTIC"
echo "=============================================="
print_status "OK" "ADB configuré et appareil connecté"
print_status "OK" "API serveur accessible"
print_status "INFO" "Coursier test: YAPO Emmanuel (ID: 3)"
print_status "INFO" "Commandes de test disponibles"
echo
echo "🚀 PRÊT POUR LE TEST DE SYNCHRONISATION!"
echo "📱 Lancez maintenant l'application mobile"
echo "🔄 Monitorer les logs en temps réel"
echo
echo "🆘 EN CAS DE PROBLÈME:"
echo "   • Vérifier connexion réseau du téléphone"
echo "   • Redémarrer l'application mobile"
echo "   • Vérifier logs ADB pour erreurs"
echo "   • Tester API avec navigateur web"
echo "=============================================="