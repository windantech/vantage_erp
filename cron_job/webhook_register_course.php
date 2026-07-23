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
if($data){
$form_title = mysqli_real_escape_string($conn,$data['form_title']);
$email = mysqli_real_escape_string($conn,$data['email_1']);
$phone_number = mysqli_real_escape_string($conn,$data['phone_1']);
$country = mysqli_real_escape_string($conn,$data['select_1']);
$entry_time = mysqli_real_escape_string($conn,$data['entry_time']);
$entry_id=md5($entry_time);
$source=0;

if($form_title == "Contact form"){
$firstname = mysqli_real_escape_string($conn,$data['name_1_first_name']);
$lastname = mysqli_real_escape_string($conn,$data['name_1_last_name']);
$program = mysqli_real_escape_string($conn,$data['text_1']);
$comment = mysqli_real_escape_string($conn,$data['text_1'])."-".mysqli_real_escape_string($conn,$data['textarea_1']);
$source=2;

}else if($form_title=="Call back Form"){
    $firstname = mysqli_real_escape_string($conn,$data['name_2']);
$lastname = mysqli_real_escape_string($conn,$data['name_3']);
  $program = mysqli_real_escape_string($conn,$data['select_2']);  
  $comment = mysqli_real_escape_string($conn,$data['select_2'])."-".mysqli_real_escape_string($conn,$data['textarea_1']);
  $source=3;
  

}

    $insert = mysqli_query($conn,"INSERT INTO `register`(entry_id,`email`, `firstname`, `lastname`, `phone_number`, `program`,country,source) 
    VALUES ('$entry_id','$email','$firstname','$lastname','$phone_number','$program','$country',$source)") or die(mysqli_error($conn));
      $receiver = "bidoom1234@gmail.com";
    $subject = "Vantage Africa School Of Leadership Approval";
    $subject= $form_title;
    $f = ucfirst(strtolower($firstname));
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
                        <p style="font-family: sans-serif; font-size: 18px; font-weight: bold; margin: 0; text-align: center;margin-bottom: 15px;">Application Approved</p>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Greetings! '.$f.' <br>Thank you for choosing the Vantage Africa School Of Leadership . 
                        <br/>  Your request to join The Vantage Africa School Of Leadership  has been approved.<br>Your application reference  number is <b>'.$entry_id.'</b>.<br> Please <a href="https://system.vantageafricaleaders.com/pay/index.php?id='.$entry_id.'"> <button>click here</button> </a> 
                        to make your first installment to get onboarded to our system and start learning with us. 
                         <br> <br> Thank you. <br> The Vantage Africa School Of Leadership  <br> Onboarding Team. </p>
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
// include 'mail.php'; 

mysqli_close($conn);
}
