<?php
require_once 'email_plugins/vendor/autoload.php';
require_once 'email_plugins/email_function.php';
require_once 'amount_to_words/formatter.php';
require_once 'pdf_plugins/generatePdf.php';
require_once 'phpqrcode/qrlib.php';
require_once 'includes/email_log_functions.php';

function generateQRCode($text, $filename = null) {
    if ($filename === null) {
        $filename = uniqid('qr_', true) . '.png';
    }

    $path = __DIR__ . 'qrcodes/';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    $fullPath = $path . $filename;

    // Generate QR code
    QRcode::png($text, $fullPath, QR_ECLEVEL_L, 10);

    return $filename;
}

function generateRandomCode() {
    $prefix = "RN-";
    $part1 = rand(100, 999);
    $part2 = substr(str_shuffle(strtoupper("abcdefghijklmnopqrstuvwxyz0123456789")), 0, 3);
    $part3 = rand(10, 99);

    return $prefix . $part1 . $part2 . $part3;
}

function sum_paid_receipt($purpose_id, $email_address, $conn) {
    $check = mysqli_query($conn, "SELECT SUM(`TransactionAmount`) AS amt FROM `dpo_payment` WHERE `purpose`='$purpose_id' AND `email`='$email_address' AND status=2") or die(mysqli_error($conn));
    return mysqli_fetch_array($check)['amt'];
}

// =============================================
// MAIN RECEIPT GENERATION FUNCTION WITH LOGGING
// =============================================
function generateReceiptWithLogging($conn, $source_type, $source_id, $record_id, $email_address, $recipient_name, $subject, $amt_paid, $amt_to_pay, $amt_due, $purpose, $purpose_id, $mood_payment, $sender, $received_by) {
    
    $receipt_no = generateRandomCode();
    $currency = "USD";
    $receipt_date = date("jS F Y");

    $qrFile = generateQRCode("https://vantageafricaleaders.c/receipts/" . $receipt_no . "_" . $receipt_date . ".pdf");
    $qrcode = "qrcodes/" . $qrFile;

    $amount = "$ " . number_format($amt_paid, 2);
    $amt_in_words = numberToWords($amt_paid) . " " . $currency . ' only.';

    $amount_paid_total = sum_paid_receipt($purpose_id, $email_address, $conn);

    if (($amt_paid - $amt_to_pay) > 0) {
        $balance = "$ " . number_format(($amt_paid - $amt_to_pay), 2);
    } else {
        $balance = "NIL";
    }

    $amt_paid_formatted = number_format($amt_paid, 2);

    $generatedFilePath = generateReceiptPdf(
        $email_address, 
        $recipient_name, 
        $subject, 
        $receipt_no, 
        $sender, 
        $receipt_date, 
        $received_by, 
        $amount, 
        $amt_in_words, 
        $balance, 
        $purpose, 
        $mood_payment, 
        $amt_due, 
        $amount_paid_total, 
        $qrcode
    );

    if ($generatedFilePath) {
        $email_sent = sendReceiptEmail($email_address, $recipient_name, $subject, $generatedFilePath);
        
        // =============================================
        // LOG THE RECEIPT EMAIL
        // =============================================
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
        
        return $generatedFilePath;
    } else {
        echo "Failed to generate pdf";
        return false;
    }
}

