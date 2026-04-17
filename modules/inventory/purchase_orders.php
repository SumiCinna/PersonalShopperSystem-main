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
            GROUP_CONCAT(CONCAT(p.name, ' (x', poi.ordered_qty, ')') SEPARATOR '||') AS item_details
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
        LEFT JOIN users u1 ON u1.user_id = po.created_by
        LEFT JOIN users u2 ON u2.user_id = po.approved_by
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
            po.created_at,
            s.name,
            u1.username,
            u2.username
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

$page_title = 'PO & BO Workflow';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Purchase Orders (PO) & BO </h1>
            <p class="text-sm text-slate-500 mt-1">Create PO from low stock, track supplier flow, and quality check.</p>
        </div>
        <div class="flex gap-2">
            <a href="add_supplier.php" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition">+ Add Supplier</a>
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

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
        <section class="xl:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="font-bold text-slate-800">Create Purchase Order</h2>
                <p class="text-xs text-slate-500 mt-1">Flow: Low Stock → Create PO → Admin Approval → Supplier Fulfillment</p>
            </div>
            <form action="../../core/inventory/po_actions.php" method="POST" class="p-5 space-y-4">
                <input type="hidden" name="action" value="create_po">

                <div>
                    <label class="text-sm font-semibold text-slate-700">Supplier</label>
                    <select name="supplier_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select supplier...</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int)$supplier['supplier_id']; ?>">
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Expected Delivery Date</label>
                    <?php $minDate = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'); ?>
                    <input type="date" name="expected_delivery" min="<?php echo $minDate; ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">PO Notes (Max 100 characters)</label>
                    <textarea name="notes" rows="2" maxlength="100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors" placeholder="Optional instructions for supplier..."></textarea>
                </div>

                <div class="border rounded-lg overflow-hidden">
                    <div class="px-3 py-2 bg-slate-50 border-b text-xs font-semibold text-slate-600">PO Items</div>
                    <div id="poItemsContainer" class="p-3 space-y-2"></div>
                    <div class="px-3 py-2 border-t bg-slate-50 flex items-center justify-between">
                        <button type="button" id="addItemBtn" class="text-sm font-semibold text-blue-700 hover:text-blue-900">+ Add Item</button>
                        <span class="text-sm font-bold text-slate-800">Subtotal: ₱<span id="poSubtotal">0.00</span></span>
                    </div>
                </div>

                <button type="submit" class="w-full rounded-lg bg-blue-600 text-white py-2.5 text-sm font-semibold hover:bg-blue-700 transition">
                    Submit PO for Admin Approval
                </button>
            </form>
        </section>

        <section class="xl:col-span-3 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
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
                    <span class="text-sm text-slate-500">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&filter_product=<?php echo urlencode($filterProduct); ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm text-slate-700 hover:bg-slate-100">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&filter_product=<?php echo urlencode($filterProduct); ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm text-slate-700 hover:bg-slate-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php endif; ?>
</main>

<?php require_once '../../includes/inventory_footer.php'; ?>

<script>
const products = <?php echo json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const suppliers = <?php echo json_encode($suppliers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const container = document.getElementById('poItemsContainer');
const addBtn = document.getElementById('addItemBtn');
const subtotalEl = document.getElementById('poSubtotal');
const supplierSelect = document.querySelector('select[name="supplier_id"]');

function getFilteredProducts() {
    const selectedSupplierId = supplierSelect.value;
    if (!selectedSupplierId) return [];
    
    const supplier = suppliers.find(s => String(s.supplier_id) === String(selectedSupplierId));
    if (!supplier || !supplier.supplied_categories) return products; // No specific categories -> allow all or whatever logic (we'll allow all for backwards max compatibility)
    
    // Parse supplied_categories
    let allowedCategories = [];
    try { allowedCategories = JSON.parse(supplier.supplied_categories); } 
    catch(e) { allowedCategories = supplier.supplied_categories.split(',').map(c => c.trim()); }
    
    if (!allowedCategories || allowedCategories.length === 0) return products;
    
    return products.filter(p => allowedCategories.includes(p.category));
}

supplierSelect.addEventListener('change', () => {
    // When supplier changes, simply reset all rows
    if (container) container.innerHTML = '';
    if (addBtn && supplierSelect.value) addItemRow();
});

function productOptions() {
    const currentProducts = getFilteredProducts();
    return ['<option value="">Choose product...</option>']
        .concat(currentProducts.map(p => {
            const lowTag = Number(p.stock) <= Number(p.low_stock_threshold) ? ' (LOW)' : '';
            return `<option value="${p.product_id}" data-cost="${p.cost_price}" data-suggested="${p.suggested_qty}">${p.name} [${p.sku}] - Stock: ${p.stock}${lowTag}</option>`;
        }))
        .join('');
}

function addItemRow() {
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'grid grid-cols-12 gap-2 items-end';
    row.innerHTML = `
        <div class="col-span-6">
            <select name="product_id[]" required class="w-full rounded border border-slate-300 px-2 py-2 text-xs product-select">
                ${productOptions()}
            </select>
        </div>
        <div class="col-span-2">
            <input type="number" name="ordered_qty[]" min="1" value="1" required class="w-full rounded border border-slate-300 px-2 py-2 text-xs qty-input">
        </div>
        <div class="col-span-3">
            <input type="number" step="0.01" min="0.01" name="unit_cost[]" required class="w-full rounded border border-slate-300 px-2 py-2 text-xs cost-input" placeholder="Unit cost">
        </div>
        <div class="col-span-1">
            <button type="button" class="w-full rounded bg-red-100 text-red-700 py-2 text-xs font-bold remove-row">✕</button>
        </div>
    `;

    container.appendChild(row);

    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove();
        computeSubtotal();
    });

    row.querySelector('.product-select').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const cost = selected.getAttribute('data-cost');
        const suggested = selected.getAttribute('data-suggested');
        const costInput = row.querySelector('.cost-input');
        const qtyInput = row.querySelector('.qty-input');

        if (cost && Number(cost) > 0) costInput.value = Number(cost).toFixed(2);
        if (suggested && Number(suggested) > 0) qtyInput.value = Number(suggested);
        computeSubtotal();
    });

    row.querySelector('.qty-input').addEventListener('input', computeSubtotal);
    row.querySelector('.cost-input').addEventListener('input', computeSubtotal);
}

function computeSubtotal() {
    if (!container || !subtotalEl) return;
    let subtotal = 0;

    container.querySelectorAll('.grid').forEach(row => {
        const qty = Number(row.querySelector('.qty-input')?.value || 0);
        const cost = Number(row.querySelector('.cost-input')?.value || 0);
        subtotal += qty * cost;
    });

    subtotalEl.textContent = subtotal.toFixed(2);
}

if (addBtn) addBtn.addEventListener('click', () => {
    if (!supplierSelect.value) {
        alert("Please select a supplier first to see their available products.");
        return;
    }
    addItemRow();
});

// Remove the automatic `if (container) addItemRow();` because they must pick a supplier first.
if (container && supplierSelect.value) addItemRow();
</script>
