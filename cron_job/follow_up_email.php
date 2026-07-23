<?php
require "conn.php";
require_once 'swiftmailer/vendor/autoload.php';
 // Get the incoming webhook data

function check_detail($conn,$code,$variable){
    $check = mysqli_query($conn,"SELECT * FROM `trainings_details` WHERE `code`='$code'");
    if(mysqli_num_rows($check) > 0){
        $row = mysqli_fetch_array($check);
        return $row[$variable];
    }else{
        return "";
    }
}




      $receiver = "bidoom1234@gmail.com";
       $f = ucfirst(strtolower("Bidan"));
       
      
    $subject = "Exciting Opportunity: Supervisory Skills Course Details Inside!";
   


// HTML message body
$body = '
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body style="background-color: #f6f6f6; font-family: sans-serif; font-size: 14px; line-height: 1.4; margin: 0; padding: 0;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="background-color: #f6f6f6; width: 100%;" width="100%" bgcolor="#f6f6f6">
      <tr>
        <td valign="top">&nbsp;</td>
        <td class="container" style="display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="max-width: 580px; padding: 10px;">

            <table role="presentation" class="main" style="background: #ffffff; border-radius: 3px; width: 100%;" width="100%">
              <tr>
                <td class="wrapper" style="padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                      <td valign="top">
                        <p style="font-size: 18px; font-weight: bold; text-align: center;margin-bottom: 15px;">Exciting Opportunity: Supervisory Skills Course Details Inside!</p>
                        <p><img src="https://vantageafricaleaders.com/admin/assets/img/logo/logo.png"><br>
                        Dear ' . $f . ',<br><br>
                        I hope this email finds you well. We are thrilled to follow up with you regarding your interest in our supervisory skills course. It is fantastic to see your enthusiasm, and we are excited to share more details about this valuable learning opportunity.<br><br>
                        Gaining supervisory skills is essential for individuals aspiring to leadership roles or looking to enhance their effectiveness in managing teams. By honing these skills, participants can take on greater responsibilities, lead teams more effectively, and drive organizational success.<br><br>
                        If you have any questions or need further information, please do not hesitate to reach out. We are here to support you every step of the way.<br><br>
                        Looking forward to welcoming you and your team to the supervisory skills course!<br><br>
                        Best regards,<br>
                        The Vantage Africa School Of Leadership<br><br>
                        </p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <div class="footer" style="clear: both; margin-top: 10px; text-align: center; width: 100%;">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="content-block" style="color: #999999; font-size: 12px; text-align: center;">
                    <span class="apple-link" style="color: #999999; font-size: 12px;">Astrol Business Center Thika Road Nairobi, 6th Floor, Room C603</span>
                    <br> 
                  </td>
                </tr>
              </table>
            </div>

          </div>
        </td>
        <td valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>
';


$subject=" Don't Miss Out: Unlock Your Potential with our Supervisory Skills Course!
";

// HTML message body
$body = '
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body style="background-color: #f6f6f6; font-family: sans-serif; font-size: 14px; line-height: 1.4; margin: 0; padding: 0;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="background-color: #f6f6f6; width: 100%;" width="100%" bgcolor="#f6f6f6">
      <tr>
        <td valign="top">&nbsp;</td>
        <td class="container" style="display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="max-width: 580px; padding: 10px;">

            <table role="presentation" class="main" style="background: #ffffff; border-radius: 3px; width: 100%;" width="100%">
              <tr>
                <td class="wrapper" style="padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                      <td valign="top">
                        <p style="font-size: 18px; font-weight: bold; text-align: center;margin-bottom: 15px;"> Do not Miss Out: Unlock Your Potential with our Supervisory Skills Course!
</p>
                        <p><img src="https://vantageafricaleaders.com/admin/assets/img/logo/logo.png"><br>
                        Dear ' . $f . ',<br><br>
                       I hope this email finds you well. I wanted to follow up on your interest in our upcoming supervisory skills course and share more about the exciting benefits awaiting you and your team.<br>
By enrolling in this course, you will gain insights into key areas essential for effective leadership and team management, acquire techniques in conflict resolution, performance management, and time management for improved team dynamics, continuous improvement, and increased productivity.<br>
Do not miss out on this opportunity to invest in your professional development and unlock your full potential as a supervisor. I urge you to register for the supervisory skills course today and take the first step towards advancing your career. Register now and embark on a journey of growth and success!

                       <br><br>
                        Best regards,<br>
                        The Vantage Africa School Of Leadership<br><br>
                        </p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <div class="footer" style="clear: both; margin-top: 10px; text-align: center; width: 100%;">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="content-block" style="color: #999999; font-size: 12px; text-align: center;">
                    <span class="apple-link" style="color: #999999; font-size: 12px;">Astrol Business Center Thika Road Nairobi, 6th Floor, Room C603</span>
                    <br> 
                  </td>
                </tr>
              </table>
            </div>

          </div>
        </td>
        <td valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>
