<?php
require_once 'vendor/autoload.php';  // Ensure this path is correct
require "conn.php";
require_once 'Parsedown.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Function to retrieve course name from the database
function check_course($conn, $id) {
    $check = mysqli_query($conn, "SELECT * FROM course WHERE course_id='$id'") or die(mysqli_error($conn));
    if (mysqli_num_rows($check) > 0) {
        $row = mysqli_fetch_array($check);
        return $row['course'];
    }
    return $id;
}

// SMTP setup
$host = 'mail.vantageafricaleaders.com';
$port = 587;
$username = 'no-reply@vantageafricaleaders.com';
$password = 'Bebblessed2020';

// Create Symfony Mailer Transport
$dsn = "smtp://$username:$password@$host:$port";
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$check = mysqli_query($conn, "
    SELECT program, email, id, firstname, MAX(datee) as datee
    FROM register
    WHERE DATE(datee) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    GROUP BY email
") or die(mysqli_error($conn));

if (mysqli_num_rows($check) > 0) {
    while ($row = mysqli_fetch_array($check)) {
        $firstname = ucwords(strtolower($row['firstname']));
        $program = check_course($conn, $row['program']);

        $selecct = mysqli_query($conn, "
            SELECT * FROM system_emails1
            WHERE course_opt='$program' AND email_opt = 2
        ") or die(mysqli_error($conn));

        $row_result = mysqli_fetch_array($selecct);

        // Personalize email content
        $f = ucfirst(strtolower($firstname)) . ",";
        $body = json_decode($row_result['body'], true);
        $body = str_replace('$name', $f, $body);

        echo $f . "<br>";

        // Create email message
        $emailMessage = (new Email())
            ->from('ceo@vantageafricaleaders.com')
            ->to($row['email'])
            ->subject($row_result['subject'])
            ->html($body)
            ->replyTo('ceo@vantageafricaleaders.com');

        // Sending email with retry logic
        $maxRetries = 1;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $mailer->send($emailMessage);
                echo "Email sent successfully to " . $row['email'] . "!<br>";
                break;
            } catch (Exception $e) {
                echo "Error sending email to " . $row['email'] . ": " . $e->getMessage() . "<br>";
                if ($attempt == $maxRetries) {
                    echo "Failed to send email after $maxRetries attempts.<br>";
                }
            }
        }

        // Delay between emails
        sleep(5);
    }
}

$conn->close();
?>
