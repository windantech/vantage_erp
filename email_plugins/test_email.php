<?php
/**
 * test_mail_brevo.php
 * Sends ONE test email via Brevo (transactional API).
 * Run:  php test_mail_brevo.php
 * Then DELETE this file (it contains an API key).
 *
 * NOTE: The sender domain (vantageafricaleaders.com) must be AUTHENTICATED
 * in Brevo, or the send will be rejected / land in spam.
 */

require_once(__DIR__ . '/vendor/autoload.php');

use SendinBlue\Client\Configuration;
use SendinBlue\Client\Api\TransactionalEmailsApi;
use SendinBlue\Client\Model\SendSmtpEmail;

// ====== PASTE YOUR NEWLY ROTATED BREVO API KEY HERE ======
$apiKey = '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';
// =========================================================

// ---- Recipient & sender ----
$toEmail    = 'windantechnologies@gmail.com';
$toName      = 'Windan Technologies';
$senderEmail = 'info@vantageafricaleaders.com';
$senderName  = 'Vantage Africa School Of Leadership';
$subject     = 'Test Email from Vantage Africa School Of Leadership';

$htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif; color:#222; padding:20px; background:#f4f6f8;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0"
         style="max-width:600px; margin:0 auto; background:#ffffff;">
    <tr>
      <td style="background:#7a1c2e; padding:24px; text-align:center; color:#fff; font-size:20px; font-weight:bold;">
        Vantage Africa School Of Leadership
      </td>
    </tr>
    <tr>
      <td style="padding:30px; font-size:16px; line-height:26px; color:#222;">
        Hello,<br><br>
        This is a <strong>test email</strong> sent via Brevo at '
        . date('Y-m-d H:i:s') . '.<br><br>
        If you received this in your inbox (not spam), Brevo sending from
        <strong>info@vantageafricaleaders.com</strong> is working correctly.<br><br>
        Kind regards,<br>
        Vantage Africa School Of Leadership
      </td>
    </tr>
    <tr>
      <td style="background:#f1f5f9; padding:16px; text-align:center; color:#475569; font-size:13px;">
        &copy; ' . date('Y') . ' Vantage Africa School Of Leadership
      </td>
    </tr>
  </table>
</body>
</html>';



$config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
$apiInstance = new TransactionalEmailsApi(new GuzzleHttp\Client(), $config);

$sendSmtpEmail = new SendSmtpEmail();
$sendSmtpEmail['subject']     = $subject;
$sendSmtpEmail['htmlContent'] = $htmlBody;
$sendSmtpEmail['sender']      = ['name' => $senderName, 'email' => $senderEmail];
$sendSmtpEmail['to']          = [['email' => $toEmail, 'name' => $toName]];

try {
    $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
    echo "✅ Sent to {$toEmail}\n";
    echo "Message ID: " . $result->getMessageId() . "\n";
    echo "Now check the Gmail INBOX and SPAM folder.\n";
} catch (Exception $e) {
    echo "❌ Failed sending to {$toEmail}\n";
    echo "Reason: " . $e->getMessage() . "\n";
    echo "\nCommon causes:\n";
    echo " - vantageafricaleaders.com not authenticated in Brevo (verify domain)\n";
    echo " - API key invalid or not rotated\n";
    echo " - sender email not allowed for your Brevo account\n";
}