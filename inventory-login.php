<?php
// inventory-login.php
session_start();

// If user is already logged in as inventory, redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'inventory') {
    header("Location: modules/inventory/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management Login - PSS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center font-sans antialiased">

    <div class="max-w-md w-full mx-4">
        
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
            
            <div class="bg-blue-900 p-6 text-center">
                <div class="mx-auto w-16 h-16 bg-blue-800 rounded-full flex items-center justify-center mb-4 border-2 border-blue-700 shadow-inner">
                    <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                </div>
                <h2 class="text-2xl font-bold tracking-wider text-white">PSS Inventory</h2>
                <p class="text-blue-300 text-sm mt-1">Staff / Inventory Manager Access</p>
            </div>

            <div class="p-8">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded text-sm font-semibold">
                        <?php 
                            echo htmlspecialchars($_SESSION['error']); 
                            unset($_SESSION['error']); // Clear it after showing
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Session Expired Message -->
                <?php if (isset($_GET['expired']) && $_GET['expired'] == 1): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 mb-6 rounded text-sm font-semibold">
                        <p class="font-semibold">Session Expired</p>
                        <p class="text-xs mt-1">Your session has expired due to inactivity. Please log in again to continue.</p>
                    </div>
                <?php endif; ?>

                <form action="auth/login.php" method="POST" class="space-y-6">
                    
                    <input type="hidden" name="login_type" value="inventory">

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Staff Username / Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <input type="text" name="username" required class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition shadow-sm" placeholder="Enter your staff ID">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Inventory Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <input type="password" name="password" required class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition shadow-sm" placeholder="••••••••">
                            <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="togglePw(this)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition shadow-md flex justify-center items-center">
                        Access Inventory System
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>
            </div>
            
            <div class="bg-gray-50 border-t border-gray-100 p-4 text-center">
                <a href="index.php" class="text-sm text-gray-500 hover:text-blue-700 font-semibold transition flex items-center justify-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Return to Main Menu
                </a>
            </div>
        </div>
        
    </div>

    <script>
        function togglePw(btn) {
            var inp = btn.closest('.relative').querySelector('input');
            inp.type = inp.type === 'password' ? 'text' : 'password';
            btn.innerHTML = inp.type === 'password' ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        }
    </script>

</body>
</html>
