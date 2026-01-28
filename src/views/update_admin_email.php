<?php
require_once "../../config/config.php";

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    die("Unauthorized");
}

Csrf::verifyOrTerminate();

$new_email = trim($_POST['email'] ?? '');

if (!$new_email || strlen($new_email) < 5) {
    die("Invalid email");
}

$admin_id = $_SESSION['admin_id'];

$sql = "UPDATE admin SET admin_email=? WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_email, $admin_id);

if ($stmt->execute()) {
    $_SESSION['admin_email'] = $new_email; // update session live
    echo "Email updated successfully.";
} else {
    echo "Error updating email.";
}

$stmt->close();
$conn->close();
?>
