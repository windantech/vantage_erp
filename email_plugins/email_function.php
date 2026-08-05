<?php
if (!function_exists('send_mail_function')) {
   function send_mail_function($email_address, $body, $subject, $attachments = [])
{
    $year = date("Y");
    $bodyWithYear = str_replace('{year}', $year, $body);
    
    // Brevo API key. The live key is NEVER stored in the repo — GitHub secret
    // scanning revokes any key it finds (that caused a past outage). It lives in
    // an untracked, gitignored file created once on the server:
    //   email_plugins/email_key.php  ->  <?php $BREVO_API_KEY = 'xkeysib-...';
    // If the file is missing we leave the key empty so the failure is loud
    // (unauthenticated) instead of silently sending on a stale/wrong key.
    $apiKey = '';
    $keyFile = __DIR__ . '/email_key.php';
    if (is_file($keyFile)) {
        include $keyFile;
        if (!empty($BREVO_API_KEY)) {
            $apiKey = $BREVO_API_KEY;
        }
    }
    
    // Configure API
    $config = SendinBlue\Client\Configuration::getDefaultConfiguration()
        ->setApiKey('api-key', $apiKey);
    
    // Create API instance
    $apiInstance = new SendinBlue\Client\Api\TransactionalEmailsApi(
        new GuzzleHttp\Client(),
        $config
    );
    
    // Prepare email
    $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail();
    $sendSmtpEmail['subject'] = $subject;
    $sendSmtpEmail['htmlContent'] = $bodyWithYear;
    $sendSmtpEmail['sender'] = [
        'name' => 'Vantage Africa School Of Leadership',
        'email' => 'info@vantageafricaleaders.com'
    ];
    $sendSmtpEmail['to'] = [[
        'email' => $email_address,
        'name' => 'Recipient'
    ]];
    
    // Add attachments if any
    if (!empty($attachments)) {
        $attachmentArray = [];
        foreach ($attachments as $filePath) {
            if (is_file($filePath)) {
                $attachmentArray[] = [
                    'content' => base64_encode(file_get_contents($filePath)),
                    'name' => basename($filePath)
                ];
            }
        }
        $sendSmtpEmail['attachment'] = $attachmentArray;
    }
    
    // Send email
    try {
        $result = $apiInstance->sendTransacEmail($sendSmtpEmail);

        // TEMPORARY DIAGNOSTIC — remove with the vasl_trace calls.
        if (function_exists('vasl_trace')) {
            vasl_trace('      BREVO OK -> ' . $email_address . ' | subject: ' . $subject
                . ' | attachments: ' . count($attachments));
        }
        return true;
    } catch (Exception $e) {
        if (function_exists('vasl_trace')) {
            vasl_trace('      BREVO FAILED -> ' . $email_address . ' | subject: ' . $subject
                . ' | ' . $e->getMessage());
        }
        // Make Brevo failures visible instead of silently dropping the email
        // (e.g. daily-limit reached, invalid API key, unverified sender).
        error_log('[mail] Brevo send FAILED to ' . (isset($email_address) ? $email_address : '?')
            . ' | subject: ' . (isset($subject) ? $subject : '?')
            . ' | error: ' . $e->getMessage());
        return false;
    }
}
}
