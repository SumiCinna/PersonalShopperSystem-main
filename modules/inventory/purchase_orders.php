<?php
session_start();
require_once '../../config/config.php';
// modules/inventory/purchase_orders.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header('Location: ../../inventory-login.php');
    exit();
}

function table_exists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

$requiredTables = ['suppliers', 'purchase_orders', 'purchase_order_items'];
$schemaReady = true;
foreach ($requiredTables as $table) {
    if (!table_exists($conn, $table)) {
        $schemaReady = false;
        break;
    }
}

$suppliers = [];
$products = [];
$purchaseOrders = [];

if ($schemaReady) {
    $suppliersRes = $conn->query("SELECT supplier_id, name, contact_person, phone, supplied_categories FROM suppliers WHERE status = 'active' ORDER BY name ASC");
    while ($row = $suppliersRes->fetch_assoc()) {
        $suppliers[] = $row;
    }

    $productsSql = "
        SELECT
            p.product_id,
            p.name,
            p.sku,
            p.stock,
            p.category,
            p.low_stock_threshold,
            p.cost_price,
            COALESCE(p.pcs_per_box, 1) AS pcs_per_box,
            CASE
                WHEN p.stock <= p.low_stock_threshold THEN GREATEST((p.low_stock_threshold - p.stock), 1)
                ELSE 1
            END AS suggested_qty
        FROM products p
        WHERE p.status = 'active'
        ORDER BY (p.stock <= p.low_stock_threshold) DESC, p.stock ASC, p.name ASC
    ";

    $productsRes = $conn->query($productsSql);
    while ($row = $productsRes->fetch_assoc()) {
        $products[] = $row;
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;

    $searchQuery = trim($_GET['search'] ?? '');
    $filterProduct = (int)($_GET['filter_product'] ?? 0);

    $whereClauses = [];
    $params = [];
    $types = '';

    if ($searchQuery !== '') {
        $whereClauses[] = "(po.po_number LIKE ? OR s.name LIKE ?)";
        $searchQueryLike = '%' . $searchQuery . '%';
        $params[] = $searchQueryLike;
        $params[] = $searchQueryLike;
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
        $totalPOs = $stmtC->get_result()->fetch_assoc()['total'];
        $stmtC->close();
    } else {
        $totalPOs = $conn->query($countSql)->fetch_assoc()['total'];
    }

    $totalPages = max(1, ceil($totalPOs / $limit));

    $poSql = "
        SELECT
            po.po_id,
            po.po_number,
            po.status,
            po.order_date,
            po.expected_delivery,
            po.grand_total,
            po.notes,
            po.rejection_reason,
            po.created_at,
            s.name AS supplier_name,
            u1.username AS created_by_name,
            u2.username AS approved_by_name,
            COUNT(poi.po_item_id) AS item_count,
            COALESCE(SUM(poi.ordered_qty), 0) AS total_qty,
            GROUP_CONCAT(
                CONCAT(
                    p.name, 
                    ' (', 
                    CASE 
                        WHEN poi.order_type = 'wholesale' THEN CONCAT(poi.box_quantity, ' box', IF(poi.box_quantity > 1, 'es', ''), ' / ', p.pcs_per_box, ' pcs per box = ', poi.ordered_qty, ' pcs')
                        ELSE CONCAT(poi.ordered_qty, ' pcs') 
                    END,
                    ')'
                ) 
                SEPARATOR '||'
            ) AS item_details
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
        LEFT JOIN users u1 ON u1.user_id = po.created_by
        LEFT JOIN users u2 ON u2.user_id = po.approved_by
        LEFT JOIN purchase_order_items poi ON poi.po_id = po.po_id
        LEFT JOIN products p ON p.product_id = poi.product_id
        $whereSql
        GROUP BY
            po.po_id, po.po_number, po.status, po.order_date, po.expected_delivery,
            po.grand_total, po.notes, po.rejection_reason, po.created_at,
            s.name, u1.username, u2.username
        ORDER BY po.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;

    $stmtP = $conn->prepare($poSql);
    $stmtP->bind_param($types, ...$params);
    $stmtP->execute();
    $poRes = $stmtP->get_result();
    
    while ($row = $poRes->fetch_assoc()) {
        $purchaseOrders[] = $row;
    }
    $stmtP->close();
}

function po_badge(string $status): string {
    return match ($status) {
        'pending_approval'   => 'bg-yellow-100 text-yellow-800',
        'approved'           => 'bg-blue-100 text-blue-800',
        'rejected'           => 'bg-red-100 text-red-800',
        'ordered'            => 'bg-indigo-100 text-indigo-800',
        'shipped'            => 'bg-purple-100 text-purple-800',
        'delivered'          => 'bg-emerald-100 text-emerald-800',
        'partially_received' => 'bg-orange-100 text-orange-800',
        'completed'          => 'bg-green-100 text-green-800',
        default              => 'bg-slate-100 text-slate-700',
    };
}

$page_title = 'PO & BO Workflow';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Purchase Orders (PO) & BO</h1>
            <p class="text-sm text-slate-500 mt-1">Create PO from low stock, track supplier flow, and quality check.</p>
        </div>
        <div class="flex gap-2">
            <a href="add_supplier.php" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">+ Add Supplier</a>
            <!-- ✅ OPEN PO MODAL BUTTON -->
            <button onclick="openPOModal()" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Purchase Order
            </button>
            <a href="supplier_returns.php" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition">Supplier Returns (BO)</a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm <?php echo (($_GET['type'] ?? '') === 'error') ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'; ?>">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-6">
            <h2 class="text-lg font-bold text-amber-900">PO/BO tables are not yet installed</h2>
            <p class="text-sm text-amber-800 mt-2">Run the SQL file <code>database/po_bo_tables.sql</code> in your database first, then refresh this page.</p>
        </div>
    <?php else: ?>

    <!-- ===== PO TRACKER TABLE (full width now that form is in modal) ===== -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-800">PO Tracker</h2>
            </div>
            
            <form action="" method="GET" class="flex flex-col sm:flex-row gap-2 items-center">
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>" placeholder="Search PO or Supplier..." class="rounded border border-slate-300 px-3 py-1.5 text-sm w-full sm:w-48">
                
                <select name="filter_product" class="rounded border border-slate-300 px-3 py-1.5 text-sm w-full sm:w-48 max-w-xs">
                    <option value="">All Products</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo (int)$p['product_id']; ?>" <?php echo (int)$filterProduct === (int)$p['product_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="rounded-lg bg-blue-600 text-white px-4 py-1.5 text-sm font-semibold hover:bg-blue-700 w-full sm:w-auto">Filter</button>
                <?php if (!empty($_GET['search']) || !empty($_GET['filter_product'])): ?>
                    <a href="purchase_orders.php" class="rounded-lg bg-slate-200 text-slate-700 px-3 py-1.5 text-sm font-semibold hover:bg-slate-300 w-full sm:w-auto text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">PO / Supplier</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Items</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($purchaseOrders) === 0): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No purchase orders yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchaseOrders as $po): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 align-top">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($po['po_number']); ?></div>
                                    <div class="text-slate-500 text-xs"><?php echo htmlspecialchars($po['supplier_name'] ?? 'Unknown Supplier'); ?></div>
                                    <div class="text-slate-400 text-xs mt-1">
                                        Ordered: <?php echo htmlspecialchars($po['order_date']); ?>
                                        <?php if (!empty($po['expected_delivery'])): ?> · ETA: <?php echo htmlspecialchars($po['expected_delivery']); ?><?php endif; ?>
                                    </div>
                                    <?php if (!empty($po['rejection_reason'])): ?>
                                        <div class="mt-1 text-xs text-red-600">Reject note: <?php echo htmlspecialchars($po['rejection_reason']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase <?php echo po_badge($po['status']); ?>">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $po['status'])); ?>
                                    </span>
                                    <div class="text-xs text-slate-400 mt-2">By: <?php echo htmlspecialchars($po['created_by_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-600">
                                    <div class="mb-1 font-semibold text-slate-800 text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded inline-block">
                                        <?php echo (int)$po['item_count']; ?> item(s) · <?php echo (int)$po['total_qty']; ?> total qty
                                    </div>
                                    <?php if (!empty($po['item_details'])): ?>
                                        <ul class="text-xs list-disc list-inside ml-3 mt-1 space-y-0.5 max-w-xs">
                                            <?php foreach(explode('||', $po['item_details']) as $detail): ?>
                                                <li><?php echo htmlspecialchars(trim($detail)); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-top text-right font-bold text-slate-800">₱<?php echo number_format((float)$po['grand_total'], 2); ?></td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex flex-col gap-2">
                                        <?php if (in_array($po['status'], ['approved', 'ordered', 'shipped', 'delivered', 'partially_received'], true)): ?>
                                            <a href="po_receive.php?po_id=<?php echo (int)$po['po_id']; ?>" class="text-xs px-3 py-1 rounded bg-emerald-100 text-emerald-700 font-semibold hover:bg-emerald-200 text-center">Receive / Inspect</a>
                                        <?php elseif ($po['status'] === 'completed'): ?>
                                            <a href="po_receive.php?po_id=<?php echo (int)$po['po_id']; ?>" class="text-xs px-3 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200 text-center">View Receiving</a>
                                        <?php endif; ?>

                                        <?php if (in_array($po['status'], ['approved', 'ordered', 'shipped', 'delivered', 'partially_received'], true)): ?>
                                            <form action="../../core/inventory/po_actions.php" method="POST" class="flex gap-1">
                                                <input type="hidden" name="action" value="update_po_status">
                                                <input type="hidden" name="po_id" value="<?php echo (int)$po['po_id']; ?>">
                                                <select name="next_status" class="text-xs rounded border border-slate-300 px-2 py-1">
                                                    <option value="ordered">Ordered</option>
                                                    <option value="shipped">Shipped</option>
                                                    <option value="delivered">Delivered</option>
                                                </select>
                                                <button class="text-xs px-2 py-1 rounded bg-slate-800 text-white hover:bg-slate-900">Set</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="px-5 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                <span class="text-sm text-slate-500">Showing page <?php echo $page; ?></span>
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&filter_product=<?php echo urlencode($filterProduct); ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm text-slate-700 hover:bg-slate-100">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-3 py-1 bg-slate-800 border border-slate-800 rounded text-sm text-white"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>&filter_product=<?php echo urlencode($filterProduct); ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm text-slate-700 hover:bg-slate-100"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&filter_product=<?php echo urlencode($filterProduct); ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm text-slate-700 hover:bg-slate-100">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php endif; ?>
</main>


<!-- ============================================================ -->
<!-- ✅  CREATE PURCHASE ORDER MODAL                              -->
<!-- ============================================================ -->
<div id="poModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" onclick="closePOModal()"></div>

    <!-- Panel -->
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col" style="max-height:90vh;">

            <!-- Modal Header -->
            <div class="bg-slate-900 rounded-t-2xl px-6 py-4 flex justify-between items-center flex-shrink-0">
                <div>
                    <h2 class="text-white font-black text-lg flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Create Purchase Order
                    </h2>
                    <p class="text-slate-400 text-xs mt-0.5">Flow: Low Stock → Create PO → Admin Approval → Supplier Fulfillment</p>
                </div>
                <button onclick="closePOModal()" class="text-slate-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Body (scrollable) -->
            <div class="overflow-y-auto flex-1">
                <form action="../../core/inventory/po_actions.php" method="POST" id="poForm" class="p-6 space-y-5">
                    <input type="hidden" name="action" value="create_po">

                    <!-- Supplier -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                        <select name="supplier_id" id="modal_supplier_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="">Select supplier...</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo (int)$supplier['supplier_id']; ?>">
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Expected Delivery -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Expected Delivery Date</label>
                        <?php $minDate = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'); ?>
                        <input type="date" name="expected_delivery" min="<?php echo $minDate; ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">PO Notes <span class="text-xs text-slate-400">(max 100 chars)</span></label>
                        <textarea name="notes" rows="2" maxlength="100" placeholder="Optional instructions for supplier..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                    </div>

                    <!-- PO Items -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <!-- Column Headers -->
                        <div class="grid grid-cols-12 gap-2 px-3 py-2 bg-slate-100 border-b border-slate-200 text-xs font-bold text-slate-600 uppercase tracking-wide">
                            <div class="col-span-4">Product</div>
                            <div class="col-span-2 text-center">Type</div>
                            <div class="col-span-2 text-center">Qty</div>
                            <div class="col-span-3 text-right">Unit Cost</div>
                            <div class="col-span-1"></div>
                        </div>

                        <div id="poItemsContainer" class="p-3 space-y-3 min-h-[60px]">
                            <p id="noItemsMsg" class="text-xs text-slate-400 italic text-center py-2">Select a supplier, then click "+ Add Item"</p>
                        </div>

                        <!-- Modal Pagination Controls -->
                        <div id="poModalPagination" class="px-3 py-2 border-t border-slate-200 bg-white flex justify-center gap-1 hidden"></div>

                        <div class="px-3 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                            <button type="button" id="addItemBtn" class="text-sm font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Item
                            </button>
                            <div class="text-right">
                                <span class="text-xs text-slate-500">Grand Total</span>
                                <div class="font-black text-slate-900 text-lg">₱<span id="poSubtotal">0.00</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full rounded-xl bg-emerald-600 text-white py-3 text-sm font-bold hover:bg-emerald-700 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Submit PO for Admin Approval
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- JS                                                            -->
<!-- ============================================================ -->
<script>
// ─── Data from PHP ────────────────────────────────────────────────────────────
const products  = <?php echo json_encode($products,  JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const suppliers = <?php echo json_encode($suppliers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

// ─── Modal helpers ────────────────────────────────────────────────────────────
function openPOModal() {
    document.getElementById('poModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closePOModal() {
    document.getElementById('poModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// ─── DOM refs ─────────────────────────────────────────────────────────────────
const supplierSel  = document.getElementById('modal_supplier_id');
const container    = document.getElementById('poItemsContainer');
const addBtn       = document.getElementById('addItemBtn');
const subtotalEl   = document.getElementById('poSubtotal');
const noItemsMsg   = document.getElementById('noItemsMsg');
const paginationContainer = document.getElementById('poModalPagination');

// ─── Modal Pagination variables ───────────────────────────────────────────────
let modalCurrentPage = 1;
const itemsPerPage = 5;

function updateModalPagination() {
    const rows = Array.from(container.querySelectorAll('.po-row'));
    const totalFiltered = rows.length;
    if (totalFiltered === 0) {
        paginationContainer.classList.add('hidden');
        paginationContainer.innerHTML = '';
        return;
    }
    
    // Ensure current page is valid
    const totalPages = Math.max(1, Math.ceil(totalFiltered / itemsPerPage));
    if (modalCurrentPage > totalPages) {
        modalCurrentPage = totalPages;
    }

    // Toggle row display
    rows.forEach((row, index) => {
        if (index >= (modalCurrentPage - 1) * itemsPerPage && index < modalCurrentPage * itemsPerPage) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Build pagination buttons
    if (totalFiltered > itemsPerPage) {
        paginationContainer.classList.remove('hidden');
        let html = '';
        
        const prevDisabled = modalCurrentPage === 1;
        html += `<button type="button" class="px-2 py-1 text-xs border rounded ${prevDisabled ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-white text-slate-700 hover:bg-slate-50'}" ${prevDisabled ? 'disabled' : ''} onclick="changeModalPage(${modalCurrentPage - 1})">Prev</button>`;
        
        for(let i = 1; i <= totalPages; i++) {
            const active = modalCurrentPage === i;
            html += `<button type="button" class="px-2 py-1 text-xs border rounded ${active ? 'bg-emerald-600 border-emerald-600 text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-50'}" onclick="changeModalPage(${i})">${i}</button>`;
        }

        const nextDisabled = modalCurrentPage === totalPages;
        html += `<button type="button" class="px-2 py-1 text-xs border rounded ${nextDisabled ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-white text-slate-700 hover:bg-slate-50'}" ${nextDisabled ? 'disabled' : ''} onclick="changeModalPage(${modalCurrentPage + 1})">Next</button>`;
        
        paginationContainer.innerHTML = html;
    } else {
        paginationContainer.classList.add('hidden');
        paginationContainer.innerHTML = '';
    }
}

// Ensure function is exposed globally for onclick handlers
window.changeModalPage = function(page) {
    modalCurrentPage = page;
    updateModalPagination();
};

// ─── Filter products by supplier ─────────────────────────────────────────────
function getFilteredProducts() {
    const sid = supplierSel.value;
    if (!sid) return [];
    const supplier = suppliers.find(s => String(s.supplier_id) === String(sid));
    if (!supplier || !supplier.supplied_categories) return products;

    let allowed = [];
    try { allowed = JSON.parse(supplier.supplied_categories); }
    catch(e) { allowed = supplier.supplied_categories.split(',').map(c => c.trim()); }
    if (!allowed || allowed.length === 0) return products;
    return products.filter(p => allowed.includes(p.category));
}

// ─── Build <option> HTML for product dropdown ─────────────────────────────────
function productOptionsHTML(selectedId = '') {
    const list = getFilteredProducts();
    let html = '<option value="">Choose product...</option>';
    list.forEach(p => {
        const lowTag  = Number(p.stock) <= Number(p.low_stock_threshold) ? ' LOW' : '';
        const sel     = String(p.product_id) === String(selectedId) ? 'selected' : '';
        html += `<option value="${p.product_id}"
                    data-cost="${p.cost_price}"
                    data-suggested="${p.suggested_qty}"
                    data-pcs="${p.pcs_per_box}"
                    ${sel}>
                    ${p.name} [${p.sku}] — Stock: ${p.stock}${lowTag}
                 </option>`;
    });
    return html;
}

// ─── Compute grand total ──────────────────────────────────────────────────────
function computeSubtotal() {
    let total = 0;
    container.querySelectorAll('.po-row').forEach(row => {
        const qty  = Number(row.querySelector('.qty-input')?.value  || 0);
        const cost = Number(row.querySelector('.cost-input')?.value || 0);
        total += qty * cost;
    });
    subtotalEl.textContent = total.toFixed(2);
}

// ─── Update a row after product or type changes ───────────────────────────────
function refreshRow(row) {
    const productSel = row.querySelector('.product-select');
    const typeSel    = row.querySelector('.type-select');
    const qtyInput   = row.querySelector('.qty-input');
    const costInput  = row.querySelector('.cost-input');
    const infoDiv    = row.querySelector('.row-info');

    const selected = productSel.options[productSel.selectedIndex];
    if (!selected || !selected.value) {
        infoDiv.innerHTML = '';
        computeSubtotal();
        return;
    }

    const costPrice   = Number(selected.getAttribute('data-cost')      || 0);
    const pcsPerBox   = Number(selected.getAttribute('data-pcs')       || 1);
    const suggested   = Number(selected.getAttribute('data-suggested') || 1);
    const type        = typeSel.value; // 'retail' | 'wholesale'

    if (type === 'wholesale') {
        // Quantity = number of BOXES; unit cost = costPrice × pcsPerBox
        const boxCost = costPrice * pcsPerBox;
        costInput.value = boxCost.toFixed(2);
        if (qtyInput.value === '' || Number(qtyInput.value) < 1) {
            // suggest boxes needed
            qtyInput.value = Math.max(1, Math.ceil(suggested / pcsPerBox));
        }
        infoDiv.innerHTML = `
            <div class="flex flex-wrap gap-3 mt-1">
                <span class="inline-flex items-center gap-1 text-xs bg-indigo-50 text-indigo-700 rounded-full px-2.5 py-0.5 font-semibold">
                    ${pcsPerBox} pcs/box
                </span>
                <span class="inline-flex items-center gap-1 text-xs bg-amber-50 text-amber-700 rounded-full px-2.5 py-0.5 font-semibold">
                    ₱${costPrice.toFixed(2)}/pc × ${pcsPerBox} = ₱${boxCost.toFixed(2)}/box
                </span>
                <span id="pcs_total_${Date.now()}" class="inline-flex items-center gap-1 text-xs bg-emerald-50 text-emerald-700 rounded-full px-2.5 py-0.5 font-semibold pcs-total-label">
                    Total pcs: ${(Number(qtyInput.value) * pcsPerBox)}
                </span>
            </div>`;
    } else {
        // Retail: quantity = pieces; unit cost = cost price per piece
        costInput.value = costPrice.toFixed(2);
        if (qtyInput.value === '' || Number(qtyInput.value) < 1) {
            qtyInput.value = Math.max(1, suggested);
        }
        infoDiv.innerHTML = `
            <div class="flex flex-wrap gap-3 mt-1">
                <span class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-600 rounded-full px-2.5 py-0.5 font-semibold">
                    Per piece — ₱${costPrice.toFixed(2)}/pc
                </span>
            </div>`;
    }
    computeSubtotal();
}

// Update "Total pcs" label in real-time as boxes qty changes (wholesale only)
function updatePcsTotalLabel(row) {
    const typeSel  = row.querySelector('.type-select');
    const qtyInput = row.querySelector('.qty-input');
    const infoDiv  = row.querySelector('.row-info');
    const productSel = row.querySelector('.product-select');
    const selected = productSel.options[productSel.selectedIndex];
    if (!selected || !selected.value || typeSel.value !== 'wholesale') return;

    const pcsPerBox = Number(selected.getAttribute('data-pcs') || 1);
    const totalPcs  = Number(qtyInput.value || 0) * pcsPerBox;
    const label     = infoDiv.querySelector('.pcs-total-label');
    if (label) label.textContent = `Total pcs: ${totalPcs}`;
}

// ─── Add a new PO item row ────────────────────────────────────────────────────
function addItemRow() {
    noItemsMsg.style.display = 'none';

    const row = document.createElement('div');
    row.className = 'po-row grid grid-cols-12 gap-2 items-start bg-slate-50 rounded-lg p-2 border border-slate-200';
    row.innerHTML = `
        <!-- Product -->
        <div class="col-span-4">
            <select name="product_id[]" class="product-select w-full rounded-lg border border-slate-300 px-2 py-2 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none" required>
                ${productOptionsHTML()}
            </select>
        </div>

        <!-- Type: Retail / Wholesale -->
        <div class="col-span-2">
            <select class="type-select w-full rounded-lg border border-slate-300 px-2 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white" name="order_type[]">
                <option value="retail">Retail</option>
                <option value="wholesale">Wholesale</option>
            </select>
        </div>

        <!-- Quantity (boxes if wholesale, pcs if retail) -->
        <div class="col-span-2">
            <input type="number" name="ordered_qty[]" min="1" value="1" required
                class="qty-input w-full rounded-lg border border-slate-300 px-2 py-2 text-xs text-center focus:ring-2 focus:ring-emerald-500 focus:outline-none font-bold"
                placeholder="Qty">
        </div>

        <!-- Unit Cost (auto-filled, uneditable) -->
        <div class="col-span-3">
            <input type="number" step="0.01" min="0.01" name="unit_cost[]" required readonly
                class="cost-input w-full rounded-lg border border-slate-300 bg-slate-100 text-slate-600 px-2 py-2 text-xs text-right cursor-not-allowed focus:outline-none"
                placeholder="Unit cost">
        </div>

        <!-- Remove -->
        <div class="col-span-1 flex justify-center">
            <button type="button" class="remove-row mt-1 w-7 h-7 rounded-full bg-red-100 text-red-600 hover:bg-red-200 flex items-center justify-center text-xs font-black leading-none">X</button>
        </div>

        <!-- Info bar (spans full row) -->
        <div class="col-span-12 row-info px-1"></div>
    `;

    container.appendChild(row);

    // Events
    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove();
        computeSubtotal();
        updateModalPagination();
        if (container.querySelectorAll('.po-row').length === 0) {
            noItemsMsg.style.display = '';
        }
    });

    row.querySelector('.product-select').addEventListener('change', () => refreshRow(row));
    row.querySelector('.type-select').addEventListener('change', () => refreshRow(row));
    row.querySelector('.qty-input').addEventListener('input', () => {
        updatePcsTotalLabel(row);
        computeSubtotal();
    });
    row.querySelector('.cost-input').addEventListener('input', computeSubtotal);

    // After adding element, recalculate pagination and switch to last page
    const totalRows = container.querySelectorAll('.po-row').length;
    modalCurrentPage = Math.ceil(totalRows / itemsPerPage) || 1;
    updateModalPagination();
}

// ─── Wire up supplier change ───────────────────────────────────────────────────
supplierSel.addEventListener('change', () => {
    // Rebuild all product dropdowns with filtered products
    container.querySelectorAll('.po-row').forEach(row => {
        const ps = row.querySelector('.product-select');
        ps.innerHTML = productOptionsHTML();
        row.querySelector('.row-info').innerHTML = '';
    });
    computeSubtotal();
    updateModalPagination();
});

// ─── Add Item button ──────────────────────────────────────────────────────────
addBtn.addEventListener('click', () => {
    if (!supplierSel.value) {
        alert('Please select a supplier first to see their available products.');
        return;
    }
    addItemRow();
});
</script>

<?php require_once '../../includes/inventory_footer.php'; ?>