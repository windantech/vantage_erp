<?php
session_start();
header('Content-Type: application/json');

require '../../database/conn.php';
require_once __DIR__ . '/../pdf_plugins/generatePdf.php';
require_once __DIR__ . '/../email_plugins/vendor/autoload.php';
require_once __DIR__ . '/../email_plugins/email_function.php';
require_once __DIR__ . '/email_log_functions.php';

function to_words($num)
{
    $num = (float) $num;
    $whole = (int) floor($num);
    $frac = (int) round(($num - $whole) * 100);
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $convert = function ($n) use (&$convert, $ones, $tens) {
        if ($n === 0) {
            return '';
        }
        if ($n >= 1000) {
            return trim($convert((int) ($n / 1000)) . ' Thousand ' . $convert($n % 1000));
        }
        if ($n >= 100) {
            return trim($ones[(int) ($n / 100)] . ' Hundred ' . $convert($n % 100));
        }
        if ($n >= 20) {
            return trim($tens[(int) ($n / 10)] . ' ' . $convert($n % 10));
        }
        return $ones[$n];
    };

    $result = trim($convert($whole));
    if ($result === '') {
        $result = 'Zero';
    }
    if ($frac > 0) {
        $result .= ' and ' . $frac . ' cents';
    }
    return $result;
}

function build_balance_invoice_pdf($invoice_data)
{
    $client_name = $invoice_data['client_name'];
    $program_name = $invoice_data['program_name'];
    $invoice_no = $invoice_data['invoice_no'];
    $invoice_date = $invoice_data['invoice_date'];
    $amount_due = $invoice_data['amount_due'];
    $total_paid = $invoice_data['total_paid'];
    $balance = $invoice_data['balance'];
    $payment_url = $invoice_data['payment_url'];

    $amount_in_words = to_words($balance) . ' USD only.';
    $html = '<html>
    <head></head>
    <body style="font-family: Arial, sans-serif; background-color: #f3f3f3; margin: 0; padding: 20px;">
        <div style="width: 210mm; min-height: 297mm; padding: 0; margin: 0 auto; background-color: #fff;">
            <table style="color: black; padding: 10px 40px 0 40px; margin-bottom: 5px; width: 210mm; height: 45mm;">
                <tbody>
                    <tr>
                        <td style="width: 30%;">
                            <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" style="width: inherit; height: 80px; object-fit: contain;" alt="">
                        </td>
                        <td style="width: 30%; font-size: 16px; font-family: Times New Roman, Times, serif;">
                            Astrol Business Center
                            6<sup>th</sup> Floor, C603,
                            Thika Road Opposite Garden City,
                            Nairobi
                        </td>
                        <td style="font-size: 16px; border-left: solid 2px #A85431; padding-left: 10px; font-family: Times New Roman, Times, serif;">
                            Leadership training<br>
                            CV & Interview coaching<br>
                            Strategic Consulting<br>
                            HR Consulting<br>
                            Online Book club
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
                <h2 style="margin: 0; text-align: center;">
                    <span style="border-bottom: dashed 2px black">PROFORMA INVOICE</span>
                </h2>
                <p style="text-align: left; margin: 1px;"><b>Date:</b> <span style="border-bottom: dotted 1px #A85431;">' . htmlspecialchars($invoice_date) . '</span></p>
                <p style="text-align: right; margin: 1px;"><b>Invoice No:</b> <span style="border-bottom: dotted 1px #A85431;">' . htmlspecialchars($invoice_no) . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Presented to:</b> <span style="border-bottom: dotted 1px #A85431;">' . htmlspecialchars($client_name) . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Amount Payable:</b> <span style="border-bottom: dotted 1px #A85431;">$' . number_format($balance, 2) . ' USD</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Purpose of Payment:</b> <span style="border-bottom: dotted 1px #A85431;">Outstanding balance for ' . htmlspecialchars($program_name) . '</span></p>

                <table style="margin-top: 6px; width: 100%; border-collapse: collapse;" border="1" cellspacing="0" cellpadding="0">
                    <tr style="background: #D96800;">
                        <th style="padding: 5px; color: white;">No</th>
                        <th style="padding: 5px; color: white;">Item</th>
                        <th style="padding: 5px; color: white;">Unit</th>
                        <th style="padding: 5px; color: white;">Qty</th>
                        <th style="padding: 5px; color: white;">Cost (USD)</th>
                        <th style="padding: 5px; color: white;">Total (USD)</th>
                    </tr>
                    <tr>
                        <td style="padding: 5px;">1</td>
                        <td style="padding: 5px; font-weight: bold;">Outstanding Balance</td>
                        <td style="padding: 5px;">Amount</td>
                        <td style="padding: 5px; text-align: center;">1</td>
                        <td style="padding: 5px; text-align: center; font-weight: bold;">' . number_format($balance, 2) . '</td>
                        <td style="padding: 5px; text-align: center; font-weight: bold;">' . number_format($balance, 2) . '</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="padding: 5px; text-align: right;"><b>Total Payable Now</b></td>
                        <td colspan="2" style="padding: 5px; text-align: center; background: #D96800; color: white;"><b>' . number_format($balance, 2) . '</b></td>
                    </tr>
                </table>

                <p style="text-align: left; margin-top: 8px;"><b>Amount in Words:</b> ' . htmlspecialchars($amount_in_words) . '</p>
                <p style="text-align: left; margin-top: 4px;"><b>Exact Amount:</b> USD <span style="font-size: 18px; font-weight: bold; color: #A85431;">' . number_format($balance, 2) . '</span></p>

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

                <p style="text-align: left; margin-bottom: 1px;"><b>Online Payment</b></p>
                <p style="text-align: left; margin: 1px 25px; font-size: 14px;">Use this payment link: <span style="color: #A85431;">' . htmlspecialchars($payment_url) . '</span></p>

                <p style="text-align: left; margin-bottom: 1px;">Yours sincerely,</p>
                <p style="text-align: left; margin: 0 1px;">Benson Kiarie, CEO & Founder,<br>Vantage Africa School of Leadership.</p>
            </div>
        </div>
    </body>
    </html>';

    $output_dir = dirname(__DIR__) . '/invoices';
    $safe_file = preg_replace('/[^A-Za-z0-9_\-]/', '_', $invoice_data['file_base'] ?? ($invoice_no . '_' . date('Ymd_His')));
    return convertHtmlToPdf($html, $output_dir, $safe_file);
}

