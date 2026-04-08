<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../../config/config.php';

define('PM_SECRET_KEY', 'sk_test_bg7ic4jq6oGSkDPeU5xeQFn5');
define('PM_BASE_URL',   'https://api.paymongo.com/v1');

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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

foreach (['pickup_date', 'pickup_time', 'payment_type', 'selected_cart_ids'] as $f) {
    if (empty($_POST[$f])) {
        header("Location: ../../modules/customer/cart.php");
        exit();
    }
}

$pickup_date  = htmlspecialchars(trim($_POST['pickup_date']));
$pickup_time  = htmlspecialchars(trim($_POST['pickup_time']));
$payment_type = $_POST['payment_type'];
$selected_ids = array_map('intval', $_POST['selected_cart_ids']);
$ids_string   = implode(',', $selected_ids);

if (!in_array($payment_type, ['full', 'partial_50', 'partial_30'])) {
    header("Location: ../../modules/customer/cart.php");
    exit();
}

// ─── Verify cart items ─────────────────────────────────────────────────────────
$cart_items = [];
$result = $conn->query("
    SELECT c.cart_id, c.quantity, p.product_id, p.name, p.price, p.discount_price
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.user_id = $user_id AND c.cart_id IN ($ids_string)
");
while ($row = $result->fetch_assoc()) {
    $row['final_price'] = ($row['discount_price'] > 0 && $row['discount_price'] < $row['price'])
        ? $row['discount_price'] : $row['price'];
    $cart_items[] = $row;
}

if (empty($cart_items)) {
    header("Location: ../../modules/customer/cart.php");
    exit();
}

// ─── ALWAYS recalculate total server-side from actual DB items ─────────────────
// Never trust $_POST['total_amount'] — recalculate from what was actually fetched
// so that order total always matches the items inserted into order_items.
$subtotal_amount = 0;
foreach ($cart_items as $item) {
    $subtotal_amount += $item['final_price'] * $item['quantity'];
}
$subtotal_amount = round($subtotal_amount, 2);

if ($subtotal_amount < 300) {
    $_SESSION['error'] = 'Minimum subtotal of ₱300.00 is required before checkout.';
    header("Location: ../../modules/customer/cart.php");
    exit();
}

$vat_amount = round($subtotal_amount * 0.12, 2);
$service_fee_amount = round($subtotal_amount * 0.10, 2);
$total_amount = round($subtotal_amount + $vat_amount + $service_fee_amount, 2);

// ─── Payment split ─────────────────────────────────────────────────────────────
$amount_to_pay = match($payment_type) {
    'partial_50' => round($total_amount * 0.5, 2),
    'partial_30' => round($total_amount * 0.3, 2),
    default      => round($total_amount, 2),
};
$balance_due = round($total_amount - $amount_to_pay, 2);

// ─── Fetch user info ───────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
if (!$stmt) die('Users prepare failed: ' . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = $user_info['first_name'] ?? $user_info['firstname'] ?? $user_info['name'] ?? '';
$last  = $user_info['last_name']  ?? $user_info['lastname']  ?? '';
$user_name  = trim("$first $last") ?: 'Customer';
$user_email = $user_info['email'] ?? $user_info['email_address'] ?? 'customer@example.com';

// ─── PayMongo PaymentIntent ────────────────────────────────────────────────────
$amount_centavos = (int) round($amount_to_pay * 100);

$pi_response = pm_request('/payment_intents', 'POST', [
    'data' => [
        'attributes' => [
            'amount'                 => $amount_centavos,
            'payment_method_allowed' => ['gcash', 'card', 'paymaya'],
            'payment_method_options' => ['card' => ['request_three_d_secure' => 'any']],
            'currency'               => 'PHP',
            'capture_type'           => 'automatic',
            'description'            => 'PSS Grocery Order — ' . $user_name,
        ]
    ]
]);

if (!isset($pi_response['data']['id'])) {
    error_log('PayMongo PI failed: ' . json_encode($pi_response));
    header("Location: ../../modules/customer/cart.php?error=payment_init_failed");
    exit();
}

$payment_intent_id = $pi_response['data']['id'];
$client_key        = $pi_response['data']['attributes']['client_key'] ?? '';

// ─── Insert Order ──────────────────────────────────────────────────────────────
$pickup_datetime = $pickup_date . ' ' . $pickup_time . ':00';
$tracking_no     = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
$payment_method  = NULL;

$stmt = $conn->prepare("
    INSERT INTO orders
        (user_id, tracking_no, total_amount, payment_method, payment_type, upfront_payment,
         balance_due, pickup_datetime, payment_status, order_status,
         payment_intent_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())
");

if (!$stmt) die('Order prepare failed: ' . $conn->error);

$stmt->bind_param("isdssddss",
    $user_id,
    $tracking_no,
    $total_amount,
    $payment_method,
    $payment_type,
    $amount_to_pay,
    $balance_due,
    $pickup_datetime,
    $payment_intent_id
);
if (!$stmt->execute()) {
    die('INSERT execute failed: ' . $stmt->error);
}
$order_id = $conn->insert_id;
$stmt->close();

if (!$order_id) {
    die('Insert ran but no order_id returned. Last error: ' . $conn->error);
}

// ─── Insert Order Items ────────────────────────────────────────────────────────
// Only $cart_items (fetched by $ids_string) are inserted — no stray items possible.
foreach ($cart_items as $item) {
    $is = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_checkout) VALUES (?, ?, ?, ?)");
    if (!$is) die('order_items prepare failed: ' . $conn->error);
    $is->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['final_price']);
    $is->execute();
    $is->close();
}

// ─── Session & Redirect ────────────────────────────────────────────────────────
$_SESSION['pending_order_id']     = $order_id;
$_SESSION['payment_intent_id']    = $payment_intent_id;
$_SESSION['payment_client_key']   = $client_key;
$_SESSION['payment_selected_ids'] = $selected_ids;
$_SESSION['payment_user_name']    = $user_name;
$_SESSION['payment_user_email']   = $user_email;

header("Location: ../../modules/customer/payment.php?order_id=" . $order_id);
exit();