// =============================================
// RECEIPT PDF GENERATION
// =============================================
function generateReceiptPdf($email_address, $recipient_name, $subject, $receipt_no, $sender, $receipt_date, $received_by, $amount, $amt_in_words, $balance, $purpose, $mood_payment, $amt_due, $amount_paid, $qrcode) {
    $html = '
    <html>
    <head></head>
    <body style="font-family: Arial, sans-serif; background-color: #f3f3f3; margin: 0; padding: 20px;">
        <div style="width: 210mm; Height: 297mm; padding: 0; margin: 0 auto; background-color: #fff;">
            <table style="color: black; padding: 10px 40px 0 40px; margin-bottom: 5px; width: 210mm; height: 45mm;">
                <tbody>
                    <tr>
                        <td style="width: 30%;">
                            <table style="color: #2B5470;">
                                <tbody>
                                    <tr>
                                        <td style="display: block; padding: 0;">
                                            <img src="https://vantageafricaleaders.c/assets/img/logo.png" style="width: inherit; height: 80px; object-fit: contain;" alt="">
                                        </td>
                                        <td style=" width: 10%;">
                                            
                                        </td>
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
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                Leadership training
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                CV & Interview coaching
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                Strategic Consulting
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                HR Consulting
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                Online Book club
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="padding: 0 40px; color: #2B5470; border-bottom: solid 5px #A85431;"></div>

            <table style="margin: 10px 0; padding: 0 40px; color: #2B5470; width: 800px; height: 8mm; text-align: center;">
                <tbody>
                    <tr>
                        <td style="width: 38%;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/>
                            </svg>
                            info@vantageafricaleaders.com 
                        </td>
                        <td style="width: 38%;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-globe2" viewBox="0 0 16 16">
                                <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855q-.215.403-.395.872c.705.157 1.472.257 2.282.287zM4.249 3.539q.214-.577.481-1.078a7 7 0 0 1 .597-.933A7 7 0 0 0 3.051 3.05q.544.277 1.198.49zM3.509 7.5c.036-1.07.188-2.087.436-3.008a9 9 0 0 1-1.565-.667A6.96 6.96 0 0 0 1.018 7.5zm1.4-2.741a12.3 12.3 0 0 0-.4 2.741H7.5V5.091c-.91-.03-1.783-.145-2.591-.332M8.5 5.09V7.5h2.99a12.3 12.3 0 0 0-.399-2.741c-.808.187-1.681.301-2.591.332zM4.51 8.5c.035.987.176 1.914.399 2.741A13.6 13.6 0 0 1 7.5 10.91V8.5zm3.99 0v2.409c.91.03 1.783.145 2.591.332.223-.827.364-1.754.4-2.741zm-3.282 3.696q.18.469.395.872c.552 1.035 1.218 1.65 1.887 1.855V11.91c-.81.03-1.577.13-2.282.287zm.11 2.276a7 7 0 0 1-.598-.933 9 9 0 0 1-.481-1.079 8.4 8.4 0 0 0-1.198.49 7 7 0 0 0 2.276 1.522zm-1.383-2.964A13.4 13.4 0 0 1 3.508 8.5h-2.49a6.96 6.96 0 0 0 1.362 3.675c.47-.258.995-.482 1.565-.667m6.728 2.964a7 7 0 0 0 2.275-1.521 8.4 8.4 0 0 0-1.197-.49 9 9 0 0 1-.481 1.078 7 7 0 0 1-.597.933M8.5 11.909v3.014c.67-.204 1.335-.82 1.887-1.855q.216-.403.395-.872A12.6 12.6 0 0 0 8.5 11.91zm3.555-.401c.57.185 1.095.409 1.565.667A6.96 6.96 0 0 0 14.982 8.5h-2.49a13.4 13.4 0 0 1-.437 3.008M14.982 7.5a6.96 6.96 0 0 0-1.362-3.675c-.47.258-.995.482-1.565.667.248.92.4 1.938.437 3.008zM11.27 2.461q.266.502.482 1.078a8.4 8.4 0 0 0 1.196-.49 7 7 0 0 0-2.275-1.52c.218.283.418.597.597.932m-.488 1.343a8 8 0 0 0-.395-.872C9.835 1.897 9.17 1.282 8.5 1.077V4.09c.81-.03 1.577-.13 2.282-.287z"/>
                            </svg>
                            www.vantageafricaleaders.com
                        </td>
                        <td>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                                <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
                            </svg>
                            254 725 303 645
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="height: 200mm; border: solid 2px #FEB958; margin: 0 20px; padding: 10px; font-family: Times New Roman, Times, serif;">
                <h2 style="margin: 0; text-align: center;">
                    <span style="border-bottom: dashed 2px black">
                        PAYMENT RECEIPT
                    </span>
                </h2>
                <p style="text-align: left;"><b>Date:</b> <span style="border-bottom: dotted 1px #A85431;">' . $receipt_date . '</span></p>
                <p style="text-align: right"><b>Receipt No:</b> <span style="border-bottom: dotted 1px #A85431;">' . $receipt_no . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Received From:</b> <span style="border-bottom: dotted 1px #A85431;">' . $recipient_name . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Amount:</b> <span style="border-bottom: dotted 1px #A85431;">' . $amt_in_words . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Purpose of Payment:</b> <span style="border-bottom: dotted 1px #A85431;">' . $purpose . '</span></p>

                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%;">
                            <table style="margin-top: 40px; width: 90%;" border="1" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                                <tr>
                                    <th colspan="2" style="padding: 5px;">Payment made through:</th>
                                </tr>
                                <tr>
                                    <td style="padding: 5px;">' . $mood_payment . '</td>
                                    <td style="padding: 5px;">' . $amount . '</td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 50%;"></td>
                    </tr>
                </table>

                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%;"></td>
                        <td>
                            <table style="margin-top: 40px; width: 100%;" border="1" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                                <tr>
                                    <th colspan="2" style="padding: 5px;">Account:</th>
                                </tr>
                                <tr>
                                    <td style="padding: 5px;">Amount Due</td>
                                    <td style="padding: 5px;">' . number_format($amt_due, 2) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px;">Total Paid</td>
                                    <td style="padding: 5px;">' . number_format($amount_paid, 2) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px;">Balance</td>
                                    <td style="padding: 5px;">' . number_format(($amt_due - $amount_paid), 2) . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="text-align: left; margin-bottom: 1px;"><b>Amount received by: </b> <span style="border-bottom: dotted 1px #A85431;">' . $received_by . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Scan to verify: </b></p>
                <img src="' . $qrcode . '" alt="QR Code" width="150" height="150">

            </div>
            
            <div style="width: 100%; border-top: solid 10px #4F2020;">
                <div style="background: linear-gradient(135deg, #4F2020 0%, #A85431 100%); height: 60px; text-align: center; color: white; font-family: Times New Roman, serif; font-size: 18px; font-weight: bold; letter-spacing: 2px; display: flex; align-items: center; justify-content: center;">
                    Developing Transformational Leaders
                </div>
            </div>
        </div>
    </body>
    </html>
    ';

    $directory = 'receipts';
    $file = $receipt_no . "_" . $receipt_date;
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);

    return $generatedFilePath;
}

