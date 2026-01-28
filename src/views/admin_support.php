<?php
require_once "../../config/config.php";
include __DIR__ . "/layouts/admin_layout_start.php";

// Admin check
if (!isset($_SESSION['admin_loggedin'])) { header("Location: ../../index.php"); exit(); }

// Handle Status Change or Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply_message'])) {
        $tid = (int)$_POST['ticket_id'];
        
        // Check if ticket is closed
        $check = $conn->query("SELECT status FROM tickets WHERE id=$tid");
        if ($check && $check->fetch_assoc()['status'] === 'closed') {
             // Stop processing if closed
             // We can optionally set a flash message here or just exit
             header("Location: admin_support.php");
             exit();
        }

        $msg = $conn->real_escape_string($_POST['reply_message']);
        
        $conn->query("INSERT INTO ticket_replies (ticket_id, sender_type, message) VALUES ($tid, 'admin', '$msg')");
        $conn->query("UPDATE tickets SET status='answered' WHERE id=$tid");
    }
    elseif (isset($_POST['close_ticket'])) {
        $tid = (int)$_POST['ticket_id'];
        $conn->query("UPDATE tickets SET status='closed' WHERE id=$tid");
    }
}

// Fetch all tickets
$tickets = $conn->query("SELECT t.*, u.company_name, u.company_email FROM tickets t JOIN users u ON t.user_id = u.id ORDER BY FIELD(t.status, 'open', 'answered', 'closed'), t.created_at DESC");
?>

<div class="max-w-6xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">
    <h1 class="text-3xl font-bold mb-6">Support Helpdesk</h1>

    <div id="tickets-container" class="space-y-4">
        <?php include __DIR__ . '/partials/admin_ticket_list.php'; ?>
    </div>
</div>

<script>
let openTicketId = null;

function toggleTicket(id) {
    // If clicking same ticket, toggle it. If clicking different, close others? No, just toggle.
    const el = document.getElementById('ticket-' + id);
    if (!el.classList.contains('hidden')) {
        el.classList.add('hidden');
        if (openTicketId === id) openTicketId = null;
    } else {
        el.classList.remove('hidden');
        openTicketId = id;
    }
}

// Poll for updates every 5 seconds
setInterval(function() {
    // Only poll if window is focused to save resources? Or just poll.
    fetch('../../src/controllers/admin_get_tickets.php')
        .then(response => response.text())
        .then(html => {
            const container = document.getElementById('tickets-container');
            
            // Basic DOM swap. Note: This destroys user's partial input if they are typing a reply!
            // We should check if user is typing. 
            const activeInput = document.activeElement;
            const isTyping = activeInput && (activeInput.tagName === 'INPUT' || activeInput.tagName === 'TEXTAREA');
            
            if (!isTyping) {
                container.innerHTML = html;
                
                // Restore Open State
                if (openTicketId) {
                    const el = document.getElementById('ticket-' + openTicketId);
                    if (el) el.classList.remove('hidden');
                }
            } else {
                console.log("User is typing, skipping update.");
            }
        });
}, 5000);
</script>

<?php include __DIR__ . "/layouts/admin_layout_end.php"; ?>
