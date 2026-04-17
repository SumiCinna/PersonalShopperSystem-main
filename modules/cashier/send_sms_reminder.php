<?php
// modules/cashier/send_sms_reminder.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pos.php");
    exit();
}

$mobile          = trim($_POST['mobile']          ?? '');
$customer_name   = trim($_POST['customer_name']   ?? '');
$tracking_no     = trim($_POST['tracking_no']     ?? '');
$pickup_datetime = trim($_POST['pickup_datetime'] ?? '');
$balance_due     = floatval($_POST['balance_due'] ?? 0);

if (empty($mobile)) {
    header("Location: pos.php?sms=no_mobile");
    exit();
}

// ─── Bearer Token ──────────────────────────────────────────────────────────────
$api_token = 'apit-BiOR2UIynjLZvKkIgxbHBo4lFERSZ9Xo-hz57j';

// Convert PH number to international format
if (str_starts_with($mobile, '09')) {
    $mobile = '63' . substr($mobile, 1);
} elseif (str_starts_with($mobile, '+63')) {
    $mobile = substr($mobile, 1);
}

// ─── Build message ─────────────────────────────────────────────────────────────
$pickup_formatted = date('F j, Y \a\t h:i A', strtotime($pickup_datetime));

$payment_line = $balance_due > 0
    ? "Balance: P" . number_format($balance_due, 2) . ". Prepare exact amount."
    : "Order is fully paid. No payment needed.";

$message =
    "Hi {$customer_name}! PSS Order Reminder.\n" .
    "Ref: {$tracking_no}\n" .
    "Pickup: {$pickup_formatted}\n" .
    "{$payment_line}\n" .
    "Go to pickup counter upon arrival. Thank you!";

// ─── Send via MoceanAPI (Bearer Auth + form-encoded body) ─────────────────────
$post_fields = http_build_query([
    'mocean-from' => 'PSS',
    'mocean-to'   => $mobile,
    'mocean-text' => $message,
]);

$ch = curl_init('https://rest.moceanapi.com/rest/2/sms');
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT,        15);
curl_setopt($ch, CURLOPT_HTTPHEADER,     [
    'Authorization: Bearer ' . $api_token,
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
]);
$response  = curl_exec($ch);
$curl_err  = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ─── Log ──────────────────────────────────────────────────────────────────────
$log_entry = date('Y-m-d H:i:s')
    . " | HTTP: {$http_code}"
    . " | TO: {$mobile}"
    . " | REF: {$tracking_no}"
    . " | RESPONSE: {$response}"
    . " | CURL_ERR: {$curl_err}\n";
file_put_contents(__DIR__ . '/sms_log.txt', $log_entry, FILE_APPEND);

// ─── Check result ─────────────────────────────────────────────────────────────
// MoceanAPI returns HTTP 200 or 202 on success — accept both
$decoded    = json_decode($response, true);
$sms_status = $decoded['messages'][0]['status'] ?? -1;

if (!$curl_err && in_array($http_code, [200, 202]) && $sms_status == 0) {
    header("Location: pos.php?sms=sent");
} else {
    header("Location: pos.php?sms=error");
}
exit();
?>