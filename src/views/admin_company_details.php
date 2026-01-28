<?php
require_once "../../config/config.php";

// Check admin login
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_companies.php");
    exit();
}
$company_id = intval($_GET['id']);

// Fetch Company Info
$sql = "SELECT username, company_name, company_email, company_address, created_at, status 
        FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();

if (!$company) {
    echo "Company not found.";
    exit();
}

// Customers Count
$total_customers = $conn->query("SELECT COUNT(*) AS c FROM clients WHERE user_id=$company_id")->fetch_assoc()['c'];

// Invoices Count
$total_invoices = $conn->query("SELECT COUNT(*) AS c FROM invoices WHERE user_id=$company_id")->fetch_assoc()['c'];

// Products Count
$total_products = $conn->query("SELECT COUNT(*) AS c FROM products WHERE user_id=$company_id")->fetch_assoc()['c'];

// ===== LAST 6 MONTH INVOICES & CUSTOMERS =====
$months = [];
$invoice_data = [];
$customer_data = [];

for ($i = 5; $i >= 0; $i--) {
    $start = date("Y-m-01", strtotime("-$i months"));
    $end = date("Y-m-t", strtotime("-$i months"));
    $months[] = date("M Y", strtotime($start));

    // Invoice count
    $count_inv = $conn->query("SELECT COUNT(*) AS c FROM invoices 
                                WHERE user_id=$company_id 
                                AND invoice_date BETWEEN '$start' AND '$end'")->fetch_assoc()['c'];
    $invoice_data[] = intval($count_inv);

    // Customer count
    $count_cus = $conn->query("SELECT COUNT(*) AS c FROM clients 
                                WHERE user_id=$company_id 
                                AND created_at BETWEEN '$start' AND '$end'")->fetch_assoc()['c'];
    $customer_data[] = intval($count_cus);
}

// Recent invoices
$recent = $conn->query("
    SELECT invoice_number, to_client_name, total_amount, invoice_date 
    FROM invoices 
    WHERE user_id=$company_id 
    ORDER BY created_at DESC 
    LIMIT 8
");

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === "xmlhttprequest";

if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_start.php";
?>

<div class="max-w-6xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">

    <h1 class="text-4xl font-extrabold text-white mb-6 text-center">
        Company Details
    </h1>

    <!-- Company Header -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-8">
        <h2 class="text-3xl font-bold text-blue-300 mb-3">
            <?php echo htmlspecialchars($company['company_name']); ?>
        </h2>

        <p class="text-gray-300 text-lg"><strong>Owner Username:</strong> <?php echo $company['username']; ?></p>
        <p class="text-gray-300 text-lg"><strong>Email:</strong> <?php echo $company['company_email']; ?></p>
        <p class="text-gray-300 text-lg"><strong>Address:</strong> <?php echo $company['company_address']; ?></p>
        <p class="text-gray-300 text-lg"><strong>Joined:</strong> <?php echo date("d M Y", strtotime($company['created_at'])); ?></p>

        <div class="mt-5 flex gap-4">

            <!-- Block/Unblock -->
            <?php if ($company['status'] === 'active'): ?>
                <button onclick="blockCompany(<?php echo $company_id; ?>)"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    Block Company
                </button>
            <?php else: ?>
                <button onclick="unblockCompany(<?php echo $company_id; ?>)"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                    Unblock Company
                </button>
            <?php endif; ?>

            <!-- Reset Password -->
            <button onclick="resetPassword(<?php echo $company_id; ?>)"
                class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg">
                Reset Password
            </button>

        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">

        <div class="bg-gray-800 p-6 rounded-lg text-center border border-blue-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-blue-400 mb-2">Customers</h2>
            <p class="text-4xl font-bold text-blue-300"><?php echo $total_customers; ?></p>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg text-center border border-emerald-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-emerald-400 mb-2">Invoices</h2>
            <p class="text-4xl font-bold text-emerald-300"><?php echo $total_invoices; ?></p>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg text-center border border-purple-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-purple-400 mb-2">Products</h2>
            <p class="text-4xl font-bold text-purple-300"><?php echo $total_products; ?></p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">

        <!-- Invoice Trend -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h3 class="text-2xl font-bold text-green-400 mb-4 text-center">
                Monthly Invoice Trend
            </h3>
            <canvas id="invoiceChart" class="h-80"></canvas>
        </div>

        <!-- Customer Trend -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h3 class="text-2xl font-bold text-purple-400 mb-4 text-center">
                Monthly Customer Growth
            </h3>
            <canvas id="customerChart" class="h-80"></canvas>
        </div>

    </div>

    <!-- Recent Invoices -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
        <h2 class="text-2xl font-bold text-white mb-4">Recent Invoices</h2>

        <table class="min-w-full text-left text-gray-300">
            <thead>
                <tr class="border-b border-gray-700">
                    <th class="py-3 px-4">Invoice #</th>
                    <th class="py-3 px-4">Customer</th>
                    <th class="py-3 px-4">Amount</th>
                    <th class="py-3 px-4">Date</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $recent->fetch_assoc()): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-700/30 transition">
                        <td class="py-3 px-4 text-blue-300"><?php echo $row['invoice_number']; ?></td>
                        <td class="py-3 px-4"><?php echo $row['to_client_name']; ?></td>
                        <td class="py-3 px-4">₹<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td class="py-3 px-4"><?php echo date("d M Y", strtotime($row['invoice_date'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>
</div>

<script src="/invoice_generator/public/js/chart.umd.min.js"></script>

<script>
(function() {
    const labels = <?php echo json_encode($months); ?>;
    const invData = <?php echo json_encode($invoice_data); ?>;
    const cusData = <?php echo json_encode($customer_data); ?>;

    Chart.defaults.color = '#e5e7eb';

    // Invoice Chart
    new Chart(document.getElementById("invoiceChart"), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: "Invoices",
                data: invData,
                borderColor: "rgb(52,211,153)",
                backgroundColor: "rgba(52,211,153,0.2)",
                borderWidth: 3,
                tension: 0.4,
                fill: true
            }]
        }
    });

    // Customer Chart
    new Chart(document.getElementById("customerChart"), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: "New Customers",
                data: cusData,
                borderColor: "rgb(192,132,252)",
                backgroundColor: "rgba(192,132,252,0.25)",
                borderWidth: 2,
                borderRadius: 6
            }]
        }
    });
})();

