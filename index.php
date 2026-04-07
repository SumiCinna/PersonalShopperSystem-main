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

require_once 'config/config.php';

$new_arrivals_query = "SELECT product_id, name, brand, category, price, discount_price, stock, image_url
                       FROM products
                       WHERE status = 'active' AND stock > 0
                       ORDER BY product_id DESC
                       LIMIT 4";
$new_arrivals_result = $conn->query($new_arrivals_query);
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

    <section class="py-16 bg-white border-y border-slate-200">
        <div class="container mx-auto px-6">
            <div class="flex justify-between items-end mb-8 border-b border-slate-200 pb-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 tracking-tight">New Arrivals</h2>
                    <p class="text-slate-500 mt-1">Fresh products recently added to our shelves.</p>
                </div>
                <a href="customer-login.php" class="hidden sm:inline-flex text-blue-600 font-bold hover:text-blue-800 items-center transition">
                    View All Products
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>

            <?php if ($new_arrivals_result && $new_arrivals_result->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php while ($product = $new_arrivals_result->fetch_assoc()): ?>
                        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col hover:shadow-xl transition-shadow duration-300 group">
                            <div class="h-48 bg-slate-100 flex justify-center items-center overflow-hidden relative">
                                <div class="absolute top-2 left-2 bg-yellow-400 text-blue-900 text-[10px] font-black px-2 py-1 rounded z-10 uppercase tracking-widest shadow-sm">NEW</div>

                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="object-contain h-full w-full p-2 group-hover:scale-105 transition-transform duration-500">
                                <?php else: ?>
                                    <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <?php endif; ?>
                            </div>

                            <div class="p-5 flex-1 flex flex-col">
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h3 class="font-bold text-slate-900 leading-tight mb-1"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="text-xs text-slate-500 mb-3"><?php echo htmlspecialchars($product['brand']); ?></p>

                                <div class="mt-auto mb-4">
                                    <?php if ($product['discount_price'] > 0 && $product['discount_price'] < $product['price']): ?>
                                        <p class="text-xs text-slate-400 line-through font-semibold">₱<?php echo number_format($product['price'], 2); ?></p>
                                        <p class="text-2xl font-black text-red-600">₱<?php echo number_format($product['discount_price'], 2); ?></p>
                                    <?php else: ?>
                                        <p class="text-2xl font-black text-green-700">₱<?php echo number_format($product['price'], 2); ?></p>
                                    <?php endif; ?>
                                </div>

                                <a href="customer-login.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition flex justify-center items-center">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    Add to Cart
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="bg-white border border-slate-200 rounded-xl p-8 text-center">
                    <p class="text-slate-500">No new arrivals yet. Please check back soon.</p>
                </div>
            <?php endif; ?>

            <div class="mt-8 text-center sm:hidden">
                <a href="customer-login.php" class="inline-block bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold py-3 px-8 rounded-lg transition w-full">View All Products</a>
            </div>
        </div>
    </section>

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
                
            </div>
            <div class="border-t border-slate-800 mt-10 pt-8 text-center text-xs text-slate-600">
                &copy; <?php echo date('Y'); ?> Personal Shopper System. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
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