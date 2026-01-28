<?php
require_once "../../config/config.php";

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    die("Unauthorized");
}

Csrf::verifyOrTerminate();

$active = intval($_POST['active'] ?? 0); // 1 or 0

$sql = "UPDATE platform_settings SET allow_registration=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $active);

if ($stmt->execute()) {
    echo "Registration setting updated.";
} else {
    echo "Error updating setting.";
}

$stmt->close();
$conn->close();
?>
