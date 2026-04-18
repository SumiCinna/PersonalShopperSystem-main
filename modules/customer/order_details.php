<?php
// modules/customer/order_details.php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// ── Load Order ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// ── Load Order Items ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT oi.quantity, oi.price_at_checkout, p.name, p.category, p.image_url
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
if (!$stmt) die('order_items query failed: ' . $conn->error);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Calculate Breakdown ────────────────────────────────────────────────────────
$derived_subtotal = 0;
foreach ($order_items as $item) {
    $derived_subtotal += $item['price_at_checkout'] * $item['quantity'];
}
$derived_vat         = round($derived_subtotal * 0.12, 2);
$derived_service_fee = round($derived_subtotal * 0.10, 2);

// ── Payment Type Labels ───────────────────────────────────────────────────────
$pt_labels = [
    'full'       => 'Full Payment',
    'partial_50' => '50% Downpayment',
    'partial_30' => '30% Downpayment',
];
$pt_label = $pt_labels[$order['payment_type']] ?? 'Payment';

// ── Payment method display (fallback to Card) ─────────────────────────────────
$payment_method_display = $order['payment_method'];
if (!$payment_method_display || strtolower($payment_method_display) === 'unpaid' || trim($payment_method_display) === '') {
    $payment_method_display = 'Card';
}

// ── Derived values ────────────────────────────────────────────────────────────
$is_full_paid     = ($order['payment_type'] === 'full'       && $order['payment_status'] === 'paid');
$is_partial_50    = ($order['payment_type'] === 'partial_50' && $order['payment_status'] === 'paid');
$is_partial_30    = ($order['payment_type'] === 'partial_30' && $order['payment_status'] === 'paid');
$has_balance      = floatval($order['balance_due']) > 0 && $order['payment_status'] === 'paid';
$paid_pct         = match($order['payment_type']) {
    'partial_50' => 50,
    'partial_30' => 30,
    default      => 100,
};

$pickup_formatted  = $order['pickup_datetime']
    ? date('D, M j, Y \a\t g:i A', strtotime($order['pickup_datetime']))
    : 'Not set';
$created_formatted = date('M j, Y \a\t g:i A', strtotime($order['created_at']));

// ── Cancellation Logic ────────────────────────────────────────────────────────
$is_cancellable = false;
$time_remaining = 0;
if ($order['order_status'] === 'pending' && $order['payment_status'] === 'pending') {
    $created_time      = new DateTime($order['created_at']);
    $current_time      = new DateTime();
    $interval          = $current_time->getTimestamp() - $created_time->getTimestamp();
    $cancellation_window = 30 * 60;

    if ($interval < $cancellation_window) {
        $is_cancellable = true;
        $time_remaining = $cancellation_window - $interval;
    }
}

$page_title = 'Order Details — ' . htmlspecialchars($order['tracking_no']);
require_once '../../includes/customer_header.php';

// ── Status badge helpers ──────────────────────────────────────────────────────
function orderStatusBadge($status) {
    $map = [
        'pending'    => ['bg-yellow-100 text-yellow-800 border-yellow-200', 'bg-yellow-500', 'Pending'],
        'processing' => ['bg-blue-100 text-blue-800 border-blue-200',       'bg-blue-500',   'Processing'],
        'ready'      => ['bg-green-100 text-green-800 border-green-200',    'bg-green-500',  'Ready for Pickup'],
        'completed'  => ['bg-gray-100 text-gray-700 border-gray-200',       'bg-gray-400',   'Completed'],
        'cancelled'  => ['bg-red-100 text-red-800 border-red-200',          'bg-red-500',    'Cancelled'],
    ];
    [$cls, $dot, $label] = $map[$status] ?? ['bg-gray-100 text-gray-600 border-gray-200', 'bg-gray-400', ucfirst($status)];
    return "<span class=\"inline-flex items-center px-3 py-1 rounded-full text-sm font-bold border $cls\">
                <span class=\"w-2 h-2 rounded-full $dot mr-2\"></span>$label
            </span>";
}

function paymentStatusBadge($status) {
    if ($status === 'paid') {
        return '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-100 text-green-800 border border-green-200">✓ Paid</span>';
    }
    return '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-yellow-100 text-yellow-800 border border-yellow-200">⏳ Pending</span>';
}
?>

