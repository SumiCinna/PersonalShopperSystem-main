<?php
session_start();

if(isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if($_SESSION['role'] === 'admin') {
        header('Location: modules/admin/dashboard.php');
        exit();
    } elseif($_SESSION['role'] === 'cashier') {
        header('Location: modules/cashier/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSS Staff Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html { scroll-behavior: smooth; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.5s ease both; }
        .fade-up-1 { animation-delay: 0.1s; }
        .fade-up-2 { animation-delay: 0.2s; }
        .fade-up-3 { animation-delay: 0.3s; }
    </style>
</head>
<body class="bg-slate-950 font-sans text-slate-200 min-h-screen flex flex-col">

    <nav class="border-b border-slate-800 bg-slate-950/80 backdrop-blur-sm sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-slate-800 p-1.5 rounded-lg border border-slate-700">
                    <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <span class="text-sm font-bold text-white tracking-tight">PSS Staff Portal</span>
                    <span class="ml-2 text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full border border-slate-700">Internal Access</span>
                </div>
            </div>
            <a href="index.php" class="text-sm text-slate-400 hover:text-white transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Customer Site
            </a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col items-center justify-center px-6 py-20">

        <div class="text-center mb-14 fade-up">
            <div class="inline-flex items-center gap-2 bg-slate-800 border border-slate-700 text-slate-400 text-xs font-semibold px-3 py-1.5 rounded-full mb-5 uppercase tracking-widest">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Authorized Personnel Only
            </div>
            <h1 class="text-4xl md:text-5xl font-black text-white mb-4 leading-tight">
                Staff Access Portal
            </h1>
            <p class="text-slate-400 text-lg max-w-md mx-auto leading-relaxed">
                Select your role below to access your dedicated dashboard.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-4xl">

            <div class="fade-up fade-up-1 group bg-slate-900 border border-slate-800 hover:border-slate-500/50 rounded-2xl p-8 flex flex-col items-center text-center transition-all duration-300 hover:shadow-2xl hover:shadow-slate-500/10 hover:-translate-y-1">
                <div class="w-16 h-16 bg-slate-500/10 border border-slate-500/20 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-slate-500/20 transition-colors duration-300">
                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Administrator</h3>
                <p class="text-sm text-slate-500 mb-7 leading-relaxed">Full system access. Manage users, products, orders, reports, and system settings.</p>
                <a href="admin-login.php" class="w-full bg-slate-600 hover:bg-slate-500 text-white text-sm font-bold py-3 px-6 rounded-xl transition-colors duration-200 flex items-center justify-center gap-2">
                    Admin Login
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>

            <div class="fade-up fade-up-2 group bg-slate-900 border border-slate-800 hover:border-emerald-500/50 rounded-2xl p-8 flex flex-col items-center text-center transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/10 hover:-translate-y-1">
                <div class="w-16 h-16 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-emerald-500/20 transition-colors duration-300">
                    <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Cashier</h3>
                <p class="text-sm text-slate-500 mb-7 leading-relaxed">Process transactions, handle customer pickups, and manage point-of-sale operations.</p>
                <a href="cashier-login.php" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold py-3 px-6 rounded-xl transition-colors duration-200 flex items-center justify-center gap-2">
                    Cashier Login
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>

            <div class="fade-up fade-up-3 group bg-slate-900 border border-slate-800 hover:border-blue-500/50 rounded-2xl p-8 flex flex-col items-center text-center transition-all duration-300 hover:shadow-2xl hover:shadow-blue-500/10 hover:-translate-y-1">
                <div class="w-16 h-16 bg-blue-500/10 border border-blue-500/20 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-blue-500/20 transition-colors duration-300">
                    <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Inventory</h3>
                <p class="text-sm text-slate-500 mb-7 leading-relaxed">Monitor stock levels, update product listings, and manage incoming and outgoing goods.</p>
                <a href="inventory-login.php" class="w-full bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold py-3 px-6 rounded-xl transition-colors duration-200 flex items-center justify-center gap-2">
                    Inventory Login
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>

        </div>

    </main>

    <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-600">
        &copy; <?php echo date('Y'); ?> Personal Shopper System &mdash; Staff Portal. All rights reserved.
    </footer>

</body>
</html>