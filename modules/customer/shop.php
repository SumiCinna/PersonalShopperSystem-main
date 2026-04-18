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

// Fetch distinct categories from products
$all_categories = [];
$cat_query = "SELECT DISTINCT category FROM products WHERE status = 'active'";
$res = $conn->query($cat_query);
while ($row = $res->fetch_assoc()) {
    if (!empty($row['category'])) {
        $all_categories[] = $row['category'];
    }
}

// Fetch categories from suppliers JSON
$sup_query = "SELECT supplied_categories FROM suppliers";
$s_res = $conn->query($sup_query);
if ($s_res) {
    while ($row = $s_res->fetch_assoc()) {
        if (!empty($row['supplied_categories'])) {
            $decoded = json_decode($row['supplied_categories'], true);
            if (is_array($decoded)) {
                $all_categories = array_merge($all_categories, $decoded);
            }
        }
    }
}

// Add our default core categories just in case
$default_cats = [
    'Beverages','Canned Goods','Condiments','Dairy',
    'Fresh Produce','Noodles','Snacks','Cooking Essentials',
    'Meat & Poultry'
];
$all_categories = array_unique(array_filter(array_merge($all_categories, $default_cats)));
sort($all_categories);

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

$sql = "SELECT product_id, name, brand, category, price, discount_price, stock, image_url, description 
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

<style>
    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .qty-input[type=number] {
        -moz-appearance: textfield;
        appearance: textfield;
    }
</style>

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
                        <?php if (!empty($all_categories)): ?>
                            <?php foreach ($all_categories as $cat): ?>
                                <li>
                                    <a href="shop.php?category=<?php echo urlencode($cat); ?>" class="block px-3 py-2 rounded-lg transition <?php echo $selected_category === $cat ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-100'; ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
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

                                // Fetch active batches for FEFO display
                                $p_id = (int)$product['product_id'];
                                $batchQuery = "SELECT batch_number, manufacture_date, expiry_date, remaining_quantity 
                                              FROM product_batches 
                                              WHERE product_id = $p_id
                                              AND status = 'Released' 
                                              AND remaining_quantity > 0 
                                              ORDER BY expiry_date ASC";
                                $batchRes = $conn->query($batchQuery);
                                $batches = [];
                                if ($batchRes) {
                                    while($bRow = $batchRes->fetch_assoc()) {
                                        $batches[] = $bRow;
                                    }
                                }
                                $product['batches'] = $batches;
                            ?>
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col hover:shadow-xl transition-shadow duration-300 relative group">
                                
                                <button onclick="toggleWishlist(<?php echo $product['product_id']; ?>, this)" class="absolute top-3 right-3 p-2 bg-white/90 backdrop-blur rounded-full shadow hover:scale-110 transition z-10 focus:outline-none">
                                    <svg class="w-5 h-5 wishlist-icon transition-colors duration-200 <?php echo $is_favorited ? 'text-red-500' : 'text-gray-400 hover:text-red-400'; ?>" fill="<?php echo $is_favorited ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </button>

                                <div onclick="openProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="h-48 w-full bg-gray-100 flex justify-center items-center overflow-hidden cursor-pointer group-hover:opacity-95 transition">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="object-contain h-full w-full p-2 transition-transform duration-300 group-hover:scale-105">
                                    <?php else: ?>
                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-5 flex-1 flex flex-col">
                                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($product['category']); ?></div>
                                    <h3 onclick="openProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" title="Click for details" class="font-bold text-gray-900 leading-tight mb-1 line-clamp-2 cursor-pointer hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($product['name']); ?></h3>
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
                                        <?php if ($product['stock'] > 0): ?>
                                            <?php $stock_class = $product['stock'] <= 10 ? 'text-orange-600 bg-orange-100' : 'text-emerald-700 bg-emerald-100'; ?>
                                            <span class="text-[13px] font-bold px-2 py-1 rounded <?php echo $stock_class; ?>">
                                                <?php echo $product['stock']; ?> left
                                            </span>
                                        <?php else: ?>
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
                                                <input type="number" id="input-qty-<?php echo $product['product_id']; ?>" value="<?php echo $cart_qty; ?>" class="qty-input w-12 text-center text-sm font-semibold text-gray-800 focus:outline-none appearance-none" min="0" max="<?php echo (int)$product['stock']; ?>" oninput="enforceQtyLimit(<?php echo $product['product_id']; ?>)">
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

