<?php
// modules/customer/home.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../customer-login.php");
    exit();
}

// Fetch 4 "Featured" or "New Arrival" products to tease the customer
$featured_query = "SELECT product_id, name, brand, category, price, discount_price, stock, image_url 
                   FROM products 
                   WHERE status = 'active' AND stock > 0 
                   ORDER BY product_id DESC LIMIT 4";
$featured_result = $conn->query($featured_query);

$page_title = 'Welcome to PSS Grocery';
require_once '../../includes/customer_header.php';
?>

<main class="bg-gray-50 min-h-screen">
    
    <section class="bg-gradient-to-r from-blue-900 to-blue-700 text-white relative overflow-hidden">
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
        
        <div class="container mx-auto px-6 py-20 lg:py-28 max-w-7xl relative z-10 flex flex-col md:flex-row items-center">
            <div class="md:w-1/2 text-center md:text-left mb-10 md:mb-0">
                <span class="inline-block py-1 px-3 rounded-full bg-blue-800 text-blue-200 text-sm font-bold tracking-widest uppercase mb-4 border border-blue-600">Fresh & Fast</span>
                <h1 class="text-4xl md:text-6xl font-black leading-tight mb-6">
                    Groceries delivered<br>to your doorstep.
                </h1>
                <p class="text-lg text-blue-100 mb-8 max-w-lg mx-auto md:mx-0">
                    Skip the long lines. Shop from our wide selection of daily essentials, fresh produce, and household items right from your phone.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
                    <a href="shop.php" class="bg-yellow-400 hover:bg-yellow-500 text-blue-900 font-black px-8 py-4 rounded-lg shadow-lg transition transform hover:-translate-y-1 text-lg flex justify-center items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        Start Shopping
                    </a>
                    <a href="shop.php?category=Snacks" class="bg-blue-800 hover:bg-blue-900 text-white border border-blue-600 font-bold px-8 py-4 rounded-lg shadow transition flex justify-center items-center">
                        View Promos
                    </a>
                </div>
            </div>
            
            <div class="md:w-1/2 flex justify-center">
                <div class="w-full max-w-md bg-white/10 backdrop-blur-sm p-6 rounded-2xl border border-white/20 shadow-2xl transform rotate-2 hover:rotate-0 transition duration-500">
                    <div class="flex justify-between items-center border-b border-white/20 pb-4 mb-4">
                        <span class="font-bold">Your Cart</span>
                        <span class="bg-yellow-400 text-blue-900 text-xs font-black px-2 py-1 rounded">3 Items</span>
                    </div>
                    <div class="space-y-3 opacity-80">
                        <div class="h-12 bg-white/20 rounded flex items-center px-4"><div class="h-6 w-6 bg-white/40 rounded mr-3"></div><div class="h-2 w-24 bg-white/40 rounded"></div></div>
                        <div class="h-12 bg-white/20 rounded flex items-center px-4"><div class="h-6 w-6 bg-white/40 rounded mr-3"></div><div class="h-2 w-32 bg-white/40 rounded"></div></div>
                        <div class="h-12 bg-white/20 rounded flex items-center px-4"><div class="h-6 w-6 bg-white/40 rounded mr-3"></div><div class="h-2 w-20 bg-white/40 rounded"></div></div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/20 flex justify-between">
                        <span class="font-bold">Total:</span>
                        <span class="font-black text-yellow-400">₱450.00</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 bg-white border-b border-gray-200">
        <div class="container mx-auto px-6 max-w-7xl">
            <h2 class="text-2xl font-black text-gray-800 mb-8 text-center uppercase tracking-wider">Shop by Category</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8">
                <a href="shop.php?category=Canned%20Goods" class="group flex flex-col items-center p-6 bg-red-50 rounded-2xl hover:bg-red-100 transition border border-red-100">
                    <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 text-red-500 group-hover:scale-110 transition transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                    </div>
                    <span class="font-bold text-gray-800">Canned Goods</span>
                </a>
                
                <a href="shop.php?category=Beverages" class="group flex flex-col items-center p-6 bg-blue-50 rounded-2xl hover:bg-blue-100 transition border border-blue-100">
                    <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 text-blue-500 group-hover:scale-110 transition transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <span class="font-bold text-gray-800">Beverages</span>
                </a>

                <a href="shop.php?category=Snacks" class="group flex flex-col items-center p-6 bg-yellow-50 rounded-2xl hover:bg-yellow-100 transition border border-yellow-100">
                    <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 text-yellow-500 group-hover:scale-110 transition transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                    </div>
                    <span class="font-bold text-gray-800">Snacks</span>
                </a>

                <a href="shop.php?category=Personal%20Care" class="group flex flex-col items-center p-6 bg-purple-50 rounded-2xl hover:bg-purple-100 transition border border-purple-100">
                    <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mb-4 text-purple-500 group-hover:scale-110 transition transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path></svg>
                    </div>
                    <span class="font-bold text-gray-800">Personal Care</span>
                </a>
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="container mx-auto px-6 max-w-7xl">
            <div class="flex justify-between items-end mb-8 border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">New Arrivals</h2>
                    <p class="text-gray-500 mt-1">Check out the latest additions to our shelves.</p>
                </div>
                <a href="shop.php" class="hidden sm:inline-flex text-blue-600 font-bold hover:text-blue-800 items-center transition">
                    View All Products <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>

            <?php if ($featured_result->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php while ($product = $featured_result->fetch_assoc()): ?>
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col hover:shadow-xl transition-shadow duration-300 group">
                            
                            <div class="h-48 bg-gray-100 flex justify-center items-center overflow-hidden relative">
                                <div class="absolute top-2 left-2 bg-yellow-400 text-blue-900 text-[10px] font-black px-2 py-1 rounded z-10 uppercase tracking-widest shadow-sm">NEW</div>
                                
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="object-cover h-full w-full group-hover:scale-105 transition-transform duration-500">
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-5 flex-1 flex flex-col">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h3 class="font-bold text-gray-900 leading-tight mb-1 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="text-xs text-gray-500 mb-3"><?php echo htmlspecialchars($product['brand']); ?></p>
                                
                                <div class="mt-auto flex justify-between items-end mb-4">
                                    <div>
                                        <?php if ($product['discount_price'] > 0 && $product['discount_price'] < $product['price']): ?>
                                            <p class="text-xs text-gray-400 line-through font-semibold">₱<?php echo number_format($product['price'], 2); ?></p>
                                            <p class="text-2xl font-black text-red-600">₱<?php echo number_format($product['discount_price'], 2); ?></p>
                                        <?php else: ?>
                                            <p class="text-2xl font-black text-green-700">₱<?php echo number_format($product['price'], 2); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <button onclick="addToCart(<?php echo $product['product_id']; ?>)" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition flex justify-center items-center">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No featured products available at the moment.</p>
            <?php endif; ?>
            
            <div class="mt-8 text-center sm:hidden">
                <a href="shop.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-8 rounded-lg transition w-full">View All Products</a>
            </div>
        </div>
    </section>

</main>

<script>
    function showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-gray-800' : 'bg-red-600';
        toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-3 transform transition-all duration-300 translate-y-4 opacity-0 pointer-events-auto min-w-[250px]`;
        
        const icon = type === 'success' 
            ? '<svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';

        toast.innerHTML = `${icon}<span class="font-medium text-sm">${message}</span>`;

        container.appendChild(toast);

        requestAnimationFrame(() => toast.classList.remove('translate-y-4', 'opacity-0'));

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function addToCart(productId) {
        fetch('../../core/customer/add_to_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, action: 'add' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                // Update cart count in header
                const cartLink = document.querySelector('a[href="cart.php"]');
                if (cartLink) {
                    let badge = cartLink.querySelector('span');
                    if (!badge && data.cart_count > 0) {
                        badge = document.createElement('span');
                        badge.className = 'absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full';
                        cartLink.appendChild(badge);
                    }
                    if (badge) badge.textContent = data.cart_count;
                    if (data.cart_count === 0 && badge) badge.remove();
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred.', 'error');
        });
    }
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close();
?>