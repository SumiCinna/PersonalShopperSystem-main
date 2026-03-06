<?php
// modules/admin/add_staff.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$page_title = 'Add New Staff';
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-slate-50 flex justify-center items-start">
    
    <div class="bg-white rounded-xl shadow-lg border border-slate-200 w-full max-w-2xl mt-6 overflow-hidden">
        
        <div class="bg-slate-900 text-white p-6 border-b-4 border-blue-600">
            <h2 class="text-2xl font-black tracking-widest uppercase">Register Staff</h2>
            <p class="text-slate-400 text-sm mt-1">Create a new standardized Cashier or Admin account.</p>
        </div>

        <div class="p-8">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded text-sm font-bold">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="../../core/admin/save_staff.php" method="POST" class="space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">First Name *</label>
                        <input type="text" name="firstname" required class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold" placeholder="Juan">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Middle Name <span class="text-slate-400 font-normal">(Optional)</span></label>
                        <input type="text" name="middlename" class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold" placeholder="Dela">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Surname (Last Name) *</label>
                        <input type="text" name="surname" id="surname" required class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold" placeholder="Dela Cruz">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Suffix <span class="text-slate-400 font-normal">(Optional)</span></label>
                        <input type="text" name="suffix" class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold" placeholder="Jr., III">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Email Address</label>
                        <input type="email" name="email" required class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold" placeholder="juan@pss.com">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Mobile Number *</label>
                        <input type="text" name="mobile" required pattern="09[0-9]{9}" maxlength="11" title="Please enter a valid 11-digit mobile number starting with 09" class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold" placeholder="09xxxxxxxxx">
                    </div>
                </div>

                <hr class="border-slate-200">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-slate-50 p-4 rounded-lg border border-slate-200">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Account Role</label>
                        <select name="role" id="role" class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold uppercase tracking-wider">
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Generated System ID (Username)</label>
                        <input type="text" name="username" id="username" readonly class="w-full bg-blue-100 border border-blue-300 text-blue-900 rounded block p-3 font-black font-mono text-lg tracking-wider outline-none cursor-not-allowed shadow-inner" placeholder="CAS-XXXX-0000">
                        <p class="text-[10px] text-slate-500 mt-1 font-bold uppercase">This is auto-generated and cannot be changed.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Temporary Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required class="w-full bg-white border border-slate-300 text-slate-900 rounded focus:ring-blue-500 focus:border-blue-500 block p-3 font-bold font-mono pr-10" placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-blue-600 focus:outline-none">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="pt-4 flex space-x-4">
                    <a href="staff.php" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold py-3 px-4 rounded text-center transition">Cancel</a>
                    <button type="submit" class="flex-[2] bg-blue-600 hover:bg-blue-700 text-white font-black uppercase tracking-widest py-3 px-4 rounded transition shadow-md">Create Staff Account</button>
                </div>

            </form>
        </div>
    </div>

</main>

<script>
    // Generate the 4 random digits ONCE when the page loads so they don't change while typing
    const randomDigits = Math.floor(1000 + Math.random() * 9000); 
    
    const roleSelect = document.getElementById('role');
    const surnameInput = document.getElementById('surname');
    const usernameInput = document.getElementById('username');
    
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.05 10.05 0 01-1.563 3.029m5.858.908l3.59 3.59"></path>';
        } else {
            passwordInput.type = 'password';
            eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
        }
    }

    function updateUsername() {
        const prefix = roleSelect.value === 'cashier' ? 'CAS' : 'ADM';
        
        // Grab the surname, remove spaces/special characters, and make it uppercase
        let surname = surnameInput.value.trim().toUpperCase().replace(/[^A-Z]/g, '');
        
        // Fallback if they haven't typed a valid letter yet
        if (!surname) {
            surname = 'STAFF';
        }

        // Build the final username!
        usernameInput.value = `${prefix}-${surname}-${randomDigits}`;
    }

    // Listen for typing or dropdown changes
    surnameInput.addEventListener('input', updateUsername);
    roleSelect.addEventListener('change', updateUsername);
    
    // Run once on load
    updateUsername();
</script>

<?php require_once '../../includes/admin_footer.php'; ?>