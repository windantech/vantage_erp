<?php
/**
 * Receipt Generator with Discount Support
 * For Vantage Africa School of Leadership
 */

require_once 'email_plugins/vendor/autoload.php';
require_once 'email_plugins/email_function.php';
require_once 'amount_to_words/formatter.php';
require_once 'pdf_plugins/generatePdf.php';
require_once 'phpqrcode/qrlib.php';
require_once 'includes/email_log_functions.php';

/**
 * Generate QR Code for receipt verification
 */
function generateQRCodeForReceipt($text, $filename = null) {
    if ($filename === null) {
        $filename = uniqid('qr_', true) . '.png';
    }

    $path = __DIR__ . '/qrcodes/';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    $fullPath = $path . $filename;
    QRcode::png($text, $fullPath, QR_ECLEVEL_L, 10);

    return $filename;
}

/**
 * Generate random receipt number
 */
function generateReceiptNumber() {
    $prefix = "RN-";
    $part1 = rand(100, 999);
    $part2 = substr(str_shuffle(strtoupper("abcdefghijklmnopqrstuvwxyz0123456789")), 0, 3);
    $part3 = rand(10, 99);

    return $prefix . $part1 . $part2 . $part3;
}

/**
 * Main function to generate receipt with discount support
 */
function generateReceiptWithDiscount($conn, $data) {
    $email_address = $data['email'];
    $recipient_name = $data['recipient_name'];
    $amount_paid = floatval($data['amount_paid']);
    $discount_amount = floatval($data['discount_amount'] ?? 0);
    $total_credit = floatval($data['total_credit']);
    $amount_due = floatval($data['amount_due']);
    $total_paid = floatval($data['total_paid']);
    $purpose = $data['purpose'];
    $purpose_id = $data['purpose_id'];
    $payment_method = $data['payment_method'];
    $currency_original = $data['currency_original'] ?? 'USD';
    $amount_original = floatval($data['amount_original'] ?? $amount_paid);
    $source_type = $data['source_type'];
    $source_id = $data['source_id'];
    $record_id = $data['record_id'] ?? null;
    
    $receipt_no = generateReceiptNumber();
    $receipt_date = date("jS F Y");
    $year = date("Y");
    
    // Generate QR Code
    $qrFile = generateQRCodeForReceipt("https://vantageafricaleaders.com/receipts/" . $receipt_no);
    $qrcode = __DIR__ . "/qrcodes/" . $qrFile;
    
    // Format amounts
    $amount_formatted = "$ " . number_format($amount_paid, 2);
    $discount_formatted = "$ " . number_format($discount_amount, 2);
    $total_credit_formatted = "$ " . number_format($total_credit, 2);
    
    // Amount in words (total credit including discount)
    $amt_in_words = numberToWords($total_credit) . " USD only.";
    
    // Balance calculation
    $balance = $amount_due - $total_paid;
    $balance_formatted = ($balance > 0) ? "$ " . number_format($balance, 2) : "NIL";
    
    // Payment method display
    $payment_method_display = getPaymentMethodName($payment_method);
    
    // Original currency note
    $currency_note = '';
    if ($currency_original === 'KES') {
        $currency_note = '(KES ' . number_format($amount_original, 2) . ' @ 129)';
    }
    
    // Generate PDF
    $html = generateReceiptHtml(
        $recipient_name,
        $receipt_no,
        $receipt_date,
        $amount_formatted,
        $discount_amount,
        $discount_formatted,
        $total_credit_formatted,
        $amt_in_words,
        $purpose,
        $payment_method_display,
        $currency_note,
        $amount_due,
        $total_paid,
        $balance_formatted,
        $qrcode,
        $year
    );
    
    $directory = 'receipts';
    $file = $receipt_no . "_" . str_replace(' ', '_', $receipt_date);
    
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);
    
    if ($generatedFilePath) {
        // Send email
        $subject = "Payment Receipt - " . $purpose;
        $email_sent = sendReceiptEmailWithDiscount($email_address, $recipient_name, $subject, $generatedFilePath, $discount_amount);
        
        // Log email
        $status = $email_sent ? 'sent' : 'failed';
        $error = $email_sent ? null : 'Failed to send receipt email';
        
        log_email(
            $conn,
            $source_type,
            $source_id,
            'receipt',
            $email_address,
            $recipient_name,
            $subject,
            [$generatedFilePath],
            $status,
            $error,
            null,
            $record_id
        );
        
        return ['success' => true, 'file' => $generatedFilePath];
    }
    
    return ['success' => false, 'message' => 'Failed to generate PDF'];
}

