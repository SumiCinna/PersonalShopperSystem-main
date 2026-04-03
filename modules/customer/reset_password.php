<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password – PSS</title>
  <style>
    body { background:#eef2fb; font-family:'Segoe UI',Arial,sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .rp-card { background:#fff; border-radius:18px; box-shadow:0 4px 30px rgba(45,91,227,.10); max-width:420px; width:100%; overflow:hidden; }
    .rp-header { background:linear-gradient(135deg,#2d5be3 0%,#1e3fa8 100%); padding:32px 36px 26px; text-align:center; }
    .rp-header .icon { display:inline-block; background:rgba(255,255,255,.15); border-radius:50%; width:56px; height:56px; line-height:56px; font-size:26px; margin-bottom:12px; }
    .rp-header h1 { margin:0; color:#fff; font-size:20px; font-weight:700; }
    .rp-header p  { margin:5px 0 0; color:rgba(255,255,255,.75); font-size:12.5px; }
    .rp-body { padding:32px 36px 28px; }
    .rp-body h2 { margin:0 0 6px; font-size:18px; color:#1a1d2e; font-weight:700; }
    .rp-body .sub { margin:0 0 22px; color:#6b7194; font-size:13.5px; line-height:1.65; }
    .rp-body label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; margin-top:14px; }
    .pw-wrap { position:relative; }
    .pw-wrap input[type=password], .pw-wrap input[type=text] {
      width:100%; box-sizing:border-box; padding:11px 42px 11px 14px;
      border:1.5px solid #d1d5db; border-radius:9px; font-size:14px;
      outline:none; transition:border .2s;
    }
    .pw-wrap input:focus { border-color:#2d5be3; }
    .pw-toggle {
      position:absolute; right:12px; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer; color:#9ca3af; padding:0;
      display:flex; align-items:center; justify-content:center;
      transition:color .2s;
    }
    .pw-toggle:hover { color:#2d5be3; }
    .rp-body .btn { display:block; width:100%; margin-top:22px; padding:13px; background:#2d5be3; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; transition:background .2s; }
    .rp-body .btn:hover { background:#1e3fa8; }
    .rp-body .btn:disabled { background:#93a5d8; cursor:not-allowed; }
    .rp-body .back-link { display:block; text-align:center; margin-top:16px; font-size:13px; color:#6b7194; text-decoration:none; }
    .rp-body .back-link:hover { color:#2d5be3; }
    .alert { padding:11px 14px; border-radius:9px; font-size:13px; margin-bottom:16px; }
    .alert-success { background:#ecfdf5; border:1.5px solid #6ee7b7; color:#065f46; }
    .alert-error   { background:#fef2f2; border:1.5px solid #fca5a5; color:#991b1b; }
    .strength-bar { height:4px; border-radius:4px; margin-top:6px; background:#e5e7eb; overflow:hidden; }
    .strength-bar span { display:block; height:100%; width:0; transition:width .3s,background .3s; border-radius:4px; }
    .strength-label { font-size:11.5px; color:#9ca3af; margin-top:3px; }

    /* Validation checklist */
    .validations { list-style:none; margin:10px 0 0; padding:0; display:flex; flex-direction:column; gap:5px; }
    .validations li {
      font-size:12.5px; color:#9ca3af; display:flex; align-items:center; gap:7px;
      transition:color .25s;
    }
    .validations li .check-icon { width:16px; height:16px; border-radius:50%; border:1.5px solid #d1d5db; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all .25s; }
    .validations li .check-icon svg { display:none; }
    .validations li.valid { color:#15803d; }
    .validations li.valid .check-icon { background:#15803d; border-color:#15803d; }
    .validations li.valid .check-icon svg { display:block; }
    .validations li.invalid { color:#b91c1c; }
    .validations li.invalid .check-icon { border-color:#b91c1c; }

    .match-msg { font-size:12px; margin-top:5px; }
    .match-ok  { color:#15803d; }
    .match-err { color:#b91c1c; }
  </style>
</head>
<body>
<?php
session_start();
require_once '../../config/config.php';

$token_raw = trim($_GET['token'] ?? '');
$error     = '';
$success   = '';
$valid     = false;
$user_id   = null;

if (empty($token_raw)) {
    $error = 'Invalid or missing reset token.';
} else {
    $token_hash = hash('sha256', $token_raw);
    $now        = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "SELECT pr.reset_id, pr.user_id, pr.expires_at, pr.used
         FROM password_resets pr
         WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > ?"
    );
    $stmt->bind_param('ss', $token_hash, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $valid   = true;
        $user_id = $row['user_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $new_pw     = $_POST['password']         ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';
    $token_raw  = $_POST['token']            ?? '';
    $token_hash = hash('sha256', $token_raw);

    if (strlen($new_pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new_pw)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_pw)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_pw)) {
        $error = 'Password must contain at least one number.';
    } elseif ($new_pw !== $confirm_pw) {
        $error = 'Passwords do not match.';
    } else {
        $now  = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "SELECT pr.reset_id, pr.user_id FROM password_resets pr
             WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > ?"
        );
        $stmt->bind_param('ss', $token_hash, $now);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = 'This reset link has expired. Please request a new one.';
            $valid = false;
        } else {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);

            $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $upd->bind_param('si', $hashed, $row['user_id']);
            $upd->execute();
            $upd->close();

            $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE reset_id = ?");
            $mark->bind_param('i', $row['reset_id']);
            $mark->execute();
            $mark->close();

            $success = 'Your password has been reset successfully! You can now log in.';
            $valid   = false;
        }
    }
}
?>

<div class="rp-card">
  <div class="rp-header">
    <div class="icon">🛒</div>
    <h1>Personal Shopper System</h1>
    <p>Customer Portal</p>
  </div>

  <div class="rp-body">
    <h2>Set New Password</h2>
    <p class="sub">Choose a strong password for your account.</p>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <a href="../../customer-login.php" class="btn" style="text-align:center;text-decoration:none;display:block;">Go to Login</a>

    <?php elseif ($error && !$valid): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <a href="forgot_password.php" class="btn" style="text-align:center;text-decoration:none;display:block;">Request New Link</a>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="reset_password.php?token=<?= urlencode($token_raw) ?>" id="rpForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token_raw) ?>">

        <label for="password">New Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" placeholder="At least 8 characters" required>
          <button type="button" class="pw-toggle" onclick="togglePw('password', this)" tabindex="-1">
            <svg id="eye-pw" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>

        <!-- Strength bar -->
        <div class="strength-bar"><span id="strengthBar"></span></div>
        <div class="strength-label" id="strengthText"></div>

        <!-- Validation checklist -->
        <ul class="validations">
          <li id="v-length">
            <span class="check-icon"><svg width="9" height="9" viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2"><polyline points="1,5 4,8 9,2"/></svg></span>
            At least 8 characters
          </li>
          <li id="v-upper">
            <span class="check-icon"><svg width="9" height="9" viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2"><polyline points="1,5 4,8 9,2"/></svg></span>
            At least 1 uppercase letter (A-Z)
          </li>
          <li id="v-lower">
            <span class="check-icon"><svg width="9" height="9" viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2"><polyline points="1,5 4,8 9,2"/></svg></span>
            At least 1 lowercase letter (a-z)
          </li>
          <li id="v-number">
            <span class="check-icon"><svg width="9" height="9" viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2"><polyline points="1,5 4,8 9,2"/></svg></span>
            At least 1 number (0-9)
          </li>
        </ul>

        <label for="confirm_password" style="margin-top:18px;">Confirm New Password</label>
        <div class="pw-wrap">
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your new password" required>
          <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)" tabindex="-1">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="match-msg" id="matchMsg"></div>

        <button type="submit" class="btn" id="submitBtn" disabled>Reset Password</button>
      </form>
    <?php endif; ?>

    <a href="../../customer-login.php" class="back-link">← Back to Login</a>
  </div>
</div>

<script>
  const pwInput      = document.getElementById('password');
  const confirmInput = document.getElementById('confirm_password');
  const bar          = document.getElementById('strengthBar');
  const txt          = document.getElementById('strengthText');
  const matchMsg     = document.getElementById('matchMsg');
  const submitBtn    = document.getElementById('submitBtn');

  const vLength = document.getElementById('v-length');
  const vUpper  = document.getElementById('v-upper');
  const vLower  = document.getElementById('v-lower');
  const vNumber = document.getElementById('v-number');

  function togglePw(id, btn) {
    var inp = document.getElementById(id);
    var isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.innerHTML = isText
      ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
      : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
  }

  function setValid(el, ok) {
    el.classList.toggle('valid',   ok);
    el.classList.toggle('invalid', !ok && pwInput.value.length > 0);
  }

  function checkAll() {
    var v = pwInput.value;
    var okLen    = v.length >= 8;
    var okUpper  = /[A-Z]/.test(v);
    var okLower  = /[a-z]/.test(v);
    var okNumber = /[0-9]/.test(v);

    setValid(vLength, okLen);
    setValid(vUpper,  okUpper);
    setValid(vLower,  okLower);
    setValid(vNumber, okNumber);

    // Strength bar
    var score = [okLen, okUpper, okLower, okNumber].filter(Boolean).length;
    var levels = [
      { w:'25%',  bg:'#ef4444', label:'Weak'   },
      { w:'50%',  bg:'#f97316', label:'Fair'   },
      { w:'75%',  bg:'#eab308', label:'Good'   },
      { w:'100%', bg:'#22c55e', label:'Strong' },
    ];
    var lvl = v.length ? (levels[score - 1] || levels[0]) : { w:'0', bg:'#e5e7eb', label:'' };
    bar.style.width      = lvl.w;
    bar.style.background = lvl.bg;
    txt.textContent      = lvl.label;
    txt.style.color      = lvl.bg;

    checkMatch();
    updateSubmit(okLen && okUpper && okLower && okNumber);
  }

  function checkMatch() {
    var pw  = pwInput.value;
    var cpw = confirmInput.value;
    if (!cpw) { matchMsg.textContent = ''; return; }
    if (pw === cpw) {
      matchMsg.textContent = '✅ Passwords match';
      matchMsg.className   = 'match-msg match-ok';
    } else {
      matchMsg.textContent = '❌ Passwords do not match';
      matchMsg.className   = 'match-msg match-err';
    }
  }

  function updateSubmit(allValid) {
    var pw  = pwInput.value;
    var cpw = confirmInput.value;
    submitBtn.disabled = !(allValid && pw === cpw && cpw.length > 0);
  }

  pwInput.addEventListener('input', checkAll);
  confirmInput.addEventListener('input', function() { checkMatch(); updateSubmit(
    pwInput.value.length >= 8 &&
    /[A-Z]/.test(pwInput.value) &&
    /[a-z]/.test(pwInput.value) &&
    /[0-9]/.test(pwInput.value)
  ); });
</script>
</body>
</html>