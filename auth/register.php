<?php
date_default_timezone_set('Asia/Manila');
require_once '../config/config.php';
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}
session_start();

define('EMAILJS_SERVICE_ID',  'service_jl4ryyf');   
define('EMAILJS_TEMPLATE_ID', 'template_0ntsd08');  
define('EMAILJS_PUBLIC_KEY',  'u4hgAipwQS-Q0NAg-');   
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
define('SITE_BASE_URL', $protocol . $host);

function sendActivationEmail(string $toEmail, string $firstname, string $token): bool {
    $activationLink = SITE_BASE_URL . '/modules/customer/verify_email.php?token=' . urlencode($token);
    $payload = [
        'service_id'  => EMAILJS_SERVICE_ID,
        'template_id' => EMAILJS_TEMPLATE_ID,
        'user_id'     => EMAILJS_PUBLIC_KEY,
        'template_params' => [
            'to_email'        => $toEmail,
            'firstname'       => $firstname,
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
    return ($httpCode === 200);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $firstname  = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $surname    = trim($_POST['surname'] ?? '');
    $suffix     = trim($_POST['suffix'] ?? '');
    $mobile     = trim($_POST['mobile'] ?? '');
    $terms      = isset($_POST['terms']) ? 1 : 0;

    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    } elseif (strlen($username) > 20) {
        $errors[] = 'Username must not exceed 20 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores.';
    }
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (strlen($email) > 254) {
        $errors[] = 'Email address must not exceed 254 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!preg_match('/@.*\.com$/', $email)) {
        $errors[] = 'Email must contain "@" and end with ".com".';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 20) {
        $errors[] = 'Password must not exceed 20 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($firstname)) {
        $errors[] = 'First name is required.';
    } elseif (strlen($firstname) > 50) {
        $errors[] = 'First name must not exceed 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $firstname)) {
        $errors[] = 'First name can only contain letters.';
    }
    if (!empty($middlename)) {
        if (strlen($middlename) > 50) {
            $errors[] = 'Middle name must not exceed 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $middlename)) {
            $errors[] = 'Middle name can only contain letters.';
        }
    }
    if (empty($surname)) {
        $errors[] = 'Surname is required.';
    } elseif (strlen($surname) > 50) {
        $errors[] = 'Surname must not exceed 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $surname)) {
        $errors[] = 'Surname can only contain letters.';
    }
    if (empty($mobile) || !preg_match('/^09\d{9}$/', $mobile)) {
        $errors[] = 'Please enter a valid mobile number (09xx xxx xxxx).';
    }
    if (!$terms) {
        $errors[] = 'You must agree to the Terms and Conditions.';
    }

    if (empty($errors)) {
        $stmtU = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
        $stmtU->bind_param('s', $username);
        $stmtU->execute();
        $stmtU->store_result();
        if ($stmtU->num_rows > 0) $errors[] = 'Username is already taken.';
        $stmtU->close();

        $stmtE = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmtE->bind_param('s', $email);
        $stmtE->execute();
        $stmtE->store_result();
        if ($stmtE->num_rows > 0) $errors[] = 'Email address is already registered.';
        $stmtE->close();

        $stmtM = $conn->prepare('SELECT user_id FROM user_profiles WHERE mobile = ?');
        $stmtM->bind_param('s', $mobile);
        $stmtM->execute();
        $stmtM->store_result();
        if ($stmtM->num_rows > 0) $errors[] = 'Mobile number is already registered.';
        $stmtM->close();
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $conn->prepare(
                'INSERT INTO users (username, password, email, role, terms_agreed, status, email_verified, created_at, updated_at)
                 VALUES (?, ?, ?, "customer", ?, "inactive", 0, NOW(), NOW())'
            );
            $stmtUser->bind_param('sssi', $username, $hashed, $email, $terms);
            $stmtUser->execute();
            $userId = $conn->insert_id;
            $stmtUser->close();

            $mn = $middlename ?: null;
            $sf = $suffix ?: null;
            $stmtProfile = $conn->prepare(
                'INSERT INTO user_profiles (user_id, firstname, middlename, surname, suffix, mobile)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmtProfile->bind_param('isssss', $userId, $firstname, $mn, $surname, $sf, $mobile);
            $stmtProfile->execute();
            $stmtProfile->close();

            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+2 minutes'));
            $conn->query("DELETE FROM email_verifications WHERE user_id = $userId");
            $stmtTok = $conn->prepare(
                'INSERT INTO email_verifications (user_id, token, expires_at, created_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmtTok->bind_param('iss', $userId, $token, $expiresAt);
            $stmtTok->execute();
            $stmtTok->close();
            $conn->commit();

            $sent = sendActivationEmail($email, $firstname, $token);
            echo json_encode([
                'success'    => true,
                'email_sent' => $sent,
                'user_id'    => $userId,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ]);
            exit;
        }
        exit;
    }

    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Personal Shopper &mdash; Create Account</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Serif+Display&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue:      #2d5be3;
  --blue-dark: #1e3fa8;
  --blue-bg:   #e8eeff;
  --bg:        #eef2fb;
  --card:      #ffffff;
  --text:      #1a1d2e;
  --muted:     #6b7194;
  --border:    #d6ddf5;
  --error:     #e03e3e;
  --success:   #1aab6d;
}
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  background-image:
    radial-gradient(ellipse at 15% 15%, rgba(45,91,227,.10) 0%, transparent 55%),
    radial-gradient(ellipse at 85% 85%, rgba(45,91,227,.08) 0%, transparent 55%);
}
.wrapper { width: 100%; max-width: 540px; }
.brand { text-align: center; margin-bottom: 24px; }
.brand h1 { font-family: 'DM Serif Display', serif; font-size: 2rem; color: var(--blue-dark); letter-spacing: -.5px; }
.brand p { color: var(--muted); font-size: .95rem; margin-top: 4px; }
.card {
  background: var(--card);
  border-radius: 20px;
  box-shadow: 0 4px 40px rgba(45,91,227,.10), 0 1px 4px rgba(0,0,0,.06);
  padding: 36px 40px 40px;
}
.steps { display: flex; align-items: flex-start; justify-content: center; margin-bottom: 30px; }
.step-item { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; position: relative; }
.step-item:not(:last-child)::after {
  content: ''; position: absolute; top: 17px;
  left: calc(50% + 18px); right: calc(-50% + 18px);
  height: 2px; background: var(--border); transition: background .35s; z-index: 0;
}
.step-item.done:not(:last-child)::after { background: var(--success); }
.step-circle {
  width: 35px; height: 35px; border-radius: 50%;
  border: 2px solid var(--border); background: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: .84rem; font-weight: 600; color: var(--muted);
  transition: all .3s; position: relative; z-index: 1;
}
.step-item.active .step-circle { border-color: var(--blue); background: var(--blue); color: #fff; }
.step-item.done  .step-circle  { border-color: var(--success); background: var(--success); color: #fff; }
.step-item.done .step-num { display: none; }
.step-item.done .step-circle::after { content: '✓'; }
.step-label { font-size: .72rem; color: var(--muted); font-weight: 500; text-align: center; }
.step-item.active .step-label { color: var(--blue); }
.step-item.done  .step-label  { color: var(--success); }
.panel { display: none; }
.panel.active { display: block; animation: fadeIn .28s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: none; } }
.panel-title { font-size: 1rem; font-weight: 600; color: var(--text); margin-bottom: 18px; }
.form-row { display: flex; gap: 14px; }
.form-row .field { flex: 1; min-width: 0; }
.field { margin-bottom: 14px; }
.field label { display: block; font-size: .82rem; font-weight: 500; color: var(--text); margin-bottom: 5px; }
.req { color: var(--blue); margin-left: 2px; }
.field input, .field select {
  width: 100%; padding: 10px 13px;
  border: 1.5px solid var(--border); border-radius: 10px;
  font-size: .93rem; font-family: 'DM Sans', sans-serif;
  color: var(--text); background: #fafbff; outline: none;
  transition: border-color .2s, box-shadow .2s; appearance: none;
}
.field input:focus, .field select:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(45,91,227,.11); background: #fff; }
.field input.invalid, .field select.invalid { border-color: var(--error); }
.err-msg { font-size: .77rem; color: var(--error); margin-top: 4px; display: none; }
.err-msg.show { display: block; }
.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 42px; }
.pw-toggle {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: var(--muted);
  font-size: .95rem; padding: 0; line-height: 1;
  display: flex; align-items: center; justify-content: center;
}
.strength-bar { display: flex; gap: 4px; margin-top: 7px; }
.strength-bar span { flex: 1; height: 3px; border-radius: 3px; background: var(--border); transition: background .3s; }
.strength-label { font-size: .74rem; color: var(--muted); margin-top: 3px; }
.terms-box {
  border: 1.5px solid var(--border); border-radius: 10px;
  padding: 12px 15px; max-height: 120px; overflow-y: auto;
  font-size: .82rem; color: var(--muted); line-height: 1.65;
  margin-bottom: 13px; background: #fafbff;
}
.check-row { display: flex; align-items: flex-start; gap: 9px; margin-bottom: 14px; }
.check-row input[type=checkbox] { width: 17px; height: 17px; accent-color: var(--blue); flex-shrink: 0; margin-top: 2px; cursor: pointer; }
.check-row label { font-size: .87rem; color: var(--text); cursor: pointer; line-height: 1.5; }
.review-box {
  background: #fafbff; border: 1.5px solid var(--border);
  border-radius: 10px; padding: 13px 15px;
  margin-bottom: 14px; font-size: .84rem; line-height: 1.9; color: var(--text);
}
.review-label { color: var(--muted); display: inline-block; width: 120px; }
.btn-row { display: flex; gap: 11px; margin-top: 4px; }
.btn {
  flex: 1; padding: 12px; border-radius: 10px;
  font-size: .96rem; font-weight: 600; font-family: 'DM Sans', sans-serif;
  cursor: pointer; transition: all .2s; border: none; text-align: center;
}
.btn-primary { background: var(--blue); color: #fff; }
.btn-primary:hover { background: var(--blue-dark); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(45,91,227,.28); }
.btn-primary:active { transform: none; }
.btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
.btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--muted); }
.btn-outline:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-bg); }
.alert { border-radius: 10px; padding: 11px 15px; font-size: .86rem; margin-bottom: 16px; display: none; line-height: 1.6; }
.alert.show { display: block; }
.alert-error   { background: #fff0f0; border: 1.5px solid #f5b8b8; color: var(--error); }
.alert-success { background: #f0faf6; border: 1.5px solid #a7e5cc; color: var(--success); }
#success-screen { display: none; text-align: center; padding: 16px 0 4px; }
.success-icon {
  width: 70px; height: 70px; border-radius: 50%;
  background: #f0faf6; border: 2px solid var(--success);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.9rem; margin: 0 auto 18px;
  animation: pop .4s cubic-bezier(.17,.67,.32,1.4);
}
@keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
#success-screen h2 { font-family: 'DM Serif Display', serif; font-size: 1.5rem; color: var(--text); margin-bottom: 7px; }
#success-screen p  { color: var(--muted); font-size: .91rem; margin-bottom: 18px; line-height: 1.7; }
.email-box {
  background: var(--blue-bg); border: 1.5px solid #c0cffa; border-radius: 10px;
  padding: 10px 16px; font-size: .9rem; font-weight: 600;
  color: var(--blue-dark); margin-bottom: 20px; word-break: break-all;
}
.resend-area { margin-top: 16px; }
.resend-area p { font-size: .84rem; color: var(--muted); margin-bottom: 8px; }
#resend-btn {
  background: none; border: 1.5px solid var(--border);
  color: var(--blue); font-size: .86rem; font-weight: 600;
  padding: 9px 20px; border-radius: 9px; cursor: pointer;
  font-family: 'DM Sans', sans-serif; transition: all .2s;
}
#resend-btn:hover:not(:disabled) { background: var(--blue-bg); border-color: var(--blue); }
#resend-btn:disabled { opacity: .5; cursor: not-allowed; }
#countdown { font-size: .8rem; color: var(--muted); margin-top: 6px; }
.login-link { text-align: center; margin-top: 16px; font-size: .87rem; color: var(--muted); }
.login-link a { color: var(--blue); text-decoration: none; font-weight: 500; }
.login-link a:hover { text-decoration: underline; }
/* Modal Styles */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,.5); overflow: auto; }
.modal.show { display: block; animation: fadeIn .2s ease; }
.modal-content {
  background: var(--card); margin: 5% auto; padding: 0; max-width: 700px;
  border-radius: 16px; box-shadow: 0 8px 60px rgba(0,0,0,.3);
  max-height: 85vh; overflow: hidden; display: flex; flex-direction: column;
}
.modal-header {
  padding: 20px 24px; border-bottom: 1.5px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
}
.modal-header h2 { font-family: 'DM Serif Display', serif; font-size: 1.3rem; color: var(--text); margin: 0; }
.modal-close {
  background: none; border: none; font-size: 1.8rem;
  cursor: pointer; color: var(--muted); line-height: 1; padding: 0;
}
.modal-close:hover { color: var(--text); }
.modal-body {
  padding: 24px; overflow-y: auto; flex: 1; font-size: .9rem; line-height: 1.8; color: var(--text);
}
.modal-body h3 { font-size: 1rem; font-weight: 700; margin-top: 16px; margin-bottom: 8px; color: var(--blue-dark); }
.modal-body h3:first-child { margin-top: 0; }
.modal-body ol, .modal-body ul { margin: 10px 0 10px 20px; }
.modal-body li { margin-bottom: 6px; }
.modal-footer {
  padding: 16px 24px; border-top: 1.5px solid var(--border);
  display: flex; justify-content: flex-end; align-items: center; gap: 16px;
}
.modal-read-status {
  font-size: .85rem; color: var(--muted); display: none; align-items: center; gap: 8px;
}
.modal-read-status.complete { color: var(--success); }
.modal-read-status.complete::before { content: '✓'; font-weight: 700; }
.modal-btn {
  background: var(--blue); color: white; border: none; padding: 10px 20px;
  border-radius: 8px; cursor: pointer; font-weight: 600; font-size: .9rem;
  font-family: 'DM Sans', sans-serif; transition: background .2s;
}
.modal-btn:hover { background: var(--blue-dark); }
.terms-link {
  color: var(--blue); text-decoration: none; font-weight: 600; cursor: pointer;
}
.terms-link:hover { text-decoration: underline; }
@media (max-width: 480px) {
  .card { padding: 26px 20px 30px; }
  .form-row { flex-direction: column; gap: 0; }
  .step-label { font-size: 0; }
  .modal-content { width: 95%; margin: 20% auto; }
  .modal-body { max-height: 60vh; }
}
</style>
</head>
<body>
<div class="wrapper">
  <div class="brand">
    <h1>Personal Shopper</h1>
    <p>Customer Portal</p>
  </div>
  <div class="card">
    <div id="form-area">
      <div class="steps" id="steps-ui">
        <div class="step-item active" data-step="1">
          <div class="step-circle"><span class="step-num">1</span></div>
          <div class="step-label">Personal</div>
        </div>
        <div class="step-item" data-step="2">
          <div class="step-circle"><span class="step-num">2</span></div>
          <div class="step-label">Account</div>
        </div>
        <div class="step-item" data-step="3">
          <div class="step-circle"><span class="step-num">3</span></div>
          <div class="step-label">Confirm</div>
        </div>
      </div>
      <div id="global-alert" class="alert alert-error"></div>

      <!-- STEP 1: Personal -->
      <div class="panel active" id="panel-1">
        <div class="panel-title">Personal information</div>
        <div class="form-row">
          <div class="field">
            <label>First Name <span class="req">*</span></label>
            <input type="text" id="firstname" placeholder="First name" maxlength="50">
            <div class="err-msg" id="err-firstname"></div>
          </div>
          <div class="field">
            <label>Middle Name</label>
            <input type="text" id="middlename" placeholder="Optional" maxlength="50">
            <div class="err-msg" id="err-middlename"></div>
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label>Surname <span class="req">*</span></label>
            <input type="text" id="surname" placeholder="Last name" maxlength="50">
            <div class="err-msg" id="err-surname"></div>
          </div>
          <div class="field" style="max-width:110px;">
            <label>Suffix</label>
            <select id="suffix">
              <option value="">None</option>
              <option>Jr.</option><option>Sr.</option>
              <option>II</option><option>III</option><option>IV</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label>Mobile Number <span class="req">*</span></label>
          <input type="tel" id="mobile" placeholder="09xx xxx xxxx" maxlength="11" inputmode="numeric">
          <div class="err-msg" id="err-mobile"></div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" onclick="goStep(1)">Continue &rarr;</button>
        </div>
      </div>

      <!-- STEP 2: Account -->
      <div class="panel" id="panel-2">
        <div class="panel-title">Create your account</div>
        <div class="field">
          <label>Username <span class="req">*</span></label>
          <input type="text" id="username" autocomplete="username" placeholder="e.g. john_doe" maxlength="20">
          <div class="err-msg" id="err-username"></div>
        </div>
        <div class="field">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" id="email" autocomplete="email" placeholder="you@example.com" maxlength="254">
          <div class="err-msg" id="err-email"></div>
        </div>
        <div class="field">
          <label>Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="password" autocomplete="new-password" placeholder="8-20 characters" maxlength="20">
            <button type="button" class="pw-toggle" onclick="togglePw('password',this)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
          </div>
          <div class="strength-bar"><span id="sb1"></span><span id="sb2"></span><span id="sb3"></span><span id="sb4"></span></div>
          <div class="strength-label" id="strength-label"></div>
          <div class="err-msg" id="err-password"></div>
        </div>
        <div class="field">
          <label>Confirm Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="confirm_password" autocomplete="new-password" placeholder="Re-enter your password" maxlength="20">
            <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
          </div>
          <div class="err-msg" id="err-confirm"></div>
        </div>
        <div class="btn-row">
          <button class="btn btn-outline" onclick="prevStep(2)">&larr; Back</button>
          <button class="btn btn-primary" onclick="goStep(2)">Continue &rarr;</button>
        </div>
      </div>

      <!-- STEP 3: Confirm -->
      <div class="panel" id="panel-3">
        <div class="panel-title">Review &amp; confirm</div>
        <div class="review-box" id="review-box"></div>
        <div class="terms-box">
          <strong>Terms &amp; Conditions and Privacy Policy</strong><br>
          Please read and review our <span class="terms-link" onclick="openModal('termsModal')"><strong>Terms &amp; Conditions</strong></span> and <span class="terms-link" onclick="openModal('privacyModal')"><strong>Privacy Policy</strong></span> before creating your account. You must agree to both to proceed with registration.
        </div>
        <div class="check-row">
          <input type="checkbox" id="terms" disabled>
          <label for="terms">I have read and agree to the <span class="terms-link" onclick="openModal('termsModal')">Terms &amp; Conditions</span> and <span class="terms-link" onclick="openModal('privacyModal')">Privacy Policy</span>.</label>
        </div>
        <div class="err-msg" id="err-terms" style="margin-bottom:10px;"></div>
        <div class="btn-row">
          <button class="btn btn-outline" onclick="prevStep(3)">&larr; Back</button>
          <button class="btn btn-primary" id="submit-btn" onclick="submitForm()">Create Account</button>
        </div>
      </div>
    </div>

    <!-- Verification Pending Screen -->
    <div id="success-screen">
      <div class="success-icon">📧</div>
      <h2>Check Your Email!</h2>
      <p>We sent an activation link to:</p>
      <div class="email-box" id="registered-email"></div>
      <p>Click the link in the email to activate your account.<br>
         The link expires in <strong>2 minutes</strong>.</p>
      <div class="resend-area">
        <p>Didn't receive it? Check your spam folder or resend below.</p>
        <button id="resend-btn" onclick="resendEmail()">Resend Activation Email</button>
        <div id="countdown"></div>
      </div>
      <div style="margin-top:22px;">
        <a href="login.php" class="btn btn-outline" style="display:inline-block;text-decoration:none;padding:10px 28px;">
          Go to Login
        </a>
      </div>
    </div>

    <div class="login-link" id="login-link-row">
      Already have an account? <a href="login.php">Sign in</a>
    </div>
  </div>

  <!-- Terms & Conditions Modal -->
  <div id="termsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Terms &amp; Conditions</h2>
        <button class="modal-close" onclick="closeModal('termsModal')">&times;</button>
      </div>
      <div class="modal-body">
        <h3>1. Acceptance of Terms</h3>
        <p>By clicking "Create Account" or accessing our services, you expressly acknowledge that you have read, understood, and agreed to be bound by these Terms of Service and our Privacy Policy. If you do not agree to these terms, you may not create an account or use our services.</p>

        <h3>2. Eligibility</h3>
        <p>You must be at least 18 years of age to register for an account. By creating an account, you represent and warrant that you meet this age requirement and have the legal capacity to enter into this agreement.</p>

        <h3>3. Privacy and Data Usage</h3>
        <p>Your privacy is our priority. By creating an account, you consent to the collection, processing, and use of your personal data for the following purposes:</p>
        <ul>
          <li><strong>Order Processing:</strong> To facilitate the purchase and delivery of products.</li>
          <li><strong>Account Management:</strong> To maintain your profile, order history, and account settings.</li>
          <li><strong>Order Pick-up Validation:</strong> To ensure secure handovers and prevent fraud, we collect and verify necessary identification details or order confirmation codes when you choose to pick up your purchases in person.</li>
          <li><strong>No-Sale Policy:</strong> We guarantee that your personal data will not be sold to third parties. Any sharing of data with service providers (e.g., payment processors) is done solely to facilitate the services you have requested.</li>
          <li><strong>Communication &amp; Notifications:</strong> By providing your email address and mobile number, you consent to receive communications from Personal Shopper. This includes receiving email verification codes to ensure account security, as well as SMS notifications regarding your order status, such as pick-up dates, designated time slots, and location details.</li>
        </ul>

        <h3>4. User Responsibility</h3>
        <p>You are solely responsible for maintaining the confidentiality of your account credentials (username and password) and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>

        <h3>5. Modifications</h3>
        <p>We may update these terms from time to time to reflect changes in our services or legal requirements. Your continued use of the account following the posting of changes constitutes your acceptance of such changes.</p>

        <h3>6. Order Requirements</h3>
        <p>All orders placed through the Personal Shopper platform must meet a minimum purchase value of 300 PHP. Orders that do not meet this minimum threshold will not be eligible for processing or checkout.</p>
      </div>
      <div class="modal-footer">
        <div class="modal-read-status" id="termsReadStatus">Scroll to read</div>
        <div style="width:150px;height:4px;background:var(--border);border-radius:2px;overflow:hidden;">
          <div id="termsProgressBar" style="width:0%;height:100%;background:var(--blue);transition:width .3s ease;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Privacy Policy Modal -->
  <div id="privacyModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Privacy Policy</h2>
        <button class="modal-close" onclick="closeModal('privacyModal')">&times;</button>
      </div>
      <div class="modal-body">
        <p><strong>Effective Date:</strong> April 18, 2026</p>

        <h3>1. Introduction</h3>
        <p>Welcome to Personal Shopper. We are committed to protecting your personal information and your right to privacy. This Privacy Policy explains how we collect, use, and safeguard your data when you create an account or use our services. By using Personal Shopper, you agree to the collection and use of information in accordance with this policy.</p>

        <h3>2. Information We Collect</h3>
        <p>We collect the following personal information when you register and use our platform:</p>
        <ul>
          <li>Full name and contact details (email address, mobile number)</li>
          <li>Account credentials (username and password)</li>
          <li>Order history and purchase records</li>
          <li>Identification details or order confirmation codes for in-person pick-up validation</li>
          <li>Payment information (processed through trusted third-party payment processors)</li>
        </ul>

        <h3>3. How We Use Your Information</h3>
        <p>Your personal data is used strictly for the following purposes:</p>
        <ul>
          <li><strong>Order Processing</strong> — To facilitate the purchase, payment, and delivery of your orders.</li>
          <li><strong>Account Management</strong> — To maintain your profile, order history, and account settings.</li>
          <li><strong>Pick-up Validation</strong> — To verify your identity and ensure secure, fraud-free in-person handovers.</li>
          <li><strong>Security</strong> — To send email verification codes and protect your account from unauthorized access.</li>
          <li><strong>Order Notifications</strong> — To send SMS and email updates regarding your order status, pick-up schedules, time slots, and location details.</li>
        </ul>

        <h3>4. How We Share Your Information</h3>
        <p>We do not sell your personal data to any third parties. Your information may be shared only with trusted service providers (such as payment processors and logistics partners) solely to fulfill the services you have requested. These parties are contractually obligated to handle your data securely and confidentially.</p>

        <h3>5. Data Retention</h3>
        <p>We retain your personal information for as long as your account remains active or as necessary to provide our services. You may request deletion of your account and associated data at any time by contacting our support team.</p>

        <h3>6. Your Rights</h3>
        <p>You have the right to:</p>
        <ul>
          <li>Access the personal data we hold about you</li>
          <li>Request correction of inaccurate or incomplete information</li>
          <li>Request deletion of your personal data, subject to legal obligations</li>
          <li>Withdraw consent to data processing at any time (note: this may affect your ability to use certain services)</li>
        </ul>

        <h3>7. Data Security</h3>
        <p>We implement appropriate technical and organizational security measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the internet is 100% secure, and we cannot guarantee absolute security.</p>

        <h3>8. Communications and Notifications</h3>
        <p>By providing your email address and mobile number, you consent to receive:</p>
        <ul>
          <li>Email communications including verification codes and account-related notices</li>
          <li>SMS notifications regarding order status, pick-up dates, time slots, and location information</li>
        </ul>
        <p>You may opt out of non-essential communications at any time by contacting us.</p>

        <h3>9. Eligibility</h3>
        <p>Our services are intended for users who are 18 years of age or older. We do not knowingly collect personal information from minors. If we become aware that a minor has provided us with personal data, we will take steps to delete such information.</p>

        <h3>10. Changes to This Policy</h3>
        <p>We may update this Privacy Policy from time to time to reflect changes in our services or applicable laws. We will notify you of significant changes via email or through a notice on our platform. Your continued use of Personal Shopper following any changes constitutes your acceptance of the updated policy.</p>

        <h3>11. Contact Us</h3>
        <p>If you have any questions, concerns, or requests regarding this Privacy Policy or your personal data, please contact us at: <strong>personalshoppersystem@gmail.com</strong></p>
      </div>
      <div class="modal-footer">
        <div class="modal-read-status" id="privacyReadStatus">Scroll to read</div>
        <div style="width:150px;height:4px;background:var(--border);border-radius:2px;overflow:hidden;">
          <div id="privacyProgressBar" style="width:0%;height:100%;background:var(--blue);transition:width .3s ease;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var currentStep = 1;
