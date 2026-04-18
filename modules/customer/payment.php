<?php
// modules/customer/payment.php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../../config/config.php';

// ─── PayMongo Config ───────────────────────────────────────────────────────────
define('PM_SECRET_KEY', 'sk_live_rBTsJk46egiGgkUotLSbqr7c');
define('PM_PUBLIC_KEY', 'pk_live_rgVrjUiMztGxTuqsCsZeKsrz');
define('PM_BASE_URL',   'https://api.paymongo.com/v1');

// ─── Helper ────────────────────────────────────────────────────────────────────
function pm_request(string $endpoint, string $method = 'GET', array $payload = []): array {
    $ch = curl_init(PM_BASE_URL . $endpoint);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(PM_SECRET_KEY . ':'),
        ],
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    curl_close($ch);
    return json_decode($raw, true) ?? [];
}

// ─── Security ─────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ─── Load order ────────────────────────────────────────────────────────────────
$order_id = intval($_GET['order_id'] ?? 0);

if (!$order_id && isset($_SESSION['pending_order_id'])) {
    $order_id = intval($_SESSION['pending_order_id']);
}

if (!$order_id) {
    header("Location: cart.php");
    exit();
}

// Always validate order belongs to this user
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    unset($_SESSION['pending_order_id']);
    header("Location: cart.php");
    exit();
}

$_SESSION['pending_order_id'] = $order_id;

if ($order['payment_status'] === 'paid') {
    header("Location: receipt.php?order_id=" . $order_id);
    exit();
}

// ─── Load order items ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT oi.quantity, oi.price_at_checkout, p.name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id ASC
");
if (!$stmt) die('Order items query failed: ' . $conn->error);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$payment_intent_id = $_SESSION['payment_intent_id'] ?? '';
$user_name         = $_SESSION['payment_user_name']  ?? 'Customer';
$user_email        = $_SESSION['payment_user_email'] ?? '';
$amount_to_pay     = floatval($order['upfront_payment']);
$balance_due       = floatval($order['balance_due']);

// ─── Build absolute return URL ─────────────────────────────────────────────────
$protocol   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'];
$dir_path   = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$return_url = $protocol . '://' . $host . $dir_path . '/payment_return.php?order_id=' . $order_id;

$payment_error = '';

// ─── Helper: Clear cart + session after payment ────────────────────────────────
function clearCartAndSession($conn, int $user_id, int $order_id): void {
    $selected_ids = $_SESSION['payment_selected_ids'] ?? [];
    if (!empty($selected_ids)) {
        $ids = implode(',', array_map('intval', $selected_ids));
        $conn->query("DELETE FROM cart WHERE user_id = $user_id AND cart_id IN ($ids)");
    }
    unset(
        $_SESSION['pending_order_id'],
        $_SESSION['payment_intent_id'],
        $_SESSION['payment_client_key'],
        $_SESSION['payment_selected_ids'],
        $_SESSION['payment_method_id'],
        $_SESSION['qrph_source_id']
    );
}

// ─── Handle QRPh POST (create source) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'qrph') {
    $amount_cents = intval(round($amount_to_pay * 100));

    $source_response = pm_request('/sources', 'POST', [
        'data' => [
            'attributes' => [
                'amount'   => $amount_cents,
                'currency' => 'PHP',
                'type'     => 'qrph',
                'redirect' => [
                    'success' => $return_url . '&method=qrph',
                    'failed'  => $return_url . '&method=qrph&status=failed',
                ],
                'billing'  => [
                    'name'  => $user_name,
                    'email' => $user_email ?: 'customer@pss.com',
                ],
            ]
        ]
    ]);

    if (isset($source_response['errors'])) {
        $payment_error = $source_response['errors'][0]['detail'] ?? 'Could not generate QR code. Please try again.';
    } else {
        $source_id   = $source_response['data']['id'] ?? '';
        $qr_image    = $source_response['data']['attributes']['qr_image']   ?? '';
        $qr_code_str = $source_response['data']['attributes']['qr_code_str'] ?? '';
        $src_status  = $source_response['data']['attributes']['status']      ?? '';

        if ($source_id) {
            $_SESSION['qrph_source_id'] = $source_id;
            // Return JSON for AJAX (JS will show QR modal)
            header('Content-Type: application/json');
            echo json_encode([
                'success'    => true,
                'source_id'  => $source_id,
                'qr_image'   => $qr_image,
                'qr_code'    => $qr_code_str,
                'status'     => $src_status,
                'amount'     => number_format($amount_to_pay, 2),
                'order_id'   => $order_id,
            ]);
            exit();
        } else {
            if ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to generate QR code.']);
                exit();
            }
            $payment_error = 'Failed to generate QR code. Please try again.';
        }
    }
}

