<?php
require_once '../../config/config.php';
require '../../includes/phpmailer/src/Exception.php';
require '../../includes/phpmailer/src/PHPMailer.php';
require '../../includes/phpmailer/src/SMTP.php';

// Dompdf for PDF generation
require '../../includes/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../views/manage_invoices.php?error=no_id");
    exit();
}

$invoice_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Fetch invoice data
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $invoice_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();
$stmt->close();

if (!$invoice) {
    header("Location: ../views/manage_invoices.php?error=notfound_or_unauthorized");
    exit();
}

// Fetch invoice items
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$itemsResult = $stmt->get_result();
$items = $itemsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch User Settings (Branding)
$stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

$theme_color = $settings['theme_color'] ?? '#2563eb';
$currency_symbol = $settings['currency_symbol'] ?? '₹';
$company_logo = $settings['company_logo'] ?? null;

// Convert Logo to Base64 for Dompdf if it exists
$logo_html = '';
if ($company_logo && file_exists('../../' . $company_logo)) {
    $logo_data = base64_encode(file_get_contents('../../' . $company_logo));
    $logo_ext = pathinfo($company_logo, PATHINFO_EXTENSION);
    $logo_html = '<img src="data:image/' . $logo_ext . ';base64,' . $logo_data . '" style="height:60px; margin-bottom:10px;">';
}

// --- Generate PDF ---
$dompdf = new Dompdf();

$html = '
    <div style="font-family: \'DejaVu Sans\', sans-serif; color:#1f2937; max-width:800px; margin:auto; padding:20px; border:1px solid #e5e7eb; border-radius:8px; border-top: 6px solid ' . $theme_color . ';">
        <div style="text-align:center; margin-bottom:20px;">
            ' . $logo_html . '
            <h2 style="color:' . $theme_color . '; margin:5px 0;">Invoice #' . htmlspecialchars($invoice['invoice_number']) . '</h2>
        </div>
        <br><br>
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <div>
                <p style="margin:0; font-weight:bold; color:#111827;">From:</p>
                <p style="margin:2px 0;">' . htmlspecialchars($invoice['from_company_name']) . '<br>'
                    . nl2br(htmlspecialchars($invoice['from_address'])) . '<br>'
                    . htmlspecialchars($invoice['from_email']) . '</p>
            </div>
            <br><br>
            <div>
                <p style="margin:0; font-weight:bold; color:#111827;">To:</p>
                <p style="margin:2px 0;">' . htmlspecialchars($invoice['to_client_name']) . '<br>'
                    . nl2br(htmlspecialchars($invoice['to_address'])) . '<br>'
                    . htmlspecialchars($invoice['to_email']) . '<br>'
                    . htmlspecialchars($invoice['to_phone']) . '</p>
            </div>
        </div>

        <p style="margin:10px 0;">
            <strong>Date:</strong> ' . htmlspecialchars($invoice['invoice_date']) . '<br>
            <strong>Due Date:</strong> ' . htmlspecialchars($invoice['due_date']) . '
        </p>

        <table cellspacing="0" cellpadding="8" width="100%" style="border-collapse:collapse; margin:20px 0; font-size:14px;">
            <thead>
                <tr style="background-color:' . $theme_color . '; color:#fff; text-align:left;">
                    <th style="padding:10px; border:1px solid #e5e7eb;">Description</th>
                    <th style="padding:10px; border:1px solid #e5e7eb;">Barcode</th>
                    <th style="padding:10px; border:1px solid #e5e7eb;">Quantity</th>
                    <th style="padding:10px; border:1px solid #e5e7eb;">Unit Price</th>
                    <th style="padding:10px; border:1px solid #e5e7eb;">Total</th>
                </tr>
            </thead>
            <tbody>';
foreach ($items as $item) {
    $html .= '
                <tr style="background-color:#f9fafb; color:#374151;">
                    <td style="border:1px solid #e5e7eb;">' . htmlspecialchars($item['description']) . '</td>
                    <td style="border:1px solid #e5e7eb;">' . htmlspecialchars($item['barcode']) . '</td>
                    <td style="border:1px solid #e5e7eb; text-align:center;">' . intval($item['quantity']) . '</td>
                    <td style="border:1px solid #e5e7eb; text-align:right;">' . $currency_symbol . number_format($item['unit_price'], 2) . '</td>
                    <td style="border:1px solid #e5e7eb; text-align:right;">' . $currency_symbol . number_format($item['total'], 2) . '</td>
                </tr>';
}
$html .= '
            </tbody>
        </table>

        <div style="text-align:right; margin-top:20px; font-size:14px; color:#111827;">
            <p><strong>Subtotal:</strong> ' . $currency_symbol . number_format($invoice['subtotal'], 2) . '</p>
            <p><strong>Tax (' . number_format($invoice['tax_rate'], 2) . '%):</strong> ' . $currency_symbol . number_format($invoice['tax_amount'], 2) . '</p>
            <p><strong>Discount:</strong> ' . $currency_symbol . number_format($invoice['discount_amount'], 2) . '</p>
            <p style="font-size:16px; font-weight:bold; color:' . $theme_color . ';">Total: ' . $currency_symbol . number_format($invoice['total_amount'], 2) . '</p>
        </div>

        <div style="margin-top:30px; padding:10px; background:#f3f4f6; border-left:4px solid ' . $theme_color . '; border-radius:4px;">
            <p style="margin:0; font-size:14px;"><strong>Notes:</strong><br>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>
        </div>
    </div>';


$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdfOutput = $dompdf->output();
$pdfFilePath = sys_get_temp_dir() . "/invoice_" . $invoice['invoice_number'] . ".pdf";
file_put_contents($pdfFilePath, $pdfOutput);

// --- Send Email ---
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = "work.primeinvoice@gmail.com";
    $mail->Password = "hriy ksve jbqo lvvu"; // Gmail App Password
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;

    $mail->setFrom("work.primeinvoice@gmail.com", "Prime Invoice");
    $mail->addAddress($invoice['to_email'], $invoice['to_client_name']);

    $mail->Subject = "Invoice #" . $invoice['invoice_number'];
    $mail->Body = "Dear " . $invoice['to_client_name'] . ",\n\nPlease find attached your invoice.\n\nRegards,\nPrime Invoice Team";
    $mail->addAttachment($pdfFilePath);

    $mail->send();

    header("Location: ../views/manage_invoices.php?status=sent&invoice_id=" . urlencode($invoice['invoice_number']));
    exit();
} catch (Exception $e) {
    header("Location: ../views/manage_invoices.php?error=mail_failed&details=" . urlencode($mail->ErrorInfo));
    exit();
}
