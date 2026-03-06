<?php
// modules/cashier/pos.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];

// --- TRANSACTION LOCK SECURITY ---
// Check if this cashier abandoned a transaction halfway through!
$lock_check = $conn->prepare("SELECT order_id FROM orders WHERE processed_by = ? AND order_status IN ('processing', 'ready')");
$lock_check->bind_param("i", $cashier_id);
$lock_check->execute();
$lock_result = $lock_check->get_result();

if ($lock_result->num_rows > 0) {
    // THEY ARE LOCKED! Force them back to the POS screen!
    $locked_order = $lock_result->fetch_assoc();
    $_SESSION['error'] = "SECURITY LOCK: You must complete or void your active transaction before taking a new customer.";
    header("Location: process_order.php?id=" . $locked_order['order_id']);
    exit();
}
$lock_check->close();

// --- FETCH PENDING QUEUE ---
// Only show orders that haven't been claimed by anyone yet
$query = "SELECT o.order_id, o.tracking_no, o.total_amount, o.balance_due, o.payment_method, o.pickup_datetime, u.username 
          FROM orders o
          JOIN users u ON o.user_id = u.user_id
          WHERE o.order_status = 'pending'
          ORDER BY o.pickup_datetime ASC"; // Urgent (earliest pickup) first
$result = $conn->query($query);

$page_title = 'POS Terminal Queue';
require_once '../../includes/cashier_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
    
    <div class="flex justify-between items-center mb-8 border-b pb-4 border-gray-200">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Pending Order Queue
            </h1>
            <p class="text-gray-500 mt-1">Select the next customer in line to begin processing their items.</p>
        </div>
        <div class="bg-white px-5 py-3 rounded-xl shadow-sm border border-gray-200 text-sm font-semibold text-gray-600 flex items-center">
            <span class="mr-2">Waiting in Line:</span> 
            <span class="text-blue-600 font-black text-2xl"><?php echo $result->num_rows; ?></span>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-900 text-white text-xs uppercase tracking-wider">
                    <th class="p-5 font-bold">Order Ref</th>
                    <th class="p-5 font-bold">Customer</th>
                    <th class="p-5 font-bold">Pickup Schedule</th>
                    <th class="p-5 font-bold text-center">Payment Status</th>
                    <th class="p-5 font-bold text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($order = $result->fetch_assoc()): ?>
                        <?php 
                            // Check urgency (if pickup is within 2 hours)
                            $pickup_ts = strtotime($order['pickup_datetime']);
                            $now = time();
                            $diff = $pickup_ts - $now;
                            
                            // Determine row styling based on urgency
                            $row_class = "hover:bg-blue-50 transition group";
                            $time_badge_color = "bg-slate-100 text-slate-600";
                            $status_label = "";

                            if ($diff < 0) {
                                // Overdue
                                $row_class = "bg-red-50 hover:bg-red-100 transition group border-l-4 border-red-500";
                                $time_badge_color = "bg-red-200 text-red-800";
                                $status_label = "OVERDUE";
                            } elseif ($diff < 7200) { // Within 2 hours
                                $row_class = "bg-yellow-50 hover:bg-yellow-100 transition group border-l-4 border-yellow-400";
                                $time_badge_color = "bg-yellow-200 text-yellow-800";
                                $status_label = "URGENT";
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            
                            <td class="p-5">
                                <div class="font-mono font-black text-slate-800 text-lg"><?php echo htmlspecialchars($order['tracking_no']); ?></div>
                                <div class="text-xs text-slate-500 mt-1">ID: #<?php echo $order['order_id']; ?></div>
                            </td>
                            
                            <td class="p-5">
                                <div class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars($order['username']); ?></div>
                                <div class="text-xs text-slate-500 mt-1">Customer</div>
                            </td>
                            
                            <td class="p-5">
                                <div class="flex items-center">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 text-lg flex items-center">
                                            <?php echo date('h:i A', $pickup_ts); ?>
                                            <?php if($status_label): ?>
                                                <span class="ml-2 text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-wider <?php echo $time_badge_color; ?> animate-pulse">
                                                    <?php echo $status_label; ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide mt-1">
                                            <?php 
                                                $date_str = date('Y-m-d', $pickup_ts);
                                                if($date_str == date('Y-m-d')) echo "Today";
                                                elseif($date_str == date('Y-m-d', strtotime('+1 day'))) echo "Tomorrow";
                                                else echo date('M j, Y', $pickup_ts);
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="p-5 text-center">
                                <?php if($order['balance_due'] > 0): ?>
                                    <div class="inline-block bg-red-50 border border-red-100 rounded-lg px-3 py-1">
                                        <div class="text-[10px] text-red-500 font-bold uppercase tracking-wider mb-0.5">Collect</div>
                                        <div class="font-black text-red-600 text-lg leading-none">₱<?php echo number_format($order['balance_due'], 2); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="inline-block bg-green-50 border border-green-100 rounded-lg px-3 py-1">
                                        <div class="text-[10px] text-green-600 font-bold uppercase tracking-wider mb-0.5">Status</div>
                                        <div class="font-black text-green-700 text-lg leading-none">PAID</div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="p-5 text-right">
                                <form action="../../core/cashier/claim_order.php" method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition shadow-md flex items-center ml-auto group-hover:scale-105 transform duration-200 border-b-4 border-blue-800 active:border-b-0 active:translate-y-1">
                                        Process Order
                                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    </button>
                                </form>
                            </td>
                            
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="p-16 text-center text-gray-500">
                            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            <p class="text-2xl font-bold text-gray-700 mb-2">Queue is empty</p>
                            <p class="text-sm">There are no pending orders. Great job keeping the line clear!</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<?php 
require_once '../../includes/cashier_footer.php'; 
$conn->close(); 
?>