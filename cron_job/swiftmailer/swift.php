<?php
require_once 'vendor/autoload.php';
$name="";
$subject="Dont Miss Out on Our Online Performance Management Course!
";

          

// Choose the transport method (SMTP, Sendmail, etc.)
$transport = new Swift_SmtpTransport('rbx107.truehost.cloud', 587);
$transport->setUsername('no-reply@vantageafricaleaders.com');
$transport->setPassword('Bebblessed2020');

// Create the SwiftMailer instance
$mailer = new Swift_Mailer($transport);

// Array of recipient emails and names
$recipients = [
    // 'bidan.murianki@theairliftsacco.co.ke' => 'Recipient 1',
    'bidoom1234@gmail.com' => 'Recipient 2',
    // 'windantechnologies@gmail.com' => 'Recipient 3',
    
    // 'benkiarie@gmail.com' => 'Benson Kiarie',
    
    // 'bensonkaranjakiarie@gmail.com' => 'Kiarie Benson',
    // Add more recipients as needed
];

foreach ($recipients as $email => $name) {
    // Create a new Swift_Message for each recipient
 
      $body = '<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">


  </head>
  <body style="background-color: #f6f6f6; font-family: Century Gothic; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;">
   
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #f6f6f6; width: 100%;" width="100%" bgcolor="#f6f6f6">
      <tr>
        <td style="font-family: Century Gothic; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
        <td class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px;">

            <!-- START CENTERED WHITE CONTAINER -->
            <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 3px; width: 100%;" width="100%">

              <!-- START MAIN CONTENT AREA -->
              <tr>
                <td class="wrapper" style="font-family: Century Gothic; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                    <tr>
                      <td style="font-family: Century Gothic; font-size: 14px; vertical-align: top;" valign="top">
                        <p style="font-family: Century Gothic; font-size: 18px; font-weight: bold; margin: 0; text-align: center;margin-bottom: 15px;">'.$subject.'</p>
                        <p style="font-family: Century Gothic; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Hi  '.$name.', <br><br>
                      I hope this email finds you well. I noticed that you registered for our online Performance Management Course, but have not yet completed your payment.
<br><br>
As a reminder, our course offers comprehensive training on effective performance management strategies, and can help you develop skills to manage and motivate your team to achieve better results. Our course also includes practical exercises and case studies that will enhance your learning experience.
<br><br>
We believe that this course will be beneficial to your career growth and professional development. Thats why we want to encourage you to complete your payment and start your journey with us. Once your payment is processed, you will receive full access to our course materials and resources.
<br><br>
If you have any questions or concerns about the payment process, please feel free to contact us. We would be happy to assist you.
<br><br>
Kindly click the link below view course outline   <a href="https://youtu.be/bBgm0OIKL9I">View course outline </a><br>
Click the link below to make  payment  <a href="https://system.vantageafricaleaders.com/pay/success.php?id=703203A8-68FF-42D5-A1B8-887DE1682170&check=2">Make payment</a><br><br>
Thank you for your interest in our course, and we look forward to welcoming you soon.

<br><br>
                      <br> 
                        Sincerely,<br>Vantage Africa School Of Leadership.<br> 
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; box-sizing: border-box; width: 100%;" width="100%">
                          <tbody>
                            <tr>
                              <td align="left" style="font-family: Century Gothic; font-size: 14px; vertical-align: top; padding-bottom: 15px;" valign="top">
                                
                              </td>
                            </tr>
                          </tbody>
                        </table>
                        
                    
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

            <!-- END MAIN CONTENT AREA -->
            </table>
            <!-- END CENTERED WHITE CONTAINER -->

            

          </div>
        </td>
        <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>';
     
    $message = new Swift_Message($subject);
    $message->setFrom(['no-reply@vantageafricaleaders.com' => 'Vantage Africa School Leadership']);
    $message->setTo([$email => $name]);
    $message->setBody($body, 'text/html');
    // $message->setBody($body);

    // Send the email
    $result = $mailer->send($message);
     sleep(1);

    if ($result) {
        echo "Email sent to $name ($email) successfully!<br>";
    } else {
        echo "Failed to send email to $name ($email).<br>";
    }
}
