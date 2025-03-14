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
        <h1 class="text-2xl font-bold text-indigo-600 mb-6">Inscription</h1>
        
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
            <div class="mb-4">
                <label for="username" class="block text-gray-700 mb-2">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-gray-700 mb-2">Email</label>
                <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 mb-2">Mot de passe</label>
                <input type="password" id="password" name="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
                <p class="text-gray-600 text-sm mt-1">Le mot de passe doit contenir au moins 6 caractères.</p>
            </div>
            
            <div class="flex justify-between items-center">
                <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition duration-200">
                    S'inscrire
                </button>
                <a href="/auth/login.php" class="text-indigo-600 hover:text-indigo-800 transition duration-200">
                    Déjà un compte?
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Script pour nettoyer le formulaire mais uniquement au chargement initial, pas lors de la soumission -->
<script>
// Uniquement au chargement initial de la page, pas pendant une soumission
if (window.performance && window.performance.navigation.type === 0) {
    // Vider les champs une seule fois au chargement initial
    setTimeout(function() {
        // Vérifier si le formulaire n'est pas en cours de soumission
        if (!document.getElementById('registerForm').classList.contains('submitting')) {
            document.getElementById('username').value = '';
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
        }
    }, 100);
}

// Marquer le formulaire comme en cours de soumission
document.getElementById('registerForm').addEventListener('submit', function() {
    this.classList.add('submitting');
});
</script>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>