<!-- Product Details Modal -->
<div id="product-modal" tabindex="-1" aria-hidden="true" class="fixed inset-0 z-[60] hidden w-full p-4 overflow-x-hidden overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex justify-center items-center opacity-0 transition-opacity duration-300">
    <div class="relative w-full max-w-5xl bg-white rounded-2xl shadow-2xl transform scale-95 transition-transform duration-300" id="product-modal-content">
        
        <!-- Modal header -->
        <button type="button" onclick="closeProductModal()" class="absolute top-4 right-4 text-gray-400 bg-transparent hover:bg-gray-100 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition z-10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Modal body -->
        <div class="p-6 md:p-8 flex flex-col lg:flex-row gap-8">
            
            <!-- Left: Image -->
            <div class="w-full lg:w-1/3 flex justify-center items-center bg-gray-50 rounded-xl p-4">
                <img id="modal-product-img" src="" alt="Product Image" class="max-h-72 object-contain mix-blend-multiply">
                <svg id="modal-product-img-fallback" class="w-32 h-32 text-gray-300 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            
            <!-- Middle: Product Info -->
            <div class="w-full lg:w-1/3 flex flex-col justify-center border-r border-gray-100 pr-4">
                <div class="flex items-center gap-2 mb-2">
                    <span id="modal-product-category" class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2.5 py-0.5 rounded uppercase tracking-wider">Category</span>
                    <span id="modal-product-brand" class="text-[11px] text-gray-500 font-semibold tracking-wide">Brand</span>
                </div>
                
                <h3 id="modal-product-name" class="text-2xl font-black text-gray-900 leading-tight mb-4">Product Name</h3>
                
                <div class="mb-4">
                    <p id="modal-product-discount-price" class="text-3xl font-black text-red-600 hidden">₱0.00</p>
                    <div class="flex items-end gap-2">
                        <p id="modal-product-price" class="text-3xl font-black text-green-700">₱0.00</p>
                        <p id="modal-product-original-price" class="text-sm font-bold tracking-wide text-gray-400 line-through hidden mb-1.5">₱0.00</p>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-3 mb-6 border border-gray-100">
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-widest mb-1.5">Description</h4>
                    <p id="modal-product-desc" class="text-sm text-gray-600 leading-relaxed max-h-40 overflow-hidden overflow-y-auto pr-2">No description available.</p>
                </div>
                
                <div class="mt-auto flex items-center justify-between">
                    <span id="modal-product-stock" class="text-sm font-bold bg-gray-100 px-3 py-1 rounded text-gray-700">0 left in stock</span>
                </div>
            </div>

            <!-- Right: Batches -->
            <div class="w-full lg:w-1/3 flex flex-col">
                <div id="modal-product-batches-container" class="h-full flex flex-col hidden">
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b pb-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Stock Expiration Breakdown
                    </h4>
                    <div id="modal-product-batches" class="flex flex-col gap-2 flex-1 overflow-y-auto pr-1">
                        <!-- Batches injected here via JS -->
                    </div>
                </div>
                <div id="modal-product-no-batches" class="hidden h-full flex flex-col items-center justify-center text-center p-6 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                     <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                     <p class="text-sm font-semibold text-gray-500">No active batches</p>
                </div>
            </div>

        </div>
    </div>
</div>

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
    const maxQty = parseInt(input.max, 10);
    let newQty = (parseInt(input.value, 10) || 0) + delta;
    if (newQty < 0) newQty = 0;
    if (!Number.isNaN(maxQty) && maxQty >= 0 && newQty > maxQty) newQty = maxQty;
    input.value = newQty;
}

