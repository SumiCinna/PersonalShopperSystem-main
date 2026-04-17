<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header('Location: ../../inventory-login.php');
    exit();
}

function table_exists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

$schemaReady = table_exists($conn, 'supplier_returns') && table_exists($conn, 'purchase_orders');
$returns = [];

if ($schemaReady) {
    $sql = "
        SELECT
            sr.return_id,
            sr.rejected_qty,
            sr.reason,
            sr.reason_notes,
            sr.status,
            sr.created_at,
            sr.resolved_at,
            po.po_number,
            p.name AS product_name,
            p.sku,
            s.name AS supplier_name,
            u.username AS created_by_name
        FROM supplier_returns sr
        JOIN purchase_orders po ON po.po_id = sr.po_id
        JOIN products p ON p.product_id = sr.product_id
        JOIN suppliers s ON s.supplier_id = sr.supplier_id
        LEFT JOIN users u ON u.user_id = sr.created_by
        ORDER BY sr.created_at DESC
        LIMIT 200
    ";

    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $returns[] = $row;
    }
}

function return_badge(string $status): string {
    return match ($status) {
        'pending_return' => 'bg-yellow-100 text-yellow-800',
        'returned_to_supplier' => 'bg-blue-100 text-blue-800',
        'resolved' => 'bg-green-100 text-green-800',
        default => 'bg-slate-100 text-slate-700',
    };
}

$page_title = 'Supplier Returns (BO)';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Supplier Return Process (BO / RTV)</h1>
            <p class="text-sm text-slate-500 mt-1">Tracks rejected PO items (expired, damaged, wrong item, near expiry) and return lifecycle.</p>
        </div>
        <a href="purchase_orders.php" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition">← Back to PO</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm <?php echo (($_GET['type'] ?? '') === 'error') ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'; ?>">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 text-amber-800">
            Please run <code>database/po_bo_tables.sql</code> first.
        </div>
    <?php else: ?>

    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-bold text-slate-800">Return to Vendor Queue</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">PO / Item</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-center">Rejected Qty</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Update</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($returns) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No rejected supplier items yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($returns as $row): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($row['po_number']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($row['product_name']); ?> (<?php echo htmlspecialchars($row['sku']); ?>)</div>
                                    <div class="text-xs text-slate-400 mt-1">Logged: <?php echo htmlspecialchars($row['created_at']); ?></div>
                                </td>
                                <td class="px-4 py-3 text-slate-700"><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td class="px-4 py-3 text-center font-bold text-red-700"><?php echo (int)$row['rejected_qty']; ?></td>
                                <td class="px-4 py-3 text-slate-700">
                                    <span class="text-xs uppercase font-semibold"><?php echo htmlspecialchars(str_replace('_', ' ', $row['reason'])); ?></span>
                                    <?php if (!empty($row['reason_notes'])): ?><div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($row['reason_notes']); ?></div><?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase <?php echo return_badge($row['status']); ?>">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $row['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <form action="../../core/inventory/po_actions.php" method="POST" class="flex gap-2 items-center">
                                        <input type="hidden" name="action" value="update_return_status">
                                        <input type="hidden" name="return_id" value="<?php echo (int)$row['return_id']; ?>">
                                        <select name="next_status" class="rounded border border-slate-300 px-2 py-1 text-xs">
                                            <option value="pending_return" <?php echo $row['status'] === 'pending_return' ? 'selected' : ''; ?>>Pending Return</option>
                                            <option value="returned_to_supplier" <?php echo $row['status'] === 'returned_to_supplier' ? 'selected' : ''; ?>>Returned to Supplier</option>
                                            <option value="resolved" <?php echo $row['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <button class="rounded bg-slate-800 text-white px-3 py-1 text-xs font-semibold hover:bg-slate-900">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php endif; ?>
</main>

<?php require_once '../../includes/inventory_footer.php'; ?>
