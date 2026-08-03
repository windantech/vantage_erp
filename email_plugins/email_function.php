<?php
if (!function_exists('send_mail_function')) {
   function send_mail_function($email_address, $body, $subject, $attachments = [])
{
    $year = date("Y");
    $bodyWithYear = str_replace('{year}', $year, $body);
    
    // Replace with your actual Brevo API key
    $apiKey = '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';
	$apiKey = 'xsmtpsib-91b05ff9042c19e32074b94c01fde5e3ac3dbfbeb516edb14cec9451592ed785-iEtwHIVF08USsNZa';
	
$apiKey = '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';
    
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
        'email' => 'no-reply@vantageafricaleaders.com'
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

        return true;
    } catch (Exception $e) {
        // Make Brevo failures visible instead of silently dropping the email
        // (e.g. daily-limit reached, invalid API key, unverified sender).
        error_log('[mail] Brevo send FAILED to ' . (isset($email_address) ? $email_address : '?')
            . ' | subject: ' . (isset($subject) ? $subject : '?')
            . ' | error: ' . $e->getMessage());
        return false;
    }
}
}
