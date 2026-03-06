<?php
// modules/customer/cart.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch Cart Items by JOINING the cart and products tables
$cart_query = "SELECT c.cart_id, c.quantity, p.product_id, p.name, p.price, p.discount_price, p.image_url, p.unit_value, p.unit_measure, p.stock 
               FROM cart c
               JOIN products p ON c.product_id = p.product_id
               WHERE c.user_id = ?
               ORDER BY c.cart_id DESC";
               
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    // Use discount price if available
    $final_price = ($row['discount_price'] > 0 && $row['discount_price'] < $row['price']) ? $row['discount_price'] : $row['price'];
    $row['final_price'] = $final_price;
    $cart_items[] = $row;
    $subtotal += ($final_price * $row['quantity']);
}
$stmt->close();

// Include the Customer Header
$page_title = 'My Shopping Cart';
require_once '../../includes/customer_header.php'; 
?>

<main class="container mx-auto px-6 py-12 flex-grow">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">My Shopping Cart</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm font-bold">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            <h2 class="text-2xl font-bold text-gray-700 mb-2">Your cart is empty</h2>
            <p class="text-gray-500 mb-6">Looks like you haven't added any groceries yet.</p>
            <a href="home.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition inline-block">
                Start Shopping
            </a>
        </div>
    <?php else: ?>
        <form action="checkout.php" method="POST" id="cartForm">
        <div class="flex flex-col lg:flex-row gap-8">
            
            <div class="lg:w-2/3">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-4 border-b border-gray-200 bg-gray-50 flex items-center">
                        <input type="checkbox" id="selectAll" class="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" onchange="toggleAll(this)" checked>
                        <label for="selectAll" class="ml-3 text-sm font-bold text-gray-700 cursor-pointer select-none">Select All Items</label>
                    </div>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($cart_items as $item): ?>
                            <li class="p-6 flex flex-col sm:flex-row items-center hover:bg-gray-50 transition">
                                
                                <div class="mr-4 flex-shrink-0">
                                    <input type="checkbox" name="selected_cart_ids[]" value="<?php echo $item['cart_id']; ?>" class="item-checkbox w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" data-price="<?php echo $item['final_price']; ?>" data-qty="<?php echo $item['quantity']; ?>" onchange="updateTotal()" checked>
                                </div>

                                <div class="w-24 h-24 flex-shrink-0 bg-gray-100 rounded-lg flex items-center justify-center p-2 mb-4 sm:mb-0">
                                    <?php if(!empty($item['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="max-h-full object-contain">
                                    <?php else: ?>
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <?php endif; ?>
                                </div>

                                <div class="sm:ml-6 flex-1 text-center sm:text-left">
                                    <h3 class="text-lg font-bold text-gray-800"><a href="#" class="hover:text-blue-600"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo floatval($item['unit_value']) . ' ' . htmlspecialchars($item['unit_measure']); ?></p>
                                    
                                    <?php if ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']): ?>
                                        <p class="text-xs text-gray-400 line-through mt-1">₱<?php echo number_format($item['price'], 2); ?></p>
                                        <p class="text-red-600 font-bold text-lg">₱<?php echo number_format($item['final_price'], 2); ?></p>
                                    <?php else: ?>
                                        <p class="text-blue-700 font-bold mt-2">₱<?php echo number_format($item['final_price'], 2); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4 sm:mt-0 sm:ml-6 flex flex-col items-center sm:items-end space-y-3">
                                    
                                    <div class="flex items-center border border-gray-300 rounded-lg">
                                        <button onclick="updateCart(<?php echo $item['cart_id']; ?>, 'decrease')" class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded-l-lg transition" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>-</button>
                                        
                                        <input type="text" readonly value="<?php echo $item['quantity']; ?>" class="w-12 text-center text-sm font-semibold border-x border-gray-300 py-1 bg-white">
                                        
                                        <button onclick="updateCart(<?php echo $item['cart_id']; ?>, 'increase')" class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded-r-lg transition" <?php echo $item['quantity'] >= $item['stock'] ? 'disabled' : ''; ?>>+</button>
                                    </div>
                                    
                                    <p class="text-sm font-bold text-gray-900">Total: ₱<?php echo number_format($item['final_price'] * $item['quantity'], 2); ?></p>

                                    <button onclick="removeItem(<?php echo $item['cart_id']; ?>)" class="text-xs text-red-500 hover:text-red-700 font-semibold flex items-center transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Remove
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="lg:w-1/3">
                <div class="bg-gray-50 rounded-xl shadow-sm border border-gray-200 p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-4">Order Summary</h2>
                    
                    <div class="flex justify-between text-gray-600 mb-4">
                        <span id="itemCountDisplay">Subtotal (<?php echo count($cart_items); ?> items)</span>
                        <span class="font-semibold" id="subtotalDisplay">₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="flex justify-between text-gray-600 mb-4 border-b pb-4">
                        <span>Convenience Fee</span>
                        <span class="font-semibold">₱0.00</span>
                    </div>
                    
                    <div class="flex justify-between text-xl font-bold text-gray-900 mb-8">
                        <span>Grand Total</span>
                        <span class="text-blue-700" id="grandTotalDisplay">₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <button type="submit" id="checkoutBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-4 rounded-lg flex justify-center items-center transition shadow-md">
                        Proceed to Checkout
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                    
                    <div class="mt-4 text-center">
                        <a href="home.php" class="text-sm text-blue-600 hover:underline">Continue Shopping</a>
                    </div>
                </div>
            </div>
            
        </div>
        </form>
    <?php endif; ?>
</main>

<script>
    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        let count = 0;
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        
        checkboxes.forEach(cb => {
            total += parseFloat(cb.dataset.price) * parseInt(cb.dataset.qty);
            count++;
        });

        const formattedTotal = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        document.getElementById('subtotalDisplay').innerText = formattedTotal;
        document.getElementById('grandTotalDisplay').innerText = formattedTotal;
        document.getElementById('itemCountDisplay').innerText = `Subtotal (${count} items)`;
        
        // Disable checkout button if nothing selected
        const btn = document.getElementById('checkoutBtn');
        if(count === 0) {
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    // AJAX function to handle + and - quantity buttons
    function updateCart(cartId, action) {
        fetch('../../core/customer/update_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart_id: cartId, action: action })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload(); // Refresh to show new totals
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // AJAX function to handle Remove Trash button
    function removeItem(cartId) {
        if (confirm("Remove this item from your cart?")) {
            fetch('../../core/customer/remove_from_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cart_id: cartId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not remove item.');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    }
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close(); 
?>