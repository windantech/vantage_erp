<?php
/**
 * International Invoice Generator with Email Logging
 * 
 * Complete replacement for invoice_international_.php
 * Includes all original functionality plus email logging
 */

require_once __DIR__ . '/phpqrcode/qrlib.php';
require_once __DIR__ . '/pdf_plugins/generatePdf.php';
require_once __DIR__ . '/email_plugins/vendor/autoload.php';
require_once __DIR__ . '/email_plugins/email_function.php';

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
function generateInvoicePdf($client_email, $client_name, $invoice_no, $invoice_date, $invoice_items, $subtotal, $discount_percent, $discount_value, $total_payable, $amount_in_words, $qrcode) {
    
    // Generate items HTML
    $items_html = '';
    $item_counter = 1;
    
    foreach ($invoice_items as $item) {
        $unit_cost = ($item['quantity'] > 0) ? ($item['total_cost'] / $item['quantity']) : 0;
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
    
    $discount_row = '';
    if ($discount_value > 0) {
        $discount_text = $discount_percent > 0 ? "Less {$discount_percent}% Discount" : "Discount Applied";
        $discount_row = '
        <tr>
            <td colspan="5" style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">' . $discount_text . '</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: red;">-$' . number_format($discount_value, 2) . '</td>
        </tr>';
    }
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0.5in;
            background-image: url(https://vantageafricaleaders.com/admin/assets/img/logo.png);
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px 200px;
        }
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
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
            padding: 10px; 
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
            padding: 20px; 
            margin: 10px 0;
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
            margin: 20px 0; 
        }
        .items-table th { 
            background-color: #2B5470; 
            color: white; 
            padding: 10px; 
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
                ▲ Leadership training<br>
                ▲ M&E Training and Consulting<br>
                ▲ Eval360 Digital M&E System<br>
                ▲ Strategy and HR Consulting<br>
                ▲ Virtual Training Programs
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Contact -->
    <table class="contact-table">
        <tr>
            <td>info@vantageafricaleaders.com</td>
            <td>www.vantageafricaleaders.com</td>
            <td>254 725 303 645</td>
        </tr>
    </table>

    <!-- Invoice Content -->
    <div class="invoice-box">
        
        <div class="invoice-title">PROFORMA INVOICE</div>
        
        <!-- Details -->
        <table style="width: 100%; margin: 20px 0;">
            <tr>
                <td style="width: 50%; padding: 10px;">
                    <strong>Date:</strong> ' . htmlspecialchars($invoice_date) . '<br><br>
                    <strong>Invoice No:</strong> ' . htmlspecialchars($invoice_no) . '
                </td>
                <td style="width: 50%; padding: 10px;">
                    <strong>Presented to:</strong> ' . htmlspecialchars($client_name) . '<br><br>
                    <strong>Amount Payable:</strong> <span style="color: #A85431; font-weight: bold;">$' . number_format($total_payable, 2) . '</span>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%;">No</th>
                    <th style="width: 40%;">Item</th>
                    <th style="width: 20%;">Unit of Measure</th>
                    <th style="width: 16%;">Cost per Unit (USD)</th>
                    <th style="width: 16%;">Total Cost (USD)</th>
                </tr>
            </thead>
            <tbody>
                ' . $items_html . '
                <tr style="background-color: #f8f9fa;">
                    <td colspan="4" style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">Subtotal</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">$' . number_format($subtotal, 2) . '</td>
                </tr>
                ' . $discount_row . '
                <tr style="background-color: #f8f9fa;">
                    <td colspan="4" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; font-size: 16px;">Total Payable</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; font-size: 16px; color: #A85431; white-space: nowrap;">$' . number_format($total_payable, 2) . '</td>
                </tr>
            </tbody>
        </table>

        <!-- Signature and QR Section -->
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

    $directory = 'invoices';
    $file = str_replace(" ", "_", $invoice_no) . "_" . $invoice_date;
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);
    
    return $generatedFilePath;
}

// =============================================
// MAIN INVOICE GENERATION FUNCTION
// =============================================
function generateInvoice($client_email, $client_name, $invoice_items, $discount_percent = 0, $discount_amount = 0, $start_date = null, $end_date = null, $location = null, $conn = null, $ticket_id = null, $record_id = null) {
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
    
    $generatedFilePath = generateInvoicePdf($client_email, $client_name, $invoice_no, $invoice_date, $invoice_items, $subtotal, $discount_percent, $discount_value, $total_payable, $amount_in_words, $qrcode);
    
    if ($generatedFilePath) {
        // Pass the new parameters to the email function
        sendInvoiceEmail($client_email, $client_name, "Proforma Invoice - " . $invoice_no, $generatedFilePath, $start_date, $end_date, $location, $conn, $ticket_id, $record_id);
        return $generatedFilePath;
    } else {
        echo "Failed to generate invoice PDF";
        return false;
    }
}

