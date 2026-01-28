<?php
// create_invoice.php (Invoice Creation Page) - Dark Theme
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Get company details from session for "Bill From" section
$from_company_name = $_SESSION['company_name'];
$from_address = $_SESSION['company_address'];
$from_email = $_SESSION['company_email'];
$user_id = $_SESSION['user_id'];

// Fetch default settings for the user
$default_settings = [
    'default_tax_rate' => 0.00,
    'default_discount_amount' => 0.00,
    'invoice_notes' => '',
];

$sql_fetch_settings = "SELECT default_tax_rate, default_discount_amount, invoice_notes FROM user_settings WHERE user_id = ?";
$stmt_fetch_settings = $conn->prepare($sql_fetch_settings);
if ($stmt_fetch_settings) {
    $stmt_fetch_settings->bind_param("i", $user_id);
    $stmt_fetch_settings->execute();
    $result_settings = $stmt_fetch_settings->get_result();
    
    if ($result_settings->num_rows > 0) {
        $default_settings = $result_settings->fetch_assoc();
    }
    $stmt_fetch_settings->close();
}

// Fetch existing clients for the dropdown
$clients = [];
$sql_fetch_clients = "SELECT id, client_name, client_address, client_email, client_phone FROM clients WHERE user_id = ? ORDER BY client_name ASC";
$stmt_fetch_clients = $conn->prepare($sql_fetch_clients);
if ($stmt_fetch_clients === false) {
    die("Error preparing client fetch statement: " . $conn->error);
}
$stmt_fetch_clients->bind_param("i", $user_id);
$stmt_fetch_clients->execute();
$result_fetch_clients = $stmt_fetch_clients->get_result();

