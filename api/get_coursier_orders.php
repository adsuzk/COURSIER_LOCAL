<?php
/**
 * FIX TEMPORAIRE: Redirection vers l'API simplifiée qui fonctionne
 * Évite les crashes NetworkOnMainThreadException
 */

// Redirection immédiate vers l'API qui fonctionne
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$newUrl = './get_coursier_orders_simple.php' . ($queryString ? '?' . $queryString : '');

header('Location: ' . $newUrl);
exit;
?>