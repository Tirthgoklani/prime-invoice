<?php
require_once "../../config/config.php";

if(!isset($_SESSION['admin_loggedin'])) { 
    die("Access Denied"); 
}

$tickets = $conn->query("SELECT t.*, u.company_name, u.company_email FROM tickets t JOIN users u ON t.user_id = u.id ORDER BY FIELD(t.status, 'open', 'answered', 'closed'), t.created_at DESC");

// load the ticket list view
include __DIR__ . '/../views/partials/admin_ticket_list.php';
?>
