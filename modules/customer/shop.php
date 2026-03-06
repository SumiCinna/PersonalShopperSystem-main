<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../customer-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$wishlist_query = "SELECT product_id FROM wishlist WHERE user_id = ?";
$w_stmt = $conn->prepare($wishlist_query);
$w_stmt->bind_param("i", $user_id);
$w_stmt->execute();
$w_result = $w_stmt->get_result();
$user_wishlist = [];
while ($row = $w_result->fetch_assoc()) {
    $user_wishlist[] = $row['product_id'];
}
$w_stmt->close();

$cat_query = "SELECT DISTINCT category FROM products WHERE status = 'active' ORDER BY category ASC";
$categories = $conn->query($cat_query);

$cart_query = "SELECT product_id, quantity FROM cart WHERE user_id = ?";
$c_stmt = $conn->prepare($cart_query);
$c_stmt->bind_param("i", $user_id);
$c_stmt->execute();
$cart_items = $c_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$c_stmt->close();
$user_cart = array_column($cart_items, 'quantity', 'product_id');
$cart_total_items = array_sum($user_cart);

$selected_category = isset($_GET['category']) ? $_GET['category'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT product_id, name, brand, category, price, discount_price, stock, image_url 
        FROM products 
        WHERE status = 'active'";
$params = [];
$types = "";

if (!empty($selected_category)) {
    $sql .= " AND category = ?";
    $params[] = $selected_category;
    $types .= "s";
}

if (!empty($search_query)) {
    $sql .= " AND (name LIKE ? OR brand LIKE ?)";
    $search_term = "%" . $search_query . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

$page_title = 'Shop Groceries';
require_once '../../includes/customer_header.php';
?>

<main class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4 lg:px-8 max-w-7xl">
        
        <div class="mb-8 border-b border-gray-200 pb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-black text-gray-900 tracking-tight">Shop Groceries</h1>
                <p class="text-gray-500 mt-1">Browse our wide selection of fresh and packaged goods.</p>
            </div>
            
            <form action="shop.php" method="GET" class="w-full md:w-96 flex shadow-sm rounded-lg overflow-hidden border border-gray-300">
                <?php if(!empty($selected_category)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                <?php endif; ?>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search products or brands..." class="flex-1 px-4 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 transition font-bold">
                    Search
                </button>
            </form>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <aside class="lg:w-1/4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sticky top-6">
                    <h3 class="font-black text-gray-800 uppercase tracking-wider text-sm mb-4 border-b pb-2">Categories</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="shop.php" class="block px-3 py-2 rounded-lg transition <?php echo empty($selected_category) ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-100'; ?>">
                                All Products
                            </a>
                        </li>
                        <?php if ($categories->num_rows > 0): ?>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <li>
                                    <a href="shop.php?category=<?php echo urlencode($cat['category']); ?>" class="block px-3 py-2 rounded-lg transition <?php echo $selected_category === $cat['category'] ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-100'; ?>">
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </aside>

            <div class="lg:w-3/4">
                
                <div class="mb-4 text-sm text-gray-500 font-bold">
                    Showing <?php echo $products->num_rows; ?> result(s) 
                    <?php if(!empty($selected_category)) echo 'in <span class="text-blue-600">'.htmlspecialchars($selected_category).'</span>'; ?>
                    <?php if(!empty($search_query)) echo 'for <span class="text-blue-600">"'.htmlspecialchars($search_query).'"</span>'; ?>
                </div>

                <?php if ($products->num_rows > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <?php 
                                $is_favorited = in_array($product['product_id'], $user_wishlist);
                                $cart_qty = $user_cart[$product['product_id']] ?? 1;
                            ?>
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col hover:shadow-xl transition-shadow duration-300 relative group">
                                
                                <button onclick="toggleWishlist(<?php echo $product['product_id']; ?>, this)" class="absolute top-3 right-3 p-2 bg-white/90 backdrop-blur rounded-full shadow hover:scale-110 transition z-10 focus:outline-none">
                                    <svg class="w-5 h-5 wishlist-icon transition-colors duration-200 <?php echo $is_favorited ? 'text-red-500' : 'text-gray-400 hover:text-red-400'; ?>" fill="<?php echo $is_favorited ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </button>

                                <div class="h-48 bg-gray-100 flex justify-center items-center overflow-hidden">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="object-cover h-full w-full group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
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
                                        <?php if ($product['stock'] > 0 && $product['stock'] <= 10): ?>
                                            <span class="text-[10px] font-bold text-orange-600 bg-orange-100 px-2 py-1 rounded">Only <?php echo $product['stock']; ?> left!</span>
                                        <?php elseif ($product['stock'] <= 0): ?>
                                            <span class="text-[10px] font-bold text-red-600 bg-red-100 px-2 py-1 rounded">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if($product['stock'] > 0): ?>
                                        <?php 
                                            $in_cart = isset($user_cart[$product['product_id']]);
                                            $btn_text = $in_cart ? 'Update' : 'Add';
                                            $btn_bg = $in_cart ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700';
                                        ?>
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center border border-gray-300 rounded-lg bg-white">
                                                <button onclick="adjustLocalQty(<?php echo $product['product_id']; ?>, -1)" class="px-2 py-1 text-gray-600 hover:bg-gray-100 rounded-l-lg font-bold">-</button>
                                                <input type="number" id="input-qty-<?php echo $product['product_id']; ?>" value="<?php echo $cart_qty; ?>" class="w-12 text-center text-sm font-semibold text-gray-800 focus:outline-none appearance-none" min="0">
                                                <button onclick="adjustLocalQty(<?php echo $product['product_id']; ?>, 1)" class="px-2 py-1 text-gray-600 hover:bg-gray-100 rounded-r-lg font-bold">+</button>
                                            </div>
                                            <button id="btn-add-<?php echo $product['product_id']; ?>" onclick="submitCart(<?php echo $product['product_id']; ?>)" class="flex-1 <?php echo $btn_bg; ?> text-white py-1.5 px-3 rounded-lg transition text-sm font-semibold flex items-center justify-center gap-1 shadow-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                <span id="btn-text-<?php echo $product['product_id']; ?>"><?php echo $btn_text; ?></span>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button disabled class="w-full bg-gray-100 text-gray-400 py-2 rounded-lg cursor-not-allowed text-sm font-semibold">Out of Stock</button>
                                    <?php endif; ?>
                                </div>

                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-16 text-center">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <h2 class="text-2xl font-bold text-gray-800">No products found</h2>
                        <p class="text-gray-500 mt-2">Try adjusting your search or category filter to find what you're looking for.</p>
                        <a href="shop.php" class="inline-block mt-6 bg-blue-50 text-blue-700 font-bold py-2 px-6 rounded-lg transition hover:bg-blue-100">Clear Filters</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<a href="cart.php" id="checkout-fab" class="fixed bottom-6 right-6 z-50 flex items-center gap-3 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-bold px-5 py-3.5 rounded-2xl shadow-2xl transition-all duration-200 group" style="box-shadow: 0 8px 30px rgba(45,91,227,0.45);">
    <div class="relative">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <?php if ($cart_total_items > 0): ?>
            <span id="fab-badge" class="absolute -top-2.5 -right-2.5 bg-yellow-400 text-blue-900 text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-full border-2 border-blue-600"><?php echo $cart_total_items; ?></span>
        <?php else: ?>
            <span id="fab-badge" class="absolute -top-2.5 -right-2.5 bg-yellow-400 text-blue-900 text-[10px] font-black w-5 h-5 items-center justify-center rounded-full border-2 border-blue-600 hidden"></span>
        <?php endif; ?>
    </div>
    <span class="text-sm tracking-wide">View Cart &amp; Checkout</span>
    <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
    </svg>
</a>

<script>
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-24 right-4 z-50 flex flex-col gap-2 pointer-events-none';
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

function adjustLocalQty(productId, delta) {
    const input = document.getElementById('input-qty-' + productId);
    let newQty = (parseInt(input.value) || 0) + delta;
    if (newQty < 0) newQty = 0;
    input.value = newQty;
}

function updateFabBadge(count) {
    const badge = document.getElementById('fab-badge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('hidden');
        badge.classList.add('flex');
    } else {
        badge.classList.add('hidden');
        badge.classList.remove('flex');
    }
}

function submitCart(productId) {
    const input = document.getElementById('input-qty-' + productId);
    const quantity = parseInt(input.value) || 0;
    fetch('../../core/customer/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, action: 'update', quantity: quantity })
    }).then(res => res.json()).then(data => {
        if (data.success) {
            showToast(data.message, 'success');

            const cartLink = document.querySelector('a[href="cart.php"]');
            if (cartLink) {
                let badge = cartLink.querySelector('span');
                if (!badge && data.cart_count > 0) {
                    badge = document.createElement('span');
                    badge.className = 'absolute -top-2 -right-2 bg-yellow-400 text-blue-900 text-[10px] font-black w-4 h-4 flex items-center justify-center rounded-full border border-blue-900';
                    cartLink.appendChild(badge);
                }
                if (badge) badge.textContent = data.cart_count;
                if (data.cart_count === 0 && badge) badge.remove();
            }

            updateFabBadge(data.cart_count);

            const btn = document.getElementById('btn-add-' + productId);
            const btnText = document.getElementById('btn-text-' + productId);
            if (btn && btnText) {
                if (data.quantity > 0) {
                    btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    btn.classList.add('bg-green-600', 'hover:bg-green-700');
                    btnText.textContent = 'Update';
                } else {
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    btnText.textContent = 'Add';
                }
            }
        } else {
            showToast(data.message, 'error');
        }
    }).catch(error => {
        console.error('Error:', error);
        showToast("Action failed. See console for details.", 'error');
    });
}

function toggleWishlist(productId, buttonElement) {
    const icon = buttonElement.querySelector('.wishlist-icon');
    fetch('../../core/customer/toggle_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'error') { showToast(data.message, 'error'); return; }
        if (data.status === 'added') {
            icon.setAttribute('fill', 'currentColor');
            icon.classList.remove('text-gray-400');
            icon.classList.add('text-red-500');
            showToast("Added to Wishlist", 'success');
        } else if (data.status === 'removed') {
            icon.setAttribute('fill', 'none');
            icon.classList.remove('text-red-500');
            icon.classList.add('text-gray-400');
            showToast("Removed from Wishlist", 'success');
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close();
?>