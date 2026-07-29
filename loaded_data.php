<?php
require_once 'header.php';
require "function.php";
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Loaded Data</h6>
                        </div>
                        <button onclick="exportTableToExcel()" class="btn btn-primary mb-3"><i class="bi bi-file-spreadsheet"></i>Export to Excel</button>
                        <div class="w-50 d-flex justify-content-end">
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addLoadedData">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

       <!-- Add Loaded Data Modal -->
<div class="modal fade" id="addLoadedData" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addLoadedDataLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="addLoadedDataLabel">
                    Loaded Data
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <form action="#" method="POST">
                    <!-- Program Type Selection -->
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Program Type</span>
                        <select id="programType" class="form-control rounded-0" name="program_type" required onchange="toggleProgramFields()">
                            <option value="">Select Type</option>
                            <option value="virtual">Virtual Course</option>
                            <option value="international">International Event</option>
                        </select>
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">First Name</span>
                        <input type="text" name="firstname" required class="form-control rounded-0" placeholder="First Name">
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Last Name</span>
                        <input type="text" name="lastname" required class="form-control rounded-0" placeholder="Last Name">
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Email Address</span>
                        <input name="email" type="email" required class="form-control rounded-0" placeholder="Email Address">
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Phone Number</span>
                        <input type="tel" name="phone_number" required class="form-control rounded-0" placeholder="Phone Number">
                    </div>

                    <!-- Virtual Course Program Selection (Initially Hidden) -->
                    <div class="input-group mb-3" id="virtualProgramField" style="display: none;">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Program</span>
                        <select class="form-control rounded-0" name="program" id="virtualProgram">   
                            <option value="">Select</option>
                            <option value="25">Senior Management Course</option>
                            <option value="15">Supervisory Skills Development Program</option>
                            <option value="9">Effective Customer Service Skills Training</option>
                            <option value="18">Strategic Leadership Development Program (SLDP)</option>
                            <option value="4">Transformational Leadership Skills Training</option>
                            <option value="79">Performance Management Training</option>
                            <option value="80">Certified Project Management Course</option>
                            <option value="41">Practical Accounting Training</option>
                            <option value="82">Advanced Digital Marketing Training</option>
                            <option value="83">Advanced MS Excel Training</option>
                            <option value="84">Microsoft Project Training</option>
                            <option value="23">Resource Mobilization and Proposal Writing Course</option>
                            <option value="5">Certified Monitoring and Evaluation Professional Course</option>
                            <option value="87">Public Speaking Training</option>
                            <option value="64">Data Analysis Using SPSS</option>
                            <option value="89">Knowledge Management Training</option>
                            <option value="90">Trainer of Trainers(TOT) Course</option>
                            <option value="115">Premium CV Writing Services</option>
                            <option value="1">Test course</option>
                        </select>
                    </div>

                    <!-- International Event Selection (Initially Hidden) -->
                    <div class="input-group mb-3" id="internationalEventField" style="display: none;">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Event</span>
                        <select id="event_id" name="event_id" class="form-control rounded-0">
                            <option value="">Choose an event</option>
                            <?php
                            // Fetch events from database
                            $query = "SELECT location,event_title, event_id, early_amount, start_on FROM Event ORDER BY start_on DESC";
                            $result = mysqli_query($conn, $query);
                            
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($event = mysqli_fetch_assoc($result)) {
                                    echo '<option value="' . htmlspecialchars($event['event_id']) . '" data-amount="' . htmlspecialchars($event['early_amount']) . '">';
                                    echo htmlspecialchars($event['event_title']) . ' (' . $event['start_on'] . '-'.$event['location'].')';
                                    echo '</option>';
                                }
                            } else {
                                echo '<option value="">No upcoming events</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Additional Fields for International Events (Initially Hidden) -->
                    <div id="internationalFieldss" style="display: block;">
                        <div class="input-group mb-3">
                            <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Organization</span>
                            <input type="text" name="organization" class="form-control rounded-0" placeholder="Organization">
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Position</span>
                            <input type="text" name="position" class="form-control rounded-0" placeholder="Position">
                        </div>
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Country</span>
                        <select required id="countrySelect" class="form-control rounded-0" name="country">
                            <option value="">-- Please choose an option --</option>
                            <option value="Afghanistan">Afghanistan</option>
                            <option value="Albania">Albania</option>
                            <option value="Algeria">Algeria</option>
                            <option value="American Samoa">American Samoa</option>
                            <option value="Andorra">Andorra</option>
                            <option value="Angola">Angola</option>
                            <option value="Anguilla">Anguilla</option>
                            <option value="Antarctica">Antarctica</option>
                            <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                            <option value="Argentina">Argentina</option>
                            <option value="Armenia">Armenia</option>
                            <option value="Aruba">Aruba</option>
                            <option value="Australia">Australia</option>
                            <option value="Austria">Austria</option>
                            <option value="Azerbaijan">Azerbaijan</option>
                            <option value="Bahamas">Bahamas</option>
                            <option value="Bahrain">Bahrain</option>
                            <option value="Bangladesh">Bangladesh</option>
                            <option value="Barbados">Barbados</option>
                            <option value="Belarus">Belarus</option>
                            <option value="Belgium">Belgium</option>
                            <option value="Belize">Belize</option>
                            <option value="Benin">Benin</option>
                            <option value="Bermuda">Bermuda</option>
                            <option value="Bhutan">Bhutan</option>
                            <option value="Bolivia">Bolivia</option>
                            <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                            <option value="Botswana">Botswana</option>
                            <option value="Brazil">Brazil</option>
                            <option value="British Indian Ocean Territory">British Indian Ocean Territory</option>
                            <option value="British Virgin Islands">British Virgin Islands</option>
                            <option value="Brunei">Brunei</option>
                            <option value="Bulgaria">Bulgaria</option>
                            <option value="Burkina Faso">Burkina Faso</option>
                            <option value="Burundi">Burundi</option>
                            <option value="Cambodia">Cambodia</option>
                            <option value="Cameroon">Cameroon</option>
                            <option value="Canada">Canada</option>
                            <option value="Cape Verde">Cape Verde</option>
                            <option value="Cayman Islands">Cayman Islands</option>
                            <option value="Central African Republic">Central African Republic</option>
                            <option value="Chad">Chad</option>
                            <option value="Chile">Chile</option>
                            <option value="China">China</option>
                            <option value="Christmas Island">Christmas Island</option>
                            <option value="Cocos Islands">Cocos Islands</option>
                            <option value="Colombia">Colombia</option>
                            <option value="Comoros">Comoros</option>
                            <option value="Cook Islands">Cook Islands</option>
                            <option value="Costa Rica">Costa Rica</option>
                            <option value="Croatia">Croatia</option>
                            <option value="Cuba">Cuba</option>
                            <option value="Curaçao">Curaçao</option>
                            <option value="Cyprus">Cyprus</option>
                            <option value="Czech Republic">Czech Republic</option>
                            <option value="Democratic Republic of the Congo">Democratic Republic of the Congo</option>
                            <option value="Denmark">Denmark</option>
                            <option value="Djibouti">Djibouti</option>
                            <option value="Dominica">Dominica</option>
                            <option value="Dominican Republic">Dominican Republic</option>
                            <option value="East Timor">East Timor</option>
                            <option value="Ecuador">Ecuador</option>
                            <option value="Egypt">Egypt</option>
                            <option value="El Salvador">El Salvador</option>
                            <option value="Equatorial Guinea">Equatorial Guinea</option>
                            <option value="Eritrea">Eritrea</option>
                            <option value="Estonia">Estonia</option>
                            <option value="Ethiopia">Ethiopia</option>
                            <option value="Falkland Islands">Falkland Islands</option>
                            <option value="Faroe Islands">Faroe Islands</option>
                            <option value="Fiji">Fiji</option>
                            <option value="Finland">Finland</option>
                            <option value="France">France</option>
                            <option value="French Guiana">French Guiana</option>
                            <option value="French Polynesia">French Polynesia</option>
                            <option value="Gabon">Gabon</option>
                            <option value="Gambia">Gambia</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Germany">Germany</option>
                            <option value="Ghana">Ghana</option>
                            <option value="Gibraltar">Gibraltar</option>
                            <option value="Greece">Greece</option>
                            <option value="Greenland">Greenland</option>
                            <option value="Grenada">Grenada</option>
                            <option value="Guadeloupe">Guadeloupe</option>
                            <option value="Guam">Guam</option>
                            <option value="Guatemala">Guatemala</option>
                            <option value="Guernsey">Guernsey</option>
                            <option value="Guinea">Guinea</option>
                            <option value="Guinea-Bissau">Guinea-Bissau</option>
                            <option value="Guyana">Guyana</option>
                            <option value="Haiti">Haiti</option>
                            <option value="Honduras">Honduras</option>
                            <option value="Hong Kong SAR China">Hong Kong SAR China</option>
                            <option value="Hungary">Hungary</option>
                            <option value="Iceland">Iceland</option>
                            <option value="India">India</option>
                            <option value="Indonesia">Indonesia</option>
                            <option value="Iran">Iran</option>
                            <option value="Iraq">Iraq</option>
                            <option value="Ireland">Ireland</option>
                            <option value="Isle of Man">Isle of Man</option>
                            <option value="Israel">Israel</option>
                            <option value="Italy">Italy</option>
                            <option value="Jamaica">Jamaica</option>
                            <option value="Japan">Japan</option>
                            <option value="Jersey">Jersey</option>
                            <option value="Jordan">Jordan</option>
                            <option value="Kazakhstan">Kazakhstan</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Kiribati">Kiribati</option>
                            <option value="Kuwait">Kuwait</option>
                            <option value="Kyrgyzstan">Kyrgyzstan</option>
                            <option value="Laos">Laos</option>
                            <option value="Latvia">Latvia</option>
                            <option value="Lebanon">Lebanon</option>
                            <option value="Lesotho">Lesotho</option>
                            <option value="Liberia">Liberia</option>
                            <option value="Libya">Libya</option>
                            <option value="Liechtenstein">Liechtenstein</option>
                            <option value="Lithuania">Lithuania</option>
                            <option value="Luxembourg">Luxembourg</option>
                            <option value="Macau SAR China">Macau SAR China</option>
                            <option value="Madagascar">Madagascar</option>
                            <option value="Malawi">Malawi</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="Maldives">Maldives</option>
                            <option value="Mali">Mali</option>
                            <option value="Malta">Malta</option>
                            <option value="Marshall Islands">Marshall Islands</option>
                            <option value="Martinique">Martinique</option>
                            <option value="Mauritania">Mauritania</option>
                            <option value="Mauritius">Mauritius</option>
                            <option value="Mayotte">Mayotte</option>
                            <option value="Mexico">Mexico</option>
                            <option value="Micronesia">Micronesia</option>
                            <option value="Moldova">Moldova</option>
                            <option value="Monaco">Monaco</option>
                            <option value="Mongolia">Mongolia</option>
                            <option value="Montenegro">Montenegro</option>
                            <option value="Montserrat">Montserrat</option>
                            <option value="Morocco">Morocco</option>
                            <option value="Mozambique">Mozambique</option>
                            <option value="Myanmar (Burma)">Myanmar (Burma)</option>
                            <option value="Namibia">Namibia</option>
                            <option value="Nauru">Nauru</option>
                            <option value="Nepal">Nepal</option>
                            <option value="Netherlands">Netherlands</option>
                            <option value="New Caledonia">New Caledonia</option>
                            <option value="New Zealand">New Zealand</option>
                            <option value="Nicaragua">Nicaragua</option>
                            <option value="Niger">Niger</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="Niue">Niue</option>
                            <option value="Norfolk Island">Norfolk Island</option>
                            <option value="North Korea">North Korea</option>
                            <option value="North Macedonia">North Macedonia</option>
                            <option value="Norway">Norway</option>
                            <option value="Oman">Oman</option>
                            <option value="Pakistan">Pakistan</option>
                            <option value="Palau">Palau</option>
                            <option value="Palestinian Territories">Palestinian Territories</option>
                            <option value="Panama">Panama</option>
                            <option value="Papua New Guinea">Papua New Guinea</option>
                            <option value="Paraguay">Paraguay</option>
                            <option value="Peru">Peru</option>
                            <option value="Philippines">Philippines</option>
                            <option value="Poland">Poland</option>
                            <option value="Portugal">Portugal</option>
                            <option value="Puerto Rico">Puerto Rico</option>
                            <option value="Qatar">Qatar</option>
                            <option value="Réunion">Réunion</option>
                            <option value="Romania">Romania</option>
                            <option value="Russia">Russia</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Samoa">Samoa</option>
                            <option value="San Marino">San Marino</option>
                            <option value="São Tomé and Príncipe">São Tomé and Príncipe</option>
                            <option value="Saudi Arabia">Saudi Arabia</option>
                            <option value="Senegal">Senegal</option>
                            <option value="Serbia">Serbia</option>
                            <option value="Seychelles">Seychelles</option>
                            <option value="Sierra Leone">Sierra Leone</option>
                            <option value="Singapore">Singapore</option>
                            <option value="Sint Maarten">Sint Maarten</option>
                            <option value="Slovakia">Slovakia</option>
                            <option value="Slovenia">Slovenia</option>
                            <option value="Solomon Islands">Solomon Islands</option>
                            <option value="Somalia">Somalia</option>
                            <option value="South Africa">South Africa</option>
                            <option value="South Korea">South Korea</option>
                            <option value="South Sudan">South Sudan</option>
                            <option value="Spain">Spain</option>
                            <option value="Sri Lanka">Sri Lanka</option>
                            <option value="St. Barthélemy">St. Barthélemy</option>
                            <option value="St. Helena">St. Helena</option>
                            <option value="St. Kitts and Nevis">St. Kitts and Nevis</option>
                            <option value="St. Lucia">St. Lucia</option>
                            <option value="St. Martin">St. Martin</option>
                            <option value="St. Pierre and Miquelon">St. Pierre and Miquelon</option>
                            <option value="St. Vincent and Grenadines">St. Vincent and Grenadines</option>
                            <option value="Sudan">Sudan</option>
                            <option value="Suriname">Suriname</option>
                            <option value="Svalbard and Jan Mayen">Svalbard and Jan Mayen</option>
                            <option value="Sweden">Sweden</option>
                            <option value="Switzerland">Switzerland</option>
                            <option value="Syria">Syria</option>
                            <option value="Taiwan">Taiwan</option>
                            <option value="Tajikistan">Tajikistan</option>
                            <option value="Tanzania">Tanzania</option>
                            <option value="Thailand">Thailand</option>
                            <option value="Timor-Leste">Timor-Leste</option>
                            <option value="Togo">Togo</option>
                            <option value="Tokelau">Tokelau</option>
                            <option value="Tonga">Tonga</option>
                            <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                            <option value="Tunisia">Tunisia</option>
                            <option value="Turkey">Turkey</option>
                            <option value="Turkmenistan">Turkmenistan</option>
                            <option value="Turks and Caicos Islands">Turks and Caicos Islands</option>
                            <option value="Tuvalu">Tuvalu</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Ukraine">Ukraine</option>
                            <option value="United Arab Emirates">United Arab Emirates</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="United States">United States</option>
                            <option value="Uruguay">Uruguay</option>
                            <option value="Uzbekistan">Uzbekistan</option>
                            <option value="Vanuatu">Vanuatu</option>
                            <option value="Vatican City">Vatican City</option>
                            <option value="Venezuela">Venezuela</option>
                            <option value="Vietnam">Vietnam</option>
                            <option value="Wallis and Futuna">Wallis and Futuna</option>
                            <option value="Western Sahara">Western Sahara</option>
                            <option value="Yemen">Yemen</option>
                            <option value="Zambia">Zambia</option>
                            <option value="Zimbabwe">Zimbabwe</option>
                        </select>
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Source</span>
                        <select id="sourceSelect" required class="form-control rounded-0" name="sourceSelect">
                            <option value="">Select</option>
                            <option value="4">Whatsapp</option>
                            <option value="5">Facebook</option>
                            <option value="6">Any other source</option>
                        </select>
                    </div>

                    <div class="w-100 d-flex">
                        <div class="w-50">
                            <button type="button" class="btn btn-danger rounded-0" data-bs-dismiss="modal" aria-label="Close">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <input type="submit" class="btn btn-success rounded-0" value="Add">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleProgramFields() {
    var programType = document.getElementById('programType').value;
    var virtualProgramField = document.getElementById('virtualProgramField');
    var virtualProgram = document.getElementById('virtualProgram');
    var internationalEventField = document.getElementById('internationalEventField');
    var internationalFields = document.getElementById('internationalFields');
    var eventSelect = document.getElementById('event_id');
    
    if (programType === 'virtual') {
        // Show virtual program field
        virtualProgramField.style.display = 'flex';
        virtualProgram.required = true;
        
        // Hide international fields
        internationalEventField.style.display = 'none';
        internationalFields.style.display = 'none';
        eventSelect.required = false;
        
    } else if (programType === 'international') {
        // Show international event fields
        internationalEventField.style.display = 'flex';
        internationalFields.style.display = 'block';
        eventSelect.required = true;
        
        // Hide virtual program field
        virtualProgramField.style.display = 'none';
        virtualProgram.required = false;
        
    } else {
        // Hide all program-specific fields if nothing is selected
        virtualProgramField.style.display = 'none';
        internationalEventField.style.display = 'none';
        internationalFields.style.display = 'none';
        virtualProgram.required = false;
        eventSelect.required = false;
    }
}
</script>

<?php
// Function to generate admission number
function generateAdmissionNumber() {
    $prefix = "VASL"; 
    $number = rand(11111111, 99999999);
    return $prefix . " " . $number;
}

if(isset($_POST['email'])){
    // Get common fields
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $sourceSelect = mysqli_real_escape_string($conn, $_POST['sourceSelect']);
    $program_type = mysqli_real_escape_string($conn, $_POST['program_type']);
    $datee = date('Y-m-d H:i:s');
            $organization = isset($_POST['organization']) ? mysqli_real_escape_string($conn, $_POST['organization']) : '';
        $position = isset($_POST['position']) ? mysqli_real_escape_string($conn, $_POST['position']) : '';
    
    // Check if it's a virtual course or international event
    if ($program_type === 'virtual') {
        // Handle Virtual Course - Save to register table
        $entry_id = "U".rand(111111,999999);
        $program = mysqli_real_escape_string($conn, $_POST['program']);
        
        $stmt = mysqli_query($conn, 
            "INSERT INTO register (entry_id, email, firstname, lastname, phone_number, program, country, datee, source) 
             VALUES('$entry_id', '$email', '$firstname', '$lastname', '$phone_number', '$program', '$country', '$datee', '$sourceSelect')"
        ) or die(mysqli_error($conn));
        
        if ($stmt) {
           require_once 'email_plugins/vendor/autoload.php';
    require_once 'email_plugins/email_function.php';
    require_once 'amount_to_words/formatter.php';
    require_once 'pdf_plugins/generatePdf.php';   
// Fetch course details
$course_query = mysqli_query($conn, "SELECT * FROM course WHERE id = $program AND status = 1") or die(mysqli_error($conn));

if (mysqli_num_rows($course_query) == 0) {
    
    $course_query = mysqli_query($conn, "SELECT * FROM course WHERE course_id = $program AND status = 1") or die(mysqli_error($conn));
    if (mysqli_num_rows($course_query) == 0) {
           echo "<script>alert('Course not found'); window.location.href='index.php';</script>";
    exit; 
    }else{
    

    }
}

$course = mysqli_fetch_array($course_query);
$course_id = $course['course_id'];


                    $adm = $course["adm_letter"];
                      $amount = number_format($course['price_usd'],2, '.', ',');
                $program_name = $course['course'];
                $course_id_new = $course['course_id_new'];
                
                // Send email if configured
                $select = mysqli_query($conn,"SELECT * FROM system_emails1 WHERE course_opt='$program_name' AND email_opt = 1");
                if($select && mysqli_num_rows($select) > 0) {
                    $row_result = mysqli_fetch_array($select);
                    
                    $email_address = $email;
                    $subject = "Vantage Africa School Of Leadership Approval";
                    
                    $recipient_name = ucfirst(strtolower($firstname))." ".ucfirst(strtolower($lastname));
                    $body = json_decode($row_result['body'], true);
                    $body = str_replace('$name', $recipient_name, $body);
                     $adm_no = "VASL-".$entry_id;
                    $invoice_no = $adm_no;
                    $purpose = $program_name;
                  
                    include 'adm_letter.php';   
                    $userDetails = array(
    'email' => $email,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'phone_number' => $phone_number,
    'organization' => $organization,
    'position' => $position,
    'country' => $country
);
          
/**
 * SQL-based Moodle User Enrollment Script
 * Direct database interaction approach
 */

/**
 * Generate a secure random password
 */
function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%&*';
    
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    $all = $uppercase . $lowercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    return str_shuffle($password);
}

/**
 * Generate unique username from email
 */
function generateUniqueUsername($email, $moodle_conn) {
    $email_parts = explode('@', $email);
    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $email_parts[0]));
    
    $username = $base_username;
    $counter = 1;
    
    // Check if username exists
    while (true) {
        $check_sql = "SELECT id FROM mdl_user WHERE username = ? AND deleted = 0";
        $check_stmt = mysqli_prepare($moodle_conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $username);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($result) == 0) {
            break; // Username is unique
        }
        
        $username = $base_username . $counter;
        $counter++;
        mysqli_stmt_close($check_stmt);
    }
    
    mysqli_stmt_close($check_stmt);
    return $username;
}

/**
 * Create user in Moodle database or update existing user
 */
function createMoodleUser($userDetails, $moodle_conn) {
    try {
        // Check if email already exists
        $email_check_sql = "SELECT id, username FROM mdl_user WHERE email = ? AND deleted = 0";
        $email_stmt = mysqli_prepare($moodle_conn, $email_check_sql);
        mysqli_stmt_bind_param($email_stmt, "s", $userDetails['email']);
        mysqli_stmt_execute($email_stmt);
        $email_result = mysqli_stmt_get_result($email_stmt);
        
        if (mysqli_num_rows($email_result) > 0) {
            // User exists - update their password and return their info
            $existing_user = mysqli_fetch_assoc($email_result);
            mysqli_stmt_close($email_stmt);
            
            // Generate new password
            $new_password = generateSecurePassword();
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $current_time = time();
            
            // Update existing user's password and details
            $update_sql = "UPDATE mdl_user SET 
                password = ?, firstname = ?, lastname = ?, phone1 = ?, 
                institution = ?, department = ?, country = ?, timemodified = ?
                WHERE id = ?";
            
            $update_stmt = mysqli_prepare($moodle_conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sssssssii", 
                $hashed_password,
                $userDetails['firstname'],
                $userDetails['lastname'],
                $userDetails['phone_number'],
                $userDetails['organization'],
                $userDetails['position'],
                $userDetails['country'],
                $current_time,
                $existing_user['id']
            );
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to update existing user: " . mysqli_error($moodle_conn));
            }
            mysqli_stmt_close($update_stmt);
            
            // Update/set force password change preference
            $pref_check_sql = "SELECT id FROM mdl_user_preferences WHERE userid = ? AND name = 'auth_forcepasswordchange'";
            $pref_check_stmt = mysqli_prepare($moodle_conn, $pref_check_sql);
            mysqli_stmt_bind_param($pref_check_stmt, "i", $existing_user['id']);
            mysqli_stmt_execute($pref_check_stmt);
            $pref_result = mysqli_stmt_get_result($pref_check_stmt);
            
            if (mysqli_num_rows($pref_result) > 0) {
                // Update existing preference
                mysqli_stmt_close($pref_check_stmt);
                $pref_update_sql = "UPDATE mdl_user_preferences SET value = '1' WHERE userid = ? AND name = 'auth_forcepasswordchange'";
                $pref_update_stmt = mysqli_prepare($moodle_conn, $pref_update_sql);
                mysqli_stmt_bind_param($pref_update_stmt, "i", $existing_user['id']);
                mysqli_stmt_execute($pref_update_stmt);
                mysqli_stmt_close($pref_update_stmt);
            } else {
                // Insert new preference
                mysqli_stmt_close($pref_check_stmt);
                $pref_sql = "INSERT INTO mdl_user_preferences (userid, name, value) VALUES (?, 'auth_forcepasswordchange', '1')";
                $pref_stmt = mysqli_prepare($moodle_conn, $pref_sql);
                mysqli_stmt_bind_param($pref_stmt, "i", $existing_user['id']);
                mysqli_stmt_execute($pref_stmt);
                mysqli_stmt_close($pref_stmt);
            }
            
            return array(
                'success' => true,
                'userid' => $existing_user['id'],
                'username' => $existing_user['username'],
                'password' => $new_password,
                'user_existed' => true
            );
        }
        mysqli_stmt_close($email_stmt);
        
        // Generate unique username and password
        $username = generateUniqueUsername($userDetails['email'], $moodle_conn);
        $password = generateSecurePassword();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Get current timestamp
        $current_time = time();
        
        // Prepare user data
        $user_sql = "INSERT INTO mdl_user (
            username, password, firstname, lastname, email, phone1, 
            institution, department, country, auth, confirmed, mnethostid, 
            lang, timezone, mailformat, maildigest, maildisplay, 
            autosubscribe, trackforums, trustbitmask, timecreated, 
            timemodified, descriptionformat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', 1, 1, 'en', '99', 1, 0, 2, 1, 0, 0, ?, ?, 1)";
        
        $user_stmt = mysqli_prepare($moodle_conn, $user_sql);
        mysqli_stmt_bind_param($user_stmt, "sssssssssii", 
            $username,
            $hashed_password,
            $userDetails['firstname'],
            $userDetails['lastname'],
            $userDetails['email'],
            $userDetails['phone_number'],
            $userDetails['organization'],
            $userDetails['position'],
            $userDetails['country'],
            $current_time,
            $current_time
        );
        
        if (!mysqli_stmt_execute($user_stmt)) {
            throw new Exception("Failed to create user: " . mysqli_error($moodle_conn));
        }
        
        $userid = mysqli_insert_id($moodle_conn);
        mysqli_stmt_close($user_stmt);
        
        // Set force password change preference
        $pref_sql = "INSERT INTO mdl_user_preferences (userid, name, value) VALUES (?, 'auth_forcepasswordchange', '1')";
        $pref_stmt = mysqli_prepare($moodle_conn, $pref_sql);
        mysqli_stmt_bind_param($pref_stmt, "i", $userid);
        mysqli_stmt_execute($pref_stmt);
        mysqli_stmt_close($pref_stmt);
        
        return array(
            'success' => true,
            'userid' => $userid,
            'username' => $username,
            'password' => $password
        );
        
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}

/**
 * Enroll user in course
 */
function enrollUserInCourse($userid, $course_id, $moodle_conn) {
    try {
        // Get course details and check if course is visible
        $course_sql = "SELECT id, fullname, visible, startdate, enddate FROM mdl_course WHERE id = ?";
        $course_stmt = mysqli_prepare($moodle_conn, $course_sql);
        mysqli_stmt_bind_param($course_stmt, "i", $course_id);
        mysqli_stmt_execute($course_stmt);
        $course_result = mysqli_stmt_get_result($course_stmt);
        
        if (mysqli_num_rows($course_result) == 0) {
            throw new Exception("Course with ID {$course_id} not found");
        }
        
        $course = mysqli_fetch_assoc($course_result);
        mysqli_stmt_close($course_stmt);
        
        // Check if course is visible
        if ($course['visible'] == 0) {
            echo "⚠️  Warning: Course '{$course['fullname']}' is hidden. User may not see it.\n";
        }
        
        // Get manual enrollment instance for the course
        $enrol_sql = "SELECT id, status FROM mdl_enrol WHERE courseid = ? AND enrol = 'manual'";
        $enrol_stmt = mysqli_prepare($moodle_conn, $enrol_sql);
        mysqli_stmt_bind_param($enrol_stmt, "i", $course_id);
        mysqli_stmt_execute($enrol_stmt);
        $enrol_result = mysqli_stmt_get_result($enrol_stmt);
        
        if (mysqli_num_rows($enrol_result) == 0) {
            throw new Exception("Manual enrollment not available for course ID {$course_id}");
        }
        
        $enrol_instance = mysqli_fetch_assoc($enrol_result);
        mysqli_stmt_close($enrol_stmt);
        
        // Check if enrollment method is enabled
        if ($enrol_instance['status'] != 0) {
            echo "⚠️  Warning: Manual enrollment is disabled for this course.\n";
        }
        
        // Get student role ID
        $role_sql = "SELECT id FROM mdl_role WHERE shortname = 'student'";
        $role_result = mysqli_query($moodle_conn, $role_sql);
        
        if (mysqli_num_rows($role_result) == 0) {
            throw new Exception("Student role not found");
        }
        
        $role = mysqli_fetch_assoc($role_result);
        $student_role_id = $role['id'];
        
        // Check if user is already enrolled
        $check_enrollment_sql = "SELECT id FROM mdl_user_enrolments WHERE enrolid = ? AND userid = ?";
        $check_stmt = mysqli_prepare($moodle_conn, $check_enrollment_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $enrol_instance['id'], $userid);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            mysqli_stmt_close($check_stmt);
            throw new Exception("User is already enrolled in this course");
        }
        mysqli_stmt_close($check_stmt);
        
        $current_time = time();
        
        // Enroll user - Make sure timeend is NULL or 0 for unlimited access
        $enroll_sql = "INSERT INTO mdl_user_enrolments (enrolid, userid, timestart, timeend, modifierid, timecreated, timemodified, status) 
                       VALUES (?, ?, ?, 0, 1, ?, ?, 0)";
        $enroll_stmt = mysqli_prepare($moodle_conn, $enroll_sql);
        mysqli_stmt_bind_param($enroll_stmt, "iiiii", 
            $enrol_instance['id'], 
            $userid, 
            $current_time, 
            $current_time, 
            $current_time
        );
        
        if (!mysqli_stmt_execute($enroll_stmt)) {
            throw new Exception("Failed to enroll user: " . mysqli_error($moodle_conn));
        }
        
        $enrollment_id = mysqli_insert_id($moodle_conn);
        mysqli_stmt_close($enroll_stmt);
        
        // Get or create course context
        $context_sql = "SELECT id FROM mdl_context WHERE contextlevel = 50 AND instanceid = ?";
        $context_stmt = mysqli_prepare($moodle_conn, $context_sql);
        mysqli_stmt_bind_param($context_stmt, "i", $course_id);
        mysqli_stmt_execute($context_stmt);
        $context_result = mysqli_stmt_get_result($context_stmt);
        
        if (mysqli_num_rows($context_result) == 0) {
            mysqli_stmt_close($context_stmt);
            
            // Create course context if it doesn't exist
            $create_context_sql = "INSERT INTO mdl_context (contextlevel, instanceid, path, depth) VALUES (50, ?, '', 1)";
            $create_context_stmt = mysqli_prepare($moodle_conn, $create_context_sql);
            mysqli_stmt_bind_param($create_context_stmt, "i", $course_id);
            mysqli_stmt_execute($create_context_stmt);
            $context_id = mysqli_insert_id($moodle_conn);
            mysqli_stmt_close($create_context_stmt);
            
            // Update the path
            $update_path_sql = "UPDATE mdl_context SET path = CONCAT('/1/', id) WHERE id = ?";
            $update_path_stmt = mysqli_prepare($moodle_conn, $update_path_sql);
            mysqli_stmt_bind_param($update_path_stmt, "i", $context_id);
            mysqli_stmt_execute($update_path_stmt);
            mysqli_stmt_close($update_path_stmt);
        } else {
            $context = mysqli_fetch_assoc($context_result);
            $context_id = $context['id'];
            mysqli_stmt_close($context_stmt);
        }
        
        // Check if role assignment already exists
        $check_role_sql = "SELECT id FROM mdl_role_assignments WHERE roleid = ? AND contextid = ? AND userid = ?";
        $check_role_stmt = mysqli_prepare($moodle_conn, $check_role_sql);
        mysqli_stmt_bind_param($check_role_stmt, "iii", $student_role_id, $context_id, $userid);
        mysqli_stmt_execute($check_role_stmt);
        $check_role_result = mysqli_stmt_get_result($check_role_stmt);
        
        if (mysqli_num_rows($check_role_result) == 0) {
            mysqli_stmt_close($check_role_stmt);
            
            // Assign student role
            $role_assign_sql = "INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder) 
                               VALUES (?, ?, ?, ?, 1, '', 0, 0)";
            $role_stmt = mysqli_prepare($moodle_conn, $role_assign_sql);
            mysqli_stmt_bind_param($role_stmt, "iiii", 
                $student_role_id, 
                $context_id, 
                $userid, 
                $current_time
            );
            
            if (!mysqli_stmt_execute($role_stmt)) {
                throw new Exception("Failed to assign student role: " . mysqli_error($moodle_conn));
            }
            mysqli_stmt_close($role_stmt);
        } else {
            mysqli_stmt_close($check_role_stmt);
        }
        
        // // Clear user's course cache to ensure course appears immediately
        // $cache_sql = "DELETE FROM mdl_cache_text WHERE md5key LIKE ?";
        // $cache_key = '%' . md5('course_' . $userid) . '%';
        // $cache_stmt = mysqli_prepare($moodle_conn, $cache_sql);
        // mysqli_stmt_bind_param($cache_stmt, "s", $cache_key);
        // mysqli_stmt_execute($cache_stmt);
        // mysqli_stmt_close($cache_stmt);
        
        return array(
            'success' => true,
            'course_name' => $course['fullname'],
            'enrollment_id' => $enrollment_id,
            'context_id' => $context_id,
            'course_visible' => $course['visible']
        );
        
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}

/**
 * Send welcome email using your existing function
 */
function sendWelcomeEmail($userDetails, $courseName, $username, $password) {
    $subject = "Welcome to {$courseName} — Your Access Details Inside";
    
    // You can customize this URL to your Moodle installation
    $loginLink = "https://vantageafricaleaders.com/moodle/login/index.php";
    $supportEmail = "info@vantageafricaleaders.com";
    $supportPhone = "+254796128454";
    $supportWhatsAppLink = "https://wa.me/254796128454";
    $portalName = "Vantage Africa School Of Leadership E-Learning Portal";
    
    $body = '
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . $subject . '</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; background-color: #f4f4f4;">
        <div style="background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="https://vantageafricaleaders.com/assets/logo.png" 
                     style="height: 60px;" alt="Vantage Africa">
            </div>
            
            <h2 style="color: #2B5470; text-align: center;">Welcome to ' . $courseName . '!</h2>
            
            <p><strong>Hi ' . $userDetails['firstname'] . ',</strong></p>
            
            <p>Great news — we\'ve set up your account for <strong>' . $courseName . '</strong> on the ' . $portalName . '.</p>
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #A85431;">
                <h3 style="color: #2B5470; margin-top: 0;">🔑 Your Access Details</h3>
                <p style="margin: 8px 0;"><strong>Username:</strong> <span style="background-color: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 14px;">' . $username . '</span></p>
                <p style="margin: 8px 0;"><strong>Password:</strong> <span style="background-color: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 14px;">' . $password . '</span></p>
                <p style="margin: 8px 0;"><strong>Login Link:</strong> <a href="' . $loginLink . '" style="color: #A85431; font-weight: bold;">' . $loginLink . '</a></p>
                <p style="font-size: 0.9em; color: #666; margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 4px;"><em><strong>Important:</strong> You\'ll be asked to change your password on first login for security.</em></p>
            </div>
            
            <div style="background-color: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8;">
                <h3 style="color: #0c5460; margin-top: 0;">🚀 Start with Module 1 (10–15 min):</h3>
                <ul style="margin: 0; padding-left: 20px; color: #0c5460;">
                    <li style="margin-bottom: 8px;">Watch the short intro video</li>
                    <li style="margin-bottom: 8px;">Open the reading pack</li>
                    <li style="margin-bottom: 8px;">Attempt Quiz 1 (you can retake it)</li>
                </ul>
            </div>
            
            <p>If anything blocks you, reply to this email or contact our support team on <strong>WhatsApp</strong> or <strong>call</strong>: <a href="' . $supportWhatsAppLink . '" style="color: #A85431; font-weight: bold;">' . $supportPhone . '</a>. We\'ll get you in within minutes.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $loginLink . '" 
                   style="background-color: #A85431; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; font-size: 16px;">
                   🔗 Log in now →
                </a>
            </div>
            
            <p><strong>Warmly,</strong><br>
            <span style="color: #A85431; font-weight: bold;">Vantage Africa Team</span></p>
            
            <div style="border-top: 1px solid #eee; margin-top: 30px; padding-top: 20px; text-align: center; color: #666; font-size: 0.9em;">
                <p><strong>Support:</strong> <a href="mailto:' . $supportEmail . '" style="color: #A85431;">' . $supportEmail . '</a></p>
                <p style="margin-top: 6px;"><strong>Call/WhatsApp:</strong> <a href="' . $supportWhatsAppLink . '" style="color: #A85431; font-weight: bold; text-decoration: none;">' . $supportPhone . '</a></p>
                <p>&copy; ' . date('Y') . ' Vantage Africa School of Leadership. All Rights Reserved</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Use your existing send_mail_function
    return send_mail_function($userDetails['email'], $body, $subject, []);
}

/**
 * Debug user enrollment - Check why user can't see course
 */
function debugUserEnrollment($userid, $course_id, $moodle_conn) {
    echo "\n=== DEBUGGING USER ENROLLMENT ===\n";
    
    // Check if user exists and is active
    $user_sql = "SELECT id, username, email, deleted, suspended, confirmed FROM mdl_user WHERE id = ?";
    $user_stmt = mysqli_prepare($moodle_conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "i", $userid);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    
    if (mysqli_num_rows($user_result) > 0) {
        $user = mysqli_fetch_assoc($user_result);
        echo "✓ User found: {$user['username']} ({$user['email']})\n";
        echo "  - Deleted: " . ($user['deleted'] ? 'YES ❌' : 'No ✓') . "\n";
        echo "  - Suspended: " . ($user['suspended'] ? 'YES ❌' : 'No ✓') . "\n";
        echo "  - Confirmed: " . ($user['confirmed'] ? 'Yes ✓' : 'NO ❌') . "\n";
    } else {
        echo "❌ User not found!\n";
        return;
    }
    mysqli_stmt_close($user_stmt);
    
    // Check course details
    $course_sql = "SELECT id, fullname, shortname, visible, startdate, enddate FROM mdl_course WHERE id = ?";
    $course_stmt = mysqli_prepare($moodle_conn, $course_sql);
    mysqli_stmt_bind_param($course_stmt, "i", $course_id);
    mysqli_stmt_execute($course_stmt);
    $course_result = mysqli_stmt_get_result($course_stmt);
    
    if (mysqli_num_rows($course_result) > 0) {
        $course = mysqli_fetch_assoc($course_result);
        echo "✓ Course found: {$course['fullname']} ({$course['shortname']})\n";
        echo "  - Visible: " . ($course['visible'] ? 'Yes ✓' : 'NO ❌') . "\n";
        echo "  - Start Date: " . ($course['startdate'] ? date('Y-m-d H:i:s', $course['startdate']) : 'No restriction') . "\n";
        echo "  - End Date: " . ($course['enddate'] ? date('Y-m-d H:i:s', $course['enddate']) : 'No restriction') . "\n";
    } else {
        echo "❌ Course not found!\n";
        return;
    }
    mysqli_stmt_close($course_stmt);
    
    // Check enrollment methods
    $enrol_sql = "SELECT id, enrol, status, name FROM mdl_enrol WHERE courseid = ?";
    $enrol_stmt = mysqli_prepare($moodle_conn, $enrol_sql);
    mysqli_stmt_bind_param($enrol_stmt, "i", $course_id);
    mysqli_stmt_execute($enrol_stmt);
    $enrol_result = mysqli_stmt_get_result($enrol_stmt);
    
    echo "Enrollment methods for course:\n";
    while ($enrol = mysqli_fetch_assoc($enrol_result)) {
        $status = $enrol['status'] == 0 ? 'Enabled ✓' : 'Disabled ❌';
        echo "  - {$enrol['enrol']} ({$enrol['name']}): {$status}\n";
    }
    mysqli_stmt_close($enrol_stmt);
    
    // Check user enrollment
    $user_enrol_sql = "
        SELECT ue.id, ue.status, ue.timestart, ue.timeend, e.enrol, e.name
        FROM mdl_user_enrolments ue
        JOIN mdl_enrol e ON ue.enrolid = e.id
        WHERE ue.userid = ? AND e.courseid = ?
    ";
    $user_enrol_stmt = mysqli_prepare($moodle_conn, $user_enrol_sql);
    mysqli_stmt_bind_param($user_enrol_stmt, "ii", $userid, $course_id);
    mysqli_stmt_execute($user_enrol_stmt);
    $user_enrol_result = mysqli_stmt_get_result($user_enrol_stmt);
    
    if (mysqli_num_rows($user_enrol_result) > 0) {
        echo "User enrollment records:\n";
        while ($enrollment = mysqli_fetch_assoc($user_enrol_result)) {
            $status = $enrollment['status'] == 0 ? 'Active ✓' : 'Suspended ❌';
            $timestart = $enrollment['timestart'] ? date('Y-m-d H:i:s', $enrollment['timestart']) : 'No start restriction';
            $timeend = $enrollment['timeend'] ? date('Y-m-d H:i:s', $enrollment['timeend']) : 'No end restriction';
            echo "  - Method: {$enrollment['enrol']} ({$enrollment['name']})\n";
            echo "    Status: {$status}\n";
            echo "    Start: {$timestart}\n";
            echo "    End: {$timeend}\n";
        }
    } else {
        echo "❌ No enrollment records found for this user in this course!\n";
    }
    mysqli_stmt_close($user_enrol_stmt);
    
    // Check role assignments
    $role_sql = "
        SELECT ra.id, r.shortname, r.name, c.contextlevel, c.instanceid
        FROM mdl_role_assignments ra
        JOIN mdl_role r ON ra.roleid = r.id
        JOIN mdl_context c ON ra.contextid = c.id
        WHERE ra.userid = ? AND c.contextlevel = 50 AND c.instanceid = ?
    ";
    $role_stmt = mysqli_prepare($moodle_conn, $role_sql);
    mysqli_stmt_bind_param($role_stmt, "ii", $userid, $course_id);
    mysqli_stmt_execute($role_stmt);
    $role_result = mysqli_stmt_get_result($role_stmt);
    
    if (mysqli_num_rows($role_result) > 0) {
        echo "Role assignments in course:\n";
        while ($role = mysqli_fetch_assoc($role_result)) {
            echo "  ✓ Role: {$role['shortname']} ({$role['name']})\n";
        }
    } else {
        echo "❌ No role assignments found for this user in this course!\n";
    }
    mysqli_stmt_close($role_stmt);
    
    // Check course context
    $context_sql = "SELECT id, path, depth FROM mdl_context WHERE contextlevel = 50 AND instanceid = ?";
    $context_stmt = mysqli_prepare($moodle_conn, $context_sql);
    mysqli_stmt_bind_param($context_stmt, "i", $course_id);
    mysqli_stmt_execute($context_stmt);
    $context_result = mysqli_stmt_get_result($context_stmt);
    
    if (mysqli_num_rows($context_result) > 0) {
        $context = mysqli_fetch_assoc($context_result);
        echo "✓ Course context exists: ID {$context['id']}, Path: {$context['path']}\n";
    } else {
        echo "❌ Course context missing!\n";
    }
    mysqli_stmt_close($context_stmt);
    
    echo "=== END DEBUGGING ===\n\n";
}

// Add this to your main function after successful enrollment
function createAndEnrollUser($userDetails, $course_id_new, $program_name, $moodle_conn) {
    // Start transaction
    mysqli_begin_transaction($moodle_conn);
    
    try {
        // Create user
        $userResult = createMoodleUser($userDetails, $moodle_conn);
        
        if (!$userResult['success']) {
            throw new Exception("User creation failed: " . $userResult['error']);
        }
        
        // Enroll user in course
        $enrollResult = enrollUserInCourse($userResult['userid'], $course_id_new, $moodle_conn);
        
        if (!$enrollResult['success']) {
            throw new Exception("Enrollment failed: " . $enrollResult['error']);
        }
        
        // Debug the enrollment (remove this in production)
        debugUserEnrollment($userResult['userid'], $course_id_new, $moodle_conn);
        
        // Send welcome email
        $emailResult = sendWelcomeEmail($userDetails, $program_name, $userResult['username'], $userResult['password']);
        
        // Commit transaction
        mysqli_commit($moodle_conn);
        
        return array(
            'success' => true,
            'message' => 'User created and enrolled successfully',
            'userid' => $userResult['userid'],
            'username' => $userResult['username'],
            'password' => $userResult['password'],
            'course_name' => $enrollResult['course_name'],
            'email_sent' => $emailResult
        );
        
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($moodle_conn);
        
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}
$moodle_conn = mysqli_connect("localhost", "vantage_elearning","Y)A)ilAZ!VYLPds1" , "vantage_elearning");
$result = createAndEnrollUser($userDetails, $course_id_new, $program_name, $moodle_conn);

error_log(print_r($result, true));
            ?>
            <script>
                window.alert("Virtual Course Added Successfully!");
                window.location.href="loaded_data";
            </script>
            <?php
        } else {
            ?>
            <script>
                window.alert("Failed to add virtual course!");
                window.location.href="loaded_data";
            </script>
            <?php
        }
        
    } } else if ($program_type === 'international') {
        // Handle International Event - Save to ticket_congress table
        $event_id = mysqli_real_escape_string($conn, $_POST['event_id']);

        
        // Get event amount from database
        $event_query = "SELECT early_amount FROM Event WHERE event_id = '$event_id'";
        $event_result = mysqli_query($conn, $event_query);
        $amount = 0;
        if ($event_result && mysqli_num_rows($event_result) > 0) {
            $event_data = mysqli_fetch_assoc($event_result);
            $amount = $event_data['early_amount'];
        }
        
        // Generate ticket details
        $ticket_id = 'VASL' . time();
        $ticket_number = 1;
        $admission_no = generateAdmissionNumber();
        $status = '1'; // Pending payment status
        $confirmation = 'pending';
        $term = 'terms';
        $fullname = $firstname . ' ' . $lastname;
        
        $insert_query = "INSERT INTO ticket_congress 
                        (fullname, email, term, phone_number, ticket_id, status, amount, ticket_number, confirmation, date_sent, organization, position, event_id, country, admission_no) 
                        VALUES 
                        ('$fullname', '$email', '$term', '$phone_number', '$ticket_id', '$status', '$amount', '$ticket_number', '$confirmation', '$datee', '$organization', '$position', '$event_id', '$country', '$admission_no')";
        
        $stmt = mysqli_query($conn, $insert_query) or die(mysqli_error($conn));
        
        if ($stmt) {
            
              include 'invoice_international_.php';
                        $start_on = $event_data['start_on'];
                      
$date = new DateTime($start_on);
$start_on = $date->format('jS M');

                        $end_on = $event_data['end_on'];
                        
                      $title = $event_data['event_title'];
$code = 0; // default value

if (stripos($title, 'Monitoring') !== false || stripos($title, 'Evaluation') !== false) {
    $code=1;
} elseif (stripos($title, 'Resource Mobilization') !== false) {
$code=2;
} elseif (stripos($title, 'Data Analysis') !== false) {
    $code=3;
}
                        
$date = new DateTime($end_on);
$end_on = $date->format('jS M');

                        $location = $event_data['location'];
                        
                                
                        generateAdmissionWithInvoice(
   $email, $fullname, $event_data['title'],
    [], // default items
    0, // no discount
    [], // default training areas
    $start_on, // start date
    $end_on, // end date
   $location,$conn
);  
            ?>
            <script>
                window.alert("International Event Registration Added Successfully!");
                window.location.href="loaded_data";
            </script>
            <?php
        } else {
            ?>
            <script>
                window.alert("Failed to add international event registration!");
                window.location.href="loaded_data";
            </script>
            <?php
        }
    }
    
    // Close the connection
    $conn->close();
}
?>

                <!-- Add Loaded Data Modal -->

                <div class="card-body">
                    <?php 
                    
