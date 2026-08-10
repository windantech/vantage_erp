<?php
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture and sanitize the form data
    $email_type = isset($_POST['email_type']) ? $conn->real_escape_string($_POST['email_type']) : 'virtual';
    $email_subject = $conn->real_escape_string($_POST['email_subject']);
    $course_opt = isset($_POST['course_opt']) ? $conn->real_escape_string($_POST['course_opt']) : '';
    $event_opt = isset($_POST['event_opt']) ? $conn->real_escape_string($_POST['event_opt']) : '';
    $event_id = isset($_POST['event_id']) ? $conn->real_escape_string($_POST['event_id']) : '';
    $event_name = isset($_POST['event_name']) ? $conn->real_escape_string($_POST['event_name']) : '';
    $email_opt = $conn->real_escape_string($_POST['email_opt']);
    $temp_opt = $conn->real_escape_string($_POST['temp_opt']);
    
    // Ensure the email_type enum can store 'academic' (one-time widen so academic
    // programme templates save with the right type). Cheap check; ALTER runs once.
    $col = $conn->query("SHOW COLUMNS FROM system_emails1 LIKE 'email_type'");
    if ($col && ($cr = $col->fetch_assoc()) && stripos($cr['Type'], "'corporate'") === false) {
        $conn->query("ALTER TABLE system_emails1 MODIFY email_type ENUM('virtual','international','academic','corporate') NOT NULL DEFAULT 'virtual'");
    }

    // Remove \r\n (carriage return and newline) from email_body
    $email_body = str_replace(["\r", "\n"], '', $_POST['email_body']);
    
    // Convert the email_body to a JSON string
    $body_json = json_encode($email_body);
    
    // Check if JSON encoding failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 0;
        exit;
    }
    
    // Use a prepared statement to insert the data into the database
    // Updated to include email_type, event_id, and event_name columns
    $stmt = $conn->prepare("INSERT INTO system_emails1 (`email_type`, `subject`, `course_opt`, `event_id`, `event_name`, `email_opt`, `temp_opt`, `body`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        // If prepare fails, try without the new columns (backward compatibility)
        $stmt = $conn->prepare("INSERT INTO system_emails1 (`subject`, `course_opt`, `email_opt`, `temp_opt`, `body`) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $email_subject, $course_opt, $email_opt, $temp_opt, $body_json);
    } else {
        $stmt->bind_param("ssssssss", $email_type, $email_subject, $course_opt, $event_id, $event_name, $email_opt, $temp_opt, $body_json);
    }
    
    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }
    
    $stmt->close();
}
?>