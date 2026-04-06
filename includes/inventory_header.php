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
    $timeout_duration = 1800;
    
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

    <!-- Sidebar -->
    <div class="fixed left-0 top-0 w-64 h-screen bg-gradient-to-b from-blue-900 to-blue-800 text-gray-100 overflow-y-auto shadow-lg flex flex-col">

        <!-- Logo & User -->
        <div class="p-6 border-b border-blue-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                    <!-- Warehouse / box icon -->
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">PSS Inventory</h1>
                    <p class="text-xs text-blue-300">Management System</p>
                </div>
            </div>

            <!-- User Badge -->
            <div class="mt-4 flex items-center gap-3 bg-blue-800 bg-opacity-50 rounded-lg px-3 py-2">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <!-- User circle icon -->
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

        <!-- Nav Links -->
        <nav class="p-4 flex flex-col flex-1">
            <div class="space-y-1">

                <!-- Dashboard -->
                <a href="dashboard.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                          <?php echo (basename($_SERVER['PHP_SELF']) === 'dashboard.php')
                                     ? 'bg-blue-600 text-white shadow-md'
                                     : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                    <!-- Home / grid icon -->
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="font-medium">Dashboard</span>
                </a>

                <!-- Products -->
                <a href="products.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                          <?php echo (basename($_SERVER['PHP_SELF']) === 'products.php')
                                     ? 'bg-blue-600 text-white shadow-md'
                                     : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                    <!-- Tag / product icon -->
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <span class="font-medium">Products</span>
                </a>

                <!-- Inventory Audit -->
                <a href="inventory_audit.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                          <?php echo (basename($_SERVER['PHP_SELF']) === 'inventory_audit.php')
                                     ? 'bg-blue-600 text-white shadow-md'
                                     : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                    <!-- Clipboard check icon -->
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                                 M9 5a2 2 0 002 2h2a2 2 0 002-2
                                 M9 5a2 2 0 012-2h2a2 2 0 012 2
                                 m-6 9l2 2 4-4"/>
                    </svg>
                    <span class="font-medium">Inventory Audit</span>
                </a>

            </div>

            <!-- Sign Out -->
            <div class="mt-auto pt-4 border-t border-blue-700">
                <a href="../../auth/logout.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-blue-200 hover:bg-red-600 hover:text-white transition">
                    <!-- Logout icon -->
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="font-medium">Sign Out</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Wrapper -->
    <div class="ml-64 flex flex-col min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="px-8 py-4">
                <h2 class="text-xl font-bold text-gray-900">
                    <?php echo isset($page_title) ? $page_title : 'Inventory Management'; ?>
                </h2>
            </div>
        </header>