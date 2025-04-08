<?php
// Script temporaire pour vérifier l'état des parties
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/db/Database.php';

// ID de l'utilisateur à vérifier
$user_id = 1; // Remplacez par l'ID de votre utilisateur

echo "Vérification des parties pour l'utilisateur ID: $user_id\n\n";

// Récupérer une connexion à la base de données
$db = Database::getInstance()->getConnection();

// 1. Vérifier le nombre total de parties terminées dans la base de données
$stmt = $db->query("SELECT COUNT(*) as count FROM games WHERE status = 'finished'");
$result = $stmt->fetch();
echo "Nombre total de parties marquées comme terminées: " . $result['count'] . "\n\n";

// 2. Vérifier les parties terminées pour cet utilisateur
$stmt = $db->prepare("SELECT COUNT(*) as count FROM games 
                      WHERE (player1_id = ? OR player2_id = ?) 
                      AND status = 'finished'");
$stmt->execute([$user_id, $user_id]);
$result = $stmt->fetch();

echo "Nombre de parties terminées pour l'utilisateur $user_id: " . $result['count'] . "\n\n";

// 3. Récupérer les détails des parties terminées pour cet utilisateur
$query = "SELECT g.*, 
           u1.username as player1_name, 
           u2.username as player2_name 
          FROM games g
          LEFT JOIN users u1 ON g.player1_id = u1.id
          LEFT JOIN users u2 ON g.player2_id = u2.id
          WHERE (g.player1_id = ? OR g.player2_id = ?) 
          AND g.status = 'finished'
          ORDER BY g.updated_at DESC
          LIMIT 10";

$stmt = $db->prepare($query);
$stmt->execute([$user_id, $user_id]);

echo "Parties terminées trouvées pour l'utilisateur $user_id: " . $stmt->rowCount() . "\n";

if ($stmt->rowCount() > 0) {
    echo "Voici les détails des parties:\n";
    echo str_repeat('-', 100) . "\n";
    echo sprintf("%-5s %-15s %-15s %-15s %-15s %-20s %-15s\n", 
                "ID", "Joueur 1", "Joueur 2", "Gagnant", "Statut", "Date màj", "Date création");
    echo str_repeat('-', 100) . "\n";
    
    while ($game = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $winner = "Match nul";
        if ($game['winner_id']) {
            $winner = ($game['winner_id'] == $game['player1_id']) ? $game['player1_name'] : $game['player2_name'];
        }
        
        echo sprintf("%-5s %-15s %-15s %-15s %-15s %-20s %-15s\n", 
            $game['id'],
            $game['player1_name'] ?? 'Inconnu',
            $game['player2_id'] == 0 ? "IA" : ($game['player2_name'] ?? 'Inconnu'),
            $winner,
            $game['status'],
            $game['updated_at'],
            $game['created_at']
        );
    }
} else {
    echo "Aucune partie terminée trouvée pour cet utilisateur.\n";
}

// 4. Vérifier les statistiques pour cet utilisateur
$stmt = $db->prepare("SELECT * FROM stats WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

echo "\n\nStatistiques pour l'utilisateur $user_id:\n";
echo str_repeat('-', 50) . "\n";

if ($stats) {
    echo "Parties jouées: " . $stats['games_played'] . "\n";
    echo "Victoires: " . $stats['games_won'] . "\n";
    echo "Défaites: " . $stats['games_lost'] . "\n";
    echo "Matchs nuls: " . ($stats['games_played'] - $stats['games_won'] - $stats['games_lost']) . "\n";
    echo "Dernière partie: " . $stats['last_game'] . "\n";
} else {
    echo "Aucune statistique trouvée pour cet utilisateur.\n";
}

// 5. Problème potentiel: Vérifier si le même utilisateur existe plusieurs fois
$query = "SELECT * FROM users WHERE username = (SELECT username FROM users WHERE id = ?)";
try {
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll();
    
    if (count($users) > 1) {
        echo "\n\nATTENTION: Plusieurs utilisateurs avec le même nom trouvés!\n";
        foreach ($users as $user) {
            echo "ID: " . $user['id'] . ", Nom: " . $user['username'] . ", Email: " . $user['email'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "\nErreur lors de la vérification des doublons d'utilisateurs: " . $e->getMessage() . "\n";
} 