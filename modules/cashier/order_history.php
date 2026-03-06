<?php
// modules/cashier/order_history.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];

// --- TRANSACTION LOCK SECURITY ---
$lock_check = $conn->prepare("SELECT order_id FROM orders WHERE processed_by = ? AND order_status IN ('processing', 'ready')");
$lock_check->bind_param("i", $cashier_id);
$lock_check->execute();
$lock_result = $lock_check->get_result();

if ($lock_result->num_rows > 0) {
    $locked_order = $lock_result->fetch_assoc();
    $_SESSION['error'] = "SECURITY LOCK: You must complete or void your active transaction before viewing history.";
    header("Location: process_order.php?id=" . $locked_order['order_id']);
    exit();
}
$lock_check->close();

// --- FETCH TRANSACTION HISTORY ---
// We join 4 tables together to get the complete picture of the invoice!
$query = "SELECT 
            i.invoice_id, i.invoice_no, i.grand_total, i.issued_at,
            o.tracking_no,
            u.username AS customer_name,
            t.payment_method
          FROM invoices i
          JOIN orders o ON i.order_id = o.order_id
          JOIN users u ON o.user_id = u.user_id
          JOIN transactions t ON i.invoice_id = t.invoice_id
          ORDER BY i.issued_at DESC 
          LIMIT 100"; // Limit to recent 100 to keep the page fast
          
$result = $conn->query($query);

$page_title = 'Transaction History';
require_once '../../includes/cashier_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
    
    <div class="flex justify-between items-center mb-8 border-b pb-4 border-gray-200">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Transaction History
            </h1>
            <p class="text-gray-500 mt-1">Review completed sales and reprint official receipts.</p>
        </div>
        <div class="bg-white px-5 py-3 rounded-xl shadow-sm border border-gray-200 text-sm font-semibold text-gray-600 flex items-center">
            <span class="mr-2">Total Records:</span> 
            <span class="text-blue-600 font-black text-xl"><?php echo $result->num_rows; ?></span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-800 text-white text-sm uppercase tracking-wider">
                        <th class="p-4 font-medium">Date & Time</th>
                        <th class="p-4 font-medium">OR Number / Ref</th>
                        <th class="p-4 font-medium">Customer</th>
                        <th class="p-4 font-medium">Payment Info</th>
                        <th class="p-4 font-medium text-right">Grand Total</th>
                        <th class="p-4 font-medium text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                                
                                <td class="p-4">
                                    <div class="font-bold text-gray-800 text-sm">
                                        <?php echo date('M j, Y', strtotime($row['issued_at'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo date('h:i A', strtotime($row['issued_at'])); ?>
                                    </div>
                                </td>
                                
                                <td class="p-4">
                                    <div class="font-mono font-bold text-blue-900"><?php echo htmlspecialchars($row['invoice_no']); ?></div>
                                    <div class="text-[10px] text-gray-400 mt-1">ORD: <?php echo htmlspecialchars($row['tracking_no']); ?></div>
                                </td>
                                
                                <td class="p-4">
                                    <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($row['customer_name']); ?></span>
                                </td>
                                
                                <td class="p-4">
                                    <span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider border border-blue-200">
                                        <?php echo htmlspecialchars($row['payment_method']); ?>
                                    </span>
                                </td>
                                
                                <td class="p-4 text-right">
                                    <div class="font-black text-green-700 text-lg">₱<?php echo number_format($row['grand_total'], 2); ?></div>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <a href="receipt.php?id=<?php echo $row['invoice_id']; ?>" target="_blank" class="inline-flex items-center text-sm font-bold text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded transition border border-blue-200">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        View OR
                                    </a>
                                </td>
                                
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="p-16 text-center text-gray-500">
                                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p class="text-xl font-bold text-gray-700 mb-1">No transactions yet</p>
                                <p class="text-sm">Completed sales will appear here.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php 
require_once '../../includes/cashier_footer.php'; 
$conn->close(); 
?>