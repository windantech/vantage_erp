<?php
session_start();
require_once 'header.php';

$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$staff_id) {
    echo '<script>alert("Invalid staff ID"); window.location.href="staff_list.php";</script>';
    exit;
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // Validate required fields
        $required = ['job_title', 'department_id', 'employment_type', 'start_date', 'basic_salary'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $error_message = "Please fill all required fields: " . implode(', ', $missing);
        } else {
            // Prepare data
            $job_title = mysqli_real_escape_string($conn, $_POST['job_title']);
            $department_id = intval($_POST['department_id']);
            $reporting_to = !empty($_POST['reporting_to']) ? intval($_POST['reporting_to']) : 'NULL';
            $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
            $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
            $probation_end_date = !empty($_POST['probation_end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['probation_end_date']) . "'" : 'NULL';
            $working_hours = mysqli_real_escape_string($conn, $_POST['working_hours'] ?? '8:00 AM - 5:00 PM');
            $work_location = mysqli_real_escape_string($conn, $_POST['work_location'] ?? 'office');
            $basic_salary = floatval($_POST['basic_salary']);
            $contract_start_date = !empty($_POST['contract_start_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['contract_start_date']) . "'" : 'NULL';
            $contract_end_date = !empty($_POST['contract_end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['contract_end_date']) . "'" : 'NULL';
            
            // Build allowances JSON
            $allowances = [];
            if (isset($_POST['allowances']) && is_array($_POST['allowances'])) {
                foreach ($_POST['allowances'] as $code => $checked) {
                    $amount = floatval($_POST['allowance_amounts'][$code] ?? 0);
                    if ($amount > 0) {
                        $allowances[$code] = $amount;
                    }
                }
            }
            $allowances_json = !empty($allowances) ? "'" . mysqli_real_escape_string($conn, json_encode($allowances)) . "'" : 'NULL';
            
            // Build deductions JSON
        // Record a decision for EVERY deduction code, not just the ticked ones. An
        // unticked box IS a decision — "do not deduct this" — and saving only the
        // ticks lost it: the column went NULL, which payroll reads as "never
        // configured" and answers with the mandatory defaults. Unticking everything
        // therefore switched every deduction ON, the opposite of what the screen says.
        //
        // The codes are fetched HERE rather than from $deduction_types, which is
        // loaded further down this file and would be an empty array at this point —
        // silently recording every deduction as OFF.
        $deductions = [];
        $posted = (isset($_POST['deductions']) && is_array($_POST['deductions'])) ? $_POST['deductions'] : [];
        $codeRes = mysqli_query($conn, "SELECT deduction_code FROM deductions WHERE is_active = 1");
        $allCodes = [];
        if ($codeRes) { while ($cr = mysqli_fetch_assoc($codeRes)) { $allCodes[] = $cr['deduction_code']; } }
        foreach ($allCodes as $code) {
            $deductions[$code] = isset($posted[$code]) && filter_var($posted[$code], FILTER_VALIDATE_BOOLEAN);
        }
        // If the deductions table could not be read, leave the column NULL rather than
        // writing '{}' — an empty map reads back as "everything off" and would wipe
        // this employee's deductions.
        $deductions_json = empty($allCodes)
            ? 'NULL'
            : "'" . mysqli_real_escape_string($conn, json_encode($deductions)) . "'";
            
            $approved_by = intval($_SESSION['login_id'] ?? 1);
            
            // Update staff record
            $update_sql = "
                UPDATE staff SET
                    job_title = '$job_title',
                    department_id = $department_id,
                    reporting_to = $reporting_to,
                    employment_type = '$employment_type',
                    start_date = '$start_date',
                    probation_end_date = $probation_end_date,
                    working_hours = '$working_hours',
                    work_location = '$work_location',
                    basic_salary = $basic_salary,
                    allowances = $allowances_json,
                    deductions = $deductions_json,
                    contract_start_date = $contract_start_date,
                    contract_end_date = $contract_end_date,
                    onboarding_status = 'approved',
                    approved_by = $approved_by,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = $staff_id
            ";
            
            if (mysqli_query($conn, $update_sql)) {
                // Log the action
                mysqli_query($conn, "
                    INSERT INTO staff_onboarding_log (staff_id, action, old_status, new_status, notes, performed_by, ip_address)
                    VALUES ($staff_id, 'approved', 'pending', 'approved', 'Staff onboarding approved with employment details', $approved_by, '{$_SERVER['REMOTE_ADDR']}')
                ");
                
                $success_message = "Staff has been approved successfully!";
            } else {
                $error_message = "Failed to approve staff: " . mysqli_error($conn);
            }
        }
    } elseif ($action === 'reject') {
        $rejection_reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        $approved_by = intval($_SESSION['login_id'] ?? 1);
        
        $update_sql = "
            UPDATE staff SET
                onboarding_status = 'rejected',
                rejection_reason = '$rejection_reason',
                approved_by = $approved_by,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = $staff_id
        ";
        
        if (mysqli_query($conn, $update_sql)) {
            mysqli_query($conn, "
                INSERT INTO staff_onboarding_log (staff_id, action, old_status, new_status, notes, performed_by, ip_address)
                VALUES ($staff_id, 'rejected', 'pending', 'rejected', '$rejection_reason', $approved_by, '{$_SERVER['REMOTE_ADDR']}')
            ");
            
            $success_message = "Staff application has been rejected.";
        } else {
            $error_message = "Failed to reject: " . mysqli_error($conn);
        }
    } elseif ($action === 'mark_review') {
        $approved_by = intval($_SESSION['login_id'] ?? 1);
        mysqli_query($conn, "UPDATE staff SET onboarding_status = 'under_review', updated_at = NOW() WHERE id = $staff_id");
        mysqli_query($conn, "
            INSERT INTO staff_onboarding_log (staff_id, action, old_status, new_status, notes, performed_by, ip_address)
            VALUES ($staff_id, 'under_review', 'pending', 'under_review', 'Marked for review', $approved_by, '{$_SERVER['REMOTE_ADDR']}')
        ");
        $success_message = "Staff marked as under review.";
    }
}

// Fetch staff details
$staff = null;
$result = mysqli_query($conn, "SELECT * FROM staff WHERE id = $staff_id LIMIT 1");
if ($result && mysqli_num_rows($result) > 0) {
    $staff = mysqli_fetch_assoc($result);
}

if (!$staff) {
    echo '<script>alert("Staff not found"); window.location.href="staff_list.php";</script>';
    exit;
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

// Fetch departments
$departments = [];
$dept_result = mysqli_query($conn, "SELECT id, department_id, department_name, department_head FROM departments WHERE status = 1 ORDER BY department_name");
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row;
    }
}

// Fetch allowance types
$allowance_types = [];
$allow_result = mysqli_query($conn, "SELECT * FROM allowance_types WHERE is_active = 1 ORDER BY display_order");
if ($allow_result) {
    while ($row = mysqli_fetch_assoc($allow_result)) {
        $allowance_types[] = $row;
    }
}

// Fetch deductions
$deduction_types = [];
$ded_result = mysqli_query($conn, "SELECT * FROM deductions WHERE is_active = 1 ORDER BY display_order");
if ($ded_result) {
    while ($row = mysqli_fetch_assoc($ded_result)) {
        $deduction_types[] = $row;
    }
}

// Get existing staff for supervisor dropdown
$staff_list_for_supervisor = [];
$sup_result = mysqli_query($conn, "SELECT id, staff_id, full_name, job_title FROM staff WHERE onboarding_status IN ('approved', 'active') AND id != $staff_id ORDER BY full_name");
if ($sup_result) {
    while ($row = mysqli_fetch_assoc($sup_result)) {
        $staff_list_for_supervisor[] = $row;
    }
}

// Decode existing allowances/deductions if editing
$existing_allowances = $staff['allowances'] ? json_decode($staff['allowances'], true) : [];
$existing_deductions = $staff['deductions'] ? json_decode($staff['deductions'], true) : [];
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="staff_list.php" class="btn btn-outline-secondary btn-sm rounded-0 mb-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                    <h4 class="mb-0">
                        <i class="fas fa-user-check me-2"></i>Review & Approve Staff
                    </h4>
                </div>
                <div>
                    <?php
                    $status_badges = [
                        'pending' => '<span class="badge bg-warning text-dark fs-6">Pending</span>',
                        'under_review' => '<span class="badge bg-info fs-6">Under Review</span>',
                        'approved' => '<span class="badge bg-success fs-6">Approved</span>',
                        'rejected' => '<span class="badge bg-danger fs-6">Rejected</span>',
                        'active' => '<span class="badge bg-primary fs-6">Active</span>'
                    ];
                    echo $status_badges[$staff['onboarding_status']] ?? '<span class="badge bg-secondary fs-6">' . ucfirst($staff['onboarding_status']) . '</span>';
                    ?>
                </div>
            </div>
            
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
            
            <div class="row">
                <!-- Left Column: Staff Info (Read Only) -->
                <div class="col-lg-5">
                    <!-- Personal Details Card -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-dark text-white rounded-0">
                            <i class="fas fa-user me-2"></i>Personal Details
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php if ($staff['passport_photo'] && file_exists('../' . $staff['passport_photo'])): ?>
                                <img src="../<?php echo $staff['passport_photo']; ?>" class="rounded-circle border" width="100" height="100" style="object-fit: cover;">
                                <?php else: ?>
                                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center text-white mx-auto" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                    <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <h5 class="mt-2 mb-0"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($staff['staff_id']); ?></small>
                            </div>
                            
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Date of Birth:</td>
                                    <td><?php echo date('M d, Y', strtotime($staff['date_of_birth'])); ?> (<?php echo floor((time() - strtotime($staff['date_of_birth'])) / 31556926); ?> yrs)</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Gender:</td>
                                    <td><?php echo ucfirst($staff['gender']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">National ID:</td>
                                    <td><strong><?php echo htmlspecialchars($staff['national_id']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Nationality:</td>
                                    <td><?php echo htmlspecialchars($staff['nationality']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Marital Status:</td>
                                    <td><?php echo ucfirst($staff['marital_status'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email:</td>
                                    <td><a href="mailto:<?php echo $staff['email']; ?>"><?php echo htmlspecialchars($staff['email']); ?></a></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Phone:</td>
                                    <td><?php echo htmlspecialchars($staff['phone']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Address:</td>
                                    <td><?php echo htmlspecialchars($staff['home_address']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Legal & Compliance -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-secondary text-white rounded-0">
                            <i class="fas fa-shield-alt me-2"></i>Legal & Compliance
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">KRA PIN:</td>
                                    <td><strong><?php echo htmlspecialchars($staff['kra_pin']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">NSSF No:</td>
                                    <td><?php echo htmlspecialchars($staff['nssf_number']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">NHIF No:</td>
                                    <td><?php echo htmlspecialchars($staff['nhif_number']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Next of Kin -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-info text-white rounded-0">
                            <i class="fas fa-heart me-2"></i>Emergency Contact
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" width="40%">Name:</td>
                                    <td><strong><?php echo htmlspecialchars($staff['nok_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Relationship:</td>
                                    <td><?php echo ucfirst($staff['nok_relationship']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Phone:</td>
                                    <td><?php echo htmlspecialchars($staff['nok_phone']); ?></td>
                                </tr>
                                <?php if ($staff['medical_conditions']): ?>
                                <tr>
                                    <td class="text-muted">Medical:</td>
                                    <td class="text-danger"><?php echo htmlspecialchars($staff['medical_conditions']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Qualifications -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-success text-white rounded-0">
                            <i class="fas fa-graduation-cap me-2"></i>Qualifications (<?php echo count($qualifications); ?>)
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($qualifications)): ?>
                            <p class="text-muted text-center py-3 mb-0">No qualifications uploaded</p>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($qualifications as $qual): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo ucfirst($qual['qualification_type']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($qual['description']); ?></small>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($qual['institution']); ?> (<?php echo $qual['year_completed']; ?>)</small>
                                        </div>
                                        <?php if ($qual['certificate_path']): ?>
                                        <a href="../<?php echo $qual['certificate_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-0">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Documents -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-warning text-dark rounded-0">
                            <i class="fas fa-folder-open me-2"></i>Documents (<?php echo count($documents); ?>)
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($documents)): ?>
                            <p class="text-muted text-center py-3 mb-0">No documents uploaded</p>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($documents as $doc): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file text-muted me-2"></i>
                                        <?php echo htmlspecialchars($doc['document_name']); ?>
                                        <br><small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></small>
                                    </div>
                                    <a href="../<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-0">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Approval Form -->
                <div class="col-lg-7">
                    <?php if (in_array($staff['onboarding_status'], ['pending', 'under_review'])): ?>
                    <form method="POST" id="approvalForm">
                        
                        <!-- Employment Details -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-header bg_main text-white rounded-0">
                                <i class="fas fa-briefcase me-2"></i>Employment Details
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Job Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control rounded-0" name="job_title" required 
                                               value="<?php echo htmlspecialchars($staff['job_title'] ?? ''); ?>"
                                               placeholder="e.g., Senior Accountant">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department <span class="text-danger">*</span></label>
                                        <select class="form-select rounded-0" name="department_id" id="department_id" required>
                                            <option value="">-- Select Department --</option>
                                            <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" 
                                                    data-head="<?php echo $dept['department_head']; ?>"
                                                    <?php echo ($staff['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Reports To (Supervisor)</label>
                                        <select class="form-select rounded-0" name="reporting_to" id="reporting_to">
                                            <option value="">-- Select Supervisor --</option>
                                            <?php foreach ($staff_list_for_supervisor as $sup): ?>
                                            <option value="<?php echo $sup['id']; ?>"
                                                    <?php echo ($staff['reporting_to'] == $sup['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sup['full_name']); ?> 
                                                <?php echo $sup['job_title'] ? '(' . $sup['job_title'] . ')' : ''; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Auto-fills from Department Head</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                                        <select class="form-select rounded-0" name="employment_type" required>
                                            <option value="">-- Select --</option>
                                            <option value="permanent" <?php echo ($staff['employment_type'] == 'permanent') ? 'selected' : ''; ?>>Permanent</option>
                                            <option value="contract" <?php echo ($staff['employment_type'] == 'contract') ? 'selected' : ''; ?>>Contract</option>
                                            <option value="temporary" <?php echo ($staff['employment_type'] == 'temporary') ? 'selected' : ''; ?>>Temporary</option>
                                            <option value="internship" <?php echo ($staff['employment_type'] == 'internship') ? 'selected' : ''; ?>>Internship</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control rounded-0" name="start_date" required
                                               value="<?php echo $staff['start_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Probation End Date</label>
                                        <input type="date" class="form-control rounded-0" name="probation_end_date"
                                               value="<?php echo $staff['probation_end_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Work Location</label>
                                        <select class="form-select rounded-0" name="work_location">
                                            <option value="office" <?php echo ($staff['work_location'] == 'office') ? 'selected' : ''; ?>>Office</option>
                                            <option value="remote" <?php echo ($staff['work_location'] == 'remote') ? 'selected' : ''; ?>>Remote</option>
                                            <option value="hybrid" <?php echo ($staff['work_location'] == 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Working Hours</label>
                                        <input type="text" class="form-control rounded-0" name="working_hours" 
                                               value="<?php echo htmlspecialchars($staff['working_hours'] ?? '8:00 AM - 5:00 PM'); ?>"
                                               placeholder="e.g., 8:00 AM - 5:00 PM">
                                    </div>
                                </div>
                                
                                <hr>
                                <h6 class="text-muted mb-3"><i class="fas fa-file-contract me-2"></i>Contract Details</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Contract Start Date</label>
                                        <input type="date" class="form-control rounded-0" name="contract_start_date"
                                               value="<?php echo $staff['contract_start_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Contract End Date</label>
                                        <input type="date" class="form-control rounded-0" name="contract_end_date"
                                               value="<?php echo $staff['contract_end_date'] ?? ''; ?>">
                                        <small class="text-muted">Leave empty for permanent staff</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Salary & Allowances -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-header bg-success text-white rounded-0">
                                <i class="fas fa-money-bill-wave me-2"></i>Compensation
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Basic Salary (KES) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text rounded-0">KES</span>
                                            <input type="number" class="form-control rounded-0" name="basic_salary" required
                                                   min="0" step="0.01" id="basic_salary"
                                                   value="<?php echo $staff['basic_salary'] ?? ''; ?>"
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gross Salary (Calculated)</label>
                                        <div class="input-group">
                                            <span class="input-group-text rounded-0">KES</span>
                                            <input type="text" class="form-control rounded-0 bg-light" id="gross_salary" readonly>
                                        </div>
                                        <small class="text-muted">Basic + Allowances</small>
                                    </div>
                                </div>
                                
                                <h6 class="text-muted mb-3"><i class="fas fa-plus-circle me-2"></i>Allowances (Check applicable & enter amount)</h6>
                                
                                <div class="row">
                                    <?php foreach ($allowance_types as $allowance): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="input-group">
                                            <div class="input-group-text rounded-0">
                                                <input type="checkbox" class="form-check-input mt-0 allowance-checkbox" 
                                                       name="allowances[<?php echo $allowance['allowance_code']; ?>]" 
                                                       value="1"
                                                       data-code="<?php echo $allowance['allowance_code']; ?>"
                                                       <?php echo isset($existing_allowances[$allowance['allowance_code']]) ? 'checked' : ''; ?>>
                                            </div>
                                            <span class="input-group-text rounded-0 flex-grow-1" style="font-size: 0.85rem;">
                                                <?php echo htmlspecialchars($allowance['allowance_name']); ?>
                                            </span>
                                            <input type="number" class="form-control rounded-0 allowance-amount" 
                                                   name="allowance_amounts[<?php echo $allowance['allowance_code']; ?>]"
                                                   id="amount_<?php echo $allowance['allowance_code']; ?>"
                                                   placeholder="Amount"
                                                   min="0" step="0.01"
                                                   value="<?php echo $existing_allowances[$allowance['allowance_code']] ?? ''; ?>"
                                                   style="max-width: 120px;"
                                                   <?php echo isset($existing_allowances[$allowance['allowance_code']]) ? '' : 'disabled'; ?>>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Deductions -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-header bg-danger text-white rounded-0">
                                <i class="fas fa-minus-circle me-2"></i>Deductions (Check if applicable)
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">Select the deductions that apply to this employee. Actual amounts will be calculated during payroll processing.</p>
                                
                                <h6 class="mb-3"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Mandatory Deductions</h6>
                                <div class="row mb-4">
                                    <?php foreach ($deduction_types as $deduction): ?>
                                    <?php if ($deduction['is_mandatory']): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="deductions[<?php echo $deduction['deduction_code']; ?>]" 
                                                   value="1"
                                                   id="ded_<?php echo $deduction['deduction_code']; ?>"
                                                   <?php echo isset($existing_deductions[$deduction['deduction_code']]) || empty($existing_deductions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="ded_<?php echo $deduction['deduction_code']; ?>">
                                                <strong><?php echo htmlspecialchars($deduction['deduction_name']); ?></strong>
                                                <?php if ($deduction['percentage']): ?>
                                                <span class="badge bg-secondary ms-1"><?php echo $deduction['percentage']; ?>%</span>
                                                <?php endif; ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($deduction['description']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                
                                <h6 class="mb-3"><i class="fas fa-hand-holding-usd text-info me-2"></i>Voluntary Deductions</h6>
                                <div class="row">
                                    <?php foreach ($deduction_types as $deduction): ?>
                                    <?php if (!$deduction['is_mandatory']): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="deductions[<?php echo $deduction['deduction_code']; ?>]" 
                                                   value="1"
                                                   id="ded_<?php echo $deduction['deduction_code']; ?>"
                                                   <?php echo isset($existing_deductions[$deduction['deduction_code']]) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="ded_<?php echo $deduction['deduction_code']; ?>">
                                                <?php echo htmlspecialchars($deduction['deduction_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($deduction['description']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-lg rounded-0 w-100"
                                                onclick="return confirm('Are you sure you want to approve this staff?');">
                                            <i class="fas fa-check-circle me-2"></i>Approve Staff
                                        </button>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <button type="button" class="btn btn-danger btn-lg rounded-0 w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                            <i class="fas fa-times-circle me-2"></i>Reject
                                        </button>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <?php if ($staff['onboarding_status'] == 'pending'): ?>
                                        <button type="submit" name="action" value="mark_review" class="btn btn-info btn-lg rounded-0 w-100">
                                            <i class="fas fa-eye me-2"></i>Mark Under Review
                                        </button>
                                        <?php else: ?>
                                        <a href="staff_list.php" class="btn btn-secondary btn-lg rounded-0 w-100">
                                            <i class="fas fa-arrow-left me-2"></i>Back to List
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- Already Processed -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-secondary text-white rounded-0">
                            <i class="fas fa-briefcase me-2"></i>Employment Details
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><td class="text-muted">Job Title:</td><td><strong><?php echo htmlspecialchars($staff['job_title'] ?? '-'); ?></strong></td></tr>
                                <tr><td class="text-muted">Department:</td><td><?php 
                                    $dept_name = '-';
                                    if ($staff['department_id']) {
                                        $dres = mysqli_query($conn, "SELECT department_name FROM departments WHERE id = " . (int) $staff['department_id']);
                                        $d = $dres ? mysqli_fetch_assoc($dres) : null;
                                        $dept_name = $d['department_name'] ?? '-';
                                    }
                                    echo htmlspecialchars($dept_name);
                                ?></td></tr>
                                <tr><td class="text-muted">Employment Type:</td><td><?php echo ucfirst($staff['employment_type'] ?? '-'); ?></td></tr>
                                <tr><td class="text-muted">Start Date:</td><td><?php echo $staff['start_date'] ? date('M d, Y', strtotime($staff['start_date'])) : '-'; ?></td></tr>
                                <tr><td class="text-muted">Work Location:</td><td><?php echo ucfirst($staff['work_location'] ?? '-'); ?></td></tr>
                                <tr><td class="text-muted">Basic Salary:</td><td><strong>KES <?php echo number_format($staff['basic_salary'] ?? 0, 2); ?></strong></td></tr>
                            </table>
                            
                            <?php if ($staff['onboarding_status'] == 'rejected' && $staff['rejection_reason']): ?>
                            <div class="alert alert-danger rounded-0 mt-3">
                                <strong>Rejection Reason:</strong><br>
                                <?php echo htmlspecialchars($staff['rejection_reason']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control rounded-0" name="rejection_reason" rows="4" required
                                  placeholder="Please provide a reason for rejecting this application..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger rounded-0">
                        <i class="fas fa-times me-1"></i>Reject Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Department head mapping
const departmentHeads = {
    <?php foreach ($departments as $dept): ?>
    <?php echo $dept['id']; ?>: <?php echo $dept['department_head'] ? $dept['department_head'] : 'null'; ?>,
    <?php endforeach; ?>
};

// Auto-fill supervisor when department changes
document.getElementById('department_id').addEventListener('change', function() {
    const deptId = this.value;
    const headId = departmentHeads[deptId];
    
    if (headId) {
        document.getElementById('reporting_to').value = headId;
    }
});

// Enable/disable allowance amount based on checkbox
document.querySelectorAll('.allowance-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const code = this.dataset.code;
        const amountInput = document.getElementById('amount_' + code);
        amountInput.disabled = !this.checked;
        if (!this.checked) {
            amountInput.value = '';
        }
        calculateGross();
    });
});

// Calculate gross salary
function calculateGross() {
    const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
    let totalAllowances = 0;
    
    document.querySelectorAll('.allowance-amount').forEach(function(input) {
        if (!input.disabled) {
            totalAllowances += parseFloat(input.value) || 0;
        }
    });
    
    const gross = basic + totalAllowances;
    document.getElementById('gross_salary').value = gross.toLocaleString('en-KE', {minimumFractionDigits: 2});
}

document.getElementById('basic_salary').addEventListener('input', calculateGross);
document.querySelectorAll('.allowance-amount').forEach(function(input) {
    input.addEventListener('input', calculateGross);
});

// Initial calculation
calculateGross();
</script>

<?php require_once 'footer.php'; ?>