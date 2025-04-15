<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enregistrer toutes les erreurs dans un fichier log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../backend/logs/php_errors.log');

require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/db/Database.php';

try {
    // Obtenir une connexion à la base de données
    $db = Database::getInstance()->getConnection();
    
    echo "<h1>Correction du trigger et des statistiques de jeu</h1>";
    
    // Lire le fichier SQL
    $sqlFile = __DIR__ . '/../backend/db/fix_trigger.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Le fichier SQL n'existe pas: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        throw new Exception("Impossible de lire le fichier SQL: {$sqlFile}");
    }
    
    // Diviser le script en instructions individuelles
    $delimiter = '//';
    $sqlPieces = array();
    $currentPiece = '';
    $lines = explode("\n", $sql);
    $inProcedure = false;
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        // Détecter le changement de délimiteur
        if (preg_match('/^DELIMITER\s+(.+)$/', $trimmedLine, $matches)) {
            $delimiter = $matches[1];
            continue;
        }
        
        // Vérifier si c'est la fin d'une procédure/fonction/trigger
        if ($inProcedure && $trimmedLine === $delimiter) {
            $currentPiece .= ";\n";
            $sqlPieces[] = $currentPiece;
            $currentPiece = '';
            $inProcedure = false;
            continue;
        }
        
        // Détecter le début d'une procédure, fonction ou trigger
        if (preg_match('/^CREATE\s+(PROCEDURE|FUNCTION|TRIGGER)/i', $trimmedLine)) {
            if (!empty($currentPiece)) {
                $sqlPieces[] = $currentPiece;
                $currentPiece = '';
            }
            $inProcedure = true;
        }
        
        // Détecter la fin d'une instruction SQL normale
        if (!$inProcedure && substr($trimmedLine, -1) === ';') {
            $currentPiece .= $line . "\n";
            $sqlPieces[] = $currentPiece;
            $currentPiece = '';
            continue;
        }
        
        // Ajouter la ligne au morceau actuel
        $currentPiece .= $line . "\n";
    }
    
    // Exécuter chaque instruction SQL
    $success = true;
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    
    foreach ($sqlPieces as $piece) {
        $piece = trim($piece);
        if (empty($piece)) continue;
        
        try {
            $stmt = $db->prepare($piece);
            $stmt->execute();
            echo "<p style='color:green'>✓ Instruction SQL exécutée avec succès</p>";
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