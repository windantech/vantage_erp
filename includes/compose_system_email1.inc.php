<?php
include '../database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture and sanitize the form data
    $email_title = $conn->real_escape_string($_POST['email_title']);
    $email_subject = $conn->real_escape_string($_POST['email_subject']);
    $alert_title = $conn->real_escape_string($_POST['alert_title']);
    $main_content = str_replace(["\r", "\n"], '', $_POST['main_content']); // Remove \r\n from main_content
    $call_to_action = $conn->real_escape_string($_POST['call_to_action']);
    $invitation_message = $conn->real_escape_string($_POST['invitation_message']);
    $schedule_link = $conn->real_escape_string($_POST['schedule_link']);
    $trainer_link = $conn->real_escape_string($_POST['trainer_link']);
    $training_details = str_replace(["\r", "\n"], '', $_POST['training_details']); // Remove \r\n from training_details
    $elearning_link = $conn->real_escape_string($_POST['elearning_link']);
    $training_reasons = str_replace(["\r", "\n"], '', $_POST['training_reasons']); // Remove \r\n from training_reasons
    $video_link = $conn->real_escape_string($_POST['video_link']);
    $training_link = $conn->real_escape_string($_POST['training_link']);
    $whatsapp_link = $conn->real_escape_string($_POST['whatsapp_link']);
    $registration_link = $conn->real_escape_string($_POST['registration_link']);
    $investment_text = str_replace(["\r", "\n"], '', $_POST['investment_text']); // Remove \r\n from investment_text
    $class_coordinator_link = $conn->real_escape_string($_POST['class_coordinator_link']);
    $referral_link = $conn->real_escape_string($_POST['referral_link']);

    // Create an associative array to store the body content
    $body = [
        'alert_title' => $alert_title,
        'main_content' => $main_content, // Store HTML as-is, without \r\n
        'call_to_action' => $call_to_action,
        'invitation_message' => $invitation_message,
        'schedule_link' => $schedule_link,
        'trainer_link' => $trainer_link,
        'training_details' => $training_details, // Store HTML as-is, without \r\n
        'elearning_link' => $elearning_link,
        'training_reasons' => $training_reasons, // Store HTML as-is, without \r\n
        'video_link' => $video_link,
        'training_link' => $training_link,
        'whatsapp_link' => $whatsapp_link,
        'registration_link' => $registration_link,
        'investment_text' => $investment_text, // Store HTML as-is, without \r\n
        'class_coordinator_link' => $class_coordinator_link,
        'referral_link' => $referral_link
    ];

    // Convert the $body array to a JSON string
    $body_json = json_encode($body);

    // Check if JSON encoding failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON encoding failed: " . json_last_error_msg();
        exit;
    }

    // Use a prepared statement to insert the data into the database
    $stmt = $conn->prepare("INSERT INTO system_emails1 (`title`, `subject`, `body`) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email_title, $email_subject, $body_json);

    if ($stmt->execute()) {
        echo 1;
    } else {
        echo 2;
    }

    $stmt->close();
}
