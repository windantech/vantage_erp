<?php
/**
 * International Invoice Generator with Email Logging
 *
 * Complete replacement for invoice_international_.php
 * Includes all original functionality plus email logging
 *
 * UPDATED: admission email now supports per-event custom templates for ALL events.
 *   The template is looked up dynamically from system_emails1 by event_id:
 *     SELECT * FROM system_emails1 WHERE event_id = $eid AND email_opt = 1 ORDER BY id DESC LIMIT 1
 *   If a matching template exists it REPLACES the default admission email body.
 *   If no template exists for the event, the existing default body is used.
 *   The body column is auto-detected: JSON-encoded string OR plain HTML both work.
 */

/**
 * TEMPORARY DIAGNOSTIC — remove once the academic-email failure is diagnosed.
 *
 * Writes a step-by-step trace of the admission/invoice email path to
 * admin/vasl_academic.log. Uses __DIR__ so it lands in a known, readable place
 * rather than following the request's CWD, and is silent-on-failure so it can
 * never itself break a send.
 */
if (!function_exists('vasl_trace')) {
    function vasl_trace($msg) {
        @file_put_contents(
            __DIR__ . '/vasl_academic.log',
            date('Y-m-d H:i:s') . '  ' . $msg . PHP_EOL,
            FILE_APPEND
        );
    }
}

require_once __DIR__ . '/phpqrcode/qrlib.php';
require_once __DIR__ . '/pdf_plugins/generatePdf.php';
require_once __DIR__ . '/email_plugins/vendor/autoload.php';
require_once __DIR__ . '/email_plugins/email_function.php';

// Define generateAdmissionNumber here so we never depend on the caller's page
// having declared it (it is only defined inside the admin add-form pages). If a
// caller — e.g. the website's process_registration.php — reaches the admission
// letter without that definition loaded, the email would otherwise fatal.
if (!function_exists('generateAdmissionNumber')) {
    function generateAdmissionNumber() {
        return 'VASL ' . rand(11111111, 99999999);
    }
}

// Define numberToWords here so we never depend on external formatter (avoids fatal if formatter is missing or errors)
function numberToWords($num) {
    $num = (float) $num;
    $whole = (int) floor($num);
    $frac = round(($num - $whole) * 100);
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $convert = function ($n) use (&$convert, $ones, $tens) {
        if ($n === 0) return '';
        if ($n >= 1000) return trim($convert((int)($n / 1000)) . ' Thousand ' . $convert($n % 1000));
        if ($n >= 100) return trim($ones[(int)($n / 100)] . ' Hundred ' . $convert($n % 100));
        if ($n >= 20) return trim($tens[(int)($n / 10)] . ' ' . $convert($n % 10));
        return $ones[$n];
    };
    $result = trim($convert($whole)) ?: 'Zero';
    if ($frac > 0) $result .= ' and ' . $frac . '/100';
    return $result;
}

function generateInvoiceQRCode($text, $filename = null) {
    if ($filename === null) {
        $filename = uniqid('invoice_qr_', true) . '.png';
    }

    $path = __DIR__ . '/qrcodes/';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    $fullPath = $path . $filename;
    QRcode::png($text, $fullPath, QR_ECLEVEL_L, 10);

    return $filename;
}

function generateInvoiceNumber() {
    $prefix = "VASL.INV";
    $number = rand(100000, 999999);
    return $prefix . " " . $number;
}



// =============================================
// INVOICE PDF GENERATION
// =============================================
function generateInvoicePdf($client_email, $client_name, $invoice_no, $invoice_date, $invoice_items, $subtotal, $discount_percent, $discount_value, $total_payable, $amount_in_words, $qrcode, $training_program = '', $corporate_variant = '', $start_date = null, $end_date = null) {
    $is_corporate_invoice = in_array($corporate_variant, ['corporate_sldp', 'corporate_me', 'singapore_me'], true);
    $page_margin = $is_corporate_invoice ? '0.35in' : '0.5in';
    $body_padding = $is_corporate_invoice ? '12px' : '20px';
    $header_cell_padding = $is_corporate_invoice ? '8px' : '10px';
    $invoice_box_padding = $is_corporate_invoice ? '14px' : '20px';
    $invoice_box_margin = $is_corporate_invoice ? '8px 0' : '10px 0';
    $details_table_margin = $is_corporate_invoice ? '12px 0' : '20px 0';
    $details_cell_padding = $is_corporate_invoice ? '7px' : '10px';
    $items_table_margin = $is_corporate_invoice ? '12px 0' : '20px 0';
    $table_head_padding = $is_corporate_invoice ? '8px' : '10px';
    $format_money = function ($amount) {
        $rounded = round((float) $amount, 2);
        if (abs($rounded - round($rounded)) < 0.00001) {
            return number_format($rounded, 0);
        }
        return number_format($rounded, 2);
    };

    // Generate items HTML
    $items_html = '';
    $item_counter = 1;

    foreach ($invoice_items as $item) {
        $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 0;
        $unit_cost = ($quantity > 0) ? ((float) $item['total_cost'] / $quantity) : (float) $item['total_cost'];

        if ($is_corporate_invoice) {
            $items_html .= '
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['description']) . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars($item['unit_measure']) . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . $format_money($unit_cost) . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . $format_money($item['total_cost']) . '</td>
        </tr>';
        } else {
            $items_html .= '
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . $item_counter . '</td>
            <td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['description']) . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">' . htmlspecialchars($item['unit_measure']) . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format($unit_cost, 2) . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format($item['total_cost'], 2) . '</td>
        </tr>';
            $item_counter++;
        }
    }

    if ($is_corporate_invoice) {
        $table_head_html = '
            <tr>
                <th style="width: 52%;">Description</th>
                <th style="width: 18%;">Quantity</th>
                <th style="width: 15%;">Unit Cost (USD)</th>
                <th style="width: 15%;">Total (USD)</th>
            </tr>';
        $summary_rows_html = '
                <tr style="background-color: #f8f9fa;">
                    <td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: center; font-weight: bold; font-size: 18px;">Subtotal per participant</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; font-size: 18px;">' . $format_money($subtotal) . '</td>
                </tr>';
        $invoice_program_title = trim((string) $training_program);
        if ($start_date && $end_date) {
            $start_label = trim((string) $start_date);
            $end_label = trim((string) $end_date);
            $has_start_label = ($start_label !== '') && (stripos($invoice_program_title, $start_label) !== false);
            $has_end_label = ($end_label !== '') && (stripos($invoice_program_title, $end_label) !== false);
            $has_embedded_date_range = preg_match('/\b\d{1,2}(st|nd|rd|th)?\b.*\bto\b.*\b\d{1,2}(st|nd|rd|th)?\b/i', $invoice_program_title) === 1;
            if (!$has_start_label && !$has_end_label && !$has_embedded_date_range) {
                $invoice_program_title .= ' (' . $start_label . ' to ' . $end_label . ')';
            }
        }
        $program_heading_html = $invoice_program_title !== ''
            ? '<div style="text-align: center; font-size: 17px; font-weight: 700; margin-bottom: 12px;">' . htmlspecialchars($invoice_program_title) . '</div>'
            : '';
        $post_table_html = '
        <table style="width: 100%; margin-top: 28px;">
            <tr>
                <td style="width: 36%; vertical-align: top; font-size: 14px; line-height: 1.4;">
                    <strong>Fees payable to:</strong><br>
                    Vantage Africa<br>
                    School of<br>
                    Leadership Ltd.<br><br>
                </td>
                <td style="width: 64%; vertical-align: top; font-size: 14px; line-height: 1.4;">
                    <strong>Account details:</strong><br>
                    Account Name: VANTAGE AFRICA SCHOOL OF LEADERSHIP LTD<br>
                    Account No:1750186168502<br>
                    Bank Name: Equity Bank (K) Limited<br>
                    Swift Code: EQBLKENA<br>
                    Bank Code: 068<br>
                    Branch Name : GARDEN CITY BRANCH | Branch Code:175
                </td>
            </tr>
        </table>
        <table style="width: 100%; margin-top: 18px;">
            <tr>
                <td style="width: 50%; vertical-align: top; font-size: 14px;">
                    <strong>Yours sincerely,</strong><br><br>
                    <strong>Dr. Benson Kiarie, PhD</strong><br>
                    CEO & Founder<br>
                    Vantage Africa School of Leadership
                </td>
                <td style="width: 50%; vertical-align: top; text-align: center; font-size: 14px;">
                    <strong>Scan to verify:</strong><br>
                    <img src="https://vantageafricaleaders.com/admin/qr_code/signature_qrcode.png" width="95" height="95" alt="QR Code">
                </td>
            </tr>
        </table>';
    } else {
        $table_head_html = '
            <tr>
                <th style="width: 8%;">No</th>
                <th style="width: 40%;">Item</th>
                <th style="width: 20%;">Unit of Measure</th>
                <th style="width: 16%;">Cost per Unit (USD)</th>
                <th style="width: 16%;">Total Cost (USD)</th>
            </tr>';
        $summary_rows_html = '
                <tr style="background-color: #f8f9fa;">
                    <td colspan="4" style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">Subtotal</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">$' . number_format($subtotal, 2) . '</td>
                </tr>';
        if ($discount_value > 0) {
            $discount_text = $discount_percent > 0 ? "Less {$discount_percent}% Discount" : "Discount Applied";
            $summary_rows_html .= '
                <tr>
                    <td colspan="4" style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">' . $discount_text . '</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: red;">-$' . number_format($discount_value, 2) . '</td>
                </tr>';
        }
        $summary_rows_html .= '
                <tr style="background-color: #f8f9fa;">
                    <td colspan="4" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; font-size: 16px;">Total Payable</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; font-size: 16px; color: #A85431; white-space: nowrap;">$' . number_format($total_payable, 2) . '</td>
                </tr>';
        $program_heading_html = '';
        $post_table_html = '
        <table style="width: 100%; margin-top: 30px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Yours sincerely,</strong><br><br>
                    <strong>Dr. Benson Kiarie, PhD</strong><br>
                    CEO & Founder<br>
                    Vantage Africa School of Leadership
                </td>
                <td style="width: 50%; vertical-align: top; text-align: center;">
                    <strong>Scan to verify:</strong><br>
                    <img src="https://vantageafricaleaders.com/admin/qr_code/signature_qrcode.png" width="100" height="100" alt="QR Code">
                </td>
            </tr>
        </table>';
    }

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: ' . $page_margin . ';
            background-image: url(https://vantageafricaleaders.com/admin/assets/img/logo.png);
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px 200px;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: ' . $body_padding . ';
            font-size: 12px;
            background-image: url(https://vantageafricaleaders.com/admin/assets/img/logo.png);
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px 200px;
            background-attachment: fixed;
            opacity: 0.05;
        }
        table {
            border-collapse: collapse;
        }
        .header-table {
            width: 100%;
            margin-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
            padding: ' . $header_cell_padding . ';
        }
        .divider {
            background-color: #A85431;
            height: 5px;
            margin: 10px 0;
        }
        .contact-table {
            width: 100%;
            margin: 10px 0;
            text-align: center;
            color: #2B5470;
        }
        .contact-table td {
            padding: 5px;
        }
        .invoice-box {
            border: 2px solid #FEB958;
            padding: ' . $invoice_box_padding . ';
            margin: ' . $invoice_box_margin . ';
            background-color: rgba(255, 255, 255, 0.95);
        }
        .invoice-title {
            text-align: center;
            color: #2B5470;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
        }
        .items-table {
            width: 100%;
            margin: ' . $items_table_margin . ';
        }
        .items-table th {
            background-color: #2B5470;
            color: white;
            padding: ' . $table_head_padding . ';
            border: 1px solid #ddd;
            text-align: center;
        }
        .items-table td {
            border: 1px solid #ddd;
        }
        .footer-container {
            width: 100%;
            border-top: solid 10px #4F2020;
            margin-top: 20px;
        }
        .footer-content {
            background: linear-gradient(135deg, #4F2020 0%, #A85431 100%);
            height: 60px;
            text-align: center;
            color: white;
            font-family: "Times New Roman", serif;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .decorative-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%),
                linear-gradient(-45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%);
            background-size: 30px 30px;
        }
        .footer-text {
            position: relative;
            z-index: 2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 25%;">
                <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" width="120" height="100" alt="Logo">
            </td>
            <td style="width: 35%; font-size: 14px;">
                Astrol Business Center<br>
                6th Floor, C603,<br>
                Thika Road Opposite Garden City,<br>
                Nairobi
            </td>
            <td style="width: 40%; font-size: 14px; border-left: 2px solid #A85431; padding-left: 10px;">
                &#9650; Leadership training<br>
                &#9650; M&E Training and Consulting<br>
                &#9650; Eval360 Digital M&E System<br>
                &#9650; Strategy and HR Consulting<br>
                &#9650; Virtual Training Programs
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Contact -->
    <table class="contact-table">
        <tr>
            <td style="width: 33.33%;">isabel@vantageafricaleaders.com</td>
            <td style="width: 33.33%;">www.vantageafricaleaders.com</td>
            <td style="width: 33.33%;">+254796393864</td>
        </tr>
    </table>

    <!-- Invoice Content -->
    <div class="invoice-box">

        <div class="invoice-title">PROFORMA INVOICE</div>

        <!-- Details -->
        <table style="width: 100%; margin: ' . $details_table_margin . ';">
            <tr>
                <td style="width: 50%; padding: ' . $details_cell_padding . ';">
                    <strong>Date:</strong> ' . htmlspecialchars($invoice_date) . '<br><br>
                    <strong>Invoice No:</strong> ' . htmlspecialchars($invoice_no) . '
                </td>
                <td style="width: 50%; padding: ' . $details_cell_padding . ';">
                    <strong>Presented to:</strong> ' . htmlspecialchars($client_name) . '<br><br>
                    <strong>Amount Payable:</strong> <span style="color: #A85431; font-weight: bold;">$' . number_format($total_payable, 2) . '</span>
                </td>
            </tr>
        </table>

        ' . $program_heading_html . '
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                ' . $table_head_html . '
            </thead>
            <tbody>
                ' . $items_html . '
                ' . $summary_rows_html . '
            </tbody>
        </table>

        ' . $post_table_html . '
    </div>

    <!-- Enhanced Footer -->
    <div class="footer-container">
        <div class="footer-content">
            <div class="decorative-overlay"></div>
            <span class="footer-text">
                Developing Transformational Leaders
            </span>
        </div>
    </div>

</body>
</html>';

    $directory = 'invoices';
    $file = str_replace(" ", "_", $invoice_no) . "_" . $invoice_date;
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);

    return $generatedFilePath;
}