/**
 * Get friendly payment method name
 */
function getPaymentMethodName($code) {
    $methods = [
        'MP-code' => 'M-Pesa',
        'TILL-code' => 'Till Number',
        'WU-MTCN' => 'Western Union',
        'Mukuru-code' => 'Mukuru',
        'LocalRep-country' => 'Local Representative',
        'EQ-code' => 'Equity Bank',
        'EC-bank' => 'Echo Bank',
        'AM-code' => 'Airtel Money',
        'Dahabsh-code' => 'Dahabshil',
        'DPO' => 'DPO Online',
        'Money-gram' => 'Money Gram',
        'Ria' => 'Ria'
    ];
    
    return $methods[$code] ?? $code;
}

/**
 * Generate Receipt HTML with discount section
 */
function generateReceiptHtml($recipient_name, $receipt_no, $receipt_date, $amount_formatted, $discount_amount, $discount_formatted, $total_credit_formatted, $amt_in_words, $purpose, $payment_method, $currency_note, $amount_due, $total_paid, $balance_formatted, $qrcode, $year) {
    
    $discount_row = '';
    if ($discount_amount > 0) {
        $discount_row = '
        <tr>
            <td style="padding: 5px;">Discount Applied</td>
            <td style="padding: 5px; color: green;">' . $discount_formatted . '</td>
        </tr>';
    }
    
    $html = '
    <html>
    <head></head>
    <body style="font-family: Arial, sans-serif; background-color: #f3f3f3; margin: 0; padding: 20px;">
        <div style="width: 210mm; height: 297mm; padding: 0; margin: 0 auto; background-color: #fff;">
            <!-- Header -->
            <table style="color: black; padding: 10px 40px 0 40px; margin-bottom: 5px; width: 210mm; height: 45mm;">
                <tbody>
                    <tr>
                        <td style="width: 30%;">
                            <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" style="width: inherit; height: 80px; object-fit: contain;" alt="Logo">
                        </td>
                        <td style="width: 30%; font-size: 16px; font-family: Times New Roman, Times, serif;">
                            Astrol Business Center<br>
                            6<sup>th</sup> Floor, C603,<br>
                            Thika Road Opposite Garden City,<br>
                            Nairobi
                        </td>
                        <td style="font-size: 16px; border-left: solid 2px #A85431; padding-left: 10px; font-family: Times New Roman, Times, serif;">
                            <span>▶ Leadership training</span><br>
                            <span>▶ CV & Interview coaching</span><br>
                            <span>▶ Strategic Consulting</span><br>
                            <span>▶ HR Consulting</span><br>
                            <span>▶ Online Book club</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="padding: 0 40px; color: #2B5470; border-bottom: solid 5px #A85431;"></div>

            <table style="margin: 10px 0; padding: 0 40px; color: #2B5470; width: 100%; text-align: center;">
                <tbody>
                    <tr>
                        <td>📧 info@vantageafricaleaders.com</td>
                        <td>🌐 www.vantageafricaleaders.com</td>
                        <td>📞 +254 725 303 645</td>
                    </tr>
                </tbody>
            </table>

            <!-- Receipt Content -->
            <div style="height: 200mm; border: solid 2px #FEB958; margin: 0 20px; padding: 10px; font-family: Times New Roman, Times, serif;">
                <h2 style="margin: 0; text-align: center;">
                    <span style="border-bottom: dashed 2px black;">PAYMENT RECEIPT</span>
                </h2>
                
                <p style="text-align: left;"><b>Date:</b> <span style="border-bottom: dotted 1px #A85431;">' . $receipt_date . '</span></p>
                <p style="text-align: right;"><b>Receipt No:</b> <span style="border-bottom: dotted 1px #A85431;">' . $receipt_no . '</span></p>
                <p style="text-align: left;"><b>Received From:</b> <span style="border-bottom: dotted 1px #A85431;">' . $recipient_name . '</span></p>
                <p style="text-align: left;"><b>Total Amount Credited:</b> <span style="border-bottom: dotted 1px #A85431;">' . $amt_in_words . '</span></p>
                <p style="text-align: left;"><b>Purpose of Payment:</b> <span style="border-bottom: dotted 1px #A85431;">' . $purpose . '</span></p>

                <!-- Payment Details Table -->
                <table style="width: 100%; margin-top: 20px;">
                    <tr>
                        <td style="width: 50%; vertical-align: top;">
                            <table border="1" cellspacing="0" cellpadding="0" style="width: 90%; border-collapse: collapse;">
                                <tr>
                                    <th colspan="2" style="padding: 5px; background-color: #f0f0f0;">Payment Details:</th>
                                </tr>
                                <tr>
                                    <td style="padding: 5px;">' . $payment_method . '</td>
                                    <td style="padding: 5px;">' . $amount_formatted . ' ' . $currency_note . '</td>
                                </tr>
                                ' . $discount_row . '
                                <tr style="background-color: #e8f5e9;">
                                    <td style="padding: 5px;"><strong>Total Credit</strong></td>
                                    <td style="padding: 5px;"><strong>' . $total_credit_formatted . '</strong></td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 50%; vertical-align: top;">
                            <table border="1" cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <th colspan="2" style="padding: 5px; background-color: #f0f0f0;">Account Summary:</th>
                                </tr>
                                <tr>
                                    <td style="padding: 5px;">Amount Due</td>
                                    <td style="padding: 5px;">$ ' . number_format($amount_due, 2) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px;">Total Paid</td>
                                    <td style="padding: 5px;">$ ' . number_format($total_paid, 2) . '</td>
                                </tr>
                                <tr style="background-color: #fff3e0;">
                                    <td style="padding: 5px;"><strong>Balance</strong></td>
                                    <td style="padding: 5px;"><strong>' . $balance_formatted . '</strong></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="text-align: left; margin-top: 30px;"><b>Amount received by:</b> <span style="border-bottom: dotted 1px #A85431;">Vantage Africa School of Leadership</span></p>
                
                <p style="text-align: left; margin-top: 20px;"><b>Scan to verify:</b></p>
                <img src="' . $qrcode . '" alt="QR Code" width="120" height="120">
            </div>
            
            <!-- Footer -->
            <div style="width: 100%; border-top: solid 10px #4F2020;">
                <div style="background: linear-gradient(135deg, #4F2020 0%, #A85431 100%); height: 50px; text-align: center; color: white; font-family: Times New Roman, serif; font-size: 16px; font-weight: bold; letter-spacing: 2px; line-height: 50px;">
                    Developing Transformational Leaders
                </div>
            </div>
        </div>
    </body>
    </html>
    ';

    return $html;
}