while ($row = $result_fetch_clients->fetch_assoc()) {
    $clients[] = $row;
}
$stmt_fetch_clients->close();

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>
<title>Prime Invoice - Smart Easy Modern</title>
<div class="max-w-6xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl">
    <h1 class="text-4xl font-extrabold text-white mb-8 text-center">Create New Invoice</h1>

    <form action="../../src/controllers/process_invoice.php" method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-4 bg-gray-800 rounded-lg shadow-inner border border-gray-700">
            <div>
                <label for="invoice_date" class="block text-sm font-medium text-gray-300 mb-1">Invoice Date</label>
                <input type="date" id="invoice_date" name="invoice_date" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div>
                <label for="due_date" class="block text-sm font-medium text-gray-300 mb-1">Due Date</label>
                <input type="date" id="due_date" name="due_date" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500" required>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 mb-8">
            <div class="p-6 border border-blue-500 rounded-lg shadow-md bg-gray-800">
                <h2 class="text-xl font-semibold text-blue-400 mb-4">Bill To:</h2>
                <div class="space-y-4">
                    <div>
                        <label for="select_client" class="block text-sm font-medium text-gray-300 mb-1">Select Existing Client</label>
                        <select id="select_client" name="selected_client_id" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Select a Client or Enter New --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo htmlspecialchars($client['id']); ?>"
                                        data-name="<?php echo htmlspecialchars($client['client_name']); ?>"
                                        data-address="<?php echo htmlspecialchars($client['client_address']); ?>"
                                        data-email="<?php echo htmlspecialchars($client['client_email']); ?>"
                                        data-phone="<?php echo htmlspecialchars($client['client_phone']); ?>">
                                    <?php echo htmlspecialchars($client['client_name']); ?> (<?php echo htmlspecialchars($client['client_email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <hr class="border-gray-700 my-4">
                    <div>
                        <label for="to_client_name" class="block text-sm font-medium text-gray-300 mb-1">Client Name</label>
                        <input type="text" id="to_client_name" name="to_client_name" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500" placeholder="Client Name" required>
                    </div>
                    <div>
                        <label for="to_address" class="block text-sm font-medium text-gray-300 mb-1">Address</label>
                        <textarea id="to_address" name="to_address" rows="3" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500" placeholder="456 Client St, City, State, Zip" required></textarea>
                    </div>
                    <div>
                        <label for="to_email" class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                        <input type="email" id="to_email" name="to_email" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500" placeholder="client@example.com" required>
                    </div>
                    <div>
                        <label for="to_phone" class="block text-sm font-medium text-gray-300 mb-1">Phone (Optional)</label>
                        <input type="text" id="to_phone" name="to_phone" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., +1 (555) 123-4567">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-8 p-6 border border-green-500 rounded-lg shadow-md bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-green-400">Items:</h2>
            </div>
            <div id="invoice-items" class="space-y-5">
            </div>
            <button type="button" id="add-item-btn" class="mt-4 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                Add Item
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="p-6 border border-purple-500 rounded-lg shadow-md bg-gray-800">
                <h2 class="text-xl font-semibold text-purple-400 mb-4">Notes:</h2>
                <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full p-3 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white focus:ring-blue-500 focus:border-blue-500" placeholder="Any additional notes or terms..."><?php echo htmlspecialchars($default_settings['invoice_notes']); ?></textarea>
            </div>
            <div class="p-6 border border-yellow-500 rounded-lg shadow-md bg-gray-800">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Summary:</h2>
                <div class="space-y-3 text-gray-300">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span class="font-medium">₹<input type="text" name="subtotal" id="subtotal" value="0.00" class="inline-block w-24 text-right bg-transparent border-none focus:outline-none text-white" readonly></span>
                    </div>
                    <div class="flex justify-between">
                        <label for="tax_rate">Tax Rate (%):</label>
                        <input type="number" id="tax_rate" name="tax_rate" min="0" step="0.01" value="<?php echo number_format($default_settings['default_tax_rate'], 2); ?>" class="inline-block w-24 p-1 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white text-right">
                    </div>
                    <div class="flex justify-between">
                        <span>Tax Amount:</span>
                        <span class="font-medium">₹<input type="text" name="tax_amount" id="tax_amount" value="0.00" class="inline-block w-24 text-right bg-transparent border-none focus:outline-none text-white" readonly></span>
                    </div>
                    <div class="flex justify-between">
                        <label for="discount_amount">Discount (₹):</label>
                        <input type="number" id="discount_amount" name="discount_amount" min="0" step="0.01" value="<?php echo number_format($default_settings['default_discount_amount'], 2); ?>" class="inline-block w-24 p-1 border border-gray-600 rounded-md shadow-sm bg-gray-700 text-white text-right">
                    </div>
                    <div class="flex justify-between border-t border-gray-700 pt-3 mt-3">
                        <span class="text-lg font-bold text-white">Total:</span>
                        <span class="text-lg font-bold text-white">₹<input type="text" name="total_amount" id="total_amount" value="0.00" class="inline-block w-24 text-right bg-transparent border-none focus:outline-none text-white" readonly></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center">
            <button name="submit" type="submit" class="inline-flex items-center px-6 py-3 text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-300">
                Save Invoice
            </button>
            <button type="button" id="print-form-btn" class="ml-4 inline-flex items-center px-6 py-3 text-base font-medium rounded-md shadow-sm text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-300">
                Print Invoice
            </button>
        </div>
    </form>
</div>

<script>
// Function to add a new item row
function addItemRow() {
    const invoiceItemsContainer = document.getElementById('invoice-items');
    if (!invoiceItemsContainer) {
        console.error('Error: invoice-items container not found');
        return;
    }
    const itemCounter = invoiceItemsContainer.children.length;
    const itemRow = document.createElement('div');
    // Revert to md:grid-cols-5 to match original layout (adjust if your design differs)
    itemRow.className = 'grid grid-cols-1 md:grid-cols-5 gap-4 items-center p-4 bg-gray-800 border border-gray-700 rounded-lg shadow-sm';
    itemRow.innerHTML = `
    <div>
        <label for="item_barcode_${itemCounter}" class="block text-sm font-medium text-gray-200 mb-1">Barcode</label>
        <div class="flex">
            <input type="text" id="item_barcode_${itemCounter}" name="item_barcode[]" 
                class="flex-grow w-f p-3 border border-gray-700 rounded-md w-full shadow-sm 
                       bg-gray-800 text-gray-200 focus:ring-blue-500 focus:border-blue-500" 
                placeholder="Scan or enter barcode">
        </div>
    </div>
    <div>
        <label for="item_description_${itemCounter}" class="block text-sm font-medium text-gray-200 mb-1">Description</label>
        <input type="text" id="item_description_${itemCounter}" name="item_description[]" 
            class="mt-1 block w-full p-3 border border-gray-700 rounded-md shadow-sm 
                   bg-gray-800 text-gray-200 focus:ring-blue-500 focus:border-blue-500" required>
    </div>
    <div>
        <label for="item_quantity_${itemCounter}" class="block text-sm font-medium text-gray-200 mb-1">Quantity</label>
        <input type="number" id="item_quantity_${itemCounter}" name="item_quantity[]" min="1" value="1" 
            class="mt-1 block w-full p-3 border border-gray-700 rounded-md shadow-sm 
                   bg-gray-800 text-gray-200 text-center focus:ring-blue-500 focus:border-blue-500" required>
    </div>
    <div>
        <label for="item_price_${itemCounter}" class="block text-sm font-medium text-gray-200 mb-1">Unit Price</label>
        <input type="number" id="item_price_${itemCounter}" name="item_price[]" min="0.01" step="0.01" value="0.00" 
            class="mt-1 block w-full p-3 border border-gray-700 rounded-md shadow-sm 
                   bg-gray-800 text-gray-200 text-right focus:ring-blue-500 focus:border-blue-500" required>
    </div>
    <div>
        <label for="item_total_${itemCounter}" class="block text-sm font-medium text-gray-200 mb-1">Total</label>
        <input type="text" id="item_total_${itemCounter}" name="item_total[]" 
            class="mt-1 block w-full p-3 border border-gray-700 rounded-md shadow-sm 
                   bg-gray-900 text-gray-400 cursor-not-allowed" value="0.00" readonly>
    </div>
    <div class="flex items-center justify-center">
        <button type="button" 
            class="remove-item-btn text-white bg-red-600 p-1 rounded hover:bg-red-500 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" 
                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 
                       002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 
                       0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm2 4a1 1 0 00-1 
                       1v3a1 1 0 102 0v-3a1 1 0 00-1-1z" 
                    clip-rule="evenodd" />
            </svg>
        </button>
    </div>
`;

    invoiceItemsContainer.appendChild(itemRow);
    attachEventListeners(itemRow);
    calculateTotals();

    // Auto-focus on the first barcode input after adding the initial row
    if (itemCounter === 0) {
        const firstBarcodeInput = document.getElementById('item_barcode_0');
        if (firstBarcodeInput) {
            firstBarcodeInput.focus();
        } else {
            console.warn('First barcode input not found for autofocus');
        }
    }
}

// Function to attach event listeners to item rows
function attachEventListeners(row) {
    const barcodeInput = row.querySelector('input[name="item_barcode[]"]');
    const quantityInput = row.querySelector('input[name="item_quantity[]"]');
    const priceInput = row.querySelector('input[name="item_price[]"]');
    const descriptionInput = row.querySelector('input[name="item_description[]"]');
    const totalInput = row.querySelector('input[name="item_total[]"]');
    const removeItemBtn = row.querySelector('.remove-item-btn');

    const handleBarcodeScan = debounce(async function() {
        const barcode = barcodeInput.value.trim();
        if (!barcode) return;

        try {
            const response = await fetch(`../controllers/get_product_by_barcode.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await response.json();
            if (data.success) {
                descriptionInput.value = data.product.product_name || '';
                priceInput.value = parseFloat(data.product.price || 0).toFixed(2);
                quantityInput.value = 1;
                totalInput.value = (parseFloat(quantityInput.value) * parseFloat(priceInput.value)).toFixed(2);
                calculateTotals();
                // Get the container reference to check if we need to add a new row
                const invoiceItemsContainer = document.getElementById('invoice-items');
                if (row === invoiceItemsContainer.lastElementChild) {
                    addItemRow();
                    // Auto-focus on the new row's barcode input
                    setTimeout(() => {
                        const newRowBarcodeInput = invoiceItemsContainer.lastElementChild.querySelector('input[name="item_barcode[]"]');
                        if (newRowBarcodeInput) {
                            newRowBarcodeInput.focus();
                        }
                    }, 100);
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Product Not Found',
                    text: data.message || 'No product found for barcode: ' + barcode,
                    background: '#1f2937',
                    color: '#fff'
                });
                descriptionInput.value = '';
                priceInput.value = '0.00';
                quantityInput.value = 1;
                totalInput.value = '0.00';
                calculateTotals();
            }
        } catch (error) {
            console.error('Error fetching product:', error);
            Swal.fire({
                icon: 'error',
                title: 'Networking Error',
                text: 'Error fetching product details. Please try again.',
                background: '#1f2937',
                color: '#fff'
            });
            descriptionInput.value = '';
            priceInput.value = '0.00';
            quantityInput.value = 1;
            totalInput.value = '0.00';
            calculateTotals();
        }
    }, 300);

    if (barcodeInput) {
        barcodeInput.addEventListener('input', handleBarcodeScan);
        barcodeInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleBarcodeScan();
            }
        });
    }

    if (quantityInput) {
        quantityInput.addEventListener('input', function() {
            const price = parseFloat(priceInput.value) || 0;
            const quantity = parseFloat(quantityInput.value) || 0;
            totalInput.value = (quantity * price).toFixed(2);
            calculateTotals();
        });
    }

    if (priceInput) {
        priceInput.addEventListener('input', function() {
            const quantity = parseFloat(quantityInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            totalInput.value = (quantity * price).toFixed(2);
            calculateTotals();
        });
    }

    if (removeItemBtn) {
        removeItemBtn.addEventListener('click', function() {
            row.remove();
            calculateTotals();
        });
    }
}

// Debounce function to limit barcode scan frequency
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Calculate totals for all items and summary
function calculateTotals() {
    let subtotal = 0;
    const invoiceItemsContainer = document.getElementById('invoice-items');
    if (!invoiceItemsContainer) {
        console.error('Error: invoice-items container not found in calculateTotals');
        return;
    }
    invoiceItemsContainer.querySelectorAll('.grid').forEach(row => {
        const quantityInput = row.querySelector('input[name="item_quantity[]"]');
        const priceInput = row.querySelector('input[name="item_price[]"]');
        const totalInput = row.querySelector('input[name="item_total[]"]');
        const quantity = parseFloat(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const itemTotal = quantity * price;
        totalInput.value = itemTotal.toFixed(2);
        subtotal += itemTotal;
    });

    const taxRateInput = document.getElementById('tax_rate');
    const discountAmountInput = document.getElementById('discount_amount');
    const subtotalInput = document.getElementById('subtotal');
    const taxAmountInput = document.getElementById('tax_amount');
    const totalAmountInput = document.getElementById('total_amount');

    if (!taxRateInput || !discountAmountInput || !subtotalInput || !taxAmountInput || !totalAmountInput) {
        console.error('Error: One or more summary input fields not found');
        return;
    }

    const taxRate = parseFloat(taxRateInput.value) || 0;
    const discountAmount = parseFloat(discountAmountInput.value) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    const totalAmount = (subtotal + taxAmount) - discountAmount;

    subtotalInput.value = subtotal.toFixed(2);
    taxAmountInput.value = taxAmount.toFixed(2);
    totalAmountInput.value = totalAmount.toFixed(2);
}

// Initialize the page with retry mechanism
function initializeInvoicePage() {
    const invoiceItemsContainer = document.getElementById('invoice-items');
    const addItemBtn = document.getElementById('add-item-btn');
    const selectClientDropdown = document.getElementById('select_client');
    const taxRateInput = document.getElementById('tax_rate');
    const discountAmountInput = document.getElementById('discount_amount');
    const printFormBtn = document.getElementById('print-form-btn');

    if (!invoiceItemsContainer || !addItemBtn || !taxRateInput || !discountAmountInput) {
        console.warn('Required elements not found, retrying initialization...');
        setTimeout(initializeInvoicePage, 100); // Retry after 100ms
        return;
    }

    // Add initial item row if none exist
    if (invoiceItemsContainer.children.length === 0) {
        addItemRow();
    }

    // Attach event listener to add item button
    addItemBtn.removeEventListener('click', addItemRow); // Prevent duplicate listeners
    addItemBtn.addEventListener('click', () => {
        addItemRow();
        console.log('Add Item button clicked, new row added');
    });

    // Client selection handler
    if (selectClientDropdown) {
        selectClientDropdown.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('to_client_name').value = selectedOption.dataset.name || '';
                document.getElementById('to_address').value = selectedOption.dataset.address || '';
                document.getElementById('to_email').value = selectedOption.dataset.email || '';
                document.getElementById('to_phone').value = selectedOption.dataset.phone || '';
            } else {
                document.getElementById('to_client_name').value = '';
                document.getElementById('to_address').value = '';
                document.getElementById('to_email').value = '';
                document.getElementById('to_phone').value = '';
            }
        });
    }

    // Event listeners for summary fields
    taxRateInput.addEventListener('input', calculateTotals);
    discountAmountInput.addEventListener('input', calculateTotals);

// Replace the existing print form functionality in your script section

// Print form functionality - Updated to print only invoice content
if (printFormBtn) {
    printFormBtn.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent form submission
        
        // First validate the form
        const form = document.querySelector('form');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Create invoice preview for printing
        createInvoicePrintView();
    });
}

// Function to create a print-friendly invoice view
function createInvoicePrintView() {
    // Get form data
    const formData = new FormData(document.querySelector('form'));
    const invoiceDate = formData.get('invoice_date');
    const dueDate = formData.get('due_date');
    const clientName = formData.get('to_client_name');
    const clientAddress = formData.get('to_address');
    const clientEmail = formData.get('to_email');
    const clientPhone = formData.get('to_phone');
    const notes = formData.get('notes');
    
    // Get company details (these should be available from PHP)
    const companyName = "<?php echo htmlspecialchars($from_company_name); ?>";
    const companyAddress = "<?php echo htmlspecialchars($from_address); ?>";
    const companyEmail = "<?php echo htmlspecialchars($from_email); ?>";
    
    // Collect items
    const items = [];
    const itemRows = document.querySelectorAll('#invoice-items .grid');
    itemRows.forEach(row => {
        const description = row.querySelector('input[name="item_description[]"]').value;
        const quantity = row.querySelector('input[name="item_quantity[]"]').value;
        const price = row.querySelector('input[name="item_price[]"]').value;
        const total = row.querySelector('input[name="item_total[]"]').value;
        
        if (description && quantity && price) {
            items.push({ description, quantity, price, total });
        }
    });
    
    // Get totals
    const subtotal = document.getElementById('subtotal').value;
    const taxRate = document.getElementById('tax_rate').value;
    const taxAmount = document.getElementById('tax_amount').value;
    const discountAmount = document.getElementById('discount_amount').value;
    const totalAmount = document.getElementById('total_amount').value;
    
    // Generate invoice number (you might want to get this from server)
    const invoiceNumber = 'INV-' + Date.now();
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Invoice - ${invoiceNumber}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    color: #333; 
                }
                .invoice-header { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 30px; 
                    border-bottom: 2px solid #023B8C; 
                    padding-bottom: 20px; 
                }
                .company-info, .invoice-info { 
                    flex: 1; 
                }
                .invoice-info { 
                    text-align: right; 
                }
                .invoice-title { 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #023B8C; 
                    margin-bottom: 10px; 
                }
                .billing-section { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 30px; 
                }
                .bill-to, .bill-from { 
                    flex: 1; 
                    margin-right: 20px; 
                }
                .section-title { 
                    font-weight: bold; 
                    font-size: 16px; 
                    color: #023B8C; 
                    margin-bottom: 10px; 
                }
                .items-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 30px; 
                }
                .items-table th, .items-table td { 
                    border: 1px solid #ddd; 
                    padding: 12px; 
                    text-align: left; 
                }
                .items-table th { 
                    background-color: #f8f9fa; 
                    font-weight: bold; 
                    color: #023B8C; 
                }
                .items-table .text-right { 
                    text-align: right; 
                }
                .summary-section { 
                    display: flex; 
                    justify-content: flex-end; 
                    margin-bottom: 30px; 
                }
                .summary-table { 
                    width: 300px; 
                }
                .summary-table tr td { 
                    padding: 8px 0; 
                    border-bottom: 1px solid #eee; 
                }
                .summary-table .total-row { 
                    font-weight: bold; 
                    font-size: 18px; 
                    border-top: 2px solid #023B8C; 
                    color: #023B8C; 
                }
                .notes-section { 
                    margin-top: 30px; 
                }
                .notes-title { 
                    font-weight: bold; 
                    margin-bottom: 10px; 
                    color: #023B8C; 
                }
                @media print { 
                    body { margin: 0; } 
                    .no-print { display: none; } 
                }
            </style>
        </head>
        <body>
            <div class="invoice-header">
                <div class="company-info">
                    <div class="invoice-title">INVOICE</div>
                    <div><strong>${companyName}</strong></div>
                    <div>${companyAddress}</div>
                    <div>${companyEmail}</div>
                </div>
                <div class="invoice-info">
                    <div><strong>Invoice #:</strong> ${invoiceNumber}</div>
                    <div><strong>Date:</strong> ${invoiceDate}</div>
                    <div><strong>Due Date:</strong> ${dueDate}</div>
                </div>
            </div>
            
            <div class="billing-section">
                <div class="bill-to">
                    <div class="section-title">Bill To:</div>
                    <div><strong>${clientName}</strong></div>
                    <div>${clientAddress}</div>
                    <div>${clientEmail}</div>
                    ${clientPhone ? `<div>${clientPhone}</div>` : ''}
                </div>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map(item => `
                        <tr>
                            <td>${item.description}</td>
                            <td class="text-right">${item.quantity}</td>
                            <td class="text-right">₹${parseFloat(item.price).toFixed(2)}</td>
                            <td class="text-right">₹${parseFloat(item.total).toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            
            <div class="summary-section">
                <table class="summary-table">
                    <tr>
                        <td>Subtotal:</td>
                        <td class="text-right">₹${parseFloat(subtotal).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td>Tax (${taxRate}%):</td>
                        <td class="text-right">₹${parseFloat(taxAmount).toFixed(2)}</td>
                    </tr>
                    ${parseFloat(discountAmount) > 0 ? `
                    <tr>
                        <td>Discount:</td>
                        <td class="text-right">-₹${parseFloat(discountAmount).toFixed(2)}</td>
                    </tr>
                    ` : ''}
                    <tr class="total-row">
                        <td>Total:</td>
                        <td class="text-right">₹${parseFloat(totalAmount).toFixed(2)}</td>
                    </tr>
                </table>
            </div>
            
            ${notes ? `
            <div class="notes-section">
                <div class="notes-title">Notes:</div>
                <div>${notes.replace(/\n/g, '<br>')}</div>
            </div>
            ` : ''}
            
            <div class="no-print" style="margin-top: 30px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #023B8C; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Invoice</button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Auto-print after a short delay
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

    // Attach listeners to existing rows
    invoiceItemsContainer.querySelectorAll('.grid').forEach(row => {
        attachEventListeners(row);
    });

    // Initial calculation to apply default values
    calculateTotals();

    console.log('Invoice page initialized successfully');
}

// Run initialization when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeInvoicePage();
});
document.addEventListener('DOMContentLoaded', function() {
    const invoiceDateInput = document.getElementById('invoice_date');
    const dueDateInput = document.getElementById('due_date');

    function updateDueDateMin() {
        if (invoiceDateInput && dueDateInput) {
            dueDateInput.min = invoiceDateInput.value;
            // If due date is already before invoice date, reset it
            if (dueDateInput.value < invoiceDateInput.value) {
                dueDateInput.value = invoiceDateInput.value;
            }
        }
    }

    // Run once on page load
    updateDueDateMin();

    // Update when invoice date changes
    if (invoiceDateInput) {
        invoiceDateInput.addEventListener('change', function() {
            updateDueDateMin();
            
            // If the user manually tries to set due date < invoice date (if min doesn't block it on some browsers)
            if (dueDateInput.value && dueDateInput.value < invoiceDateInput.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Date',
                    text: 'Due Date cannot be earlier than Invoice Date.',
                    background: '#1f2937',
                    color: '#fff'
                });
                dueDateInput.value = invoiceDateInput.value;
            }
        });
        
        dueDateInput.addEventListener('change', function() {
            if (this.value < invoiceDateInput.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Date',
                    text: 'Due Date cannot be earlier than Invoice Date.',
                    background: '#1f2937',
                    color: '#fff'
                });
                this.value = invoiceDateInput.value;
            }
        });
    }
});
// Fallback: Retry initialization if DOMContentLoaded doesn't fire
setTimeout(() => {
    if (!document.getElementById('invoice-items').children.length) {
        console.warn('No item rows found after DOMContentLoaded, forcing initialization');
        initializeInvoicePage();
    }
}, 500);


</script>

<?php
if (!$is_ajax) {
    include './layouts/common_layout_end.php';
}
?>
