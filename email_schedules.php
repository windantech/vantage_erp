<?php
session_start();
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <?php
        // Fetch active courses
        $courses = [];
        $course_result = mysqli_query($conn, "SELECT course_id, course FROM course WHERE status = 1 ORDER BY course ASC");
        if ($course_result) {
            while ($row = mysqli_fetch_assoc($course_result)) {
                $courses[] = $row;
            }
        }
        
        // Fetch active events
        $events = [];
        $event_result = mysqli_query($conn, "SELECT event_id, event_title FROM Event WHERE status = 1 ORDER BY event_title ASC");
        if ($event_result) {
            while ($row = mysqli_fetch_assoc($event_result)) {
                $events[] = $row;
            }
        }
        
        // Handle form submission
        $success_message = '';
        $error_message = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_email'])) {
            $email_type = mysqli_real_escape_string($conn, $_POST['email_type']);
            $target_id = intval($_POST['target_id']);
            $target_name = mysqli_real_escape_string($conn, $_POST['target_name']);
            $email_template_id = intval($_POST['email_template_id']);
            $payment_filter = mysqli_real_escape_string($conn, $_POST['payment_filter']);
            $scheduled_date = mysqli_real_escape_string($conn, $_POST['scheduled_date']);
            $scheduled_time = mysqli_real_escape_string($conn, $_POST['scheduled_time']);
            $created_by = intval($_SESSION['login_id'] ?? 1);
            
            // Get email_opt from selected template
            $email_opt_result = mysqli_query($conn, "SELECT email_opt, subject FROM system_emails1 WHERE id = $email_template_id LIMIT 1");
            $email_number = 0;
            $email_subject = '';
            if ($email_opt_result && mysqli_num_rows($email_opt_result) > 0) {
                $email_opt_row = mysqli_fetch_assoc($email_opt_result);
                $email_number = $email_opt_row['email_opt'];
                $email_subject = $email_opt_row['subject'];
            }
            
            // Count recipients based on type and filter
            if ($email_type == 'virtual') {
            if ($payment_filter == 'all') {
    $count_query = "SELECT COUNT(*) as cnt FROM register WHERE program = '$target_id' AND email IS NOT NULL AND email != ''";
} elseif ($payment_filter == 'paid') {
    $count_query = "SELECT COUNT(*) as cnt FROM register r 
                    INNER JOIN dpo_payment p ON r.entry_id = p.app_id 
                    WHERE r.program = '$target_id' AND r.email IS NOT NULL AND r.email != '' 
                    AND p.TransactionAmount > 0";
} else {
    $count_query = "SELECT COUNT(*) as cnt FROM register r 
                    LEFT JOIN dpo_payment p ON r.entry_id = p.app_id 
                    WHERE r.program = '$target_id' AND r.email IS NOT NULL AND r.email != '' 
                    AND (p.id IS NULL OR p.TransactionAmount IS NULL OR p.TransactionAmount = 0)";
}
            } else {
                $count_query = "SELECT COUNT(*) as cnt FROM ticket_congress WHERE event_id = $target_id AND email IS NOT NULL AND email != ''";
                if ($payment_filter == 'paid') {
                    $count_query .= " AND status = 2";
                } elseif ($payment_filter == 'unpaid') {
                    $count_query .= " AND (status != 2 OR status IS NULL)";
                }
            }
            
            $recipient_count = 0;
            $count_result = mysqli_query($conn, $count_query);
            if ($count_result) {
                $count_row = mysqli_fetch_assoc($count_result);
                $recipient_count = $count_row['cnt'];
            }
            
            // Insert schedule
            $insert = mysqli_query($conn, "
                INSERT INTO email_schedules 
                (email_type, target_id, target_name, email_template_id, email_number, payment_filter, scheduled_date, scheduled_time, total_recipients, created_by)
                VALUES 
                ('$email_type', $target_id, '$target_name', $email_template_id, $email_number, '$payment_filter', '$scheduled_date', '$scheduled_time', $recipient_count, $created_by)
            ");
            
            if ($insert) {
                $success_message = "Email scheduled successfully! Will send \"$email_subject\" to $recipient_count recipients on " . date('M d, Y', strtotime($scheduled_date)) . " at " . date('h:i A', strtotime($scheduled_time));
            } else {
                $error_message = "Failed to schedule email: " . mysqli_error($conn);
            }
        }
        
        // Handle cancel action
        if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
            $cancel_id = intval($_GET['cancel']);
            mysqli_query($conn, "UPDATE email_schedules SET status = 'cancelled' WHERE id = $cancel_id AND status = 'pending'");
            $success_message = "Schedule #$cancel_id has been cancelled.";
        }
        
        // Fetch existing schedules
        $schedules = [];
        $schedule_result = mysqli_query($conn, "
            SELECT es.*, ru.fullname as created_by_name 
            FROM email_schedules es 
            LEFT JOIN registered_users ru ON es.created_by = ru.id 
            ORDER BY es.scheduled_date DESC, es.scheduled_time DESC 
            LIMIT 50
        ");
        if ($schedule_result) {
            while ($row = mysqli_fetch_assoc($schedule_result)) {
                $schedules[] = $row;
            }
        }
        ?>
        
        <div class="container-fluid mt-5 pt-4">
            <div class="row">
                <div class="col-12">
                    <h4 class="mb-4"><i class="fas fa-clock me-2"></i>Email Scheduling</h4>
                    
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row">
                <!-- Schedule Form -->
                <div class="col-lg-5 mb-4">
                    <div class="card shadow-sm rounded-0">
                        <div class="card-header bg_main rounded-0 py-3">
                            <h6 class="mb-0 text-white"><i class="fas fa-plus-circle me-2"></i>Schedule New Email</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="scheduleForm">
                                <input type="hidden" name="target_id" id="target_id">
                                <input type="hidden" name="target_name" id="target_name">
                                
                                <!-- Email Type -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email Type <span class="text-danger">*</span></label>
                                    <select name="email_type" id="email_type" class="form-select rounded-0" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="virtual">Virtual Course</option>
                                        <option value="international">International Event</option>
                                    </select>
                                </div>
                                
                                <!-- Course/Event Selection -->
                                <div class="mb-3" id="target_container" style="display: none;">
                                    <label class="form-label fw-bold" id="target_label">Select Course/Event <span class="text-danger">*</span></label>
                                    <select id="target_select" class="form-select rounded-0" required disabled>
                                        <option value="">-- Select --</option>
                                    </select>
                                </div>
                                
                                <!-- Email Selection -->
                                <div class="mb-3" id="email_container" style="display: none;">
                                    <label class="form-label fw-bold">Select Email <span class="text-danger">*</span></label>
                                    <select name="email_template_id" id="email_template_id" class="form-select rounded-0" required disabled>
                                        <option value="">-- Select Email --</option>
                                    </select>
                                    <small class="text-muted" id="email_loading" style="display: none;">
                                        <i class="fas fa-spinner fa-spin"></i> Loading emails...
                                    </small>
                                </div>
                                
                                <!-- Payment Filter -->
                                <div class="mb-3" id="payment_container" style="display: none;">
                                    <label class="form-label fw-bold">Send To</label>
                                    <select name="payment_filter" id="payment_filter" class="form-select rounded-0">
                                        <option value="all">All Registrations</option>
                                        <option value="paid">Paid Only</option>
                                        <option value="unpaid">Unpaid Only</option>
                                    </select>
                                </div>
                                
                                <!-- Schedule Date & Time -->
                                <div class="row" id="datetime_container" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Scheduled Date <span class="text-danger">*</span></label>
                                        <input type="date" name="scheduled_date" id="scheduled_date" class="form-control rounded-0" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Scheduled Time</label>
                                        <input type="time" name="scheduled_time" id="scheduled_time" class="form-control rounded-0" value="08:00">
                                    </div>
                                </div>
                                
                                <!-- Recipient Count Preview -->
                                <div class="mb-3" id="recipient_preview" style="display: none;">
                                    <div class="alert alert-info mb-0 rounded-0">
                                        <i class="fas fa-users me-2"></i>
                                        <span id="recipient_count">0</span> recipients will receive this email
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="d-grid" id="submit_container" style="display: none;">
                                    <button type="submit" name="schedule_email" class="btn btn-primary rounded-0">
                                        <i class="fas fa-calendar-plus me-2"></i>Schedule Email
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Scheduled Emails List -->
                <div class="col-lg-7">
                    <div class="card shadow-sm rounded-0">
                        <div class="card-header bg-dark text-white rounded-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Scheduled Emails</h6>
                            <span class="badge bg-light text-dark"><?php echo count($schedules); ?> schedules</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Course/Event</th>
                                            <th>Email #</th>
                                            <th>Filter</th>
                                            <th>Scheduled</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($schedules)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                No scheduled emails yet
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td>
                                                <?php if ($schedule['email_type'] == 'virtual'): ?>
                                                <span class="badge bg-primary">Virtual</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">International</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="fw-bold"><?php echo htmlspecialchars(substr($schedule['target_name'], 0, 25)); ?><?php echo strlen($schedule['target_name']) > 25 ? '...' : ''; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">Email <?php echo $schedule['email_number']; ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                $filter_badges = [
                                                    'all' => '<span class="badge bg-info">All</span>',
                                                    'paid' => '<span class="badge bg-success">Paid</span>',
                                                    'unpaid' => '<span class="badge bg-warning text-dark">Unpaid</span>'
                                                ];
                                                echo $filter_badges[$schedule['payment_filter']] ?? $schedule['payment_filter'];
                                                ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('M d, Y', strtotime($schedule['scheduled_date'])); ?><br>
                                                    <span class="text-muted"><?php echo date('h:i A', strtotime($schedule['scheduled_time'])); ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_badges = [
                                                    'pending' => '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending</span>',
                                                    'processing' => '<span class="badge bg-info"><i class="fas fa-spinner fa-spin"></i> Processing</span>',
                                                    'completed' => '<span class="badge bg-success"><i class="fas fa-check"></i> Completed</span>',
                                                    'failed' => '<span class="badge bg-danger"><i class="fas fa-times"></i> Failed</span>',
                                                    'cancelled' => '<span class="badge bg-secondary"><i class="fas fa-ban"></i> Cancelled</span>'
                                                ];
                                                echo $status_badges[$schedule['status']] ?? $schedule['status'];
                                                ?>
                                                <?php if (in_array($schedule['status'], ['completed', 'failed'])): ?>
                                                <br><small class="text-muted"><?php echo $schedule['sent_count']; ?>/<?php echo $schedule['total_recipients']; ?> sent</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="email_schedule_details.php?id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-outline-primary rounded-0" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($schedule['status'] == 'pending'): ?>
                                                <a href="?cancel=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-outline-danger rounded-0" title="Cancel" onclick="return confirm('Cancel this scheduled email?');">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
const courses = <?php echo json_encode($courses); ?>;
const events = <?php echo json_encode($events); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const emailTypeSelect = document.getElementById('email_type');
    const targetContainer = document.getElementById('target_container');
    const targetLabel = document.getElementById('target_label');
    const targetSelect = document.getElementById('target_select');
    const emailContainer = document.getElementById('email_container');
    const emailSelect = document.getElementById('email_template_id');
    const emailLoading = document.getElementById('email_loading');
    const paymentContainer = document.getElementById('payment_container');
    const datetimeContainer = document.getElementById('datetime_container');
    const recipientPreview = document.getElementById('recipient_preview');
    const submitContainer = document.getElementById('submit_container');
    const targetIdInput = document.getElementById('target_id');
    const targetNameInput = document.getElementById('target_name');
    const paymentFilter = document.getElementById('payment_filter');
    
    // Email Type Change
    emailTypeSelect.addEventListener('change', function() {
        const type = this.value;
        
        targetSelect.innerHTML = '<option value="">-- Select --</option>';
        emailSelect.innerHTML = '<option value="">-- Select Email --</option>';
        emailSelect.disabled = true;
        targetIdInput.value = '';
        targetNameInput.value = '';
        
        if (type === '') {
            targetContainer.style.display = 'none';
            emailContainer.style.display = 'none';
            paymentContainer.style.display = 'none';
            datetimeContainer.style.display = 'none';
            recipientPreview.style.display = 'none';
            submitContainer.style.display = 'none';
            return;
        }
        
        if (type === 'virtual') {
            targetLabel.innerHTML = 'Select Course <span class="text-danger">*</span>';
            courses.forEach(course => {
                const option = document.createElement('option');
                option.value = course.course_id;
                option.textContent = course.course;
                option.dataset.name = course.course;
                targetSelect.appendChild(option);
            });
        } else {
            targetLabel.innerHTML = 'Select Event <span class="text-danger">*</span>';
            events.forEach(event => {
                const option = document.createElement('option');
                option.value = event.event_id;
                option.textContent = event.event_title;
                option.dataset.name = event.event_title;
                targetSelect.appendChild(option);
            });
        }
        
        targetSelect.disabled = false;
        targetContainer.style.display = 'block';
    });
    
    // Target Change - Load emails
    targetSelect.addEventListener('change', function() {
        const targetId = this.value;
        const selectedOption = this.options[this.selectedIndex];
        const emailType = emailTypeSelect.value;
        
        targetIdInput.value = targetId;
        targetNameInput.value = selectedOption.dataset.name || selectedOption.textContent;
        
        emailSelect.innerHTML = '<option value="">-- Select Email --</option>';
        emailSelect.disabled = true;
        
        if (targetId === '') {
            emailContainer.style.display = 'none';
            paymentContainer.style.display = 'none';
            datetimeContainer.style.display = 'none';
            recipientPreview.style.display = 'none';
            submitContainer.style.display = 'none';
            return;
        }
        
        emailContainer.style.display = 'block';
        emailLoading.style.display = 'inline';
        
        // AJAX call to ajax/ folder
     fetch(`ajax/get_emails_for_target.php?type=${emailType}&target_id=${targetId}`)
            .then(response => response.json())
            .then(data => {
                emailLoading.style.display = 'none';
                
                if (data.emails && data.emails.length > 0) {
                    data.emails.forEach(email => {
                        const option = document.createElement('option');
                        option.value = email.id;
                        option.textContent = `Email ${email.email_opt} - ${email.subject}`;
                        emailSelect.appendChild(option);
                    });
                    emailSelect.disabled = false;
                } else {
                    emailSelect.innerHTML = '<option value="">No emails found for this selection</option>';
                    if (data.error) {
                        console.log('Error:', data.error);
                    }
                }
            })
            .catch(error => {
                emailLoading.style.display = 'none';
                emailSelect.innerHTML = '<option value="">Error loading emails</option>';
                console.error('Error:', error);
            });
    });
    
    // Email Selection Change
    emailSelect.addEventListener('change', function() {
        if (this.value === '') {
            paymentContainer.style.display = 'none';
            datetimeContainer.style.display = 'none';
            recipientPreview.style.display = 'none';
            submitContainer.style.display = 'none';
            return;
        }
        
        paymentContainer.style.display = 'block';
        datetimeContainer.style.display = 'flex';
        submitContainer.style.display = 'block';
        updateRecipientCount();
    });
    
    // Payment Filter Change
    paymentFilter.addEventListener('change', updateRecipientCount);
    
    function updateRecipientCount() {
        const emailType = emailTypeSelect.value;
        const targetId = targetIdInput.value;
        const filter = paymentFilter.value;
        
        if (!targetId) return;
        
        recipientPreview.style.display = 'block';
        document.getElementById('recipient_count').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch(`ajax/count_recipients.php?type=${emailType}&target_id=${targetId}&filter=${filter}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('recipient_count').textContent = data.count || 0;
            })
            .catch(error => {
                document.getElementById('recipient_count').textContent = '?';
            });
    }
});
</script>

<?php require_once 'footer.php'; ?>