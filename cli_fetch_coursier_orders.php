<?php
// Simple helper to fetch coursier orders via the same endpoint used by the mobile app.
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_GET['coursier_id'] = $argv[1] ?? '7';
$_GET['token'] = $argv[2] ?? null; // optional token if endpoint requires it
require __DIR__ . '/api/get_coursier_orders_simple.php';
