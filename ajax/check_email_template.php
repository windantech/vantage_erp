<?php
/**
 * AJAX: Get emails from system_emails1 for a specific course/event
 */
session_start();
require_once '../../database/conn.php';

header('Content-Type: application/json');

$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$target_id = isset($_GET['target_id']) ? intval($_GET['target_id']) : 0;

if (!$type || !$target_id) {
    echo json_encode(['emails' => [], 'error' => 'Missing parameters']);
    exit;
}

// Build query based on type
if ($type == 'international') {
    $query = "SELECT id, email_opt, subject, course_opt 
              FROM system_emails1 
              WHERE event_id = $target_id 
              AND email_type = 'international'
              ORDER BY email_opt ASC";
} else {
    $query = "SELECT id, email_opt, subject, course_opt 
              FROM system_emails1 
              WHERE event_id = $target_id 
              AND (email_type = 'virtual' OR email_type IS NULL OR email_type = '')
              ORDER BY email_opt ASC";
}

$result = mysqli_query($conn, $query);

$emails = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $emails[] = [
            'id' => $row['id'],
            'email_opt' => $row['email_opt'],
            'subject' => $row['subject'],
            'course_opt' => $row['course_opt']
        ];
    }
}

echo json_encode(['emails' => $emails]);

mysqli_close($conn);
?>