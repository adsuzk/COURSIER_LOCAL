<?php
/**
 * ============================================================================
 * 🤖 INTELLIGENCE ARTIFICIELLE CHAT SUZOSKY
 * ============================================================================
 * 
 * Système d'IA avancé pour le chat support avec reconnaissance d'intention
 * et création automatique de réclamations
 * 
 * @version 1.0.0
 * @author Équipe Suzosky  
 * @date 25 septembre 2025
 * ============================================================================
 */

class SuzoskyChatAI {
    private $pdo;
    private $intentions;
    private $motsCles;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initializeAI();
    }
    
    /**
     * Initialisation de l'IA avec les intentions et mots-clés
     */
    private function initializeAI() {
        // Intentions détectées par l'IA
        $this->intentions = [
            'reclamation' => [
                'confidence' => 0,
                'mots_cles' => [
                    'réclamation', 'reclamation', 'plainte', 'problème', 'souci', 
                    'erreur', 'bug', 'dysfonctionnement', 'incident', 'défaillance',
                    'mécontentement', 'insatisfaction', 'remboursement', 'compensation',
                    'litige', 'différend', 'contestation'
                ],
                'reponse' => '🤖 Je comprends que vous souhaitez faire une réclamation. Je vais vous aider à décrire précisément votre problème pour qu\'il soit traité rapidement par notre équipe.'
            ],
            'information' => [
                'confidence' => 0,
                'mots_cles' => [
                    'information', 'renseignement', 'question', 'aide', 'comment',
                    'pourquoi', 'quand', 'où', 'combien', 'tarif', 'prix', 'horaires'
                ],
                'reponse' => '🤖 Je suis là pour vous renseigner ! Posez-moi votre question et je ferai de mon mieux pour vous aider.'
            ],
            'commande' => [
                'confidence' => 0,
                'mots_cles' => [
                    'commande', 'livraison', 'coursier', 'délai', 'retard', 'statut',
                    'suivi', 'tracking', 'numéro', 'transaction'
                ],
                'reponse' => '🤖 Je peux vous aider avec votre commande. Avez-vous le numéro de transaction pour que je puisse vérifier le statut ?'
            ],
            'salutation' => [
                'confidence' => 0,
                'mots_cles' => [
                    'bonjour', 'bonsoir', 'salut', 'hello', 'hey', 'coucou'
                ],
                'reponse' => '🤖 Bonjour ! Bienvenue sur le support Suzosky. Comment puis-je vous aider aujourd\'hui ?'
            ]
        ];
    }
    
    /**
     * Analyse d'un message et détection d'intention
     */
    public function analyzeMessage($message) {
        $message = strtolower(trim($message));
        $intentions_detectees = [];
        
        foreach ($this->intentions as $type => $intention) {
            $confidence = 0;
            
            foreach ($intention['mots_cles'] as $mot_cle) {
                if (strpos($message, strtolower($mot_cle)) !== false) {
                    $confidence += 1;
                }
            }
            
            // Calcul du pourcentage de confiance
            $confidence_percentage = ($confidence / count($intention['mots_cles'])) * 100;
            
            if ($confidence_percentage > 0) {
                $intentions_detectees[$type] = [
                    'confidence' => $confidence_percentage,
                    'reponse' => $intention['reponse']
                ];
            }
        }
        
        // Trier par confiance décroissante
        arsort($intentions_detectees);
        
        return [
            'message_original' => $message,
            'intentions' => $intentions_detectees,
            'intention_principale' => !empty($intentions_detectees) ? array_keys($intentions_detectees)[0] : 'unknown'
        ];
    }
    
    /**
     * Génération de réponse intelligente
     */
    public function generateResponse($analysis) {
        if (empty($analysis['intentions'])) {
            return [
                'type' => 'default',
                'message' => '🤖 Je suis votre assistant virtuel Suzosky. Je peux vous aider avec vos commandes, répondre à vos questions ou traiter vos réclamations. Comment puis-je vous assister ?',
                'actions' => ['general_help']
            ];
        }
        
        $intention_principale = $analysis['intention_principale'];
        $reponse = $analysis['intentions'][$intention_principale]['reponse'];
        
        switch ($intention_principale) {
            case 'reclamation':
                return [
                    'type' => 'reclamation',
                    'message' => $reponse,
                    'actions' => ['start_complaint_process'],
                    'next_step' => 'ask_transaction_number'
                ];
                
            case 'commande':
                return [
                    'type' => 'commande',
                    'message' => $reponse,
                    'actions' => ['track_order'],
                    'next_step' => 'ask_transaction_number'
                ];
                
            default:
                return [
                    'type' => $intention_principale,
                    'message' => $reponse,
                    'actions' => ['continue_conversation']
                ];
        }
    }
    
    /**
     * Processus guidé pour les réclamations
     */
    public function processComplaintWorkflow($step, $data) {
        switch ($step) {
            case 'ask_transaction_number':
                return [
                    'message' => '🤖 Pour traiter votre réclamation efficacement, j\'ai besoin de votre numéro de transaction. Pouvez-vous me le communiquer ?',
                    'input_type' => 'text',
                    'validation' => 'transaction_number',
                    'next_step' => 'ask_problem_type'
                ];
                
            case 'ask_problem_type':
                return [
                    'message' => '🤖 Merci ! Maintenant, quel type de problème rencontrez-vous ?',
                    'input_type' => 'select',
                    'options' => [
                        'commande' => 'Problème avec ma commande',
                        'livraison' => 'Problème de livraison',
                        'paiement' => 'Problème de paiement',
                        'coursier' => 'Problème avec le coursier',
                        'technique' => 'Problème technique',
                        'autre' => 'Autre problème'
                    ],
                    'next_step' => 'ask_description'
                ];
                
            case 'ask_description':
                return [
                    'message' => '🤖 Parfait ! Pouvez-vous maintenant décrire précisément votre problème ? Plus vous serez précis, plus nous pourrons vous aider rapidement.',
                    'input_type' => 'textarea',
                    'next_step' => 'ask_screenshot'
                ];
                
            case 'ask_screenshot':
                return [
                    'message' => '🤖 Avez-vous une capture d\'écran ou un document à joindre pour illustrer votre problème ?',
                    'input_type' => 'file',
                    'optional' => true,
                    'next_step' => 'create_complaint'
                ];
                
            case 'create_complaint':
                return $this->createComplaint($data);
        }
    }
    
    /**
     * Création d'une réclamation
     */
    public function createComplaint($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO reclamations (
                    numero_transaction, client_id, guest_id, type_reclamation, 
                    sujet, description, capture_ecran, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $metadata = json_encode([
                'created_by_ai' => true,
                'ai_confidence' => $data['ai_confidence'] ?? 0,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'chat_session_id' => $data['chat_session_id'] ?? null
            ]);
            
            $stmt->execute([
                $data['numero_transaction'],
                $data['client_id'] ?? null,
                $data['guest_id'] ?? null,
                $data['type_reclamation'],
                $this->generateComplaintTitle($data['type_reclamation'], $data['numero_transaction']),
                $data['description'],
                $data['capture_ecran'] ?? null,
                $metadata
            ]);
            
            $complaint_id = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'complaint_id' => $complaint_id,
                'message' => "🤖 Parfait ! Votre réclamation #$complaint_id a été créée et transmise à notre équipe. Vous recevrez une réponse dans les plus brefs délais. Y a-t-il autre chose que je puisse faire pour vous ?",
                'actions' => ['complaint_created']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '🤖 Une erreur est survenue lors de la création de votre réclamation. Veuillez réessayer ou contacter directement notre support.',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Génération automatique du titre de réclamation
     */
    private function generateComplaintTitle($type, $transaction) {
        $titles = [
            'commande' => "Problème commande - Transaction $transaction",
            'livraison' => "Incident livraison - Transaction $transaction", 
            'paiement' => "Problème paiement - Transaction $transaction",
            'coursier' => "Problème coursier - Transaction $transaction",
            'technique' => "Problème technique - Transaction $transaction",
            'autre' => "Réclamation - Transaction $transaction"
        ];
        
        return $titles[$type] ?? "Réclamation - Transaction $transaction";
    }
    
    /**
     * Validation des données
     */
    public function validateTransactionNumber($number) {
        // Vérifier le format du numéro de transaction
        if (!preg_match('/^[A-Z0-9]{8,20}$/', strtoupper($number))) {
            return [
                'valid' => false,
                'message' => '🤖 Le format du numéro de transaction ne semble pas correct. Il doit contenir entre 8 et 20 caractères alphanumériques.'
            ];
        }
        
        // Vérifier si la transaction existe
        $stmt = $this->pdo->prepare("SELECT id FROM commandes WHERE numero_commande = ?");
        $stmt->execute([strtoupper($number)]);
        
        if ($stmt->rowCount() === 0) {
            return [
                'valid' => false,
                'message' => '🤖 Je ne trouve pas cette transaction dans notre système. Vérifiez le numéro ou contactez notre support si vous êtes certain qu\'il est correct.'
            ];
        }
        
        return [
            'valid' => true,
            'message' => '🤖 Transaction trouvée ! Je peux maintenant traiter votre réclamation.'
        ];
    }
}