// =============================================
// SEND INVOICE EMAIL WITH LOGGING
// =============================================
function sendInvoiceEmail($client_email, $client_name, $subject, $generatedFilePath, $start_date = null, $end_date = null, $location = null, $conn = null, $ticket_id = null, $record_id = null) {
    $year = date("Y");
    $attachments = [$generatedFilePath];
    
    // Format dates if provided
    $training_dates = '';
    if ($start_date && $end_date) {
        $training_dates = $start_date . ' – ' . $end_date;
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
                            <h4 style="color: #2B5470; margin: 0 0 10px 0;">🎓 Training Overview</h4>
                            <p style="margin: 5px 0;"><strong>📅 Dates:</strong> ' . $training_dates . '</p>
                            <p style="margin: 5px 0;"><strong>📍 Venue:</strong> ' . $training_location . '</p>
                        </div>';
    }
    
    $body .= '
                        <p>Please find attached your official invoice for the program.</p>
                        
                        <div style="background-color: #f0f8ff; padding: 15px; border: 1px solid #A85431; margin: 15px 0; border-radius: 5px;">
                            <h4 style="color: #2B5470; margin: 0 0 15px 0;">💳 Payment Details:</h4>

                            <div style="margin-bottom: 15px;">
                                <h5 style="color: #A85431; margin: 0 0 8px 0;">💻 Online Payment:</h5>
                                <p style="margin: 0;"><a href="https://vantageafricaleaders.com/pay.php" style="color: #2B5470; text-decoration: none; font-weight: bold;">https://vantageafricaleaders.com/pay.php</a></p>
                            </div>
                        </div>
                        
                        <div style="background-color: #fff3cd; padding: 15px; border: 1px solid #ffc107; margin: 15px 0; border-radius: 5px;">
                            <p style="margin: 0; font-weight: bold; color: #856404;">📧 Once payment is made, kindly send your confirmation to <a href="mailto:isabel@vantageafricaleaders.com" style="color: #856404;">isabel@vantageafricaleaders.com</a> or <strong>+254796393864</strong> so we can finalize your registration.</p>
                        </div>
                        
                        <p style="font-size: 16px; color: #2B5470; font-weight: bold; text-align: center; margin: 20px 0;">We can\'t wait to see you in class and watch you unlock new skills, confidence, and opportunities. This is your moment – let\'s make it count! 🚀</p>
                        
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
                    <a href="" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Help</a>
                    <a href="" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Privacy Policy</a>
                    <a href="" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Terms of Service</a>
                    <a href="" style="text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Website</a>
                    <div style="color: #9ba4b3; font-size: .8rem; margin: 10px 0;">
                        We sent this email to
                        <span>' .$client_email. '</span>
                        <a href="" style="text-decoration: underline; color: #9ba4b3; font-weight: 700;">Unsubscribe</a>
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
function generateAdmissionLetter($client_email, $client_name, $training_program, $total_fee, $training_areas = [], $conn = null, $ticket_id = null, $record_id = null, $program_variant = null, $location = null) {
    $admission_no = generateAdmissionNumber();
    $admission_date = date("d/m/Y");
    
    // Generate QR code for admission letter
    $qrFile = generateInvoiceQRCode("https://vantageafricaleaders.com/admin/admissions/".$admission_no . "_" . $admission_date.".pdf");
    $qrcode = "qrcodes/" . $qrFile;
    
    generateAdmissionPdf($client_email, $client_name, $admission_no, $admission_date, $training_program, $total_fee, $training_areas, $qrcode, $conn, $ticket_id, $record_id, $program_variant, $location);
}

function generateAdmissionPdf($client_email, $client_name, $admission_no, $admission_date, $training_program, $total_fee, $training_areas = [], $qrcode = '', $conn = null, $ticket_id = null, $record_id = null, $program_variant = null, $location = null) {
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
    } elseif ($program_variant === 'singapore_me') {
        $default_training_areas = [
            '1. Foundations of Monitoring & Evaluation: results-based management, indicators, data visualization',
            '2. Framework Design & Planning: theory of change, logical frameworks, evaluation plans',
            '3. Data Systems & Digital Reporting: digital M&E systems, dashboards, decision support',
            '4. Evaluations & Practice: baseline, midterm & endline evaluations, participatory M&E',
            '5. Governance & Ethics: accountability systems, equity, learning systems',
        ];
        $intro_content = "This Strategic Monitoring & Evaluation Training (Singapore) is designed to equip participants with the capacity to design M&E systems, track performance and support decision-making. The curriculum combines frameworks, digital reporting and evaluation practice for the Singapore programme.";
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
    
    // Use provided training areas or default ones
    $areas_to_display = !empty($training_areas) ? $training_areas : $default_training_areas;
    
    $areas_section_title = ($program_variant === 'corporate_sldp' || $program_variant === 'corporate_me' || $program_variant === 'singapore_me')
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
    $url_contact         = 'https://vantageafricaleaders.com/contact.php';
    $url_elearning       = 'https://eval360.tech';
    $url_team_training   = 'https://vantageafricaleaders.com/company-nominations/';
    $title = trim((string) ($training_program ?? ''));
    $db_location = trim((string) ($location ?? ''));
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
        <p style="margin: 4px 0; font-size: 10px;">• <a href="' . htmlspecialchars($url_confirm_payment) . '" style="color: #A85431;">Confirm your Registration</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_free_sessions) . '" style="color: #A85431;">Watch training videos</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_whatsapp) . '" style="color: #25D366;">Join WhatsApp group</a></p>
        <p style="margin: 4px 0; font-size: 10px;">• <a href="' . htmlspecialchars($url_contact) . '" style="color: #A85431;">Talk to us</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_elearning) . '" style="color: #A85431;">Explore E-learning</a> &nbsp;|&nbsp; <a href="' . htmlspecialchars($url_team_training) . '" style="color: #A85431;">Training for my team</a></p>
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
                ▲ Leadership training<br>
                ▲ M&E Training and Consulting<br>
                ▲ Eval360 Digital M&E System<br>
                ▲ Strategy and HR Consulting<br>
                ▲ Virtual Training Programs
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Contact -->
    <table class="contact-table">
        <tr>
            <td>📧 info@vantageafricaleaders.com</td>
            <td>🌐 www.vantageafricaleaders.com</td>
            <td>📞 254 725 303 645</td>
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
                <strong>Congratulations!</strong> You have been accepted into the <strong>' . htmlspecialchars($training_program) . '</strong> at Vantage Africa School of Leadership.
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
            <p style="margin: 5px 0;"><strong>What\'s Next?</strong> • Confirm acceptance • Complete enrollment • Prepare for transformation</p>
            
            <p style="margin: 5px 0;">We look forward to welcoming you to our community of emerging leaders. <strong>Welcome to Vantage Africa School of Leadership!</strong></p>
        </div>

        ' . $ctas_letter_html . '

        <!-- Signature and QR Section -->
        <table style="width: 100%; margin-top: 10px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Yours sincerely,</strong><br><br>
                    <strong>Benson Kiarie</strong><br>
                    CEO & Founder<br>
                    Vantage Africa School of Leadership
                </td>
                <td style="width: 50%; vertical-align: top; text-align: center;">
                    <strong>Scan to verify:</strong><br>
                    <img src="https://vantageafricaleaders.com/admin/qr_code/signature_qrcode.png" width="70" height="70" alt="QR Code">
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
        sendAdmissionEmail($client_email, $client_name, "Admission Letter - " . $admission_no, $generatedFilePath, $conn, $ticket_id, $record_id, $training_program, $location);
        return $generatedFilePath;
    } else {
        echo "Failed to generate admission letter PDF";
        return false;
    }
}

