<?php
// modules/cashier/process_order.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: pos.php");
    exit();
}

$order_id = intval($_GET['id']);
$cashier_id = $_SESSION['user_id'];

// 1. Fetch Order Data (And ensure this cashier is the one locked to it!)
$query = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ? AND o.processed_by = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $cashier_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    // Order doesn't exist OR this cashier isn't authorized for it
    header("Location: pos.php");
    exit();
}
$order = $order_result->fetch_assoc();
$stmt->close();

// 2. Fetch Order Items
$items_query = "SELECT oi.quantity, oi.price_at_checkout, p.name, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$items_stmt->close();

$page_title = 'POS Terminal - ' . $order['tracking_no'];
require_once '../../includes/cashier_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-6 bg-gray-200 flex flex-col md:flex-row gap-6">
    
    <div class="md:w-2/3 flex flex-col h-full bg-white shadow-xl border border-gray-300 rounded-lg overflow-hidden">
        
        <div class="bg-slate-900 text-white p-5 flex justify-between items-center border-b-4 border-slate-700">
            <div>
                <h2 class="text-2xl font-mono font-black tracking-widest text-yellow-400"><?php echo htmlspecialchars($order['tracking_no']); ?></h2>
                <p class="text-sm text-gray-300 mt-1">Customer: <span class="font-bold text-white"><?php echo htmlspecialchars($order['username']); ?></span></p>
            </div>
            
            <form id="voidForm" action="../../core/cashier/update_order.php" method="POST">
                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                <input type="hidden" name="is_void_action" value="1">
                <input type="hidden" name="void_password" id="void_password">
                
                <button type="button" onclick="confirmVoid()" class="bg-red-600 hover:bg-red-700 text-white text-sm font-bold py-2 px-4 rounded transition shadow-inner border border-red-800 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    VOID TRANSACTION
                </button>
            </form>
        </div>
        
        <div class="flex-1 overflow-y-auto p-0">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-100 border-b-2 border-gray-200 sticky top-0 shadow-sm">
                    <tr>
                        <th class="p-4 text-sm text-gray-700 font-bold uppercase">Item Description</th>
                        <th class="p-4 text-sm text-gray-700 font-bold uppercase text-center">Qty</th>
                        <th class="p-4 text-sm text-gray-700 font-bold uppercase text-right">Price</th>
                        <th class="p-4 text-sm text-gray-700 font-bold uppercase text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-mono text-sm">
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-yellow-50 transition">
                            <td class="p-4">
                                <div class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="text-xs text-gray-500 mt-1 uppercase">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                            </td>
                            <td class="p-4 text-center font-black text-lg text-blue-900"><?php echo $item['quantity']; ?></td>
                            <td class="p-4 text-right text-gray-600">₱<?php echo number_format($item['price_at_checkout'], 2); ?></td>
                            <td class="p-4 text-right font-bold text-gray-900 text-base">₱<?php echo number_format($item['price_at_checkout'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="md:w-1/3 flex flex-col gap-6">
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md font-bold">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 shadow-xl rounded-lg border-t-4 border-blue-600">
            <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-2">Current Order Phase</h3>
            <div class="flex items-center mb-6">
                <?php if ($order['order_status'] === 'processing'): ?>
                    <span class="flex h-4 w-4 relative mr-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-4 w-4 bg-blue-600"></span>
                    </span>
                    <span class="text-2xl font-black text-blue-900 uppercase">Processing</span>
                <?php elseif ($order['order_status'] === 'ready'): ?>
                    <span class="flex h-4 w-4 relative mr-3">
                        <span class="relative inline-flex rounded-full h-4 w-4 bg-green-500"></span>
                    </span>
                    <span class="text-2xl font-black text-green-700 uppercase">Ready for Pickup</span>
                <?php endif; ?>
            </div>
            
            <form action="../../core/cashier/update_order.php" method="POST">
                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                <input type="hidden" name="payment_status" value="<?php echo $order['payment_status']; ?>">
                
                <?php if ($order['order_status'] === 'processing'): ?>
                    <input type="hidden" name="order_status" value="ready">
                    <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-slate-900 font-black py-4 px-4 rounded-lg shadow-md transition text-lg flex justify-center items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Groceries Packed & Ready
                    </button>
                    <p class="text-xs text-gray-500 mt-3 text-center">Clicking this will alert the customer to proceed to the counter.</p>
                <?php else: ?>
                    <div class="bg-green-50 text-green-800 p-3 rounded text-sm font-semibold text-center border border-green-200">
                        Items are ready. Proceed to payment below.
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="bg-white p-6 shadow-xl rounded-lg flex-1 flex flex-col <?php echo ($order['order_status'] !== 'ready') ? 'opacity-50 pointer-events-none grayscale' : ''; ?>">
            <h3 class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-4 border-b-2 border-gray-100 pb-2 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                Checkout Terminal
            </h3>
            
            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 mb-6 shadow-inner">
                
                <div class="flex justify-between items-center mb-4 border-b border-slate-200 pb-2">
                    <span class="text-slate-500 font-bold text-xs uppercase">Scheduled Pickup</span>
                    <span class="text-slate-800 font-bold text-sm bg-yellow-100 px-2 py-1 rounded">
                        <?php echo date('M j, Y - h:i A', strtotime($order['pickup_datetime'])); ?>
                    </span>
                </div>

                <div class="flex justify-between items-center mb-2">
                    <span class="text-slate-500 font-bold text-xs uppercase">Grand Total</span>
                    <span class="text-slate-800 font-bold">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                
                <?php if ($order['upfront_payment'] > 0): ?>
                    <?php 
                        // Calculate percentage paid
                        $percent_paid = round(($order['upfront_payment'] / $order['total_amount']) * 100);
                    ?>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-blue-600 font-bold text-xs uppercase">
                            Online Paid (<?php echo $percent_paid; ?>%)
                        </span>
                        <span class="text-blue-700 font-bold">- ₱<?php echo number_format($order['upfront_payment'], 2); ?></span>
                    </div>
                    <div class="text-[10px] text-blue-500 mb-3 text-right font-mono font-bold tracking-widest border-b border-slate-200 pb-3">
                        REF: <?php echo htmlspecialchars($order['online_reference']); ?>
                    </div>
                <?php endif; ?>

                <div class="flex justify-between items-end mt-4">
                    <span class="text-slate-800 font-black uppercase text-sm">Collect Balance</span>
                    <span class="text-4xl font-black text-green-600 font-mono <?php echo $order['balance_due'] == 0 ? 'text-gray-400' : ''; ?>">
                        ₱<?php echo number_format($order['balance_due'], 2); ?>
                    </span>
                </div>
            </div>

            <form action="../../core/cashier/update_order.php" method="POST" class="flex flex-col flex-1">
                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                <input type="hidden" name="order_status" value="completed">
                <input type="hidden" name="payment_status" value="paid">
                <input type="hidden" id="total_amount" value="<?php echo $order['balance_due']; ?>">

                <div class="space-y-5 mb-auto">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tender Method</label>
                        <select name="payment_method" id="payment_method" class="w-full bg-white border-2 border-slate-300 text-slate-900 rounded-lg p-3 font-bold shadow-sm focus:border-blue-500 focus:ring-0" onchange="togglePaymentUI()">
                            <?php if($order['balance_due'] > 0): ?>
                                <option value="cash" selected>Cash Payment (Balance)</option>
                                <option value="gcash">GCash (Balance)</option>
                            <?php else: ?>
                                <option value="prepaid" selected>Fully Paid Online</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="cash_ui" class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <label class="block text-sm font-bold text-blue-900 mb-2">Cash Received (₱)</label>
                        <input type="number" step="0.01" name="amount_tendered" id="amount_tendered" class="w-full bg-white border-2 border-blue-300 text-blue-900 rounded-lg p-4 font-black font-mono text-3xl text-right focus:border-blue-600 focus:ring-0 shadow-inner" placeholder="0.00" oninput="calculateChange()">
                        
                        <div class="flex justify-between items-center mt-4 pt-4 border-t border-blue-200">
                            <span class="text-blue-800 font-bold uppercase text-xs">Change Due</span>
                            <span id="change_display" class="text-3xl font-black text-slate-800 font-mono">₱0.00</span>
                            <input type="hidden" name="change_amount" id="change_amount" value="0">
                        </div>
                    </div>

                    <div id="online_ui" class="hidden bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <label class="block text-sm font-bold text-yellow-900 mb-2">Reference Number</label>
                        <input type="text" name="payment_reference" id="payment_reference" value="<?php echo ($order['balance_due'] <= 0) ? htmlspecialchars($order['online_reference'] ?? '') : ''; ?>" class="w-full bg-white border-2 border-yellow-300 text-yellow-900 rounded-lg p-4 font-mono font-bold text-lg focus:border-yellow-600 focus:ring-0 shadow-inner" placeholder="Enter Ref No.">
                    </div>
                </div>

                <button type="submit" id="complete_btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-black py-5 px-4 rounded-lg mt-6 transition shadow-lg text-xl disabled:bg-slate-300 disabled:text-slate-500 disabled:cursor-not-allowed flex justify-center items-center" disabled>
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Finalize Transaction
                </button>
            </form>
        </div>

    </div>
</main>

<!-- Void Authorization Modal -->
<div id="voidModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border-t-4 border-red-600">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Manager Authorization</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-4">Please enter the Manager Password to void this transaction.</p>
                                <input type="password" id="modal_void_password" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none" placeholder="Enter Password" onkeyup="if(event.key === 'Enter') submitVoid()">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="button" onclick="submitVoid()" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">Confirm Void</button>
                    <button type="button" onclick="closeVoidModal()" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const totalAmount = parseFloat(document.getElementById('total_amount').value);
    const amountTenderedInput = document.getElementById('amount_tendered');
    const changeDisplay = document.getElementById('change_display');
    const changeAmountInput = document.getElementById('change_amount');
    const completeBtn = document.getElementById('complete_btn');
    
    const paymentMethodSelect = document.getElementById('payment_method');
    const cashUI = document.getElementById('cash_ui');
    const onlineUI = document.getElementById('online_ui');
    const refInput = document.getElementById('payment_reference');

    function togglePaymentUI() {
        if (paymentMethodSelect.value === 'cash') {
            cashUI.classList.remove('hidden');
            onlineUI.classList.add('hidden');
            refInput.required = false;
            amountTenderedInput.required = true;
            calculateChange();
        } else {
            cashUI.classList.add('hidden');
            onlineUI.classList.remove('hidden');
            refInput.required = true;
            amountTenderedInput.required = false;
            completeBtn.disabled = false;
        }
    }

    function calculateChange() {
        if (paymentMethodSelect.value !== 'cash') return;

        let tendered = parseFloat(amountTenderedInput.value);
        if (isNaN(tendered)) tendered = 0;

        let change = tendered - totalAmount;

        if (change >= 0 && tendered > 0) {
            changeDisplay.textContent = '₱' + change.toFixed(2);
            changeDisplay.classList.add('text-green-600');
            changeDisplay.classList.remove('text-red-500');
            changeAmountInput.value = change.toFixed(2);
            completeBtn.disabled = false; 
        } else {
            changeDisplay.textContent = 'Insufficient';
            changeDisplay.classList.remove('text-green-600');
            changeDisplay.classList.add('text-red-500');
            changeAmountInput.value = 0;
            completeBtn.disabled = true; 
        }
    }

    function confirmVoid() {
        document.getElementById('voidModal').classList.remove('hidden');
        document.getElementById('modal_void_password').value = '';
        setTimeout(() => document.getElementById('modal_void_password').focus(), 100);
    }

    function closeVoidModal() {
        document.getElementById('voidModal').classList.add('hidden');
    }

    function submitVoid() {
        const password = document.getElementById('modal_void_password').value;
        if (password) {
            document.getElementById('void_password').value = password;
            document.getElementById('voidForm').submit();
        }
    }

    togglePaymentUI();
</script>

<?php require_once '../../includes/cashier_footer.php'; $conn->close(); ?>