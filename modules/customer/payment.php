<?php
// modules/customer/payment.php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../../config/config.php';

// ─── PayMongo Config ───────────────────────────────────────────────────────────
define('PM_SECRET_KEY', 'sk_test_bg7ic4jq6oGSkDPeU5xeQFn5');
define('PM_PUBLIC_KEY', 'pk_test_KRugwuNGnXVHLMg1bz7rjxbB');
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
// Prioritize GET param, fallback to session — but ALWAYS validate against user_id
$order_id = intval($_GET['order_id'] ?? 0);

// If no order_id in GET, try session — but clear stale session if it doesn't match
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
    // Order doesn't exist or doesn't belong to this user — clear stale session
    unset($_SESSION['pending_order_id']);
    header("Location: cart.php");
    exit();
}

// Keep session in sync with the validated order_id
$_SESSION['pending_order_id'] = $order_id;

// Already paid? Go to receipt
if ($order['payment_status'] === 'paid') {
    header("Location: receipt.php?order_id=" . $order_id);
    exit();
}

// ─── Load order items (strictly by this order_id) ─────────────────────────────
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

// ─── Errors / messages ────────────────────────────────────────────────────────
$payment_error = '';

// ─── Handle GCash POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'gcash') {
    $pm_response = pm_request('/payment_methods', 'POST', [
        'data' => [
            'attributes' => [
                'type'    => 'gcash',
                'billing' => [
                    'name'  => $user_name,
                    'email' => $user_email ?: 'customer@pss.com',
                ],
            ]
        ]
    ]);

    if (isset($pm_response['errors'])) {
        $payment_error = $pm_response['errors'][0]['detail'] ?? 'Could not initiate GCash. Please try again.';
    } else {
        $pm_id = $pm_response['data']['id'];

        $attach = pm_request('/payment_intents/' . $payment_intent_id . '/attach', 'POST', [
            'data' => [
                'attributes' => [
                    'payment_method' => $pm_id,
                    'return_url'     => $return_url . '&method=gcash',
                ]
            ]
        ]);

        $status      = $attach['data']['attributes']['status'] ?? '';
        $next_action = $attach['data']['attributes']['next_action'] ?? null;

        if ($status === 'succeeded') {
            $conn->query("UPDATE orders SET payment_status='paid', order_status='confirmed', payment_method='gcash' WHERE order_id=$order_id");
            clearCartAndSession($conn, $user_id, $order_id);
            header("Location: receipt.php?order_id=" . $order_id);
            exit();
        } elseif ($next_action && isset($next_action['redirect']['url'])) {
            $_SESSION['payment_method_id'] = $pm_id;
            header("Location: " . $next_action['redirect']['url']);
            exit();
        } else {
            $payment_error = $attach['errors'][0]['detail'] ?? 'GCash payment could not be initiated.';
        }
    }
}

