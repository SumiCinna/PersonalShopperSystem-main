<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

$page_title = 'Stock Management';
require_once '../../includes/inventory_header.php';

$filter_status = $_GET['status'] ?? 'Pending';

// Pagination setup
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM product_batches WHERE status = ?";
$stmt_count = $conn->prepare($count_query);
$stmt_count->bind_param("s", $filter_status);
$stmt_count->execute();
$total_batches = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = max(1, ceil($total_batches / $items_per_page));
$offset = ($current_page - 1) * $items_per_page;

// Fetch batches
$query = "
    SELECT pb.batch_id, pb.batch_number, pb.manufacture_date, pb.expiry_date, 
           pb.initial_quantity, pb.remaining_quantity, pb.status, pb.created_at,
           p.name AS product_name, p.sku, p.product_id
    FROM product_batches pb
    JOIN products p ON p.product_id = pb.product_id
    WHERE pb.status = ?
    ORDER BY pb.expiry_date ASC, pb.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $filter_status, $items_per_page, $offset);
$stmt->execute();
$batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 leading-tight">Stock Management (FEFO)</h1>
            <p class="text-slate-500 mt-1 text-sm">Release new batches to inventory and track expiration dates.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="stock_management.php?status=Pending" class="px-4 py-2 rounded-lg text-sm font-semibold transition <?php echo $filter_status==='Pending' ? 'bg-amber-100 text-amber-700 shadow-sm border border-amber-200' : 'bg-white text-slate-600 border hover:bg-slate-50'; ?>">Pending Batches</a>
            <a href="stock_management.php?status=Released" class="px-4 py-2 rounded-lg text-sm font-semibold transition <?php echo $filter_status==='Released' ? 'bg-emerald-100 text-emerald-800 shadow-sm border border-emerald-200' : 'bg-white text-slate-600 border hover:bg-slate-50'; ?>">Released Batches</a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div id="toast-success" class="mb-6 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm shadow-sm transition-all animate-[fadeIn_0.3s_ease-in-out]">
            <div class="flex items-center gap-3 text-emerald-800 font-medium">
                <div class="w-8 h-8 rounded-full bg-emerald-200 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span><?php echo htmlspecialchars($_GET['msg']); ?></span>
            </div>
            <button onclick="document.getElementById('toast-success').style.display='none'" class="text-emerald-600 hover:text-emerald-900 transition bg-emerald-100 hover:bg-emerald-200 rounded-lg p-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div id="toast-error" class="mb-6 flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm shadow-sm transition-all animate-[fadeIn_0.3s_ease-in-out]">
            <div class="flex items-center gap-3 text-red-800 font-medium">
                <div class="w-8 h-8 rounded-full bg-red-200 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span><?php echo htmlspecialchars($_GET['error']); ?></span>
            </div>
            <button onclick="document.getElementById('toast-error').style.display='none'" class="text-red-600 hover:text-red-900 transition bg-red-100 hover:bg-red-200 rounded-lg p-1.5 focus:outline-none focus:ring-2 focus:ring-red-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    <?php endif; ?>

    <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 border-b text-slate-800 uppercase text-xs tracking-wide">
                <tr>
                    <th class="px-6 py-4 font-bold">Product</th>
                    <th class="px-6 py-4 font-bold">Batch #</th>
                    <th class="px-6 py-4 font-bold">Manufacture Date</th>
                    <th class="px-6 py-4 font-bold relative">
                        Expiry Date 
                        <?php if($filter_status === 'Pending'): ?> <span class="bg-amber-200 text-amber-800 text-[10px] px-1.5 py-0.5 rounded ml-1">FEFO Focus</span> <?php endif; ?>
                    </th>
                    <th class="px-6 py-4 font-bold">Quantity</th>
                    <th class="px-6 py-4 font-bold align-middle">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($batches) === 0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                            No <?php echo htmlspecialchars($filter_status); ?> batches found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($batches as $b): ?>
                    <tr class="hover:bg-slate-50 transition <?php echo ($filter_status === 'Pending') ? 'bg-amber-50/20' : ''; ?>">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800"><?php echo htmlspecialchars($b['product_name']); ?></div>
                            <div class="text-xs text-slate-500 font-mono">SKU: <?php echo htmlspecialchars($b['sku']); ?></div>
                        </td>
                        <td class="px-6 py-4 font-mono text-slate-700 bg-slate-50/50"><?php echo htmlspecialchars($b['batch_number']); ?></td>
                        <td class="px-6 py-4"><?php echo $b['manufacture_date'] ? date('F d, Y', strtotime($b['manufacture_date'])) : 'N/A'; ?></td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800"><?php echo $b['expiry_date'] ? date('F d, Y', strtotime($b['expiry_date'])) : 'None'; ?></div>
                            <?php
                                if ($b['expiry_date']) {
                                    $days_to_expire = (strtotime($b['expiry_date']) - time()) / 86400;
                                    if ($days_to_expire < 30 && $days_to_expire > 0) {
                                        echo '<span class="text-[10px] bg-amber-100 text-amber-700 font-bold px-1.5 py-0.5 rounded mt-1 inline-block">Near Expiry</span>';
                                    } elseif ($days_to_expire <= 0) {
                                        echo '<span class="text-[10px] bg-red-100 text-red-700 font-bold px-1.5 py-0.5 rounded mt-1 inline-block">Expired</span>';
                                    }
                                }
                            ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold <?php echo $b['status'] === 'Pending' ? 'text-amber-600' : 'text-emerald-600'; ?>">
                                <?php echo $b['remaining_quantity']; ?> <span class="text-xs font-normal text-slate-400">/ <?php echo $b['initial_quantity']; ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-middle">
                            <?php if ($b['status'] === 'Pending'): ?>
                                <button type="button" onclick="confirmRelease(<?php echo $b['batch_id']; ?>, <?php echo $b['product_id']; ?>, <?php echo $b['remaining_quantity']; ?>)" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-bold transition shadow-sm whitespace-nowrap focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Release to Stock
                                </button>
                            <?php elseif ($b['status'] === 'Released'): ?>
                                <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[11px] font-bold border border-emerald-200 whitespace-nowrap">Active in Store</span>
                            <?php else: ?>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($b['status']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 flex items-center justify-between border-t border-slate-200 bg-slate-50 flex-col sm:flex-row gap-3">
            <div class="text-sm font-medium text-slate-500">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_batches); ?> of <?php echo $total_batches; ?> batches
            </div>
            <div class="flex gap-1 flex-wrap justify-center">
                <?php if ($current_page > 1): ?>
                    <a href="?status=<?php echo urlencode($filter_status); ?>&page=<?php echo $current_page - 1; ?>" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 transition hover:shadow-sm">Prev</a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?status=' . urlencode($filter_status) . '&page=1" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 transition hover:shadow-sm">1</a>';
                    if ($start_page > 2) echo '<span class="px-3 py-1.5 flex items-center text-slate-400">...</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <a href="?status=<?php echo urlencode($filter_status); ?>&page=<?php echo $i; ?>" class="px-3 py-1.5 border rounded-lg text-sm font-medium transition hover:shadow-sm flex items-center justify-center <?php echo $current_page === $i ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span class="px-3 py-1.5 flex items-center text-slate-400">...</span>';
                    echo '<a href="?status=' . urlencode($filter_status) . '&page=' . $total_pages . '" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 transition hover:shadow-sm">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?status=<?php echo urlencode($filter_status); ?>&page=<?php echo $current_page + 1; ?>" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 transition hover:shadow-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Release Confirmation Modal -->
