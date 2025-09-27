<?php
// CLI helper to reset agent password quickly.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['matricule'] = $argv[1] ?? 'CM20250001';
require __DIR__ . '/api/reset_agent_password.php';