function enforceQtyLimit(productId) {
    const input = document.getElementById('input-qty-' + productId);
    if (!input) return;

    const maxQty = parseInt(input.max, 10);
    let qty = parseInt(input.value, 10);

    if (Number.isNaN(qty) || qty < 0) qty = 0;
    if (!Number.isNaN(maxQty) && maxQty >= 0 && qty > maxQty) qty = maxQty;

    input.value = qty;
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
    const maxQty = parseInt(input.max, 10);
    let quantity = parseInt(input.value, 10);
    if (Number.isNaN(quantity) || quantity < 0) quantity = 0;
    if (!Number.isNaN(maxQty) && maxQty >= 0 && quantity > maxQty) quantity = maxQty;
    input.value = quantity;
    fetch('../../core/customer/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, action: 'update', quantity: quantity })
    }).then(res => res.json()).then data => {
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

// --- Product Modal Logic ---
const modalEl = document.getElementById('product-modal');
const modalContent = document.getElementById('product-modal-content');

function openProductModal(product) {
    // Populate Image
    if (product.image_url) {
        document.getElementById('modal-product-img').src = product.image_url;
        document.getElementById('modal-product-img').classList.remove('hidden');
        document.getElementById('modal-product-img-fallback').classList.add('hidden');
    } else {
        document.getElementById('modal-product-img').classList.add('hidden');
        document.getElementById('modal-product-img-fallback').classList.remove('hidden');
    }

    // Populate Details
    document.getElementById('modal-product-name').textContent = product.name;
    document.getElementById('modal-product-brand').textContent = product.brand || 'Unbranded';
    document.getElementById('modal-product-category').textContent = product.category;
    document.getElementById('modal-product-desc').innerHTML = product.description ? product.description.replace(/\n/g, '<br>') : '<span class="italic text-gray-400">No description available.</span>';
    
    // Pricing Logic
    const originalPrice = parseFloat(product.price).toFixed(2);
    const discountPrice = parseFloat(product.discount_price).toFixed(2);
    
    if (product.discount_price > 0 && product.discount_price < product.price) {
        document.getElementById('modal-product-price').classList.add('hidden');
        document.getElementById('modal-product-discount-price').classList.remove('hidden');
        document.getElementById('modal-product-discount-price').textContent = '₱' + discountPrice;
        
        document.getElementById('modal-product-original-price').classList.remove('hidden');
        document.getElementById('modal-product-original-price').textContent = '₱' + originalPrice;
    } else {
        document.getElementById('modal-product-price').classList.remove('hidden');
        document.getElementById('modal-product-price').textContent = '₱' + originalPrice;
        
        document.getElementById('modal-product-discount-price').classList.add('hidden');
        document.getElementById('modal-product-original-price').classList.add('hidden');
    }

    // Stock Badge
    const stockEl = document.getElementById('modal-product-stock');
    if (product.stock > 0) {
        stockEl.textContent = product.stock + ' left in stock';
        stockEl.className = parseInt(product.stock) <= 10 ? 'text-sm font-black bg-orange-100 text-orange-700 px-3 py-1 rounded' : 'text-sm font-black bg-emerald-100 text-emerald-800 px-3 py-1 rounded';
    } else {
        stockEl.textContent = 'Out of Stock';
        stockEl.className = 'text-sm font-black bg-red-100 text-red-700 px-3 py-1 rounded';
    }

    // Batches Info
    const batchesContainer = document.getElementById('modal-product-batches-container');
    const noBatchesContainer = document.getElementById('modal-product-no-batches');
    const batchesContent = document.getElementById('modal-product-batches');
    batchesContent.innerHTML = ''; // Clear previous content

    if (product.batches && product.batches.length > 0) {
        batchesContainer.classList.remove('hidden');
        noBatchesContainer.classList.add('hidden');
        product.batches.forEach((batch, index) => {
            const batchEl = document.createElement('div');
            batchEl.className = 'flex justify-between items-center bg-white rounded-lg shadow-sm p-3 border';
            batchEl.innerHTML = `
                <div class="flex-1">
                    <p class="text-sm text-gray-700 font-bold mb-0.5">Batch #${index + 1}</p>
                    <p class="text-[11px] text-gray-500 font-semibold uppercase tracking-wider">Manufacture Date: ${new Date(batch.manufacture_date).toLocaleDateString()}</p>
                    <p class="text-[11px] text-gray-500 font-semibold uppercase tracking-wider">Expiration Date: <span class="text-red-600">${new Date(batch.expiry_date).toLocaleDateString()}</span></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-black ${batch.remaining_quantity <= 10 ? 'text-orange-600' : 'text-green-600'}">${batch.remaining_quantity} <span class="text-xs font-semibold text-gray-400">pcs</span></p>
                </div>
            `;
            batchesContent.appendChild(batchEl);
        });
    } else {
        batchesContainer.classList.add('hidden');
        noBatchesContainer.classList.remove('hidden');
    }

    // Open Modal
    modalEl.classList.remove('hidden');
    // slight delay to allow display:block to render before applying opacity transition
    setTimeout(() => {
        modalEl.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
    
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeProductModal() {
    modalEl.classList.add('opacity-0');
    modalContent.classList.remove('scale-100');
    modalContent.classList.add('scale-95');
    
    setTimeout(() => {
        modalEl.classList.add('hidden');
        document.body.style.overflow = ''; 
    }, 300); // Wait for transition
}

// Close modal when clicking outside the box
modalEl.addEventListener('click', (e) => {
    if (e.target === modalEl) closeProductModal();
});
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close();
?>