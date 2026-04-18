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
$stats = ['pending' => 0, 'sent' => 0, 'resolved' => 0, 'total_qty' => 0, 'total_value' => 0.00];

if ($schemaReady) {
    // Get all returns with enhanced data
    $sql = "
        SELECT
            sr.return_id,
            sr.rejected_qty,
            sr.reason,
            sr.reason_notes,
            sr.status,
            sr.resolution_type,
            sr.credit_memo_amount,
            sr.return_notes,
            sr.created_at,
            sr.resolved_at,
            sr.sent_to_supplier_at,
            sr.received_by_supplier_at,
            po.po_number,
            poi.unit_cost,
            p.name AS product_name,
            p.sku,
            s.name AS supplier_name,
            u.username AS created_by_name
        FROM supplier_returns sr
        JOIN purchase_orders po ON po.po_id = sr.po_id
        JOIN purchase_order_items poi ON poi.po_item_id = sr.po_item_id
        JOIN products p ON p.product_id = sr.product_id
        JOIN suppliers s ON s.supplier_id = sr.supplier_id
        LEFT JOIN users u ON u.user_id = sr.created_by
        ORDER BY sr.created_at DESC
        LIMIT 200
    ";

    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $returns[] = $row;
        
        // Calculate stats
        $stats['total_qty'] += (int)$row['rejected_qty'];
        $stats['total_value'] += ((int)$row['rejected_qty'] * (float)$row['unit_cost']);
        
        if ($row['status'] === 'pending_return') $stats['pending']++;
        if ($row['status'] === 'returned_to_supplier') $stats['sent']++;
        if ($row['status'] === 'resolved') $stats['resolved']++;
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

function resolution_badge(string $type): string {
    return match ($type) {
        'replace' => 'bg-blue-100 text-blue-800 border border-blue-300',
        'credit_memo' => 'bg-amber-100 text-amber-800 border border-amber-300',
        'pending' => 'bg-slate-100 text-slate-700 border border-slate-300',
        default => 'bg-slate-50 text-slate-600 border border-slate-200',
    };
}

$page_title = 'Supplier Returns (BO)';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Bad Orders & Supplier Returns (RTV)</h1>
            <p class="text-sm text-slate-500 mt-1">Manage rejected items, return area inventory, and resolution tracking (Replace / Credit Memo).</p>
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
            Please run <code>database/po_bo_tables.sql</code> and <code>database/bad_orders_enhancement.sql</code> first.
        </div>
    <?php else: ?>

    <!-- ========================================
         STATISTICS DASHBOARD
         ======================================== -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500 uppercase font-bold tracking-wide">Pending Returns</div>
            <div class="text-3xl font-black text-yellow-600 mt-1"><?php echo $stats['pending']; ?></div>
            <div class="text-[11px] text-slate-400 mt-1">Awaiting shipment to supplier</div>
        </div>
        
        <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500 uppercase font-bold tracking-wide">Sent to Supplier</div>
            <div class="text-3xl font-black text-blue-600 mt-1"><?php echo $stats['sent']; ?></div>
            <div class="text-[11px] text-slate-400 mt-1">In transit / processing</div>
        </div>
        
        <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500 uppercase font-bold tracking-wide">Resolved</div>
            <div class="text-3xl font-black text-emerald-600 mt-1"><?php echo $stats['resolved']; ?></div>
            <div class="text-[11px] text-slate-400 mt-1">Closed or replaced</div>
        </div>
        
        <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500 uppercase font-bold tracking-wide">Total Impact</div>
            <div class="text-sm font-black text-red-600 mt-1"><?php echo number_format($stats['total_qty']); ?> units</div>
            <div class="text-[11px] text-slate-400 mt-1">₱<?php echo number_format($stats['total_value'], 2); ?></div>
        </div>
    </div>

    <!-- ========================================
         RETURN PROCESS FLOW GUIDE
         ======================================== -->
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold text-slate-900 mb-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-slate-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177A2.25 2.25 0 003.75 16.5h12a2.25 2.25 0 002.197-1.411l.081-.472a2.25 2.25 0 00-.735-2.307m-12.36 3.837h16.5a2.25 2.25 0 002.25-2.25V9m-12.36-9H5.25A2.25 2.25 0 003 11.25v11.25A2.25 2.25 0 005.25 24h13.5A2.25 2.25 0 0021 21.75V11.25a2.25 2.25 0 00-2.25-2.25H2.684A2.25 2.25 0 00.484 9m15.59.5H9" />
            </svg>
            Return Workflow & Resolution Options
        </h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Status Workflow -->
            <div class="space-y-2">
                <p class="text-sm font-semibold text-slate-700 mb-3">Status Progression:</p>
                <div class="flex items-center gap-2">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-yellow-100 text-yellow-700 font-bold flex items-center justify-center text-sm">1</div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-700">Pending Return</div>
                        <div class="text-xs text-slate-500">Items segregated in return area</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm">2</div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-700">Sent to Supplier</div>
                        <div class="text-xs text-slate-500">Return process initiated/pickup arranged</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-sm">3</div>
                    <div class="flex-1">
                        <div class="font-medium text-slate-700">Resolved</div>
                        <div class="text-xs text-slate-500">Replaced or credit applied</div>
                    </div>
                </div>
            </div>
            
            <!-- Resolution Options -->
            <div class="space-y-2">
                <p class="text-sm font-semibold text-slate-700 mb-3">Resolution Options:</p>
                <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
                    <div class="font-semibold text-blue-900 text-sm">🔄 Option A: Replace</div>
                    <div class="text-xs text-blue-700 mt-1">Supplier sends replacement stock immediately. New PO may be generated for tracking.</div>
                </div>
                <div class="p-3 rounded-lg bg-amber-50 border border-amber-200">
                    <div class="font-semibold text-amber-900 text-sm">📋 Option B: Credit Memo</div>
                    <div class="text-xs text-amber-700 mt-1">Amount deducted from next purchase order. Creates audit trail for financial reconciliation.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         RETURN AREA INVENTORY
         ======================================== -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-bold text-slate-800">📍 Return Area Inventory (Segregated Items)</h2>
            <span class="text-xs bg-yellow-100 text-yellow-800 font-bold px-2 py-1 rounded">Awaiting Return Processing</span>
        </div>
        <div class="p-5">
            <?php 
            $returnAreaItems = array_filter($returns, fn($r) => $r['status'] === 'pending_return');
            if (empty($returnAreaItems)): 
            ?>
                <p class="text-sm text-slate-500 py-4">No items currently in return area. All rejected items have been returned or resolved.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] font-bold">
                            <tr>
                                <th class="px-4 py-3 text-left">Product</th>
                                <th class="px-4 py-3 text-center">Qty in Return</th>
                                <th class="px-4 py-3 text-left">Reason</th>
                                <th class="px-4 py-3 text-left">Date Received</th>
                                <th class="px-4 py-3 text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($returnAreaItems as $item): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="text-xs text-slate-500">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-red-100 font-black text-red-700"><?php echo (int)$item['rejected_qty']; ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-bold uppercase bg-red-50 text-red-700 border border-red-200 px-2 py-1 rounded inline-block"><?php echo htmlspecialchars(str_replace('_', ' ', $item['reason'])); ?></span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    <?php echo date('M d, Y', strtotime($item['created_at'])); ?><br/>
                                    <span class="text-slate-400"><?php echo date('h:i A', strtotime($item['created_at'])); ?></span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 max-w-xs">
                                    <?php echo !empty($item['reason_notes']) ? htmlspecialchars($item['reason_notes']) : '—'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ========================================
         RETURN TO VENDOR QUEUE
         ======================================== -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-bold text-slate-800">Return to Vendor Queue (All Bad Orders)</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                    <tr>
                        <th class="px-4 py-3 text-left">PO / Item</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Resolution</th>
                        <th class="px-4 py-3 text-left">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($returns) === 0): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No rejected supplier items yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($returns as $row): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($row['po_number']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($row['product_name']); ?> (<?php echo htmlspecialchars($row['sku']); ?>)</div>
                                    <div class="text-[10px] text-slate-400 mt-1">Logged: <?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium text-sm"><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td class="px-4 py-3 text-center text-lg font-black text-red-600"><?php echo (int)$row['rejected_qty']; ?></td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-bold text-red-700 uppercase bg-red-50 border border-red-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars(str_replace('_', ' ', $row['reason'])); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase <?php echo return_badge($row['status']); ?>">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $row['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex text-[10px] font-bold px-2 py-1 rounded border <?php echo resolution_badge($row['resolution_type'] ?? 'pending'); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['resolution_type'] ?? 'pending'))); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <form action="../../core/inventory/po_actions.php" method="POST" class="flex flex-col gap-2">
                                        <input type="hidden" name="action" value="update_return_status">
                                        <input type="hidden" name="return_id" value="<?php echo (int)$row['return_id']; ?>">
                                        <select name="next_status" class="rounded border border-slate-300 px-2 py-1 text-xs bg-white">
                                            <option value="">- Select Status -</option>
                                            <option value="pending_return" <?php echo $row['status'] === 'pending_return' ? 'selected' : ''; ?>>Pending Return</option>
                                            <option value="returned_to_supplier" <?php echo $row['status'] === 'returned_to_supplier' ? 'selected' : ''; ?>>Sent to Supplier</option>
                                            <option value="resolved" <?php echo $row['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <select name="resolution_type" class="rounded border border-slate-300 px-2 py-1 text-xs bg-white">
                                            <option value="">- Select Resolution -</option>
                                            <option value="replace" <?php echo ($row['resolution_type'] ?? '') === 'replace' ? 'selected' : ''; ?>>Replace</option>
                                            <option value="credit_memo" <?php echo ($row['resolution_type'] ?? '') === 'credit_memo' ? 'selected' : ''; ?>>Credit Memo</option>
                                        </select>
                                        <button class="rounded bg-blue-600 text-white px-3 py-1 text-xs font-semibold hover:bg-blue-700 transition">Update</button>
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
