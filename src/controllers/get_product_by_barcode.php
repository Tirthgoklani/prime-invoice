<?php
require_once '../../config/config.php';

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if(!isset($_GET['barcode']) || empty(trim($_GET['barcode']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Barcode is required']);
    exit();
}

$barcode = trim($_GET['barcode']);
$user_id = $_SESSION['user_id'];
$sql = "SELECT product_name, price FROM products WHERE user_id = ? AND barcode = ?";
$stmt = $conn->prepare($sql);

if($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("is", $user_id, $barcode);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'product' => [
            'product_name' => $product['product_name'],
            'price' => floatval($product['price'])
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Product not found for barcode: ' . htmlspecialchars($barcode)]);
}

$stmt->close();
$conn->close();
?>