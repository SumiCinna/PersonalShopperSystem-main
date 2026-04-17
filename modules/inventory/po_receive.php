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

$schemaReady = table_exists($conn, 'purchase_orders') && table_exists($conn, 'purchase_order_items') && table_exists($conn, 'po_receivings');

$poId = (int)($_GET['po_id'] ?? 0);
$po = null;
$poItems = [];
$receivingHistory = [];

if ($schemaReady && $poId > 0) {
    $poStmt = $conn->prepare(
        "SELECT po.po_id, po.po_number, po.status, po.order_date, po.expected_delivery, po.notes, po.grand_total,
                s.name AS supplier_name
         FROM purchase_orders po
         LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
         WHERE po.po_id = ? LIMIT 1"
    );
    $poStmt->bind_param('i', $poId);
    $poStmt->execute();
    $po = $poStmt->get_result()->fetch_assoc();
    $poStmt->close();

    if ($po) {
        $itemsStmt = $conn->prepare(
            "SELECT poi.po_item_id, poi.product_id, poi.ordered_qty, poi.received_qty, poi.rejected_qty, poi.unit_cost,
                    p.name AS product_name, p.sku, p.category
             FROM purchase_order_items poi
             JOIN products p ON p.product_id = poi.product_id
             WHERE poi.po_id = ?
             ORDER BY poi.po_item_id ASC"
        );
        $itemsStmt->bind_param('i', $poId);
        $itemsStmt->execute();
        $itemsRes = $itemsStmt->get_result();
        while ($row = $itemsRes->fetch_assoc()) {
            $poItems[] = $row;
        }
        $itemsStmt->close();

        $historyStmt = $conn->prepare(
            "SELECT pr.receiving_id, pr.received_at, pr.remarks, u.username AS received_by_name
             FROM po_receivings pr
             LEFT JOIN users u ON u.user_id = pr.received_by
             WHERE pr.po_id = ?
             ORDER BY pr.received_at DESC"
        );
        $historyStmt->bind_param('i', $poId);
        $historyStmt->execute();
        $historyRes = $historyStmt->get_result();

        // Fetch received items to map to history
        $receivedItems = [];
        $rItemsStmt = $conn->prepare("
            SELECT pri.receiving_id, pri.received_qty, pri.accepted_qty, pri.rejected_qty,
                   p.name AS product_name, p.sku
            FROM po_receiving_items pri
            JOIN po_receivings pr ON pr.receiving_id = pri.receiving_id
            JOIN products p ON p.product_id = pri.product_id
            WHERE pr.po_id = ?
        ");
        if ($rItemsStmt) {
            $rItemsStmt->bind_param('i', $poId);
            $rItemsStmt->execute();
            $rRes = $rItemsStmt->get_result();
            while($rRow = $rRes->fetch_assoc()) {
                $receivedItems[$rRow['receiving_id']][] = $rRow;
            }
            $rItemsStmt->close();
        }

        while ($row = $historyRes->fetch_assoc()) {
            $row['items'] = $receivedItems[$row['receiving_id']] ?? [];
            $receivingHistory[] = $row;
        }
        $historyStmt->close();
    }
}

$page_title = 'PO Receiving & Inspection';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Receiving Module and Quality Inspection</h1>
            <p class="text-sm text-slate-500 mt-1">Step 2-4 flow: receive delivery → inspect items → update stock (accepted) + BO return (rejected).</p>
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
    <?php elseif (!$po): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-slate-600">
            <?php if ($poId <= 0): ?>
                Select a PO from <a class="text-blue-600 font-semibold" href="purchase_orders.php">PO Tracker</a> to begin receiving.
            <?php else: ?>
                Purchase order not found.
            <?php endif; ?>
        </div>
    <?php else: ?>

    <section class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-col lg:flex-row lg:justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-800"><?php echo htmlspecialchars($po['po_number']); ?></h2>
                <p class="text-sm text-slate-500">Supplier: <?php echo htmlspecialchars($po['supplier_name'] ?? 'Unknown'); ?> · Status: <span class="font-semibold"><?php echo htmlspecialchars(str_replace('_', ' ', $po['status'])); ?></span></p>
            </div>
            <div class="text-sm text-slate-500">
                <div>Order Date: <?php echo htmlspecialchars($po['order_date']); ?></div>
                <?php if (!empty($po['expected_delivery'])): ?><div>Expected: <?php echo htmlspecialchars($po['expected_delivery']); ?></div><?php endif; ?>
                <div class="font-bold text-slate-800">Total: ₱<?php echo number_format((float)$po['grand_total'], 2); ?></div>
            </div>
        </div>

        <form action="../../core/inventory/po_actions.php" method="POST" class="p-5">
            <input type="hidden" name="action" value="receive_items">
            <input type="hidden" name="po_id" value="<?php echo (int)$po['po_id']; ?>">

            <?php if (!in_array($po['status'], ['approved', 'ordered', 'shipped', 'delivered', 'partially_received'], true)): ?>
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 text-sm mb-4">
                    This PO is currently <b><?php echo htmlspecialchars($po['status']); ?></b>. Receiving is allowed only for approved and in-transit orders.
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <?php foreach ($poItems as $index => $item): ?>
                    <?php 
                    $pending = max(((int)$item['ordered_qty'] - (int)$item['received_qty']), 0); 
                    // Auto-fill batch number using date and random hex string
                    $defaultBatch = 'RCV-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                    ?>
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50 relative overflow-hidden shadow-sm">
                        <!-- Item Header -->
                        <div class="flex justify-between items-start md:items-center mb-4 pb-3 border-b border-slate-200">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                <p class="text-sm text-slate-500">SKU: <?php echo htmlspecialchars($item['sku']); ?></p>
                                <input type="hidden" name="po_item_id[]" value="<?php echo (int)$item['po_item_id']; ?>">
                            </div>
                            <div class="text-right text-sm">
                                <div class="text-slate-500">Ordered: <span class="font-bold text-slate-800"><?php echo (int)$item['ordered_qty']; ?></span></div>
                                <div class="text-slate-500">Already Received: <span class="font-bold text-emerald-600"><?php echo (int)$item['received_qty']; ?></span></div>
                            </div>
                        </div>

                        <!-- Inspection Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            
                            <!-- Delivered Block -->
                            <div class="bg-white p-3 rounded-lg border border-slate-300 shadow-sm flex flex-col justify-center transition-colors focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100">
                                <label class="block text-[11px] font-bold text-slate-500 tracking-wider uppercase mb-1">Delivered Qty</label>
                                <input type="number" min="0" max="<?php echo (int)$pending; ?>" value="<?php echo (int)$pending; ?>" name="received_qty[]" class="w-full rounded border-0 border-b-2 border-slate-200 px-2 py-2 text-2xl font-black text-blue-900 bg-transparent text-center focus:ring-0 focus:border-blue-500 delivered-input" <?php echo $pending === 0 ? 'readonly' : ''; ?>>
                                <p class="text-[10px] text-slate-400 text-center mt-2">Physical count off the truck</p>
                            </div>

                            <!-- Accepted Block -->
                            <div class="bg-white p-3 rounded-lg border border-emerald-200 shadow-sm flex flex-col gap-2 transition-colors focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-100 relative">
                                <div class="absolute top-0 right-0 bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-bl-lg rounded-tr-lg">GOOD</div>
                                <div>
                                    <label class="flex items-center text-[11px] font-bold text-emerald-700 tracking-wider uppercase mb-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3.5 h-3.5 mr-1 text-emerald-600"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        Accepted Qty
                                    </label>
                                    <input type="number" min="0" max="<?php echo (int)$pending; ?>" value="<?php echo (int)$pending; ?>" name="accepted_qty[]" class="w-full rounded border-0 border-b-2 border-emerald-200 px-2 py-2 text-2xl font-black text-emerald-900 bg-transparent text-center focus:ring-0 focus:border-emerald-500 accepted-input" <?php echo $pending === 0 ? 'readonly' : ''; ?>>
                                </div>
                                <div class="grid grid-cols-2 gap-2 mt-auto">
                                    <div class="<?php echo ($item['category'] === 'Fresh Produce') ? 'col-span-2' : ''; ?>">
                                        <label class="block text-[10px] uppercase text-emerald-800 font-semibold mb-1">Batch #</label>
                                        <input type="text" name="batch_number[]" value="<?php echo $defaultBatch; ?>" class="w-full rounded border border-emerald-200 bg-emerald-50 px-2 py-1.5 text-xs focus:ring-0 text-slate-500 cursor-not-allowed" placeholder="Batch #" readonly>
                                    </div>
                                    <?php if ($item['category'] !== 'Fresh Produce'): ?>
                                    <div>
                                        <label class="block text-[10px] uppercase text-emerald-800 font-semibold mb-1">Expiry Date</label>
                                        <input type="date" name="expiry_date[]" class="w-full rounded border border-emerald-200 bg-emerald-50 px-2 py-1.5 text-xs text-slate-700 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    <?php else: ?>
                                        <input type="hidden" name="expiry_date[]" value="">
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Rejected Block -->
                            <div class="bg-white p-3 rounded-lg border border-red-200 shadow-sm flex flex-col gap-2 transition-colors focus-within:border-red-400 focus-within:ring-2 focus-within:ring-red-100 relative">
                                <div class="absolute top-0 right-0 bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-bl-lg rounded-tr-lg">DEFECTIVE</div>
                                <div>
                                    <label class="flex items-center text-[11px] font-bold text-red-700 tracking-wider uppercase mb-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3.5 h-3.5 mr-1 text-red-600"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        Rejected Qty
                                    </label>
                                    <input type="number" min="0" max="<?php echo (int)$pending; ?>" value="0" name="rejected_qty[]" class="w-full rounded border-0 border-b-2 border-red-200 px-2 py-2 text-2xl font-black text-red-900 bg-transparent text-center focus:ring-0 focus:border-red-500 rejected-input" <?php echo $pending === 0 ? 'readonly' : ''; ?>>
                                </div>
                                <div class="mt-auto">
                                    <label class="block text-[10px] uppercase text-red-800 font-semibold mb-1">Reject Reason</label>
                                    <select name="reject_reason[]" class="w-full rounded border border-red-200 bg-red-50 px-2 py-1.5 text-xs text-slate-700 focus:ring-red-500 focus:border-red-500">
                                        <?php if ($item['category'] === 'Fresh Produce'): ?>
                                            <option value="expired">Perished</option>
                                        <?php else: ?>
                                            <option value="expired">Expired</option>
                                            <option value="near_expiry">Near Expiry</option>
                                        <?php endif; ?>
                                        <option value="damaged_packaging">Damaged Packaging</option>
                                        <option value="wrong_item">Wrong Item</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 border-t border-slate-200 pt-5">
                <label class="text-sm font-semibold text-slate-700 block mb-1">Receiving Remarks</label>
                <textarea name="remarks" rows="2" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Optional receiving notes"></textarea>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition" <?php echo !in_array($po['status'], ['approved', 'ordered', 'shipped', 'delivered', 'partially_received'], true) ? 'disabled' : ''; ?>>
                    Save Receiving + Run Inspection Logic
                </button>
            </div>
        </form>
    </section>

    <section class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-bold text-slate-800">Receiving History</h2>
        </div>
        <div class="p-5">
            <?php if (count($receivingHistory) === 0): ?>
                <p class="text-sm text-slate-500">No receiving transactions yet for this PO.</p>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($receivingHistory as $history): ?>
                        <li class="rounded-lg border border-slate-200 overflow-hidden shadow-sm">
                            <details class="group">
                                <summary class="flex items-center justify-between cursor-pointer bg-slate-50 px-4 py-3 hover:bg-slate-100 transition">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Receiving #<?php echo (int)$history['receiving_id']; ?> 
                                            <span class="text-xs font-medium text-slate-500 ml-2 font-mono bg-slate-200 px-2 py-0.5 rounded"><?php echo htmlspecialchars($history['received_at']); ?></span>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1 pl-6">Handled by: <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($history['received_by_name'] ?? 'N/A'); ?></span></div>
                                    </div>
                                    <div class="text-slate-400 group-open:rotate-180 transition-transform duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                    </div>
                                </summary>
                                <div class="px-4 py-3 bg-white border-t border-slate-200">
                                    <?php if (!empty($history['remarks'])): ?>
                                        <div class="text-xs text-slate-600 mb-3 p-2 bg-amber-50 border border-amber-100 rounded-md">
                                            <span class="font-bold text-amber-800">Remarks:</span> <?php echo nl2br(htmlspecialchars($history['remarks'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($history['items'])): ?>
                                        <div class="overflow-x-auto rounded border border-slate-200">
                                            <table class="w-full text-left text-xs text-slate-600">
                                                <thead class="bg-slate-100 text-slate-700 uppercase font-semibold">
                                                    <tr>
                                                        <th class="px-3 py-2">Product</th>
                                                        <th class="px-3 py-2 text-center w-24">Delivered</th>
                                                        <th class="px-3 py-2 text-center w-24 text-emerald-600">Accepted</th>
                                                        <th class="px-3 py-2 text-center w-24 text-red-600">Rejected</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <?php foreach ($history['items'] as $hItem): ?>
                                                    <tr class="hover:bg-slate-50">
                                                        <td class="px-3 py-2 font-medium text-slate-800"><?php echo htmlspecialchars($hItem['product_name']); ?> <span class="text-slate-400 font-normal ml-1">(<?php echo htmlspecialchars($hItem['sku']); ?>)</span></td>
                                                        <td class="px-3 py-2 text-center font-bold text-slate-600"><?php echo (int)$hItem['received_qty']; ?></td>
                                                        <td class="px-3 py-2 text-center font-bold text-emerald-600"><?php echo (int)$hItem['accepted_qty']; ?></td>
                                                        <td class="px-3 py-2 text-center font-bold text-red-600"><?php echo (int)$hItem['rejected_qty']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-xs text-slate-500 italic">No specific item data recorded for this batch.</p>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <?php endif; ?>
</main>

<?php require_once '../../includes/inventory_footer.php'; ?>

<script>
document.querySelectorAll('.space-y-4 > div').forEach(container => {
    const delivered = container.querySelector('.delivered-input');
    const accepted = container.querySelector('.accepted-input');
    const rejected = container.querySelector('.rejected-input');

    if (!delivered || !accepted || !rejected) return;

    function syncValues() {
        const d = Number(delivered.value || 0);
        let a = Number(accepted.value || 0);
        let r = Number(rejected.value || 0);

        if ((a + r) > d) {
            if (document.activeElement === accepted) {
                // User is changing accepted, force rejected to match remainder.
                r = Math.max(d - a, 0);
                rejected.value = r;
            } else if (document.activeElement === rejected) {
                // User is changing rejected, force accepted to match remainder.
                a = Math.max(d - r, 0);
                accepted.value = a;
            } else {
                // Fallback, re-adjust accepted.
                a = Math.max(d - r, 0);
                accepted.value = a;
            }
        }
        
        // Add visual validation warning if they somehow go over
        if ((a + r) > d) {
            delivered.classList.add('text-red-600');
        } else {
            delivered.classList.remove('text-red-600');
        }
    }

    delivered.addEventListener('input', () => {
        accepted.value = delivered.value;
        rejected.value = 0;
        syncValues();
    });
    
    accepted.addEventListener('input', syncValues);
    rejected.addEventListener('input', syncValues);
});
</script>
