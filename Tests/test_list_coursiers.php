<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Liste Coursiers</title></head><body>";
echo "<h1>🧾 Aperçu des coursiers</h1>";

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
    echo "<p style='color:green'>✅ Connexion base OK</p>";

    $stmt = $pdo->query("SHOW COLUMNS FROM coursiers");
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    echo "<p><strong>Colonnes:</strong> " . htmlspecialchars(implode(', ', $columns)) . "</p>";

    $idColumn = 'id';
    foreach (['id', 'ID', 'id_coursier', 'coursier_id'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $idColumn = $candidate;
            break;
        }
    }

    $nameColumn = null;
    foreach (['nom', 'name', 'fullname', 'prenom', 'full_name'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $nameColumn = $candidate;
            break;
        }
    }

    $phoneColumn = null;
    foreach (['telephone', 'phone', 'mobile', 'tel'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $phoneColumn = $candidate;
            break;
        }
    }

    $query = sprintf('SELECT * FROM coursiers ORDER BY %s ASC LIMIT 10', "`$idColumn`");
    $rows = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "<p style='color:orange'>⚠️ Aucun coursier trouvé</p>";
    } else {
        echo "<table border='1' cellspacing='0' cellpadding='6'><tr>";
        echo "<th>#</th><th>{$idColumn}</th>";
        echo $nameColumn ? "<th>{$nameColumn}</th>" : '';
        echo $phoneColumn ? "<th>{$phoneColumn}</th>" : '';
        echo "<th>Statut</th></tr>";
        foreach ($rows as $index => $row) {
            $idVal = htmlspecialchars((string)($row[$idColumn] ?? 'N/A'));
            $nameVal = $nameColumn ? htmlspecialchars((string)($row[$nameColumn] ?? '')) : '';
            $phoneVal = $phoneColumn ? htmlspecialchars((string)($row[$phoneColumn] ?? '')) : '';
            $statutVal = htmlspecialchars((string)($row['statut'] ?? ($row['status'] ?? '')));
            echo '<tr>';
            echo '<td>' . ($index + 1) . '</td>';
            echo '<td><strong>' . $idVal . '</strong></td>';
            if ($nameColumn) {
                echo '<td>' . $nameVal . '</td>';
            }
            if ($phoneColumn) {
                echo '<td>' . $phoneVal . '</td>';
            }
            echo '<td>' . $statutVal . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p><small>Test généré le " . date('Y-m-d H:i:s') . "</small></p>";
echo "</body></html>";
