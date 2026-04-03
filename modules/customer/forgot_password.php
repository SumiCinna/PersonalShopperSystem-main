<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password – PSS</title>
  <link rel="stylesheet" href="assets/css/style.css"> <!-- your existing CSS -->
  <style>
    /* Minimal scoped styles – blend with your existing design */
    body { background: #eef2fb; font-family: 'Segoe UI', Arial, sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .fp-card { background:#fff; border-radius:18px; box-shadow:0 4px 30px rgba(45,91,227,.10); max-width:420px; width:100%; padding:0; overflow:hidden; }
    .fp-header { background:linear-gradient(135deg,#2d5be3 0%,#1e3fa8 100%); padding:32px 36px 26px; text-align:center; }
    .fp-header .icon { display:inline-block; background:rgba(255,255,255,.15); border-radius:50%; width:56px; height:56px; line-height:56px; font-size:26px; margin-bottom:12px; }
    .fp-header h1 { margin:0; color:#fff; font-size:20px; font-weight:700; }
    .fp-header p  { margin:5px 0 0; color:rgba(255,255,255,.75); font-size:12.5px; }
    .fp-body { padding:32px 36px 28px; }
    .fp-body h2 { margin:0 0 6px; font-size:18px; color:#1a1d2e; font-weight:700; }
    .fp-body .sub { margin:0 0 22px; color:#6b7194; font-size:13.5px; line-height:1.65; }
    .fp-body label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
    .fp-body input[type=email] {
      width:100%; box-sizing:border-box; padding:11px 14px; border:1.5px solid #d1d5db;
      border-radius:9px; font-size:14px; outline:none; transition:border .2s;
    }
    .fp-body input[type=email]:focus { border-color:#2d5be3; }
    .fp-body .btn-reset {
      display:block; width:100%; margin-top:20px; padding:13px;
      background:#2d5be3; color:#fff; border:none; border-radius:10px;
      font-size:15px; font-weight:700; cursor:pointer; transition:background .2s;
    }
    .fp-body .btn-reset:hover { background:#1e3fa8; }
    .fp-body .btn-reset:disabled { background:#93a5d8; cursor:not-allowed; }
    .fp-body .back-link { display:block; text-align:center; margin-top:16px; font-size:13px; color:#6b7194; text-decoration:none; }
    .fp-body .back-link:hover { color:#2d5be3; }
    .alert { padding:11px 14px; border-radius:9px; font-size:13px; margin-bottom:16px; }
    .alert-success { background:#ecfdf5; border:1.5px solid #6ee7b7; color:#065f46; }
    .alert-error   { background:#fef2f2; border:1.5px solid #fca5a5; color:#991b1b; }
    .spinner { display:none; width:18px; height:18px; border:2.5px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; margin:0 auto; }
    @keyframes spin { to { transform:rotate(360deg); } }
  </style>
</head>
<body>

<?php
session_start();
$success = $_SESSION['fp_success'] ?? '';
$error   = $_SESSION['fp_error']   ?? '';
unset($_SESSION['fp_success'], $_SESSION['fp_error']);
?>

<div class="fp-card">
  <!-- Header -->
  <div class="fp-header">
    <div class="icon">🛒</div>
    <h1>Personal Shopper System</h1>
    <p>Customer Portal</p>
  </div>

  <!-- Body -->
  <div class="fp-body">
    <h2>Forgot Password?</h2>
    <p class="sub">Enter your registered email address and we'll send you a link to reset your password.</p>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

  <form id="fpForm" action="../../auth/forgot_password.php" method="POST">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" placeholder="you@example.com" required>

      <button type="submit" class="btn-reset" id="submitBtn">
        <span id="btnText">Send Reset Link</span>
        <div class="spinner" id="spinner"></div>
      </button>
    </form>

  <a href="../../customer-login.php" class="back-link">← Back to Login</a>
  </div>
</div>

<script>
  document.getElementById('fpForm').addEventListener('submit', function () {
    document.getElementById('btnText').style.display = 'none';
    document.getElementById('spinner').style.display = 'block';
    document.getElementById('submitBtn').disabled = true;
  });
</script>

</body>
</html>