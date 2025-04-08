<?php
// Script pour initialiser les statistiques de tous les utilisateurs
// Ce script doit être exécuté une seule fois pour configurer correctement les statistiques

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Charger les dépendances
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';

// Se connecter à la base de données
$db = Database::getInstance()->getConnection();

echo "Initialisation des statistiques de tous les utilisateurs...\n\n";

// 1. S'assurer que la table stats existe
try {
    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        user_id INT PRIMARY KEY,
        games_played INT DEFAULT 0 COMMENT 'Nombre total de parties jouées',
        games_won INT DEFAULT 0 COMMENT 'Nombre de parties gagnées',
        games_lost INT DEFAULT 0 COMMENT 'Nombre de parties perdues',
        last_game TIMESTAMP NULL COMMENT 'Date de la dernière partie',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table stats vérifiée/créée avec succès.\n";
} catch (PDOException $e) {
    echo "Erreur lors de la création de la table stats: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Récupérer tous les utilisateurs
try {
    $users = $db->query("SELECT id, username FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "Trouvé " . count($users) . " utilisateurs.\n\n";
} catch (PDOException $e) {
    echo "Erreur lors de la récupération des utilisateurs: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Pour chaque utilisateur, calculer et mettre à jour les statistiques
foreach ($users as $user) {
    $user_id = $user['id'];
    $username = $user['username'];
    
    echo "Traitement des statistiques pour {$username} (ID: {$user_id})...\n";
    
    try {
        // Récupérer les statistiques de cet utilisateur
        $stmt = $db->prepare("SELECT 
            COUNT(*) as total_games,
            SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as victories,
            SUM(CASE WHEN winner_id IS NULL THEN 1 ELSE 0 END) as draws,
            SUM(CASE WHEN winner_id IS NOT NULL AND winner_id != ? THEN 1 ELSE 0 END) as defeats
            FROM games 
            WHERE (player1_id = ? OR player2_id = ?) 
            AND status = 'finished'");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_games = $stats['total_games'] ?? 0;
        $victories = $stats['victories'] ?? 0;
        $defeats = $stats['defeats'] ?? 0;
        $draws = $stats['draws'] ?? 0;
        
        echo "  - Parties jouées: {$total_games}\n";
        echo "  - Victoires: {$victories}\n";
        echo "  - Défaites: {$defeats}\n";
        echo "  - Matchs nuls: {$draws}\n";
        
        // Mettre à jour ou insérer les statistiques
        $stmt = $db->prepare("INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            games_played = ?,
            games_won = ?,
            games_lost = ?,
            last_game = NOW()");
        $stmt->execute([$user_id, $total_games, $victories, $defeats, $total_games, $victories, $defeats]);
        
        echo "  - Statistiques mises à jour avec succès.\n";
    } catch (PDOException $e) {
        echo "  - Erreur lors de la mise à jour des statistiques: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 4. Vérifier que le trigger existe
try {
    $triggers = $db->query("SHOW TRIGGERS LIKE 'after_game_finished'")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($triggers) === 0) {
        echo "Le trigger after_game_finished n'existe pas. Création...\n";
        
        $db->exec("
        DROP TRIGGER IF EXISTS after_game_finished;
        ");
        
        $db->exec("
        CREATE TRIGGER after_game_finished
        AFTER UPDATE ON games
        FOR EACH ROW
        BEGIN
            IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
                -- Mise à jour des statistiques du joueur 1
                INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                VALUES (NEW.player1_id, 1, IF(NEW.winner_id = NEW.player1_id, 1, 0), IF(NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL, 1, 0), NOW())
                ON DUPLICATE KEY UPDATE
                    games_played = games_played + 1,
                    games_won = games_won + IF(NEW.winner_id = NEW.player1_id, 1, 0),
                    games_lost = games_lost + IF(NEW.winner_id != NEW.player1_id AND NEW.winner_id IS NOT NULL, 1, 0),
                    last_game = NOW();
                
                -- Mise à jour des statistiques du joueur 2 (seulement s'il n'est pas un bot)
                IF NEW.player2_id != 0 THEN
                    INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game)
                    VALUES (NEW.player2_id, 1, IF(NEW.winner_id = NEW.player2_id, 1, 0), IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0), NOW())
                    ON DUPLICATE KEY UPDATE
                        games_played = games_played + 1,
                        games_won = games_won + IF(NEW.winner_id = NEW.player2_id, 1, 0),
                        games_lost = games_lost + IF(NEW.winner_id != NEW.player2_id AND NEW.winner_id IS NOT NULL, 1, 0),
                        last_game = NOW();
                END IF;
            END IF;
        END;
        ");
        
        echo "Trigger créé avec succès.\n";
    } else {
        echo "Le trigger after_game_finished existe déjà.\n";
    }
} catch (PDOException $e) {
    echo "Erreur lors de la vérification/création du trigger: " . $e->getMessage() . "\n";
}

echo "\nInitialisation des statistiques terminée avec succès.\n";
?> 