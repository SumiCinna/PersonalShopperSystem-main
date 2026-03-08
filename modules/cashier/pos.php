<?php
// modules/cashier/pos.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
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
    $_SESSION['error'] = "SECURITY LOCK: You must complete or void your active transaction before taking a new customer.";
    header("Location: process_order.php?id=" . $locked_order['order_id']);
    exit();
}
$lock_check->close();

// --- SEARCH & FILTER ---
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';

$where_clauses = ["o.order_status = 'pending'"];
$params = [];
$types  = '';

if ($search !== '') {
    $where_clauses[] = "(o.tracking_no LIKE ? OR u.username LIKE ? OR up.firstname LIKE ? OR up.surname LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}
if ($date_filter !== '') {
    $where_clauses[] = "DATE(o.pickup_datetime) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$where_sql = implode(' AND ', $where_clauses);

$query = "SELECT o.order_id, o.tracking_no, o.total_amount, o.balance_due, o.payment_method, 
                 o.pickup_datetime, u.username, u.user_id,
                 up.firstname, up.middlename, up.surname, up.suffix, up.mobile,
                 CONCAT_WS(' ', up.firstname, up.middlename, up.surname, up.suffix) AS full_name
          FROM orders o
          JOIN users u ON o.user_id = u.user_id
          LEFT JOIN user_profiles up ON up.user_id = u.user_id
          WHERE $where_sql
          ORDER BY o.pickup_datetime ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$page_title = 'POS Terminal Queue';
require_once '../../includes/cashier_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">

    <div class="flex justify-between items-center mb-6 border-b pb-4 border-gray-200">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Pending Order Queue
            </h1>
            <p class="text-gray-500 mt-1">Select the next customer in line to begin processing their items.</p>
        </div>
        <div class="bg-white px-5 py-3 rounded-xl shadow-sm border border-gray-200 text-sm font-semibold text-gray-600 flex items-center">
            <span class="mr-2">Waiting in Line:</span>
            <span class="text-blue-600 font-black text-2xl"><?php echo $result->num_rows; ?></span>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- ===== SEARCH & FILTER BAR ===== -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-col sm:flex-row gap-3 items-end">
        <div class="flex-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Search</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Reference no., customer name..."
                    class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Pickup Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-lg text-sm transition">
                Filter
            </button>
            <?php if ($search || $date_filter): ?>
                <a href="pos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2.5 rounded-lg text-sm transition">
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ===== ORDER TABLE ===== -->
    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-900 text-white text-xs uppercase tracking-wider">
                    <th class="p-5 font-bold">Order Ref</th>
                    <th class="p-5 font-bold">Customer</th>
                    <th class="p-5 font-bold">Pickup Schedule</th>
                    <th class="p-5 font-bold text-center">Payment</th>
                    <th class="p-5 font-bold text-center">Details</th>
                    <th class="p-5 font-bold text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($order = $result->fetch_assoc()): ?>
                        <?php
                            $pickup_ts = strtotime($order['pickup_datetime']);
                            $now = time();
                            $diff = $pickup_ts - $now;

                            $row_class = "hover:bg-blue-50 transition group";
                            $time_badge_color = "bg-slate-100 text-slate-600";
                            $status_label = "";

                            if ($diff < 0) {
                                $row_class = "bg-red-50 hover:bg-red-100 transition group border-l-4 border-red-500";
                                $time_badge_color = "bg-red-200 text-red-800";
                                $status_label = "OVERDUE";
                            } elseif ($diff < 7200) {
                                $row_class = "bg-yellow-50 hover:bg-yellow-100 transition group border-l-4 border-yellow-400";
                                $time_badge_color = "bg-yellow-200 text-yellow-800";
                                $status_label = "URGENT";
                            }

                            // Fetch order items for this order
                            $items_stmt = $conn->prepare("SELECT oi.quantity, oi.price_at_checkout, p.name 
                                FROM order_items oi JOIN products p ON oi.product_id = p.product_id 
                                WHERE oi.order_id = ?");
                            $items_stmt->bind_param("i", $order['order_id']);
                            $items_stmt->execute();
                            $items_res = $items_stmt->get_result();
                            $order_items = $items_res->fetch_all(MYSQLI_ASSOC);
                            $items_stmt->close();

                            // Fetch past orders from same customer (last 5, excluding current)
                            $hist_stmt = $conn->prepare("SELECT tracking_no, total_amount, order_status, created_at 
                                FROM orders WHERE user_id = ? AND order_id != ? 
                                ORDER BY created_at DESC LIMIT 5");
                            $hist_stmt->bind_param("ii", $order['user_id'], $order['order_id']);
                            $hist_stmt->execute();
                            $hist_res = $hist_stmt->get_result();
                            $order_history = $hist_res->fetch_all(MYSQLI_ASSOC);
                            $hist_stmt->close();
                        ?>
                        <tr class="<?php echo $row_class; ?>">

                            <td class="p-5">
                                <div class="font-mono font-black text-slate-800 text-lg"><?php echo htmlspecialchars($order['tracking_no']); ?></div>
                                <div class="text-xs text-slate-500 mt-1">ID: #<?php echo $order['order_id']; ?></div>
                            </td>

                            <td class="p-5">
                                <div class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars($order['full_name'] ?: $order['username']); ?></div>
                                <div class="text-xs text-slate-500">@<?php echo htmlspecialchars($order['username']); ?></div>
                                <?php if (!empty($order['mobile'])): ?>
                                    <div class="text-xs text-blue-600 font-semibold mt-0.5">📞 <?php echo htmlspecialchars($order['mobile']); ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="p-5">
                                <span class="font-bold text-slate-800 text-lg flex items-center">
                                    <?php echo date('h:i A', $pickup_ts); ?>
                                    <?php if ($status_label): ?>
                                        <span class="ml-2 text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-wider <?php echo $time_badge_color; ?> animate-pulse">
                                            <?php echo $status_label; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1 block">
                                    <?php
                                        $date_str = date('Y-m-d', $pickup_ts);
                                        if ($date_str == date('Y-m-d')) echo "Today";
                                        elseif ($date_str == date('Y-m-d', strtotime('+1 day'))) echo "Tomorrow";
                                        else echo date('M j, Y', $pickup_ts);
                                    ?>
                                </span>
                            </td>

                            <td class="p-5 text-center">
                                <?php if ($order['balance_due'] > 0): ?>
                                    <div class="inline-block bg-red-50 border border-red-100 rounded-lg px-3 py-1">
                                        <div class="text-[10px] text-red-500 font-bold uppercase tracking-wider mb-0.5">Collect</div>
                                        <div class="font-black text-red-600 text-lg leading-none">₱<?php echo number_format($order['balance_due'], 2); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="inline-block bg-green-50 border border-green-100 rounded-lg px-3 py-1">
                                        <div class="text-[10px] text-green-600 font-bold uppercase tracking-wider mb-0.5">Status</div>
                                        <div class="font-black text-green-700 text-lg leading-none">PAID</div>
                                    </div>
                                <?php endif; ?>
                                <div class="text-[10px] text-slate-400 mt-1 uppercase"><?php echo strtoupper(str_replace('_', ' ', $order['payment_method'])); ?></div>
                            </td>

                            <!-- Details Button -->
                            <td class="p-5 text-center">
                                <button onclick="openDetailsModal(<?php echo $order['order_id']; ?>)"
                                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2 px-4 rounded-lg text-sm transition flex items-center gap-1.5 mx-auto">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    View
                                </button>
                            </td>

                            <td class="p-5 text-right">
                                <form action="../../core/cashier/claim_order.php" method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition shadow-md flex items-center ml-auto group-hover:scale-105 transform duration-200 border-b-4 border-blue-800 active:border-b-0 active:translate-y-1">
                                        Process
                                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    </button>
                                </form>
                            </td>

                        </tr>

                        <!-- Hidden Details Modal Data -->
                        <script>
                        (function() {
                            const data = {
                                order_id: <?php echo $order['order_id']; ?>,
                                tracking_no: <?php echo json_encode($order['tracking_no']); ?>,
                                customer_name: <?php echo json_encode($order['full_name'] ?: $order['username']); ?>,
                                username: <?php echo json_encode($order['username']); ?>,
                                contact: <?php echo json_encode($order['mobile'] ?? '—'); ?>,
                                pickup: <?php echo json_encode(date('F j, Y \a\t h:i A', $pickup_ts)); ?>,
                                total: <?php echo json_encode('₱' . number_format($order['total_amount'], 2)); ?>,
                                balance: <?php echo json_encode($order['balance_due'] > 0 ? '₱' . number_format($order['balance_due'], 2) : 'Fully Paid'); ?>,
                                payment_method: <?php echo json_encode(strtoupper(str_replace('_', ' ', $order['payment_method']))); ?>,
                                items: <?php echo json_encode(array_map(function($i) {
                                    return [
                                        'name' => $i['name'],
                                        'qty'  => $i['quantity'],
                                        'price' => '₱' . number_format($i['price_at_checkout'], 2),
                                        'subtotal' => '₱' . number_format($i['price_at_checkout'] * $i['quantity'], 2),
                                    ];
                                }, $order_items)); ?>,
                                history: <?php echo json_encode(array_map(function($h) {
                                    return [
                                        'tracking_no' => $h['tracking_no'],
                                        'total'       => '₱' . number_format($h['total_amount'], 2),
                                        'status'      => ucfirst($h['order_status']),
                                        'date'        => date('M j, Y', strtotime($h['created_at'])),
                                    ];
                                }, $order_history)); ?>
                            };
                            window.__orderData = window.__orderData || {};
                            window.__orderData[data.order_id] = data;
                        })();
                        </script>

                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-16 text-center text-gray-500">
                            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            <p class="text-2xl font-bold text-gray-700 mb-2"><?php echo ($search || $date_filter) ? 'No orders match your search.' : 'Queue is empty'; ?></p>
                            <p class="text-sm"><?php echo ($search || $date_filter) ? 'Try adjusting your filters.' : 'There are no pending orders. Great job keeping the line clear!'; ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<!-- ===== ORDER DETAILS + CUSTOMER INFO MODAL ===== -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-70 backdrop-blur-sm" onclick="closeDetailsModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">

            <!-- Modal Header -->
            <div class="bg-slate-900 rounded-t-2xl px-6 py-4 flex justify-between items-center">
                <div>
                    <h2 class="text-white font-black text-lg" id="modal_tracking">—</h2>
                    <p class="text-slate-400 text-xs mt-0.5">Order Details & Customer Info</p>
                </div>
                <button onclick="closeDetailsModal()" class="text-slate-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">

                <!-- Customer Info Panel -->
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                    <p class="text-xs font-bold text-blue-400 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Customer Information
                    </p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-blue-400 text-xs font-bold">Name</p>
                            <p class="font-black text-blue-900" id="modal_customer_name">—</p>
                            <p class="text-blue-500 text-xs" id="modal_username">—</p>
                        </div>
                        <div>
                            <p class="text-blue-400 text-xs font-bold">Contact</p>
                            <p class="font-bold text-blue-900" id="modal_contact">—</p>
                        </div>
                        <div>
                            <p class="text-blue-400 text-xs font-bold">Pickup Schedule</p>
                            <p class="font-bold text-blue-900" id="modal_pickup">—</p>
                        </div>
                        <div>
                            <p class="text-blue-400 text-xs font-bold">Payment Method</p>
                            <p class="font-bold text-blue-900" id="modal_payment_method">—</p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-blue-200 flex gap-6 text-sm">
                        <div>
                            <p class="text-blue-400 text-xs font-bold">Order Total</p>
                            <p class="font-black text-blue-900 text-lg" id="modal_total">—</p>
                        </div>
                        <div>
                            <p class="text-blue-400 text-xs font-bold">Balance Due</p>
                            <p class="font-black text-lg" id="modal_balance">—</p>
                        </div>
                    </div>
                </div>

                <!-- Items List -->
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Items in This Order
                    </p>
                    <div class="rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-4 py-2 text-left font-bold">Item</th>
                                    <th class="px-4 py-2 text-center font-bold">Qty</th>
                                    <th class="px-4 py-2 text-right font-bold">Unit Price</th>
                                    <th class="px-4 py-2 text-right font-bold">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="modal_items_body" class="divide-y divide-gray-100">
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order History -->
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Customer's Past Orders
                    </p>
                    <div id="modal_history" class="space-y-2 text-sm"></div>
                </div>

            </div>

            <div class="px-6 pb-5">
                <button onclick="closeDetailsModal()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl transition text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openDetailsModal(orderId) {
    const d = window.__orderData[orderId];
    if (!d) return;

    document.getElementById('modal_tracking').textContent       = d.tracking_no;
    document.getElementById('modal_customer_name').textContent  = d.customer_name;
    document.getElementById('modal_username').textContent       = '@' + d.username;
    document.getElementById('modal_contact').textContent        = d.contact || '—';
    document.getElementById('modal_pickup').textContent         = d.pickup;
    document.getElementById('modal_total').textContent          = d.total;
    document.getElementById('modal_payment_method').textContent = d.payment_method;

    const balEl = document.getElementById('modal_balance');
    balEl.textContent = d.balance;
    balEl.className = 'font-black text-lg ' + (d.balance === 'Fully Paid' ? 'text-green-600' : 'text-red-600');

    // Items
    const tbody = document.getElementById('modal_items_body');
    tbody.innerHTML = d.items.map(item => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2.5 font-semibold text-gray-800">${item.name}</td>
            <td class="px-4 py-2.5 text-center font-bold text-gray-700">${item.qty}</td>
            <td class="px-4 py-2.5 text-right text-gray-500">${item.price}</td>
            <td class="px-4 py-2.5 text-right font-black text-gray-900">${item.subtotal}</td>
        </tr>
    `).join('');

    // History
    const histDiv = document.getElementById('modal_history');
    if (d.history.length === 0) {
        histDiv.innerHTML = '<p class="text-gray-400 text-xs italic">No previous orders from this customer.</p>';
    } else {
        const statusColors = {
            'Pending': 'bg-yellow-100 text-yellow-700',
            'Completed': 'bg-gray-100 text-gray-600',
            'Cancelled': 'bg-red-100 text-red-600',
            'Ready': 'bg-green-100 text-green-700',
            'Processing': 'bg-blue-100 text-blue-700',
        };
        histDiv.innerHTML = d.history.map(h => `
            <div class="flex justify-between items-center bg-gray-50 border border-gray-100 rounded-lg px-4 py-2.5">
                <div>
                    <span class="font-mono font-bold text-gray-700 text-sm">${h.tracking_no}</span>
                    <span class="text-xs text-gray-400 ml-2">${h.date}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-black text-gray-800">${h.total}</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full ${statusColors[h.status] || 'bg-gray-100 text-gray-600'}">${h.status}</span>
                </div>
            </div>
        `).join('');
    }

    document.getElementById('detailsModal').classList.remove('hidden');
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.add('hidden');
}
</script>

<?php
require_once '../../includes/cashier_footer.php';
$conn->close();
?>