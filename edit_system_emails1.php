<?php
/**
 * edit_system_emails_standalone.php
 * --------------------------------------------------------------------------
 * A FULLY STANDALONE edit page. It does NOT render header.php / footer.php,
 * so none of the shared SB Admin asset conflicts (jQuery load-order, 404s,
 * DataTables double-init) can affect it. It loads its own clean jQuery +
 * Summernote + SweetAlert from CDN.
 *
 * It still:
 *   - reads $conn from header.php (output buffered + discarded, so no HTML leaks)
 *   - reads the email row from system_emails1
 *   - posts to includes/upd_system_email.inc.php (unchanged)
 *   - uses the AI build pipeline: email_ai_extract_fields.php -> email_fill_template.php
 */

// --- Get $conn from header.php WITHOUT rendering its HTML/assets ---
ob_start();
require_once 'header.php';
ob_end_clean();   // discard everything header.php printed; keep $conn in scope

// --- Load the email row ---
$id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';
if ($id === '') { header('Location: system_emails1'); exit; }

$result = $conn->query("SELECT * FROM system_emails1 WHERE id = '$id'") or die($conn->error);
if ($result->num_rows === 0) { header('Location: system_emails1'); exit; }
$sm = $result->fetch_assoc();

$email_subject    = $sm['subject'];
$saved_email_type = !empty($sm['email_type']) ? $sm['email_type'] : 'virtual';
$saved_course     = $sm['course_opt'];
$saved_event_id   = isset($sm['event_id']) ? $sm['event_id'] : '';
$saved_event_name = isset($sm['event_name']) ? $sm['event_name'] : '';
$saved_email      = $sm['email_opt'];

// Decode body (JSON-encoded HTML, with fallbacks)
$body = json_decode($sm['body'], true);
if (is_string($body)) {
    $body_content = $body;
} elseif (is_array($body) && isset($body['body'])) {
    $body_content = $body['body'];
} else {
    $body_content = $sm['body'];
}

// Courses + events for the dropdowns
$courses = [];
$cr = $conn->query("SELECT course_id, course FROM course ORDER BY course ASC");
if ($cr) { while ($r = $cr->fetch_assoc()) $courses[] = $r; }

$events = [];
$er = $conn->query("SELECT event_id, event_title FROM Event WHERE status = 1 ORDER BY event_title ASC");
if ($er) { while ($r = $er->fetch_assoc()) $events[] = $r; }

// Academic programmes = the Events whose location is an academic marker
// (academic#type#program_id). Their titles are the programme names; storing the
// event_id keeps the existing send-time (event_id) matching working unchanged.
$academic_events = [];
$aer = $conn->query("SELECT event_id, event_title FROM Event WHERE location LIKE 'academic#%' AND status = 1 ORDER BY event_title ASC");
if ($aer) { while ($r = $aer->fetch_assoc()) $academic_events[] = $r; }

