<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';

echo "<h1>Mise à jour de la base de données</h1>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Lire le fichier SQL
    $sql = file_get_contents(__DIR__ . '/../db/update_db.sql');
    
    // Diviser le fichier en requêtes individuelles
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    // Exécuter chaque requête
    foreach ($queries as $query) {
        if (empty($query)) continue;
        
        // Gérer les délimiteurs pour les triggers
        if (strpos($query, 'DELIMITER //') !== false) {
            $parts = explode('//', $query);
            foreach ($parts as $part) {
                if (trim($part) === 'DELIMITER' || empty(trim($part))) continue;
                try {
                    $db->exec(trim($part));
                    echo "<div style='color: green;'>✓ Trigger/Procédure créé avec succès</div>";
                } catch (PDOException $e) {
                    echo "<div style='color: orange;'>⚠ Note: " . $e->getMessage() . "</div>";
                }
            }
        } else {
            try {
                $db->exec($query);
                echo "<div style='color: green;'>✓ Requête exécutée avec succès: " . substr($query, 0, 100) . "...</div>";
            } catch (PDOException $e) {
                // Si l'erreur concerne une colonne ou table qui existe déjà, ce n'est pas grave
                if (strpos($e->getMessage(), "Duplicate") !== false || 
                    strpos($e->getMessage(), "already exists") !== false) {
                    echo "<div style='color: orange;'>⚠ Note: " . $e->getMessage() . "</div>";
                } else {
                    echo "<div style='color: red;'>✗ Erreur: " . $e->getMessage() . "</div>";
                }
            }
        }
    }
    
    echo "<h2 style='color: green;'>Mise à jour terminée avec succès!</h2>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Erreur lors de la mise à jour:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
} 