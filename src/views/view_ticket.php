<?php
require_once "../../config/config.php";

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ticket_id = (int)($_GET['id'] ?? 0);

// Fetch Ticket & Verify Ownership
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $ticket_id, $user_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    die("Ticket not found or access denied.");
}

// Handle Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) { die("CSRF Error"); }
    
    // Prevent reply if closed (double check)
    if ($ticket['status'] === 'closed') {
        die("Cannot reply to a closed ticket.");
    }
    
    $msg = $conn->real_escape_string($_POST['message']);
    $conn->query("INSERT INTO ticket_replies (ticket_id, sender_type, message) VALUES ($ticket_id, 'user', '$msg')");
    
    // Re-open ticket if closed
    $conn->query("UPDATE tickets SET status='open' WHERE id=$ticket_id");
    
    header("Location: view_ticket.php?id=$ticket_id");
    exit();
}

// Fetch Replies
$replies = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id=$ticket_id ORDER BY created_at ASC");

include __DIR__ . '/layouts/common_layout_start.php';
?>

<div class="max-w-4xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">
    <div class="flex justify-between items-start mb-6 border-b border-gray-700 pb-4">
        <div>
            <a href="support.php" class="text-blue-400 text-sm mb-2 inline-block">← Back to Tickets</a>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                Ticket #<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?>
                <span class="text-xs px-2 py-1 rounded bg-gray-700 uppercase tracking-widest"><?php echo $ticket['status']; ?></span>
            </h1>
        </div>
    </div>

    <!-- Conversation -->
    <div id="replies-container" class="space-y-6 mb-8">
        <?php include __DIR__ . '/partials/ticket_replies_list.php'; ?>
    </div>

<script>
// Poll for new replies every 5 seconds
setInterval(function() {
    const ticketId = <?php echo $ticket_id; ?>;
    fetch('../../src/controllers/get_ticket_replies.php?ticket_id=' + ticketId)
        .then(response => response.text())
        .then(html => {
            const container = document.getElementById('replies-container');
            // Only update if difference? Or just swap. Swapping is easiest.
            // Check if user is scrolling up? If so, don't snap to bottom?
            // For now, simple swap.
            container.innerHTML = html;
        });
}, 5000);
</script>

    <!-- Reply Form -->
    <!-- Reply Form -->
    <?php if ($ticket['status'] !== 'closed'): ?>
    <form method="POST" class="bg-gray-800 p-4 rounded border border-gray-700">
        <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
        <label class="block text-gray-400 mb-2 font-semibold">Post a Reply</label>
        <textarea name="message" rows="3" class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-white focus:border-blue-500 outline-none" required placeholder="Type your reply here..."></textarea>
        <div class="mt-3 text-right">
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded">Send Reply</button>
        </div>
    </form>
    <?php else: ?>
        <div class="bg-red-900/20 border border-red-500/50 p-4 rounded text-center">
            <p class="text-red-300 font-semibold">🔒 This ticket is closed. You can no longer reply.</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layouts/common_layout_end.php'; ?>
