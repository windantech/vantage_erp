<?php
require_once 'header.php';

/* ============================================================
   DUPLICATE HANDLER  (runs before any output)
   ?duplicate=ID  -> clone the row, then open the clone for editing.
   ============================================================ */
if (isset($_GET['duplicate'])) {
    $dupId = (int) $_GET['duplicate'];
    if ($dupId > 0) {
        $q = $conn->prepare("SELECT template, subject, body, attachment FROM marketing_email_messages WHERE id = ?");
        $q->bind_param("i", $dupId);
        $q->execute();
        $r = $q->get_result();
        if ($src = $r->fetch_assoc()) {
            $newSubject = $src['subject'] . ' (Copy)';
            $ins = $conn->prepare("INSERT INTO marketing_email_messages (template, subject, body, attachment) VALUES (?, ?, ?, ?)");
            $ins->bind_param("ssss", $src['template'], $newSubject, $src['body'], $src['attachment']);
            $ins->execute();
            $newId = $ins->insert_id;
            $ins->close();
            $q->close();
            // Open the new copy in edit mode
            echo "<script>window.location.href='bulk_mail.php?id=" . $newId . "';</script>";
            exit;
        }
        $q->close();
    }
    echo "<script>window.alert('Could not duplicate — record not found.');window.location.href='send_mail';</script>";
    exit;
}

/* ============================================================
   EDIT LOAD  (runs before output)
   ?id=ID  -> load an existing email into the builder for editing.
   ============================================================ */
$edit_id        = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit_subject   = '';
$edit_body      = '';
$is_edit        = false;

if ($edit_id > 0) {
    $eq = $conn->prepare("SELECT subject, body FROM marketing_email_messages WHERE id = ?");
    $eq->bind_param("i", $edit_id);
    $eq->execute();
    $er = $eq->get_result();
    if ($erow = $er->fetch_assoc()) {
        $is_edit      = true;
        $edit_subject = $erow['subject'];
        $edit_body    = $erow['body'];

        // Repair legacy double-escaped bodies so they load clean in the editor
        if (strpos($edit_body, '\\"') !== false || strpos($edit_body, "\\'") !== false || strpos($edit_body, '\\r\\n') !== false) {
            $edit_body = str_replace(array('\\r\\n', '\\n', '\\r', '\\t'), array("\n", "\n", "\r", "\t"), $edit_body);
            $edit_body = stripslashes($edit_body);
        }
    }
    $eq->close();
    if (!$is_edit) { $edit_id = 0; }
}

/* ============================================================
   SAVE HANDLER  (runs before any HTML output)
   INSERT for new emails, UPDATE when editing an existing one.
   Columns: template, subject, body, attachment
   ============================================================ */
