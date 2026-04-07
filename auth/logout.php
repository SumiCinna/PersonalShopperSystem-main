<?php
session_start();
require_once '../config/config.php';

$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CASHIER SHIFT LOG: Record logout time and total sales ---
    if ($role === 'cashier' && isset($_SESSION['shift_id']) && isset($_SESSION['user_id'])) {
        date_default_timezone_set('Asia/Manila');

        $shift_id   = $_SESSION['shift_id'];
        $cashier_id = $_SESSION['user_id'];
        $now        = date('Y-m-d H:i:s');

        // Get the login_time for this shift
        $shift_stmt = $conn->prepare("SELECT login_time, date FROM shift_logs WHERE shift_id = ?");
        $shift_stmt->bind_param("i", $shift_id);
        $shift_stmt->execute();
        $shift_row = $shift_stmt->get_result()->fetch_assoc();
        $shift_stmt->close();

        if ($shift_row) {
            $login_time = $shift_row['login_time'];
            $shift_date = $shift_row['date'];

            // Sum all transactions by this cashier from login_time up to now
            // Uses BETWEEN with exact timestamps to capture only this shift's sales
            $sales_stmt = $conn->prepare("
                SELECT COALESCE(SUM(amount_paid), 0) AS total
                FROM transactions
                WHERE cashier_id = ?
                  AND transaction_date >= ?
                  AND transaction_date <= ?
            ");
            $sales_stmt->bind_param("iss", $cashier_id, $login_time, $now);
            $sales_stmt->execute();
            $total_sales = $sales_stmt->get_result()->fetch_assoc()['total'];
            $sales_stmt->close();

            $upd = $conn->prepare("UPDATE shift_logs SET logout_time = ?, total_sales = ? WHERE shift_id = ?");
            $upd->bind_param("sdi", $now, $total_sales, $shift_id);
            $upd->execute();
            $upd->close();
        }
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    if ($role === 'admin') {
        header("Location: ../admin-login.php");
    } elseif ($role === 'cashier') {
        header("Location: ../cashier-login.php");
    } elseif ($role === 'inventory') {
        header("Location: ../inventory-login.php");
    } else {
        header("Location: ../index.php");
    }
    exit();
}

$cancel_url = 'javascript:history.back()';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Out - PSS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-sm text-center">
        <div class="flex justify-center mb-4">
            <div class="bg-red-100 rounded-full p-4">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </div>
        </div>
        <h2 class="text-xl font-black text-gray-800 mb-2">Sign Out</h2>
        <p class="text-gray-500 text-sm mb-8">Are you sure you want to sign out?</p>
        <div class="flex gap-3">
            <a href="<?php echo $cancel_url; ?>" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl transition text-sm flex items-center justify-center">
                Cancel
            </a>
            <form method="POST" action="" class="flex-1">
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition text-sm">
                    Yes, Sign Out
                </button>
            </form>
        </div>
    </div>
</body>
</html>