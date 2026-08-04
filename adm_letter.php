<?php
/**
 * Admission Letter Generator with Email Logging
 * 
 * This file generates and sends admission letters and invoices with automatic logging.
 * 
 * Required variables before including this file:
 * - $conn (database connection)
 * - $email_address
 * - $recipient_name  
 * - $subject
 * - $adm_no
 * - $amount
 * - $purpose (program name)
 * - $body (email body HTML)
 * - $adm (admission letter content)
 * - $entry_id
 * - $record_id (optional - the primary key ID from register table)
 */

// Include email logging functions
require_once __DIR__ . '/includes/email_log_functions.php';

$invoice_date = date("jS F Y");
$letter_date = date("jS F Y");
$invoice_no = $adm_no;

// A course/programme whose admission-letter or invoice content has not been set up
// yet must still receive a complete, valid pair of PDFs — with a visible placeholder
// telling staff what is missing — rather than a blank page or no email at all. This
// matters most for academic programmes (Event.location contains 'academic#'), which
// run continuously with no intake or fixed dates and are often registered before
// their letter text and fee structure have been configured.
$ADM_PLACEHOLDER     = '<p><em>ADM letter to be configured here.</em></p>';
$INVOICE_PLACEHOLDER = 'Invoice details will be configured here';

$adm = (isset($adm) && trim(strip_tags((string) $adm)) !== '') ? $adm : $ADM_PLACEHOLDER;

// $purpose doubles as the invoice line-item description and as the programme name in
// prose, so keep a readable version for the latter — the placeholder must not end up
// mid-sentence as "registering for Invoice details will be configured here".
$purpose_name = (isset($purpose) && trim((string) $purpose) !== '') ? trim((string) $purpose) : 'your programme';
$purpose      = (isset($purpose) && trim((string) $purpose) !== '') ? $purpose : $INVOICE_PLACEHOLDER;

// Keep the fee blank-with-placeholder rather than printing a misleading 0.00.
$amount_raw = isset($amount) ? trim((string) $amount) : '';
$has_amount = ($amount_raw !== '' && (float) str_replace(',', '', $amount_raw) > 0);
$amount     = $has_amount ? $amount_raw : $INVOICE_PLACEHOLDER;