// =============================================
// MAIN INVOICE GENERATION FUNCTION
// =============================================
function generateInvoice($client_email, $client_name, $invoice_items, $discount_percent = 0, $discount_amount = 0, $start_date = null, $end_date = null, $location = null, $conn = null, $ticket_id = null, $record_id = null, $training_program = '', $corporate_variant = '') {
    // Ensure numberToWords exists (fallback if file was included in a context where top-level def didn't run)
    if (!function_exists('numberToWords')) {
        function numberToWords($num) {
            $num = (float) $num;
            $whole = (int) floor($num);
            $frac = round(($num - $whole) * 100);
            $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            $convert = function ($n) use (&$convert, $ones, $tens) {
                if ($n === 0) return '';
                if ($n >= 1000) return trim($convert((int)($n / 1000)) . ' Thousand ' . $convert($n % 1000));
                if ($n >= 100) return trim($ones[(int)($n / 100)] . ' Hundred ' . $convert($n % 100));
                if ($n >= 20) return trim($tens[(int)($n / 10)] . ' ' . $convert($n % 10));
                return $ones[$n];
            };
            $result = trim($convert($whole)) ?: 'Zero';
            if ($frac > 0) $result .= ' and ' . $frac . '/100';
            return $result;
        }
    }
    $invoice_no = generateInvoiceNumber();
    $invoice_date = date("jS F Y");
    $currency = "USD";

    // Calculate totals
    $subtotal = 0;
    foreach ($invoice_items as $item) {
        $subtotal += $item['total_cost'];
    }

    // Apply discount
    $discount_value = 0;
    if ($discount_percent > 0) {
        $discount_value = ($subtotal * $discount_percent) / 100;
    } elseif ($discount_amount > 0) {
        $discount_value = $discount_amount;
    }

    $total_payable = $subtotal - $discount_value;
    $amount_in_words = numberToWords($total_payable) . " " . $currency . ' only.';

    // Generate QR code for invoice verification
    $qrFile = generateInvoiceQRCode("https://vantageafricaleaders.com/admin/invoices/".$invoice_no . "_" . $invoice_date.".pdf");
    $qrcode = "qrcodes/" . $qrFile;

    $generatedFilePath = generateInvoicePdf($client_email, $client_name, $invoice_no, $invoice_date, $invoice_items, $subtotal, $discount_percent, $discount_value, $total_payable, $amount_in_words, $qrcode, $training_program, $corporate_variant, $start_date, $end_date);

    if ($generatedFilePath) {
        // Pass the new parameters to the email function
        sendInvoiceEmail($client_email, $client_name, "Proforma Invoice - " . $invoice_no, $generatedFilePath, $start_date, $end_date, $location, $conn, $ticket_id, $record_id, $corporate_variant);
        return $generatedFilePath;
    } else {
        echo "Failed to generate invoice PDF";
        return false;
    }
}

