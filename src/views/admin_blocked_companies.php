<?php
require_once "../../config/config.php";

// Admin session check
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Count blocked companies
$total_blocked = $conn->query("SELECT COUNT(*) AS t FROM users WHERE status='blocked'")
                      ->fetch_assoc()['t'];

// Fetch blocked companies
$sql = "
    SELECT id, username, company_name, company_email, created_at 
    FROM users 
    WHERE status='blocked'
    ORDER BY created_at DESC
";
$blockedCompanies = $conn->query($sql);

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_start.php";
?>

<div class="max-w-6xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl">

    <h1 class="text-4xl font-extrabold text-white mb-8 text-center">Blocked Companies</h1>

    <!-- Summary Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-gray-800 p-6 rounded-lg text-center border border-red-500/60 shadow-md">
            <h2 class="text-xl font-semibold text-red-400 mb-2">Total Blocked Companies</h2>
            <p class="text-4xl font-bold text-red-300"><?php echo $total_blocked; ?></p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 overflow-x-auto">

        <table class="min-w-full text-left text-gray-300">
            <thead>
                <tr class="border-b border-gray-700">
                    <th class="py-3 px-4">Company</th>
                    <th class="py-3 px-4">Username</th>
                    <th class="py-3 px-4">Email</th>
                    <th class="py-3 px-4">Blocked Since</th>
                    <th class="py-3 px-4 text-center">Action</th>
                </tr>
            </thead>

            <tbody>

                <?php while ($row = $blockedCompanies->fetch_assoc()): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-700/30 transition">

                        <td class="py-3 px-4 text-red-300">
                            <?php echo htmlspecialchars($row['company_name']); ?>
                        </td>

                        <td class="py-3 px-4"><?php echo htmlspecialchars($row['username']); ?></td>

                        <td class="py-3 px-4"><?php echo htmlspecialchars($row['company_email']); ?></td>

                        <td class="py-3 px-4">
                            <?php echo date("d M Y", strtotime($row['created_at'])); ?>
                        </td>

                        <td class="py-3 px-4 text-center">
                            <button
                                onclick="unblockCompany(<?php echo $row['id']; ?>)"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                                Unblock
                            </button>
                        </td>

                    </tr>
                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<script>
function unblockCompany(id) {
    Swal.fire({
        title: 'Unblock Company?',
        text: "Restore access?",
        icon: 'question',
        background: '#1f2937',
        color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, unblock!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("unblock_company.php?id=" + id)
                .then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Unblocked!',
                        background: '#1f2937',
                        color: '#fff',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => loadPage("admin_blocked_companies.php"));
                });
        }
    });
}
</script>

<?php if (!$is_ajax) include __DIR__ . "/layouts/admin_layout_end.php"; ?>
