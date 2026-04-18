<?php
// modules/admin/staff.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$query = "SELECT
            u.user_id, u.username, u.email, u.role, u.status, u.last_login, u.created_at,
            up.mobile,
            CONCAT_WS(' ', up.firstname, up.middlename, up.surname) AS full_name
          FROM users u
          LEFT JOIN user_profiles up ON u.user_id = up.user_id
          WHERE u.role IN ('cashier', 'inventory')
          ORDER BY u.role ASC, u.username ASC";
$result = $conn->query($query);
$staff_list = $result->fetch_all(MYSQLI_ASSOC);

// Build stat counts
$counts = ['cashier' => 0, 'inventory' => 0, 'suspended' => 0];
foreach ($staff_list as $s) {
    if (isset($counts[$s['role']])) $counts[$s['role']]++;
    if ($s['status'] !== 'active') $counts['suspended']++;
}
$total_staff = count($staff_list);

// Avatar initials helper
function initials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

$page_title = 'Staff Management';
require_once '../../includes/admin_header.php';
?>

<style>
    .staff-wrap { padding: 2rem; background: #f8fafc; min-height: 100vh; }

    /* Header */
    .staff-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0; }
    .staff-header h1 { font-size: 1.75rem; font-weight: 800; color: #0f172a; letter-spacing: -.02em; }
    .staff-header p  { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
    .add-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: #1d4ed8; color: #fff; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: background .15s; }
    .add-btn:hover { background: #1e40af; }
    .add-btn svg { width: 15px; height: 15px; }

    /* Alert */
    .alert-success { background: #f0fdf4; border-left: 3px solid #22c55e; color: #166534; padding: 12px 16px; border-radius: 0 8px 8px 0; font-size: 13px; font-weight: 600; margin-bottom: 1.25rem; }

    /* Stat row */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-bottom: 1.5rem; }
    .stat-c { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; }
    .stat-c .s-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; margin-bottom: 6px; }
    .stat-c .s-val { font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1; }

    /* Staff cards */
    .staff-grid { display: grid; gap: 10px; }
    .s-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; display: grid; grid-template-columns: 48px 1fr auto auto; align-items: center; gap: 12px 16px; transition: border-color .15s; }
    .s-card:hover { border-color: #94a3b8; }
    .s-card.suspended { opacity: .75; }

    /* Avatar */
    .avatar { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 800; flex-shrink: 0; }
    .av-admin     { background: #ede9fe; color: #5b21b6; }
    .av-cashier   { background: #d1fae5; color: #065f46; }
    .av-inventory { background: #fef3c7; color: #92400e; }

    /* Info block */
    .s-name     { font-size: 14px; font-weight: 700; color: #0f172a; }
    .s-username { font-size: 12px; color: #475569; margin-top: 2px; }
    .s-email    { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .s-phone    { font-size: 11px; color: #94a3b8; margin-top: 1px; }

    /* Meta column */
    .meta-col { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; border-width: 1px; border-style: solid; }
    .b-admin     { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
    .b-cashier   { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
    .b-inventory { background: #fef3c7; color: #92400e; border-color: #fde68a; }

    .status-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; }
    .dot { width: 6px; height: 6px; border-radius: 50%; }
    .dot-active    { background: #22c55e; }
    .dot-suspended { background: #ef4444; }
    .status-active    { color: #166534; }
    .status-suspended { color: #991b1b; }

    .last-login { font-size: 11px; color: #94a3b8; }

    /* Action column */
    .action-col { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; min-width: 110px; }
    .btn-suspend { padding: 6px 14px; font-size: 11px; font-weight: 700; border-radius: 7px; border: 1px solid #fca5a5; color: #991b1b; background: #fee2e2; cursor: pointer; transition: all .15s; }
    .btn-suspend:hover { background: #fecaca; }
    .btn-restore { padding: 6px 14px; font-size: 11px; font-weight: 700; border-radius: 7px; border: 1px solid #86efac; color: #166534; background: #dcfce7; cursor: pointer; transition: all .15s; }
    .btn-restore:hover { background: #bbf7d0; }
    .you-label { font-size: 11px; color: #94a3b8; font-style: italic; }

    /* Empty */
    .empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 14px; }
</style>

<main class="flex-1 overflow-y-auto staff-wrap">

    <div class="staff-header">
        <div>
            <h1>Staff Management</h1>
            <p>Manage cashiers and administrators for the POS system.</p>
        </div>
        <a href="add_staff.php" class="add-btn">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Add New Staff
        </a>
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
            <div class="s-lbl">Total staff</div>
            <div class="s-val"><?= $total_staff ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">Cashiers</div>
            <div class="s-val" style="color:#065f46"><?= $counts['cashier'] ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">Inventory</div>
            <div class="s-val" style="color:#92400e"><?= $counts['inventory'] ?></div>
        </div>
        <div class="stat-c">
            <div class="s-lbl">Suspended</div>
            <div class="s-val" style="color:#b91c1c"><?= $counts['suspended'] ?></div>
        </div>
    </div>

    <!-- Staff cards -->
    <div class="staff-grid">
        <?php if (empty($staff_list)): ?>
            <div class="empty">No staff members found.</div>
        <?php endif; ?>

        <?php foreach ($staff_list as $staff):
            $display_name = trim($staff['full_name']) ?: $staff['username'];
            $initials     = initials($display_name);
            $is_me        = $staff['user_id'] === $_SESSION['user_id'];
            $is_suspended = $staff['status'] !== 'active';

            $av_class = match($staff['role']) {
                'admin'     => 'av-admin',
                'inventory' => 'av-inventory',
                default     => 'av-cashier',
            };
            $badge_class = match($staff['role']) {
                'admin'     => 'b-admin',
                'inventory' => 'b-inventory',
                default     => 'b-cashier',
            };
        ?>
        <div class="s-card <?= $is_suspended ? 'suspended' : '' ?>">

            <!-- Avatar -->
            <div class="avatar <?= $av_class ?>"><?= $initials ?></div>

            <!-- Info -->
            <div>
                <div class="s-name"><?= htmlspecialchars($display_name) ?></div>
                <div class="s-username">@<?= htmlspecialchars($staff['username']) ?></div>
                <div class="s-email"><?= htmlspecialchars($staff['email']) ?></div>
                <?php if (!empty($staff['mobile'])): ?>
                    <div class="s-phone">+63 <?= htmlspecialchars(ltrim($staff['mobile'], '0')) ?></div>
                <?php endif; ?>
            </div>

            <!-- Meta: badge, status, last login -->
            <div class="meta-col">
                <span class="badge <?= $badge_class ?>"><?= ucfirst($staff['role']) ?></span>

                <?php if ($is_suspended): ?>
                    <span class="status-pill status-suspended">
                        <span class="dot dot-suspended"></span> Suspended
                    </span>
                <?php else: ?>
                    <span class="status-pill status-active">
                        <span class="dot dot-active"></span> Active
                    </span>
                <?php endif; ?>

                <span class="last-login">
                    <?= $staff['last_login']
                        ? date('M j, Y · h:i A', strtotime($staff['last_login']))
                        : 'Never logged in' ?>
                </span>
            </div>

            <!-- Action -->
            <div class="action-col">
                <?php if ($is_me): ?>
                    <span class="you-label">You (current user)</span>
                <?php else: ?>
                    <form id="form-<?= $staff['user_id'] ?>" action="../../core/admin/toggle_staff.php" method="POST">
                        <input type="hidden" name="user_id"        value="<?= $staff['user_id'] ?>">
                        <input type="hidden" name="current_status" value="<?= $staff['status'] ?>">
                    </form>
                    <?php if (!$is_suspended): ?>
                        <button type="button" onclick="confirmForm('form-<?= $staff['user_id'] ?>', '<?= addslashes(htmlspecialchars($display_name)) ?>', false)" class="btn-suspend">Suspend Access</button>
                    <?php else: ?>
                        <button type="button" onclick="confirmForm('form-<?= $staff['user_id'] ?>', '<?= addslashes(htmlspecialchars($display_name)) ?>', true)" class="btn-restore">Restore Access</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
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

function confirmForm(formId, staffName, isSuspended) {
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
        
        title.textContent = 'Restore Staff Access';
        message.innerHTML = `Are you sure you want to restore system access for <strong class="text-slate-800">${staffName}</strong>?`;
        btn.className = 'px-5 py-2.5 rounded-xl text-sm font-black text-white bg-green-600 hover:bg-green-700 transition shadow-sm w-full';
        btn.textContent = 'Yes, Restore';
    } else {
        icon.className = 'mx-auto flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-5 text-red-600';
        icon.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" class="w-8 h-8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
        
        title.textContent = 'Suspend Staff Access';
        message.innerHTML = `Are you sure you want to suspend access for <strong class="text-slate-800">${staffName}</strong>? They will be immediately blocked.`;
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