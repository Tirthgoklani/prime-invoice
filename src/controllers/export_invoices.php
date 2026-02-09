<?php
// Disable error display for production (errors are logged instead)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../../config/config.php';

// Dompdf for PDF generation - only load if file exists
$dompdf_available = file_exists('../../includes/dompdf/vendor/autoload.php');
if ($dompdf_available) {
    require '../../includes/dompdf/vendor/autoload.php';
}

// Use statements must be at top level (outside conditional)
if ($dompdf_available) {
    // Dompdf classes will be available after autoload
}

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../views/export_data.php?error=invalid_request");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !Csrf::validateToken($_POST['csrf_token'])) {
    header("Location: ../views/export_data.php?error=invalid_token");
    exit();
}

$user_id = $_SESSION['user_id'];
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
$format = isset($_POST['format']) ? $_POST['format'] : 'excel';

// Validate dates
if (empty($start_date) || empty($end_date)) {
    header("Location: ../views/export_data.php?error=missing_dates");
    exit();
}

// Fetch invoices within date range with error handling
try {
    $sql = "SELECT * FROM invoices 
            WHERE user_id = ? 
            AND deleted_at IS NULL 
            AND invoice_date BETWEEN ? AND ?
            ORDER BY invoice_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Export Error - Prepare failed: " . $conn->error);
        header("Location: ../views/export_data.php?error=database_error");
        exit();
    }
    
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoices = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Check if any invoices found
    if (empty($invoices)) {
        header("Location: ../views/export_data.php?error=no_invoices&start_date=$start_date&end_date=$end_date");
        exit();
    }
} catch (Exception $e) {
    error_log("Export Error - Database query failed: " . $e->getMessage());
    header("Location: ../views/export_data.php?error=database_error");
    exit();
}

