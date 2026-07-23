<?php
require_once 'swiftmailer/vendor/autoload.php';
require "conn.php";
 function send_mail_function($email_address,$body,$subject)
    {

        // Choose the transport method (SMTP, Sendmail, etc.)
        $transport = new Swift_SmtpTransport('rbx107.truehost.cloud', 587);
        $transport->setUsername('no-reply@vantageafricaleaders.com');
        $transport->setPassword('Bebblessed2020');

        // Create the SwiftMailer instance
        $mailer = new Swift_Mailer($transport);

        // Array of recipient emails and names
        $recipients = [
            $email_address => 'Recipient 1',
            // Add more recipients as needed
        ];

        foreach ($recipients as $email => $name) {
            // Create a new Swift_Message for each recipient
            $message = new Swift_Message($subject);
            $message->setFrom(['no-reply@africaairliftinitiative.com' => 'Africa Airlift Initiative']);
            $message->setTo([$email => $name]);

            // Replace placeholders in the body with actual values
            $bodyWithYear = str_replace('{year}', $year, $body);
            $message->setBody($bodyWithYear,
            'text/html');

            // Send the email
            $result = $mailer->send($message);
            sleep(1); // Optional delay between sending emails
        }
    
}
send_mail_function("bidoom1234@gmail.com","Body","Subject");