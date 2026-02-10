<?php
// view_invoice.php
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php"); // Redirect to login if not logged in
    exit();
}

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_invoices.php?error=no_id"); // Redirect if no valid ID
    exit();
}

$invoice_id = intval($_GET['id']);
$user_id = $_SESSION['user_id']; // Get the logged-in user's ID

$invoice_data = null;
$item_data = [];

// Fetch existing invoice details, ensuring it belongs to the logged-in user
$sql_fetch_invoice = "SELECT * FROM invoices WHERE id = ? AND user_id = ?";
$stmt_fetch_invoice = $conn->prepare($sql_fetch_invoice);
if ($stmt_fetch_invoice === false) {
    die("Error preparing invoice fetch statement: " . $conn->error);
}
$stmt_fetch_invoice->bind_param("ii", $invoice_id, $user_id);
$stmt_fetch_invoice->execute();
$result_fetch_invoice = $stmt_fetch_invoice->get_result();

if ($result_fetch_invoice->num_rows > 0) {
    $invoice_data = $result_fetch_invoice->fetch_assoc();

    // Fetch existing invoice items
    $sql_fetch_items = "SELECT description, quantity, unit_price, total FROM invoice_items WHERE invoice_id = ?";
    $stmt_fetch_items = $conn->prepare($sql_fetch_items);
    if ($stmt_fetch_items === false) {
        die("Error preparing items fetch statement: " . $conn->error);
    }
    $stmt_fetch_items->bind_param("i", $invoice_id);
    $stmt_fetch_items->execute();
    $result_fetch_items = $stmt_fetch_items->get_result();

    while ($row = $result_fetch_items->fetch_assoc()) {
        $item_data[] = $row;
    }
    $stmt_fetch_items->close();
} else {
    // Invoice not found or not belonging to the user
    $stmt_fetch_invoice->close();
    $conn->close();
    header("Location: manage_invoices.php?error=notfound");
    exit();
}
$stmt_fetch_invoice->close();

// Fetch User Settings (Branding)
$sql_settings = "SELECT * FROM user_settings WHERE user_id = ?";
$stmt_settings = $conn->prepare($sql_settings);
$stmt_settings->bind_param("i", $user_id);
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();
$settings = $result_settings->fetch_assoc();
$stmt_settings->close();

$theme_color = $settings['theme_color'] ?? '#3B82F6';
$currency_symbol = $settings['currency_symbol'] ?? '$';
$company_logo = $settings['company_logo'] ?? null;

$conn->close();

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>

