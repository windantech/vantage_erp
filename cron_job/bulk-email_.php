<?php
require_once 'vendor/autoload.php';  // Ensure this path is correct
require "conn.php";
require_once 'Parsedown.php';

$year = date("Y");

// Function to retrieve email content from the database
function fetchEmailContent($conn, $id, $field) {
    $stmt = $conn->prepare("SELECT * FROM `marketing_email_messages` WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result->num_rows > 0) ? $result->fetch_assoc()[$field] : "";
}

// Function to check SMTP connection
function checkSmtpConnection($host, $port) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$connection) {
        return false; // Connection failed
    }
    fclose($connection);
    return true; // Connection successful
}

// SMTP setup
$host = 'mail.vantageafricaleaders.com';
$port = 587;
$username = 'no-reply@vantageafricaleaders.com';
$password = 'Bebblessed2020';

$check = $conn->query("SELECT * FROM `scheduled_email` WHERE status=1 AND email LIKE '%@%' ORDER BY id DESC LIMIT 10");
if(mysqli_num_rows($check) > 0){
// Check SMTP connection before proceeding
if (!checkSmtpConnection($host, $port)) {
    die("Error: Cannot connect to SMTP server. Please check server status or credentials.");
}

// Setup SwiftMailer Transport
$transport = new Swift_SmtpTransport($host, $port, 'tls');
$transport->setUsername($username)
          ->setPassword($password);

// Create the Mailer instance
$mailer = new Swift_Mailer($transport);

// Retrieve and clean up scheduled emails
$conn->query("DELETE FROM scheduled_email WHERE email NOT LIKE '%@%'") or die(mysqli_error($conn));
$check = $conn->query("SELECT * FROM `scheduled_email` WHERE status=1 AND email LIKE '%@%' ORDER BY id DESC LIMIT 10");

$recipients = [];
$email_ids = [];
$student_ids = [];

if ($check->num_rows > 0) {
    while ($row = $check->fetch_assoc()) {
        $email = str_replace(' ', '', $row['email']);
        $recipients[$email] = $row['firstname'];
        $email_ids[] = $row['bulk_email_id'];
        $student_ids[] = $row['id'];
        // echo $email."<br>";
    }
}

// Send emails dynamically
foreach ($recipients as $email => $name) {
    $count = 0;
    $email_id = $email_ids[$count];
   
    $student_id = $student_ids[$count];
 
    $conn->query("UPDATE scheduled_email SET status=2, date_sent=NOW() WHERE email='$email'");

    // Prepare email content and Parsedown for markdown
    $Parsedown = new Parsedown();
    $emailBody = $Parsedown->text(fetchEmailContent($conn, $email_id, "body"));
    $emailSubject = fetchEmailContent($conn, $email_id, "subject");
    $attachmentPath = "https://vantageafricaleaders.com/admin/attachments/" . fetchEmailContent($conn, $email_id, "attachment");

    // Build email body with HTML content
    $body = '<html>
    <head><meta charset="utf-8"><title>Email Template</title>
    <style> /* CSS styles here */ </style>
    </head>
    <body>
        <table cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr><td>Hi '. $name.',</td></tr>
            <tr><td><p>' . $emailBody . '</p></td></tr>
            <tr><td><footer><p>&copy; ' . $year . ' Vantage Africa School Of Leadership. All rights reserved. <a href="#">Unsubscribe</a></p></footer></td></tr>
        </table>
    </body>
    </html>';

    // Configure the email message
    $message = new Swift_Message($emailSubject);
    $message->setFrom(['ceo@vantageafricaleaders.com' => 'Vantage Africa School Of Leadership'])
            ->setTo([$email => $name])
            ->setReplyTo('ceo@vantageafricaleaders.com')
            ->setBody($body, 'text/html');

    // Attach file if it exists
    if (!empty($attachmentPath) && file_exists($attachmentPath)) {
        $message->attach(Swift_Attachment::fromPath($attachmentPath));
    }

    // Sending with retry logic
    $maxRetries = 1;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $result = $mailer->send($message);
            if ($result) {
                echo "Email sent successfully to $email!<br>";
                // break;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "<br>";
            if ($attempt == $maxRetries) {
                echo "Failed to send email after $maxRetries attempts.<br>";
            }
            // sleep(5); // Wait before retrying
        }
    }

    // Delay between emails
    sleep(5);
    $count++;
}

$conn->close();
}
?>
