<?php
// core/customer/payment_return.php
session_start();
require_once '../../config/config.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;
$payment_intent_id = $_GET['payment_intent_id'] ?? null;

if (!$order_id || !$payment_intent_id) {
    die("Missing order ID or payment intent ID.");
}

// ── Fetch Order ───────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found or access denied.");
}

// ── Verify Payment Intent with PayMongo ───────────────────────────────────────
$client = new \GuzzleHttp\Client();
$paymongo_secret_key = getenv('PAYMONGO_SECRET_KEY');

try {
    $response = $client->request('GET', "https://api.paymongo.com/v1/payment_intents/{$payment_intent_id}", [
        'auth' => [$paymongo_secret_key, '']
    ]);

    $payment_intent = json_decode($response->getBody(), true);
    $payment_status = $payment_intent['data']['attributes']['status'];

    // Check if the payment was successful
    if ($payment_status === 'succeeded') {
        $paid_amount = $payment_intent['data']['attributes']['amount'] / 100;

        // Check if the paid amount matches the expected upfront payment
        if (abs($paid_amount - $order['upfront_payment']) < 0.01) {

            // Extract the actual payment method used
            $payment_method = 'Card';
            if (isset($payment_intent['data']['relationships']['payments']['data'][0]['id'])) {
                $payment_id = $payment_intent['data']['relationships']['payments']['data'][0]['id'];
                
                // Get payment details to determine method
                $payment_response = $client->request('GET', "https://api.paymongo.com/v1/payments/{$payment_id}", [
                    'auth' => [$paymongo_secret_key, '']
                ]);
                $payment_data = json_decode($payment_response->getBody(), true);
                
                if (isset($payment_data['data']['attributes']['source']['type'])) {
                    $source_type = strtolower($payment_data['data']['attributes']['source']['type']);
                    if ($source_type === 'gcash') {
                        $payment_method = 'GCash';
                    } elseif ($source_type === 'card') {
                        $payment_method = 'Card';
                    } elseif ($source_type === 'paymaya') {
                        $payment_method = 'PayMaya';
                    }
                }
            }

            // Use a transaction to ensure data integrity
            $conn->begin_transaction();

            try {
                // Update order status
                $update_stmt = $conn->prepare("
                    UPDATE orders
                    SET
                        payment_status = 'paid',
                        order_status = 'processing',
                        payment_method = ?,
                        paymongo_pi_id = ?
                    WHERE
                        order_id = ? AND user_id = ?
                ");
                if (!$update_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $update_stmt->bind_param("ssii", $payment_method, $payment_intent_id, $order_id, $user_id);

                if (!$update_stmt->execute()) {
                    throw new Exception("Execute failed: " . $update_stmt->error);
                }

                $update_stmt->close();
                $conn->commit();

                // Redirect to a success page
                header("Location: ../../modules/customer/receipt.php?order_id=" . $order_id);
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Transaction failed: " . $e->getMessage());
                die("An error occurred while updating your order. Please contact support.");
            }

        } else {
            // Amount mismatch
            die("Payment amount mismatch. Please contact support.");
        }
    } else {
        // Payment not successful
        header("Location: ../../modules/customer/payment_failed.php?order_id=" . $order_id);
        exit();
    }
} catch (\GuzzleHttp\Exception\ClientException $e) {
    $response = $e->getResponse();
    $responseBodyAsString = $response->getBody()->getContents();
    error_log("PayMongo API Error: " . $responseBodyAsString);
    die("Could not verify payment. Please contact support.");
} catch (Exception $e) {
    error_log("General Error in payment_return.php: " . $e->getMessage());
    die("An unexpected error occurred. Please contact support.");
}
