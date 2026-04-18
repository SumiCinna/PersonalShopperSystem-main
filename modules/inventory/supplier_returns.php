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
            poi.order_type,
            poi.box_quantity,
            p.name AS product_name,
            p.sku,
            p.pcs_per_box,
            s.name AS supplier_name,
            u.username AS created_by_name
        FROM supplier_returns sr
        JOIN purchase_orders po ON po.po_id = sr.po_id
        LEFT JOIN purchase_order_items poi ON poi.po_item_id = sr.po_item_id
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
        <a href="purchase_orders.php" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to PO
        </a>
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
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
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
        
        
    </div>

    <!-- ========================================
         RETURN PROCESS FLOW GUIDE
         ======================================== -->
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold text-slate-900 mb-4 flex items-center gap-2">
            
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
                    <div class="font-semibold text-blue-900 text-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Option A: Replace
                    </div>
                    <div class="text-xs text-blue-700 mt-1">Supplier sends replacement stock immediately. New PO may be generated for tracking.</div>
                </div>
                <div class="p-3 rounded-lg bg-amber-50 border border-amber-200">
                    <div class="font-semibold text-amber-900 text-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Option B: Credit Memo
                    </div>
                    <div class="text-xs text-amber-700 mt-1">Amount deducted from next purchase order. Creates audit trail for financial reconciliation.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         RETURN TO VENDOR QUEUE
         ======================================== -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-12">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-col xl:flex-row justify-between items-start xl:items-center bg-slate-50 gap-4">
            <div class="flex flex-wrap gap-2" id="rtv-tabs">
                <button class="rtv-tab active px-3 py-1.5 rounded-lg text-sm font-bold bg-slate-800 text-white transition-colors" data-filter="all">All Returns</button>
                <button class="rtv-tab px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors" data-filter="pending_return">Pending Return</button>
                <button class="rtv-tab px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors" data-filter="returned_to_supplier">Sent to Supplier</button>
                <button class="rtv-tab px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors" data-filter="resolved-replace">Resolved (Replace)</button>
                <button class="rtv-tab px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors" data-filter="resolved-credit_memo">Resolved (Credit Memo)</button>
            </div>
            <div class="relative w-full xl:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="rtv-search" class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Search PO, Supplier, Product...">
            </div>
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
                    <?php if (empty($returns)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No rejected supplier items found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($returns as $index => $row): 
                            $qty = (int)$row['rejected_qty'];
                            $type = $row['order_type'] ?? 'retail';
                            $pcsPerBox = max(1, (int)($row['pcs_per_box'] ?? 1));
                            if ($type === 'wholesale' && $qty % $pcsPerBox === 0) {
                                $displayQty = $qty / $pcsPerBox;
                                $displayUnit = $displayQty > 1 ? 'Boxes' : 'Box';
                            } else {
                                $displayQty = $qty;
                                $displayUnit = $displayQty > 1 || $displayQty === 0 ? 'Pieces' : 'Piece';
                            }
                            
                            $statusData = $row['status'];
                            $resolutionData = $row['resolution_type'] ?? 'pending';
                            if ($statusData === 'resolved') {
                                $filterCategory = 'resolved-' . $resolutionData;
                            } else {
                                $filterCategory = $statusData;
                            }
                            
                            $searchContext = strtolower($row['po_number'] . ' ' . $row['supplier_name'] . ' ' . $row['product_name'] . ' ' . $row['sku']);
                        ?>
                            <tr class="hover:bg-slate-50 transition rtv-row" data-category="<?php echo htmlspecialchars($filterCategory); ?>" data-search="<?php echo htmlspecialchars($searchContext); ?>" style="<?php echo $index >= 5 ? 'display: none;' : ''; ?>">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($row['po_number']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($row['product_name']); ?> (<?php echo htmlspecialchars($row['sku']); ?>)</div>
                                    <div class="text-[10px] text-slate-400 mt-1">Logged: <?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium text-sm"><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="text-lg font-black text-red-600 leading-none"><?php echo $displayQty; ?></div>
                                    <div class="text-[10px] text-slate-500 font-medium mt-1">
                                        <?php echo $displayUnit; ?>
                                    </div>
                                </td>
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
                                            <option value="pending_return" <?php echo $row['status'] === 'pending_return' ? 'selected' : ''; ?>>Pending Return</option>
                                            <option value="returned_to_supplier" <?php echo $row['status'] === 'returned_to_supplier' ? 'selected' : ''; ?>>Sent to Supplier</option>
                                            <option value="resolved" <?php echo $row['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <select name="resolution_type" class="rounded border border-slate-300 px-2 py-1 text-xs bg-white">
                                            <option value="pending">- Select Resolution -</option>
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

        <div id="rtv-pagination-bar" class="px-5 py-4 flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-slate-50 gap-3 hidden">
            <div id="rtv-page-info" class="text-sm font-medium text-slate-500"></div>
            <div id="rtv-page-controls" class="flex gap-1 flex-wrap justify-center"></div>
        </div>
    </section>

    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const rowsSelector = '.rtv-row';
    const itemsPerPage = 5;

    let currentPage = 1;
    let currentFilter = 'all';
    let searchQuery = '';

    let allRows = Array.from(document.querySelectorAll(rowsSelector));
    let filteredRows = [...allRows];

    function applyFilters() {
        filteredRows = allRows.filter(row => {
            const categoryMatch = currentFilter === 'all' || row.dataset.category === currentFilter;
            const searchMatch = searchQuery === '' || row.dataset.search.includes(searchQuery);
            return categoryMatch && searchMatch;
        });

        currentPage = 1;
        renderPagination();
    }

    function renderPagination() {
        const total = filteredRows.length;
        const totalPages = Math.ceil(total / itemsPerPage) || 1;
        
        // Hide ALL rows first to reset
        allRows.forEach(r => r.style.display = 'none');
        
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        // Show only the page subset of filteredRows
        filteredRows.forEach((row, i) => {
            if (i >= start && i < end) {
                row.style.display = '';
            }
        });

        const bar = document.getElementById('rtv-pagination-bar');
        const info = document.getElementById('rtv-page-info');
        const controls = document.getElementById('rtv-page-controls');

        if (total > 0) {
            bar.classList.remove('hidden');
            info.textContent = `Showing ${start + 1} to ${Math.min(end, total)} of ${total} returns`;

            controls.innerHTML = '';
            if (total > itemsPerPage) {
                if (currentPage > 1) controls.appendChild(createBtn('Prev', currentPage - 1));
                for (let i = 1; i <= totalPages; i++) {
                    controls.appendChild(createBtn(i, i, i === currentPage));
                }
                if (currentPage < totalPages) controls.appendChild(createBtn('Next', currentPage + 1));
            }
        } else {
            bar.classList.add('hidden');
        }
    }

    function createBtn(label, targetPage, isActive = false) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = label;
        btn.className = 'px-3 py-1 border rounded text-sm font-medium transition flex items-center justify-center cursor-pointer ';
        if (isActive) {
            btn.className += 'bg-blue-600 text-white border-blue-600 shadow-sm';
        } else {
            btn.className += 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50';
        }
        btn.addEventListener('click', () => {
            currentPage = targetPage;
            renderPagination();
        });
        return btn;
    }

    // Apply Tab Navigation Logic
    const tabs = document.querySelectorAll('.rtv-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            // Reset states
            tabs.forEach(t => {
                t.className = 'rtv-tab px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors';
            });
            
            // Set active state
            e.currentTarget.className = 'rtv-tab active px-3 py-1.5 rounded-lg text-sm font-bold bg-slate-800 text-white transition-colors';
            
            currentFilter = e.currentTarget.dataset.filter;
            applyFilters();
        });
    });

    // Apply Search Logic
    const searchInput = document.getElementById('rtv-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }

    // Initial render
    renderPagination();
});
</script>

<?php require_once '../../includes/inventory_footer.php'; ?>
