<?php
// backfill_active_coords.php - Remplit des coordonnées GPS par défaut pour les commandes actives d'un coursier
// Usage (CLI): php backfill_active_coords.php <COURSER_ID>
// Par défaut, COURSER_ID=5

require_once __DIR__ . '/config.php';

function getPdoCompat() {
    if (function_exists('getPDO')) return getPDO();
    if (function_exists('getDBConnection')) return getDBConnection();
    // Fallback très basique si helpers indisponibles
    return new PDO('mysql:host=127.0.0.1;dbname=coursier_local;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

try {
    $pdo = getPdoCompat();

    $coursierId = 5;
    if (PHP_SAPI === 'cli' && isset($argv[1]) && is_numeric($argv[1])) {
        $coursierId = (int)$argv[1];
    } elseif (isset($_GET['coursier_id'])) {
        $coursierId = (int)$_GET['coursier_id'];
    }

    // Coordonnées de test (Abidjan)
    $latPickup = 5.362;   // Cocody Angré
    $lngPickup = -3.987;
    $addrPickup = "Cocody Angré, près du Carrefour";
    $latDrop = 5.320;     // Plateau
    $lngDrop = -4.012;
    $addrDrop = "Plateau, Rue du Commerce";

    $activeStatuses = [
        'assignee','nouvelle','acceptee','en_cours','picked_up','recuperee','en_livraison'
    ];
    $ph = implode(',', array_fill(0, count($activeStatuses), '?'));

    // Backfill pour les commandes sans coordonnées de retrait
        $sqlPickup = "
                UPDATE commandes
                SET latitude_retrait = ?,
                        longitude_retrait = ?,
                        adresse_retrait = ?
                WHERE coursier_id = ?
                    AND statut IN ($ph)
                    AND (
                        latitude_retrait IS NULL OR longitude_retrait IS NULL OR
                        latitude_retrait = 0 OR longitude_retrait = 0
                    )
        ";
        $stmt = $pdo->prepare($sqlPickup);
        $params = array_merge([$latPickup, $lngPickup, $addrPickup, $coursierId], $activeStatuses);
        $stmt->execute($params);
    $updatedPickup = $stmt->rowCount();

    // Backfill pour les commandes sans coordonnées de livraison
        $sqlDrop = "
                UPDATE commandes
                SET latitude_livraison = ?,
                        longitude_livraison = ?,
                        adresse_livraison = ?
                WHERE coursier_id = ?
                    AND statut IN ($ph)
                    AND (
                        latitude_livraison IS NULL OR longitude_livraison IS NULL OR
                        latitude_livraison = 0 OR longitude_livraison = 0
                    )
        ";
        $stmt2 = $pdo->prepare($sqlDrop);
        $params2 = array_merge([$latDrop, $lngDrop, $addrDrop, $coursierId], $activeStatuses);
        $stmt2->execute($params2);
    $updatedDrop = $stmt2->rowCount();

    echo "✅ Backfill effectué pour le coursier #{$coursierId}\n";
    echo "   - Retrait MAJ: {$updatedPickup}\n";
    echo "   - Livraison MAJ: {$updatedDrop}\n";

    // Afficher un aperçu des commandes actives avec coordonnées
    $sqlList = "
        SELECT id, code_commande, statut,
               COALESCE(latitude_depart, latitude_retrait) AS lat_pick,
               COALESCE(longitude_depart, longitude_retrait) AS lng_pick,
               COALESCE(latitude_arrivee, latitude_livraison) AS lat_drop,
               COALESCE(longitude_arrivee, longitude_livraison) AS lng_drop
        FROM commandes
        WHERE coursier_id = ? AND statut IN ($ph)
        ORDER BY created_at DESC
        LIMIT 10
    ";
    $stmt3 = $pdo->prepare($sqlList);
    $stmt3->execute(array_merge([$coursierId], $activeStatuses));
    $rows = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    echo "\n📍 Commandes actives (aperçu coords):\n";
    foreach ($rows as $r) {
        $p1 = number_format((float)$r['lat_pick'], 6);
        $p2 = number_format((float)$r['lng_pick'], 6);
        $d1 = number_format((float)$r['lat_drop'], 6);
        $d2 = number_format((float)$r['lng_drop'], 6);
        echo "  - #{$r['id']} ({$r['statut']}): Pickup=($p1,$p2) -> Drop=($d1,$d2)\n";
    }
} catch (Throwable $e) {
    echo "❌ Erreur: ".$e->getMessage()."\n";
}

?>
