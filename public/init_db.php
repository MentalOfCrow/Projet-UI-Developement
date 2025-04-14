<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Désactiver la limite de temps d'exécution
set_time_limit(300);

// Activer la fonction ignore_user_abort pour éviter les interruptions
ignore_user_abort(true);

/**
 * Initialise la base de données pour le jeu de dames
 * 
 * @param string $host Hôte de la base de données
 * @param string $user Nom d'utilisateur
 * @param string $password Mot de passe
 * @param string $dbname Nom de la base de données
 * @return array Résultat avec statut et message
 */
function initializeDatabase($host = "localhost", $user = "root", $password = "", $dbname = "checkers_game") {
    try {
        echo "<p>Connexion à la base de données...</p>";
        
        // Créer la connexion en activant les requêtes bufferisées
        $conn = new PDO("mysql:host=$host", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true // Active le buffer pour les requêtes
        ]);
        
        echo "<p>Connexion réussie!</p>";
        
        // Créer la base de données si elle n'existe pas
        $conn->exec("DROP DATABASE IF EXISTS $dbname");
        $conn->exec("CREATE DATABASE $dbname DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p>Base de données '$dbname' créée.</p>";
        
        // Sélectionner la base de données
        $conn->exec("USE $dbname");
        
        // Vérifier si le fichier SQL existe
        $sqlFilePath = __DIR__ . '/../backend/db/db.sql';
        if (!file_exists($sqlFilePath)) {
            throw new Exception("Fichier SQL introuvable à l'emplacement : $sqlFilePath");
        }
        
        // Lire le fichier SQL
        $sql = file_get_contents($sqlFilePath);
        
        // Diviser le fichier en requêtes individuelles
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        echo "<p>Exécution des requêtes SQL...</p>";
        
        // Exécuter chaque requête
        foreach ($queries as $query) {
            if (empty($query)) continue;
            
            try {
                $conn->exec($query);
            } catch (PDOException $e) {
                // Ignorer les erreurs de tables déjà existantes
                if (strpos($e->getMessage(), "already exists") === false) {
                    echo "<p style='color:red'>Erreur: " . $e->getMessage() . "</p>";
                }
            }
        }
        
        // Créer un utilisateur de test
        $username = "testuser";
        $email = "test@example.com";
        $password = password_hash("password123", PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password]);
            $stmt->closeCursor(); // Libérer la requête
            
            // Récupérer l'ID de l'utilisateur
            $userId = $conn->lastInsertId();
            
            // Initialiser les statistiques pour l'utilisateur
            $statsQuery = "INSERT INTO stats (user_id, games_played, games_won, games_lost, draws, win_streak, longest_win_streak, games_vs_bots, games_vs_players, bot_games_won, player_games_won, last_game) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL)";
            $statsStmt = $conn->prepare($statsQuery);
            $statsStmt->execute([$userId]);
            $statsStmt->closeCursor(); // Libérer la requête
            
            echo "<p style='color:green'>Utilisateur de test créé: '$username' avec mot de passe 'password123'</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Duplicate entry") === false) {
                echo "<p style='color:red'>Erreur lors de la création de l'utilisateur: " . $e->getMessage() . "</p>";
            } else {
                echo "<p style='color:orange'>L'utilisateur '$username' existe déjà.</p>";
            }
        }
        
        return [
            'success' => true,
            'message' => 'Base de données initialisée avec succès!'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

echo "<h1>Initialisation de la base de données</h1>";

// Exécuter la fonction d'initialisation
$result = initializeDatabase();

if ($result['success']) {
    echo "<h2 style='color:green'>" . $result['message'] . "</h2>";
    echo "<p><a href='/auth/login.php'>Se connecter</a> avec le nom d'utilisateur: 'testuser' et le mot de passe: 'password123'</p>";
} else {
    echo "<h2 style='color:red'>Erreur:</h2>";
    echo "<pre>" . $result['message'] . "</pre>";
}

echo "<p><a href='/'>Retour à l'accueil</a></p>";
?> 