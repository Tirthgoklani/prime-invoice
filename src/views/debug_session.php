<?php
require_once '../../config/config.php';
// Temporarily bypass maintenance redirect for this debug file
// How? config.php is already included. If maintenance was on, we'd be redirected!
// UNLESS we are admin.

echo "<pre>";
echo "<h3>Session Debug</h3>";
print_r($_SESSION);
echo "\n<h3>Cookies</h3>";
print_r($_COOKIE);
print_r($_COOKIE);
echo "\n<h3>Maintenance Mode</h3>";
$res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
$status = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['setting_value'] : 'Error/Missing';
echo "DB Status: " . ($status == '1' ? 'ACTIVE (1)' : 'INACTIVE (0)');

echo "\n<h3>Access Check</h3>";
if (isset($_SESSION['admin_loggedin'])) {
    echo "You are ADMIN. Maintenance block is SKIPPED.";
} else {
    echo "You are CLIENT. Maintenance block should be ENFORCED.";
}

echo "</pre>";

// Logic to simulate client
if (isset($_GET['simulate_client'])) {
    unset($_SESSION['admin_loggedin']);
    echo "<script>window.location.href='debug_session.php';</script>"; // Reload to show new status
}

echo '<br><a href="admin_settings.php" style="background: #2563eb; color: white; padding: 10px; text-decoration: none; border-radius: 5px;">Back to Admin Settings</a>';
echo ' &nbsp; ';
echo '<a href="debug_session.php?simulate_client=1" style="background: #dc2626; color: white; padding: 10px; text-decoration: none; border-radius: 5px;">⚠️ Test as Client (Logout Admin)</a>';
?>
