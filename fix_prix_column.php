<?php
$file = 'admin/comptabilite.php';
$content = file_get_contents($file);

// Remplacer prix par prix_total sauf dans prix_moyen et prix_estime
$content = preg_replace('/\bc\.prix\b(?!_)/', 'c.prix_total', $content);
$content = preg_replace('/SUM\(prix\)/', 'SUM(prix_total)', $content);
$content = preg_replace('/AVG\(c\.prix\)/', 'AVG(c.prix_total)', $content);

file_put_contents($file, $content);
echo "✅ Fichier comptabilite.php corrigé : prix → prix_total\n";
