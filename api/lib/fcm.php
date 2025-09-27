<?php
// api/lib/fcm.php - Simple FCM sender
function fcm_send($tokens, $title, $body, $data = []) {
    $serverKey = getenv('FCM_SERVER_KEY');
    if (!$serverKey) {
        return ['success' => false, 'error' => 'FCM_SERVER_KEY missing'];
    }
    $url = 'https://fcm.googleapis.com/fcm/send';
    $payload = [
        'registration_ids' => array_values($tokens),
        'notification' => [
            'title' => $title,
            'body' => $body,
            'sound' => 'default'
        ],
        'data' => $data,
        'priority' => 'high'
    ];
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $result = curl_exec($ch);
    if ($result === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $err];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['success' => $code >= 200 && $code < 300, 'code' => $code, 'result' => $result];
}