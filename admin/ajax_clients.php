<?php
// ajax_clients.php - renvoie le HTML des cartes clients particuliers
// Charger la configuration pour la base de données
require_once __DIR__ . '/../config.php';
$pdo = getPDO();

try {
    // Trier par ID si date_creation manquante
    $privateClients = $pdo->query("SELECT * FROM clients_particuliers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $privateClients = [];
}

ob_start();
foreach ($privateClients as $client) {
    // Initiales
    $initials = '';
    if (!empty($client['nom'])) $initials .= strtoupper($client['nom'][0]);
    if (!empty($client['prenoms'])) $initials .= strtoupper($client['prenoms'][0]);
    if (empty($initials)) $initials = 'C';
    // Nom complet
    $fullName = trim(($client['nom'] ?? '') . ' ' . ($client['prenoms'] ?? ''));
    if (empty($fullName)) $fullName = 'Client #' . ($client['id'] ?? '');
    // Statistiques non disponibles dans cette vue AJAX
    $ordersCount = '-';
    $lastOrder = '-';
    // Ville
    $ville = htmlspecialchars($client['ville'] ?? 'Non renseignée');
    // Date inscription (clients_particuliers.date_creation)
    $inscription = isset($client['date_creation']) ? date('d/m/Y', strtotime($client['date_creation'])) : '-';
    ?>
    <div class="client-card">
        <div class="client-header">
            <div class="client-avatar"><?= $initials ?></div>
            <div class="client-info">
                <h3><?= htmlspecialchars($fullName) ?></h3>
                <p><span class="badge badge-gold">ID: <?= htmlspecialchars($client['id']) ?></span></p>
            </div>
        </div>
        <div class="client-details">
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-phone"></i> Téléphone</span>
                <span class="detail-value"><?= htmlspecialchars($client['telephone']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="detail-value"><?= !empty($client['email']) ? htmlspecialchars($client['email']) : 'Non renseigné' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Ville</span>
                <span class="detail-value"><?= $ville ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-calendar-alt"></i> Inscription</span>
                <span class="detail-value"><?= $inscription ?></span>
            </div>
        </div>
    <div class="client-stats">
            <div class="mini-stat">
                <div class="mini-stat-number"><?= $ordersCount ?></div>
                <div class="mini-stat-label">Commandes</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-number"><?= $lastOrder ?></div>
                <div class="mini-stat-label">Dernière</div>
            </div>
        </div>
        <!-- Actions Admin -->
        <div class="client-actions" style="margin-top:1rem;text-align:right;">
            <a href="?section=clients&action=delete_client&id=<?= htmlspecialchars($client['id']) ?>" class="btn-secondary" onclick="return confirm('Supprimer ce client ?');">
                <i class="fas fa-trash"></i> Supprimer
            </a>
        </div>
    </div>
    <?php
}
$html = ob_get_clean();
echo $html;
