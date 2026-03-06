<?php
// modules/admin/orders.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Simple filter logic
$status_filter = $_GET['status'] ?? 'all';

$query = "SELECT o.order_id, o.tracking_no, o.total_amount, o.order_status, o.payment_status, o.created_at, 
                 cust.username AS customer_name, 
                 cash.username AS cashier_name
          FROM orders o
          JOIN users cust ON o.user_id = cust.user_id
          LEFT JOIN users cash ON o.processed_by = cash.user_id";

if ($status_filter !== 'all') {
    $query .= " WHERE o.order_status = ?";
}

$query .= " ORDER BY o.created_at DESC LIMIT 200"; // Keep the page fast

$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$result = $stmt->get_result();

// Count totals for the mini-dashboard
$counts = $conn->query("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status")->fetch_all(MYSQLI_ASSOC);
$status_counts = ['pending' => 0, 'processing' => 0, 'ready' => 0, 'completed' => 0, 'cancelled' => 0];
$total_orders = 0;
foreach ($counts as $c) {
    $status_counts[$c['order_status']] = $c['count'];
    $total_orders += $c['count'];
}

$page_title = 'Master Order Audit';
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-slate-50 w-full">
    
    <div class="mb-8 border-b border-slate-200 pb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Master Order Audit</h1>
            <p class="text-slate-500 mt-1">Track the operational status of all store orders.</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <a href="orders.php?status=all" class="px-4 py-2 rounded-lg text-sm font-bold transition <?php echo $status_filter === 'all' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
            All Orders (<?php echo $total_orders; ?>)
        </a>
        <a href="orders.php?status=pending" class="px-4 py-2 rounded-lg text-sm font-bold transition <?php echo $status_filter === 'pending' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
            Pending (<?php echo $status_counts['pending']; ?>)
        </a>
        <a href="orders.php?status=processing" class="px-4 py-2 rounded-lg text-sm font-bold transition <?php echo $status_filter === 'processing' ? 'bg-yellow-500 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
            Processing (<?php echo $status_counts['processing']; ?>)
        </a>
        <a href="orders.php?status=ready" class="px-4 py-2 rounded-lg text-sm font-bold transition <?php echo $status_filter === 'ready' ? 'bg-green-500 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
            Ready (<?php echo $status_counts['ready']; ?>)
        </a>
        <a href="orders.php?status=completed" class="px-4 py-2 rounded-lg text-sm font-bold transition <?php echo $status_filter === 'completed' ? 'bg-slate-500 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
            Completed (<?php echo $status_counts['completed']; ?>)
        </a>
        <a href="orders.php?status=cancelled" class="px-4 py-2 rounded-lg text-sm font-bold transition <?php echo $status_filter === 'cancelled' ? 'bg-red-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'; ?>">
            Cancelled (<?php echo $status_counts['cancelled']; ?>)
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden mb-10">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="p-4 font-bold">Tracking # / Date</th>
                        <th class="p-4 font-bold">Customer</th>
                        <th class="p-4 font-bold text-center">Fulfillment Status</th>
                        <th class="p-4 font-bold text-center">Payment</th>
                        <th class="p-4 font-bold">Handled By</th>
                        <th class="p-4 font-bold text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4">
                                    <div class="font-mono text-sm font-bold text-blue-900"><?php echo htmlspecialchars($row['tracking_no']); ?></div>
                                    <div class="text-xs text-slate-500 mt-1"><?php echo date('M j, Y - h:i A', strtotime($row['created_at'])); ?></div>
                                </td>
                                <td class="p-4 font-semibold text-slate-700">
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                </td>
                                <td class="p-4 text-center">
                                    <?php 
                                        $bg = 'bg-slate-100 text-slate-800';
                                        if($row['order_status'] == 'pending') $bg = 'bg-blue-100 text-blue-800';
                                        if($row['order_status'] == 'processing') $bg = 'bg-yellow-100 text-yellow-800';
                                        if($row['order_status'] == 'ready') $bg = 'bg-green-100 text-green-800';
                                        if($row['order_status'] == 'completed') $bg = 'bg-slate-200 text-slate-600';
                                        if($row['order_status'] == 'cancelled') $bg = 'bg-red-100 text-red-800';
                                    ?>
                                    <span class="<?php echo $bg; ?> text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">
                                        <?php echo htmlspecialchars($row['order_status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="<?php echo $row['payment_status'] === 'paid' ? 'text-green-600' : 'text-orange-500'; ?> font-bold text-xs uppercase">
                                        <?php echo htmlspecialchars($row['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-sm font-semibold text-slate-600">
                                    <?php echo $row['cashier_name'] ? htmlspecialchars($row['cashier_name']) : '<span class="text-slate-400 italic">Unassigned</span>'; ?>
                                </td>
                                <td class="p-4 text-right font-black text-slate-900 font-mono">
                                    ₱<?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="p-12 text-center text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p class="font-bold text-lg text-slate-700">No orders found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php 
require_once '../../includes/admin_footer.php'; 
$conn->close(); 
?>