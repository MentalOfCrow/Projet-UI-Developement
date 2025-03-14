<?php
// Supprimer tout output buffering existant et en démarrer un nouveau
while (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/AuthController.php';

$authController = new AuthController();

// Rediriger si déjà connecté
if ($authController->isLoggedIn()) {
    // Nettoyer le buffer avant de rediriger
    ob_end_clean();
    header('Location: /');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->register();
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

$pageTitle = "Inscription - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-indigo-600 mb-6 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            Inscription
        </h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
                <p class="mt-2">
                    <a href="/auth/login.php" class="text-green-700 underline">Se connecter maintenant</a>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <!-- Email en PREMIER pour que l'autocomplétion fonctionne correctement -->
            <div class="mb-4">
                <label for="email" class="block text-gray-700 mb-2 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Email
                </label>
                <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500" autocomplete="email" placeholder="votre.email@exemple.com">
            </div>
            
            <!-- Username en SECOND -->
            <div class="mb-4">
                <label for="username" class="block text-gray-700 mb-2 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Nom d'utilisateur
                </label>
                <input type="text" id="username" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500" autocomplete="username" placeholder="Choisissez un nom d'utilisateur">
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 mb-2 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Mot de passe
                </label>
                <input type="password" id="password" name="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500" autocomplete="new-password" placeholder="Choisissez un mot de passe sécurisé">
                <p class="text-gray-600 text-sm mt-1">Le mot de passe doit contenir au moins 6 caractères.</p>
            </div>
            
            <div class="flex justify-between items-center">
                <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition duration-200 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    S'inscrire
                </button>
                <a href="/auth/login.php" class="text-indigo-600 hover:text-indigo-800 transition duration-200 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    Déjà un compte?
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Script pour nettoyer le formulaire au chargement initial si nécessaire -->
<script>
// Vérifier si le formulaire a été soumis
if (window.performance && window.performance.navigation.type === 0) {
    // Vider les champs au chargement initial de la page (pas lors d'un rechargement après soumission)
    setTimeout(function() {
        // Supprimer le délai entre le chargement de la page et l'autocomplétion
        if (!document.getElementById('registerForm').classList.contains('submitting')) {
            if (document.getElementById('email').value.includes('@gmail.com') && 
                document.getElementById('username').value.includes('@gmail.com')) {
                // Si l'email est dupliqué dans le champ username, le vider
                document.getElementById('username').value = '';
            }
        }
    }, 200);  // Délai plus long pour laisser l'autocomplétion se faire
}

// Marquer le formulaire comme en cours de soumission
document.getElementById('registerForm').addEventListener('submit', function() {
    this.classList.add('submitting');
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>