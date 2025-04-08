<?php
/**
 * Script d'initialisation des statistiques pour tous les utilisateurs
 * 
 * Ce script peut être exécuté soit en ligne de commande soit via une interface web.
 * Il va:
 * 1. Vérifier/créer la table stats si elle n'existe pas
 * 2. Récupérer tous les utilisateurs
 * 3. Pour chaque utilisateur, calculer les statistiques des parties terminées
 * 4. Mettre à jour la table stats
 * 5. Créer un trigger pour automatiser les mises à jour futures
 */

// Si ce script est inclus dans un autre fichier, ne pas redéclarer ces paramètres
if (!defined('INIT_STATS_INCLUDED')) {
    define('INIT_STATS_INCLUDED', true);
    
    // Activer l'affichage des erreurs en développement
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Enregistrer toutes les erreurs dans un fichier log
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
    
    // Inclure les fichiers nécessaires
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../db/Database.php';
}

// Déterminer si le script est exécuté en ligne de commande
$isCLI = (php_sapi_name() === 'cli');

/**
 * Fonction pour afficher les messages selon le contexte d'exécution
 */
function output($message, $type = 'info') {
    global $isCLI;
    
    if ($isCLI) {
        $prefix = match($type) {
            'error' => "\033[31m[ERREUR]\033[0m ",
            'success' => "\033[32m[SUCCÈS]\033[0m ",
            'warning' => "\033[33m[ATTENTION]\033[0m ",
            default => "\033[36m[INFO]\033[0m ",
        };
        echo $prefix . $message . PHP_EOL;
    } else {
        // Pour l'usage via l'API, nous retournons simplement les informations
        // Le message sera affiché par le script appelant
        error_log("[{$type}] {$message}");
    }
}

/**
 * Fonction principale qui initialise les statistiques de tous les utilisateurs
 * 
 * @param bool $resetExisting Réinitialiser les statistiques existantes avant de recalculer
 * @return bool Succès de l'opération
 */
function initAllStats($resetExisting = false) {
    global $isCLI;
    
    try {
        // Se connecter à la base de données
        $db = Database::getInstance()->getConnection();
        
        // 1. Vérifier si la table stats existe, sinon la créer
        output("Vérification de la table stats...");
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS stats (
                user_id INT PRIMARY KEY,
                games_played INT DEFAULT 0,
                games_won INT DEFAULT 0,
                games_lost INT DEFAULT 0,
                last_game TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            output("Table stats vérifiée/créée avec succès.", "success");
        } catch (PDOException $e) {
            output("Erreur lors de la création de la table stats: " . $e->getMessage(), "error");
            return false;
        }
        
        // 2. Réinitialiser les statistiques existantes si demandé
        if ($resetExisting) {
            try {
                $db->exec("TRUNCATE TABLE stats");
                output("Réinitialisation des statistiques effectuée.", "success");
            } catch (PDOException $e) {
                output("Erreur lors de la réinitialisation des statistiques: " . $e->getMessage(), "error");
                return false;
            }
        }
        
        // 3. Récupérer tous les utilisateurs
        output("Récupération de la liste des utilisateurs...");
        $users = $db->query("SELECT id, username FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $totalUsers = count($users);
        $updatedUsers = 0;
        
        output("Traitement de {$totalUsers} utilisateurs...");
        
        // 4. Pour chaque utilisateur, calculer et mettre à jour les statistiques
        foreach ($users as $user) {
            $user_id = $user['id'];
            $username = $user['username'];
            
            // Récupérer les parties terminées de cet utilisateur
            $gamesQuery = "SELECT 
                            id, 
                            player1_id, 
                            player2_id, 
                            winner_id,
                            updated_at
                        FROM games 
                        WHERE (player1_id = ? OR player2_id = ?) 
                        AND status = 'finished'";
            
            $gamesStmt = $db->prepare($gamesQuery);
            $gamesStmt->execute([$user_id, $user_id]);
            
            $games = $gamesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $gamesPlayed = count($games);
            $gamesWon = 0;
            $gamesLost = 0;
            $lastGame = null;
            
            foreach ($games as $game) {
                // Mise à jour de la date de dernière partie
                if ($lastGame === null || strtotime($game['updated_at']) > strtotime($lastGame)) {
                    $lastGame = $game['updated_at'];
                }
                
                // Vérifier si le joueur a gagné
                if ($game['winner_id'] == $user_id) {
                    $gamesWon++;
                } 
                // Vérifier si le joueur a perdu (seulement si un gagnant est défini)
                else if ($game['winner_id'] !== null && $game['winner_id'] != $user_id) {
                    $gamesLost++;
                }
                // Si winner_id est null et c'est contre un bot, c'est une défaite
                else if ($game['winner_id'] === null && 
                        (($game['player1_id'] == $user_id && $game['player2_id'] == 0) || 
                         ($game['player2_id'] == $user_id && $game['player1_id'] == 0))) {
                    $gamesLost++;
                }
            }
            
            if ($gamesPlayed > 0) {
                // Mettre à jour ou insérer les statistiques
                $updateQuery = "INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game) 
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                games_played = ?, 
                                games_won = ?, 
                                games_lost = ?, 
                                last_game = ?";
                
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute([
                    $user_id, $gamesPlayed, $gamesWon, $gamesLost, $lastGame,
                    $gamesPlayed, $gamesWon, $gamesLost, $lastGame
                ]);
                
                $updatedUsers++;
                
                if ($isCLI && $totalUsers > 10) {
                    // Afficher un indicateur de progression toutes les 10 utilisateurs en mode CLI
                    if ($updatedUsers % 10 === 0 || $updatedUsers === $totalUsers) {
                        $percent = round(($updatedUsers / $totalUsers) * 100);
                        echo "\rProgression: {$updatedUsers}/{$totalUsers} ({$percent}%) utilisateurs traités";
                        if ($updatedUsers === $totalUsers) {
                            echo PHP_EOL;
                        }
                    }
                }
            }
        }
        
        // 5. Vérifier que le trigger existe
        $triggers = $db->query("SHOW TRIGGERS LIKE 'after_game_finished'")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($triggers) === 0) {
            output("Création du trigger pour les mises à jour automatiques...");
            
            // Supprimer le trigger s'il existe pour éviter les erreurs
            $db->exec("DROP TRIGGER IF EXISTS after_game_finished");
            
            // Créer le trigger
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
            END
            ");
            
            output("Trigger after_game_finished créé avec succès.", "success");
        } else {
            output("Le trigger after_game_finished existe déjà.", "info");
        }
        
        output("Initialisation des statistiques terminée avec succès.", "success");
        output("Total: {$totalUsers} utilisateurs, {$updatedUsers} mis à jour.", "success");
        
        return true;
        
    } catch (Exception $e) {
        output("Erreur générale: " . $e->getMessage(), "error");
        return false;
    }
}