<div id="release-modal" class="fixed inset-0 z-50 hidden flex flex-col items-center justify-center bg-slate-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 transform scale-95 transition-transform duration-300 relative" id="release-modal-content">
        <button type="button" onclick="closeReleaseModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-slate-800 tracking-tight">Confirm Release</h3>
        </div>
        <p class="text-slate-600 text-sm mb-6 leading-relaxed">Are you sure you want to release this batch? This will permanently move the pending units into the active store inventory.</p>
        <form id="release-form" action="../../core/inventory/release_batch.php" method="POST" class="flex justify-end gap-3">
            <input type="hidden" name="batch_id" id="modal-batch-id" value="">
            <input type="hidden" name="product_id" id="modal-product-id" value="">
            <input type="hidden" name="qty" id="modal-qty" value="">
            <button type="button" onclick="closeReleaseModal()" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-bold transition text-sm">Cancel</button>
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow-sm transition text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Confirm Release</button>
        </form>
    </div>
</div>

<script>
    function confirmRelease(batchId, productId, qty) {
        document.getElementById('modal-batch-id').value = batchId;
        document.getElementById('modal-product-id').value = productId;
        document.getElementById('modal-qty').value = qty;
        
        const modal = document.getElementById('release-modal');
        const content = document.getElementById('release-modal-content');
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Timeout to allow the "display: flex" to render before kicking off the opacity transition
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
    }
    
    function closeReleaseModal() {
        const modal = document.getElementById('release-modal');
        const content = document.getElementById('release-modal-content');
        
        modal.classList.add('opacity-0');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        
        // Wait for CSS transition string (300ms) to complete before hiding the modal frame
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }

    // Close on click outside
    document.getElementById('release-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReleaseModal();
        }
    });

    // Close on Esc key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('release-modal').classList.contains('hidden')) {
            closeReleaseModal();
        }
    });
</script>

<?php require_once '../../includes/inventory_footer.php'; ?>