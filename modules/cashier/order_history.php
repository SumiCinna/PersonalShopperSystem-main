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
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
        $page = 1;
}

// --- FILTERS ---
$filter_search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$filter_date_from = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';

$where_parts = [];
$bind_types = '';
$bind_values = [];

if ($filter_search !== '') {
    $where_parts[] = "(u.username LIKE ? OR o.tracking_no LIKE ? OR i.invoice_no LIKE ?)";
    $bind_types .= 'sss';
    $search_like = "%$filter_search%";
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}
if ($filter_date_from !== '') {
    $where_parts[] = "DATE(o.created_at) >= ?";
    $bind_types .= 's';
    $bind_values[] = $filter_date_from;
}
if ($filter_date_to !== '') {
    $where_parts[] = "DATE(o.created_at) <= ?";
    $bind_types .= 's';
    $bind_values[] = $filter_date_to;
}

$where_sql = count($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Count total records for pagination
$count_query = "SELECT COUNT(DISTINCT o.order_id) AS total
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                LEFT JOIN invoices i ON i.order_id = o.order_id
                $where_sql";
$count_stmt = $conn->prepare($count_query);
if ($bind_types !== '' && !empty($bind_values)) {
    $count_stmt->bind_param($bind_types, ...$bind_values);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = (int)($count_result->fetch_assoc()['total'] ?? 0);
$count_stmt->close();
$total_pages = max(1, (int)ceil($total_records / $per_page));

if ($page > $total_pages) {
        $page = $total_pages;
}

$offset = ($page - 1) * $per_page;

$query = "SELECT 
                        o.order_id, o.tracking_no, o.order_status, o.total_amount, o.created_at,
                        u.username AS customer_name,
                        i.invoice_id, i.invoice_no, i.grand_total, i.issued_at,
                        t.payment_method
                    FROM orders o
                    JOIN users u ON o.user_id = u.user_id
                    LEFT JOIN invoices i ON i.order_id = o.order_id
                    LEFT JOIN transactions t ON t.invoice_id = i.invoice_id
                    $where_sql
                    ORDER BY o.created_at DESC
                    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$final_types = $bind_types . 'ii';
$final_values = array_merge($bind_values, [$per_page, $offset]);
$stmt->bind_param($final_types, ...$final_values);
$stmt->execute();
$result = $stmt->get_result();

$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = $total_records > 0 ? min($offset + $result->num_rows, $total_records) : 0;

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

function build_history_query(array $overrides = []): string {
    $base = array_filter([
        'search' => $_GET['search'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'page' => $_GET['page'] ?? '',
    ], static fn($v) => $v !== null && $v !== '');

    return http_build_query(array_merge($base, $overrides));
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
            <span class="mr-2">Showing:</span>
            <span class="text-blue-600 font-black text-xl"><?php echo $start_record; ?>-<?php echo $end_record; ?></span>
            <span class="mx-2 text-gray-400">|</span>
            <span class="mr-2">Total:</span>
            <span class="text-blue-600 font-black text-xl"><?php echo $total_records; ?></span>
        </div>
    </div>

    <!-- ── Status Legend ──────────────────────────────────────────────────────── -->
    <form method="GET" action="order_history.php" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>"
                       placeholder="Customer or Order Ref"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none">
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button type="submit" class="px-4 py-2 text-sm font-bold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Apply</button>
            <a href="order_history.php" class="px-4 py-2 text-sm font-bold rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition border border-gray-300">Reset</a>
        </div>
    </form>

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
                            $status_normalized = strtolower(trim((string)($row['order_status'] ?? '')));
                            $is_completed = $status_normalized === 'completed';
                            $is_cancelled = $status_normalized === 'cancelled';
                            $has_receipt = !empty($row['invoice_id']);

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
                                <?php if ($has_receipt): ?>
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

    <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">
                Page <span class="font-bold text-gray-800"><?php echo $page; ?></span> of
                <span class="font-bold text-gray-800"><?php echo $total_pages; ?></span>
            </p>

            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo build_history_query(['page' => $page - 1]); ?>" class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-100 transition">Prev</a>
                <?php else: ?>
                    <span class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-gray-200 text-gray-400 bg-gray-100 cursor-not-allowed">Prev</span>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="px-3 py-1.5 text-sm font-bold rounded-lg border border-blue-600 bg-blue-600 text-white"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo build_history_query(['page' => $p]); ?>" class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-100 transition"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo build_history_query(['page' => $page + 1]); ?>" class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-100 transition">Next</a>
                <?php else: ?>
                    <span class="px-3 py-1.5 text-sm font-semibold rounded-lg border border-gray-200 text-gray-400 bg-gray-100 cursor-not-allowed">Next</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php
require_once '../../includes/cashier_footer.php';
$conn->close();
?>