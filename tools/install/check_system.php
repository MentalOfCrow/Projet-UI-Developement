<?php
/**
 * Outil de diagnostic du système
 * Ce script vérifie la configuration du serveur, les extensions PHP requises
 * et les permissions des répertoires pour s'assurer que l'application
 * peut fonctionner correctement.
 */

// Activer l'affichage des erreurs pour le diagnostic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Titre de la page
$pageTitle = "Diagnostic du système";

// Vérifier la version de PHP
$phpVersion = phpversion();
$requiredPhpVersion = '7.4.0';
$phpVersionOk = version_compare($phpVersion, $requiredPhpVersion, '>=');

// Extensions PHP requises
$requiredExtensions = [
    'pdo',
    'pdo_mysql',
    'json',
    'session',
    'mbstring',
    'fileinfo'
];

$extensionsStatus = [];
foreach ($requiredExtensions as $ext) {
    $extensionsStatus[$ext] = extension_loaded($ext);
}

// Vérifier les répertoires importants
$baseDir = dirname(dirname(dirname(__FILE__)));
$directories = [
    'data' => $baseDir . '/data',
    'data/users' => $baseDir . '/data/users',
    'data/games' => $baseDir . '/data/games',
    'data/stats' => $baseDir . '/data/stats',
    'backend/logs' => $baseDir . '/backend/logs'
];

$directoriesStatus = [];
foreach ($directories as $name => $path) {
    $exists = file_exists($path);
    $writable = false;
    
    if ($exists) {
        $writable = is_writable($path);
    } else {
        // Essayer de créer le répertoire s'il n'existe pas
        @mkdir($path, 0755, true);
        $exists = file_exists($path);
        $writable = $exists ? is_writable($path) : false;
    }
    
    $directoriesStatus[$name] = [
        'path' => $path,
        'exists' => $exists,
        'writable' => $writable
    ];
}

// Vérifier la configuration du serveur web
$serverInfo = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu',
    'name' => $_SERVER['SERVER_NAME'] ?? 'Inconnu',
    'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Inconnu',
    'doc_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Inconnu',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'Inconnu',
    'https' => isset($_SERVER['HTTPS']) ? 'Oui' : 'Non'
];

// Vérifier l'accès à la base de données
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'checkers', // Nom de la base de données par défaut
    'user' => 'root',
    'password' => ''
];

// Essayer de charger les variables d'environnement depuis .env si disponible
$envFile = $baseDir . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            if ($name === 'DB_HOST') $dbConfig['host'] = $value;
            if ($name === 'DB_NAME') $dbConfig['dbname'] = $value;
            if ($name === 'DB_USER') $dbConfig['user'] = $value;
            if ($name === 'DB_PASSWORD') $dbConfig['password'] = $value;
        }
    }
}

// Tester la connexion à la base de données
$dbConnected = false;
$dbError = '';

try {
    $dbh = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $dbConnected = true;
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// Calculer le résultat global du diagnostic
$allExtensionsOk = !in_array(false, $extensionsStatus);
$allDirectoriesOk = true;
foreach ($directoriesStatus as $dir) {
    if (!$dir['exists'] || !$dir['writable']) {
        $allDirectoriesOk = false;
        break;
    }
}

$allConditionsOk = $phpVersionOk && $allExtensionsOk && $allDirectoriesOk && $dbConnected;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Jeu de Dames en ligne</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center text-purple-800 mb-8">
            <i class="fas fa-stethoscope mr-2"></i><?php echo $pageTitle; ?>
        </h1>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="p-6 <?php echo $allConditionsOk ? 'bg-green-600' : 'bg-yellow-600'; ?> text-white">
                <h2 class="text-xl font-bold flex items-center">
                    <i class="<?php echo $allConditionsOk ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'; ?> mr-2"></i>
                    Résumé du diagnostic
                </h2>
                <p class="mt-2">
                    <?php if ($allConditionsOk): ?>
                        Toutes les vérifications ont été passées avec succès. Le système est correctement configuré.
                    <?php else: ?>
                        Certaines vérifications ont échoué. Veuillez résoudre les problèmes identifiés ci-dessous.
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="p-6">
                <!-- Version PHP -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2 text-gray-800">Version de PHP</h3>
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-4 <?php echo $phpVersionOk ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <i class="<?php echo $phpVersionOk ? 'fas fa-check' : 'fas fa-times'; ?>"></i>
                        </div>
                        <div>
                            <p class="text-gray-800">
                                Version actuelle: <span class="font-mono"><?php echo $phpVersion; ?></span>
                            </p>
                            <p class="text-gray-600 text-sm">
                                Version requise: <span class="font-mono"><?php echo $requiredPhpVersion; ?></span> ou supérieure
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Extensions PHP -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2 text-gray-800">Extensions PHP</h3>
                    <div class="space-y-2">
                        <?php foreach ($extensionsStatus as $ext => $loaded): ?>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-4 <?php echo $loaded ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <i class="<?php echo $loaded ? 'fas fa-check' : 'fas fa-times'; ?>"></i>
                            </div>
                            <p class="text-gray-800">
                                <?php echo $ext; ?>
                                <?php if (!$loaded): ?>
                                <span class="text-red-600 ml-2">(non installée)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Répertoires -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2 text-gray-800">Répertoires</h3>
                    <div class="space-y-2">
                        <?php foreach ($directoriesStatus as $name => $status): ?>
                        <div class="flex items-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-4 <?php echo ($status['exists'] && $status['writable']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <i class="<?php echo ($status['exists'] && $status['writable']) ? 'fas fa-check' : 'fas fa-times'; ?>"></i>
                            </div>
                            <div>
                                <p class="text-gray-800"><?php echo $name; ?></p>
                                <p class="text-gray-500 text-sm font-mono truncate"><?php echo $status['path']; ?></p>
                                <?php if (!$status['exists']): ?>
                                <p class="text-red-600 text-sm">Le répertoire n'existe pas</p>
                                <?php elseif (!$status['writable']): ?>
                                <p class="text-red-600 text-sm">Le répertoire n'est pas accessible en écriture</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Base de données -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2 text-gray-800">Connexion à la base de données</h3>
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-4 <?php echo $dbConnected ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <i class="<?php echo $dbConnected ? 'fas fa-check' : 'fas fa-times'; ?>"></i>
                        </div>
                        <div>
                            <p class="text-gray-800">
                                <?php if ($dbConnected): ?>
                                Connexion réussie à la base de données <span class="font-mono"><?php echo $dbConfig['dbname']; ?></span>
                                <?php else: ?>
                                Échec de la connexion à la base de données
                                <?php endif; ?>
                            </p>
                            <?php if (!$dbConnected): ?>
                            <p class="text-red-600 text-sm"><?php echo $dbError; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Serveur web -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold mb-2 text-gray-800">Informations sur le serveur</h3>
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($serverInfo as $key => $value): ?>
                            <tr>
                                <td class="px-6 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo ucfirst($key); ?></td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo $value; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="flex justify-center">
            <a href="../install.php" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Retour aux outils d'installation
            </a>
        </div>
    </div>
</body>
</html> 