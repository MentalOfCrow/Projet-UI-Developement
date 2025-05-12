<?php
/**
 * Script d'installation du système de classement (leaderboard)
 * Ce script installe la table de classement et les procédures stockées associées
 */

// Démarrer la sortie tampon
ob_start();

// Activer l'affichage des erreurs en développement
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inclure les fichiers de configuration
require_once __DIR__ . '/backend/includes/config.php';
require_once __DIR__ . '/backend/db/Database.php';

// Fonction pour afficher les messages
function displayMessage($message, $type = 'info') {
    $colors = [
        'success' => '#28a745',
        'danger' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#17a2b8'
    ];
    
    $color = $colors[$type] ?? $colors['info'];
    
    echo "<div style=\"margin: 10px 0; padding: 10px; background-color: {$color}; color: white; border-radius: 5px;\">";
    echo $message;
    echo "</div>";
}

// En-tête HTML
echo "<!DOCTYPE html>
<html lang=\"fr\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Installation du système de classement</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2 { color: #4338ca; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .step { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Installation du système de classement</h1>
    <div class=\"step\">
        <h2>Exécution du script SQL</h2>";

try {
    // Obtenir une connexion à la base de données
    $db = Database::getInstance()->getConnection();
    
    // Lire le contenu du fichier SQL
    $sqlFile = __DIR__ . '/backend/db/update_leaderboard.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Le fichier SQL n'existe pas: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        throw new Exception("Impossible de lire le fichier SQL: {$sqlFile}");
    }
    
    displayMessage("Fichier SQL chargé avec succès.", 'success');
    
    // Diviser le script SQL en instructions individuelles
    // Nous devons diviser correctement pour gérer les procédures stockées et triggers
    $delimiter = '//';
    $sqlPieces = array();
    
    // Diviser le script en fonction du délimiteur
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
            $currentPiece .= ";\n"; // Remplacer le délimiteur par un point-virgule
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
    
    // Ajouter le dernier morceau s'il existe
    if (!empty($currentPiece)) {
        $sqlPieces[] = $currentPiece;
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
            displayMessage("Instruction SQL exécutée avec succès: " . substr($piece, 0, 50) . "...", 'success');
        } catch (PDOException $e) {
            $success = false;
            displayMessage("Erreur lors de l'exécution de l'instruction SQL: " . $e->getMessage() . "<br>Instruction: " . $piece, 'danger');
        }
    }
    
    if ($success) {
        displayMessage("✅ Installation du système de classement terminée avec succès!", 'success');
    } else {
        displayMessage("⚠️ Installation terminée avec des avertissements. Vérifiez les messages ci-dessus.", 'warning');
    }
    
} catch (Exception $e) {
    displayMessage("❌ Erreur: " . $e->getMessage(), 'danger');
}

echo "    </div>
    <div class=\"step\">
        <h2>Prochaines étapes</h2>
        <p>Le système de classement est maintenant installé. Vous pouvez accéder au classement via la page <a href=\"/leaderboard.php\">Classement</a>.</p>
        <p>Le classement est automatiquement mis à jour après chaque partie.</p>
    </div>
</body>
</html>"; 