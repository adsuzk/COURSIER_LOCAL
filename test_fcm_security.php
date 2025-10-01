<?php
require_once 'Scripts/Scripts cron/fcm_token_security.php';
$fcm = new FCMTokenSecurity(['verbose' => true]);
$result = $fcm->canAcceptNewOrders();
echo "=== FCMTokenSecurity Test ===\n";
print_r($result);
echo "\n=== API Comparison ===\n";
$api_url = 'http://localhost/COURSIER_LOCAL/api/get_coursier_availability.php';
$api_response = file_get_contents($api_url);
$api_data = json_decode($api_response, true);
print_r($api_data);