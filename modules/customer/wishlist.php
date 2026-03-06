<?php
// modules/customer/wishlist.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch favorited products by joining the wishlist and products tables, plus cart quantity
$query = "SELECT w.wishlist_id, p.product_id, p.name, p.price, p.discount_price, p.image_url, p.stock, c.quantity as cart_qty 
          FROM wishlist w 
          JOIN products p ON w.product_id = p.product_id 
          LEFT JOIN cart c ON p.product_id = c.product_id AND c.user_id = ?
          WHERE w.user_id = ?
          ORDER BY w.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$page_title = 'My Favorites';
require_once '../../includes/customer_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8 border-b pb-4">
        <h1 class="text-3xl font-black text-gray-800">My Favorites</h1>
        <p class="text-gray-500">Items you have saved for later.</p>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php while ($item = $result->fetch_assoc()): ?>
                <?php $qty = $item['cart_qty'] ? $item['cart_qty'] : 1; ?>
                <div class="bg-white border rounded-xl shadow-sm overflow-hidden flex flex-col hover:shadow-lg transition">
                    
                    <div class="relative h-48 bg-gray-100 flex justify-center items-center">
                        <?php if ($item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="object-cover h-full w-full">
                        <?php else: ?>
                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <?php endif; ?>
                        
                        <button onclick="removeFromWishlist(<?php echo $item['product_id']; ?>)" class="absolute top-2 right-2 p-2 bg-white rounded-full shadow hover:bg-red-50 text-red-500 transition" title="Remove from Wishlist">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L12 8.343l3.172-3.171a4 4 0 115.656 5.656L12 21.414l-8.828-8.828a4 4 0 010-5.656z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                    
                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="font-bold text-gray-800 line-clamp-2"><?php echo htmlspecialchars($item['name']); ?></h3>
                        
                        <?php if ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']): ?>
                            <p class="text-xs text-gray-400 line-through mt-2">₱<?php echo number_format($item['price'], 2); ?></p>
                            <p class="text-xl font-black text-red-600 mb-4">₱<?php echo number_format($item['discount_price'], 2); ?></p>
                        <?php else: ?>
                            <p class="text-xl font-black text-green-600 mt-2 mb-4">₱<?php echo number_format($item['price'], 2); ?></p>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <?php if ($item['stock'] > 0): ?>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center border border-gray-300 rounded-lg bg-white">
                                        <button onclick="adjustLocalQty(<?php echo $item['product_id']; ?>, -1)" class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-l-lg font-bold">-</button>
                                        <input type="number" id="input-qty-<?php echo $item['product_id']; ?>" value="<?php echo $qty; ?>" class="w-12 text-center text-sm font-semibold text-gray-800 focus:outline-none appearance-none" min="0">
                                        <button onclick="adjustLocalQty(<?php echo $item['product_id']; ?>, 1)" class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-r-lg font-bold">+</button>
                                    </div>
                                    <button onclick="addToCart(<?php echo $item['product_id']; ?>)" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition flex justify-center items-center shadow-sm" title="Update Cart">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    </button>
                                </div>
                            <?php else: ?>
                                <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-2 px-4 rounded cursor-not-allowed">Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-16 bg-white rounded-xl border border-gray-200 shadow-sm">
            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
            <h2 class="text-2xl font-bold text-gray-700">Your wishlist is empty!</h2>
            <p class="text-gray-500 mt-2 mb-6">Browse our products and click the heart icon to save them here.</p>
            <a href="home.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition shadow">Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

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

function removeFromWishlist(productId) {
    if(!confirm('Remove this item from your favorites?')) return;

    fetch('../../core/customer/toggle_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'removed') {
            window.location.reload();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => console.error('Error:', error));
}

function adjustLocalQty(productId, delta) {
    const input = document.getElementById('input-qty-' + productId);
    let currentQty = parseInt(input.value) || 0;
    let newQty = currentQty + delta;
    if (newQty < 0) newQty = 0;
    input.value = newQty;
}

function addToCart(productId) {
    const input = document.getElementById('input-qty-' + productId);
    const quantity = parseInt(input.value) || 0;

    fetch('../../core/customer/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            product_id: productId,
            action: 'update',
            quantity: quantity
        })
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
                    badge.className = 'absolute -top-2 -right-2 bg-yellow-400 text-blue-900 text-[10px] font-black w-4 h-4 flex items-center justify-center rounded-full border border-blue-900';
                    cartLink.appendChild(badge);
                }
                if (badge) badge.textContent = data.cart_count;
                if (data.cart_count === 0 && badge) badge.remove();
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$stmt->close();
$conn->close();
?>