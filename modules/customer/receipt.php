<?php
// modules/customer/receipt.php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$order_id = intval($_GET['order_id'] ?? 0);

if (!$order_id) {
    header("Location: home.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: home.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT oi.quantity, oi.price_at_checkout, p.name, p.category
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
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

// ── Payment method display (fallback to Card) ─────────────────────────────────
$payment_method_display = $order['payment_method'];
if (!$payment_method_display || strtolower(trim($payment_method_display)) === 'unpaid' || trim($payment_method_display) === '') {
    $payment_method_display = 'Card';
}

// ── Derived values ─────────────────────────────────────────────────────────────
$pt_labels = [
    'full'       => 'Full Payment',
    'partial_50' => '50% Downpayment',
    'partial_30' => '30% Downpayment',
];
$pt_label = $pt_labels[$order['payment_type']] ?? 'Payment';

$paid_pct = match($order['payment_type']) {
    'partial_50' => 50,
    'partial_30' => 30,
    default      => 100,
};
$has_balance = floatval($order['balance_due']) > 0;

$pickup_datetime = $order['pickup_datetime']
    ? date('D, M j, Y \a\t g:i A', strtotime($order['pickup_datetime']))
    : 'TBD';
$order_date = date('M j, Y \a\t g:i A', strtotime($order['created_at']));

$page_title = 'Order Receipt';
require_once '../../includes/customer_header.php';
?>

<style>
/* Plain monochrome styling only for the receipt content area */
.receipt-plain .receipt-mono {
    border-top: 1px solid #000;
}

.receipt-plain .receipt-mono,
.receipt-plain .receipt-mono * {
    color: #000 !important;
}

.receipt-plain .receipt-mono [class*="bg-"],
.receipt-plain .receipt-mono [class*="from-"],
.receipt-plain .receipt-mono [class*="to-"] {
    background: #fff !important;
}

.receipt-plain .receipt-mono [class*="border-"] {
    border-color: #000 !important;
}

.receipt-plain .receipt-mono .divide-y > :not([hidden]) ~ :not([hidden]),
.receipt-plain .receipt-mono .divide-gray-100 > :not([hidden]) ~ :not([hidden]),
.receipt-plain .receipt-mono .divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
    border-color: #000 !important;
}

.receipt-plain .receipt-mono th,
.receipt-plain .receipt-mono td {
    border-color: #000 !important;
}

.receipt-plain .receipt-mono a {
    color: #000 !important;
    text-decoration: underline;
}

.receipt-plain .receipt-content .receipt-section {
    width: 100%;
}

.receipt-plain .receipt-content .amount-row {
    align-items: flex-start;
    gap: 0.5rem;
}

.receipt-plain .receipt-content .amount-value {
    min-width: 110px;
    text-align: right;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 4mm;
    }

    nav, footer, .no-print { display: none !important; }
    .receipt-plain { padding: 0 !important; background: #fff !important; }
    .receipt-plain > .max-w-2xl > :not(.receipt-card) { display: none !important; }

    .receipt-plain .receipt-card {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        border: 1px solid #000 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    .receipt-plain .receipt-mono {
        border-top: 1px solid #000 !important;
        font-size: 10px !important;
        line-height: 1.15 !important;
    }

    .receipt-plain .receipt-content {
        padding: 6px !important;
    }

    .receipt-plain .receipt-content table th,
    .receipt-plain .receipt-content table td {
        padding: 2px 4px !important;
        font-size: 9px !important;
    }

    .receipt-plain .receipt-content .text-3xl { font-size: 1.1rem !important; }
    .receipt-plain .receipt-content .text-2xl { font-size: 1rem !important; }
    .receipt-plain .receipt-content .text-xl { font-size: 0.88rem !important; }
    .receipt-plain .receipt-content .text-lg { font-size: 0.8rem !important; }
    .receipt-plain .receipt-content .text-base { font-size: 0.72rem !important; }
    .receipt-plain .receipt-content .text-sm { font-size: 0.64rem !important; }
    .receipt-plain .receipt-content .text-xs { font-size: 0.58rem !important; }

    .receipt-plain .receipt-content .space-y-2 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.18rem !important;
    }

    .receipt-plain .receipt-content .gap-5 { gap: 0.35rem !important; }
    .receipt-plain .receipt-content .gap-4,
    .receipt-plain .receipt-content .gap-3,
    .receipt-plain .receipt-content .gap-2 { gap: 0.2rem !important; }

    .receipt-plain .receipt-content .p-6 { padding: 0.35rem !important; }
    .receipt-plain .receipt-content .p-5,
    .receipt-plain .receipt-content .p-4 { padding: 0.3rem !important; }
    .receipt-plain .receipt-content .py-4,
    .receipt-plain .receipt-content .py-3 { padding-top: 0.2rem !important; padding-bottom: 0.2rem !important; }
    .receipt-plain .receipt-content .px-5,
    .receipt-plain .receipt-content .px-4 { padding-left: 0.3rem !important; padding-right: 0.3rem !important; }

    .receipt-plain .receipt-content .mb-4,
    .receipt-plain .receipt-content .mb-3,
    .receipt-plain .receipt-content .mt-4,
    .receipt-plain .receipt-content .mt-2,
    .receipt-plain .receipt-content .mt-1 {
        margin-top: 0.15rem !important;
        margin-bottom: 0.15rem !important;
    }
}
</style>

