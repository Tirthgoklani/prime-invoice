<?php
require_once "../../config/config.php";

session_start();

// stop impersonating
if(isset($_GET['action']) && $_GET['action'] === 'stop') {
    if(isset($_SESSION['impersonator_admin_id'])) {
        // restore admin session
        $_SESSION['admin_loggedin'] = true;
        $_SESSION['admin_id'] = $_SESSION['impersonator_admin_id'];
        
        // cleanup user session
        unset($_SESSION['loggedin']);
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['company_name']);
        unset($_SESSION['company_email']);
        unset($_SESSION['is_impersonating']);
        unset($_SESSION['impersonator_admin_id']);

        header("Location: ../../src/views/admin_companies.php");
        exit();
    }
}

// start impersonation - must be admin
if(!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    die("Access Denied");
}

if(!isset($_GET['user_id'])) {
    die("User ID missing");
}

$target_user_id = (int)$_GET['user_id'];
$stmt = $conn->prepare("SELECT id, username, company_name, company_email FROM users WHERE id = ?");
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$result = $stmt->get_result();

if($row = $result->fetch_assoc()) {
    // save admin id
    $_SESSION['impersonator_admin_id'] = $_SESSION['admin_id'] ?? 1;
    
    // set user session
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['company_name'] = $row['company_name'];
    $_SESSION['company_email'] = $row['company_email'];
    $_SESSION['is_impersonating'] = true;

    header("Location: ../views/dashboard.php");
    exit();
} else {
    die("User not found");
}
?>
