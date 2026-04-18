<?php
// modules/admin/orders.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$status_filter = $_GET['status'] ?? 'all';
$search        = trim($_GET['search'] ?? '');
$sort          = $_GET['sort'] ?? 'newest';

// --- Main query ---
$where_clauses = [];
$params        = [];
$types         = '';

if ($status_filter !== 'all') {
    $where_clauses[] = "o.order_status = ?";
    $params[]        = $status_filter;
    $types          .= 's';
}

if ($search !== '') {
    $where_clauses[] = "(o.tracking_no LIKE ? OR cust.username LIKE ? OR cash.username LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$order_sql = match($sort) {
    'oldest'      => 'o.created_at ASC',
    'amount-desc' => 'o.total_amount DESC',
    'amount-asc'  => 'o.total_amount ASC',
    default       => 'o.created_at DESC',
};

$query = "
    SELECT
        o.order_id,
        o.tracking_no,
        o.total_amount,
        o.order_status,
        o.payment_status,
        o.payment_type,
        o.pickup_datetime,
        o.created_at,
        cust.username AS customer_name,
        cash.username AS cashier_name
    FROM orders o
    JOIN  users cust ON o.user_id      = cust.user_id
    LEFT JOIN users cash ON o.processed_by = cash.user_id
    $where_sql
    ORDER BY $order_sql
    LIMIT 200
";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// --- Status counts (always unfiltered) ---
$counts_res   = $conn->query("SELECT order_status, COUNT(*) AS cnt FROM orders GROUP BY order_status");
$status_counts = ['pending' => 0, 'processing' => 0, 'ready' => 0, 'completed' => 0, 'cancelled' => 0];
$total_orders  = 0;
while ($c = $counts_res->fetch_assoc()) {
    $status_counts[$c['order_status']] = (int)$c['cnt'];
    $total_orders += (int)$c['cnt'];
}

// --- Revenue (paid orders only) ---
$rev_res = $conn->query("SELECT SUM(total_amount) AS rev, COUNT(*) AS paid_count FROM orders WHERE payment_status = 'paid'");
$rev_row = $rev_res->fetch_assoc();
$revenue    = (float)($rev_row['rev'] ?? 0);
$paid_count = (int)($rev_row['paid_count'] ?? 0);

// --- Payment type labels ---
$pay_type_labels = [
    'full'       => 'Full',
    'partial'    => 'Partial',
    'partial_50' => '50% down',
    'partial_30' => '30% down',
];

$page_title = 'Master Order Audit';
require_once '../../includes/admin_header.php';
?>

