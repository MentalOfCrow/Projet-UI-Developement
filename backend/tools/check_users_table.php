<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/backend/includes/config.php';
require_once __DIR__ . '/backend/db/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Afficher les informations sur la table users
    echo "<h2>Structure de la table users</h2>";
    $stmt = $db->query("DESCRIBE users");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
    
    // Vérifier si privacy_level existe
    echo "<h2>Vérification de la colonne privacy_level</h2>";
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'privacy_level'");
    if ($stmt->rowCount() > 0) {
        echo "La colonne 'privacy_level' existe.";
    } else {
        echo "La colonne 'privacy_level' n'existe PAS.";
    }
    
    // Vérifier si privacy_setting existe
    echo "<h2>Vérification de la colonne privacy_setting</h2>";
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'privacy_setting'");
    if ($stmt->rowCount() > 0) {
        echo "La colonne 'privacy_setting' existe.";
    } else {
        echo "La colonne 'privacy_setting' n'existe PAS.";
    }
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?> 