<?php
require_once "../../config/config.php";

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle New Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
        die("CSRF Error");
    }
    
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']); // Initial message
    
    // Create Ticket
    $conn->query("INSERT INTO tickets (user_id, subject, status) VALUES ($user_id, '$subject', 'open')");
    $ticket_id = $conn->insert_id;
    
    // Add Initial Message as Reply
    $conn->query("INSERT INTO ticket_replies (ticket_id, sender_type, message) VALUES ($ticket_id, 'user', '$message')");
    
    $success = "Ticket created successfully!";
}

// Fetch Tickets
$tickets = $conn->query("SELECT * FROM tickets WHERE user_id=$user_id ORDER BY created_at DESC");

// Determine if AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$is_ajax) include __DIR__ . '/layouts/common_layout_start.php';
?>

<div class="max-w-6xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-extrabold text-white">Support Tickets</h1>
        <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg">
            + New Ticket
        </button>
    </div>

    <?php if (isset($success)): ?>
        <div class="bg-green-500/20 text-green-300 p-3 rounded mb-4 border border-green-500/50"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Ticket List -->
    <div class="bg-gray-800 rounded-lg overflow-hidden border border-gray-700">
        <table class="w-full text-left">
            <thead class="bg-gray-700 text-gray-300">
                <tr>
                    <th class="p-4">Subject</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Created</th>
                    <th class="p-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php if ($tickets->num_rows > 0): ?>
                    <?php while ($row = $tickets->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-700/50">
                            <td class="p-4 font-medium"><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold uppercase
                                    <?php echo $row['status'] === 'open' ? 'bg-green-500/20 text-green-400' : ($row['status'] === 'answered' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-gray-500/20 text-gray-400'); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-400 text-sm"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                            <td class="p-4 text-right">
                                <a href="view_ticket.php?id=<?php echo $row['id']; ?>" class="text-blue-400 hover:text-blue-300">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="p-6 text-center text-gray-500">No tickets found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Ticket Modal -->
<div id="newTicketModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-gray-800 p-8 rounded-lg shadow-2xl w-full max-w-lg border border-gray-700 relative">
        <button onclick="document.getElementById('newTicketModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white">✕</button>
        <h2 class="text-2xl font-bold mb-6 text-white">Create Support Ticket</h2>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
            <input type="hidden" name="create_ticket" value="1">
            
            <div class="mb-4">
                <label class="block text-gray-400 mb-2">Subject</label>
                <input type="text" name="subject" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white" required placeholder="Brief summary of issue">
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-400 mb-2">Message</label>
                <textarea name="message" rows="5" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white" required placeholder="Describe your problem..."></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('newTicketModal').classList.add('hidden')" class="px-4 py-2 text-gray-300 hover:text-white">Cancel</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded">Submit Ticket</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$is_ajax) include __DIR__ . '/layouts/common_layout_end.php'; ?>
