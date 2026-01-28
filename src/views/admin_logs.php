<?php
require_once "../../config/config.php";
include __DIR__ . "/layouts/admin_layout_start.php";

// Admin check
if (!isset($_SESSION['admin_loggedin'])) { header("Location: ../../index.php"); exit(); }

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Fetch Logs
// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%$search%";

// Count Total Logs (with search)
if ($search) {
    $stmt_count = $conn->prepare("SELECT COUNT(*) as c FROM audit_logs l LEFT JOIN users u ON l.user_id = u.id WHERE u.username LIKE ?");
    $stmt_count->bind_param("s", $search_param);
    $stmt_count->execute();
    $total_logs = $stmt_count->get_result()->fetch_assoc()['c'];
} else {
    $total_logs = $conn->query("SELECT COUNT(*) as c FROM audit_logs")->fetch_assoc()['c'];
}
$total_pages = ceil($total_logs / $limit);

// Fetch Logs (with search)
if ($search) {
    $stmt = $conn->prepare("
        SELECT l.*, u.username, u.company_name, 
               i.deleted_at as inv_deleted, 
               p.deleted_at as prod_deleted 
        FROM audit_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        LEFT JOIN invoices i ON l.item_id = i.id AND l.item_type = 'invoice'
        LEFT JOIN products p ON l.item_id = p.id AND l.item_type = 'product'
        WHERE u.username LIKE ?
        ORDER BY l.created_at DESC LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $search_param, $limit, $offset);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query("
        SELECT l.*, u.username, u.company_name, 
               i.deleted_at as inv_deleted, 
               p.deleted_at as prod_deleted 
        FROM audit_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        LEFT JOIN invoices i ON l.item_id = i.id AND l.item_type = 'invoice'
        LEFT JOIN products p ON l.item_id = p.id AND l.item_type = 'product'
        ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset
    ");
}
?>

<div class="max-w-7xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Security & Activity Logs</h1>
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by username..." 
                   class="bg-gray-800 border border-gray-700 text-white px-4 py-2 rounded focus:border-blue-500 outline-none">
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded">Search</button>
            <?php if($search): ?>
                <a href="admin_logs.php" class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-2 rounded">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-700 text-gray-300 uppercase font-semibold">
                <tr>
                    <th class="p-4">Time</th>
                    <th class="p-4">User</th>
                    <th class="p-4">Action</th>
                    <th class="p-4">Details</th>
                    <th class="p-4">IP Address</th>
                    <th class="p-4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php while ($row = $logs->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-700/50">
                        <td class="p-4 text-gray-400 whitespace-nowrap"><?php echo date("M d, H:i:s", strtotime($row['created_at'])); ?></td>
                        <td class="p-4 font-medium text-blue-300">
                            <?php echo $row['username'] ? htmlspecialchars($row['username']) : 'System/Guest'; ?>
                        </td>
                        <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($row['action']); ?></td>
                        <td class="p-4 text-gray-300 max-w-xs truncate" title="<?php echo htmlspecialchars($row['details']); ?>">
                            <?php echo htmlspecialchars($row['details']); ?>
                        </td>
                        <td class="p-4 text-gray-500 font-mono"><?php echo htmlspecialchars($row['ip_address']); ?></td>
                        <td class="p-4">
                            <?php if ($row['action'] === 'DELETE_INVOICE' && !empty($row['inv_deleted'])): ?>
                                <a href="#" 
                                   class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-bold"
                                   onclick="confirmRestore(event, 'invoice', '<?php echo $row['item_id']; ?>')">
                                   ♻️ Restore
                                </a>
                            <?php elseif ($row['action'] === 'DELETE_PRODUCT' && !empty($row['prod_deleted'])): ?>
                                <a href="#" 
                                   class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-bold"
                                   onclick="confirmRestore(event, 'product', '<?php echo $row['item_id']; ?>')">
                                   ♻️ Restore
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6 flex justify-center gap-2">
        <?php for($i=1; $i<=$total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1 rounded <?php echo $i==$page ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    </div>
</div>

<script>
function confirmRestore(event, type, id) {
    event.preventDefault();
    Swal.fire({
        title: 'Restore Item?',
        text: `Are you sure you want to restore this ${type}?`,
        icon: 'question',
        background: '#1f2937', color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Yes, Restore'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `../controllers/admin_restore.php?type=${type}&id=${id}`;
        }
    });
}
</script>

<?php include __DIR__ . "/layouts/admin_layout_end.php"; ?>
