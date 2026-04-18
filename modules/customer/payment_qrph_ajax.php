<?php
// modules/customer/payment_qrph_ajax.php
// Pure JSON endpoint — no HTML output ever
// QRPh uses the Payment Intent + Payment Method workflow (NOT /sources)
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../../config/config.php';

header('Content-Type: application/json');

// ─── Security ─────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// ─── PayMongo Config ───────────────────────────────────────────────────────────
define('PM_SECRET_KEY', 'sk_live_rBTsJk46egiGgkUotLSbqr7c');
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

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$order_id = intval($_GET['order_id'] ?? $_POST['order_id'] ?? $_SESSION['pending_order_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit();
}

// Validate order belongs to user
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit();
}

$amount_to_pay      = floatval($order['upfront_payment']);
$user_name          = $_SESSION['payment_user_name']  ?? 'Customer';
$user_email         = $_SESSION['payment_user_email'] ?? 'customer@pss.com';
$payment_intent_id  = $_SESSION['payment_intent_id']  ?? '';

// ─── Build return URL ──────────────────────────────────────────────────────────
$protocol   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'];
$dir_path   = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$return_url = $protocol . '://' . $host . $dir_path . '/payment_return.php?order_id=' . $order_id;

// ─── Action: create QRPh via Payment Intent workflow ──────────────────────────
// QRPh does NOT use /sources. Instead:
//   1. Create a payment_method with type=qrph
//   2. Attach it to the existing payment_intent
//   3. The attach response contains next_action.code.image_url = QR image (base64 PNG)
//   4. Poll the payment_intent status to detect payment
if ($action === 'create_qrph') {

    // Always create a dedicated QRPh payment intent.
    // The session payment_intent_id was created for card (payment_method_allowed=['card'])
    // and cannot accept QRPh — reusing it causes "No such payment intent" or attach errors.
    $pi = pm_request('/payment_intents', 'POST', [
        'data' => [
            'attributes' => [
                'amount'                 => intval(round($amount_to_pay * 100)),
                'currency'               => 'PHP',
                'payment_method_allowed' => ['qrph'],
                'description'            => 'Order #' . $order_id,
            ]
        ]
    ]);

    if (isset($pi['errors'])) {
        echo json_encode(['success' => false, 'error' => $pi['errors'][0]['detail'] ?? 'Failed to create payment intent.', 'raw' => $pi]);
        exit();
    }

    $payment_intent_id = $pi['data']['id'] ?? '';
    if (!$payment_intent_id) {
        echo json_encode(['success' => false, 'error' => 'No payment intent ID returned.', 'raw' => $pi]);
        exit();
    }
    // Store separately so we don't overwrite the card payment intent
    $_SESSION['qrph_payment_intent_id'] = $payment_intent_id;

    // Step 1: Create payment method with type=qrph
    $pm_resp = pm_request('/payment_methods', 'POST', [
        'data' => [
            'attributes' => [
                'type'    => 'qrph',
                'billing' => [
                    'name'  => $user_name,
                    'email' => $user_email ?: 'customer@pss.com',
                ],
            ]
        ]
    ]);

    if (isset($pm_resp['errors'])) {
        echo json_encode(['success' => false, 'error' => $pm_resp['errors'][0]['detail'] ?? 'Failed to create QRPh payment method.', 'raw' => $pm_resp]);
        exit();
    }

    $pm_id = $pm_resp['data']['id'] ?? '';
    if (!$pm_id) {
        echo json_encode(['success' => false, 'error' => 'No payment method ID returned.', 'raw' => $pm_resp]);
        exit();
    }

    // Step 2: Attach payment method to payment intent
    $attach = pm_request('/payment_intents/' . $payment_intent_id . '/attach', 'POST', [
        'data' => [
            'attributes' => [
                'payment_method' => $pm_id,
                'return_url'     => $return_url . '&method=qrph',
            ]
        ]
    ]);

    if (isset($attach['errors'])) {
        echo json_encode(['success' => false, 'error' => $attach['errors'][0]['detail'] ?? 'Failed to attach QRPh payment method.', 'raw' => $attach]);
        exit();
    }

    $pi_attrs    = $attach['data']['attributes'] ?? [];
    $pi_status   = $pi_attrs['status'] ?? '';
    $next_action = $pi_attrs['next_action'] ?? null;

    // If already succeeded (unlikely for QRPh but handle it)
    if ($pi_status === 'succeeded') {
        $conn->query("UPDATE orders SET payment_status='paid', order_status='confirmed', payment_method='qrph' WHERE order_id=" . intval($order_id));
        clearQRPhSession();
        echo json_encode(['success' => true, 'already_paid' => true, 'redirect' => 'receipt.php?order_id=' . $order_id]);
        exit();
    }

    // next_action.type should be 'consume_qr' for QRPh
    // QR image is at next_action.code.image_url (base64 PNG data URI)
    $qr_image  = '';
    $qr_string = '';
    if ($next_action && ($next_action['type'] ?? '') === 'consume_qr') {
        $code      = $next_action['code'] ?? [];
        $qr_image  = $code['image_url']  ?? '';  // base64 PNG data URI
        $qr_string = $code['id']         ?? '';  // QR code string/id
    }

    // Store payment method ID so we can track it
    $_SESSION['payment_method_id'] = $pm_id;

    echo json_encode([
        'success'            => true,
        'payment_intent_id'  => $payment_intent_id,
        'payment_method_id'  => $pm_id,
        'qr_image'           => $qr_image,
        'qr_string'          => $qr_string,
        'pi_status'          => $pi_status,
        'amount'             => number_format($amount_to_pay, 2),
        'order_id'           => $order_id,
    ]);
    exit();
}

