<?php
// modules/admin/customers.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$suspension_threshold = 3;

// Search / filter params
$search      = trim($_GET['search'] ?? '');
$filter      = $_GET['filter'] ?? 'all'; // all | highrisk | suspended
$sort        = $_GET['sort']   ?? 'risk'; // risk | newest | name

// --- Main query ---
$order_sql = match($sort) {
    'newest' => 'u.created_at DESC',
    'name'   => 'p.firstname ASC, p.surname ASC',
    default  => 'cancelled_count DESC, u.created_at DESC',
};

$query = "SELECT
            u.user_id, u.username, u.email, u.status, u.created_at,
            p.firstname, p.surname, p.mobile,
            COUNT(CASE WHEN o.order_status = 'cancelled'  THEN 1 END) AS cancelled_count,
            COUNT(CASE WHEN o.order_status = 'completed'  THEN 1 END) AS completed_count
          FROM users u
          LEFT JOIN user_profiles p ON u.user_id = p.user_id
          LEFT JOIN orders o        ON u.user_id = o.user_id
          WHERE u.role = 'customer'
          GROUP BY u.user_id
          ORDER BY $order_sql";

$result    = $conn->query($query);
$all_customers = $result->fetch_all(MYSQLI_ASSOC);

// --- Analytics (always from full list) ---
$total_customers    = count($all_customers);
$active_count       = 0;
$suspended_count    = 0;
$high_risk_count    = 0;
$total_completed    = 0;
$total_cancelled    = 0;

foreach ($all_customers as $c) {
    if ($c['status'] === 'active') $active_count++; else $suspended_count++;
    if ($c['cancelled_count'] >= $suspension_threshold && $c['status'] === 'active') $high_risk_count++;
    $total_completed += $c['completed_count'];
    $total_cancelled += $c['cancelled_count'];
}

// --- Apply search + filter to display list ---
$customers = array_filter($all_customers, function($c) use ($search, $filter, $suspension_threshold) {
    // Filter tab
    if ($filter === 'highrisk'  && !($c['cancelled_count'] >= $suspension_threshold && $c['status'] === 'active')) return false;
    if ($filter === 'suspended' && $c['status'] !== 'suspended') return false;

    // Search
    if ($search !== '') {
        $full_name = strtolower(trim($c['firstname'] . ' ' . $c['surname']));
        $hay = $full_name . strtolower($c['username']) . strtolower($c['email']);
        if (strpos($hay, strtolower($search)) === false) return false;
    }
    return true;
});

// Avatar initials helper
function initials(string $first, string $last): string {
    $f = $first ? strtoupper(substr(trim($first), 0, 1)) : '';
    $l = $last  ? strtoupper(substr(trim($last),  0, 1)) : '';
    return $f . $l ?: '??';
}

$page_title = 'Customer Accounts';
require_once '../../includes/admin_header.php';
?>

