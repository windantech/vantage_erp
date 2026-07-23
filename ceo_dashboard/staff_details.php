<?php
require_once 'header.php';
require_once '../function.php';

$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

// Handle status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $current_user_id = intval($_SESSION['login_id'] ?? 1);
    $allowed_statuses = ['active', 'approved', 'suspended', 'terminated'];
    
    if (in_array($new_status, $allowed_statuses)) {
        // Update status
        $update_sql = "UPDATE staff SET onboarding_status = '$new_status', updated_by = $current_user_id, updated_at = NOW() WHERE id = $staff_id";
        
        if (mysqli_query($conn, $update_sql)) {
            // Log the action
            $action_map = [
                'active' => 'activated',
                'suspended' => 'suspended',
                'terminated' => 'terminated',
                'approved' => 'reactivated'
            ];
            $action = $action_map[$new_status] ?? 'status_changed';
            $notes = isset($_POST['status_notes']) ? mysqli_real_escape_string($conn, $_POST['status_notes']) : "Status changed to $new_status";
            
            mysqli_query($conn, "
                INSERT INTO staff_onboarding_log (staff_id, action, notes, performed_by, ip_address)
                VALUES ($staff_id, '$action', '$notes', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')
            ");
            
            $success_message = "Staff status updated to " . ucfirst($new_status) . " successfully!";
        } else {
            $error_message = "Failed to update status: " . mysqli_error($conn);
        }
    }
}

if (!$staff_id) {
    echo '<script>alert("Invalid staff ID"); window.location.href="staff_list.php";</script>';
    exit;
}

// Fetch staff details - simple query
$staff = null;
$result = mysqli_query($conn, "SELECT * FROM staff WHERE id = $staff_id LIMIT 1");
if ($result && mysqli_num_rows($result) > 0) {
    $staff = mysqli_fetch_assoc($result);
}

if (!$staff) {
    echo '<script>alert("Staff not found"); window.location.href="staff_list.php";</script>';
    exit;
}

// Get department name separately
$department_name = '';
if ($staff['department_id']) {
    $dept_result = mysqli_query($conn, "SELECT department_name FROM departments WHERE id = " . intval($staff['department_id']) . " LIMIT 1");
    if ($dept_result && $row = mysqli_fetch_assoc($dept_result)) {
        $department_name = $row['department_name'];
    }
}

// Get supervisor name separately
$supervisor_name = '';
if ($staff['reporting_to']) {
    $sup_result = mysqli_query($conn, "SELECT full_name FROM staff WHERE id = " . intval($staff['reporting_to']) . " LIMIT 1");
    if ($sup_result && $row = mysqli_fetch_assoc($sup_result)) {
        $supervisor_name = $row['full_name'];
    }
}

// Get approver name separately
$approved_by_name = '';
if ($staff['approved_by']) {
    $app_result = mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = " . intval($staff['approved_by']) . " LIMIT 1");
    if ($app_result && $row = mysqli_fetch_assoc($app_result)) {
        $approved_by_name = $row['fullname'];
    }
}

// Fetch qualifications
$qualifications = [];
$qual_result = mysqli_query($conn, "SELECT * FROM staff_qualifications WHERE staff_id = $staff_id ORDER BY year_completed DESC");
if ($qual_result) {
    while ($row = mysqli_fetch_assoc($qual_result)) {
        $qualifications[] = $row;
    }
}

// Fetch documents
$documents = [];
$doc_result = mysqli_query($conn, "SELECT * FROM staff_documents WHERE staff_id = $staff_id ORDER BY uploaded_at DESC");
if ($doc_result) {
    while ($row = mysqli_fetch_assoc($doc_result)) {
        $documents[] = $row;
    }
}

// Fetch onboarding log
$logs = [];
$log_result = mysqli_query($conn, "SELECT * FROM staff_onboarding_log WHERE staff_id = $staff_id ORDER BY performed_at DESC LIMIT 20");
if ($log_result) {
    while ($row = mysqli_fetch_assoc($log_result)) {
        $row['performed_by_name'] = '';
        if ($row['performed_by']) {
            $perf_result = mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = " . intval($row['performed_by']) . " LIMIT 1");
            if ($perf_result && $perf_row = mysqli_fetch_assoc($perf_result)) {
                $row['performed_by_name'] = $perf_row['fullname'];
            }
        }
        $logs[] = $row;
    }
}

// Decode allowances and deductions
$allowances = [];
$deductions = [];
if (!empty($staff['allowances'])) {
    $decoded = json_decode($staff['allowances'], true);
    if (is_array($decoded)) {
        $allowances = $decoded;
    }
}
if (!empty($staff['deductions'])) {
    $decoded = json_decode($staff['deductions'], true);
    if (is_array($decoded)) {
        $deductions = $decoded;
    }
}

// Get allowance names
$allowance_names = [];
$allow_result = mysqli_query($conn, "SELECT allowance_code, allowance_name FROM allowance_types");
if ($allow_result) {
    while ($row = mysqli_fetch_assoc($allow_result)) {
        $allowance_names[$row['allowance_code']] = $row['allowance_name'];
    }
}

// Get deduction names
$deduction_names = [];
$ded_result = mysqli_query($conn, "SELECT deduction_code, deduction_name FROM deductions");
if ($ded_result) {
    while ($row = mysqli_fetch_assoc($ded_result)) {
        $deduction_names[$row['deduction_code']] = $row['deduction_name'];
    }
}

$status_badges = [
    'pending' => '<span class="badge bg-warning text-dark fs-6">Pending</span>',
    'under_review' => '<span class="badge bg-info fs-6">Under Review</span>',
    'approved' => '<span class="badge bg-success fs-6">Approved</span>',
    'rejected' => '<span class="badge bg-danger fs-6">Rejected</span>',
    'active' => '<span class="badge bg-primary fs-6">Active</span>',
    'suspended' => '<span class="badge bg-warning fs-6">Suspended</span>',
    'inactive' => '<span class="badge bg-secondary fs-6">Inactive</span>',
    'terminated' => '<span class="badge bg-dark fs-6">Terminated</span>'
];
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-0">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-0">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="staff_list.php" class="btn btn-outline-secondary btn-sm rounded-0 mb-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                    <h4 class="mb-0"><i class="fas fa-user me-2"></i>Staff Details</h4>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php echo $status_badges[$staff['onboarding_status']] ?? '<span class="badge bg-secondary fs-6">' . ucfirst($staff['onboarding_status']) . '</span>'; ?>
                    
                    <?php if (in_array($staff['onboarding_status'], ['pending', 'under_review'])): ?>
                    <a href="staff_approve.php?id=<?php echo $staff_id; ?>" class="btn btn-success rounded-0">
                        <i class="fas fa-check me-1"></i>Review & Approve
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($staff['onboarding_status'] == 'approved'): ?>
                    <button type="button" class="btn btn-success rounded-0" data-bs-toggle="modal" data-bs-target="#activateModal">
                        <i class="fas fa-user-check me-1"></i>Activate Staff
                    </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($staff['onboarding_status'], ['approved', 'active'])): ?>
                    <a href="staff_edit.php?id=<?php echo $staff_id; ?>" class="btn btn-primary rounded-0">
                        <i class="fas fa-edit me-1"></i>Edit Details
                    </a>
                    <?php endif; ?>
                    
                    <!-- System Access Button -->
