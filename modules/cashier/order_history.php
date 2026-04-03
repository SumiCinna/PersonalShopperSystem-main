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

// --- FETCH ALL ORDERS (with optional invoice/transaction data) ---
// Uses LEFT JOIN so cancelled/pending orders without invoices still appear
$query = "SELECT 
            o.order_id, o.tracking_no, o.order_status, o.total_amount, o.created_at,
            u.username AS customer_name,
            i.invoice_id, i.invoice_no, i.grand_total, i.issued_at,
            t.payment_method
          FROM orders o
          JOIN users u ON o.user_id = u.user_id
          LEFT JOIN invoices i ON i.order_id = o.order_id
          LEFT JOIN transactions t ON t.invoice_id = i.invoice_id
          ORDER BY o.created_at DESC
          LIMIT 100";

$result = $conn->query($query);

// --- STATUS BADGE HELPER ---
function statusBadge($status) {
    $map = [
        'pending'    => ['bg-yellow-100 text-yellow-800 border-yellow-300',  '🕐', 'Pending'],
        'processing' => ['bg-blue-100 text-blue-800 border-blue-300',        '⚙️', 'Processing'],
        'ready'      => ['bg-purple-100 text-purple-800 border-purple-300',  '📦', 'Ready'],
        'completed'  => ['bg-green-100 text-green-800 border-green-300',     '✅', 'Completed'],
        'cancelled'  => ['bg-red-100 text-red-800 border-red-300',           '✕',  'Cancelled'],
    ];
    [$cls, $icon, $label] = $map[$status] ?? ['bg-gray-100 text-gray-600 border-gray-300', '?', ucfirst($status)];
    return "<span class=\"inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-full border $cls uppercase tracking-wide\">
                <span>$icon</span><span>$label</span>
            </span>";
}

$page_title = 'Transaction History';
require_once '../../includes/cashier_header.php';
?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">

    <div class="flex justify-between items-center mb-8 border-b pb-4 border-gray-200">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Transaction History
            </h1>
            <p class="text-gray-500 mt-1">Review all orders and reprint official receipts for completed sales.</p>
        </div>
        <div class="bg-white px-5 py-3 rounded-xl shadow-sm border border-gray-200 text-sm font-semibold text-gray-600 flex items-center">
            <span class="mr-2">Total Records:</span>
            <span class="text-blue-600 font-black text-xl"><?php echo $result->num_rows; ?></span>
        </div>
    </div>

    <!-- ── Status Legend ──────────────────────────────────────────────────────── -->
    <div class="flex flex-wrap gap-2 mb-5">
        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide self-center mr-1">Legend:</span>
        <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-full border bg-yellow-100 text-yellow-800 border-yellow-300">🕐 Pending</span>
        <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-full border bg-blue-100 text-blue-800 border-blue-300">⚙️ Processing</span>
        <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-full border bg-purple-100 text-purple-800 border-purple-300">📦 Ready</span>
        <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-full border bg-green-100 text-green-800 border-green-300">✅ Completed</span>
        <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-full border bg-red-100 text-red-800 border-red-300">✕ Cancelled</span>
    </div>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-800 text-white text-sm uppercase tracking-wider">
                        <th class="p-4 font-medium">Date & Time</th>
                        <th class="p-4 font-medium">OR / Order Ref</th>
                        <th class="p-4 font-medium">Customer</th>
                        <th class="p-4 font-medium text-center">Status</th>
                        <th class="p-4 font-medium">Payment</th>
                        <th class="p-4 font-medium text-right">Amount</th>
                        <th class="p-4 font-medium text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()):
                            $is_completed = $row['order_status'] === 'completed';
                            $is_cancelled = $row['order_status'] === 'cancelled';

                            // Row tint based on status
                            $row_bg = match($row['order_status']) {
                                'cancelled'  => 'bg-red-50',
                                'completed'  => '',
                                'pending'    => 'bg-yellow-50',
                                'processing' => 'bg-blue-50',
                                'ready'      => 'bg-purple-50',
                                default      => '',
                            };

                            $display_date   = $is_completed && $row['issued_at']
                                                ? $row['issued_at']
                                                : $row['created_at'];
                            $amount_display = $is_completed && $row['grand_total']
                                                ? $row['grand_total']
                                                : $row['total_amount'];

                            // Payment method with Card fallback — applies to ALL statuses including cancelled
                            $pm_raw = trim((string)($row['payment_method'] ?? ''));
                            if ($pm_raw === '' || strtolower($pm_raw) === 'unpaid') {
                                $pm_display = 'Card';
                            } else {
                                $pm_display = ucwords(str_replace('_', ' ', $pm_raw));
                            }
                        ?>
                        <tr class="hover:bg-gray-50 transition <?php echo $row_bg; ?>">

                            <td class="p-4">
                                <div class="font-bold text-gray-800 text-sm">
                                    <?php echo date('M j, Y', strtotime($display_date)); ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    <?php echo date('h:i A', strtotime($display_date)); ?>
                                </div>
                            </td>

                            <td class="p-4">
                                <?php if ($is_completed && $row['invoice_no']): ?>
                                    <div class="font-mono font-black text-blue-900 text-sm"><?php echo htmlspecialchars($row['invoice_no']); ?></div>
                                    <div class="text-[10px] text-gray-400 mt-0.5">ORD: <?php echo htmlspecialchars($row['tracking_no']); ?></div>
                                <?php else: ?>
                                    <div class="font-mono font-bold text-gray-700 text-sm"><?php echo htmlspecialchars($row['tracking_no']); ?></div>
                                    <div class="text-[10px] text-gray-400 mt-0.5">No invoice issued</div>
                                <?php endif; ?>
                            </td>

                            <td class="p-4">
                                <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($row['customer_name']); ?></span>
                            </td>

                            <!-- Status Badge -->
                            <td class="p-4 text-center">
                                <?php echo statusBadge($row['order_status']); ?>
                            </td>

                            <td class="p-4">
                                <span class="bg-blue-100 text-blue-800 text-[10px] font-black px-2 py-1 rounded-full uppercase tracking-wider border border-blue-200">
                                    <?php echo htmlspecialchars($pm_display); ?>
                                </span>
                            </td>

                            <td class="p-4 text-right">
                                <?php if ($is_cancelled): ?>
                                    <div class="font-black text-red-400 text-base line-through">₱<?php echo number_format($amount_display, 2); ?></div>
                                    <div class="text-[10px] text-red-400 font-bold uppercase">Voided</div>
                                <?php else: ?>
                                    <div class="font-black text-green-700 text-lg">₱<?php echo number_format($amount_display, 2); ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="p-4 text-center">
                                <?php if ($is_completed && $row['invoice_id']): ?>
                                    <a href="receipt.php?id=<?php echo $row['invoice_id']; ?>" target="_blank"
                                       class="inline-flex items-center text-sm font-bold text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition border border-blue-200">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View OR
                                    </a>
                                <?php elseif ($is_cancelled): ?>
                                    <span class="inline-flex items-center text-xs font-bold text-red-400 bg-red-50 px-3 py-1.5 rounded-lg border border-red-200">
                                        Cancelled
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center text-xs font-bold text-gray-400 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                                        In Progress
                                    </span>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-16 text-center text-gray-500">
                                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
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