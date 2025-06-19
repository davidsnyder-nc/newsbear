<?php
session_start();
require_once 'includes/AuthManager.php';

$auth = new AuthManager();
$error = '';

// If authentication is disabled, redirect to home
if (!$auth->isAuthEnabled()) {
    header('Location: index.php');
    exit;
}

// If already logged in, redirect to home
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if ($auth->authenticate($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NewsBear</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="text-center">
            <img src="attached_assets/newsbear_brown_logo.png" alt="NewsBear" class="mx-auto h-16 w-auto">
            <h1 class="mt-2 text-3xl font-bold text-newsbear-brown">NewsBear</h1>
            <p class="mt-2 text-sm text-gray-600">Sign in to access your news briefings</p>
        </div>
        
        <div class="bg-white shadow-lg rounded-lg p-8">
            <form method="POST" class="space-y-6">
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" id="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-newsbear-brown focus:border-transparent"
                           placeholder="Enter your username">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-newsbear-brown focus:border-transparent"
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="w-full bg-newsbear-brown hover:bg-opacity-90 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
            </form>
        </div>
        
        <div class="text-center">
            <a href="index.php" class="text-sm text-gray-600 hover:text-newsbear-brown">
                <i class="fas fa-arrow-left mr-1"></i>Back to Home
            </a>
        </div>
    </div>
</body>
</html>