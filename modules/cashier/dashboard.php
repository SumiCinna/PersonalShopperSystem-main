<?php
// modules/cashier/dashboard.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];

// --- TRANSACTION LOCK SECURITY ---
$lock_check = $conn->prepare("SELECT order_id FROM orders WHERE processed_by = ? AND order_status IN ('processing', 'ready')");
$lock_check->bind_param("i", $cashier_id);
$lock_check->execute();
$lock_result = $lock_check->get_result();
if ($lock_result->num_rows > 0) {
    $locked_order = $lock_result->fetch_assoc();
    $_SESSION['error'] = "SECURITY LOCK: You must complete or void your active transaction before viewing analytics.";
    header("Location: process_order.php?id=" . $locked_order['order_id']);
    exit();
}
$lock_check->close();

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
$now_ts = time();

// 1. Shift Sales Today
$stmt = $conn->prepare("SELECT SUM(amount_paid) as total_sales FROM transactions WHERE cashier_id = ? AND DATE(transaction_date) = ?");
$stmt->bind_param("is", $cashier_id, $today);
$stmt->execute();
$sales_today = $stmt->get_result()->fetch_assoc()['total_sales'] ?? 0;
$stmt->close();

// 2. Orders Completed Today
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE processed_by = ? AND order_status = 'completed' AND DATE(created_at) = ?");
$stmt->bind_param("is", $cashier_id, $today);
$stmt->execute();
$customers_served = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 3. Pending Orders Queue
$pending_queue = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'")->fetch_assoc()['count'];

