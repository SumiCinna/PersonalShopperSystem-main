<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
	header('Location: ../../customer-login.php');
	exit();
}

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['profile_csrf'])) {
	$_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['profile_csrf'];

function profileRedirect(string $type, string $message): void {
	$_SESSION['profile_flash_type'] = $type;
	$_SESSION['profile_flash_message'] = $message;
	header('Location: profile.php');
	exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$posted_token = $_POST['csrf_token'] ?? '';
	if (!hash_equals($_SESSION['profile_csrf'] ?? '', $posted_token)) {
		profileRedirect('error', 'Invalid request token. Please refresh and try again.');
	}

	$action = $_POST['action'] ?? '';

	if ($action === 'update_phone') {
		$phone = trim($_POST['phone'] ?? '');

		if (!preg_match('/^09\d{9}$/', $phone)) {
			profileRedirect('error', 'Please enter a valid mobile number (11 digits starting with 09).');
		}

		$check_stmt = $conn->prepare('SELECT user_id FROM user_profiles WHERE mobile = ? AND user_id <> ? LIMIT 1');
		$check_stmt->bind_param('si', $phone, $user_id);
		$check_stmt->execute();
		$duplicate = $check_stmt->get_result()->fetch_assoc();
		$check_stmt->close();

		if ($duplicate) {
			profileRedirect('error', 'That mobile number is already used by another account.');
		}

		$upd_stmt = $conn->prepare('UPDATE user_profiles SET mobile = ? WHERE user_id = ?');
		$upd_stmt->bind_param('si', $phone, $user_id);
		$upd_stmt->execute();
		$affected_rows = $upd_stmt->affected_rows;
		$upd_stmt->close();

		if ($affected_rows >= 0) {
			profileRedirect('success', 'Phone number updated successfully.');
		}

		profileRedirect('error', 'Unable to update phone number right now.');
	}

	if ($action === 'change_password') {
		$current_password = $_POST['current_password'] ?? '';
		$new_password     = $_POST['new_password'] ?? '';
		$confirm_password = $_POST['confirm_password'] ?? '';

		if ($current_password === '' || $new_password === '' || $confirm_password === '') {
			profileRedirect('error', 'Please fill in current, new, and confirm password fields.');
		}

		if (strlen($new_password) < 8) {
			profileRedirect('error', 'Password must be at least 8 characters.');
		}

		if (strlen($new_password) > 20) {
			profileRedirect('error', 'Password must not exceed 20 characters.');
		}

		if (!preg_match('/[A-Z]/', $new_password)) {
			profileRedirect('error', 'Password must contain at least one uppercase letter.');
		}

		if (!preg_match('/[a-z]/', $new_password)) {
			profileRedirect('error', 'Password must contain at least one lowercase letter.');
		}

		if (!preg_match('/[0-9]/', $new_password)) {
			profileRedirect('error', 'Password must contain at least one number.');
		}

		if ($new_password !== $confirm_password) {
			profileRedirect('error', 'New password and confirm password do not match.');
		}

		if ($new_password === $current_password) {
			profileRedirect('error', 'New password must be different from your current password.');
		}

		$pw_stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
		$pw_stmt->bind_param('i', $user_id);
		$pw_stmt->execute();
		$row = $pw_stmt->get_result()->fetch_assoc();
		$pw_stmt->close();

		if (!$row) {
			profileRedirect('error', 'Account not found. Please log in again.');
		}

		$stored_password = $row['password'] ?? '';
		$current_ok      = password_verify($current_password, $stored_password) || hash_equals($stored_password, $current_password);

		if (!$current_ok) {
			profileRedirect('error', 'Current password is incorrect.');
		}

		$new_hash  = password_hash($new_password, PASSWORD_DEFAULT);
		$save_stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
		$save_stmt->bind_param('si', $new_hash, $user_id);
		$save_stmt->execute();
		$save_stmt->close();

		profileRedirect('success', 'Password updated successfully.');
	}
}

$stmt = $conn->prepare(
	"SELECT u.user_id, u.username, u.email,
			p.firstname, p.middlename, p.surname, p.suffix, p.mobile
	 FROM users u
	 LEFT JOIN user_profiles p ON u.user_id = p.user_id
	 WHERE u.user_id = ?
	 LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
	header('Location: ../../auth/logout.php');
	exit();
}

$full_name_parts = [
	trim((string) ($user['firstname'] ?? '')),
	trim((string) ($user['middlename'] ?? '')),
	trim((string) ($user['surname'] ?? '')),
	trim((string) ($user['suffix'] ?? '')),
];
$full_name = trim(implode(' ', array_filter($full_name_parts, static fn($part) => $part !== '')));
if ($full_name === '') {
	$full_name = $user['username'];
}

$mobile_display = $user['mobile'] ?? '';

$flash_type    = $_SESSION['profile_flash_type'] ?? '';
$flash_message = $_SESSION['profile_flash_message'] ?? '';
unset($_SESSION['profile_flash_type'], $_SESSION['profile_flash_message']);

$page_title = 'My Profile';
require_once '../../includes/customer_header.php';
?>

<main style="min-height:calc(100vh - 64px); background:#f3f4f6; display:flex; flex-direction:column; justify-content:center; padding:2rem 1.5rem;">
	<div style="width:100%; max-width:1200px; margin:0 auto;">

		<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem; margin-bottom:1.5rem;">
			<div>
				<h1 style="font-size:1.75rem; font-weight:900; color:#111827; margin:0; line-height:1.2;">My Profile</h1>
				<p style="font-size:0.9375rem; color:#6b7280; margin:4px 0 0;">Manage your account details and security settings.</p>
			</div>
			<?php if (!empty($flash_message)): ?>
				<?php $is_success = $flash_type === 'success'; ?>
				<div style="font-size:0.9rem; font-weight:600; padding:0.5rem 1.125rem; border-radius:8px; border:1px solid <?php echo $is_success ? '#86efac; background:#f0fdf4; color:#15803d' : '#fca5a5; background:#fef2f2; color:#dc2626'; ?>;">
					<?php echo htmlspecialchars($flash_message); ?>
				</div>
			<?php endif; ?>
		</div>

		<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1.25rem; align-items:stretch;">

			<div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:1.75rem;">
				<h2 style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#9ca3af; margin:0 0 1.25rem;">Personal Info</h2>

				<div style="margin-bottom:1.1rem;">
					<label style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">Full Name</label>
					<input type="text" value="<?php echo htmlspecialchars($full_name); ?>" readonly
						style="width:100%; box-sizing:border-box; background:#f9fafb; border:1px solid #e5e7eb; border-radius:9px; padding:0.65rem 0.875rem; font-size:1rem; font-weight:600; color:#374151; cursor:not-allowed;">
				</div>

				<div style="margin-bottom:1.1rem;">
					<label style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">Username</label>
					<input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly
						style="width:100%; box-sizing:border-box; background:#f9fafb; border:1px solid #e5e7eb; border-radius:9px; padding:0.65rem 0.875rem; font-size:1rem; font-weight:600; color:#374151; cursor:not-allowed;">
				</div>

				<div>
					<label style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">Account ID</label>
					<input type="text" value="#<?php echo (int) $user['user_id']; ?>" readonly
						style="width:100%; box-sizing:border-box; background:#f9fafb; border:1px solid #e5e7eb; border-radius:9px; padding:0.65rem 0.875rem; font-size:1rem; font-weight:600; color:#374151; cursor:not-allowed;">
				</div>
			</div>

			<div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:1.75rem;">
				<h2 style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#9ca3af; margin:0 0 1.25rem;">Contact Details</h2>

				<div style="margin-bottom:1.1rem;">
					<label style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">Email</label>
					<input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly
						style="width:100%; box-sizing:border-box; background:#f9fafb; border:1px solid #e5e7eb; border-radius:9px; padding:0.65rem 0.875rem; font-size:1rem; font-weight:600; color:#374151; cursor:not-allowed;">
				</div>

				<form method="POST">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
					<input type="hidden" name="action" value="update_phone">

					<div style="margin-bottom:0.35rem;">
						<label for="phone" style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">Phone Number</label>
						<input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($mobile_display); ?>"
							pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" required placeholder="09XXXXXXXXX"
							style="width:100%; box-sizing:border-box; background:#fff; border:1px solid #d1d5db; border-radius:9px; padding:0.65rem 0.875rem; font-size:1rem; font-weight:600; color:#1f2937; outline:none; transition:border-color 0.15s, box-shadow 0.15s;"
							onfocus="this.style.borderColor='#3b82f6';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.15)'"
							onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
					</div>
					<p style="font-size:0.8rem; color:#9ca3af; margin:0.3rem 0 1.1rem;">Format: 09XXXXXXXXX</p>

					<button type="submit"
						style="width:100%; background:#2563eb; color:#fff; font-size:1rem; font-weight:700; padding:0.7rem 1rem; border:none; border-radius:9px; cursor:pointer; transition:background 0.15s;"
						onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
						Update Phone
					</button>
				</form>
			</div>

			<div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:1.75rem;">
				<h2 style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#9ca3af; margin:0 0 1.25rem;">Change Password</h2>

				<form method="POST" style="display:flex; flex-direction:column; gap:1.1rem;">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
					<input type="hidden" name="action" value="change_password">

					<div>
						<label for="current_password" style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">Current Password</label>
						<div style="position:relative;">
							<input type="password" id="current_password" name="current_password" required
								style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:9px; padding:0.65rem 2.5rem 0.65rem 0.875rem; font-size:1rem; outline:none; transition:border-color 0.15s, box-shadow 0.15s;"
								onfocus="this.style.borderColor='#3b82f6';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.15)'"
								onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
							<button type="button" aria-label="Show or hide current password" data-toggle-password="current_password"
								style="position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#6b7280; padding:0; display:flex; align-items:center; justify-content:center;">
								<svg data-eye="open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							</button>
						</div>
					</div>

					<div>
						<label for="new_password" style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">New Password</label>
						<div style="position:relative;">
							<input type="password" id="new_password" name="new_password" minlength="8" maxlength="20" required
								pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,20}"
								title="8-20 characters with at least 1 uppercase letter, 1 lowercase letter, and 1 number"
								style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:9px; padding:0.65rem 2.5rem 0.65rem 0.875rem; font-size:1rem; outline:none; transition:border-color 0.15s, box-shadow 0.15s;"
								onfocus="this.style.borderColor='#3b82f6';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.15)'"
								onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
							<button type="button" aria-label="Show or hide new password" data-toggle-password="new_password"
								style="position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#6b7280; padding:0; display:flex; align-items:center; justify-content:center;">
								<svg data-eye="open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							</button>
						</div>
						<p id="new_password_feedback" style="font-size:0.78rem; color:#9ca3af; margin:0.35rem 0 0; min-height:1.1rem;">Use 8-20 characters with uppercase, lowercase, and a number.</p>
					</div>

					<div>
						<label for="confirm_password" style="display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af; margin-bottom:0.35rem;">Confirm New Password</label>
						<div style="position:relative;">
							<input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="20" required
								style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:9px; padding:0.65rem 2.5rem 0.65rem 0.875rem; font-size:1rem; outline:none; transition:border-color 0.15s, box-shadow 0.15s;"
								onfocus="this.style.borderColor='#3b82f6';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.15)'"
								onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
							<button type="button" aria-label="Show or hide confirm new password" data-toggle-password="confirm_password"
								style="position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#6b7280; padding:0; display:flex; align-items:center; justify-content:center;">
								<svg data-eye="open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
							</button>
						</div>
						<p id="confirm_password_feedback" style="font-size:0.78rem; color:#9ca3af; margin:0.35rem 0 0; min-height:1.1rem;"></p>
					</div>

					<button type="submit"
						style="width:100%; background:#111827; color:#fff; font-size:1rem; font-weight:700; padding:0.7rem 1rem; border:none; border-radius:9px; cursor:pointer; transition:background 0.15s;"
						onmouseover="this.style.background='#000'" onmouseout="this.style.background='#111827'">
						Change Password
					</button>
				</form>
			</div>

		</div>
	</div>
