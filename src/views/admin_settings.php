<?php
require_once "../../config/config.php";

// Admin session check
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Fetch admin details
$admin_email = $_SESSION['admin_email'];

// Fetch platform settings
// Fetch platform settings
$settings = $conn->query("SELECT allow_registration FROM platform_settings LIMIT 1");
if ($settings && $settings->num_rows == 0) {
    // Create default settings row if missing
    $conn->query("INSERT INTO platform_settings (allow_registration) VALUES (1)");
    $allow_reg = 1;
} else {
    $allow_reg = $settings ? $settings->fetch_assoc()['allow_registration'] : 0;
}

// Fetch System Settings (Maintenance)
$sys_settings = [];
try {
    $sys_res = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($sys_res) {
        while ($row = $sys_res->fetch_assoc()) {
            $sys_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // Table might not exist yet, ignore error
    error_log("System settings table missing: " . $e->getMessage());
}
$maintenance_mode = isset($sys_settings['maintenance_mode']) && $sys_settings['maintenance_mode'] == '1';
$maintenance_end = isset($sys_settings['maintenance_end_time']) ? $sys_settings['maintenance_end_time'] : '';

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_start.php";
?>

<div class="max-w-5xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl">

    <h1 class="text-4xl font-extrabold text-white mb-8 text-center">Admin Settings</h1>

    <!-- Tabs Navigation -->
    <div class="flex border-b border-gray-700 mb-6">
        <button class="tab-btn px-6 py-3 text-lg font-semibold text-gray-300 hover:text-white transition border-b-2 border-transparent"
                onclick="switchTab('account')"
                id="tab-account-btn">
            Account Settings
        </button>

        <button class="tab-btn px-6 py-3 text-lg font-semibold text-gray-300 hover:text-white transition border-b-2 border-transparent"
                onclick="switchTab('platform')"
                id="tab-platform-btn">
            Platform Settings
        </button>
    </div>

    <!-- TAB CONTENT -->

    <!-- ACCOUNT SETTINGS -->
    <div id="tab-account" class="tab-content">

        <!-- Change Email -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-md border border-gray-700 mb-8">
            <h2 class="text-2xl font-bold text-blue-300 mb-4">Change Email</h2>

            <label class="block text-gray-300 mb-1">Current Email</label>
            <input type="text" disabled value="<?php echo $admin_email; ?>"
                   class="w-full px-3 py-2 mb-4 rounded bg-gray-700 text-gray-300 border border-gray-600">

            <label class="block text-gray-300 mb-1">New Email</label>
            <input id="newEmail" type="email"
                   class="w-full px-3 py-2 rounded bg-gray-700 text-gray-200 border border-gray-600 mb-4">

            <button onclick="updateEmail()"
                    class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                Update Email
            </button>
        </div>

        <!-- Change Password -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-md border border-gray-700">
            <h2 class="text-2xl font-bold text-purple-300 mb-4">Change Password</h2>

            <label class="block text-gray-300 mb-1">Current Password</label>
            <input id="currentPass" type="password"
                   class="w-full px-3 py-2 mb-4 rounded bg-gray-700 text-gray-200 border border-gray-600">

            <label class="block text-gray-300 mb-1">New Password</label>
            <input id="newPass" type="password"
                   class="w-full px-3 py-2 mb-4 rounded bg-gray-700 text-gray-200 border border-gray-600">

            <button onclick="updatePassword()"
                    class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg">
                Update Password
            </button>
        </div>

    </div>

    <!-- PLATFORM SETTINGS -->
    <div id="tab-platform" class="tab-content hidden">

        <div class="bg-gray-800 p-6 rounded-lg shadow-md border border-gray-700 mb-6">
            <h2 class="text-2xl font-bold text-yellow-300 mb-4">Registration Control</h2>

            <p class="text-gray-300 mb-4">
                Toggle whether new companies can sign up on the platform.
            </p>

            <label class="flex items-center gap-3">
                <input type="checkbox" id="regToggle"
                       <?php echo $allow_reg ? 'checked' : ''; ?>
                       class="w-5 h-5 text-yellow-400 bg-gray-700 border-gray-600 rounded focus:ring-yellow-500">
                <span class="text-gray-200 text-lg">Allow New Registrations</span>
            </label>

            <button onclick="updateRegistrationSetting()"
                    class="mt-5 px-5 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg">
                Save Changes
            </button>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg shadow-md border border-gray-700 bg-opacity-50">
            <h2 class="text-2xl font-bold text-red-400 mb-4">Maintenance Mode</h2>
            <p class="text-gray-300 mb-4">
                Enable to block user access (Admins can still login).
            </p>

            <div class="space-y-4">
                <label class="flex items-center gap-3">
                    <input type="checkbox" id="maintToggle"
                           <?php echo $maintenance_mode ? 'checked' : ''; ?>
                           class="w-5 h-5 text-red-500 bg-gray-700 border-gray-600 rounded focus:ring-red-500">
                    <span class="text-gray-200 text-lg">Enable Maintenance Mode</span>
                </label>

                <div id="maintenanceTimeContainer" class="<?php echo $maintenance_mode ? '' : 'hidden'; ?>">
                    <label class="block text-gray-300 mb-1">Expected Completion Time</label>
                    <input type="datetime-local" id="maintTime" value="<?php echo $maintenance_end ? date('Y-m-d\TH:i', strtotime($maintenance_end)) : ''; ?>"
                           class="w-full max-w-xs px-3 py-2 rounded bg-gray-700 text-gray-200 border border-gray-600">
                </div>

                <button onclick="updateMaintenanceSetting()"
                        class="mt-2 px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    Update Maintenance Mode
                </button>
            </div>
        </div>

    </div>

</div>

<script>
const csrfToken = "<?php echo Csrf::generateToken(); ?>";

// ===== Tabs =====
function switchTab(tab) {
    document.querySelectorAll(".tab-content").forEach(el => el.classList.add("hidden"));
    document.querySelectorAll(".tab-btn").forEach(el => el.classList.remove("border-blue-400", "text-white"));

    document.getElementById("tab-" + tab).classList.remove("hidden");
    document.getElementById("tab-" + tab + "-btn").classList.add("border-blue-400", "text-white");
}

// Set default tab
switchTab("account");

// ===== Update Email =====
function updateEmail() {
    const email = document.getElementById("newEmail").value;
    if (!email || email.length < 5) {
        Swal.fire({ icon: 'error', title: 'Invalid Email', text: 'Please enter a valid email address.', background: '#1f2937', color: '#fff' });
        return;
    }
    const formData = new FormData();
    formData.append('email', email);
    formData.append('csrf_token', csrfToken);

    fetch("update_admin_email.php", {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(result => {
            Swal.fire({ icon: 'success', title: 'Calculated', text: result, background: '#1f2937', color: '#fff' })
            .then(() => loadPage("admin_settings.php"));
        });
}

// ===== Update Password =====
function updatePassword() {
    const curr = document.getElementById("currentPass").value;
    const pass = document.getElementById("newPass").value;
    
    // Strict pattern: Min 8, 1 Upper, 1 Lower, 1 Number
    const strongPassRegex = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/;

    if (!curr || !pass) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Please fill in all fields.', background: '#1f2937', color: '#fff' });
        return;
    }
    
    if (!strongPassRegex.test(pass)) {
        Swal.fire({ 
            icon: 'error', 
            title: 'Weak Password', 
            text: 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, and a number.',
            background: '#1f2937', color: '#fff' 
        });
        return;
    }
    const formData = new FormData();
    formData.append('current', curr);
    formData.append('newpass', pass);
    formData.append('csrf_token', csrfToken);

    fetch("update_admin_password.php", {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(result => {
            const isSuccess = result.toLowerCase().includes('success');
            Swal.fire({ 
                icon: isSuccess ? 'success' : 'error', 
                title: 'Status', 
                text: result, 
                background: '#1f2937', 
                color: '#fff' 
            }).then(() => {
                if(isSuccess) {
                    document.getElementById("currentPass").value = '';
                    document.getElementById("newPass").value = '';
                }
            });
        });
}

// ===== Update Registration Setting =====
function updateRegistrationSetting() {
    const active = document.getElementById("regToggle").checked ? 1 : 0;
    const formData = new FormData();
    formData.append('active', active);
    formData.append('csrf_token', csrfToken);

    fetch("update_registration_setting.php", {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(result => Swal.fire({ icon: 'success', title: 'Saved', text: result, background: '#1f2937', color: '#fff' }));
}

// ===== Update Maintenance Setting =====
function updateMaintenanceSetting() {
    const active = document.getElementById("maintToggle").checked ? 1 : 0;
    const time = document.getElementById("maintTime").value;

    const formData = new FormData();
    formData.append('active', active);
    formData.append('time', time);
    formData.append('csrf_token', csrfToken);

    fetch("update_system_settings.php", {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(result => Swal.fire({ icon: 'success', title: 'Saved', text: result, background: '#1f2937', color: '#fff' }));
}

// ===== Toggle Date Input Visibility =====
const maintToggle = document.getElementById('maintToggle');
const timeContainer = document.getElementById('maintenanceTimeContainer');

if (maintToggle && timeContainer) {
    maintToggle.addEventListener('change', function() {
        if (this.checked) {
            timeContainer.classList.remove('hidden');
        } else {
            timeContainer.classList.add('hidden');
        }
    });
}
</script>

<?php if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_end.php"; ?>
