<?php
// Simple CLI harness to simulate GET /api/index.php?action=ping
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET['action'] = 'ping';
require __DIR__ . '/../api/index.php';
