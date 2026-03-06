<?php
// modules/admin/staff.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

// Fetch all staff members (excluding normal customers)
$query = "SELECT 
            u.user_id, u.username, u.email, u.role, u.status, u.last_login, u.created_at,
            up.mobile,
            CONCAT_WS(' ', up.firstname, up.middlename, up.surname) AS full_name
          FROM users u
          LEFT JOIN user_profiles up ON u.user_id = up.user_id
          WHERE u.role IN ('admin', 'cashier') 
          ORDER BY u.role ASC, u.username ASC";
$result = $conn->query($query);

$page_title = 'Staff Management';
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-slate-50">
    
    <div class="flex justify-between items-center mb-8 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Staff Management</h1>
            <p class="text-slate-500 mt-1">Manage Cashiers and Administrators for the POS system.</p>
        </div>
        <a href="add_staff.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition shadow-md flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add New Staff
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-bold">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-800 text-white text-xs uppercase tracking-wider">
                    <th class="p-4 font-bold">Staff Member</th>
                    <th class="p-4 font-bold">Role</th>
                    <th class="p-4 font-bold">Status</th>
                    <th class="p-4 font-bold">Last Login</th>
                    <th class="p-4 font-bold text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while ($staff = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-4">
                            <div class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars(trim($staff['full_name']) ?: $staff['username']); ?></div>
                            <div class="text-sm text-slate-600"><?php echo htmlspecialchars($staff['username']); ?></div>
                            <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($staff['email']); ?></div>
                            <?php if (!empty($staff['mobile'])): ?>
                                <div class="text-xs text-slate-500 mt-1">Tel: <?php echo htmlspecialchars($staff['mobile']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="p-4">
                            <?php if ($staff['role'] === 'admin'): ?>
                                <span class="bg-purple-100 text-purple-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider border border-purple-200">Admin</span>
                            <?php else: ?>
                                <span class="bg-blue-100 text-blue-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider border border-blue-200">Cashier</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4">
                            <?php if ($staff['status'] === 'active'): ?>
                                <span class="text-green-600 font-bold flex items-center text-sm">
                                    <span class="h-2 w-2 rounded-full bg-green-500 mr-2"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="text-red-600 font-bold flex items-center text-sm">
                                    <span class="h-2 w-2 rounded-full bg-red-500 mr-2"></span> Suspended
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-sm text-slate-600">
                            <?php echo $staff['last_login'] ? date('M j, Y h:i A', strtotime($staff['last_login'])) : 'Never Logged In'; ?>
                        </td>
                        <td class="p-4 text-center">
                            <?php if ($staff['user_id'] !== $_SESSION['user_id']): // Prevent Admin from suspending themselves! ?>
                                <form action="../../core/admin/toggle_staff.php" method="POST" onsubmit="return confirm('Are you sure you want to change this staff member\'s access?');">
                                    <input type="hidden" name="user_id" value="<?php echo $staff['user_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $staff['status']; ?>">
                                    
                                    <?php if ($staff['status'] === 'active'): ?>
                                        <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition border border-red-200">Suspend Access</button>
                                    <?php else: ?>
                                        <button type="submit" class="text-xs font-bold text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 px-3 py-1.5 rounded transition border border-green-200">Restore Access</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 font-bold italic">You (Current User)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</main>

<?php 
require_once '../../includes/admin_footer.php'; 
$conn->close(); 
?>