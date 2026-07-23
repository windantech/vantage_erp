<?php
session_start();
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture and sanitize the form data
    $email_subject = $conn->real_escape_string($_POST['upd_email_subject']);
    $course_opt = $conn->real_escape_string($_POST['upd_course_opt']);
    $email_opt = $conn->real_escape_string($_POST['upd_email_opt']);
    $temp_opt = $conn->real_escape_string($_POST['upd_temp_opt']);
    $upd_id = $conn->real_escape_string($_POST['upd_id']); // Get the ID for the update
    $updated_by = $conn->real_escape_string($_SESSION['login_id']); // Capture who made the update

    // Get the current date and time in the format YYYY-MM-DD HH:MM:SS
    $last_updated = date('Y-m-d');

    // Remove \r\n (carriage return and newline) from email_body
    $email_body = str_replace(["\r", "\n"], '', $_POST['upd_email_body']);

    // Convert the email_body to a JSON string (if needed)
    $body_json = json_encode($email_body);

    // Check if JSON encoding failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 0;
        exit; // Exit to avoid executing further code
    }

    // Use a prepared statement to update the record in the database
    $stmt = $conn->prepare("
        UPDATE system_emails1 
        SET `subject` = ?, 
            `course_opt` = ?, 
            `email_opt` = ?, 
            `temp_opt` = ?, 
            `body` = ?, 
            `updated_by` = ?, 
            `last_updated` = ? 
        WHERE `id` = ?
    ");
    $stmt->bind_param(
        "sssssssi", 
        $email_subject, 
        $course_opt, 
        $email_opt, 
        $temp_opt, 
        $body_json, 
        $updated_by, 
        $last_updated, 
        $upd_id
    );

    if ($stmt->execute()) {
        echo 1; // Success response
    } else {
        echo 2; // Error response
    }

    $stmt->close();
}
?>
