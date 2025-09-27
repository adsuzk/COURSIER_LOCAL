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
                'reason' => 'COURSIER_INEXISTANT',
                'message' => "Coursier ID {$coursierId} introuvable"
            ];
        }
        
        $violations = [];
        
        // Vérifications critiques
        if ($coursier['statut_connexion'] !== 'en_ligne') {
            $violations[] = "Statut: {$coursier['statut_connexion']} (requis: en_ligne)";
        }
        
        if (empty($coursier['current_session_token'])) {
            $violations[] = "Aucun token de session actif";
        }
        
        if ($coursier['minutes_inactif'] > 30) {
            $violations[] = "Inactif depuis {$coursier['minutes_inactif']} minutes (max: 30)";
        }
        
        if ($coursier['solde'] <= 0) {
            $violations[] = "Solde insuffisant: {$coursier['solde']} FCFA (requis: > 0)";
        }
        
        if ($coursier['tokens_actifs'] == 0) {
            $violations[] = "Aucun token FCM actif - Impossible de notifier";
        }
        
        if (!empty($violations)) {
            return [
                'success' => false,
                'reason' => 'COURSIER_NON_ELIGIBLE',
                'coursier' => [
                    'id' => $coursierId,
                    'nom_complet' => $coursier['nom'] . ' ' . $coursier['prenoms'],
                    'matricule' => $coursier['matricule']
                ],
                'violations' => $violations,
                'message' => 'Coursier ne respecte pas les conditions de sécurité'
            ];
        }
        
        return [
            'success' => true,
            'coursier' => [
                'id' => $coursierId,
                'nom_complet' => $coursier['nom'] . ' ' . $coursier['prenoms'],
                'matricule' => $coursier['matricule'],
                'solde' => $coursier['solde'],
                'tokens_actifs' => $coursier['tokens_actifs']
            ],
            'message' => 'Coursier éligible pour assignation'
        ];
    }
    
    /**
     * Trouver automatiquement le meilleur coursier disponible
     */
    private function findBestAvailableCourier($commandeId): array {
        // Récupérer les détails de la commande
        $stmt = $this->pdo->prepare("SELECT prix_total FROM commandes WHERE id = ?");
        $stmt->execute([$commandeId]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande) {
            return [
                'success' => false,
                'reason' => 'COMMANDE_INEXISTANTE',
                'message' => "Commande ID {$commandeId} introuvable"
            ];
        }
        
        $prixCommande = $commande['prix_total'];
        $soldeMinimum = max(100, $prixCommande * 0.05); // 5% du prix ou 100 FCFA minimum
        
        // Chercher les coursiers éligibles
        $stmt = $this->pdo->prepare("
            SELECT 
                a.id, a.matricule, a.nom, a.prenoms,
                COALESCE(a.solde_wallet, 0) as solde,
                COUNT(dt.id) as tokens_actifs,
                TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) as minutes_inactif
            FROM agents_suzosky a
            INNER JOIN device_tokens dt ON a.id = dt.coursier_id AND dt.is_active = 1
            WHERE a.statut_connexion = 'en_ligne'
            AND a.current_session_token IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) <= 30
            AND COALESCE(a.solde_wallet, 0) >= ?
            GROUP BY a.id
            HAVING tokens_actifs > 0
            ORDER BY a.solde_wallet DESC, a.last_login_at DESC
            LIMIT 5
        ");
        
        $stmt->execute([$soldeMinimum]);
        $coursiersEligibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($coursiersEligibles)) {
            return [
                'success' => false,
                'reason' => 'AUCUN_COURSIER_ELIGIBLE',
                'message' => "Aucun coursier ne respecte tous les critères",
                'criteres' => [
                    'statut' => 'en_ligne',
                    'session_active' => 'Obligatoire',
                    'activite_recente' => '< 30 minutes',
                    'solde_minimum' => "{$soldeMinimum} FCFA",
                    'token_fcm' => 'Obligatoire'
                ]
            ];
        }
        
        // Retourner le meilleur coursier (plus gros solde, activité récente)
        $meilleurCoursier = $coursiersEligibles[0];
        
        return [
            'success' => true,
            'coursier' => [
                'id' => $meilleurCoursier['id'],
                'nom_complet' => $meilleurCoursier['nom'] . ' ' . $meilleurCoursier['prenoms'],
                'matricule' => $meilleurCoursier['matricule'],
                'solde' => $meilleurCoursier['solde'],
                'tokens_actifs' => $meilleurCoursier['tokens_actifs']
            ],
            'alternatives' => count($coursiersEligibles) - 1,
            'message' => 'Meilleur coursier sélectionné automatiquement'
        ];
    }
    
    /**
     * Effectuer l'assignation sécurisée avec double-vérification
     */
    public function assignOrderSecurely($commandeId, $coursierId): array {
        echo "🔒 ASSIGNATION SÉCURISÉE COMMANDE #{$commandeId}\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // 1. Vérification finale avant assignation
        $eligibility = $this->canAssignOrder($commandeId, $coursierId);
        
        if (!$eligibility['success']) {
            echo "   ❌ Assignation refusée: {$eligibility['message']}\n";
            return $eligibility;
        }
        
        $coursier = $eligibility['coursier'];
        echo "   ✅ Coursier éligible: {$coursier['nom_complet']} (M:{$coursier['matricule']})\n";
        echo "   💰 Solde: {$coursier['solde']} FCFA\n";
        echo "   📱 Tokens FCM: {$coursier['tokens_actifs']}\n\n";
        
        // 2. Effectuer l'assignation avec transaction
        try {
            $this->pdo->beginTransaction();
            
            // Assigner la commande
            $stmt = $this->pdo->prepare("
                UPDATE commandes 
                SET coursier_id = ?, 
                    statut = 'assignee',
                    date_assignation = NOW(),
                    updated_at = NOW()
                WHERE id = ? 
                AND statut IN ('en_attente', 'nouvelle')
                AND (coursier_id IS NULL OR coursier_id = 0)
            ");
            
            $assignationReussie = $stmt->execute([$coursierId, $commandeId]);
            $lignesAffectees = $stmt->rowCount();
            
            if (!$assignationReussie || $lignesAffectees == 0) {
                throw new Exception("Échec assignation BDD - Commande peut-être déjà assignée");
            }
            
            // 3. Vérification post-assignation (double-check sécurité)
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.coursier_id, c.statut, a.statut_connexion
                FROM commandes c
                INNER JOIN agents_suzosky a ON c.coursier_id = a.id
                WHERE c.id = ?
            ");
            $stmt->execute([$commandeId]);
            $verification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$verification || $verification['statut_connexion'] !== 'en_ligne') {
                throw new Exception("Validation sécurité échouée - Coursier plus connecté");
            }
            
            $this->pdo->commit();
            
            echo "   ✅ Assignation réussie et sécurisée\n";
            echo "   📋 Commande #{$commandeId} → Coursier #{$coursierId}\n";
            
            return [
                'success' => true,
                'commande_id' => $commandeId,
                'coursier' => $coursier,
                'message' => 'Assignation sécurisée complétée avec succès',
                'next_step' => 'Envoi notification FCM'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "   ❌ Erreur assignation: " . $e->getMessage() . "\n";
            
            return [
                'success' => false,
                'reason' => 'ERREUR_ASSIGNATION',
                'message' => $e->getMessage(),
                'action_required' => 'Réessayer ou choisir autre coursier'
            ];
        }
    }
}

// Test si exécuté directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "🧪 TEST SYSTÈME ASSIGNATION SÉCURISÉE\n";
    echo "=" . str_repeat("=", 60) . "\n";
    
    $assignment = new SecureOrderAssignment();
    
    // Tester la capacité globale du système
    $systemCheck = $assignment->security->canAcceptNewOrders();
    echo "📊 État système: " . ($systemCheck['can_accept_orders'] ? '✅ OPÉRATIONNEL' : '❌ INDISPONIBLE') . "\n";
    echo "🔊 Message: {$systemCheck['message']}\n\n";
    
    if ($systemCheck['can_accept_orders']) {
        echo "🎯 Système prêt pour accepter des commandes\n";
    } else {
        echo "⚠️ Message client à afficher:\n";
        echo $assignment->security->getUnavailabilityMessage() . "\n";
    }
}
?>