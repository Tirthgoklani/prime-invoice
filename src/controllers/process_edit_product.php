<?php
require_once '../../config/config.php';

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = isset($_POST['product_id']) && is_numeric($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $user_id = $_SESSION['user_id'];
    $product_name = sanitize_input($_POST['product_name']);
    $barcode = sanitize_input($_POST['barcode']);
    $price = floatval(sanitize_input($_POST['price']));
    $description = sanitize_input($_POST['description']);

    if(!$product_id || empty($product_name) || empty($barcode) || $price < 0) {
        header("Location: ../src/views/edit_product.php?id=" . $product_id . "&error=invalid_data");
        exit();
    }

    // check if product belongs to user
    $sql_check = "SELECT id FROM products WHERE id = ? AND user_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    if($stmt_check === false) {
        die("Error preparing check statement: " . $conn->error);
    }
    $stmt_check->bind_param("ii", $product_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if($result_check->num_rows == 0) {
        $stmt_check->close();
        $conn->close();
        header("Location: ../src/views/manage_products.php?error=unauthorized");
        exit();
    }
    $stmt_check->close();

    $sql_update = "UPDATE products SET product_name = ?, barcode = ?, price = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    if($stmt_update === false) {
        die("Error preparing update statement: " . $conn->error);
    }
    $stmt_update->bind_param("ssdssi", $product_name, $barcode, $price, $description, $product_id, $user_id);

    if($stmt_update->execute()) {
        log_activity($user_id, 'UPDATE_PRODUCT', "Updated product #$product_id ($product_name)", 'product', $product_id);
        $stmt_update->close();
        $conn->close();
        header("Location: ../src/views/manage_products.php?status=updated");
        exit();
    } else {
        $stmt_update->close();
        $conn->close();
        header("Location: ../src/views/edit_product.php?id=" . $product_id . "&error=update_failed");
        exit();
    }
} else {
    header("Location: ../src/views/manage_products.php");
    exit();
}
?>