function get_outstanding_invoice_context($conn, $source_type, $source_id, $record_id)
{
    if (!in_array($source_type, ['register', 'ticket_congress'], true)) {
        throw new Exception('Outstanding balance invoice is only supported for register or ticket records.');
    }

    $ctx = [
        'source_type' => $source_type,
        'source_id' => $source_id,
        'record_id' => $record_id,
        'client_name' => '',
        'client_email' => '',
        'program_name' => '',
        'amount_due' => 0.0,
        'total_paid' => 0.0,
        'payment_url' => 'https://vantageafricaleaders.com/pay.php'
    ];

    if ($source_type === 'register') {
        $query = "SELECT r.id, r.entry_id, r.firstname, r.lastname, r.email, r.program, c.course, c.price_usd
                  FROM register r
                  LEFT JOIN course c ON r.program = c.course_id
                  WHERE r.entry_id = '$source_id' OR r.id = '$record_id'
                  LIMIT 1";
        $result = mysqli_query($conn, $query);

        if (!$result || mysqli_num_rows($result) === 0) {
            $fallback_query = "SELECT r.id, r.entry_id, r.firstname, r.lastname, r.email, r.program, c.course, c.price_usd
                               FROM register r
                               LEFT JOIN course c ON r.program = c.id
                               WHERE r.entry_id = '$source_id' OR r.id = '$record_id'
                               LIMIT 1";
            $result = mysqli_query($conn, $fallback_query);
        }

        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception('Client record not found');
        }

        $row = mysqli_fetch_assoc($result);
        $purpose = mysqli_real_escape_string($conn, (string) $row['program']);
        $canonical_source_id = (string) ($row['entry_id'] ?? $source_id);

        $ctx['source_id'] = $canonical_source_id;
        $ctx['record_id'] = (string) ($row['id'] ?? $record_id);
        $ctx['client_name'] = ucwords(strtolower(trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''))));
        $ctx['client_email'] = (string) ($row['email'] ?? '');
        $ctx['program_name'] = (string) ($row['course'] ?? 'Training Program');
        $ctx['amount_due'] = floatval($row['price_usd'] ?? 0);
        $ctx['payment_url'] = 'https://vantageafricaleaders.com/pay/index.php?id=' . urlencode($canonical_source_id);

        $paid_query = mysqli_query(
            $conn,
            "SELECT SUM(TransactionAmount) AS total_paid
             FROM dpo_payment
             WHERE email = '" . mysqli_real_escape_string($conn, $ctx['client_email']) . "'
             AND purpose = '$purpose'
             AND status = 2"
        );
        if ($paid_query && $paid_row = mysqli_fetch_assoc($paid_query)) {
            $ctx['total_paid'] = floatval($paid_row['total_paid'] ?? 0);
        }
    } else {
        $query = "SELECT t.id, t.ticket_id, t.fullname, t.email, t.event_id, e.event_title, e.early_amount
                  FROM ticket_congress t
                  LEFT JOIN Event e ON t.event_id = e.event_id
                  WHERE t.ticket_id = '$source_id' OR t.id = '$record_id'
                  LIMIT 1";
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception('Client record not found');
        }

        $row = mysqli_fetch_assoc($result);
        $event_id = mysqli_real_escape_string($conn, (string) ($row['event_id'] ?? ''));
        $ticket_id = (string) ($row['ticket_id'] ?? $source_id);
        $row_id = (string) ($row['id'] ?? '');

        $ctx['source_id'] = $ticket_id;
        $ctx['record_id'] = $row_id !== '' ? $row_id : $record_id;
        $ctx['client_name'] = trim((string) ($row['fullname'] ?? ''));
        $ctx['client_email'] = (string) ($row['email'] ?? '');
        $ctx['program_name'] = (string) ($row['event_title'] ?? 'International Event');
        $ctx['amount_due'] = floatval($row['early_amount'] ?? 0);
        $ctx['payment_url'] = 'https://vantageafricaleaders.com/pay.php';

        $paid_query = mysqli_query(
            $conn,
            "SELECT SUM(TransactionAmount) AS total_paid
             FROM dpo_payment
             WHERE email = '" . mysqli_real_escape_string($conn, $ctx['client_email']) . "'
             AND status = 2
             AND (
                 purpose = '$event_id'
                 OR app_id = '" . mysqli_real_escape_string($conn, $ticket_id) . "'
                 OR app_id = '" . mysqli_real_escape_string($conn, $row_id) . "'
             )"
        );
        if ($paid_query && $paid_row = mysqli_fetch_assoc($paid_query)) {
            $ctx['total_paid'] = floatval($paid_row['total_paid'] ?? 0);
        }
    }

    if ($ctx['client_email'] === '') {
        throw new Exception('Client has no email on record');
    }
    if ($ctx['amount_due'] <= 0) {
        throw new Exception('Could not determine the total fee for this record');
    }

    $ctx['balance'] = round($ctx['amount_due'] - $ctx['total_paid'], 2);
    if ($ctx['balance'] <= 0) {
        throw new Exception('This client has no outstanding balance.');
    }

    return $ctx;
}

