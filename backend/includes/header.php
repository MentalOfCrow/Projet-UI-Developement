<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : APP_NAME; ?></title>
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.3/dist/cdn.min.js" defer></script>
    
    <!-- Custom CSS -->
    <style>
        /* Styles personnalisés */
        body {
            background-color: #f7f9fc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        main {
            flex: 1;
        }
        
        /* Styles pour le jeu de dames */
        .checkerboard .square {
            position: relative;
        }
        
        .checkerboard .piece {
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .checkerboard .piece:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Barre de navigation -->
    <nav class="bg-indigo-800 text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <a href="/" class="text-xl font-bold"><?php echo APP_NAME; ?></a>
                
                <div class="hidden md:flex space-x-6">
                    <a href="/" class="hover:text-indigo-200 transition">Accueil</a>
                    <a href="/game/play.php" class="hover:text-indigo-200 transition">Jouer</a>
                    <a href="/pages/about.php" class="hover:text-indigo-200 transition">À propos</a>
                    <a href="/pages/help.php" class="hover:text-indigo-200 transition">Aide</a>
                    <a href="/pages/faq.php" class="hover:text-indigo-200 transition">FAQ</a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <?php if (Session::isLoggedIn()): ?>
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-1 focus:outline-none">
                                <span><?php echo Session::getUsername(); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                                <a href="/game/play.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-100">Mes parties</a>
                                <a href="/auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-100">Déconnexion</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login.php" class="hover:text-indigo-200 transition">Connexion</a>
                        <a href="/auth/register.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">Inscription</a>
                    <?php endif; ?>
                </div>
                
                <!-- Menu mobile -->
                <div class="md:hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="text-white focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" class="absolute top-16 right-0 left-0 bg-indigo-800 shadow-md z-10">
                        <div class="container mx-auto px-4 py-2">
                            <a href="/" class="block py-2 hover:text-indigo-200 transition">Accueil</a>
                            <a href="/game/play.php" class="block py-2 hover:text-indigo-200 transition">Jouer</a>
                            <a href="/pages/about.php" class="block py-2 hover:text-indigo-200 transition">À propos</a>
                            <a href="/pages/help.php" class="block py-2 hover:text-indigo-200 transition">Aide</a>
                            <a href="/pages/faq.php" class="block py-2 hover:text-indigo-200 transition">FAQ</a>
                            
                            <?php if (!Session::isLoggedIn()): ?>
                                <a href="/auth/login.php" class="block py-2 hover:text-indigo-200 transition">Connexion</a>
                                <a href="/auth/register.php" class="block py-2 hover:text-indigo-200 transition">Inscription</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="flex-grow"><?php // Ne pas fermer la balise main ici, elle est fermée dans footer.php ?>
</body>
</html>
