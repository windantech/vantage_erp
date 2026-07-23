<?php
require "conn.php";
require_once 'swiftmailer/vendor/autoload.php';
 // Get the incoming webhook data
$webhookData = file_get_contents('php://input');
$data = json_decode($webhookData, true);

function check_detail($conn,$code,$variable){
    $check = mysqli_query($conn,"SELECT * FROM `trainings_details` WHERE `code`='$code'");
    if(mysqli_num_rows($check) > 0){
        $row = mysqli_fetch_array($check);
        return $row[$variable];
    }else{
        return "";
    }
}
$firstname = mysqli_real_escape_string($conn,$data['name_2']);
$lastname = mysqli_real_escape_string($conn,$data['name_1']);
$phone = mysqli_real_escape_string($conn,$data['phone_1']);
$email = mysqli_real_escape_string($conn,$data['email_1']);
$organization = mysqli_real_escape_string($conn,$data['text_2']);
$position = mysqli_real_escape_string($conn,$data['text_1']);
$training_loc = mysqli_real_escape_string($conn,$data['select_1']);
$form_title = mysqli_real_escape_string($conn,$data['form_title']);
$entry_time = mysqli_real_escape_string($conn,$data['entry_time']);
$entry_id=md5($entry_time);
$code=$training_loc;
$sql = "INSERT INTO application_data_training ( `firstname`, `lastname`, `phone`, `email`, `organization`, `position`, `training_loc`, `form_title`, `entry_time`) VALUES ('$firstname','$lastname','$phone','$email','$organization','$position','$training_loc','$form_title','$entry_time')";

// Execute the INSERT query
if (mysqli_query($conn, $sql)) {
    $subject = "Registration Successful: Welcome to Vantage Africa M&E Professionals Training.";
$receiver=$email;
$f = ucwords(strtolower($firstname));
    $body = '<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">


  </head>
  <body style="background-color: #f6f6f6; font-family: sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;">
   
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #f6f6f6; width: 100%;" width="100%" bgcolor="#f6f6f6">
      <tr>
        <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
        <td class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px;">

            <!-- START CENTERED WHITE CONTAINER -->
            <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 3px; width: 100%;" width="100%">

              <!-- START MAIN CONTENT AREA -->
              <tr>
                <td class="wrapper" style="font-family: sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                    <tr>
                      <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">
                  
      <center>
    <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" alt="Logo" width="600" style="display:block;">
    </center>

                        <p style="font-family: sans-serif; font-size: 18px; font-weight: bold; margin: 0; text-align: center;margin-bottom: 15px;">Application Approved</p>
                        
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Greetings '.$f.'! <br>Thank you for registering for Certified Monitoring and Evaluation Professionals CMEP Training by Vantage Africa School of Leadership. This highly practical training will provide you with all the skills you need to practice Monitoring and Evaluation in any sector, anywhere in the world. .<br><br>Your registration number is  <b>'.rand(111111,999999).'</b>.<br><br>As part of the onboarding process, you are required to watch this video which forms part of your training content. (https://www.youtube.com/watch?v=-_QL3DZEBcA) <br><br>  Please <a href="https://system.vantageafricaleaders.com/pay/pay_training.php?id='.$entry_id.'"> <button>click here</button> </a> 
                        to view payment details and more details about the training. 
                         <br> <br> Sincerely.<br>Dr. Benson Kiarie, PhD <br>CEO and Lead Trainer  <br>  Vantage Africa School Of Leadership  <br> Email: ceo@vantageafricaleaders.com   <br>Phone: +254796128454 </p>
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; box-sizing: border-box; width: 100%;" width="100%">
                          <tbody>
                            <tr>
                              <td align="left" style="font-family: sans-serif; font-size: 14px; vertical-align: top; padding-bottom: 15px;" valign="top">
                                
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

            <!-- START FOOTER -->
            <div class="footer" style="clear: both; margin-top: 10px; text-align: center; width: 100%;">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                <tr>
                  <td class="content-block" style="font-family: sans-serif; vertical-align: top; padding-bottom: 10px; padding-top: 10px; color: #999999; font-size: 12px; text-align: center;" valign="top" align="center">
                    <span class="apple-link" style="color: #999999; font-size: 12px; text-align: center;">
                    Astrol Business Center Thika Road Nairobi, 6th Floor, Room C603
                    
</span>
                    <br> 
                  </td>
                </tr>
                
              </table>
            </div>
            <!-- END FOOTER -->

          </div>
        </td>
        <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
      </tr>
    </table>
  </body>
</html>';
//$location = "location: index.php";
include 'mail.php'; 
} else {
    echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

// Close the database conn
mysqli_close($conn);



