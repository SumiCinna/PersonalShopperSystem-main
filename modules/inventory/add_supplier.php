<?php
session_start();
require_once '../../config/config.php';
// modules/inventory/add_supplier.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header('Location: ../../inventory-login.php');
    exit();
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name']           ?? '');
    $supplier_type  = trim($_POST['supplier_type']  ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $position       = trim($_POST['position']       ?? '');
    $phone          = '+63' . trim($_POST['phone']  ?? '');
    $email          = trim($_POST['email']          ?? '');

    $region       = trim($_POST['region']       ?? '');
    $province     = trim($_POST['province']     ?? '');
    $city         = trim($_POST['city']         ?? '');
    $barangay     = trim($_POST['barangay']     ?? '');
    $building_lot = trim($_POST['building_lot'] ?? '');
    $zip_code     = trim($_POST['zip_code']     ?? '');

    $addressParts = [];
    if (!empty($building_lot)) $addressParts[] = $building_lot;
    if (!empty($barangay))     $addressParts[] = $barangay;
    if (!empty($city))         $addressParts[] = $city;
    if (!empty($province))     $addressParts[] = $province;
    if (!empty($region))       $addressParts[] = $region;
    if (!empty($zip_code))     $addressParts[] = $zip_code;

    $address = implode(', ', $addressParts);

    $categories   = $_POST['categories']    ?? [];

    if (empty($name) || empty($supplier_type) || empty($contact_person) || empty($position) ||
        empty($region) || empty($city) || empty($barangay)) {
        $msg     = "All required fields must be filled.";
        $msgType = "error";
    } elseif (empty($categories)) {
        $msg     = "Please select at least one category, or add a new one.";
        $msgType = "error";
    } else {
        $encodedCategories = json_encode($categories);
        $sql = "INSERT INTO suppliers (name, supplier_type, contact_person, position, phone, email, address, supplied_categories, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssssss', $name, $supplier_type, $contact_person, $position,
                          $phone, $email, $address, $encodedCategories);
        if ($stmt->execute()) {
            $msg     = "Supplier successfully added.";
            $msgType = "success";
        } else {
            $msg     = "Error adding supplier: " . $conn->error;
            $msgType = "error";
        }
        $stmt->close();
    }
}

