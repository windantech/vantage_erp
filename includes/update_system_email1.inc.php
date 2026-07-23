<?php
require '../../database/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture the form data
    $email_title = $_POST['email_title'];
    $email_subject = $_POST['email_subject'];
    $alert_title = $_POST['alert_title'];

    // Replace <br> with \r\n before escaping the string
    $main_content = preg_replace('/<br\s*\/?>/', "\r\n", $_POST['main_content']);
    $call_to_action = $_POST['call_to_action'];
    $invitation_message = $_POST['invitation_message'];
    $schedule_link = $_POST['schedule_link'];
    $trainer_link = $_POST['trainer_link'];

    // Same for other fields where <br> may appear
    $training_details = preg_replace('/<br\s*\/?>/', "\r\n", $_POST['training_details']);
    $elearning_link = $_POST['elearning_link'];
    $training_reasons = preg_replace('/<br\s*\/?>/', "\r\n", $_POST['training_reasons']);
    $video_link = $_POST['video_link'];
    $training_link = $_POST['training_link'];
    $whatsapp_link = $_POST['whatsapp_link'];
    $registration_link = $_POST['registration_link'];
    $investment_text = preg_replace('/<br\s*\/?>/', "\r\n", $_POST['investment_text']);
    $class_coordinator_link = $_POST['class_coordinator_link'];
    $referral_link = $_POST['referral_link'];

    // Now sanitize and escape strings after replacing <br> with \r\n
    $email_title = $conn->real_escape_string($email_title);
    $email_subject = $conn->real_escape_string($email_subject);
    $alert_title = $conn->real_escape_string($alert_title);
    $main_content = $conn->real_escape_string($main_content);
    $call_to_action = $conn->real_escape_string($call_to_action);
    $invitation_message = $conn->real_escape_string($invitation_message);
    $schedule_link = $conn->real_escape_string($schedule_link);
    $trainer_link = $conn->real_escape_string($trainer_link);
    $training_details = $conn->real_escape_string($training_details);
    $elearning_link = $conn->real_escape_string($elearning_link);
    $training_reasons = $conn->real_escape_string($training_reasons);
    $video_link = $conn->real_escape_string($video_link);
    $training_link = $conn->real_escape_string($training_link);
    $whatsapp_link = $conn->real_escape_string($whatsapp_link);
    $registration_link = $conn->real_escape_string($registration_link);
    $investment_text = $conn->real_escape_string($investment_text);
    $class_coordinator_link = $conn->real_escape_string($class_coordinator_link);
    $referral_link = $conn->real_escape_string($referral_link);

    // Create an associative array to store the body content
    $body = [
        'alert_title' => $alert_title,
        'main_content' => $main_content,
        'call_to_action' => $call_to_action,
        'invitation_message' => $invitation_message,
        'schedule_link' => $schedule_link,
        'trainer_link' => $trainer_link,
        'training_details' => $training_details,
        'elearning_link' => $elearning_link,
        'training_reasons' => $training_reasons,
        'video_link' => $video_link,
        'training_link' => $training_link,
        'whatsapp_link' => $whatsapp_link,
        'registration_link' => $registration_link,
        'investment_text' => $investment_text,
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
