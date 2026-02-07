<?php
// config.php - Database connection settings
date_default_timezone_set('Asia/Kolkata'); // Set to IST

// Secure Session Settings
if (session_status() === PHP_SESSION_NONE) {
    // Set secure cookie parameters
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie
        'path' => '/',
        'domain' => '', // Current domain
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // True if HTTPS
        'httponly' => true, // JavaScript cannot access
        'samesite' => 'Lax' // Allow navigation from external sites (and safer refresh behavior)
    ]);
    session_start();
}

// Include CSRF Helper
require_once __DIR__ . '/../src/classes/Csrf.php';
// Define database parameters
define('DB_SERVER', 'sql113.infinityfree.comt'); // Your database server, usually 'localhost'
define('DB_USERNAME', 'if0_41016906');   // Your database username
define('DB_PASSWORD', 'MdHBI1C4sNrBu');       // Your database password
define('DB_NAME', 'if0_41016906_primeinvoice'); // The database name we created earlier

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Log the error for debugging, but don't expose sensitive info to the user
    error_log("Failed to connect to MySQL: " . $conn->connect_error);
    // Return a generic error to the client
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
    exit();
}

// Set character set to UTF-8 for proper handling of various characters
$conn->set_charset("utf8mb4");

// Start session for managing user login state - ALREADY STARTED ABOVE if needed, but best to ensure headers sent after check
// Note: session_start() is at top.

// ============================================
// MAINTENANCE MODE CHECK
// ============================================
$current_script = basename($_SERVER['PHP_SELF']);
// Pages allowed during maintenance
// index.php (Login), maintenance.php (Target), logout.php (Exit)
// Also allow admin update scripts so they aren't blocked when saving settings
$allowed_pages = [
    'index.php', 
    'maintenance.php', 
    'logout.php', 
    'update_system_settings.php', 
    'update_registration_setting.php',
    'update_admin_email.php',
    'update_admin_password.php',
    'debug_session.php',
    'check_maintenance_status.php'
];

// Skip check if Admin is logged in or if page is allowed
if (!isset($_SESSION['admin_loggedin']) && !in_array($current_script, $allowed_pages)) {
    
    // Check DB for maintenance setting
    // Use try-catch to avoid crashing if table missing (though it handles silently mostly)
    $is_maintenance = false;
    try {
        $m_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'";
        $m_result = $conn->query($m_sql);
        if ($m_result && $m_result->num_rows > 0) {
            $is_maintenance = (bool)$m_result->fetch_assoc()['setting_value'];
        }
    } catch (Exception $e) { /* Ignore */ }

    if ($is_maintenance) {
        // Determine path relative to current script
        // Fallback absolute path if unsure
        $redirect_path = '/invoice_generator/src/views/maintenance.php';
        
        // Try relative paths based on common locations
        if (file_exists('src/views/maintenance.php')) {
            $redirect_path = 'src/views/maintenance.php';
        } elseif (file_exists('../views/maintenance.php')) {
            $redirect_path = '../views/maintenance.php';
        } elseif (file_exists('maintenance.php')) {
             $redirect_path = 'maintenance.php';
        }

        header("Location: " . $redirect_path);
        exit();
    }
}

function log_activity($user_id, $action, $details = null, $item_type = null, $item_id = null) {
    global $conn;
    // Get real IP address
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Convert IPv6 localhost to IPv4 for readability
    if ($ip == '::1') {
        $ip = '127.0.0.1';
    }
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, item_type, item_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $user_id, $action, $details, $ip, $item_type, $item_id);
    $stmt->execute();
}
