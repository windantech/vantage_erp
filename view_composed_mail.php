<?php
require_once 'header.php';

// ---- Fetch the saved email safely ----
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$subject = '';
$body = '';
$attachment = '';
$found = false;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT subject, body, attachment FROM marketing_email_messages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $subject    = $row['subject'];
        $body       = $row['body'];
        $attachment = $row['attachment'];
        $found      = true;

        // --- Repair legacy double-escaped bodies ---
        // Older saves ran the HTML through mysqli_escape_string AND a prepared
        // statement, baking in literal \" \' and \r\n. If we detect that pattern,
        // unescape it so the email renders correctly.
        if (strpos($body, '\\"') !== false || strpos($body, "\\'") !== false || strpos($body, '\\r\\n') !== false) {
            $body = str_replace(array('\\r\\n', '\\n', '\\r', '\\t'), array("\n", "\n", "\r", "\t"), $body);
            $body = stripslashes($body);
        }
    }
    $stmt->close();
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">View Composed Mail</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <a href="bulk_mail.php" class="btn border-0 p-0 text-white">
                                <i class="bi bi-plus-lg"></i> Add
                            </a>
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-3 text-white">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (!$found): ?>
                        <div class="alert alert-warning rounded-0">No email found for that ID.</div>
                    <?php else: ?>
                    <div class="row">

                        <!-- ===== LEFT: live preview of the saved email (rendered as-is) ===== -->
                        <div class="col-md-6">
                            <div class="mb-2">
                                <span class="fw-bold">Subject:</span>
                                <?php echo htmlspecialchars($subject); ?>
                            </div>
                            <!-- iframe isolates the email's own styles from the admin page CSS -->
                            <iframe id="emailPreview"
                                    style="width:100%; height:calc(100vh - 28vh); border:1px solid #dee2e6; background:#f2f2f2;"></iframe>
                        </div>

                        <!-- ===== RIGHT: schedule / send form (unchanged) ===== -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg_main text-white">
                                    Schedule emails to be sent by Cron Job
                                </div>
                                <div class="card-body">
                                    <form action="selecting_data.php" enctype="multipart/form-data" method="POST">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <input type="hidden" id="id" name="id" value="<?php echo $id; ?>" class="form-control" required/>
                                                <div class="form-group">
                                                    <label><b>Select target group</b></label>
                                                    <select name="status" class="form-control" onchange="check_todo(this.value)">
                                                        <option value="">Select</option>
                                                        <option value="handbook">Lead Form</option>
                                                        <option value="program">Contact Us</option>
                                                        <option value="application">Get In Touch</option>
                                                        <option value="enquiries">Enquiries (CRM)</option>
                                                        <option value="raw_data">Imported data</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Lead Form date range -->
                                            <div class="col-md-4" style="display:none;" id="handbook">
                                                <div class="form-group">
                                                    <label>Start date</label>
                                                    <input type="date" id="start_date" name="start_date" class="form-control"/>
                                                </div>
                                                <div class="form-group">
                                                    <label>End date</label>
                                                    <input type="date" id="end_date" name="end_date" class="form-control"/>
                                                </div>
                                            </div>

                                            <!-- Enquiries (CRM) filters -->
                                            <div class="col-md-8" style="display:none;" id="enquiries_section">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label><b>Department</b></label>
                                                            <select name="enquiry_department" class="form-control">
                                                                <option value="all">All Departments</option>
                                                                <option value="virtual">Virtual Courses</option>
                                                                <option value="international">International Events</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>Start date</label>
                                                            <input type="date" name="enquiry_start_date" class="form-control"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label>End date</label>
                                                            <input type="date" name="enquiry_end_date" class="form-control"/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewEnquiryCount()">
                                                        <i class="bi bi-eye"></i> Preview Count
                                                    </button>
                                                    <span id="enquiry_count_display" class="ms-2 text-muted"></span>
                                                </div>
                                            </div>

                                            <!-- Imported data - bulk select with checkboxes -->
                                            <div class="col-md-8" style="display:none;" id="anyother">
                                                <div class="form-group">
                                                    <label><b>Select data set(s)</b></label>
                                                    <div class="mb-2">
                                                        <label class="form-check-label me-3">
                                                            <input type="checkbox" id="select_all_datasets" onclick="toggleAllDatasets(this)">
                                                            <strong>Select All</strong>
                                                        </label>
                                                    </div>
                                                    <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                                        <?php
                                                        $check = mysqli_query($conn, "SELECT DISTINCT(`data_id`) as data_id, comment, COUNT(*) as total FROM `marketing_data_email_one` WHERE email LIKE '%@%' GROUP BY data_id, comment ORDER BY data_id DESC");
                                                        if ($check && mysqli_num_rows($check) > 0) {
                                                            while ($row_member = mysqli_fetch_array($check)) {
                                                                ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input dataset-checkbox" type="checkbox" name="upload_dates[]" value="<?php echo htmlspecialchars($row_member['comment']); ?>" id="dataset_<?php echo $row_member['data_id']; ?>">
                                                                    <label class="form-check-label" for="dataset_<?php echo $row_member['data_id']; ?>">
                                                                        <?php echo ucfirst($row_member['comment']); ?>
                                                                        <span class="badge bg-secondary"><?php echo $row_member['total']; ?> emails</span>
                                                                    </label>
                                                                </div>
                                                                <?php
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="btn_submit" style="display:none;" class="form-group mt-4">
                                                <button id="submit_btn" type="submit" class="btn btn-primary">Send Mail</button>
                                            </div>
                                            <div>
                                                <?php
                                                if ($attachment) {
                                                    echo 'Available attachment <a target="_blank" href="attachments/' . htmlspecialchars($attachment) . '">' . htmlspecialchars($attachment) . '</a>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($found): ?>
<script>
// ---- Render the saved email body into the isolated iframe, exactly as stored ----
(function () {
    var emailHtml = <?php echo json_encode($body); ?>;
    var frame = document.getElementById('emailPreview');
    var doc = frame.contentWindow.document;
    doc.open();
    doc.write(emailHtml);
    doc.close();
})();
</script>
<?php endif; ?>

<script>
function check_todo(val) {
    document.getElementById('handbook').style.display = 'none';
    document.getElementById('anyother').style.display = 'none';
    document.getElementById('enquiries_section').style.display = 'none';
    document.getElementById('btn_submit').style.display = 'none';
    document.getElementById('enquiry_count_display').innerHTML = '';

    if (val === '') return;

    if (val === 'handbook') {
        document.getElementById('handbook').style.display = 'block';
    } else if (val === 'enquiries') {
        document.getElementById('enquiries_section').style.display = 'block';
    } else if (val === 'raw_data') {
        document.getElementById('anyother').style.display = 'block';
    }
    document.getElementById('btn_submit').style.display = 'block';
}

function toggleAllDatasets(source) {
    document.querySelectorAll('.dataset-checkbox').forEach(function (cb) {
        cb.checked = source.checked;
    });
}

function previewEnquiryCount() {
    var dept = document.querySelector('[name="enquiry_department"]').value;
    var startDate = document.querySelector('[name="enquiry_start_date"]').value;
    var endDate = document.querySelector('[name="enquiry_end_date"]').value;
    var display = document.getElementById('enquiry_count_display');

    if (!startDate || !endDate) {
        display.innerHTML = '<span class="text-danger">Please select both dates</span>';
        return;
    }

    display.innerHTML = '<i class="bi bi-hourglass-split"></i> Counting...';

    fetch('ajax/count_enquiries.php?dept=' + dept + '&start=' + startDate + '&end=' + endDate)
        .then(function (response) { return response.json(); })
        .then(function (data) {
            display.innerHTML = '<span class="text-success fw-bold">' + data.count + ' enquiries found</span>';
            if (data.virtual !== undefined) {
                display.innerHTML += ' <small class="text-muted">(Virtual: ' + data.virtual + ', International: ' + data.international + ')</small>';
            }
        })
        .catch(function () {
            display.innerHTML = '<span class="text-danger">Error counting</span>';
        });
}
</script>

<?php require_once 'footer.php'; ?>