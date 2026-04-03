<?php
// modules/customer/payment_return.php
session_start();
date_default_timezone_set('Asia/Manila');
require_once '../../config/config.php';

// ─── PayMongo Config ───────────────────────────────────────────────────────────
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

// ─── Security ─────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$order_id = intval($_GET['order_id'] ?? $_SESSION['pending_order_id'] ?? 0);
$method   = $_GET['method'] ?? 'card'; // 'gcash' or 'card'

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// ─── Load Order ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Already paid? Go to receipt directly
if ($order['payment_status'] === 'paid') {
    header("Location: receipt.php?order_id=" . $order_id);
    exit();
}

$payment_intent_id = $_SESSION['payment_intent_id'] ?? $order['payment_intent_id'] ?? '';
$error_message     = '';
$payment_verified  = false;

// ─── Verify PaymentIntent Status ──────────────────────────────────────────────
if ($payment_intent_id) {
    $pi_data = pm_request('/payment_intents/' . $payment_intent_id);
    $pi_status = $pi_data['data']['attributes']['status'] ?? 'unknown';

    if ($pi_status === 'succeeded') {
        $payment_verified = true;

        // Get the last payment record from PI for reference
        $payments_list = $pi_data['data']['attributes']['payments'] ?? [];
        $last_payment  = !empty($payments_list) ? end($payments_list) : null;
        $pm_ref        = $last_payment['id'] ?? $payment_intent_id;

        // ── Update order in DB ─────────────────────────────────────────────
        $payment_method_name = ($method === 'gcash') ? 'gcash' : 'card';
        $error_message = ''; // Reset error message

        $stmt = $conn->prepare("
            UPDATE orders
            SET payment_status   = 'paid',
                order_status     = 'processing',
                payment_method   = ?
            WHERE order_id = ? AND user_id = ?
        ");

        if (!$stmt) {
            error_log("DB prepare failed in payment_return: " . $conn->error);
            $error_message = "A database error occurred while updating your order. Please contact support.";
        } else {
            $stmt->bind_param("sii", $payment_method_name, $order_id, $user_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 0) {
                    error_log("Order update failed for order_id: $order_id and user_id: $user_id. No rows affected.");
                    $error_message = "Could not find the order to update. Please contact support.";
                }
            } else {
                error_log("DB execute failed in payment_return: " . $stmt->error);
                $error_message = "A database error occurred while executing the update. Please contact support.";
            }
            $stmt->close();
        }

        // If there was no error, proceed with clearing session and redirecting
        if (empty($error_message)) {
            // ── Clear cart ─────────────────────────────────────────────────────
            $selected_ids = $_SESSION['payment_selected_ids'] ?? [];
            if (!empty($selected_ids)) {
                $ids_str = implode(',', array_map('intval', $selected_ids));
                $conn->query("DELETE FROM cart WHERE user_id = $user_id AND cart_id IN ($ids_str)");
            }

            // ── Clear payment session vars ─────────────────────────────────────
            unset(
                $_SESSION['pending_order_id'],
                $_SESSION['payment_intent_id'],
                $_SESSION['payment_client_key'],
                $_SESSION['payment_selected_ids'],
                $_SESSION['payment_method_id'],
                $_SESSION['payment_user_name'],
                $_SESSION['payment_user_email']
            );

            // ── Redirect to receipt ────────────────────────────────────────────
            header("Location: receipt.php?order_id=" . $order_id . "&paid=1");
            exit();
        }
        // If $error_message is set, the script will continue and display the error page.

    } elseif (in_array($pi_status, ['awaiting_payment_method', 'processing'])) {
        // Payment still in progress or needs retry
        $error_message = 'Your payment could not be completed. Please try again.';

    } elseif ($pi_status === 'awaiting_next_action') {
        // Still needs action — redirect back to payment page
        header("Location: payment.php?order_id=" . $order_id . "&retry=1");
        exit();

    } else {
        $error_message = 'Payment was not successful (status: ' . htmlspecialchars($pi_status) . '). Please try again.';
        error_log("PayMongo PI $payment_intent_id returned status: $pi_status");
    }
} else {
    $error_message = 'Payment session expired. Please place your order again.';
}

// ─── Show error page if verification failed ────────────────────────────────────
$page_title = 'Payment Status';
require_once '../../includes/customer_header.php';
?>

<main class="min-h-screen bg-gradient-to-br from-slate-50 to-red-50 py-16 px-4 flex items-center">
    <div class="max-w-lg mx-auto w-full">
        <div class="bg-white rounded-2xl shadow-lg border border-red-100 overflow-hidden">

            <div class="bg-gradient-to-r from-red-500 to-rose-600 px-6 py-8 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-black text-white mb-1">Payment Failed</h1>
                <p class="text-red-100 text-sm">Don't worry — your order has been saved.</p>
            </div>

            <div class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <p class="text-sm text-red-700 font-semibold"><?php echo $error_message; ?></p>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 mb-6 text-sm">
                    <p class="text-gray-500 mb-1">Order Reference</p>
                    <p class="font-black text-gray-800 text-lg">#<?php echo $order_id; ?></p>
                    <p class="text-gray-500 mt-2 text-xs">Your order is saved. You can retry payment below.</p>
                </div>

                <div class="space-y-3">
                    <a href="payment.php?order_id=<?php echo $order_id; ?>"
                        class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-center transition text-sm">
                        🔄 Retry Payment
                    </a>
                    <a href="orders.php"
                        class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl text-center transition text-sm">
                        View My Orders
                    </a>
                    <a href="cart.php"
                        class="block text-center text-sm text-gray-400 hover:text-gray-600 transition py-2">
                        ← Back to Cart
                    </a>
                </div>
            </div>

        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            Need help? Contact our store during business hours <strong>(10:00 AM – 7:00 PM)</strong>
        </p>
    </div>
</main>

<?php
require_once '../../includes/customer_footer.php';
$conn->close();
?>