function build_invoice_identity($source_type, $source_id)
{
    $safe_source = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $source_id);
    $invoice_prefix = 'VASL-' . $safe_source;
    $stamp = date('Ymd_His');

    return [
        'invoice_no' => $invoice_prefix . '-BAL',
        'file_base' => $invoice_prefix . '_BAL_' . $stamp
    ];
}

function get_preview_url($invoice_path)
{
    $file_name = basename((string) $invoice_path);
    return 'invoices/' . rawurlencode($file_name);
}

function resolve_draft_invoice_path($draft_file)
{
    $base = basename((string) $draft_file);
    if ($base === '' || $base !== $draft_file) {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9._\-]+\.pdf$/', $base)) {
        return null;
    }

    $path = dirname(__DIR__) . '/invoices/' . $base;
    return is_file($path) ? $path : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    if (!isset($_SESSION['login_id']) && !isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
        throw new Exception('Session expired. Please login again.');
    }

    $source_type = mysqli_real_escape_string($conn, $_POST['source_type'] ?? '');
    $source_id = mysqli_real_escape_string($conn, $_POST['source_id'] ?? '');
    $record_id = mysqli_real_escape_string($conn, $_POST['record_id'] ?? '');
    $action = mysqli_real_escape_string($conn, $_POST['action'] ?? 'send');
    $draft_file = $_POST['draft_file'] ?? '';

    if ($source_type === '' || $source_id === '') {
        throw new Exception('Missing required details');
    }

    if (!in_array($action, ['preview', 'send'], true)) {
        throw new Exception('Invalid action');
    }

    $ctx = get_outstanding_invoice_context($conn, $source_type, $source_id, $record_id);
    $identity = build_invoice_identity($ctx['source_type'], $ctx['source_id']);

    if ($action === 'preview') {
        $invoice_path = build_balance_invoice_pdf([
            'client_name' => $ctx['client_name'] !== '' ? $ctx['client_name'] : $ctx['client_email'],
            'program_name' => $ctx['program_name'],
            'invoice_no' => $identity['invoice_no'],
            'invoice_date' => date('jS F Y'),
            'amount_due' => $ctx['amount_due'],
            'total_paid' => $ctx['total_paid'],
            'balance' => $ctx['balance'],
            'payment_url' => $ctx['payment_url'],
            'file_base' => $identity['file_base']
        ]);

        if (!$invoice_path || !is_file($invoice_path)) {
            throw new Exception('Failed to generate invoice preview');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Invoice preview generated.',
            'balance' => $ctx['balance'],
            'preview_url' => get_preview_url($invoice_path),
            'draft_file' => basename($invoice_path)
        ]);
        exit;
    }

    $invoice_path = resolve_draft_invoice_path($draft_file);
    if ($invoice_path === null) {
        $invoice_path = build_balance_invoice_pdf([
            'client_name' => $ctx['client_name'] !== '' ? $ctx['client_name'] : $ctx['client_email'],
            'program_name' => $ctx['program_name'],
            'invoice_no' => $identity['invoice_no'],
            'invoice_date' => date('jS F Y'),
            'amount_due' => $ctx['amount_due'],
            'total_paid' => $ctx['total_paid'],
            'balance' => $ctx['balance'],
            'payment_url' => $ctx['payment_url'],
            'file_base' => $identity['file_base']
        ]);
    }

    if (!$invoice_path || !is_file($invoice_path)) {
        throw new Exception('Failed to generate invoice PDF');
    }

    $subject = 'Outstanding Balance Invoice - ' . $identity['invoice_no'];
    $body = '<html><body style="font-family: Arial, sans-serif; color: #222;">
        <p>Dear ' . htmlspecialchars($ctx['client_name'] !== '' ? $ctx['client_name'] : 'Client') . ',</p>
        <p>Please find attached your updated invoice for the outstanding balance.</p>
        <p><strong>Program/Event:</strong> ' . htmlspecialchars($ctx['program_name']) . '<br>
           <strong>Outstanding Balance:</strong> $' . number_format($ctx['balance'], 2) . '</p>
        <p>You can make payment using this link: <a href="' . htmlspecialchars($ctx['payment_url']) . '">' . htmlspecialchars($ctx['payment_url']) . '</a></p>
        <p>Warm regards,<br>Vantage Africa School of Leadership</p>
    </body></html>';

    $email_sent = send_mail_function($ctx['client_email'], $body, $subject, [$invoice_path]);
    $sent_by = intval($_SESSION['login_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

    log_email(
        $conn,
        $ctx['source_type'],
        $ctx['source_id'],
        'invoice',
        $ctx['client_email'],
        $ctx['client_name'],
        $subject,
        [$invoice_path],
        $email_sent ? 'sent' : 'failed',
        $email_sent ? null : 'Failed to send outstanding balance invoice',
        $sent_by > 0 ? $sent_by : null,
        $ctx['record_id'] !== '' ? intval($ctx['record_id']) : null
    );

    if (!$email_sent) {
        throw new Exception('Invoice generated, but email sending failed.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Outstanding balance invoice sent to ' . $ctx['client_email'],
        'balance' => $ctx['balance'],
        'preview_url' => get_preview_url($invoice_path)
    ]);
} catch (Exception $e) {
    error_log('Outstanding invoice error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

