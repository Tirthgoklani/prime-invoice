<?php
// delete_invoice.php
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php"); // Redirect to login if not logged in
    exit();
}

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../views/manage_invoices.php?error=no_id"); // Redirect if no valid ID
    exit();
}

$invoice_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Start a transaction for atomicity (optional but good practice for related deletions)
$conn->begin_transaction();

try {
    // First, delete related items from invoice_items table
    $sql_delete_items = "DELETE FROM invoice_items WHERE invoice_id = ?";
    $stmt_delete_items = $conn->prepare($sql_delete_items);
    if ($stmt_delete_items === false) {
        throw new Exception("Error preparing item deletion statement: " . $conn->error);
    }
    $stmt_delete_items->bind_param("i", $invoice_id);
    $stmt_delete_items->execute();
    $stmt_delete_items->close();

    // Soft delete the invoice (deleted_at = NOW())
    // Ensure the invoice belongs to the logged-in user for security
    $sql_delete_invoice = "UPDATE invoices SET deleted_at = NOW() WHERE id = ? AND user_id = ?";
    $stmt_delete_invoice = $conn->prepare($sql_delete_invoice);
    if ($stmt_delete_invoice === false) {
        throw new Exception("Error preparing invoice deletion statement: " . $conn->error);
    }
    $stmt_delete_invoice->bind_param("ii", $invoice_id, $user_id);
    $stmt_delete_invoice->execute();

    if ($stmt_delete_invoice->affected_rows > 0) {
        $conn->commit(); // Commit transaction if successful
        log_activity($user_id, 'DELETE_INVOICE', "Deleted invoice #$invoice_id", 'invoice', $invoice_id);
        $stmt_delete_invoice->close();
        $conn->close();
        header("Location: ../views/manage_invoices.php?status=deleted&invoice_id=" . $invoice_id);
        exit();
    } else {
        // Invoice not found or did not belong to the user
        $conn->rollback(); // Rollback if no rows were affected
        $stmt_delete_invoice->close();
        $conn->close();
        header("Location: ../views/manage_invoices.php?error=notfound_or_unauthorized");
        exit();
    }
} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    $conn->close();
    die("Error deleting invoice: " . $e->getMessage());
}
?>