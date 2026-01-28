<?php
// settings.php - Unified Account & Settings Hub
require_once '../../config/config.php';

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Helper to fetch current user data and settings
function fetchUserData($conn, $user_id) {
    $data = [];
    
    // Fetch User Profile
    $sql_user = "SELECT username, company_name, company_address, company_email, password_hash FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql_user);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data['user'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch Settings
    $sql_settings = "SELECT * FROM user_settings WHERE user_id = ?";
    $stmt = $conn->prepare($sql_settings);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data['settings'] = $result->fetch_assoc();
    } else {
        // Defaults
        $data['settings'] = [
            'default_tax_rate' => 0.00,
            'default_discount_amount' => 0.00,
            'invoice_notes' => '',
            'company_logo' => null,
            'theme_color' => '#3B82F6',
            'currency_symbol' => '₹'
        ];
    }
    $stmt->close();

    return $data;
}

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CSRF Check
    if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please refresh.';
        $message_type = 'error';
    } else {
        $form_type = $_POST['form_type'] ?? '';

        // 1. Update Business Profile
        if ($form_type === 'business_profile') {
            $company_name = htmlspecialchars(trim($_POST['company_name']));
            $company_address = htmlspecialchars(trim($_POST['company_address']));
            $company_email = htmlspecialchars(trim($_POST['company_email']));

            // Validate Email uniqueness (ignoring self)
            $sql_check = "SELECT id FROM users WHERE company_email = ? AND id != ?";
            $stmt = $conn->prepare($sql_check);
            $stmt->bind_param("si", $company_email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $message = 'Email already in use by another account.';
                $message_type = 'error';
            } else {
                $sql_update = "UPDATE users SET company_name=?, company_address=?, company_email=? WHERE id=?";
                $stmt_upd = $conn->prepare($sql_update);
                $stmt_upd->bind_param("sssi", $company_name, $company_address, $company_email, $user_id);
                if ($stmt_upd->execute()) {
                    // Update session
                    $_SESSION['company_name'] = $company_name;
                    $_SESSION['company_address'] = $company_address;
                    $_SESSION['company_email'] = $company_email;
                    $message = 'Business profile updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Database error updating profile.';
                    $message_type = 'error';
                }
                $stmt_upd->close();
            }
            $stmt->close();
        }

        // 2. Update Invoice Branding/Settings
        elseif ($form_type === 'invoice_settings') {
            $tax_rate = floatval($_POST['default_tax_rate']);
            $discount = floatval($_POST['default_discount_amount']);
            $notes = htmlspecialchars(trim($_POST['invoice_notes']));
            $theme_color = htmlspecialchars(trim($_POST['theme_color']));
            $currency = htmlspecialchars(trim($_POST['currency_symbol']));
            
            // Handle Logo Upload
            $logo_path = null;
            // First, get existing logo path to preserve it if not changing
            $curr_data = fetchUserData($conn, $user_id);
            $logo_path = $curr_data['settings']['company_logo'];

            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file_ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_ext, $allowed)) {
                    $new_filename = 'logo_' . $user_id . '_' . time() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $dest_path)) {
                        $logo_path = 'uploads/logos/' . $new_filename; // Relative path for DB
                    } else {
                        $message = 'Failed to upload logo file.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid file type. Only JPG, PNG, GIF allowed.';
                    $message_type = 'error';
                }
            } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
                $logo_path = null;
            }

            // Upsert Settings
            // Check existence first
            $check_exist = $conn->query("SELECT id FROM user_settings WHERE user_id = $user_id");
            if ($check_exist->num_rows > 0) {
                $sql = "UPDATE user_settings SET default_tax_rate=?, default_discount_amount=?, invoice_notes=?, theme_color=?, currency_symbol=?, company_logo=? WHERE user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ddssssi", $tax_rate, $discount, $notes, $theme_color, $currency, $logo_path, $user_id);
            } else {
                $sql = "INSERT INTO user_settings (user_id, default_tax_rate, default_discount_amount, invoice_notes, theme_color, currency_symbol, company_logo) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iddssss", $user_id, $tax_rate, $discount, $notes, $theme_color, $currency, $logo_path);
            }
            
            if ($stmt->execute()) {
                $message = ($message_type !== 'error') ? 'Invoice settings saved!' : $message; // Don't overwrite upload error
                $message_type = ($message_type !== 'error') ? 'success' : 'error';
            } else {
                $message = 'Error saving settings: ' . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }

        // 3. Security (Password)
        elseif ($form_type === 'security') {
            $current_pass = $_POST['current_password'];
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];
            
            // Verify current
            $curr_data = fetchUserData($conn, $user_id);
            if (password_verify($current_pass, $curr_data['user']['password_hash'])) {
                if ($new_pass === $confirm_pass && strlen($new_pass) >= 6) {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
                    $stmt->bind_param("si", $new_hash, $user_id);
                    if ($stmt->execute()) {
                        $message = 'Password updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating password.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'New passwords do not match or are too short.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Current password is incorrect.';
                $message_type = 'error';
            }
        }
    }
}

