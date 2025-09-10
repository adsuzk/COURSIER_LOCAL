<?php
// repair_index.php
// Script de réparation on-line pour patcher index.html en production
// A exécuter depuis le navigateur ou CLI: https://votre-domaine/scripts/repair_index.php

$indexFile = __DIR__ . '/../index.html';
if (!file_exists($indexFile)) {
    http_response_code(404);
    echo 'Fichier index.html introuvable.';
    exit;
}
if (!is_writable($indexFile)) {
    http_response_code(500);
    echo 'index.html n\'est pas accessible en écriture.';
    exit;
}

$content = file_get_contents($indexFile);

// 1) Correction de proceedToPayment fallback
$search1 = 'price: window.currentPriceData?.totalPrice || 0,';
$replace1 = 'price: window.currentPriceData?.totalPrice || 1500,';
$content = str_replace($search1, $replace1, $content);

// 2) Correction de submitOrderDirect fallback
$search2 = 'price: window.currentPriceData?.totalPrice || 1500,';
$replace2 = 'price: window.currentPriceData?.totalPrice || 1500,'; // Peut ajuster si besoin
$content = str_replace($search2, $replace2, $content);

// 3) Utiliser orderData.price pour amount
$search3 = 'const amount = createResult.data.price;';
$replace3 = 'const amount = orderData.price;';
$content = str_replace($search3, $replace3, $content);

// 4) Patcher proceedToPayment construction
$search4 = 'price: window.currentPrice || \'À calculer\',';
$replace4 = 'price: window.currentPriceData?.totalPrice || 1500,';
$content = str_replace($search4, $replace4, $content);

file_put_contents($indexFile, $content);
echo 'Patch appliqué avec succès à index.html';
