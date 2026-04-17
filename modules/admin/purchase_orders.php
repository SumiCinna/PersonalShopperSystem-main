<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

function table_exists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

$schemaReady = table_exists($conn, 'purchase_orders') && table_exists($conn, 'purchase_order_items') && table_exists($conn, 'suppliers');
$rows = [];
$products = [];
$totalPages = 1;
$page = 1;

if ($schemaReady) {
    $prodRes = $conn->query("SELECT product_id, name FROM products WHERE status = 'active' ORDER BY name ASC");
    if ($prodRes) {
        while ($p = $prodRes->fetch_assoc()) {
            $products[] = $p;
        }
    }

    $statusFilter = trim($_GET['status'] ?? 'all');
    $allowedFilters = ['all', 'pending_approval', 'approved', 'rejected', 'ordered', 'shipped', 'delivered', 'completed'];
    if (!in_array($statusFilter, $allowedFilters, true)) {
        $statusFilter = 'all';
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;

    $searchQuery = trim($_GET['search'] ?? '');
    $filterProduct = (int)($_GET['filter_product'] ?? 0);

    $whereClauses = [];
    $params = [];
    $types = '';

    if ($statusFilter !== 'all') {
        $whereClauses[] = "po.status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    
    if ($searchQuery !== '') {
        $whereClauses[] = "(po.po_number LIKE ? OR s.name LIKE ?)";
        $searchLike = '%' . $searchQuery . '%';
        $params[] = $searchLike;
        $params[] = $searchLike;
        $types .= 'ss';
    }

    if ($filterProduct > 0) {
        $whereClauses[] = "EXISTS (SELECT 1 FROM purchase_order_items poiFilter WHERE poiFilter.po_id = po.po_id AND poiFilter.product_id = ?)";
        $params[] = $filterProduct;
        $types .= 'i';
    }

    $whereSql = count($whereClauses) > 0 ? "WHERE " . implode(' AND ', $whereClauses) : "";

    $countSql = "
        SELECT COUNT(po.po_id) AS total
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
        $whereSql
    ";

    if ($types !== '') {
        $stmtC = $conn->prepare($countSql);
        $stmtC->bind_param($types, ...$params);
        $stmtC->execute();
        $totalPOs = $stmtC->get_result()->fetch_assoc()['total'] ?? 0;
        $stmtC->close();
    } else {
        $totalPOs = $conn->query($countSql)->fetch_assoc()['total'] ?? 0;
    }

    $totalPages = max(1, ceil($totalPOs / $limit));

    $sql = "
        SELECT
            po.po_id,
            po.po_number,
            po.status,
            po.order_date,
            po.expected_delivery,
            po.grand_total,
            po.notes,
            po.rejection_reason,
            s.name AS supplier_name,
            c.username AS created_by_name,
            a.username AS approved_by_name,
            COUNT(poi.po_item_id) AS item_count,
            COALESCE(SUM(poi.ordered_qty), 0) AS total_qty,
            GROUP_CONCAT(CONCAT(p.name, ' (x', poi.ordered_qty, ')') SEPARATOR '||') AS item_details
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
        LEFT JOIN users c ON c.user_id = po.created_by
        LEFT JOIN users a ON a.user_id = po.approved_by
        LEFT JOIN purchase_order_items poi ON poi.po_id = po.po_id
        LEFT JOIN products p ON p.product_id = poi.product_id
        $whereSql
        GROUP BY
            po.po_id,
            po.po_number,
            po.status,
            po.order_date,
            po.expected_delivery,
            po.grand_total,
            po.notes,
            po.rejection_reason,
            s.name,
            c.username,
            a.username
        ORDER BY po.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $bindTypes = $types . 'ii';
    $bindParams = $params;
    $bindParams[] = $limit;
    $bindParams[] = $offset;
    
    if (!empty($bindParams)) {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
    
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    $countsRes = $conn->query("SELECT status, COUNT(*) AS cnt FROM purchase_orders GROUP BY status");
    $counts = ['all' => 0];
    if ($countsRes) {
        while ($c = $countsRes->fetch_assoc()) {
            $counts[$c['status']] = (int)$c['cnt'];
            $counts['all'] += (int)$c['cnt'];
        }
    }
} else {
    $statusFilter = 'all';
    $counts = ['all' => 0];
}

function po_badge(string $status): string {
    return match ($status) {
        'pending_approval' => 'bg-yellow-100 text-yellow-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'rejected' => 'bg-red-100 text-red-800',
        'ordered' => 'bg-indigo-100 text-indigo-800',
        'shipped' => 'bg-purple-100 text-purple-800',
        'delivered' => 'bg-emerald-100 text-emerald-800',
        'partially_received' => 'bg-orange-100 text-orange-800',
        'completed' => 'bg-green-100 text-green-800',
        default => 'bg-slate-100 text-slate-700',
    };
}

$page_title = 'PO Approval Center';
require_once '../../includes/admin_header.php';
?>

<main class="flex-1 overflow-y-auto p-8 bg-slate-50">
    <div class="mb-8 border-b border-slate-200 pb-4">
        <h1 class="text-3xl font-black text-slate-900 tracking-tight">PO Approval Center</h1>
        <p class="text-slate-500 mt-1">Admin controls for PO approval, rejection, and procurement monitoring.</p>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm <?php echo (($_GET['type'] ?? '') === 'error') ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'; ?>">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 text-amber-800">
            PO tables are not installed yet. Run <code>database/po_bo_tables.sql</code> first.
        </div>
    <?php else: ?>

    <div class="flex flex-wrap gap-2 mb-6">
        <?php foreach (['all','pending_approval','approved','rejected','ordered','shipped','delivered','completed'] as $f): ?>
            <a href="purchase_orders.php?status=<?php echo urlencode($f); ?>"
               class="px-3 py-2 rounded-lg text-xs font-bold transition <?php echo $statusFilter === $f ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'; ?>">
                <?php echo strtoupper(str_replace('_', ' ', $f)); ?>
                (<?php echo (int)($counts[$f] ?? 0); ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <form action="" method="GET" class="mb-6 flex flex-col sm:flex-row gap-2">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>" placeholder="Search PO or Supplier..." class="rounded border border-slate-300 px-3 py-2 text-sm w-full sm:w-64">
        
        <select name="filter_product" class="rounded border border-slate-300 px-3 py-2 text-sm w-full sm:w-64">
            <option value="">All Products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?php echo (int)$p['product_id']; ?>" <?php echo (int)$filterProduct === (int)$p['product_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="rounded-lg bg-blue-600 text-white px-4 py-2 text-sm font-semibold hover:bg-blue-700 w-full sm:w-auto">Filter</button>
        <?php if (!empty($_GET['search']) || !empty($_GET['filter_product'])): ?>
            <a href="purchase_orders.php?status=<?php echo urlencode($statusFilter); ?>" class="rounded-lg bg-slate-200 text-slate-700 px-4 py-2 text-sm font-semibold hover:bg-slate-300 w-full sm:w-auto text-center">Clear</a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">PO</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Summary</th>
                        <th class="px-4 py-3 text-left">Approver Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($rows) === 0): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-slate-500">No purchase orders found for this filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $po): ?>
                            <tr class="hover:bg-slate-50 align-top">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($po['po_number']); ?></div>
                                    <div class="text-xs text-slate-500 mt-1">Supplier: <?php echo htmlspecialchars($po['supplier_name'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-slate-400">Order Date: <?php echo htmlspecialchars($po['order_date']); ?></div>
                                    <?php if (!empty($po['expected_delivery'])): ?><div class="text-xs text-slate-400">ETA: <?php echo htmlspecialchars($po['expected_delivery']); ?></div><?php endif; ?>
                                    <?php if (!empty($po['rejection_reason'])): ?><div class="text-xs text-red-600 mt-1">Reject note: <?php echo htmlspecialchars($po['rejection_reason']); ?></div><?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase <?php echo po_badge($po['status']); ?>">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $po['status'])); ?>
                                    </span>
                                    <div class="text-xs text-slate-400 mt-2">Created by: <?php echo htmlspecialchars($po['created_by_name'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-slate-400">Approved by: <?php echo htmlspecialchars($po['approved_by_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <div class="mb-1 font-semibold text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded inline-block">
                                        <?php echo (int)$po['item_count']; ?> item(s) · <?php echo (int)$po['total_qty']; ?> total qty
                                    </div>
                                    <?php if (!empty($po['item_details'])): ?>
                                        <ul class="text-xs list-disc list-inside ml-2 mt-1 space-y-0.5 max-w-[250px]">
                                            <?php foreach(explode('||', $po['item_details']) as $detail): ?>
                                                <li><?php echo htmlspecialchars(trim($detail)); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <div class="mt-2 font-bold text-slate-900">₱<?php echo number_format((float)$po['grand_total'], 2); ?></div>
                                    <?php if (!empty($po['notes'])): ?><div class="text-xs text-slate-500 mt-1 italic"><?php echo htmlspecialchars($po['notes']); ?></div><?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($po['status'] === 'pending_approval'): ?>
                                        <div class="space-y-2">
                                            <form action="../../core/admin/po_approval.php" method="POST" class="inline">
                                                <input type="hidden" name="action" value="approve_po">
                                                <input type="hidden" name="po_id" value="<?php echo (int)$po['po_id']; ?>">
                                                <button class="w-full rounded bg-green-600 text-white px-3 py-2 text-xs font-bold hover:bg-green-700">Approve PO</button>
                                            </form>

                                            <form action="../../core/admin/po_approval.php" method="POST" class="space-y-1">
                                                <input type="hidden" name="action" value="reject_po">
                                                <input type="hidden" name="po_id" value="<?php echo (int)$po['po_id']; ?>">
                                                <input type="text" name="reason" placeholder="Reason for rejection" class="w-full rounded border border-slate-300 px-2 py-1 text-xs" required>
                                                <button class="w-full rounded bg-red-600 text-white px-3 py-2 text-xs font-bold hover:bg-red-700">Reject PO</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">No action required</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                <span class="text-sm text-slate-500">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&filter_product=<?php echo urlencode($filterProduct); ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm text-slate-700 hover:bg-slate-100">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&filter_product=<?php echo urlencode($filterProduct); ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm text-slate-700 hover:bg-slate-100">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</main>

<?php require_once '../../includes/admin_footer.php'; ?>
