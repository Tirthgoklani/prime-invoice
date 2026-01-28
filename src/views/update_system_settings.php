<?php
// update_system_settings.php
require_once "../../config/config.php";

// Admin check
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Check
    if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
       echo "Invalid Token";
       exit();
    }

    $active = isset($_POST['active']) ? (int)$_POST['active'] : 0;
    $time = isset($_POST['time']) && !empty($_POST['time']) ? $_POST['time'] : NULL;

    // Update maintenance_mode
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $active, $active);
    $stmt->execute();
    $stmt->close();

    // Update maintenance_end_time
    // If empty, set to NULL
    if ($time) {
        $stmt2 = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_end_time', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt2->bind_param("ss", $time, $time);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $conn->query("UPDATE system_settings SET setting_value = NULL WHERE setting_key = 'maintenance_end_time'");
    }
    
    // Log action
    if (function_exists('log_activity')) {
        log_activity($_SESSION['admin_id'], 'UPDATE_SETTINGS', "Updated Maintenance Mode: " . ($active ? 'ON' : 'OFF'));
    }

    echo "Maintenance settings updated successfully.";
}
?>
