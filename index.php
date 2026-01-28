<?php
// index.php
require_once 'config/config.php';

// Function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$login_error_message = '';
$register_status_message = '';
$register_error_message = '';

// Determine form type
$form_type = $_POST['form_type'] ?? 'login';

// Check Registration Setting
$allow_registration = 1; // Default
$set_res = $conn->query("SELECT allow_registration FROM platform_settings LIMIT 1");
if ($set_res && $set_res->num_rows > 0) {
    $allow_registration = (int)$set_res->fetch_assoc()['allow_registration'];
}


// ===============================================
// 1️⃣ UNIFIED LOGIN HANDLER (ADMIN + USER)
// ===============================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && $form_type === 'login') {

    if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
        $login_error_message = "Invalid request token. Please refresh the page.";
    } else {


    $input_identifier = sanitize_input($_POST['username']);
    $input_password   = $_POST['password'];

    // ---- A. CHECK ADMIN LOGIN ----
    $admin_sql = "SELECT id, admin_email, admin_password_hash FROM admin WHERE admin_email = ?";
    $stmt = $conn->prepare($admin_sql);
    $stmt->bind_param("s", $input_identifier);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($input_password, $admin['admin_password_hash'])) {

        $_SESSION['admin_loggedin'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['admin_email'];

        log_activity($admin['id'], 'LOGIN', "Admin logged in");

        header("Location: ./src/views/admin_dashboard.php");
        exit();
    }

    // ---- B. CHECK NORMAL USER LOGIN ----
    $user_sql = "SELECT id, username, password_hash, company_name, company_address, company_email 
                 FROM users WHERE username = ?";
    $stmt = $conn->prepare($user_sql);
    $stmt->bind_param("s", $input_identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($input_password, $user['password_hash'])) {

        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['company_address'] = $user['company_address'];
        $_SESSION['company_email'] = $user['company_email'];

        log_activity($user['id'], 'LOGIN', "User logged in");

        header("Location: ./src/views/dashboard.php");
        exit();
    }

    // If neither matched → show error
    $login_error_message = "Invalid username/email or password.";
    }
}



