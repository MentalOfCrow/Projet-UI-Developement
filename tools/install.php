<?php
/**
 * Script d'installation principal
 * Ce script affiche une interface pour accéder aux différents outils d'installation
 */

// Désactiver les erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// Titre de la page
$pageTitle = "Outils d'installation";

// Vérifier si on est en mode local pour la sécurité
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);

// Pour une installation en production, vous pouvez ajouter une vérification par mot de passe
$allowAccess = $isLocalhost;
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
            <i class="fas fa-tools mr-2"></i><?php echo $pageTitle; ?>
        </h1>
        
        <?php if (!$allowAccess): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                </div>
                <div>
                    <p class="font-bold">Accès refusé</p>
                    <p>Pour des raisons de sécurité, les outils d'installation ne sont accessibles qu'en local.</p>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Installation de base -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-purple-700 text-white">
                    <h3 class="font-bold text-xl mb-2">Installation de base</h3>
                    <p class="text-purple-100">Configuration initiale de la base de données</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-gray-700 mb-4">
                        Installe les tables de base pour le fonctionnement du jeu: utilisateurs, parties, mouvements, etc.
                    </p>
                    <a href="install/install.php" class="inline-block bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                        Lancer l'installation
                    </a>
                </div>
            </div>
            
            <!-- Installation du leaderboard -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-blue-700 text-white">
                    <h3 class="font-bold text-xl mb-2">Classement</h3>
                    <p class="text-blue-100">Configuration du système de classement</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-gray-700 mb-4">
                        Installe et configure la table de classement (leaderboard) pour le suivi des performances.
                    </p>
                    <a href="install/install_leaderboard.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                        Configurer le classement
                    </a>
                </div>
            </div>
            
            <!-- Vérification du système -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-green-700 text-white">
                    <h3 class="font-bold text-xl mb-2">Diagnostic</h3>
                    <p class="text-green-100">Vérification du système</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-gray-700 mb-4">
                        Vérifie la configuration du serveur, les extensions PHP requises et les droits d'accès.
                    </p>
                    <a href="install/check_system.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                        Vérifier le système
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Notes supplémentaires -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Note importante</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Ces outils doivent être utilisés uniquement lors de l'installation initiale ou pour la maintenance.</p>
                        <p class="mt-1">Une fois l'installation terminée, il est recommandé de restreindre l'accès à ce répertoire.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="/" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                <i class="fas fa-home mr-2"></i>Retour à l'accueil
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 