<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';
require_once __DIR__ . '/../../backend/includes/session.php';

// Vérifier si l'utilisateur est connecté
if (!Session::isLoggedIn()) {
    echo "Vous devez être connecté pour accéder à cette page.";
    exit;
}

$user_id = Session::getUserId();
$db = Database::getInstance()->getConnection();

echo "<h1>Réparation des statistiques pour l'utilisateur ID: $user_id</h1>";

// 1. Vérifier si une table "stats" existe
$stmt = $db->query("SHOW TABLES LIKE 'stats'");
$stats_table_exists = $stmt->rowCount() > 0;

echo "<h2>Vérification de la structure de la base de données</h2>";
echo "Table 'stats' existe: " . ($stats_table_exists ? "Oui" : "Non") . "<br>";

// Créer la table stats si elle n'existe pas
if (!$stats_table_exists) {
    echo "Création de la table 'stats'...<br>";
    $db->exec("CREATE TABLE IF NOT EXISTS stats (
        user_id INT PRIMARY KEY,
        games_played INT DEFAULT 0 COMMENT 'Nombre total de parties jouées',
        games_won INT DEFAULT 0 COMMENT 'Nombre de parties gagnées',
        games_lost INT DEFAULT 0 COMMENT 'Nombre de parties perdues',
        last_game TIMESTAMP NULL COMMENT 'Date de la dernière partie',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Table 'stats' créée avec succès.<br>";
}

// 2. Vérifier si l'entrée de l'utilisateur existe dans la table stats
$stmt = $db->prepare("SELECT * FROM stats WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Vérification des statistiques actuelles</h2>";
if ($user_stats) {
    echo "Statistiques actuelles: <br>";
    echo "- Parties jouées: " . $user_stats['games_played'] . "<br>";
    echo "- Parties gagnées: " . $user_stats['games_won'] . "<br>";
    echo "- Parties perdues: " . $user_stats['games_lost'] . "<br>";
    echo "- Dernière partie: " . $user_stats['last_game'] . "<br>";
} else {
    echo "Aucune statistique trouvée pour cet utilisateur.<br>";
}

// 3. Calculer les statistiques correctes basées sur l'historique des parties
echo "<h2>Recalcul des statistiques à partir de l'historique</h2>";

$stmt = $db->prepare("SELECT 
    COUNT(*) as total_games,
    SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as victories,
    SUM(CASE WHEN winner_id IS NULL THEN 1 ELSE 0 END) as draws,
    SUM(CASE WHEN winner_id IS NOT NULL AND winner_id != ? THEN 1 ELSE 0 END) as defeats
    FROM games 
    WHERE (player1_id = ? OR player2_id = ?) 
    AND status = 'finished'");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$calculated_stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Statistiques calculées d'après l'historique: <br>";
echo "- Parties terminées: " . $calculated_stats['total_games'] . "<br>";
echo "- Victoires: " . $calculated_stats['victories'] . "<br>";
echo "- Défaites: " . $calculated_stats['defeats'] . "<br>";
echo "- Matchs nuls: " . $calculated_stats['draws'] . "<br>";

// 4. Liste des parties terminées
echo "<h2>Liste des parties terminées</h2>";
$stmt = $db->prepare("SELECT g.*, 
    u1.username as player1_name, 
    u2.username as player2_name 
    FROM games g
    JOIN users u1 ON g.player1_id = u1.id
    LEFT JOIN users u2 ON g.player2_id = u2.id
    WHERE (g.player1_id = ? OR g.player2_id = ?) 
    AND g.status = 'finished'
    ORDER BY g.updated_at DESC");
$stmt->execute([$user_id, $user_id]);

echo "<table border='1' cellpadding='5'>";
echo "<tr>
        <th>ID</th>
        <th>Joueur 1</th>
        <th>Joueur 2</th>
        <th>Statut</th>
        <th>Gagnant</th>
        <th>Date création</th>
        <th>Date mise à jour</th>
    </tr>";

while ($game = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $winner_name = "";
    if ($game['winner_id'] == $game['player1_id']) {
        $winner_name = $game['player1_name'];
    } elseif ($game['winner_id'] == $game['player2_id']) {
        $winner_name = $game['player2_name'];
    } elseif ($game['winner_id'] === null) {
        $winner_name = "Match nul";
    }
    
    echo "<tr>";
    echo "<td>" . $game['id'] . "</td>";
    echo "<td>" . $game['player1_name'] . " (ID: " . $game['player1_id'] . ")</td>";
    echo "<td>" . ($game['player2_id'] == 0 ? 'IA' : $game['player2_name'] . " (ID: " . $game['player2_id'] . ")") . "</td>";
    echo "<td>" . $game['status'] . "</td>";
    echo "<td>" . ($game['winner_id'] !== null ? $winner_name . " (ID: " . $game['winner_id'] . ")" : "Match nul") . "</td>";
    echo "<td>" . $game['created_at'] . "</td>";
    echo "<td>" . $game['updated_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 5. Mise à jour des statistiques
echo "<h2>Mise à jour des statistiques</h2>";

// Exécuter l'action de mise à jour si demandée
if (isset($_GET['action']) && $_GET['action'] === 'update') {
    if ($user_stats) {
        // Mettre à jour les statistiques existantes
        $stmt = $db->prepare("UPDATE stats SET 
            games_played = ?, 
            games_won = ?, 
            games_lost = ?, 
            last_game = NOW() 
            WHERE user_id = ?");
        $stmt->execute([
            $calculated_stats['total_games'],
            $calculated_stats['victories'],
            $calculated_stats['defeats'],
            $user_id
        ]);
        echo "Statistiques mises à jour avec succès!";
    } else {
        // Créer une nouvelle entrée dans la table stats
        $stmt = $db->prepare("INSERT INTO stats (user_id, games_played, games_won, games_lost, last_game) 
            VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id,
            $calculated_stats['total_games'],
            $calculated_stats['victories'],
            $calculated_stats['defeats']
        ]);
        echo "Statistiques créées avec succès!";
    }
    
    echo "<br><a href='debug_stats.php'>Rafraîchir la page</a>";
} else {
    echo "<a href='debug_stats.php?action=update' class='button'>Mettre à jour les statistiques</a>";
}

// 6. Vérifier les triggers
echo "<h2>Vérification des triggers</h2>";
$stmt = $db->query("SHOW TRIGGERS");
$triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($triggers) > 0) {
    echo "Triggers trouvés:<br>";
    foreach ($triggers as $trigger) {
        echo "- " . $trigger['Trigger'] . " (" . $trigger['Table'] . ")<br>";
    }
} else {
    echo "Aucun trigger trouvé dans la base de données.<br>";
    echo "Recréation du trigger de mise à jour des statistiques...<br>";
    
    // Recréer le trigger
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
    
    echo "Trigger recréé avec succès.<br>";
}
?> 