<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']]);
    }
});
require '../../database/conn.php';
// Include required files
require_once 'email_log_functions.php';
require_once __DIR__ . '/../email_plugins/vendor/autoload.php';
require_once __DIR__ . '/../email_plugins/email_function.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get parameters
$action = $_POST['action'] ?? '';
$email_type = $_POST['email_type'] ?? '';
$source_type = $_POST['source_type'] ?? '';
$source_id = $_POST['source_id'] ?? '';
$record_id = $_POST['record_id'] ?? '';

// Validate action
if ($action !== 'resend_email') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Validate required parameters
if (empty($email_type) || empty($source_type) || empty($source_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Get the logged-in user ID (adjust based on your session structure)
$sent_by = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

try {
    $result = resend_email($conn, $source_type, $source_id, $record_id, $email_type, $sent_by);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Resend a specific email type
 */
function resend_email($conn, $source_type, $source_id, $record_id, $email_type, $sent_by = null) {
    
    if ($source_type === 'register') {
        return resend_register_email($conn, $source_id, $record_id, $email_type, $sent_by);
    } elseif ($source_type === 'ticket_congress') {
        return resend_ticket_email($conn, $source_id, $record_id, $email_type, $sent_by);
    } elseif ($source_type === 'enquiry') {
        return resend_enquiry_email($conn, $source_id, $record_id, $email_type, $sent_by);
    }
    
    return ['success' => false, 'error' => 'Invalid source type'];
}

/**
 * Resolve stored attachment path to an existing local file path.
 * Handles paths saved from both /admin and /admin/includes execution contexts.
 */
function resolve_attachment_file_path($attachment_path) {
    $path = trim((string) $attachment_path);
    if ($path === '') {
        return null;
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^\./+#', '', $path);

    // Already absolute and exists
    if (is_file($path)) {
        return $path;
    }

    // Convert absolute public_html paths
    $public_root = '/home2/vantage/public_html/';
    if (strpos($path, $public_root) === 0 && is_file($path)) {
        return $path;
    }

    $admin_dir = dirname(__DIR__); // /admin
    $project_root = dirname($admin_dir); // /public_html

    $candidates = [];

    if (strpos($path, 'admin/') === 0) {
        $candidates[] = $project_root . '/' . $path;
    } elseif (strpos($path, 'includes/') === 0) {
        $candidates[] = $admin_dir . '/' . $path;
    } elseif (strpos($path, 'receipts/') === 0) {
        $candidates[] = $admin_dir . '/' . $path;
        $candidates[] = __DIR__ . '/' . $path;
    } else {
        $candidates[] = $project_root . '/' . ltrim($path, '/');
        $candidates[] = $admin_dir . '/' . $path;
        $candidates[] = __DIR__ . '/' . $path;
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Resend email for Virtual Course (register)
 */
function resend_register_email($conn, $entry_id, $record_id, $email_type, $sent_by) {
    
    $query = "SELECT r.*, c.course, c.price_usd 
              FROM register r 
              LEFT JOIN course c ON r.program = c.id 
              WHERE r.entry_id = '" . mysqli_real_escape_string($conn, $entry_id) . "'";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        return ['success' => false, 'error' => 'Record not found'];
    }
    
    $record = mysqli_fetch_assoc($result);
    $email = $record['email'];
    $name = ucwords(strtolower($record['firstname'] . ' ' . $record['lastname']));
    $course_name = $record['course'];
    $price = $record['price_usd'];
    
    switch ($email_type) {
        case 'welcome':
            return resend_welcome_email($conn, 'register', $entry_id, $record_id, $email, $name, $course_name, $sent_by);
        case 'admission_letter':
            return resend_admission_letter($conn, 'register', $entry_id, $record_id, $record, $sent_by);
        case 'invoice':
            return resend_invoice($conn, 'register', $entry_id, $record_id, $record, $sent_by);
        case 'receipt':
            return resend_receipt($conn, 'register', $entry_id, $record_id, $record, $sent_by);
        case 'moodle_credentials':
            return resend_moodle_credentials($conn, $entry_id, $record_id, $record, $sent_by);
        default:
            return ['success' => false, 'error' => 'Unsupported email type: ' . $email_type];
    }
}

/**
 * Resend email for International Event (ticket_congress)
 */
function resend_ticket_email($conn, $ticket_id, $record_id, $email_type, $sent_by) {
    
    $query = "SELECT t.*, e.event_title, e.price 
              FROM ticket_congress t 
              LEFT JOIN Event e ON t.event_id = e.event_id 
              WHERE t.ticket_id = '" . mysqli_real_escape_string($conn, $ticket_id) . "'
              OR t.id = '" . mysqli_real_escape_string($conn, $ticket_id) . "'";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        return ['success' => false, 'error' => 'Record not found'];
    }
    
    $record = mysqli_fetch_assoc($result);
    $email = $record['email'];
    $name = $record['fullname'];
    $event_name = $record['event_title'];
    
    switch ($email_type) {
        case 'welcome':
            return resend_welcome_email($conn, 'ticket_congress', $ticket_id, $record_id, $email, $name, $event_name, $sent_by);
        case 'admission_letter':
            return resend_admission_letter($conn, 'ticket_congress', $ticket_id, $record_id, $record, $sent_by);
        case 'invoice':
            return resend_invoice($conn, 'ticket_congress', $ticket_id, $record_id, $record, $sent_by);
        case 'receipt':
            return resend_receipt($conn, 'ticket_congress', $ticket_id, $record_id, $record, $sent_by);
        default:
            return ['success' => false, 'error' => 'Unsupported email type: ' . $email_type];
    }
}

/**
 * Resend email for Enquiry
 */
function resend_enquiry_email($conn, $enquiry_id, $record_id, $email_type, $sent_by) {
    return ['success' => false, 'error' => 'Enquiry email resend not implemented yet'];
}

/**
 * Resend Welcome Email
 * Template matches the registration confirmation style from invoice_international_.php
 */
function resend_welcome_email($conn, $source_type, $source_id, $record_id, $email, $name, $program_name, $sent_by) {
    
    $subject = "Registration Confirmed - " . $program_name;
    $year = date("Y");
    
    $body = '<html>
    <head></head>
    <body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">
        <div style="border: solid 1px #d1d3e2;">
            <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
            <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
            <div style="padding: 0 .5rem">
                <h5><b>Registration Confirmed - ' . htmlspecialchars($program_name) . '</b></h5>
                <p><strong>Dear ' . htmlspecialchars($name) . ',</strong></p>
                <div>
                    <p>Thank you for registering for <strong>' . htmlspecialchars($program_name) . '</strong> at Vantage Africa School of Leadership.</p>
                    <p>We are thrilled to have you on board! Your registration has been confirmed and we look forward to helping you grow as a transformational leader.</p>
                    <p>You will receive your admission letter and invoice shortly. In the meantime, feel free to explore our library of training recordings:</p>
                    <p style="margin: 1rem 0; text-align: center;">
                        <a href="https://vantageafricaleaders.com/trainings/videos.php" style="display: inline-block; background: linear-gradient(135deg, #DAA520 0%, #B8860B 100%); color: #fff !important; padding: 0.85rem 1.75rem; border-radius: 10px; font-weight: 700; font-size: 1rem; text-decoration: none; box-shadow: 0 4px 14px rgba(139, 69, 19, 0.35);">Watch training videos →</a>
                    </p>
                    <p>If you have any questions, please don\'t hesitate to reach out.</p>
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
                    We sent this email to <span>' . htmlspecialchars($email) . '</span>
                    <a href="" style="text-decoration: underline; color: #9ba4b3; font-weight: 700;">Unsubscribe</a>
                </div>
                <div style="color: #9ba4b3; font-size: .8rem;">
                    &copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    $email_sent = send_mail_function($email, $body, $subject, []);
    
    log_email(
        $conn, $source_type, $source_id, 'welcome',
        $email, $name, $subject, [],
        $email_sent ? 'sent' : 'failed',
        $email_sent ? null : 'Failed to resend welcome email',
        $sent_by, $record_id
    );
    
    return [
        'success' => $email_sent,
        'error' => $email_sent ? null : 'Failed to send email'
    ];
}

/**
 * Resend Admission Letter
 * Template matches sendAdmissionEmail() from invoice_international_.php
 */
function resend_admission_letter($conn, $source_type, $source_id, $record_id, $record, $sent_by) {
    
    $email = $record['email'];
    $name = $source_type === 'register' 
        ? ucwords(strtolower($record['firstname'] . ' ' . $record['lastname']))
        : $record['fullname'];
    
    // Search for admission letter PDF by name pattern
    $letters_dir = __DIR__ . '/../letters/';
    $pdf_path = null;
    
    if (is_dir($letters_dir)) {
        $search_name = ucwords(strtolower($record['firstname'] . ' ' . $record['lastname']));
        $files = glob($letters_dir . $search_name . '_*.pdf');
        
        if (!empty($files)) {
            usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
            $pdf_path = $files[0];
        }
    }
    
    if (!$pdf_path) {
        return ['success' => false, 'error' => 'Admission letter PDF not found for ' . $name . '. Please regenerate from the admin panel.'];
    }
    
    $subject = "Admission Letter - " . $name;
    $year = date("Y");
    $attachments = [$pdf_path];
    
    $body = '<html>
    <head></head>
    <body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">
        <div style="border: solid 1px #d1d3e2;">
            <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
            <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
            <div style="padding: 0 .5rem">
                <h5><b>' . htmlspecialchars($subject) . '</b></h5>
                <p>Dear ' . htmlspecialchars($name) . ',</p>
                <div>
                    <p><strong>Congratulations!</strong> 🎉</p>
                    <p>We are thrilled to confirm your admission to our training program. Please find attached your official admission letter with all the important details.</p>
                    <p><strong>What\'s Next:</strong></p>
                    <ul>
                        <li>Review your admission letter for training details and fee structure</li>
                        <li>Complete your payment using the invoice that will be sent separately</li>
                        <li>We will contact you with training schedules and joining instructions</li>
                    </ul>
                    <p style="margin: 1.25rem 0;">While you prepare, explore our library of training recordings to get a head start:</p>
                    <p style="margin: 1rem 0; text-align: center;">
                        <a href="https://vantageafricaleaders.com/trainings/videos.php" style="display: inline-block; background: linear-gradient(135deg, #DAA520 0%, #B8860B 100%); color: #fff !important; padding: 0.85rem 1.75rem; border-radius: 10px; font-weight: 700; font-size: 1rem; text-decoration: none; box-shadow: 0 4px 14px rgba(139, 69, 19, 0.35);">Watch training videos →</a>
                    </p>
                    <p>We look forward to having you join our learning community and wish you great success in your training journey!</p>
                </div>
            </div>
            <div style="padding: .5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                <div style="color: #9ba4b3; font-size: .8rem; margin: 10px 0;">
                    We sent this email to <span>' . htmlspecialchars($email) . '</span>
                </div>
                <div style="color: #9ba4b3; font-size: .8rem;">
                    &copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    $email_sent = send_mail_function($email, $body, $subject, $attachments);
    
    log_email(
        $conn, $source_type, $source_id, 'admission_letter',
        $email, $name, $subject, $attachments,
        $email_sent ? 'sent' : 'failed',
        $email_sent ? null : 'Failed to resend admission letter',
        $sent_by, $record_id
    );
    
    return [
        'success' => $email_sent,
        'error' => $email_sent ? null : 'Failed to send email'
    ];
}

/**
 * Resend Invoice
 * Template matches sendInvoiceEmail() from invoice_international_.php
 */
function resend_invoice($conn, $source_type, $source_id, $record_id, $record, $sent_by) {
    
    $entry_id = $source_id;
    
    $pdf_path = null;

    // First priority: use the latest invoice attachment already logged for this record.
    // This supports balance invoices and any custom invoice naming scheme.
    $last_invoice = get_last_email_of_type($conn, $source_type, $source_id, 'invoice');
    if ($last_invoice && !empty($last_invoice['attachment_paths'])) {
        $attachments = is_array($last_invoice['attachment_paths'])
            ? $last_invoice['attachment_paths']
            : json_decode($last_invoice['attachment_paths'], true);

        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $resolved = resolve_attachment_file_path($attachment);
                if (!empty($resolved)) {
                    $pdf_path = $resolved;
                    break;
                }
            }
        }
    }

    // Fallback: search by legacy pattern VASL-{entry_id}_*.pdf
    if (!$pdf_path) {
        $invoices_dir = __DIR__ . '/../invoices/';
        if (is_dir($invoices_dir)) {
            $files = glob($invoices_dir . 'VASL-' . $entry_id . '_*.pdf');
            if (!empty($files)) {
                usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                $pdf_path = $files[0];
            }
        }
    }
    
    if (!$pdf_path) {
        return ['success' => false, 'error' => 'Invoice PDF not found for entry ' . $entry_id . '. Please regenerate from the admin panel.'];
    }
    
    $email = $record['email'];
    $name = $source_type === 'register' 
        ? ucwords(strtolower($record['firstname'] . ' ' . $record['lastname']))
        : $record['fullname'];
    $subject = !empty($last_invoice['subject']) ? $last_invoice['subject'] : ("Proforma Invoice - VASL-" . $entry_id);
    $year = date("Y");
    $attachments = [$pdf_path];
    
    $body = '<html>
    <head></head>
    <body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">
        <div style="border: solid 1px #d1d3e2;">
            <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
            <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
            <div style="padding: 0 .5rem">
                <h5 id="subject_email">
                    <b>' . htmlspecialchars($subject) . '</b>
                </h5>
                <p>
                    <strong>Dear ' . htmlspecialchars($name) . ',</strong>
                </p>
                <div id="body_email">
                    <p>Your journey with Vantage Africa School of Leadership has just begun and we couldn\'t be more excited to walk this path with you! You\'ve taken a bold step towards advancing your career, and now it\'s time to secure your spot.</p>
                    
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
                    We sent this email to <span>' . htmlspecialchars($email) . '</span>
                    <a href="" style="text-decoration: underline; color: #9ba4b3; font-weight: 700;">Unsubscribe</a>
                </div>
                <div style="color: #9ba4b3; font-size: .8rem;">
                    &copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    $email_sent = send_mail_function($email, $body, $subject, $attachments);
    
    log_email(
        $conn, $source_type, $source_id, 'invoice',
        $email, $name, $subject, $attachments,
        $email_sent ? 'sent' : 'failed',
        $email_sent ? null : 'Failed to resend invoice',
        $sent_by, $record_id
    );
    
    return [
        'success' => $email_sent,
        'error' => $email_sent ? null : 'Failed to send email'
    ];
}

/**
 * Resend Receipt
 */
function resend_receipt($conn, $source_type, $source_id, $record_id, $record, $sent_by) {
    
    $email = $record['email'];
    
    // Get the last receipt sent
    $last_receipt = get_last_email_of_type($conn, $source_type, $source_id, 'receipt');
    
    if ($last_receipt && !empty($last_receipt['attachment_paths'])) {
        $attachments = is_array($last_receipt['attachment_paths']) 
            ? $last_receipt['attachment_paths'] 
            : json_decode($last_receipt['attachment_paths'], true);
        
        if (!empty($attachments)) {
            $pdf_path = resolve_attachment_file_path($attachments[0]);
            if (!empty($pdf_path)) {
            $name = $source_type === 'register' 
                ? ucwords(strtolower($record['firstname'] . ' ' . $record['lastname']))
                : $record['fullname'];
            $subject = "Payment Receipt";
            $year = date("Y");
            
            $body = '<html>
            <head></head>
            <body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">
                <div style="border: solid 1px #d1d3e2;">
                    <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
                    <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
                    <div style="padding: 0 .5rem">
                        <h5><b>Payment Receipt</b></h5>
                        <p><strong>Dear ' . htmlspecialchars($name) . ',</strong></p>
                        <div>
                            <p>Thank you for your payment. Please find attached your official payment receipt for your records.</p>
                            <p>If you have any questions regarding your payment, please don\'t hesitate to contact us.</p>
                            <p><strong>Warm regards,</strong><br>
                            <span style="color: #A85431; font-weight: bold;">Vantage Africa School of Leadership</span></p>
                        </div>
                    </div>
                    <div style="padding: .5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                        <div style="color: #9ba4b3; font-size: .8rem; margin: 10px 0;">
                            We sent this email to <span>' . htmlspecialchars($email) . '</span>
                        </div>
                        <div style="color: #9ba4b3; font-size: .8rem;">
                            &copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved
                        </div>
                    </div>
                </div>
            </body>
            </html>';
            
            $email_sent = send_mail_function($email, $body, $subject, [$pdf_path]);
            
            log_email(
                $conn, $source_type, $source_id, 'receipt',
                $email, $name, $subject, [$pdf_path],
                $email_sent ? 'sent' : 'failed',
                $email_sent ? null : 'Failed to resend receipt',
                $sent_by, $record_id
            );
            
            return [
                'success' => $email_sent,
                'error' => $email_sent ? null : 'Failed to send email'
            ];
            }
        }
    }
    
    return ['success' => false, 'error' => 'Receipt PDF not found. Please generate a new receipt from the payment section.'];
}

/**
 * Resend Moodle Credentials
 */
function resend_moodle_credentials($conn, $entry_id, $record_id, $record, $sent_by) {
    
    $email = $record['email'];
    $name = ucwords(strtolower($record['firstname'] . ' ' . $record['lastname']));
    $course_name = $record['course'];
    
    $moodle_query = "SELECT username, password FROM moodle_users WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' ORDER BY id DESC LIMIT 1";
    $moodle_result = mysqli_query($conn, $moodle_query);
    
    if (!$moodle_result || mysqli_num_rows($moodle_result) == 0) {
        $username = $record['moodle_username'] ?? $record['username'] ?? null;
        $password = $record['moodle_password'] ?? null;
        
        if (!$username) {
            return ['success' => false, 'error' => 'Moodle credentials not found. Please create Moodle account first.'];
        }
    } else {
        $moodle_data = mysqli_fetch_assoc($moodle_result);
        $username = $moodle_data['username'];
        $password = $moodle_data['password'];
    }
    
    $subject = "Your Learning Portal Access - " . $course_name;
    $year = date("Y");
    
    $body = '<html>
    <head></head>
    <body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">
        <div style="border: solid 1px #d1d3e2;">
            <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
            <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
            <div style="padding: 0 .5rem">
                <h5><b>Your Learning Portal Access - ' . htmlspecialchars($course_name) . '</b></h5>
                <p><strong>Dear ' . htmlspecialchars($name) . ',</strong></p>
                <div>
                    <p>Your learning portal account has been set up for <strong>' . htmlspecialchars($course_name) . '</strong>. Here are your login credentials:</p>
                    <div style="background-color: #f0f8ff; padding: 15px; border: 1px solid #A85431; margin: 15px 0; border-radius: 5px;">
                        <p style="margin: 5px 0;"><strong>🌐 Portal URL:</strong> <a href="https://learn.vantageafricaleaders.com" style="color: #2B5470; font-weight: bold;">learn.vantageafricaleaders.com</a></p>
                        <p style="margin: 5px 0;"><strong>👤 Username:</strong> ' . htmlspecialchars($username) . '</p>
                        <p style="margin: 5px 0;"><strong>🔑 Password:</strong> ' . htmlspecialchars($password ?? '[Use your registered password]') . '</p>
                    </div>
                    <div style="background-color: #fff3cd; padding: 15px; border: 1px solid #ffc107; margin: 15px 0; border-radius: 5px;">
                        <p style="margin: 0; font-weight: bold; color: #856404;">🔒 Please change your password after your first login for security.</p>
                    </div>
                    <p>If you experience any issues accessing the portal, please contact us at <a href="mailto:isabel@vantageafricaleaders.com" style="color: #2B5470;">isabel@vantageafricaleaders.com</a></p>
                    <p><strong>Warm regards,</strong><br>
                    <span style="color: #A85431; font-weight: bold;">Vantage Africa School of Leadership</span></p>
                </div>
            </div>
            <div style="padding: .5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                <div style="color: #9ba4b3; font-size: .8rem; margin: 10px 0;">
                    We sent this email to <span>' . htmlspecialchars($email) . '</span>
                </div>
                <div style="color: #9ba4b3; font-size: .8rem;">
                    &copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    $email_sent = send_mail_function($email, $body, $subject, []);
    
    log_email(
        $conn, 'register', $entry_id, 'moodle_credentials',
        $email, $name, $subject, [],
        $email_sent ? 'sent' : 'failed',
        $email_sent ? null : 'Failed to resend Moodle credentials',
        $sent_by, $record_id
    );
    
    return [
        'success' => $email_sent,
        'error' => $email_sent ? null : 'Failed to send email'
    ];
}