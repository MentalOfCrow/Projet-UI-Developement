<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';

echo "<h1>Mise à jour de la structure de la table 'moves'</h1>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Vérifier d'abord si les colonnes 'from_position' et 'to_position' existent
    $checkQuery = "SELECT COUNT(*) as count FROM information_schema.columns 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'moves' 
                  AND column_name IN ('from_position', 'to_position')";
    $stmt = $db->query($checkQuery);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "<p>Les colonnes 'from_position' et/ou 'to_position' existent. Mise à jour nécessaire.</p>";
        
        // Récupérer les données existantes
        $moveData = [];
        $dataQuery = "SELECT id, game_id, user_id, from_position, to_position, captured FROM moves";
        $dataStmt = $db->query($dataQuery);
        while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
            $moveData[] = $row;
        }
        
        echo "<p>Données récupérées : " . count($moveData) . " mouvements</p>";
        
        // Modifier la structure de la table
        $alterQuery = "ALTER TABLE moves 
                       ADD COLUMN from_row INT DEFAULT 0,
                       ADD COLUMN from_col INT DEFAULT 0,
                       ADD COLUMN to_row INT DEFAULT 0,
                       ADD COLUMN to_col INT DEFAULT 0";
        
        echo "<p>Exécution de la requête : " . $alterQuery . "</p>";
        $db->exec($alterQuery);
        
        // Mettre à jour les données
        echo "<p>Mise à jour des données...</p>";
        $updateStmt = $db->prepare("UPDATE moves SET 
                                   from_row = :from_row, 
                                   from_col = :from_col, 
                                   to_row = :to_row, 
                                   to_col = :to_col 
                                   WHERE id = :id");
        
        foreach ($moveData as $move) {
            if (!empty($move['from_position']) && !empty($move['to_position'])) {
                list($fromRow, $fromCol) = explode(',', $move['from_position']);
                list($toRow, $toCol) = explode(',', $move['to_position']);
                
                $updateStmt->bindParam(':from_row', $fromRow, PDO::PARAM_INT);
                $updateStmt->bindParam(':from_col', $fromCol, PDO::PARAM_INT);
                $updateStmt->bindParam(':to_row', $toRow, PDO::PARAM_INT);
                $updateStmt->bindParam(':to_col', $toCol, PDO::PARAM_INT);
                $updateStmt->bindParam(':id', $move['id'], PDO::PARAM_INT);
                $updateStmt->execute();
            }
        }
        
        // Supprimer les anciennes colonnes
        $dropQuery = "ALTER TABLE moves 
                     DROP COLUMN from_position,
                     DROP COLUMN to_position";
        
        echo "<p>Exécution de la requête : " . $dropQuery . "</p>";
        $db->exec($dropQuery);
        
        echo "<p style='color: green;'>Mise à jour terminée avec succès !</p>";
    } else {
        echo "<p style='color: green;'>La table 'moves' est déjà à jour. Aucune modification nécessaire.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Erreur lors de la mise à jour :</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}

echo "<p><a href='/'>Retour à l'accueil</a></p>";
?> 