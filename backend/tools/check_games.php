<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Charger les dépendances
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';

// Se connecter à la base de données
$db = Database::getInstance()->getConnection();

// Récupérer le nombre de parties par statut
echo "<h2>Nombre de parties par statut :</h2>";
$stmt = $db->query("SELECT status, COUNT(*) as count FROM games GROUP BY status");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Statut</th><th>Nombre</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Récupérer toutes les parties
echo "<h2>Liste des 20 dernières parties dans la base de données :</h2>";
$stmt = $db->query("SELECT g.*, 
                    u1.username as player1_name, 
                    u2.username as player2_name 
                    FROM games g
                    LEFT JOIN users u1 ON g.player1_id = u1.id
                    LEFT JOIN users u2 ON g.player2_id = u2.id
                    ORDER BY g.updated_at DESC 
                    LIMIT 20");

echo "<table border='1' cellpadding='5'>";
echo "<tr>
        <th>ID</th>
        <th>Joueur 1</th>
        <th>Joueur 2</th>
        <th>Statut</th>
        <th>Joueur actuel</th>
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
        $winner_name = "-";
    }
    
    echo "<tr>";
    echo "<td>" . $game['id'] . "</td>";
    echo "<td>" . $game['player1_name'] . " (ID: " . $game['player1_id'] . ")</td>";
    echo "<td>" . ($game['player2_id'] == 0 ? 'IA' : ($game['player2_name'] . " (ID: " . $game['player2_id'] . ")")) . "</td>";
    echo "<td>" . $game['status'] . "</td>";
    echo "<td>" . $game['current_player'] . "</td>";
    echo "<td>" . ($game['winner_id'] !== null ? $winner_name . " (ID: " . $game['winner_id'] . ")" : "-") . "</td>";
    echo "<td>" . $game['created_at'] . "</td>";
    echo "<td>" . $game['updated_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Structures des tables
echo "<h2>Structure de la table 'games'</h2>";
$stmt = $db->query("DESCRIBE games");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier les permissions de l'utilisateur actuel
echo "<h2>Si vous êtes connecté, vérifiez vos parties en cours/terminées :</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Inconnu';
    
    echo "<p>Utilisateur connecté : ID " . $user_id . " (" . $username . ")</p>";
    
    // Parties en cours
    echo "<h3>Parties en cours :</h3>";
    $stmt = $db->prepare("SELECT g.*, 
                          u1.username as player1_name, 
                          u2.username as player2_name 
                          FROM games g
                          LEFT JOIN users u1 ON g.player1_id = u1.id
                          LEFT JOIN users u2 ON g.player2_id = u2.id
                          WHERE (g.player1_id = ? OR g.player2_id = ?) 
                          AND g.status = 'in_progress'
                          ORDER BY g.updated_at DESC");
    $stmt->execute([$user_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Adversaire</th><th>Date de début</th><th>Dernier mouvement</th></tr>";
        
        while ($game = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $opponent = ($game['player1_id'] == $user_id) ? 
                ($game['player2_id'] == 0 ? 'IA' : $game['player2_name']) : 
                $game['player1_name'];
            
            echo "<tr>";
            echo "<td>" . $game['id'] . "</td>";
            echo "<td>" . $opponent . "</td>";
            echo "<td>" . $game['created_at'] . "</td>";
            echo "<td>" . $game['updated_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Aucune partie en cours.</p>";
    }
    
    // Parties terminées
    echo "<h3>Parties terminées :</h3>";
    $stmt = $db->prepare("SELECT g.*, 
                          u1.username as player1_name, 
                          u2.username as player2_name 
                          FROM games g
                          LEFT JOIN users u1 ON g.player1_id = u1.id
                          LEFT JOIN users u2 ON g.player2_id = u2.id
                          WHERE (g.player1_id = ? OR g.player2_id = ?) 
                          AND g.status = 'finished'
                          ORDER BY g.updated_at DESC");
    $stmt->execute([$user_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Adversaire</th><th>Résultat</th><th>Date de fin</th></tr>";
        
        while ($game = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $opponent = ($game['player1_id'] == $user_id) ? 
                ($game['player2_id'] == 0 ? 'IA' : $game['player2_name']) : 
                $game['player1_name'];
            
            $result = "Match nul";
            if ($game['winner_id'] !== null) {
                $result = ($game['winner_id'] == $user_id) ? "Victoire" : "Défaite";
            }
            
            echo "<tr>";
            echo "<td>" . $game['id'] . "</td>";
            echo "<td>" . $opponent . "</td>";
            echo "<td>" . $result . "</td>";
            echo "<td>" . $game['updated_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Aucune partie terminée.</p>";
    }
} else {
    echo "<p>Aucun utilisateur connecté. <a href='/auth/login.php'>Se connecter</a></p>";
} 