$page_title = 'Add Supplier';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50 text-slate-800">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center">
        <div>
            <h1 class="text-3xl font-bold">Add Supplier</h1>
            <p class="text-slate-500 mt-1">Register a new supplier and configure their product categories.</p>
        </div>
        <a href="purchase_orders.php"
           class="mt-4 md:mt-0 px-4 py-2 bg-slate-200 text-slate-800 rounded font-semibold hover:bg-slate-300">
            Back to PO
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm
            <?php echo $msgType === 'error'
                ? 'border-red-200 bg-red-50 text-red-700'
                : 'border-green-200 bg-green-50 text-green-700'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="w-full bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <form method="POST" id="supplierForm" class="p-6 space-y-5">

            <!-- Hidden inputs — carry text names to PHP -->
            <input type="hidden" name="region"   id="region_val">
            <input type="hidden" name="province" id="province_val">
            <input type="hidden" name="city"     id="city_val">
            <input type="hidden" name="barangay" id="barangay_val">

            <!-- Supplier Name -->
            <div>
                <label class="block text-sm font-semibold mb-1">Company / Supplier Name</label>
                <input type="text" name="name" maxlength="50" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 hover:border-blue-400"
                       placeholder="Enter company name (max 50 chars)">
            </div>

            <!-- Supplier Type -->
            <div>
                <label class="block text-sm font-semibold mb-1">Supplier Type</label>
                <select name="supplier_type" required
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
                    <option value="">Select type...</option>
                    <option value="Manufacturer">Manufacturer</option>
                    <option value="Wholesaler">Wholesaler</option>
                    <option value="Distributor">Distributor</option>
                </select>
            </div>

            <!-- Contact Person + Position -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold mb-1">Contact Person</label>
                    <input type="text" name="contact_person" maxlength="50" required
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500"
                           placeholder="Max 50 chars">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Position</label>
                    <input type="text" name="position" required maxlength="50"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500"
                           placeholder="e.g., Sales Manager">
                </div>
            </div>

            <!-- Phone + Email -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold mb-1">Phone</label>
                    <div class="flex items-center">
                        <span class="flex-shrink-0 bg-slate-100 border border-r-0 border-slate-300 rounded-l-lg px-3 py-2 text-sm font-semibold text-slate-600">+63</span>
                        <input type="text" name="phone"
                               pattern="^9[0-9]{9}$"
                               title="Must be exactly 10 digits starting with 9"
                               maxlength="10" required
                               class="w-full border border-slate-300 rounded-r-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500"
                               placeholder="9xxxxxxxxx">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" id="emailInput" maxlength="256"
                           pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$"
                           title="Must be a valid email ending with .com"
                           required
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 hover:border-blue-400 transition-colors"
                           placeholder="example@domain.com">
                    <p id="emailError" class="text-[10px] text-red-500 font-semibold mt-1 hidden">Must contain '@' and end with '.com'</p>
                </div>
            </div>

            <!-- ── Address ─────────────────────────────────────────── -->
            <div class="border-t border-slate-200 pt-5 mt-5">
                <h3 class="text-sm font-bold text-slate-800 mb-1">Complete Address</h3>
                <p class="text-xs text-slate-400 mb-4">Select each field in order — options load automatically.</p>

                <!-- Row 1: Region + Province -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Region <span class="text-red-500">*</span>
                        </label>
                        <select id="region_select"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
                            <option value="">Loading regions…</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Province <span class="text-red-500">*</span>
                        </label>
                        <select id="province_select" disabled
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 disabled:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400">
                            <option value="">Select Region first</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: City + Barangay -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            City / Municipality <span class="text-red-500">*</span>
                        </label>
                        <select id="city_select" disabled
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 disabled:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400">
                            <option value="">Select Province first</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Barangay <span class="text-red-500">*</span>
                        </label>
                        <select id="barangay_select" disabled
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 disabled:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400">
                            <option value="">Select City first</option>
                        </select>
                    </div>
                </div>

                <!-- Row 3: ZIP (auto) + Building/Lot -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            ZIP Code
                            <span class="ml-1 text-xs font-normal text-slate-400">(auto-filled)</span>
                        </label>
                        <input type="text" name="zip_code" id="zip_code" readonly
                               class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2 text-sm text-slate-500 cursor-not-allowed"
                               placeholder="Auto-filled from city">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Building / Lot No.
                            <span class="ml-1 text-xs font-normal text-slate-400">(Optional)</span>
                        </label>
                        <input type="text" name="building_lot" maxlength="50"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500"
                               placeholder="e.g., Unit 2B, Lot 5, Bldg. A">
                    </div>
                </div>
            </div>
            <!-- ── /Address ──────────────────────────────────────────── -->

            <!-- Categories -->
            <div>
                <div class="flex justify-between items-end mb-2">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Categories <span class="text-red-500">*</span></label>
                        <p class="text-xs text-slate-500">Select at least one category that this supplier can provide.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mt-3 mb-5" id="categoryGroup">
                    <?php
                    $categoriesList = [
                        'Beverages','Canned Goods','Condiments','Dairy',
                        'Fresh Produce','Noodles','Snacks','Cooking Essentials',
                        'Meat & Poultry',
                    ];
                    foreach ($categoriesList as $index => $cat) {
                        $elId = 'cat_wrap_' . $index;
                        $escapedCat = htmlspecialchars($cat, ENT_QUOTES, 'UTF-8');
                        echo "<div class='flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2.5 bg-slate-50 hover:bg-slate-100 transition-colors group' id='{$elId}'>
                                <label class='flex items-center gap-2 flex-1 cursor-pointer truncate'>
                                    <input type='checkbox' name='categories[]' value='{$escapedCat}'
                                           class='category-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4'>
                                    <span class='text-sm font-medium text-slate-700 truncate'>{$escapedCat}</span>
                                </label>
                                <button type='button' onclick=\"promptDeleteCategory('{$elId}')\" 
                                        class='text-red-500 hover:text-red-700 focus:outline-none transition-colors ml-2' 
                                        title='Remove Category'>
                                    <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'/>
                                    </svg>
                                </button>
                              </div>";
                    }
                    ?>
                </div>

                <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                    <label class="block text-sm font-bold text-blue-900 mb-1">Add Custom Category</label>
                    <p class="text-xs text-blue-700 mb-3">If the category isn't in the list above, you can add it here.</p>
                    <div class="flex gap-2">
                        <input type="text" id="new_category_input" maxlength="50"
                               class="flex-1 border border-blue-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 placeholder-blue-300 bg-white"
                               placeholder="Type a custom category name (max 50 chars)…">
                        <button type="button" id="addCatBtn"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg transition-colors whitespace-nowrap shadow-sm">
                            Add to List
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="pt-2">
                <button type="submit"
                        class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-lg hover:bg-blue-700 transition">
                    Save Supplier
                </button>
            </div>
        </form>
    </div>