// =============================================
// SEND RECEIPT EMAIL
// =============================================
function sendReceiptEmail($email_address, $recipient_name, $subject, $generatedFilePath) {
    $year = date("Y");
    $attachments = [$generatedFilePath];

    $body = '<html>' .
        '<head></head>' .
        '<body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">' .
            '<div style="border: solid 1px #d1d3e2;">
                <img src="https://vantageafricaleaders.c/assets/img/logo.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
                <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
                <div style="padding: 0 .5rem">
                    <h5 id="subject_email">
                        <b>Payment Receipt</b>
                    </h5>
                    <p>
                        Dear ' . htmlspecialchars($recipient_name) . ',
                    </p>
                    <div id="body_email">
                        <p>Thank you for your payment</p>
                        <p>Kindly find attached the confirmation receipt of your payment.</p>
                        <p>If you have any questions regarding this receipt, please don\'t hesitate to contact us.</p>
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
                        <span>' . $email_address . '</span>
                        <a href="" style="text-decoration: underline; color: #9ba4b3; font-weight: 700;">Unsubscribe</a>
                    </div>
                    <div style="color: #9ba4b3; font-size: .8rem;">
                        &copy; ' . $year . '
                        Vantage Africa School of Leadership. All Rights Reserved
                    </div>
                </div>
            </div>' .
        '</body></html>';

    return send_mail_function($email_address, $body, $subject, $attachments);
}

// =============================================
// ORIGINAL FUNCTIONS (for backward compatibility)
// =============================================

/**
 * Original generatePdf function - now with optional logging
 * Use generateReceiptWithLogging() for new implementations
 */
function generatePdf($email_address, $recipient_name, $subject, $receipt_no, $sender, $receipt_date, $received_by, $amount, $amt_in_words, $balance, $purpose, $mood_payment, $amt_due, $amount_paid, $qrcode, $conn = null, $source_type = null, $source_id = null, $record_id = null) {
    
    $generatedFilePath = generateReceiptPdf($email_address, $recipient_name, $subject, $receipt_no, $sender, $receipt_date, $received_by, $amount, $amt_in_words, $balance, $purpose, $mood_payment, $amt_due, $amount_paid, $qrcode);

    if ($generatedFilePath) {
        $email_sent = sendReceiptEmail($email_address, $recipient_name, $subject, $generatedFilePath);
        
        // Log if connection and source info provided
        if ($conn && $source_type && $source_id) {
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
        }
        
        return $generatedFilePath;
    } else {
        echo "Failed to generate pdf";
        return false;
    }
}

/**
 * Original sendEmail_ function - kept for backward compatibility
 */
function sendEmail_($email_address, $recipient_name, $subject, $generatedFilePath) {
    return sendReceiptEmail($email_address, $recipient_name, $subject, $generatedFilePath);
}