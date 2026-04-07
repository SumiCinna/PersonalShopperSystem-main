<?php
// customer-login.php

error_reporting(E_ALL);
session_start();

// If already logged in as a customer, send to dashboard
if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'){
    header('Location: modules/customer/home.php');
    exit();
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);

$unverified_user_id = isset($_SESSION['unverified_user_id']) ? intval($_SESSION['unverified_user_id']) : 0;
unset($_SESSION['unverified_user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - Personal Shopper System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen flex items-center justify-center">
    
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-900">Personal Shopper</h1>
            <p class="text-gray-500 mt-2">Customer Portal</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if($unverified_user_id): ?>
            <div class="bg-yellow-50 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4 text-sm">
                <p class="font-semibold mb-2">⚠️ Your email address is not verified yet.</p>
                <p class="mb-3">Please check your inbox for the activation link, or click below to resend it.</p>
                <button 
                    onclick="resendVerification(<?php echo $unverified_user_id; ?>, this)" 
                    class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg transition text-sm">
                    Resend Activation Email
                </button>
                <p id="resend-msg" class="mt-2 text-center text-sm"></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="auth/login.php">
            <input type="hidden" name="login_type" value="customer">

            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Username or Email</label>
                <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <div class="mb-2">
                <label class="block text-gray-700 font-semibold mb-2">Password</label>
                <div class="relative">
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700" onclick="togglePw(this)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>

            <div class="mb-6 text-right">
                <a href="modules/customer/forgot_password.php" class="text-sm text-blue-600 hover:underline">Forgot password?</a>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition">
                Login to Shop
            </button>
            
            <div class="mt-4 text-center">
                <a href="auth/register.php" class="text-blue-600 hover:underline">Don't have an account? Sign up</a>
            </div>

            <div class="mt-3 text-center">
                <a href="index.php" class="inline-block w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-lg transition border border-gray-300">
                    Back to Home
                </a>
            </div>
        </form>
    </div>

    <script>
        function togglePw(btn) {
            var inp = btn.closest('.relative').querySelector('input');
            inp.type = inp.type === 'password' ? 'text' : 'password';
            btn.innerHTML = inp.type === 'password'
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
                : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        }

        function resendVerification(userId, btn) {
            btn.disabled = true;
            btn.textContent = 'Sending…';

            fetch('core/customer/resend_verification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var msg = document.getElementById('resend-msg');
                if (res.success) {
                    msg.textContent = '✅ Activation email sent! Check your inbox.';
                    msg.style.color = '#15803d';
                    btn.textContent = 'Resend Activation Email';
                    // Disable for 2 min to prevent spam
                    var secs = 120;
                    var timer = setInterval(function() {
                        secs--;
                        btn.textContent = 'Resend again in ' + secs + 's';
                        if (secs <= 0) {
                            clearInterval(timer);
                            btn.disabled = false;
                            btn.textContent = 'Resend Activation Email';
                        }
                    }, 1000);
                } else {
                    msg.textContent = '❌ ' + (res.message || 'Failed to send. Try again.');
                    msg.style.color = '#b91c1c';
                    btn.disabled = false;
                    btn.textContent = 'Resend Activation Email';
                }
            })
            .catch(function() {
                document.getElementById('resend-msg').textContent = '❌ Network error. Try again.';
                document.getElementById('resend-msg').style.color = '#b91c1c';
                btn.disabled = false;
                btn.textContent = 'Resend Activation Email';
            });
        }
    </script>

</body>
</html>