</main>

<!-- Delete Category Modal -->
<div id="deleteCatModal" class="fixed inset-0 z-[100] hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 text-center mx-4">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </div>
        <h3 class="text-xl font-black text-slate-800 mb-2">Remove Category?</h3>
        <p class="text-slate-500 text-sm mb-6">Are you sure you want to remove this category from the list? It will no longer be an option.</p>
        <div class="flex gap-3 justify-center">
            <button type="button" onclick="closeDeleteCatModal()" class="px-5 py-2.5 font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors w-full">Cancel</button>
            <button type="button" id="confirmDeleteCatBtn" class="px-5 py-2.5 font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors w-full">Yes, Remove</button>
        </div>
    </div>
</div>

<script>
/* ─── PSGC API base ────────────────────────────────────────────────────── */
const API = 'https://psgc.cloud/api';
const NCR_CODE = '1300000000';

/* ─── ZIP Code map (city name → zip) ──────────────────────────────────── */
const ZIP_MAP = {
    // NCR
    'manila':1000,'city of manila':1000,
    'caloocan':1400,'city of caloocan':1400,
    'las piñas':1740,'las pinas':1740,'city of las piñas':1740,'city of las pinas':1740,
    'makati':1200,'city of makati':1200,
    'malabon':1470,'city of malabon':1470,
    'mandaluyong':1550,'city of mandaluyong':1550,
    'marikina':1800,'city of marikina':1800,
    'muntinlupa':1770,'city of muntinlupa':1770,
    'navotas':1485,'city of navotas':1485,
    'parañaque':1700,'paranaque':1700,'city of parañaque':1700,'city of paranaque':1700,
    'pasay':1300,'city of pasay':1300,
    'pasig':1600,'city of pasig':1600,
    'pateros':1620,
    'quezon city':1100,
    'san juan':1500,'city of san juan':1500,
    'taguig':1630,'city of taguig':1630,
    'valenzuela':1440,'city of valenzuela':1440,
    // Region I
    'laoag':2900,'city of laoag':2900,
    'vigan':2700,'city of vigan':2700,
    'dagupan':2400,'city of dagupan':2400,
    'urdaneta':2428,'city of urdaneta':2428,
    'san carlos':2420, // Pangasinan — overridden below for Negros
    // Region II
    'tuguegarao':3500,'city of tuguegarao':3500,
    'cauayan':3305,'city of cauayan':3305,
    'ilagan':3300,'city of ilagan':3300,
    'santiago':3311,'city of santiago':3311,
    // Region III
    'angeles':2009,'city of angeles':2009,
    'olongapo':2200,'city of olongapo':2200,
    'malolos':3000,'city of malolos':3000,
    'meycauayan':3020,'city of meycauayan':3020,
    'san jose del monte':3023,'city of san jose del monte':3023,
    'balanga':2100,'city of balanga':2100,
    'cabanatuan':3100,'city of cabanatuan':3100,
    'gapan':3105,'city of gapan':3105,
    'palayan':3132,'city of palayan':3132,
    'science city of muñoz':3119,'muñoz':3119,'munoz':3119,
    'tarlac':2300,'city of tarlac':2300,
    'san fernando':2000,'city of san fernando':2000, // Pampanga
    // Region IV-A
    'calamba':4027,'city of calamba':4027,
    'san pablo':4000,'city of san pablo':4000,
    'antipolo':1870,'city of antipolo':1870,
    'bacoor':4102,'city of bacoor':4102,
    'cavite':4100,'city of cavite':4100,
    'dasmariñas':4114,'dasmarinas':4114,'city of dasmariñas':4114,'city of dasmarinas':4114,
    'general trias':4107,'city of general trias':4107,
    'imus':4103,'city of imus':4103,
    'tagaytay':4120,'city of tagaytay':4120,
    'trece martires':4109,'city of trece martires':4109,
    'batangas':4200,'city of batangas':4200,
    'lipa':4217,'city of lipa':4217,
    'lucena':4301,'city of lucena':4301,
    'tayabas':4327,'city of tayabas':4327,
    // Region IV-B
    'puerto princesa':5300,'city of puerto princesa':5300,
    'calapan':5200,'city of calapan':5200,
    // Region V
    'legazpi':4500,'city of legazpi':4500,
    'naga':4400,'city of naga':4400,
    'iriga':4431,'city of iriga':4431,
    'ligao':4504,'city of ligao':4504,
    'tabaco':4511,'city of tabaco':4511,
    'sorsogon':4700,'city of sorsogon':4700,
    // Region VI
    'iloilo':5000,'city of iloilo':5000,
    'bacolod':6100,'city of bacolod':6100,
    'roxas':5800,'city of roxas':5800,
    'passi':5037,'city of passi':5037,
    'sagay':6122,'city of sagay':6122,
    'la carlota':6130,'city of la carlota':6130,
    'silay':6116,'city of silay':6116,
    'victorias':6119,'city of victorias':6119,
    // Region VII
    'cebu':6000,'city of cebu':6000,
    'mandaue':6014,'city of mandaue':6014,
    'lapu-lapu':6015,'city of lapu-lapu':6015,'lapulapu':6015,
    'toledo':6038,'city of toledo':6038,
    'danao':6004,'city of danao':6004,
    'carcar':6019,'city of carcar':6019,
    'bogo':6010,'city of bogo':6010,
    'dumaguete':6200,'city of dumaguete':6200,
    'bais':6206,'city of bais':6206,
    'bayawan':6221,'city of bayawan':6221,
    'canlaon':6223,'city of canlaon':6223,
    'guihulngan':6214,'city of guihulngan':6214,
    'tagbilaran':6300,'city of tagbilaran':6300,
    // Region VIII
    'tacloban':6500,'city of tacloban':6500,
    'ormoc':6541,'city of ormoc':6541,
    'borongan':6800,'city of borongan':6800,
    'maasin':6600,'city of maasin':6600,
    'baybay':6521,'city of baybay':6521,
    'calbayog':6710,'city of calbayog':6710,
    'catbalogan':6700,'city of catbalogan':6700,
    // Region IX
    'zamboanga':7000,'city of zamboanga':7000,
    'dapitan':7101,'city of dapitan':7101,
    'dipolog':7100,'city of dipolog':7100,
    'pagadian':7016,'city of pagadian':7016,
    'isabela':7300,'city of isabela':7300,
    // Region X
    'cagayan de oro':9000,'city of cagayan de oro':9000,
    'iligan':9200,'city of iligan':9200,
    'oroquieta':7207,'city of oroquieta':7207,
    'ozamiz':7200,'city of ozamiz':7200,
    'tangub':7214,'city of tangub':7214,
    'gingoog':9014,'city of gingoog':9014,
    'malaybalay':8700,'city of malaybalay':8700,
    'valencia':8709,'city of valencia':8709,
    // Region XI
    'davao':8000,'city of davao':8000,'davao city':8000,
    'tagum':8100,'city of tagum':8100,
    'panabo':8105,'city of panabo':8105,
    'digos':8002,'city of digos':8002,
    'mati':8200,'city of mati':8200,
    'samal':8119,'island garden city of samal':8119,
    // Region XII
    'koronadal':9506,'city of koronadal':9506,
    'general santos':9500,'city of general santos':9500,
    'kidapawan':9400,'city of kidapawan':9400,
    'cotabato':9600,'city of cotabato':9600,
    'tacurong':9800,'city of tacurong':9800,
    // Region XIII
    'butuan':8600,'city of butuan':8600,
    'bayugan':8502,'city of bayugan':8502,
    'bislig':8311,'city of bislig':8311,
    'surigao':8400,'city of surigao':8400,
    'tandag':8300,'city of tandag':8300,
    // CAR
    'baguio':2600,'city of baguio':2600,
    'tabuk':3800,'city of tabuk':3800,
    // BARMM
    'marawi':9700,'city of marawi':9700,
    'lamitan':7302,'city of lamitan':7302,
    'cotabato city':9600,
};