// Si le script est exécuté directement (pas inclus dans un autre fichier)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    // Traitement des arguments en ligne de commande
    if ($isCLI) {
        $resetExisting = false;
        $showHelp = false;
        
        // Analyser les arguments
        $options = getopt("hr", ["help", "reset"]);
        
        if (isset($options['h']) || isset($options['help'])) {
            $showHelp = true;
        }
        
        if (isset($options['r']) || isset($options['reset'])) {
            $resetExisting = true;
        }
        
        if ($showHelp) {
            echo "Usage: php " . basename(__FILE__) . " [options]\n";
            echo "Options:\n";
            echo "  -h, --help   Afficher cette aide\n";
            echo "  -r, --reset  Réinitialiser toutes les statistiques existantes avant de recalculer\n";
            exit(0);
        }
        
        // Exécuter la fonction principale
        $success = initAllStats($resetExisting);
        exit($success ? 0 : 1);
    } 
    // Si accédé via le navigateur, afficher une interface
    else {
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Initialisation des statistiques</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; line-height: 1.6; }
                .container { max-width: 800px; margin: 0 auto; background: #f5f5f5; padding: 20px; border-radius: 5px; }
                h1 { color: #333; }
                .success { color: green; }
                .error { color: red; }
                .info { color: blue; }
                .btn { display: inline-block; padding: 8px 16px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Initialisation des statistiques</h1>';
        
        $resetExisting = isset($_GET['reset']) && $_GET['reset'] === '1';
        
        echo '<p>Exécution de l\'initialisation des statistiques ' . ($resetExisting ? 'avec réinitialisation' : 'sans réinitialisation') . '...</p>';
        
        // Démarrer la capture de la sortie
        ob_start();
        $success = initAllStats($resetExisting);
        $output = ob_get_clean();
        
        // Afficher les résultats
        echo '<h2>' . ($success ? '<span class="success">Succès</span>' : '<span class="error">Erreur</span>') . '</h2>';
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
        
        echo '<p><a href="/" class="btn">Retour à l\'accueil</a></p>';
        echo '</div></body></html>';
    }
}
?> 