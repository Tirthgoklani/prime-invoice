<?php
require_once "../../config/config.php";

// Admin session check
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

// ===== Summary Stats =====
$total_companies = $conn->query("SELECT COUNT(*) AS t FROM users")->fetch_assoc()['t'];
$active_companies = $conn->query("SELECT COUNT(*) AS t FROM users WHERE status='active'")->fetch_assoc()['t'];
$blocked_companies = $conn->query("SELECT COUNT(*) AS t FROM users WHERE status='blocked'")->fetch_assoc()['t'];

// ===== Fetch All Companies =====
$companies = $conn->query("
    SELECT 
        id,
        username,
        company_name,
        company_email,
        created_at,
        status
    FROM users
    ORDER BY created_at DESC
");

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_start.php";
?>



<div class="max-w-7xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">

    <h1 class="text-4xl font-extrabold text-white mb-8 text-center">All Companies</h1>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-gray-800 p-6 rounded-lg text-center border border-blue-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-blue-400 mb-2">Total Companies</h2>
            <p class="text-4xl font-bold text-blue-300"><?php echo $total_companies; ?></p>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg text-center border border-emerald-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-emerald-400 mb-2">Active</h2>
            <p class="text-4xl font-bold text-emerald-300"><?php echo $active_companies; ?></p>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg text-center border border-red-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-red-400 mb-2">Blocked</h2>
            <p class="text-4xl font-bold text-red-300"><?php echo $blocked_companies; ?></p>
        </div>
    </div>


    <!-- Companies Table -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 overflow-x-auto">

        <h2 class="text-2xl font-bold text-white mb-4">Registered Companies</h2>

        <table class="min-w-full text-left text-gray-300">
            <thead>
                <tr class="border-b border-gray-700">
                    <th class="py-3 px-4">Company</th>
                    <th class="py-3 px-4">Username</th>
                    <th class="py-3 px-4">Email</th>
                    <th class="py-3 px-4">Created</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-center">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $companies->fetch_assoc()): ?>
                    <?php
                    // Details
                    $cid = $row['id'];
                    $count_clients = $conn->query("SELECT COUNT(*) AS c FROM clients WHERE user_id=$cid")->fetch_assoc()['c'];
                    $count_invoices = $conn->query("SELECT COUNT(*) AS c FROM invoices WHERE user_id=$cid")->fetch_assoc()['c'];
                    ?>

                    <tr class="border-b border-gray-700 hover:bg-gray-700/30 transition">
                        <td class="py-3 px-4">
                            <div class="font-semibold text-blue-300"><?php echo htmlspecialchars($row['company_name']); ?></div>
                            <div class="text-gray-400 text-sm">
                                Customers: <?php echo $count_clients; ?> |
                                Invoices: <?php echo $count_invoices; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4"><?php echo htmlspecialchars($row['username']); ?></td>
                        <td class="py-3 px-4"><?php echo htmlspecialchars($row['company_email']); ?></td>
                        <td class="py-3 px-4"><?php echo date("d M Y", strtotime($row['created_at'])); ?></td>

                        <td class="py-3 px-4">
                            <?php if ($row['status'] === 'active'): ?>
                                <span class="px-3 py-1 bg-emerald-600/40 text-emerald-300 rounded-full text-sm">Active</span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-red-600/40 text-red-300 rounded-full text-sm">Blocked</span>
                            <?php endif; ?>
                        </td>

                        <td class="py-3 px-4 text-center">
                            <!-- VIEW DETAILS -->
                            <button onclick="loadPage('admin_company_details.php?id=<?php echo $cid; ?>')" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm mr-2">View</button>

                            <!-- BLOCK / UNBLOCK -->
                            <?php if ($row['status'] === 'active'): ?>
                                <button onclick="blockCompany(<?php echo $cid; ?>)" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm">Block</button>
                            <?php else: ?>
                                <button onclick="unblockCompany(<?php echo $cid; ?>)" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm">Unblock</button>
                            <?php endif; ?>
                            
                            <!-- IMPERSONATE -->
                            <a href="#" onclick="confirmImpersonate(event, <?php echo $cid; ?>)" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-md text-sm ml-2">Login As</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function blockCompany(id) {
    Swal.fire({
        title: 'Block Company?',
        text: "They won't be able to log in!",
        icon: 'warning',
        background: '#1f2937', color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, block it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("block_company.php?id=" + id)
                .then(() => {
                    Swal.fire({ title: 'Blocked!', icon: 'success', background: '#1f2937', color: '#fff', timer: 1000, showConfirmButton: false })
                    .then(() => loadPage("admin_companies.php"));
                });
        }
    });
}
function unblockCompany(id) {
    Swal.fire({
        title: 'Unblock Company?', text: "Restore access?", icon: 'question',
        background: '#1f2937', color: '#fff',
        showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: 'Yes, unblock!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("unblock_company.php?id=" + id)
                .then(() => {
                    Swal.fire({ title: 'Unblocked!', icon: 'success', background: '#1f2937', color: '#fff', timer: 1000, showConfirmButton: false })
                    .then(() => loadPage("admin_companies.php"));
                });
        }
    });
}
function confirmImpersonate(event, id) {
    event.preventDefault();
    Swal.fire({
        title: 'Impersonate User?',
        text: "You will be logged in as this company.",
        icon: 'info',
        background: '#1f2937', color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#9333ea',
        confirmButtonText: 'Yes, Login As'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "../controllers/admin_impersonate.php?user_id=" + id;
        }
    });
}
</script>

<?php if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_end.php"; ?>
