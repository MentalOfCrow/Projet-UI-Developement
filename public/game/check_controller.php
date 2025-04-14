<?php
// Script de diagnostic pour GameController
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';
require_once __DIR__ . '/../../backend/includes/session.php';
require_once __DIR__ . '/../../backend/models/User.php';
require_once __DIR__ . '/../../backend/models/Game.php';
require_once __DIR__ . '/../../backend/controllers/GameController.php';

/**
 * Classe de diagnostic pour GameController
 */
class GameControllerDiagnostic {
    private $db;
    
    /**
     * Exécute tous les tests de diagnostic
     */
    public function runDiagnostic() {
        echo "<h1>Diagnostic du GameController</h1>";
        
        $this->checkConfiguration();
        $this->testDirectConnection();
        $this->checkRequiredTables();
        $this->testGameControllerInstantiation();
        $this->suggestFixes();
        
        echo "<p><a href='/public/game/play.php'>Retour à la page de jeu</a></p>";
    }
    
    /**
     * Vérifie la configuration de la base de données
     */
    private function checkConfiguration() {
        echo "<h2>1. Configuration de la base de données</h2>";
        echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : "Non défini") . "<br>";
        echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : "Non défini") . "<br>";
        echo "DB_USER: " . (defined('DB_USER') ? DB_USER : "Non défini") . "<br>";
        echo "DB_PASS: " . (defined('DB_PASS') ? "***" : "Non défini") . "<br>";
    }
    
    /**
     * Teste la connexion directe à la base de données
     */
    private function testDirectConnection() {
        echo "<h2>2. Test de connexion directe</h2>";
        try {
            $this->db = Database::getInstance()->getConnection();
            echo "<p style='color:green'>✓ Connexion établie avec succès</p>";
            echo "Type de la connexion: " . (is_object($this->db) ? get_class($this->db) : gettype($this->db)) . "<br>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ Erreur de connexion: " . $e->getMessage() . "</p>";
        }
    }
    
    /**
     * Vérifie l'existence des tables requises
     */
    private function checkRequiredTables() {
        echo "<h2>3. Vérification des tables</h2>";
        $requiredTables = ['users', 'games', 'moves', 'stats'];
        try {
            $tablesMissing = false;
            if (!isset($this->db)) {
                $this->db = Database::getInstance()->getConnection();
            }
            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<ul>";
            foreach($requiredTables as $table) {
                if (in_array($table, $tables)) {
                    echo "<li style='color:green'>✓ Table '$table' existe</li>";
                } else {
                    echo "<li style='color:red'>✗ Table '$table' n'existe pas</li>";
                    $tablesMissing = true;
                }
            }
            echo "</ul>";
            
            if ($tablesMissing) {
                echo "<p><a href='/backend/tools/initialize_database.php' target='_blank'>Initialiser la base de données</a></p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ Erreur lors de la vérification des tables: " . $e->getMessage() . "</p>";
        }
    }
    
    /**
     * Teste l'instanciation du GameController
     */
    private function testGameControllerInstantiation() {
        echo "<h2>4. Test d'instanciation du GameController</h2>";
        try {
            $gameController = new GameController();
            echo "<p style='color:green'>✓ GameController instancié avec succès</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ Erreur lors de l'instanciation: " . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    }
    
    /**
     * Suggère des corrections pour les erreurs courantes
     */
    private function suggestFixes() {
        echo "<h2>5. Correction proposée</h2>";
        echo "<p>Si vous rencontrez l'erreur 'Cannot implicitly convert PDO to string', modifiez le fichier <code>backend/controllers/GameController.php</code> à la ligne 35 :</p>";

        echo "<pre style='background-color:#f5f5f5; padding:10px;'>
// Avant (problématique) :
if (!&#36;this->db) { ... }

// Après (corrigé) :
if (!(&#36;this->db instanceof PDO)) { ... }
</pre>";

        echo "<p>Ou si le problème est dans la vérification de la connexion, remplacer par :</p>";

        echo "<pre style='background-color:#f5f5f5; padding:10px;'>
// Avant (problématique) :
// Vérifier que la connexion est établie
if (!&#36;this->db) {
    error_log('GameController: Échec de la connexion à la base de données');
    throw new Exception('Impossible de se connecter à la base de données');
}

// Après (corrigé) :
// Vérifier que la connexion est établie
if (!(&#36;this->db instanceof PDO)) {
    error_log('GameController: Échec de la connexion à la base de données');
    throw new Exception('Impossible de se connecter à la base de données');
}
</pre>";
    }
}

// Exécuter le diagnostic
$diagnostic = new GameControllerDiagnostic();
$diagnostic->runDiagnostic();
?> 