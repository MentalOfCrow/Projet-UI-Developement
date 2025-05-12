<?php
class ChatController {
    private string $chatDir;

    public function __construct() {
        $this->chatDir = __DIR__ . '/../chat_data/';
        if (!is_dir($this->chatDir)) {
            mkdir($this->chatDir, 0777, true);
        }
    }

    private function getChatFile(int $gameId): string {
        return $this->chatDir . 'game_' . $gameId . '.json';
    }

    /**
     * Ajouter un message au chat d'une partie
     */
    public function addMessage(int $gameId, int $userId, string $username, string $message): array {
        $message = trim($message);
        if ($message === '') {
            return ['success' => false, 'message' => 'Message vide'];
        }

        $chatFile = $this->getChatFile($gameId);
        $chat = file_exists($chatFile) ? json_decode(file_get_contents($chatFile), true) : [];

        $entry = [
            'id'        => time() . rand(1000, 9999),
            'user_id'   => $userId,
            'username'  => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            'message'   => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            'timestamp' => time()
        ];
        $chat[] = $entry;
        file_put_contents($chatFile, json_encode($chat, JSON_PRETTY_PRINT));

        return ['success' => true, 'message' => 'ok'];
    }

    /**
     * Récupérer les messages depuis un timestamp
     */
    public function getMessages(int $gameId, int $since = 0): array {
        $chatFile = $this->getChatFile($gameId);
        if (!file_exists($chatFile)) {
            return ['success' => true, 'messages' => []];
        }
        $chat = json_decode(file_get_contents($chatFile), true);
        $filtered = array_filter($chat, fn($m) => $m['timestamp'] > $since);
        return ['success' => true, 'messages' => array_values($filtered)];
    }
} 