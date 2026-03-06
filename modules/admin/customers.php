<?php
// modules/admin/customers.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$suspension_threshold = 3; // The rule: 3 cancelled orders flags the account

// The "God-Eye" Query: Grabs users, their real names, and counts their specific order types
$query = "SELECT 
            u.user_id, u.username, u.email, u.status, u.created_at,
            p.firstname, p.surname, p.mobile,
            COUNT(CASE WHEN o.order_status = 'cancelled' THEN 1 END) as cancelled_count,
            COUNT(CASE WHEN o.order_status = 'completed' THEN 1 END) as completed_count
          FROM users u
          LEFT JOIN user_profiles p ON u.user_id = p.user_id
          LEFT JOIN orders o ON u.user_id = o.user_id
          WHERE u.role = 'customer'
          GROUP BY u.user_id
          ORDER BY cancelled_count DESC, u.created_at DESC";
          
$result = $conn->query($query);

// Quick analytics for the header
$total_customers = $result->num_rows;
$high_risk_count = 0;
$customers = [];

while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
    if ($row['cancelled_count'] >= $suspension_threshold && $row['status'] === 'active') {
        $high_risk_count++;
    }
}

$page_title = 'Customer Management';
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-slate-50">
    
    <div class="flex justify-between items-center mb-8 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Customer Accounts</h1>
            <p class="text-slate-500 mt-1">Monitor shopping activity and handle abusive accounts.</p>
        </div>
        
        <div class="flex space-x-4">
            <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-200 text-sm font-semibold text-slate-600">
                Total Users: <span class="text-blue-600 font-black text-lg"><?php echo $total_customers; ?></span>
            </div>
            <?php if ($high_risk_count > 0): ?>
                <div class="bg-red-50 px-4 py-2 rounded-lg shadow-sm border border-red-200 text-sm font-bold text-red-800 animate-pulse">
                    High Risk Accounts: <span class="text-red-600 font-black text-lg"><?php echo $high_risk_count; ?></span>
                </div>
            <?php endif; ?>
        </div>
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
                    <th class="p-4 font-bold">Customer Details</th>
                    <th class="p-4 font-bold">Contact Info</th>
                    <th class="p-4 font-bold text-center">Completed Orders</th>
                    <th class="p-4 font-bold text-center">Cancelled Orders</th>
                    <th class="p-4 font-bold text-center">System Status</th>
                    <th class="p-4 font-bold text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($customers) > 0): ?>
                    <?php foreach ($customers as $customer): ?>
                        <?php 
                            $is_high_risk = ($customer['cancelled_count'] >= $suspension_threshold);
                            $row_class = ($is_high_risk && $customer['status'] === 'active') ? 'bg-red-50/30' : 'hover:bg-slate-50';
                        ?>
                        <tr class="<?php echo $row_class; ?> transition">
                            
                            <td class="p-4">
                                <div class="font-bold text-slate-900 text-base">
                                    <?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['surname']); ?>
                                </div>
                                <div class="text-xs text-slate-500 font-mono mt-1">@<?php echo htmlspecialchars($customer['username']); ?></div>
                                <div class="text-[10px] text-slate-400 mt-1">Joined: <?php echo date('M j, Y', strtotime($customer['created_at'])); ?></div>
                            </td>
                            
                            <td class="p-4">
                                <div class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($customer['email']); ?></div>
                                <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($customer['mobile'] ?? 'No number'); ?></div>
                            </td>
                            
                            <td class="p-4 text-center">
                                <span class="font-black text-green-600 text-lg"><?php echo $customer['completed_count']; ?></span>
                            </td>
                            
                            <td class="p-4 text-center">
                                <span class="font-black text-lg <?php echo $is_high_risk ? 'text-red-600' : 'text-slate-600'; ?>">
                                    <?php echo $customer['cancelled_count']; ?>
                                </span>
                                <?php if ($is_high_risk && $customer['status'] === 'active'): ?>
                                    <div class="mt-1">
                                        <span class="bg-red-100 text-red-800 text-[9px] font-black px-2 py-0.5 rounded uppercase tracking-widest border border-red-200">Suspension Candidate</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="p-4 text-center">
                                <?php if ($customer['status'] === 'active'): ?>
                                    <span class="inline-flex items-center bg-green-100 text-green-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider border border-green-200">
                                        <span class="w-2 h-2 rounded-full bg-green-500 mr-1.5 animate-pulse"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center bg-red-100 text-red-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider border border-red-200">
                                        <span class="w-2 h-2 rounded-full bg-red-500 mr-1.5"></span> Suspended
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="p-4 text-center">
                                <form action="../../core/admin/toggle_customer.php" method="POST" onsubmit="return confirm('Are you sure you want to change this customer\'s account status?');">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['user_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $customer['status']; ?>">
                                    
                                    <?php if ($customer['status'] === 'active'): ?>
                                        <button type="submit" class="text-xs font-bold text-red-600 hover:text-white hover:bg-red-600 bg-red-50 px-4 py-2 rounded transition border border-red-200 w-full shadow-sm">
                                            Suspend Account
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="text-xs font-bold text-green-600 hover:text-white hover:bg-green-600 bg-green-50 px-4 py-2 rounded transition border border-green-200 w-full shadow-sm">
                                            Restore Access
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-12 text-center text-slate-500">
                            <p class="font-bold text-lg text-slate-700">No customers registered yet.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<?php 
require_once '../../includes/admin_footer.php'; 
$conn->close(); 
?>