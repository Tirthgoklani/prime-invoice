<?php
// src/controllers/admin_get_tickets.php
require_once "../../config/config.php";

// Admin check
// Admin check
if (!isset($_SESSION['admin_loggedin'])) { die("Access Denied"); }

// Fetch Query (Shared Logic)
$tickets = $conn->query("SELECT t.*, u.company_name, u.company_email FROM tickets t JOIN users u ON t.user_id = u.id ORDER BY FIELD(t.status, 'open', 'answered', 'closed'), t.created_at DESC");

// Include the view partial
include __DIR__ . '/../views/partials/admin_ticket_list.php';
?>
