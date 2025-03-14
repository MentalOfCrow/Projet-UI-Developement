<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/controllers/AuthController.php';

$authController = new AuthController();

// Rediriger si déjà connecté
if ($authController->isLoggedIn()) {
    header('Location: /');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->login();
    
    if ($result['success']) {
        $success = $result['message'];
        // Redirection
        header('Location: ' . $result['redirect']);
        exit();
    } else {
        $error = $result['message'];
    }
}

$pageTitle = "Connexion - " . APP_NAME;
?>

<?php include __DIR__ . '/../../backend/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-indigo-600 mb-6">Connexion</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 mb-2">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 mb-2">Mot de passe</label>
                <input type="password" id="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
            </div>
            
            <div class="flex justify-between items-center">
                <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition duration-200">
                    Se connecter
                </button>
                <a href="/auth/register.php" class="text-indigo-600 hover:text-indigo-800 transition duration-200">
                    Créer un compte
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>
