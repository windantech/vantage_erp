<?php
/**
 * Email Logging Functions
 * Track all sent communications (welcome, admission, invoice, receipt, etc.)
 */

// ============================================
// LOG EMAIL FUNCTION
// ============================================

/**
 * Log an email that was sent
 * 
 * @param mysqli $conn Database connection
 * @param string $source_type 'register', 'ticket_congress', 'enquiry', 'other'
 * @param string $source_id entry_id, ticket_id, or enquiry_ref
 * @param string $email_type 'welcome', 'admission_letter', 'invoice', 'receipt', 'reminder', 'followup', 'custom', 'moodle_credentials'
 * @param string $recipient_email
 * @param string $recipient_name
 * @param string $subject
 * @param array $attachments Array of file paths
 * @param string $status 'sent', 'failed', 'pending', 'queued'
 * @param string $error_message Error message if failed
 * @param int $sent_by User ID who triggered (null for auto)
 * @param int $record_id Primary key ID from source table
 * @return array ['success' => bool, 'log_id' => int, 'error' => string]
 */
function log_email($conn, $source_type, $source_id, $email_type, $recipient_email, $recipient_name = null, $subject = null, $attachments = [], $status = 'sent', $error_message = null, $sent_by = null, $record_id = null) {
    
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_logs'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        return ['success' => false, 'error' => 'email_logs table does not exist'];
    }
    
    // Prepare data
    $source_type = mysqli_real_escape_string($conn, $source_type);
    $source_id = mysqli_real_escape_string($conn, $source_id);
    $email_type = mysqli_real_escape_string($conn, $email_type);
    $recipient_email = mysqli_real_escape_string($conn, $recipient_email);
    $recipient_name = $recipient_name ? "'" . mysqli_real_escape_string($conn, $recipient_name) . "'" : "NULL";
    $subject = $subject ? "'" . mysqli_real_escape_string($conn, $subject) . "'" : "NULL";
    $status = mysqli_real_escape_string($conn, $status);
    $error_message = $error_message ? "'" . mysqli_real_escape_string($conn, $error_message) . "'" : "NULL";
    $sent_by = $sent_by ? intval($sent_by) : "NULL";
    $record_id = $record_id ? intval($record_id) : "NULL";
    
    // Handle attachments
    $has_attachments = !empty($attachments) ? 1 : 0;
    $attachment_paths = !empty($attachments) ? "'" . mysqli_real_escape_string($conn, json_encode($attachments)) . "'" : "NULL";
    
    // Get IP address
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? "'" . mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']) . "'" : "NULL";
    
    $query = "INSERT INTO email_logs (
        source_type, source_id, record_id, email_type, 
        recipient_email, recipient_name, subject,
        has_attachments, attachment_paths,
        status, error_message, sent_by, ip_address
    ) VALUES (
        '$source_type', '$source_id', $record_id, '$email_type',
        '$recipient_email', $recipient_name, $subject,
        $has_attachments, $attachment_paths,
        '$status', $error_message, $sent_by, $ip_address
    )";
    
    if (mysqli_query($conn, $query)) {
        return ['success' => true, 'log_id' => mysqli_insert_id($conn)];
    } else {
        return ['success' => false, 'error' => mysqli_error($conn)];
    }
}

// ============================================
// GET EMAIL LOGS FOR A RECORD
// ============================================

/**
 * Get all email logs for a specific record
 * 
 * @param mysqli $conn Database connection
 * @param string $source_type 'register', 'ticket_congress', 'enquiry'
 * @param string $source_id entry_id, ticket_id, or enquiry_ref
 * @return array List of email logs
 */
function get_email_logs($conn, $source_type, $source_id) {
    $logs = [];
    
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_logs'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        return $logs;
    }
    
    $source_type = mysqli_real_escape_string($conn, $source_type);
    $source_id = mysqli_real_escape_string($conn, $source_id);
    
    $query = "SELECT el.*, ru.fullname AS sent_by_name 
              FROM email_logs el
              LEFT JOIN registered_users ru ON el.sent_by = ru.id
              WHERE el.source_type = '$source_type' AND el.source_id = '$source_id'
              ORDER BY el.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Decode attachment paths
            if ($row['attachment_paths']) {
                $row['attachments'] = json_decode($row['attachment_paths'], true);
            } else {
                $row['attachments'] = [];
            }
            $logs[] = $row;
        }
    }
    
    return $logs;
}

// ============================================
// GET EMAIL TYPE DISPLAY INFO
// ============================================

/**
 * Get display info for email type (label, icon, color)
 * 
 * @param string $email_type
 * @return array ['label', 'icon', 'color']
 */
function get_email_type_display($email_type) {
    $types = [
        'welcome' => [
            'label' => 'Welcome Email',
            'icon' => 'bi-envelope-heart',
            'color' => 'success'
        ],
        'admission_letter' => [
            'label' => 'Admission Letter',
            'icon' => 'bi-file-earmark-text',
            'color' => 'primary'
        ],
        'invoice' => [
            'label' => 'Invoice',
            'icon' => 'bi-receipt',
            'color' => 'warning'
        ],
        'receipt' => [
            'label' => 'Payment Receipt',
            'icon' => 'bi-check2-square',
            'color' => 'success'
        ],
        'reminder' => [
            'label' => 'Reminder',
            'icon' => 'bi-bell',
            'color' => 'info'
        ],
        'followup' => [
            'label' => 'Follow-up',
            'icon' => 'bi-reply',
            'color' => 'secondary'
        ],
        'moodle_credentials' => [
            'label' => 'LMS Credentials',
            'icon' => 'bi-mortarboard',
            'color' => 'info'
        ],
        'custom' => [
            'label' => 'Custom Email',
            'icon' => 'bi-envelope',
            'color' => 'secondary'
        ]
    ];
    
    return $types[$email_type] ?? ['label' => ucfirst($email_type), 'icon' => 'bi-envelope', 'color' => 'secondary'];
}

