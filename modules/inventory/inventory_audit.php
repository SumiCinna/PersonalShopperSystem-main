<?php
// modules/inventory/inventory_audit.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

// --- PAGINATION CONFIG ---
$per_page     = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset       = ($current_page - 1) * $per_page;

// --- FILTERS ---
$filter_action    = isset($_GET['action'])    && in_array($_GET['action'], ['add','update','inactive']) ? $_GET['action'] : '';
$filter_search    = isset($_GET['search'])    ? trim($_GET['search'])    : '';
$filter_date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filter_date_to   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';

// --- BUILD WHERE CLAUSE ---
$where_parts = [];
$bind_types  = '';
$bind_values = [];

if ($filter_action !== '') {
    if ($filter_action === 'inactive') {
        $where_parts[] = "al.action = 'update' AND LOWER(COALESCE(al.field_changed, '')) = 'status' AND LOWER(COALESCE(al.new_value, '')) LIKE '%inactive%'";
    } else {
        $where_parts[] = "al.action = ?";
        $bind_types   .= 's';
        $bind_values[] = $filter_action;
    }
}
if ($filter_search !== '') {
    // Search against live product name from JOIN, username, and field_changed
    $where_parts[] = "(p.name LIKE ? OR u.username LIKE ? OR al.field_changed LIKE ?)";
    $bind_types   .= 'sss';
    $like = "%$filter_search%";
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
}
if ($filter_date_from !== '') {
    $where_parts[] = "DATE(al.created_at) >= ?";
    $bind_types   .= 's';
    $bind_values[] = $filter_date_from;
}
if ($filter_date_to !== '') {
    $where_parts[] = "DATE(al.created_at) <= ?";
    $bind_types   .= 's';
    $bind_values[] = $filter_date_to;
}

$where_sql = count($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// --- COUNT TOTAL ROWS ---
$count_sql = "
    SELECT COUNT(*) as total
    FROM activity_logs al
    JOIN users u ON al.user_id = u.user_id
    LEFT JOIN products p ON al.product_id = p.product_id
    $where_sql
";
$count_stmt = $conn->prepare($count_sql);
if ($bind_types && $bind_values) {
    $count_stmt->bind_param($bind_types, ...$bind_values);
}
$count_stmt->execute();
$total_rows  = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_rows / $per_page));
$count_stmt->close();

// Clamp current page
if ($current_page > $total_pages) $current_page = $total_pages;

// --- FETCH AUDIT ROWS ---
// FIX: Product name comes ONLY from LEFT JOIN products.
// al.product_name column is NOT referenced — it does not exist in this table.
// For deleted products (p.name is NULL), we show "Deleted Product #ID".
$data_sql = "
    SELECT
        al.log_id,
        al.action,
        al.product_id,
        COALESCE(p.name, CONCAT('Deleted Product #', al.product_id)) AS product_name,
        al.field_changed,
        al.old_value,
        al.new_value,
        u.username AS done_by,
        al.created_at
    FROM activity_logs al
    JOIN users u ON al.user_id = u.user_id
    LEFT JOIN products p ON al.product_id = p.product_id
    $where_sql
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$data_stmt = $conn->prepare($data_sql);
$final_types  = $bind_types . 'ii';
$final_values = array_merge($bind_values, [$per_page, $offset]);
$data_stmt->bind_param($final_types, ...$final_values);
$data_stmt->execute();
$audit_rows = $data_stmt->get_result();
$data_stmt->close();

// --- SUMMARY COUNTS (always unfiltered) ---
$summary = $conn->query("SELECT action, COUNT(*) as cnt FROM activity_logs GROUP BY action")->fetch_all(MYSQLI_ASSOC);
$summary_map = ['add' => 0, 'update' => 0, 'delete' => 0];
foreach ($summary as $s) $summary_map[$s['action']] = $s['cnt'];
$total_all = array_sum($summary_map);

$inactive_products_count = 0;
$inactive_result = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE status = 'inactive'");
if ($inactive_result) {
    $inactive_products_count = (int) ($inactive_result->fetch_assoc()['cnt'] ?? 0);
}

