<?php
// M&E Training Confirmation Email Template - MOBILE RESPONSIVE
// Replace these variables with actual values before sending

$name = "John Doe";  // Recipient's name
$location = "Dar es Salaam, Tanzania";  // Training location
$training_dates = "8-10 September 2025";  // Training dates
$ticket_link = "https://system.vantageafricaleaders.com/vantage/ticketing.php";  // Ticket confirmation URL
$training_info_link = "https://vantageafricaleaders.com/international-trainings/monitoring-and-evaluation-training-in-sierra-leone-2/";  // Training info URL

$body = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>M&E Training Confirmation</title>
    <style type="text/css">
        /* Client-specific Styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        table {
            border-collapse: collapse !important;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        
        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        
        /* Prevent blue links in iOS */
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }
        
        /* Mobile Styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .mobile-padding {
                padding: 20px 15px !important;
            }
            
            .mobile-text {
                font-size: 15px !important;
                line-height: 1.6 !important;
            }
            
            .mobile-heading {
                font-size: 24px !important;
            }
            
            .mobile-subheading {
                font-size: 18px !important;
            }
            
            .mobile-button {
                display: block !important;
                width: 85% !important;
                max-width: 300px !important;
                padding: 14px 20px !important;
                font-size: 15px !important;
                margin: 10px auto !important;
                box-sizing: border-box !important;
            }
            
            .mobile-logo {
                width: 140px !important;
                height: auto !important;
            }
            
            .mobile-detail-padding {
                padding: 20px 10px !important;
            }
            
            .mobile-detail-item {
                padding: 12px !important;
                margin-bottom: 10px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f5f5f5; width: 100%;">
    <!-- Wrapper table for background -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f5f5f5; margin: 0; padding: 0;">
        <tr>
            <td align="center" style="padding: 10px;">
                
                <!-- Main Container -->
                <table role="presentation" class="email-container" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; max-width: 600px; width: 100%;">
                    
                    <!-- Header with Logo -->
                    <tr>
                        <td class="mobile-padding" style="background-color: #8B4513; padding: 30px 20px; text-align: center;">
                            <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" alt="Vantage Africa Leaders" class="mobile-logo" width="180" style="display: block; margin: 0 auto 20px auto; max-width: 180px; height: auto;">
                            <h1 class="mobile-heading" style="color: #ffffff; font-size: 28px; margin: 0 0 10px 0; font-weight: bold; line-height: 1.2;">Registration Confirmed!</h1>
                            <p class="mobile-text" style="color: #fafafa; font-size: 16px; margin: 0; line-height: 1.4;">Welcome to our M&E Training</p>
                        </td>
                    </tr>
                    
                    <!-- Greeting Section -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #ffffff;">
                            <p class="mobile-text" style="color: #2c1810; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;"><strong>Greetings,</strong></p>
                            <p class="mobile-text" style="color: #2c1810; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">
                                We are pleased to confirm your registration for the <strong>Certified Monitoring & Evaluation training</strong> in <strong style="color: #8B4513;">' . $location . '</strong>, happening from <strong>' . $training_dates . '</strong>.
                            </p>
                            <p class="mobile-text" style="color: #2c1810; font-size: 16px; line-height: 1.6; margin: 0;">
                                This transformative training will equip you with essential M&E skills to drive real impact in your work.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Training Details Box -->
                    <tr>
                        <td style="padding: 0 20px 20px 20px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #DAA520; border-radius: 8px; width: 100%;">
                                <tr>
                                    <td class="mobile-detail-padding" style="padding: 25px 20px; text-align: center;">
                                        <h2 class="mobile-subheading" style="color: #2c1810; font-size: 22px; margin: 0 0 20px 0; font-weight: bold;">Training Details</h2>
                                        
                                        <!-- Location -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 10px;">
                                            <tr>
                                                <td class="mobile-detail-item" style="background-color: #ffffff; padding: 15px; border-radius: 6px; text-align: center;">
                                                    <p style="margin: 0; color: #6b5b47; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;"><strong>Location</strong></p>
                                                    <p class="mobile-text" style="margin: 8px 0 0 0; color: #2c1810; font-size: 16px; font-weight: bold; line-height: 1.4;">' . $location . '</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Dates -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 10px;">
                                            <tr>
                                                <td class="mobile-detail-item" style="background-color: #ffffff; padding: 15px; border-radius: 6px; text-align: center;">
                                                    <p style="margin: 0; color: #6b5b47; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;"><strong>Dates</strong></p>
                                                    <p class="mobile-text" style="margin: 8px 0 0 0; color: #2c1810; font-size: 16px; font-weight: bold; line-height: 1.4;">' . $training_dates . '</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Duration -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td class="mobile-detail-item" style="background-color: #ffffff; padding: 15px; border-radius: 6px; text-align: center;">
                                                    <p style="margin: 0; color: #6b5b47; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;"><strong>Duration</strong></p>
                                                    <p class="mobile-text" style="margin: 8px 0 0 0; color: #2c1810; font-size: 16px; font-weight: bold; line-height: 1.4;">3 Full Days</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- CTA Buttons Section -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #654321; text-align: center;">
                            <h3 class="mobile-subheading" style="color: #ffffff; font-size: 20px; margin: 0 0 10px 0; line-height: 1.2;">Next Steps</h3>
                            <p class="mobile-text" style="color: #fafafa; font-size: 15px; margin: 0 0 20px 0; line-height: 1.4;">Take action to secure your spot</p>
                            
                            <!-- Primary Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding: 5px 0;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $ticket_link . '" style="height:48px;v-text-anchor:middle;width:280px;" arcsize="50%" fillcolor="#F4D03F">
                                            <w:anchorlock/>
                                            <center style="color:#2c1810;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">🎟️ Confirm Your Ticket Now</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="' . $ticket_link . '" class="mobile-button" style="background-color: #F4D03F; color: #2c1810; text-decoration: none; padding: 14px 30px; border-radius: 25px; display: inline-block; font-weight: bold; font-size: 16px; line-height: 1.2; mso-hide: all;">🎟️ Confirm Your Ticket Now</a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Secondary Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding: 10px 0 5px 0;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $training_info_link . '" style="height:48px;v-text-anchor:middle;width:280px;" arcsize="50%" strokecolor="#ffffff" strokeweight="2px" fillcolor="transparent">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">📚 Learn More About Training</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="' . $training_info_link . '" class="mobile-button" style="background-color: transparent; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 2px solid #ffffff; border-radius: 25px; display: inline-block; font-weight: bold; font-size: 16px; line-height: 1.2; mso-hide: all;">📚 Learn More About Training</a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Questions Section -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #fafafa; text-align: center;">
                            <h3 class="mobile-subheading" style="color: #2c1810; font-size: 18px; margin: 0 0 15px 0; line-height: 1.2;">Questions? We are Here to Help!</h3>
                            <p class="mobile-text" style="color: #6b5b47; font-size: 15px; margin: 0 0 5px 0; line-height: 1.4;">📱 Phone: <a href="tel:+254796393864" style="color: #8B4513; text-decoration: none; font-weight: bold;">+254 796 393864</a></p>
                            <p class="mobile-text" style="color: #6b5b47; font-size: 15px; margin: 0; line-height: 1.4;">We are excited to have you with us!</p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td class="mobile-padding" style="background-color: #2c1810; padding: 30px 20px; text-align: center;">
                            <p style="color: #e8e1d9; font-size: 14px; margin: 0 0 10px 0; line-height: 1.4;"><strong style="color: #DAA520;">Vantage Africa School of Leadership</strong></p>
                            <p style="color: #8a7966; font-size: 13px; font-style: italic; margin: 0 0 15px 0; line-height: 1.4;">Transforming Leaders, Empowering Communities</p>
                            <p style="color: #e8e1d9; font-size: 12px; margin: 0 0 5px 0; line-height: 1.4;">Email: ceo@vantageafricaleaders.com</p>
                            <p style="color: #6b5b47; font-size: 11px; margin: 15px 0 0 0; line-height: 1.4;">© 2025 Vantage Africa School of Leadership. All rights reserved.</p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
</body>
</html>';

// Send email (example using mail function)
// $to = "recipient@example.com";
// $subject = "M&E Training Confirmation - " . $location;
// $headers = "MIME-Version: 1.0" . "\r\n";
// $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
// $headers .= "From: Vantage Africa <noreply@vantageafricaleaders.com>" . "\r\n";
// mail($to, $subject, $body, $headers);

// For testing, you can echo the body
echo $body;
?>