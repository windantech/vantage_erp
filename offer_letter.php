<?php
require_once 'email_plugins/vendor/autoload.php';
require_once 'email_plugins/email_function.php';
require_once 'pdf_plugins/generatePdf.php';

$recipient_name = "Assumed Name";
$email_address = "bidoom1234@gmail.com";
$subject = "Invoice Template";
$invoice_no = "RC884056";
$currency = "USD";
$invoice_date = date("jS F Y");

$amt_to_pay = 2775;
$amt_paid = 2775;
$amount = number_format($amt_to_pay, 2) . " " . $currency . ' only.';
$purpose = "SOUTH SUDAN M&E TRAINING.";

generatePdf($email_address, $recipient_name, $subject, $invoice_no, $invoice_date, $amount, $purpose);

function generatePdf($email_address, $recipient_name, $subject, $invoice_no, $invoice_date, $amount, $purpose)
{
    $html = '
    <html>
    <head></head>
    <body style="font-family: Century Gothic, sans-serif; background-color: #f3f3f3; margin: 0; padding: 20px;">
        <div style="width: 210mm; padding: 0; margin: 0 auto; background-color: #fff;">
            <div style="margin: 0 20px; padding: 50px 10px; font-family: Century Gothic, sans-serif;">
                <p style="text-align: left; margin-left: 30px;">
                    Sub: Welcome to Monitoring and Evaluation (M&E) Training! Exclusive Invitation
                </p>

                <p style="text-align: left; margin-left: 30px;">
                    Hi [Client Name]
                </p>

                <h3 style="text-align: center; padding: 20px; background-color: #FFC000; border-radius: 10px">
                    Your journey to becoming a Certified M&E Professional (CMEP) has just started! And we are all excited to welcome you on board
                </h3>

                <div style="width: 100%; overflow: hidden;">
                    <!-- First Column: Image with Positioned Text -->
                   <div style="float: left; width: 30%; padding: 5px; position: relative;">
                        <img src="email_images/dr_benson.jpg" style="width: 100%;" alt="">
                        <h4 style="text-align: center; padding: 10px 15px; position: relative; background-color: #FFD966; border: solid 2px #41719C; border-radius: 10px; margin-top: -10px;">
                            Dr. Benson Kiarie, PhD (Lead Trainer)
                        </h4>
                    </div>
                    
                    <!-- Second Column: Text Content -->
                    <div style="float: left; width: 45%; padding: 5px; text-align: justify;">
                        Research shows that M&E is the MOST demanded skill among international NGOs today. Every sector is scrambling to get qualified M&E professionals.
                        <br>
                        <br>
                        M&E will see you move up the career ladder because of the additional value you are bringing in the organization. Everywhere you look today, there is an organization hiring an M&E officer, or looking for an M&E consultant.
                        <br>
                        <br>
                        With an M&E certification, you have a huge competitive advantage over your peers in whichever field you are in.
                    </div>
                    
                    <!-- Third Column: Heading Text -->
                    <div style="float: left; width: 19%; padding: 5px; text-align: center; background-color: #5B3E00; color: #FF8686;">
                        <h2>
                            Transform your life and career through superior M&E Skills Today
                        </h2>
                    </div>

                    <!-- Clear float after the divs -->
                    <div style="clear: both;"></div>
                </div>


                <p style="text-align: left; margin-left: 0;">
                    I am thrilled to extend an exclusive invitation to you to participate in our upcoming Monitoring and Evaluation Training.
                </p>
                
                <div style="width: 100%; overflow: hidden;">
                    <div style="float: left; width: 28%; padding: 1px; text-align: center;">
                        <h3 style="padding: 10px 15px; background-color: #ED7D31; border: solid 2px #41719C; border-radius: 10px; margin: 0;">
                            See Training Schedule
                        </h3>
                    </div>
                    <div style="float: left; width: 28%; padding: 1px; text-align: center;">
                        <h3 style="padding: 10px 15px; background-color: #ED7D31; border: solid 2px #41719C; border-radius: 10px; margin: 0;">
                            Meet your trainer (Dr. Benson)
                        </h3>
                    </div>
                    <div style="float: left; width: 35%; padding: 1px;">
                        <div style="padding: 10px 15px; background-color: #ED7D31; border: solid 2px #41719C; border-radius: 10px; margin: 0;">
                            <h4 style="margin: 0">Training Details</h4>
                            <b>Duration:</b> 5 Weeks, 3 days each week, 1.5 hours daily in the evening
                            <br><b>Time:</b> 8pm EAT, 7pm CAT
                            <br><b>Mode:</b> Zoom
                        </div>
                    </div>

                    <!-- Clear float after the divs -->
                    <div style="clear: both;"></div>
                </div>


                 <h2 style="padding: 20px; background-color: #FFC000; border: solid 2px #41719C; border-radius: 10px">
                    Register now in our vibrant E-Learning Platform
                </h2>

                <h2 style="text-align: center; color: #FF8686;">
                    Here`s why this training is crucial for you:
                </h2>

                <ul>
                    <li>
                        <b>Career in the M&E Sector: </b> If you have ever wanted to start a career in the lucrative Monitoring and Evaluation field, this training will prepare you for that.
                    </li>
                    <li>
                        <b>Career Growth: </b> Use your M&E skills to climb up the ladder in your current career. People grow the fastest if they can measure and demonstrate impact.
                    </li>
                    <li>
                        <b>M&E Consultant: </b> Become an M&E consultant and see your life transform
                    </li>
                    <li>
                        <b>Develop an M&E System: </b> Your organization needs a vibrant M&E system. We will walk the journey with you in the process of preparing one.
                    </li>
                    <li>
                        <b>Start an NGO: </b> M&E training will give you the skills you need to start and successfully run an NGO, and therefore multiply your impact while doing something you love.
                    </li>
                    <li>
                        <b>Networking: </b> Our training brings together professionals from around the world to network and share opportunities. You will never miss another relevant opportunity
                    </li>
                    <li>
                        <b>VAMEPA Membership: </b> After the training, you will be eligible to join Vantage Africa M&E Professionals Association. We have members from the entire continent committed to growth in the M&E space.
                    </li>
                    <li>
                        <b>Post Training Support: </b> We do not just leave you after the training. We will walk the journey with you for 3 months at no extra cost.
                    </li>
                </ul>

                <div style="width: 100%; overflow: hidden;">
                    <div style="float: left; width: 35%; padding: 10px; text-align: center;">
                        <h3 style="padding: 10px; background-color: #ED7D31; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                            Watch Free Introductory M&E Training Here
                        </h3>
                    </div>
                    <div style="float: left; width: 20%; padding: 10px; text-align: center;">
                        <!-- Empty div for spacing, like the empty middle <td> -->
                    </div>
                    <div style="float: left; width: 35%; padding: 10px; text-align: center;">
                        <div style="padding: 10px; background-color: #FFC000; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                            <h3>I need training for my team</h3>
                            <small>(You will receive a customized proposal)</small>
                        </div>
                    </div>

                    <!-- Clear float after the divs -->
                    <div style="clear: both;"></div>
                </div>


                 <div style="width: 100%; overflow: hidden;">
                    <div style="float: left; width: 20%; padding: 2px; text-align: center;">
                        <h4 style="padding: 10px 15px; text-align: center; background-color: #FFC000; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                            Join Class WhatsApp Group for More info
                        </h4>
                    </div>
                    <div style="float: left; width: 30%; padding: 2px;">
                        <div style="padding: 10px 15px; background-color: #ED7D31; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                            <h5 style="margin: 0; text-align: center;">Register and Pay Investment: $195 Only</h5>
                            <small>- Incl of 5 weeks training</small>
                            <br> <small>- Training materials</small>
                            <br> <small>- Certificates</small>
                            <br> <small>- VAMEPA Membership Fee</small>
                        </div>
                    </div>
                    <div style="float: left; width: 20%; padding: 2px; text-align: center;">
                        <h4 style="padding: 10px 15px; background-color: #FFC000; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                            Contact Class Coordinator (WhatsApp)
                        </h4>
                    </div>
                    <div style="float: left; width: 20%; padding: 2px; text-align: center;">
                        <h4 style="padding: 10px 15px; background-color: #ED7D31; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                            Refer 5 Clients and Do the Course for FREE
                        </h4>
                    </div>

                    <!-- Clear float after the divs -->
                    <div style="clear: both;"></div>
                </div>


                 <p style="text-align: left; margin-left: 0;">
                    I believe this training will enhance your capabilities to drive positive change and set you on a path to exponential growth. 
                </p>

                 <p style="text-align: left; margin-left: 0;">
                    I look forward to an exceptional time with you during the training. See you soon.
                </p>

                <p style="text-align: left; margin-left: 0;">
                    Warm regards,
                    <br>
                    <br>
                    Dr. Benson Kiarie, PhD
                    <br>
                    CEO, Vantage Africa School of Leadership
                    <br>
                    Email: ceo@vantageafricaleaders.com
                </p>
            </div>
        </div>
    </body>
    </html>
    ';

    echo $html;

    $directory = 'letter';
    $file = $invoice_no . "_" . $invoice_date;
    $generatedFilePath = convertHtmlToPdf($html, $directory, $file);

    if ($generatedFilePath) {
        sendEmail($email_address, $recipient_name, $subject, $generatedFilePath);
    } else {
        echo "Failed to generate pdf";
    }
}

function sendEmail($email_address, $recipient_name, $subject, $generatedFilePath)
{
    $year = date("Y");
    $attachments = [$generatedFilePath];

    $body = '<html>' .
        '<head></head>' .
        '<body style="background-color: #e0e0e0; border-bottom: solid 1.5px #056839;">' .
        '<div style="border: solid 1px #d1d3e2;">
                <img src="https://vantageafricaleaders.com/wp-content/uploads/2023/06/cropped-Vantage_africa_logo-PNG-1.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
                <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
                <div style="padding: 0 .5rem">
                    <h5 id="subject_email">
                        <b></b>
                    </h5>
                    <p>
                        Dear, ' . $recipient_name . '
                    </p>
                    <div id="body_email">
                    Kindly find the attached file.
                    </div>
                </div>
                <div style="padding: .5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                    <div style="margin: 10px 0;">
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16">
                                <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951" />
                            </svg>
                        </a>
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-instagram" viewBox="0 0 16 16">
                                <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334" />
                            </svg>
                        </a>
                                                            
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-twitter-x" viewBox="0 0 16 16">
                                <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865z" />
                            </svg>
                        </a>
                        <a href="" style="margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-youtube" viewBox="0 0 16 16">
                                <path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.01 2.01 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.01 2.01 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31 31 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.01 2.01 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A100 100 0 0 1 7.858 2zM6.4 5.209v4.818l4.157-2.408z" />
                            </svg>
                        </a>
                    </div>
                    <a href="" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Help</a>
                    <a href="" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Privacy Policy</a>
                    <a href="" style="border-right: solid 2px #9ba4b3; margin-right: 10px; padding-right: 10px; text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Terms of Service</a>
                    <a href="" style="text-decoration: none; color: #9ba4b3; font-weight: 800; font-size: .8rem;">Website</a>
                    <div style="color: #9ba4b3; font-size: .8rem; margin: 10px 0;">
                        We sent this email to
                        <span>' . $email_address . '</span>
                        <a href="" style="text-decoration: underline; color: #9ba4b3; font-weight: 700;">Unsubscribe</a>
                    </div>
                    <div style="color: #9ba4b3; font-size: .8rem;">
                        &copy; ' . $year . '
                        Vantage Africa School of Leadership. All Rights Reserved
                    </div>
                </div>
            </div>' .
        '</body></html>';

    send_mail_function($email_address, $body, $subject, $attachments);
}