function lookupZip(cityName) {
    const key = cityName.toLowerCase().trim();
    if (ZIP_MAP[key]) return ZIP_MAP[key];
    // Strip "City of " / "Municipality of " prefix and try again
    const stripped = key.replace(/^(city of |municipality of )/i, '').trim();
    return ZIP_MAP[stripped] || '';
}

/* ─── Helpers ──────────────────────────────────────────────────────────── */
function setLoading(selectEl, msg = 'Loading…') {
    selectEl.innerHTML = `<option value="">${msg}</option>`;
    selectEl.disabled = true;
}

function populateSelect(selectEl, items, nameKey, placeholder) {
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;
    [...items]
        .sort((a, b) => a[nameKey].localeCompare(b[nameKey]))
        .forEach(item => {
            const opt = document.createElement('option');
            opt.value        = item.code;
            opt.textContent  = item[nameKey];
            opt.dataset.name = item[nameKey];
            selectEl.appendChild(opt);
        });
    selectEl.disabled = false;
}

function resetBelow(level) {
    const cascade = {
        province: [
            { sel: 'province_select', hidden: 'province_val', placeholder: 'Select Region first' },
            { sel: 'city_select',     hidden: 'city_val',     placeholder: 'Select Province first' },
            { sel: 'barangay_select', hidden: 'barangay_val', placeholder: 'Select City first' },
        ],
        city: [
            { sel: 'city_select',     hidden: 'city_val',     placeholder: 'Select Province first' },
            { sel: 'barangay_select', hidden: 'barangay_val', placeholder: 'Select City first' },
        ],
        barangay: [
            { sel: 'barangay_select', hidden: 'barangay_val', placeholder: 'Select City first' },
        ],
    };
    (cascade[level] || []).forEach(({ sel, hidden, placeholder }) => {
        const el = document.getElementById(sel);
        el.innerHTML = `<option value="">${placeholder}</option>`;
        el.disabled  = true;
        document.getElementById(hidden).value = '';
    });
    if (level !== 'barangay') document.getElementById('zip_code').value = '';
}

