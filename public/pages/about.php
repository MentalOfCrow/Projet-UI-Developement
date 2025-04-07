<?php
require_once __DIR__ . '/../../backend/includes/config.php';

$pageTitle = "À propos - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 mt-2 pt-4">
    <h1 class="text-3xl font-bold text-purple-600 mb-8">À propos de <?php echo APP_NAME; ?></h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold text-purple-600 mb-4">Notre histoire</h2>
        <p class="text-gray-600 mb-4">
            Le jeu de dames est l'un des plus anciens jeux de plateau au monde, avec une histoire remontant à plusieurs millénaires. 
            Notre plateforme a été créée pour permettre aux joueurs du monde entier de profiter de ce jeu classique en ligne.
        </p>
        <p class="text-gray-600">
            Nous avons développé ce projet dans le cadre du module UF Développement pour mettre en pratique 
            nos compétences en développement logiciel et en gestion de bases de données.
        </p>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold text-purple-600 mb-4">Fonctionnalités clés</h2>
        <ul class="list-disc pl-6 text-gray-600 space-y-2">
            <li>Système de matchmaking pour trouver des adversaires en ligne</li>
            <li>File d'attente pour gérer les connexions des joueurs</li>
            <li>Plateau interactif pour jouer en ligne</li>
            <li>Suivi des parties et des scores via une base de données</li>
            <li>Authentification sécurisée pour les utilisateurs</li>
        </ul>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-purple-600 mb-4">Technologies utilisées</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Backend</h3>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>PHP pour la logique du serveur</li>
                    <li>MySQL pour la base de données</li>
                    <li>Architecture MVC pour l'organisation du code</li>
                </ul>
            </div>
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Frontend</h3>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>HTML5 et CSS3 pour la structure et le style</li>
                    <li>JavaScript pour l'interactivité</li>
                    <li>Tailwind CSS pour un design moderne et réactif</li>
                    <li>Alpine.js pour la réactivité des composants</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>