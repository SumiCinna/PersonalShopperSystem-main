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
$vat_rate = 0.12;
$service_fee_rate = 0.10;

while ($row = $result->fetch_assoc()) {
    // Use discount price if available
    $final_price = ($row['discount_price'] > 0 && $row['discount_price'] < $row['price']) ? $row['discount_price'] : $row['price'];
    $row['final_price'] = $final_price;
    $cart_items[] = $row;
    $subtotal += ($final_price * $row['quantity']);
}
$stmt->close();

$vat_amount = round($subtotal * $vat_rate, 2);
$service_fee_amount = round($subtotal * $service_fee_rate, 2);
$grand_total = round($subtotal + $vat_amount + $service_fee_amount, 2);

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
                            <li id="cart-item-<?php echo $item['cart_id']; ?>" class="p-6 flex flex-col sm:flex-row items-center hover:bg-gray-50 transition">
                                
                                <div class="mr-4 flex-shrink-0">
                                    <input type="checkbox" name="selected_cart_ids[]" value="<?php echo $item['cart_id']; ?>" id="checkbox-<?php echo $item['cart_id']; ?>" class="item-checkbox w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer" data-price="<?php echo $item['final_price']; ?>" data-qty="<?php echo $item['quantity']; ?>" onchange="updateTotal()" checked>
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
                                        <button type="button" id="btn-decrease-<?php echo $item['cart_id']; ?>" onclick="updateCart(<?php echo $item['cart_id']; ?>, 'decrease')" class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded-l-lg transition" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>-</button>
                                        
                                        <input type="text" id="qty-<?php echo $item['cart_id']; ?>" readonly value="<?php echo $item['quantity']; ?>" class="w-12 text-center text-sm font-semibold border-x border-gray-300 py-1 bg-white">
                                        
                                        <button type="button" id="btn-increase-<?php echo $item['cart_id']; ?>" onclick="updateCart(<?php echo $item['cart_id']; ?>, 'increase')" class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded-r-lg transition">+</button>
                                    </div>
                                    
                                    <p class="text-sm font-bold text-gray-900">Total: <span id="item-total-<?php echo $item['cart_id']; ?>">₱<?php echo number_format($item['final_price'] * $item['quantity'], 2); ?></span></p>

                                    <button type="button" onclick="removeItem(<?php echo $item['cart_id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'])); ?>')" class="text-xs text-red-500 hover:text-red-700 font-semibold flex items-center transition">
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
                        <span>VAT (12%)</span>
                        <span class="font-semibold" id="vatDisplay">₱<?php echo number_format($vat_amount, 2); ?></span>
                    </div>

                    <div class="flex justify-between text-gray-600 mb-4 border-b pb-4">
                        <span>Service Fee (10%)</span>
                        <span class="font-semibold" id="serviceFeeDisplay">₱<?php echo number_format($service_fee_amount, 2); ?></span>
                    </div>
                    
                    <div class="flex justify-between text-xl font-bold text-gray-900 mb-8">
                        <span>Grand Total</span>
                        <span class="text-blue-700" id="grandTotalDisplay">₱<?php echo number_format($grand_total, 2); ?></span>
                    </div>

                    <p id="minimumNotice" class="text-sm text-red-600 font-semibold mb-4 hidden">
                        Minimum grand total of ₱300.00 is required before checkout.
                    </p>
                    
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