// --- BUILD QUERY STRING HELPER ---
function build_query(array $overrides = []): string {
    $base = array_filter([
        'action'    => $_GET['action']    ?? '',
        'search'    => $_GET['search']    ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
        'page'      => $_GET['page']      ?? '',
    ]);
    return http_build_query(array_merge($base, $overrides));
}

$page_title = 'Inventory Audit Trail';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto">

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Inventory Audit Trail</h1>
            <p class="text-gray-500 text-sm mt-1">Full history of all inventory changes — who did what and when</p>
        </div>
        <a href="dashboard.php" class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg transition text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Dashboard
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">All Activity</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_all); ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-green-200 shadow-sm p-5 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Products Added</p>
                <p class="text-2xl font-bold text-green-700"><?php echo number_format($summary_map['add']); ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-yellow-200 shadow-sm p-5 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Fields Updated</p>
                <p class="text-2xl font-bold text-yellow-700"><?php echo number_format($summary_map['update']); ?></p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11M9 21V3m12 9a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Products Inactive</p>
                <p class="text-2xl font-bold text-gray-700"><?php echo number_format($inactive_products_count); ?></p>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <form method="GET" action="inventory_audit.php" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Search</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>"
                           placeholder="Product, user, or field..."
                           class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Action</label>
                <select name="action" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none bg-white">
                    <option value="">All Actions</option>
                    <option value="add"      <?php echo $filter_action === 'add'      ? 'selected' : ''; ?>>Added</option>
                    <option value="update"   <?php echo $filter_action === 'update'   ? 'selected' : ''; ?>>Updated</option>
                    <option value="inactive" <?php echo $filter_action === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none">
            </div>
        </div>
        <div class="flex items-center gap-3 mt-4">
            <button type="submit" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                Apply Filters
            </button>
            <a href="inventory_audit.php" class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-semibold px-5 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Reset
            </a>
        </div>
    </form>

    <!-- Audit Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                             M9 5a2 2 0 002 2h2a2 2 0 002-2
                             M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Audit Log
            </h2>
            <span class="text-xs text-indigo-600 font-semibold bg-indigo-100 px-3 py-1 rounded-full">
                <?php echo number_format($total_rows); ?> record<?php echo $total_rows !== 1 ? 's' : ''; ?> found
            </span>
        </div>

        <div class="overflow-x-auto">
            <?php if ($audit_rows->num_rows > 0): ?>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <th class="px-5 py-3 text-left w-8">#</th>
                            <th class="px-5 py-3 text-left">Action</th>
                            <th class="px-5 py-3 text-left">Product</th>
                            <th class="px-5 py-3 text-left">Field Changed</th>
                            <th class="px-5 py-3 text-left">Old Value</th>
                            <th class="px-5 py-3 text-left">New Value</th>
                            <th class="px-5 py-3 text-left">Done By</th>
                            <th class="px-5 py-3 text-left">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $row_num = $offset + 1;
                        while ($log = $audit_rows->fetch_assoc()):
                            $badge = match($log['action']) {
                                'add'    => ['classes' => 'bg-green-100 text-green-800',   'icon' => 'M12 4v16m8-8H4', 'label' => 'Added'],
                                'update' => ['classes' => 'bg-yellow-100 text-yellow-800', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'label' => 'Updated'],
                                'delete' => ['classes' => 'bg-red-100 text-red-800',       'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', 'label' => 'Deleted'],
                                default  => ['classes' => 'bg-gray-100 text-gray-700',     'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => ucfirst($log['action'])],
                            };
                            $is_deleted_product = str_starts_with($log['product_name'], 'Deleted Product #');
                            $field_normalized = strtolower((string)($log['field_changed'] ?? ''));
                            $is_price_field = in_array($field_normalized, ['cost_price', 'selling_price', 'cost price', 'selling price'], true);
                            $new_value_display = $log['new_value'];
                            if ($is_price_field && $log['new_value'] !== null && $log['new_value'] !== '' && is_numeric($log['new_value'])) {
                                $new_value_display = number_format((float)$log['new_value'], 2, '.', '');
                            }
                        ?>
                            <tr class="hover:bg-indigo-50 transition-colors">

                                <td class="px-5 py-3 text-xs text-gray-400 font-mono"><?php echo $row_num++; ?></td>

                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?php echo $badge['classes']; ?>">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="<?php echo $badge['icon']; ?>"/>
                                        </svg>
                                        <?php echo $badge['label']; ?>
                                    </span>
                                </td>

                                
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    <?php if ($is_deleted_product): ?>
                                        <span class="text-gray-400 italic text-xs"><?php echo htmlspecialchars($log['product_name']); ?></span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($log['product_name']); ?>
                                        <?php if ($log['product_id']): ?>
                                            <a href="edit_product.php?id=<?php echo $log['product_id']; ?>"
                                               class="ml-1 text-indigo-400 hover:text-indigo-600 text-xs font-normal">
                                                #<?php echo $log['product_id']; ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3 text-gray-600">
                                    <?php if ($log['field_changed']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs font-mono">
                                            <?php echo htmlspecialchars($log['field_changed']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3 max-w-[160px]">
                                    <?php if (!empty($log['old_value'])): ?>
                                        <span class="inline-block text-red-500 line-through text-xs break-words max-w-full">
                                            <?php echo htmlspecialchars($log['old_value']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3 max-w-[160px]">
                                    <?php if (!empty($log['new_value'])): ?>
                                        <span class="inline-block text-green-600 font-medium text-xs break-words max-w-full">
                                            <?php echo htmlspecialchars((string)$new_value_display); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        
                                        </span>
                                        <span class="text-gray-700"><?php echo htmlspecialchars($log['done_by']); ?></span>
                                    </div>
                                </td>

                                <td class="px-5 py-3 whitespace-nowrap">
                                    <p class="font-medium text-gray-700"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($log['created_at'])); ?></p>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="py-16 text-center">
                    <svg class="w-14 h-14 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                                 M9 5a2 2 0 002 2h2a2 2 0 002-2
                                 M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-gray-500 font-medium">No audit records found.</p>
                    <p class="text-gray-400 text-sm mt-1">Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination Footer -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-sm text-gray-500">
                    Showing
                    <span class="font-semibold text-gray-700"><?php echo $offset + 1; ?></span>
                    –
                    <span class="font-semibold text-gray-700"><?php echo min($offset + $per_page, $total_rows); ?></span>
                    of
                    <span class="font-semibold text-gray-700"><?php echo number_format($total_rows); ?></span>
                    records
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($current_page > 1): ?>
                        <a href="?<?php echo build_query(['page' => $current_page - 1]); ?>"
                           class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-indigo-50 hover:border-indigo-400 hover:text-indigo-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>Prev
                        </a>
                    <?php else: ?>
                        <span class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>Prev
                        </span>
                    <?php endif; ?>

                    <?php
                    $start_p = max(1, $current_page - 2);
                    $end_p   = min($total_pages, $current_page + 2);
                    if ($start_p > 1): ?>
                        <a href="?<?php echo build_query(['page' => 1]); ?>"
                           class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition">1</a>
                        <?php if ($start_p > 2): ?><span class="px-2 py-1.5 text-gray-400 text-sm">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $start_p; $p <= $end_p; $p++): ?>
                        <?php if ($p === $current_page): ?>
                            <span class="px-3 py-1.5 text-sm font-bold rounded-lg bg-indigo-600 text-white border border-indigo-600 shadow-sm"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo build_query(['page' => $p]); ?>"
                               class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_p < $total_pages): ?>
                        <?php if ($end_p < $total_pages - 1): ?><span class="px-2 py-1.5 text-gray-400 text-sm">…</span><?php endif; ?>
                        <a href="?<?php echo build_query(['page' => $total_pages]); ?>"
                           class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?<?php echo build_query(['page' => $current_page + 1]); ?>"
                           class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-indigo-50 hover:border-indigo-400 hover:text-indigo-700 transition">
                            Next<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed">
                            Next<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once '../../includes/inventory_footer.php'; ?>
<?php $conn->close(); ?>