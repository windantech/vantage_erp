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
    <body style="font-family: Arial, sans-serif; background-color: #f3f3f3; margin: 0; padding: 20px;">
        <div style="width: 210mm; Height: 297mm; padding: 0; margin: 0 auto; background-color: #fff;">
            <table style="color: black; padding: 10px 40px 0 40px; margin-bottom: 5px; width: 210mm; height: 45mm;">
                <tbody>
                    <tr>
                        <td style="width: 30%;">
                            <table style="color: #2B5470;">
                                <tbody>
                                    <tr>
                                        <td style="display: block; padding: 0;">
                                            <img src="assets/img/logo.png" style="width: inherit; height: 80px; object-fit: contain;" alt="">
                                        </td>
                                        <td style=" width: 10%;">
                                            
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 30%; font-size: 16px; font-family: Times New Roman, Times, serif;">
                            Astrol Business Center
                            6<sup>th</sup> Floor, C603,
                            Thika Road Opposite Garden City,
                            Nairobi
                        </td>
                        <td style="font-size: 16px; border-left: solid 2px #A85431; padding-left: 10px; font-family: Times New Roman, Times, serif;">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                Leadership training
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                CV & Interview coaching
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                Strategic Consulting
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                HR Consulting
                            </span>
                            <br>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-triangle-fill" viewBox="0 0 16 16">
                                    <path fill="#A85431" fill-rule="evenodd" d="M7.022 1.566a1.13 1.13 0 0 1 1.96 0l6.857 11.667c.457.778-.092 1.767-.98 1.767H1.144c-.889 0-1.437-.99-.98-1.767z"/>
                                </svg>
                                Online Book club
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="padding: 0 40px; color: #2B5470; border-bottom: solid 5px #A85431;"></div>

            <table style="margin: 10px 0; padding: 0 40px; color: #2B5470; width: 800px; height: 8mm; text-align: center;">
                <tbody>
                    <tr>
                        <td style="width: 38%;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/>
                            </svg>

                            info@vantageafricaleaders.com 
                        </td>
                        <td style="width: 38%;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-globe2" viewBox="0 0 16 16">
                                <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855q-.215.403-.395.872c.705.157 1.472.257 2.282.287zM4.249 3.539q.214-.577.481-1.078a7 7 0 0 1 .597-.933A7 7 0 0 0 3.051 3.05q.544.277 1.198.49zM3.509 7.5c.036-1.07.188-2.087.436-3.008a9 9 0 0 1-1.565-.667A6.96 6.96 0 0 0 1.018 7.5zm1.4-2.741a12.3 12.3 0 0 0-.4 2.741H7.5V5.091c-.91-.03-1.783-.145-2.591-.332M8.5 5.09V7.5h2.99a12.3 12.3 0 0 0-.399-2.741c-.808.187-1.681.301-2.591.332zM4.51 8.5c.035.987.176 1.914.399 2.741A13.6 13.6 0 0 1 7.5 10.91V8.5zm3.99 0v2.409c.91.03 1.783.145 2.591.332.223-.827.364-1.754.4-2.741zm-3.282 3.696q.18.469.395.872c.552 1.035 1.218 1.65 1.887 1.855V11.91c-.81.03-1.577.13-2.282.287zm.11 2.276a7 7 0 0 1-.598-.933 9 9 0 0 1-.481-1.079 8.4 8.4 0 0 0-1.198.49 7 7 0 0 0 2.276 1.522zm-1.383-2.964A13.4 13.4 0 0 1 3.508 8.5h-2.49a6.96 6.96 0 0 0 1.362 3.675c.47-.258.995-.482 1.565-.667m6.728 2.964a7 7 0 0 0 2.275-1.521 8.4 8.4 0 0 0-1.197-.49 9 9 0 0 1-.481 1.078 7 7 0 0 1-.597.933M8.5 11.909v3.014c.67-.204 1.335-.82 1.887-1.855q.216-.403.395-.872A12.6 12.6 0 0 0 8.5 11.91zm3.555-.401c.57.185 1.095.409 1.565.667A6.96 6.96 0 0 0 14.982 8.5h-2.49a13.4 13.4 0 0 1-.437 3.008M14.982 7.5a6.96 6.96 0 0 0-1.362-3.675c-.47.258-.995.482-1.565.667.248.92.4 1.938.437 3.008zM11.27 2.461q.266.502.482 1.078a8.4 8.4 0 0 0 1.196-.49 7 7 0 0 0-2.275-1.52c.218.283.418.597.597.932m-.488 1.343a8 8 0 0 0-.395-.872C9.835 1.897 9.17 1.282 8.5 1.077V4.09c.81-.03 1.577-.13 2.282-.287z"/>
                            </svg>
                            www.vantageafricaleaders.com
                        </td>
                        <td>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                                <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
                            </svg>
                            254 725 303 645
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="border: solid 2px #FEB958; margin: 0 20px; padding: 10px; font-family: Times New Roman, Times, serif;">
                <h2 style="margin: 0; text-align: center;">
                    <span style="border-bottom: dashed 2px black">
                        PAYMENT INVOICE
                    </span>
                </h2>
                <p style="text-align: left; margin: 1px;"><b>Date:</b> <span style="border-bottom: dotted 1px #A85431;">' . $invoice_date . '</span></p>
                <p style="text-align: right; margin: 1px;"><b>Invoice No:</b> <span style="border-bottom: dotted 1px #A85431;">' . $invoice_no . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Presented to:</b> <span style="border-bottom: dotted 1px #A85431;">' . $recipient_name . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Amount Payable:</b> <span style="border-bottom: dotted 1px #A85431;">' . $amount . '</span></p>
                <p style="text-align: left; margin-bottom: 1px;"><b>Purpose of Payment:</b> <span style="border-bottom: dotted 1px #A85431;">' . $purpose . '</span></p>

                <table style="margin-top: 4px; width: 100%;" border="1" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                    <tr style="background: #D96800;">
                        <th colspan="1" style="padding: 5px; white-space: nowrap; color: white;">No</th>
                        <th colspan="1" style="padding: 5px; white-space: nowrap; color: white;">Item</th>
                        <th colspan="1" style="padding: 5px; white-space: nowrap; color: white;">Unit of Measure</th>
                        <th colspan="1" style="padding: 5px; white-space: nowrap; color: white;">No of Units</th>
                        <th colspan="1" style="padding: 5px; white-space: nowrap; color: white;">Cost per Unit (USD)</th>
                        <th colspan="1" style="padding: 5px; white-space: nowrap; color: white;">Total Cost(USD)</th>
                    </tr>
                    <tbody>
                        <tr>
                            <td style="padding: 5px;">1</td>
                            <td style="padding: 5px; width: 35%;">Training Materials</td>
                            <td style="padding: 5px; white-space: nowrap;">No. of Participants</td>
                            <td style="padding: 5px; text-align: center;">5</td>
                            <td style="padding: 5px; text-align: center;">80</td>
                            <td style="padding: 5px; text-align: center;">400</td>
                        </tr>

                         <tr>
                            <td style="padding: 5px;">2</td>
                            <td style="padding: 5px; width: 35%;">3 Day Training on M&E</td>
                            <td style="padding: 5px; white-space: nowrap;">No. of Participants</td>
                            <td style="padding: 5px; text-align: center;">5</td>
                            <td style="padding: 5px; text-align: center;">320</td>
                            <td style="padding: 5px; text-align: center;">1,600</td>
                        </tr>

                         <tr>
                            <td style="padding: 5px;">3</td>
                            <td style="padding: 5px; width: 35%;">3 Months Post- Training Support & Association membership.</td>
                            <td style="padding: 5px; white-space: nowrap;">No. of Participants</td>
                            <td style="padding: 5px; text-align: center;">5</td>
                            <td style="padding: 5px; text-align: center;">100</td>
                            <td style="padding: 5px; text-align: center;">500</td>
                        </tr>

                         <tr>
                            <td style="padding: 5px;">4</td>
                            <td style="padding: 5px; width: 35%;">Two Certificates</td>
                            <td style="padding: 5px; white-space: nowrap;">No. of Participants</td>
                            <td style="padding: 5px; text-align: center;">5</td>
                            <td style="padding: 5px; text-align: center;">50</td>
                            <td style="padding: 5px; text-align: center;">250</td>
                        </tr>

                         <tr>
                            <td style="padding: 5px;">5</td>
                            <td style="padding: 5px; width: 35%;">Meals & Conference</td>
                            <td style="padding: 5px;"></td>
                            <td style="padding: 5px; text-align: center;">5</td>
                            <td style="padding: 5px; text-align: center;">30</td>
                            <td style="padding: 5px; text-align: center;">150</td>
                        </tr>

                        <tr>
                            <td colspan="1" style="padding: 5px;"></td>
                            <td colspan="3" style="padding: 5px; text-align: right;"><b>Total</b></td>
                            <td colspan="1" style="padding: 5px; text-align: center;">580</td>
                            <td colspan="1" style="padding: 5px; text-align: center;">2,900</td>
                        </tr>

                        <tr>
                            <td colspan="1" style="padding: 5px;"></td>
                            <td colspan="3" style="padding: 5px; text-align: right;"><b>Less 5% Corporate discount</b></td>
                            <td colspan="1" style="padding: 5px;"></td>
                            <td colspan="1" style="padding: 5px; text-align: center;">145</td>
                        </tr>

                        <tr>
                            <td colspan="1" style="padding: 5px;"></td>
                            <td colspan="4" style="padding: 5px; text-align: right;"><b>Total Payable</b></td>
                            <td colspan="1" style="padding: 5px; text-align: center; background: #D96800; color: white;">2,775</td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="text-align: left; margin-bottom: 0;">HOW TO PAY:</h3>
                <p style="text-align: left; margin: 1px;"><b>Direct Bank Transfer</b></p>
                <ol style="text-align: left; margin: 1px; font-size: 14px;">
                    <li>Swift code; EQBLKENA</li>
                    <li>Branch Code; 68026</li>
                    <li>Bank Name; Equity Bank Kenya</li>
                    <li>Branch; Kimathi</li>
                    <li>Account Name; Vantage Africa School of Leadership</li>
                    <li>Account number: 0260280135396</li>
                </ol>
                
                <p style="text-align: left; margin-bottom: 1px;"><b>Online Payments</b></p>
                <p style="text-align: left; margin: 1px 25px; font-size: 14px;">To pay online, Click <a href="https://vantageafricaleaders.com/" target="_blank">here</a></p>

                <p style="text-align: left; margin-bottom: 1px;"><b>Authorized Signature: </b></p>
                <img src="qr_code/signature_qrcode.png" alt="">
            </div>
        </div>
    </body>
    </html>
    ';

    echo $html;

    $directory = 'invoices';
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