<div class="max-w-4xl mx-auto bg-gray-900 text-white p-8 rounded-lg shadow-xl print-container">
    <div class="flex justify-between items-start mb-8 print-hide">
        <div>
            <?php if ($company_logo): ?>
                <img src="../../<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo" class="h-20 mb-3 object-contain max-w-xs">
            <?php endif; ?>
            <h1 class="text-4xl font-extrabold" style="color: <?php echo htmlspecialchars($theme_color); ?>;">
                Invoice #<?php echo htmlspecialchars($invoice_data['invoice_number']); ?>
            </h1>
        </div>
        <div class="flex space-x-3">
            <!-- Print Button -->
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-md shadow-sm text-white bg-gray-700 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 4V2a2 2 0 012-2h6a2 2 0 012 2v2h2a2 2 0 012 2v8a2 2 0 01-2 2H3a2 2 0 01-2-2V6a2 2 0 012-2h2zm0 2h10v6H5V6zm-2 8a2 2 0 00-2 2v2h16v-2a2 2 0 00-2-2H3z" clip-rule="evenodd" />
                </svg>
                Print Invoice
            </button>

            <!-- Edit Invoice Button -->
            <a href="./edit_invoice.php?id=<?php echo $invoice_data['id']; ?>" class="inline-flex items-center px-4 py-2 border border-indigo-600 text-sm font-medium rounded-md shadow-sm text-white bg-indigo-700 hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                </svg>
                Edit Invoice
            </a>

            <!-- Back Button -->
            <a href="./manage_invoices.php" class="inline-flex items-center px-4 py-2 border border-red-600 text-sm font-medium rounded-md shadow-sm text-white bg-red-700 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11l-3 3 3 3V7z" clip-rule="evenodd" />
                </svg>
                Back
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <div>
        <div>
            <h2 class="text-xl font-semibold mb-2" style="color: <?php echo htmlspecialchars($theme_color); ?>;">Bill From:</h2>
            <p class="text-white font-bold"><?php echo htmlspecialchars($invoice_data['from_company_name']); ?></p>
            <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($invoice_data['from_address'])); ?></p>
            <p class="text-gray-300"><?php echo htmlspecialchars($invoice_data['from_email']); ?></p>
        </div>
        <div class="text-left md:text-right">
            <h2 class="text-xl font-semibold mb-2" style="color: <?php echo htmlspecialchars($theme_color); ?>;">Invoice Details:</h2>
            <p class="text-gray-200"><strong>Date:</strong> <?php echo htmlspecialchars($invoice_data['invoice_date']); ?></p>
            <p class="text-gray-200"><strong>Due:</strong> <?php echo htmlspecialchars($invoice_data['due_date']); ?></p>
            <p class="text-gray-200"><strong>Number:</strong> <?php echo htmlspecialchars($invoice_data['invoice_number']); ?></p>
        </div>
    </div>

    <div class="mb-8 p-6 border rounded-lg shadow-sm bg-gray-800" style="border-color: <?php echo htmlspecialchars($theme_color); ?>;">
        <h2 class="text-xl font-semibold mb-4" style="color: <?php echo htmlspecialchars($theme_color); ?>;">Bill To:</h2>
        <p class="text-white font-bold"><?php echo htmlspecialchars($invoice_data['to_client_name']); ?></p>
        <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($invoice_data['to_address'])); ?></p>
        <p class="text-gray-300"><?php echo htmlspecialchars($invoice_data['to_email']); ?></p>
    </div>

    <div class="mb-8 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700 border border-gray-700 rounded-lg">
            <thead class="bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Quantity</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Unit Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="bg-gray-900 divide-y divide-gray-700">
                <?php if (!empty($item_data)): ?>
                    <?php foreach ($item_data as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white"><?php echo htmlspecialchars($item['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-300"><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-300"><?php echo htmlspecialchars($currency_symbol) . number_format($item['unit_price'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-white"><?php echo htmlspecialchars($currency_symbol) . number_format($item['total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-400">No items found for this invoice.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <?php if (!empty($invoice_data['notes'])): ?>
                <h2 class="text-xl font-semibold mb-2" style="color: <?php echo htmlspecialchars($theme_color); ?>;">Notes:</h2>
                <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($invoice_data['notes']); ?></p>
            <?php endif; ?>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-700">
            <h2 class="text-xl font-semibold mb-4" style="color: <?php echo htmlspecialchars($theme_color); ?>;">Summary:</h2>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-300">Subtotal:</span>
                    <span class="font-medium text-white"><?php echo htmlspecialchars($currency_symbol) . number_format($invoice_data['subtotal'], 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-300">Tax Rate:</span>
                    <span class="font-medium text-white"><?php echo number_format($invoice_data['tax_rate'], 2); ?>%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-300">Tax Amount:</span>
                    <span class="font-medium text-white"><?php echo htmlspecialchars($currency_symbol) . number_format($invoice_data['tax_amount'], 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-300">Discount:</span>
                    <span class="font-medium text-white"><?php echo htmlspecialchars($currency_symbol) . number_format($invoice_data['discount_amount'], 2); ?></span>
                </div>
                <div class="flex justify-between border-t border-gray-700 pt-3 mt-3">
                    <span class="text-lg font-bold text-white">Total:</span>
                    <span class="text-lg font-bold" style="color: <?php echo htmlspecialchars($theme_color); ?>;"><?php echo htmlspecialchars($currency_symbol) . number_format($invoice_data['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    @page {
    margin: 0;   /* Removes browser’s default white border */
    size: A4;    /* or 'auto' / 'Letter' depending on your region */
}
    body {
        background-color: #111827 !important; /* Dark background */
        color: #fff !important;
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-container {

        background: #1f2937 !important; /* Dark gray card */
        color: #fff !important;
        box-shadow: none !important;
        margin: 0 !important;
        max-width: 100% !important;
        padding: 20px !important;
    }
    table, th, td {
        border-color: #fff !important;
        color: #fff !important;
    }
    .print-hide {
        display: none !important;
    }
    aside, main > *:not(.print-container) {
        display: none !important;
    }
}
</style>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname.substring(window.location.pathname.lastIndexOf('/') + 1);
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.classList.remove('active');
            link.style.backgroundColor = '';
            if (link.getAttribute('href') === 'manage_invoices.php' && currentPath.startsWith('view_invoice.php')) {
                link.classList.add('active');
                link.style.backgroundColor = '#1a56db';
            } else if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
                link.style.backgroundColor = '#1a56db';
            }
        });
    });
</script>

<?php
if (!$is_ajax) {
    include './layouts/common_layout_end.php';
}
?>
