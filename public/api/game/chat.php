<?php
require_once __DIR__ . '/../../../backend/includes/session.php';
require_once __DIR__ . '/../../../backend/controllers/ChatController.php';
require_once __DIR__ . '/../../../backend/db/JsonDatabase.php';
require_once __DIR__ . '/../../../backend/controllers/GameController.php';

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$chat = new ChatController();
$action = $_GET['action'] ?? '';
$gameId = intval($_GET['game_id'] ?? ($_POST['game_id'] ?? 0));

if ($gameId <= 0) {
    echo json_encode(['success' => false, 'message' => 'game_id manquant']);
    exit;
}

$gameController = new GameController();
$gameInfo = $gameController->getGame($gameId);

switch ($action) {
    case 'send':
        $payload = json_decode(file_get_contents('php://input'), true);
        $message = $payload['message'] ?? '';
        $userId = Session::getUserId();
        $db = JsonDatabase::getInstance();
        $user = $db->getUserById($userId);
        $username = $user['username'] ?? 'Joueur';
        echo json_encode($chat->addMessage($gameId, $userId, $username, $message));

        if ($gameInfo['success'] && $gameInfo['game']['player2_id'] == 0) {
            $botId = 0;
            $botName = 'Bot';
            // Générer une réponse simple du bot
            $botReply = generateBotReply($message);
            $chat->addMessage($gameId, $botId, $botName, $botReply);
        }
        break;
    case 'get':
        $since = intval($_GET['since'] ?? 0);
        echo json_encode($chat->getMessages($gameId, $since));
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'action invalide']);
}

function generateBotReply(string $msg): string {
    $msg = strtolower($msg);
    if (str_contains($msg, 'bonjour') || str_contains($msg, 'salut')) return 'Bonjour ! Prêt à jouer ?';
    if (str_contains($msg, 'bien') || str_contains($msg, 'ça va')) return 'Je vais toujours bien quand je joue aux dames !';
    if (str_contains($msg, 'gg') || str_contains($msg, 'bravo')) return 'Merci ! Le jeu n\'est pas encore fini.';
    $responses = ['À toi de jouer.', 'Hmmm...', 'Intéressant.', "Je réfléchis à mon prochain coup.", 'Bonne partie !'];
    return $responses[array_rand($responses)];
} 