<!-- Remove Item Confirmation Modal -->
<div id="removeModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 text-center sm:text-left">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-12 sm:w-12">
                            <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-xl font-black leading-6 text-gray-900 mb-2" id="modal-title">Remove Item?</h3>
                            <div class="mt-2 text-center sm:text-left">
                                <p class="text-sm text-gray-500">Are you sure you want to remove <span id="modal-item-name" class="font-bold text-gray-900">this item</span> from your cart? You can always add it back later.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-4 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                    <button type="button" onclick="confirmRemoval()" class="inline-flex w-full justify-center rounded-xl bg-red-600 px-6 py-3 text-sm font-bold text-white shadow-lg hover:bg-red-700 sm:w-auto transition-all active:scale-95">Yes, Remove Item</button>
                    <button type="button" onclick="closeRemoveModal()" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-6 py-3 text-sm font-bold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-100 sm:mt-0 sm:w-auto transition-all active:scale-95">Keep in Cart</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const VAT_RATE = 0.12;
    const SERVICE_FEE_RATE = 0.10;
    const MINIMUM_SUBTOTAL = 300;

    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        updateTotal();
    }

    function updateTotal() {
        let subtotal = 0;
        let count = 0;
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        
        checkboxes.forEach(cb => {
            subtotal += parseFloat(cb.dataset.price) * parseInt(cb.dataset.qty);
            count++;
        });

        const vat = subtotal * VAT_RATE;
    const serviceFee = subtotal * SERVICE_FEE_RATE;
    const grandTotal = subtotal + vat + serviceFee;

        const formattedSubtotal = '₱' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const formattedVat = '₱' + vat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const formattedServiceFee = '₱' + serviceFee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const formattedGrandTotal = '₱' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        document.getElementById('subtotalDisplay').innerText = formattedSubtotal;
        document.getElementById('vatDisplay').innerText = formattedVat;
    document.getElementById('serviceFeeDisplay').innerText = formattedServiceFee;
        document.getElementById('grandTotalDisplay').innerText = formattedGrandTotal;
        document.getElementById('itemCountDisplay').innerText = `Subtotal (${count} items)`;

        const selectAllCheckbox = document.getElementById('selectAll');
        const allCheckboxes = document.querySelectorAll('.item-checkbox');
        if (allCheckboxes.length > 0) {
            selectAllCheckbox.checked = count === allCheckboxes.length;
        }
        
        // Disable checkout button if nothing selected or minimum subtotal not met
        const btn = document.getElementById('checkoutBtn');
        const minimumNotice = document.getElementById('minimumNotice');
        if(count === 0 || grandTotal < MINIMUM_SUBTOTAL) {
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            if (count > 0 && grandTotal < MINIMUM_SUBTOTAL) {
                minimumNotice.classList.remove('hidden');
            } else {
                minimumNotice.classList.add('hidden');
            }
        } else {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            minimumNotice.classList.add('hidden');
        }
    }

    document.getElementById('cartForm')?.addEventListener('submit', function (event) {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        let subtotal = 0;
        checked.forEach(cb => {
            subtotal += parseFloat(cb.dataset.price) * parseInt(cb.dataset.qty);
        });

        const vat = subtotal * VAT_RATE;
        const serviceFee = subtotal * SERVICE_FEE_RATE;
        const grandTotal = subtotal + vat + serviceFee;

        if (checked.length === 0) {
            event.preventDefault();
            alert('Please select at least one item to proceed to checkout.');
            return;
        }

        if (grandTotal < MINIMUM_SUBTOTAL) {
            event.preventDefault();
            alert('Minimum grand total of ₱300.00 is required before checkout.');
        }
    });

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
                // Update quantity display
                const qtyInput = document.getElementById(`qty-${cartId}`);
                const checkbox = document.getElementById(`checkbox-${cartId}`);
                const itemTotal = document.getElementById(`item-total-${cartId}`);
                const btnDecrease = document.getElementById(`btn-decrease-${cartId}`);
                
                if (qtyInput && checkbox && itemTotal) {
                    qtyInput.value = data.new_qty;
                    checkbox.dataset.qty = data.new_qty;
                    
                    const price = parseFloat(checkbox.dataset.price);
                    const newTotal = price * data.new_qty;
                    itemTotal.innerText = '₱' + newTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    
                    // Enable/disable decrease button
                    if (btnDecrease) {
                        btnDecrease.disabled = (data.new_qty <= 1);
                    }
                    
                    updateTotal(); // Refresh order summary
                }
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    let itemToRemove = null;

    // AJAX function to handle Remove Trash button
    function removeItem(cartId, itemName) {
        itemToRemove = cartId;
        document.getElementById('modal-item-name').textContent = itemName;
        document.getElementById('removeModal').classList.remove('hidden');
    }

    function closeRemoveModal() {
        document.getElementById('removeModal').classList.add('hidden');
        itemToRemove = null;
    }

    function confirmRemoval() {
        if (!itemToRemove) return;
        
        const cartId = itemToRemove;
        fetch('../../core/customer/remove_from_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart_id: cartId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const itemRow = document.getElementById(`cart-item-${cartId}`);
                if (itemRow) {
                    itemRow.remove();
                    updateTotal();
                    
                    // Check if cart is now empty
                    const remainingItems = document.querySelectorAll('.item-checkbox');
                    if (remainingItems.length === 0) {
                        window.location.reload(); 
                    }
                }
                closeRemoveModal();
            } else {
                alert(data.message || 'Could not remove item.');
                closeRemoveModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            closeRemoveModal();
        });
    }

    updateTotal();
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close(); 
?>