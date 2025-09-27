<?php
echo "<!DOCTYPE html><html><head><title>Test Tokens Debug</title></head><body>";
echo "<h1>🔍 Debug FCM Tokens</h1>";

// Test connexion base
$config_path = __DIR__ . '/../config.php';
echo "<p>📁 Tentative de chargement: <code>$config_path</code></p>";

if (!file_exists($config_path)) {
    die("<p style='color:red'>❌ Fichier config.php non trouvé!</p></body></html>");
}

require_once $config_path;

// Test connexion MySQL
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✅ Connexion base réussie</p>";
} catch (Exception $e) {
    die("<p style='color:red'>❌ Erreur base: " . htmlspecialchars($e->getMessage()) . "</p></body></html>");
}

// Vérifier la table device_tokens
try {
    $sql = "SHOW TABLES LIKE 'device_tokens'";
    $stmt = $pdo->query($sql);
    if ($stmt->rowCount() == 0) {
        echo "<p style='color:orange'>⚠️ Table device_tokens n'existe pas</p>";
    } else {
        echo "<p style='color:green'>✅ Table device_tokens existe</p>";
        
        // Compter les tokens
        $count_sql = "SELECT COUNT(*) as total FROM device_tokens";
        $count_stmt = $pdo->query($count_sql);
        $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>📊 Nombre total de tokens: <strong>$count</strong></p>";
        
        if ($count > 0) {
            // Afficher les derniers tokens
            $recent_sql = "SELECT coursier_id, platform, app_version, created_at, is_active, 
                          SUBSTRING(token, 1, 30) as token_preview 
                          FROM device_tokens 
                          ORDER BY created_at DESC 
                          LIMIT 5";
            $recent_stmt = $pdo->query($recent_sql);
            echo "<h3>🕐 Derniers tokens enregistrés:</h3><table border='1' style='border-collapse:collapse'>";
            echo "<tr><th>Coursier ID</th><th>Platform</th><th>Version</th><th>Créé</th><th>Actif</th><th>Token (30 premiers chars)</th></tr>";
            while ($row = $recent_stmt->fetch(PDO::FETCH_ASSOC)) {
                $active_badge = $row['is_active'] ? '✅' : '❌';
                echo "<tr>";
                echo "<td>{$row['coursier_id']}</td>";
                echo "<td>{$row['platform']}</td>";
                echo "<td>{$row['app_version']}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "<td>$active_badge</td>";
                echo "<td><code>{$row['token_preview']}...</code></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Afficher la structure
        $desc_sql = "DESCRIBE device_tokens";
        $desc_stmt = $pdo->query($desc_sql);
        echo "<h3>🗃️ Structure de la table:</h3><table border='1' style='border-collapse:collapse'>";
        echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Défaut</th></tr>";
        while ($col = $desc_stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur requête: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p><small>🕐 Test généré le " . date('Y-m-d H:i:s') . "</small></p>";
echo "</body></html>";
?>