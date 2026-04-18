<?php
// modules/admin/sales_report.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

// ============================================================
// 1. DATE FILTERING
// ============================================================
$preset     = $_GET['preset'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-t');

// Quick preset shortcuts
switch ($preset) {
    case 'today':
        $start_date = $end_date = date('Y-m-d'); break;
    case 'yesterday':
        $start_date = $end_date = date('Y-m-d', strtotime('-1 day')); break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date   = date('Y-m-d'); break;
    case 'last_week':
        $start_date = date('Y-m-d', strtotime('monday last week'));
        $end_date   = date('Y-m-d', strtotime('sunday last week')); break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date   = date('Y-m-t'); break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date   = date('Y-m-t',  strtotime('last day of last month')); break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date   = date('Y-12-31'); break;
}

$query_start = $start_date . ' 00:00:00';
$query_end   = $end_date   . ' 23:59:59';

// ============================================================
// 2. HANDLE CSV EXPORT
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_query = "SELECT 
                        i.invoice_no, i.issued_at, i.subtotal, i.tax_amount,
                        i.discount_amount, i.grand_total,
                        t.payment_method,
                        CONCAT(up.firstname, ' ', up.surname) AS cashier_name,
                        o.tracking_no, o.order_status
                     FROM invoices i
                     JOIN transactions t ON i.invoice_id = t.invoice_id
                     JOIN users c ON t.cashier_id = c.user_id
                     LEFT JOIN user_profiles up ON c.user_id = up.user_id
                     JOIN orders o ON i.order_id = o.order_id
                     WHERE i.issued_at BETWEEN ? AND ?
                     ORDER BY i.issued_at DESC";
    $stmt = $conn->prepare($export_query);
    $stmt->bind_param("ss", $query_start, $query_end);
    $stmt->execute();
    $export_result = $stmt->get_result();
    $stmt->close();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . $start_date . '_to_' . $end_date . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['OR Number','Date & Time','Tracking No','Cashier','Payment Method','Subtotal','Tax (VAT)','Discount','Grand Total','Order Status']);
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($out, [
            $row['invoice_no'],
            date('Y-m-d H:i:s', strtotime($row['issued_at'])),
            $row['tracking_no'],
            $row['cashier_name'],
            strcasecmp($row['payment_method'], 'prepaid') === 0 ? 'GCash (QRPH)' : $row['payment_method'],
            $row['subtotal'],
            $row['tax_amount'],
            $row['discount_amount'],
            $row['grand_total'],
            $row['order_status'],
        ]);
    }
    fclose($out);
    exit();
}

// ============================================================
// 3. FINANCIAL SUMMARY
// ============================================================
$summary_query = "SELECT 
                    COUNT(invoice_id) as total_invoices,
                    SUM(grand_total)     as gross_revenue,
                    SUM(subtotal)        as net_sales,
                    SUM(tax_amount)      as total_tax,
                    SUM(discount_amount) as total_discounts,
                    AVG(grand_total)     as avg_order_value,
                    MAX(grand_total)     as largest_sale
                  FROM invoices 
                  WHERE issued_at BETWEEN ? AND ?";
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$gross_revenue   = $summary['gross_revenue']   ?? 0;
$net_sales       = $summary['net_sales']       ?? 0;
$total_tax       = $summary['total_tax']       ?? 0;
$total_discounts = $summary['total_discounts'] ?? 0;
$total_invoices  = $summary['total_invoices']  ?? 0;
$avg_order_value = $summary['avg_order_value'] ?? 0;
$largest_sale    = $summary['largest_sale']    ?? 0;

// ============================================================
// 4. PREVIOUS PERIOD COMPARISON (for % change)
// ============================================================
$period_days   = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
$prev_end_ts   = strtotime($start_date) - 1;
$prev_start_ts = $prev_end_ts - ($period_days * 86400) + 1;
$prev_start    = date('Y-m-d', $prev_start_ts) . ' 00:00:00';
$prev_end      = date('Y-m-d', $prev_end_ts)   . ' 23:59:59';

$prev_query = "SELECT COALESCE(SUM(grand_total),0) as prev_revenue, COUNT(*) as prev_orders
               FROM invoices WHERE issued_at BETWEEN ? AND ?";