// ACTIONS
// ACTIONS
function blockCompany(id) {
    Swal.fire({
        title: 'Block Company?',
        text: "They won't be able to log in!",
        icon: 'warning',
        background: '#1f2937',
        color: '#fff',
        showCancelButton: true,
        confirmButtonText: 'Yes, block it!',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("block_company.php?id=" + id).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Blocked',
                    background: '#1f2937',
                    color: '#fff',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => loadPage("admin_company_details.php?id=" + id));
            });
        }
    });
}

function unblockCompany(id) {
    Swal.fire({
        title: 'Unblock Company?',
        text: "Restore access?",
        icon: 'question',
        background: '#1f2937',
        color: '#fff',
        showCancelButton: true,
        confirmButtonText: 'Yes, unblock!',
        confirmButtonColor: '#10b981'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("unblock_company.php?id=" + id).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Unblocked',
                    background: '#1f2937',
                    color: '#fff',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => loadPage("admin_company_details.php?id=" + id));
            });
        }
    });
}

function resetPassword(id) {
    Swal.fire({
        title: 'Reset Password',
        input: 'password',
        inputLabel: 'Enter new password',
        inputPlaceholder: 'New password',
        background: '#1f2937',
        color: '#fff',
        confirmButtonColor: '#d97706',
        showCancelButton: true,
        inputAttributes: { autocapitalize: 'off', minlength: 6 },
        preConfirm: (pass) => {
            if (!pass || pass.length < 6) {
                Swal.showValidationMessage('Password must be at least 6 characters');
            }
            return pass;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("reset_password.php?id=" + id + "&pass=" + result.value)
                .then(response => response.text())
                .then(txt => {
                    if (txt.includes('success')) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'Password has been reset.',
                            background: '#1f2937',
                            color: '#fff'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Could not reset password.',
                            background: '#1f2937',
                            color: '#fff'
                        });
                    }
                });
        }
    });
}
</script>

<?php if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_end.php"; ?>
