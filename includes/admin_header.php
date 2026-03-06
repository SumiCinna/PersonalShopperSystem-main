<?php
// includes/admin_header.php
if(!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - PSS Admin' : 'Admin Control Panel - PSS'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex min-h-screen">
    
    <aside class="w-64 bg-slate-900 text-white flex flex-col hidden md:flex shadow-2xl z-10">
        <div class="p-6 border-b border-slate-800">
            <h2 class="text-2xl font-black tracking-widest text-blue-400">PSS ADMIN</h2>
            <p class="text-slate-400 text-xs mt-1 uppercase tracking-wider">Master Control Panel</p>
        </div>
        
        <nav class="flex-1 p-4 space-y-2 mt-4">
            <a href="dashboard.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'dashboard.php' ? 'bg-blue-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard Overview
            </a>
            <a href="products.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'products.php' ? 'bg-blue-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                Inventory & Products
            </a>
            <a href="sales_report.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'sales_report.php' ? 'bg-blue-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Financial Reports
            </a>
            <a href="orders.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'orders.php' ? 'bg-blue-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                Master Order Audit
            </a>
            <a href="staff.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'staff.php' || $current_page == 'add_staff.php' ? 'bg-blue-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Staff Management
            </a>
            <a href="customers.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'customers.php' ? 'bg-blue-600 text-white font-bold shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Customer Accounts
            </a>
        </nav>
        
        <div class="p-4 border-t border-slate-800">
            <div class="text-xs text-slate-400 mb-3 text-center">Logged in as: <span class="font-bold text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></span></div>
            <a href="../../auth/logout.php" class="block py-2 px-4 bg-slate-800 hover:bg-red-600 text-white rounded text-center transition font-semibold shadow border border-slate-700 hover:border-red-500">Secure Logout</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">