// =============================================
// SEND ADMISSION EMAIL WITH LOGGING
// =============================================
function sendAdmissionEmail($client_email, $client_name, $subject, $generatedFilePath, $conn = null, $ticket_id = null, $record_id = null, $event_title = null, $location = null) {
    $year = date("Y");
    $attachments = [$generatedFilePath];

    // Event-specific CTA URLs (same logic as pay/thank-you.php)
    $url_confirm_payment = ($ticket_id !== null && $ticket_id !== '') ? ('https://vantageafricaleaders.com/pay/complete_payment.php?ticket_id=' . urlencode($ticket_id)) : 'https://vantageafricaleaders.com/pay.php';
    $url_free_sessions   = 'https://vantageafricaleaders.com/trainings/videos.php';
    $url_whatsapp        = 'https://wa.me/254796128454';
    $url_contact         = 'https://vantageafricaleaders.com/contact.php';
    $url_elearning       = 'https://eval360.tech';
    $url_team_training   = 'https://vantageafricaleaders.com/company-nominations/';

    $title = trim((string) ($event_title ?? ''));
    $db_location = trim((string) ($location ?? ''));
    if ($title !== '' || $db_location !== '') {
        $pay_dir = __DIR__ . '/../pay';
        if (file_exists($pay_dir . '/event_contact_map.php')) {
            include_once $pay_dir . '/event_contact_map.php';
        }
        if (file_exists($pay_dir . '/event_elearning_map.php')) {
            include_once $pay_dir . '/event_elearning_map.php';
        }
        if (file_exists($pay_dir . '/event_whatsapp_map.php')) {
            include_once $pay_dir . '/event_whatsapp_map.php';
        }
        $try_countries = [
            'Trinidad and Tobago', 'Trinidad & Tobago', 'Papua New Guinea', 'Sierra Leone', 'South Sudan', 'Sri Lanka', 'Singapore', 'Philippines', 'Bangladesh', 'Barbados',
            'S. Sudan', 'Tanzania', 'TZ', 'Cameroon', 'Botswana', 'Nepal', 'Eswatini', 'Trinidad', 'Burundi', 'Namibia', 'Fiji', 'Rwanda', 'Malawi', 'Liberia', 'Gambia',
            'Malaysia', 'Guyana', 'Jamaica', 'Mozambique', 'Lesotho', 'Zimbabwe', 'Nigeria', 'Ethiopia', 'DRC',
        ];
        $loc = null;
        foreach ($try_countries as $c) {
            if (stripos($title, $c) !== false) {
                $loc = $c;
                break;
            }
        }
        if ($loc === null && $db_location !== '') {
            $loc = preg_replace('/\s*\([^)]*\)\s*$/', '', $db_location);
            $loc = trim($loc);
        }
        if ($loc === null) {
            $loc = '';
        }
        if (function_exists('get_event_contact_phone')) {
            $contact_phone = get_event_contact_phone($loc, $title);
            if ($contact_phone !== null && $contact_phone !== '') {
                $url_contact = 'https://wa.me/' . preg_replace('/\D/', '', $contact_phone);
            }
        }
        if (function_exists('get_event_elearning_url')) {
            $elearning_url = get_event_elearning_url($loc, $title);
            if ($elearning_url !== null && $elearning_url !== '') {
                $url_elearning = $elearning_url;
            }
        }
        if (function_exists('get_event_whatsapp_url')) {
            $whatsapp_url = get_event_whatsapp_url($loc, $title);
            if ($whatsapp_url !== null && $whatsapp_url !== '') {
                $url_whatsapp = $whatsapp_url;
            }
        }
    }

    $btn_primary = 'display: inline-block; background: linear-gradient(135deg, #F4991A 0%, #E88B0F 100%); color: #fff !important; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 600; font-size: 0.95rem; text-decoration: none; margin: 4px 6px 4px 0; box-shadow: 0 4px 14px rgba(244, 153, 26, 0.35);';
    $btn_secondary = 'display: inline-block; background: #fff; color: #8B4513 !important; border: 2px solid #8B4513; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 600; font-size: 0.95rem; text-decoration: none; margin: 4px 6px 4px 0;';
    $btn_whatsapp = 'display: inline-block; background: #25D366; color: #fff !important; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 600; font-size: 0.95rem; text-decoration: none; margin: 4px 6px 4px 0;';
    $btn_outline = 'display: inline-block; background: #fff; color: #8B4513 !important; border: 2px solid #8B4513; padding: 0.75rem 1.25rem; border-radius: 10px; font-weight: 600; font-size: 0.95rem; text-decoration: none; margin: 4px 6px 4px 0;';

    $ctas_html = '<p style="margin: 1.25rem 0 0.5rem;"><strong>What you can do next:</strong></p>
        <p style="margin: 0.5rem 0;">
            <a href="' . htmlspecialchars($url_confirm_payment) . '" style="' . $btn_primary . '">Confirm your Registration</a>
            <a href="' . htmlspecialchars($url_free_sessions) . '" style="' . $btn_secondary . '">Watch training videos</a>
            <a href="' . htmlspecialchars($url_whatsapp) . '" style="' . $btn_whatsapp . '">Join WhatsApp group</a>
        </p>
        <p style="margin: 0.5rem 0;">
            <a href="' . htmlspecialchars($url_contact) . '" style="' . $btn_outline . '">Talk to us</a>
            <a href="' . htmlspecialchars($url_elearning) . '" style="' . $btn_outline . '">Explore E-learning</a>
            <a href="' . htmlspecialchars($url_team_training) . '" style="' . $btn_outline . '">Training for my team</a>
        </p>';

    $body = '<html>' .
        '<head></head>' .
        '<body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">' .
            '<div style="border: solid 1px #d1d3e2;">
                <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
                <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
                <div style="padding: 0 .5rem">
                    <h5><b>' . $subject . '</b></h5>
                    <p>Dear ' . htmlspecialchars($client_name) . ',</p>
                    <div>
                        <p><strong>Congratulations!</strong> 🎉</p>
                        <p>We are thrilled to confirm your admission to our training program. Please find attached your official admission letter with all the important details.</p>
                        <p><strong>What\'s Next:</strong></p>
                        <ul>
                            <li>Review your admission letter for training details and fee structure</li>
                            <li>Complete your payment using the invoice that will be sent separately</li>
                            <li>We will contact you with training schedules and joining instructions</li>
                        </ul>
                        ' . $ctas_html . '
                        <p style="margin: 1.25rem 0 0;">We look forward to having you join our learning community and wish you great success in your training journey!</p>
                    </div>
                </div>
                <div style="padding: .5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                    <div style="color: #9ba4b3; font-size: .8rem; margin: 10px 0;">
                        We sent this email to <span>' . $client_email . '</span>
                    </div>
                    <div style="color: #9ba4b3; font-size: .8rem;">
                        &copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved
                    </div>
                </div>
            </div>' .
        '</body></html>';

    // Send email
    $email_sent = send_mail_function($client_email, $body, $subject, $attachments);
    
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
function generateAdmissionWithInvoice($client_email, $client_name, $training_program, $invoice_items = [], $discount_percent = 0, $training_areas = [], $start_date = null, $end_date = null, $location = null, $conn = null, $ticket_id = null, $record_id = null, $corporate_variant = '', $event_amount = null) {
    global $code;
    
    // Use explicit corporate variant from process_payment (by event title): 'corporate_sldp', 'corporate_me', 'singapore_me', or ''
    $is_corporate_sldp = ($corporate_variant === 'corporate_sldp');
    $is_corporate_me   = ($corporate_variant === 'corporate_me');
    $is_singapore_me   = ($corporate_variant === 'singapore_me');
    $corporate_fee = 2900;
    
    if ($is_corporate_sldp || $is_corporate_me || $is_singapore_me) {
        if ($is_corporate_sldp) {
            $invoice_items = [
                ['description' => 'Strategic Leadership Development Programme (SLDP) - Corporate (training, materials, certificate; includes administrative, compliance & financial processing)', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => $corporate_fee],
            ];
        } elseif ($is_singapore_me) {
            $invoice_items = [
                ['description' => 'Strategic Monitoring & Evaluation Training - Singapore (training, materials, certificate; includes administrative, compliance & financial processing)', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => $corporate_fee],
            ];
        } else {
            $invoice_items = [
                ['description' => 'Certified Monitoring & Evaluation Professional (CMEP) - Corporate Training (training, materials, certificate; includes administrative, compliance & financial processing)', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => $corporate_fee],
            ];
        }
        $training_areas = []; // passed through to letter; generateAdmissionPdf will use variant lists
    }
    
    // Check if invoice_items is provided and is an array
    if (empty($invoice_items) || !is_array($invoice_items)) {
        if($code == 1){
            // Non-corporate M&E: use event price from Event.early_amount when provided, so invoice matches other M&E training events
            if ($event_amount !== null && $event_amount > 0) {
                $invoice_items = [
                    ['description' => 'Certified Monitoring and Evaluation Professional Course (training, materials, certificate, post-training support, meals)', 'unit_measure' => 'No. of Participants', 'quantity' => 1, 'total_cost' => $event_amount],
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
    
    // =============================================
    // LOG WELCOME/REGISTRATION EMAIL
    // =============================================
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
    
    // Generate invoice with training details (corporate uses optional note via invoice_items description)
    generateInvoice($client_email, $client_name, $invoice_items, $discount_percent, 0, $start_date, $end_date, $location, $conn, $ticket_id, $record_id);
    
    // Generate admission letter (with corporate variant for SLDP/CMEP/Singapore M&E content)
    $program_variant = null;
    if ($is_corporate_sldp) {
        $program_variant = 'corporate_sldp';
    } elseif ($is_corporate_me) {
        $program_variant = 'corporate_me';
    } elseif ($is_singapore_me) {
        $program_variant = 'singapore_me';
    }
    generateAdmissionLetter($client_email, $client_name, $training_program, $final_fee, $training_areas, $conn, $ticket_id, $record_id, $program_variant, $location);
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