// Generate Admission Letter PDF
function generatePdf($email_address, $recipient_name, $subject, $adm_no, $letter_date, $amount, $purpose, $body, $adm, $has_amount = true)
{
    $amount_cell = $has_amount
        ? $amount
        : '<span style="font-style: italic; color: #A85431;">' . $amount . '</span>';

    $html = '
    <html>
    <head></head>
    <body style="font-family: Arial, sans-serif; background-color: #f3f3f3; margin: 0; padding: 20px;">
        <div style="width: 210mm; min-height: 297mm; padding: 15px 0; margin: 0 auto; background-color: #fff;">
            <table style="color: black; padding: 10px 40px 0 40px; margin-bottom: 5px; width: 210mm; height: 45mm;">
                <tbody>
                    <tr>
                        <td style="width: 30%;">
                            <table style="color: #2B5470;">
                                <tbody>
                                    <tr>
                                        <td style="display: block; padding: 0;">
                                            <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" style="width: inherit; height: 80px; object-fit: contain;" alt="">
                                        </td>
                                        <td style=" width: 10%;"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 30%; font-size: 16px; font-family: Times New Roman, Times, serif;">
                            Astrol Business Center
                            6<sup>th</sup> Floor, C603,
                            Thika Road Opposite Garden City,
                            Nairobi
                        </td>
                        <td style="font-size: 16px; border-left: solid 2px #A85431; padding-left: 10px; font-family: Times New Roman, Times, serif;">
                            <span>Leadership training</span><br>
                            <span>CV & Interview coaching</span><br>
                            <span>Strategic Consulting</span><br>
                            <span>HR Consulting</span><br>
                            <span>Online Book club</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="padding: 0 40px; color: #2B5470; border-bottom: solid 5px #A85431;"></div>

            <table style="margin: 10px 0; padding: 0 40px; color: #2B5470; width: 800px; height: 8mm; text-align: center;">
                <tbody>
                    <tr>
                        <td style="width: 38%;">info@vantageafricaleaders.com</td>
                        <td style="width: 38%;">www.vantageafricaleaders.com</td>
                        <td>254 725 303 645</td>
                    </tr>
                </tbody>
            </table>

            <div style="border: solid 2px #FEB958; margin: 0 20px; padding: 10px; font-family: Times New Roman, Times, serif;">
                <p style="text-align: right; margin: 1px;"><b>Date:</b> <span style="border-bottom: dotted 1px #A85431;">' . $letter_date . '</span></p>
                <p style="text-align: left; margin: 0 1px;"><b>Admission No:</b> <span style="border-bottom: dotted 1px #A85431;">' . $adm_no . '</span> <br><b>Name: </b> <span style="border-bottom: dotted 1px #A85431;">' . $recipient_name . '</span></p>
                
                <p style="text-align: left; margin: 0 1px;">Dear ' . $recipient_name . ',</p><br>
                '.$adm.'

                <table style="margin-top: 4px; width: 100%; border-collapse: collapse;" border="1" cellspacing="0" cellpadding="0">
                    <tr style="background: #D96800;">
                        <th colspan="2" style="padding: 5px; white-space: nowrap; color: white; text-align: left; text-transform: uppercase;">Fee Structure</th>
                    </tr>
                    <tr style="background: #FEB958;">
                        <th style="padding: 5px; white-space: nowrap; color: black; text-align: left;">Description</th>
                        <th style="padding: 5px; white-space: nowrap; color: black; text-align: left;">Amount</th>
                    </tr>
                    <tbody>
                        <tr>
                            <td style="padding: 5px; width: 50%;">Course Fees</td>
                            <td style="padding: 5px; width: 50%;">'. $amount_cell .'</td>
                        </tr>
                    </tbody>
                </table>

                <p style="text-align: left; margin-bottom: 1px;">
                    We wish you a successful and enjoyable training journey. We also hope that you will find all of your needs satisfactorily met here. 
                    Thank you for your prompt attention and for choosing Vantage Africa School of Leadership.
                </p>
               
                <p style="text-align: left; margin-bottom: 1px;">Yours faithfully,</p>

                <p style="text-align: left; margin: 0 1px;">
                    Dr. Benson Kiarie, PhD<br>
                    CEO, Vantage Africa School of Leadership<br>
                    Email: bkiarie@vantageafricaleaders.com<br>
                    Phone: +254725303645
                </p>
            </div>
        </div>
    </body>
    </html>';

    $directory = 'admin/letters';
    $file = $recipient_name . "_" . date("jS F Y");
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);

    if ($generatedFilePath) {
        return $generatedFilePath;
    } else {
        return "Failed to generate pdf";
    }
}

