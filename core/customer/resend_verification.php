<?php
session_start();
require_once '../../config/config.php';

// EmailJS credentials — same as register.php
define('EMAILJS_SERVICE_ID',  'service_jl4ryyf');
define('EMAILJS_TEMPLATE_ID', 'template_0ntsd08');
define('EMAILJS_PUBLIC_KEY',  'u4hgAipwQS-Q0NAg-');
define('SITE_BASE_URL',       'http://localhost:3000');

header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Get user info
$stmt = $conn->prepare("SELECT email_verified, status, email FROM users 
                         JOIN user_profiles ON users.user_id = user_profiles.user_id 
                         WHERE users.user_id = ?");
// user_profiles has firstname
$stmt = $conn->prepare("
    SELECT u.email, u.email_verified, u.status, p.firstname
    FROM users u
    JOIN user_profiles p ON p.user_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit;
}

if ($row['email_verified'] == 1 || $row['status'] === 'active') {
    echo json_encode(['success' => false, 'message' => 'This account is already verified.']);
    exit;
}

// Rate limit: max 1 resend per 2 minutes
$stmtCheck = $conn->prepare("SELECT created_at FROM email_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmtCheck->bind_param('i', $user_id);
$stmtCheck->execute();
$lastRow = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if ($lastRow && (time() - strtotime($lastRow['created_at'])) < 120) {
    echo json_encode(['success' => false, 'message' => 'Please wait before requesting another email.']);
    exit;
}

// Generate new token
$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Delete old tokens and insert new one
$conn->query("DELETE FROM email_verifications WHERE user_id = $user_id");
$stmtTok = $conn->prepare("INSERT INTO email_verifications (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
$stmtTok->bind_param('iss', $user_id, $token, $expiresAt);
$stmtTok->execute();
$stmtTok->close();

// Send email via EmailJS
$activationLink = SITE_BASE_URL . '/modules/customer/verify_email.php?token=' . urlencode($token);

$payload = [
    'service_id'  => EMAILJS_SERVICE_ID,
    'template_id' => EMAILJS_TEMPLATE_ID,
    'user_id'     => EMAILJS_PUBLIC_KEY,
    'template_params' => [
        'to_email'        => $row['email'],
        'firstname'       => $row['firstname'],
        'activation_link' => $activationLink,
    ],
];

$ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'origin: ' . SITE_BASE_URL,
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send email. Try again later.']);
}