// ===============================================
// 2️⃣ COMPANY REGISTRATION HANDLER
// ===============================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && $form_type === 'register') {

    if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
        $register_error_message = "Invalid request token. Please refresh the page.";
    } else {


    $input_username    = sanitize_input($_POST['reg_username']);
    $input_password    = $_POST['reg_password'];
    $company_name      = sanitize_input($_POST['company_name']);
    $company_address   = sanitize_input($_POST['company_address']);
    $company_email     = sanitize_input($_POST['company_email']);

    $hashed_password = password_hash($input_password, PASSWORD_DEFAULT);

    // Check username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $register_error_message = 'Username already taken.';
    } else {

        // Check email
        $stmt2 = $conn->prepare("SELECT id FROM users WHERE company_email=?");
        $stmt2->bind_param("s", $company_email);
        $stmt2->execute();
        $stmt2->store_result();

        if ($stmt2->num_rows > 0) {
            $register_error_message = 'Email already registered.';
        } else {

            // Insert new user
            $stmt3 = $conn->prepare("INSERT INTO users 
                (username, password_hash, company_name, company_address, company_email)
                VALUES (?, ?, ?, ?, ?)");
            $stmt3->bind_param("sssss", $input_username, $hashed_password, 
                               $company_name, $company_address, $company_email);

            if ($stmt3->execute()) {
                $new_user_id = $stmt3->insert_id;
                log_activity($new_user_id, 'REGISTER', "New company registered: $company_name");

                $register_status_message = 'Registration successful! Please login.';
                $form_type = 'login';
            } else {
                $register_error_message = 'Registration failed.';
            }

            $stmt3->close();
        }
        $stmt2->close();
    }

    $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prime Invoice - Login/Register</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">

<style>
body { font-family: 'Inter', sans-serif; }
.tab { background-color:#374151; color:#e5e7eb; }
.tab.active { background-color:#3B82F6; color:white; }
.tab-content { display:none; }
.tab-content.active { display:block; }
</style>
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-gray-800 shadow-2xl rounded-2xl p-10 w-full max-w-2xl">
    
    <img src="./logo.png" class="mx-auto mb-6 w-48">

    <!-- Tabs -->
    <div class="flex border-b border-gray-700 mb-6">
        <button id="login-tab" class="tab flex-1 py-3 px-6 text-center font-semibold rounded-tl-lg 
            <?php echo $form_type==='login'?'active':''; ?>" onclick="showTab('login')">
            Login
        </button>
        <button id="register-tab" class="tab flex-1 py-3 px-6 text-center font-semibold rounded-tr-lg 
            <?php echo $form_type==='register'?'active':''; ?>" onclick="showTab('register')">
            Register
        </button>
    </div>

    <!-- Login -->
    <div id="login-content" class="tab-content <?php echo $form_type==='login'?'active':''; ?>">

        <h2 class="text-2xl font-semibold text-blue-400 mb-6 text-center">Welcome Back</h2>

        <form action="index.php" method="POST" class="space-y-5">
            <input type="hidden" name="form_type" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">


            <label class="block">Username / Admin Email</label>
            <input type="text" name="username" class="w-full p-3 bg-gray-900 border border-gray-600 rounded" required>

            <label class="block">Password</label>
            <input type="password" name="password" class="w-full p-3 bg-gray-900 border border-gray-600 rounded" required>

            <button type="submit" class="w-full py-2 bg-blue-600 rounded hover:bg-blue-500">
                Login
            </button>

            <?php if (!empty($login_error_message)): ?>
                <p class="text-red-400 text-center mt-4"><?= $login_error_message ?></p>
            <?php endif; ?>
        </form>
    </div>


    <!-- Registration -->
    <div id="register-content" class="tab-content <?php echo $form_type==='register'?'active':''; ?>">

        <h2 class="text-2xl font-semibold text-blue-400 mb-6 text-center">Create Account</h2>

        <form action="index.php" method="POST" class="space-y-5">
            <input type="hidden" name="form_type" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">

            <?php if ($allow_registration): ?>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block">Username</label>
                    <input type="text" name="reg_username" class="w-full p-3 bg-gray-900 border border-gray-600 rounded" required minlength="4">
                </div>
                <div>
                    <label class="block">Password</label>
                    <input type="password" name="reg_password" id="reg_password" class="w-full p-3 bg-gray-900 border border-gray-600 rounded" required 
                           minlength="8" 
                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                           title="Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters">
                    
                    <!-- Password Guidelines -->
                    <ul id="password-requirements" class="mt-2 space-y-1 text-sm text-gray-400 hidden">
                        <li id="req-len" class="flex items-center gap-2 transition-colors"><span class="icon">○</span> At least 8 characters</li>
                        <li id="req-upper" class="flex items-center gap-2 transition-colors"><span class="icon">○</span> One uppercase letter</li>
                        <li id="req-lower" class="flex items-center gap-2 transition-colors"><span class="icon">○</span> One lowercase letter</li>
                        <li id="req-num" class="flex items-center gap-2 transition-colors"><span class="icon">○</span> One number</li>
                    </ul>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block">Company Name</label>
                    <input type="text" name="company_name" class="w-full p-3 bg-gray-900 border border-gray-600 rounded" required>
                </div>
                <div>
                    <label class="block">Company Email</label>
                    <input type="email" name="company_email" class="w-full p-3 bg-gray-900 border border-gray-600 rounded" required
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                </div>
            </div>

            <label class="block">Company Address</label>
            <textarea name="company_address" rows="2" class="w-full p-3 bg-gray-900 border border-gray-600 rounded" required></textarea>

            <button type="submit" class="w-full py-2 bg-blue-600 rounded hover:bg-blue-500">Register</button>
            <?php else: ?>
                <div class="text-center py-6">
                    <svg class="w-16 h-16 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <h3 class="text-xl font-bold text-gray-200 mb-2">Registration Closed</h3>
                    <p class="text-gray-400 mb-4">New registrations are currently disabled by the administrator.</p>
                    <a href="mailto:admin@example.com" class="inline-block px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded text-blue-300 transition-colors">
                        Contact Admin
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($register_error_message): ?>
                <p class="text-red-400 text-center mt-4"><?= $register_error_message ?></p>
            <?php elseif ($register_status_message): ?>
                <p class="text-green-400 text-center mt-4"><?= $register_status_message ?></p>
            <?php endif; ?>
        </form>
    </div>

</div>

<script>
function showTab(tab) {
    document.querySelectorAll(".tab-content").forEach(e => e.classList.remove("active"));
    document.querySelectorAll(".tab").forEach(e => e.classList.remove("active"));
    document.getElementById(tab + "-content").classList.add("active");
    document.getElementById(tab + "-tab").classList.add("active");
}
showTab("<?= $form_type ?>");

// Live Password Validation
const passInput = document.getElementById('reg_password');
const reqList = document.getElementById('password-requirements');
const reqs = {
    len: /.{8,}/,
    upper: /[A-Z]/,
    lower: /[a-z]/,
    num: /\d/
};

if (passInput) {
    passInput.addEventListener('focus', () => reqList.classList.remove('hidden'));
    passInput.addEventListener('blur', () => {
        if (!passInput.value) reqList.classList.add('hidden');
    });

    passInput.addEventListener('input', function() {
        const val = this.value;
        
        for (const [key, regex] of Object.entries(reqs)) {
            const el = document.getElementById(`req-${key}`);
            const icon = el.querySelector('.icon');
            
            if (regex.test(val)) {
                el.classList.add('text-green-400', 'font-medium');
                el.classList.remove('text-gray-400');
                icon.textContent = '✓';
            } else {
                el.classList.remove('text-green-400', 'font-medium');
                el.classList.add('text-gray-400');
                icon.textContent = '○';
            }
        }
    });
}
</script>

</body>
</html>
