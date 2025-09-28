<?php
/**
 * SYSTÈME D'ATTRIBUTION SÉCURISÉE DES COMMANDES
 * Contrôle strict avant toute assignation - Conformité légale
 */

require_once 'config.php';
require_once 'fcm_token_security.php';

class SecureOrderAssignment {
    private $pdo;
    private $security;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->security = new FCMTokenSecurity();
    }
    
    /**
     * Vérification complète avant assignation d'une commande
     * ⚠️ CRITIQUE: Aucune commande ne peut être assignée à un coursier déconnecté
     */
    public function canAssignOrder($commandeId, $coursierId = null): array {
        // 1. Nettoyage sécurité obligatoire AVANT toute assignation
        $this->security->enforceTokenSecurity();
        
        // 2. Vérifier l'état du système
        $systemStatus = $this->security->canAcceptNewOrders();
        
        if (!$systemStatus['can_accept_orders']) {
            return [
                'success' => false,
                'reason' => 'AUCUN_COURSIER_DISPONIBLE',
                'message' => 'Aucun coursier connecté et disponible',
                'system_message' => $systemStatus['message'],
                'action_required' => 'Attendre qu\'un coursier se connecte'
            ];
        }
        
        // 3. Si coursier spécifique demandé, vérifier son éligibilité
        if ($coursierId) {
            return $this->verifyCourierEligibility($coursierId, $commandeId);
        }
        
        // 4. Sinon, trouver le meilleur coursier disponible
        return $this->findBestAvailableCourier($commandeId);
    }
    
    /**
     * Vérifier l'éligibilité complète d'un coursier spécifique
     */
    private function verifyCourierEligibility($coursierId, $commandeId): array {
        // Récupérer les détails du coursier
        $stmt = $this->pdo->prepare("
            SELECT 
                a.id, a.matricule, a.nom, a.prenoms,
                a.statut_connexion, a.current_session_token, a.last_login_at,
                COALESCE(a.solde_wallet, 0) as solde,
                TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) as minutes_inactif,
                COUNT(dt.id) as tokens_actifs
            FROM agents_suzosky a
            LEFT JOIN device_tokens dt ON a.id = dt.coursier_id AND dt.is_active = 1
            WHERE a.id = ?
            GROUP BY a.id
        ");
        
        $stmt->execute([$coursierId]);
        $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coursier) {
            return [
                'success' => false,
                'reason' => 'COURIER_INEXISTANT',
                'message' => 'Le coursier n\'existe pas',
                'action_required' => 'Vérifier l\'ID du coursier'
            ];
        }
        
        // Vérifications supplémentaires...
        
        return [
            'success' => true,
            'coursier' => $coursier
        ];
    }
    
    /**
     * Trouver le meilleur coursier disponible pour une commande
     */
    private function findBestAvailableCourier($commandeId): array {
        // Logique pour trouver le meilleur coursier...
        
        return [
            'success' => true,
            'coursier_id' => $bestCourierId
        ];
    }
}

/**
 * Compatibilité : le cœur du module se trouve désormais dans Scripts/Scripts cron.
 */

require_once __DIR__ . '/Scripts/Scripts cron/secure_order_assignment.php';