if (isset($_POST['save_mail'])) {

    $save_id = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    $subject = trim($_POST['subject'] ?? '');
    // GrapesJS exported HTML lands in body (hidden textarea synced on submit)
    // Do NOT escape here — the prepared statement below binds parameters safely.
    $content = $_POST['body'] ?? '';
    // Which named template was used (or "AI" / "N/A")
    $template = trim($_POST['template'] ?? 'N/A');
    if ($template === '' || $template === 'select') { $template = 'N/A'; }

    $attachmentName = '';

    if (isset($_FILES['attachment_file']) && !empty($_FILES['attachment_file']['name'])) {
        $originalFilename = basename($_FILES['attachment_file']['name']);
        $upload_dir = "attachments/";
        if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }

        $uploadPath = $upload_dir . $originalFilename;
        // If the same file already exists, just reuse the name (don't overwrite)
        if (!file_exists($uploadPath)) {
            if (!move_uploaded_file($_FILES['attachment_file']['tmp_name'], $uploadPath)) {
                echo "<script>window.alert('File upload failed!');</script>";
            }
        }
        $attachmentName = $originalFilename;
    }

    if ($save_id > 0) {
        // ----- UPDATE existing email -----
        // Only overwrite the attachment if a new file was uploaded.
        if ($attachmentName !== '') {
            $stmt = $conn->prepare(
                "UPDATE marketing_email_messages SET template = ?, subject = ?, body = ?, attachment = ? WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $template, $subject, $content, $attachmentName, $save_id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE marketing_email_messages SET template = ?, subject = ?, body = ? WHERE id = ?"
            );
            $stmt->bind_param("sssi", $template, $subject, $content, $save_id);
        }
        $okMsg = 'Email updated successfully';
    } else {
        // ----- INSERT new email -----
        $stmt = $conn->prepare(
            "INSERT INTO marketing_email_messages (template, subject, body, attachment) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $template, $subject, $content, $attachmentName);
        $okMsg = 'Message saved Successfully';
    }

    if ($stmt->execute()) {
        echo "<script>
                window.alert('" . $okMsg . "');
                window.location.href='send_mail';
              </script>";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!-- GrapesJS (drag-and-drop email builder) + newsletter preset -->
<link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
<script src="https://unpkg.com/grapesjs"></script>
<script src="https://unpkg.com/grapesjs-preset-newsletter"></script>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4 rounded-0">
                        <div class="card-header bg_main rounded-0 py-3">
                            <div class="w-100 d-flex align-items-center">
                                <div class="w-50">
                                    <h6 class="m-0 font-weight-bold text-white text-uppercase">Composed Mail</h6>
                                </div>
                                <div class="w-50 d-flex justify-content-end">
                                    <a href="send_mail" class="btn border-0 p-0 text-white">
                                        <i class="fas fa-eye"></i> View Composed Emails
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="composeForm">
                                <!-- Hidden field actually saved: holds the GrapesJS exported HTML -->
                                <textarea name="body" id="email_body" style="display:none;"></textarea>
                                <!-- Which named template (or AI) was used -->
                                <input type="hidden" name="template" id="template_used" value="N/A">
                                <!-- Set when editing an existing email; blank for new -->
                                <input type="hidden" name="edit_id" id="edit_id" value="<?php echo $edit_id; ?>">

                                <?php if ($is_edit): ?>
                                <div class="alert alert-info rounded-0 py-2">
                                    <i class="fas fa-pencil-alt"></i> Editing existing email (ID <?php echo $edit_id; ?>).
                                    Saving will update it. <a href="bulk_mail.php" class="ms-2">Start a new one instead</a>.
                                </div>
                                <?php endif; ?>

                                <!-- ===== Top controls: Subject + Start method ===== -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="fw-bold">Mail Subject</label>
                                            <input type="text" id="subject" name="subject" class="form-control rounded-0"
                                                   value="<?php echo htmlspecialchars($edit_subject); ?>"
                                                   placeholder="View Our New Exciting Offers" required/>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="fw-bold">Start From</label>
                                            <select id="startMethod" class="form-control rounded-0">
                                                <option value="template">Predefined Template</option>
                                                <option value="ai">Generate with AI</option>
                                                <option value="blank">Blank Canvas</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- ===== Predefined named templates ===== -->
                                <div id="templatePanel" class="mb-3">
                                    <label class="fw-bold d-block mb-2">Choose a starting template</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-0 tpl-btn" data-tpl="event">Event Promo</button>
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-0 tpl-btn" data-tpl="course">Course Follow-up</button>
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-0 tpl-btn" data-tpl="offer">Special Offer</button>
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-0 tpl-btn" data-tpl="announce">Simple Announcement</button>
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-0 tpl-btn" data-tpl="newsletter">Newsletter</button>
                                    </div>
                                </div>

                                <!-- ===== AI generation panel ===== -->
                                <div id="aiPanel" class="mb-3" style="display:none;">
                                    <div class="card rounded-0 border">
                                        <div class="card-header bg-light fw-bold">
                                            <i class="fas fa-robot"></i> Describe the email — AI will draft it
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Message / instructions</label>
                                                <textarea id="ai_message" class="form-control rounded-0" rows="4"
                                                          placeholder="e.g. Invite past delegates to our June leadership summit in Nairobi. Warm, motivating tone. Mention early-bird pricing."></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label>Design instructions (optional)</label>
                                                <textarea id="ai_design" class="form-control rounded-0" rows="2"
                                                          placeholder="e.g. Make the discount part a fancy badge. Use a bold gold call-to-action button. Add a colored banner at the top."></textarea>
                                                <small class="text-muted">Describe how it should <em>look</em> — layout, badges, buttons, banners, emphasis.</small>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="form-group">
                                                        <label>Links to include (optional, one per line)</label>
                                                        <textarea id="ai_links" class="form-control rounded-0" rows="2"
                                                                  placeholder="https://vantageafricaleaders.com/register"></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Purpose (optional)</label>
                                                        <input type="text" id="ai_purpose" class="form-control rounded-0"
                                                               placeholder="Register Today">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" id="aiGenerateBtn" class="btn bg_main text-white rounded-0">
                                                <i class="fas fa-magic"></i> Generate Draft
                                            </button>
                                            <span id="aiStatus" class="ms-2 text-muted"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- ===== Drag-and-drop builder ===== -->
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <label class="fw-bold d-block mb-2">
                                            <i class="fas fa-pencil-alt"></i> Compose Mail — drag blocks, click to edit
                                        </label>
                                        <div id="gjs" style="border:1px solid #dee2e6; min-height:520px;"></div>

                                        <!-- ===== AI Refine panel (appears once an email exists) ===== -->
                                        <div id="refinePanel" class="mt-3" style="display:none;">
                                            <div class="card rounded-0 border">
                                                <div class="card-header bg-light fw-bold">
                                                    <i class="fas fa-wand-magic-sparkles"></i> Ask AI to adjust this email
                                                </div>
                                                <div class="card-body">
                                                    <div class="d-flex gap-2 align-items-start">
                                                        <textarea id="refine_instruction" class="form-control rounded-0" rows="2"
                                                                  placeholder="e.g. Make the discount a bigger gold badge, add a short testimonial, and change the headline to mention June."></textarea>
                                                        <button type="button" id="refineBtn" class="btn bg_main text-white rounded-0" style="white-space:nowrap;">
                                                            <i class="fas fa-arrows-rotate"></i> Refine
                                                        </button>
                                                    </div>
                                                    <div class="d-flex align-items-center mt-2">
                                                        <span id="refineStatus" class="text-muted small"></span>
                                                        <button type="button" id="undoRefineBtn" class="btn btn-sm btn-outline-secondary rounded-0 ms-auto" style="display:none;">
                                                            <i class="fas fa-rotate-left"></i> Undo last change
                                                        </button>
                                                    </div>
                                                    <small class="text-muted d-block mt-1">The AI keeps everything else and only applies what you ask. You can keep refining.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ===== Attachment + Save ===== -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Attach Files</label>
                                            <input class="form-control rounded-0" name="attachment_file" id="file-upload" type="file"/>
                                            <div id="file-name" class="small text-muted mt-1"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                                        <button class="btn btn-success rounded-0" type="submit" name="save_mail" id="saveBtn">
                                            <i class="fas fa-save"></i> Save Mail
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
/* ============================================================
   VASL bulk mail builder
   - GrapesJS newsletter preset (drag & drop, email-safe export)
   - Predefined named templates (not the system email 1-17)
   - AI generation via email_ai_generate.php
   ============================================================ */
(function () {
    // ---- VASL brand palette ----
    var MAROON = '#3C0F0F';
    var RUST   = '#A54B2D';
    var GOLD   = '#D2A53C';

    // ---------- Predefined named templates (email-safe inline HTML) ----------
    function band(title, sub) {
        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;">' +
            '<tr><td style="background:' + MAROON + ';padding:28px 24px;text-align:center;">' +
            '<h1 style="margin:0;color:#ffffff;font-family:Arial,sans-serif;font-size:24px;">' + title + '</h1>' +
            (sub ? '<p style="margin:8px 0 0;color:' + GOLD + ';font-family:Arial,sans-serif;font-size:14px;">' + sub + '</p>' : '') +
            '</td></tr></table>';
    }
    function btn(label) {
        return '<table cellpadding="0" cellspacing="0" border="0" style="margin:18px auto;"><tr>' +
            '<td style="background:' + RUST + ';border-radius:4px;">' +
            '<a href="#" style="display:inline-block;padding:12px 28px;color:#ffffff;font-family:Arial,sans-serif;' +
            'font-size:15px;text-decoration:none;font-weight:bold;">' + label + '</a></td></tr></table>';
    }
    function body(html) {
        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;background:#ffffff;">' +
            '<tr><td style="padding:28px 24px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#444444;">' +
            html + '</td></tr></table>';
    }
    function footer() {
        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;">' +
            '<tr><td style="background:#f2f2f2;padding:18px 24px;text-align:center;font-family:Arial,sans-serif;' +
            'font-size:12px;color:#888888;">&copy; <?php echo date("Y"); ?> Vantage Africa School Of Leadership. ' +
            'All rights reserved. &nbsp;|&nbsp; <a href="#" style="color:#888888;">Unsubscribe</a></td></tr></table>';
    }

    var TEMPLATES = {
        event: band('You\'re Invited', 'An Exclusive Leadership Event') +
            body('<p>Dear Friend,</p><p>Join us for a transformative leadership experience designed to ' +
                 'sharpen your vision and expand your network. Early-bird places are limited.</p>' + btn('Register Today')) +
            footer(),
        course: band('Continue Your Journey', 'A Course Built For You') +
            body('<p>Hello,</p><p>Thank you for your interest in our leadership programs. Here is a quick ' +
                 'follow-up with everything you need to take the next step.</p>' + btn('View Course Details')) +
            footer(),
        offer: band('Special Offer', 'For a Limited Time') +
            body('<p>Hi there,</p><p>We have an exciting offer just for you. Don\'t miss this opportunity to ' +
                 'invest in your growth at a special rate.</p>' + btn('Claim Your Offer')) +
            footer(),
        announce: band('Announcement') +
            body('<p>Hello,</p><p>We wanted to share an important update with you. Read on for the details.</p>') +
            footer(),
        newsletter: band('VASL Newsletter', 'Insights & Updates') +
            body('<h2 style="color:' + MAROON + ';font-size:18px;margin:0 0 8px;">This Month at VASL</h2>' +
                 '<p>A round-up of our latest stories, events, and leadership insights.</p>' +
                 '<hr style="border:none;border-top:1px solid #eee;margin:18px 0;">' +
                 '<h3 style="color:' + RUST + ';font-size:16px;margin:0 0 6px;">Featured</h3>' +
                 '<p>Add your highlight here.</p>' + btn('Read More')) +
            footer()
    };

    // ---------- Init GrapesJS ----------
    var editor = grapesjs.init({
        container: '#gjs',
        height: '560px',
        fromElement: false,
        storageManager: false,
        plugins: ['grapesjs-preset-newsletter'],
        pluginsOpts: {
            'grapesjs-preset-newsletter': {
                modalTitleImport: 'Import template',
                cellStyle: { 'font-size': '14px', 'font-family': 'Arial, sans-serif' }
            }
        }
    });

    function loadTemplate(key) {
        if (TEMPLATES[key]) {
            editor.setComponents(TEMPLATES[key]);
            document.getElementById('template_used').value = key;
            var rp = document.getElementById('refinePanel');
            if (rp) { rp.style.display = ''; }
        }
    }

    // ---------- Initial canvas content ----------
    <?php if ($is_edit): ?>
    // Editing an existing email: load its saved HTML into the builder.
    var existingEmailHtml = <?php echo json_encode($edit_body); ?>;
    editor.setComponents(existingEmailHtml);
    document.getElementById('template_used').value = 'Edited';
    <?php else: ?>
    // New email: start from the default template.
    loadTemplate('event');
    <?php endif; ?>

    // ---------- Start-method switching ----------
    var startMethod   = document.getElementById('startMethod');
    var templatePanel = document.getElementById('templatePanel');
    var aiPanel       = document.getElementById('aiPanel');

    function refreshPanels() {
        var v = startMethod.value;
        templatePanel.style.display = (v === 'template') ? '' : 'none';
        aiPanel.style.display       = (v === 'ai')       ? '' : 'none';
        if (v === 'blank') {
            editor.setComponents('');
            document.getElementById('template_used').value = 'Blank';
        }
    }
    startMethod.addEventListener('change', refreshPanels);

    // Template buttons
    document.querySelectorAll('.tpl-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.tpl-btn').forEach(function (x) { x.classList.remove('active', 'bg_main', 'text-white'); });
            this.classList.add('active', 'bg_main', 'text-white');
            loadTemplate(this.getAttribute('data-tpl'));
        });
    });

    // ---------- AI generation ----------
    var aiBtn    = document.getElementById('aiGenerateBtn');
    var aiStatus = document.getElementById('aiStatus');

    aiBtn.addEventListener('click', function () {
        var message = document.getElementById('ai_message').value.trim();
        var links   = document.getElementById('ai_links').value.trim();
        var purpose = document.getElementById('ai_purpose').value.trim();
        var design  = document.getElementById('ai_design').value.trim();

        if (!message) {
            aiStatus.textContent = 'Please describe the email first.';
            aiStatus.className = 'ms-2 text-danger';
            return;
        }

        aiBtn.disabled = true;
        aiStatus.textContent = 'Generating…';
        aiStatus.className = 'ms-2 text-muted';

        var fd = new FormData();
        fd.append('message', message);
        fd.append('links', links);
        fd.append('purpose', purpose);
        fd.append('design', design);

        fetch('email_ai_generate.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok && data.html) {
                    editor.setComponents(data.html);
                    document.getElementById('template_used').value = 'AI';
                    aiStatus.textContent = 'Draft loaded — edit it below.';
                    aiStatus.className = 'ms-2 text-success';
                    showRefine();
                } else {
                    aiStatus.textContent = (data && data.error) ? data.error : 'Generation failed.';
                    aiStatus.className = 'ms-2 text-danger';
                }
            })
            .catch(function () {
                aiStatus.textContent = 'Network error. Try again.';
                aiStatus.className = 'ms-2 text-danger';
            })
            .finally(function () { aiBtn.disabled = false; });
    });

    // ---------- AI Refine (adjust the existing email) ----------
    var refinePanel = document.getElementById('refinePanel');
    var refineBtn   = document.getElementById('refineBtn');
    var refineStat  = document.getElementById('refineStatus');
    var undoBtn     = document.getElementById('undoRefineBtn');
    var preRefineHtml = null; // snapshot for one-step undo

    function showRefine() { refinePanel.style.display = ''; }

    refineBtn.addEventListener('click', function () {
        var instruction = document.getElementById('refine_instruction').value.trim();
        if (!instruction) {
            refineStat.textContent = 'Describe the adjustment first.';
            refineStat.className = 'text-danger small';
            return;
        }

        // Snapshot the live builder HTML so we can undo, and send it for editing
        var currentHtml = editor.runCommand('gjs-get-inlined-html') || editor.getHtml();
        if (!currentHtml || !currentHtml.trim()) {
            refineStat.textContent = 'There is no email to refine yet.';
            refineStat.className = 'text-danger small';
            return;
        }
        preRefineHtml = currentHtml;

        refineBtn.disabled = true;
        refineStat.textContent = 'Applying your adjustment…';
        refineStat.className = 'text-muted small';

        var fd = new FormData();
        fd.append('mode', 'refine');
        fd.append('instruction', instruction);
        fd.append('current_html', currentHtml);

        fetch('email_ai_generate.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok && data.html) {
                    editor.setComponents(data.html);
                    document.getElementById('template_used').value = 'AI';
                    refineStat.textContent = 'Updated. Refine again or edit directly.';
                    refineStat.className = 'text-success small';
                    undoBtn.style.display = '';
                    document.getElementById('refine_instruction').value = '';
                } else {
                    refineStat.textContent = (data && data.error) ? data.error : 'Adjustment failed.';
                    refineStat.className = 'text-danger small';
                }
            })
            .catch(function () {
                refineStat.textContent = 'Network error. Try again.';
                refineStat.className = 'text-danger small';
            })
            .finally(function () { refineBtn.disabled = false; });
    });

    undoBtn.addEventListener('click', function () {
        if (preRefineHtml !== null) {
            editor.setComponents(preRefineHtml);
            preRefineHtml = null;
            undoBtn.style.display = 'none';
            refineStat.textContent = 'Reverted the last AI change.';
            refineStat.className = 'text-muted small';
        }
    });

    // ---------- On submit: export GrapesJS HTML into the hidden body field ----------
    document.getElementById('composeForm').addEventListener('submit', function (e) {
        var html = editor.runCommand('gjs-get-inlined-html') || editor.getHtml();
        document.getElementById('email_body').value = html;
        if (!html || !html.trim()) {
            e.preventDefault();
            alert('The email body is empty. Add some content before saving.');
        }
    });

    // ---------- File name display ----------
    var fileUpload = document.getElementById('file-upload');
    var fileName   = document.getElementById('file-name');
    fileUpload.addEventListener('change', function () {
        fileName.textContent = this.value.replace(/^.*[\\\/]/, '');
    });

    refreshPanels();

    <?php if ($is_edit): ?>
    // Editing: make the AI Refine panel available immediately.
    showRefine();
    <?php endif; ?>
})();
</script>

<?php require_once 'footer.php'; ?>