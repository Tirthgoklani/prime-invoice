<?php
// Restore deleted items
require_once "../../config/config.php";

session_start();

if(!isset($_SESSION['admin_loggedin'])) { 
    die("Access Denied"); 
}

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if(!$id || !in_array($type, ['invoice', 'product'])) {
    die("Invalid request");
}

$conn->begin_transaction();

try {
    if($type === 'invoice') {
        $stmt = $conn->prepare("UPDATE invoices SET deleted_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        log_activity($_SESSION['admin_id'], 'RESTORE_INVOICE', "Restored invoice #$id", 'invoice', $id);
    } elseif($type === 'product') {
        $stmt = $conn->prepare("UPDATE products SET deleted_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        log_activity($_SESSION['admin_id'], 'RESTORE_PRODUCT', "Restored product #$id", 'product', $id);
    }
    
    $conn->commit();
    header("Location: ../../src/views/admin_logs.php?status=restored");
} catch(Exception $e) {
    $conn->rollback();
    die("Error restoring: " . $e->getMessage());
}
?>