// ─── Action: poll payment intent status ───────────────────────────────────────
// Poll the payment intent (not a source) to detect when QRPh is paid
if ($action === 'poll_qrph') {
    // Use the QRPh-dedicated payment intent, not the card one
    $pi_id = $_GET['payment_intent_id'] ?? ($_SESSION['qrph_payment_intent_id'] ?? '');

    if (!$pi_id) {
        echo json_encode(['status' => 'unknown', 'error' => 'No payment intent ID']);
        exit();
    }

    $pi      = pm_request('/payment_intents/' . $pi_id);
    $attrs   = $pi['data']['attributes'] ?? [];
    $status  = $attrs['status'] ?? 'awaiting_payment_method';

    if ($status === 'succeeded') {
        $conn->query("UPDATE orders SET payment_status='paid', order_status='confirmed', payment_method='qrph' WHERE order_id=" . intval($order_id));
        // Clear cart
        $selected_ids = $_SESSION['payment_selected_ids'] ?? [];
        if (!empty($selected_ids)) {
            $ids = implode(',', array_map('intval', $selected_ids));
            $conn->query("DELETE FROM cart WHERE user_id = $user_id AND cart_id IN ($ids)");
        }
        clearQRPhSession();
        echo json_encode(['status' => 'paid', 'redirect' => 'receipt.php?order_id=' . $order_id]);
        exit();
    }

    // Check for QRPh-specific expiry in the payments array
    $payments = $attrs['payments'] ?? [];
    foreach ($payments as $pmt) {
        $pmt_status = $pmt['attributes']['status'] ?? '';
        if ($pmt_status === 'failed') {
            echo json_encode(['status' => 'expired']);
            exit();
        }
    }

    if ($status === 'payment_error' || $status === 'failed') {
        echo json_encode(['status' => 'expired']);
        exit();
    }

    // awaiting_next_action = QR shown, waiting for scan
    // awaiting_payment_method = still setting up
    echo json_encode(['status' => $status]);
    exit();
}

function clearQRPhSession(): void {
    unset(
        $_SESSION['qrph_source_id'],
        $_SESSION['qrph_payment_intent_id'],
        $_SESSION['pending_order_id'],
        $_SESSION['payment_intent_id'],
        $_SESSION['payment_client_key'],
        $_SESSION['payment_selected_ids'],
        $_SESSION['payment_method_id']
    );
}

// Unknown action
echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
exit();