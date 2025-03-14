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
        
        /* Badge d'état de connexion */
        .connection-status {
            position: relative;
        }
        
        .connection-status::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981; /* vert pour connecté */
            bottom: 0;
            right: -5px;
        }
        
        .connection-status.offline::after {
            background-color: #ef4444; /* rouge pour déconnecté */
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
                            <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none bg-indigo-700 px-3 py-1 rounded-full">
                                <span class="connection-status"><?php echo Session::getUsername(); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                                <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-200">
                                    Connecté en tant que <?php echo Session::getUsername(); ?>
                                </div>
                                <a href="/game/play.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-100 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Mes parties
                                </a>
                                <a href="/auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-100 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Déconnexion
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login.php" class="hover:text-indigo-200 transition flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Connexion
                        </a>
                        <a href="/auth/register.php" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                            Inscription
                        </a>
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
                            <?php else: ?>
                                <div class="py-2 text-indigo-300">Connecté en tant que <?php echo Session::getUsername(); ?></div>
                                <a href="/auth/logout.php" class="block py-2 hover:text-indigo-200 transition">Déconnexion</a>
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