$like_conditions = array_map(function($program) {
    return "program ='" . addslashes($program) . "'";
}, $course_);

// Combine the LIKE conditions with OR
$where_clause = implode(' OR ', $like_conditions);


$sql = "SELECT `id`, `entry_id`, `email`, `firstname`, `lastname`, `phone_number`, `program`, `country`, `datee` 
        FROM `register` 
        WHERE ($where_clause)  AND source>3
        ORDER BY datee DESC";
        
        
                    // $sql = "SELECT `id`, `entry_id`, `email`, `firstname`, `lastname`, `phone_number`, `program`, `country`, `datee`,source FROM `register` WHERE source >3  ORDER BY datee DESC";
$result = $conn->query($sql);
if (!function_exists('check_source')) {
function check_source($id){
    if($id == 4){
        return "Whatsapp";
    }
    else  if($id == 5){
        return "Facebook";

    }else{
        return "Any other source";
    }
}
}
                    ?>
                    <div class="table-responsive overflow">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 6, "desc" ]]'>
    <thead>
        <tr>
            <th class="nowrap">Email</th>
            <th class="nowrap">Firstname</th>
            <th class="nowrap">Lastname</th>
            <th class="nowrap">Phone Number</th>
            <th class="nowrap">Program</th>
            <th class="nowrap">Country</th>
             <th class="nowrap">Source</th>
            <th class="nowrap">Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result->num_rows > 0) {
            // Output data of each row
            while ($row = $result->fetch_assoc()) {
                echo "<tr onclick=\"location.href='includes/enquiry_details.inc.php?from=loaded_data&entry_id=" . $row['entry_id'] . "'\">";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['firstname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['lastname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
                echo "<td>" . htmlspecialchars(check_course($conn,$row['program'])) ."</td>";
                echo "<td>" . htmlspecialchars($row['country']) . "</td>";
                echo "<td>" . htmlspecialchars(check_source($row['source'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['datee']) . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7'>No results found</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php
$conn->close();
?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    function exportTableToExcel() {
    let table = $('#dataTable').DataTable();
    
    // Extract table headers
    let headers = [];
    $('#dataTable thead tr th').each(function() {
        headers.push($(this).text().trim()); // Get header text only
    });

    // Extract table data
    let allData = table.rows().data().toArray(); 

    // Convert data to array format without HTML
    let cleanData = allData.map(row => row.map(cell => $("<div>").html(cell).text()));

    // Add headers as the first row
    cleanData.unshift(headers);

    // Convert to Excel format
    let worksheet = XLSX.utils.aoa_to_sheet(cleanData);
    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, worksheet, "Sheet1");

    // Export the Excel file
    XLSX.writeFile(wb, "Get in touch<?php echo rand(11111,99999); ?>.xlsx");
}
</script>
<?php
require_once 'footer.php';
?>