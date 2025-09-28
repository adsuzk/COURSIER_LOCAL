<?php
/**
 * SYSTÈME DE SÉCURITÉ CRITIQUE FCM TOKENS
 * Surveillance et nettoyage automatique pour conformité légale
 */

require_once 'config.php';

class FCMTokenSecurity {
    private $pdo;
    private $verbose;
    
    public function __construct(array $options = []) {
        $this->pdo = getDBConnection();
        $this->verbose = $options['verbose'] ?? (PHP_SAPI === 'cli');
    }
    
    private function logMessage(string $message): void {
        if (!$this->verbose) {
            return;
        }
        echo $message;
    }
    }
    
    /**
     * NETTOYAGE CRITIQUE: Désactiver tous les tokens des coursiers déconnectés
     * ⚠️ CONFORMITÉ LÉGALE: Aucun token actif pour coursier hors ligne
     */
    public function enforceTokenSecurity(): array {
        echo "🚨 SÉCURITÉ FCM TOKENS - NETTOYAGE CRITIQUE\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        $results = [
            'tokens_disabled' => 0,
            'sessions_cleaned' => 0,
            'security_violations' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        try {
            // 1. Identifier les coursiers déconnectés avec tokens actifs (VIOLATION SÉCURITÉ)
            echo "\n🔍 1. DÉTECTION VIOLATIONS SÉCURITÉ\n";
            $stmt = $this->pdo->query("
                SELECT 
                    a.id, a.matricule, a.nom, a.prenoms, a.statut_connexion,
                    a.last_login_at, a.current_session_token,
                    COUNT(dt.id) as tokens_actifs,
                    TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) as minutes_inactif
                FROM agents_suzosky a
                INNER JOIN device_tokens dt ON a.id = dt.coursier_id 
                WHERE dt.is_active = 1 
                AND (
                    a.statut_connexion != 'en_ligne' 
                    OR a.current_session_token IS NULL
                    OR TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) > 30
                )
                GROUP BY a.id
                ORDER BY tokens_actifs DESC
            ");
            
            $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($violations)) {
                echo "   ✅ Aucune violation détectée - Sécurité conforme\n";
            } else {
                echo "   🚨 " . count($violations) . " VIOLATIONS CRITIQUES détectées!\n\n";
                
                foreach ($violations as $violation) {
                    $matricule = $violation['matricule'] ?? 'N/A';
                    echo "      ⚠️ {$violation['nom']} {$violation['prenoms']} (M:{$matricule})\n";
                    echo "         Status: {$violation['statut_connexion']}\n";
                    echo "         Tokens actifs: {$violation['tokens_actifs']}\n";
                    $this->logMessage("🚨 SÉCURITÉ FCM TOKENS - NETTOYAGE CRITIQUE\n");
                    $this->logMessage("=" . str_repeat("=", 60) . "\n");
                    echo "         Inactif depuis: {$violation['minutes_inactif']} min\n";
                    echo "         Session: " . ($violation['current_session_token'] ? '✅' : '❌') . "\n\n";
                    
                    $results['security_violations'][] = [
                        'coursier_id' => $violation['id'],
                        'matricule' => $matricule,
                        'nom_complet' => $violation['nom'] . ' ' . $violation['prenoms'],
                        'statut' => $violation['statut_connexion'],
                        'tokens_actifs' => $violation['tokens_actifs'],
                        'minutes_inactif' => $violation['minutes_inactif']
                        $this->logMessage("\n🔍 1. DÉTECTION VIOLATIONS SÉCURITÉ\n");
                }
            }
            
            // 2. CORRECTION IMMÉDIATE: Désactiver tous les tokens des coursiers déconnectés
            echo "🔒 2. CORRECTION AUTOMATIQUE\n";
            
            $stmt = $this->pdo->prepare("
                UPDATE device_tokens dt
                INNER JOIN agents_suzosky a ON dt.coursier_id = a.id
                SET dt.is_active = 0,
                    dt.updated_at = NOW()
                WHERE dt.is_active = 1 
                AND (
                    a.statut_connexion != 'en_ligne' 
                    OR a.current_session_token IS NULL
                    OR TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) > 30
                )
            ");
            
            $stmt->execute();
            $results['tokens_disabled'] = $stmt->rowCount();
                            $this->logMessage("   ✅ Aucune violation détectée - Sécurité conforme\n");
            echo "   ✅ {$results['tokens_disabled']} tokens désactivés\n";
                            $this->logMessage("   🚨 " . count($violations) . " VIOLATIONS CRITIQUES détectées!\n\n");
            // 3. Nettoyer les sessions expirées
            echo "\n🧹 3. NETTOYAGE SESSIONS EXPIRÉES\n";
            
                                $this->logMessage("      ⚠️ {$violation['nom']} {$violation['prenoms']} (M:{$matricule})\n");
                                $this->logMessage("         Status: {$violation['statut_connexion']}\n");
                                $this->logMessage("         Tokens actifs: {$violation['tokens_actifs']}\n");
                                $this->logMessage("         Inactif depuis: {$violation['minutes_inactif']} min\n");
                                $this->logMessage("         Session: " . ($violation['current_session_token'] ? '✅' : '❌') . "\n\n");
                WHERE statut_connexion = 'en_ligne' 
                AND (
                    last_login_at IS NULL 
                    OR TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) > 30
                )
            ");
            
            $stmt->execute();
            $results['sessions_cleaned'] = $stmt->rowCount();
            
            echo "   ✅ {$results['sessions_cleaned']} sessions nettoyées\n";
            
                        $this->logMessage("🔒 2. CORRECTION AUTOMATIQUE\n");
            echo "\n📊 4. RAPPORT SÉCURITÉ FINAL\n";
            
            // Coursiers connectés avec tokens actifs (état normal)
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count
                FROM agents_suzosky a
                INNER JOIN device_tokens dt ON a.id = dt.coursier_id
                WHERE a.statut_connexion = 'en_ligne'
                AND a.current_session_token IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) <= 30
                AND dt.is_active = 1
            ");
            $coursiersSecurises = $stmt->fetchColumn();
            
            // Tokens orphelins (ne devraient pas exister)
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count
                        $this->logMessage("   ✅ {$results['tokens_disabled']} tokens désactivés\n");
                LEFT JOIN agents_suzosky a ON dt.coursier_id = a.id
                WHERE dt.is_active = 1 
                        $this->logMessage("\n🧹 3. NETTOYAGE SESSIONS EXPIRÉES\n");
            ");
            $tokensOrphelins = $stmt->fetchColumn();
            
            echo "   ✅ Coursiers sécurisés (connectés + tokens): {$coursiersSecurises}\n";
            echo "   " . ($tokensOrphelins > 0 ? '🚨' : '✅') . " Tokens orphelins: {$tokensOrphelins}\n";
            
            $results['coursiers_securises'] = $coursiersSecurises;
            $results['tokens_orphelins'] = $tokensOrphelins;
            $results['security_status'] = $tokensOrphelins == 0 ? 'CONFORME' : 'NON_CONFORME';
            
        } catch (Exception $e) {
            echo "   ❌ Erreur sécurité: " . $e->getMessage() . "\n";
            $results['error'] = $e->getMessage();
        }
        
        return $results;
                        $this->logMessage("   ✅ {$results['sessions_cleaned']} sessions nettoyées\n");
    
    /**
                        $this->logMessage("\n📊 4. RAPPORT SÉCURITÉ FINAL\n");
     */
    public function canAcceptNewOrders(): array {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count
            FROM agents_suzosky a
            INNER JOIN device_tokens dt ON a.id = dt.coursier_id
            WHERE a.statut_connexion = 'en_ligne'
            AND a.current_session_token IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) <= 30
            AND dt.is_active = 1
            AND COALESCE(a.solde_wallet, 0) > 0
        ");
        
        $coursiersDisponibles = $stmt->fetchColumn();
        
        return [
            'can_accept_orders' => $coursiersDisponibles > 0,
            'coursiers_disponibles' => $coursiersDisponibles,
            'message' => $coursiersDisponibles > 0 
                ? "Système opérationnel - {$coursiersDisponibles} coursier(s) disponible(s)"
                : "⚠️ AUCUN COURSIER DISPONIBLE - Service temporairement suspendu"
        ];
    }
                        $this->logMessage("   ✅ Coursiers sécurisés (connectés + tokens): {$coursiersSecurises}\n");
                        $this->logMessage("   " . ($tokensOrphelins > 0 ? '🚨' : '✅') . " Tokens orphelins: {$tokensOrphelins}\n");
     * Message commercial pour l'index quand aucun coursier disponible
     */
    public function getUnavailabilityMessage(): string {
        return "
        <div style='background: linear-gradient(135deg, #ff6b6b, #ffa500); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0;'>
            <h3>🚚 Service Temporairement Indisponible</h3>
                        $this->logMessage("   ❌ Erreur sécurité: " . $e->getMessage() . "\n");
            <p><strong>Veuillez réessayer dans quelques minutes</strong></p>
            <p style='font-size: 0.9em; opacity: 0.8;'>Nous garantissons la sécurité et la qualité de nos services</p>
        </div>
        ";
    }
}

            if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
                $security = new FCMTokenSecurity(['verbose' => true]);
    $security = new FCMTokenSecurity();
    $results = $security->enforceTokenSecurity();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎯 RÉSUMÉ SÉCURITÉ:\n";
    echo "   Violations corrigées: " . count($results['security_violations']) . "\n";
    echo "   Tokens désactivés: {$results['tokens_disabled']}\n";
    echo "   Sessions nettoyées: {$results['sessions_cleaned']}\n";
    echo "   Statut final: " . ($results['security_status'] ?? 'INCONNU') . "\n";
    
    $orderStatus = $security->canAcceptNewOrders();
    echo "   Acceptation commandes: " . ($orderStatus['can_accept_orders'] ? '✅ OUI' : '❌ NON') . "\n";
    echo "   Message: {$orderStatus['message']}\n";
}
?>