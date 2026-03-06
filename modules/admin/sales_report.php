<?php
// modules/admin/sales_report.php
session_start();
require_once '../../config/config.php';

// --- STRICT SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

// --- 1. DATE FILTERING LOGIC ---
// Default to the current month if no dates are selected
$start_date = $_GET['start_date'] ?? date('Y-m-01'); 
$end_date = $_GET['end_date'] ?? date('Y-m-t'); 

// Append times to ensure we capture the very beginning of the start date and the very end of the end date
$query_start = $start_date . ' 00:00:00';
$query_end = $end_date . ' 23:59:59';

// --- 2. FETCH FINANCIAL SUMMARY (THE MATH) ---
// Notice how we just SUM the columns from our perfect invoices table!
$summary_query = "SELECT 
                    COUNT(invoice_id) as total_invoices,
                    SUM(grand_total) as gross_revenue,
                    SUM(subtotal) as net_sales,
                    SUM(tax_amount) as total_tax_collected
                  FROM invoices 
                  WHERE issued_at BETWEEN ? AND ?";
                  
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fallbacks in case the result is null (no sales in that period)
$gross_revenue = $summary['gross_revenue'] ?? 0;
$net_sales = $summary['net_sales'] ?? 0;
$total_tax = $summary['total_tax_collected'] ?? 0;
$total_invoices = $summary['total_invoices'] ?? 0;

// --- 3. FETCH DETAILED TRANSACTION LIST ---
$details_query = "SELECT 
                    i.invoice_no, i.grand_total, i.issued_at,
                    t.payment_method,
                    c.username AS cashier_name
                  FROM invoices i
                  JOIN transactions t ON i.invoice_id = t.invoice_id
                  JOIN users c ON t.cashier_id = c.user_id
                  WHERE i.issued_at BETWEEN ? AND ?
                  ORDER BY i.issued_at DESC";
                  
$stmt = $conn->prepare($details_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$details_result = $stmt->get_result();
$stmt->close();

$page_title = 'Financial Sales Report';
require_once '../../includes/admin_header.php'; 
?>

<style>
    @media print {
        .no-print { display: none !important; }
        aside { display: none !important; }
        body { background-color: white !important; }
        main { padding: 0 !important; margin: 0 !important; }
        .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
        .shadow-sm, .shadow-md { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
    .print-header { display: none; }
</style>

<main class="flex-1 overflow-y-auto p-8 bg-slate-50 w-full">
    
    <div class="print-header">
        <h1 class="text-2xl font-black uppercase tracking-widest">PSS Grocery Management</h1>
        <h2 class="text-lg font-bold text-gray-700">Official Financial Report</h2>
        <p class="text-sm text-gray-500">Period: <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?></p>
        <p class="text-xs text-gray-400 mt-1">Generated on: <?php echo date('F j, Y h:i A'); ?> by Admin</p>
        <hr class="my-4 border-gray-300">
    </div>

    <div class="no-print flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-slate-200 pb-6 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Financial Reports</h1>
            <p class="text-slate-500 mt-1">Generate and export sales data for accounting.</p>
        </div>
        
        <form method="GET" class="flex flex-wrap items-end gap-3 bg-white p-3 rounded-lg shadow-sm border border-slate-200">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded focus:ring-blue-500 focus:border-blue-500 block p-2 font-mono">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded focus:ring-blue-500 focus:border-blue-500 block p-2 font-mono">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition shadow">
                Filter
            </button>
            <button type="button" onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-4 rounded transition shadow flex items-center ml-2">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print PDF
            </button>
        </form>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-white rounded-xl shadow-sm border-t-4 border-slate-800 p-6">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Gross Revenue</p>
            <p class="text-3xl font-black text-slate-900 font-mono">₱<?php echo number_format($gross_revenue, 2); ?></p>
            <p class="text-[10px] text-slate-500 mt-2 leading-tight">Total cash/digital money collected from customers (VAT Inclusive).</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border-t-4 border-green-500 p-6">
            <p class="text-xs font-bold text-green-600 uppercase tracking-widest mb-1">Net Sales (Vatable)</p>
            <p class="text-3xl font-black text-green-700 font-mono">₱<?php echo number_format($net_sales, 2); ?></p>
            <p class="text-[10px] text-slate-500 mt-2 leading-tight">True store revenue after extracting the 12% government tax.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border-t-4 border-red-500 p-6">
            <p class="text-xs font-bold text-red-600 uppercase tracking-widest mb-1">VAT Payable to BIR</p>
            <p class="text-3xl font-black text-red-700 font-mono">₱<?php echo number_format($total_tax, 2); ?></p>
            <p class="text-[10px] text-slate-500 mt-2 leading-tight">Output VAT collected that must be remitted to the government.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border-t-4 border-blue-500 p-6">
            <p class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-1">Transactions</p>
            <p class="text-3xl font-black text-blue-700 font-mono"><?php echo number_format($total_invoices); ?></p>
            <p class="text-[10px] text-slate-500 mt-2 leading-tight">Total number of official receipts generated in this period.</p>
        </div>

    </div>

    <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden mb-10">
        <div class="bg-slate-50 border-b border-slate-200 p-4">
            <h3 class="font-bold text-slate-800 text-sm uppercase tracking-wider">Itemized Ledger</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="p-4 font-bold">Date & Time</th>
                        <th class="p-4 font-bold">OR Number</th>
                        <th class="p-4 font-bold">Cashier</th>
                        <th class="p-4 font-bold text-center">Method</th>
                        <th class="p-4 font-bold text-right">Gross Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($details_result->num_rows > 0): ?>
                        <?php while ($row = $details_result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 text-sm text-slate-600">
                                    <?php echo date('M j, Y - h:i A', strtotime($row['issued_at'])); ?>
                                </td>
                                <td class="p-4 font-mono text-sm font-bold text-blue-900">
                                    <?php echo htmlspecialchars($row['invoice_no']); ?>
                                </td>
                                <td class="p-4 text-sm font-semibold text-slate-700">
                                    <?php echo htmlspecialchars($row['cashier_name']); ?>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider border border-slate-200">
                                        <?php echo htmlspecialchars($row['payment_method']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right font-black text-slate-900 font-mono">
                                    ₱<?php echo number_format($row['grand_total'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p class="font-bold text-lg text-slate-700">No transactions found</p>
                                <p class="text-sm">There are no sales records for the selected date range.</p>
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