<?php if (in_array($staff['onboarding_status'], ['approved', 'active'])): ?>
    <?php if (($staff['system_access_granted'] ?? 0) == 1): ?>
        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#systemAccessModal">
            <i class="fas fa-key me-1"></i>System Access
        </button>
    <?php else: ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#systemAccessModal">
            <i class="fas fa-user-plus me-1"></i>Grant System Access
        </button>
    <?php endif; ?>
<?php endif; ?>
                    
                    <?php if ($staff['onboarding_status'] == 'active'): ?>
                    <button type="button" class="btn btn-warning rounded-0" data-bs-toggle="modal" data-bs-target="#suspendModal">
                        <i class="fas fa-pause me-1"></i>Suspend
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            

<?php
// ---- Handle biometric device enrolment ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_biometric'])) {
    $b_device   = mysqli_real_escape_string($conn, $_POST['biometric_device']);
    $b_user_id  = mysqli_real_escape_string($conn, trim($_POST['biometric_user_id']));
    $b_staffcode= mysqli_real_escape_string($conn, $staff['staff_id']);   // VASL-STF-xxxx
    $b_staffpk  = intval($staff['id']);
    $b_name     = mysqli_real_escape_string($conn, $staff['full_name']);

    if ($b_device !== '' && $b_user_id !== '') {
        // Upsert keyed on device + user id (same id on another device is a different row)
        $sql = "INSERT INTO device_user_map
                  (device_id, device_user_id, staff_id, staff_table_id, full_name)
                VALUES
                  ('$b_device', '$b_user_id', '$b_staffcode', $b_staffpk, '$b_name')
                ON DUPLICATE KEY UPDATE
                  staff_id='$b_staffcode', staff_table_id=$b_staffpk, full_name='$b_name'";
        if (mysqli_query($conn, $sql)) {
            // Backfill any existing punches from THIS device+user to this staff
            mysqli_query($conn, "UPDATE attendance_logs
                                 SET staff_id='$b_staffcode'
                                 WHERE device_id='$b_device' AND device_user_id='$b_user_id'");
            $success_message = "Staff enrolled on $b_device with ID $b_user_id.";
        } else {
            $error_message = "Failed to enroll: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Please select a device and enter the assigned ID.";
    }
}

// ---- Load this staff member's existing biometric enrolments ----
$biometric_enrolments = [];
$be = mysqli_query($conn, "SELECT device_id, device_user_id
                           FROM device_user_map
                           WHERE staff_table_id = " . intval($staff['id']) . "
                           ORDER BY device_id");
if ($be) { while ($r = mysqli_fetch_assoc($be)) $biometric_enrolments[] = $r; }
?>



<!-- Biometric Enrolment -->
<div class="card shadow-sm rounded-0 mb-4">
    <div class="card-header bg_main text-white rounded-0 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-fingerprint me-2"></i>Biometric Devices</span>
        <button type="button" class="btn btn-sm btn-light rounded-0" data-bs-toggle="modal" data-bs-target="#biometricModal">
            <i class="fas fa-plus me-1"></i>Add to Device
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($biometric_enrolments)): ?>
            <p class="text-muted text-center py-4 mb-0">
                <i class="fas fa-info-circle me-2"></i>Not enrolled on any biometric device yet
            </p>
        <?php else: ?>
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Device</th>
                        <th>Assigned ID on Device</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($biometric_enrolments as $en): ?>
                    <tr>
                        <td><i class="fas fa-fingerprint text-muted me-2"></i><?php echo htmlspecialchars($en['device_id']); ?></td>
                        <td><span class="badge bg-dark"><?php echo htmlspecialchars($en['device_user_id']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>



<!-- Biometric Enrolment Modal -->
<div class="modal fade" id="biometricModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <form method="POST">
                <input type="hidden" name="enroll_biometric" value="1">
                <div class="modal-header bg_main text-white">
                    <h5 class="modal-title"><i class="fas fa-fingerprint me-2"></i>Add to Biometric Device</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Enroll <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                       (<?php echo htmlspecialchars($staff['staff_id']); ?>) on a device.</p>

                    <div class="mb-3">
                        <label class="form-label">Device <span class="text-danger">*</span></label>
                        <select name="biometric_device" class="form-select rounded-0" required>
                            <option value="">-- Select Device --</option>
                            <option value="K40-Main Office">K40 - Main Office</option>
                            <option value="K40-Office Two">K40 - Office Two</option>
                            <option value="K40-Boardroom">K40 - Boardroom</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Assigned ID on this device <span class="text-danger">*</span></label>
                        <input type="text" name="biometric_user_id" class="form-control rounded-0"
                               placeholder="e.g. 10" required>
                        <small class="text-muted">
                            This is the User ID entered on the device itself when enrolling the fingerprint.
                            The same number on a different device is treated as separate.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn bg_main text-white rounded-0">
                        <i class="fas fa-check me-1"></i>Enroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-4">
                    <!-- Profile Card -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-body text-center py-4">
                            <?php if (!empty($staff['passport_photo']) && file_exists('../' . $staff['passport_photo'])): ?>
                            <img src="../<?php echo $staff['passport_photo']; ?>" class="rounded-circle border shadow" width="120" height="120" style="object-fit: cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center text-white mx-auto shadow" style="width: 120px; height: 120px; font-size: 3rem;">
                                <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                            
                            <h4 class="mt-3 mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($staff['job_title'] ?? 'Position Not Assigned'); ?></p>
                            <span class="badge bg-dark"><?php echo htmlspecialchars($staff['staff_id']); ?></span>
                            
                            <?php if ($department_name): ?>
                            <p class="mt-3 mb-0"><span class="badge bg-light text-dark"><?php echo htmlspecialchars($department_name); ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-dark text-white rounded-0">
                            <i class="fas fa-address-card me-2"></i>Contact Information
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><i class="fas fa-envelope text-muted me-2"></i><a href="mailto:<?php echo $staff['email']; ?>"><?php echo htmlspecialchars($staff['email']); ?></a></p>
                            <p class="mb-2"><i class="fas fa-phone text-muted me-2"></i><?php echo htmlspecialchars($staff['phone']); ?></p>
                            <?php if (!empty($staff['phone_alt'])): ?>
                            <p class="mb-2"><i class="fas fa-phone-alt text-muted me-2"></i><?php echo htmlspecialchars($staff['phone_alt']); ?></p>
                            <?php endif; ?>
                            <p class="mb-0"><i class="fas fa-home text-muted me-2"></i><?php echo htmlspecialchars($staff['home_address']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-danger text-white rounded-0">
                            <i class="fas fa-heart me-2"></i>Emergency Contact
                        </div>
                        <div class="card-body">
                            <h6><?php echo htmlspecialchars($staff['nok_name']); ?></h6>
                            <p class="text-muted mb-2"><?php echo ucfirst($staff['nok_relationship']); ?></p>
                            <p class="mb-1"><i class="fas fa-phone text-muted me-2"></i><?php echo htmlspecialchars($staff['nok_phone']); ?></p>
                            <?php if (!empty($staff['medical_conditions'])): ?>
                            <hr>
                            <p class="mb-0 text-danger"><i class="fas fa-notes-medical me-2"></i><strong>Medical:</strong> <?php echo htmlspecialchars($staff['medical_conditions']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-8">
                    <!-- Personal Details -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-secondary text-white rounded-0">
                            <i class="fas fa-user me-2"></i>Personal Details
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr><td class="text-muted">Date of Birth:</td><td><?php echo date('M d, Y', strtotime($staff['date_of_birth'])); ?></td></tr>
                                        <tr><td class="text-muted">Age:</td><td><?php echo floor((time() - strtotime($staff['date_of_birth'])) / 31556926); ?> years</td></tr>
                                        <tr><td class="text-muted">Gender:</td><td><?php echo ucfirst($staff['gender']); ?></td></tr>
                                        <tr><td class="text-muted">Marital Status:</td><td><?php echo ucfirst($staff['marital_status'] ?? '-'); ?></td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr><td class="text-muted">National ID:</td><td><strong><?php echo htmlspecialchars($staff['national_id']); ?></strong></td></tr>
                                        <tr><td class="text-muted">Nationality:</td><td><?php echo htmlspecialchars($staff['nationality']); ?></td></tr>
                                        <tr><td class="text-muted">KRA PIN:</td><td><?php echo htmlspecialchars($staff['kra_pin']); ?></td></tr>
                                        <tr><td class="text-muted">NSSF No:</td><td><?php echo htmlspecialchars($staff['nssf_number']); ?></td></tr>
                                        <tr><td class="text-muted">NHIF No:</td><td><?php echo htmlspecialchars($staff['nhif_number']); ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Employment Details -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg_main text-white rounded-0 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-briefcase me-2"></i>Employment Details</span>
                            <?php if (in_array($staff['onboarding_status'], ['approved', 'active'])): ?>
                            <a href="staff_edit.php?id=<?php echo $staff_id; ?>" class="btn btn-sm btn-light rounded-0">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($staff['job_title'])): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr><td class="text-muted">Job Title:</td><td><strong><?php echo htmlspecialchars($staff['job_title']); ?></strong></td></tr>
                                        <tr><td class="text-muted">Department:</td><td><?php echo htmlspecialchars($department_name ?: '-'); ?></td></tr>
                                        <tr><td class="text-muted">Reports To:</td><td><?php echo htmlspecialchars($supervisor_name ?: '-'); ?></td></tr>
                                        <tr><td class="text-muted">Employment Type:</td><td><span class="badge bg-info"><?php echo ucfirst($staff['employment_type'] ?? '-'); ?></span></td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless">
                                        <tr><td class="text-muted">Start Date:</td><td><?php echo !empty($staff['start_date']) ? date('M d, Y', strtotime($staff['start_date'])) : '-'; ?></td></tr>
                                        <tr><td class="text-muted">Probation Ends:</td><td><?php echo !empty($staff['probation_end_date']) ? date('M d, Y', strtotime($staff['probation_end_date'])) : '-'; ?></td></tr>
                                        <tr><td class="text-muted">Working Hours:</td><td><?php echo htmlspecialchars($staff['working_hours'] ?? '-'); ?></td></tr>
                                        <tr><td class="text-muted">Work Location:</td><td><?php echo ucfirst($staff['work_location'] ?? '-'); ?></td></tr>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if (!empty($staff['contract_start_date']) || !empty($staff['contract_end_date'])): ?>
                            <hr>
                            <h6 class="text-muted"><i class="fas fa-file-contract me-2"></i>Contract Period</h6>
                            <p class="mb-0">
                                <?php echo !empty($staff['contract_start_date']) ? date('M d, Y', strtotime($staff['contract_start_date'])) : '-'; ?>
                                to
                                <?php echo !empty($staff['contract_end_date']) ? date('M d, Y', strtotime($staff['contract_end_date'])) : 'Open-ended'; ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <p class="text-muted text-center py-3 mb-0"><i class="fas fa-info-circle me-2"></i>Employment details not yet assigned</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Compensation -->
                    <?php if (!empty($staff['basic_salary'])): ?>
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-success text-white rounded-0 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-money-bill-wave me-2"></i>Compensation</span>
                            <?php if (in_array($staff['onboarding_status'], ['approved', 'active'])): ?>
                            <a href="staff_edit.php?id=<?php echo $staff_id; ?>" class="btn btn-sm btn-light rounded-0">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center border-end">
                                    <small class="text-muted">Basic Salary</small>
                                    <h4 class="text-success mb-0">KES <?php echo number_format($staff['basic_salary'], 2); ?></h4>
                                </div>
                                <div class="col-md-4 text-center border-end">
                                    <small class="text-muted">Total Allowances</small>
                                    <h4 class="text-info mb-0">KES <?php echo number_format(array_sum($allowances), 2); ?></h4>
                                </div>
                                <div class="col-md-4 text-center">
                                    <small class="text-muted">Gross Salary</small>
                                    <h4 class="text-primary mb-0">KES <?php echo number_format($staff['basic_salary'] + array_sum($allowances), 2); ?></h4>
                                </div>
                            </div>
                            
                            <?php if (!empty($allowances)): ?>
                            <hr>
                            <h6 class="text-muted mb-3">Allowances</h6>
                            <div class="row">
                                <?php foreach ($allowances as $code => $amount): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span><?php echo $allowance_names[$code] ?? $code; ?>:</span>
                                        <strong>KES <?php echo number_format($amount, 2); ?></strong>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($deductions)): ?>
                            <hr>
                            <h6 class="text-muted mb-3">Applicable Deductions</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($deductions as $code => $active): ?>
                                <?php if ($active): ?>
                                <span class="badge bg-danger"><?php echo $deduction_names[$code] ?? $code; ?></span>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Qualifications -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-warning text-dark rounded-0">
                            <i class="fas fa-graduation-cap me-2"></i>Qualifications (<?php echo count($qualifications); ?>)
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($qualifications)): ?>
                            <p class="text-muted text-center py-4 mb-0">No qualifications recorded</p>
                            <?php else: ?>
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Institution</th>
                                        <th>Year</th>
                                        <th>File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($qualifications as $qual): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo ucfirst($qual['qualification_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($qual['description']); ?></td>
                                        <td><?php echo htmlspecialchars($qual['institution']); ?></td>
                                        <td><?php echo $qual['year_completed']; ?></td>
                                        <td>
                                            <?php if (!empty($qual['certificate_path'])): ?>
                                            <a href="../<?php echo $qual['certificate_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-0">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php else: ?>
                                            -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Documents -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-info text-white rounded-0">
                            <i class="fas fa-folder-open me-2"></i>Documents (<?php echo count($documents); ?>)
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($documents)): ?>
                            <p class="text-muted text-center py-4 mb-0">No documents uploaded</p>
                            <?php else: ?>
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Uploaded</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td><i class="fas fa-file text-muted me-2"></i><?php echo htmlspecialchars($doc['document_name']); ?></td>
                                        <td><span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></td>
                                        <td>
                                            <a href="../<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-0">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Activity Log -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-dark text-white rounded-0">
                            <i class="fas fa-history me-2"></i>Activity Log
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($logs)): ?>
                            <p class="text-muted text-center py-4 mb-0">No activity recorded</p>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($logs as $log): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></strong>
                                            <?php if (!empty($log['old_status']) && !empty($log['new_status'])): ?>
                                            <span class="badge bg-secondary"><?php echo $log['old_status']; ?></span>
                                            <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                            <span class="badge bg-primary"><?php echo $log['new_status']; ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($log['notes'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($log['notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?php echo date('M d, Y H:i', strtotime($log['performed_at'])); ?>
                                                <?php if (!empty($log['performed_by_name'])): ?>
                                                <br>by <?php echo htmlspecialchars($log['performed_by_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Activate Staff Modal -->
<div class="modal fade" id="activateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <form method="POST">
                <input type="hidden" name="change_status" value="1">
                <input type="hidden" name="new_status" value="active">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-check me-2"></i>Activate Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to activate <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>?</p>
                    <p class="text-muted small mb-3">Once activated, this staff member will appear in payroll processing and other HR modules.</p>
                    <div class="mb-0">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control rounded-0" name="status_notes" rows="2" placeholder="e.g., Started work on..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-0">
                        <i class="fas fa-check me-1"></i>Activate Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Suspend Staff Modal -->
<div class="modal fade" id="suspendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <form method="POST">
                <input type="hidden" name="change_status" value="1">
                <input type="hidden" name="new_status" value="suspended">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-pause me-2"></i>Suspend Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to suspend <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>?</p>
                    <p class="text-muted small mb-3">Suspended staff will not appear in payroll processing.</p>
                    <div class="mb-0">
                        <label class="form-label">Reason for Suspension <span class="text-danger">*</span></label>
                        <textarea class="form-control rounded-0" name="status_notes" rows="2" required placeholder="Provide reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-0">
                        <i class="fas fa-pause me-1"></i>Suspend Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/system_access_modal.php'; ?>
<?php require_once 'footer.php'; ?>