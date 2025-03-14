    </main>
    
    <!-- Pied de page -->
    <footer class="bg-indigo-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4"><?php echo APP_NAME; ?></h3>
                    <p class="text-indigo-200">
                        Le jeu de dames en ligne simple et accessible. Affrontez des joueurs du monde entier !
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Liens rapides</h3>
                    <ul class="space-y-2">
                        <li><a href="/" class="text-indigo-200 hover:text-white transition">Accueil</a></li>
                        <li><a href="/game/play.php" class="text-indigo-200 hover:text-white transition">Jouer</a></li>
                        <li><a href="/pages/about.php" class="text-indigo-200 hover:text-white transition">À propos</a></li>
                        <li><a href="/pages/help.php" class="text-indigo-200 hover:text-white transition">Aide</a></li>
                        <li><a href="/pages/faq.php" class="text-indigo-200 hover:text-white transition">FAQ</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact</h3>
                    <ul class="space-y-2">
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <a href="mailto:contact@jeudedames.fr" class="text-indigo-200 hover:text-white transition">contact@jeudedames.fr</a>
                        </li>
                        <li class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span class="text-indigo-200">Suivez-nous sur les réseaux sociaux</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t border-indigo-700 text-center text-indigo-300 text-sm">
                <p>&copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?> | Tous droits réservés</p>
                <p class="mt-2">Version <?php echo APP_VERSION; ?></p>
            </div>
        </div>
    </footer>
</body>
</html>