// =============================================
// SEND INVOICE EMAIL WITH LOGGING
// =============================================
function sendInvoiceEmail($client_email, $client_name, $subject, $generatedFilePath, $start_date = null, $end_date = null, $location = null, $conn = null, $ticket_id = null, $record_id = null, $corporate_variant = '') {
    $year = date("Y");
    $attachments = [$generatedFilePath];
    $url_pay_now = ($ticket_id !== null && $ticket_id !== '')
        ? ('https://vantageafricaleaders.com/pay/complete_payment.php?ticket_id=' . urlencode($ticket_id))
        : 'https://vantageafricaleaders.com/pay.php';
    $is_corporate_variant = in_array($corporate_variant, ['corporate_sldp', 'corporate_me', 'singapore_me'], true);

    // Format dates if provided
    $training_dates = '';
    if ($start_date && $end_date) {
        $training_dates = $start_date . ' &#8211; ' . $end_date;
    }

    // Use default location if not provided
    $training_location = $location;

    $body = '<html>' .
        '<head></head>' .
        '<body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">' .
            '<div style="border: solid 1px #d1d3e2;">
                <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
                <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
                <div style="padding: 0 .5rem">
                    <h5 id="subject_email">
                        <b>' . $subject . '</b>
                    </h5>
                    <p>
                        <strong>Dear ' . $client_name . ',</strong>
                    </p>
                    <div id="body_email">
                        <p>Your journey with Vantage Africa School of Leadership has just begun and we couldn\'t be more excited to walk this path with you! You\'ve taken a bold step towards advancing your career, and now it\'s time to secure your spot.</p>';

    // Add training details if dates are provided
    if ($training_dates) {
        $body .= '
                        <div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #A85431; margin: 15px 0;">
                            <h4 style="color: #2B5470; margin: 0 0 10px 0;">&#127891; Training Overview</h4>
                            <p style="margin: 5px 0;"><strong>&#128197; Dates:</strong> ' . $training_dates . '</p>
                            <p style="margin: 5px 0;"><strong>&#128205; Venue:</strong> ' . $training_location . '</p>
                        </div>';
    }
    $bank_payment_html = '';
    if ($is_corporate_variant) {
        $bank_payment_html = '
                            <div style="margin-bottom: 5px;">
                                <h5 style="color: #A85431; margin: 0 0 8px 0;">&#127974; Bank Payment:</h5>
                                <p style="margin: 4px 0;"><strong>Account Name:</strong> VANTAGE AFRICA SCHOOL OF LEADERSHIP LTD</p>
                                <p style="margin: 4px 0;"><strong>Account No:</strong> 1750186168502</p>
                                <p style="margin: 4px 0;"><strong>Bank Name:</strong> Equity Bank (K) Limited</p>
                                <p style="margin: 4px 0;"><strong>Swift Code:</strong> EQBLKENA</p>
                                <p style="margin: 4px 0;"><strong>Bank Code:</strong> 068</p>
                                <p style="margin: 4px 0;"><strong>Branch:</strong> GARDEN CITY BRANCH | <strong>Branch Code:</strong> 175</p>
                            </div>';
    }

    $body .= '
                        <p>Please find attached your official invoice for the program.</p>

                        <div style="background-color: #f0f8ff; padding: 15px; border: 1px solid #A85431; margin: 15px 0; border-radius: 5px;">
                            <h4 style="color: #2B5470; margin: 0 0 15px 0;">&#128179; Payment Details:</h4>

                            <div style="margin-bottom: 15px;">
                                <h5 style="color: #A85431; margin: 0 0 8px 0;">&#128187; Online Payment:</h5>
                                <p style="margin: 0;"><a href="' . htmlspecialchars($url_pay_now) . '" style="color: #2B5470; text-decoration: none; font-weight: bold;">' . htmlspecialchars($url_pay_now) . '</a></p>
                            </div>
                            ' . $bank_payment_html . '
                        </div>

                        <div style="background-color: #fff3cd; padding: 15px; border: 1px solid #ffc107; margin: 15px 0; border-radius: 5px;">
                            <p style="margin: 0; font-weight: bold; color: #856404;">&#128231; Once payment is made, kindly send your confirmation to <a href="mailto:isabel@vantageafricaleaders.com" style="color: #856404;">isabel@vantageafricaleaders.com</a> or <strong>+254796393864</strong> so we can finalize your registration.</p>
                        </div>

                        <p style="font-size: 16px; color: #2B5470; font-weight: bold; text-align: center; margin: 20px 0;">We can\'t wait to see you in class and watch you unlock new skills, confidence, and opportunities. This is your moment &#8211; let\'s make it count! &#128640;</p>

                        <p><strong>Warm regards,</strong><br>
                        <span style="color: #A85431; font-weight: bold;">Vantage Africa School of Leadership</span></p>
                    </div>
                </div>
                <div style="padding: .5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                    <div style="margin: 10px 0;">
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Facebook</a>
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Instagram</a>
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Twitter</a>
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">YouTube</a>
                    </div>
                    <a href="https://vantageafricaleaders.com/contact.php" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Help</a>
                    <a href="https://vantageafricaleaders.com/policies.php#privacy" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Privacy Policy</a>
                    <a href="https://vantageafricaleaders.com/policies.php#terms" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Terms of Service</a>
                    <a href="https://vantageafricaleaders.com/policies.php#refund" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Refund Policy</a>
                    <a href="https://vantageafricaleaders.com/" style="text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Website</a>
                    <div style="color: #9ba4b3; font-size: .8rem; margin: 10px 0;">
                        We sent this email to
                        <span>' .$client_email. '</span>
                        <a href="https://vantageafricaleaders.com/policies.php#privacy" style="text-decoration: underline; color: #9ba4b3; font-weight: 700;">Manage Privacy Preferences</a>
                    </div>
                    <div style="color: #9ba4b3; font-size: .8rem;">
                        &copy; ' .$year. '
                        Vantage Africa School of Leadership. All Rights Reserved
                    </div>
                </div>
            </div>' .
        '</body></html>';

    // Send email
    $email_sent = send_mail_function($client_email, $body, $subject, $attachments);

    // =============================================
    // LOG THE INVOICE EMAIL
    // =============================================
    if ($conn && $ticket_id) {
        $status = $email_sent ? 'sent' : 'failed';
        $error = $email_sent ? null : 'Failed to send invoice email';

        log_email(
            $conn,
            'ticket_congress',
            $ticket_id,
            'invoice',
            $client_email,
            $client_name,
            $subject,
            $attachments,
            $status,
            $error,
            null,
            $record_id
        );
    }

    return $email_sent;
}

// =============================================
// ADMISSION LETTER GENERATION
// =============================================
// $event_id (last param) is passed through into generateAdmissionPdf().
function generateAdmissionLetter($client_email, $client_name, $training_program, $total_fee, $training_areas = [], $conn = null, $ticket_id = null, $record_id = null, $program_variant = null, $location = null, $invite_position = '', $invite_organization = '', $invite_country = '', $invite_start_date = null, $invite_end_date = null, $event_id = null) {
    $admission_no = generateAdmissionNumber();
    $admission_date = date("d/m/Y");

    // Generate QR code for admission letter
    $qrFile = generateInvoiceQRCode("https://vantageafricaleaders.com/admin/admissions/".$admission_no . "_" . $admission_date.".pdf");
    $qrcode = "qrcodes/" . $qrFile;

    generateAdmissionPdf($client_email, $client_name, $admission_no, $admission_date, $training_program, $total_fee, $training_areas, $qrcode, $conn, $ticket_id, $record_id, $program_variant, $location, $invite_position, $invite_organization, $invite_country, $invite_start_date, $invite_end_date, $event_id);
}

