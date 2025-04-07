<?php
require_once __DIR__ . '/../../backend/includes/config.php';

$pageTitle = "Aide - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 mt-2 pt-4">
    <h1 class="text-3xl font-bold text-purple-600 mb-8">Aide et règles du jeu</h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold text-purple-600 mb-4">Règles du jeu de dames</h2>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Le plateau</h3>
                <p class="text-gray-600">Le jeu se joue sur un damier de 8×8 cases. Seules les cases noires sont utilisées.</p>
            </div>
            
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Les pièces</h3>
                <p class="text-gray-600">Chaque joueur possède 12 pions au début de la partie. Ces pions peuvent devenir des "dames" en atteignant la dernière rangée du côté adverse.</p>
            </div>
            
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Les déplacements</h3>
                <ul class="list-disc pl-6 text-gray-600 space-y-2">
                    <li>Les pions se déplacent en diagonale d'une case, uniquement vers l'avant (vers le bas pour les pions noirs, vers le haut pour les pions blancs).</li>
                    <li>Les dames peuvent se déplacer en diagonale d'une ou plusieurs cases, aussi bien en avant qu'en arrière.</li>
                    <li>La prise est obligatoire. Si plusieurs prises sont possibles, vous devez choisir celle qui capture le plus grand nombre de pièces.</li>
                    <li>Pour prendre une pièce adverse, vous devez la sauter en diagonale et atterrir sur une case libre juste après.</li>
                    <li>Vous pouvez enchaîner plusieurs prises en un seul tour si cela est possible.</li>
                </ul>
            </div>
            
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">La dame</h3>
                <p class="text-gray-600">Lorsqu'un pion atteint la dernière rangée du côté adverse, il est couronné et devient une dame. Une dame peut se déplacer en diagonale sur plusieurs cases, aussi bien en avant qu'en arrière.</p>
            </div>
            
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Fin de la partie</h3>
                <p class="text-gray-600">La partie est gagnée par le joueur qui a capturé ou bloqué toutes les pièces adverses. Si aucun joueur ne peut gagner, la partie est déclarée nulle.</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold text-purple-600 mb-4">Comment jouer sur notre plateforme</h2>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Commencer une partie</h3>
                <ol class="list-decimal pl-6 text-gray-600 space-y-2">
                    <li>Connectez-vous à votre compte ou créez-en un si vous n'en avez pas encore.</li>
                    <li>Rendez-vous sur la page "Jouer" en cliquant sur le bouton correspondant dans le menu de navigation.</li>
                    <li>Cliquez sur "Rejoindre la file d'attente" pour être mis en relation avec un adversaire.</li>
                    <li>Une fois un adversaire trouvé, la partie commencera automatiquement.</li>
                </ol>
            </div>
            
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Jouer votre tour</h3>
                <ol class="list-decimal pl-6 text-gray-600 space-y-2">
                    <li>Cliquez sur une de vos pièces pour la sélectionner.</li>
                    <li>Les cases disponibles pour le déplacement seront mises en surbrillance en violet.</li>
                    <li>Cliquez sur une case en surbrillance pour déplacer votre pièce.</li>
                    <li>Si plusieurs prises sont possibles, vous devrez effectuer toutes les prises.</li>
                    <li>Après votre coup, ce sera au tour de votre adversaire.</li>
                </ol>
            </div>
            
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Fin de partie</h3>
                <p class="text-gray-600">La partie se termine lorsqu'un joueur a capturé ou bloqué toutes les pièces adverses. Le résultat sera enregistré dans vos statistiques.</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-purple-600 mb-4">Besoin d'aide supplémentaire ?</h2>
        <p class="text-gray-600 mb-4">Si vous avez d'autres questions ou si vous rencontrez des problèmes techniques, n'hésitez pas à nous contacter :</p>
        <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
            </svg>
            <a href="mailto:contact@jeudedames.fr" class="text-purple-600 hover:underline">contact@jeudedames.fr</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>