// 4. Overdue Orders (pickup time has passed, still pending)
$overdue_result = $conn->query("
    SELECT o.order_id, o.tracking_no, o.pickup_datetime, u.username,
           CONCAT_WS(' ', up.firstname, up.surname) AS full_name
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.user_id
    WHERE o.order_status = 'pending' AND o.pickup_datetime < NOW()
    ORDER BY o.pickup_datetime ASC
");
$overdue_orders = $overdue_result->fetch_all(MYSQLI_ASSOC);

// 5. Low / Out of Stock products (view only for cashier)
$stock_result = $conn->query("
    SELECT product_id, name, sku, stock, low_stock_threshold, status
    FROM products
    WHERE stock <= low_stock_threshold AND status = 'active'
    ORDER BY stock ASC LIMIT 15
");
$low_stock_items = $stock_result->fetch_all(MYSQLI_ASSOC);

// 6. Check items in PENDING orders that are out of stock
$unavailable_result = $conn->query("
    SELECT o.order_id, o.tracking_no, p.name AS product_name, p.stock, oi.quantity
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.order_id
    JOIN products p ON p.product_id = oi.product_id
    WHERE o.order_status = 'pending' AND p.stock < oi.quantity
    ORDER BY p.stock ASC
");
$unavailable_items = $unavailable_result->fetch_all(MYSQLI_ASSOC);

// 7. Last known pending count (for incoming order alert via JS polling)
// We pass the current count to JS, which polls and compares

$page_title = 'Shift Overview';
require_once '../../includes/cashier_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">

    <!-- Hidden poll dot for JS reference -->
    <span id="pollDot" style="display:none;"></span>
    <!-- Hidden pending count for JS updates -->
    <span id="pendingCountDisplay" class="pendingCountLive" style="display:none;"><?php echo $pending_queue; ?></span>

    <!-- Incoming Order Alert Banner -->
    <div id="incomingOrderAlert" class="<?php echo $pending_queue > 0 ? '' : 'hidden'; ?> mb-6 bg-green-500 text-white rounded-xl px-6 py-4 flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 flex-shrink-0 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            <span class="font-black text-lg" id="incomingAlertText">🔔 <?php echo $pending_queue; ?> order<?php echo $pending_queue != 1 ? 's' : ''; ?> waiting in the queue!</span>
        </div>
        <div class="flex gap-3 items-center">
            <a href="pos.php" class="bg-white text-green-700 font-black px-4 py-2 rounded-lg text-sm hover:bg-green-50 transition">View Queue →</a>
            <button onclick="dismissIncomingAlert()" class="text-green-100 hover:text-white font-bold text-2xl leading-none">&times;</button>
        </div>
    </div>

    <div class="mb-8 border-b pb-4">
        <h1 class="text-3xl font-bold text-gray-800">Shift Overview</h1>
        <p class="text-gray-500 mt-1">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>. Here is your performance for today.</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded font-bold">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded font-bold">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-green-100 rounded-full mr-4 text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">Your Shift Sales</p>
                <p class="text-3xl font-black text-gray-900">₱<?php echo number_format($sales_today, 2); ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-blue-100 rounded-full mr-4 text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">Orders Completed</p>
                <p class="text-3xl font-black text-gray-900"><?php echo $customers_served; ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-yellow-100 rounded-full mr-4 text-yellow-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">Pending Orders</p>
                <p class="text-3xl font-black text-gray-900 pendingCountLive"><?php echo $pending_queue; ?></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">

        <!-- OVERDUE PICKUP ALERTS -->
        <div class="bg-white rounded-xl shadow-sm border <?php echo count($overdue_orders) > 0 ? 'border-red-300' : 'border-gray-100'; ?> overflow-hidden">
            <div class="<?php echo count($overdue_orders) > 0 ? 'bg-red-50 border-b border-red-200' : 'bg-gray-50 border-b border-gray-100'; ?> p-5 flex justify-between items-center">
                <h2 class="font-bold <?php echo count($overdue_orders) > 0 ? 'text-red-800' : 'text-gray-600'; ?> flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Overdue Pickup Orders
                    <?php if (count($overdue_orders) > 0): ?>
                        <span class="bg-red-500 text-white text-xs font-black px-2 py-0.5 rounded-full animate-pulse"><?php echo count($overdue_orders); ?></span>
                    <?php endif; ?>
                </h2>
                <a href="pos.php" class="text-sm text-blue-600 hover:underline font-semibold">View Queue →</a>
            </div>
            <?php if (count($overdue_orders) > 0): ?>
                <ul class="divide-y divide-red-50">
                    <?php foreach ($overdue_orders as $oo): ?>
                        <li class="p-4 flex justify-between items-center">
                            <div>
                                <p class="font-black text-gray-800 font-mono"><?php echo htmlspecialchars($oo['tracking_no']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($oo['full_name'] ?: $oo['username']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-red-600">Was due at:</p>
                                <p class="text-sm font-black text-red-700"><?php echo date('h:i A, M j', strtotime($oo['pickup_datetime'])); ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="p-10 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto text-green-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="font-semibold text-sm">No overdue orders right now.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ITEM AVAILABILITY FLAGS (Out of stock in pending orders) -->
        <div class="bg-white rounded-xl shadow-sm border <?php echo count($unavailable_items) > 0 ? 'border-orange-300' : 'border-gray-100'; ?> overflow-hidden">
            <div class="<?php echo count($unavailable_items) > 0 ? 'bg-orange-50 border-b border-orange-200' : 'bg-gray-50 border-b border-gray-100'; ?> p-5 flex justify-between items-center">
                <h2 class="font-bold <?php echo count($unavailable_items) > 0 ? 'text-orange-800' : 'text-gray-600'; ?> flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Item Availability Flags
                    <?php if (count($unavailable_items) > 0): ?>
                        <span class="bg-orange-500 text-white text-xs font-black px-2 py-0.5 rounded-full"><?php echo count($unavailable_items); ?></span>
                    <?php endif; ?>
                </h2>
            </div>
            <?php if (count($unavailable_items) > 0): ?>
                <ul class="divide-y divide-orange-50">
                    <?php foreach ($unavailable_items as $ui): ?>
                        <li class="p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-black text-gray-800 text-sm"><?php echo htmlspecialchars($ui['product_name']); ?></p>
                                    <p class="text-xs text-gray-500 font-mono mt-0.5">Order: <?php echo htmlspecialchars($ui['tracking_no']); ?></p>
                                </div>
                                <div class="text-right flex-shrink-0 ml-3">
                                    <?php if ($ui['stock'] == 0): ?>
                                        <span class="bg-red-100 text-red-700 text-xs font-black px-2 py-1 rounded">OUT OF STOCK</span>
                                    <?php else: ?>
                                        <span class="bg-orange-100 text-orange-700 text-xs font-black px-2 py-1 rounded">INSUFFICIENT</span>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1">Stock: <strong><?php echo $ui['stock']; ?></strong> / Needed: <strong><?php echo $ui['quantity']; ?></strong></p>
                                </div>
                            </div>
                            <!-- Substitute Item Note -->
                            <div class="mt-2">
                                <button onclick="toggleSubNote(this)" class="text-xs text-blue-600 hover:text-blue-800 font-semibold underline">
                                    + Add Substitute Note
                                </button>
                                <div class="sub-note hidden mt-2">
                                    <input type="text" placeholder="e.g. Suggest: Bear Brand 33g instead"
                                        class="w-full text-xs border border-blue-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-300 outline-none bg-blue-50"
                                        onkeydown="if(event.key==='Enter'){saveSubNote(this, '<?php echo htmlspecialchars($ui['tracking_no']); ?>', '<?php echo htmlspecialchars(addslashes($ui['product_name'])); ?>')}">
                                    <p class="text-xs text-gray-400 mt-1">Press Enter to save note. You can communicate this to the customer when processing.</p>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="p-10 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto text-green-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="font-semibold text-sm">All items in pending orders are available.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- LOW / OUT OF STOCK INVENTORY VIEW (cashier read-only) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <div class="bg-gray-50 border-b border-gray-200 p-5 flex justify-between items-center">
            <h2 class="font-bold text-gray-700 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                Inventory Status
                <span class="text-xs font-normal text-gray-400">(View only — contact admin to restock)</span>
            </h2>
            <?php if (count($low_stock_items) > 0): ?>
                <span class="text-xs font-bold text-orange-600 bg-orange-100 px-3 py-1 rounded-full"><?php echo count($low_stock_items); ?> item(s) need attention</span>
            <?php endif; ?>
        </div>
        <?php if (count($low_stock_items) > 0): ?>
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3 font-bold">SKU</th>
                        <th class="px-5 py-3 font-bold">Product Name</th>
                        <th class="px-5 py-3 font-bold text-center">Threshold</th>
                        <th class="px-5 py-3 font-bold text-center">Current Stock</th>
                        <th class="px-5 py-3 font-bold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($low_stock_items as $item): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-3 font-mono text-xs font-bold text-slate-500"><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td class="px-5 py-3 font-bold text-slate-800"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="px-5 py-3 text-center text-slate-500 font-semibold"><?php echo $item['low_stock_threshold']; ?></td>
                            <td class="px-5 py-3 text-center">
                                <span class="font-black text-lg <?php echo $item['stock'] == 0 ? 'text-red-600' : 'text-orange-500'; ?>">
                                    <?php echo $item['stock']; ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if ($item['stock'] == 0): ?>
                                    <span class="bg-red-100 text-red-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">Out of Stock</span>
                                <?php else: ?>
                                    <span class="bg-orange-100 text-orange-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">Low Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="p-10 text-center text-gray-400">
                <svg class="w-10 h-10 mx-auto text-green-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="font-semibold text-sm">All products are sufficiently stocked.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- CTA Banner -->
    <div class="bg-blue-900 rounded-xl shadow-md p-8 text-white flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold mb-1">Ready to serve the next customer?</h2>
            <p class="text-blue-200">There are currently <strong class="pendingCountLive" id="pendingCountBanner"><?php echo $pending_queue; ?></strong> orders waiting in the queue.</p>
        </div>
        <a href="pos.php" class="bg-yellow-400 hover:bg-yellow-500 text-blue-900 font-bold py-3 px-8 rounded-lg shadow transition text-lg">
            Open POS Terminal &rarr;
        </a>
    </div>

</main>

<script>
// ===== INCOMING ORDER ALERT — polls every 15 seconds =====
let knownPendingCount = <?php echo $pending_queue; ?>;
let alertDismissed = false;

async function pollPendingQueue() {
    try {
        const res = await fetch('get_pending_count.php?t=' + Date.now()); // cache-bust
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        const newCount = parseInt(data.count);

        // Update all count displays
        document.querySelectorAll('.pendingCountLive').forEach(el => el.textContent = newCount);

        // Flash the pending card green briefly on update
        const card = document.getElementById('pendingCountDisplay');
        if (card && newCount !== knownPendingCount) {
            card.closest('.bg-white').style.transition = 'background 0.3s';
            card.closest('.bg-white').style.background = '#f0fdf4';
            setTimeout(() => card.closest('.bg-white').style.background = '', 1500);
        }

        // Show alert banner if count went UP
        if (newCount > knownPendingCount && !alertDismissed) {
            const diff = newCount - knownPendingCount;
            document.getElementById('incomingAlertText').textContent =
                `🔔 ${diff} new order${diff > 1 ? 's' : ''} just entered the queue! (${newCount} total pending)`;
            document.getElementById('incomingOrderAlert').classList.remove('hidden');
            try { new Audio('../../assets/sounds/ding.mp3').play(); } catch(e) {}
        }

        // Hide banner if queue is now empty
        if (newCount === 0) {
            document.getElementById('incomingOrderAlert').classList.add('hidden');
        }

        // Update live indicator dot color
        const dot = document.getElementById('pollDot');
        if (dot) { dot.style.background = '#22c55e'; setTimeout(() => dot.style.background = '#94a3b8', 1000); }

        knownPendingCount = newCount;
    } catch(e) {
        // Show poll error dot in red
        const dot = document.getElementById('pollDot');
        if (dot) dot.style.background = '#ef4444';
        console.warn('Poll failed:', e);
    }
}

function dismissIncomingAlert() {
    document.getElementById('incomingOrderAlert').classList.add('hidden');
    alertDismissed = true;
    setTimeout(() => { alertDismissed = false; }, 60000);
}

// Poll immediately on load, then every 15s
pollPendingQueue();
setInterval(pollPendingQueue, 15000);

// ===== SUBSTITUTE ITEM NOTE TOGGLE =====
function toggleSubNote(btn) {
    const noteDiv = btn.nextElementSibling;
    noteDiv.classList.toggle('hidden');
    if (!noteDiv.classList.contains('hidden')) {
        noteDiv.querySelector('input').focus();
        btn.textContent = '− Hide Note';
    } else {
        btn.textContent = '+ Add Substitute Note';
    }
}

function saveSubNote(input, trackingNo, productName) {
    const note = input.value.trim();
    if (!note) return;
    input.disabled = true;
    input.style.borderColor = '#16a34a';
    input.style.background = '#f0fdf4';
    const p = input.nextElementSibling;
    p.textContent = `✓ Note saved for ${trackingNo}: "${note}"`;
    p.style.color = '#16a34a';
    // Store in sessionStorage so it persists during the shift
    sessionStorage.setItem(`sub_note_${trackingNo}_${productName}`, note);
}
</script>

<?php require_once '../../includes/cashier_footer.php'; $conn->close(); ?>