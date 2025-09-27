<?php

if (!function_exists('ensureCoursierBridge')) {
    /**
     * Garantit la présence d'un enregistrement dans `coursiers` aligné avec un agent `agents_suzosky`.
     * Renvoie l'identifiant du coursier (existant ou créé).
     *
     * @param PDO $pdo Connexion à la base
     * @param array $agent Données de l'agent (attend au minimum `id`, `nom` et idéalement `telephone`)
     * @param callable|null $logger Callback facultatif pour tracer les opérations (string $message)
     * @return int
     */
    function ensureCoursierBridge(PDO $pdo, array $agent, ?callable $logger = null): int
    {
        $agentId = isset($agent['id']) ? (int) $agent['id'] : 0;
        if ($agentId <= 0) {
            if ($logger) {
                $logger('ID agent invalide, impossible de synchroniser.');
            }
            return 0;
        }

        $log = function (string $message) use ($logger, $agentId): void {
            if ($logger) {
                $logger("CoursierBridge(agent={$agentId}): {$message}");
            }
        };

        // 1. Vérifier un coursier avec le même ID
        $stmtById = $pdo->prepare('SELECT id FROM coursiers WHERE id = ? LIMIT 1');
        $stmtById->execute([$agentId]);
        if ($existingId = $stmtById->fetchColumn()) {
            $log('Enregistrement coursiers déjà présent (match ID).');
            return (int) $existingId;
        }

        // 2. Vérifier via le téléphone pour réutiliser un coursier existant
        $telephone = $agent['telephone'] ?? null;
        if ($telephone) {
            $stmtByPhone = $pdo->prepare('SELECT id FROM coursiers WHERE telephone = ? LIMIT 1');
            $stmtByPhone->execute([$telephone]);
            if ($existingPhoneId = $stmtByPhone->fetchColumn()) {
                $log("Association trouvée via téléphone (coursier_id={$existingPhoneId}).");
                return (int) $existingPhoneId;
            }
        }

        // 3. Créer ou mettre à jour un enregistrement miroir dans coursiers
        $nom = trim(($agent['prenoms'] ?? '') . ' ' . ($agent['nom'] ?? ''));
        if ($nom === '') {
            $nom = $agent['nom'] ?? ('Coursier ' . $agentId);
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $telephoneValue = $telephone ?: null;

        try {
            $insert = $pdo->prepare(
                "INSERT INTO coursiers (id, nom, telephone, statut, disponible, created_at, updated_at)
                 VALUES (?, ?, ?, 'actif', 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     nom = VALUES(nom),
                     telephone = VALUES(telephone),
                     statut = VALUES(statut),
                     disponible = VALUES(disponible),
                     updated_at = VALUES(updated_at)"
            );
            $insert->execute([$agentId, $nom, $telephoneValue, $now, $now]);
            $log('Création/mise à jour effectuée dans coursiers.');
        } catch (PDOException $e) {
            $log('Erreur lors de la synchronisation: ' . $e->getMessage());
            return 0;
        }

        return $agentId;
    }
}
