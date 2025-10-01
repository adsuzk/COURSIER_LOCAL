@echo off
:loop
cls
echo ======================================
echo    MONITOR COMMANDES COURSIER #5
echo ======================================
echo.
C:\xampp\php\php.exe -r "require 'config.php'; $db = getDbConnection(); $stmt = $db->query('SELECT id, code_commande, statut, mode_paiement, heure_acceptation, heure_debut, heure_retrait, heure_livraison, cash_recupere FROM commandes WHERE coursier_id=5 ORDER BY id DESC LIMIT 3'); echo str_pad('ID', 4) . ' | ' . str_pad('Code', 18) . ' | ' . str_pad('Statut', 10) . ' | ' . str_pad('Paiement', 10) . ' | Cash | Debut | Recup | Livre' . PHP_EOL; echo str_repeat('-', 95) . PHP_EOL; while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo str_pad($row['id'], 4) . ' | ' . str_pad($row['code_commande'], 18) . ' | ' . str_pad($row['statut'], 10) . ' | ' . str_pad($row['mode_paiement'] ?: '-', 10) . ' | ' . ($row['cash_recupere'] ? 'OUI' : 'NON') . '  | ' . ($row['heure_debut'] ? '✓' : '✗') . '     | ' . ($row['heure_retrait'] ? '✓' : '✗') . '     | ' . ($row['heure_livraison'] ? '✓' : '✗') . PHP_EOL; }"
echo.
echo Actualisation toutes les 2 secondes...
echo Appuyez sur Ctrl+C pour arreter
timeout /t 2 /nobreak >nul
goto loop
