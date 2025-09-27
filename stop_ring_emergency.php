<?php
// Script d'urgence pour arrêter la sonnerie
echo "=== ARRÊT D'URGENCE SONNERIE ===\n";

echo "Exécution commande ADB pour arrêter l'app...\n";
$adb_path = 'C:\Users\manud\AppData\Local\Android\Sdk\platform-tools\adb.exe';
$device_id = '12334454CF015507';

// Commandes d'arrêt
$commands = [
    "$adb_path -s $device_id shell am force-stop com.suzosky.coursier.debug",
    "$adb_path -s $device_id shell am kill com.suzosky.coursier.debug",
    "$adb_path -s $device_id shell killall com.suzosky.coursier.debug"
];

foreach ($commands as $cmd) {
    echo "Exécution: $cmd\n";
    $output = shell_exec($cmd . " 2>&1");
    if ($output) {
        echo "Sortie: $output\n";
    }
}

echo "✅ Commandes d'arrêt envoyées\n";
echo "La sonnerie devrait maintenant s'arrêter.\n";
echo "\nSi elle continue, redémarrez le téléphone.\n";
?>