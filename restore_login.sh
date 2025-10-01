#!/bin/bash
# Script pour reconnecter le coursier #5
adb shell "run-as com.suzosky.coursier.debug sh -c 'mkdir -p /data/data/com.suzosky.coursier.debug/shared_prefs'"
adb shell "run-as com.suzosky.coursier.debug sh -c 'cat > /data/data/com.suzosky.coursier.debug/shared_prefs/suzosky_prefs.xml << EOF
<?xml version=\"1.0\" encoding=\"utf-8\" standalone=\"yes\" ?>
<map>
    <boolean name=\"is_logged_in\" value=\"true\" />
    <string name=\"coursier_id\">5</string>
    <string name=\"coursier_nom\">Test Coursier</string>
    <string name=\"coursier_telephone\">+225 05 05 05 05 05</string>
</map>
EOF'"
echo "Redémarrage de l'app..."
adb shell am force-stop com.suzosky.coursier.debug
adb shell am start -n com.suzosky.coursier.debug/com.suzosky.coursier.MainActivity