// Generate Invoice PDF
function generatePdf_invoice($email_address, $recipient_name, $subject, $invoice_no, $invoice_date, $amount, $purpose, $entry_id, $has_amount = true)
{
    // With no fee configured, a single spanning placeholder row reads correctly;
    // repeating the placeholder sentence in the Cost/Total columns would not.
    if ($has_amount) {
        $items_rows_html = '
                    <tr>
                        <td style="padding: 5px;">1</td>
                        <td style="padding: 5px;">'.$purpose.'</td>
                        <td style="padding: 5px;">1</td>
                        <td style="padding: 5px; text-align: center;">1</td>
                        <td style="padding: 5px; text-align: center;">'.$amount.'</td>
                        <td style="padding: 5px; text-align: center;">'.$amount.'</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="padding: 5px; text-align: right;"><b>Total Payable</b></td>
                        <td colspan="2" style="padding: 5px; text-align: center; background: #D96800; color: white;">'.$amount.'</td>
                    </tr>';
    } else {
        $items_rows_html = '
                    <tr>
                        <td colspan="6" style="padding: 10px; text-align: center; font-style: italic; color: #A85431;">'.$amount.'</td>
                    </tr>';
    }

    $html = '
    <html>
    <head></head>
    <body style="font-family: Arial, sans-serif; background-color: #f3f3f3; margin: 0; padding: 20px;">
        <div style="width: 210mm; Height: 297mm; padding: 0; margin: 0 auto; background-color: #fff;">
            <table style="color: black; padding: 10px 40px 0 40px; margin-bottom: 5px; width: 210mm; height: 45mm;">
                <tbody>
                    <tr>
                        <td style="width: 30%;">
                            <img src="admin/assets/img/logo.png" style="width: inherit; height: 80px; object-fit: contain;" alt="">
                        </td>
                        <td style="width: 30%; font-size: 16px; font-family: Times New Roman, Times, serif;">
                            Astrol Business Center 6<sup>th</sup> Floor, C603, Thika Road Opposite Garden City, Nairobi
                        </td>
                        <td style="font-size: 16px; border-left: solid 2px #A85431; padding-left: 10px; font-family: Times New Roman, Times, serif;">
                            Leadership training<br>CV & Interview coaching<br>Strategic Consulting<br>HR Consulting<br>Online Book club
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="padding: 0 40px; color: #2B5470; border-bottom: solid 5px #A85431;"></div>

            <div style="border: solid 2px #FEB958; margin: 0 20px; padding: 10px; font-family: Times New Roman, Times, serif;">
                <h2 style="margin: 0; text-align: center;"><span style="border-bottom: dashed 2px black">PROFORMA INVOICE</span></h2>
                <p style="text-align: left; margin: 1px;"><b>Date:</b> <span style="border-bottom: dotted 1px #A85431;">' . $invoice_date . '</span></p>
                <p style="text-align: right; margin: 1px;"><b>Invoice No:</b> <span style="border-bottom: dotted 1px #A85431;">' . $invoice_no . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Presented to:</b> <span style="border-bottom: dotted 1px #A85431;">' . $recipient_name . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Amount Payable:</b> <span style="border-bottom: dotted 1px #A85431;">' . $amount . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Purpose of Payment:</b> <span style="border-bottom: dotted 1px #A85431;">' . $purpose . '</span></p>

                <table style="margin-top: 4px; width: 100%;" border="1" cellspacing="0" cellpadding="0">
                    <tr style="background: #D96800;">
                        <th style="padding: 5px; color: white;">No</th>
                        <th style="padding: 5px; color: white;">Item</th>
                        <th style="padding: 5px; color: white;">Unit</th>
                        <th style="padding: 5px; color: white;">Qty</th>
                        <th style="padding: 5px; color: white;">Cost (USD)</th>
                        <th style="padding: 5px; color: white;">Total (USD)</th>
                    </tr>
                    '.$items_rows_html.'
                </table>

                <h3 style="text-align: left; margin-bottom: 0;">HOW TO PAY:</h3>
                <p style="text-align: left; margin: 1px;"><b>Direct Bank Transfer</b></p>
                <ol style="text-align: left; margin: 1px; font-size: 14px;">
                    <li>Swift code: EQBLKENA</li>
                    <li>Branch Code: 68026</li>
                    <li>Bank Name: Equity Bank Kenya</li>
                    <li>Branch: Kimathi</li>
                    <li>Account Name: Vantage Africa School of Leadership</li>
                    <li>Account number: 0260280135396</li>
                </ol>
                
                <p style="text-align: left; margin-bottom: 1px;"><b>Online Payments</b></p>
                <p style="text-align: left; margin: 1px 25px; font-size: 14px;">To pay online, Click <a href="https://vantageafricaleaders.com/proceed_pay.php?id='.$entry_id.'" target="_blank">here</a></p>

                <p style="text-align: left; margin-bottom: 1px;">Yours sincerely,</p>
                <p style="text-align: left; margin: 0 1px;">Benson Kiarie, CEO & Founder,<br>Vantage Africa School of Leadership.</p>
            </div>
        </div>
    </body>
    </html>';

    $directory = 'admin/invoices';
    $file = $invoice_no . "_" . $invoice_date;
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);

    if ($generatedFilePath) {
        return $generatedFilePath;
    } else {
        return "Failed to generate pdf";
    }
}

