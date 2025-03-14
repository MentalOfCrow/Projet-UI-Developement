<?php
/**
 * Script d'installation du jeu de dames
 * Ce script vérifie les prérequis, crée la base de données et les tables nécessaires
 */

// Fonction pour afficher les messages
function displayMessage($message, $type = 'info') {
    $colors = [
        'success' => '#28a745',
        'danger' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#17a2b8'
    ];
    
    $color = $colors[$type] ?? $colors['info'];
    
    echo "<div style=\"margin: 10px 0; padding: 10px; background-color: {$color}; color: white; border-radius: 5px;\">";
    echo $message;
    echo "</div>";
}

// Vérifier si PHP est en mode CLI
$isCli = php_sapi_name() === 'cli';


// En-tête HTML pour le mode navigateur
if (!$isCli) {
    echo "<!DOCTYPE html>
<html lang=\"fr\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Installation du Jeu de Dames</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2 { color: #4338ca; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .step { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Installation du Jeu de Dames</h1>";
}

// Étape 1: Vérifier les prérequis
if (!$isCli) echo "<div class=\"step\"><h2>Étape 1: Vérification des prérequis</h2>";

// Vérifier la version de PHP
$phpVersion = phpversion();
$phpVersionOk = version_compare($phpVersion, '7.4.0', '>=');

if ($phpVersionOk) {
    displayMessage("✅ Version de PHP: {$phpVersion}", 'success');
} else {
    displayMessage("❌ Version de PHP: {$phpVersion}. PHP 7.4 ou supérieur est requis.", 'danger');
}

// Vérifier les extensions requises
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $missingExtensions[] = $extension;
    }
}

if (empty($missingExtensions)) {
    displayMessage("✅ Toutes les extensions requises sont chargées.", 'success');
} else {
    displayMessage("❌ Extensions manquantes: " . implode(', ', $missingExtensions), 'danger');
}

// Vérifier les droits d'écriture
$writableDirs = ['public/assets', 'backend/logs'];
$notWritableDirs = [];

foreach ($writableDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    if (!is_writable($dir)) {
        $notWritableDirs[] = $dir;
    }
}

if (empty($notWritableDirs)) {
    displayMessage("✅ Tous les répertoires nécessaires sont accessibles en écriture.", 'success');
} else {
    displayMessage("❌ Répertoires non accessibles en écriture: " . implode(', ', $notWritableDirs), 'danger');
}

if (!$isCli) echo "</div>";

// Étape 2: Configuration de la base de données
if (!$isCli) echo "<div class=\"step\"><h2>Étape 2: Configuration de la base de données</h2>";

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_host'])) {
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    
    try {
        // Tester la connexion
        $dsn = "mysql:host={$db_host}";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        displayMessage("✅ Connexion à MySQL réussie.", 'success');
        
        // Créer la base de données
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        displayMessage("✅ Base de données '{$db_name}' créée avec succès.", 'success');
        
        // Sélectionner la base de données
        $pdo->exec("USE `{$db_name}`");
        
        // Lire le fichier SQL
        $sql = file_get_contents(__DIR__ . '/backend/db/db.sql');
        
        // Exécuter le script SQL
        $pdo->exec($sql);
        displayMessage("✅ Tables créées avec succès.", 'success');
        
        // Créer le fichier de configuration
        $configContent = "<?php
/**
 * Fichier de configuration
 * Définit les constantes et paramètres globaux de l'application
 */

// Activer l'affichage des erreurs en développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

// Paramètres de l'application
define('APP_NAME', 'Jeu de Dames');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // 'development' ou 'production'

// Chemins de l'application
define('BASE_PATH', realpath(__DIR__ . '/../../'));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('VIEWS_PATH', BASE_PATH . '/views');
define('BACKEND_PATH', BASE_PATH . '/backend');

// Paramètres de la base de données
define('DB_HOST', '{$db_host}');
define('DB_NAME', '{$db_name}');
define('DB_USER', '{$db_user}');
define('DB_PASS', '{$db_pass}');

// Paramètres de sécurité
define('HASH_COST', 10); // Coût de hachage pour bcrypt

// Chargement des classes et fonctions essentielles
require_once __DIR__ . '/session.php';
Session::start();

// Fonction pour charger automatiquement les classes
spl_autoload_register(function (\$class_name) {
    \$paths = [
        BACKEND_PATH . '/models/',
        BACKEND_PATH . '/controllers/',
        BACKEND_PATH . '/db/'
    ];
    
    foreach (\$paths as \$path) {
        \$file = \$path . \$class_name . '.php';
        if (file_exists(\$file)) {
            require_once \$file;
            return;
        }
    }
});

// Fonction pour échapper les sorties HTML
function e(\$string) {
    return htmlspecialchars(\$string, ENT_QUOTES, 'UTF-8');
}

// Fonction pour rediriger
function redirect(\$path) {
    header('Location: ' . \$path);
    exit();
}

// Démarrer la session
Session::start();";
        
        // Écrire le fichier de configuration
        file_put_contents(__DIR__ . '/backend/includes/config.php', $configContent);
        displayMessage("✅ Fichier de configuration créé avec succès.", 'success');
        
        displayMessage("🎉 Installation terminée avec succès! Vous pouvez maintenant <a href=\"/\">accéder au jeu</a>.", 'success');
        
    } catch (PDOException $e) {
        displayMessage("❌ Erreur de connexion à la base de données: " . $e->getMessage(), 'danger');
    }
} else {
    // Afficher le formulaire de configuration
    if (!$isCli) {
        echo '<form method="POST" action="">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Hôte MySQL:</label>
                <input type="text" name="db_host" value="localhost" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Nom de la base de données:</label>
                <input type="text" name="db_name" value="checkers_game" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Utilisateur MySQL:</label>
                <input type="text" name="db_user" value="root" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Mot de passe MySQL:</label>
                <input type="password" name="db_pass" value="" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div>
                <button type="submit" style="background-color: #4338ca; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">Installer</button>
            </div>
        </form>';
    } else {
        // En mode CLI, afficher les instructions
        echo "Pour installer en mode CLI, exécutez la commande suivante:\n";
        echo "php install.php --db-host=localhost --db-name=checkers_game --db-user=root --db-pass=password\n";
    }
}

if (!$isCli) echo "</div>";

// Étape 3: Finalisation (instructions)
if (!$isCli) {
    echo "<div class=\"step\">
        <h2>Étape 3: Finalisation</h2>
        <p>Une fois l'installation terminée, suivez ces étapes :</p>
        <ol>
            <li>Démarrez votre serveur PHP avec la commande : <pre>php -S localhost:8000 -t public</pre></li>
            <li>Accédez au jeu en ouvrant votre navigateur à l'adresse : <a href=\"http://localhost:8000\">http://localhost:8000</a></li>
            <li>Créez un compte et commencez à jouer !</li>
        </ol>
    </div>
</body>
</html>";
} 