<?php
// Vérifier si le script est déjà en cours d'exécution via un autre fichier
if (defined('RUNNING_INIT')) {
    return; // Éviter la double exécution
}
define('RUNNING_INIT', true);

// Set error display for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Désactiver la limite de temps d'exécution
set_time_limit(300);

// Ignorer les abandons de connexion
ignore_user_abort(true);

// Database connection information
$host = "localhost";
$user = "root";
$password = "";
$dbname = "checkers_game";

// Éviter de sortir du script si déjà inclus
if (!isset($included_script)) {
    echo "<h1>Initializing Database for Checkers Game</h1>";
}

try {
    // First connect without selecting a database
    $conn = new PDO("mysql:host=$host", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true // Active les requêtes bufferisées
    ]);
    echo "<p>Connected to MySQL server successfully!</p>";
    
    // Create database if it doesn't exist
    $conn->exec("CREATE DATABASE IF NOT EXISTS $dbname DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>Database '$dbname' created or already exists.</p>";
    
    // Select the database
    $conn->exec("USE $dbname");
    echo "<p>Using database '$dbname'.</p>";
    
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/../db/db.sql');
    
    // Drop existing tables to ensure clean installation
    echo "<p>Dropping existing tables if they exist...</p>";
    $dropTables = [
        "DROP TABLE IF EXISTS leaderboard;",
        "DROP TABLE IF EXISTS notifications;",
        "DROP TABLE IF EXISTS friends;",
        "DROP TABLE IF EXISTS moves;",
        "DROP TABLE IF EXISTS games;",
        "DROP TABLE IF EXISTS stats;",
        "DROP TABLE IF EXISTS queue;",
        "DROP TABLE IF EXISTS users;"
    ];
    
    foreach ($dropTables as $dropQuery) {
        try {
            $conn->exec($dropQuery);
        } catch (PDOException $e) {
            echo "<p style='color:orange'>⚠️ Note: " . $e->getMessage() . "</p>";
        }
    }
    
    // Split by delimiters to handle stored procedures and triggers
    $queries = explode("DELIMITER //", $sql);
    
    // Execute first part (tables, etc.)
    $tableQueries = explode(';', $queries[0]);
    foreach ($tableQueries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        try {
            $conn->exec($query);
            echo "<p style='color:green'>✓ Successfully executed: " . substr($query, 0, 50) . "...</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "already exists") !== false) {
                echo "<p style='color:orange'>⚠️ Note: " . $e->getMessage() . "</p>";
            } else {
                echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Handle stored procedures and triggers
    for ($i = 1; $i < count($queries); $i++) {
        if (empty(trim($queries[$i]))) continue;
        
        // Extract the procedure/trigger definition
        $parts = explode("DELIMITER ;", $queries[$i]);
        $definition = trim($parts[0]);
        
        try {
            $conn->exec($definition);
            echo "<p style='color:green'>✓ Successfully created procedure/trigger</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "already exists") !== false) {
                echo "<p style='color:orange'>⚠️ Note: " . $e->getMessage() . "</p>";
            } else {
                echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Create a test user
    $username = "testuser";
    $email = "test@example.com";
    $password = password_hash("password123", PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $password]);
        echo "<p style='color:green'>✓ Created test user: '$username' with password 'password123'</p>";
        
        // Initialize stats for the test user
        $userId = $conn->lastInsertId();
        $statsQuery = "INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, win_streak, longest_win_streak, games_vs_bots, games_vs_players, bot_games_won, player_games_won, last_game) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL)";
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->execute([$userId]);
        echo "<p style='color:green'>✓ Initialized stats for test user</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Duplicate entry") !== false) {
            echo "<p style='color:orange'>⚠️ Test user already exists.</p>";
        } else {
            echo "<p style='color:red'>✗ Error creating test user: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2 style='color:green'>Database initialization complete!</h2>";
    echo "<p>You can now <a href='/auth/login.php'>log in</a> with username: '$username' and password: 'password123'</p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 