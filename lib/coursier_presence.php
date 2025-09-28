<?php
/**
 * Outils centralisés pour l'état de connexion des coursiers
 * AVEC NETTOYAGE AUTOMATIQUE DES STATUTS OBSOLÈTES
 */

require_once __DIR__ . '/../config.php';

if (!function_exists('autoCleanExpiredStatuses')) {
    function autoCleanExpiredStatuses(?PDO $existingPdo = null): int {
        $pdo = $existingPdo ?? getDBConnection();
        
        try {
            // Nettoyer automatiquement les statuts expirés (> 30 min d'inactivité)
            $stmt = $pdo->prepare("
                UPDATE agents_suzosky 
                SET statut_connexion = 'hors_ligne',
                    current_session_token = NULL
                WHERE statut_connexion = 'en_ligne' 
                AND (
                    last_login_at IS NULL 
                    OR TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) > 30
                )
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('ensureCourierConnectivityColumns')) {
    function ensureCourierConnectivityColumns(PDO $pdo): void
    {
        static $columnsEnsured = false;
        if ($columnsEnsured) {
            return;
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM agents_suzosky');
            $existing = [];
            while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing[$col['Field']] = true;
            }
        } catch (Throwable $e) {
            $existing = [];
        }

        $alterStatements = [];
        if (!isset($existing['statut_connexion'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN statut_connexion ENUM('en_ligne','hors_ligne') DEFAULT 'hors_ligne'";
        }
        if (!isset($existing['current_session_token'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN current_session_token VARCHAR(100) NULL";
        }
        if (!isset($existing['last_login_at'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN last_login_at DATETIME NULL";
        }
        if (!isset($existing['last_login_ip'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN last_login_ip VARCHAR(64) NULL";
        }
        if (!isset($existing['last_login_user_agent'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN last_login_user_agent VARCHAR(255) NULL";
        }
        if (!isset($existing['connexion_source'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN connexion_source VARCHAR(50) NULL";
        }
        if (!isset($existing['connexion_source_details'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN connexion_source_details VARCHAR(255) NULL";
        }
        if (!isset($existing['connexion_last_seen_at'])) {
            $alterStatements[] = "ALTER TABLE agents_suzosky ADD COLUMN connexion_last_seen_at DATETIME NULL";
        }

        foreach ($alterStatements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // best-effort: ignorer si colonne déjà existante ou erreur de compatibilité
            }
        }

        $columnsEnsured = true;
    }
}

if (!function_exists('markCourierConnected')) {
    function markCourierConnected(PDO $pdo, int $coursierId, array $context = []): void
    {
        if ($coursierId <= 0) {
            return;
        }

        ensureCourierConnectivityColumns($pdo);

        $fields = [
            "statut_connexion = 'en_ligne'",
            'connexion_last_seen_at = NOW()'
        ];
        $params = ['id' => $coursierId];

        if (!empty($context['session_token'])) {
            $fields[] = 'current_session_token = :session_token';
            $params['session_token'] = substr($context['session_token'], 0, 100);
        }

        if (!empty($context['ip'])) {
            $fields[] = 'last_login_ip = :ip';
            $params['ip'] = substr($context['ip'], 0, 60);
        }

        if (!empty($context['user_agent'])) {
            $fields[] = 'last_login_user_agent = :ua';
            $params['ua'] = substr($context['user_agent'], 0, 240);
        }

        if (!empty($context['source'])) {
            $fields[] = 'connexion_source = :source';
            $params['source'] = substr($context['source'], 0, 40);
        }

        if (!empty($context['details'])) {
            $fields[] = 'connexion_source_details = :details';
            $params['details'] = substr($context['details'], 0, 240);
        }

        if (!empty($context['touch_last_login'])) {
            $fields[] = 'last_login_at = NOW()';
        }

        $sql = 'UPDATE agents_suzosky SET ' . implode(', ', $fields) . ' WHERE id = :id';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable $e) {
            // silent fail to avoid breaking API calls
        }
    }
}

if (!function_exists('markCourierDisconnected')) {
    function markCourierDisconnected(PDO $pdo, int $coursierId, array $context = []): void
    {
        if ($coursierId <= 0) {
            return;
        }

        ensureCourierConnectivityColumns($pdo);

        $fields = [
            "statut_connexion = 'hors_ligne'",
            'current_session_token = NULL'
        ];
        $params = ['id' => $coursierId];

        if (!empty($context['source'])) {
            $fields[] = 'connexion_source = :source';
            $params['source'] = substr($context['source'], 0, 40);
        }

        if (!empty($context['details'])) {
            $fields[] = 'connexion_source_details = :details';
            $params['details'] = substr($context['details'], 0, 240);
        }

        $sql = 'UPDATE agents_suzosky SET ' . implode(', ', $fields) . ' WHERE id = :id';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable $e) {
            // best-effort
        }
    }
}

if (!function_exists('getAgentsSchemaInfo')) {
    function getAgentsSchemaInfo(PDO $pdo): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $columns = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM agents_suzosky');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[$row['Field']] = true;
            }
        } catch (PDOException $e) {
            $columns = [];
        }

        $joinColumn = isset($columns['id_coursier']) ? 'id_coursier' : 'id';
        $statusExpression = isset($columns['statut_connexion']) ? 'a.statut_connexion'
            : (isset($columns['status']) ? 'a.status'
                : (isset($columns['is_online']) ? "CASE WHEN a.is_online = 1 THEN 'en_ligne' ELSE 'hors_ligne' END"
                    : "'inconnu'"));

        $onlineExpression = isset($columns['is_online']) ? 'a.is_online' : 'NULL';
        $latitudeExpression = isset($columns['latitude']) ? 'a.latitude' : 'NULL';
        $longitudeExpression = isset($columns['longitude']) ? 'a.longitude' : 'NULL';

        $statusFilterColumn = isset($columns['statut']) ? 'statut' : (isset($columns['status']) ? 'status' : null);

        $cache = [
            'join_column' => $joinColumn,
            'status_expression' => $statusExpression,
            'online_expression' => $onlineExpression,
            'latitude_expression' => $latitudeExpression,
            'longitude_expression' => $longitudeExpression,
            'status_filter_column' => $statusFilterColumn,
        ];

        return $cache;
    }
}

if (!function_exists('getAllCouriers')) {
    function getAllCouriers(?PDO $existingPdo = null): array
    {
        $pdo = $existingPdo ?? getDBConnection();
        $info = getAgentsSchemaInfo($pdo);
        $filter = '';
        if ($info['status_filter_column']) {
            $filter = "WHERE LOWER(a." . $info['status_filter_column'] . ") IN ('actif','active')";
        }

        try {
            $query = "
          SELECT a." . $info['join_column'] . " AS id,
                       a.nom,
                       a.prenoms,
                       a.telephone,
                       a.email,
              a.type_poste,
                       COALESCE(a.solde_wallet, 0) AS solde_wallet,
                       " . $info['status_expression'] . " AS statut_connexion,
                       a.current_session_token,
                       a.last_login_at,
                       a.connexion_last_seen_at,
                       (COALESCE(a.connexion_last_seen_at, a.last_login_at) > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) AS is_recent_activity,
                       COUNT(CASE WHEN dt.is_active = 1 THEN dt.id END) AS active_fcm_tokens
                FROM agents_suzosky a
                LEFT JOIN device_tokens dt ON dt.coursier_id = a." . $info['join_column'] . " AND dt.is_active = 1
                $filter
                GROUP BY a." . $info['join_column'] . ", a.nom, a.prenoms, a.telephone, a.email, a.type_poste, a.solde_wallet,
                         a.current_session_token, a.last_login_at, a.connexion_last_seen_at, " . $info['status_expression'] . "
                ORDER BY " . $info['status_expression'] . " DESC, a.last_login_at DESC
            ";
            $stmt = $pdo->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
}

if (!function_exists('getCoursierStatusLight')) {
    function getCoursierStatusLight(array $coursier, ?PDO $existingPdo = null): array
    {
        $pdo = $existingPdo ?? getDBConnection();

        $status = [
            'color' => 'red',
            'label' => 'Non disponible',
            'conditions' => []
        ];

        $hasToken = !empty($coursier['current_session_token']);
        $status['conditions']['token'] = $hasToken;

        $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
        $status['conditions']['online'] = $isOnline;

        $hasFCMToken = false;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
            $stmt->execute([$coursier['id']]);
            $hasFCMToken = ((int) $stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasFCMToken = false;
        }
        $status['conditions']['fcm'] = $hasFCMToken;

        $hasSufficientBalance = true; // TODO: Vérifier le solde réel selon la logique métier
        $status['conditions']['balance'] = $hasSufficientBalance;

        $lastActivitySource = $coursier['connexion_last_seen_at'] ?? $coursier['last_login_at'] ?? null;
        $lastActivity = strtotime($lastActivitySource ?? '0');
        $isRecentActivity = $lastActivity > (time() - 300);
        if (isset($coursier['is_recent_activity'])) {
            $isRecentActivity = (bool) $coursier['is_recent_activity'];
        }
        $status['conditions']['activity'] = $isRecentActivity;

        if ($hasToken && $isOnline && $isRecentActivity) {
            if (!$hasFCMToken) {
                $status['color'] = 'orange';
                $status['label'] = '⚠️ FCM manquant';
            } elseif ($hasSufficientBalance) {
                $status['color'] = 'green';
                $status['label'] = '✅ Opérationnel';
            } else {
                $status['color'] = 'orange';
                $status['label'] = '💰 Solde faible';
            }
        } else {
            $status['color'] = 'red';
            if (!$hasToken) {
                $status['label'] = '📱 App déconnectée';
            } elseif (!$isOnline) {
                $status['label'] = '⚫ Hors ligne';
            } else {
                $status['label'] = '😴 Inactif';
            }
        }

        return $status;
    }
}

if (!function_exists('getFCMGlobalStatus')) {
    function getFCMGlobalStatus(?PDO $existingPdo = null): array
    {
        $pdo = $existingPdo ?? getDBConnection();

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_connected
                FROM agents_suzosky
                WHERE statut_connexion = 'en_ligne'
                  AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30
            ");
            $stmt->execute();
            $connectedCoursiers = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT a.id) as with_fcm
                FROM agents_suzosky a
                INNER JOIN device_tokens dt ON a.id = dt.coursier_id
                WHERE a.statut_connexion = 'en_ligne'
                  AND TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) <= 30
                  AND dt.is_active = 1
            ");
            $stmt->execute();
            $connectedWithFCM = (int) $stmt->fetchColumn();

            $fcmRate = $connectedCoursiers > 0 ? round(($connectedWithFCM / $connectedCoursiers) * 100, 1) : 0;

            return [
                'total_connected' => $connectedCoursiers,
                'with_fcm' => $connectedWithFCM,
                'without_fcm' => $connectedCoursiers - $connectedWithFCM,
                'fcm_rate' => $fcmRate,
                'status' => $fcmRate >= 80 ? 'excellent' : ($fcmRate >= 60 ? 'correct' : 'critique')
            ];
        } catch (Exception $e) {
            return [
                'total_connected' => 0,
                'with_fcm' => 0,
                'without_fcm' => 0,
                'fcm_rate' => 0,
                'status' => 'erreur',
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('getConnectedCouriers')) {
    function getConnectedCouriers(?PDO $existingPdo = null): array
    {
        $pdo = $existingPdo ?? getDBConnection();
        
        // NETTOYAGE AUTOMATIQUE des statuts expirés AVANT de récupérer les données
        autoCleanExpiredStatuses($pdo);
        
        $coursiers = getAllCouriers($pdo);
        $connected = [];

        foreach ($coursiers as $coursier) {
            $hasToken = !empty($coursier['current_session_token']);
            $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
            $isRecentActivity = isset($coursier['is_recent_activity'])
                ? (bool) $coursier['is_recent_activity']
                : (strtotime(($coursier['connexion_last_seen_at'] ?? $coursier['last_login_at'] ?? '0')) > (time() - 1800));

            if ($hasToken && $isOnline && $isRecentActivity) {
                $connected[] = $coursier + [
                    'status_light' => getCoursierStatusLight($coursier, $pdo)
                ];
            }
        }

        return $connected;
    }
}