<style>
    .cust-wrap-page { padding: 2rem; background: #f8fafc; min-height: 100vh; }

    /* Header */
    .cust-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0; }
    .cust-header h1 { font-size: 1.75rem; font-weight: 800; color: #0f172a; letter-spacing: -.02em; }
    .cust-header p  { font-size: 0.875rem; color: #64748b; margin-top: 4px; }

    /* Alert */
    .alert-success { background: #f0fdf4; border-left: 3px solid #22c55e; color: #166534; padding: 12px 16px; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 600; margin-bottom: 1.25rem; }

    /* Stat row */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-bottom: 1.5rem; }
    .stat-c { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; }
    .stat-c .s-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; margin-bottom: 6px; }
    .stat-c .s-val { font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1; }

    /* Toolbar */
    .toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 1rem; align-items: center; }
    .search-wrap { position: relative; flex: 1; min-width: 220px; }
    .search-wrap svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
    .search-wrap input { width: 100%; padding: 9px 12px 9px 34px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #0f172a; background: #fff; outline: none; }
    .search-wrap input:focus { border-color: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,.15); }
    .filter-pill { padding: 7px 14px; border: 1px solid #e2e8f0; border-radius: 999px; font-size: 12px; font-weight: 600; background: #fff; color: #64748b; text-decoration: none; transition: all .15s; cursor: pointer; }
    .filter-pill:hover { background: #f1f5f9; color: #0f172a; }
    .filter-pill.pill-active   { background: #0f172a; color: #fff; border-color: #0f172a; }
    .filter-pill.pill-risk     { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
    .filter-pill.pill-risk.pill-active { background: #b91c1c; color: #fff; border-color: #b91c1c; }
    .filter-pill.pill-suspended.pill-active { background: #475569; color: #fff; border-color: #475569; }
    .sort-select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #0f172a; background: #fff; cursor: pointer; outline: none; }
    .result-count { font-size: 12px; color: #94a3b8; margin-left: auto; }

    /* Table */
    .table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
    .tbl thead tr { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .tbl th { padding: 10px 14px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; text-align: left; }
    .tbl th.center { text-align: center; }
    .tbl td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #0f172a; }
    .tbl tr:last-child td { border-bottom: none; }
    .tbl tbody tr:hover td { background: #f8fafc; }
    .tbl tbody tr.risk-row td { background: #fff5f5; }
    .tbl tbody tr.risk-row:hover td { background: #fee2e2; }
    .tbl td.center { text-align: center; }

    /* Customer cell */
    .cust-cell { display: flex; align-items: center; gap: 10px; }
    .avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; flex-shrink: 0; background: #dbeafe; color: #1e40af; }
    .avatar.av-suspended { background: #f1f5f9; color: #94a3b8; }
    .avatar.av-risk { background: #fee2e2; color: #991b1b; }
    .cust-name  { font-size: 13px; font-weight: 700; color: #0f172a; }
    .cust-user  { font-size: 11px; color: #475569; font-family: monospace; margin-top: 2px; }
    .cust-date  { font-size: 11px; color: #94a3b8; margin-top: 2px; }

    /* Contact cell */
    .c-email { font-size: 12px; color: #0f172a; }
    .c-phone { font-size: 11px; color: #94a3b8; margin-top: 3px; }

    /* Order counts */
    .ord-num { font-size: 1.1rem; font-weight: 800; }
    .ord-good { color: #15803d; }
    .ord-bad  { color: #b91c1c; }
    .ord-zero { color: #94a3b8; }
    .risk-flag { display: inline-block; margin-top: 5px; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    /* Status */
    .status-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; }
    .dot { width: 6px; height: 6px; border-radius: 50%; }
    .dot-active    { background: #22c55e; }
    .dot-suspended { background: #ef4444; }
    .s-active    { color: #166534; }
    .s-suspended { color: #991b1b; }

    /* Action buttons */
    .btn-suspend { padding: 6px 14px; font-size: 11px; font-weight: 700; border-radius: 7px; border: 1px solid #fca5a5; color: #991b1b; background: #fee2e2; cursor: pointer; transition: all .15s; width: 100%; }
    .btn-suspend:hover { background: #fecaca; }
    .btn-restore { padding: 6px 14px; font-size: 11px; font-weight: 700; border-radius: 7px; border: 1px solid #86efac; color: #166534; background: #dcfce7; cursor: pointer; transition: all .15s; width: 100%; }
    .btn-restore:hover { background: #bbf7d0; }

    /* Empty */
    .empty-state { padding: 56px; text-align: center; }
    .empty-state svg { color: #e2e8f0; margin-bottom: 12px; }
    .empty-state p { font-weight: 700; font-size: 1rem; color: #475569; }
    .empty-state span { font-size: 13px; color: #94a3b8; }

    /* Col widths */
    .col-cust    { width: 22%; }
    .col-contact { width: 20%; }
    .col-comp    { width: 12%; }
    .col-cancel  { width: 15%; }
    .col-status  { width: 13%; }
    .col-action  { width: 12%; }
</style>

<main class="flex-1 overflow-y-auto cust-wrap-page">

    <div class="cust-header">
        <div>
            <h1>Customer Accounts</h1>
            <p>Monitor shopping activity and manage abusive accounts.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert-success">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="stat-row">
        <div class="stat-c">
            <div class="s-lbl">Total customers</div>
            <div class="s-val"><?= $total_customers ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">Active</div>
            <div class="s-val" style="color:#15803d"><?= $active_count ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">Suspended</div>
            <div class="s-val" style="color:#b91c1c"><?= $suspended_count ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">High-risk</div>
            <div class="s-val" style="color:#b91c1c"><?= $high_risk_count ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">Completed orders</div>
            <div class="s-val"><?= $total_completed ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">Cancelled orders</div>
            <div class="s-val" style="color:#b91c1c"><?= $total_cancelled ?></div>
        </div>
    </div>

    <!-- Toolbar -->
    <form method="GET" action="customers.php" class="toolbar">
        <div class="search-wrap">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search name, email, or username…">
        </div>

        <?php
        $filter_tabs = [
            'all'       => 'All (' . $total_customers . ')',
            'highrisk'  => 'High-risk (' . $high_risk_count . ')',
            'suspended' => 'Suspended (' . $suspended_count . ')',
        ];
        foreach ($filter_tabs as $key => $label):
            $cls = 'filter-pill';
            if ($key === 'highrisk')  $cls .= ' pill-risk';
            if ($key === 'suspended') $cls .= ' pill-suspended';
            if ($filter === $key)     $cls .= ' pill-active';
        ?>
            <a href="?filter=<?= $key ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
               class="<?= $cls ?>"><?= $label ?></a>
        <?php endforeach; ?>

        <select name="sort" class="sort-select" onchange="this.form.submit()">
            <option value="risk"   <?= $sort === 'risk'   ? 'selected' : '' ?>>Most cancelled first</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest accounts</option>
            <option value="name"   <?= $sort === 'name'   ? 'selected' : '' ?>>Name A–Z</option>
        </select>

        <button type="submit" class="filter-pill" style="border-radius:8px;cursor:pointer;">Search</button>
        <?php if ($search): ?>
            <a href="?filter=<?= $filter ?>&sort=<?= $sort ?>" class="filter-pill" style="border-radius:8px;">Clear</a>
        <?php endif; ?>

        <span class="result-count"><?= count($customers) ?> customer<?= count($customers) !== 1 ? 's' : '' ?></span>
    </form>

    <!-- Table -->
    <div class="table-card">
        <div class="overflow-x-auto">
            <table class="tbl">
                <colgroup>
                    <col class="col-cust">
                    <col class="col-contact">
                    <col class="col-comp">
                    <col class="col-cancel">
                    <col class="col-status">
                    <col class="col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th class="center">Completed</th>
                        <th class="center">Cancelled</th>
                        <th class="center">Status</th>
                        <th class="center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                                    </svg>
                                    <p>No customers found</p>
                                    <span>Try adjusting your search or filter.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer):
                            $is_high_risk  = ($customer['cancelled_count'] >= $suspension_threshold && $customer['status'] === 'active');
                            $is_suspended  = ($customer['status'] !== 'active');
                            $display_name  = trim($customer['firstname'] . ' ' . $customer['surname']) ?: $customer['username'];
                            $av_initials   = initials($customer['firstname'] ?? '', $customer['surname'] ?? '');
                            $av_class      = $is_suspended ? 'av-suspended' : ($is_high_risk ? 'av-risk' : '');
                            $row_class     = $is_high_risk ? 'risk-row' : '';
                        ?>
                        <tr class="<?= $row_class ?>">

                            <td>
                                <div class="cust-cell">
                                    <div class="avatar <?= $av_class ?>"><?= $av_initials ?></div>
                                    <div>
                                        <div class="cust-name"><?= htmlspecialchars($display_name) ?></div>
                                        <div class="cust-user">@<?= htmlspecialchars($customer['username']) ?></div>
                                        <div class="cust-date">Joined <?= date('M j, Y', strtotime($customer['created_at'])) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="c-email"><?= htmlspecialchars($customer['email']) ?></div>
                                <div class="c-phone">
                                    <?= $customer['mobile']
                                        ? '+63 ' . htmlspecialchars(ltrim($customer['mobile'], '0'))
                                        : 'No number' ?>
                                </div>
                            </td>

                            <td class="center">
                                <span class="ord-num <?= $customer['completed_count'] > 0 ? 'ord-good' : 'ord-zero' ?>">
                                    <?= $customer['completed_count'] ?>
                                </span>
                            </td>

                            <td class="center">
                                <span class="ord-num <?= $customer['cancelled_count'] >= $suspension_threshold ? 'ord-bad' : ($customer['cancelled_count'] > 0 ? 'ord-bad' : 'ord-zero') ?>">
                                    <?= $customer['cancelled_count'] ?>
                                </span>
                                <?php if ($is_high_risk): ?>
                                    <div><span class="risk-flag">Suspension candidate</span></div>
                                <?php endif; ?>
                            </td>

                            <td class="center">
                                <?php if (!$is_suspended): ?>
                                    <span class="status-pill s-active">
                                        <span class="dot dot-active"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="status-pill s-suspended">
                                        <span class="dot dot-suspended"></span> Suspended
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="center">
                                <form id="form-<?= $customer['user_id'] ?>" action="../../core/admin/toggle_customer.php" method="POST">
                                    <input type="hidden" name="user_id"        value="<?= $customer['user_id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $customer['status'] ?>">
                                </form>
                                <?php if (!$is_suspended): ?>
                                    <button type="button" onclick="confirmForm('form-<?= $customer['user_id'] ?>', '<?= addslashes(htmlspecialchars($display_name)) ?>', false)" class="btn-suspend">Suspend</button>
                                <?php else: ?>
                                    <button type="button" onclick="confirmForm('form-<?= $customer['user_id'] ?>', '<?= addslashes(htmlspecialchars($display_name)) ?>', true)" class="btn-restore">Restore</button>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- Custom Confirmation Modal -->
<div id="confirmModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="closeModal()"></div>
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-sm overflow-hidden z-10 transform scale-95 opacity-0 transition-all duration-200" id="modalPanel">
        <div class="p-6 text-center">
            <div id="modalIcon" class="mx-auto flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mb-5">
                <!-- SVG dynamically injected -->
            </div>
            <h3 class="text-xl font-black text-slate-800 mb-2" id="modalTitle">Confirm Action</h3>
            <p class="text-sm text-slate-500 leading-relaxed" id="modalMessage">Are you sure?</p>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex gap-3 justify-center">
            <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-100 transition shadow-sm w-full">Cancel</button>
            <button type="button" id="modalConfirmBtn" class="px-5 py-2.5 rounded-xl text-sm font-black text-white transition shadow-sm w-full">Confirm</button>
        </div>
    </div>
</div>

<script>
let formToSubmit = null;

function confirmForm(formId, customerName, isSuspended) {
    formToSubmit = document.getElementById(formId);
    
    const modal = document.getElementById('confirmModal');
    const backdrop = document.getElementById('modalBackdrop');
    const panel = document.getElementById('modalPanel');
    const title = document.getElementById('modalTitle');
    const message = document.getElementById('modalMessage');
    const btn = document.getElementById('modalConfirmBtn');
    const icon = document.getElementById('modalIcon');

    if (isSuspended) {
        icon.className = 'mx-auto flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-5 text-green-600';
        icon.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" class="w-8 h-8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        
        title.textContent = 'Restore Customer';
        message.innerHTML = `Are you sure you want to restore the account for <strong class="text-slate-800">${customerName}</strong>? They will be able to order again.`;
        btn.className = 'px-5 py-2.5 rounded-xl text-sm font-black text-white bg-green-600 hover:bg-green-700 transition shadow-sm w-full';
        btn.textContent = 'Yes, Restore';
    } else {
        icon.className = 'mx-auto flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-5 text-red-600';
        icon.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" class="w-8 h-8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        
        title.textContent = 'Suspend Customer';
        message.innerHTML = `Are you sure you want to suspend the account for <strong class="text-slate-800">${customerName}</strong>? They will be unable to log in and make orders.`;
        btn.className = 'px-5 py-2.5 rounded-xl text-sm font-black text-white bg-red-600 hover:bg-red-700 transition shadow-sm w-full';
        btn.textContent = 'Yes, Suspend';
    }

    modal.classList.remove('hidden');
    // Trigger reflow for animations
    void modal.offsetWidth;
    backdrop.classList.remove('opacity-0');
    panel.classList.remove('scale-95', 'opacity-0');

    btn.onclick = function() {
        if (formToSubmit) formToSubmit.submit();
    };
}

function closeModal() {
    const backdrop = document.getElementById('modalBackdrop');
    const panel = document.getElementById('modalPanel');
    backdrop.classList.add('opacity-0');
    panel.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        document.getElementById('confirmModal').classList.add('hidden');
    }, 200);
}
</script>

<?php
require_once '../../includes/admin_footer.php';
$conn->close();
?>