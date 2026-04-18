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

// Fetch batches
$query = "
    SELECT pb.batch_id, pb.batch_number, pb.manufacture_date, pb.expiry_date, 
           pb.initial_quantity, pb.remaining_quantity, pb.status, pb.created_at,
           p.name AS product_name, p.sku, p.product_id
    FROM product_batches pb
    JOIN products p ON p.product_id = pb.product_id
    WHERE pb.status = ?
    ORDER BY pb.expiry_date ASC, pb.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter_status);
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
                    <th class="px-6 py-4 font-bold">Mfg Date</th>
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
                        <td class="px-6 py-4"><?php echo $b['manufacture_date'] ? date('Y M d', strtotime($b['manufacture_date'])) : 'N/A'; ?></td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800"><?php echo $b['expiry_date'] ? date('Y M d', strtotime($b['expiry_date'])) : 'None'; ?></div>
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
                                <form action="../../core/inventory/release_batch.php" method="POST">
                                    <input type="hidden" name="batch_id" value="<?php echo $b['batch_id']; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $b['product_id']; ?>">
                                    <input type="hidden" name="qty" value="<?php echo $b['remaining_quantity']; ?>">
                                    <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-bold transition shadow-sm whitespace-nowrap">
                                        Release to Stock
                                    </button>
                                </form>
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
    </div>
</main>

<?php require_once '../../includes/inventory_footer.php'; ?>