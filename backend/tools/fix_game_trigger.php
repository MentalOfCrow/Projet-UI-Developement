<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer toutes les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';

try {
    // Obtenir une connexion à la base de données
    $db = Database::getInstance()->getConnection();
    
    echo "<h1>Correction du trigger et des statistiques de jeu</h1>";
    
    // Lire le fichier SQL
    $sqlFile = __DIR__ . '/../db/fix_trigger.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Le fichier SQL n'existe pas: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        throw new Exception("Impossible de lire le fichier SQL: {$sqlFile}");
    }
    
    // Séparation des commandes SQL (en utilisant le délimiteur //)
    $commands = explode("DELIMITER //", $sql);
    
    // Exécuter chaque commande SQL
    $success = true;
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    
    foreach ($commands as $command) {
        // Nettoyage de la commande
        $command = trim($command);
        if (empty($command)) continue;
        
        // Remplacement du délimiteur de fin
        $command = str_replace("DELIMITER ;", "", $command);
        $command = str_replace("//", "", $command);
        
        try {
            $stmt = $db->prepare($command);
            $stmt->execute();
            echo "<p style='color:green'>✓ Commande SQL exécutée avec succès</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "already exists") !== false) {
                echo "<p style='color:orange'>⚠️ Note: " . htmlspecialchars($e->getMessage()) . "</p>";
            } else {
                $success = false;
                echo "<p style='color:red'>✗ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    // Vérifier les statistiques après correction
    $stmt = $db->query("
        SELECT u.username, s.* 
        FROM users u 
        LEFT JOIN stats s ON u.id = s.user_id 
        ORDER BY s.games_played DESC 
        LIMIT 10
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Statistiques après correction</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
            <th>Joueur</th>
            <th>Parties jouées</th>
            <th>Victoires</th>
            <th>Défaites</th>
            <th>Matchs nuls</th>
            <th>Dernière partie</th>
          </tr>";
    
    foreach ($stats as $stat) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($stat['username']) . "</td>";
        echo "<td>" . ($stat['games_played'] ?? 0) . "</td>";
        echo "<td>" . ($stat['games_won'] ?? 0) . "</td>";
        echo "<td>" . ($stat['games_lost'] ?? 0) . "</td>";
        echo "<td>" . ($stat['draws'] ?? 0) . "</td>";
        echo "<td>" . ($stat['last_game'] ?? 'Jamais') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    if ($success) {
        echo "<h2 style='color:green'>✓ Correction terminée avec succès!</h2>";
    } else {
        echo "<h2 style='color:orange'>⚠️ Correction terminée avec des avertissements</h2>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Erreur:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
} 