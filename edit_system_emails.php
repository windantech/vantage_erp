<?php
require_once 'header.php';
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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Edit System Email</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <button onclick="location.href='system_emails1'" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-left-circle"></i> Back
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-12 col-md-12">
                            <div class="card rounded-0">
                                <div class="card-header bg_main rounded-0">
                                    Update Email
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Database connection and data retrieval
                                    $id = $conn->real_escape_string($_GET['id']);
                                    $query = "SELECT * FROM system_emails1 WHERE id = '$id'";
                                    $result = $conn->query($query) or die($conn->error);

                                    if ($result->num_rows > 0) {
                                        $sm__data = $result->fetch_assoc();

                                        // Extract title and subject
                                        $email_title = $sm__data['title'];
                                        $email_subject = $sm__data['subject'];

                                        // Decode the JSON body field
                                        $body = json_decode($sm__data['body'], true);

                                        // Assign each JSON key to a variable
                                        $alert_title = $body['alert_title'] ?? '';
                                        $main_content = $body['main_content'] ?? '';
                                        $call_to_action = $body['call_to_action'] ?? '';
                                        $invitation_message = $body['invitation_message'] ?? '';
                                        $schedule_link = $body['schedule_link'] ?? '';
                                        $trainer_link = $body['trainer_link'] ?? '';
                                        $training_details = $body['training_details'] ?? '';
                                        $elearning_link = $body['elearning_link'] ?? '';
                                        $training_reasons = $body['training_reasons'] ?? '';
                                        $video_link = $body['video_link'] ?? '';
                                        $training_link = $body['training_link'] ?? '';
                                        $whatsapp_link = $body['whatsapp_link'] ?? '';
                                        $registration_link = $body['registration_link'] ?? '';
                                        $investment_text = $body['investment_text'] ?? '';
                                        $class_coordinator_link = $body['class_coordinator_link'] ?? '';
                                        $referral_link = $body['referral_link'] ?? '';
                                    } else { ?>
                                        <script>
                                            location.href = "system_emails1";
                                        </script>
                                    <?php } ?>

                                    <form class="row" id="update_email_form1">
                                        <input type="hidden" name="upd_id" id="upd_id" value="<?php echo $id; ?>">

                                        <!-- Title Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Title</span>
                                                <input type="text" name="email_title" id="email_title" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($email_title); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Subject Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Subject</span>
                                                <input type="text" name="email_subject" id="email_subject" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($email_subject); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Alert Title Field -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Alert Title</span>
                                                <input type="text" name="alert_title" id="alert_title" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($alert_title); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Main Content Field -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Main Content</span>
                                                <textarea name="main_content" id="main_content" class="form-control rounded-0 w-100" required><?php echo htmlspecialchars($main_content); ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Call to Action Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Call to Action</span>
                                                <input type="text" name="call_to_action" id="call_to_action" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($call_to_action); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Invitation Message Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Invitation Message</span>
                                                <input type="text" name="invitation_message" id="invitation_message" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($invitation_message); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Schedule Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Schedule Link</span>
                                                <input type="url" name="schedule_link" id="schedule_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($schedule_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Trainer Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Trainer Link</span>
                                                <input type="url" name="trainer_link" id="trainer_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($trainer_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Training Details Field -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Training Details</span>
                                                <textarea name="training_details" id="training_details" class="form-control rounded-0 w-100" required><?php echo htmlspecialchars($training_details); ?></textarea>
                                            </div>
                                        </div>

                                        <!-- E-Learning Link Field -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">E-Learning Link</span>
                                                <input type="url" name="elearning_link" id="elearning_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($elearning_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Training Reasons Field -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Training Reasons</span>
                                                <textarea name="training_reasons" id="training_reasons" class="form-control rounded-0 w-100" required><?php echo htmlspecialchars($training_reasons); ?></textarea>
                                            </div>
                                        </div>

                                        <!-- CTA Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">CTA Link</span>
                                                <input type="url" name="video_link" id="video_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($video_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Training Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Training Link</span>
                                                <input type="text" name="training_link" id="training_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($training_link); ?>" required onkeyup="document.getElementById('cta_custom_preview').href = this.value;">
                                            </div>
                                        </div>

                                        <!-- WhatsApp Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">WhatsApp Link</span>
                                                <input type="url" name="whatsapp_link" id="whatsapp_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($whatsapp_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Registration Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Registration Link</span>
                                                <input type="url" name="registration_link" id="registration_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($registration_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Investment Text Field -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Investment Text</span>
                                                <textarea name="investment_text" id="investment_text" class="form-control rounded-0 w-100" required><?php echo htmlspecialchars($investment_text); ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Coordinator Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Coordinator Link</span>
                                                <input type="url" name="class_coordinator_link" id="class_coordinator_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($class_coordinator_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Referral Link Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group px-1 mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;">Referral Link</span>
                                                <input type="url" name="referral_link" id="referral_link" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($referral_link); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <div class="d-flex justify-content-end px-2">
                                            <button type="submit" class="btn btn-success rounded-0">Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-12 col-md-12">
                            <div class="card rounded-0">
                                <div class="card-header bg_main rounded-0">
                                    Preview Email
                                </div>
                                <div class="card-body">
                                    <div style="border: solid 1px #d1d3e2;">
                                        <img src="assets/img/logo.png" style="height: 50px; padding: 1rem 0 0 .5rem;" alt="">
                                        <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
                                        <div style="padding: 0 .5rem">
                                            <p style="text-align: left; margin-left: 30px;">
                                                Sub: <span id="subject_email"><?= htmlspecialchars($email_subject) ?></span>
                                            </p>

                                            <p style="text-align: left; margin-left: 30px;">
                                                Hi [Client Name]
                                            </p>

                                            <h3 id="alert_disp" style="text-align: center; padding: 20px; background-color: #FFC000; border-radius: 10px">
                                                <?= htmlspecialchars($alert_title) ?>
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
                                                <div style="float: left; width: 45%; padding: 5px; text-align: justify;" id="main_content_preview">
                                                    <?= nl2br(html_entity_decode(strip_tags($main_content))) ?>
                                                </div>

                                                <!-- Third Column: Heading Text -->
                                                <div style="float: left; width: 19%; padding: 5px; text-align: center; background-color: #5B3E00; color: #FF8686;" id="cta_preview">
                                                    <h2 id="cta_preview_text">
                                                        <?= htmlspecialchars($call_to_action) ?>
                                                    </h2>
                                                </div>

                                                <!-- Clear float after the divs -->
                                                <div style="clear: both;"></div>
                                            </div>

                                            <p style="text-align: left; margin-left: 0;" id="invitation_preview">
                                                <?= htmlspecialchars($invitation_message) ?>
                                            </p>

                                            <div style="width: 100%; overflow: hidden;">
                                                <a href="<?= htmlspecialchars($schedule_link) ?>" target="_blank" id="training_schedule_link" style="float: left; width: 28%; padding: 1px; text-align: center; text-decoration: none; color: black;">
                                                    <h3 style="padding: 10px 5px; background-color: #ED7D31; border: solid 2px #41719C; border-radius: 10px; margin: 0;">
                                                        See Training Schedule
                                                    </h3>
                                                </a>

                                                <a href="<?= htmlspecialchars($trainer_link) ?>" id="trainer_link_preview" target="_blank" style="float: left; width: 28%; padding: 1px; text-align: center; text-decoration: none; color: black;">
                                                    <h3 style="padding: 10px 5px; background-color: #ED7D31; border: solid 2px #41719C; border-radius: 10px; margin: 0;">
                                                        Meet your trainer (Dr. Benson)
                                                    </h3>
                                                </a>

                                                <div style="float: left; width: 35%; padding: 1px;">
                                                    <div id="training_details_preview" style="padding: 10px 15px; background-color: #ED7D31; border: solid 2px #41719C; border-radius: 10px; margin: 0;">
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
                                                <a href="<?= htmlspecialchars($elearning_link) ?>" id="elearning_link_preview" style="display: block; text-decoration: none; color: black; width: 100%; height: 100%;">
                                                    Register now in our vibrant E-Learning Platform
                                                </a>
                                            </h2>

                                            <h2 style="text-align: center; color: #FF8686;">
                                                Here`s why this training is crucial for you:
                                            </h2>

                                            <div id="training_reasons_preview">
                                                <?= nl2br($training_reasons) ?>
                                            </div>

                                            <div style="width: 100%; overflow: hidden;">
                                                <a href="<?= htmlspecialchars($video_link) ?>" target="_blank" id="cta_preview" style="float: left; width: 40%; padding: 10px; text-align: center; text-decoration: none; color: black;">
                                                    <h3 style="padding: 10px 5px; background-color: #ED7D31; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                                                        Watch Free Introductory M&E Training Here
                                                    </h3>
                                                </a>

                                                <div style="float: left; width: 20%; padding: 10px; text-align: center;">
                                                    <!-- Empty div for spacing -->
                                                </div>

                                                <a id="cta_custom_preview" href="<?= htmlspecialchars($video_link) ?>" target="_blank" style="float: left; width: 40%; padding: 10px; text-align: center; text-decoration: none; color: black;">
                                                    <div style="padding: 10px 5px; background-color: #FFC000; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                                                        <h3>I need training for my team</h3>
                                                        <small>(You will receive a customized proposal)</small>
                                                    </div>
                                                </a>

                                                <!-- Clear float after the divs -->
                                                <div style="clear: both;"></div>
                                            </div>


                                            <div style="width: 100%; overflow: hidden;">
                                                <a id="whatsapp_group_link" href="<?= htmlspecialchars($whatsapp_link) ?>" target="_blank" style="float: left; width: 24%; padding: 2px; text-align: center; text-decoration: none; color: black;">
                                                    <h4 style="padding: 10px 5px; text-align: center; background-color: #FFC000; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                                                        Join Class WhatsApp Group for More info
                                                    </h4>
                                                </a>

                                                <a href="<?= htmlspecialchars($registration_link) ?>" target="_blank" id="registration_link_preview" style="float: left; width: 24%; padding: 2px; text-decoration: none; color: black;">
                                                    <div id="investment_text_preview" style="padding: 10px 5px; background-color: #ED7D31; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                                                        <?= nl2br($investment_text) ?> <!-- This will display the investment text with proper HTML formatting and line breaks -->
                                                    </div>
                                                </a>

                                                <a id="class_coordinator_link" href="<?= htmlspecialchars($class_coordinator_link) ?>" target="_blank" style="float: left; width: 24%; padding: 2px; text-align: center; text-decoration: none; color: black;">
                                                    <h4 style="padding: 10px 5px; background-color: #FFC000; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                                                        Contact Class Coordinator (WhatsApp)
                                                    </h4>
                                                </a>

                                                <a id="referral_link_preview" href="<?= htmlspecialchars($referral_link) ?>" target="_blank" style="float: left; width: 24%; padding: 2px; text-align: center; text-decoration: none; color: black;">
                                                    <h4 style="padding: 10px 5px; background-color: #ED7D31; border: solid 2px #ED7D31; border-radius: 10px; margin: 0;">
                                                        Refer 5 Clients and Do the Course for FREE
                                                    </h4>
                                                </a>

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
                                                <span>sample@gmail.com</span>
                                                <a href="" style="text-decoration: underline; color: #9ba4b3; font-weight: 700;">Unsubscribe</a>
                                            </div>
                                            <div style="color: #9ba4b3; font-size: .8rem;">
                                                &copy; <?php echo date("Y"); ?>
                                                Vantage Africa School of Leadership. All Rights Reserved
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>

<script>
    // Function to initialize Summernote editor
    function initSummernote(selector, previewSelector) {
        $(selector).summernote({
            height: 200,
            toolbar: [
                ["style", ["style"]],
                ["font", ["bold", "italic", "underline", "strikethrough", "superscript", "subscript", "clear"]],
                ["fontname", ["fontname"]],
                ["fontsize", ["fontsize"]],
                ["color", ["color"]],
                ["para", ["ol", "ul", "paragraph", "height"]],
                ["table", ["table"]],
                ["insert", ["link", "picture", "video", "shape"]],
                ["view", ["undo", "redo", "fullscreen", "codeview", "help"]]
            ],
            callbacks: {
                onChange: function(contents) {
                    $(previewSelector).html(contents);
                }
            }
        });
    }

    // Initialize all editors
    initSummernote("#main_content", "#main_content_preview");
    initSummernote("#training_details", "#training_details_preview");
    initSummernote("#training_reasons", "#training_reasons_preview");
    initSummernote("#investment_text", "#investment_text_preview");
</script>