// Fetch Final Data for View
$data = fetchUserData($conn, $user_id);
$user = $data['user'];
$conf = $data['settings'];

// AJAX Request Handling
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$is_ajax) include './layouts/common_layout_start.php';
?>

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-white">Account & Settings</h1>
        <p class="text-gray-400 mt-2">Manage your business profile, branding, and security preferences.</p>
    </div>

    <!-- Alert Message -->
    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg border <?php echo $message_type === 'success' ? 'bg-green-900/50 border-green-500 text-green-200' : 'bg-red-900/50 border-red-500 text-red-200'; ?> flex items-center shadow-lg animate-fade-in-down">
        <span class="text-2xl mr-3"><?php echo $message_type === 'success' ? '✅' : '⚠️'; ?></span>
        <div><?php echo $message; ?></div>
    </div>
    <?php endif; ?>

    <!-- Tabs Container -->
    <div class="bg-gray-800 rounded-xl shadow-2xl border border-gray-700 overflow-hidden min-h-[600px] flex flex-col md:flex-row">
        
        <!-- Sidebar Tabs -->
        <div class="w-full md:w-64 bg-gray-900/50 border-r border-gray-700 flex flex-col">
            <nav class="flex-1 p-4 space-y-2">
                <button onclick="openTab('profile')" id="btn-profile" class="tab-btn w-full text-left px-4 py-3 rounded-lg flex items-center space-x-3 transition-all duration-200 text-blue-400 bg-blue-900/20 shadow-inner">
                    <span class="text-xl">🏢</span>
                    <span class="font-medium">Business Profile</span>
                </button>
                <button onclick="openTab('branding')" id="btn-branding" class="tab-btn w-full text-left px-4 py-3 rounded-lg flex items-center space-x-3 text-gray-400 hover:bg-gray-800 transition-all duration-200 hover:text-gray-200">
                    <span class="text-xl">🎨</span>
                    <span class="font-medium">Branding & Invoice</span>
                </button>
                <button onclick="openTab('security')" id="btn-security" class="tab-btn w-full text-left px-4 py-3 rounded-lg flex items-center space-x-3 text-gray-400 hover:bg-gray-800 transition-all duration-200 hover:text-gray-200">
                    <span class="text-xl">🔒</span>
                    <span class="font-medium">Security</span>
                </button>
            </nav>
        </div>

        <!-- Content Area -->
        <div class="flex-1 p-8 bg-gray-800 relative">
            
            <!-- 1. BUSINESS PROFILE TAB -->
            <div id="tab-profile" class="tab-content animate-fade-in">
                <h2 class="text-2xl font-bold text-white mb-6 pb-2 border-b border-gray-700">Business Information</h2>
                <form action="settings.php" method="POST" class="space-y-6 max-w-2xl">
                    <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
                    <input type="hidden" name="form_type" value="business_profile">

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Company Name</label>
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars($user['company_name']); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Business Email</label>
                            <input type="email" name="company_email" value="<?php echo htmlspecialchars($user['company_email']); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Business Address</label>
                            <textarea name="company_address" rows="3" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required><?php echo htmlspecialchars($user['company_address']); ?></textarea>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg shadow-lg hover:shadow-blue-500/30 transition-all transform hover:-translate-y-0.5">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- 2. BRANDING TAB -->
            <div id="tab-branding" class="tab-content hidden animate-fade-in">
                <h2 class="text-2xl font-bold text-white mb-6 pb-2 border-b border-gray-700">Invoice Branding</h2>
                <form action="settings.php" method="POST" enctype="multipart/form-data" class="space-y-8 max-w-3xl">
                    <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
                    <input type="hidden" name="form_type" value="invoice_settings">

                    <!-- Logo Upload Section -->
                    <div class="grid md:grid-cols-3 gap-8">
                        <div class="col-span-1">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Company Logo</label>
                            <p class="text-xs text-gray-500 mb-3">Recommended size: ~80x80px or 36x36px. (Max height on invoice: 80px)</p>
                            <div id="logo-preview-container" class="relative group w-24 h-24 bg-gray-900 border-2 border-dashed border-gray-600 rounded-xl flex items-center justify-center overflow-hidden hover:border-blue-500 transition-colors">
                                <?php if ($conf['company_logo']): ?>
                                    <img id="logo-img-preview" src="../../<?php echo htmlspecialchars($conf['company_logo']); ?>" class="object-contain w-full h-full">
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-xs text-white">
                                        Change Logo
                                    </div>
                                <?php else: ?>
                                    <div id="logo-placeholder" class="text-center text-gray-500 p-2">
                                        <span class="text-2xl block mb-1">📤</span>
                                        <span class="text-xs">Upload Logo</span>
                                    </div>
                                    <img id="logo-img-preview" src="" class="object-contain w-full h-full hidden">
                                <?php endif; ?>
                                <input type="file" id="company_logo_input" name="company_logo" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
                            </div>
                            <?php if ($conf['company_logo']): ?>
                            <div class="mt-2 text-sm text-red-400 hover:text-red-300 cursor-pointer flex items-center">
                                <input type="checkbox" name="remove_logo" value="1" class="mr-2"> Remove Logo
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Brand Colors -->
                        <div class="col-span-2 space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Theme Color</label>
                                <div class="flex items-center space-x-4">
                                    <input type="color" name="theme_color" value="<?php echo htmlspecialchars($conf['theme_color'] ?? '#3B82F6'); ?>" class="h-10 w-20 bg-transparent border-0 cursor-pointer rounded">
                                    <span class="text-gray-400 text-sm">Primary color for invoice headers and accents.</span>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Currency Symbol</label>
                                <select name="currency_symbol" class="bg-gray-900 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    <option value="₹" <?php echo ($conf['currency_symbol']=='₹')?'selected':''; ?>>₹ INR (Indian Rupee)</option>
                                    <option value="$" <?php echo ($conf['currency_symbol']=='$')?'selected':''; ?>>$ USD (US Dollar)</option>
                                    <option value="€" <?php echo ($conf['currency_symbol']=='€')?'selected':''; ?>>€ EUR (Euro)</option>
                                    <option value="£" <?php echo ($conf['currency_symbol']=='£')?'selected':''; ?>>£ GBP (British Pound)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-700">

                    <!-- Default Values -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-200 mb-4">Invoice Defaults</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Default Tax Rate (%)</label>
                                <input type="number" step="0.01" name="default_tax_rate" value="<?php echo htmlspecialchars($conf['default_tax_rate']); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Default Discount</label>
                                <input type="number" step="0.01" name="default_discount_amount" value="<?php echo htmlspecialchars($conf['default_discount_amount']); ?>" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-400 mb-1">Default Invoice Notes / Footer</label>
                                <textarea name="invoice_notes" rows="3" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($conf['invoice_notes']); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">These notes will appear at the bottom of every new invoice.</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-500 text-white font-semibold rounded-lg shadow-lg hover:shadow-purple-500/30 transition-all transform hover:-translate-y-0.5">
                            Save Branding
                        </button>
                    </div>
                </form>
            </div>

            <!-- 3. SECURITY TAB -->
            <div id="tab-security" class="tab-content hidden animate-fade-in">
                <h2 class="text-2xl font-bold text-white mb-6 pb-2 border-b border-gray-700">Security Settings</h2>
                <form action="settings.php" method="POST" class="space-y-6 max-w-xl">
                    <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
                    <input type="hidden" name="form_type" value="security">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Current Password</label>
                            <input type="password" name="current_password" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-red-500 focus:border-transparent transition-all" required>
                        </div>
                        <hr class="border-gray-700 my-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">New Password</label>
                            <input type="password" name="new_password" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-red-500 focus:border-transparent transition-all" required minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="Min 8 chars, 1 upper, 1 lower, 1 number">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-red-500 focus:border-transparent transition-all" required minlength="8">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="px-6 py-3 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-lg shadow-lg hover:shadow-red-500/30 transition-all transform hover:-translate-y-0.5">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
function openTab(tabName) {
    // Hide all
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    
    // Reset buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('text-blue-400', 'bg-blue-900/20', 'shadow-inner');
        btn.classList.add('text-gray-400');
    });

    // Show selected
    document.getElementById('tab-' + tabName).classList.remove('hidden');
    
    // Highlight button
    const activeBtn = document.getElementById('btn-' + tabName);
    activeBtn.classList.add('text-blue-400', 'bg-blue-900/20', 'shadow-inner');
    activeBtn.classList.remove('text-gray-400');
}

// Default open
openTab('profile');

// ===== Live Logo Preview =====
const logoInput = document.getElementById('company_logo_input');
const logoImg = document.getElementById('logo-img-preview');
const logoPlaceholder = document.getElementById('logo-placeholder');

if (logoInput && logoImg) {
    logoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                logoImg.src = e.target.result;
                logoImg.classList.remove('hidden');
                if (logoPlaceholder) logoPlaceholder.classList.add('hidden');
            }
            reader.readAsDataURL(file);
        }
    });
}
</script>

<?php if (!$is_ajax) include './layouts/common_layout_end.php'; ?>