<main class="receipt-plain min-h-screen bg-white py-6 px-4">
<div class="max-w-2xl mx-auto">

    <!-- ── Payment Success Banner ─────────────────────────────────────────── -->
    <div class="mb-8 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg overflow-hidden text-white p-7">
        <div class="flex items-center gap-4">
            <div class="bg-white bg-opacity-20 rounded-full p-3 flex-shrink-0">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <h1 class="text-3xl font-black">Payment Successful!</h1>
                <p class="text-green-100 text-sm mt-0.5">
                    <?php if ($paid_pct === 100): ?>
                        Your order has been fully paid and confirmed.
                    <?php else: ?>
                        Your <?php echo $paid_pct; ?>% downpayment has been received. Bring the balance when you pick up.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ── Main Receipt Card ──────────────────────────────────────────────── -->
    <div class="receipt-card bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">

        <div class="receipt-mono">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-700 px-8 py-6 text-white">
            <div class="flex justify-between items-center gap-6">
                <div>
                    <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1">Official Receipt</p>
                    <p class="text-3xl font-black tracking-tight"><?php echo htmlspecialchars($order['tracking_no']); ?></p>
                    <p class="text-blue-200 text-sm mt-1"><?php echo $order_date; ?></p>
                </div>
                <div class="text-right min-w-[170px] flex flex-col items-end justify-center leading-tight">
                    <span class="inline-block bg-white bg-opacity-20 text-white text-xs font-bold px-3 py-1 rounded-full text-right whitespace-nowrap">
                        <?php echo $pt_label; ?>
                    </span>
                    <p class="text-blue-100 text-xs text-right mt-1">via <?php echo ucfirst($payment_method_display); ?></p>
                </div>
            </div>
        </div>

        <div class="receipt-content px-5 py-5 space-y-2">

            <!-- ── Status Row ──────────────────────────────────────────────── -->
            <div class="receipt-section grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="bg-blue-50 rounded-xl p-4 border border-blue-100 text-center">
                    <p class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-1">Order Status</p>
                    <p class="text-base font-black text-blue-900 capitalize"><?php echo ucfirst($order['order_status']); ?></p>
                </div>
                <div class="bg-green-50 rounded-xl p-4 border border-green-100 text-center">
                    <p class="text-xs font-bold text-green-400 uppercase tracking-widest mb-1">Payment</p>
                    <p class="text-base font-black text-green-900">Confirmed ✓</p>
                </div>
                <!-- METHOD now uses $payment_method_display with Card fallback -->
                <div class="bg-purple-50 rounded-xl p-4 border border-purple-100 text-center">
                    <p class="text-xs font-bold text-purple-400 uppercase tracking-widest mb-1">Method</p>
                    <p class="text-base font-black text-purple-900 capitalize"><?php echo htmlspecialchars(ucfirst($payment_method_display)); ?></p>
                </div>
            </div>

            <!-- ── Payment Breakdown ──────────────────────────────────────── -->
            <div class="receipt-section">
                <h3 class="font-black text-gray-800 text-base mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    Payment Breakdown
                </h3>

                <!-- Payment type info card -->
                <?php if ($paid_pct === 100): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="font-bold text-green-800">Fully Paid — No balance due at pickup</p>
                        <p class="text-xs text-green-600">The complete order amount was paid online via <?php echo ucfirst($payment_method_display); ?>.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-bold text-orange-800">
                            <?php echo $paid_pct; ?>% Paid Online — Bring
                            <span class="text-orange-900">₱<?php echo number_format($order['balance_due'], 2); ?></span>
                            at pickup
                        </p>
                        <p class="text-xs text-orange-600">You paid <?php echo $paid_pct; ?>% (₱<?php echo number_format($order['upfront_payment'], 2); ?>) as a downpayment via <?php echo ucfirst($payment_method_display); ?>.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Amount rows -->
                <div class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
                    <div class="divide-y divide-gray-200">

                        <div class="amount-row flex justify-between items-center px-5 py-2">
                            <span class="text-sm text-gray-500">Items Subtotal</span>
                            <span class="amount-value font-semibold text-gray-700">₱<?php echo number_format($derived_subtotal, 2); ?></span>
                        </div>

                        <div class="amount-row flex justify-between items-center px-5 py-2">
                            <span class="text-sm text-gray-500">VAT (12%)</span>
                            <span class="amount-value font-semibold text-gray-700">₱<?php echo number_format($derived_vat, 2); ?></span>
                        </div>

                        <div class="amount-row flex justify-between items-center px-5 py-2">
                            <span class="text-sm text-gray-500">Service Fee (10%)</span>
                            <span class="amount-value font-semibold text-gray-700">₱<?php echo number_format($derived_service_fee, 2); ?></span>
                        </div>

                        <div class="amount-row flex justify-between items-center px-5 py-3 bg-green-50">
                            <div>
                                <p class="text-sm font-bold text-green-700">
                                    ✓ Paid Online
                                    <span class="ml-2 text-xs font-bold bg-green-200 text-green-800 px-2 py-0.5 rounded-full"><?php echo $pt_label; ?></span>
                                </p>
                                <p class="text-xs text-green-600">via <?php echo ucfirst($payment_method_display); ?></p>
                            </div>
                            <span class="amount-value font-black text-green-700 text-lg">₱<?php echo number_format($order['upfront_payment'], 2); ?></span>
                        </div>

                        <?php if ($has_balance): ?>
                        <div class="amount-row flex justify-between items-center px-5 py-3 bg-orange-50">
                            <div>
                                <p class="text-sm font-bold text-orange-700">⚠ Balance Due at Store Pickup</p>
                                <p class="text-xs text-orange-500">Pay the cashier when you collect your order</p>
                            </div>
                            <span class="amount-value font-black text-orange-700 text-lg">₱<?php echo number_format($order['balance_due'], 2); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="amount-row flex justify-between items-center px-5 py-3 bg-green-50">
                            <span class="text-sm font-bold text-green-600">No balance due at store</span>
                            <span class="amount-value font-black text-green-600">₱0.00</span>
                        </div>
                        <?php endif; ?>

                        <div class="amount-row flex justify-between items-center px-5 py-4 bg-white border-t border-gray-100">
                            <span class="text-base font-black text-gray-900 uppercase tracking-wider">Grand Total</span>
                            <span class="amount-value text-2xl font-black text-blue-700">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment progress bar -->
                <div class="mt-4">
                    <div class="flex justify-between text-xs font-bold text-gray-500 mb-1">
                        <span>Payment progress</span>
                        <span><?php echo $paid_pct; ?>% paid online</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-green-500 h-2.5 rounded-full" style="width: <?php echo $paid_pct; ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>₱<?php echo number_format($order['upfront_payment'], 2); ?> paid</span>
                        <?php if ($has_balance): ?>
                        <span>₱<?php echo number_format($order['balance_due'], 2); ?> remaining</span>
                        <?php else: ?>
                        <span class="text-green-600 font-semibold">Complete ✓</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Pickup Details ─────────────────────────────────────────── -->
            <div class="receipt-section bg-amber-50 border border-amber-200 rounded-xl p-6">
                <h3 class="font-black text-gray-800 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Pickup Schedule
                </h3>
                <p class="text-xl font-black text-gray-900"><?php echo $pickup_datetime; ?></p>
                <p class="text-sm text-amber-700 mt-2">Please arrive 10–15 minutes early. Items are reserved for your scheduled time.</p>
            </div>

            <!-- ── Order Items ────────────────────────────────────────────── -->
            <div class="receipt-section">
                <h3 class="font-black text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Items Ordered
                </h3>
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3 text-left font-semibold">Product</th>
                                <th class="px-4 py-3 text-center font-semibold">Qty</th>
                                <th class="px-4 py-3 text-right font-semibold">Unit</th>
                                <th class="px-4 py-3 text-right font-semibold">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($order_items as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($item['name']); ?></p>
                                    <?php if (!empty($item['category'])): ?>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($item['category']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-gray-700 text-sm"><?php echo $item['quantity']; ?></td>
                                <td class="px-4 py-3 text-right text-sm text-gray-600">₱<?php echo number_format($item['price_at_checkout'], 2); ?></td>
                                <td class="px-4 py-3 text-right font-black text-gray-900 text-sm">₱<?php echo number_format($item['price_at_checkout'] * $item['quantity'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Important Notes ────────────────────────────────────────── -->
            <div class="receipt-section bg-blue-50 border border-blue-200 rounded-xl p-6">
                <h4 class="font-black text-blue-900 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    Important Information
                </h4>
                <ul class="space-y-2 text-sm text-blue-900">
                    <li class="flex gap-2">
                        <span class="text-blue-600 font-bold flex-shrink-0">✓</span>
                        <span>Save your tracking number <strong><?php echo htmlspecialchars($order['tracking_no']); ?></strong> for reference.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-blue-600 font-bold flex-shrink-0">✓</span>
                        <span>Arrive <strong>10–15 minutes before</strong> your scheduled pickup time.</span>
                    </li>
                    <?php if ($has_balance): ?>
                    <li class="flex gap-2">
                        <span class="text-orange-500 font-bold flex-shrink-0">⚠</span>
                        <span>
                            You paid <strong><?php echo $paid_pct; ?>% (₱<?php echo number_format($order['upfront_payment'], 2); ?>)</strong> online via <strong><?php echo ucfirst($payment_method_display); ?></strong>.
                            Please bring the remaining <strong>₱<?php echo number_format($order['balance_due'], 2); ?></strong> at pickup.
                            Accepted payments: Cash and Card.
                        </span>
                    </li>
                    <?php else: ?>
                    <li class="flex gap-2">
                        <span class="text-green-600 font-bold flex-shrink-0">✓</span>
                        <span>Your order is <strong>fully paid</strong>. No additional payment is required at pickup.</span>
                    </li>
                    <?php endif; ?>
                    <li class="flex gap-2">
                        <span class="text-blue-600 font-bold flex-shrink-0">✓</span>
                        <span>Track your order status in <a href="orders.php" class="underline font-semibold">My Orders</a>.</span>
                    </li>
                </ul>
            </div>

    </div><!-- /px-8 py-8 -->
    </div><!-- /receipt-mono -->

        <!-- Footer Actions -->
        <div class="px-8 py-5 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row gap-3 justify-end no-print">
            <button onclick="window.print()"
                class="flex items-center justify-center gap-2 px-5 py-3 bg-white hover:bg-gray-100 text-gray-700 font-bold rounded-xl border border-gray-300 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print Receipt
            </button>
            <a href="order_details.php?id=<?php echo $order_id; ?>"
               class="flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Order Details
            </a>
            <a href="orders.php"
               class="flex items-center justify-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                My Orders
            </a>
            <a href="home.php"
               class="flex items-center justify-center gap-2 px-5 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                Keep Shopping
            </a>
        </div>

    </div><!-- /receipt card -->
</div>
</main>

<?php
require_once '../../includes/customer_footer.php';
$conn->close();
?>