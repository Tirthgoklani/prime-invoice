<?php
// manage_products.php
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$search_query = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = htmlspecialchars(trim($_GET['search']));
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// SQL to fetch products for the logged-in user with search and pagination
$sql_count = "SELECT COUNT(*) AS total FROM products WHERE user_id = ? AND deleted_at IS NULL";
$sql_fetch = "SELECT * FROM products WHERE user_id = ? AND deleted_at IS NULL";
$params_count = "i";
$params_fetch = "i";
$bind_values_count = [$user_id];
$bind_values_fetch = [$user_id];

if ($search_query) {
    $sql_count .= " AND (product_name LIKE ? OR barcode LIKE ? OR description LIKE ?)";
    $sql_fetch .= " AND (product_name LIKE ? OR barcode LIKE ? OR description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params_count .= "sss";
    $params_fetch .= "sss";
    $bind_values_count = array_merge($bind_values_count, [$search_param, $search_param, $search_param]);
    $bind_values_fetch = array_merge($bind_values_fetch, [$search_param, $search_param, $search_param]);
}

$sql_fetch .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params_fetch .= "ii";
$bind_values_fetch = array_merge($bind_values_fetch, [$limit, $offset]);

// Fetch total count for pagination
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param($params_count, ...$bind_values_count);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_products = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);
$stmt_count->close();

// Fetch products for the current page
$products = [];
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param($params_fetch, ...$bind_values_fetch);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

while ($row = $result_fetch->fetch_assoc()) {
    $products[] = $row;
}
$stmt_fetch->close();
$conn->close();

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>

<div class="max-w-6xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl">
    <h1 class="text-4xl font-extrabold text-blue-400 mb-8 text-center">Manage Products</h1>

    <?php
    // Display status messages
    if (isset($_GET['status'])) {
        $status = htmlspecialchars($_GET['status']);
        if ($status == 'added') {
            echo '<div class="bg-green-900 border border-green-600 text-green-300 px-4 py-3 rounded relative mb-6">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline"> Product added successfully.</span>
                  </div>';
        } elseif ($status == 'updated') {
            echo '<div class="bg-green-900 border border-green-600 text-green-300 px-4 py-3 rounded relative mb-6">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline"> Product updated successfully.</span>
                  </div>';
        } elseif ($status == 'deleted') {
            echo '<div class="bg-green-900 border border-green-600 text-green-300 px-4 py-3 rounded relative mb-6">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline"> Product deleted successfully.</span>
                  </div>';
        }
    }
    ?>

    <div class="mb-6 flex justify-between items-center">
        <form action="manage_products.php" method="GET" class="flex w-full md:w-1/2">
            <input type="text" name="search" placeholder="Search products using name/barcode"
                   class="flex-grow p-3 border border-gray-700 bg-gray-800 text-gray-200 rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                   value="<?php echo $search_query; ?>">
            <button type="submit" class="px-5 py-3 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 transition duration-300">
                Search
            </button>
        </form>
        <a href="./add_product.php" class="ml-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
            Add New Product
        </a>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center text-gray-400 p-8 border border-gray-700 rounded-lg bg-gray-800">
            <p class="text-lg font-semibold mb-2">No products found.</p>
            <?php if ($search_query): ?>
                <p>Try adjusting your search criteria or <a href="manage_products.php" class="text-blue-400 hover:underline">view all products</a>.</p>
            <?php else: ?>
                <p>Start by <a href="./add_product.php" class="text-blue-400 hover:underline">adding a new product</a>.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto shadow-md rounded-lg">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Product Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Barcode</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Price</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-gray-900 divide-y divide-gray-700">
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-200">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                <?php echo htmlspecialchars($product['barcode']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-200">
                                ₹<?php echo number_format($product['price'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400">
                                <?php echo htmlspecialchars($product['description'] ? substr($product['description'], 0, 50) . (strlen($product['description']) > 50 ? '...' : '') : 'No description'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="text-indigo-400 hover:text-indigo-200 mr-3" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                        <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                                <button onclick="confirmDeletion(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')" class="text-red-400 hover:text-red-200" title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm2 4a1 1 0 00-1 1v3a1 1 0 102 0v-3a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="flex justify-center items-center gap-x-1 mt-8">
                <?php if ($page > 1): ?>
                    <a href="manage_products.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>" class="min-h-[38px] min-w-[38px] flex justify-center items-center text-gray-300 hover:bg-gray-700 py-2 px-3 rounded-xl text-sm">
                        <svg class="flex-shrink-0 w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </a>
                <?php endif; ?>

                <div class="flex items-center gap-x-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="manage_products.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"
                           class="min-h-[38px] min-w-[38px] flex justify-center items-center <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?> py-2 px-3 rounded-xl text-sm"
                           aria-current="<?php echo ($i == $page) ? 'page' : 'false'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="manage_products.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>" class="min-h-[38px] min-w-[38px] flex justify-center items-center text-gray-300 hover:bg-gray-700 py-2 px-3 rounded-xl text-sm">
                        <svg class="flex-shrink-0 w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
    function confirmDeletion(productId, productName) {
        Swal.fire({
            title: 'Delete Product?',
            text: "Are you sure you want to delete \"" + productName + "\"? This cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            background: '#1f2937',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../controllers/delete_product.php?id=' + productId;
            }
        });
    }
</script>

<?php
if (!$is_ajax) {
    include './layouts/common_layout_end.php';
}
?>
