<?php
// edit_invoice.php
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php"); // Redirect to login if not logged in
    exit();
}

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_invoices.php"); // Redirect if no valid ID
    exit();
}

$invoice_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$invoice_data = null;
$item_data = [];

// Fetch existing invoice details
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
    $sql_fetch_items = "SELECT id, description, quantity, unit_price, total FROM invoice_items WHERE invoice_id = ?";
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

// Handle form submission for updating the invoice
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve updated data
    $updated_invoice_date = htmlspecialchars(trim($_POST['invoice_date']));
    $updated_due_date = htmlspecialchars(trim($_POST['due_date']));
    $updated_to_client_name = htmlspecialchars(trim($_POST['to_client_name']));
    $updated_to_address = htmlspecialchars(trim($_POST['to_address']));
    $updated_to_email = htmlspecialchars(trim($_POST['to_email']));
    $updated_to_email = htmlspecialchars(trim($_POST['to_email']));
    $updated_notes = htmlspecialchars(trim($_POST['notes']));
    
    // Date Validation
    if (strtotime($updated_due_date) < strtotime($updated_invoice_date)) {
        header("Location: edit_invoice.php?id=$invoice_id&error=invalid_date");
        exit();
    }

    $updated_tax_rate = floatval($invoice_data['tax_rate']);
    $updated_discount_amount = floatval($invoice_data['discount_amount']);

    // Recalculate totals based on submitted item data
    $updated_subtotal = 0;
    $updated_items_data = [];
    $submitted_item_ids = [];

    if (isset($_POST['item_description']) && is_array($_POST['item_description'])) {
        foreach ($_POST['item_description'] as $key => $description) {
            $item_id = isset($_POST['item_id'][$key]) ? intval($_POST['item_id'][$key]) : 0;
            $qty = floatval(htmlspecialchars(trim($_POST['item_quantity'][$key])));
            $price = floatval($item['unit_price']); // Always from DB

            $item_total = $qty * $price;
            $updated_subtotal += $item_total;

            $updated_items_data[] = [
                'id' => $item_id,
                'description' => htmlspecialchars(trim($description)),
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => $item_total
            ];
            if ($item_id > 0) {
                $submitted_item_ids[] = $item_id;
            }
        }
    }

    $updated_tax_amount = $updated_subtotal * ($updated_tax_rate / 100);
    $updated_total_amount = ($updated_subtotal + $updated_tax_amount) - $updated_discount_amount;

    // Update main invoice table
    $sql_update_invoice = "UPDATE invoices SET
                            invoice_date = ?, due_date = ?, to_client_name = ?, to_address = ?, to_email = ?,
                            subtotal = ?, tax_rate = ?, tax_amount = ?, discount_amount = ?, total_amount = ?, notes = ?
                           WHERE id = ? AND user_id = ?";
    $stmt_update_invoice = $conn->prepare($sql_update_invoice);
    if ($stmt_update_invoice === false) {
        die("Error preparing invoice update statement: " . $conn->error);
    }
    $stmt_update_invoice->bind_param("sssssdddddsii",
        $updated_invoice_date, $updated_due_date, $updated_to_client_name, $updated_to_address, $updated_to_email,
        $updated_subtotal, $updated_tax_rate, $updated_tax_amount, $updated_discount_amount, $updated_total_amount, $updated_notes,
        $invoice_id, $user_id
    );

    if ($stmt_update_invoice->execute()) {
        // Handle invoice items (delete/update/insert)
        if (!empty($submitted_item_ids)) {
            $sql_delete_removed_items = "DELETE FROM invoice_items WHERE invoice_id = ? AND id NOT IN (" . implode(',', array_fill(0, count($submitted_item_ids), '?')) . ")";
            $stmt_delete_removed_items = $conn->prepare($sql_delete_removed_items);
            $stmt_delete_removed_items->bind_param(str_repeat('i', count($submitted_item_ids) + 1), $invoice_id, ...$submitted_item_ids);
            $stmt_delete_removed_items->execute();
            $stmt_delete_removed_items->close();
        } else {
            $sql_delete_all_items = "DELETE FROM invoice_items WHERE invoice_id = ?";
            $stmt_delete_all_items = $conn->prepare($sql_delete_all_items);
            $stmt_delete_all_items->bind_param("i", $invoice_id);
            $stmt_delete_all_items->execute();
            $stmt_delete_all_items->close();
        }

        foreach ($updated_items_data as $item) {
            if ($item['id'] > 0) {
                $sql_update_item = "UPDATE invoice_items SET description = ?, quantity = ?, unit_price = ?, total = ? WHERE id = ? AND invoice_id = ?";
                $stmt_update_item = $conn->prepare($sql_update_item);
                $stmt_update_item->bind_param("sdddii", $item['description'], $item['quantity'], $item['unit_price'], $item['total'], $item['id'], $invoice_id);
                $stmt_update_item->execute();
                $stmt_update_item->close();
            } else {
                $sql_insert_item = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert_item = $conn->prepare($sql_insert_item);
                $stmt_insert_item->bind_param("isddd", $invoice_id, $item['description'], $item['quantity'], $item['unit_price'], $item['total']);
                $stmt_insert_item->execute();
                $stmt_insert_item->close();
            }
        }

        $stmt_update_invoice->close();
        $conn->close();
        header("Location: manage_invoices.php?status=updated&invoice_id=" . $invoice_id);
        exit();
    } else {
        $stmt_update_invoice->close();
        $conn->close();
        header("Location: manage_invoices.php?error=update_failed");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prime Invoice - Smart Easy Modern</title>
</head>
<body class="bg-gray-900 text-gray-100">
<?php include './layouts/common_layout_start.php'; ?>

<div class="max-w-6xl mx-auto bg-gray-800 p-8 rounded-lg shadow-xl">
  <h1 class="text-4xl font-extrabold text-blue-400 mb-8 text-center">
    Edit Invoice #<?php echo htmlspecialchars($invoice_data['invoice_number']); ?>
  </h1>

  <form action="edit_invoice.php?id=<?php echo $invoice_id; ?>" method="POST" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
    <!-- Invoice Dates -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-4 bg-gray-700 rounded-lg shadow-inner">
      <div>
        <label for="invoice_date" class="block text-sm font-medium text-gray-300 mb-1">Invoice Date</label>
        <input type="date" id="invoice_date" name="invoice_date" value="<?php echo htmlspecialchars($invoice_data['invoice_date']); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 focus:ring-blue-500 focus:border-blue-500" required>
      </div>
      <div>
        <label for="due_date" class="block text-sm font-medium text-gray-300 mb-1">Due Date</label>
        <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($invoice_data['due_date']); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 focus:ring-blue-500 focus:border-blue-500" required>
      </div>
    </div>

    <!-- Bill To -->
    <div class="p-6 border border-gray-600 rounded-lg shadow-md bg-gray-700">
      <h2 class="text-xl font-semibold text-purple-400 mb-4">Bill To:</h2>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1">Client Name</label>
          <input type="text" name="to_client_name" value="<?php echo htmlspecialchars($invoice_data['to_client_name']); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 focus:ring-blue-500 focus:border-blue-500" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1">Address</label>
          <textarea name="to_address" rows="3" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($invoice_data['to_address']); ?></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
          <input type="email" name="to_email" value="<?php echo htmlspecialchars($invoice_data['to_email']); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 focus:ring-blue-500 focus:border-blue-500" required>
        </div>
      </div>
    </div>

    <!-- Invoice Items -->
    <div>
      <h2 class="text-xl font-semibold text-green-400 mb-4">Invoice Items</h2>
      <div id="invoice-items" class="space-y-4">
        <?php foreach ($item_data as $index => $item): ?>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center p-4 bg-gray-700 rounded-lg">
          <input type="hidden" name="item_id[]" value="<?php echo htmlspecialchars($item['id']); ?>">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
            <input type="text" name="item_description[]" value="<?php echo htmlspecialchars($item['description']); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 focus:ring-blue-500 focus:border-blue-500" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Quantity</label>
            <input type="number" name="item_quantity[]" value="<?php echo htmlspecialchars($item['quantity']); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 text-center focus:ring-blue-500 focus:border-blue-500" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Unit Price</label>
            <input type="number" value="<?php echo htmlspecialchars(number_format($item['unit_price'], 2)); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-400 border border-gray-600 text-right" readonly>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Total</label>
            <input type="text" value="<?php echo htmlspecialchars(number_format($item['total'], 2)); ?>" class="w-full p-3 rounded-md bg-gray-900 text-gray-400 border border-gray-600" readonly>
          </div>
          <div class="flex items-center justify-center">
            <button type="button" class="remove-item-btn text-red-400 hover:text-red-600">✖</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" id="add-item-btn" class="mt-4 px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">+ Add Item</button>
    </div>

    <!-- Totals -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1">Subtotal</label>
        <input type="text" id="subtotal" value="<?php echo htmlspecialchars(number_format($invoice_data['subtotal'], 2)); ?>" readonly class="w-full p-3 rounded-md bg-gray-900 text-gray-400 border border-gray-600">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1">Tax Rate (%)</label>
        <input type="number" id="tax_rate" value="<?php echo htmlspecialchars($invoice_data['tax_rate']); ?>" readonly class="w-full p-3 rounded-md bg-gray-900 text-gray-400 border border-gray-600 text-right">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1">Tax Amount</label>
        <input type="text" id="tax_amount" value="<?php echo htmlspecialchars(number_format($invoice_data['tax_amount'], 2)); ?>" readonly class="w-full p-3 rounded-md bg-gray-900 text-gray-400 border border-gray-600">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1">Discount Amount</label>
        <input type="number" id="discount_amount" value="<?php echo htmlspecialchars($invoice_data['discount_amount']); ?>" readonly class="w-full p-3 rounded-md bg-gray-900 text-gray-400 border border-gray-600 text-right">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1">Total Amount</label>
        <input type="text" id="total_amount" value="<?php echo htmlspecialchars(number_format($invoice_data['total_amount'], 2)); ?>" readonly class="w-full p-3 rounded-md bg-gray-900 text-gray-400 border border-gray-600">
      </div>
    </div>

    <!-- Notes -->
    <div>
      <label class="block text-sm font-medium text-gray-300 mb-1">Notes</label>
      <textarea name="notes" rows="4" class="w-full p-3 rounded-md bg-gray-900 text-gray-100 border border-gray-600 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($invoice_data['notes']); ?></textarea>
    </div>

    <!-- Buttons -->
    <div class="text-center">
      <button type="submit" class="px-6 py-3 rounded-md bg-blue-600 hover:bg-blue-700 text-white font-medium">Update Invoice</button>
      <a href="manage_invoices.php" class="ml-4 px-6 py-3 rounded-md bg-gray-700 text-gray-300 hover:bg-gray-600">Cancel</a>
    </div>
  </form>
</div>

<script>
// (Your JS from before stays unchanged)
</script>

<?php include './layouts/common_layout_end.php'; ?>
</body>
</html>
