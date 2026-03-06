<?php
session_start();

if(isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if($_SESSION['role'] === 'admin') {
        header('Location: modules/admin/dashboard.php');
        exit();
    } elseif($_SESSION['role'] === 'cashier') {
        header('Location: modules/cashier/dashboard.php');
        exit();
    } elseif($_SESSION['role'] === 'customer') {
        header('Location: modules/customer/home.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to PSS - Personal Shopper System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html { scroll-behavior: smooth; }

        @keyframes blob {
            0%   { transform: translate(0px, 0px) scale(1); }
            33%  { transform: translate(30px, -50px) scale(1.1); }
            66%  { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <div class="bg-blue-600 text-white p-1.5 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
                <span class="text-xl font-bold text-slate-800 tracking-tight">PSS Grocery</span>
            </div>

            <div class="hidden md:flex items-center space-x-8">
                <a href="#features" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Features</a>

                <div class="relative" id="staff-menu">
                    <button class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition flex items-center outline-none select-none">
                        Staff Access
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div id="staff-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 overflow-hidden z-50" style="display:none;">
                        <a href="admin-login.php" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600 border-b border-slate-100">Admin Portal</a>
                        <a href="cashier-login.php" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600">Cashier Terminal</a>
                    </div>
                </div>

                <a href="customer-login.php" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold py-2.5 px-6 rounded-lg transition shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                    Login / Sign Up
                </a>
            </div>
        </div>
    </nav>

    <header class="relative bg-white overflow-hidden">
        <div class="container mx-auto px-6 py-20 md:py-32 flex flex-col-reverse md:flex-row items-center">
            
            <div class="w-full md:w-1/2 pr-0 md:pr-12 text-center md:text-left mt-12 md:mt-0">
                <div class="inline-block bg-blue-50 text-blue-600 text-xs font-bold px-3 py-1 rounded-full mb-6 uppercase tracking-wider">
                    Personal Shopper System
                </div>
                <h1 class="text-4xl md:text-6xl font-black text-slate-900 leading-tight mb-6">
                    Your groceries, <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-500">personally picked.</span>
                </h1>
                <p class="text-lg text-slate-500 mb-8 leading-relaxed max-w-lg mx-auto md:mx-0">
                    Skip the lines and let us handle the shopping. Browse our inventory, place your order, and pick it up when it's ready.
                </p>
                <div class="flex flex-col sm:flex-row justify-center md:justify-start gap-4">
                    <a href="customer-login.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-xl shadow-lg transition transform hover:-translate-y-1 text-center flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Start Shopping Now
                    </a>
                    <a href="auth/register.php" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold py-4 px-8 rounded-xl shadow-sm transition text-center">
                        Create Account
                    </a>
                </div>
            </div>

            <div class="w-full md:w-1/2 flex justify-center">
                <div class="relative w-full max-w-lg">
                    <div class="absolute top-0 -left-4 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
                    <div class="absolute top-0 -right-4 w-72 h-72 bg-yellow-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
                    <div class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
                    
                    <div class="relative bg-white bg-opacity-80 backdrop-blur-sm rounded-2xl shadow-2xl border border-white p-6 transform rotate-2 hover:rotate-0 transition duration-500">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <div class="h-2.5 w-32 bg-slate-200 rounded-full mb-2"></div>
                                <div class="h-2 w-24 bg-slate-100 rounded-full"></div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="h-20 bg-slate-100 rounded-lg w-full"></div>
                            <div class="flex justify-between">
                                <div class="h-2 w-12 bg-slate-200 rounded-full"></div>
                                <div class="h-2 w-12 bg-slate-200 rounded-full"></div>
                            </div>
                        </div>
                        <div class="mt-6 pt-6 border-t border-slate-100">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-bold text-slate-800">Order Ready</span>
                                <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded">Pickup</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="features" class="py-24 bg-slate-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-black text-slate-900 mb-4">Why shop with PSS?</h2>
                <p class="text-slate-500 text-lg">We've streamlined the grocery experience to save you time and ensure you get exactly what you need.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition duration-300">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Live Inventory</h3>
                    <p class="text-slate-500 leading-relaxed">Our system tracks stock in real-time. If you can add it to your cart, it's on our shelves. No more substitutions.</p>
                </div>
                
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition duration-300">
                    <div class="w-14 h-14 bg-green-50 text-green-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Quick Pickup</h3>
                    <p class="text-slate-500 leading-relaxed">Place your order online and we'll pack it for you. Just show your order ID at the counter and go.</p>
                </div>
                
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition duration-300">
                    <div class="w-14 h-14 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Secure Payments</h3>
                    <p class="text-slate-500 leading-relaxed">Pay via GCash for a contactless experience, or pay with cash when you pick up your items.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-6 md:mb-0 text-center md:text-left">
                    <span class="text-2xl font-bold text-white tracking-tight">PSS Grocery</span>
                    <p class="mt-2 text-sm text-slate-500">Making shopping easier, one order at a time.</p>
                </div>
                <div class="flex space-x-8 text-sm font-semibold">
                    <a href="customer-login.php" class="hover:text-white transition">Shop</a>
                    <a href="admin-login.php" class="hover:text-white transition">Admin</a>
                    <a href="cashier-login.php" class="hover:text-white transition">Cashier</a>
                </div>
            </div>
            <div class="border-t border-slate-800 mt-10 pt-8 text-center text-xs text-slate-600">
                &copy; <?php echo date('Y'); ?> Personal Shopper System. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        var menu = document.getElementById('staff-menu');
        var dropdown = document.getElementById('staff-dropdown');
        var hideTimer;

        menu.addEventListener('mouseenter', function() {
            clearTimeout(hideTimer);
            dropdown.style.display = 'block';
        });

        menu.addEventListener('mouseleave', function() {
            hideTimer = setTimeout(function() {
                dropdown.style.display = 'none';
            }, 1500);
        });

        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                var target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>

</body>
</html>