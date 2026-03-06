<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}
session_start();

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
    $addr_label = trim($_POST['address_label'] ?? 'Home');
    $region     = trim($_POST['region'] ?? '');
    $province   = trim($_POST['province'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $barangay   = trim($_POST['barangay'] ?? '');
    $block_no   = trim($_POST['block_no'] ?? '');
    $lot_no     = trim($_POST['lot_no'] ?? '');
    $postal     = trim($_POST['postal_code'] ?? '');
    $terms      = isset($_POST['terms']) ? 1 : 0;

    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
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

    if (empty($firstname)) $errors[] = 'First name is required.';
    if (empty($surname))   $errors[] = 'Surname is required.';

    if (empty($mobile) || !preg_match('/^[0-9+\-\s]{7,15}$/', $mobile)) {
        $errors[] = 'Please enter a valid mobile number.';
    }

    if (empty($region) || empty($province) || empty($city) || empty($barangay)) {
        $errors[] = 'Please fill in all required address fields.';
    }

    if (!$terms) {
        $errors[] = 'You must agree to the Terms and Conditions.';
    }

    if (empty($errors)) {
        $stmtU = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
        $stmtU->bind_param('s', $username);
        $stmtU->execute();
        $stmtU->store_result();
        if ($stmtU->num_rows > 0) {
            $errors[] = 'Username is already taken.';
        }
        $stmtU->close();

        $stmtE = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmtE->bind_param('s', $email);
        $stmtE->execute();
        $stmtE->store_result();
        if ($stmtE->num_rows > 0) {
            $errors[] = 'Email address is already registered.';
        }
        $stmtE->close();
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            $stmtUser = $conn->prepare('INSERT INTO users (username, password, email, role, terms_agreed, status, created_at, updated_at) VALUES (?, ?, ?, "customer", ?, "active", NOW(), NOW())');
            $stmtUser->bind_param('sssi', $username, $hashed, $email, $terms);
            $stmtUser->execute();
            $userId = $conn->insert_id;
            $stmtUser->close();

            $mn = $middlename ?: null;
            $sf = $suffix ?: null;
            $stmtProfile = $conn->prepare('INSERT INTO user_profiles (user_id, firstname, middlename, surname, suffix, mobile) VALUES (?, ?, ?, ?, ?, ?)');
            $stmtProfile->bind_param('isssss', $userId, $firstname, $mn, $surname, $sf, $mobile);
            $stmtProfile->execute();
            $stmtProfile->close();

            $bn = $block_no ?: null;
            $ln = $lot_no ?: null;
            $pc = $postal ?: null;
            $stmtAddr = $conn->prepare('INSERT INTO user_addresses (user_id, address_label, region, province, city, barangay, block_no, lot_no, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmtAddr->bind_param('issssssss', $userId, $addr_label, $region, $province, $city, $barangay, $bn, $ln, $pc);
            $stmtAddr->execute();
            $stmtAddr->close();

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
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
.brand h1 {
  font-family: 'DM Serif Display', serif;
  font-size: 2rem;
  color: var(--blue-dark);
  letter-spacing: -.5px;
}
.brand p { color: var(--muted); font-size: .95rem; margin-top: 4px; }

.card {
  background: var(--card);
  border-radius: 20px;
  box-shadow: 0 4px 40px rgba(45,91,227,.10), 0 1px 4px rgba(0,0,0,.06);
  padding: 36px 40px 40px;
}

.steps {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  margin-bottom: 30px;
}
.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  flex: 1;
  position: relative;
}
.step-item:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 17px;
  left: calc(50% + 18px);
  right: calc(-50% + 18px);
  height: 2px;
  background: var(--border);
  transition: background .35s;
  z-index: 0;
}
.step-item.done:not(:last-child)::after { background: var(--success); }

.step-circle {
  width: 35px; height: 35px;
  border-radius: 50%;
  border: 2px solid var(--border);
  background: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: .84rem; font-weight: 600; color: var(--muted);
  transition: all .3s;
  position: relative; z-index: 1;
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
.field label {
  display: block; font-size: .82rem; font-weight: 500;
  color: var(--text); margin-bottom: 5px;
}
.req { color: var(--blue); margin-left: 2px; }

.field input, .field select {
  width: 100%;
  padding: 10px 13px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: .93rem;
  font-family: 'DM Sans', sans-serif;
  color: var(--text);
  background: #fafbff;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  appearance: none;
}
.field input:focus, .field select:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(45,91,227,.11);
  background: #fff;
}
.field input.invalid { border-color: var(--error); }
.err-msg { font-size: .77rem; color: var(--error); margin-top: 4px; display: none; }
.err-msg.show { display: block; }

.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 42px; }
.pw-toggle {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: var(--muted);
  font-size: .95rem; padding: 0; line-height: 1;
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
.alert-error { background: #fff0f0; border: 1.5px solid #f5b8b8; color: var(--error); }

.field select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='7' viewBox='0 0 11 7'%3E%3Cpath d='M1 1l4.5 4.5L10 1' stroke='%236b7194' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 13px center;
  padding-right: 34px;
}

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
#success-screen p  { color: var(--muted); font-size: .91rem; margin-bottom: 22px; }

.login-link { text-align: center; margin-top: 16px; font-size: .87rem; color: var(--muted); }
.login-link a { color: var(--blue); text-decoration: none; font-weight: 500; }
.login-link a:hover { text-decoration: underline; }

@media (max-width: 480px) {
  .card { padding: 26px 20px 30px; }
  .form-row { flex-direction: column; gap: 0; }
  .step-label { font-size: 0; }
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
          <div class="step-label">Account</div>
        </div>
        <div class="step-item" data-step="2">
          <div class="step-circle"><span class="step-num">2</span></div>
          <div class="step-label">Personal</div>
        </div>
        <div class="step-item" data-step="3">
          <div class="step-circle"><span class="step-num">3</span></div>
          <div class="step-label">Address</div>
        </div>
        <div class="step-item" data-step="4">
          <div class="step-circle"><span class="step-num">4</span></div>
          <div class="step-label">Confirm</div>
        </div>
      </div>

      <div id="global-alert" class="alert alert-error"></div>

      <div class="panel active" id="panel-1">
        <div class="panel-title">Create your account</div>
        <div class="field">
          <label>Username <span class="req">*</span></label>
          <input type="text" id="username" autocomplete="username" placeholder="e.g. john_doe">
          <div class="err-msg" id="err-username"></div>
        </div>
        <div class="field">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" id="email" autocomplete="email" placeholder="you@example.com">
          <div class="err-msg" id="err-email"></div>
        </div>
        <div class="field">
          <label>Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="password" autocomplete="new-password" placeholder="Min. 8 characters">
            <button type="button" class="pw-toggle" onclick="togglePw('password',this)">👁</button>
          </div>
          <div class="strength-bar">
            <span id="sb1"></span><span id="sb2"></span><span id="sb3"></span><span id="sb4"></span>
          </div>
          <div class="strength-label" id="strength-label"></div>
          <div class="err-msg" id="err-password"></div>
        </div>
        <div class="field">
          <label>Confirm Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="confirm_password" autocomplete="new-password" placeholder="Re-enter your password">
            <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)">👁</button>
          </div>
          <div class="err-msg" id="err-confirm"></div>
        </div>
        <div class="btn-row">
          <button class="btn btn-primary" onclick="goStep(1)">Continue &rarr;</button>
        </div>
      </div>

      <div class="panel" id="panel-2">
        <div class="panel-title">Personal information</div>
        <div class="form-row">
          <div class="field">
            <label>First Name <span class="req">*</span></label>
            <input type="text" id="firstname" placeholder="First name">
            <div class="err-msg" id="err-firstname"></div>
          </div>
          <div class="field">
            <label>Middle Name</label>
            <input type="text" id="middlename" placeholder="Optional">
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label>Surname <span class="req">*</span></label>
            <input type="text" id="surname" placeholder="Last name">
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
          <input type="tel" id="mobile" placeholder="+63 9XX XXX XXXX">
          <div class="err-msg" id="err-mobile"></div>
        </div>
        <div class="btn-row">
          <button class="btn btn-outline" onclick="prevStep(2)">&larr; Back</button>
          <button class="btn btn-primary" onclick="goStep(2)">Continue &rarr;</button>
        </div>
      </div>

      <div class="panel" id="panel-3">
        <div class="panel-title">Delivery address</div>
        <div class="form-row">
          <div class="field">
            <label>Label</label>
            <select id="address_label">
              <option value="Home">Home</option>
              <option value="Work">Work</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="field">
            <label>Region <span class="req">*</span></label>
            <input type="text" id="region" placeholder="e.g. NCR">
            <div class="err-msg" id="err-region"></div>
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label>Province <span class="req">*</span></label>
            <input type="text" id="province" placeholder="e.g. Metro Manila">
            <div class="err-msg" id="err-province"></div>
          </div>
          <div class="field">
            <label>City <span class="req">*</span></label>
            <input type="text" id="city" placeholder="e.g. Caloocan City">
            <div class="err-msg" id="err-city"></div>
          </div>
        </div>
        <div class="field">
          <label>Barangay <span class="req">*</span></label>
          <input type="text" id="barangay" placeholder="Barangay name or number">
          <div class="err-msg" id="err-barangay"></div>
        </div>
        <div class="form-row">
          <div class="field">
            <label>Block No.</label>
            <input type="text" id="block_no" placeholder="Block 1">
          </div>
          <div class="field">
            <label>Lot No.</label>
            <input type="text" id="lot_no" placeholder="Lot 24">
          </div>
          <div class="field">
            <label>Postal Code</label>
            <input type="text" id="postal_code" placeholder="1428" maxlength="10">
          </div>
        </div>
        <div class="btn-row">
          <button class="btn btn-outline" onclick="prevStep(3)">&larr; Back</button>
          <button class="btn btn-primary" onclick="goStep(3)">Continue &rarr;</button>
        </div>
      </div>

      <div class="panel" id="panel-4">
        <div class="panel-title">Review &amp; confirm</div>
        <div class="review-box" id="review-box"></div>
        <div class="terms-box">
          <strong>Terms &amp; Conditions</strong><br>
          By creating an account, you agree to our Terms of Service and Privacy Policy. You consent to the collection and use of your personal data for order processing, account management, and personalised shopping assistance. Your data will not be sold to third parties. You must be at least 18 years old to register. Personal Shopper reserves the right to suspend accounts that violate our usage policies.
        </div>
        <div class="check-row">
          <input type="checkbox" id="terms">
          <label for="terms">I have read and agree to the <strong>Terms &amp; Conditions</strong> and <strong>Privacy Policy</strong>.</label>
        </div>
        <div class="err-msg" id="err-terms" style="margin-bottom:10px;"></div>
        <div class="btn-row">
          <button class="btn btn-outline" onclick="prevStep(4)">&larr; Back</button>
          <button class="btn btn-primary" id="submit-btn" onclick="submitForm()">Create Account</button>
        </div>
      </div>
    </div>

    <div id="success-screen">
      <div class="success-icon">✓</div>
      <h2>Welcome aboard!</h2>
      <p>Your account has been created successfully.<br>You can now log in and start shopping.</p>
      <a href="login.php" class="btn btn-primary" style="display:block;text-decoration:none;">Go to Login</a>
    </div>

    <div class="login-link" id="login-link-row">
      Already have an account? <a href="logout.php">Sign in</a>
    </div>
  </div>
</div>

<script>
var currentStep = 1;

function v(id) { return document.getElementById(id); }

function showErr(id, msg) {
  var el = v('err-' + id);
  if (!el) return;
  el.textContent = msg;
  el.classList.toggle('show', !!msg);
  var inp = v(id);
  if (inp) inp.classList.toggle('invalid', !!msg);
}
function clearErr(id) { showErr(id, ''); }

function togglePw(id, btn) {
  var inp = v(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

v('password').addEventListener('input', function() {
  var p = this.value, score = 0;
  if (p.length >= 8) score++;
  if (/[A-Z]/.test(p)) score++;
  if (/[a-z]/.test(p)) score++;
  if (/[0-9]/.test(p)) score++;
  var cols = ['', '#e03e3e', '#f97316', '#eab308', '#1aab6d'];
  var labs = ['', 'Weak', 'Fair', 'Good', 'Strong'];
  ['sb1','sb2','sb3','sb4'].forEach(function(id, i) {
    v(id).style.background = i < score ? cols[score] : 'var(--border)';
  });
  var lbl = v('strength-label');
  lbl.textContent = p.length ? labs[score] : '';
  lbl.style.color = cols[score];
});

function validateStep(n) {
  if (n === 1) {
    var ok = true;
    var u = v('username').value.trim();
    var e = v('email').value.trim();
    var p = v('password').value;
    var c = v('confirm_password').value;
    ['username','email','password','confirm'].forEach(clearErr);

    if (u.length < 3) { showErr('username', 'Username must be at least 3 characters.'); ok = false; }
    else if (!/^[a-zA-Z0-9_]+$/.test(u)) { showErr('username', 'Letters, numbers and underscores only.'); ok = false; }

    if (!e || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) { showErr('email', 'Enter a valid email address.'); ok = false; }

    if (p.length < 8) { showErr('password', 'At least 8 characters required.'); ok = false; }
    else if (!/[A-Z]/.test(p)) { showErr('password', 'Needs at least one uppercase letter (A-Z).'); ok = false; }
    else if (!/[a-z]/.test(p)) { showErr('password', 'Needs at least one lowercase letter (a-z).'); ok = false; }
    else if (!/[0-9]/.test(p)) { showErr('password', 'Needs at least one number (0-9).'); ok = false; }

    if (ok && p !== c) { showErr('confirm', 'Passwords do not match.'); ok = false; }
    return ok;
  }
  if (n === 2) {
    var ok = true;
    ['firstname','surname','mobile'].forEach(clearErr);
    if (!v('firstname').value.trim()) { showErr('firstname', 'First name is required.'); ok = false; }
    if (!v('surname').value.trim())   { showErr('surname',   'Surname is required.'); ok = false; }
    var m = v('mobile').value.trim();
    if (!m || !/^[0-9+\-\s]{7,15}$/.test(m)) { showErr('mobile', 'Enter a valid mobile number.'); ok = false; }
    return ok;
  }
  if (n === 3) {
    var ok = true;
    ['region','province','city','barangay'].forEach(function(f) {
      clearErr(f);
      if (!v(f).value.trim()) { showErr(f, 'This field is required.'); ok = false; }
    });
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
  if (from === 3) buildReview();
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
  var fn = v('firstname').value.trim();
  var mn = v('middlename').value.trim();
  var sn = v('surname').value.trim();
  var sf = v('suffix').value;
  var fullname = [fn, mn, sn, sf].filter(Boolean).join(' ');
  var rows = [
    ['Username',       v('username').value],
    ['Email',          v('email').value],
    ['Full Name',      fullname],
    ['Mobile',         v('mobile').value],
    ['Address Label',  v('address_label').value],
    ['Region',         v('region').value],
    ['Province',       v('province').value],
    ['City',           v('city').value],
    ['Barangay',       v('barangay').value],
  ];
  var extras = [v('block_no').value, v('lot_no').value, v('postal_code').value].filter(Boolean).join(' / ');
  if (extras) rows.push(['Block/Lot/Postal', extras]);
  v('review-box').innerHTML = rows.map(function(r) {
    return '<span class="review-label">' + r[0] + '</span><strong>' + (r[1] || '&mdash;') + '</strong>';
  }).join('<br>');
}

function submitForm() {
  var termsEl = v('terms');
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
  fd.append('address_label',    v('address_label').value);
  fd.append('region',           v('region').value.trim());
  fd.append('province',         v('province').value.trim());
  fd.append('city',             v('city').value.trim());
  fd.append('barangay',         v('barangay').value.trim());
  fd.append('block_no',         v('block_no').value.trim());
  fd.append('lot_no',           v('lot_no').value.trim());
  fd.append('postal_code',      v('postal_code').value.trim());
  fd.append('terms',            '1');

  fetch(window.location.href, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.success) {
        v('form-area').style.display = 'none';
        v('login-link-row').style.display = 'none';
        v('success-screen').style.display = 'block';
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
            prevStep(4); prevStep(3); prevStep(2);
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
</script>
</body>
</html>