<main class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-6 px-4">
<div class="max-w-7xl mx-auto space-y-4">

    <!-- Back Button -->
    <a href="orders.php" class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-semibold transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Orders
    </a>

    <!-- ── Header Card ──────────────────────────────────────────────────────── -->
    <div class="bg-gradient-to-r from-blue-700 to-indigo-700 rounded-2xl shadow-lg text-white px-7 py-5">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3">
            <div>
                <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-0.5">Order Details</p>
                <h1 class="text-2xl font-black tracking-tight"><?php echo htmlspecialchars($order['tracking_no']); ?></h1>
                <p class="text-blue-200 text-sm mt-0.5">Placed on <?php echo $created_formatted; ?></p>
            </div>
            <div class="flex flex-row md:flex-row items-center gap-2">
                <?php echo orderStatusBadge($order['order_status']); ?>
                <?php echo paymentStatusBadge($order['payment_status']); ?>
            </div>
        </div>
    </div>

    <!-- ── Cancellation Timer ───────────────────────────────────────────────── -->
    <?php if ($is_cancellable): ?>
    <div id="cancellation-banner" class="bg-red-50 border-2 border-dashed border-red-200 rounded-2xl px-5 py-4 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div>
            <h3 class="font-black text-red-800">Need to cancel?</h3>
            <p class="text-sm text-red-600">You can cancel within the next <strong><span id="countdown-timer">--:--</span></strong> minutes.</p>
        </div>
        <form action="../../core/customer/cancel_order.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? This action cannot be undone.');">
            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-5 rounded-xl transition shadow-md">
                Cancel Order
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Main Grid: Payment + Right Column ────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

        <!-- LEFT: Payment Breakdown (wider) -->
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <h2 class="font-black text-gray-800 text-base">Payment Breakdown</h2>
            </div>

            <div class="p-5">
                <!-- Payment Type Banner -->
                <?php if ($is_full_paid): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4 flex items-center gap-3">
                    <div class="w-9 h-9 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-black text-green-800 text-sm">Fully Paid — No balance due!</p>
                        <p class="text-xs text-green-600">₱<?php echo number_format($order['total_amount'], 2); ?> paid in full online via <?php echo ucfirst($payment_method_display); ?>.</p>
                    </div>
                </div>
                <?php elseif ($is_partial_50 || $is_partial_30): ?>
                <div class="bg-orange-50 border border-orange-200 rounded-xl p-3 mb-4 flex items-center gap-3">
                    <div class="w-9 h-9 bg-orange-400 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-black text-orange-800 text-sm"><?php echo $paid_pct; ?>% Downpayment Paid — Balance due at store pickup</p>
                        <p class="text-xs text-orange-600">Please bring <strong>₱<?php echo number_format($order['balance_due'], 2); ?></strong> when you pick up your order.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment Progress Bar -->
                <?php if ($order['payment_status'] === 'paid'): ?>
                <div class="mb-4">
                    <div class="flex justify-between text-xs font-semibold text-gray-500 mb-1">
                        <span>Payment progress</span>
                        <span><?php echo $paid_pct; ?>% paid</span>
                    </div>
                    <div class="bg-gray-200 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full" style="width: <?php echo $paid_pct; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Amount Rows -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-600 text-sm">Items Subtotal</p>
                        <span class="text-sm font-bold text-gray-800">₱<?php echo number_format($derived_subtotal, 2); ?></span>
                    </div>

                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-600 text-sm">VAT (12%)</p>
                        <span class="text-sm font-bold text-gray-800">₱<?php echo number_format($derived_vat, 2); ?></span>
                    </div>

                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-600 text-sm">Service Fee (10%)</p>
                        <span class="text-sm font-bold text-gray-800">₱<?php echo number_format($derived_service_fee, 2); ?></span>
                    </div>

                    <div class="flex justify-between items-center py-2.5 border-b border-gray-100 bg-blue-50 px-3 rounded-lg">
                        <div>
                            <p class="font-bold text-blue-800 text-sm">Grand Total</p>
                            <p class="text-xs text-blue-600">Total amount after fees</p>
                        </div>
                        <span class="text-lg font-black text-blue-700">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>

                    <div class="flex justify-between items-center py-2.5 border-b border-gray-100">
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">
                                <?php echo $pt_label; ?>
                                <span class="ml-2 text-xs font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                                    <?php echo $paid_pct; ?>%
                                </span>
                            </p>
                            <p class="text-xs text-gray-500">Paid online via <?php echo ucfirst($payment_method_display); ?></p>
                        </div>
                        <span class="text-base font-black <?php echo $order['payment_status'] === 'paid' ? 'text-green-600' : 'text-yellow-500'; ?>">
                            <?php echo $order['payment_status'] === 'paid' ? '✓ ' : '⏳ '; ?>₱<?php echo number_format($order['upfront_payment'], 2); ?>
                        </span>
                    </div>

                    <?php if (floatval($order['balance_due']) > 0): ?>
                    <div class="flex justify-between items-center py-2.5 bg-orange-50 rounded-xl px-4 border border-orange-200">
                        <div>
                            <p class="font-bold text-orange-800 text-sm">Balance Due at Store Pickup</p>
                            <p class="text-xs text-orange-600">Pay this amount when you collect your order</p>
                        </div>
                        <span class="text-base font-black text-orange-700">₱<?php echo number_format($order['balance_due'], 2); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="flex justify-between items-center py-2.5 bg-green-50 rounded-xl px-4 border border-green-200">
                        <p class="font-bold text-green-700 text-sm">No Balance Due</p>
                        <span class="text-base font-black text-green-600">₱0.00</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Pickup + Order Progress stacked -->
        <div class="lg:col-span-2 flex flex-col gap-4">

            <!-- Pickup Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex-1">
                <h2 class="font-black text-gray-800 text-base mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Pickup Schedule
                </h2>
                <div class="bg-amber-50 rounded-xl p-4 border border-amber-200">
                    <p class="text-xs text-amber-700 font-semibold mb-1">Scheduled for</p>
                    <p class="text-lg font-black text-gray-900"><?php echo $pickup_formatted; ?></p>
                    <p class="text-xs text-amber-600 mt-2">Please arrive 10–15 minutes early. Items will be reserved for your pickup time.</p>
                </div>
            </div>

            <!-- Order Status Timeline -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex-1">
                <h2 class="font-black text-gray-800 text-base mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Order Progress
                </h2>
                <?php
                $stages      = ['pending', 'processing', 'ready', 'completed'];
                $current_idx = array_search($order['order_status'], $stages);
                $current_idx = ($current_idx === false) ? 0 : $current_idx;
                $labels      = ['Order Placed', 'Being Prepared', 'Ready for Pickup', 'Completed'];
                $descs       = ['Your order was received.', 'Staff are preparing your items.', 'Head to the pickup counter!', 'Order successfully collected.'];
                ?>
                <div class="space-y-2">
                    <?php foreach ($stages as $i => $stage): ?>
                    <?php
                    $done   = $i <= $current_idx;
                    $active = $i === $current_idx;
                    $dotCls = $done ? 'bg-blue-600' : 'bg-gray-200';
                    $lblCls = $done ? 'text-gray-900 font-bold' : 'text-gray-400';
                    ?>
                    <div class="flex items-start gap-3">
                        <div class="flex flex-col items-center">
                            <div class="w-7 h-7 rounded-full <?php echo $dotCls; ?> flex items-center justify-center flex-shrink-0">
                                <?php if ($done): ?>
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                                <?php else: ?>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($i < count($stages) - 1): ?>
                            <div class="w-0.5 h-3 <?php echo $done && $i < $current_idx ? 'bg-blue-300' : 'bg-gray-200'; ?> mt-0.5"></div>
                            <?php endif; ?>
                        </div>
                        <div class="pb-1">
                            <p class="text-sm <?php echo $lblCls; ?>"><?php echo $labels[$i]; ?></p>
                            <?php if ($active): ?>
                            <p class="text-xs text-blue-500 font-semibold"><?php echo $descs[$i]; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div><!-- /main grid -->

    <!-- ── Order Items Card ─────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            <h2 class="font-black text-gray-800 text-base">Items Ordered (<?php echo count($order_items); ?>)</h2>
        </div>

        <!-- Desktop -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                        <th class="px-6 py-3 text-left font-semibold">Product</th>
                        <th class="px-6 py-3 text-center font-semibold">Qty</th>
                        <th class="px-6 py-3 text-right font-semibold">Unit Price</th>
                        <th class="px-6 py-3 text-right font-semibold">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($order_items as $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($item['name']); ?></p>
                            <?php if (!empty($item['category'])): ?>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($item['category']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-3 text-center">
                            <span class="bg-blue-100 text-blue-700 font-black text-sm px-3 py-0.5 rounded-full">
                                <?php echo $item['quantity']; ?>x
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right text-sm font-semibold text-gray-700">
                            ₱<?php echo number_format($item['price_at_checkout'], 2); ?>
                        </td>
                        <td class="px-6 py-3 text-right font-black text-gray-900 text-sm">
                            ₱<?php echo number_format($item['price_at_checkout'] * $item['quantity'], 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 border-t-2 border-gray-200">
                        <td colspan="3" class="px-6 py-2 text-right font-bold text-gray-600 text-xs uppercase">Items Subtotal</td>
                        <td class="px-6 py-2 text-right font-bold text-gray-900 text-sm">₱<?php echo number_format($derived_subtotal, 2); ?></td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td colspan="3" class="px-6 py-2 text-right font-bold text-gray-600 text-xs uppercase">VAT (12%)</td>
                        <td class="px-6 py-2 text-right font-bold text-gray-900 text-sm">₱<?php echo number_format($derived_vat, 2); ?></td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td colspan="3" class="px-6 py-2 text-right font-bold text-gray-600 text-xs uppercase">Service Fee (10%)</td>
                        <td class="px-6 py-2 text-right font-bold text-gray-900 text-sm">₱<?php echo number_format($derived_service_fee, 2); ?></td>
                    </tr>
                    <tr class="bg-blue-50 border-t border-blue-100">
                        <td colspan="3" class="px-6 py-3 text-right font-black text-blue-800 text-sm uppercase tracking-wider">Grand Total</td>
                        <td class="px-6 py-3 text-right font-black text-blue-700 text-base">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                    </tr>
                    <?php if ($order['payment_status'] === 'paid'): ?>
                    <tr class="bg-green-50">
                        <td colspan="3" class="px-6 py-2 text-right font-bold text-green-700 text-sm">Paid Online (<?php echo $pt_label; ?>)</td>
                        <td class="px-6 py-2 text-right font-black text-green-700 text-sm">₱<?php echo number_format($order['upfront_payment'], 2); ?></td>
                    </tr>
                    <?php if ($order['balance_due'] > 0): ?>
                    <tr class="bg-orange-50">
                        <td colspan="3" class="px-6 py-2 text-right font-bold text-orange-700 text-sm">Balance Due at Pickup</td>
                        <td class="px-6 py-2 text-right font-black text-orange-700 text-sm">₱<?php echo number_format($order['balance_due'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>

        <!-- Mobile item list -->
        <div class="md:hidden divide-y divide-gray-100">
            <?php foreach ($order_items as $item): ?>
            <div class="px-5 py-3 flex justify-between items-center">
                <div class="flex-1">
                    <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($item['name']); ?></p>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($item['category']); ?> &bull; ₱<?php echo number_format($item['price_at_checkout'], 2); ?> each</p>
                </div>
                <div class="text-right ml-4">
                    <span class="bg-blue-100 text-blue-700 font-black text-xs px-2 py-0.5 rounded-full"><?php echo $item['quantity']; ?>x</span>
                    <p class="font-black text-gray-900 text-sm mt-1">₱<?php echo number_format($item['price_at_checkout'] * $item['quantity'], 2); ?></p>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Mobile Total Summary -->
            <div class="px-5 py-3 bg-gray-50 space-y-2">
                <div class="flex justify-between text-xs text-gray-500">
                    <span>Subtotal</span>
                    <span>₱<?php echo number_format($derived_subtotal, 2); ?></span>
                </div>
                <div class="flex justify-between text-xs text-gray-500">
                    <span>VAT (12%)</span>
                    <span>₱<?php echo number_format($derived_vat, 2); ?></span>
                </div>
                <div class="flex justify-between text-xs text-gray-500">
                    <span>Service Fee (10%)</span>
                    <span>₱<?php echo number_format($derived_service_fee, 2); ?></span>
                </div>
                <div class="flex justify-between font-black text-blue-700 text-sm pt-1 border-t border-dashed border-gray-300">
                    <span>Grand Total</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <?php if ($order['payment_status'] === 'paid'): ?>
                <div class="flex justify-between text-sm font-bold text-green-600">
                    <span>Paid (<?php echo $pt_label; ?>)</span>
                    <span>₱<?php echo number_format($order['upfront_payment'], 2); ?></span>
                </div>
                <?php if ($order['balance_due'] > 0): ?>
                <div class="flex justify-between text-sm font-bold text-orange-600">
                    <span>Balance at Pickup</span>
                    <span>₱<?php echo number_format($order['balance_due'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Footer Actions ───────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row gap-3 pb-4">
        <a href="orders.php"
           class="flex-1 flex items-center justify-center gap-2 py-2.5 px-5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold rounded-xl transition shadow-sm text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Orders
        </a>

        <?php if ($order['order_status'] !== 'cancelled'): ?>
            <a href="receipt.php?order_id=<?php echo $order_id; ?>"
               class="flex-1 flex items-center justify-center gap-2 py-2.5 px-5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition shadow-md text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                View Receipt
            </a>
        <?php endif; ?>
    </div>

</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($is_cancellable): ?>
    const countdownElement = document.getElementById('countdown-timer');
    const cancellationBanner = document.getElementById('cancellation-banner');
    let timeLeft = <?php echo $time_remaining; ?>;

    function updateCountdown() {
        if (timeLeft <= 0) {
            countdownElement.textContent = '00:00';
            if (cancellationBanner) cancellationBanner.style.display = 'none';
            clearInterval(timerInterval);
            return;
        }
        const minutes = Math.floor(timeLeft / 60);
        let seconds = timeLeft % 60;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        countdownElement.textContent = `${minutes}:${seconds}`;
        timeLeft--;
    }

    updateCountdown();
    const timerInterval = setInterval(updateCountdown, 1000);
    <?php endif; ?>
});
</script>

<?php
require_once '../../includes/customer_footer.php';
$conn->close();
?>