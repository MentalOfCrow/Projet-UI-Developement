<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/backend/includes/config.php';
require_once __DIR__ . '/backend/db/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Vérifier si privacy_setting existe
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'privacy_setting'");
    $hasPrivacySetting = $stmt->rowCount() > 0;
    
    // Vérifier si privacy_level existe
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'privacy_level'");
    $hasPrivacyLevel = $stmt->rowCount() > 0;
    
    echo "<h2>État actuel</h2>";
    echo "Colonne privacy_setting: " . ($hasPrivacySetting ? "Existe" : "N'existe pas") . "<br>";
    echo "Colonne privacy_level: " . ($hasPrivacyLevel ? "Existe" : "N'existe pas") . "<br>";
    
    echo "<h2>Actions à effectuer</h2>";
    
    // Scénario 1: Si privacy_level existe mais pas privacy_setting
    if ($hasPrivacyLevel && !$hasPrivacySetting) {
        echo "Création de privacy_setting à partir de privacy_level...<br>";
        
        // Créer la colonne privacy_setting
        $db->exec("ALTER TABLE users ADD COLUMN privacy_setting ENUM('public', 'friends', 'private') DEFAULT 'friends'");
        
        // Copier les valeurs de privacy_level vers privacy_setting
        $db->exec("UPDATE users SET privacy_setting = privacy_level");
        
        echo "Colonne privacy_setting créée et valeurs copiées.<br>";
    }
    // Scénario 2: Si aucune des deux n'existe
    else if (!$hasPrivacyLevel && !$hasPrivacySetting) {
        echo "Création de la colonne privacy_setting...<br>";
        
        // Créer la colonne privacy_setting
        $db->exec("ALTER TABLE users ADD COLUMN privacy_setting ENUM('public', 'friends', 'private') DEFAULT 'friends'");
        
        echo "Colonne privacy_setting créée avec la valeur par défaut 'friends'.<br>";
    }
    // Scénario 3: Si privacy_setting existe déjà
    else {
        echo "La colonne privacy_setting existe déjà, aucune action nécessaire.<br>";
    }
    
    // Vérification finale
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'privacy_setting'");
    echo "<h2>Vérification finale</h2>";
    echo "Colonne privacy_setting: " . ($stmt->rowCount() > 0 ? "Existe" : "N'existe toujours pas") . "<br>";
    
    echo "<h2>Terminé</h2>";
    echo "<a href='profile.php'>Retourner à la page de profil</a>";
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?> 