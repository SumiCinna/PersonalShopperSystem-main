<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

$cashier_id = $_SESSION['user_id'];

$search       = trim($_GET['search'] ?? '');
$filter_date  = trim($_GET['date'] ?? '');
$filter_month = trim($_GET['month'] ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$per_page     = 10;
$offset       = ($page - 1) * $per_page;

$where_clauses = ["cashier_id = ?"];
$params        = [$cashier_id];
$types         = "i";

if (!empty($search)) {
    $where_clauses[] = "(cashier_name LIKE ? OR DATE_FORMAT(date, '%M %d, %Y') LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

if (!empty($filter_date)) {
    $where_clauses[] = "date = ?";
    $params[] = $filter_date;
    $types   .= "s";
}

if (!empty($filter_month)) {
    $where_clauses[] = "DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $filter_month;
    $types   .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM shift_logs WHERE $where_sql");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = max(1, ceil($total_records / $per_page));

// Paginated results
$paginated_params = array_merge($params, [$per_page, $offset]);
$paginated_types  = $types . "ii";
$stmt = $conn->prepare("SELECT * FROM shift_logs WHERE $where_sql ORDER BY login_time DESC LIMIT ? OFFSET ?");
$stmt->bind_param($paginated_types, ...$paginated_params);
$stmt->execute();
$shifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// All-time summary (unfiltered)
$total_stmt = $conn->prepare("SELECT COALESCE(SUM(total_sales), 0) as grand_total, COUNT(*) as shift_count FROM shift_logs WHERE cashier_id = ?");
$total_stmt->bind_param("i", $cashier_id);
$total_stmt->execute();
$summary = $total_stmt->get_result()->fetch_assoc();
$total_stmt->close();

// Filtered total sales (all pages)
$filtered_stmt = $conn->prepare("SELECT COALESCE(SUM(total_sales), 0) as filtered_total FROM shift_logs WHERE $where_sql");
$filtered_stmt->bind_param($types, ...$params);
$filtered_stmt->execute();
$filtered_total = $filtered_stmt->get_result()->fetch_assoc()['filtered_total'];
$filtered_stmt->close();

// Build query string helper for pagination links
function paginate_url($page, $extra = []) {
    $params = array_merge($_GET, ['page' => $page], $extra);
    unset($params['page']);
    $params['page'] = $page;
    return 'sales_audit.php?' . http_build_query($params);
}

$page_title = 'Sales Audit Trail';
require_once '../../includes/cashier_header.php';
?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">

    <div class="mb-8 border-b pb-4">
        <h1 class="text-3xl font-bold text-gray-800">Sales Audit Trail</h1>
        <p class="text-gray-500 mt-1">Your personal shift history — each entry is recorded automatically when you sign out.</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-green-100 rounded-full mr-4 text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">All-Time Total Sales</p>
                <p class="text-3xl font-black text-gray-900">₱<?php echo number_format($summary['grand_total'], 2); ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-blue-100 rounded-full mr-4 text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">Total Shifts Logged</p>
                <p class="text-3xl font-black text-gray-900"><?php echo number_format($summary['shift_count']); ?></p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <form method="GET" action="sales_audit.php" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or date..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Filter by Date</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Filter by Month</label>
                <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-green-700 hover:bg-green-800 text-white font-bold px-5 py-2 rounded-lg text-sm transition">Apply</button>
                <a href="sales_audit.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-5 py-2 rounded-lg text-sm transition">Reset</a>
            </div>
        </form>
    </div>

    <!-- Audit Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 p-5 flex justify-between items-center">
            <h2 class="font-bold text-gray-700 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Shift Records
            </h2>
            <span class="text-xs text-gray-400 font-semibold"><?php echo $total_records; ?> record(s) found</span>
        </div>

        <?php if (count($shifts) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-3 font-bold">#</th>
                            <th class="px-5 py-3 font-bold">Cashier Name</th>
                            <th class="px-5 py-3 font-bold">Date</th>
                            <th class="px-5 py-3 font-bold">Login Time</th>
                            <th class="px-5 py-3 font-bold">Logout Time</th>
                            <th class="px-5 py-3 font-bold text-right">Total Sales</th>
                            <th class="px-5 py-3 font-bold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($shifts as $i => $shift): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-5 py-4 text-gray-400 font-semibold text-xs"><?php echo $offset + $i + 1; ?></td>
                                <td class="px-5 py-4">
                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($shift['cashier_name']); ?></span>
                                </td>
                                <td class="px-5 py-4 text-gray-600 font-semibold">
                                    <?php echo date('F j, Y', strtotime($shift['date'])); ?>
                                </td>
                                <td class="px-5 py-4 text-gray-600">
                                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                        <?php echo date('h:i:s A', strtotime($shift['login_time'])); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <?php if ($shift['logout_time']): ?>
                                        <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-600">
                                            <?php echo date('h:i:s A', strtotime($shift['logout_time'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span class="font-black text-gray-900 text-base">₱<?php echo number_format($shift['total_sales'], 2); ?></span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <?php if (!$shift['logout_time']): ?>
                                        <span class="bg-green-100 text-green-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">In Progress</span>
                                    <?php elseif ($shift['total_sales'] > 0): ?>
                                        <span class="bg-blue-100 text-blue-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">Completed</span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-500 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">No Sales</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="5" class="px-5 py-4 text-right font-bold text-gray-600 uppercase text-xs tracking-wider">Filtered Total:</td>
                            <td class="px-5 py-4 text-right font-black text-green-700 text-base">
                                ₱<?php echo number_format($filtered_total, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-400 font-semibold">
                        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> records
                    </p>
                    <div class="flex items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo paginate_url(1); ?>" class="px-3 py-1.5 text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">&laquo;</a>
                            <a href="<?php echo paginate_url($page - 1); ?>" class="px-3 py-1.5 text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">&lsaquo; Prev</a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page   = min($total_pages, $page + 2);
                        for ($p = $start_page; $p <= $end_page; $p++):
                        ?>
                            <a href="<?php echo paginate_url($p); ?>"
                               class="px-3 py-1.5 text-xs font-bold rounded-lg transition <?php echo $p === $page ? 'bg-green-700 text-white' : 'text-gray-600 bg-gray-100 hover:bg-gray-200'; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo paginate_url($page + 1); ?>" class="px-3 py-1.5 text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">Next &rsaquo;</a>
                            <a href="<?php echo paginate_url($total_pages); ?>" class="px-3 py-1.5 text-xs font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="p-16 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <p class="font-bold text-sm">No shift records found.</p>
                <p class="text-xs mt-1">Records appear here automatically after you sign out.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php require_once '../../includes/cashier_footer.php'; $conn->close(); ?>