// ============================================
// CHECK IF SPECIFIC EMAIL WAS SENT
// ============================================

/**
 * Check if a specific type of email was already sent to a record
 * 
 * @param mysqli $conn Database connection
 * @param string $source_type
 * @param string $source_id
 * @param string $email_type
 * @return bool
 */
function was_email_sent($conn, $source_type, $source_id, $email_type) {
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_logs'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        return false;
    }
    
    $source_type = mysqli_real_escape_string($conn, $source_type);
    $source_id = mysqli_real_escape_string($conn, $source_id);
    $email_type = mysqli_real_escape_string($conn, $email_type);
    
    $query = "SELECT id FROM email_logs 
              WHERE source_type = '$source_type' 
              AND source_id = '$source_id' 
              AND email_type = '$email_type'
              AND status = 'sent'
              LIMIT 1";
    
    $result = mysqli_query($conn, $query);
    return $result && mysqli_num_rows($result) > 0;
}

// ============================================
// GET LAST EMAIL OF TYPE
// ============================================

/**
 * Get the last email of a specific type sent to a record
 * 
 * @param mysqli $conn Database connection
 * @param string $source_type
 * @param string $source_id
 * @param string $email_type
 * @return array|null
 */
function get_last_email_of_type($conn, $source_type, $source_id, $email_type) {
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_logs'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        return null;
    }
    
    $source_type = mysqli_real_escape_string($conn, $source_type);
    $source_id = mysqli_real_escape_string($conn, $source_id);
    $email_type = mysqli_real_escape_string($conn, $email_type);
    
    $query = "SELECT * FROM email_logs 
              WHERE source_type = '$source_type' 
              AND source_id = '$source_id' 
              AND email_type = '$email_type'
              ORDER BY created_at DESC
              LIMIT 1";
    
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// ============================================
// COUNT EMAILS BY TYPE FOR A RECORD
// ============================================

/**
 * Count how many times each email type was sent to a record
 * 
 * @param mysqli $conn Database connection
 * @param string $source_type
 * @param string $source_id
 * @return array ['email_type' => count]
 */
function count_emails_by_type($conn, $source_type, $source_id) {
    $counts = [];
    
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_logs'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        return $counts;
    }
    
    $source_type = mysqli_real_escape_string($conn, $source_type);
    $source_id = mysqli_real_escape_string($conn, $source_id);
    
    $query = "SELECT email_type, COUNT(*) AS count 
              FROM email_logs 
              WHERE source_type = '$source_type' AND source_id = '$source_id'
              GROUP BY email_type";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[$row['email_type']] = intval($row['count']);
        }
    }
    
    return $counts;
}

// ============================================
// GET EMAIL SUMMARY FOR DISPLAY
// ============================================

/**
 * Get a summary of emails sent to a record for display
 * Returns structured data for UI display
 * 
 * @param mysqli $conn Database connection
 * @param string $source_type
 * @param string $source_id
 * @return array ['total' => int, 'by_type' => array, 'recent' => array]
 */
function get_email_summary($conn, $source_type, $source_id) {
    $summary = [
        'total' => 0,
        'by_type' => [],
        'recent' => []
    ];
    
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_logs'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        return $summary;
    }
    
    $source_type = mysqli_real_escape_string($conn, $source_type);
    $source_id = mysqli_real_escape_string($conn, $source_id);
    
    // Get total count
    $total_query = "SELECT COUNT(*) AS total FROM email_logs WHERE source_type = '$source_type' AND source_id = '$source_id'";
    $total_result = mysqli_query($conn, $total_query);
    if ($total_result && $row = mysqli_fetch_assoc($total_result)) {
        $summary['total'] = intval($row['total']);
    }
    
    // Get counts by type with last sent date
    $by_type_query = "SELECT email_type, COUNT(*) AS count, MAX(created_at) AS last_sent 
                      FROM email_logs 
                      WHERE source_type = '$source_type' AND source_id = '$source_id' AND status = 'sent'
                      GROUP BY email_type";
    $by_type_result = mysqli_query($conn, $by_type_query);
    if ($by_type_result) {
        while ($row = mysqli_fetch_assoc($by_type_result)) {
            $type_info = get_email_type_display($row['email_type']);
            $summary['by_type'][$row['email_type']] = [
                'count' => intval($row['count']),
                'last_sent' => $row['last_sent'],
                'label' => $type_info['label'],
                'icon' => $type_info['icon'],
                'color' => $type_info['color']
            ];
        }
    }
    
    // Get 5 most recent emails
    $recent_query = "SELECT * FROM email_logs 
                     WHERE source_type = '$source_type' AND source_id = '$source_id'
                     ORDER BY created_at DESC LIMIT 5";
    $recent_result = mysqli_query($conn, $recent_query);
    if ($recent_result) {
        while ($row = mysqli_fetch_assoc($recent_result)) {
            $type_info = get_email_type_display($row['email_type']);
            $row['type_info'] = $type_info;
            $summary['recent'][] = $row;
        }
    }
    
    return $summary;
}

// ============================================
// DELETE OLD EMAIL LOGS (CLEANUP)
// ============================================

/**
 * Delete email logs older than specified days
 * 
 * @param mysqli $conn Database connection
 * @param int $days Number of days to keep
 * @return int Number of deleted records
 */
function cleanup_old_email_logs($conn, $days = 365) {
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'email_logs'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        return 0;
    }
    
    $days = intval($days);
    $query = "DELETE FROM email_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)";
    
    if (mysqli_query($conn, $query)) {
        return mysqli_affected_rows($conn);
    }
    return 0;
}