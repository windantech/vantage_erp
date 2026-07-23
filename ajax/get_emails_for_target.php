<?php
/**
 * AJAX: Get emails for a specific course / event
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../database/conn.php';

/* ---------- SAFETY CHECKS ---------- */
if (!isset($conn) || !$conn) {
    echo json_encode([
        'emails' => [],
        'count'  => 0,
        'error'  => 'Database connection failed'
    ]);
    exit;
}

/* ---------- INPUT ---------- */
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$target_id = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;

if ($type === '' || $target_id === 0) {
    echo json_encode([
        'emails' => [],
        'count'  => 0,
        'error'  => 'Missing parameters'
    ]);
    exit;
}

/* ---------- QUERY ---------- */
if ($type === 'international') {
    $query = "
        SELECT id, email_opt, subject, course_opt
        FROM system_emails1
        WHERE event_id = $target_id
          AND email_type = 'international'
        ORDER BY email_opt ASC
    ";
} else {
    $query = "
        SELECT id, email_opt, subject, course_opt
        FROM system_emails1
        WHERE event_id = $target_id
          AND (email_type = 'virtual' OR email_type IS NULL OR email_type = '')
        ORDER BY email_opt ASC
    ";
}

/* ---------- EXECUTE ---------- */
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode([
        'emails' => [],
        'count'  => 0,
        'error'  => mysqli_error($conn)
    ]);
    exit;
}

/* ---------- BUILD RESPONSE ---------- */
$emails = [];
while ($row = mysqli_fetch_assoc($result)) {
    $subject = mb_convert_encoding($row['subject'] ?? 'No Subject', 'UTF-8', 'UTF-8');
    $course_opt = mb_convert_encoding($row['course_opt'] ?? '', 'UTF-8', 'UTF-8');

    $emails[] = [
        'id'        => (int)$row['id'],
        'email_opt' => (int)$row['email_opt'],
        'subject'   => $subject,
        'course_opt'=> $course_opt
    ];
}

echo json_encode([
    'emails' => $emails,
    'count'  => count($emails)
], JSON_UNESCAPED_UNICODE);

exit;