/**
 * Send email with logging
 * This replaces the original sendEmail function
 */
function sendEmailWithLogging($conn, $entry_id, $record_id, $email_address, $recipient_name, $subject, $adm_letter_path, $invoice_path, $body)
{
    $attachments = [];
    
    if ($adm_letter_path && file_exists($adm_letter_path)) {
        $attachments[] = $adm_letter_path;
    }
    if ($invoice_path && file_exists($invoice_path)) {
        $attachments[] = $invoice_path;
    }

    // Send the email
    $email_sent = send_mail_function($email_address, $body, $subject, $attachments);
    
    // Determine status
    $status = $email_sent ? 'sent' : 'failed';
    $error = $email_sent ? null : 'Failed to send email';
    
    // Log Welcome Email
    log_email(
        $conn,
        'register',
        $entry_id,
        'welcome',
        $email_address,
        $recipient_name,
        $subject,
        [],
        $status,
        $error,
        null,
        $record_id
    );
    
    // Log Admission Letter
    if ($adm_letter_path && file_exists($adm_letter_path)) {
        log_email(
            $conn,
            'register',
            $entry_id,
            'admission_letter',
            $email_address,
            $recipient_name,
            'Admission Letter - VASL-' . $entry_id,
            [$adm_letter_path],
            $status,
            $error,
            null,
            $record_id
        );
    }
    
    // Log Invoice
    if ($invoice_path && file_exists($invoice_path)) {
        log_email(
            $conn,
            'register',
            $entry_id,
            'invoice',
            $email_address,
            $recipient_name,
            'Proforma Invoice - VASL-' . $entry_id,
            [$invoice_path],
            $status,
            $error,
            null,
            $record_id
        );
    }
    
    return $email_sent;
}

// The email body itself must never be blank — a template row that failed to decode
// (json_decode returning null) previously produced an empty-bodied approval email.
if (!isset($body) || !is_string($body) || trim(strip_tags($body)) === '') {
    $body = 'Dear ' . htmlspecialchars($recipient_name) . ',<br><br>'
          . 'Thank you for registering for <strong>' . htmlspecialchars($purpose_name) . '</strong> '
          . 'at Vantage Africa School of Leadership. Please find your admission letter and '
          . 'proforma invoice attached.<br><br>'
          . 'Warm regards,<br>Vantage Africa School of Leadership';
}

// Generate PDFs. Each is generated independently: a failure building one document
// must not stop the other from being attached, and must never stop the email from
// going out. Previously an mPDF exception here (e.g. an unwritable output dir) was
// fatal, so the client received nothing at all.
$adm_letter_path = null;
$invoice_path = null;

try {
    $adm_letter_path = generatePdf($email_address, $recipient_name, $subject, $adm_no, $letter_date, $amount, $purpose, $body, $adm, $has_amount);
} catch (\Throwable $e) {
    error_log('[adm_letter] admission letter PDF failed for ' . $email_address
        . ' (' . $purpose . '): ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}

try {
    $invoice_path = generatePdf_invoice($email_address, $recipient_name, $subject, $invoice_no, $invoice_date, $amount, $purpose, $entry_id, $has_amount);
} catch (\Throwable $e) {
    error_log('[adm_letter] invoice PDF failed for ' . $email_address
        . ' (' . $purpose . '): ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}

// Get record_id if not set
if (!isset($record_id)) {
    $record_query = mysqli_query($conn, "SELECT id FROM register WHERE entry_id = '$entry_id' LIMIT 1");
    if ($record_query && mysqli_num_rows($record_query) > 0) {
        $record_id = mysqli_fetch_assoc($record_query)['id'];
    } else {
        $record_id = null;
    }
}

// Send email with logging
sendEmailWithLogging($conn, $entry_id, $record_id, $email_address, $recipient_name, $subject, $adm_letter_path, $invoice_path, $body);