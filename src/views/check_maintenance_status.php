<?php
// check_maintenance_status.php
// Lightweight check for polling
require_once '../../config/config.php';

header('Content-Type: application/json');

$maintenance_mode = false;
$sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    if ($result->fetch_assoc()['setting_value'] == '1') {
        $maintenance_mode = true;
    }
}

echo json_encode(['maintenance' => $maintenance_mode]);
exit();
?>
