<?php
// customer-login.php

error_reporting(E_ALL);
session_start();

// If already logged in as a customer, send to dashboard
if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'){
    header('Location: modules/customer/home.php');
    exit();
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - Personal Shopper System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen flex items-center justify-center">
    
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-900">Personal Shopper</h1>
            <p class="text-gray-500 mt-2">Customer Portal</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="auth/login.php">
            <input type="hidden" name="login_type" value="customer">

            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Username or Email</label>
                <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-semibold mb-2">Password</label>
                <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition">
                Login to Shop
            </button>
            
            <div class="mt-4 text-center">
                <a href="auth/register.php" class="text-blue-600 hover:underline">Don't have an account? Sign up</a>
            </div>
        </form>
    </div>

</body>
</html>