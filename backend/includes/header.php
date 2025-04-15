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
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="flex flex-col min-h-screen m-0 p-0 overflow-x-hidden">
    <!-- Barre de navigation -->
    <nav class="bg-purple-900 text-white w-screen" style="min-width: 100%; border-radius: 0;">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <a href="/" class="text-xl font-bold">Jeu de Dames</a>
                
                <div class="hidden md:flex space-x-6">
                    <a href="/" class="hover:text-purple-200 transition">Accueil</a>
                    <a href="/game/play.php" class="hover:text-purple-200 transition">Jouer</a>
                    <a href="/game/history.php" class="hover:text-purple-200 transition">Historique</a>
                    <a href="/leaderboard.php" class="hover:text-purple-200 transition">Classement</a>
                    <a href="/pages/about.php" class="hover:text-purple-200 transition">À propos</a>
                    <a href="/pages/help.php" class="hover:text-purple-200 transition">Aide</a>
                    <a href="/pages/faq.php" class="hover:text-purple-200 transition">FAQ</a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <?php if (Session::isLoggedIn()): ?>
                        <!-- Notifications -->
                        <div x-data="{ open: false, notifications: [], count: 0, loading: false }" @click.away="open = false" class="relative">
                            <button @click="open = !open; if(open && !loading) { loading = true; fetch('/api/notifications/get_notifications.php?unread_only=true').then(r => r.json()).then(data => { if(data.success) { notifications = data.notifications || []; count = data.count || 0; loading = false; } }); }" class="flex items-center space-x-2 focus:outline-none relative">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span x-show="count > 0" x-text="count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"></span>
                            </button>
                            
                            <div x-show="open" class="absolute right-0 mt-2 w-80 bg-white shadow-lg py-1 z-10 rounded-md max-h-96 overflow-y-auto">
                                <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-200 flex justify-between items-center">
                                    <span>Notifications</span>
                                    <button @click="fetch('/api/notifications/mark_as_read.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mark_all: true }) }).then(() => { count = 0; notifications.forEach(n => n.is_read = true); })" class="text-xs text-blue-600 hover:text-blue-800">Tout marquer comme lu</button>
                                </div>
                                <template x-if="loading">
                                    <div class="px-4 py-2 text-sm text-gray-500 text-center">
                                        Chargement...
                                    </div>
                                </template>
                                <template x-if="!loading && notifications.length === 0">
                                    <div class="px-4 py-2 text-sm text-gray-500 text-center">
                                        Aucune notification
                                    </div>
                                </template>
                                <template x-for="notification in notifications" :key="notification.id">
                                    <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100" :class="{'bg-blue-50': !notification.is_read}">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-2">
                                                <template x-if="notification.type === 'friend_request'">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                                    </svg>
                                                </template>
                                                <template x-if="notification.type === 'friend_accepted'">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </template>
                                                <template x-if="notification.type === 'game_invite'">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </template>
                                            </div>
                                            <div class="flex-grow">
                                                <p class="text-sm" x-text="notification.content"></p>
                                                <p class="text-xs text-gray-500" x-text="new Date(notification.created_at).toLocaleString()"></p>
                                            </div>
                                            <button @click.stop="fetch('/api/notifications/mark_as_read.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ notification_id: notification.id }) }).then(() => { notification.is_read = true; count = Math.max(0, count - 1); })" class="text-xs text-gray-400 hover:text-gray-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <div class="notification-footer mt-3 pt-2 border-t border-gray-100 text-center">
                                    <a href="/notifications.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                                        Voir toutes les notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none bg-purple-800 px-3 py-1" style="border-radius: 0;">
                                <span class="connection-status"><?php echo Session::getUsername(); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white shadow-lg py-1 z-10" style="border-radius: 0;">
                                <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-200">
                                    Connecté en tant que <?php echo Session::getUsername(); ?>
                                </div>
                                <a href="/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-100 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Profil
                                </a>
                                <a href="/game/play.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-100 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Mes parties
                                </a>
                                <a href="/game/history.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-100 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Historique des parties
                                </a>
                                <a href="/auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-100 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Déconnexion
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login.php" class="hover:text-purple-200 transition flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Connexion
                        </a>
                        <a href="/auth/register.php" class="bg-purple-700 text-white px-4 py-2 hover:bg-purple-800 transition flex items-center" style="border-radius: 0;">
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
                    
                    <div x-show="open" @click.away="open = false" class="absolute top-16 right-0 left-0 bg-purple-900 shadow-md z-10">
                        <div class="container mx-auto px-4 py-2">
                            <a href="/" class="block py-2 hover:text-purple-200 transition">Accueil</a>
                            <a href="/game/play.php" class="block py-2 hover:text-purple-200 transition">Jouer</a>
                            <a href="/game/history.php" class="block py-2 hover:text-purple-200 transition">Historique</a>
                            <a href="/leaderboard.php" class="block py-2 hover:text-purple-200 transition">Classement</a>
                            <a href="/pages/about.php" class="block py-2 hover:text-purple-200 transition">À propos</a>
                            <a href="/pages/help.php" class="block py-2 hover:text-purple-200 transition">Aide</a>
                            <a href="/pages/faq.php" class="block py-2 hover:text-purple-200 transition">FAQ</a>
                            
                            <?php if (!Session::isLoggedIn()): ?>
                                <a href="/auth/login.php" class="block py-2 hover:text-purple-200 transition">Connexion</a>
                                <a href="/auth/register.php" class="block py-2 hover:text-purple-200 transition">Inscription</a>
                            <?php else: ?>
                                <a href="/profile.php" class="block py-2 hover:text-purple-200 transition">Profil</a>
                                <div class="py-2 text-purple-300">Connecté en tant que <?php echo Session::getUsername(); ?></div>
                                <a href="/auth/logout.php" class="block py-2 hover:text-purple-200 transition">Déconnexion</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="flex-grow"><?php // Ne pas fermer la balise main ici, elle est fermée dans footer.php ?>
    <div class="notification-footer mt-3 pt-2 border-t border-gray-100 text-center">
        <a href="/notifications.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
            Voir toutes les notifications
        </a>
    </div>
</body>
</html>