// ─── Handle Card POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'card') {
    $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $exp_parts   = explode('/', $_POST['expiry'] ?? '/');
    $exp_month   = (int)trim($exp_parts[0] ?? 0);
    $exp_year    = (int)trim('20' . ($exp_parts[1] ?? 0));
    $cvc         = trim($_POST['cvc'] ?? '');
    $card_name   = trim($_POST['card_name'] ?? $user_name);

    $pm_response = pm_request('/payment_methods', 'POST', [
        'data' => [
            'attributes' => [
                'type'    => 'card',
                'details' => [
                    'card_number' => $card_number,
                    'exp_month'   => $exp_month,
                    'exp_year'    => $exp_year,
                    'cvc'         => $cvc,
                ],
                'billing' => [
                    'name'  => $card_name,
                    'email' => $user_email ?: 'customer@pss.com',
                ],
            ]
        ]
    ]);

    if (isset($pm_response['errors'])) {
        $payment_error = $pm_response['errors'][0]['detail'] ?? 'Invalid card details. Please check and try again.';
    } else {
        $pm_id = $pm_response['data']['id'];

        $attach = pm_request('/payment_intents/' . $payment_intent_id . '/attach', 'POST', [
            'data' => [
                'attributes' => [
                    'payment_method' => $pm_id,
                    'return_url'     => $return_url . '&method=card',
                ]
            ]
        ]);

        $status      = $attach['data']['attributes']['status'] ?? '';
        $next_action = $attach['data']['attributes']['next_action'] ?? null;

        if ($status === 'succeeded') {
            $conn->query("UPDATE orders SET payment_status='paid', order_status='confirmed', payment_method='card' WHERE order_id=$order_id");
            clearCartAndSession($conn, $user_id, $order_id);
            header("Location: receipt.php?order_id=" . $order_id);
            exit();
        } elseif ($next_action && isset($next_action['redirect']['url'])) {
            $_SESSION['payment_method_id'] = $pm_id;
            header("Location: " . $next_action['redirect']['url']);
            exit();
        } else {
            $payment_error = $attach['errors'][0]['detail'] ?? 'Payment failed. Try a different card.';
        }
    }
}

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
        $_SESSION['payment_method_id']
    );
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

    .test-badge { background: repeating-linear-gradient(45deg,#fef08a 0,#fef08a 10px,#fde047 10px,#fde047 20px); }
</style>

<main class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-10 px-4">
    <div class="max-w-5xl mx-auto">

        <!-- Test Mode Banner -->
        <div class="test-badge rounded-xl px-5 py-3 mb-6 flex items-center gap-3 border border-yellow-400">
            <svg class="w-5 h-5 text-yellow-700 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="text-sm flex-1">
                <span class="font-black text-yellow-800">🧪 TEST MODE ACTIVE</span>
                <span class="text-yellow-700 block mt-1 text-xs">Use test card numbers from <a href="https://developers.paymongo.com/docs/testing" target="_blank" class="underline hover:text-yellow-900 font-semibold">PayMongo testing docs</a> to simulate payments</span>
            </div>
        </div>

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

                        <!-- Tab Buttons -->
                        <div class="flex gap-3 mb-6">
                            <button type="button" onclick="switchTab('gcash')" id="tab-gcash"
                                class="pm-tab-btn active flex-1 py-3 rounded-xl font-bold text-sm flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="40" height="40" rx="8" fill="currentColor" fill-opacity="0.15"/>
                                    <text x="50%" y="60%" dominant-baseline="middle" text-anchor="middle" font-size="10" font-weight="900" fill="currentColor">G</text>
                                </svg>
                                GCash
                            </button>
                            <button type="button" onclick="switchTab('card')" id="tab-card"
                                class="pm-tab-btn flex-1 py-3 rounded-xl font-bold text-sm flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                Credit / Debit Card
                            </button>
                        </div>

                        <!-- ── GCash Tab ─────────────────────────────────── -->
                        <div id="panel-gcash" class="tab-panel active">
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-blue-600 text-white font-black text-3xl mb-4 shadow-lg">
                                    G
                                </div>
                                <h3 class="font-black text-gray-800 text-lg mb-1">Pay with GCash</h3>
                                <p class="text-sm text-gray-500 mb-6">You'll be redirected to the GCash payment page to complete your transaction.</p>

                                <div class="bg-white rounded-lg p-4 border border-blue-200 mb-6 text-left">
                                    <p class="text-xs text-gray-500 mb-1">Account</p>
                                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($user_name); ?></p>
                                    <?php if ($user_email): ?>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user_email); ?></p>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" onsubmit="showLoading(this, 'Redirecting to GCash...')">
                                    <input type="hidden" name="action" value="gcash">
                                    <button type="submit" class="pay-btn-shimmer w-full bg-green-600 hover:bg-green-700 text-white font-black py-4 rounded-xl text-lg transition shadow-md flex items-center justify-center gap-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                        </svg>
                                        Pay ₱<?php echo number_format($amount_to_pay, 2); ?> via GCash
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- ── Card Tab ──────────────────────────────────── -->
                        <div id="panel-card" class="tab-panel">
                            <form method="POST" id="cardForm" onsubmit="return validateCard(this)">
                                <input type="hidden" name="action" value="card">

                                <!-- Card Preview -->
                                <div class="bg-gradient-to-br from-slate-700 to-slate-900 rounded-2xl p-5 mb-5 shadow-xl text-white relative overflow-hidden">
                                    <div class="absolute top-0 right-0 w-40 h-40 bg-white opacity-5 rounded-full -mt-10 -mr-10"></div>
                                    <div class="absolute bottom-0 left-0 w-32 h-32 bg-white opacity-5 rounded-full -mb-10 -ml-10"></div>
                                    <div class="relative z-10">
                                        <div class="flex justify-between items-start mb-6">
                                            <svg class="w-10 h-10 opacity-80" viewBox="0 0 48 48" fill="none">
                                                <rect x="2" y="8" width="44" height="32" rx="4" fill="white" fill-opacity=".2"/>
                                                <circle cx="18" cy="24" r="8" fill="#ef4444" fill-opacity=".8"/>
                                                <circle cx="30" cy="24" r="8" fill="#f59e0b" fill-opacity=".8"/>
                                            </svg>
                                            <span class="text-xs font-semibold opacity-60 uppercase tracking-widest">Credit Card</span>
                                        </div>
                                        <p class="font-mono text-xl tracking-[0.2em] mb-4 opacity-90" id="preview_number">•••• •••• •••• ••••</p>
                                        <div class="flex justify-between text-xs opacity-70">
                                            <div>
                                                <p class="uppercase tracking-wider mb-0.5">Card Holder</p>
                                                <p class="font-bold text-sm text-white opacity-90" id="preview_name">YOUR NAME</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="uppercase tracking-wider mb-0.5">Expires</p>
                                                <p class="font-bold text-sm text-white opacity-90" id="preview_expiry">MM/YY</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Number -->
                                <div class="mb-4">
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Card Number</label>
                                    <input type="text" name="card_number" id="card_number" required
                                        placeholder="1234 5678 9012 3456"
                                        value="4343 4343 4343 4345"
                                        maxlength="19"
                                        inputmode="numeric"
                                        autocomplete="cc-number"
                                        class="card-input w-full bg-gray-50 border border-gray-300 rounded-xl p-4 text-lg outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        oninput="formatCardNumber(this)"
                                        onkeyup="updateCardPreview()">
                                </div>

                                <!-- Name -->
                                <div class="mb-4">
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Name on Card</label>
                                    <input type="text" name="card_name" id="card_name" required
                                        placeholder="JUAN DELA CRUZ"
                                        value="TEST USER"
                                        autocomplete="cc-name"
                                        class="w-full bg-gray-50 border border-gray-300 rounded-xl p-4 outline-none focus:ring-2 focus:ring-blue-500 transition uppercase"
                                        oninput="document.getElementById('preview_name').textContent = this.value.toUpperCase() || 'YOUR NAME'">
                                </div>

                                <!-- Expiry + CVC -->
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">Expiry Date</label>
                                        <input type="text" name="expiry" id="expiry" required
                                            placeholder="MM/YY"
                                            value="12/28"
                                            maxlength="5"
                                            inputmode="numeric"
                                            autocomplete="cc-exp"
                                            class="card-input w-full bg-gray-50 border border-gray-300 rounded-xl p-4 text-lg outline-none focus:ring-2 focus:ring-blue-500 transition"
                                            oninput="formatExpiry(this)"
                                            onkeyup="document.getElementById('preview_expiry').textContent = this.value || 'MM/YY'">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">CVC / CVV</label>
                                        <input type="text" name="cvc" id="cvc" required
                                            placeholder="•••"
                                            value="111"
                                            maxlength="4"
                                            inputmode="numeric"
                                            autocomplete="cc-csc"
                                            class="card-input w-full bg-gray-50 border border-gray-300 rounded-xl p-4 text-lg outline-none focus:ring-2 focus:ring-blue-500 transition"
                                            oninput="this.value = this.value.replace(/\D/g, '').slice(0, 4)">
                                    </div>
                                </div>

                                <button type="submit" id="cardPayBtn"
                                    class="pay-btn-shimmer w-full bg-green-600 text-white font-black py-4 rounded-xl text-lg transition shadow-md flex items-center justify-center gap-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Pay ₱<?php echo number_format($amount_to_pay, 2); ?> Securely
                                </button>

                                <!-- Test card hint -->
                                <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-800">
                                    <strong>🧪 Test Card Only:</strong>
                                    <span class="font-mono">4343 4343 4343 4345</span> ·
                                    Exp: <span class="font-mono">12/28</span> ·
                                    CVC: <span class="font-mono">111</span>
                                    <p class="mt-2 text-xs text-yellow-700">⚠️ Only PayMongo test card numbers are accepted. Using other card numbers will result in an error.</p>
                                </div>
                            </form>
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

                    <!-- Items — now strictly from this order only -->
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

                    <!-- Payment type badge -->
                    <div class="mt-4">
                        <?php
                        $pt_labels = ['full' => 'Full Payment', 'partial_50' => '50% Downpayment', 'partial_30' => '30% Downpayment'];
                        $pt_label  = $pt_labels[$order['payment_type']] ?? 'Payment';
                        ?>
                        <span class="inline-block bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full">
                            <?php echo $pt_label; ?>
                        </span>
                    </div>

                    <!-- Back to cart link -->
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

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl p-8 shadow-2xl flex flex-col items-center gap-4 max-w-xs mx-4">
        <div class="w-14 h-14 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
        <p class="font-bold text-gray-800 text-center" id="loadingText">Processing payment...</p>
        <p class="text-sm text-gray-500 text-center">Please do not close this window.</p>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('.pm-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        document.getElementById('panel-' + tab).classList.add('active');
    }

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

        if (num.length < 13) {
            alert('Please enter a valid card number (at least 13 digits).');
            return false;
        }
        if (!luhnCheck(num)) {
            alert('Invalid card number. Please check and try again.');
            return false;
        }
        if (!/^\d{2}\/\d{2}$/.test(expiry)) {
            alert('Please enter expiry as MM/YY.');
            return false;
        }
        if (cvc.length < 3) {
            alert('Please enter a valid CVC (3-4 digits).');
            return false;
        }

        showLoading(form, 'Processing card payment...');
        return true;
    }

    function luhnCheck(num) {
        let sum = 0;
        let isEven = false;
        for (let i = num.length - 1; i >= 0; i--) {
            let digit = parseInt(num.charAt(i), 10);
            if (isEven) {
                digit *= 2;
                if (digit > 9) digit -= 9;
            }
            sum += digit;
            isEven = !isEven;
        }
        return (sum % 10) === 0;
    }

    function showLoading(form, msg) {
        document.getElementById('loadingText').textContent = msg || 'Processing...';
        document.getElementById('loadingOverlay').classList.remove('hidden');
        document.getElementById('loadingOverlay').classList.add('flex');
    }
</script>

<?php
require_once '../../includes/customer_footer.php';
$conn->close();
?>