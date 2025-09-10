<?php
require_once __DIR__ . '/../lib/util.php';
/**
 * ============================================================================
 * 🔄 SERVEUR DE SYNCHRONISATION TEMPS RÉEL SUZOSKY
 * ============================================================================
 * 
 * Serveur WebSocket pour la synchronisation en temps réel de toutes les interfaces :
 * - admin.php (central)
 * - business.php
 * - coursier.php  
 * - concierge.php
 * 
 * Utilise Ratchet pour la gestion des connexions WebSocket
 * 
 * @version 1.0.0 - Synchronisation unifiée
 * @author Équipe Suzosky
 * @date 26 août 2025
 * ============================================================================
 */

// Autoload des dépendances
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

/**
 * Classe de synchronisation temps réel
 */
class SuzoskySyncRealTime implements MessageComponentInterface {
    protected $clients = [];
    private $pdo;
    private $last_sync;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->last_sync = date('Y-m-d H:i:s', strtotime('-10 seconds'));
    }
    
    public function onOpen(ConnectionInterface $conn) {
        // Stocker la nouvelle connexion
        $this->clients[$conn->resourceId] = $conn;
        $this->log("Nouvelle connexion: {$conn->resourceId}");
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Traitement du message reçu
        $data = json_decode($msg, true);
        
        if (isset($data['action']) && $data['action'] === 'sync') {
            $sync_data = $this->getSyncData();
            
            if (!empty($sync_data)) {
                $this->sendEvent('sync', $sync_data);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Supprimer la connexion
        unset($this->clients[$conn->resourceId]);
        $this->log("Connexion {$conn->resourceId} déconnectée");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->log("Erreur: {$e->getMessage()}");
        $conn->close();
    }
    
    /**
     * Boucle principale de synchronisation
     */
    public function startSync() {
        $max_execution_time = 300; // 5 minutes max
        $start_time = time();
        
        while (time() - $start_time < $max_execution_time) {
            foreach ($this->clients as $client) {
                // Vérifier si le client est toujours connecté
                if (!$client->isWritable()) {
                    $this->log("Client déconnecté: {$client->resourceId}");
                    unset($this->clients[$client->resourceId]);
                    continue;
                }
                
                // Envoi du heartbeat
                $this->sendEvent('heartbeat', [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'client_id' => $client->resourceId
                ], $client);
                
                // Mettre à jour le last_sync
                $this->last_sync = date('Y-m-d H:i:s');
            }
            
            sleep(2); // Vérification toutes les 2 secondes
        }
    }
    
    /**
     * Récupère les données de synchronisation
     */
    private function getSyncData() {
        $sync_data = [
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        try {
            // 1. SYNCHRONISATION AGENTS
            $sync_data['agents'] = $this->getAgentsUpdates();
            
            // 2. SYNCHRONISATION CHAT
            $sync_data['chat'] = $this->getChatUpdates();
            
            // 3. SYNCHRONISATION COMMANDES
            $sync_data['commandes'] = $this->getCommandesUpdates();
            
            // 4. SYNCHRONISATION COURSIERS
            $sync_data['coursiers'] = $this->getCoursiersUpdates();
            
            // 5. SYNCHRONISATION STATISTIQUES
            $sync_data['stats'] = $this->getStatsUpdates();
            // 6. SYNCHRONISATION CLIENTS PARTiculiers
            $sync_data['clients'] = $this->getClientsUpdates();
            
            // Retourner seulement si il y a des changements
            if ($this->hasChanges($sync_data)) {
                return $sync_data;
            }
            
        } catch (Exception $e) {
            $this->log("Erreur sync: " . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return null;
    }
    
    /**
     * Vérifications agents
     */
    private function getAgentsUpdates() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total,
                       COUNT(CASE WHEN created_at > ? THEN 1 END) as nouveaux,
                       COUNT(CASE WHEN updated_at > ? THEN 1 END) as modifies
                FROM agents_suzosky
            ");
            $stmt->execute([$this->last_sync, $this->last_sync]);
            $result = $stmt->fetch();
            
            // Si il y a des nouveaux/modifiés, récupérer les détails
            if ($result['nouveaux'] > 0 || $result['modifies'] > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT id, nom, prenoms, type_poste, statut, updated_at
                    FROM agents_suzosky 
                    WHERE updated_at > ? OR created_at > ?
                    ORDER BY updated_at DESC
                ");
                $stmt->execute([$this->last_sync, $this->last_sync]);
                $result['details'] = $stmt->fetchAll();
            }
            
            return $result;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Vérifications chat
     */
    private function getChatUpdates() {
        try {
            $stats = [
                'sessions_actives' => 0,
                'messages_non_lus' => 0,
                'nouveaux_messages' => []
            ];
            
            // Sessions actives
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM chat_sessions WHERE status = 'active'");
            $stats['sessions_actives'] = $stmt->fetchColumn();
            
            // Messages non lus
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM chat_messages 
                WHERE read_by_admin = 0 AND sender_type = 'user'
            ");
            $stats['messages_non_lus'] = $stmt->fetchColumn();
            
            // Nouveaux messages depuis last_sync
            $stmt = $this->pdo->prepare("
                SELECT session_id, sender_name, message, created_at
                FROM chat_messages 
                WHERE created_at > ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$this->last_sync]);
            $stats['nouveaux_messages'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Vérifications commandes
     */
    private function getCommandesUpdates() {
        try {
            // Simuler car nous n'avons pas encore la table unifiée
            return [
                'total' => 0,
                'nouvelles' => 0,
                'en_cours' => 0,
                'details' => []
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Vérifications coursiers
     */
    private function getCoursiersUpdates() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total,
                       COUNT(CASE WHEN statut = 'actif' THEN 1 END) as actifs,
                       COUNT(CASE WHEN updated_at > ? THEN 1 END) as modifies
                FROM agents_suzosky 
                WHERE type_poste = 'coursier'
            ");
            $stmt->execute([$this->last_sync]);
            
            return $stmt->fetch();
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Statistiques générales
     */
    private function getStatsUpdates() {
        // Compatibilité Windows/Linux pour sys_getloadavg()
        $server_load = function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 0;
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'server_load' => $server_load,
            'memory_usage' => memory_get_usage(true),
            'active_connections' => count($this->clients) // Compteur réel
        ];
    }
    }
    
    /**
     * Récupération des nouveaux clients particuliers
     */
    private function getClientsUpdates() {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nom, prenoms, telephone, email, date_creation
                 FROM clients_particuliers
                 WHERE date_creation > ?"
            );
            $stmt->execute([$this->last_sync]);
            $newClients = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['new' => $newClients];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    private function hasChanges($data) {
        // Vérifier agents
        if (isset($data['agents']) && ($data['agents']['nouveaux'] > 0 || $data['agents']['modifies'] > 0)) {
            return true;
        }
        
        // Vérifier chat
        if (isset($data['chat']) && !empty($data['chat']['nouveaux_messages'])) {
            return true;
        }
        
        // Vérifier commandes
        if (isset($data['commandes']) && $data['commandes']['nouvelles'] > 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Envoie un événement SSE
     */
    private function sendEvent($event, $data, ConnectionInterface $client = null) {
        $payload = json_encode(['event' => $event, 'data' => $data], JSON_UNESCAPED_UNICODE);
        
        if ($client) {
            // Envoyer à un seul client
            $client->send($payload);
        } else {
            // Diffuser à tous les clients
            foreach ($this->clients as $client) {
                $client->send($payload);
            }
        }
    }
    
    /**
     * Log des événements
     */
    private function log($message) {
        $log_file = __DIR__ . '/logs/sync_realtime_' . date('Y-m-d') . '.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * ============================================================================
 * DÉMARRAGE SERVEUR WEB SOCKET
 * ============================================================================
 */

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SuzoskySyncRealTime()
        )
    ),
    '0.0.0.0:8080'
);

$server->run();
?>