// Get company details from users table (not user_settings)
$stmt = $conn->prepare("SELECT company_name, company_email, company_address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();
$stmt->close();

$company_name = $settings['company_name'] ?? 'Your Company';
$company_email = $settings['company_email'] ?? '';
$company_address = $settings['company_address'] ?? '';

if ($format === 'excel') {
    // Export as CSV (Excel-compatible)
    try {
        $filename = "invoices_" . $start_date . "_to_" . $end_date . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Company header
        fputcsv($output, [$company_name]);
        fputcsv($output, ['Invoice Export Report']);
        fputcsv($output, ['Date Range: ' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
        fputcsv($output, []); // Empty row
        
        // Column headers
        fputcsv($output, [
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Client Name',
            'Client Email',
            'Subtotal',
            'Tax Rate (%)',
            'Tax Amount',
            'Discount',
            'Total Amount',
            'Status',
            'Created At'
        ]);
        
        // Data rows
        $total_sum = 0;
        foreach ($invoices as $invoice) {
            fputcsv($output, [
                $invoice['invoice_number'] ?? '',
                date('M d, Y', strtotime($invoice['invoice_date'] ?? 'now')),
                date('M d, Y', strtotime($invoice['due_date'] ?? 'now')),
                $invoice['to_client_name'] ?? '',
                $invoice['to_email'] ?? '',
                '₹' . number_format($invoice['subtotal'] ?? 0, 2),
                number_format($invoice['tax_rate'] ?? 0, 2),
                '₹' . number_format($invoice['tax_amount'] ?? 0, 2),
                '₹' . number_format($invoice['discount_amount'] ?? 0, 2),
                '₹' . number_format($invoice['total_amount'] ?? 0, 2),
                ucfirst($invoice['status'] ?? 'pending'),
                date('M d, Y H:i', strtotime($invoice['created_at'] ?? 'now'))
            ]);
            $total_sum += $invoice['total_amount'];
        }
        
        // Summary row
        fputcsv($output, []); // Empty row
        fputcsv($output, ['', '', '', '', '', '', '', '', 'TOTAL:', '₹' . number_format($total_sum, 2)]);
        fputcsv($output, ['', '', '', '', '', '', '', '', 'Count:', count($invoices) . ' invoices']);
        
        fclose($output);
        exit();
    } catch (Exception $e) {
        error_log("Export Error - CSV generation failed: " . $e->getMessage());
        header("Location: ../views/export_data.php?error=export_failed");
        exit();
    }
    
} elseif ($format === 'pdf') {
    // Check if dompdf is available
    if (!$dompdf_available) {
        error_log("Export Error - Dompdf library not found on server");
        header("Location: ../views/export_data.php?error=pdf_library_missing");
        exit();
    }
    
    // Export as PDF
    try {
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new \Dompdf\Dompdf($options);
        
        // Build HTML for PDF
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    color: #333;
                    margin: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 3px solid #023B8C;
                    padding-bottom: 15px;
                }
                .company-name {
                    font-size: 28px;
                    font-weight: bold;
                    color: #023B8C;
                    margin-bottom: 5px;
                }
                .report-title {
                    font-size: 20px;
                    color: #666;
                    margin-bottom: 5px;
                }
                .date-range {
                    font-size: 14px;
                    color: #888;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    font-size: 11px;
                }
                th {
                    background-color: #023B8C;
                    color: white;
                    padding: 10px 8px;
                    text-align: left;
                    font-weight: bold;
                }
                td {
                    padding: 8px;
                    border-bottom: 1px solid #ddd;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .text-right {
                    text-align: right;
                }
                .summary {
                    margin-top: 20px;
                    padding: 15px;
                    background-color: #f0f0f0;
                    border-radius: 5px;
                }
                .summary-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    font-size: 14px;
                }
                .summary-label {
                    font-weight: bold;
                }
                .total-row {
                    font-size: 18px;
                    font-weight: bold;
                    color: #023B8C;
                    border-top: 2px solid #023B8C;
                    padding-top: 10px;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">' . htmlspecialchars($company_name) . '</div>
                <div class="report-title">Invoice Export Report</div>
                <div class="date-range">
                    ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Tax</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';
        
        $total_sum = 0;
        $total_tax = 0;
        $total_discount = 0;
        
        foreach ($invoices as $invoice) {
            $status_color = $invoice['status'] === 'paid' ? '#10b981' : ($invoice['status'] === 'pending' ? '#f59e0b' : '#ef4444');
            
            $html .= '<tr>
                <td>' . htmlspecialchars($invoice['invoice_number']) . '</td>
                <td>' . date('M d, Y', strtotime($invoice['invoice_date'])) . '</td>
                <td>' . htmlspecialchars($invoice['to_client_name']) . '</td>
                <td>' . htmlspecialchars($invoice['to_email']) . '</td>
                <td class="text-right">₹' . number_format($invoice['subtotal'], 2) . '</td>
                <td class="text-right">₹' . number_format($invoice['tax_amount'], 2) . '</td>
                <td class="text-right">₹' . number_format($invoice['discount_amount'], 2) . '</td>
                <td class="text-right">₹' . number_format($invoice['total_amount'], 2) . '</td>
                <td><span style="color: ' . $status_color . ';">' . ucfirst($invoice['status']) . '</span></td>
            </tr>';
            
            $total_sum += $invoice['total_amount'];
            $total_tax += $invoice['tax_amount'];
            $total_discount += $invoice['discount_amount'];
        }
        
        $html .= '</tbody>
            </table>
            
            <div class="summary">
                <div class="summary-row">
                    <span class="summary-label">Total Invoices:</span>
                    <span>' . count($invoices) . '</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Total Tax Collected:</span>
                    <span>₹' . number_format($total_tax, 2) . '</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Total Discounts:</span>
                    <span>₹' . number_format($total_discount, 2) . '</span>
                </div>
                <div class="summary-row total-row">
                    <span class="summary-label">Grand Total:</span>
                    <span>₹' . number_format($total_sum, 2) . '</span>
                </div>
            </div>
            
            <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #999;">
                Generated on ' . date('F d, Y \a\t H:i:s') . ' | ' . htmlspecialchars($company_name) . '
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        $filename = "invoices_" . $start_date . "_to_" . $end_date . ".pdf";
        $dompdf->stream($filename, array("Attachment" => true));
        exit();
    } catch (Exception $e) {
        error_log("Export Error - PDF generation failed: " . $e->getMessage());
        header("Location: ../views/export_data.php?error=pdf_failed");
        exit();
    }
}

$conn->close();
?>
