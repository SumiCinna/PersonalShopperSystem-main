<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Inventory Management'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php
    // Session timeout configuration
    $timeout_duration = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['user_id'])) {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
        
        $elapsed_time = time() - $_SESSION['last_activity'];
        
        if ($elapsed_time > $timeout_duration) {
            session_destroy();
            header("Location: ../../inventory-login.php?expired=1");
            exit();
        }
        
        $_SESSION['last_activity'] = time();
    }
    ?>
    <!-- Sidebar Navigation -->
    <div class="fixed left-0 top-0 w-64 h-screen bg-gradient-to-b from-blue-900 to-blue-800 text-gray-100 overflow-y-auto shadow-lg flex flex-col">
        <!-- Logo Section -->
        <div class="p-6 border-b border-blue-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-lg">PSS Inventory</h1>
                    <p class="text-xs text-blue-300">Management</p>
                </div>
            </div>
            <!-- User info under logo -->
            <div class="mt-4 flex items-center gap-3 bg-blue-800 bg-opacity-50 rounded-lg px-3 py-2">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-white leading-tight"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    <p class="text-xs text-blue-300">Inventory Manager</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-4 flex flex-col flex-1">
            <div class="space-y-2">
                <!-- Dashboard Link -->
                <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?php echo (strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m2-2l6.553-6.553a1 1 0 011.414 0l6.553 6.553M3 12a9 9 0 1118 0m-9 9v-5.6a1 1 0 011-1h2a1 1 0 011 1V21"></path>
                    </svg>
                    <span class="font-medium">Dashboard</span>
                </a>

                <!-- Products Link -->
                <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?php echo (strpos($_SERVER['PHP_SELF'], 'products.php') !== false) ? 'bg-blue-600 text-white shadow-md' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <span class="font-medium">Products</span>
                </a>
            </div>

            <!-- Sign Out pushed to bottom -->
            <div class="mt-auto pt-4 border-t border-blue-700">
                <a href="../../auth/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-blue-200 hover:bg-red-600 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="font-medium">Sign Out</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="ml-64 flex flex-col min-h-screen">
        <!-- Top Header Bar -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-8 py-4">
                <h2 class="text-xl font-bold text-gray-900">
                    <?php echo isset($page_title) ? $page_title : 'Inventory Management'; ?>
                </h2>
            </div>
        </header>