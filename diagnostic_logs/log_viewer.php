<?php
/**
 * INTERFACE DE VISUALISATION DES LOGS AVANCÉS
 * Tableau de bord complet pour le debugging
 */

require_once 'advanced_logger.php';

class LogViewer {
    private $logPath;
    private $allowedLogFiles = [
        'application.log' => 'Application Générale',
        'index.log' => 'Interface Index',
        'coursier.log' => 'Interface Coursier',
        'admin.log' => 'Interface Admin',
        'concierge.log' => 'Interface Concierge',
        'recrutement.log' => 'Interface Recrutement',
        'payments.log' => 'Paiements',
        'database.log' => 'Base de Données',
        'user_actions.log' => 'Actions Utilisateurs',
        'api.log' => 'API Calls',
        'security.log' => 'Sécurité',
        'performance.log' => 'Performance',
        'critical_errors.log' => 'Erreurs Critiques'
    ];

    public function __construct() {
        $this->logPath = __DIR__ . '/';
    }

    public function displayDashboard() {
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>🔍 Dashboard Logs Avancés - Coursier Prod</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    background: #0f0f0f; 
                    color: #fff; 
                    line-height: 1.6;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 20px;
                    text-align: center;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                .header h1 { font-size: 2.5em; margin-bottom: 10px; }
                .header p { font-size: 1.1em; opacity: 0.9; }
                
                .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .stat-card {
                    background: #1a1a1a;
                    border-radius: 10px;
                    padding: 20px;
                    border-left: 4px solid;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    transition: transform 0.3s ease;
                }
                .stat-card:hover { transform: translateY(-5px); }
                .stat-card.critical { border-color: #ff4757; }
                .stat-card.warning { border-color: #ffa502; }
                .stat-card.info { border-color: #3742fa; }
                .stat-card.success { border-color: #2ed573; }
                
                .stat-number { font-size: 2.5em; font-weight: bold; margin-bottom: 5px; }
                .stat-label { font-size: 0.9em; opacity: 0.8; text-transform: uppercase; }
                
                .controls {
                    background: #1a1a1a;
                    border-radius: 10px;
                    padding: 20px;
                    margin-bottom: 20px;
                    display: flex;
                    gap: 15px;
                    flex-wrap: wrap;
                    align-items: center;
                }
                
                .control-group {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }
                
                .control-group label {
                    font-size: 0.9em;
                    color: #ccc;
                    font-weight: 500;
                }
                
                select, input[type="text"], input[type="number"] {
                    background: #2a2a2a;
                    border: 1px solid #444;
                    border-radius: 5px;
                    color: #fff;
                    padding: 8px 12px;
                    font-size: 14px;
                }
                
                select:focus, input:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
                }
                
                .btn {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    border-radius: 5px;
                    padding: 10px 20px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                }
                
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                }
                
                .log-viewer {
                    background: #1a1a1a;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                
                .log-header {
                    background: #2a2a2a;
                    padding: 15px 20px;
                    border-bottom: 1px solid #444;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .log-content {
                    max-height: 600px;
                    overflow-y: auto;
                    font-family: 'Courier New', monospace;
                    font-size: 13px;
                    line-height: 1.4;
                }
                
                .log-line {
                    padding: 8px 20px;
                    border-bottom: 1px solid #2a2a2a;
                    white-space: pre-wrap;
                    word-break: break-all;
                }
                
                .log-line:hover { background: #2a2a2a; }
                .log-line.error { background: rgba(255, 71, 87, 0.1); border-left: 3px solid #ff4757; }
                .log-line.warning { background: rgba(255, 165, 2, 0.1); border-left: 3px solid #ffa502; }
                .log-line.info { background: rgba(55, 66, 250, 0.1); border-left: 3px solid #3742fa; }
                .log-line.debug { background: rgba(46, 213, 115, 0.1); border-left: 3px solid #2ed573; }
                
                .timestamp { color: #9b59b6; font-weight: bold; }
                .level { font-weight: bold; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
                .level.ERROR { background: #ff4757; color: white; }
                .level.WARNING { background: #ffa502; color: white; }
                .level.INFO { background: #3742fa; color: white; }
                .level.DEBUG { background: #2ed573; color: white; }
                
                .search-highlight { background: #ffeb3b; color: #000; padding: 1px 3px; border-radius: 2px; }
                
                .real-time-toggle {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .toggle-switch {
                    position: relative;
                    display: inline-block;
                    width: 50px;
                    height: 24px;
                }
                
                .toggle-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                
                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #444;
                    transition: .4s;
                    border-radius: 24px;
                }
                
                .slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                
                input:checked + .slider { background-color: #667eea; }
                input:checked + .slider:before { transform: translateX(26px); }
                
                @media (max-width: 768px) {
                    .controls { flex-direction: column; align-items: stretch; }
                    .log-content { font-size: 11px; }
                    .header h1 { font-size: 2em; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🔍 Dashboard Logs Avancés</h1>
                <p>Système de monitoring extrêmement précis - Coursier Prod</p>
            </div>

            <div class="container">
                <!-- Statistiques -->
                <div class="stats-grid">
                    <?php echo $this->generateStats(); ?>
                </div>

                <!-- Contrôles -->
                <div class="controls">
                    <div class="control-group">
                        <label>Fichier de log :</label>
                        <select id="logFile" onchange="loadLogs()">
                            <?php foreach ($this->allowedLogFiles as $file => $label): ?>
                                <option value="<?php echo $file; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label>Niveau minimum :</label>
                        <select id="logLevel" onchange="filterLogs()">
                            <option value="">Tous</option>
                            <option value="DEBUG">DEBUG</option>
                            <option value="INFO">INFO</option>
                            <option value="WARNING">WARNING</option>
                            <option value="ERROR">ERROR</option>
                            <option value="CRITICAL">CRITICAL</option>
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label>Recherche :</label>
                        <input type="text" id="searchText" placeholder="Rechercher dans les logs..." onkeyup="searchLogs()">
                    </div>
                    
                    <div class="control-group">
                        <label>Dernières lignes :</label>
                        <input type="number" id="lineCount" value="100" min="10" max="1000" onchange="loadLogs()">
                    </div>
                    
                    <button class="btn" onclick="loadLogs()">🔄 Actualiser</button>
                    <button class="btn" onclick="clearLogs()">🗑️ Vider</button>
                    <button class="btn" onclick="downloadLogs()">⬇️ Télécharger</button>
                    
                    <div class="real-time-toggle">
                        <label>Temps réel :</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="realTime" onchange="toggleRealTime()">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Visualiseur de logs -->
                <div class="log-viewer">
                    <div class="log-header">
                        <h3 id="logTitle">Application Générale</h3>
                        <div>
                            <span id="logInfo">Chargement...</span>
                            <span id="liveIndicator" style="display: none; color: #2ed573;">● LIVE</span>
                        </div>
                    </div>
                    <div class="log-content" id="logContent">
                        <div style="padding: 40px; text-align: center; color: #666;">
                            Sélectionnez un fichier de log pour commencer...
                        </div>
                    </div>
                </div>
            </div>

            <script>
                let realTimeInterval = null;
                let lastLogSize = 0;

                function loadLogs() {
                    const logFile = document.getElementById('logFile').value;
                    const lineCount = document.getElementById('lineCount').value;
                    const logTitle = document.getElementById('logFile').selectedOptions[0].text;
                    
                    document.getElementById('logTitle').textContent = logTitle;
                    document.getElementById('logInfo').textContent = 'Chargement...';
                    
                    fetch('?action=getLogs&file=' + encodeURIComponent(logFile) + '&lines=' + lineCount)
                        .then(response => response.json())
                        .then(data => {
                            displayLogs(data.logs);
                            document.getElementById('logInfo').textContent = 
                                `${data.totalLines} lignes | Taille: ${data.fileSize} | Modifié: ${data.lastModified}`;
                            lastLogSize = data.fileSizeBytes;
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            document.getElementById('logContent').innerHTML = 
                                '<div style="padding: 20px; color: #ff4757;">Erreur lors du chargement des logs</div>';
                        });
                }

                function displayLogs(logs) {
                    const content = document.getElementById('logContent');
                    content.innerHTML = '';
                    
                    if (logs.length === 0) {
                        content.innerHTML = '<div style="padding: 20px; color: #666;">Aucun log trouvé</div>';
                        return;
                    }
                    
                    logs.forEach(line => {
                        const div = document.createElement('div');
                        div.className = 'log-line ' + (line.level ? line.level.toLowerCase() : '');
                        div.innerHTML = formatLogLine(line.content);
                        content.appendChild(div);
                    });
                    
                    content.scrollTop = content.scrollHeight;
                }

                function formatLogLine(line) {
                    // Mise en forme des timestamps
                    line = line.replace(/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[^\]]*)\]/g, 
                        '<span class="timestamp">[$1]</span>');
                    
                    // Mise en forme des niveaux
                    line = line.replace(/\[(ERROR|WARNING|INFO|DEBUG|CRITICAL)\]/g, 
                        '<span class="level $1">$1</span>');
                    
                    // Recherche highlight
                    const searchText = document.getElementById('searchText').value;
                    if (searchText) {
                        const regex = new RegExp('(' + searchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                        line = line.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                    
                    return line;
                }

                function filterLogs() {
                    const level = document.getElementById('logLevel').value;
                    const lines = document.querySelectorAll('.log-line');
                    
                    lines.forEach(line => {
                        if (!level) {
                            line.style.display = 'block';
                        } else {
                            const hasLevel = line.querySelector('.level.' + level) || 
                                           (level === 'DEBUG' && !line.querySelector('.level'));
                            line.style.display = hasLevel ? 'block' : 'none';
                        }
                    });
                }

                function searchLogs() {
                    loadLogs(); // Recharger avec highlight
                }

                function toggleRealTime() {
                    const isEnabled = document.getElementById('realTime').checked;
                    const indicator = document.getElementById('liveIndicator');
                    
                    if (isEnabled) {
                        indicator.style.display = 'inline';
                        realTimeInterval = setInterval(() => {
                            checkForUpdates();
                        }, 2000);
                    } else {
                        indicator.style.display = 'none';
                        if (realTimeInterval) {
                            clearInterval(realTimeInterval);
                            realTimeInterval = null;
                        }
                    }
                }

                function checkForUpdates() {
                    const logFile = document.getElementById('logFile').value;
                    
                    fetch('?action=checkSize&file=' + encodeURIComponent(logFile))
                        .then(response => response.json())
                        .then(data => {
                            if (data.size > lastLogSize) {
                                loadLogs();
                            }
                        });
                }

                function clearLogs() {
                    if (confirm('Êtes-vous sûr de vouloir vider ce fichier de log ?')) {
                        const logFile = document.getElementById('logFile').value;
                        
                        fetch('?action=clearLogs&file=' + encodeURIComponent(logFile), {method: 'POST'})
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    loadLogs();
                                    alert('Logs vidés avec succès');
                                } else {
                                    alert('Erreur lors du vidage des logs');
                                }
                            });
                    }
                }

                function downloadLogs() {
                    const logFile = document.getElementById('logFile').value;
                    window.location.href = '?action=download&file=' + encodeURIComponent(logFile);
                }

                // Chargement initial
                document.addEventListener('DOMContentLoaded', function() {
                    loadLogs();
                });
            </script>
        </body>
        </html>
        <?php
    }

    private function generateStats() {
        $stats = [
            'critical' => 0,
            'errors' => 0,
            'warnings' => 0,
            'total_logs' => 0
        ];

        foreach ($this->allowedLogFiles as $file => $label) {
            $filepath = $this->logPath . $file;
            if (file_exists($filepath)) {
                $content = file_get_contents($filepath);
                $stats['total_logs'] += substr_count($content, "\n");
                $stats['critical'] += preg_match_all('/\[CRITICAL\]/', $content);
                $stats['errors'] += preg_match_all('/\[ERROR\]/', $content);
                $stats['warnings'] += preg_match_all('/\[WARNING\]/', $content);
            }
        }

        return sprintf('
            <div class="stat-card critical">
                <div class="stat-number">%d</div>
                <div class="stat-label">Erreurs Critiques</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number">%d</div>
                <div class="stat-label">Erreurs</div>
            </div>
            <div class="stat-card info">
                <div class="stat-number">%d</div>
                <div class="stat-label">Avertissements</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number">%s</div>
                <div class="stat-label">Total Logs</div>
            </div>',
            $stats['critical'],
            $stats['errors'],
            $stats['warnings'],
            number_format($stats['total_logs'])
        );
    }

    public function handleAjax() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'getLogs':
                $this->getLogsAjax();
                break;
            case 'checkSize':
                $this->checkFileSizeAjax();
                break;
            case 'clearLogs':
                $this->clearLogsAjax();
                break;
            case 'download':
                $this->downloadLogsAjax();
                break;
        }
    }

    private function getLogsAjax() {
        $file = $_GET['file'] ?? 'application.log';
        $lines = min(1000, max(10, (int)($_GET['lines'] ?? 100)));
        
        if (!isset($this->allowedLogFiles[$file])) {
            http_response_code(400);
            echo json_encode(['error' => 'Fichier non autorisé']);
            return;
        }
        
        $filepath = $this->logPath . $file;
        
        if (!file_exists($filepath)) {
            echo json_encode([
                'logs' => [],
                'totalLines' => 0,
                'fileSize' => '0 B',
                'fileSizeBytes' => 0,
                'lastModified' => 'N/A'
            ]);
            return;
        }
        
        // Lire les dernières lignes
        $command = "tail -n $lines " . escapeshellarg($filepath);
        $output = shell_exec($command);
        
        if ($output === null) {
            // Fallback pour Windows
            $content = file_get_contents($filepath);
            $allLines = explode("\n", $content);
            $output = implode("\n", array_slice($allLines, -$lines));
        }
        
        $logLines = array_filter(explode("\n", $output));
        $parsedLogs = [];
        
        foreach ($logLines as $line) {
            $level = null;
            if (preg_match('/\[(ERROR|WARNING|INFO|DEBUG|CRITICAL)\]/', $line, $matches)) {
                $level = $matches[1];
            }
            
            $parsedLogs[] = [
                'content' => $line,
                'level' => $level
            ];
        }
        
        $fileSize = filesize($filepath);
        $totalLines = substr_count(file_get_contents($filepath), "\n");
        
        echo json_encode([
            'logs' => $parsedLogs,
            'totalLines' => $totalLines,
            'fileSize' => $this->formatBytes($fileSize),
            'fileSizeBytes' => $fileSize,
            'lastModified' => date('Y-m-d H:i:s', filemtime($filepath))
        ]);
    }

    private function checkFileSizeAjax() {
        $file = $_GET['file'] ?? 'application.log';
        $filepath = $this->logPath . $file;
        
        $size = file_exists($filepath) ? filesize($filepath) : 0;
        echo json_encode(['size' => $size]);
    }

    private function clearLogsAjax() {
        $file = $_GET['file'] ?? 'application.log';
        
        if (!isset($this->allowedLogFiles[$file])) {
            echo json_encode(['success' => false, 'error' => 'Fichier non autorisé']);
            return;
        }
        
        $filepath = $this->logPath . $file;
        $success = file_put_contents($filepath, '') !== false;
        
        echo json_encode(['success' => $success]);
    }

    private function downloadLogsAjax() {
        $file = $_GET['file'] ?? 'application.log';
        
        if (!isset($this->allowedLogFiles[$file])) {
            http_response_code(400);
            echo 'Fichier non autorisé';
            return;
        }
        
        $filepath = $this->logPath . $file;
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            echo 'Fichier non trouvé';
            return;
        }
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '_' . date('Y-m-d_H-i-s') . '.log"');
        header('Content-Length: ' . filesize($filepath));
        
        readfile($filepath);
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Gestion des requêtes
if (isset($_GET['action'])) {
    $viewer = new LogViewer();
    $viewer->handleAjax();
    exit;
}

// Affichage du dashboard
$viewer = new LogViewer();
$viewer->displayDashboard();
?>
