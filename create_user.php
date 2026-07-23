<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Create User</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <!--<button class="btn border-0 p-0">-->
                            <!--    <i class="bi bi-plus-lg"></i> Add-->
                            <!--</button>-->
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">


<div class="container ">
  <div class="card card-body col-md-8 ">
   
      <form action="#"  method="POST" class="form">

        <!--<h2>Create User</h2>-->
        
          <div class="mt-2">
            <div class="form-group">
              <label for="name">FullName</label>
              <input type="text" class="form-control" id="fullname" name="fullname" placeholder="Enter Name" required>
            </div>
          </div>
          <div class="mt-2">
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" class="form-control" id="email" name="email" placeholder="Enter Email" required>
            </div>
          </div>

          <div>
            <div class="form-group mt-2">
              <!-- btb -->
              <button type="submit" class="btn btn-success" id="create_user">Create User</button>
            </div>
          </div>
            
      </form>
</div>
</div>
  <?php 
  if(isset($_POST['email'])){
      // Email function
require_once  'email_plugins/vendor/autoload.php';
require_once  'email_plugins/email_function.php';
      function generatePassword($length = 8) {
    return substr(bin2hex(random_bytes($length)), 0, $length);
}


      $email = $_POST['email'];
      $fullname = $_POST['fullname'];
      $token = md5($email);
      $password = generatePassword();
      
      $md5Password = md5($password); // First, hash with MD5
$hashedPassword = password_hash($md5Password, PASSWORD_DEFAULT);
      
      $insert = mysqli_query($conn,"INSERT INTO `registered_users`(`email`, `fullname`, `token`,password,role) VALUES('$email','$fullname','$token','$hashedPassword','0') ")or die(mysqli_error($conn));
      
      $subject = "Vantage  Africa School of Leadership account creation notification";
$receiver = $email;

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
                        <p style="font-family: Century Gothic; font-size: 18px; font-weight: bold; margin: 0; text-align: center;margin-bottom: 15px;">Account creation notification!</p>
                        <p style="font-family: Century Gothic; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Hi, '.$fullname.', <br> This is to notify you that an account has been created  for you in our ERP.  
                        Please use the following link to access the portal  <a href="https://vantageafricaleaders.com/admin/"><button>Click here</button> </a> <br>
                        Username : '.$email.'<br>
                        Password : '.$password.'
                        
                        <br><br> 
                  Telephone: 254725303645<br>Astrol Business Center , 6th Floor, Room C603<br>
                        Thika Road Nairobi, Nairobi, Kenya<br>
                       
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
include 'email_plugins/email_function.php';
send_mail_function($email, $body, $subject, $attachments =null);
      ?>
      
  
       <script>
    function Success() {
            Swal.fire({
                position: 'top-end',
                title:'',
              
                margin: 'auto',
                html: '<i class="fas fa-2x fa-check-circle"></i><p> Success. An email has been sent to <?php $fullname; ?> to complete personal details. </p>',
                showConfirmButton: false,
                width: 500,
                padding: '1rem',
                color: '#fff',
                background: '#00b894',
                showClass: {
    popup: 'animate__animated animate__fadeInRight'
  },
                hideClass: {
                popup: 'animate__animated  animate__fadeOutRight'
                         },
                timer: 5000
            })
        }
        
        
    Success();
    
</script>
      <?php    
  }
  ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>