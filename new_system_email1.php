<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';

        // Academic programmes are flagged by an 'academic#' marker in Event.location
        // (case-insensitive, and not always at the very start). LOWER(...) LIKE
        // '%academic#%' matches them wherever/however they're cased.
        // International events: current/future or undated, and NOT academic.
        $events = [];
        $event_result = $conn->query("SELECT event_id, event_title FROM Event
                    WHERE (location IS NULL OR LOWER(location) NOT LIKE '%academic#%')
                      AND (DATE(`end_on`) >= CURDATE() OR DATE(`start_on`) >= CURDATE() OR `end_on` IS NULL OR `start_on` IS NULL)
                    ORDER BY event_title ASC");
        if ($event_result) {
            while ($row = $event_result->fetch_assoc()) { $events[] = $row; }
        }

        // Academic programmes are evergreen, so list them ALL regardless of date.
        // Both save their template against event_id like international — only the
        // picker list differs.
        $acad_events = [];
        $acad_result = $conn->query("SELECT event_id, event_title FROM Event
                    WHERE LOWER(location) LIKE '%academic#%'
                    ORDER BY event_title ASC");
        if ($acad_result) {
            while ($row = $acad_result->fetch_assoc()) { $acad_events[] = $row; }
        }
        ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">New System Email</h6>
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
                        <div class="col-xl-12 col-md-12 mb-3">
                            <div class="card rounded-0">
                                <div class="card-header bg_main rounded-0" id="compose_header">
                                    Compose Email
                                </div>
                                <div class="card-body">
                                    <form class="row d-flex" id="step1">
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Email Type</span>
                                                <select name="email_type" id="email_type" class="form-control rounded-0 w-100" required>
                                                    <option value="" hidden>---Select Email Type---</option>
                                                    <option value="virtual">Virtual Courses</option>
                                                    <option value="international">International Events</option>
                                                    <option value="academic">Academic Programmes</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Subject</span>
                                                <input type="text" name="email_subject" id="email_subject" class="form-control rounded-0 w-100" placeholder="Enter Subject" aria-label="Subject" aria-describedby="basic-addon1" required>
                                            </div>
                                        </div>

                                        <div class="col-xl-6 col-md-6 p-1" id="course_container">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Course</span>
                                                <select name="course_opt" id="course_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Course---</option>
                                                    <?php
                                                    $check_course = mysqli_query($conn,"SELECT * FROM `course`") or die(mysqli_error($conn));
                                                    if(mysqli_num_rows($check_course)){
                                                        while($row = mysqli_fetch_array($check_course)){ ?>
                                                            <option value="<?php echo $row['course']; ?>" data-id="<?php echo $row['course_id']; ?>"><?php echo $row['course']; ?></option>
                                                        <?php }
                                                    } ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-6 col-md-6 p-1 d-none" id="event_container">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Event</span>
                                                <select name="event_opt" id="event_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Event---</option>
                                                    <?php foreach ($events as $event): ?>
                                                    <option value="<?php echo htmlspecialchars($event['event_title']); ?>" data-id="<?php echo $event['event_id']; ?>">
                                                        <?php echo htmlspecialchars($event['event_title']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-6 col-md-6 p-1 d-none" id="academic_container">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Programme</span>
                                                <select name="academic_opt" id="academic_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Academic Programme---</option>
                                                    <?php foreach ($acad_events as $aev): ?>
                                                    <option value="<?php echo htmlspecialchars($aev['event_title']); ?>" data-id="<?php echo $aev['event_id']; ?>">
                                                        <?php echo htmlspecialchars($aev['event_title']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-6 col-md-6 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Email Number</span>
                                                <select name="email_opt" id="email_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Email Number---</option>
                                                    <?php
                                                    for ($i = 1; $i <= 18; $i++) {
                                                        echo '<option value="' . $i . '">Email ' . $i . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Select Email Label</span>
                                                <select name="temp_opt" id="temp_opt" class="form-control rounded-0 w-100">
                                                    <option value="" hidden>---Select Label---</option>
                                                    <option value="__ai__">✨ Generate with AI</option>
                                                    <option value="__blank__">General / Blank</option>
                                                    <option value="Follow Up Emails-1">Follow Up Emails-1</option>
                                                    <option value="Follow Up  Email-2">Follow Up Email-2</option>
                                                    <option value="Who is Dr. Benson Kiarie Meet your M&E Trainer">Who is Dr. Benson Kiarie Meet your M&E Trainer-3</option>
                                                    <option value="Here is the recording link  Email-3">Here is the recording link Email-4</option>
                                                    <option value="Day 2 of  Training  Email-4">Day 2 of Training Email-5</option>
                                                    <option value="First 2  sessions   Email-5">First 2 sessions Email-6</option>
                                                    <option value="You Can Still Register Email 6">You Can Still Register Email 7</option>
                                                    <option value="Role of AI in Email 7">Role of AI in Email 8</option>
                                                    <option value="Todays Class Link-Email 8">Todays Class Link-Email 9</option>
                                                    <option value="Intake closed-Email 9">Intake closed-Email 10</option>
                                                    <option value="Next Intake-Email 10">Next Intake-Email 11</option>
                                                    <option value="Logical Model - Email 11">Logical Model - Email 12</option>
                                                    <option value="Powerful  Reports- Email 12">Powerful Reports- Email 13</option>
                                                    <option value="Thriving as a Consultant- Email 13">Thriving as a Consultant- Email 14</option>
                                                    <option value="Next Intake Count Down- Email 14">Next Intake Count Down- Email 15</option>
                                                    <option value="Register  Today- Email 15">Register Today- Email 16</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end px-2">
                                            <button type="button" id="step1_btn" class="btn btn-success rounded-0">
                                                Next <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <form class="row d-none" id="step2">
                                        <div class="col-xl-12 col-md-12 p-1">
                                            <textarea name="email_body" id="email_body" class="form-control" required style="display:none;"></textarea>

                                            <div id="ai_panel" class="mb-2 p-3 d-none" style="background:#fbf7ef; border:1px solid #e3d3a3;">
                                                <div class="fw-semibold mb-2" style="color:#7a1c2e;"><i class="bi bi-stars"></i> Generate email with AI</div>
                                                <textarea id="ai_message" class="form-control rounded-0 mb-2" rows="4" placeholder="Describe what this email should say. e.g. Invite registrants to our Data Analysis training starting next Monday, highlight the hands-on SPSS practice and the certificate."></textarea>
                                                <input type="text" id="ai_links" class="form-control rounded-0 mb-2" placeholder="Optional links (comma separated): https://... , https://...">
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button" id="ai_generate_btn" class="btn btn-sm rounded-0 text-white" style="background:#7a1c2e;">
                                                        <i class="bi bi-magic"></i> Generate
                                                    </button>
                                                    <span id="ai_status" class="small text-muted"></span>
                                                </div>
                                            </div>

                                            <div id="layout_picker" class="mb-2 p-2" style="background:#f6f6f8; border:1px solid #dee2e6;">
                                                <div class="small text-muted mb-2 fw-semibold">Start from a layout (you can customize after):</div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="button" class="btn btn-sm rounded-0 layout-btn text-white" style="background:#7a1c2e;" data-layout="house">★ VASL House Template</button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 layout-btn" data-layout="event">Event Promo</button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 layout-btn" data-layout="course">Course Follow-up</button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 layout-btn" data-layout="announce">Simple Announcement</button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 layout-btn" data-layout="reminder">Class Reminder</button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 layout-btn" data-layout="recording">Recording / Resources</button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 layout-btn" data-layout="closing">Intake Closing</button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 layout-btn" data-layout="welcome">Welcome / Onboarding</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-0 layout-btn" data-layout="blank">Blank Canvas</button>
                                                </div>
                                            </div>

                                            <div id="gjs" style="border:1px solid #dee2e6; min-height:520px;"></div>
                                        </div>

                                        <div class="w-100 d-flex">
                                            <div class="w-50">
                                                <button type="button" id="prev_btn" class="btn btn-success rounded-0">
                                                    <i class="fa fa-hand-o-left" aria-hidden="true"></i> Previous
                                                </button>
                                            </div>
                                            <div class="w-50 d-flex justify-content-end">
                                                <button type="submit" id="finish" class="btn btn-success rounded-0">
                                                    <i class="bi bi-check2" aria-hidden="true"></i> Submit
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <div id="preview_temps"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- GrapesJS drag-and-drop email builder with custom VASL-branded blocks. -->
<link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.13/dist/css/grapes.min.css">
<script src="https://unpkg.com/grapesjs@0.21.13/dist/grapes.min.js"></script>
<script src="https://unpkg.com/grapesjs-preset-newsletter@1.0.2/dist/grapesjs-preset-newsletter.min.js"></script>
<script src="vasl_email_templates.js"></script>

<style>
    #gjs { min-height: 560px; }
    .gjs-pn-panel, .gjs-block, .gjs-field { border-radius: 0 !important; }
    .gjs-block.vasl-block { font-size: 11px; }
    #step1 .error { color:#dc3545; font-size:.78rem; }
</style>

<script>
/* ============================================================================
   Self-contained page script:
     1) GrapesJS builder (branded blocks + starter layouts)
     2) Step-1 form (validation, localStorage save/restore, transitions)
     3) Submit
   Summernote is NOT used here. Waits for jQuery + GrapesJS before running.
============================================================================ */
(function bootstrapPage(){
    if (typeof window.jQuery === 'undefined' || typeof window.grapesjs === 'undefined') {
        return setTimeout(bootstrapPage, 50);
    }
    jQuery(function ($) {

    /* Defensive: tear down any Summernote the global scripts attached to us. */
    (function killSummernote(tries){
        try {
            if ($.fn && $.fn.summernote) {
                var $ta = $('#email_body');
                if ($ta.next('.note-editor').length || $ta.parent().find('.note-editor').length) {
                    $ta.summernote('destroy');
                }
                $('#step2 .note-editor').remove();
                $ta.hide();
            }
        } catch (e) {}
        if ((tries = (tries || 0) + 1) < 12) {
            setTimeout(function(){ killSummernote(tries); }, 150);
        }
    })(0);

    /* ===================== SECTION 1 — GrapesJS ===================== */
    var C = {
        maroon:'#7a1c2e', wine:'#5a1020', dark:'#3a1010', gold:'#c0a040',
        goldlt:'#e8c55a', ink:'#1a0a0a', cream:'#fdf6e3',
        font:"'Segoe UI', Arial, sans-serif"
    };

    var BLOCKS = {
        header:
            '<div style="background:'+C.maroon+'; padding:28px 40px 20px; text-align:center; font-family:'+C.font+';">' +
              '<img src="https://d15k2d11r6t6rl.cloudfront.net/pub/bfra/re3npkbr/uk0/cg1/09s/cropped-Vantage_africa_logo-PNG-1.png" alt="Vantage Africa" style="height:48px; display:inline-block;">' +
              '<div style="color:rgba(255,255,255,0.65); font-size:12px; letter-spacing:2px; text-transform:uppercase; margin-top:8px;">School of Leadership</div>' +
            '</div>',
        hero:
            '<div style="background:'+C.wine+'; padding:28px 40px; border-top:3px solid '+C.gold+'; font-family:'+C.font+';">' +
              '<span style="display:inline-block; background:'+C.gold+'; color:#3a1500; font-size:11px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; padding:4px 14px; border-radius:20px; margin-bottom:14px;">Badge text</span>' +
              '<h1 style="color:#fff; font-size:26px; font-weight:700; line-height:1.3; margin:0;">Your headline <span style="color:'+C.goldlt+';">highlight</span></h1>' +
            '</div>',
        intro:
            '<div style="background:#ffffff; padding:28px 40px; font-family:'+C.font+';">' +
              '<p style="font-size:17px; font-weight:700; color:#3a1000; margin:0 0 12px;">Hi $name,</p>' +
              '<p style="font-size:15px; color:#2c1010; line-height:1.75; margin:0;">Your introduction paragraph goes here. Replace this text with your message.</p>' +
            '</div>',
        button:
            '<div style="background:'+C.wine+'; padding:22px 40px; text-align:center; font-family:'+C.font+';">' +
              '<a href="http://" style="display:inline-block; background:'+C.gold+'; color:#3a1500; font-size:14px; font-weight:700; padding:12px 30px; border-radius:24px; text-decoration:none;">Call to action</a>' +
            '</div>',
        benefits:
            '<div style="background:'+C.dark+'; padding:28px 40px; font-family:'+C.font+';">' +
              '<p style="font-size:20px; font-weight:700; color:#fff; text-align:center; margin:0 0 22px;">Section title</p>' +
              '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' +
                '<td valign="top" width="50%" style="padding:0 7px 14px 0;">' +
                  '<div style="background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:16px;">' +
                    '<h3 style="font-size:14px; font-weight:700; color:#fff; margin:0 0 5px;">Benefit one</h3>' +
                    '<p style="font-size:13px; color:rgba(255,255,255,0.72); line-height:1.6; margin:0;">Short description of this benefit.</p>' +
                  '</div></td>' +
                '<td valign="top" width="50%" style="padding:0 0 14px 7px;">' +
                  '<div style="background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:16px;">' +
                    '<h3 style="font-size:14px; font-weight:700; color:#fff; margin:0 0 5px;">Benefit two</h3>' +
                    '<p style="font-size:13px; color:rgba(255,255,255,0.72); line-height:1.6; margin:0;">Short description of this benefit.</p>' +
                  '</div></td>' +
              '</tr></table>' +
            '</div>',
        pricing:
            '<div style="background:#fff; padding:0; font-family:'+C.font+';"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' +
              '<td valign="top" width="50%" align="center" style="padding:20px 18px; border-right:1px solid #e8d8d8;">' +
                '<h4 style="font-size:14px; font-weight:700; color:#3a1010; margin:0 0 6px;">Individual</h4>' +
                '<div style="display:inline-block; background:'+C.maroon+'; color:#fff; font-size:15px; font-weight:700; padding:6px 16px; border-radius:20px; margin-bottom:10px;">$195</div>' +
                '<p style="font-size:13px; color:#666; margin:0 0 10px;">What is included</p>' +
                '<a href="http://" style="display:inline-block; border:2px solid '+C.maroon+'; color:'+C.maroon+'; font-size:12px; font-weight:700; padding:6px 16px; border-radius:20px; text-decoration:none;">Register</a>' +
              '</td>' +
              '<td valign="top" width="50%" align="center" style="padding:20px 18px;">' +
                '<h4 style="font-size:14px; font-weight:700; color:#3a1010; margin:0 0 6px;">Team</h4>' +
                '<p style="font-size:13px; color:#666; margin:0 0 10px;">Tailored group packages for your organization.</p>' +
                '<a href="http://" style="display:inline-block; border:2px solid '+C.maroon+'; color:'+C.maroon+'; font-size:12px; font-weight:700; padding:6px 16px; border-radius:20px; text-decoration:none;">Contact us</a>' +
              '</td>' +
            '</tr></table></div>',
        divider:
            '<div style="background:#ffffff; padding:10px 40px;"><hr style="border:none; border-top:1px solid #e0d8d8; margin:0;"></div>',
        classinfo:
            '<div style="background:'+C.cream+'; padding:24px 40px; font-family:'+C.font+';">' +
              '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' +
                '<td valign="top" width="33%" align="center" style="padding:6px 10px;">' +
                  '<div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:'+C.maroon+'; font-weight:700; margin-bottom:4px;">Date</div>' +
                  '<div style="font-size:14px; color:#3a1010; font-weight:600;">Day, Month 00</div></td>' +
                '<td valign="top" width="33%" align="center" style="padding:6px 10px; border-left:1px solid #e8dcc0; border-right:1px solid #e8dcc0;">' +
                  '<div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:'+C.maroon+'; font-weight:700; margin-bottom:4px;">Time</div>' +
                  '<div style="font-size:14px; color:#3a1010; font-weight:600;">0:00 AM EAT</div></td>' +
                '<td valign="top" width="33%" align="center" style="padding:6px 10px;">' +
                  '<div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:'+C.maroon+'; font-weight:700; margin-bottom:4px;">Platform</div>' +
                  '<div style="font-size:14px; color:#3a1010; font-weight:600;">Online / Zoom</div></td>' +
              '</tr></table>' +
            '</div>',
        resources:
            '<div style="background:#ffffff; padding:24px 40px; font-family:'+C.font+';">' +
              '<p style="font-size:16px; font-weight:700; color:'+C.maroon+'; margin:0 0 14px;">Your resources</p>' +
              '<table width="100%" cellpadding="0" cellspacing="0" border="0">' +
                '<tr><td style="padding:8px 0; border-bottom:1px solid #f0eaea;"><a href="http://" style="color:#2c1010; font-size:14px; text-decoration:none;">&#9656; Session recording</a></td></tr>' +
                '<tr><td style="padding:8px 0; border-bottom:1px solid #f0eaea;"><a href="http://" style="color:#2c1010; font-size:14px; text-decoration:none;">&#9656; Slides &amp; handouts</a></td></tr>' +
                '<tr><td style="padding:8px 0;"><a href="http://" style="color:#2c1010; font-size:14px; text-decoration:none;">&#9656; E-learning portal</a></td></tr>' +
              '</table>' +
            '</div>',
        urgency:
            '<div style="background:'+C.gold+'; padding:18px 40px; text-align:center; font-family:'+C.font+';">' +
              '<div style="color:#3a1500; font-size:13px; font-weight:700; letter-spacing:1px; text-transform:uppercase;">Limited time</div>' +
              '<div style="color:#3a1010; font-size:18px; font-weight:700; margin-top:4px;">Intake closes soon &mdash; secure your place</div>' +
            '</div>',
        footer:
            '<div style="background:'+C.ink+'; padding:18px 40px; text-align:center; font-family:'+C.font+';">' +
              '<p style="color:rgba(255,255,255,0.4); font-size:11px; line-height:1.8; margin:0;">Vantage Africa School of Leadership<br>' +
              '<a href="#" style="color:rgba(255,255,255,0.55);">Unsubscribe</a> &middot; <a href="#" style="color:rgba(255,255,255,0.55);">Privacy Policy</a></p>' +
            '</div>'
    };

    var LAYOUTS = {
        event:    [BLOCKS.header, BLOCKS.hero, BLOCKS.intro, BLOCKS.benefits, BLOCKS.pricing, BLOCKS.button, BLOCKS.footer],
        course:   [BLOCKS.header, BLOCKS.hero, BLOCKS.intro, BLOCKS.button, BLOCKS.divider, BLOCKS.footer],
        announce: [BLOCKS.header, BLOCKS.intro, BLOCKS.button, BLOCKS.footer],
        reminder: [BLOCKS.header, BLOCKS.hero, BLOCKS.intro, BLOCKS.classinfo, BLOCKS.button, BLOCKS.footer],
        recording:[BLOCKS.header, BLOCKS.intro, BLOCKS.resources, BLOCKS.button, BLOCKS.footer],
        closing:  [BLOCKS.header, BLOCKS.urgency, BLOCKS.intro, BLOCKS.pricing, BLOCKS.button, BLOCKS.footer],
        welcome:  [BLOCKS.header, BLOCKS.hero, BLOCKS.intro, BLOCKS.benefits, BLOCKS.button, BLOCKS.footer],
        blank:    []
    };

    /* Build a customised intro block with a specific heading + body so each
       named template reads appropriately (staff still edit the words). */
    function introBlock(title, body) {
        return '<div style="background:#ffffff; padding:28px 40px; font-family:'+C.font+';">' +
                 '<p style="font-size:17px; font-weight:700; color:#3a1000; margin:0 0 12px;">Hi $name,</p>' +
                 (title ? '<p style="font-size:16px; font-weight:700; color:'+C.maroon+'; margin:0 0 10px;">'+title+'</p>' : '') +
                 '<p style="font-size:15px; color:#2c1010; line-height:1.75; margin:0;">'+body+'</p>' +
               '</div>';
    }

    /* The 16 named templates from the step-1 dropdown, each composed from
       branded blocks with purpose-shaped placeholder copy. Keys MUST match the
       <option value="..."> strings in #temp_opt exactly. */
    var NAMED_TEMPLATES = {
        "Follow Up Emails-1": [BLOCKS.header, BLOCKS.hero,
            introBlock("Great to connect with you", "Thank you for your interest in our training. We would love to help you take the next step. Here is what you need to know to get started."),
            BLOCKS.button, BLOCKS.footer],

        "Follow Up  Email-2": [BLOCKS.header,
            introBlock("Just checking in", "We noticed you have not completed your registration yet. We are here to help with any questions so you can secure your place."),
            BLOCKS.button, BLOCKS.divider, BLOCKS.footer],

        "Who is Dr. Benson Kiarie Meet your M&E Trainer": [BLOCKS.header, BLOCKS.hero,
            introBlock("Meet your trainer, Dr. Benson Kiarie, PhD", "Your training is led by a seasoned M&E expert with years of real-world experience helping professionals and organizations measure and demonstrate impact."),
            BLOCKS.benefits, BLOCKS.button, BLOCKS.footer],

        "Here is the recording link  Email-3": [BLOCKS.header,
            introBlock("Here is your session recording", "In case you missed it or would like to review, your session recording and resources are ready below."),
            BLOCKS.resources, BLOCKS.button, BLOCKS.footer],

        "Day 2 of  Training  Email-4": [BLOCKS.header, BLOCKS.hero,
            introBlock("See you for Day 2", "We had a great first session. Here are the details for Day 2 of your training so you are fully prepared."),
            BLOCKS.classinfo, BLOCKS.button, BLOCKS.footer],

        "First 2  sessions   Email-5": [BLOCKS.header,
            introBlock("How were your first two sessions?", "You are making excellent progress. Here is a quick recap and what to expect in the sessions ahead."),
            BLOCKS.resources, BLOCKS.button, BLOCKS.footer],

        "You Can Still Register Email 6": [BLOCKS.header, BLOCKS.urgency,
            introBlock("There is still time to join", "Registration is still open. Join your peers and start building the skills that move your career forward."),
            BLOCKS.pricing, BLOCKS.button, BLOCKS.footer],

        "Role of AI in Email 7": [BLOCKS.header, BLOCKS.hero,
            introBlock("The role of AI in your field", "Discover how artificial intelligence is transforming the way professionals work, and how this training prepares you for it."),
            BLOCKS.benefits, BLOCKS.button, BLOCKS.footer],

        "Todays Class Link-Email 8": [BLOCKS.header, BLOCKS.hero,
            introBlock("Your class starts today", "Here is everything you need to join today's live session. We look forward to seeing you there."),
            BLOCKS.classinfo, BLOCKS.button, BLOCKS.footer],

        "Intake closed-Email 9": [BLOCKS.header,
            introBlock("This intake is now closed", "Thank you for your interest. This intake has closed, but the next one is coming soon and we would love to have you join."),
            BLOCKS.button, BLOCKS.divider, BLOCKS.footer],

        "Next Intake-Email 10": [BLOCKS.header, BLOCKS.hero,
            introBlock("The next intake is open", "Good news. Registration for the next intake is now open. Secure your place early and get ready to grow."),
            BLOCKS.pricing, BLOCKS.button, BLOCKS.footer],

        "Logical Model - Email 11": [BLOCKS.header, BLOCKS.hero,
            introBlock("Mastering the Logical Framework", "The logical model is at the heart of effective M&E. Learn how to build one that clearly maps your inputs, outputs, and impact."),
            BLOCKS.benefits, BLOCKS.button, BLOCKS.footer],

        "Powerful  Reports- Email 12": [BLOCKS.header, BLOCKS.hero,
            introBlock("Write reports that drive decisions", "Great data deserves great reporting. Learn to craft powerful, clear reports that influence stakeholders and demonstrate impact."),
            BLOCKS.benefits, BLOCKS.button, BLOCKS.footer],

        "Thriving as a Consultant- Email 13": [BLOCKS.header, BLOCKS.hero,
            introBlock("Thriving as an independent consultant", "Ready to go independent? Discover the skills and strategies you need to build a thriving consulting practice."),
            BLOCKS.benefits, BLOCKS.button, BLOCKS.footer],

        "Next Intake Count Down- Email 14": [BLOCKS.header, BLOCKS.urgency,
            introBlock("The countdown is on", "Only a few days left before the next intake begins. Do not miss your chance to join this cohort."),
            BLOCKS.pricing, BLOCKS.button, BLOCKS.footer],

        "Register  Today- Email 15": [BLOCKS.header, BLOCKS.urgency, BLOCKS.hero,
            introBlock("Register today", "Take the next step in your professional journey. Register today and join a community of leaders and changemakers."),
            BLOCKS.pricing, BLOCKS.button, BLOCKS.footer]
    };

    function wrapEmail(inner) {
        return '<div class="vasl-email" style="max-width:600px; margin:0 auto; background:'+C.ink+'; font-family:'+C.font+';">' + inner + '</div>';
    }

    var editor = null;

    function initEditor() {
        if (editor) return editor;
        editor = grapesjs.init({
            container: '#gjs',
            height: '560px',
            fromElement: false,
            storageManager: false,
            plugins: ['grapesjs-preset-newsletter'],
            pluginsOpts: {
                'grapesjs-preset-newsletter': {
                    cellStyle: { 'font-size': '14px', 'font-family': C.font }
                }
            }
        });

        var bm = editor.BlockManager;
        function addBlock(id, label, content) {
            bm.add(id, { label: label, category: 'VASL Brand',
                         attributes: { class: 'vasl-block' }, content: content });
        }
        addBlock('vasl-header',   'Header / Logo', BLOCKS.header);
        addBlock('vasl-hero',     'Hero Banner',   BLOCKS.hero);
        addBlock('vasl-intro',    'Intro Text',    BLOCKS.intro);
        addBlock('vasl-button',   'CTA Button',    BLOCKS.button);
        addBlock('vasl-benefits', 'Benefit Cards', BLOCKS.benefits);
        addBlock('vasl-pricing',  'Pricing Row',   BLOCKS.pricing);
        addBlock('vasl-divider',  'Divider',       BLOCKS.divider);
        addBlock('vasl-classinfo','Class Details', BLOCKS.classinfo);
        addBlock('vasl-resources','Resource Links',BLOCKS.resources);
        addBlock('vasl-urgency',  'Urgency Band',  BLOCKS.urgency);
        addBlock('vasl-footer',   'Footer',        BLOCKS.footer);

        var sync = function () { syncEmailBody(); };
        editor.on('update component:update component:add component:remove style:update', sync);

        return editor;
    }

    function applyLayout(key) {
        if (key === 'house') { loadHouseTemplate(); return; }
        var ed = initEditor();
        var parts = LAYOUTS[key] || [];
        ed.setComponents(wrapEmail(parts.join('')));
        syncEmailBody();
    }

    /* Load the VASL house template (placeholder version) into the builder. */
    function loadHouseTemplate() {
        var ed = initEditor();
        $.ajax({
            url: 'email_house_default.php', type: 'GET', dataType: 'json',
            success: function (res) {
                if (res && res.ok && res.html) {
                    ed.setComponents(res.html);
                    syncEmailBody();
                }
            }
        });
    }

    function setEmailBody(html) {
        var ed = initEditor();
        ed.setComponents(html || '');
        $('#email_body').val(html || '');
    }
    /* Reliable HTML+CSS inliner.
       GrapesJS stores styles in a separate stylesheet keyed by auto-generated
       ids/classes (e.g. #i64k {...}). The preset's 'gjs-get-inlined-html'
       command is unreliable across versions, so we inline ourselves:
       parse editor.getCss(), match simple #id / .class / tag selectors against
       the editor.getHtml() DOM, and fold the declarations into style="".
    */
    function buildInlinedHtml() {
        if (!editor) return $('#email_body').val() || '';
        var rawHtml = editor.getHtml() || '';
        var rawCss  = editor.getCss({ avoidProtected: true }) || '';

        // Parse the HTML into a detached document we can manipulate
        var doc = document.implementation.createHTMLDocument('');
        doc.body.innerHTML = rawHtml;

        // Very small CSS parser: split rules on '}' then 'selector { decls }'
        // Handles comma-separated simple selectors (#id, .class, tag).
        var rules = [];
        rawCss.replace(/\/\*[\s\S]*?\*\//g, '') // strip comments
              .split('}').forEach(function (chunk) {
            var idx = chunk.indexOf('{');
            if (idx === -1) return;
            var sels = chunk.slice(0, idx).trim();
            var decls = chunk.slice(idx + 1).trim();
            if (!sels || !decls) return;
            // skip media queries / keyframes / pseudo selectors we can't inline
            if (sels.indexOf('@') !== -1 || sels.indexOf(':') !== -1) return;
            sels.split(',').forEach(function (sel) {
                sel = sel.trim();
                // only simple selectors (no combinators) are safe to inline
                if (!sel || /[ >+~]/.test(sel)) return;
                rules.push({ sel: sel, decls: decls });
            });
        });

        // Apply each rule's declarations to matching elements
        rules.forEach(function (r) {
            var nodes;
            try { nodes = doc.querySelectorAll(r.sel); } catch (e) { return; }
            nodes.forEach(function (el) {
                var existing = el.getAttribute('style') || '';
                if (existing && existing.charAt(existing.length - 1) !== ';') existing += ';';
                el.setAttribute('style', existing + r.decls + ';');
            });
        });

        // Strip the now-redundant GrapesJS id attributes for cleaner output
        doc.querySelectorAll('[id]').forEach(function (el) {
            if (/^i[a-z0-9]{3,}$/.test(el.id)) el.removeAttribute('id');
        });

        return doc.body.innerHTML;
    }

    function syncEmailBody() {
        $('#email_body').val(buildInlinedHtml());
    }

    function getEmailBody() {
        return buildInlinedHtml();
    }

    $('.layout-btn').on('click', function () {
        applyLayout($(this).attr('data-layout'));
    });

    // ---- AI generation: post the message/links to the backend, load result ----
    $('#ai_generate_btn').on('click', function () {
        var msg = $.trim($('#ai_message').val());
        var links = $.trim($('#ai_links').val());
        var purpose = localStorage.getItem('temp_opt') || '';
        if (!msg) {
            $('#ai_status').css('color', '#b00').text('Please describe what the email should say.');
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#ai_status').css('color', '#666').text('Generating your email…');

        $.ajax({
            url: 'email_ai_house_generate.php',
            type: 'POST',
            dataType: 'json',
            data: { message: msg, links: links },
            success: function (res) {
                if (res && res.ok && res.html) {
                    initEditor();
                    editor.setComponents(res.html);
                    syncEmailBody();
                    $('#ai_status').css('color', '#1a7a3c').text('Email generated into the house template. Edit it below, then Submit.');
                } else {
                    $('#ai_status').css('color', '#b00').text((res && res.error) ? res.error : 'Generation failed.');
                }
            },
            error: function (xhr, status, error) {
                $('#ai_status').css('color', '#b00').text('Request failed: ' + error);
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    var step2El = document.getElementById('step2');
    if (step2El) {
        var mo = new MutationObserver(function () {
            if (!step2El.classList.contains('d-none')) {
                initEditor();
                setTimeout(function () { if (editor) editor.refresh(); }, 60);
            }
        });
        mo.observe(step2El, { attributes: true, attributeFilter: ['class'] });
    }

    /* ============ SECTION 2 — Step-1 form ============ */
    if (localStorage.getItem("email_type")) {
        $("#email_type").val(localStorage.getItem("email_type"));
        handleEmailTypeChange(localStorage.getItem("email_type"));
    }
    if (localStorage.getItem("email_subject")) $("#email_subject").val(localStorage.getItem("email_subject"));
    if (localStorage.getItem("course_opt"))    $("#course_opt").val(localStorage.getItem("course_opt"));
    if (localStorage.getItem("event_opt"))     $("#event_opt").val(localStorage.getItem("event_opt"));
    if (localStorage.getItem("academic_opt"))  $("#academic_opt").val(localStorage.getItem("academic_opt"));
    if (localStorage.getItem("email_opt"))     $("#email_opt").val(localStorage.getItem("email_opt"));
    if (localStorage.getItem("temp_opt"))      $("#temp_opt").val(localStorage.getItem("temp_opt"));

    updateComposeHeader();

    $("#email_type").change(function () { handleEmailTypeChange($(this).val()); });

    function handleEmailTypeChange(selectedType) {
        $("#course_container, #event_container, #academic_container").addClass("d-none");
        if (selectedType === "virtual")            $("#course_container").removeClass("d-none");
        else if (selectedType === "international")  $("#event_container").removeClass("d-none");
        else if (selectedType === "academic")      $("#academic_container").removeClass("d-none");
    }

    function updateComposeHeader() {
        var emailType = localStorage.getItem("email_type");
        var emailOpt = localStorage.getItem("email_opt");
        if (emailType === "virtual" && localStorage.getItem("course_opt") && emailOpt) {
            $("#compose_header").html("Compose Email (" + localStorage.getItem("course_opt") + " - Email " + emailOpt + ")");
        } else if (emailType === "international" && localStorage.getItem("event_opt") && emailOpt) {
            $("#compose_header").html("Compose Email (" + localStorage.getItem("event_opt") + " - Email " + emailOpt + ")");
        } else if (emailType === "academic" && localStorage.getItem("academic_opt") && emailOpt) {
            $("#compose_header").html("Compose Email (" + localStorage.getItem("academic_opt") + " - Email " + emailOpt + ")");
        }
    }

    function goToStep2() {
        updateComposeHeader();
        $("#step1").addClass("d-none").removeClass("d-flex");
        $("#step2").addClass("d-flex").removeClass("d-none");
    }

    $("#step1_btn").click(function () {
        var emailType = $("#email_type").val();

        if (!emailType || emailType === "") {
            $("#email_type").css("border-color", "red");
            $("#email_type").after("<span class='error' style='position: absolute; right: 0; bottom: -3vh;'>Please select an email type.</span>");
            $("#email_type").focus();
            return false;
        } else {
            $("#email_type").css("border-color", "");
            $("#email_type").next(".error").remove();
        }

        var courseEventValid = false;
        if (emailType === "virtual") {
            courseEventValid = selNotEmpty($("#course_opt"));
        } else if (emailType === "international") {
            courseEventValid = selNotEmptyEvent($("#event_opt"));
        } else if (emailType === "academic") {
            courseEventValid = selNotEmptyEvent($("#academic_opt"));
        }

        if (isNotEmpty($("#email_subject")) && courseEventValid &&
            selNotEmpty($("#email_opt")) && selNotEmpty($("#temp_opt"))) {

            localStorage.setItem("email_type", emailType);
            localStorage.setItem("email_subject", $("#email_subject").val());
            localStorage.setItem("email_opt", $("#email_opt").val());
            localStorage.setItem("temp_opt", $("#temp_opt").val());

            if (emailType === "virtual") {
                localStorage.setItem("course_opt", $("#course_opt").val());
                localStorage.setItem("event_id", $("#course_opt option:selected").data("id") || "");
                localStorage.setItem("event_opt", "");
                localStorage.setItem("event_name", "");
            } else if (emailType === "international") {
                localStorage.setItem("event_opt", $("#event_opt").val());
                localStorage.setItem("event_id", $("#event_opt option:selected").data("id") || "");
                localStorage.setItem("event_name", $("#event_opt").val());
                localStorage.setItem("course_opt", "");
                localStorage.setItem("academic_opt", "");
            } else if (emailType === "academic") {
                // Academic programmes are Event rows — save against event_id just
                // like international, so the admission-letter lookup finds them.
                localStorage.setItem("academic_opt", $("#academic_opt").val());
                localStorage.setItem("event_opt", $("#academic_opt").val());
                localStorage.setItem("event_id", $("#academic_opt option:selected").data("id") || "");
                localStorage.setItem("event_name", $("#academic_opt").val());
                localStorage.setItem("course_opt", "");
            }

            // Seed the builder with the rich predefined template matching the
            // chosen name. Prefer the external VASL_TEMPLATES (full designs);
            // fall back to the inline NAMED_TEMPLATES, then to an empty canvas.
            var chosen = $("#temp_opt").val();
            var richHtml = (window.VASL_TEMPLATES && window.VASL_TEMPLATES[chosen]) ? window.VASL_TEMPLATES[chosen] : null;

            if (chosen === "__ai__") {
                // AI mode: show the AI panel, hide layout picker, start empty.
                $("#ai_panel").removeClass("d-none");
                $("#layout_picker").addClass("d-none");
                setEmailBody("");
            } else if (chosen && chosen !== "__blank__" && richHtml) {
                $("#ai_panel").addClass("d-none");
                $("#layout_picker").removeClass("d-none");
                initEditor();
                editor.setComponents(wrapEmail(richHtml));
                syncEmailBody();
            } else if (chosen && chosen !== "__blank__" && NAMED_TEMPLATES[chosen]) {
                $("#ai_panel").addClass("d-none");
                $("#layout_picker").removeClass("d-none");
                initEditor();
                editor.setComponents(wrapEmail(NAMED_TEMPLATES[chosen].join('')));
                syncEmailBody();
            } else {
                $("#ai_panel").addClass("d-none");
                $("#layout_picker").removeClass("d-none");
                loadHouseTemplate();
            }
            goToStep2();
        }
    });

    $("#prev_btn").click(function () {
        $("#step2").addClass("d-none").removeClass("d-flex");
        $("#step1").addClass("d-flex").removeClass("d-none");
    });

    /* ============ SECTION 3 — Submit ============ */
    $("#step2").submit(function (event) {
        event.preventDefault();

        var button = $(this).find('button[type="submit"]'),
            spinner = '<span class="spinner"></span>';
        if (!button.hasClass("loading")) {
            button.toggleClass("loading").html(spinner);
            button.prop("disabled", true);
        }

        $("#email_body").val(getEmailBody());

        var formData = $(this).serialize();

        if (localStorage.getItem("email_type"))    formData += "&email_type=" + encodeURIComponent(localStorage.getItem("email_type"));
        if (localStorage.getItem("email_subject")) formData += "&email_subject=" + encodeURIComponent(localStorage.getItem("email_subject"));
        if (localStorage.getItem("course_opt"))    formData += "&course_opt=" + encodeURIComponent(localStorage.getItem("course_opt"));
        if (localStorage.getItem("event_opt"))     formData += "&event_opt=" + encodeURIComponent(localStorage.getItem("event_opt"));
        if (localStorage.getItem("event_id"))      formData += "&event_id=" + encodeURIComponent(localStorage.getItem("event_id"));
        if (localStorage.getItem("event_name"))    formData += "&event_name=" + encodeURIComponent(localStorage.getItem("event_name"));
        if (localStorage.getItem("email_opt"))     formData += "&email_opt=" + encodeURIComponent(localStorage.getItem("email_opt"));
        if (localStorage.getItem("temp_opt"))      formData += "&temp_opt=" + encodeURIComponent(localStorage.getItem("temp_opt"));

        $.ajax({
            url: "includes/new_system_email.inc.php",
            type: "POST",
            data: formData,
            success: function (response) {
                if (response == 1) {
                    Swal.fire({ icon: "success", title: "Success!", text: "The email has been saved successfully.",
                        position: "top-end", showConfirmButton: true, confirmButtonText: "Close" })
                    .then((result) => {
                        if (result.isConfirmed) { localStorage.clear(); window.location.href = "system_emails1"; }
                    });
                } else if (response == 2) {
                    Swal.fire({ icon: "error", title: "Failed", text: "There was an issue saving the email.",
                        position: "top-end", showConfirmButton: true, confirmButtonText: "Close" })
                    .then(() => {
                        button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Submit');
                        button.prop("disabled", false);
                    });
                } else if (response == 0) {
                    Swal.fire({ icon: "error", title: "Error", text: "An error occurred while encoding the email.",
                        position: "top-end", showConfirmButton: true, confirmButtonText: "Close" })
                    .then(() => {
                        button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Submit');
                        button.prop("disabled", false);
                    });
                }
            },
            error: function (xhr, status, error) {
                Swal.fire({ icon: "error", title: "Error", text: "There was an error with the submission.",
                    position: "top-end", showConfirmButton: true, confirmButtonText: "Close" })
                .then(() => {
                    button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Submit');
                    button.prop("disabled", false);
                });
                console.log(error);
            },
        });
    });

    /* ============ SECTION 4 — Validation helpers ============ */
    function isNotEmpty(caller) {
        caller.next(".error").remove();
        if (caller.val().trim() === "") {
            caller.css("border-color", "red");
            caller.after("<span class='error' style='position: absolute; right: 0; bottom: -3vh;'>This field is required.</span>");
            caller.focus();
            return false;
        }
        caller.css("border-color", ""); caller.next(".error").remove();
        return true;
    }

    function selNotEmpty(caller) {
        caller.next(".error").remove();
        var v = caller.find(":selected").val();
        if (v === "selected" || v === "" || !v) {
            caller.css("border-color", "red");
            caller.after("<span class='error' style='position: absolute; right: 0 !important; bottom: -2.5vh !important;'>This field is required.</span>");
            caller.focus();
            return false;
        }
        caller.css("border-color", ""); caller.next(".error").remove();
        return true;
    }

    function selNotEmptyEvent(caller) {
        caller.next(".error").remove();
        var v = caller.find(":selected").val();
        if (v === "selected" || v === "" || !v) {
            caller.css("border-color", "red");
            caller.after("<span class='error' style='position: absolute; right: 0 !important; bottom: -2.5vh !important;'>This field is required.</span>");
            caller.focus();
            return false;
        }
        caller.css("border-color", ""); caller.next(".error").remove();
        return true;
    }

    }); // jQuery ready
})();   // bootstrapPage
</script>

<?php
require_once 'footer.php';
?>