</main>

<script>
(function () {
	const OPEN_EYE = '<svg data-eye="open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
	const CLOSED_EYE = '<svg data-eye="closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

	document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
		button.addEventListener('click', function () {
			const inputId = button.getAttribute('data-toggle-password');
			const input = document.getElementById(inputId);
			if (!input) return;
			input.type = input.type === 'password' ? 'text' : 'password';
			button.innerHTML = input.type === 'password' ? OPEN_EYE : CLOSED_EYE;
		});
	});

	const passwordForm = document.querySelector('form input[name="action"][value="change_password"]')?.closest('form');
	if (!passwordForm) return;

	const newPassword = document.getElementById('new_password');
	const confirmPassword = document.getElementById('confirm_password');
	const newPasswordFeedback = document.getElementById('new_password_feedback');
	const confirmPasswordFeedback = document.getElementById('confirm_password_feedback');
	const complexityRule = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,20}$/;
	const neutralText = 'Use 8-20 characters with uppercase, lowercase, and a number.';

	function setFeedback(element, message, color) {
		if (!element) return;
		element.textContent = message;
		element.style.color = color;
	}

	function validatePasswordFields() {
		if (!newPassword || !confirmPassword) return true;

		newPassword.setCustomValidity('');
		confirmPassword.setCustomValidity('');
		let valid = true;

		if (newPassword.value.length === 0) {
			setFeedback(newPasswordFeedback, neutralText, '#9ca3af');
		} else if (!complexityRule.test(newPassword.value)) {
			newPassword.setCustomValidity('Password must be 8-20 characters and include uppercase, lowercase, and number.');
			setFeedback(newPasswordFeedback, 'Password must be 8-20 characters and include uppercase, lowercase, and number.', '#dc2626');
			valid = false;
		} else {
			setFeedback(newPasswordFeedback, 'Password format looks good.', '#16a34a');
		}

		if (confirmPassword.value.length === 0) {
			setFeedback(confirmPasswordFeedback, '', '#9ca3af');
		} else if (newPassword.value !== confirmPassword.value) {
			confirmPassword.setCustomValidity('New password and confirm password do not match.');
			setFeedback(confirmPasswordFeedback, 'New password and confirm password do not match.', '#dc2626');
			valid = false;
		} else {
			setFeedback(confirmPasswordFeedback, 'Passwords match.', '#16a34a');
		}

		return valid && newPassword.checkValidity() && confirmPassword.checkValidity();
	}

	newPassword?.addEventListener('input', validatePasswordFields);
	confirmPassword?.addEventListener('input', validatePasswordFields);

	passwordForm.addEventListener('submit', function (event) {
		if (!validatePasswordFields()) {
			event.preventDefault();
			newPassword?.reportValidity();
			confirmPassword?.reportValidity();
		}
	});
})();
</script>

<?php
require_once '../../includes/customer_footer.php';
$conn->close();
?>