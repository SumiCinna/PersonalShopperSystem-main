<?php
// includes/customer_header.php
if(!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// Quick query to get the number of items in the user's cart for the notification badge
$cart_count = 0;
if(isset($_SESSION['user_id'])) {
    $cart_stmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $cart_stmt->bind_param("i", $_SESSION['user_id']);
    $cart_stmt->execute();
    $cart_stmt->bind_result($cart_count);
    $cart_stmt->fetch();
    $cart_stmt->close();
    
    // If cart is empty, make it 0 instead of null
    $cart_count = $cart_count ? $cart_count : 0; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - PSS' : 'Personal Shopper System'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen font-sans antialiased">

    <nav class="bg-blue-900 text-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            
            <a href="home.php" class="text-2xl font-bold tracking-wider flex items-center">
                <svg class="w-8 h-8 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                PSS <span class="text-blue-300 font-light ml-2 text-sm hidden sm:block">Member Shop</span>
            </a>

            <div class="flex items-center gap-4 sm:gap-6">
                
                <!-- Home -->
                <a href="home.php" class="flex flex-col items-center text-blue-200 hover:text-white transition group" title="Home">
                    <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-0.5 hidden sm:block">Home</span>
                </a>

                <!-- Shop -->
                <a href="shop.php" class="flex flex-col items-center text-blue-200 hover:text-white transition group" title="Shop Catalog">
                    <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-0.5 hidden sm:block">Shop</span>
                </a>

                <!-- Wishlist -->
                <a href="wishlist.php" class="flex flex-col items-center text-blue-200 hover:text-white transition group" title="My Wishlist">
                    <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-0.5 hidden sm:block">Saved</span>
                </a>

                <!-- Cart -->
                <a href="cart.php" class="flex flex-col items-center text-blue-200 hover:text-white transition group relative" title="My Cart">
                    <div class="relative">
                        <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <?php if($cart_count > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-yellow-400 text-blue-900 text-[10px] font-black w-4 h-4 flex items-center justify-center rounded-full border border-blue-900">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-0.5 hidden sm:block">Cart</span>
                </a>

                <!-- Orders -->
                <a href="orders.php" class="flex flex-col items-center text-blue-200 hover:text-white transition group" title="My Orders">
                    <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-0.5 hidden sm:block">Orders</span>
                </a>
                
                <!-- Divider -->
                <div class="h-8 w-px bg-blue-800 mx-1 hidden sm:block"></div>

                <!-- Customer Dropdown -->
                <div class="relative">
                    <button onclick="toggleUserMenu()" class="flex items-center gap-2 text-white hover:text-yellow-400 transition focus:outline-none group">
                        <div class="w-8 h-8 bg-blue-700 rounded-full flex items-center justify-center border border-blue-500 group-hover:border-yellow-400 transition">
                            <span class="font-bold text-sm"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                        </div>
                        <div class="hidden sm:block text-left">
                            <span class="block text-xs font-bold leading-none"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <span class="block text-[10px] text-blue-300 leading-none mt-0.5">Account</span>
                        </div>
                        <svg class="w-3 h-3 text-blue-300 group-hover:text-yellow-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div id="userMenuDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 hidden overflow-hidden z-50 text-left">
                        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                            <p class="text-xs text-gray-500">Signed in as</p>
                            <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        </div>
                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                            My Profile
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <a href="../../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition font-semibold">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('userMenuDropdown');
            menu.classList.toggle('hidden');
        }
        
        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            const button = document.querySelector('button[onclick="toggleUserMenu()"]');
            const menu = document.getElementById('userMenuDropdown');
            if (button && menu && !button.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>