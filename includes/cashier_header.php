<?php
// includes/cashier_header.php
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
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - PSS Cashier' : 'Cashier Portal - PSS'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex min-h-screen">
    
    <aside class="w-64 bg-green-900 text-white flex flex-col hidden md:flex shadow-xl z-10">
        <div class="p-6 border-b border-green-800">
            <h2 class="text-2xl font-bold tracking-wider text-yellow-400">PSS Cashier</h2>
            <p class="text-green-300 text-sm mt-1">Operator: <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Staff'; ?></p>
        </div>
        
        <nav class="flex-1 p-4 space-y-2 mt-4">
            <a href="dashboard.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'dashboard.php' ? 'bg-green-700 text-white font-semibold shadow' : 'text-green-200 hover:bg-green-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Overview
            </a>
            <a href="pos.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'pos.php' ? 'bg-green-700 text-white font-semibold shadow' : 'text-green-200 hover:bg-green-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                POS Terminal
            </a>
            <a href="order_history.php" class="block py-2.5 px-4 rounded transition <?php echo $current_page == 'order_history.php' ? 'bg-green-700 text-white font-semibold shadow' : 'text-green-200 hover:bg-green-800 hover:text-white'; ?>">
                <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Transaction History
            </a>
        </nav>
        
        <div class="p-4 border-t border-green-800">
            <a href="../../auth/logout.php" class="block py-2.5 px-4 bg-red-600 hover:bg-red-700 text-white rounded text-center transition font-semibold shadow">Sign Out</a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">