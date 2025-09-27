<?php
// Proxy root endpoint to ensure legacy APKs calling /agent_auth.php keep working
// Routes to api/agent_auth.php
require_once __DIR__ . '/api/agent_auth.php';
