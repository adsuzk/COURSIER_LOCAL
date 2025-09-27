<?php
// CLI harness to simulate POST /api/agent_auth.php?action=login
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_POST['action'] = 'login';
$_POST['identifier'] = getenv('AGENT_ID') ?: 'dummy';
$_POST['password'] = getenv('AGENT_PWD') ?: 'dummy';
require __DIR__ . '/api/agent_auth.php';
