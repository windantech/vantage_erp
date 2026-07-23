<?php
// Email 4: Hear From Our Alumni - Testimonials
// Replace these variables with actual values before sending

$name = "John Doe";  // Recipient's name
$testimonial_video_link = "https://www.youtube.com/watch?v=xS-Y4Rh46UA";  // YouTube testimonial video
$training_registration_link = "https://system.vantageafricaleaders.com/vantage/ticketing.php";  // Registration URL

$body = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>What Others Are Saying - M&E Training Alumni</title>
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
            
            .mobile-quote {
                font-size: 16px !important;
            }
            
            .mobile-video-thumb {
                width: 100% !important;
                height: auto !important;
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
                            <h1 class="mobile-heading" style="color: #ffffff; font-size: 28px; margin: 0; font-weight: bold; line-height: 1.3;">What Others Are Saying</h1>
                            <p class="mobile-text" style="color: #fafafa; font-size: 16px; margin: 10px 0 0 0; line-height: 1.4;">About Their Training Experience</p>
                        </td>
                    </tr>
                    
                    <!-- Opening Question -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #DAA520; text-align: center;">
                            <h2 class="mobile-subheading" style="color: #2c1810; font-size: 24px; margin: 0; font-weight: bold; line-height: 1.3;">Still Wondering What to Expect From the M&E Training?</h2>
                        </td>
                    </tr>
                    
                    <!-- Introduction Text -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #ffffff;">
                            <p class="mobile-text" style="color: #2c1810; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">
                                🎥 Hear directly from our <strong>alumni</strong> and discover how this training has empowered professionals across sectors to <strong>elevate their careers</strong> and <strong>maximize their impact</strong>.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Video Testimonial Section -->
                    <tr>
                        <td style="padding: 0 20px 20px 20px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #2c1810; border-radius: 8px; width: 100%;">
                                <tr>
                                    <td class="mobile-padding" style="padding: 30px 20px; text-align: center;">
                                        <p style="font-size: 48px; margin: 0 0 15px 0;">🎬</p>
                                        <h3 class="mobile-subheading" style="color: #F4D03F; font-size: 20px; margin: 0 0 20px 0; font-weight: bold;">Watch Our Alumni Share Their Stories</h3>
                                        
                                        <!-- Video Thumbnail/Link -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 20px;">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <a href="' . $testimonial_video_link . '" target="_blank" style="display: block; text-decoration: none;">
                                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #654321; border-radius: 8px; position: relative;">
                                                            <tr>
                                                                <td style="padding: 60px 20px; text-align: center; position: relative;">
                                                                    <!-- Play Button -->
                                                                    <div style="display: inline-block; width: 80px; height: 80px; background-color: #F4D03F; border-radius: 50%; position: relative;">
                                                                        <p style="color: #2c1810; font-size: 36px; margin: 0; line-height: 80px; text-align: center;">▶</p>
                                                                    </div>
                                                                    <p style="color: #ffffff; font-size: 16px; margin: 15px 0 0 0; font-weight: bold;">Click to Watch Testimonials</p>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Watch Button -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td align="center" style="padding: 10px 0;">
                                                    <!--[if mso]>
                                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $testimonial_video_link . '" style="height:50px;v-text-anchor:middle;width:280px;" arcsize="50%" fillcolor="#DAA520">
                                                        <w:anchorlock/>
                                                        <center style="color:#2c1810;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">▶️ Watch the Testimonial Video</center>
                                                    </v:roundrect>
                                                    <![endif]-->
                                                    <!--[if !mso]><!-->
                                                    <a href="' . $testimonial_video_link . '" class="mobile-button" target="_blank" style="background-color: #DAA520; color: #2c1810; text-decoration: none; padding: 15px 35px; border-radius: 25px; display: inline-block; font-weight: bold; font-size: 16px; line-height: 1.2; mso-hide: all;">▶️ Watch the Testimonial Video</a>
                                                    <!--<![endif]-->
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- What Alumni Say -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #ffffff;">
                            <p class="mobile-text" style="color: #2c1810; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0; text-align: center;">
                                Our participants often describe the training as a <strong>transformative experience</strong> - equipping them with <strong>practical tools</strong>, <strong>global frameworks</strong>, and the <strong>confidence</strong> to lead and deliver results in the field.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Key Benefits Section -->
                    <tr>
                        <td style="padding: 0 20px 20px 20px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #fafafa; border-radius: 8px; width: 100%;">
                                <tr>
                                    <td class="mobile-padding" style="padding: 25px 20px;">
                                        <h3 class="mobile-subheading" style="color: #2c1810; font-size: 20px; margin: 0 0 20px 0; text-align: center; font-weight: bold;">What Our Alumni Gained</h3>
                                        
                                        <!-- Benefit 1 -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 15px;">
                                            <tr>
                                                <td style="background-color: #ffffff; padding: 15px; border-radius: 6px; border-left: 4px solid #8B4513;">
                                                    <p style="color: #2c1810; font-size: 18px; margin: 0 0 5px 0; font-weight: bold;">💼 Career Breakthroughs</p>
                                                    <p class="mobile-text" style="color: #6b5b47; font-size: 14px; margin: 0; line-height: 1.5;">Promotions, new opportunities, and enhanced professional recognition</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Benefit 2 -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 15px;">
                                            <tr>
                                                <td style="background-color: #ffffff; padding: 15px; border-radius: 6px; border-left: 4px solid #DAA520;">
                                                    <p style="color: #2c1810; font-size: 18px; margin: 0 0 5px 0; font-weight: bold;">🛠️ Practical Skillsets</p>
                                                    <p class="mobile-text" style="color: #6b5b47; font-size: 14px; margin: 0; line-height: 1.5;">Real-world tools and frameworks you can apply immediately</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Benefit 3 -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 15px;">
                                            <tr>
                                                <td style="background-color: #ffffff; padding: 15px; border-radius: 6px; border-left: 4px solid #CD853F;">
                                                    <p style="color: #2c1810; font-size: 18px; margin: 0 0 5px 0; font-weight: bold;">🌍 Global Frameworks</p>
                                                    <p class="mobile-text" style="color: #6b5b47; font-size: 14px; margin: 0; line-height: 1.5;">Industry-standard methods recognized worldwide</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Benefit 4 -->
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="background-color: #ffffff; padding: 15px; border-radius: 6px; border-left: 4px solid #8B4513;">
                                                    <p style="color: #2c1810; font-size: 18px; margin: 0 0 5px 0; font-weight: bold;">💪 Confidence to Lead</p>
                                                    <p class="mobile-text" style="color: #6b5b47; font-size: 14px; margin: 0; line-height: 1.5;">The assurance to deliver measurable results in your field</p>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Featured Quote -->
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #654321; border-radius: 8px; width: 100%;">
                                <tr>
                                    <td class="mobile-padding" style="padding: 30px 20px; text-align: center;">
                                        <p style="font-size: 48px; margin: 0 0 20px 0;">💬</p>
                                        <p class="mobile-quote" style="color: #F4D03F; font-size: 20px; line-height: 1.5; margin: 0 0 15px 0; font-weight: bold; font-style: italic;">
                                            "It was a turning point in my professional journey."
                                        </p>
                                        <p class="mobile-text" style="color: #fafafa; font-size: 16px; margin: 0; line-height: 1.5;">
                                            - M&E Training Alumni
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Call to Action -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #DAA520; text-align: center;">
                            <h3 class="mobile-subheading" style="color: #2c1810; font-size: 22px; margin: 0 0 15px 0; font-weight: bold; line-height: 1.3;">Yours Could Be Too</h3>
                            <p class="mobile-text" style="color: #2c1810; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                                Join hundreds of professionals who have transformed their careers through our M&E training. Your success story starts here.
                            </p>
                            
                            <!-- Register Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding: 10px 0;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $training_registration_link . '" style="height:50px;v-text-anchor:middle;width:280px;" arcsize="50%" fillcolor="#2c1810">
                                            <w:anchorlock/>
                                            <center style="color:#F4D03F;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">🎓 Start Your Journey Today</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="' . $training_registration_link . '" class="mobile-button" style="background-color: #2c1810; color: #F4D03F; text-decoration: none; padding: 15px 35px; border-radius: 25px; display: inline-block; font-weight: bold; font-size: 16px; line-height: 1.2; mso-hide: all;">🎓 Start Your Journey Today</a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Social Proof Stats -->
                    <tr>
                        <td class="mobile-padding" style="padding: 30px 20px; background-color: #ffffff; text-align: center;">
                            <h3 class="mobile-subheading" style="color: #2c1810; font-size: 20px; margin: 0 0 20px 0; font-weight: bold;">Join a Global Community</h3>
                            
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding: 0 10px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="15" border="0">
                                            <tr>
                                                <td style="width: 33.33%; text-align: center; vertical-align: top;">
                                                    <p style="color: #8B4513; font-size: 32px; font-weight: bold; margin: 0 0 5px 0;">1000+</p>
                                                    <p class="mobile-text" style="color: #6b5b47; font-size: 14px; margin: 0; line-height: 1.4;">Professionals Trained</p>
                                                </td>
                                                <td style="width: 33.33%; text-align: center; vertical-align: top;">
                                                    <p style="color: #DAA520; font-size: 32px; font-weight: bold; margin: 0 0 5px 0;">45+</p>
                                                    <p class="mobile-text" style="color: #6b5b47; font-size: 14px; margin: 0; line-height: 1.4;">Countries Represented</p>
                                                </td>
                                                <td style="width: 33.33%; text-align: center; vertical-align: top;">
                                                    <p style="color: #CD853F; font-size: 32px; font-weight: bold; margin: 0 0 5px 0;">98%</p>
                                                    <p class="mobile-text" style="color: #6b5b47; font-size: 14px; margin: 0; line-height: 1.4;">Satisfaction Rate</p>
                                                </td>
                                            </tr>
                                        </table>
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
                            <p class="mobile-text" style="color: #6b5b47; font-size: 15px; margin: 0; line-height: 1.4;">📧 Email: <a href="mailto:ceo@vantageafricaleaders.com" style="color: #8B4513; text-decoration: none; font-weight: bold;">ceo@vantageafricaleaders.com</a></p>
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
// $subject = "What Others Are Saying - M&E Training Testimonials";
// $headers = "MIME-Version: 1.0" . "\r\n";
// $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
// $headers .= "From: Vantage Africa <noreply@vantageafricaleaders.com>" . "\r\n";
// mail($to, $subject, $body, $headers);

// For testing, you can echo the body
echo $body;
?>