// ─── Handle QRPh status poll (AJAX) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'poll_qrph') {
    $source_id = $_GET['source_id'] ?? ($_SESSION['qrph_source_id'] ?? '');
    if (!$source_id) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'unknown']);
        exit();
    }

    $src = pm_request('/sources/' . $source_id);
    $status = $src['data']['attributes']['status'] ?? 'pending';

    // If paid, update the order
    if ($status === 'chargeable' || $status === 'paid') {
        // For QRPh sources, we need to create a payment to complete
        if ($status === 'chargeable') {
            $pay_response = pm_request('/payments', 'POST', [
                'data' => [
                    'attributes' => [
                        'amount'      => intval(round($amount_to_pay * 100)),
                        'currency'    => 'PHP',
                        'source'      => [
                            'id'   => $source_id,
                            'type' => 'source',
                        ],
                        'description' => 'Order #' . $order_id,
                    ]
                ]
            ]);
            $pay_status = $pay_response['data']['attributes']['status'] ?? '';
            if ($pay_status === 'paid') {
                $conn->query("UPDATE orders SET payment_status='paid', order_status='confirmed', payment_method='qrph' WHERE order_id=$order_id");
                clearCartAndSession($conn, $user_id, $order_id);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'paid', 'redirect' => 'receipt.php?order_id=' . $order_id]);
                exit();
            }
        } else {
            $conn->query("UPDATE orders SET payment_status='paid', order_status='confirmed', payment_method='qrph' WHERE order_id=$order_id");
            clearCartAndSession($conn, $user_id, $order_id);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'paid', 'redirect' => 'receipt.php?order_id=' . $order_id]);
            exit();
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => $status]);
    exit();
}

$page_title = 'Secure Payment';
require_once '../../includes/customer_header.php';
?>