';


$subject="Secure Your Spot in the Supervisory Skills Course! ";

// HTML message body
$body = '
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body style="background-color: #f6f6f6; font-family: sans-serif; font-size: 14px; line-height: 1.4; margin: 0; padding: 0;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="background-color: #f6f6f6; width: 100%;" width="100%" bgcolor="#f6f6f6">
      <tr>
        <td valign="top">&nbsp;</td>
        <td class="container" style="display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="max-width: 580px; padding: 10px;">

            <table role="presentation" class="main" style="background: #ffffff; border-radius: 3px; width: 100%;" width="100%">
              <tr>
                <td class="wrapper" style="padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                      <td valign="top">
                        <p style="font-size: 18px; font-weight: bold; text-align: center;margin-bottom: 15px;"> Secure Your Spot in the Supervisory Skills Course!
</p>
                        <p><img src="https://vantageafricaleaders.com/admin/assets/img/logo/logo.png"><br>
                        Dear ' . $f . ',<br><br>
                       I hope this email finds you well. I am reaching out to remind you that time is running out to secure your spot in our upcoming supervisory skills course. With limited availability, I urge you to take action as soon as possible to ensure your participation.<br>
This course offers a unique opportunity for you and your team to enhance your supervisory skills and drive success in your roles. Throughout the course, we will cover key topics including effective communication, team leadership, conflict resolution, performance management, and time management. These topics are essential for building strong teams, fostering positive work environments, and achieving organizational goals.<br>
Do not miss out on this opportunity to enhance your skills and advance your career. Do not delay – secure your spot today by registering and investing in your future success!

                       <br><br>
                        Best regards,<br>
                        The Vantage Africa School Of Leadership<br><br>
                        </p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <div class="footer" style="clear: both; margin-top: 10px; text-align: center; width: 100%;">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="content-block" style="color: #999999; font-size: 12px; text-align: center;">
                    <span class="apple-link" style="color: #999999; font-size: 12px;">Astrol Business Center Thika Road Nairobi, 6th Floor, Room C603</span>
                    <br> 
                  </td>
                </tr>
              </table>
            </div>

          </div>
        </td>
        <td valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>
';


$subject="Supervisory Skills Course Starts Soon!";

// HTML message body
$body = '
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body style="background-color: #f6f6f6; font-family: sans-serif; font-size: 14px; line-height: 1.4; margin: 0; padding: 0;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="background-color: #f6f6f6; width: 100%;" width="100%" bgcolor="#f6f6f6">
      <tr>
        <td valign="top">&nbsp;</td>
        <td class="container" style="display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="max-width: 580px; padding: 10px;">

            <table role="presentation" class="main" style="background: #ffffff; border-radius: 3px; width: 100%;" width="100%">
              <tr>
                <td class="wrapper" style="padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                      <td valign="top">
                        <p style="font-size: 18px; font-weight: bold; text-align: center;margin-bottom: 15px;"> Supervisory Skills Course Starts Soon!
</p>
                        <p><img src="https://vantageafricaleaders.com/admin/assets/img/logo/logo.png"><br>
                        Dear ' . $f . ',<br><br>
                      I hope this email finds you well. This is your final reminder that our supervisory skills course is just around the corner, and there are only a few days left to secure your spot!<br>
This comprehensive course is designed to equip you and your team with the essential skills needed to excel in supervisory roles. Through engaging sessions, we will cover topics such as effective communication, team leadership, conflict resolution, performance management, and time management. These skills are vital for fostering a positive work environment, enhancing team performance, and driving organizational success.
Do not delay – secure your spot today and seize the opportunity to enhance your skills and advance your career!


                       <br><br>
                        Best regards,<br>
                        The Vantage Africa School Of Leadership<br><br>
                        </p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <div class="footer" style="clear: both; margin-top: 10px; text-align: center; width: 100%;">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="content-block" style="color: #999999; font-size: 12px; text-align: center;">
                    <span class="apple-link" style="color: #999999; font-size: 12px;">Astrol Business Center Thika Road Nairobi, 6th Floor, Room C603</span>
                    <br> 
                  </td>
                </tr>
              </table>
            </div>

          </div>
        </td>
        <td valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>
';


    // Please <a href="https://system.vantageafricaleaders.com/pay/index.php?id='.$entry_id.'"> <button>click here</button> </a> 
echo $body;
//$location = "location: index.php";
// include 'mail.php'; 

mysqli_close($conn);