<style>
    /* ── Scoped styles for this page ── */
    .audit-wrap { padding: 2rem; background: #f8fafc; min-height: 100vh; }

    /* Page header */
    .audit-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.25rem; }
    .audit-header h1 { font-size: 1.75rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
    .audit-header p  { font-size: 0.875rem; color: #64748b; margin-top: 4px; }

    /* Stat cards */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-bottom: 1.5rem; }
    .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; }
    .stat-card .s-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; margin-bottom: 6px; }
    .stat-card .s-val   { font-size: 1.35rem; font-weight: 800; color: #0f172a; line-height: 1; }
    .stat-card .s-sub   { font-size: 11px; color: #94a3b8; margin-top: 4px; }
    .s-val.c-blue   { color: #1d4ed8; }
    .s-val.c-yellow { color: #b45309; }
    .s-val.c-green  { color: #15803d; }
    .s-val.c-slate  { color: #475569; }
    .s-val.c-red    { color: #b91c1c; }

    /* Filter pills */
    .filter-bar { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 1.25rem; }
    .filter-pill { padding: 6px 16px; border-radius: 999px; border: 1px solid #e2e8f0; background: #fff; font-size: 12px; font-weight: 600; color: #64748b; text-decoration: none; transition: all .15s; }
    .filter-pill:hover { background: #f1f5f9; color: #0f172a; }
    .filter-pill.pill-all        { background: #0f172a; color: #fff; border-color: #0f172a; }
    .filter-pill.pill-pending    { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
    .filter-pill.pill-processing { background: #b45309; color: #fff; border-color: #b45309; }
    .filter-pill.pill-ready      { background: #15803d; color: #fff; border-color: #15803d; }
    .filter-pill.pill-completed  { background: #475569; color: #fff; border-color: #475569; }
    .filter-pill.pill-cancelled  { background: #b91c1c; color: #fff; border-color: #b91c1c; }

    /* Toolbar */
    .toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 1rem; align-items: center; }
    .search-wrap { position: relative; flex: 1; min-width: 220px; }
    .search-wrap svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
    .search-wrap input { width: 100%; padding: 9px 12px 9px 36px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #0f172a; background: #fff; outline: none; }
    .search-wrap input:focus { border-color: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,.15); }
    .sort-select { padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #0f172a; background: #fff; outline: none; cursor: pointer; }
    .sort-select:focus { border-color: #94a3b8; }

    /* Table */
    .table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
    .tbl thead tr { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .tbl th { padding: 10px 14px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; white-space: nowrap; }
    .tbl th.center { text-align: center; }
    .tbl th.right  { text-align: right; }
    .tbl td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #0f172a; }
    .tbl tr:last-child td { border-bottom: none; }
    .tbl tbody tr:hover td { background: #f8fafc; }
    .tbl td.center { text-align: center; }
    .tbl td.right  { text-align: right; }

    /* Cell components */
    .tracking-no { font-family: monospace; font-size: 12px; font-weight: 700; color: #1e40af; }
    .date-sub    { font-size: 11px; color: #94a3b8; margin-top: 3px; }
    .cust-name   { font-weight: 600; }
    .cashier-name { font-size: 12px; color: #475569; }
    .cashier-unassigned { font-size: 12px; color: #cbd5e1; font-style: italic; }
    .pickup-date { font-size: 12px; }
    .pickup-time { font-size: 11px; color: #94a3b8; }
    .pickup-none { font-size: 11px; color: #cbd5e1; font-style: italic; }
    .amount-val  { font-family: monospace; font-weight: 800; font-size: 13px; }
    .pay-type-sub { font-size: 10px; color: #94a3b8; margin-top: 3px; }

    /* Badges */
    .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .b-pending    { background: #dbeafe; color: #1e40af; }
    .b-processing { background: #fef3c7; color: #92400e; }
    .b-ready      { background: #dcfce7; color: #166534; }
    .b-completed  { background: #f1f5f9; color: #475569; }
    .b-cancelled  { background: #fee2e2; color: #991b1b; }
    .b-paid       { background: #dcfce7; color: #166534; }
    .b-pay-pend   { background: #fef3c7; color: #92400e; }
    .b-failed     { background: #fee2e2; color: #991b1b; }

    /* Result count */
    .result-count { font-size: 12px; color: #94a3b8; margin-left: auto; align-self: center; }

    /* Empty state */
    .empty-state { padding: 64px 0; text-align: center; }
    .empty-state svg { color: #e2e8f0; margin-bottom: 12px; }
    .empty-state p { font-weight: 700; font-size: 1rem; color: #475569; }
    .empty-state span { font-size: 13px; color: #94a3b8; }
</style>

<main class="flex-1 overflow-y-auto audit-wrap">

    <!-- Page header -->
    <div class="audit-header">
        <div>
            <h1>Master Order Audit</h1>
            <p>Real-time operational overview of all store orders.</p>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="s-label">Total orders</div>
            <div class="s-val"><?= $total_orders ?></div>
        </div>
        <div class="stat-card">
            <div class="s-label">Revenue collected</div>
            <div class="s-val" style="font-size:1.05rem">₱<?= number_format($revenue, 2) ?></div>
            <div class="s-sub"><?= $paid_count ?> paid</div>
        </div>
        <div class="stat-card">
            <div class="s-label">Pending</div>
            <div class="s-val c-blue"><?= $status_counts['pending'] ?></div>
        </div>
        <div class="stat-card">
            <div class="s-label">Processing</div>
            <div class="s-val c-yellow"><?= $status_counts['processing'] ?></div>
        </div>
        <div class="stat-card">
            <div class="s-label">Ready</div>
            <div class="s-val c-green"><?= $status_counts['ready'] ?></div>
        </div>
        <div class="stat-card">
            <div class="s-label">Completed</div>
            <div class="s-val c-slate"><?= $status_counts['completed'] ?></div>
        </div>
        <div class="stat-card">
            <div class="s-label">Cancelled</div>
            <div class="s-val c-red"><?= $status_counts['cancelled'] ?></div>
        </div>
    </div>

    <!-- Status filter pills -->
    <div class="filter-bar">
        <?php
        $pills = [
            'all'        => "All ({$total_orders})",
            'pending'    => "Pending ({$status_counts['pending']})",
            'processing' => "Processing ({$status_counts['processing']})",
            'ready'      => "Ready ({$status_counts['ready']})",
            'completed'  => "Completed ({$status_counts['completed']})",
            'cancelled'  => "Cancelled ({$status_counts['cancelled']})",
        ];
        foreach ($pills as $key => $label):
            $active = $status_filter === $key ? "pill-{$key}" : '';
        ?>
        <a href="?status=<?= $key ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
           class="filter-pill <?= $active ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search + Sort toolbar -->
    <form method="GET" action="orders.php" class="toolbar">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">

        <div class="search-wrap">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search tracking #, customer, cashier…">
        </div>

        <select name="sort" class="sort-select" onchange="this.form.submit()">
            <option value="newest"      <?= $sort === 'newest'      ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest"      <?= $sort === 'oldest'      ? 'selected' : '' ?>>Oldest first</option>
            <option value="amount-desc" <?= $sort === 'amount-desc' ? 'selected' : '' ?>>Amount: high to low</option>
            <option value="amount-asc"  <?= $sort === 'amount-asc'  ? 'selected' : '' ?>>Amount: low to high</option>
        </select>

        <button type="submit" class="filter-pill" style="border-radius:8px;cursor:pointer;">Search</button>

        <?php if ($search): ?>
        <a href="?status=<?= htmlspecialchars($status_filter) ?>&sort=<?= $sort ?>"
           class="filter-pill" style="border-radius:8px;">Clear</a>
        <?php endif; ?>

        <span class="result-count"><?= $result->num_rows ?> order<?= $result->num_rows !== 1 ? 's' : '' ?></span>
    </form>

    <!-- Orders table -->
    <div class="table-card">
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tracking / Date</th>
                        <th>Customer</th>
                        <th class="center">Fulfillment</th>
                        <th class="center">Payment</th>
                        <th>Handled by</th>
                        <th>Pickup</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                // Status badge class
                                $status_badge = match($row['order_status']) {
                                    'pending'    => 'b-pending',
                                    'processing' => 'b-processing',
                                    'ready'      => 'b-ready',
                                    'completed'  => 'b-completed',
                                    'cancelled'  => 'b-cancelled',
                                    default      => 'b-completed',
                                };
                                // Payment badge class
                                $pay_badge = match($row['payment_status']) {
                                    'paid'   => 'b-paid',
                                    'failed' => 'b-failed',
                                    default  => 'b-pay-pend',
                                };
                                // Payment type label
                                $pay_type_label = $pay_type_labels[$row['payment_type']] ?? ucfirst($row['payment_type']);
                                // Pickup
                                $pickup_html = '<span class="pickup-none">Not set</span>';
                                if (!empty($row['pickup_datetime'])) {
                                    $pu = strtotime($row['pickup_datetime']);
                                    $pickup_html = '<span class="pickup-date">' . date('M j, Y', $pu) . '</span>'
                                                 . '<br><span class="pickup-time">' . date('h:i A', $pu) . '</span>';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="tracking-no"><?= htmlspecialchars($row['tracking_no']) ?></div>
                                    <div class="date-sub"><?= date('M j, Y · h:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td class="cust-name"><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td class="center">
                                    <span class="badge <?= $status_badge ?>">
                                        <?= htmlspecialchars($row['order_status']) ?>
                                    </span>
                                </td>
                                <td class="center">
                                    <span class="badge <?= $pay_badge ?>">
                                        <?= htmlspecialchars($row['payment_status']) ?>
                                    </span>
                                    <div class="pay-type-sub"><?= htmlspecialchars($pay_type_label) ?></div>
                                </td>
                                <td>
                                    <?php if ($row['cashier_name']): ?>
                                        <span class="cashier-name"><?= htmlspecialchars($row['cashier_name']) ?></span>
                                    <?php else: ?>
                                        <span class="cashier-unassigned">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $pickup_html ?></td>
                                <td class="right">
                                    <span class="amount-val">₱<?= number_format($row['total_amount'], 2) ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p>No orders found</p>
                                    <span>Try adjusting your search or filter.</span>
                                </div>
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