<style>
    .pm-tab-btn { transition: all 0.2s ease; }
    .pm-tab-btn.active { background: #1d4ed8; color: #fff; box-shadow: 0 4px 14px rgba(29,78,216,.35); }
    .pm-tab-btn:not(.active) { background: #f1f5f9; color: #64748b; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; animation: fadeIn .25s ease; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }

    .card-input { font-family: 'Courier New', monospace; letter-spacing: 0.05em; }

    @keyframes shimmer {
        0%   { background-position: -200% center; }
        100% { background-position:  200% center; }
    }
    .pay-btn-shimmer {
        background: linear-gradient(90deg, #16a34a 0%, #22c55e 40%, #16a34a 100%);
        background-size: 200% auto;
    }
    .pay-btn-shimmer:hover { animation: shimmer 1.5s linear infinite; }

    /* QRPh Modal */
    #qrph-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.75);
        backdrop-filter: blur(4px);
        z-index: 100;
        align-items: center;
        justify-content: center;
    }
    #qrph-modal.open { display: flex; }

    @keyframes qr-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.4); }
        50%       { box-shadow: 0 0 0 12px rgba(59,130,246,0); }
    }
    .qr-box { animation: qr-pulse 2s ease-in-out infinite; }

    @keyframes spin { to { transform: rotate(360deg); } }
    .spin { animation: spin 1s linear infinite; }

    .status-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .status-dot.pending  { background: #f59e0b; animation: qr-pulse 1.5s infinite; }
    .status-dot.paid     { background: #16a34a; }
    .status-dot.expired  { background: #ef4444; }
</style>

<main class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-10 px-4">
    <div class="max-w-5xl mx-auto">

        <div class="flex flex-col lg:flex-row gap-6">

            <!-- ─── LEFT: Payment Methods ──────────────────────────────── -->
            <div class="lg:w-3/5">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-700 to-indigo-700 px-6 py-5 flex items-center gap-3">
                        <div class="bg-white bg-opacity-20 rounded-full p-2">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-white font-black text-xl">Secure Payment</h1>
                            <p class="text-blue-200 text-xs">Your payment is protected by PayMongo</p>
                        </div>
                        <div class="ml-auto">
                            <span class="text-xs text-blue-200 bg-blue-800 bg-opacity-50 px-3 py-1 rounded-full font-semibold">
                                Order #<?php echo $order_id; ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-6">

                        <!-- Error Alert -->
                        <?php if ($payment_error): ?>
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 flex items-start gap-3">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="font-bold text-red-700 text-sm">Payment Failed</p>
                                <p class="text-red-600 text-sm mt-0.5"><?php echo htmlspecialchars($payment_error); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Amount to Pay -->
                        <div class="bg-blue-50 rounded-xl p-4 mb-6 text-center border border-blue-100">
                            <p class="text-xs text-blue-500 font-bold uppercase tracking-widest mb-1">Amount to Pay Now</p>
                            <p class="text-4xl font-black text-blue-700">₱<?php echo number_format($amount_to_pay, 2); ?></p>
                            <?php if ($balance_due > 0): ?>
                            <p class="text-xs text-gray-500 mt-2">
                                <span class="text-red-500 font-semibold">+ ₱<?php echo number_format($balance_due, 2); ?> balance</span> due at store pickup
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- ── QRPh Tab ─────────────────────────────────── -->
                        <div id="panel-qrph" class="tab-panel active">
                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-6 text-center">

                                <!-- QRPh Branding -->
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-white border-2 border-blue-200 mb-4 shadow">
                                    <svg class="w-12 h-12 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M3 3h6v6H3V3zm0 12h6v6H3v-6zm12-12h6v6h-6V3zm-1 12h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4-4h2v2h-2v-2zm0 4h2v2h-2v-2zm-4 0h2v2h-2v-2z"/>
                                    </svg>
                                </div>

                                <h3 class="font-black text-gray-800 text-lg mb-1">Pay with QRPh</h3>
                                <p class="text-sm text-gray-500 mb-2">
                                    Scan the QR code using any Philippine bank app or e-wallet that supports QRPh (BPI, BDO, Metrobank, Unionbank, Maya, etc.)
                                </p>

                                <!-- Accepted banks -->
                                <div class="flex flex-wrap justify-center gap-1.5 mb-5">
                                    <?php
                                    $banks = ['BPI', 'BDO', 'Metrobank', 'UnionBank', 'Maya', 'Landbank', 'DBP', 'PNB'];
                                    foreach ($banks as $b):
                                    ?>
                                    <span class="text-xs bg-white border border-blue-200 text-blue-700 px-2 py-0.5 rounded-full font-semibold"><?php echo $b; ?></span>
                                    <?php endforeach; ?>
                                    <span class="text-xs bg-white border border-gray-200 text-gray-400 px-2 py-0.5 rounded-full">& more</span>
                                </div>

                                <!-- Account info -->
                                <div class="bg-white rounded-lg p-4 border border-blue-100 mb-5 text-left">
                                    <p class="text-xs text-gray-500 mb-1">Paying as</p>
                                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($user_name); ?></p>
                                    <?php if ($user_email): ?>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user_email); ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- How it works steps -->
                                <div class="bg-white rounded-lg border border-blue-100 p-4 mb-5 text-left">
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">How it works</p>
                                    <div class="space-y-2">
                                        <div class="flex items-start gap-3 text-sm">
                                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-black">1</span>
                                            <span class="text-gray-600">Click <strong>"Generate QR Code"</strong> below</span>
                                        </div>
                                        <div class="flex items-start gap-3 text-sm">
                                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-black">2</span>
                                            <span class="text-gray-600">Open your bank/e-wallet app and scan the QR code</span>
                                        </div>
                                        <div class="flex items-start gap-3 text-sm">
                                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-black">3</span>
                                            <span class="text-gray-600">Confirm payment of <strong>₱<?php echo number_format($amount_to_pay, 2); ?></strong> in your app</span>
                                        </div>
                                        <div class="flex items-start gap-3 text-sm">
                                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-black">4</span>
                                            <span class="text-gray-600">This page updates automatically once payment is confirmed</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Generate QR Button -->
                                <button type="button" id="qrph-generate-btn" onclick="generateQRPh()"
                                    class="pay-btn-shimmer w-full bg-green-600 hover:bg-green-700 text-white font-black py-4 rounded-xl text-lg transition shadow-md flex items-center justify-center gap-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 3h6v6H3V3zm0 12h6v6H3v-6zm12-12h6v6h-6V3zm-1 12h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4-4h2v2h-2v-2zm0 4h2v2h-2v-2zm-4 0h2v2h-2v-2z"/>
                                    </svg>
                                    Generate QR Code - ₱<?php echo number_format($amount_to_pay, 2); ?>
                                </button>

                                <p class="text-xs text-gray-400 mt-3">QR code expires in <strong>10 minutes</strong>. Keep this page open.</p>
                            </div>
                        </div>

                        <!-- Security note -->
                        <div class="mt-6 flex items-center justify-center gap-2 text-xs text-gray-400">
                            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            256-bit SSL Encrypted · Powered by PayMongo
                        </div>

                    </div><!-- /p-6 -->
                </div><!-- /card -->
            </div><!-- /left -->

            <!-- ─── RIGHT: Order Summary ───────────────────────────────── -->
            <div class="lg:w-2/5">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sticky top-6">
                    <h2 class="font-black text-gray-800 text-lg mb-4 border-b pb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Order Summary
                        <span class="ml-auto text-xs font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                            <?php echo count($order_items); ?> item<?php echo count($order_items) !== 1 ? 's' : ''; ?>
                        </span>
                    </h2>

                    <div class="space-y-3 max-h-52 overflow-y-auto mb-4 pr-1">
                        <?php if (empty($order_items)): ?>
                            <p class="text-sm text-gray-400 text-center py-4">No items found for this order.</p>
                        <?php else: ?>
                            <?php foreach ($order_items as $item): ?>
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-start gap-2 flex-1">
                                    <span class="bg-blue-100 text-blue-700 font-black text-xs px-2 py-0.5 rounded-full flex-shrink-0"><?php echo $item['quantity']; ?>x</span>
                                    <span class="text-gray-700 leading-tight"><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                                <span class="font-semibold text-gray-800 ml-2 flex-shrink-0">₱<?php echo number_format($item['price_at_checkout'] * $item['quantity'], 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pickup Details -->
                    <div class="bg-slate-50 rounded-xl p-4 mb-4 border border-slate-100">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Pickup Details</p>
                        <div class="flex items-center gap-2 text-sm text-gray-700 mb-1">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="font-semibold"><?php echo date('D, M j, Y', strtotime($order['pickup_datetime'])); ?></span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-700">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-semibold"><?php echo date('g:i A', strtotime($order['pickup_datetime'])); ?></span>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="space-y-2 border-t pt-4">
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Order Total</span>
                            <span class="font-semibold">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-green-700">
                            <span>Pay Now</span>
                            <span>₱<?php echo number_format($amount_to_pay, 2); ?></span>
                        </div>
                        <?php if ($balance_due > 0): ?>
                        <div class="flex justify-between text-sm font-bold text-red-600">
                            <span>Balance at Store</span>
                            <span>₱<?php echo number_format($balance_due, 2); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4">
                        <?php
                        $pt_labels = ['full' => 'Full Payment', 'partial_50' => '50% Downpayment', 'partial_30' => '30% Downpayment'];
                        $pt_label  = $pt_labels[$order['payment_type']] ?? 'Payment';
                        ?>
                        <span class="inline-block bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full">
                            <?php echo $pt_label; ?>
                        </span>
                    </div>

                    <a href="cart.php" class="mt-5 flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600 transition justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to Cart
                    </a>
                </div>
            </div>

        </div><!-- /flex -->
    </div><!-- /max-w -->
</main>

<!-- ─── QRPh Modal ──────────────────────────────────────────────────────────── -->
<div id="qrph-modal" role="dialog" aria-modal="true" aria-label="QRPh Payment">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">

        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-700 px-6 py-4 flex items-center gap-3">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 3h6v6H3V3zm0 12h6v6H3v-6zm12-12h6v6h-6V3zm-1 12h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm4-4h2v2h-2v-2zm0 4h2v2h-2v-2zm-4 0h2v2h-2v-2z"/>
            </svg>
            <div>
                <p class="text-white font-black">Scan to Pay via QRPh</p>
                <p class="text-blue-200 text-xs">Order #<?php echo $order_id; ?></p>
            </div>
            <button onclick="closeQRModal()" class="ml-auto text-blue-200 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 text-center">

            <!-- Status indicator -->
            <div class="flex items-center justify-center gap-2 mb-4" id="qr-status-row">
                <span class="status-dot pending" id="qr-status-dot"></span>
                <span class="text-sm font-semibold text-gray-600" id="qr-status-text">Waiting for payment…</span>
            </div>

            <!-- QR Image Area -->
            <div id="qr-loading" class="py-8 flex flex-col items-center gap-3">
                <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full spin"></div>
                <p class="text-sm text-gray-500">Generating QR code…</p>
            </div>

            <div id="qr-display" class="hidden">
                <!-- Amount badge -->
                <div class="inline-block bg-blue-600 text-white font-black text-xl px-6 py-2 rounded-full mb-4 shadow">
                    ₱<span id="qr-amount"></span>
                </div>

                <!-- QR Code image -->
                <div class="qr-box inline-block rounded-2xl overflow-hidden border-4 border-blue-200 mb-4">
                    <img id="qr-img" src="" alt="QRPh QR Code" class="w-80 h-80 object-contain">
                </div>

                <!-- QR string fallback -->
                <div id="qr-string-wrap" class="hidden bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
                    <p class="text-xs text-gray-500 mb-1">Or copy QR string:</p>
                    <p class="font-mono text-xs text-gray-700 break-all select-all" id="qr-string-text"></p>
                </div>

                <p class="text-xs text-gray-500 mb-1">
                    Open your bank / e-wallet app → Scan QR → Pay
                </p>

                <!-- Timer -->
                <div class="flex items-center justify-center gap-1.5 text-xs text-amber-600 font-semibold mt-2" id="qr-timer-row">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Expires in <span id="qr-countdown">60:00</span>
                </div>
            </div>

            <!-- Success state -->
            <div id="qr-success" class="hidden py-4">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="font-black text-green-700 text-lg mb-1">Payment Received!</p>
                <p class="text-sm text-gray-500">Redirecting to your receipt…</p>
            </div>

            <!-- Expired state -->
            <div id="qr-expired" class="hidden py-4">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="font-black text-red-600 text-lg mb-1">QR Code Expired</p>
                <p class="text-sm text-gray-500 mb-4">Please generate a new QR code to try again.</p>
                <button onclick="closeQRModal()" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">
                    Generate New QR
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-filter backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl p-8 shadow-2xl flex flex-col items-center gap-4 max-w-xs mx-4">
        <div class="w-14 h-14 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="font-bold text-gray-800 text-center" id="loadingText">Processing payment...</p>
        <p class="text-sm text-gray-500 text-center">Please do not close this window.</p>
    </div>
</div>

<script>
    // ── Tab switching ──────────────────────────────────────────────────────────
    function switchTab(tab) {
        document.querySelectorAll('.pm-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        document.getElementById('panel-' + tab).classList.add('active');
    }

    // ── Card helpers ───────────────────────────────────────────────────────────
    function formatCardNumber(el) {
        let v = el.value.replace(/\D/g, '').slice(0, 16);
        el.value = v.match(/.{1,4}/g)?.join(' ') || v;
    }
    function formatExpiry(el) {
        let v = el.value.replace(/\D/g, '').slice(0, 4);
        if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
        el.value = v;
    }
    function updateCardPreview() {
        const num = document.getElementById('card_number').value || '';
        const padded = num.padEnd(19, '•').replace(/ /g, '').replace(/(.{4})/g, '$1 ').trim();
        document.getElementById('preview_number').textContent = padded || '•••• •••• •••• ••••';
    }
    function validateCard(form) {
        const num    = form.card_number.value.replace(/\s/g, '');
        const expiry = form.expiry.value;
        const cvc    = form.cvc.value;
        if (num.length < 13) { alert('Please enter a valid card number (at least 13 digits).'); return false; }
        if (!luhnCheck(num)) { alert('Invalid card number. Please check and try again.'); return false; }
        if (!/^\d{2}\/\d{2}$/.test(expiry)) { alert('Please enter expiry as MM/YY.'); return false; }
        if (cvc.length < 3) { alert('Please enter a valid CVC (3-4 digits).'); return false; }
        showLoading(form, 'Processing card payment...');
        return true;
    }
    function luhnCheck(num) {
        let sum = 0, isEven = false;
        for (let i = num.length - 1; i >= 0; i--) {
            let d = parseInt(num.charAt(i), 10);
            if (isEven) { d *= 2; if (d > 9) d -= 9; }
            sum += d; isEven = !isEven;
        }
        return (sum % 10) === 0;
    }
    function showLoading(form, msg) {
        document.getElementById('loadingText').textContent = msg || 'Processing...';
        document.getElementById('loadingOverlay').classList.remove('hidden');
        document.getElementById('loadingOverlay').classList.add('flex');
    }

    // ── QRPh logic ─────────────────────────────────────────────────────────────
    let pollInterval   = null;
    let countdownTimer = null;
    let currentSourceId = null;

    function generateQRPh() {
        // Open modal in loading state
        openQRModal();

        fetch('payment_qrph_ajax.php?action=create_qrph&order_id=<?php echo $order_id; ?>', {
            method: 'POST',
        })
        .then(r => {
            // Guard: make sure we actually got JSON back, not an HTML error page
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                return r.text().then(txt => { throw new Error('Non-JSON response: ' + txt.slice(0, 200)); });
            }
            return r.json();
        })
        .then(data => {
            if (data.success) {
                currentSourceId = data.source_id;
                showQRCode(data);
                startPolling(data.source_id);
                startCountdown(600); // 10 minutes expiry
            } else {
                closeQRModal();
                alert(data.error || 'Failed to generate QR code. Please try again.');
            }
        })
        .catch(err => {
            closeQRModal();
            alert('Error: ' + err.message);
        });
    }

    function openQRModal() {
        // Reset state
        document.getElementById('qr-loading').classList.remove('hidden');
        document.getElementById('qr-display').classList.add('hidden');
        document.getElementById('qr-success').classList.add('hidden');
        document.getElementById('qr-expired').classList.add('hidden');
        document.getElementById('qr-status-row').classList.remove('hidden');
        setStatusDot('pending', 'Waiting for payment…');

        document.getElementById('qrph-modal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeQRModal() {
        stopPolling();
        stopCountdown();
        document.getElementById('qrph-modal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function showQRCode(data) {
        document.getElementById('qr-loading').classList.add('hidden');
        document.getElementById('qr-display').classList.remove('hidden');
        document.getElementById('qr-amount').textContent = data.amount;

        const img = document.getElementById('qr-img');
        // qr_image is a base64 data URI (data:image/png;base64,...)
        if (data.qr_image) {
            img.src = data.qr_image;
            img.classList.remove('hidden');
        } else {
            img.classList.add('hidden');
        }

        // Show QR string fallback if no image
        if (data.qr_string && !data.qr_image) {
            document.getElementById('qr-string-text').textContent = data.qr_string;
            document.getElementById('qr-string-wrap').classList.remove('hidden');
        }
    }

    function setStatusDot(type, text) {
        const dot  = document.getElementById('qr-status-dot');
        const span = document.getElementById('qr-status-text');
        dot.className = 'status-dot ' + type;
        span.textContent = text;
    }

    function startPolling(sourceId) {
        stopPolling();
        // Poll every 3 seconds
        pollInterval = setInterval(() => pollStatus(sourceId), 3000);
    }

    function stopPolling() {
        if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    }

    function pollStatus(sourceId) {
        fetch(`payment_qrph_ajax.php?action=poll_qrph&source_id=${encodeURIComponent(sourceId)}&order_id=<?php echo $order_id; ?>`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'paid') {
                stopPolling();
                stopCountdown();
                showSuccess(data.redirect);
            } else if (data.status === 'expired' || data.status === 'failed') {
                stopPolling();
                stopCountdown();
                showExpired();
            }
            // 'pending' / 'awaiting_payment' — keep polling
        })
        .catch(() => { /* silent — keep polling */ });
    }

    function showSuccess(redirectUrl) {
        document.getElementById('qr-display').classList.add('hidden');
        document.getElementById('qr-status-row').classList.add('hidden');
        document.getElementById('qr-success').classList.remove('hidden');
        setStatusDot('paid', 'Payment confirmed!');

        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 2000);
    }

    function showExpired() {
        document.getElementById('qr-display').classList.add('hidden');
        document.getElementById('qr-status-row').classList.add('hidden');
        document.getElementById('qr-expired').classList.remove('hidden');
    }

    function startCountdown(seconds) {
        stopCountdown();
        let remaining = seconds;
        updateCountdownDisplay(remaining);

        countdownTimer = setInterval(() => {
            remaining--;
            updateCountdownDisplay(remaining);
            if (remaining <= 0) {
                stopCountdown();
                stopPolling();
                showExpired();
            }
        }, 1000);
    }

    function stopCountdown() {
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    }

    function updateCountdownDisplay(secs) {
        const m = Math.floor(secs / 60).toString().padStart(2, '0');
        const s = (secs % 60).toString().padStart(2, '0');
        const el = document.getElementById('qr-countdown');
        if (el) el.textContent = `${m}:${s}`;
        // Turn red in last 5 minutes
        const timerRow = document.getElementById('qr-timer-row');
        if (timerRow && secs < 300) timerRow.style.color = '#dc2626';
    }

    // Close modal on backdrop click
    document.getElementById('qrph-modal').addEventListener('click', function(e) {
        if (e.target === this) closeQRModal();
    });
</script>

<?php
require_once '../../includes/customer_footer.php';
$conn->close();
?>