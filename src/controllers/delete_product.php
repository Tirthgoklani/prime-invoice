<?php
// delete_product.php
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../views/manage_products.php?error=no_id");
    exit();
}

$product_id = intval($_GET['id']);

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // Soft delete the product
    $sql_delete_product = "UPDATE products SET deleted_at = NOW() WHERE id = ? AND user_id = ?";
    $stmt_delete_product = $conn->prepare($sql_delete_product);
    if ($stmt_delete_product === false) {
        throw new Exception("Error preparing product deletion statement: " . $conn->error);
    }
    $stmt_delete_product->bind_param("ii", $product_id, $user_id);
    $stmt_delete_product->execute();

    if ($stmt_delete_product->affected_rows > 0) {
        $conn->commit(); // Commit transaction if successful
        log_activity($user_id, 'DELETE_PRODUCT', "Deleted product #$product_id", 'product', $product_id);
        $stmt_delete_product->close();
        $conn->close();
        header("Location: ../views/manage_products.php?status=deleted&product_id=" . $product_id);
        exit();
    } else {
        // Product not found or did not belong to the user
        $conn->rollback(); // Rollback if no rows were affected
        $stmt_delete_product->close();
        $conn->close();
        header("Location: ../views/manage_products.php?error=notfound_or_unauthorized");
        exit();
    }
} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    $conn->close();
    die("Error deleting product: " . $e->getMessage());
}
?>