<?php


// Setup SwiftMailer transport
$transport = new Swift_SmtpTransport('mail.vantageafricaleaders.com', 587);
$transport->setUsername('no-reply@vantageafricaleaders.com');
$transport->setPassword('Bebblessed2020');

// Create the Mailer using your created Transport
$mailer = new Swift_Mailer($transport);

try {
    // Create a message
    $message = new Swift_Message($subject);
    $message->setFrom(['no-reply@vantageafricaleaders.com' => 'Vantage Africa School Of Leadership']);
    $message->setReplyTo(['bkiarie@vantageafricaleaders.com' => 'Benson Kiarie']);
    $message->setTo($receiver);
    $message->setBody($body, 'text/html');

    // Send the email
    $result = $mailer->send($message);

    if ($result) {
        echo "Email sent to $receiver successfully!<br>";
    } else {
        echo "Failed to send email to $receiver.<br>";
    }
} catch (Swift_TransportException $e) {
    // Log or handle the exception
    echo "Error sending email: " . $e->getMessage();
}