$stmt = $conn->prepare($prev_query);
$stmt->bind_param("ss", $prev_start, $prev_end);
$stmt->execute();
$prev = $stmt->get_result()->fetch_assoc();
$stmt->close();
$prev_revenue = $prev['prev_revenue'] ?? 0;
$prev_orders  = $prev['prev_orders']  ?? 0;

function pct_change($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}
$revenue_change = pct_change($gross_revenue, $prev_revenue);
$orders_change  = pct_change($total_invoices, $prev_orders);

// ============================================================
// 5. DAILY REVENUE TREND (for chart)
// ============================================================
$daily_query = "SELECT DATE(issued_at) as day, 
                       SUM(grand_total) as daily_total,
                       COUNT(*) as daily_orders
                FROM invoices 
                WHERE issued_at BETWEEN ? AND ?
                GROUP BY DATE(issued_at)
                ORDER BY day ASC";
$stmt = $conn->prepare($daily_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$daily_result = $stmt->get_result();
$stmt->close();

$daily_labels = []; $daily_revenue = []; $daily_orders_data = [];
while ($row = $daily_result->fetch_assoc()) {
    $daily_labels[]      = date('M j', strtotime($row['day']));
    $daily_revenue[]     = (float)$row['daily_total'];
    $daily_orders_data[] = (int)$row['daily_orders'];
}

// ============================================================
// 6. PAYMENT METHOD BREAKDOWN (for chart)
// ============================================================
$payment_query = "SELECT t.payment_method, 
                         COUNT(*) as tx_count, 
                         SUM(i.grand_total) as tx_total
                  FROM transactions t 
                  JOIN invoices i ON t.invoice_id = i.invoice_id
                  WHERE i.issued_at BETWEEN ? AND ?
                  GROUP BY t.payment_method
                  ORDER BY tx_total DESC";
$stmt = $conn->prepare($payment_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$payment_result = $stmt->get_result();
$stmt->close();
$payment_labels = []; $payment_totals = []; $payment_counts = [];
while ($row = $payment_result->fetch_assoc()) {
    $pm_label = strtoupper($row['payment_method']);
    if ($pm_label === 'PREPAID') $pm_label = 'GCASH (QRPH)';
    $payment_labels[] = $pm_label;
    $payment_totals[] = (float)$row['tx_total'];
    $payment_counts[] = (int)$row['tx_count'];
}

// ============================================================
// 7. TOP CASHIERS PERFORMANCE
// ============================================================
$cashier_query = "SELECT 
                    CONCAT(up.firstname, ' ', up.surname) AS cashier_name,
                    COUNT(i.invoice_id) as tx_count,
                    SUM(i.grand_total) as total_sales
                  FROM invoices i
                  JOIN transactions t ON i.invoice_id = t.invoice_id
                  JOIN users u ON t.cashier_id = u.user_id
                  LEFT JOIN user_profiles up ON u.user_id = up.user_id
                  WHERE i.issued_at BETWEEN ? AND ?
                  GROUP BY t.cashier_id, up.firstname, up.surname
                  ORDER BY total_sales DESC LIMIT 5";
$stmt = $conn->prepare($cashier_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$cashier_result = $stmt->get_result();
$stmt->close();

// ============================================================
// 8. TOP SELLING PRODUCTS IN PERIOD
// ============================================================
$top_products_query = "SELECT 
                           p.name, p.category, p.sku,
                           SUM(oi.quantity) as total_qty,
                           SUM(oi.quantity * oi.price_at_checkout) as total_revenue
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.product_id
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE o.created_at BETWEEN ? AND ?
                          AND o.order_status = 'completed'
                        GROUP BY oi.product_id, p.name, p.category, p.sku
                        ORDER BY total_qty DESC LIMIT 5";
$stmt = $conn->prepare($top_products_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$top_products_result = $stmt->get_result();
$stmt->close();

// ============================================================
// 9. HOURLY HEATMAP DATA
// ============================================================
$hourly_query = "SELECT HOUR(issued_at) as hr, COUNT(*) as cnt, SUM(grand_total) as total
                 FROM invoices 
                 WHERE issued_at BETWEEN ? AND ?
                 GROUP BY HOUR(issued_at)
                 ORDER BY hr ASC";
$stmt = $conn->prepare($hourly_query);
$stmt->bind_param("ss", $query_start, $query_end);
$stmt->execute();
$hourly_result = $stmt->get_result();
$stmt->close();
$hourly_counts = array_fill(0, 24, 0);
$hourly_totals = array_fill(0, 24, 0);
while ($row = $hourly_result->fetch_assoc()) {
    $hourly_counts[(int)$row['hr']] = (int)$row['cnt'];
    $hourly_totals[(int)$row['hr']] = (float)$row['total'];
}

// ============================================================
// 10. DETAILED TRANSACTION LIST
// ============================================================
$details_query = "SELECT 
                    i.invoice_no, i.grand_total, i.subtotal, i.tax_amount,
                    i.discount_amount, i.issued_at,
                    t.payment_method,
                    o.tracking_no, o.order_status,
                    CONCAT(up.firstname, ' ', up.surname) AS cashier_name
                  FROM invoices i
                  JOIN transactions t ON i.invoice_id = t.invoice_id
                  JOIN users c ON t.cashier_id = c.user_id
                  LEFT JOIN user_profiles up ON c.user_id = up.user_id
                  JOIN orders o ON i.order_id = o.order_id
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
/* ===================== PRINT STYLES ===================== */
@media print {
    .no-print { display: none !important; }
    aside { display: none !important; }
    body, html { background: white !important; }
    main { padding: 20px !important; }
    .print-header { display: block !important; }
    .print-break { page-break-before: always; }
    .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; }
    .rounded-2xl, .rounded-xl { border-radius: 0 !important; }
    canvas { max-height: 200px !important; }
}
.print-header { display: none; }

/* ===================== BASE ===================== */
.sr-report main { font-family: 'DM Sans', sans-serif; }

/* ===================== KPI CARDS ===================== */
.kpi-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    padding: 22px 24px;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.kpi-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
.kpi-card .accent-bar {
    position: absolute; top: 0; left: 0;
    height: 3px; width: 100%;
}
.kpi-card .kpi-label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: #94A3B8; margin-bottom: 6px;
}
.kpi-card .kpi-value {
    font-size: 26px; font-weight: 800; color: #0F172A; font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.kpi-card .kpi-sub {
    font-size: 11px; color: #94A3B8; margin-top: 6px;
}
.kpi-delta {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 11px; font-weight: 700; padding: 2px 7px;
    border-radius: 20px; margin-top: 8px;
}
.kpi-delta.up   { background: #DCFCE7; color: #15803D; }
.kpi-delta.down { background: #FEE2E2; color: #B91C1C; }
.kpi-delta.flat { background: #F1F5F9; color: #64748B; }

/* ===================== PRESET BUTTONS ===================== */
.preset-btn {
    padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 600;
    border: 1px solid #E2E8F0; background: #fff; color: #475569; cursor: pointer;
    transition: all 0.15s;
}
.preset-btn:hover, .preset-btn.active { background: #0F172A; color: #fff; border-color: #0F172A; }

/* ===================== SECTION CARDS ===================== */
.panel {
    background: #fff; border-radius: 16px;
    border: 1px solid #E2E8F0; overflow: hidden;
}
.panel-header {
    padding: 16px 20px; border-bottom: 1px solid #F1F5F9;
    display: flex; justify-content: space-between; align-items: center;
}
.panel-title { font-size: 13px; font-weight: 700; color: #0F172A; }
.panel-sub   { font-size: 11px; color: #94A3B8; margin-top: 1px; }

/* ===================== TABLE ===================== */
.data-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.data-table th {
    padding: 10px 14px; background: #F8FAFC; color: #64748B;
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; border-bottom: 1px solid #E2E8F0; white-space: nowrap;
}
.data-table td { padding: 11px 14px; border-bottom: 1px solid #F1F5F9; color: #334155; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #F8FAFC; }

/* ===================== BADGES ===================== */
.badge {
    display: inline-block; font-size: 9px; font-weight: 800;
    padding: 3px 8px; border-radius: 20px; letter-spacing: 0.06em; text-transform: uppercase;
}
.badge-cash     { background:#F0FDF4; color:#166534; border: 1px solid #BBF7D0; }
.badge-gcash    { background:#EFF6FF; color:#1D4ED8; border: 1px solid #BFDBFE; }
.badge-card     { background:#F5F3FF; color:#6D28D9; border: 1px solid #DDD6FE; }
.badge-online   { background:#FFF7ED; color:#C2410C; border: 1px solid #FED7AA; }
.badge-default  { background:#F1F5F9; color:#475569; border: 1px solid #E2E8F0; }
.badge-completed{ background:#F0FDF4; color:#166534; border: 1px solid #BBF7D0; }
.badge-pending  { background:#FFFBEB; color:#92400E; border: 1px solid #FDE68A; }

/* ===================== CASHIER BAR ===================== */
.cashier-bar-bg { background: #F1F5F9; border-radius: 4px; height: 5px; }
.cashier-bar-fill { height: 5px; border-radius: 4px; background: linear-gradient(90deg, #3B82F6, #6366F1); transition: width 0.6s ease; }

/* ===================== HOURLY HEATMAP ===================== */
.heatmap-cell {
    display: inline-flex; flex-direction: column; align-items: center;
    justify-content: center; width: calc(100% / 12 - 4px); aspect-ratio: 1;
    border-radius: 6px; font-size: 9px; font-weight: 700; color: #fff;
    transition: transform 0.15s;
    min-width: 28px;
}
.heatmap-cell:hover { transform: scale(1.12); }
.heat-0 { background: #F1F5F9; color: #CBD5E1; }
.heat-1 { background: #DBEAFE; color: #1D4ED8; }
.heat-2 { background: #93C5FD; color: #1E40AF; }
.heat-3 { background: #3B82F6; color: #fff; }
.heat-4 { background: #1D4ED8; color: #fff; }
.heat-5 { background: #1E3A8A; color: #fff; }

/* ===================== EXPORT BUTTONS ===================== */
.export-btn {
    display: inline-flex; align-items: center; gap-6px; gap: 6px;
    padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.15s; text-decoration: none; border: none;
}
.btn-primary  { background:#0F172A; color:#fff; }
.btn-primary:hover { background:#1E293B; }
.btn-secondary { background:#fff; color:#334155; border: 1px solid #E2E8F0; }
.btn-secondary:hover { background:#F8FAFC; }
.btn-green    { background:#16A34A; color:#fff; }
.btn-green:hover { background:#15803D; }
</style>

<div class="sr-report flex-1 flex flex-col overflow-hidden">
<main class="flex-1 overflow-y-auto p-6 bg-slate-50 w-full">

    <!-- PRINT HEADER -->
    <div class="print-header mb-6 text-center">
        <h1 class="text-2xl font-black uppercase tracking-widest">PSS Grocery Management System</h1>
        <h2 class="text-base font-bold text-gray-600">Official Financial Sales Report</h2>
        <p class="text-sm text-gray-500">Period: <?php echo date('F j, Y', strtotime($start_date)); ?> – <?php echo date('F j, Y', strtotime($end_date)); ?></p>
        <p class="text-xs text-gray-400 mt-1">Generated: <?php echo date('F j, Y h:i A'); ?> | Admin</p>
        <hr class="my-3 border-gray-200">
    </div>

    <!-- ======================================================== -->
    <!-- PAGE HEADER + CONTROLS                                     -->
    <!-- ======================================================== -->
    <div class="no-print mb-6">
        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-4 border-b border-slate-200 pb-5">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Financial Reports</h1>
                <p class="text-slate-400 text-sm mt-1">Revenue analytics, VAT tracking, and transaction ledger</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <!-- Export CSV -->
                <a href="?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&export=csv"
                   class="export-btn btn-green">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export CSV
                </a>
                <!-- Print -->
                <button onclick="window.print()" class="export-btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print / PDF
                </button>
            </div>
        </div>

        <!-- DATE FILTER PANEL -->
        <div class="panel p-4">
            <div class="flex flex-wrap gap-2 mb-3 items-center">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1">Quick Select:</span>
                <?php
                $presets = [
                    'today'      => 'Today',
                    'yesterday'  => 'Yesterday',
                    'this_week'  => 'This Week',
                    'last_week'  => 'Last Week',
                    'this_month' => 'This Month',
                    'last_month' => 'Last Month',
                    'this_year'  => 'This Year',
                ];
                foreach ($presets as $key => $label):
                    $is_active = ($preset === $key) ? 'active' : '';
                ?>
                <a href="?preset=<?php echo $key; ?>" class="preset-btn <?php echo $is_active; ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                           class="bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-lg p-2 font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                           class="bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-lg p-2 font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="export-btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                    Apply Filter
                </button>
            </form>
            <p class="text-xs text-slate-400 mt-3">
                Showing: <strong class="text-slate-600"><?php echo date('F j, Y', strtotime($start_date)); ?></strong>
                to <strong class="text-slate-600"><?php echo date('F j, Y', strtotime($end_date)); ?></strong>
                (<?php echo $period_days; ?> day<?php echo $period_days > 1 ? 's' : ''; ?>)
            </p>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- KPI SUMMARY CARDS                                          -->
    <!-- ======================================================== -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">

        <?php
        function delta_badge($change) {
            if ($change > 0)      return '<span class="kpi-delta up">▲ ' . abs($change) . '% vs previous period</span>';
            elseif ($change < 0)  return '<span class="kpi-delta down">▼ ' . abs($change) . '% vs previous period</span>';
            else                  return '<span class="kpi-delta flat">— No change</span>';
        }
        ?>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#3B82F6,#6366F1)"></div>
            <div class="kpi-label">Gross Revenue</div>
            <div class="kpi-value">₱<?php echo number_format($gross_revenue, 2); ?></div>
            <div class="kpi-sub">VAT-inclusive total collected</div>
            <?php echo delta_badge($revenue_change); ?>
        </div>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#10B981,#059669)"></div>
            <div class="kpi-label">Net Sales (Vatable)</div>
            <div class="kpi-value">₱<?php echo number_format($net_sales, 2); ?></div>
            <div class="kpi-sub">Revenue after extracting 12% VAT</div>
        </div>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#EF4444,#DC2626)"></div>
            <div class="kpi-label">VAT Payable (BIR)</div>
            <div class="kpi-value">₱<?php echo number_format($total_tax, 2); ?></div>
            <div class="kpi-sub">Output VAT to remit to government</div>
        </div>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#F59E0B,#D97706)"></div>
            <div class="kpi-label">Transactions</div>
            <div class="kpi-value"><?php echo number_format($total_invoices); ?></div>
            <div class="kpi-sub">Official receipts issued</div>
            <?php echo delta_badge($orders_change); ?>
        </div>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#8B5CF6,#7C3AED)"></div>
            <div class="kpi-label">Avg. Order Value</div>
            <div class="kpi-value">₱<?php echo number_format($avg_order_value, 2); ?></div>
            <div class="kpi-sub">Average per invoice in period</div>
        </div>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#EC4899,#DB2777)"></div>
            <div class="kpi-label">Largest Sale</div>
            <div class="kpi-value">₱<?php echo number_format($largest_sale, 2); ?></div>
            <div class="kpi-sub">Highest single transaction</div>
        </div>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#14B8A6,#0D9488)"></div>
            <div class="kpi-label">Total Discounts</div>
            <div class="kpi-value">₱<?php echo number_format($total_discounts, 2); ?></div>
            <div class="kpi-sub">Reductions applied in period</div>
        </div>

        <div class="kpi-card">
            <div class="accent-bar" style="background:linear-gradient(90deg,#64748B,#475569)"></div>
            <div class="kpi-label">Previous Period Revenue</div>
            <div class="kpi-value">₱<?php echo number_format($prev_revenue, 2); ?></div>
            <div class="kpi-sub"><?php echo date('M j', strtotime(explode(' ', $prev_start)[0])); ?>–<?php echo date('M j', strtotime(explode(' ', $prev_end)[0])); ?></div>
        </div>

    </div>

    <!-- ======================================================== -->
    <!-- ROW: REVENUE TREND CHART + PAYMENT MIX                    -->
    <!-- ======================================================== -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

        <!-- Daily Revenue + Orders Trend -->
        <div class="panel xl:col-span-2">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Revenue Trend</div>
                    <div class="panel-sub">Daily gross revenue and order count over the selected period</div>
                </div>
                <div class="flex gap-3 text-xs text-slate-500 items-center">
                    <span class="flex items-center gap-1"><span style="width:10px;height:10px;background:#3B82F6;border-radius:2px;display:inline-block"></span>Revenue</span>
                    <span class="flex items-center gap-1"><span style="width:10px;height:10px;background:#F59E0B;border-radius:2px;display:inline-block"></span>Orders</span>
                </div>
            </div>
            <div class="p-5" style="position:relative;height:260px">
                <?php if (!empty($daily_labels)): ?>
                <canvas id="trendChart" role="img" aria-label="Line chart of daily revenue and order count"></canvas>
                <?php else: ?>
                <div class="flex flex-col items-center justify-center h-full text-slate-400">
                    <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-sm font-medium">No sales data for this period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Payment Methods</div>
                    <div class="panel-sub">Revenue by payment type</div>
                </div>
            </div>
            <div class="p-5" style="position:relative;height:180px">
                <?php if (!empty($payment_labels)): ?>
                <canvas id="paymentChart" role="img" aria-label="Donut chart of payment method breakdown"></canvas>
                <?php else: ?>
                <div class="flex items-center justify-center h-full text-slate-400 text-sm">No payment data</div>
                <?php endif; ?>
            </div>
            <?php if (!empty($payment_labels)): ?>
            <div class="px-5 pb-4 space-y-2">
                <?php
                $pay_colors = ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444'];
                $pay_total  = array_sum($payment_totals);
                foreach ($payment_labels as $pi => $pl):
                    $pct = $pay_total > 0 ? round(($payment_totals[$pi] / $pay_total) * 100, 1) : 0;
                    $col = $pay_colors[$pi % count($pay_colors)];
                ?>
                <div class="flex justify-between items-center text-xs">
                    <span class="flex items-center gap-2">
                        <span style="width:8px;height:8px;border-radius:2px;background:<?php echo $col; ?>;display:inline-block"></span>
                        <span class="font-medium text-slate-600"><?php echo $pl; ?></span>
                    </span>
                    <span class="font-bold text-slate-800">₱<?php echo number_format($payment_totals[$pi], 2); ?> <span class="text-slate-400 font-normal">(<?php echo $pct; ?>%)</span></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ======================================================== -->
    <!-- ROW: TOP PRODUCTS + TOP CASHIERS + HOURLY HEATMAP         -->
    <!-- ======================================================== -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-5">

        <!-- Top Products -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Top Products</div>
                    <div class="panel-sub">By units sold (completed orders)</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr>
                        <th class="text-left">Product</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Revenue</th>
                    </tr></thead>
                    <tbody>
                        <?php
                        $rank = 1;
                        $has_top = false;
                        while ($tp = $top_products_result->fetch_assoc()):
                            $has_top = true;
                        ?>
                        <tr>
                            <td>
                                <div class="flex items-start gap-2">
                                    <span style="font-size:10px;font-weight:800;color:#94A3B8;min-width:14px">#<?php echo $rank++; ?></span>
                                    <div>
                                        <div class="font-semibold text-slate-800 text-xs leading-tight"><?php echo htmlspecialchars($tp['name']); ?></div>
                                        <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($tp['category']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right font-bold text-slate-700"><?php echo number_format($tp['total_qty']); ?></td>
                            <td class="text-right font-bold text-blue-700">₱<?php echo number_format($tp['total_revenue'], 0); ?></td>
                        </tr>
                        <?php endwhile; if (!$has_top): ?>
                        <tr><td colspan="3" class="text-center text-slate-400 text-xs py-8">No completed orders in this period</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Cashiers -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Cashier Performance</div>
                    <div class="panel-sub">By total sales processed</div>
                </div>
            </div>
            <div class="p-4 space-y-4">
                <?php
                $cashier_rows = [];
                while ($cr = $cashier_result->fetch_assoc()) $cashier_rows[] = $cr;
                $top_cashier_sales = !empty($cashier_rows) ? $cashier_rows[0]['total_sales'] : 1;
                if (!empty($cashier_rows)):
                    foreach ($cashier_rows as $ci => $cr):
                        $bar_pct = $top_cashier_sales > 0 ? round(($cr['total_sales'] / $top_cashier_sales) * 100) : 0;
                ?>
                <div>
                    <div class="flex justify-between items-baseline mb-1">
                        <span class="text-xs font-semibold text-slate-700">
                            <?php if ($ci === 0): ?><span class="text-amber-500 mr-1">👑</span><?php endif; ?>
                            <?php echo htmlspecialchars($cr['cashier_name']); ?>
                        </span>
                        <span class="text-xs font-black text-slate-800">₱<?php echo number_format($cr['total_sales'], 0); ?></span>
                    </div>
                    <div class="cashier-bar-bg">
                        <div class="cashier-bar-fill" style="width:<?php echo $bar_pct; ?>%"></div>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5"><?php echo $cr['tx_count']; ?> transaction<?php echo $cr['tx_count'] != 1 ? 's' : ''; ?></div>
                </div>
                <?php endforeach; else: ?>
                <div class="flex flex-col items-center justify-center h-24 text-slate-400">
                    <p class="text-sm">No cashier data in this period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hourly Heatmap -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Sales by Hour</div>
                    <div class="panel-sub">Transaction volume heat map</div>
                </div>
            </div>
            <div class="p-4">
                <?php
                $max_hourly = max($hourly_counts) ?: 1;
                $labels_am_pm = [];
                for ($h = 0; $h < 24; $h++) {
                    $labels_am_pm[] = $h == 0 ? '12a' : ($h < 12 ? $h.'a' : ($h == 12 ? '12p' : ($h-12).'p'));
                }
                ?>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                    <?php for ($h = 0; $h < 24; $h++):
                        $cnt = $hourly_counts[$h];
                        $intensity = $max_hourly > 0 ? $cnt / $max_hourly : 0;
                        $heat_class = $intensity == 0 ? 'heat-0' : ($intensity < 0.2 ? 'heat-1' : ($intensity < 0.4 ? 'heat-2' : ($intensity < 0.6 ? 'heat-3' : ($intensity < 0.8 ? 'heat-4' : 'heat-5'))));
                    ?>
                    <div class="heatmap-cell <?php echo $heat_class; ?>" 
                         title="<?php echo $labels_am_pm[$h]; ?>: <?php echo $cnt; ?> transaction<?php echo $cnt != 1 ? 's' : ''; ?> — ₱<?php echo number_format($hourly_totals[$h], 0); ?>">
                        <span style="font-size:8px;opacity:0.8"><?php echo $labels_am_pm[$h]; ?></span>
                        <span><?php echo $cnt ?: '—'; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="mt-3 flex items-center gap-2 text-[10px] text-slate-400">
                    <span>Low</span>
                    <div style="display:flex;gap:2px">
                        <div style="width:14px;height:8px;border-radius:2px" class="heat-1"></div>
                        <div style="width:14px;height:8px;border-radius:2px" class="heat-2"></div>
                        <div style="width:14px;height:8px;border-radius:2px" class="heat-3"></div>
                        <div style="width:14px;height:8px;border-radius:2px" class="heat-4"></div>
                        <div style="width:14px;height:8px;border-radius:2px" class="heat-5"></div>
                    </div>
                    <span>High</span>
                    <span class="ml-auto">Hover for details</span>
                </div>
            </div>
        </div>

    </div>

    <!-- ======================================================== -->
    <!-- DETAILED TRANSACTION LEDGER                               -->
    <!-- ======================================================== -->
    <div class="panel print-break">
        <div class="panel-header">
            <div>
                <div class="panel-title">Itemized Transaction Ledger</div>
                <div class="panel-sub">
                    <?php echo $total_invoices; ?> record<?php echo $total_invoices != 1 ? 's' : ''; ?> found for this period
                </div>
            </div>
            <a href="?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&export=csv"
               class="export-btn btn-green no-print" style="font-size:11px;padding:6px 12px;">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export CSV
            </a>
        </div>

        <!-- Searchable on the frontend -->
        <div class="p-4 border-b border-slate-100 no-print">
            <input type="text" id="ledger-search" placeholder="Search by OR number, cashier, method..."
                   class="w-full max-w-sm bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-lg p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   onkeyup="filterTable()">
        </div>

        <div class="overflow-x-auto">
            <table class="data-table" id="ledger-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>OR Number</th>
                        <th>Tracking No.</th>
                        <th>Cashier</th>
                        <th class="text-center">Method</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">VAT</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Grand Total</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($details_result->num_rows > 0): ?>
                        <?php
                        $running_total = 0;
                        while ($row = $details_result->fetch_assoc()):
                            $running_total += $row['grand_total'];
                            $method_badge = 'badge-default';
                            $orig_pm = $row['payment_method'] ?? '';
                            $display_pm = strcasecmp($orig_pm, 'prepaid') === 0 ? 'GCash (QRPH)' : $orig_pm;
                            $pm = strtolower($display_pm);
                            if (str_contains($pm, 'cash')) $method_badge = 'badge-cash';
                            elseif (str_contains($pm, 'gcash')) $method_badge = 'badge-gcash';
                            elseif (str_contains($pm, 'card') || str_contains($pm, 'credit')) $method_badge = 'badge-card';
                            elseif (str_contains($pm, 'online')) $method_badge = 'badge-online';
                        ?>
                        <tr>
                            <td class="text-slate-500 text-xs whitespace-nowrap">
                                <?php echo date('M j, Y', strtotime($row['issued_at'])); ?>
                                <span class="text-slate-400"> <?php echo date('h:i A', strtotime($row['issued_at'])); ?></span>
                            </td>
                            <td class="font-mono font-bold text-blue-800 text-xs"><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                            <td class="font-mono text-xs text-slate-500"><?php echo htmlspecialchars($row['tracking_no']); ?></td>
                            <td class="font-medium text-slate-700 text-xs"><?php echo htmlspecialchars($row['cashier_name'] ?? '—'); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $method_badge; ?>"><?php echo htmlspecialchars($display_pm); ?></span>
                            </td>
                            <td class="text-right text-slate-600 text-xs">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                            <td class="text-right text-red-600 text-xs">₱<?php echo number_format($row['tax_amount'], 2); ?></td>
                            <td class="text-right text-emerald-600 text-xs">
                                <?php echo $row['discount_amount'] > 0 ? '-₱' . number_format($row['discount_amount'], 2) : '—'; ?>
                            </td>
                            <td class="text-right font-black text-slate-900">₱<?php echo number_format($row['grand_total'], 2); ?></td>
                            <td class="text-center">
                                <span class="badge badge-<?php echo $row['order_status']; ?>"><?php echo ucfirst($row['order_status']); ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <!-- TOTALS ROW -->
                        <tr style="background:#F8FAFC;border-top:2px solid #E2E8F0;">
                            <td colspan="8" class="text-right font-black text-slate-700 text-sm py-3 px-4">PERIOD TOTAL</td>
                            <td class="text-right font-black text-slate-900 text-base py-3 px-4">₱<?php echo number_format($gross_revenue, 2); ?></td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="py-16 text-center text-slate-400">
                                <svg class="w-14 h-14 mx-auto text-slate-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="font-bold text-lg text-slate-600">No transactions found</p>
                                <p class="text-sm">Try adjusting the date range above.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    

</main>
</div>

<!-- ============================================================ -->
<!-- CHART.JS + LOGIC                                              -->
<!-- ============================================================ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
const gridColor = 'rgba(0,0,0,0.04)';
const tickColor = '#94A3B8';

<?php if (!empty($daily_labels)): ?>
// Revenue Trend Chart
const trendCtx = document.getElementById('trendChart');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($daily_labels); ?>,
        datasets: [
            {
                type: 'line',
                label: 'Orders',
                data: <?php echo json_encode($daily_orders_data); ?>,
                borderColor: '#F59E0B',
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#F59E0B',
                yAxisID: 'y2'
            },
            {
                type: 'bar',
                label: 'Revenue (₱)',
                data: <?php echo json_encode($daily_revenue); ?>,
                backgroundColor: 'rgba(59,130,246,0.75)',
                borderRadius: 6,
                borderWidth: 0,
                yAxisID: 'y'
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label === 'Revenue (₱)'
                        ? ' ₱' + ctx.parsed.y.toLocaleString('en-PH', {minimumFractionDigits:2})
                        : ' ' + ctx.parsed.y + ' orders'
                }
            }
        },
        scales: {
            x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 10 }, maxRotation: 45 } },
            y: {
                grid: { color: gridColor },
                ticks: { color: tickColor, font: { size: 10 }, callback: v => '₱' + v.toLocaleString() },
                position: 'left'
            },
            y2: {
                grid: { display: false },
                ticks: { color: '#F59E0B', font: { size: 10 } },
                position: 'right'
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($payment_labels)): ?>
// Payment Method Donut
const payColors = ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444'];
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($payment_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($payment_totals); ?>,
            backgroundColor: payColors,
            borderWidth: 0,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '62%',
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>

// Client-side ledger search
function filterTable() {
    const q = document.getElementById('ledger-search').value.toLowerCase();
    const rows = document.querySelectorAll('#ledger-table tbody tr:not(:last-child)');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php
require_once '../../includes/admin_footer.php';
$conn->close();
?>