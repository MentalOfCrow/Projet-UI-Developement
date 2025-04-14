<?php
// Set error display for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../db/Database.php';

echo "<h1>Resetting Game Statistics</h1>";

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    // Reset stats table
    $statsSql = "TRUNCATE TABLE stats";
    $db->exec($statsSql);
    echo "<p style='color:green'>✓ Stats table cleared</p>";
    
    // Reset games table (this will cascade to moves table if you have foreign key constraints)
    $gamesSql = "TRUNCATE TABLE games";
    $db->exec($gamesSql);
    echo "<p style='color:green'>✓ Games table cleared</p>";
    
    // Reset moves table explicitly (in case there's no cascade)
    $movesSql = "TRUNCATE TABLE moves";
    $db->exec($movesSql);
    echo "<p style='color:green'>✓ Moves table cleared</p>";
    
    // Reset game_analysis table if it exists
    try {
        $analysisSql = "TRUNCATE TABLE game_analysis";
        $db->exec($analysisSql);
        echo "<p style='color:green'>✓ Game analysis table cleared</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>⚠️ Note: Game analysis table may not exist or couldn't be truncated</p>";
    }
    
    // Initialize stats for each user
    $usersSql = "SELECT id FROM users";
    $usersStmt = $db->query($usersSql);
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $userId = $user['id'];
        
        // Check if stats record exists
        $checkSql = "SELECT COUNT(*) FROM stats WHERE user_id = ?";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$userId]);
        $exists = $checkStmt->fetchColumn();
        
        if ($exists) {
            // Update existing record
            $updateSql = "UPDATE stats SET 
                games_played = 0, 
                games_won = 0, 
                games_lost = 0, 
                draws = 0,
                win_streak = 0,
                longest_win_streak = 0,
                games_vs_bots = 0,
                games_vs_players = 0,
                bot_games_won = 0,
                player_games_won = 0,
                updated_at = NOW()
                WHERE user_id = ?";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([$userId]);
        } else {
            // Insert new record
            $insertSql = "INSERT INTO stats (
                user_id, games_played, games_won, games_lost, draws, 
                win_streak, longest_win_streak, games_vs_bots, games_vs_players,
                bot_games_won, player_games_won, created_at, updated_at
            ) VALUES (
                ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NOW(), NOW()
            )";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([$userId]);
        }
    }
    
    echo "<p style='color:green'>✓ Reset statistics for " . count($users) . " users</p>";
    
    // Commit transaction
    $db->commit();
    
    echo "<h2 style='color:green'>All game statistics have been reset successfully!</h2>";
    echo "<p>You can now <a href='/'>return to the game</a>.</p>";
    
} catch (PDOException $e) {
    // Rollback transaction in case of error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "<h2 style='color:red'>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 