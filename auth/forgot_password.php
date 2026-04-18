<?php
// auth/forgot_password.php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../config/config.php';

// ─── EmailJS Config ───────────────────────────────────────────────────────────
if (!defined('EMAILJS_SERVICE_ID'))                define('EMAILJS_SERVICE_ID',               'service_jl4ryyf');
if (!defined('EMAILJS_PUBLIC_KEY'))                define('EMAILJS_PUBLIC_KEY',               'u4hgAipwQS-Q0NAg-');
if (!defined('EMAILJS_PRIVATE_KEY'))               define('EMAILJS_PRIVATE_KEY',              'OC-tVKWiKvPS5XVEPWYa2');
if (!defined('EMAILJS_TEMPLATE_FORGOT_PASSWORD'))  define('EMAILJS_TEMPLATE_FORGOT_PASSWORD', 'template_6ggz3iv');
if (!defined('SITE_BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    define('SITE_BASE_URL', $protocol . $host);
}

// ─── Force Philippines timezone (UTC+8) ──────────────────────────────────────
date_default_timezone_set('Asia/Manila');

function redirectBack(string $type, string $msg): void {
    $_SESSION['fp_' . $type] = $msg;
    header('Location: ../modules/customer/forgot_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../modules/customer/forgot_password.php');
    exit();
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectBack('error', 'Please enter a valid email address.');
}

// ── Look up user FIRST (needed for cooldown check too) ────────────────────────
$stmt = $conn->prepare("SELECT user_id, username, email, status, role FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'customer') {
    redirectBack('success', 'If that email is registered, you will receive a reset link shortly.');
}

if ($user['status'] !== 'active') {
    redirectBack('error', 'Your account is ' . htmlspecialchars($user['status']) . '. Please contact support.');
}

// ── 3-Minute Cooldown (using PHP time comparison to avoid timezone issues) ────
$cool_stmt = $conn->prepare(
    "SELECT created_at FROM password_resets 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 1"
);
$cool_stmt->bind_param('i', $user['user_id']);
$cool_stmt->execute();
$cool_row = $cool_stmt->get_result()->fetch_assoc();
$cool_stmt->close();

if ($cool_row) {
    // Convert DB time (which is UTC) to timestamp, then compare with PHP Manila time
    $db_time      = strtotime($cool_row['created_at']);
$seconds_since = time() - $db_time;
    if ($seconds_since < 30) {
        $seconds_left = 30 - $seconds_since;
        $mins = floor($seconds_left / 60);
        $secs = $seconds_left % 60;
        $wait = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
        redirectBack('error', "Please wait {$wait} before requesting another reset link.");
    }
}

// ── Generate token ────────────────────────────────────────────────────────────
$token      = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $token);
// ── Clear old tokens, insert new ─────────────────────────────────────────────
$del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
$del->bind_param('i', $user['user_id']);
$del->execute();
$del->close();

$ins = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 MINUTE))");
$ins->bind_param('is', $user['user_id'], $token_hash);
$ins->execute();
$ins->close();

// ── Build reset link (using SITE_BASE_URL like register.php) ─────────────────
$reset_link = SITE_BASE_URL . '/modules/customer/reset_password.php?token=' . urlencode($token);

$firstname = explode(' ', trim($user['username']))[0];

// ── Send via EmailJS REST API ─────────────────────────────────────────────────
$payload = json_encode([
    'service_id'  => EMAILJS_SERVICE_ID,
    'template_id' => EMAILJS_TEMPLATE_FORGOT_PASSWORD,
    'user_id'     => EMAILJS_PUBLIC_KEY,
    'accessToken' => EMAILJS_PRIVATE_KEY,
    'template_params' => [
        'to_email'   => $user['email'],
        'firstname'  => $firstname,
        'reset_link' => $reset_link,
        'reply_to'   => 'no-reply@personalshopper.com',
    ],
]);

$ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Origin: ' . SITE_BASE_URL,
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

error_log("[ForgotPW] To: {$user['email']} | HTTP: $http_status | Response: $response" . ($curl_error ? " | cURL: $curl_error" : ''));

if ($http_status === 200) {
    redirectBack('success', 'A password reset link has been sent to your email. It expires in 2 minutes.');
} else {
    redirectBack('error', "Email failed (HTTP $http_status): $response");
}
?>