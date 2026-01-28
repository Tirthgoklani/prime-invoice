<?php
// src/controllers/get_ticket_replies.php
require_once "../../config/config.php";


// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$ticket_id = (int)($_GET['ticket_id'] ?? 0);

// Validate ownership
$check = $conn->query("SELECT id FROM tickets WHERE id=$ticket_id AND user_id=$user_id");
if ($check->num_rows === 0) {
    die("Ticket not found");
}

// Fetch Replies
$replies = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id=$ticket_id ORDER BY created_at ASC");

// Include Partial
include __DIR__ . '/../views/partials/ticket_replies_list.php';
?>
