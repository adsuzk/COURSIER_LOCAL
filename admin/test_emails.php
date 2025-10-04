<?php
/**
 * Test rapide du système d'emails V2.0
 * Accéder à: http://localhost/COURSIER_LOCAL/admin/test_emails.php
 */

require_once __DIR__ . '/../config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Système Emails V2.0</title>
    <style>
        body { font-family: Arial; max-width: 1200px; margin: 50px auto; padding: 20px; background: #1A1A1A; color: #E5E5E5; }
        h1 { color: #D4A853; }
        .test { background: rgba(255,255,255,0.05); padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid rgba(212,168,83,0.2); }
        .success { color: #10B981; }
        .error { color: #EF4444; }
        pre { background: #000; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(212,168,83,0.2); color: #D4A853; }
    </style>
</head>
<body>
<h1>📧 Test Système Emails V2.0</h1>
";

try {
    $pdo = getPDO();
    echo "<div class='test'><span class='success'>✅ Connexion PDO réussie</span></div>";
    
    // Test 1: Vérifier l'existence des tables
    echo "<div class='test'><h2>Test 1: Vérification des Tables</h2>";
    $tables = ['email_logs', 'email_campaigns', 'email_templates', 'email_settings'];
    $allExist = true;
    
    echo "<table><tr><th>Table</th><th>Statut</th><th>Nombre d'entrées</th></tr>";
    foreach ($tables as $table) {
        $exists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
        if ($exists) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<tr><td>$table</td><td class='success'>✅ Existe</td><td>$count</td></tr>";
        } else {
            echo "<tr><td>$table</td><td class='error'>❌ N'existe pas</td><td>-</td></tr>";
            $allExist = false;
        }
    }
    echo "</table>";
    
    if (!$allExist) {
        echo "<p class='error'>⚠️ Certaines tables n'existent pas. Accédez à <a href='../admin.php?section=emails' style='color: #D4A853;'>admin.php?section=emails</a> pour les créer automatiquement.</p>";
    } else {
        echo "<p class='success'>✅ Toutes les tables existent</p>";
    }
    echo "</div>";
    
    // Test 2: Vérifier les templates
    echo "<div class='test'><h2>Test 2: Templates Disponibles</h2>";
    $templates = $pdo->query("SELECT * FROM email_templates")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($templates)) {
        echo "<p class='error'>❌ Aucun template trouvé</p>";
    } else {
        echo "<p class='success'>✅ " . count($templates) . " template(s) trouvé(s)</p>";
        echo "<table><tr><th>ID</th><th>Nom</th><th>Type</th><th>Sujet</th></tr>";
        foreach ($templates as $t) {
            echo "<tr><td>#{$t['id']}</td><td>{$t['name']}</td><td>{$t['type']}</td><td>" . substr($t['subject'], 0, 50) . "...</td></tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Test 3: Compter les destinataires potentiels
    echo "<div class='test'><h2>Test 3: Destinataires Potentiels</h2>";
    $particuliers = $pdo->query("SELECT COUNT(*) FROM clients_particuliers WHERE email IS NOT NULL AND email != ''")->fetchColumn();
    $business = $pdo->query("SELECT COUNT(*) FROM business_clients WHERE contact_email IS NOT NULL AND contact_email != ''")->fetchColumn();
    $total = $pdo->query("SELECT COUNT(DISTINCT email) FROM (
        SELECT email FROM clients_particuliers WHERE email IS NOT NULL AND email != ''
        UNION
        SELECT contact_email as email FROM business_clients WHERE contact_email IS NOT NULL AND contact_email != ''
    ) as all_emails")->fetchColumn();
    
    echo "<table>";
    echo "<tr><td>👤 Clients Particuliers</td><td class='success'><strong>$particuliers</strong></td></tr>";
    echo "<tr><td>🏢 Clients Business</td><td class='success'><strong>$business</strong></td></tr>";
    echo "<tr><td>📧 Total Unique</td><td class='success'><strong>$total</strong></td></tr>";
    echo "</table>";
    echo "</div>";
    
    // Test 4: Statistiques emails envoyés
    echo "<div class='test'><h2>Test 4: Statistiques Emails</h2>";
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened
        FROM email_logs
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($stats['total'] == 0) {
        echo "<p>Aucun email envoyé pour le moment.</p>";
    } else {
        $openRate = $stats['sent'] > 0 ? round(($stats['opened'] / $stats['sent']) * 100, 1) : 0;
        echo "<table>";
        echo "<tr><td>Total Emails</td><td><strong>{$stats['total']}</strong></td></tr>";
        echo "<tr><td>✅ Envoyés</td><td class='success'><strong>{$stats['sent']}</strong></td></tr>";
        echo "<tr><td>❌ Échoués</td><td class='error'><strong>{$stats['failed']}</strong></td></tr>";
        echo "<tr><td>👁️ Ouverts</td><td class='success'><strong>{$stats['opened']}</strong></td></tr>";
        echo "<tr><td>📊 Taux d'Ouverture</td><td><strong>{$openRate}%</strong></td></tr>";
        echo "</table>";
    }
    echo "</div>";
    
    // Test 5: Campagnes
    echo "<div class='test'><h2>Test 5: Campagnes</h2>";
    $campaigns = $pdo->query("SELECT COUNT(*) FROM email_campaigns")->fetchColumn();
    echo "<p>Nombre de campagnes créées: <strong>$campaigns</strong></p>";
    echo "</div>";
    
    // Test 6: Structure de la base
    echo "<div class='test'><h2>Test 6: Structure Table email_logs</h2>";
    $columns = $pdo->query("DESCRIBE email_logs")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table></div>";
    
    // Récapitulatif
    echo "<div class='test' style='background: rgba(16,185,129,0.1); border-color: #10B981;'>";
    echo "<h2>✅ Récapitulatif</h2>";
    echo "<ul>";
    echo "<li class='success'>Connexion base de données: OK</li>";
    echo "<li class='success'>Tables créées: " . ($allExist ? "OK" : "ERREUR") . "</li>";
    echo "<li class='success'>Templates disponibles: " . count($templates) . "</li>";
    echo "<li class='success'>Destinataires potentiels: $total</li>";
    echo "</ul>";
    echo "<p style='margin-top: 20px;'><strong>🎉 Le système d'emails V2.0 est prêt à être utilisé !</strong></p>";
    echo "<p><a href='../admin.php?section=emails' style='display: inline-block; background: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%); color: #1A1A1A; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 10px;'>🚀 Accéder à l'Interface Emails</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='test' style='border-color: #EF4444;'>";
    echo "<h2 class='error'>❌ Erreur</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<div style='margin-top: 30px; padding: 20px; background: rgba(59,130,246,0.1); border-radius: 8px; border: 1px solid rgba(59,130,246,0.3);'>";
echo "<h3 style='color: #3B82F6;'>📚 Documentation</h3>";
echo "<p>Consultez le fichier <strong>EMAIL_SYSTEM_V2_README.md</strong> pour la documentation complète.</p>";
echo "<ul>";
echo "<li>📊 Dashboard avec statistiques en temps réel</li>";
echo "<li>✉️ Envoi d'emails individuels</li>";
echo "<li>📢 Campagnes massives ciblées</li>";
echo "<li>📝 Gestion de templates avec variables</li>";
echo "<li>📋 Historique complet avec filtres</li>";
echo "<li>📈 Analytics avancées avec insights</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>
