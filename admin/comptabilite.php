<?php
/**
 * Module de Comptabilité Complète - Suzosky
 * Analyse financière détaillée de toute l'activité
 */

if (!defined('ADMIN_CONTEXT')) {
    die('Accès interdit');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // PhpSpreadsheet pour Excel

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$pdo = getPDO();

// ==================== GESTION DES EXPORTS ====================
if (isset($_GET['export'])) {
    $exportType = $_GET['export']; // 'excel' ou 'pdf'
    $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
    $dateFin = $_GET['date_fin'] ?? date('Y-m-d');
    
    // Récupérer les données de comptabilité
    $comptaData = getComptabiliteData($pdo, $dateDebut, $dateFin);
    
    if ($exportType === 'excel') {
        exportComptabiliteExcel($comptaData, $dateDebut, $dateFin);
    } elseif ($exportType === 'pdf') {
        exportComptabilitePDF($comptaData, $dateDebut, $dateFin);
    }
    exit;
}

// ==================== FONCTIONS DE CALCUL ====================

/**
 * Récupère toutes les données de comptabilité pour une période donnée
 */
function getComptabiliteData($pdo, $dateDebut, $dateFin) {
    $data = [];
    
    // 1. CHIFFRE D'AFFAIRES GLOBAL (somme de tous les prix des commandes livrées)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as nb_livraisons,
            SUM(prix_total) as ca_total,
            AVG(prix_total) as prix_moyen
        FROM commandes 
        WHERE statut = 'livree' 
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $caData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $data['ca_total'] = (float)($caData['ca_total'] ?? 0);
    $data['nb_livraisons'] = (int)($caData['nb_livraisons'] ?? 0);
    $data['prix_moyen'] = (float)($caData['prix_moyen'] ?? 0);
    
    // 2. DÉTAIL DES COMMANDES AVEC TAUX HISTORIQUES
    // On récupère chaque commande avec les taux applicables au moment de la livraison
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.prix_total,
            c.created_at,
            c.coursier_id,
            c.adresse_retrait,
            c.adresse_livraison,
            
            -- Récupérer les taux applicables au moment de la commande
            (SELECT taux_commission FROM config_tarification 
             WHERE date_application <= c.created_at 
             ORDER BY date_application DESC LIMIT 1) as taux_commission_suzosky,
            
            (SELECT frais_plateforme FROM config_tarification 
             WHERE date_application <= c.created_at 
             ORDER BY date_application DESC LIMIT 1) as frais_plateforme,
            
            (SELECT frais_publicitaires FROM config_tarification 
             WHERE date_application <= c.created_at 
             ORDER BY date_application DESC LIMIT 1) as frais_publicitaires
            
        FROM commandes c
        WHERE c.statut = 'livree'
        AND c.created_at BETWEEN ? AND ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data['commandes'] = $commandes;
    
    // 3. CALCULS AGRÉGÉS
    $totalCommissionSuzosky = 0;
    $totalFraisPlateforme = 0;
    $totalFraisPublicitaires = 0;
    $totalRevenusCoursiers = 0;
    
    foreach ($commandes as $cmd) {
        $prix = (float)$cmd['prix_total'];
        $tauxCommission = (float)($cmd['taux_commission_suzosky'] ?? 15) / 100;
        $tauxPlateforme = (float)($cmd['frais_plateforme'] ?? 5) / 100;
        $tauxPublicite = (float)($cmd['frais_publicitaires'] ?? 3) / 100;
        
        $commissionSuzosky = $prix * $tauxCommission;
        $fraisPlateforme = $prix * $tauxPlateforme;
        $fraisPublicitaires = $prix * $tauxPublicite;
        $revenuCoursier = $prix - $commissionSuzosky;
        
        $totalCommissionSuzosky += $commissionSuzosky;
        $totalFraisPlateforme += $fraisPlateforme;
        $totalFraisPublicitaires += $fraisPublicitaires;
        $totalRevenusCoursiers += $revenuCoursier;
    }
    
    $data['total_commission_suzosky'] = $totalCommissionSuzosky;
    $data['total_frais_plateforme'] = $totalFraisPlateforme;
    $data['total_frais_publicitaires'] = $totalFraisPublicitaires;
    $data['total_revenus_coursiers'] = $totalRevenusCoursiers;
    
    // 4. REVENUS SUZOSKY (Commission - Frais plateforme - Frais publicitaires)
    $data['revenus_nets_suzosky'] = $totalCommissionSuzosky - $totalFraisPlateforme - $totalFraisPublicitaires;
    
    // 5. STATISTIQUES PAR COURSIER
    $stmt = $pdo->prepare("
        SELECT 
            c.coursier_id,
            a.nom as coursier_nom,
            a.prenoms as coursier_prenom,
            COUNT(*) as nb_livraisons,
            SUM(c.prix_total) as ca_coursier,
            AVG(c.prix_total) as prix_moyen
        FROM commandes c
        JOIN agents_suzosky a ON a.id = c.coursier_id
        WHERE c.statut = 'livree'
        AND c.created_at BETWEEN ? AND ?
        GROUP BY c.coursier_id
        ORDER BY ca_coursier DESC
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $data['stats_coursiers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. ÉVOLUTION TEMPORELLE (par jour)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as nb_livraisons,
            SUM(prix_total) as ca_journalier
        FROM commandes
        WHERE statut = 'livree'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $data['evolution_journaliere'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. TAUX ACTUELS ET HISTORIQUE
    $stmt = $pdo->query("
        SELECT * FROM config_tarification 
        ORDER BY date_application DESC
    ");
    $data['historique_taux'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data['date_debut'] = $dateDebut;
    $data['date_fin'] = $dateFin;
    
    return $data;
}

/**
 * Export Excel de la comptabilité
 */
function exportComptabiliteExcel($data, $dateDebut, $dateFin) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Comptabilité');
    
    // Styles
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFB800']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    
    $titleStyle = [
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1a1a1a']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    
    // En-tête
    $sheet->setCellValue('A1', 'COMPTABILITÉ SUZOSKY');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->applyFromArray($titleStyle);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    $sheet->setCellValue('A2', "Période : du $dateDebut au $dateFin");
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row = 4;
    
    // SECTION 1: SYNTHÈSE GLOBALE
    $sheet->setCellValue("A$row", 'SYNTHÈSE GLOBALE');
    $sheet->mergeCells("A$row:B$row");
    $sheet->getStyle("A$row:B$row")->applyFromArray($headerStyle);
    $row++;
    
    $synthese = [
        ['Chiffre d\'affaires total', number_format($data['ca_total'], 0, ',', ' ') . ' FCFA'],
        ['Nombre de livraisons', $data['nb_livraisons']],
        ['Prix moyen par livraison', number_format($data['prix_moyen'], 0, ',', ' ') . ' FCFA'],
        ['Revenus coursiers (après commission)', number_format($data['total_revenus_coursiers'], 0, ',', ' ') . ' FCFA'],
        ['Commission Suzosky', number_format($data['total_commission_suzosky'], 0, ',', ' ') . ' FCFA'],
        ['Frais plateforme', number_format($data['total_frais_plateforme'], 0, ',', ' ') . ' FCFA'],
        ['Frais publicitaires', number_format($data['total_frais_publicitaires'], 0, ',', ' ') . ' FCFA'],
        ['REVENUS NETS SUZOSKY', number_format($data['revenus_nets_suzosky'], 0, ',', ' ') . ' FCFA'],
    ];
    
    foreach ($synthese as $item) {
        $sheet->setCellValue("A$row", $item[0]);
        $sheet->setCellValue("B$row", $item[1]);
        if ($item[0] === 'REVENUS NETS SUZOSKY') {
            $sheet->getStyle("A$row:B$row")->getFont()->setBold(true);
            $sheet->getStyle("B$row")->getFont()->getColor()->setRGB('00AA00');
        }
        $row++;
    }
    
    $row += 2;
    
    // SECTION 2: DÉTAIL PAR COURSIER
    $sheet->setCellValue("A$row", 'DÉTAIL PAR COURSIER');
    $sheet->mergeCells("A$row:E$row");
    $sheet->getStyle("A$row:E$row")->applyFromArray($headerStyle);
    $row++;
    
    $sheet->setCellValue("A$row", 'Coursier');
    $sheet->setCellValue("B$row", 'Nb livraisons');
    $sheet->setCellValue("C$row", 'CA généré');
    $sheet->setCellValue("D$row", 'Prix moyen');
    $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
    $row++;
    
    foreach ($data['stats_coursiers'] as $stat) {
        $sheet->setCellValue("A$row", $stat['coursier_nom'] . ' ' . $stat['coursier_prenom']);
        $sheet->setCellValue("B$row", $stat['nb_livraisons']);
        $sheet->setCellValue("C$row", number_format($stat['ca_coursier'], 0, ',', ' ') . ' FCFA');
        $sheet->setCellValue("D$row", number_format($stat['prix_moyen'], 0, ',', ' ') . ' FCFA');
        $row++;
    }
    
    $row += 2;
    
    // SECTION 3: HISTORIQUE DES TAUX
    $sheet->setCellValue("A$row", 'HISTORIQUE DES TAUX DE TARIFICATION');
    $sheet->mergeCells("A$row:D$row");
    $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
    $row++;
    
    $sheet->setCellValue("A$row", 'Date application');
    $sheet->setCellValue("B$row", 'Commission Suzosky');
    $sheet->setCellValue("C$row", 'Frais plateforme');
    $sheet->setCellValue("D$row", 'Frais publicitaires');
    $sheet->getStyle("A$row:D$row")->applyFromArray($headerStyle);
    $row++;
    
    foreach ($data['historique_taux'] as $taux) {
        $sheet->setCellValue("A$row", $taux['date_application']);
        $sheet->setCellValue("B$row", $taux['taux_commission'] . '%');
        $sheet->setCellValue("C$row", $taux['frais_plateforme'] . '%');
        $sheet->setCellValue("D$row", $taux['frais_publicitaires'] . '%');
        $row++;
    }
    
    // Auto-dimensionner les colonnes
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Téléchargement
    $filename = "comptabilite_suzosky_{$dateDebut}_au_{$dateFin}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

/**
 * Export PDF de la comptabilité
 */
function exportComptabilitePDF($data, $dateDebut, $dateFin) {
    require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    
    // Métadonnées
    $pdf->SetCreator('Suzosky Admin');
    $pdf->SetAuthor('Suzosky');
    $pdf->SetTitle('Comptabilité Suzosky');
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    $pdf->AddPage();
    
    // Couleurs Suzosky
    $goldColor = [255, 184, 0];
    $darkColor = [26, 26, 26];
    
    // En-tête
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor($goldColor[0], $goldColor[1], $goldColor[2]);
    $pdf->Cell(0, 10, 'COMPTABILITÉ SUZOSKY', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
    $pdf->Cell(0, 8, "Période : du $dateDebut au $dateFin", 0, 1, 'C');
    $pdf->Ln(5);
    
    // SYNTHÈSE GLOBALE
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetFillColor($goldColor[0], $goldColor[1], $goldColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, 'SYNTHÈSE GLOBALE', 0, 1, 'L', true);
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
    
    $synthese = [
        ['Chiffre d\'affaires total', number_format($data['ca_total'], 0, ',', ' ') . ' FCFA'],
        ['Nombre de livraisons', $data['nb_livraisons']],
        ['Prix moyen par livraison', number_format($data['prix_moyen'], 0, ',', ' ') . ' FCFA'],
        ['Revenus coursiers (après commission)', number_format($data['total_revenus_coursiers'], 0, ',', ' ') . ' FCFA'],
        ['Commission Suzosky', number_format($data['total_commission_suzosky'], 0, ',', ' ') . ' FCFA'],
        ['Frais plateforme', number_format($data['total_frais_plateforme'], 0, ',', ' ') . ' FCFA'],
        ['Frais publicitaires', number_format($data['total_frais_publicitaires'], 0, ',', ' ') . ' FCFA'],
    ];
    
    foreach ($synthese as $item) {
        $pdf->Cell(100, 6, $item[0], 1, 0, 'L');
        $pdf->Cell(80, 6, $item[1], 1, 1, 'R');
    }
    
    // REVENUS NETS SUZOSKY (en vert)
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 170, 0);
    $pdf->Cell(100, 7, 'REVENUS NETS SUZOSKY', 1, 0, 'L', true);
    $pdf->Cell(80, 7, number_format($data['revenus_nets_suzosky'], 0, ',', ' ') . ' FCFA', 1, 1, 'R');
    
    $pdf->Ln(8);
    
    // DÉTAIL PAR COURSIER
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor($goldColor[0], $goldColor[1], $goldColor[2]);
    $pdf->Cell(0, 8, 'DÉTAIL PAR COURSIER', 0, 1, 'L', true);
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
    $pdf->Cell(60, 6, 'Coursier', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'Nb livraisons', 1, 0, 'C', true);
    $pdf->Cell(50, 6, 'CA généré', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Prix moyen', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($data['stats_coursiers'] as $stat) {
        $pdf->Cell(60, 5, $stat['coursier_nom'] . ' ' . $stat['coursier_prenom'], 1, 0, 'L');
        $pdf->Cell(30, 5, $stat['nb_livraisons'], 1, 0, 'C');
        $pdf->Cell(50, 5, number_format($stat['ca_coursier'], 0, ',', ' ') . ' F', 1, 0, 'R');
        $pdf->Cell(40, 5, number_format($stat['prix_moyen'], 0, ',', ' ') . ' F', 1, 1, 'R');
    }
    
    // Téléchargement
    $filename = "comptabilite_suzosky_{$dateDebut}_au_{$dateFin}.pdf";
    $pdf->Output($filename, 'D');
}

// ==================== AFFICHAGE HTML ====================

// Récupérer les filtres
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois
$dateFin = $_GET['date_fin'] ?? date('Y-m-d'); // Aujourd'hui

// Charger les données
$comptaData = getComptabiliteData($pdo, $dateDebut, $dateFin);

// Calculer quelques métriques supplémentaires
$tauxCommissionMoyen = $comptaData['ca_total'] > 0 ? ($comptaData['total_commission_suzosky'] / $comptaData['ca_total']) * 100 : 0;
$tauxPlateforme = $comptaData['ca_total'] > 0 ? ($comptaData['total_frais_plateforme'] / $comptaData['ca_total']) * 100 : 0;
$tauxPublicitaires = $comptaData['ca_total'] > 0 ? ($comptaData['total_frais_publicitaires'] / $comptaData['ca_total']) * 100 : 0;
$margeNetteSuzosky = $comptaData['ca_total'] > 0 ? ($comptaData['revenus_nets_suzosky'] / $comptaData['ca_total']) * 100 : 0;

?>

<style>
/* === COMPTABILITÉ SUZOSKY - STYLE COMPLET DARK === */

.filter-bar {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--suzosky-gold);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group input[type="date"] {
    width: 100%;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid var(--glass-border);
    border-radius: 8px;
    font-size: 1rem;
    color: white;
    transition: all 0.3s;
}

.filter-group input[type="date"]:focus {
    outline: none;
    border-color: var(--suzosky-gold);
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 0 20px rgba(212, 168, 83, 0.3);
}

.filter-actions {
    display: flex;
    gap: 1rem;
}

.btn-filter {
    background: var(--gradient-gold);
    color: var(--suzosky-dark);
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1rem;
    height: 45px;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(212, 168, 83, 0.5);
}

.btn-export {
    background: rgba(255, 255, 255, 0.1);
    color: var(--suzosky-gold);
    border: 2px solid var(--suzosky-gold);
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    height: 45px;
    backdrop-filter: blur(10px);
}

.btn-export:hover {
    background: var(--suzosky-gold);
    color: var(--suzosky-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(212, 168, 83, 0.5);
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.metric-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
    border-left: 4px solid var(--suzosky-gold);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(212, 168, 83, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(212, 168, 83, 0.25);
    border-left-width: 6px;
}

.metric-card.highlight {
    background: linear-gradient(135deg, rgba(212, 168, 83, 0.2), rgba(255, 255, 255, 0.08));
    border-left-color: #F4E4B8;
    box-shadow: 0 8px 32px rgba(212, 168, 83, 0.4);
}

.metric-icon {
    width: 50px;
    height: 50px;
    background: var(--gradient-gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: var(--suzosky-dark);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
}

.metric-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.metric-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--suzosky-gold);
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 10px rgba(212, 168, 83, 0.3);
}

.metric-subtitle {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
}

.metric-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--suzosky-gold);
    color: var(--suzosky-dark);
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-top: 0.5rem;
}

.details-section {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
    margin-bottom: 2rem;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--suzosky-gold);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 3px solid var(--suzosky-gold);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-title::before {
    content: '';
    width: 4px;
    height: 24px;
    background: var(--suzosky-gold);
    border-radius: 2px;
    box-shadow: 0 0 10px rgba(212, 168, 83, 0.5);
}

.breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.breakdown-item {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border-left: 3px solid var(--suzosky-gold);
    transition: all 0.3s;
}

.breakdown-item:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.breakdown-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.breakdown-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: white;
}

.breakdown-percent {
    font-size: 0.85rem;
    color: var(--suzosky-green);
    font-weight: 600;
    margin-top: 0.25rem;
}

.data-table-responsive {
    overflow-x: auto;
    margin-top: 1rem;
}

.compta-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.compta-table thead {
    background: var(--gradient-gold);
    color: var(--suzosky-dark);
}

.compta-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.compta-table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: background 0.2s;
}

.compta-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.05);
}

.compta-table td {
    padding: 1rem;
    color: rgba(255, 255, 255, 0.9);
}

.compta-table tr:nth-child(even) {
    background: rgba(255, 255, 255, 0.02);
}

.no-data {
    text-align: center;
    padding: 3rem;
    color: rgba(255, 255, 255, 0.5);
    font-style: italic;
}

.chart-container {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
    margin-bottom: 2rem;
}

.revenue-breakdown {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.revenue-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.revenue-bar-container {
    flex: 1;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    height: 40px;
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.revenue-bar {
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 1rem;
    color: white;
    font-weight: 700;
    transition: width 0.5s ease;
    font-size: 0.9rem;
    text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.revenue-bar.ca-total { background: linear-gradient(90deg, #27AE60, #2ECC71); }
.revenue-bar.commission { background: var(--gradient-gold); color: var(--suzosky-dark); }
.revenue-bar.frais { background: linear-gradient(90deg, #E94560, #F06292); }
.revenue-bar.revenus-nets { background: linear-gradient(90deg, #0F3460, #16213E); }
.revenue-bar.coursiers { background: linear-gradient(90deg, #9C27B0, #BA68C8); }

.revenue-label {
    min-width: 200px;
    font-weight: 600;
    color: white;
}

.alert-info {
    background: rgba(33, 150, 243, 0.1);
    border: 1px solid rgba(33, 150, 243, 0.3);
    border-left: 4px solid #2196F3;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    color: #64B5F6;
}

.alert-warning {
    background: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.3);
    border-left: 4px solid #FF9800;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    color: #FFB74D;
}

@media (max-width: 768px) {
    .compta-container {
        padding: 1rem;
    }
    
    .metrics-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-bar {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-filter, .btn-export {
        width: 100%;
        justify-content: center;
    }
    
    .revenue-label {
        min-width: 150px;
        font-size: 0.85rem;
    }
}

</style>

<div class="compta-container">
    <!-- En-tête -->
    <div class="compta-header">
        <h1>📊 Comptabilité Complète Suzosky</h1>
        <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;">
            Analyse financière détaillée de toute l'activité de livraison
        </p>
    </div>

    <!-- Barre de filtres -->
    <form method="GET" action="admin.php" class="filter-bar">
        <input type="hidden" name="section" value="finances">
        <input type="hidden" name="tab" value="comptabilite">
        
        <div class="filter-group">
            <label for="date_debut">📅 Date de début</label>
            <input type="date" id="date_debut" name="date_debut" value="<?php echo htmlspecialchars($dateDebut); ?>" required>
        </div>
        
        <div class="filter-group">
            <label for="date_fin">📅 Date de fin</label>
            <input type="date" id="date_fin" name="date_fin" value="<?php echo htmlspecialchars($dateFin); ?>" required>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn-filter">🔍 Filtrer</button>
            <a href="admin.php?section=finances&tab=comptabilite&export=excel&date_debut=<?php echo urlencode($dateDebut); ?>&date_fin=<?php echo urlencode($dateFin); ?>" class="btn-export">
                📥 Excel
            </a>
            <a href="admin.php?section=finances&tab=comptabilite&export=pdf&date_debut=<?php echo urlencode($dateDebut); ?>&date_fin=<?php echo urlencode($dateFin); ?>" class="btn-export">
                📄 PDF
            </a>
        </div>
    </form>

    <?php if ($comptaData['nb_livraisons'] === 0): ?>
        <div class="alert-warning">
            ⚠️ Aucune livraison trouvée pour la période sélectionnée (<?php echo $dateDebut; ?> au <?php echo $dateFin; ?>).
        </div>
    <?php else: ?>

    <!-- Métriques principales -->
    <div class="metrics-grid">
        <!-- Chiffre d'affaires global -->
        <div class="metric-card highlight">
            <div class="metric-icon">💰</div>
            <div class="metric-label">Chiffre d'affaires global</div>
            <div class="metric-value"><?php echo number_format($comptaData['ca_total'], 0, ',', ' '); ?> FCFA</div>
            <div class="metric-subtitle"><?php echo $comptaData['nb_livraisons']; ?> livraisons effectuées</div>
            <div class="metric-badge"><?php echo number_format($comptaData['prix_moyen'], 0, ',', ' '); ?> FCFA / livraison</div>
        </div>

        <!-- Revenus coursiers -->
        <div class="metric-card">
            <div class="metric-icon">🚴</div>
            <div class="metric-label">Revenus coursiers</div>
            <div class="metric-value"><?php echo number_format($comptaData['total_revenus_coursiers'], 0, ',', ' '); ?> FCFA</div>
            <div class="metric-subtitle">Après déduction commission</div>
            <div class="metric-badge"><?php echo number_format(100 - $tauxCommissionMoyen, 1); ?>% du CA</div>
        </div>

        <!-- Commission Suzosky -->
        <div class="metric-card">
            <div class="metric-icon">🏢</div>
            <div class="metric-label">Commission Suzosky</div>
            <div class="metric-value"><?php echo number_format($comptaData['total_commission_suzosky'], 0, ',', ' '); ?> FCFA</div>
            <div class="metric-subtitle">Prélevée sur chaque livraison</div>
            <div class="metric-badge"><?php echo number_format($tauxCommissionMoyen, 1); ?>% du CA</div>
        </div>

        <!-- Frais plateforme -->
        <div class="metric-card">
            <div class="metric-icon">⚙️</div>
            <div class="metric-label">Frais plateforme</div>
            <div class="metric-value"><?php echo number_format($comptaData['total_frais_plateforme'], 0, ',', ' '); ?> FCFA</div>
            <div class="metric-subtitle">Coûts techniques & infrastructure</div>
            <div class="metric-badge"><?php echo number_format($tauxPlateforme, 1); ?>% du CA</div>
        </div>

        <!-- Frais publicitaires -->
        <div class="metric-card">
            <div class="metric-icon">📢</div>
            <div class="metric-label">Frais publicitaires</div>
            <div class="metric-value"><?php echo number_format($comptaData['total_frais_publicitaires'], 0, ',', ' '); ?> FCFA</div>
            <div class="metric-subtitle">Marketing & acquisition</div>
            <div class="metric-badge"><?php echo number_format($tauxPublicitaires, 1); ?>% du CA</div>
        </div>

        <!-- REVENUS NETS SUZOSKY -->
        <div class="metric-card highlight">
            <div class="metric-icon">✨</div>
            <div class="metric-label">Revenus nets Suzosky</div>
            <div class="metric-value" style="color: #00AA00;"><?php echo number_format($comptaData['revenus_nets_suzosky'], 0, ',', ' '); ?> FCFA</div>
            <div class="metric-subtitle">Commission - Frais plateforme - Frais pub</div>
            <div class="metric-badge" style="background: #00AA00; color: white;"><?php echo number_format($margeNetteSuzosky, 1); ?>% de marge nette</div>
        </div>
    </div>

    <!-- Graphique de répartition des revenus -->
    <div class="chart-container">
        <h2 class="section-title">💹 Répartition des revenus</h2>
        <div class="revenue-breakdown">
            <?php
            $maxValue = $comptaData['ca_total'];
            $items = [
                ['label' => 'Chiffre d\'affaires total', 'value' => $comptaData['ca_total'], 'class' => 'ca-total'],
                ['label' => 'Revenus coursiers', 'value' => $comptaData['total_revenus_coursiers'], 'class' => 'coursiers'],
                ['label' => 'Commission Suzosky', 'value' => $comptaData['total_commission_suzosky'], 'class' => 'commission'],
                ['label' => 'Frais plateforme', 'value' => $comptaData['total_frais_plateforme'], 'class' => 'frais'],
                ['label' => 'Frais publicitaires', 'value' => $comptaData['total_frais_publicitaires'], 'class' => 'frais'],
                ['label' => 'Revenus nets Suzosky', 'value' => $comptaData['revenus_nets_suzosky'], 'class' => 'revenus-nets'],
            ];
            
            foreach ($items as $item):
                $percent = $maxValue > 0 ? ($item['value'] / $maxValue) * 100 : 0;
            ?>
            <div class="revenue-item">
                <div class="revenue-label"><?php echo $item['label']; ?></div>
                <div class="revenue-bar-container">
                    <div class="revenue-bar <?php echo $item['class']; ?>" style="width: <?php echo $percent; ?>%;">
                        <?php echo number_format($item['value'], 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Détail des charges Suzosky -->
    <div class="details-section">
        <h2 class="section-title">💼 Détail des revenus et charges Suzosky</h2>
        
        <div class="alert-info">
            <strong>ℹ️ Calcul des revenus nets Suzosky :</strong><br>
            Revenus nets = Commission Suzosky - Frais plateforme - Frais publicitaires<br>
            = <?php echo number_format($comptaData['total_commission_suzosky'], 0, ',', ' '); ?> - 
            <?php echo number_format($comptaData['total_frais_plateforme'], 0, ',', ' '); ?> - 
            <?php echo number_format($comptaData['total_frais_publicitaires'], 0, ',', ' '); ?> 
            = <strong style="color: #00AA00;"><?php echo number_format($comptaData['revenus_nets_suzosky'], 0, ',', ' '); ?> FCFA</strong>
        </div>
        
        <div class="breakdown-grid">
            <div class="breakdown-item">
                <div class="breakdown-label">💵 Commission totale perçue</div>
                <div class="breakdown-value"><?php echo number_format($comptaData['total_commission_suzosky'], 0, ',', ' '); ?> FCFA</div>
                <div class="breakdown-percent"><?php echo number_format($tauxCommissionMoyen, 2); ?>% du CA</div>
            </div>
            
            <div class="breakdown-item" style="border-left-color: #FF9800;">
                <div class="breakdown-label">⚙️ Frais plateforme</div>
                <div class="breakdown-value" style="color: #FF5722;">-<?php echo number_format($comptaData['total_frais_plateforme'], 0, ',', ' '); ?> FCFA</div>
                <div class="breakdown-percent" style="color: #FF5722;"><?php echo number_format($tauxPlateforme, 2); ?>% du CA</div>
            </div>
            
            <div class="breakdown-item" style="border-left-color: #FF9800;">
                <div class="breakdown-label">📢 Frais publicitaires</div>
                <div class="breakdown-value" style="color: #FF5722;">-<?php echo number_format($comptaData['total_frais_publicitaires'], 0, ',', ' '); ?> FCFA</div>
                <div class="breakdown-percent" style="color: #FF5722;"><?php echo number_format($tauxPublicitaires, 2); ?>% du CA</div>
            </div>
            
            <div class="breakdown-item" style="border-left-color: #00AA00; background: #e8f5e9;">
                <div class="breakdown-label">✨ Revenus nets Suzosky</div>
                <div class="breakdown-value" style="color: #00AA00;"><?php echo number_format($comptaData['revenus_nets_suzosky'], 0, ',', ' '); ?> FCFA</div>
                <div class="breakdown-percent" style="color: #00AA00;"><?php echo number_format($margeNetteSuzosky, 2); ?>% de marge nette</div>
            </div>
        </div>
    </div>

    <!-- Statistiques par coursier -->
    <div class="details-section">
        <h2 class="section-title">🚴 Performance par coursier</h2>
        
        <?php if (empty($comptaData['stats_coursiers'])): ?>
            <div class="no-data">Aucune donnée disponible</div>
        <?php else: ?>
        <div class="data-table-responsive">
            <table class="compta-table">
                <thead>
                    <tr>
                        <th>Coursier</th>
                        <th>Nb livraisons</th>
                        <th>CA généré</th>
                        <th>Prix moyen</th>
                        <th>Part du CA total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comptaData['stats_coursiers'] as $stat): 
                        $partCA = $comptaData['ca_total'] > 0 ? ($stat['ca_coursier'] / $comptaData['ca_total']) * 100 : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($stat['coursier_nom'] . ' ' . $stat['coursier_prenom']); ?></strong></td>
                        <td><?php echo $stat['nb_livraisons']; ?></td>
                        <td><strong><?php echo number_format($stat['ca_coursier'], 0, ',', ' '); ?> FCFA</strong></td>
                        <td><?php echo number_format($stat['prix_moyen'], 0, ',', ' '); ?> FCFA</td>
                        <td><span class="metric-badge"><?php echo number_format($partCA, 1); ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Historique des taux de tarification -->
    <div class="details-section">
        <h2 class="section-title">📈 Historique des taux de tarification</h2>
        
        <div class="alert-info">
            <strong>ℹ️ Important :</strong> Les calculs de cette comptabilité utilisent les taux qui étaient en vigueur au moment de chaque livraison. Ceci garantit une précision comptable parfaite même si les taux ont changé pendant la période sélectionnée.
        </div>
        
        <?php if (empty($comptaData['historique_taux'])): ?>
            <div class="no-data">Aucun historique de taux disponible</div>
        <?php else: ?>
        <div class="data-table-responsive">
            <table class="compta-table">
                <thead>
                    <tr>
                        <th>Date d'application</th>
                        <th>Commission Suzosky</th>
                        <th>Frais plateforme</th>
                        <th>Frais publicitaires</th>
                        <th>Total charges</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $today = date('Y-m-d');
                    foreach ($comptaData['historique_taux'] as $taux): 
                        $dateApp = substr($taux['date_application'], 0, 10);
                        $isActif = ($dateApp <= $today);
                        $totalCharges = (float)$taux['taux_commission'] + (float)$taux['frais_plateforme'] + (float)$taux['frais_publicitaires'];
                    ?>
                    <tr style="<?php echo $isActif && $dateApp === $today ? 'background: #fff9e6;' : ''; ?>">
                        <td><strong><?php echo date('d/m/Y', strtotime($taux['date_application'])); ?></strong></td>
                        <td><?php echo $taux['taux_commission']; ?>%</td>
                        <td><?php echo $taux['frais_plateforme']; ?>%</td>
                        <td><?php echo $taux['frais_publicitaires']; ?>%</td>
                        <td><strong><?php echo number_format($totalCharges, 1); ?>%</strong></td>
                        <td>
                            <?php if ($dateApp === $today): ?>
                                <span style="color: #00AA00; font-weight: 700;">✅ Actif actuellement</span>
                            <?php elseif ($isActif): ?>
                                <span style="color: #2196F3; font-weight: 600;">📅 Appliqué</span>
                            <?php else: ?>
                                <span style="color: #999;">🕐 Futur</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Évolution temporelle -->
    <?php if (!empty($comptaData['evolution_journaliere'])): ?>
    <div class="chart-container">
        <h2 class="section-title">📊 Évolution journalière du CA</h2>
        <div class="data-table-responsive">
            <table class="compta-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Nb livraisons</th>
                        <th>CA journalier</th>
                        <th>Variation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $prevCA = 0;
                    foreach ($comptaData['evolution_journaliere'] as $jour): 
                        $variation = $prevCA > 0 ? (($jour['ca_journalier'] - $prevCA) / $prevCA) * 100 : 0;
                        $variationClass = $variation > 0 ? 'success' : ($variation < 0 ? 'danger' : 'neutral');
                        $variationIcon = $variation > 0 ? '📈' : ($variation < 0 ? '📉' : '➡️');
                    ?>
                    <tr>
                        <td><strong><?php echo date('d/m/Y', strtotime($jour['date'])); ?></strong></td>
                        <td><?php echo $jour['nb_livraisons']; ?></td>
                        <td><strong><?php echo number_format($jour['ca_journalier'], 0, ',', ' '); ?> FCFA</strong></td>
                        <td>
                            <?php if ($prevCA > 0): ?>
                                <span style="color: <?php echo $variationClass === 'success' ? '#00AA00' : ($variationClass === 'danger' ? '#FF5722' : '#999'); ?>;">
                                    <?php echo $variationIcon; ?> <?php echo number_format(abs($variation), 1); ?>%
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                    $prevCA = $jour['ca_journalier'];
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // fin du test nb_livraisons > 0 ?>
</div>

<script>
// Animation des barres de progression
document.addEventListener('DOMContentLoaded', function() {
    const bars = document.querySelectorAll('.revenue-bar');
    bars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
});
</script>
