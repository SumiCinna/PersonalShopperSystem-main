    <?php
// modules/customer/verify_email.php
session_start();
require_once '../../config/config.php';

$token  = trim($_GET['token'] ?? '');
$status = 'error'; // 'success' | 'expired' | 'already' | 'error'
$message = '';

if (empty($token)) {
    $status  = 'error';
    $message = 'Invalid or missing activation link.';
} else {
    // Look up the token
    $stmt = $conn->prepare("
        SELECT ev.user_id, ev.expires_at, u.email_verified, u.status
        FROM email_verifications ev
        JOIN users u ON u.user_id = ev.user_id
        WHERE ev.token = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $status  = 'error';
        $message = 'This activation link is invalid or has already been used.';
    } elseif ($row['email_verified'] == 1 || $row['status'] === 'active') {
        $status  = 'already';
        $message = 'Your account is already verified. You can log in now.';
    } elseif (strtotime($row['expires_at']) < time()) {
        $status  = 'expired';
        $message = 'This activation link has expired. Please request a new one.';
        // Store user_id in session so the resend page can use it
        $_SESSION['expired_user_id'] = $row['user_id'];
    } else {
        // ── Activate the account ──────────────────────────────────────────────
        $userId = $row['user_id'];
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE users SET email_verified = 1, status = 'active', updated_at = NOW() WHERE user_id = $userId");
            $conn->query("DELETE FROM email_verifications WHERE user_id = $userId");
            $conn->commit();
            $status  = 'success';
            $message = 'Your account has been activated! You can now log in.';
        } catch (Exception $e) {
            $conn->rollback();
            $status  = 'error';
            $message = 'Something went wrong while activating your account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Activation &mdash; Personal Shopper</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'DM Sans', sans-serif;
  background: #eef2fb;
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 32px 16px;
  background-image:
    radial-gradient(ellipse at 15% 15%, rgba(45,91,227,.10) 0%, transparent 55%),
    radial-gradient(ellipse at 85% 85%, rgba(45,91,227,.08) 0%, transparent 55%);
}
.card {
  background: #fff; border-radius: 20px;
  box-shadow: 0 4px 40px rgba(45,91,227,.10), 0 1px 4px rgba(0,0,0,.06);
  padding: 48px 44px; max-width: 460px; width: 100%; text-align: center;
}
.icon {
  width: 80px; height: 80px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 2.2rem; margin: 0 auto 22px;
  animation: pop .4s cubic-bezier(.17,.67,.32,1.4);
}
@keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.icon-success { background: #f0faf6; border: 2px solid #1aab6d; }
.icon-error   { background: #fff0f0; border: 2px solid #e03e3e; }
.icon-expired { background: #fff8f0; border: 2px solid #f97316; }
.icon-already { background: #f0f4ff; border: 2px solid #2d5be3; }
h1 { font-family: 'DM Serif Display', serif; font-size: 1.7rem; color: #1a1d2e; margin-bottom: 10px; }
p  { color: #6b7194; font-size: .95rem; line-height: 1.7; margin-bottom: 28px; }
.btn {
  display: inline-block; padding: 12px 32px; border-radius: 10px;
  font-size: .97rem; font-weight: 700; text-decoration: none;
  font-family: 'DM Sans', sans-serif; transition: all .2s;
  cursor: pointer; border: none;
}
.btn-primary { background: #2d5be3; color: #fff; }
.btn-primary:hover { background: #1e3fa8; transform: translateY(-1px); }
.btn-outline { background: transparent; border: 1.5px solid #d6ddf5; color: #2d5be3; margin-left: 10px; }
.btn-outline:hover { background: #e8eeff; }
</style>
</head>
<body>
<div class="card">
  <?php if ($status === 'success'): ?>
    <div class="icon icon-success">✅</div>
    <h1>Account Activated!</h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <a href="../../customer-login.php" class="btn btn-primary">Go to Login</a>

  <?php elseif ($status === 'already'): ?>
    <div class="icon icon-already">✔️</div>
    <h1>Already Verified</h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <a href="../../customer-login.php" class="btn btn-primary">Go to Login</a>

  <?php elseif ($status === 'expired'): ?>
    <div class="icon icon-expired">⏰</div>
    <h1>Link Expired</h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <button class="btn btn-primary" onclick="resendFromExpired(this)">Resend Activation Email</button>
    <a href="../../customer-login.php" class="btn btn-outline">Back to Login</a>
    <p id="resend-msg" style="margin-top:16px;font-size:.85rem;"></p>
    <script>
    function resendFromExpired(btn) {
      var userId = <?php echo intval($_SESSION['expired_user_id'] ?? 0); ?>;
      if (!userId) { document.getElementById('resend-msg').textContent = 'Unable to resend. Please register again.'; return; }
      btn.disabled = true; btn.textContent = 'Sending\u2026';
      fetch('../../core/customer/resend_verification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
      }).then(function(r){ return r.json(); }).then(function(res){
        var msg = document.getElementById('resend-msg');
        msg.textContent = res.success ? '✅ New activation email sent! Check your inbox.' : (res.message || 'Failed. Try again.');
        msg.style.color = res.success ? '#1aab6d' : '#e03e3e';
        btn.textContent = 'Resend Activation Email';
        if (!res.success) btn.disabled = false;
      }).catch(function(){
        btn.disabled = false; btn.textContent = 'Resend Activation Email';
        document.getElementById('resend-msg').textContent = 'Network error. Try again.';
      });
    }
    </script>

  <?php else: ?>
    <div class="icon icon-error">❌</div>
    <h1>Activation Failed</h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <a href="../../customer-login.php" class="btn btn-primary">Back to Login</a>
  <?php endif; ?>
</div>
</body>
</html>