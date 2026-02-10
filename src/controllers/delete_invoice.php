<?php
require_once '../../config/config.php';

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../views/manage_invoices.php?error=no_id");
    exit();
}

$invoice_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$conn->begin_transaction();

try {
    // delete invoice items first
    $sql_delete_items = "DELETE FROM invoice_items WHERE invoice_id = ?";
    $stmt_delete_items = $conn->prepare($sql_delete_items);
    if($stmt_delete_items === false) {
        throw new Exception("Error preparing item deletion statement: " . $conn->error);
    }
    $stmt_delete_items->bind_param("i", $invoice_id);
    $stmt_delete_items->execute();
    $stmt_delete_items->close();

    // soft delete invoice
    $sql_delete_invoice = "UPDATE invoices SET deleted_at = NOW() WHERE id = ? AND user_id = ?";
    $stmt_delete_invoice = $conn->prepare($sql_delete_invoice);
    if($stmt_delete_invoice === false) {
        throw new Exception("Error preparing invoice deletion statement: " . $conn->error);
    }
    $stmt_delete_invoice->bind_param("ii", $invoice_id, $user_id);
    $stmt_delete_invoice->execute();

    if($stmt_delete_invoice->affected_rows > 0) {
        $conn->commit();
        log_activity($user_id, 'DELETE_INVOICE', "Deleted invoice #$invoice_id", 'invoice', $invoice_id);
        $stmt_delete_invoice->close();
        $conn->close();
        header("Location: ../views/manage_invoices.php?status=deleted&invoice_id=" . $invoice_id);
        exit();
    } else {
        $conn->rollback();
        $stmt_delete_invoice->close();
        $conn->close();
        header("Location: ../views/manage_invoices.php?error=notfound_or_unauthorized");
        exit();
    }
} catch(Exception $e) {
    $conn->rollback();
    $conn->close();
    die("Error deleting invoice: " . $e->getMessage());
}
?>