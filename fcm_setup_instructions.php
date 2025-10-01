<?php
/**
 * CONFIGURATION FCM - Instructions de déploiement
 */

echo "=== CONFIGURATION FCM FIREBASE ===\n\n";

echo "Pour activer les notifications FCM, suivez ces étapes:\n\n";

echo "1️⃣  OBTENIR LA CLÉ SERVEUR LEGACY:\n";
echo "   - Allez sur https://console.firebase.google.com\n";
echo "   - Sélectionnez votre projet: coursier-suzosky\n";
echo "   - Paramètres du projet > Cloud Messaging\n";
echo "   - Copiez la 'Clé de serveur' (Legacy server key)\n\n";

echo "2️⃣  CONFIGURER LA CLÉ:\n";
echo "   Option A - Variable d'environnement (recommandé):\n";
echo "     set FCM_SERVER_KEY=VOTRE_CLE_ICI\n\n";
echo "   Option B - Fichier .env:\n";
echo "     Créer .env avec: FCM_SERVER_KEY=VOTRE_CLE_ICI\n\n";

echo "3️⃣  VÉRIFIER LA CONFIGURATION:\n";
echo "   - Fichier Firebase: coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json\n";

if (file_exists(__DIR__ . '/coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json')) {
    echo "   ✅ Fichier trouvé\n";
    $config = json_decode(file_get_contents(__DIR__ . '/coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json'), true);
    echo "   Project ID: " . ($config['project_id'] ?? 'non trouvé') . "\n";
} else {
    echo "   ❌ Fichier non trouvé\n";
}

echo "\n4️⃣  TESTER:\n";
echo "   php test_fcm_simple.php\n\n";

echo "📖 DOCUMENTATION:\n";
echo "   Voir: DOCUMENTATION_FCM_FIREBASE_FINAL.md\n\n";

// Pour le moment, créer un mode fallback qui log sans envoyer
echo "🔧 MODE DÉVELOPPEMENT ACTIVÉ:\n";
echo "   Les notifications seront SIMULÉES jusqu'à configuration de la clé FCM\n";
echo "   Les commandes seront ATTRIBUÉES normalement aux coursiers\n\n";
?>
