<?php
require_once "../../config/config.php";

// Admin session check
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Fetch companies for dropdown filter
$companies = $conn->query("SELECT id, company_name FROM users ORDER BY company_name ASC");

// Default query (no filters)
$where = "1=1";

// Apply filters
if (isset($_GET['search']) && $_GET['search'] !== "") {
    $s = $conn->real_escape_string($_GET['search']);
    $where .= " AND (clients.client_name LIKE '%$s%' OR clients.client_email LIKE '%$s%')";
}

if (isset($_GET['company']) && $_GET['company'] !== "all") {
    $cid = intval($_GET['company']);
    $where .= " AND clients.user_id=$cid";
}

if (isset($_GET['from']) && isset($_GET['to']) && $_GET['from'] !== "" && $_GET['to'] !== "") {
    $f = $_GET['from'];
    $t = $_GET['to'];
    $where .= " AND DATE(clients.created_at) BETWEEN '$f' AND '$t'";
}

// Fetch customers with filters
$sql = "
    SELECT clients.*, users.company_name 
    FROM clients 
    JOIN users ON users.id = clients.user_id
    WHERE $where
    ORDER BY clients.created_at DESC
";

$customers = $conn->query($sql);

// Count totals
$total_customers = $conn->query("SELECT COUNT(*) AS c FROM clients")->fetch_assoc()['c'];

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_start.php";
?>

<div class="max-w-7xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl">

    <h1 class="text-4xl font-extrabold text-white mb-8 text-center">All Customers</h1>

    <!-- Summary Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-gray-800 p-6 rounded-lg text-center border border-purple-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-purple-400 mb-2">Total Customers</h2>
            <p class="text-4xl font-bold text-purple-300"><?php echo $total_customers; ?></p>
        </div>
    </div>

    <!-- Collapsible Filter Panel -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg mb-6">
        
        <button class="w-full text-left px-6 py-4 bg-gray-800 hover:bg-gray-700 transition flex justify-between items-center"
                onclick="toggleFilterPanel()">
            <span class="text-xl font-semibold text-blue-300">Filters</span>
            <span id="filter-arrow" class="text-gray-300 text-2xl">▼</span>
        </button>

        <div id="filter-panel" class="hidden px-6 py-6 border-t border-gray-700">

            <form id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                    <!-- Search -->
                    <div>
                        <label class="block text-gray-300 mb-1">Search Customer</label>
                        <input type="text" name="search"
                               class="w-full px-3 py-2 rounded bg-gray-700 text-gray-200 border border-gray-600"
                               placeholder="Name, Email">
                    </div>

                    <!-- Company Dropdown -->
                    <div>
                        <label class="block text-gray-300 mb-1">Company</label>
                        <select name="company"
                                class="w-full px-3 py-2 rounded bg-gray-700 text-gray-200 border border-gray-600">
                            <option value="all">All Companies</option>
                            <?php while ($c = $companies->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo $c['company_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-gray-300 mb-1">From</label>
                        <input type="date" name="from"
                               class="w-full px-3 py-2 rounded bg-gray-700 text-gray-200 border border-gray-600">
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-gray-300 mb-1">To</label>
                        <input type="date" name="to"
                               class="w-full px-3 py-2 rounded bg-gray-700 text-gray-200 border border-gray-600">
                    </div>

                </div>

                <div class="mt-6 text-right">
                    <button type="button" onclick="applyCustomerFilters()"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        Apply Filters
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 overflow-x-auto">

        <table class="min-w-full text-left text-gray-300">
            <thead>
                <tr class="border-b border-gray-700">
                    <th class="py-3 px-4">Customer</th>
                    <th class="py-3 px-4">Email</th>
                    <th class="py-3 px-4">Phone</th>
                    <th class="py-3 px-4">Company</th>
                    <th class="py-3 px-4">Added</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $customers->fetch_assoc()): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-700/30 transition">
                        <td class="py-3 px-4"><?php echo $row['client_name']; ?></td>
                        <td class="py-3 px-4"><?php echo $row['client_email']; ?></td>
                        <td class="py-3 px-4"><?php echo $row['client_phone']; ?></td>
                        <td class="py-3 px-4 text-blue-300"><?php echo $row['company_name']; ?></td>
                        <td class="py-3 px-4"><?php echo date("d M Y", strtotime($row['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>

        </table>

    </div>

</div>


<script>
// Toggle collapsible panel
function toggleFilterPanel() {
    const panel = document.getElementById("filter-panel");
    const arrow = document.getElementById("filter-arrow");

    panel.classList.toggle("hidden");
    arrow.textContent = panel.classList.contains("hidden") ? "▼" : "▲";
}

// Apply filters via AJAX
function applyCustomerFilters() {
    let form = document.getElementById("filterForm");
    let data = new FormData(form);
    let params = new URLSearchParams(data).toString();

    loadPage("admin_customers.php?" + params);
}
</script>

<?php if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_end.php"; ?>
