<?php
require "conn.php";
require_once 'Parsedown.php';
require 'vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;



// === FETCH selected_emails FOR TODAY ===
$sql = "SELECT selected_emails FROM system_emails_config WHERE DATE(scheduled_date) = CURDATE()";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("No emails scheduled for today.");
}

$row = $result->fetch_assoc();
$selected_emails = $conn->real_escape_string($row['selected_emails']);

// === FETCH EMAIL TEMPLATE ===
$templateQuery = "SELECT subject, body,course_opt FROM system_emails1 WHERE id = $selected_emails";
$templateResult = $conn->query($templateQuery);

if ($templateResult->num_rows == 0) {
    die("Email template not found for selected_emails = $selected_emails");
}

$template = $templateResult->fetch_assoc();
$selected_emails = $template['course_opt'];
$email_subject = $template['subject'];
$template_body_raw = $template['body'];
$template_body_array = json_decode($template_body_raw, true);
$template_body = is_array($template_body_array) ? $template_body_array[0] : $template_body_raw; // Fallback if not an array

// === FETCH RECIPIENTS ===
$recipientsQuery = "
    SELECT fullname, email 
    FROM ticket_congress 
    WHERE event_id = '$selected_emails'  LIMIT 1
";
$recipientsResult = $conn->query($recipientsQuery);

if ($recipientsResult->num_rows == 0) {
    die("No recipients found for event_id = $selected_emails");
}

// === SETUP MAILER ===
$smtp_host = 'mail.vantageafricaleaders.com';
$smtp_user = 'no-reply@vantageafricaleaders.com';
$smtp_pass = 'Bebblessed2020';
$smtp_port = 587;

$dsn = "smtp://$smtp_user:$smtp_pass@$smtp_host:$smtp_port";
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

// === LOOP AND SEND EMAILS ===
while ($recipient = $recipientsResult->fetch_assoc()) {
    $firstname = explode(" ", trim($recipient['fullname']))[0];
    $f = ucfirst(strtolower($firstname)) . ",";

 
      $body = json_decode($template_body, true);
     $personalized_body = str_replace('$name',$f, $body);
     
  

    $email = (new Email())
        ->from($smtp_user)
        ->to($recipient['email'])
        ->subject($email_subject)
        ->html($personalized_body);

    try {
        $mailer->send($email);
        echo "Email sent to {$recipient['email']}<br>";
    } catch (Exception $e) {
        echo "Failed to send email to {$recipient['email']}: " . $e->getMessage() . "<br>";
    }
}

$conn->close();
