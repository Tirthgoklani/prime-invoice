<?php
// edit_product.php
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../manage_products.php?error=not_found");
    exit();
}

$product_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Fetch product details
$product = null;
$sql_fetch = "SELECT * FROM products WHERE id = ? AND user_id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch === false) {
    die("Error preparing product fetch statement: " . $conn->error);
}
$stmt_fetch->bind_param("ii", $product_id, $user_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows > 0) {
    $product = $result_fetch->fetch_assoc();
} else {
    $stmt_fetch->close();
    $conn->close();
    header("Location: ../manage_products.php?error=not_found");
    exit();
}
$stmt_fetch->close();

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>
<title>Prime Invoice - Smart Easy Modern</title>
<div class="max-w-2xl mx-auto bg-gray-900 p-8 rounded-lg shadow-2xl">
    <h1 class="text-3xl font-extrabold text-white mb-6 text-center">Edit Product</h1>

    <?php
    // Display status messages
    if (isset($_GET['status']) && $_GET['status'] == 'updated') {
        echo '<div class="bg-green-800 border border-green-600 text-green-200 px-4 py-3 rounded relative mb-6">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline">Product updated successfully.</span>
              </div>';
    }
    ?>

    <form action="../controllers/process_edit_product.php" method="POST" class="space-y-6">
        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">

        <div>
            <label for="product_name" class="block text-sm font-medium text-gray-300 mb-1">Product Name</label>
            <input type="text" id="product_name" name="product_name" 
                   value="<?php echo htmlspecialchars($product['product_name']); ?>" 
                   class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-800 text-white focus:ring-blue-500 focus:border-blue-500" required>
        </div>

        <div>
            <label for="barcode" class="block text-sm font-medium text-gray-300 mb-1">Barcode</label>
            <input type="text" id="barcode" name="barcode" 
                   value="<?php echo htmlspecialchars($product['barcode']); ?>" 
                   class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-800 text-white focus:ring-blue-500 focus:border-blue-500" required>
        </div>

        <div>
            <label for="price" class="block text-sm font-medium text-gray-300 mb-1">Price</label>
            <input type="number" id="price" name="price" min="0.01" step="0.01" 
                   value="<?php echo htmlspecialchars(number_format($product['price'], 2)); ?>" 
                   class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-800 text-white focus:ring-blue-500 focus:border-blue-500" required>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-300 mb-1">Description</label>
            <textarea id="description" name="description" rows="4" 
                      class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-800 text-white focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
        </div>

        <div class="text-center">
            <button type="submit" 
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                Update Product
            </button>
            <a href="manage_products.php" 
               class="ml-4 inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-gray-700 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-300">
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
