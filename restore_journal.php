<?php
/**
 * restore_journal.php
 *
 * Script de restauration automatique du dossier JOURNAL et de Journal.php
 * Usage : exécutez ce fichier via navigateur (https://votre-site/restore_journal.php) ou en CLI (php restore_journal.php)
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

$baseDir = __DIR__;
$journalDir = $baseDir . '/JOURNAL';

// 1. Créer le dossier JOURNAL si nécessaire
if (!is_dir($journalDir)) {
    if (mkdir($journalDir, 0755, true)) {
        echo "Dossier JOURNAL créé.\n";
    } else {
        echo "Échec de création du dossier JOURNAL. Vérifiez les permissions.\n";
        exit;
    }
} else {
    echo "Dossier JOURNAL déjà existant.\n";
}

// 2. Créer le fichier Journal.php si manquant
$journalFile = $journalDir . '/Journal.php';
if (!file_exists($journalFile)) {
    $stub = <<<'PHP'
<?php
/**
 * Stub JournalUniverselCoursierProd
 * Remplacez ce contenu par le code complet de JOURNAL/Journal.php
 */
class JournalUniverselCoursierProd {
    private $logDir;
    public function __construct() {
        $this->logDir = __DIR__;
    }
    public function logMaxDetail($type, $desc, $details = []) {
        // TODO: implémenter la logique de journalisation
    }
}
PHP;
    if (file_put_contents($journalFile, $stub) !== false) {
        echo "Fichier Journal.php stub créé.\n";
    } else {
        echo "Échec de création de Journal.php.\n";
        exit;
    }
} else {
    echo "Fichier Journal.php déjà présent.\n";
}

// Instructions
echo "\n<b>Note :</b> Remplacez le contenu du stub par le code complet de /JOURNAL/Journal.php si vous souhaitez le journal universel complet.\n";