var registeredUserId = null;
var registeredEmail  = '';
var LETTERS_ONLY = /^[a-zA-Z\s\-'\.]+$/;
var termsRead = false;
var privacyRead = false;

function v(id) { return document.getElementById(id); }

// Modal Functions
function openModal(modalId) {
  v(modalId).classList.add('show');
  document.body.style.overflow = 'hidden';
  
  // Add scroll listener when modal opens
  setTimeout(function() {
    var modal = v(modalId);
    var body = modal.querySelector('.modal-body');
    if (body) {
      body.addEventListener('scroll', function() { handleModalScroll(modalId); });
    }
  }, 100);
}

function closeModal(modalId) {
  v(modalId).classList.remove('show');
  document.body.style.overflow = 'auto';
}

function handleModalScroll(modalId) {
  var modal = v(modalId);
  var body = modal.querySelector('.modal-body');
  
  if (!body) return;
  
  var scrollProgress = (body.scrollTop / (body.scrollHeight - body.clientHeight)) * 100;
  scrollProgress = Math.min(scrollProgress, 100);
  
  var type = modalId === 'termsModal' ? 'terms' : 'privacy';
  var progressBar = v(type === 'terms' ? 'termsProgressBar' : 'privacyProgressBar');
  var statusEl = v(type === 'terms' ? 'termsReadStatus' : 'privacyReadStatus');
  
  progressBar.style.width = scrollProgress + '%';
  
  // Mark as read when scrolled to 95% or bottom
  if (scrollProgress >= 95) {
    if (type === 'terms' && !termsRead) {
      termsRead = true;
      statusEl.textContent = '✓ Read';
      statusEl.classList.add('complete');
      updateCheckboxState();
    } else if (type === 'privacy' && !privacyRead) {
      privacyRead = true;
      statusEl.textContent = '✓ Read';
      statusEl.classList.add('complete');
      updateCheckboxState();
    }
  }
}

function updateCheckboxState() {
  var termsCheckbox = v('terms');
  if (termsRead && privacyRead) {
    termsCheckbox.disabled = false;
  } else {
    termsCheckbox.disabled = true;
    termsCheckbox.checked = false;
  }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
  var termsModal = v('termsModal');
  var privacyModal = v('privacyModal');
  if (event.target === termsModal) {
    closeModal('termsModal');
  }
  if (event.target === privacyModal) {
    closeModal('privacyModal');
  }
}

function showErr(id, msg) {
  var el = v('err-' + id); if (!el) return;
  el.textContent = msg; el.classList.toggle('show', !!msg);
  var inp = v(id); if (inp) inp.classList.toggle('invalid', !!msg);
}
function clearErr(id) { showErr(id, ''); }

function togglePw(id, btn) {
  var inp = v(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.innerHTML = inp.type === 'password'
    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
}

v('password').addEventListener('input', function() {
  var p = this.value, score = 0;
  if (p.length >= 8) score++;
  if (/[A-Z]/.test(p)) score++;
  if (/[a-z]/.test(p)) score++;
  if (/[0-9]/.test(p)) score++;
  var cols = ['','#e03e3e','#f97316','#eab308','#1aab6d'];
  var labs = ['','Weak','Fair','Good','Strong'];
  ['sb1','sb2','sb3','sb4'].forEach(function(id, i) {
    v(id).style.background = i < score ? cols[score] : 'var(--border)';
  });
  var lbl = v('strength-label');
  lbl.textContent = p.length ? labs[score] : '';
  lbl.style.color = cols[score];
});

['firstname','middlename','surname'].forEach(function(fid) {
  v(fid).addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z\s\-'\.]/g, ''); });
  v(fid).addEventListener('keypress', function(e) {
    if (!/[a-zA-Z\s\-'\.]/.test(String.fromCharCode(e.which))) e.preventDefault();
  });
});
v('mobile').addEventListener('input', function() {
  var val = this.value.replace(/\D/g, '');
  this.value = val.slice(0, 11);
});

function validateStep(n) {
  if (n === 1) {
    var ok = true;
    ['firstname','middlename','surname','mobile'].forEach(clearErr);
    var fn = v('firstname').value.trim(), mn = v('middlename').value.trim();
    var sn = v('surname').value.trim(), m = v('mobile').value.trim();
    if (!fn)             { showErr('firstname','First name is required.'); ok = false; }
    else if (fn.length > 50) { showErr('firstname','First name must not exceed 50 characters.'); ok = false; }
    else if (!LETTERS_ONLY.test(fn)) { showErr('firstname','First name can only contain letters.'); ok = false; }
    if (mn && !LETTERS_ONLY.test(mn)) { showErr('middlename','Middle name can only contain letters.'); ok = false; }
    else if (mn.length > 50)  { showErr('middlename','Middle name must not exceed 50 characters.'); ok = false; }
    if (!sn)             { showErr('surname','Surname is required.'); ok = false; }
    else if (sn.length > 50)  { showErr('surname','Surname must not exceed 50 characters.'); ok = false; }
    else if (!LETTERS_ONLY.test(sn)) { showErr('surname','Surname can only contain letters.'); ok = false; }
    if (!m || !/^09\d{9}$/.test(m)) { showErr('mobile','Enter valid mobile (09xx xxx xxxx).'); ok = false; }
    return ok;
  }
  if (n === 2) {
    var ok = true;
    var u = v('username').value.trim(), e = v('email').value.trim();
    var p = v('password').value, c = v('confirm_password').value;
    ['username','email','password','confirm'].forEach(clearErr);
    if (u.length < 3)    { showErr('username','Username must be at least 3 characters.'); ok = false; }
    else if (u.length > 20)  { showErr('username','Username must not exceed 20 characters.'); ok = false; }
    else if (!/^[a-zA-Z0-9_]+$/.test(u)) { showErr('username','Letters, numbers and underscores only.'); ok = false; }
    if (!e)              { showErr('email','Email address is required.'); ok = false; }
    else if (e.length > 254) { showErr('email','Email must not exceed 254 characters.'); ok = false; }
    else if (!/^[^\s@]+@[^\s@]+\.com$/.test(e)) { showErr('email','Enter valid email (must end with .com).'); ok = false; }
    if (p.length < 8)   { showErr('password','At least 8 characters required.'); ok = false; }
    else if (p.length > 20)  { showErr('password','Password must not exceed 20 characters.'); ok = false; }
    else if (!/[A-Z]/.test(p)) { showErr('password','Needs at least one uppercase letter (A-Z).'); ok = false; }
    else if (!/[a-z]/.test(p)) { showErr('password','Needs at least one lowercase letter (a-z).'); ok = false; }
    else if (!/[0-9]/.test(p)) { showErr('password','Needs at least one number (0-9).'); ok = false; }
    if (ok && p !== c)  { showErr('confirm','Passwords do not match.'); ok = false; }
    return ok;
  }
  return true;
}

function updateUI(to) {
  document.querySelectorAll('.step-item').forEach(function(el) {
    var s = parseInt(el.dataset.step);
    el.classList.remove('active','done');
    if (s === to) el.classList.add('active');
    else if (s < to) el.classList.add('done');
  });
}
function goStep(from) {
  if (!validateStep(from)) return;
  if (from === 2) buildReview();
  v('panel-' + from).classList.remove('active');
  v('panel-' + (from + 1)).classList.add('active');
  currentStep = from + 1;
  updateUI(currentStep);
  v('global-alert').classList.remove('show');
}
function prevStep(from) {
  v('panel-' + from).classList.remove('active');
  v('panel-' + (from - 1)).classList.add('active');
  currentStep = from - 1;
  updateUI(currentStep);
}
function buildReview() {
  var fn = v('firstname').value.trim(), mn = v('middlename').value.trim();
  var sn = v('surname').value.trim(), sf = v('suffix').value;
  var fullname = [fn, mn, sn, sf].filter(Boolean).join(' ');
  var rows = [
    ['Full Name', fullname],
    ['Mobile',    v('mobile').value],
    ['Username',  v('username').value],
    ['Email',     v('email').value],
  ];
  v('review-box').innerHTML = rows.map(function(r) {
    return '<span class="review-label">' + r[0] + '</span><strong>' + (r[1] || '&mdash;') + '</strong>';
  }).join('<br>');
}

function submitForm() {
  var termsEl  = v('terms');
  var errTerms = v('err-terms');
  if (!termsEl.checked) {
    errTerms.textContent = 'You must agree to the Terms and Conditions.';
    errTerms.classList.add('show');
    return;
  }
  errTerms.classList.remove('show');
  var btn = v('submit-btn');
  btn.disabled = true;
  btn.textContent = 'Creating account\u2026';

  var fd = new FormData();
  fd.append('username',         v('username').value.trim());
  fd.append('email',            v('email').value.trim());
  fd.append('password',         v('password').value);
  fd.append('confirm_password', v('confirm_password').value);
  fd.append('firstname',        v('firstname').value.trim());
  fd.append('middlename',       v('middlename').value.trim());
  fd.append('surname',          v('surname').value.trim());
  fd.append('suffix',           v('suffix').value);
  fd.append('mobile',           v('mobile').value.trim());
  fd.append('terms',            '1');

  fetch(window.location.href, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.success) {
        registeredUserId = res.user_id;
        registeredEmail  = v('email').value.trim();
        v('form-area').style.display = 'none';
        v('login-link-row').style.display = 'none';
        v('registered-email').textContent = registeredEmail;
        v('success-screen').style.display = 'block';
        startResendCooldown(30);
      } else {
        var al = v('global-alert');
        var msgs = res.errors ? res.errors : [res.message];
        al.innerHTML = msgs.map(function(m){ return '&bull; ' + m; }).join('<br>');
        al.classList.add('show');
        btn.disabled = false;
        btn.textContent = 'Create Account';
        if (res.errors) {
          var joined = res.errors.join(' ').toLowerCase();
          if (joined.includes('username') || joined.includes('email')) {
            prevStep(3); prevStep(2);
          }
        }
      }
    })
    .catch(function() {
      v('global-alert').innerHTML = '&bull; Something went wrong. Please try again.';
      v('global-alert').classList.add('show');
      btn.disabled = false;
      btn.textContent = 'Create Account';
    });
}

var resendTimer = null;

function startResendCooldown(seconds) {
  var btn = v('resend-btn');
  var cd  = v('countdown');
  btn.disabled = true;
  clearInterval(resendTimer);
  resendTimer = setInterval(function() {
    seconds--;
    var m = Math.floor(seconds / 60);
    var s = seconds % 60;
    cd.textContent = 'Resend available in ' + m + ':' + String(s).padStart(2,'0');
    if (seconds <= 0) {
      clearInterval(resendTimer);
      btn.disabled = false;
      cd.textContent = '';
    }
  }, 1000);
}

function resendEmail() {
  if (!registeredUserId) return;
  var btn = v('resend-btn');
  btn.disabled = true;
  btn.textContent = 'Sending\u2026';

  fetch('../core/customer/resend_verification.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: registeredUserId })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    btn.textContent = 'Resend Activation Email';
    var cd = v('countdown');
    if (res.success) {
      cd.textContent = '✅ Email sent! Check your inbox.';
      cd.style.color = 'var(--success)';
      setTimeout(function() { cd.style.color = ''; }, 3000);
    } else {
      cd.textContent = res.message || 'Failed to send. Try again.';
    }
    startResendCooldown(30);
  })
  .catch(function() {
    btn.textContent = 'Resend Activation Email';
    btn.disabled = false;
    v('countdown').textContent = 'Network error. Try again.';
  });
}
</script>
</body>
</html>