function generateCorporateMealInvitationPdf($client_name, $training_program, $location = null, $position = '', $organization = '', $country = '', $program_variant = null, $start_date = null, $end_date = null) {
    $today = date('jS F Y');
    $program_title = trim((string) $training_program);
    $is_sldp = ($program_variant === 'corporate_sldp');
    if ($program_title === '') {
        $program_title = $is_sldp
            ? 'Strategic Leadership Development Program (SLDP), Singapore'
            : 'Strategic Monitoring & Evaluation Training, Singapore';
    }
    $recipient_name = trim((string) $client_name) !== '' ? trim((string) $client_name) : 'Participant';
    $recipient_position = trim((string) $position);
    $recipient_org = trim((string) $organization);
    $recipient_country = trim((string) $country);
    $recipient_block_html = '<strong>' . htmlspecialchars($recipient_name) . ',</strong><br>';
    if ($recipient_position !== '') {
        $recipient_block_html .= htmlspecialchars($recipient_position) . ',<br>';
    }
    if ($recipient_org !== '') {
        $recipient_block_html .= htmlspecialchars($recipient_org) . ',<br>';
    }
    if ($recipient_country !== '') {
        $recipient_block_html .= htmlspecialchars($recipient_country) . '.';
    }
    $date_span = ($start_date && $end_date)
        ? (trim((string) $start_date) . ' to ' . trim((string) $end_date))
        : ($is_sldp ? '8th to 12th June, 2026' : '1st to 5th June 2026');
    $subject_line = $is_sldp
        ? ('Re: Invitation to Strategic Leadership Development Program (SLDP), ' . $date_span)
        : 'Re: Invitation to Strategic Monitoring & Evaluation Training, Singapore';
    $paragraph_intro = $is_sldp
        ? ('Vantage Africa School of Leadership is pleased to invite you and your institution to participate in the <strong>Strategic Leadership Development Program (SLDP)</strong>, to be held in <strong>Singapore from ' . htmlspecialchars($date_span) . '</strong>.')
        : ('Vantage Africa School of Leadership is pleased to invite you and your institution to participate in the <strong>Strategic Monitoring & Evaluation (M&E) Training</strong>, to be held in <strong>Singapore from ' . htmlspecialchars($date_span) . '</strong>. This programme is designed for <strong>senior leaders</strong> responsible for institutional performance, accountability, and the long-term sustainability of Monitoring and Evaluation systems.');
    $paragraph_context = $is_sldp
        ? 'Across many institutions, significant effort has been invested in developing strategic plans and operational frameworks. However, experience consistently shows that the success of these plans is <strong>not determined by their design alone</strong>. Rather, it depends on the strength of leadership, particularly the ability to translate strategy into execution, align teams around shared priorities, and sustain performance in dynamic and often complex environments. This programme therefore adopts a strategic focus, aimed at strengthening the capacity of leaders and institutions to:'
        : 'Across many institutions, significant investments have been made in establishing M&E structures. However, experience shows that the effectiveness of these systems is <strong>not determined solely by technical design</strong>. Rather, it depends on how M&E is positioned within the institution, particularly its integration into leadership decision-making, policy processes, and organizational culture. This training therefore adopts a strategic focus, aimed at strengthening the ability of institutions to:';
    $bullet_points_html = $is_sldp
        ? '<li>Strengthen leadership effectiveness at executive and institutional levels</li>
            <li>Translate strategy into measurable results and sustained performance</li>
            <li>Align teams and departments around shared organizational priorities</li>
            <li>Enhance accountability, decision-making, and execution discipline</li>
            <li>Build resilient institutions capable of performing in complex environments</li>'
        : '<li>Build and sustain a strong M&E culture</li>
            <li>Strengthen results-based management at institutional level</li>
            <li>Enhance grant accountability and performance reporting</li>
            <li>Use M&E data to inform strategic and policy-level decisions</li>';
    $paragraph_delivery = $is_sldp
        ? 'The Strategic Leadership Development Program is structured as an <strong>intensive five-day executive training</strong>, specifically tailored for Board Members, Chief Executive Officers, Directors, Senior Managers, Government Leaders, and institutional decision-makers.'
        : 'A key feature of this programme is the application of <strong>AI-enabled Digital M&E Systems</strong>. Each participant will receive access to <strong>Eval360</strong>, a digital MEAL platform, through which the full M&E cycle will be applied in a structured and practical manner.';
    $paragraph_facilitator = $is_sldp
        ? 'The programme will be led by <strong>Dr. Benson Kiarie, PhD</strong>, a seasoned leadership and institutional performance expert who has trained over 18,000 professionals across 29 countries. The programme includes structured opportunities for professional networking, peer learning, and collaborative exchange with senior leaders from more than 20 countries. Participants will also engage in a study tour, providing insights into systems that have successfully integrated leadership, governance, and performance management.'
        : 'The training will be led by <strong>Dr. Benson Kiarie, PhD</strong>, and will utilize applied case studies and practical exercises drawn from government, academic, and development programme contexts. In addition to the training sessions, participants will engage with senior leaders from across Africa and participate in a study tour in Singapore, offering exposure to systems that have successfully integrated performance management into governance and service delivery.';
    $paragraph_cost = $is_sldp
        ? 'The total cost of participation is <strong>USD 2,900 per participant</strong>, covering airport transfers, training sessions and materials, and the study tour in Singapore. Should you require further information or wish to confirm participation, please do not hesitate to contact us. We look forward to welcoming you to Singapore.'
        : 'The total cost is <strong>USD 2,900 per participant</strong>, covering: Airport transfers, Training sessions and materials, Access to the Eval360 digital system and Study tour in Singapore. We are confident that this programme will provide significant value to your institution, particularly in strengthening the strategic positioning and sustainability of your M&E systems.';

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0.35in; }
        body { font-family: "Times New Roman", serif; margin: 0; padding: 0; color: #111; font-size: 11.5px; line-height: 1.3; }
        .page { border: 1.5px solid #FEB958; padding: 14px 16px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .header-table td { vertical-align: top; font-size: 11.5px; }
        .divider { height: 3px; background: #A85431; margin: 6px 0 8px; }
        .contact-row { width: 100%; border-collapse: collapse; margin: 6px 0 10px; color: #2B5470; }
        .contact-row td { text-align: center; width: 33.33%; font-size: 11px; padding: 2px 4px; }
        .recipient { margin: 6px 0 8px; }
        .subject { margin: 6px 0 8px; font-weight: bold; }
        .para { margin: 6px 0; text-align: justify; }
        .bullet-list { margin: 4px 0 6px 16px; padding: 0; }
        .bullet-list li { margin: 2px 0; }
        .contacts-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .contacts-grid td { width: 50%; vertical-align: top; font-size: 11.5px; padding: 0 8px 0 0; }
        .signature-mark { margin: 6px 0 4px; width: 150px; height: 24px; border-bottom: 1px solid #6b6b6b; }
        .signature { margin-top: 8px; }
        .footer-banner { margin-top: 8px; background: linear-gradient(135deg, #4F2020 0%, #A85431 100%); color: #fff; text-align: center; padding: 6px 8px; font-size: 11px; font-weight: bold; letter-spacing: 0.4px; }
    </style>
</head>
<body>
    <div class="page">
        <table class="header-table">
            <tr>
                <td style="width: 24%;">
                    <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" style="width: 120px; height: auto; max-height: 90px; object-fit: contain;" alt="Vantage Africa Logo">
                </td>
                <td style="width: 36%;">
                    Astrol Business Center<br>
                    6th Floor, C603,<br>
                    Thika Road Opposite Garden City,<br>
                    Nairobi, Kenya
                </td>
                <td style="width: 40%; border-left: 2px solid #A85431; padding-left: 10px;">
                    &#9650; Leadership training<br>
                    &#9650; M&E Training and Consulting<br>
                    &#9650; Eval360 Digital M&E System<br>
                    &#9650; Strategy and HR Consulting<br>
                    &#9650; Virtual Training Programs
                </td>
            </tr>
        </table>
        <div class="divider"></div>

        <table class="contact-row">
            <tr>
                <td>info@vantageafricaleaders.com</td>
                <td>www.vantageafricaleaders.com</td>
                <td>+254 725 303 645</td>
            </tr>
        </table>

        <p><strong>' . htmlspecialchars($today) . '</strong></p>
        <div class="recipient">
            ' . $recipient_block_html . '
        </div>

        <p>Dear ' . htmlspecialchars($recipient_name) . ',</p>
        <p class="subject">' . $subject_line . '</p>

        <p class="para">
            ' . $paragraph_intro . '
        </p>
        <p class="para">
            ' . $paragraph_context . '
        </p>

        <ul class="bullet-list">
            ' . $bullet_points_html . '
        </ul>

        <p class="para">
            ' . $paragraph_delivery . '
        </p>
        <p class="para">
            ' . $paragraph_facilitator . '
        </p>
        <p class="para">
            ' . $paragraph_cost . '
        </p>

        <table class="contacts-grid">
            <tr>
                <td>
                    <div class="signature">
                        Yours faithfully,<br>
                        <div class="signature-mark"></div>
                        <strong>Dr. Benson Kiarie, PhD</strong><br>
                        CEO, Vantage Africa School of Leadership<br>
                        Email: <strong>bkiarie@vantageafricaleaders.com</strong><br>
                        Phone: <strong>+254725303645</strong>
                    </div>
                </td>
                <td>
                    <strong>Alternative Contact:</strong><br>
                    Tacy Isabel<br>
                    Country Coordinator,<br>
                    Vantage Africa School of Leadership<br>
                    Email: <strong>isabel@vantageafricaleaders.com</strong><br>
                    Phone: <strong>+254 796 393864</strong>
                </td>
            </tr>
        </table>

        <div class="footer-banner">Developing Transformational Leaders</div>
    </div>
</body>
</html>';

    $directory = 'admissions';
    $file = 'Invitation_MEAL_Singapore_2026_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $client_name ?: 'Participant');
    return convertHtmlToPdf($html, $directory, $file);
}

// $event_id (last param) is passed through into sendAdmissionEmail().
function generateAdmissionPdf($client_email, $client_name, $admission_no, $admission_date, $training_program, $total_fee, $training_areas = [], $qrcode = '', $conn = null, $ticket_id = null, $record_id = null, $program_variant = null, $location = null, $invite_position = '', $invite_organization = '', $invite_country = '', $invite_start_date = null, $invite_end_date = null, $event_id = null) {
    $admission_program_name = trim((string) $training_program);
    if ($program_variant === 'corporate_sldp') {
        $admission_program_name = preg_replace('/\s*-\s*Corporate\b/i', '', $admission_program_name);
        $admission_program_name = preg_replace('/\bCorporate\b/i', '', $admission_program_name);
        $admission_program_name = preg_replace('/\(\s+/', '(', $admission_program_name);
        $admission_program_name = preg_replace('/\s+\)/', ')', $admission_program_name);
        $admission_program_name = preg_replace('/\s{2,}/', ' ', $admission_program_name);
        $admission_program_name = trim((string) $admission_program_name, " \t\n\r\0\x0B-");
    }
    if ($admission_program_name === '') {
        $admission_program_name = trim((string) $training_program);
    }
    $qr_image_url = 'https://vantageafricaleaders.com/admin/qr_code/signature_qrcode.png';
    if (!empty($qrcode)) {
        $qr_image_url = 'https://vantageafricaleaders.com/admin/' . ltrim((string) $qrcode, '/');
    }

    global $code;

    // Corporate SLDP: curriculum and outcome
    if ($program_variant === 'corporate_sldp') {
        $default_training_areas = [
            '1. Strategy & Institutional Alignment',
            '2. Decision-Making Under Pressure',
            '3. Accountability & Performance Governance',
            '4. Leading Change & Reform',
            '5. Executive Communication & Influence',
        ];
        $intro_content = "This corporate programme is designed to equip senior leaders with the capability to diagnose execution failure and lead institutions to measurable performance. The curriculum combines strategy, accountability, and executive communication.";
        $outcome_line = "Outcome: Participants gain the ability to diagnose execution failure and lead institutions to measurable performance.";
    } elseif ($program_variant === 'corporate_me') {
        $default_training_areas = [
            '1. Foundations of Monitoring & Evaluation: results-based management, indicators, data visualization',
            '2. Framework Design & Planning: theory of change, logical frameworks, evaluation plans',
            '3. Data Systems & Digital Reporting: digital M&E systems, dashboards, decision support',
            '4. Evaluations & Practice: baseline, midterm & endline evaluations, participatory M&E',
            '5. Governance & Ethics: accountability systems, equity, learning systems',
        ];
        $intro_content = "This corporate programme is designed to equip participants with the capacity to design M&E systems, track performance and support decision-making. The curriculum combines frameworks, digital reporting and evaluation practice.";
        $outcome_line = "Outcome: Participants gain capacity to design M&E systems, track performance and support decision-making.";
    }

    if (!isset($default_training_areas)) {
        if($code == 1){
            $default_training_areas = [
                '1. The Place of M&E in project Cycle',
                '2. Theory of Change',
                '3. M&E Demystified',
                '4. Rationale for Monitoring and Evaluation',
                '5. Key Concepts in M&E',
                '6. Formulating Goals, Objectives and Objectively Verifiable Indicators- OVIs',
                '7. M&E Frameworks and Practical Designing of a Logframe',
                '8. Impact Harvesting and Reporting',
                '9. Performance Monitoring and Evaluation Plans',
                '10. Developing of a Performance Results Report',
                '11. Data Management and visualization',
                '12. Developing a comprehensive M&E Report',
                '13. Participatory M&E Approaches',
                '14. Sustaining M&E Systems in organizations',
                '15. M&E Consultancy, Careers and Emerging Issues',
                '16. Digital AI enabled M&E Systems'
            ];
            $intro_content = "This comprehensive program is designed to equip you with essential M&E skills and knowledge needed to become a transformational leader in your field. Our curriculum combines theoretical foundations with practical applications.";
        } elseif($code == 3){
            $default_training_areas = [
                '1. Data Cleaning and Transformation',
                '2. Descriptive and Inferential Statistics',
                '3. Hypothesis Testing and Regression Analysis',
                '4. Data Visualization and Storytelling',
                '5. Creating Interactive Dashboards',
                '6. Data Modeling and Management',
                '7. Predictive Analytics and Advanced Statistical Tests',
                '8. Connecting and Transforming Datasets',
                '9. Business Intelligence Reporting',
                '10. Exporting Professional Reports',
            ];
            $intro_content = "This comprehensive program is designed to equip you with essential Data Analysis and knowledge needed to become a transformational leader in your field. Our curriculum combines theoretical foundations with practical applications.";
        } elseif($code == 2){
            $default_training_areas = [
                '1. Overview of Project Management',
                '2. Theory of Change',
                '3. Introduction to Resource Mobilization',
                '4. Resource Mobilization Planning',
                '5. Getting organized for Resource Mobilization',
                '6. Resource Mobilization Strategies',
                '7. Developing a Concept note and Proposal',
                '8. Project M&E',
                '9. Budget Development',
                '10. Developing a Logical Framework for your Project',
                '11. Building Relationships with Donors',
                '12. Emerging Issues in Donor Environment',
            ];
            $intro_content = "This comprehensive program is designed to equip you with essential Resource Mobilization and Proposal Writing skills and knowledge needed to become a transformational leader in your field. Our curriculum combines theoretical foundations with practical applications.";
        } else {
            $default_training_areas = [
                '1. The Place of M&E in project Cycle',
                '2. Theory of Change',
                '3. M&E Demystified',
                '4. Rationale for Monitoring and Evaluation',
                '5. Key Concepts in M&E',
                '6. Formulating Goals, Objectives and Objectively Verifiable Indicators- OVIs',
                '7. M&E Frameworks and Practical Designing of a Logframe',
                '8. Impact Harvesting and Reporting',
                '9. Performance Monitoring and Evaluation Plans',
                '10. Developing of a Performance Results Report',
                '11. Data Management and visualization',
                '12. Developing a comprehensive M&E Report',
                '13. Participatory M&E Approaches',
                '14. Sustaining M&E Systems in organizations',
                '15. M&E Consultancy, Careers and Emerging Issues',
                '16. Digital AI enabled M&E Systems'
            ];
            $intro_content = "This comprehensive program is designed to equip you with essential M&E skills and knowledge needed to become a transformational leader in your field. Our curriculum combines theoretical foundations with practical applications.";
        }
        $outcome_line = null;
    }

    // Academic programmes (Event.location contains 'academic#') run continuously and
    // must never inherit the dated-event syllabus selected above — a leadership intake
    // would otherwise be sent the M&E curriculum. Use the programme's OWN configured
    // modules; when none have been set up yet, show a placeholder so staff can see the
    // gap, rather than shipping another programme's content to the client.
    $is_academic_letter = (stripos((string) $location, 'academic#') !== false);
    if ($is_academic_letter) {
        $academic_helper = __DIR__ . '/includes/academic_approval.php';
        if (is_file($academic_helper)) { require_once $academic_helper; }

        $academic_modules = [];
        if ($conn && function_exists('academic_program_modules')) {
            $academic_modules = academic_program_modules($conn, $admission_program_name);
        }

        if (!empty($academic_modules)) {
            $training_areas = $academic_modules;
        } elseif (empty($training_areas)) {
            // Customer-safe fallback when the programme's curriculum modules
            // aren't configured yet — never ship a developer placeholder.
            // Real fix: set the programme's curriculum in academic_programs.
            $default_training_areas = ['Programme modules to be confirmed.'];
        }

        $intro_content = 'This programme is available on a continuous basis — there is no fixed intake '
            . 'and no set start date, so you may begin at a time that suits you. Your place is confirmed '
            . 'upon settlement of the fees indicated below.';
        $outcome_line = null;
    }

    // Use provided training areas or default ones
    $areas_to_display = !empty($training_areas) ? $training_areas : $default_training_areas;

    $areas_section_title = ($program_variant === 'corporate_sldp' || $program_variant === 'corporate_me')
        ? 'BREAKDOWN OF WHAT IS TAUGHT'
        : 'THE AREAS TO BE TRAINED INCLUDE';

    $outcome_row_html = '';
    if (!empty($outcome_line)) {
        $outcome_row_html = '
                <tr style="background-color: #f0f8ff;">
                    <td colspan="2" style="padding: 10px; border: 1px solid #ddd; font-weight: bold; color: #2B5470;">' . htmlspecialchars($outcome_line) . '</td>
                </tr>';
    }

    // Generate two-column layout
    $training_areas_html = '';
    $total_areas = count($areas_to_display);
    $half_count = ceil($total_areas / 2);

    for ($i = 0; $i < $half_count; $i++) {
        $left_area = isset($areas_to_display[$i]) ? htmlspecialchars($areas_to_display[$i]) : '';
        $right_area = isset($areas_to_display[$i + $half_count]) ? htmlspecialchars($areas_to_display[$i + $half_count]) : '';

        $training_areas_html .= '
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; width: 50%; vertical-align: top;">' . $left_area . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; width: 50%; vertical-align: top;">' . $right_area . '</td>
        </tr>';
    }

    // CTA URLs for letter PDF (same as thank-you / admission email)
    $url_confirm_payment = ($ticket_id !== null && $ticket_id !== '') ? ('https://vantageafricaleaders.com/pay/complete_payment.php?ticket_id=' . urlencode($ticket_id)) : 'https://vantageafricaleaders.com/pay.php';
    $url_free_sessions   = 'https://vantageafricaleaders.com/trainings/videos.php';
    $url_whatsapp        = 'https://wa.me/254796128454';
    // Override WhatsApp number for Singapore "Synergy Room" sessions
    $is_synergy_room_location = false;
    if ($location ) {
        $is_synergy_room_location = (stripos($location, 'Synergy Room') !== false);
    }

    if (  $is_synergy_room_location) {
        $url_whatsapp = 'https://wa.me/254725303645';
    }
    $url_contact         = 'https://vantageafricaleaders.com/contact.php';
    $url_elearning       = 'https://eval360.tech';
    $url_team_training   = 'https://vantageafricaleaders.com/company-nominations/';
    $title = trim((string) ($training_program ?? ''));
    // 'academic#' is an internal flag on Event.location, not part of the venue name.
    $db_location = trim(str_ireplace('academic#', '', (string) ($location ?? '')));
    if ($title !== '' || $db_location !== '') {
        $pay_dir = __DIR__ . '/../pay';
        if (file_exists($pay_dir . '/event_contact_map.php')) { include_once $pay_dir . '/event_contact_map.php'; }
        if (file_exists($pay_dir . '/event_elearning_map.php')) { include_once $pay_dir . '/event_elearning_map.php'; }
        if (file_exists($pay_dir . '/event_whatsapp_map.php')) { include_once $pay_dir . '/event_whatsapp_map.php'; }
        $try_countries = [
            'Trinidad and Tobago', 'Trinidad & Tobago', 'Papua New Guinea', 'Sierra Leone', 'South Sudan', 'Sri Lanka', 'Singapore', 'Philippines', 'Bangladesh', 'Barbados',
            'S. Sudan', 'Tanzania', 'TZ', 'Cameroon', 'Botswana', 'Nepal', 'Eswatini', 'Trinidad', 'Burundi', 'Namibia', 'Fiji', 'Rwanda', 'Malawi', 'Liberia', 'Gambia',
            'Malaysia', 'Guyana', 'Jamaica', 'Mozambique', 'Lesotho', 'Zimbabwe', 'Nigeria', 'Ethiopia', 'DRC',
        ];
        $loc = null;
        foreach ($try_countries as $c) {
            if (stripos($title, $c) !== false) { $loc = $c; break; }
        }
        if ($loc === null && $db_location !== '') {
            $loc = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $db_location));
        }
        if ($loc === null) { $loc = ''; }
        if (function_exists('get_event_contact_phone')) {
            $contact_phone = get_event_contact_phone($loc, $title);
            if ($contact_phone !== null && $contact_phone !== '') {
                $url_contact = 'https://wa.me/' . preg_replace('/\D/', '', $contact_phone);
            }
        }
        if (function_exists('get_event_elearning_url')) {
            $elearning_url = get_event_elearning_url($loc, $title);
            if ($elearning_url !== null && $elearning_url !== '') { $url_elearning = $elearning_url; }
        }
        if (function_exists('get_event_whatsapp_url')) {
            $whatsapp_url = get_event_whatsapp_url($loc, $title);
            if ($whatsapp_url !== null && $whatsapp_url !== '') { $url_whatsapp = $whatsapp_url; }
        }
    }
    $ctas_letter_html = '<div style="margin: 12px 0; padding: 10px; border: 1px solid #A85431; background: #f8f9fa; border-radius: 6px;">
        <p style="margin: 0 0 8px; font-weight: bold; color: #2B5470; font-size: 12px;">What you can do next (click to open):</p>
        <p style="margin: 4px 0; font-size: 10px;">&#8226; <a href="' . htmlspecialchars($url_confirm_payment) . '" style="color: #A85431;">Confirm your Registration</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_free_sessions) . '" style="color: #A85431;">Watch training videos</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_whatsapp) . '" style="color: #25D366;">Join WhatsApp group</a></p>
        <p style="margin: 4px 0; font-size: 10px;">&#8226; <a href="' . htmlspecialchars($url_contact) . '" style="color: #A85431;">Talk to us</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_elearning) . '" style="color: #A85431;">Explore E-learning</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_team_training) . '" style="color: #A85431;">Training for my team</a></p>
        </div>';

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0.4in;
            background-image: url(https://vantageafricaleaders.com/admin/assets/img/logo.png);
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px 200px;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 15px;
            font-size: 11px;
            background-image: url(https://vantageafricaleaders.com/admin/assets/img/logo.png);
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px 200px;
            background-attachment: fixed;
            opacity: 0.05;
        }
        table {
            border-collapse: collapse;
        }
        .header-table {
            width: 100%;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
            padding: 8px;
        }
        .divider {
            background-color: #A85431;
            height: 4px;
            margin: 8px 0;
        }
        .contact-table {
            width: 100%;
            margin: 8px 0;
            text-align: center;
            color: #2B5470;
        }
        .contact-table td {
            padding: 4px;
        }
        .invoice-box {
            border: 2px solid #FEB958;
            padding: 15px;
            margin: 8px 0;
            background-color: rgba(255, 255, 255, 0.95);
        }
        .invoice-title {
            text-align: center;
            color: #2B5470;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
        }
        .items-table {
            width: 100%;
            margin: 15px 0;
        }
        .items-table th {
            background-color: #2B5470;
            color: white;
            padding: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .items-table td {
            border: 1px solid #ddd;
        }
        .footer-container {
            width: 100%;
            border-top: solid 3px #4F2020;
            margin-top: 5px;
        }
        .footer-content {
            background: linear-gradient(135deg, #4F2020 0%, #A85431 100%);
            height: 30px;
            text-align: center;
            color: white;
            font-family: "Times New Roman", serif;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .decorative-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%),
                linear-gradient(-45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%);
            background-size: 30px 30px;
        }
        .footer-text {
            position: relative;
            z-index: 2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 25%;">
                <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" width="120" height="100" alt="Logo">
            </td>
            <td style="width: 35%; font-size: 14px;">
                Astrol Business Center<br>
                6th Floor, C603,<br>
                Thika Road Opposite Garden City,<br>
                Nairobi
            </td>
            <td style="width: 40%; font-size: 14px; border-left: 2px solid #A85431; padding-left: 10px;">
                &#9650; Leadership training<br>
                &#9650; M&E Training and Consulting<br>
                &#9650; Eval360 Digital M&E System<br>
                &#9650; Strategy and HR Consulting<br>
                &#9650; Virtual Training Programs
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Contact -->
    <table class="contact-table">
        <tr>
            <td>&#128231; isabel@vantageafricaleaders.com</td>
            <td>&#127760; www.vantageafricaleaders.com</td>
            <td>&#128222; +254796393864</td>
        </tr>
    </table>

    <!-- Admission Content -->
    <div class="invoice-box">

        <div class="invoice-title">ADMISSION LETTER</div>

        <!-- Details -->
        <table style="width: 100%; margin: 15px 0;">
            <tr>
                <td style="width: 50%; padding: 8px;">
                    <strong>Date:</strong> ' . htmlspecialchars($admission_date) . '<br><br>
                    <strong>Admission No:</strong> ' . htmlspecialchars($admission_no) . '
                </td>
                <td style="width: 50%; padding: 8px;">
                    <strong>Student Name:</strong> ' . htmlspecialchars($client_name) . '<br><br>
                    <strong>Program Fee:</strong> <span style="color: #A85431; font-weight: bold;">'. number_format($total_fee, 2) . '</span>
                </td>
            </tr>
        </table>

        <!-- Letter Content -->
        <div style="line-height: 1.4; text-align: justify; margin: 15px 0; font-size: 11px;">
            <p>Dear <strong>' . htmlspecialchars($client_name) . '</strong>,</p>

            <p style="background-color: #f8f9fa; padding: 10px; border-left: 4px solid #A85431; margin: 15px 0; font-style: italic; color: #2B5470;">
                <strong>Congratulations!</strong> You have been accepted into the <strong>' . htmlspecialchars($admission_program_name) . '</strong> at Vantage Africa School of Leadership.
            </p>

            <p>' . htmlspecialchars($intro_content) . '</p>
        </div>

        <!-- Training Areas Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th colspan="2" style="background-color: #A85431; color: white; padding: 12px; text-align: center; font-size: 16px;">
                        ' . htmlspecialchars($areas_section_title) . ':
                    </th>
                </tr>
            </thead>
            <tbody>
                ' . $training_areas_html . '
                ' . $outcome_row_html . '
                <tr style="background-color: #f8f9fa;">
                    <td colspan="2" style="padding: 10px; border: 1px solid #ddd; text-align: center; font-weight: bold; font-size: 14px; color: #A85431;">
                        Program Investment: $' . number_format($total_fee, 2) . '
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Next Steps -->
        <div style="line-height: 1.3; text-align: justify; margin: 10px 0; font-size: 11px;">
            <p style="margin: 5px 0;"><strong>What\'s Next?</strong> &#8226; Confirm acceptance &#8226; Complete enrollment &#8226; Prepare for transformation</p>

            <p style="margin: 5px 0;">We look forward to welcoming you to our community of emerging leaders. <strong>Welcome to Vantage Africa School of Leadership!</strong></p>
        </div>

        ' . $ctas_letter_html . '

        <!-- Signature and QR Section -->
        <table style="width: 100%; margin-top: 10px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    Yours faithfully,<br><br>
                    Dr. Benson Kiarie, PhD<br>
                    CEO, Vantage Africa School of Leadership<br>
                    Email: bkiarie@vantageafricaleaders.com<br>
                    Phone: +254725303645
                </td>
                <td style="width: 50%; vertical-align: top; text-align: center;">
                    <strong>Scan to verify:</strong><br>
                    <img src="' . htmlspecialchars($qr_image_url) . '" width="70" height="70" alt="QR Code">
                </td>
            </tr>
        </table>
    </div>

    <!-- Enhanced Footer -->
    <div class="footer-container">
        <div class="footer-content">
            <div class="decorative-overlay"></div>
            <span class="footer-text">
                Developing Transformational Leaders
            </span>
        </div>
    </div>

</body>
</html>';

    $directory = 'admissions';
    $file = str_replace(" ", "_", $admission_no) . "_" . str_replace("/", "-", $admission_date);
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);

    if ($generatedFilePath) {
        // pass $event_id as the final argument
        sendAdmissionEmail($client_email, $client_name, "Admission Letter - " . $admission_no, $generatedFilePath, $conn, $ticket_id, $record_id, $training_program, $location, $program_variant, $invite_position, $invite_organization, $invite_country, $invite_start_date, $invite_end_date, $event_id);
        return $generatedFilePath;
    } else {
        echo "Failed to generate admission letter PDF";
        return false;
    }
}


// =============================================
// SEND ADMISSION EMAIL WITH LOGGING
// =============================================
// Template-ONLY: the admission email body comes from system_emails1 by event_id.
//   SELECT * FROM system_emails1 WHERE event_id = $eid AND email_opt = 1 ORDER BY id DESC LIMIT 1
// There is NO default body. If no usable template is found, the send is aborted
// (and the reason is logged) rather than mailing an empty body.
// The `body` column is stored as a JSON-encoded string; we json_decode it, with a
// manual-unescape fallback in case the stored JSON is slightly malformed.
function sendAdmissionEmail($client_email, $client_name, $subject, $generatedFilePath, $conn = null, $ticket_id = null, $record_id = null, $event_title = null, $location = null, $program_variant = null, $invite_position = '', $invite_organization = '', $invite_country = '', $invite_start_date = null, $invite_end_date = null, $event_id = null) {
    $year = date("Y");
    $attachments = [$generatedFilePath];

    // =============================================
    // event_id -> custom email template (system_emails1). REQUIRED for all events.
    // =============================================
    $custom_body = null;
    if ($conn && $event_id !== null) {
        $eid = (int) $event_id;
        $tpl_res = mysqli_query($conn, "SELECT * FROM system_emails1 WHERE event_id = $eid AND email_opt = 1 ORDER BY id DESC LIMIT 1");

        if (!$tpl_res) {
            error_log('ADMISSION EMAIL: template query failed for event_id=' . $eid . ' -> ' . mysqli_error($conn));
        } elseif (mysqli_num_rows($tpl_res) === 0) {
            error_log('ADMISSION EMAIL: no template row for event_id=' . $eid . ' with email_opt=1');
        } else {
            $tpl_row  = mysqli_fetch_assoc($tpl_res);
            $raw_body = isset($tpl_row['body']) ? (string) $tpl_row['body'] : '';

            // Body is stored as a JSON-encoded string (starts with ", contains \t \" \/).
            $tpl_body = json_decode($raw_body, true);

            // Fallback: if json_decode fails but we have raw content, unescape manually.
            if (!is_string($tpl_body) || $tpl_body === '') {
                if ($raw_body !== '') {
                    $trimmed = trim($raw_body);
                    if (strlen($trimmed) >= 2 && $trimmed[0] === '"' && substr($trimmed, -1) === '"') {
                        $trimmed = substr($trimmed, 1, -1);
                    }
                    $tpl_body = stripcslashes($trimmed);
                } else {
                    $tpl_body = '';
                }
            }

            if (is_string($tpl_body) && trim($tpl_body) !== '') {
                $recipient_name = ucfirst(strtolower((string) $client_name));
                $custom_body = str_replace('$name', $recipient_name, $tpl_body);
            } else {
                error_log('ADMISSION EMAIL: template row found for event_id=' . $eid . ' but body was empty/unparseable (raw length=' . strlen($raw_body) . ')');
            }
        }
    } else {
        error_log('ADMISSION EMAIL: skipped template lookup — conn=' . ($conn ? 'yes' : 'no') . ' event_id=' . var_export($event_id, true));
    }

    // No bespoke template configured for this event — fall back to a standard
    // covering note so the admission email still goes out (the full letter is the
    // attached PDF). A configured template (system_emails1, email_opt=1) overrides this.
    if ($custom_body === null) {
        error_log('ADMISSION EMAIL: no template for event_id=' . var_export($event_id, true) . ' — using default covering body.');
        $recipient_name = ucfirst(strtolower((string) $client_name));
        $prog = trim((string) $event_title);
        $custom_body = 'Dear ' . htmlspecialchars($recipient_name) . ',<br><br>'
            . 'Congratulations! Please find attached your admission letter'
            . ($prog !== '' ? ' for <strong>' . htmlspecialchars($prog) . '</strong>' : '')
            . '.<br><br>We are delighted to welcome you to Vantage Africa School of Leadership. '
            . 'If you have any questions, simply reply to this email and our team will be glad to assist.'
            . '<br><br>Warm regards,<br>Vantage Africa School of Leadership';
    }

    // Corporate variants attach an extra invitation PDF (still applies with template body).
    $is_corporate_variant = in_array($program_variant, ['corporate_sldp', 'corporate_me', 'singapore_me'], true);
    if ($is_corporate_variant && function_exists('generateCorporateMealInvitationPdf')) {
        $invitation_pdf = generateCorporateMealInvitationPdf($client_name, (string) $event_title, $location, $invite_position, $invite_organization, $invite_country, $program_variant, $invite_start_date, $invite_end_date);
        if (is_string($invitation_pdf) && is_file($invitation_pdf)) {
            $attachments[] = $invitation_pdf;
        }
    }

    $final_body = $custom_body;

    // Send email
    $email_sent = send_mail_function($client_email, $final_body, $subject, $attachments);

    // =============================================
    // LOG THE ADMISSION LETTER EMAIL
    // =============================================
    if ($conn && $ticket_id) {
        $status = $email_sent ? 'sent' : 'failed';
        $error = $email_sent ? null : 'Failed to send admission letter email';

        log_email(
            $conn,
            'ticket_congress',
            $ticket_id,
            'admission_letter',
            $client_email,
            $client_name,
            $subject,
            $attachments,
            $status,
            $error,
            null,
            $record_id
        );
    }

    return $email_sent;
}
// =============================================
// COMBINED FUNCTION - ADMISSION + INVOICE
// =============================================
// $event_id (last param) is passed through into generateAdmissionLetter().
/**
 * Corporate trainings (corporate_programs, Event.location = 'corporate#<id>').
 * Fully self-contained, currency-aware (KES by default) proforma invoice + confirmation
 * email — NO admission letter. Deliberately does not touch the USD generateInvoice /
 * generateInvoicePdf path used by every other programme.
 */
function generateCorporateTrainingInvoice($conn, $program_id, $client_email, $client_name, $start_date = null, $end_date = null, $ticket_id = null, $record_id = null) {
    $program_id = (int) $program_id;
    $res = mysqli_query($conn, "SELECT `title`, `fee`, `fee_unit`, `currency`, `location`, `start_date`, `end_date` FROM `corporate_programs` WHERE `id` = $program_id LIMIT 1");
    if (!$res || !($p = mysqli_fetch_assoc($res))) {
        return false;
    }

    $title    = trim((string) $p['title']) !== '' ? (string) $p['title'] : 'Corporate Training';
    $currency = trim((string) $p['currency']) !== '' ? strtoupper((string) $p['currency']) : 'KES';
    $fee_num  = (float) preg_replace('/[^0-9.]/', '', (string) $p['fee']);
    $fee_unit = trim((string) $p['fee_unit']);
    $venue    = trim((string) $p['location']);
    // Fall back to the training's own dates if the caller didn't pass any.
    $sd = $start_date ?: ((!empty($p['start_date']) && $p['start_date'] !== '0000-00-00') ? $p['start_date'] : null);
    $ed = $end_date   ?: ((!empty($p['end_date'])   && $p['end_date']   !== '0000-00-00') ? $p['end_date']   : null);
    $sd_fmt = $sd ? date('j M Y', strtotime($sd)) : null;
    $ed_fmt = $ed ? date('j M Y', strtotime($ed)) : null;

    $invoice_no   = generateInvoiceNumber();
    $invoice_date = date("jS F Y");

    $pay_link = ($ticket_id !== null && $ticket_id !== '')
        ? ('https://vantageafricaleaders.com/pay/complete_payment.php?ticket_id=' . urlencode($ticket_id) . '&pay=1')
        : 'https://vantageafricaleaders.com/pay.php';

    $money = function ($n) use ($currency) {
        return htmlspecialchars($currency) . ' ' . number_format((float) $n, 2);
    };
    $desc = $title . ($fee_unit !== '' ? ' (' . $fee_unit . ')' : '');
    $dates_line = $sd_fmt ? ($sd_fmt . ($ed_fmt && $ed_fmt !== $sd_fmt ? ' &#8211; ' . $ed_fmt : '')) : '';

    $html = '<html><head><meta charset="utf-8"><style>
        body{font-family:DejaVu Sans, Arial, sans-serif;color:#222;font-size:13px;}
        .wrap{padding:24px;}
        .top{border-bottom:3px solid #A85431;padding-bottom:10px;margin-bottom:18px;}
        .top h1{color:#2B5470;margin:0;font-size:22px;}
        .muted{color:#666;}
        table{width:100%;border-collapse:collapse;margin:14px 0;}
        th,td{border:1px solid #ddd;padding:9px;text-align:left;}
        th{background:#2B5470;color:#fff;}
        td.r,th.r{text-align:right;}
        .tot{background:#FDEBCB;font-weight:bold;}
        .pay{margin-top:16px;padding:12px;border:1px solid #A85431;background:#f0f8ff;}
        .pay a{color:#2B5470;font-weight:bold;word-break:break-all;}
    </style></head><body><div class="wrap">
        <div class="top">
            <h1>Proforma Invoice</h1>
            <div class="muted">Vantage Africa School of Leadership</div>
        </div>
        <p><strong>Invoice No:</strong> ' . htmlspecialchars($invoice_no) . '<br>
           <strong>Date:</strong> ' . htmlspecialchars($invoice_date) . '<br>
           <strong>Bill To:</strong> ' . htmlspecialchars($client_name) . ' (' . htmlspecialchars($client_email) . ')' .
           ($dates_line !== '' ? '<br><strong>Training Dates:</strong> ' . $dates_line : '') .
           ($venue !== '' ? '<br><strong>Venue:</strong> ' . htmlspecialchars($venue) : '') . '</p>
        <table>
            <thead><tr><th>Description</th><th class="r">Amount (' . htmlspecialchars($currency) . ')</th></tr></thead>
            <tbody>
                <tr><td>' . htmlspecialchars($desc) . '</td><td class="r">' . $money($fee_num) . '</td></tr>
                <tr class="tot"><td>Total Payable</td><td class="r">' . $money($fee_num) . '</td></tr>
            </tbody>
        </table>
        <div class="pay">
            <strong>Pay online:</strong> <a href="' . htmlspecialchars($pay_link) . '">' . htmlspecialchars($pay_link) . '</a>
        </div>
    </div></body></html>';

    $directory = 'invoices';
    $file = str_replace(" ", "_", $invoice_no) . "_" . $invoice_date;
    $pdfPath = convertHtmlToPdf($html, $directory, $file);
    if (!$pdfPath) {
        return false;
    }

    // Confirmation email (reuses the shared, currency-agnostic invoice email + logging).
    sendInvoiceEmail($client_email, $client_name, "Proforma Invoice - " . $invoice_no, $pdfPath, $sd_fmt, $ed_fmt, $venue, $conn, $ticket_id, $record_id, '');
    return $pdfPath;
}

function generateAdmissionWithInvoice($client_email, $client_name, $training_program, $invoice_items = [], $discount_percent = 0, $training_areas = [], $start_date = null, $end_date = null, $location = null, $conn = null, $ticket_id = null, $record_id = null, $corporate_variant = '', $event_amount = null, $invite_position = '', $invite_organization = '', $invite_country = '', $event_id = null) {
    global $code;

    // Corporate trainings (Event.location = 'corporate#<id>') are table-driven and run their own
    // isolated KES proforma-invoice + confirmation path — no admission letter. Early return leaves
    // every other flow (academic / virtual / corporate_variant) completely untouched.
    if ($conn && preg_match('/corporate#(\d+)/i', (string) $location, $cm)) {
        return generateCorporateTrainingInvoice($conn, (int) $cm[1], $client_email, $client_name, $start_date, $end_date, $ticket_id, $record_id);
    }

    // Use explicit corporate variant from process_payment (by event title): 'corporate_sldp', 'corporate_me', 'singapore_me' or ''
    $is_corporate_sldp = ($corporate_variant === 'corporate_sldp');
    $is_corporate_me   = ($corporate_variant === 'corporate_me');
    $is_singapore_me   = ($corporate_variant === 'singapore_me');
    $corporate_fee = 2900;

    if ($is_corporate_sldp || $is_corporate_me || $is_singapore_me) {
        if ($is_corporate_sldp) {
            // Corporate SLDP breakdown - total 2,900 USD
            $invoice_items = [
                [
                    'description'   => 'Professional training delivery & facilitation',
                    'unit_measure'  => 'Program package',
                    'quantity'      => 1,
                    'total_cost'    => 1640.00,
                ],
                [
                    'description'   => 'Training materials, manuals & learning resources',
                    'unit_measure'  => '1 package',
                    'quantity'      => 1,
                    'total_cost'    => 120.00,
                ],
                [
                    'description'   => 'Participant accounts in digital learning & reporting system',
                    'unit_measure'  => '1 package',
                    'quantity'      => 1,
                    'total_cost'    => 100.00,
                ],
                [
                    'description'   => 'Conference facilities and meals',
                    'unit_measure'  => '5 days',
                    'quantity'      => 5,
                    'total_cost'    => 750.00,
                ],
                [
                    'description'   => 'Airport transfer (arrival & departure)',
                    'unit_measure'  => '2 transfers',
                    'quantity'      => 2,
                    'total_cost'    => 90.00,
                ],
                [
                    'description'   => 'Study tour of Singapore (guided visit to institutions)',
                    'unit_measure'  => '1 package',
                    'quantity'      => 1,
                    'total_cost'    => 200.00,
                ],
            ];
        } elseif ($is_singapore_me) {
            // Corporate M&E (Singapore) breakdown - total 2,900 USD
            $invoice_items = [
                [
                    'description'   => 'Professional training delivery & facilitation',
                    'unit_measure'  => 'Program package',
                    'quantity'      => 1,
                    'total_cost'    => 1640.00,
                ],
                [
                    'description'   => 'Training materials, manuals & learning resources',
                    'unit_measure'  => '1 package',
                    'quantity'      => 1,
                    'total_cost'    => 120.00,
                ],
                [
                    'description'   => 'Participant accounts in digital learning & reporting system',
                    'unit_measure'  => '1 package',
                    'quantity'      => 1,
                    'total_cost'    => 100.00,
                ],
                [
                    'description'   => 'Conference facilities and meals',
                    'unit_measure'  => '5 days',
                    'quantity'      => 5,
                    'total_cost'    => 750.00,
                ],
                [
                    'description'   => 'Airport transfer (arrival & departure)',
                    'unit_measure'  => '2 transfers',
                    'quantity'      => 2,
                    'total_cost'    => 90.00,
                ],
                [
                    'description'   => 'Study tour of Singapore (guided visit to institutions)',
                    'unit_measure'  => '1 package',
                    'quantity'      => 1,
                    'total_cost'    => 200.00,
                ],
            ];
        } else { // generic corporate M&E (CMEP)
            $invoice_items = [
                [
                    'description'   => 'Certified Monitoring & Evaluation Professional (CMEP) - Corporate Training (training, materials, certificate; includes administrative, compliance & financial processing)',
                    'unit_measure'  => 'No. of Participants',
                    'quantity'      => 1,
                    'total_cost'    => $corporate_fee,
                ],
            ];
        }
        $training_areas = []; // passed through to letter; generateAdmissionPdf will use variant lists
    }

    // Academic programmes are Events flagged with 'academic#' in Event.location.
    // They run continuously — no intake and no fixed dates — and are often registered
    // before their fee structure has been configured. They must NOT fall through to
    // the dated-event default line items below (M&E / Resource Mobilization / Data
    // Analysis), which would invoice the client for the wrong programme at the wrong
    // price. Use the event's own fee when it has one, otherwise issue the invoice with
    // a visible placeholder so the client still receives a document and staff can see
    // exactly what is outstanding.
    $is_academic_event = (stripos((string) $location, 'academic#') !== false);

    vasl_trace('--- generateAdmissionWithInvoice ENTER'
        . ' | to=' . $client_email
        . ' | program=' . $training_program
        . ' | location=' . var_export($location, true)
        . ' | event_id=' . var_export($event_id, true)
        . ' | event_amount=' . var_export($event_amount, true)
        . ' | ticket_id=' . var_export($ticket_id, true)
        . ' | ACADEMIC=' . ($is_academic_event ? 'YES' : 'no'));

    if ($is_academic_event) {
        if (empty($invoice_items) || !is_array($invoice_items)) {
            $academic_fee = (float) $event_amount;
            if ($academic_fee > 0) {
                $invoice_items = [[
                    'description'  => trim((string) $training_program) !== '' ? $training_program : 'Academic programme',
                    'unit_measure' => 'No. of Participants',
                    'quantity'     => 1,
                    'total_cost'   => $academic_fee,
                ]];
            } else {
                $invoice_items = [[
                    'description'  => 'Invoice details will be configured here',
                    'unit_measure' => '-',
                    'quantity'     => 1,
                    'total_cost'   => 0.00,
                ]];
            }
        }
        // Continuously available: no intake dates to advertise, so the invoice and
        // email suppress their date/venue blocks rather than printing empty ones.
        // ($location keeps its 'academic#' marker here — generateAdmissionPdf still
        // needs it to select the academic curriculum. It is stripped there, and the
        // venue line it feeds is only rendered when dates exist, which they never do
        // for an academic programme.)
        $start_date = null;
        $end_date   = null;
    }

    // Check if invoice_items is provided and is an array
    if (empty($invoice_items) || !is_array($invoice_items)) {
        if($code == 1){
            $invoice_items = [
                ['description' => 'Certified Monitoring and Evaluation Professional Course', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 80.00],
                ['description' => '3 Day Training on M&E', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 320.00],
                ['description' => '3 Months Post-Training Support & Association membership', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 100.00],
                ['description' => 'Resource Mobilization & Proposal Writing Training', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 0.00],
                ['description' => 'Two Certificates', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 50.00],
                ['description' => 'Meals & Conference', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 30.00]
            ];
        } elseif($code == 3){
            $invoice_items = [
                ['description' => 'Certified Data Analysis (Multi-Tool) Course (R, STATA, SPSS, Tableau, Power BI)', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 80.00],
                ['description' => '3 Day Training on Data Analysis using (R, STATA, SPSS, Tableau, Power BI)', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 320.00],
                ['description' => 'Three Months Access to eLearning Platform', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 100.00],
                ['description' => 'One Certificate (Multi-Tool Data Analytics)', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 50.00],
                ['description' => 'Meals & Conference', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 30.00]
            ];
        } elseif($code == 2){
            $invoice_items = [
                ['description' => 'Certified Resource Mobilization and Proposal Writing Course', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 80.00],
                ['description' => '3 Day Training on Resource Mobilization and Proposal Writing', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 320.00],
                ['description' => '3 Months Post-Training Support & Association membership', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 100.00],
                ['description' => 'One Certificate', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 50.00],
                ['description' => 'Meals & Conference', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 30.00]
            ];
        } else {
            $invoice_items = [
                ['description' => 'Certified Monitoring and Evaluation Professional Course', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 80.00],
                ['description' => '3 Day Training on M&E', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 320.00],
                ['description' => '3 Months Post-Training Support & Association membership', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 100.00],
                ['description' => 'Resource Mobilization & Proposal Writing Training', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 0.00],
                ['description' => 'Two Certificates', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 50.00],
                ['description' => 'Meals & Conference', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 30.00]
            ];
        }
    }

    // Calculate total fee from invoice items
    $total_fee = 0;
    foreach ($invoice_items as $item) {
        $total_fee += $item['total_cost'];
    }

    // Apply discount to get final fee
    if ($discount_percent > 0) {
        $discount_amount = ($total_fee * $discount_percent) / 100;
        $final_fee = $total_fee - $discount_amount;
    } else {
        $final_fee = $total_fee;
    }

    // Admission-letter variant (no I/O, cannot fail) — computed before the try.
    $program_variant = null;
    if ($is_corporate_sldp) {
        $program_variant = 'corporate_sldp';
    } elseif ($is_corporate_me) {
        $program_variant = 'corporate_me';
    } elseif ($is_singapore_me) {
        $program_variant = 'singapore_me';
    }

    // Generate + send the invoice and admission letter. If any part of the heavy
    // PDF / QR / DB generation throws, the client must NOT be left without an
    // email: log the real reason and fall back to a simple approval message.
    // (Callers such as the website's process_registration.php wrap this call in
    // a silent catch, so without this the failure is invisible AND no email goes.)
    $emails_generated = false;
    try {
        // Log the welcome/registration email record.
        if ($conn && $ticket_id) {
            log_email(
                $conn,
                'ticket_congress',
                $ticket_id,
                'welcome',
                $client_email,
                $client_name,
                'Registration Confirmed - ' . $training_program,
                [],
                'sent',
                null,
                null,
                $record_id
            );
        }

        // Invoice (corporate uses optional note via invoice_items description).
        vasl_trace('  step 2: calling generateInvoice (items=' . count($invoice_items) . ', total=' . $final_fee . ')');
        generateInvoice($client_email, $client_name, $invoice_items, $discount_percent, 0, $start_date, $end_date, $location, $conn, $ticket_id, $record_id, $training_program, $corporate_variant);
        vasl_trace('  step 2: generateInvoice RETURNED — invoice email attempted');

        // Admission letter — pass $event_id as the final argument.
        vasl_trace('  step 3: calling generateAdmissionLetter');
        generateAdmissionLetter($client_email, $client_name, $training_program, $final_fee, $training_areas, $conn, $ticket_id, $record_id, $program_variant, $location, $invite_position, $invite_organization, $invite_country, $start_date, $end_date, $event_id);
        vasl_trace('  step 3: generateAdmissionLetter RETURNED — letter email attempted');

        $emails_generated = true;
    } catch (\Throwable $e) {
        vasl_trace('  !! THREW: ' . get_class($e) . ': ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine());
        error_log('[admission-email] generation failed for ' . $client_email
            . ' (' . $training_program . '): ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }

    vasl_trace('  emails_generated=' . ($emails_generated ? 'true' : 'FALSE')
        . ' | send_mail_function exists=' . (function_exists('send_mail_function') ? 'yes' : 'NO'));

    // Safety net: never leave a registered client without an email.
    if (!$emails_generated && function_exists('send_mail_function')) {
        $recipient_name  = ucwords(strtolower(trim((string) $client_name)));
        $fallback_subject = 'Vantage Africa School Of Leadership Approval';
        $fallback_body    = 'Dear ' . htmlspecialchars($recipient_name) . ',<br><br>'
            . 'Thank you for registering for <strong>' . htmlspecialchars($training_program) . '</strong> '
            . 'at Vantage Africa School of Leadership. Your registration has been received and approved. '
            . 'Our team will shortly send your official admission letter and invoice together with the next steps.<br><br>'
            . 'Warm regards,<br>Vantage Africa School of Leadership';
        $fb = send_mail_function($client_email, $fallback_body, $fallback_subject, []);
        vasl_trace('  fallback email send_mail_function returned ' . var_export($fb, true));
    }

    vasl_trace('--- generateAdmissionWithInvoice EXIT');
}

// =============================================
// HELPER FUNCTIONS
// =============================================
function generateInvoiceWithFixedDiscount($client_email, $client_name, $invoice_items, $discount_amount, $conn = null, $ticket_id = null, $record_id = null) {
    generateInvoice($client_email, $client_name, $invoice_items, 0, $discount_amount, null, null, null, $conn, $ticket_id, $record_id);
}

function createSampleInvoice() {
    $client_email = "windantechnologies@gmail.com";
    $client_name = "Tacy Isabel";

    $invoice_items = [
        ['description' => 'Certified Monitoring and Evaluation Professional Course', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 80.00],
        ['description' => '3 Day Training on M&E', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 320.00],
        ['description' => '3 Months Post-Training Support & Association membership', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 100.00],
        ['description' => 'Resource Mobilization & Proposal Writing Training', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 0.00],
        ['description' => 'Two Certificates', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 50.00],
        ['description' => 'Meals & Conference', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => 30.00]
    ];

    generateInvoice($client_email, $client_name, $invoice_items, 0, 0);
}