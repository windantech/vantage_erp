<?php
/**
 * Cron Job: Send Email 2 to registrations from yesterday
 * 
 * This script checks for:
 * 1. Virtual course registrations (register table) from 1 day ago
 * 2. International event registrations (ticket_congress table) from 1 day ago
 * 
 * Then sends them Email 2 from the system_emails1 table
 * Uses event_id column which stores:
 *   - course_id for virtual courses
 *   - event_id for international events
 * 
 * Recommended cron schedule: Run daily at 8:00 AM
 * 0 8 * * * /usr/bin/php /path/to/cron_job_new/send_email2_day_after.php
 */

// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Database connection
require_once '../../database/conn.php';

// Email function
require_once __DIR__ . '/../email_plugins/vendor/autoload.php';
require_once __DIR__ . '/../email_plugins/email_function.php';

// Log file for tracking
$log_file = __DIR__ . '/logs/email2_cron_' . date('Y-m-d') . '.log';

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

/**
 * Write to log file
 */
function write_log($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

/**
 * Get course_id from course table using program value
 */
function get_course_id($conn, $program_id) {
    // First try by course_id
    $result = mysqli_query($conn, "SELECT course_id FROM course WHERE course_id = " . intval($program_id) . " LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['course_id'];
    }
    // Fallback: check by id and return course_id
    $result = mysqli_query($conn, "SELECT course_id FROM course WHERE id = " . intval($program_id) . " LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['course_id'];
    }
    return $program_id; // Return original if not found
}

// ============================================
// MAIN EXECUTION
// ============================================

write_log("========== Starting Email 2 Cron Job ==========", $log_file);

$yesterday = date('Y-m-d', strtotime('-1 day'));
write_log("Looking for registrations from: $yesterday", $log_file);

$total_sent = 0;
$total_failed = 0;

// ============================================
// PROCESS VIRTUAL COURSE REGISTRATIONS (UNPAID ONLY)
// ============================================

write_log("--- Processing Virtual Course Registrations (Unpaid Only) ---", $log_file);

// Get registrations from yesterday that have NOT paid
// Check dpo_payment table for payments
$check = mysqli_query($conn, "
    SELECT r.* FROM register r
    LEFT JOIN dpo_payment p ON r.entry_id = p.app_id
    WHERE DATE(r.datee) = '$yesterday' 
    AND r.email IS NOT NULL 
    AND r.email != ''
    AND (p.id IS NULL OR p.TransactionAmount IS NULL OR p.TransactionAmount = 0)
") or die(mysqli_error($conn));

if (mysqli_num_rows($check) > 0) {
    write_log("Found " . mysqli_num_rows($check) . " virtual registrations", $log_file);
    
    while ($row = mysqli_fetch_array($check)) {
        $firstname = ucwords(strtolower($row['firstname']));
        $course_id = get_course_id($conn, $row['program']);
        
        write_log("Processing: " . $row['email'] . " ($firstname) - Course ID: $course_id", $log_file);
        
        // Get email template using event_id (which stores course_id for virtual)
        $selecct = mysqli_query($conn, "
            SELECT * FROM system_emails1
            WHERE event_id = " . intval($course_id) . " 
            AND email_opt = 2 
            AND (email_type = 'virtual' OR email_type IS NULL OR email_type = '')
        ") or die(mysqli_error($conn));
        
        if (mysqli_num_rows($selecct) == 0) {
            write_log("  WARNING: No Email 2 template found for course ID: $course_id", $log_file);
            $total_failed++;
            continue;
        }
        
        $row_result = mysqli_fetch_array($selecct);
        
        // Personalize email content
        $f = ucfirst(strtolower($firstname)) . ",";
        $body = json_decode($row_result['body'], true);
        $body = str_replace('$name', $f, $body);
        
        echo $f . "<br>";
        
        // Send email using send_mail_function
        try {
            $result = send_mail_function($row['email'], $body, $row_result['subject']);
            
            if ($result) {
                echo "Email sent successfully to " . $row['email'] . "!<br>";
                write_log("  SUCCESS: Email sent to " . $row['email'], $log_file);
                $total_sent++;
            } else {
                echo "Failed to send email to " . $row['email'] . "<br>";
                write_log("  FAILED: Could not send to " . $row['email'], $log_file);
                $total_failed++;
            }
        } catch (Exception $e) {
            echo "Error sending email to " . $row['email'] . ": " . $e->getMessage() . "<br>";
            write_log("  ERROR: " . $e->getMessage(), $log_file);
            $total_failed++;
        }
        
        // Delay between emails
        sleep(5);
    }
} else {
    write_log("No virtual registrations found for $yesterday", $log_file);
}

// ============================================
// PROCESS INTERNATIONAL EVENT REGISTRATIONS (UNPAID ONLY)
// ============================================

write_log("--- Processing International Event Registrations (Unpaid Only) ---", $log_file);

// Get registrations from yesterday that have NOT paid
// status = 2 means paid, so we get status != 2 or status IS NULL
$check_intl = mysqli_query($conn, "
    SELECT * FROM ticket_congress 
    WHERE DATE(date_sent) = '$yesterday' 
    AND email IS NOT NULL 
    AND email != ''
    AND (status != 2 OR status IS NULL)
") or die(mysqli_error($conn));

if (mysqli_num_rows($check_intl) > 0) {
    write_log("Found " . mysqli_num_rows($check_intl) . " international registrations", $log_file);
    
    while ($row = mysqli_fetch_array($check_intl)) {
        $fullname = ucwords(strtolower($row['fullname']));
        // Get first name from fullname
        $name_parts = explode(' ', $fullname);
        $firstname = $name_parts[0];
        
        $event_id = $row['event_id'];
        
        write_log("Processing: " . $row['email'] . " ($firstname) - Event ID: $event_id", $log_file);
        
        // Get email template using event_id for international
        $selecct = mysqli_query($conn, "
            SELECT * FROM system_emails1
            WHERE event_id = " . intval($event_id) . " 
            AND email_opt = 2 
            AND email_type = 'international'
        ") or die(mysqli_error($conn));
        
        if (mysqli_num_rows($selecct) == 0) {
            write_log("  WARNING: No Email 2 template found for event ID: $event_id", $log_file);
            $total_failed++;
            continue;
        }
        
        $row_result = mysqli_fetch_array($selecct);
        
        // Personalize email content
        $f = ucfirst(strtolower($firstname)) . ",";
        $body = json_decode($row_result['body'], true);
        $body = str_replace('$name', $f, $body);
        
        echo $f . "<br>";
        
        // Send email using send_mail_function
        try {
            $result = send_mail_function($row['email'], $body, $row_result['subject']);
            
            if ($result) {
                echo "Email sent successfully to " . $row['email'] . "!<br>";
                write_log("  SUCCESS: Email sent to " . $row['email'], $log_file);
                $total_sent++;
            } else {
                echo "Failed to send email to " . $row['email'] . "<br>";
                write_log("  FAILED: Could not send to " . $row['email'], $log_file);
                $total_failed++;
            }
        } catch (Exception $e) {
            echo "Error sending email to " . $row['email'] . ": " . $e->getMessage() . "<br>";
            write_log("  ERROR: " . $e->getMessage(), $log_file);
            $total_failed++;
        }
        
        // Delay between emails
        sleep(5);
    }
} else {
    write_log("No international registrations found for $yesterday", $log_file);
}

// ============================================
// SUMMARY
// ============================================

write_log("========== Cron Job Complete ==========", $log_file);
write_log("Total Sent: $total_sent", $log_file);
write_log("Total Failed: $total_failed", $log_file);
write_log("==========================================", $log_file);

// Close database connection
mysqli_close($conn);
?>