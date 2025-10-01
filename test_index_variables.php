<?php
// Test de la page index pour voir les variables injectées
require_once 'config.php';

$sessionSenderPhoneRaw = $_SESSION['client_telephone'] ?? '';
$sessionSenderPhoneDisplay = '';
$sessionSenderPhoneDigits = '';

$hasClientSession = !empty($_SESSION['client_id'] ?? null)
    || !empty($_SESSION['client_email'] ?? null)
    || !empty($_SESSION['client_telephone'] ?? null);

// Logique FCM
$coursiersDisponibles = false;
$messageIndisponibilite = '';
$commercialFallbackMessage = "Nos coursiers sont actuellement très sollicités. Restez sur cette page — des coursiers se libèrent dans un instant et le formulaire se rouvrira automatiquement pour vous permettre de commander immédiatement. Merci pour votre patience !";

try {
    require_once __DIR__ . '/fcm_token_security.php';
    $fcmSec = new FCMTokenSecurity(['verbose' => false]);
    $canAccept = $fcmSec->canAcceptNewOrders();
    $coursiersDisponibles = (is_array($canAccept) && !empty($canAccept['can_accept_orders']));
    if (!$coursiersDisponibles) {
        $messageIndisponibilite = method_exists($fcmSec, 'getUnavailabilityMessage') ? $fcmSec->getUnavailabilityMessage() : "Service momentanément indisponible.";
    }
    if (trim((string)$messageIndisponibilite) === '' || $messageIndisponibilite === "Service momentanément indisponible.") {
        $messageIndisponibilite = $commercialFallbackMessage;
    }
} catch (Exception $e) {
    error_log('[SECURITY] Erreur vérification disponibilité coursiers (FCMTokenSecurity): ' . $e->getMessage());
    $coursiersDisponibles = false;
    $messageIndisponibilite = "Service momentanément indisponible.";
}

$initialCoursierAvailability = isset($coursiersDisponibles) ? (bool)$coursiersDisponibles : false;
if (!$hasClientSession) {
    $initialCoursierAvailability = true;
}
$defaultUnavailableMessage = $messageIndisponibilite ?? ($commercialFallbackMessage ?? 'Nos coursiers sont momentanément indisponibles. Restez sur la page, un coursier va se reconnecter.');
if (trim((string)$defaultUnavailableMessage) === '') {
    $defaultUnavailableMessage = 'Nos coursiers sont momentanément indisponibles. Restez sur la page, un coursier va se reconnecter.';
}

echo "=== Variables d'état ===\n";
echo "hasClientSession: " . ($hasClientSession ? 'true' : 'false') . "\n";
echo "coursiersDisponibles: " . ($coursiersDisponibles ? 'true' : 'false') . "\n";
echo "initialCoursierAvailability: " . ($initialCoursierAvailability ? 'true' : 'false') . "\n";
echo "messageIndisponibilite: " . $messageIndisponibilite . "\n";
echo "defaultUnavailableMessage: " . $defaultUnavailableMessage . "\n";

echo "\n=== Variables JavaScript qui seront injectées ===\n";
echo "window.initialCoursierAvailability = " . ($initialCoursierAvailability ? 'true' : 'false') . ";\n";
echo "window.hasClientSession = " . ($hasClientSession ? 'true' : 'false') . ";\n";
echo "window.COMMERCIAL_FALLBACK_MESSAGE = " . json_encode($defaultUnavailableMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
echo "window.initialCoursierMessage = " . json_encode($defaultUnavailableMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
?>