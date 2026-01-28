<?php
// manage_invoices.php
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

// SQL queries
$sql_count = "SELECT COUNT(*) AS total FROM invoices WHERE user_id = ? AND deleted_at IS NULL";
$sql_fetch = "SELECT * FROM invoices WHERE user_id = ? AND deleted_at IS NULL";
$params_count = "i";
$params_fetch = "i";
$bind_values_count = [$user_id];
$bind_values_fetch = [$user_id];

if ($search_query) {
    $sql_count .= " AND (invoice_number LIKE ? OR to_client_name LIKE ? OR to_email LIKE ?)";
    $sql_fetch .= " AND (invoice_number LIKE ? OR to_client_name LIKE ? OR to_email LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params_count .= "sss";
    $params_fetch .= "sss";
    $bind_values_count = array_merge($bind_values_count, [$search_param, $search_param, $search_param]);
    $bind_values_fetch = array_merge($bind_values_fetch, [$search_param, $search_param, $search_param]);
}

$sql_fetch .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params_fetch .= "ii";
$bind_values_fetch = array_merge($bind_values_fetch, [$limit, $offset]);

// --- Fetch total count for pagination ---
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    die("Error preparing count statement: " . $conn->error);
}
$stmt_count->bind_param($params_count, ...$bind_values_count);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_invoices = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_invoices / $limit);
$stmt_count->close();

// --- Fetch invoices ---
$invoices = [];
$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch === false) {
    die("Error preparing fetch statement: " . $conn->error);
}
$stmt_fetch->bind_param($params_fetch, ...$bind_values_fetch);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

while ($row = $result_fetch->fetch_assoc()) {
    $invoices[] = $row;
}
$stmt_fetch->close();
$conn->close();

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>

<div class="max-w-7xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl text-gray-200">
    <h1 class="text-4xl font-extrabold text-white mb-8 text-center">Manage Invoices</h1>

    <?php
    if (isset($_GET['status'])) {
        $status = htmlspecialchars($_GET['status']);
        $invoice_id_msg = isset($_GET['invoice_id']) ? htmlspecialchars($_GET['invoice_id']) : '';

        if ($status == 'deleted') {
            echo '<div class="bg-green-800 border border-green-500 text-green-100 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline">Invoice #' . $invoice_id_msg . ' deleted successfully.</span>
                </div>';
        } elseif ($status == 'updated') {
            echo '<div class="bg-green-800 border border-green-500 text-green-100 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline">Invoice #' . $invoice_id_msg . ' updated successfully.</span>
                </div>';
        } elseif ($status == 'sent') {
            echo '<div class="bg-green-800 border border-green-500 text-green-100 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline">Invoice #' . $invoice_id_msg . ' has been emailed successfully.</span>
                </div>';
        }
    }

    if (isset($_GET['error'])) {
        $error = htmlspecialchars($_GET['error']);
        $error_message = '';

        if ($error == 'no_id') {
            $error_message = 'No invoice ID provided.';
        } elseif ($error == 'notfound_or_unauthorized') {
            $error_message = 'Invoice not found or you are not authorized.';
        } elseif ($error == 'notfound') {
            $error_message = 'Invoice not found or you are not authorized to view/edit it.';
        } elseif ($error == 'mail_failed') {
            $details = isset($_GET['details']) ? htmlspecialchars($_GET['details']) : 'Unknown error';
            $error_message = 'Failed to send the invoice email. Details: ' . $details;
        } else {
            $error_message = 'An unknown error occurred.';
        }

        echo '<div class="bg-red-800 border border-red-500 text-red-100 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">' . $error_message . '</span>
            </div>';
    }
    ?>

    <div class="mb-6 flex justify-between items-center">
        <form action="manage_invoices.php" method="GET" class="flex w-full md:w-1/2">
            <input type="text" name="search" placeholder="Search by invoice # or client name/email..."
                   class="flex-grow p-3 bg-gray-800 border border-gray-700 text-gray-200 rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                   value="<?php echo $search_query; ?>">
            <button type="submit" class="px-5 py-3 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 transition duration-300">
                Search
            </button>
        </form>
        <a href="create_invoice.php" class="ml-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
            Create New Invoice
        </a>
    </div>

    <?php if (empty($invoices)): ?>
        <div class="text-center text-gray-300 p-8 border border-gray-700 rounded-lg bg-gray-800">
            <p class="text-lg font-semibold mb-2">No invoices found for you.</p>
            <?php if ($search_query): ?>
                <p>Try adjusting your search criteria or <a href="manage_invoices.php" class="text-blue-400 hover:underline">view all invoices</a>.</p>
            <?php else: ?>
                <p>Start by <a href="create_invoice.php" class="text-blue-400 hover:underline">creating a new invoice</a>.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto shadow-md rounded-lg">
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Client Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Due Date</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-gray-900 divide-y divide-gray-700">
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover:bg-gray-800">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">
                                <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo htmlspecialchars($invoice['to_client_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                <?php echo htmlspecialchars($invoice['invoice_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                <?php echo htmlspecialchars($invoice['due_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-green-400">
                                ₹<?php echo number_format($invoice['total_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-400 hover:text-blue-600 mr-3" title="View">
                                    👁️
                                </a>
                                <a href="edit_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-indigo-400 hover:text-indigo-600 mr-3" title="Edit">
                                    ✏️
                                </a>
                                <a href="../controllers/send_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-green-400 hover:text-green-600 mr-3" title="Send Mail">
                                    ✉️
                                </a>
                                <button onclick="confirmDeletion(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')" class="text-red-400 hover:text-red-600" title="Delete">
                                    🗑️
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
                    <a href="manage_invoices.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>" class="min-h-[38px] min-w-[38px] flex justify-center items-center text-gray-300 hover:bg-gray-700 py-2 px-3 rounded-xl text-sm">
                        ⬅
                    </a>
                <?php endif; ?>

                <div class="flex items-center gap-x-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="manage_invoices.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"
                           class="min-h-[38px] min-w-[38px] flex justify-center items-center <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?> py-2 px-3 rounded-xl text-sm">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="manage_invoices.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>" class="min-h-[38px] min-w-[38px] flex justify-center items-center text-gray-300 hover:bg-gray-700 py-2 px-3 rounded-xl text-sm">
                        ➡
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function confirmDeletion(invoiceId, invoiceNumber) {
    Swal.fire({
        title: 'Delete Invoice?',
        text: "Are you sure you want to delete Invoice #" + invoiceNumber + "? This cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        background: '#1f2937',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../controllers/delete_invoice.php?id=' + invoiceId;
        }
    });
}
</script>

<?php
if (!$is_ajax) {
    include './layouts/common_layout_end.php';
}
?>