/**
 * Send receipt email with discount mention
 */
function sendReceiptEmailWithDiscount($email_address, $recipient_name, $subject, $generatedFilePath, $discount_amount = 0) {
    $year = date("Y");
    $attachments = [$generatedFilePath];
    
    $discount_note = '';
    if ($discount_amount > 0) {
        $discount_note = '<p style="color: #28a745;"><strong>A discount of $' . number_format($discount_amount, 2) . ' has been applied to your account.</strong></p>';
    }

    $body = '<html>
    <head></head>
    <body style="background-color: #e0e0e0;">
        <div style="border: solid 1px #d1d3e2; max-width: 600px; margin: 0 auto; background: #fff;">
            <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="Logo">
            <hr style="border: solid 1px #d1d3e2; margin: 8px 0;">
            <div style="padding: 0 1rem;">
                <h4><b>Payment Receipt</b></h4>
                <p>Dear ' . htmlspecialchars($recipient_name) . ',</p>
                <p>Thank you for your payment.</p>
                ' . $discount_note . '
                <p>Kindly find attached the confirmation receipt of your payment.</p>
                <p>If you have any questions regarding this receipt, please don\'t hesitate to contact us.</p>
                <p><strong>Warm regards,</strong><br>
                <span style="color: #A85431; font-weight: bold;">Vantage Africa School of Leadership</span></p>
            </div>
            <div style="padding: .5rem; border-top: solid 1px #d1d3e2; text-align: center; background: #f8f9fa;">
                <div style="margin: 10px 0;">
                    <a href="#" style="margin-right: 10px; text-decoration: none; color: #9ba4b3;">Facebook</a>
                    <a href="#" style="margin-right: 10px; text-decoration: none; color: #9ba4b3;">Instagram</a>
                    <a href="#" style="margin-right: 10px; text-decoration: none; color: #9ba4b3;">Twitter</a>
                    <a href="#" style="text-decoration: none; color: #9ba4b3;">YouTube</a>
                </div>
                <div style="color: #9ba4b3; font-size: .8rem;">
                    We sent this email to ' . htmlspecialchars($email_address) . '
                </div>
                <div style="color: #9ba4b3; font-size: .8rem; margin-top: 5px;">
                    &copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved
                </div>
            </div>
        </div>
    </body>
    </html>';

    return send_mail_function($email_address, $body, $subject, $attachments);
}
