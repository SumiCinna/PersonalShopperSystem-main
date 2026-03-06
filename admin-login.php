<?php
// admin-login.php (Root Folder)
session_start();

// If already logged in as an admin, send them straight to the dashboard
if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'){
    header('Location: modules/admin/dashboard.php');
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
    <title>Admin Portal - Personal Shopper System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center">
    
    <div class="max-w-md w-full bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="bg-slate-800 p-6 text-center">
            <div class="w-16 h-16 bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-3 shadow-inner">
                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">Admin Portal</h1>
            <p class="text-slate-400 text-sm mt-1">Authorized Personnel Only</p>
        </div>
        
        <div class="p-8">
            <?php if($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 text-sm" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="auth/login.php">
                <input type="hidden" name="login_type" value="admin">

                <div class="mb-5">
                    <label class="block text-slate-700 text-sm font-bold mb-2">Username or Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <input type="text" name="username" class="w-full pl-10 pr-3 py-3 border border-slate-300 rounded focus:outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-500 transition-colors" placeholder="admin" required>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-slate-700 text-sm font-bold mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <input type="password" name="password" class="w-full pl-10 pr-3 py-3 border border-slate-300 rounded focus:outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-500 transition-colors" placeholder="••••••••" required>
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 px-4 rounded transition-colors flex items-center justify-center">
                    Secure Login
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="index.php" class="text-slate-500 text-sm hover:text-slate-700 transition-colors">← Back to Customer Store</a>
            </div>
        </div>
    </div>

</body>
</html>