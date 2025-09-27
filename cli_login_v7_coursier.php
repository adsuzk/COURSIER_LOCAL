<?php
// Simule un POST V7 vers coursier.php avec alias et JSON
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$payload = [
    // Essaye avec alias typiques V7
    'matricule' => getenv('AGENT_ID') ?: 'dummy',
    'pwd' => getenv('AGENT_PWD') ?: 'dummy'
];
$raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
// Injecte le corps JSON via flux php://input
class PhpInputMock {
    private static $data;
    public static function set($s){ self::$data=$s; }
    public static function get(){ return self::$data; }
}
// Override file_get_contents pour php://input — non trivial via CLI; on inclut agent_auth directement
// Ici on simule simplement en peuplant $_POST après decode manuel (comme le code coursier.php le ferait)
$decoded = json_decode($raw, true);
foreach ($decoded as $k=>$v) { $_POST[$k] = $v; }
require __DIR__ . '/coursier.php';
