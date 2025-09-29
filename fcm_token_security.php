<?php
/**
 * Fichier de compatibilité : redirige les anciens appels vers la nouvelle arborescence
 * Les scripts FCM résident désormais dans Scripts/Scripts cron
 */

$newPath = __DIR__ . '/Scripts/Scripts cron/fcm_token_security.php';
if (file_exists($newPath)) {
    require_once $newPath;
} else {
    // Fallback si le fichier cible n'existe pas (déploiement incomplet)
    error_log('[COMPAT] fcm_token_security.php redirect failed - target missing: ' . $newPath);
    
    if (!class_exists('FCMTokenSecurity')) {
        // Classe de secours minimale pour éviter les fatal errors
        class FCMTokenSecurity {
            private bool $verbose;
            
            public function __construct(array $options = []) {
                $this->verbose = (bool)($options['verbose'] ?? false);
            }
            
            public function enforceTokenSecurity(): array {
                    return [
                        'tokens_disabled' => 0,
                        'sessions_cleaned' => 0,
                        'security_violations' => [],
                        'timestamp' => date('Y-m-d H:i:s'),
                        'fallback_mode' => true
                    ];
                }
            
                /**
                 * Fallback conservative behavior: when the full security script is missing,
                 * deny accepting new orders by default to avoid accepting orders while
                 * we cannot verify token health.
                 */
                public function canAcceptNewOrders(): array {
                    return [
                        'can_accept_orders' => false,
                        'available_coursiers' => 0,
                        'fallback_mode' => true
                    ];
                }
            
                public function getUnavailabilityMessage(): string {
                    return 'Nos coursiers sont actuellement très sollicités. Restez sur cette page — des coursiers se libèrent dans un instant et le formulaire se rouvrira automatiquement pour vous permettre de commander immédiatement. Merci pour votre patience !';
                }
        }
    }
}