<?php
require_once "../../config/config.php";
include __DIR__ . "/layouts/admin_layout_start.php";

// Admin check
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
        die("CSRF validation failed");
    }

    // Delete Action
    if (isset($_POST['delete_id'])) {
        $del_id = (int)$_POST['delete_id'];
        $conn->query("DELETE FROM announcements WHERE id=$del_id");
        $success = "Announcement deleted!";
    }
    // Create Action
    else {
        $msg = $conn->real_escape_string($_POST['message']);
        $type = $conn->real_escape_string($_POST['type']);
        $sql = "INSERT INTO announcements (message, type) VALUES ('$msg', '$type')";
        if ($conn->query($sql)) {
            $success = "Announcement posted!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Fetch Announcements
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
?>

<div class="max-w-4xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">
    <h1 class="text-3xl font-bold mb-6">Manage Announcements</h1>

    <?php if (isset($success)): ?>
        <div class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-3 rounded relative mb-6" role="alert" data-auto-dismiss="true">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline"><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <!-- Create Form -->
    <form method="POST" class="mb-10 bg-gray-800 p-6 rounded-lg border border-gray-700">
        <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
        
        <div class="mb-4">
            <label class="block text-gray-400 mb-2">Announcement Message</label>
            <textarea name="message" rows="3" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white" required></textarea>
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-400 mb-2">Alert Type</label>
            <select name="type" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white">
                <option value="info">Info (Blue)</option>
                <option value="warning">Warning (Yellow)</option>
                <option value="success">Success (Green)</option>
                <option value="danger">Danger (Red)</option>
            </select>
        </div>
        
        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded">Post Announcement</button>
    </form>

    <!-- List -->
    <h2 class="text-xl font-semibold mb-4 text-gray-300">Active Announcements</h2>
    <div class="space-y-4">
        <?php while ($row = $announcements->fetch_assoc()): ?>
            <div class="bg-gray-800 p-4 rounded border-l-4 border-<?php echo ($row['type'] == 'info' ? 'blue' : ($row['type'] == 'warning' ? 'yellow' : ($row['type'] == 'danger' ? 'red' : 'green'))); ?>-500 flex justify-between items-center">
                <div>
                    <p class="font-medium text-white"><?php echo htmlspecialchars($row['message']); ?></p>
                    <span class="text-xs text-gray-500"><?php echo date("M d, H:i", strtotime($row['created_at'])); ?></span>
                </div>
                <form method="POST" onsubmit="confirmDelete(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
                    <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" class="text-red-400 hover:text-red-300">🗑️</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
function confirmDelete(event) {
    event.preventDefault(); // Stop submission
    const form = event.target;
    
    Swal.fire({
        title: 'Delete Announcement?',
        text: "This cannot be undone!",
        icon: 'warning',
        background: '#1f2937', color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}
</script>

<?php include __DIR__ . "/layouts/admin_layout_end.php"; ?>
