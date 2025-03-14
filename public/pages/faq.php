<?php
require_once __DIR__ . '/../../backend/includes/config.php';

$pageTitle = "FAQ - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-indigo-600 mb-8">Questions fréquemment posées</h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-8" x-data="{ activeTab: 'general' }">
        <!-- Onglets de navigation -->
        <div class="flex flex-wrap border-b mb-6">
            <button 
                @click="activeTab = 'general'" 
                :class="{ 'text-indigo-600 border-b-2 border-indigo-600': activeTab === 'general', 'text-gray-500 hover:text-indigo-500': activeTab !== 'general' }"
                class="mr-6 py-2 font-medium"
            >
                Général
            </button>
            <button 
                @click="activeTab = 'account'" 
                :class="{ 'text-indigo-600 border-b-2 border-indigo-600': activeTab === 'account', 'text-gray-500 hover:text-indigo-500': activeTab !== 'account' }"
                class="mr-6 py-2 font-medium"
            >
                Compte et Inscription
            </button>
            <button 
                @click="activeTab = 'gameplay'" 
                :class="{ 'text-indigo-600 border-b-2 border-indigo-600': activeTab === 'gameplay', 'text-gray-500 hover:text-indigo-500': activeTab !== 'gameplay' }"
                class="mr-6 py-2 font-medium"
            >
                Règles du jeu
            </button>
            <button 
                @click="activeTab = 'technical'" 
                :class="{ 'text-indigo-600 border-b-2 border-indigo-600': activeTab === 'technical', 'text-gray-500 hover:text-indigo-500': activeTab !== 'technical' }"
                class="py-2 font-medium"
            >
                Technique
            </button>
        </div>
        
        <!-- Contenu des onglets -->
        <div x-show="activeTab === 'general'">
            <div class="space-y-6">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Qu'est-ce que <?php echo APP_NAME; ?> ?</h3>
                    <p class="text-gray-600">
                        <?php echo APP_NAME; ?> est une plateforme de jeu de dames en ligne qui vous permet d'affronter d'autres joueurs en temps réel. 
                        Vous pouvez jouer depuis n'importe quel navigateur web sans avoir à installer de logiciel supplémentaire.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">L'utilisation est-elle gratuite ?</h3>
                    <p class="text-gray-600">
                        Oui, <?php echo APP_NAME; ?> est entièrement gratuit. Vous pouvez créer un compte et jouer autant de parties que vous le souhaitez sans frais.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Comment puis-je commencer à jouer ?</h3>
                    <p class="text-gray-600">
                        Pour commencer à jouer, vous devez d'abord créer un compte. Ensuite, connectez-vous et cliquez sur le bouton "Jouer" dans le menu principal. 
                        Vous serez mis en file d'attente et automatiquement mis en relation avec un adversaire dès qu'il y en aura un disponible.
                    </p>
                </div>
            </div>
        </div>
        
        <div x-show="activeTab === 'account'" style="display: none;">
            <div class="space-y-6">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Comment créer un compte ?</h3>
                    <p class="text-gray-600">
                        Pour créer un compte, cliquez sur "Inscription" dans le menu principal. Vous devrez fournir un nom d'utilisateur, 
                        une adresse e-mail et un mot de passe. Une fois le formulaire soumis, vous pourrez vous connecter immédiatement.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">J'ai oublié mon mot de passe, que faire ?</h3>
                    <p class="text-gray-600">
                        Si vous avez oublié votre mot de passe, cliquez sur le lien "Mot de passe oublié ?" sur la page de connexion. 
                        Vous recevrez un e-mail avec des instructions pour réinitialiser votre mot de passe.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Est-ce que mes informations personnelles sont sécurisées ?</h3>
                    <p class="text-gray-600">
                        Oui, vos informations personnelles sont sécurisées. Nous utilisons des techniques de cryptage modernes pour protéger vos données. 
                        Nous ne partageons pas vos informations avec des tiers et n'utilisons pas vos données à des fins publicitaires.
                    </p>
                </div>
            </div>
        </div>
        
        <div x-show="activeTab === 'gameplay'" style="display: none;">
            <div class="space-y-6">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Quelles sont les règles du jeu de dames ?</h3>
                    <p class="text-gray-600">
                        Les règles du jeu de dames sont simples : les pièces se déplacent en diagonale d'une case vers l'avant. 
                        Pour capturer une pièce adverse, vous devez la sauter en diagonale et atterrir sur une case vide. 
                        Lorsqu'un pion atteint la dernière rangée du côté adverse, il devient une dame et peut se déplacer en diagonale dans toutes les directions. 
                        Pour plus de détails, consultez notre page d'aide.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Que se passe-t-il si je perds ma connexion pendant une partie ?</h3>
                    <p class="text-gray-600">
                        Si vous perdez votre connexion pendant une partie, vous avez un délai de 2 minutes pour vous reconnecter et reprendre là où vous vous étiez arrêté. 
                        Après ce délai, la partie sera automatiquement déclarée perdue.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Comment fonctionne le système de classement ?</h3>
                    <p class="text-gray-600">
                        Chaque joueur a un score de compétence (ELO) qui augmente lorsqu'il gagne et diminue lorsqu'il perd. 
                        Le montant de la variation dépend de la différence de classement entre les deux joueurs. 
                        En battant un joueur mieux classé, vous gagnerez plus de points qu'en battant un joueur moins bien classé.
                    </p>
                </div>
            </div>
        </div>
        
        <div x-show="activeTab === 'technical'" style="display: none;">
            <div class="space-y-6">
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Sur quels navigateurs puis-je jouer ?</h3>
                    <p class="text-gray-600">
                        <?php echo APP_NAME; ?> est compatible avec tous les navigateurs modernes, dont Chrome, Firefox, Safari et Edge. 
                        Pour une expérience optimale, nous recommandons d'utiliser la dernière version de votre navigateur préféré.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Puis-je jouer sur mobile ?</h3>
                    <p class="text-gray-600">
                        Oui, <?php echo APP_NAME; ?> est entièrement responsive et fonctionne parfaitement sur les appareils mobiles. 
                        Vous pouvez jouer sur votre smartphone ou tablette sans problème.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Comment signaler un problème technique ?</h3>
                    <p class="text-gray-600">
                        Si vous rencontrez un problème technique, vous pouvez nous contacter via l'adresse e-mail fournie en bas de cette page. 
                        Veuillez inclure autant de détails que possible sur le problème rencontré, ainsi que le type de navigateur et d'appareil que vous utilisez.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section de contact -->
    <div class="mt-12 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-indigo-600 mb-4">Vous n'avez pas trouvé de réponse à votre question ?</h2>
        <p class="text-gray-600 mb-6">
            Si vous avez d'autres questions ou besoin d'assistance, n'hésitez pas à nous contacter. Notre équipe est à votre disposition pour vous aider.
        </p>
        <div class="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <a href="mailto:contact@jeudedames.fr" class="text-indigo-600 hover:underline">contact@jeudedames.fr</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>