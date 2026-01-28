<?php
require_once "../../config/config.php";

if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    die("Unauthorized");
}

$user_id = intval($_GET['id']);
$new_pass = $_GET['pass'];

if (!$new_pass || strlen($new_pass) < 4) {
    die("Invalid password");
}

$hashed = password_hash($new_pass, PASSWORD_BCRYPT);

$sql = "UPDATE users SET password_hash=? WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $hashed, $user_id);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}

$stmt->close();
$conn->close();
?>
