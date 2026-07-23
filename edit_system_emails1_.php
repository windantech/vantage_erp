<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';

        // Fetch international events
        $events = [];
        $event_result = $conn->query("SELECT event_id, event_title FROM Event WHERE status = 1 ORDER BY event_title ASC");
        if ($event_result) {
            while ($row = $event_result->fetch_assoc()) {
                $events[] = $row;
            }
        }

        // Fetch courses
        $courses = [];
        $course_result = $conn->query("SELECT course_id, course FROM course ORDER BY course ASC");
        if ($course_result) {
            while ($row = $course_result->fetch_assoc()) {
                $courses[] = $row;
            }
        }
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
                                <div class="card-header bg_main rounded-0" id="upd_compose_header">
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

                                        // Extract subject
                                        $email_subject = $sm__data['subject'];

                                        // Decode the JSON body field
                                        $body = json_decode($sm__data['body'], true);
                                        // Handle different body formats
                                        if (is_string($body)) {
                                            $body_content = $body;
                                        } elseif (is_array($body) && isset($body['body'])) {
                                            $body_content = $body['body'];
                                        } else {
                                            $body_content = $sm__data['body'];
                                        }

                                        // Get saved selections
                                        $saved_email_type = isset($sm__data['email_type']) ? $sm__data['email_type'] : 'virtual';
                                        $saved_course = $sm__data['course_opt'];
                                        $saved_event_id = isset($sm__data['event_id']) ? $sm__data['event_id'] : '';
                                        $saved_event_name = isset($sm__data['event_name']) ? $sm__data['event_name'] : '';
                                        $saved_email = $sm__data['email_opt'];
                                        $saved_template = $sm__data['temp_opt'];
                                    } else { ?>
                                        <script>
                                            location.href = "system_emails1";
                                        </script>
                                    <?php } ?>

                                    <!-- Step 1 Form (Subject, Course/Event, Email, Template) -->
                                    <form class="row d-flex" id="upd_step1">
                                        <!-- Email Type Selection -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Email Type</span>
                                                <select name="upd_email_type" id="upd_email_type" class="form-control rounded-0 w-100" required>
                                                    <option value="virtual" <?php echo $saved_email_type == 'virtual' ? 'selected' : ''; ?>>Virtual Courses</option>
                                                    <option value="international" <?php echo $saved_email_type == 'international' ? 'selected' : ''; ?>>International Events</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Subject Field -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Subject</span>
                                                <input type="text" name="upd_email_subject" id="upd_email_subject" class="form-control rounded-0 w-100" value="<?php echo htmlspecialchars($email_subject); ?>" placeholder="Enter Subject" aria-label="Subject" aria-describedby="basic-addon1" required>
                                            </div>
                                        </div>

                                        <!-- Select Course Field (for Virtual) -->
                                        <div class="col-xl-6 col-md-6 p-1 <?php echo $saved_email_type == 'international' ? 'd-none' : ''; ?>" id="upd_course_container">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Course</span>
                                                <select name="upd_course_opt" id="upd_course_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Course---</option>
                                                    <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo htmlspecialchars($course['course']); ?>"
                                                            data-id="<?php echo $course['course_id']; ?>"
                                                            <?php echo ($saved_course == $course['course']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($course['course']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Select Event Field (for International) -->
                                        <div class="col-xl-6 col-md-6 p-1 <?php echo $saved_email_type == 'virtual' ? 'd-none' : ''; ?>" id="upd_event_container">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Event</span>
                                                <select name="upd_event_opt" id="upd_event_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Event---</option>
                                                    <?php foreach ($events as $event): ?>
                                                    <option value="<?php echo htmlspecialchars($event['event_title']); ?>"
                                                            data-id="<?php echo $event['event_id']; ?>"
                                                            <?php echo ($saved_event_name == $event['event_title'] || $saved_event_id == $event['event_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($event['event_title']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Select Email Field -->
                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Email</span>
                                                <select name="upd_email_opt" id="upd_email_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Email Number---</option>
                                                    <?php for ($i = 1; $i <= 18; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo ($saved_email == $i) ? 'selected' : ''; ?>>
                                                        Email <?php echo $i; ?>
                                                    </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Select Email Template Field - with data-saved attribute -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Email Template</span>
                                                <select name="upd_temp_opt" id="upd_temp_opt" class="form-control rounded-0 w-100" data-saved="<?php echo htmlspecialchars($saved_template); ?>">
                                                    <option value="" hidden>---Select Email Template---</option>
                                                    <?php
                                                    $templates = [
                                                        "Follow Up Emails-1" => "Follow Up Emails-1",
                                                        "Follow Up  Email-2" => "Follow Up Email-2",
                                                        "Who is Dr. Benson Kiarie Meet your M&E Trainer" => "Who is Dr. Benson Kiarie Meet your M&E Trainer-3",
                                                        "Here is the recording link  Email-3" => "Here is the recording link Email-4",
                                                        "Day 2 of  Training  Email-4" => "Day 2 of Training Email-5",
                                                        "First 2  sessions   Email-5" => "First 2 sessions Email-6",
                                                        "You Can Still Register Email 6" => "You Can Still Register Email 7",
                                                        "Role of AI in Email 7" => "Role of AI in Email 8",
                                                        "Todays Class Link-Email 8" => "Todays Class Link-Email 9",
                                                        "Intake closed-Email 9" => "Intake closed-Email 10",
                                                        "Next Intake-Email 10" => "Next Intake-Email 11",
                                                        "Logical Model - Email 11" => "Logical Model - Email 12",
                                                        "Powerful  Reports- Email 12" => "Powerful Reports- Email 13",
                                                        "Thriving as a Consultant- Email 13" => "Thriving as a Consultant- Email 14",
                                                        "Next Intake Count Down- Email 14" => "Next Intake Count Down- Email 15",
                                                        "Register  Today- Email 15" => "Register Today- Email 16"
                                                    ];
                                                    foreach ($templates as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($saved_template == $value) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Template preview (full design, scrollable) -->
                                        <div class="col-xl-12 col-md-12 p-1 d-none" id="upd_tpl_preview_wrap">
                                            <div class="p-2" style="background:#f6f3ec; border:1px solid #e3d3a3;">
                                                <div class="small text-muted mb-2">
                                                    <i class="bi bi-eye"></i> Preview of <strong id="upd_tpl_preview_name"></strong> &mdash; this design will be used, fitted with your email's content.
                                                </div>
                                                <div id="upd_tpl_preview_box" style="height:480px; overflow:auto; border:1px solid #dee2e6; background:#fff;">
                                                    <iframe id="upd_tpl_preview" scrolling="no" style="border:0; background:#fff; transform-origin:top left;"></iframe>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Next Button -->
                                        <div class="d-flex justify-content-end px-2 mt-2">
                                            <button type="button" id="upd_step1_btn" class="btn btn-success rounded-0">
                                                Next <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <!-- Step 2 Form (Email Body) -->
                                    <form class="row d-none" id="upd_step2">
                                        <input type="hidden" name="upd_id" id="upd_id" value="<?php echo $id; ?>">

                                        <!-- Action bar -->
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2 p-2" style="background:#f6f3ec; border:1px solid #e3d3a3;">
                                                <div class="form-check form-switch me-2 mb-0">
                                                    <input class="form-check-input" type="checkbox" id="upd_ai_fit_toggle" checked>
                                                    <label class="form-check-label small" for="upd_ai_fit_toggle">Build from template (AI writes the words, design &amp; footer stay fixed)</label>
                                                </div>
                                                <button type="button" id="upd_plain_btn" class="btn btn-sm btn-outline-dark rounded-0">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Load As-Is
                                                </button>
                                                <span id="upd_status" class="small text-muted ms-1"></span>
                                            </div>
                                        </div>

                                        <!-- Two columns: editor (left) + live preview (right) -->
                                        <div class="col-lg-6 col-md-12 p-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="input-group-text rounded-0 bg_main me-2" style="width: 6rem;">Edit</span>
                                                <span class="small text-muted">Edit your email here</span>
                                            </div>
                                            <textarea name="upd_email_body" id="upd_email_body" class="form-control" required></textarea>
                                        </div>
                                        <div class="col-lg-6 col-md-12 p-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="input-group-text rounded-0 bg_main me-2" style="width: 6rem;">Preview</span>
                                                <span class="small text-muted">Live preview as you type</span>
                                            </div>
                                            <iframe id="upd_preview" style="width:100%; height:560px; border:1px solid #dee2e6; background:#fff;"></iframe>
                                        </div>

                                        <!-- Navigation Buttons -->
                                        <div class="w-100 d-flex mt-2 px-1">
                                            <div class="w-50">
                                                <button type="button" id="upd_prev_btn" class="btn btn-success rounded-0">
                                                    <i class="fa fa-hand-o-left" aria-hidden="true"></i> Previous
                                                </button>
                                            </div>
                                            <div class="w-50 d-flex justify-content-end">
                                                <button type="submit" id="upd_finish" class="btn btn-success rounded-0">
                                                    <i class="bi bi-check2" aria-hidden="true"></i> Update
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <!-- Saved/selected template HTML holder (hidden; JS reads from it) -->
                                    <div id="upd_preview_temps" style="display:none;"><?php echo $body_content; ?></div>
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

<script src="edit_system_emails1.js"></script>