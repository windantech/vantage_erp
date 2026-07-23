<?php
session_start();
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the ID
    $upd_id = $conn->real_escape_string($_POST['upd_id']);
    
    // Capture and sanitize the form data
    $email_type = isset($_POST['upd_email_type']) ? $conn->real_escape_string($_POST['upd_email_type']) : 'virtual';
    $subject = $conn->real_escape_string($_POST['upd_email_subject']);
    $course_opt = isset($_POST['upd_course_opt']) ? $conn->real_escape_string($_POST['upd_course_opt']) : '';
    $event_id = isset($_POST['upd_event_id']) ? $conn->real_escape_string($_POST['upd_event_id']) : '';
    $event_name = isset($_POST['upd_event_name']) ? $conn->real_escape_string($_POST['upd_event_name']) : '';
    $email_opt = $conn->real_escape_string($_POST['upd_email_opt']);
    $temp_opt = $conn->real_escape_string($_POST['upd_temp_opt']);
    
    // Remove \r\n (carriage return and newline) from email_body
    $email_body = str_replace(["\r", "\n"], '', $_POST['upd_email_body']); 
    
    // Convert the email_body to a JSON string
    $body_json = json_encode($email_body);
    
    // Check if JSON encoding failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 0;
        exit;
    }
    
    // Try to update with new columns first
    $stmt = $conn->prepare("UPDATE system_emails1 SET 
        `email_type` = ?, 
        `subject` = ?, 
        `course_opt` = ?, 
        `event_id` = ?, 
        `event_name` = ?, 
        `email_opt` = ?, 
        `temp_opt` = ?, 
        `body` = ? 
        WHERE `id` = ?");
    
    if (!$stmt) {
        // If prepare fails (new columns don't exist), try without them
        $stmt = $conn->prepare("UPDATE system_emails1 SET 
            `subject` = ?, 
            `course_opt` = ?, 
            `email_opt` = ?, 
            `temp_opt` = ?, 
            `body` = ? 
            WHERE `id` = ?");
        
        if ($stmt) {
            $stmt->bind_param("sssssi", $subject, $course_opt, $email_opt, $temp_opt, $body_json, $upd_id);
        } else {
            echo 2;
            exit;
        }
    } else {
        $stmt->bind_param("ssssssssi", $email_type, $subject, $course_opt, $event_id, $event_name, $email_opt, $temp_opt, $body_json, $upd_id);
    }
    
    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }
    
    $stmt->close();
    $conn->close();
}
?>