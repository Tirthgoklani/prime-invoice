<?php
// add_product.php - Add Product Page
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = htmlspecialchars(trim($_POST['product_name']));
    $barcode = htmlspecialchars(trim($_POST['barcode']));
    $price = floatval($_POST['price']);
    $description = htmlspecialchars(trim($_POST['description']));
    
    // Check if barcode already exists for this user
    $sql_check = "SELECT id FROM products WHERE user_id = ? AND barcode = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("is", $user_id, $barcode);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $message = 'A product with this barcode already exists!';
        $message_type = 'error';
    } else {
        // Insert new product
        $sql_insert = "INSERT INTO products (user_id, product_name, barcode, price, description) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("issds", $user_id, $product_name, $barcode, $price, $description);
        
        if ($stmt_insert->execute()) {
            $new_product_id = $stmt_insert->insert_id;
            log_activity($user_id, 'CREATE_PRODUCT', "Created product: $product_name", 'product', $new_product_id);
            $message = 'Product added successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error adding product. Please try again.';
            $message_type = 'error';
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>


<div class="max-w-4xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl">
    <h1 class="text-4xl font-extrabold text-blue-400 mb-8 text-center">Add New Product</h1>

    <?php if (!empty($message)): ?>
        <div class="<?php echo $message_type === 'success' ? 'bg-green-800 border-green-500 text-green-200' : 'bg-red-800 border-red-500 text-red-200'; ?> border px-4 py-3 rounded mb-6">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="add_product.php" method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="product_name" class="block text-sm font-medium text-gray-300 mb-1">Product Name *</label>
                <input type="text" id="product_name" name="product_name" 
                       class="mt-1 block w-full p-3 border border-gray-700 bg-gray-800 text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                       placeholder="Enter product name" required>
            </div>
            <div>
                <label for="price" class="block text-sm font-medium text-gray-300 mb-1">Price *</label>
                <input type="number" id="price" name="price" min="0.01" step="0.01" 
                       class="mt-1 block w-full p-3 border border-gray-700 bg-gray-800 text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                       placeholder="0.00" required>
            </div>
        </div>

        <div>
            <label for="barcode" class="block text-sm font-medium text-gray-300 mb-1">Barcode *</label>
            <div class="flex">
                <input type="text" id="barcode" name="barcode" 
                       class="flex-grow p-3 border border-gray-700 bg-gray-800 text-white rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                       placeholder="Enter barcode or scan" required autofocus>
            </div>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-300 mb-1">Description</label>
            <textarea id="description" name="description" rows="4" 
                      class="mt-1 block w-full p-3 border border-gray-700 bg-gray-800 text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                      placeholder="Product description (optional)"></textarea>
        </div>

        <div class="text-center">
            <button type="submit" 
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                Add Product
            </button>
            <a href="./manage_products.php" 
               class="ml-4 inline-flex items-center px-6 py-3 border border-gray-700 text-base font-medium rounded-md shadow-sm text-gray-300 bg-gray-800 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-300">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php
if (!$is_ajax) {
    include './layouts/common_layout_end.php';
}
?>