/* ─── API fetchers ─────────────────────────────────────────────────────── */
async function apiFetch(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

async function loadAllNCRCities() {
    // NCR has no provinces — fetch cities/municipalities directly
    const cities = await apiFetch(`${API}/regions/${NCR_CODE}/cities-municipalities/`);
    // Filter out sub-municipalities like Tondo, Binondo, etc.
    return cities.filter(c => c.type === 'City' || c.type === 'Mun');
}

/* ─── Region change ────────────────────────────────────────────────────── */
document.getElementById('region_select').addEventListener('change', async function () {
    const code = this.value;
    const name = this.options[this.selectedIndex]?.dataset.name || '';

    document.getElementById('region_val').value = name;
    resetBelow('province');

    if (!code) return;

    const provSel = document.getElementById('province_select');
    const citySel = document.getElementById('city_select');

    if (code === NCR_CODE) {
        // No provinces in NCR — pin province label, load cities directly
        provSel.innerHTML = '<option value="ncr" selected>Metro Manila (NCR)</option>';
        provSel.disabled  = true;
        document.getElementById('province_val').value = 'Metro Manila, NCR';

        setLoading(citySel, 'Loading cities…');
        try {
            const cities = await loadAllNCRCities();
            populateSelect(citySel, cities, 'name', 'Select City / Municipality…');
        } catch (e) {
            citySel.innerHTML = '<option value="">Failed to load cities</option>';
        }
    } else {
        setLoading(provSel, 'Loading provinces…');
        try {
            const provinces = await apiFetch(`${API}/regions/${code}/provinces/`);
            populateSelect(provSel, provinces, 'name', 'Select Province…');
        } catch (e) {
            provSel.innerHTML = '<option value="">Failed to load provinces</option>';
        }
    }
});

/* ─── Province change ──────────────────────────────────────────────────── */
document.getElementById('province_select').addEventListener('change', async function () {
    const code = this.value;
    const name = this.options[this.selectedIndex]?.dataset.name || '';

    document.getElementById('province_val').value = name;
    resetBelow('city');

    if (!code || code === 'ncr') return;

    const citySel = document.getElementById('city_select');
    setLoading(citySel, 'Loading cities…');
    try {
        const cities = await apiFetch(`${API}/provinces/${code}/cities-municipalities/`);
        populateSelect(citySel, cities, 'name', 'Select City / Municipality…');
    } catch (e) {
        citySel.innerHTML = '<option value="">Failed to load cities</option>';
    }
});

/* ─── City change ──────────────────────────────────────────────────────── */
document.getElementById('city_select').addEventListener('change', async function () {
    const code = this.value;
    const name = this.options[this.selectedIndex]?.dataset.name || '';

    document.getElementById('city_val').value = name;
    resetBelow('barangay');

    if (!code) return;

    // Auto-fill ZIP
    const zip = lookupZip(name);
    document.getElementById('zip_code').value = zip;

    const brgySel = document.getElementById('barangay_select');
    setLoading(brgySel, 'Loading barangays…');
    try {
        const barangays = await apiFetch(`${API}/cities-municipalities/${code}/barangays/`);
        populateSelect(brgySel, barangays, 'name', 'Select Barangay…');
    } catch (e) {
        brgySel.innerHTML = '<option value="">Failed to load barangays</option>';
    }
});

/* ─── Barangay change ──────────────────────────────────────────────────── */
document.getElementById('barangay_select').addEventListener('change', function () {
    const name = this.options[this.selectedIndex]?.dataset.name || '';
    document.getElementById('barangay_val').value = name;
});

/* ─── Form validation ──────────────────────────────────────────────────── */
document.getElementById('emailInput').addEventListener('input', function() {
    const val = this.value;
    const errorEl = document.getElementById('emailError');
    if (val && (!val.includes('@') || !val.endsWith('.com'))) {
        errorEl.classList.remove('hidden');
        this.classList.add('border-red-500', 'focus:ring-red-500');
    } else {
        errorEl.classList.add('hidden');
        this.classList.remove('border-red-500', 'focus:ring-red-500');
    }
});

document.getElementById('supplierForm').addEventListener('submit', function (e) {
    // Category check
    const checkedCount  = document.querySelectorAll('.category-checkbox:checked').length;
    if (checkedCount === 0) {
        e.preventDefault();
        alert('Please select at least one category.');
        return;
    }

    // Address check
    const addressFields = [
        { id: 'region_val',   label: 'Region',             focus: 'region_select' },
        { id: 'city_val',     label: 'City / Municipality', focus: 'city_select' },
        { id: 'barangay_val', label: 'Barangay',            focus: 'barangay_select' },
    ];
    for (const { id, label, focus } of addressFields) {
        if (!document.getElementById(id).value.trim()) {
            e.preventDefault();
            alert(`Please select a ${label}.`);
            document.getElementById(focus).focus();
            return;
        }
    }
});

/* ─── Category Management ────────────────────────────────────────────────── */
let catIdToDelete = null;

window.promptDeleteCategory = function(elementId) {
    catIdToDelete = elementId;
    document.getElementById('deleteCatModal').classList.remove('hidden');
};

window.closeDeleteCatModal = function() {
    document.getElementById('deleteCatModal').classList.add('hidden');
    catIdToDelete = null;
};

document.getElementById('confirmDeleteCatBtn').addEventListener('click', function() {
    if (catIdToDelete) {
        const el = document.getElementById(catIdToDelete);
        if (el) el.remove();
    }
    closeDeleteCatModal();
});

document.getElementById('addCatBtn').addEventListener('click', function() {
    const input = document.getElementById('new_category_input');
    const val = input.value.trim();
    if (!val) return;
    
    const escapedVal = val.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;").replace(/"/g, "&quot;");
    const id = 'cat_wrap_custom_' + Date.now();
    const html = `
        <div class='flex items-center justify-between border border-blue-200 rounded-lg px-3 py-2.5 bg-blue-50 hover:bg-blue-100 transition-colors group' id='${id}'>
            <label class='flex items-center gap-2 flex-1 cursor-pointer truncate'>
                <input type='checkbox' name='categories[]' value='${escapedVal}' checked
                       class='category-checkbox rounded border-blue-400 text-blue-600 focus:ring-blue-500 w-4 h-4'>
                <span class='text-sm font-medium text-blue-900 truncate'>${escapedVal}</span>
            </label>
            <button type='button' onclick="promptDeleteCategory('${id}')" 
                    class='text-red-500 hover:text-red-700 focus:outline-none transition-colors ml-2' 
                    title='Remove Category'>
                <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'/>
                </svg>
            </button>
        </div>
    `;
    input.value = '';
    document.getElementById('categoryGroup').insertAdjacentHTML('beforeend', html);
});

document.getElementById('new_category_input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('addCatBtn').click();
    }
});

/* ─── Boot: load regions ───────────────────────────────────────────────── */
(async () => {
    const regionSel = document.getElementById('region_select');
    setLoading(regionSel, 'Loading regions…');
    try {
        const regions = await apiFetch(`${API}/regions/`);
        populateSelect(regionSel, regions, 'name', 'Select Region…');
    } catch (e) {
        regionSel.innerHTML = '<option value="">Failed to load regions</option>';
        console.error('Region load failed:', e);
    }
})();
</script>

<?php require_once '../../includes/inventory_footer.php'; ?>