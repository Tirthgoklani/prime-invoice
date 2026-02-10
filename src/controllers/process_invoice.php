<?php
require_once '../../config/config.php';

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
        header("Location: ../views/create_invoice.php?error=invalid_token");
        exit();
    }

    $invoice_date = sanitize_input($_POST['invoice_date']);
    $due_date = sanitize_input($_POST['due_date']);
    $from_company_name = $_SESSION['company_name'];
    $from_address = $_SESSION['company_address'];
    $from_email = $_SESSION['company_email'];
    $user_id = $_SESSION['user_id'];
    $selected_client_id = isset($_POST['selected_client_id']) ? intval($_POST['selected_client_id']) : 0;
    $input_client_name = sanitize_input($_POST['to_client_name']);
    $input_client_address = sanitize_input($_POST['to_address']);
    $input_client_email = sanitize_input($_POST['to_email']);
    $input_client_phone = sanitize_input($_POST['to_phone']);
    $subtotal = floatval(sanitize_input($_POST['subtotal']));
    $tax_rate = floatval(sanitize_input($_POST['tax_rate']));
    $tax_amount = floatval(sanitize_input($_POST['tax_amount']));
    $discount_amount = floatval(sanitize_input($_POST['discount_amount']));
    $total_amount = floatval(sanitize_input($_POST['total_amount']));
    $notes = sanitize_input($_POST['notes']);

    $item_barcodes = $_POST['item_barcode'] ?? [];
    $item_descriptions = $_POST['item_description'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];
    $item_prices = $_POST['item_price'] ?? [];
    $item_totals = $_POST['item_total'] ?? [];

    if(empty($invoice_date) || empty($due_date) || empty($input_client_name) || empty($input_client_address) || empty($input_client_email)) {
        header("Location: ../views/create_invoice.php?error=missing_fields");
        exit();
    }

    if(empty($item_descriptions) || count($item_descriptions) !== count($item_quantities) || count($item_quantities) !== count($item_prices)) {
        header("Location: ../views/create_invoice.php?error=invalid_items");
        exit();
    }

    // check due date
    if(strtotime($due_date) < strtotime($invoice_date)) {
        header("Location: ../views/create_invoice.php?error=invalid_date");
        exit();
    }

    // handle client
    $client_id = null;
    if($selected_client_id > 0) {
        $sql_check_client = "SELECT id FROM clients WHERE id = ? AND user_id = ?";
        $stmt_check_client = $conn->prepare($sql_check_client);
        $stmt_check_client->bind_param("ii", $selected_client_id, $user_id);
        $stmt_check_client->execute();
        $result_check_client = $stmt_check_client->get_result();
        if($result_check_client->num_rows > 0) {
            $client_id = $selected_client_id;
        }
        $stmt_check_client->close();
    }

    if(!$client_id) {
        $sql_check_email = "SELECT id FROM clients WHERE user_id = ? AND client_email = ?";
        $stmt_check_email = $conn->prepare($sql_check_email);
        $stmt_check_email->bind_param("is", $user_id, $input_client_email);
        $stmt_check_email->execute();
        $result_email = $stmt_check_email->get_result();
        
        if($result_email->num_rows > 0) {
            $client_id = $result_email->fetch_assoc()['id'];
        } else {
            $sql_insert_client = "INSERT INTO clients (user_id, client_name, client_address, client_email, client_phone) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert_client = $conn->prepare($sql_insert_client);
            $stmt_insert_client->bind_param("issss", $user_id, $input_client_name, $input_client_address, $input_client_email, $input_client_phone);
            $stmt_insert_client->execute();
            $client_id = $conn->insert_id;
            $stmt_insert_client->close();
        }
        $stmt_check_email->close();
    }

    $conn->begin_transaction();
    try {
        $invoice_number = "INV-" . date("YmdHis") . "-" . uniqid();

        $sql_invoice = "INSERT INTO invoices (
                            user_id, client_id, invoice_number, invoice_date, due_date,
                            from_company_name, from_address, from_email,
                            to_client_name, to_address, to_email, to_phone,
                            subtotal, tax_rate, tax_amount, discount_amount, total_amount, notes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_invoice = $conn->prepare($sql_invoice);
        if($stmt_invoice === false) {
            throw new Exception("Error preparing invoice statement: " . $conn->error);
        }
        $stmt_invoice->bind_param("iissssssssssddddds", 
            $user_id, $client_id, $invoice_number, $invoice_date, $due_date,
            $from_company_name, $from_address, $from_email,
            $input_client_name, $input_client_address, $input_client_email, $input_client_phone,
            $subtotal, $tax_rate, $tax_amount, $discount_amount, $total_amount, $notes
        );
        $stmt_invoice->execute();
        $invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        $sql_item = "INSERT INTO invoice_items (
                        invoice_id, barcode, description, quantity, unit_price, total
                    ) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);
        if($stmt_item === false) {
            throw new Exception("Error preparing item statement: " . $conn->error);
        }

        for($i = 0; $i < count($item_descriptions); $i++) {
            $barcode = sanitize_input($item_barcodes[$i] ?? '');
            $description = sanitize_input($item_descriptions[$i]);
            $quantity = floatval(sanitize_input($item_quantities[$i]));
            $unit_price = floatval(sanitize_input($item_prices[$i]));
            $total = floatval(sanitize_input($item_totals[$i]));
            $stmt_item->bind_param("issddd", $invoice_id, $barcode, $description, $quantity, $unit_price, $total);
            $stmt_item->execute();
        }
        $stmt_item->close();

        $conn->commit();
        
        log_activity($user_id, 'CREATE_INVOICE', "Created invoice #$invoice_number", 'invoice', $invoice_id);
        
        header("Location: ../views/dashboard.php?status=success&invoice_id=" . $invoice_id);
        exit();
        
    } catch(Exception $e) {
        $conn->rollback();
        header("Location: ../views/create_invoice.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../views/create_invoice.php?error=invalid_request");
}

$conn->close();
?>