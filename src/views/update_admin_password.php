<?php
require_once "../../config/config.php";

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    die("Unauthorized");
}

Csrf::verifyOrTerminate();

$admin_id = $_SESSION['admin_id'];

$current = $_POST['current'] ?? '';
$newpass = $_POST['newpass'] ?? '';

if (!$current || !$newpass) {
    die("Missing fields");
}

if (strlen($newpass) < 4) {
    die("Password too short");
}

// Fetch current password
$sql = "SELECT admin_password_hash FROM admin WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || !password_verify($current, $result['admin_password_hash'])) {
    die("Current password incorrect.");
}

$stmt->close();

// Update new password
$new_hash = password_hash($newpass, PASSWORD_BCRYPT);

$sql2 = "UPDATE admin SET admin_password_hash=? WHERE id=?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("si", $new_hash, $admin_id);

if ($stmt2->execute()) {
    echo "Password updated successfully.";
} else {
    echo "Error updating password.";
}

$stmt2->close();
$conn->close();
?>