// Corporate trainings = Events whose location is a corporate marker (corporate#<id>).
$corporate_events = [];
$cer = $conn->query("SELECT event_id, event_title FROM Event WHERE location LIKE 'corporate#%' AND status = 1 ORDER BY event_title ASC");
if ($cer) { while ($r = $cer->fetch_assoc()) $corporate_events[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit System Email — Vantage Africa</title>

    <!-- Clean, self-contained assets (no shared header/footer) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">

    <style>
        :root { --vasl-maroon:#7a1c2e; --vasl-wine:#5a1020; --vasl-gold:#c0a040; --vasl-cream:#fdf6e3; }
        body { background:#f4f1ea; }
        .bg_main { background: var(--vasl-maroon) !important; color:#fff; }
        .vasl-bar { background: var(--vasl-cream); border:1px solid #e3d3a3; }
        .input-group-text.bg_main { color:#fff; min-width:9rem; }
        .rounded-0 { border-radius:0 !important; }
        .error { color:#b00; font-size:.8rem; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="card shadow rounded-0">
        <div class="card-header bg_main rounded-0 py-3 d-flex align-items-center">
            <h6 class="m-0 fw-bold text-white text-uppercase">Edit System Email</h6>
            <div class="ms-auto">
                <button onclick="location.href='system_emails1'" class="btn btn-sm btn-light rounded-0">
                    <i class="bi bi-arrow-left-circle"></i> Back
                </button>
            </div>
        </div>
        <div class="card-body">

            <!-- STEP 1 -->
            <form class="row" id="upd_step1">
                <div class="col-12 p-1">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main">Email Type</span>
                        <select id="upd_email_type" class="form-control rounded-0" required>
                            <option value="virtual" <?php echo $saved_email_type=='virtual'?'selected':''; ?>>Virtual Courses</option>
                            <option value="international" <?php echo $saved_email_type=='international'?'selected':''; ?>>International Events</option>
                            <option value="academic" <?php echo $saved_email_type=='academic'?'selected':''; ?>>Academic Programmes</option>
                            <option value="corporate" <?php echo $saved_email_type=='corporate'?'selected':''; ?>>Corporate Trainings</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 p-1">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main">Subject</span>
                        <input type="text" id="upd_email_subject" class="form-control rounded-0" value="<?php echo htmlspecialchars($email_subject); ?>" placeholder="Enter Subject" required>
                    </div>
                </div>

                <div class="col-md-6 p-1 <?php echo $saved_email_type!='virtual'?'d-none':''; ?>" id="upd_course_container">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main">Select Course</span>
                        <select id="upd_course_opt" class="form-control rounded-0">
                            <option value="" hidden>---Select Course---</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['course']); ?>" data-id="<?php echo $c['course_id']; ?>" <?php echo ($saved_course==$c['course'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($c['course']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6 p-1 <?php echo $saved_email_type!='international'?'d-none':''; ?>" id="upd_event_container">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main">Select Event</span>
                        <select id="upd_event_opt" readonly class="form-control rounded-0">
                            <option value="" hidden>---Select Event---</option>
                            <?php foreach ($events as $e): ?>
                            <option value="<?php echo htmlspecialchars($e['event_title']); ?>" data-id="<?php echo $e['event_id']; ?>" <?php echo ($saved_event_name==$e['event_title'] || $saved_event_id==$e['event_id'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($e['event_title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6 p-1 <?php echo $saved_email_type!='academic'?'d-none':''; ?>" id="upd_academic_container">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main">Select Course</span>
                        <select id="upd_academic_opt" class="form-control rounded-0">
                            <option value="" hidden>---Select Academic Programme---</option>
                            <?php foreach ($academic_events as $ae): ?>
                            <option value="<?php echo htmlspecialchars($ae['event_title']); ?>" data-id="<?php echo $ae['event_id']; ?>" <?php echo ($saved_event_id==$ae['event_id'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($ae['event_title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6 p-1 <?php echo $saved_email_type!='corporate'?'d-none':''; ?>" id="upd_corporate_container">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main">Select Training</span>
                        <select id="upd_corporate_opt" class="form-control rounded-0">
                            <option value="" hidden>---Select Corporate Training---</option>
                            <?php foreach ($corporate_events as $ce): ?>
                            <option value="<?php echo htmlspecialchars($ce['event_title']); ?>" data-id="<?php echo $ce['event_id']; ?>" <?php echo ($saved_event_id==$ce['event_id'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($ce['event_title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6 p-1">
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main">Select Email</span>
                        <select id="upd_email_opt" class="form-control rounded-0">
                            <option value="" hidden>---Select Email Number---</option>
                            <?php for ($i=1; $i<=18; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($saved_email==$i)?'selected':''; ?>>Email <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end px-2 mt-2">
                    <button type="button" id="upd_step1_btn" class="btn rounded-0 text-white" style="background:var(--vasl-maroon);">
                        Next <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>

            <!-- STEP 2 -->
            <form class="row d-none" id="upd_step2">
                <input type="hidden" name="upd_id" id="upd_id" value="<?php echo $id; ?>">

                <div class="col-12 p-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 p-2 vasl-bar">
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

                <div class="col-lg-6 col-md-12 p-1">
                    <div class="d-flex align-items-center mb-1">
                        <span class="input-group-text rounded-0 bg_main me-2" style="width:6rem;">Edit</span>
                        <span class="small text-muted">Edit your email here</span>
                    </div>
                    <textarea id="upd_email_body" class="form-control"></textarea>
                </div>

                <div class="col-lg-6 col-md-12 p-1">
                    <div class="d-flex align-items-center mb-1">
                        <span class="input-group-text rounded-0 bg_main me-2" style="width:6rem;">Preview</span>
                        <span class="small text-muted">Live preview as you type</span>
                    </div>
                    <iframe id="upd_preview" style="width:100%; height:560px; border:1px solid #dee2e6; background:#fff;"></iframe>
                </div>

                <div class="w-100 d-flex mt-2 px-1">
                    <div class="w-50">
                        <button type="button" id="upd_prev_btn" class="btn btn-secondary rounded-0">
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                    </div>
                    <div class="w-50 d-flex justify-content-end">
                        <button type="submit" id="upd_finish" class="btn rounded-0 text-white" style="background:var(--vasl-maroon);">
                            <i class="bi bi-check2"></i> Update
                        </button>
                    </div>
                </div>
            </form>

            <!-- Saved email HTML holder (hidden; JS reads from it) -->
            <div id="upd_preview_temps" style="display:none;"><?php echo $body_content; ?></div>
        </div>
    </div>
</div>

<!-- Own clean assets — loaded in correct order, no conflicts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {
    var savedBody = $("#upd_preview_temps").html();

    function renderPreview(html) {
        var f = document.getElementById("upd_preview");
        var doc = f.contentDocument || f.contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;">' + (html || "") + "</body></html>");
        doc.close();
    }

    // Init Summernote
    $("#upd_email_body").summernote({
        height: 420,
        prettifyHtml: false,
        callbacks: {
            onChange: function (c) { renderPreview(c); },
            onKeyup:  function () { renderPreview($("#upd_email_body").summernote("code")); },
            onPaste:  function () { setTimeout(function(){ renderPreview($("#upd_email_body").summernote("code")); }, 30); }
        }
    });

    // Type toggle shows the right dropdown
    $("#upd_email_type").on("change", function () {
        var t = $(this).val();
        $("#upd_course_container").toggleClass("d-none",   t !== "virtual");
        $("#upd_event_container").toggleClass("d-none",    t !== "international");
        $("#upd_academic_container").toggleClass("d-none", t !== "academic");
        $("#upd_corporate_container").toggleClass("d-none", t !== "corporate");
    });

    // Simple required-field check
    function need(el) {
        var v = (el.val() || "").toString().trim();
        el.next(".error").remove();
        if (v === "") { el.after("<span class='error'>Required.</span>"); return false; }
        return true;
    }

    // STEP 1 -> STEP 2
    $("#upd_step1_btn").on("click", function () {
        var type = $("#upd_email_type").val();
        var ok = need($("#upd_email_subject")) & need($("#upd_email_opt"));
        if (type === "virtual")        ok = ok & need($("#upd_course_opt"));
        else if (type === "academic")  ok = ok & need($("#upd_academic_opt"));
        else if (type === "corporate") ok = ok & need($("#upd_corporate_opt"));
        else                            ok = ok & need($("#upd_event_opt"));
        if (!ok) return;

        $("#upd_step1").addClass("d-none");
        $("#upd_step2").removeClass("d-none");

        var emailNo = $("#upd_email_opt").val();

        if ($("#upd_ai_fit_toggle").is(":checked") && savedBody.trim()) {
            buildFromTemplate(savedBody, type, emailNo);
        } else {
            place(savedBody);
            $("#upd_status").css("color", "#666").text("Loaded as-is. Edit on the left.");
        }
    });

    function place(html) {
        $("#upd_email_body").summernote("code", html);
        renderPreview(html);
    }

    // AI build: classify purpose -> extract fields -> PHP fills the matching
    // purpose template (footer safe). One call to email_ai_build.php.
    function buildFromTemplate(source, type, emailNo) {
        $("#upd_status").css("color", "#666").text("Reading your email and choosing the right template…");
        $.ajax({
            url: "email_ai_build.php", type: "POST", dataType: "json",
            data: { html: source },
            success: function (res) {
                if (res && res.ok && res.html) {
                    var label = res.purpose ? res.purpose.replace("_", " ") : "template";
                    $("#upd_status").css("color", "#1a7a3c").text("Built as a " + label + " email — design & footer intact. Edit on the left.");
                    place(res.html);
                } else {
                    $("#upd_status").css("color", "#b00").text((res && res.error) ? res.error : "Could not build; loaded as-is.");
                    place(source);
                }
            },
            error: function () { $("#upd_status").css("color", "#b00").text("Build failed; loaded as-is."); place(source); }
        });
    }

    // Load As-Is button
    $("#upd_plain_btn").on("click", function () {
        place(savedBody);
        $("#upd_status").css("color", "#666").text("Loaded as-is.");
    });

    // Back to step 1
    $("#upd_prev_btn").on("click", function () {
        $("#upd_step2").addClass("d-none");
        $("#upd_step1").removeClass("d-none");
    });

    // Submit -> existing backend
    $("#upd_step2").on("submit", function (e) {
        e.preventDefault();
        var btn = $("#upd_finish");
        btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span> Saving…');

        var data = {
            upd_id:            $("#upd_id").val(),
            upd_email_type:    $("#upd_email_type").val(),
            upd_email_subject: $("#upd_email_subject").val(),
            upd_email_opt:     $("#upd_email_opt").val(),
            upd_email_body:    $("#upd_email_body").summernote("code")
        };
        if (data.upd_email_type === "virtual") {
            data.upd_course_opt = $("#upd_course_opt").val();
            data.upd_event_id   = $("#upd_course_opt option:selected").data("id") || "";
        } else if (data.upd_email_type === "academic") {
            data.upd_course_opt = $("#upd_academic_opt").val();          // programme name
            data.upd_event_name = $("#upd_academic_opt").val();
            data.upd_event_id   = $("#upd_academic_opt option:selected").data("id") || "";
        } else if (data.upd_email_type === "corporate") {
            data.upd_course_opt = $("#upd_corporate_opt").val();          // training name
            data.upd_event_name = $("#upd_corporate_opt").val();
            data.upd_event_id   = $("#upd_corporate_opt option:selected").data("id") || "";
        } else {
            data.upd_event_opt  = $("#upd_event_opt").val();
            data.upd_event_name = $("#upd_event_opt").val();
            data.upd_event_id   = $("#upd_event_opt option:selected").data("id") || "";
        }

        $.ajax({
            url: "includes/upd_system_email.inc.php", type: "POST", data: data,
            success: function (resp) {
                if (resp == 1) {
                    Swal.fire({ icon:"success", title:"Saved", text:"The email was updated." })
                        .then(function(){ window.location.href = "system_emails1"; });
                } else {
                    Swal.fire({ icon:"error", title:"Failed", text:"There was an issue updating the email (code " + resp + ")." });
                    btn.prop("disabled", false).html('<i class="bi bi-check2"></i> Update');
                }
            },
            error: function () {
                Swal.fire({ icon:"error", title:"Error", text:"Submission failed." });
                btn.prop("disabled", false).html('<i class="bi bi-check2"></i> Update');
            }
        });
    });
});
</script>
</body>
</html>
