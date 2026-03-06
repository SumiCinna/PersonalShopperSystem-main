<?php
// modules/admin/products.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
// If not logged in OR not an admin, kick them out to the login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

// Fetch all products from the database
$query = "SELECT product_id, name, brand, category, sku, cost_price, price, discount_price, stock, low_stock_threshold, status, description, image_url, unit_value, unit_measure FROM products ORDER BY product_id DESC";
$result = $conn->query($query);

// 1. Include the global header (HTML head, Tailwind CSS)
$page_title = 'Manage Inventory - Admin Dashboard';
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 p-8 overflow-y-auto">
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Manage Inventory</h1>
            <p class="text-gray-500 text-sm mt-1">View and manage your product catalog</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <!-- Search Bar UI -->
            <div class="relative">
                <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search products..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>

            <a href="add_product.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center justify-center transition shadow-sm text-sm">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Product
            </a>
        </div>
    </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="productsTable">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider border-b border-gray-200">
                                <th class="p-4 font-medium">SKU</th>
                                <th class="p-4 font-medium">Product Name</th>
                                <th class="p-4 font-medium">Category</th>
                                <th class="p-4 font-medium text-right">Cost (₱)</th>
                                <th class="p-4 font-medium text-right">Price (₱)</th>
                                <th class="p-4 font-medium text-center">Stock</th>
                                <th class="p-4 font-medium text-center">Status</th>
                                <th class="p-4 font-medium text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-blue-50 transition border-b border-gray-100 last:border-0">
                                        <td class="p-4 text-sm font-mono text-gray-500"><?php echo htmlspecialchars($row['sku']); ?></td>
                                        <td class="p-4">
                                            <div class="font-bold text-gray-800"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['brand']); ?></div>
                                        </td>
                                        <td class="p-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td class="p-4 text-sm text-gray-600 text-right font-mono">₱<?php echo number_format($row['cost_price'], 2); ?></td>
                                        <td class="p-4 text-sm font-bold text-gray-800 text-right font-mono">
                                            <?php if ($row['discount_price'] > 0 && $row['discount_price'] < $row['price']): ?>
                                                <span class="text-xs text-gray-400 line-through block">₱<?php echo number_format($row['price'], 2); ?></span>
                                                <span class="text-red-600">₱<?php echo number_format($row['discount_price'], 2); ?></span>
                                            <?php else: ?>
                                                ₱<?php echo number_format($row['price'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="p-4 text-center">
                                            <?php if ($row['stock'] <= 0): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Out of Stock
                                                </span>
                                            <?php elseif ($row['stock'] <= $row['low_stock_threshold']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    Low: <?php echo $row['stock']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-700 font-medium"><?php echo $row['stock']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="p-4 text-center">
                                            <?php if ($row['status'] === 'active'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-1.5"></span> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    <span class="w-2 h-2 bg-gray-500 rounded-full mr-1.5"></span> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-center flex justify-center space-x-3">
                                            <button onclick='openPreviewModal(<?php echo json_encode($row); ?>)' class="text-gray-400 hover:text-green-600 transition" title="Preview">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            </button>
                                            <a href="edit_product.php?id=<?php echo $row['product_id']; ?>" class="text-gray-400 hover:text-blue-600 transition" title="Edit">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </a>
                                            <button onclick="openDeleteModal(<?php echo $row['product_id']; ?>)" class="text-gray-400 hover:text-red-600 transition" title="Delete">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="p-12 text-center text-gray-500">
                                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                        <p class="text-lg">No products found in the inventory.</p>
                                        <p class="text-sm mt-1">Click "Add New Product" to get started!</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

<!-- Product Preview Modal -->
<div id="productPreviewModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                
                <!-- Modal Header -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-between items-center border-b border-gray-200">
                    <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">Product Details</h3>
                    <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex flex-col md:flex-row gap-8">
                        <!-- Image Section -->
                        <div class="w-full md:w-1/3 flex-shrink-0">
                            <div class="aspect-square w-full bg-gray-50 rounded-xl flex items-center justify-center overflow-hidden border border-gray-200 shadow-sm">
                                <img id="previewImage" src="" alt="Product Image" class="w-full h-full object-cover hidden">
                                <svg id="previewPlaceholder" class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        </div>

                        <!-- Details Section -->
                        <div class="w-full md:w-2/3 flex flex-col">
                            <!-- Header -->
                            <div class="mb-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h2 id="previewName" class="text-2xl font-bold text-gray-900 leading-tight">Product Name</h2>
                                        <p class="text-sm text-gray-500 mt-1 font-mono">SKU: <span id="previewSku" class="text-gray-700"></span></p>
                                    </div>
                                    <span id="previewStatus" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                                </div>
                            </div>

                            <!-- Key Metrics Box -->
                            <div class="bg-slate-50 rounded-lg p-4 border border-slate-100 grid grid-cols-3 gap-4 mb-6">
                                <div class="text-center border-r border-slate-200 last:border-0">
                                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Selling Price</p>
                                    <p id="previewPrice" class="text-xl font-bold text-blue-700 mt-1">-</p>
                                </div>
                                <div class="text-center border-r border-slate-200 last:border-0">
                                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Cost Price</p>
                                    <p id="previewCost" class="text-lg font-semibold text-slate-700 mt-1">-</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Stock Level</p>
                                    <p id="previewStock" class="text-lg font-semibold text-slate-700 mt-1">-</p>
                                </div>
                            </div>

                            <!-- Attributes -->
                            <div class="grid grid-cols-2 gap-y-4 gap-x-6 mb-6">
                                <div>
                                    <p class="text-xs text-gray-400 uppercase">Brand</p>
                                    <p id="previewBrand" class="text-sm font-medium text-gray-900">-</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 uppercase">Category</p>
                                    <p id="previewCategory" class="text-sm font-medium text-gray-900">-</p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-400 uppercase">Size / Unit</p>
                                    <p id="previewUnit" class="text-sm font-medium text-gray-900">-</p>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mt-auto">
                                <p class="text-xs text-gray-400 uppercase mb-1">Description</p>
                                <div class="bg-white border border-gray-100 rounded p-3">
                                    <p id="previewDescription" class="text-sm text-gray-600 leading-relaxed">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="button" onclick="closePreviewModal()" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Delete Product</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Are you sure you want to delete this product? This action cannot be undone.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="button" onclick="confirmDelete()" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">Delete</button>
                    <button type="button" onclick="closeDeleteModal()" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let productToDeleteId = null;

    function openPreviewModal(product) {
        // Populate Text Fields
        document.getElementById('previewName').textContent = product.name;
        document.getElementById('previewSku').textContent = product.sku;
        document.getElementById('previewBrand').textContent = product.brand || 'N/A';
        document.getElementById('previewCategory').textContent = product.category;
        
        // Handle Unit Display
        let unitDisplay = 'N/A';
        if(product.unit_value || product.unit_measure) {
            unitDisplay = (product.unit_value || '') + ' ' + (product.unit_measure || '');
        }
        document.getElementById('previewUnit').textContent = unitDisplay;

        document.getElementById('previewCost').textContent = '₱' + parseFloat(product.cost_price).toFixed(2);
        document.getElementById('previewPrice').textContent = '₱' + parseFloat(product.price).toFixed(2);
        document.getElementById('previewStock').textContent = product.stock + ' units';
        document.getElementById('previewDescription').textContent = product.description || 'No description available.';

        // Handle Status Badge
        const statusEl = document.getElementById('previewStatus');
        if (product.status === 'active') {
            statusEl.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800';
            statusEl.textContent = 'Active';
        } else {
            statusEl.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800';
            statusEl.textContent = 'Inactive';
        }

        // Handle Image
        const imgEl = document.getElementById('previewImage');
        const placeholderEl = document.getElementById('previewPlaceholder');
        
        if (product.image_url && product.image_url.trim() !== '') {
            imgEl.src = product.image_url;
            imgEl.classList.remove('hidden');
            placeholderEl.classList.add('hidden');
        } else {
            imgEl.classList.add('hidden');
            placeholderEl.classList.remove('hidden');
        }

        // Show Modal
        document.getElementById('productPreviewModal').classList.remove('hidden');
    }

    function closePreviewModal() {
        document.getElementById('productPreviewModal').classList.add('hidden');
    }

    function openDeleteModal(productId) {
        productToDeleteId = productId;
        document.getElementById('deleteConfirmationModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteConfirmationModal').classList.add('hidden');
        productToDeleteId = null;
    }

    function confirmDelete() {
        if (productToDeleteId) {
            fetch('../../core/admin/delete_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: productToDeleteId })
            })
            .then(response => response.json()) // Read the JSON response from PHP
            .then(data => {
                if (data.success) {
                    // Step 3: If successful, alert the user and refresh the page to update the table
                    alert(data.message);
                    window.location.reload(); 
                } else {
                    // If the PHP script returned an error, show it
                    alert("Error: " + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("A network error occurred while trying to delete the product.");
            });
            closeDeleteModal();
        }
    }

    function filterTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("productsTable");
        const tr = table.getElementsByTagName("tr");

        // Loop through all table rows, and hide those who don't match the search query
        for (let i = 1; i < tr.length; i++) { // Start at 1 to skip header
            let match = false;
            // Check Name (col 1) and SKU (col 0)
            const tdSku = tr[i].getElementsByTagName("td")[0];
            const tdName = tr[i].getElementsByTagName("td")[1];
            
            if (tdSku || tdName) {
                const txtValueSku = tdSku.textContent || tdSku.innerText;
                const txtValueName = tdName.textContent || tdName.innerText;
                
                if (txtValueSku.toUpperCase().indexOf(filter) > -1 || txtValueName.toUpperCase().indexOf(filter) > -1) {
                    match = true;
                }
            }
            
            if (match) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
</script>
        
        <?php require_once '../../includes/admin_footer.php'; ?>
<?php $conn->close(); ?>