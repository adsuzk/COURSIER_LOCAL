<?php
/**
 * TEST API MOBILE - Vérifier ce que reçoit l'app coursier
 */

$coursier_id = 5;
$url = "http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=get_commandes&coursier_id=$coursier_id";

echo "=== TEST API MOBILE POUR COURSIER #$coursier_id ===\n\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";
echo "RÉPONSE:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n";
?>
