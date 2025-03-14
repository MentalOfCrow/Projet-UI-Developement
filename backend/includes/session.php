<?php
/**
 * Classe de gestion des sessions
 * Cette classe fournit des méthodes pour gérer les sessions utilisateur
 */
class Session {
    /**
     * Démarre la session si elle n'est pas déjà démarrée
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Vérifie si un utilisateur est connecté
     * @return bool True si l'utilisateur est connecté, false sinon
     */
    public static function isLoggedIn() {
        self::start();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Définit un utilisateur comme connecté
     * @param int $user_id ID de l'utilisateur
     * @param string $username Nom d'utilisateur
     */
    public static function setLoggedIn($user_id, $username) {
        self::start();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Déconnecte l'utilisateur (détruits les variables de session)
     */
    public static function logout() {
        self::start();
        // Supprimer les variables de session
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['logged_in']);
        unset($_SESSION['last_activity']);
        
        // Détruire la session
        session_destroy();
    }
    
    /**
     * Récupère l'ID de l'utilisateur connecté
     * @return int|null ID de l'utilisateur ou null s'il n'est pas connecté
     */
    public static function getUserId() {
        self::start();
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Récupère le nom d'utilisateur de l'utilisateur connecté
     * @return string|null Nom d'utilisateur ou null s'il n'est pas connecté
     */
    public static function getUsername() {
        self::start();
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    /**
     * Définit une variable de session
     * @param string $key Clé de la variable
     * @param mixed $value Valeur à stocker
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Récupère une variable de session
     * @param string $key Clé de la variable
     * @param mixed $default Valeur par défaut si la variable n'existe pas
     * @return mixed Valeur de la variable ou valeur par défaut
     */
    public static function get($key, $default = null) {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * Supprime une variable de session
     * @param string $key Clé de la variable
     */
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * Vérifie si l'utilisateur est connecté, sinon redirige vers la page de connexion
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: /auth/login");
            exit;
        }
    }
}
