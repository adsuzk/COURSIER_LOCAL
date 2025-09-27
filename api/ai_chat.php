<?php
/**
 * ============================================================================
 * 🤖 API INTELLIGENCE ARTIFICIELLE CHAT - SUZOSKY
 * ============================================================================
 * 
 * API pour le traitement des messages par l'IA et gestion des réclamations
 * 
 * @version 1.0.0
 * @author Équipe Suzosky  
 * @date 25 septembre 2025
 * ============================================================================
 */

require_once '../config.php';
require_once '../classes/SuzoskyChatAI.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    // Connexion base de données
    $pdo = new PDO(
        "mysql:host={$config['db']['development']['host']};dbname={$config['db']['development']['name']};charset=utf8mb4",
        $config['db']['development']['user'],
        $config['db']['development']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    $chatAI = new SuzoskyChatAI($pdo);
    
    switch ($action) {
        case 'analyze_message':
            handleAnalyzeMessage($chatAI, $input);
            break;
            
        case 'process_complaint_step':
            handleComplaintStep($chatAI, $input);
            break;
            
        case 'track_order':
            handleTrackOrder($pdo, $input);
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Analyse d'un message par l'IA
 */
function handleAnalyzeMessage($chatAI, $input) {
    $message = $input['message'] ?? '';
    $workflow = $input['complaint_workflow'] ?? null;
    
    if (empty($message)) {
        throw new Exception('Message vide');
    }
    
    // Si on est dans un processus de réclamation en cours
    if ($workflow && !empty($workflow['current_step'])) {
        $result = $chatAI->processComplaintWorkflow($workflow['current_step'], $workflow['data'] ?? []);
        
        echo json_encode([
            'success' => true,
            'ai_response' => $result,
            'workflow' => $workflow
        ]);
        return;
    }
    
    // Analyse normale du message
    $analysis = $chatAI->analyzeMessage($message);
    $response = $chatAI->generateResponse($analysis);
    
    $workflow_data = null;
    if ($response['type'] === 'reclamation') {
        // Initialiser le processus de réclamation
        $workflow_data = [
            'current_step' => 'ask_transaction_number',
            'data' => [],
            'started_at' => date('Y-m-d H:i:s')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'ai_response' => $response,
        'workflow' => $workflow_data,
        'analysis' => $analysis, // Pour debug
        'escalate_to_human' => shouldEscalateToHuman($analysis)
    ]);
}

/**
 * Traitement d'une étape du processus de réclamation
 */
function handleComplaintStep($chatAI, $input) {
    $step = $input['step'] ?? '';
    $data = $input['data'] ?? [];
    $workflow = $input['workflow'] ?? [];
    
    // Valider les données selon l'étape
    switch ($step) {
        case 'transaction':
            $validation = $chatAI->validateTransactionNumber($data['transaction_number'] ?? '');
            if (!$validation['valid']) {
                echo json_encode([
                    'success' => true,
                    'ai_response' => ['message' => $validation['message']],
                    'workflow' => $workflow // Rester à la même étape
                ]);
                return;
            }
            
            // Sauvegarder le numéro validé
            $workflow['data']['transaction_number'] = strtoupper($data['transaction_number']);
            $workflow['current_step'] = 'ask_problem_type';
            
            echo json_encode([
                'success' => true,
                'ai_response' => ['message' => '✅ Transaction validée ! Quel type de problème rencontrez-vous ?'],
                'workflow' => $workflow
            ]);
            break;
            
        case 'type':
            $workflow['data']['problem_type'] = $data['problem_type'];
            $workflow['current_step'] = 'ask_description';
            
            echo json_encode([
                'success' => true,
                'ai_response' => ['message' => '📝 Parfait ! Maintenant, décrivez précisément votre problème :'],
                'workflow' => $workflow
            ]);
            break;
            
        case 'description':
            $workflow['data']['description'] = $data['description'];
            $workflow['current_step'] = 'ask_screenshot';
            
            echo json_encode([
                'success' => true,
                'ai_response' => ['message' => '📎 Avez-vous une capture d\'écran ou un document à joindre ?'],
                'workflow' => $workflow
            ]);
            break;
            
        case 'file':
        case 'skip_file':
            $attachments = [];
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                foreach ($data['attachments'] as $attachment) {
                    if (isset($attachment['path'])) {
                        $attachments[] = [
                            'path' => (string) $attachment['path'],
                            'name' => $attachment['original_name'] ?? basename($attachment['path']),
                            'url' => $attachment['url'] ?? null,
                            'mime' => $attachment['mime'] ?? null,
                            'size' => $attachment['size'] ?? null,
                        ];
                    }
                }
            }

            // Finaliser la réclamation
            $complaintData = [
                'numero_transaction' => $workflow['data']['transaction_number'],
                'client_id' => null,
                'guest_id' => $input['guest_id'] ?? null,
                'type_reclamation' => $workflow['data']['problem_type'],
                'description' => $workflow['data']['description'],
                'ai_confidence' => 85, // Score de confiance IA
                'chat_session_id' => $input['conversation_id'] ?? null,
                'attachments' => $attachments
            ];
            
            $result = $chatAI->createComplaint($complaintData);
            
            if ($result['success']) {
                $workflow['completed'] = true;
                $workflow['complaint_id'] = $result['complaint_id'];
            }
            
            echo json_encode([
                'success' => true,
                'ai_response' => $result,
                'workflow' => $workflow,
                'complaint_id' => $result['complaint_id'] ?? null
            ]);
            break;
            
        default:
            throw new Exception('Étape non reconnue');
    }
}

/**
 * Suivi de commande
 */
function handleTrackOrder($pdo, $input) {
    $transactionNumber = $input['transaction_number'] ?? '';
    
    if (empty($transactionNumber)) {
        throw new Exception('Numéro de transaction requis');
    }
    
    $stmt = $pdo->prepare("
        SELECT c.*, 
               CASE 
                   WHEN c.coursier_id IS NOT NULL THEN 
                       (SELECT nom FROM agents_suzosky WHERE id = c.coursier_id)
                   ELSE NULL
               END as coursier_nom
        FROM commandes c 
        WHERE c.numero_commande = ?
    ");
    
    $stmt->execute([strtoupper($transactionNumber)]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'error' => 'Commande introuvable'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);
}

/**
 * Détermine s'il faut escalader vers un humain
 */
function shouldEscalateToHuman($analysis) {
    // Si aucune intention détectée avec confiance suffisante
    if (empty($analysis['intentions']) || max(array_column($analysis['intentions'], 'confidence')) < 30) {
        return true;
    }
    
    // Si message contient des mots-clés d'escalade
    $escalationKeywords = ['urgent', 'important', 'manager', 'responsable', 'plainte', 'avocat', 'tribunal'];
    $message = strtolower($analysis['message_original']);
    
    foreach ($escalationKeywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}