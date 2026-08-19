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
        
        $updated_by = intval($_SESSION['login_id'] ?? 1);
        
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
                updated_at = NOW(),
                updated_by = $updated_by
            WHERE id = $staff_id
        ";
        
        if (mysqli_query($conn, $update_sql)) {
            // Log the action
            mysqli_query($conn, "
                INSERT INTO staff_onboarding_log (staff_id, action, notes, performed_by, ip_address)
                VALUES ($staff_id, 'details_updated', 'Employment and compensation details updated', $updated_by, '{$_SERVER['REMOTE_ADDR']}')
            ");
            
            $success_message = "Staff details updated successfully!";
        } else {
            $error_message = "Failed to update: " . mysqli_error($conn);
        }
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

// Decode existing allowances/deductions
$existing_allowances = [];
$existing_deductions = [];
if (!empty($staff['allowances'])) {
    $decoded = json_decode($staff['allowances'], true);
    if (is_array($decoded)) {
        $existing_allowances = $decoded;
    }
}
if (!empty($staff['deductions'])) {
    $decoded = json_decode($staff['deductions'], true);
    if (is_array($decoded)) {
        $existing_deductions = $decoded;
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="staff_details.php?id=<?php echo $staff_id; ?>" class="btn btn-outline-secondary btn-sm rounded-0 mb-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Details
                    </a>
                    <h4 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Staff Details
                    </h4>
                </div>
                <div>
                    <span class="badge bg-dark fs-6"><?php echo htmlspecialchars($staff['staff_id']); ?></span>
                </div>
            </div>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-0">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <a href="staff_details.php?id=<?php echo $staff_id; ?>" class="btn btn-sm btn-success ms-3">View Details</a>
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
                <!-- Staff Info Summary (Left) -->
                <div class="col-lg-3">
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-body text-center py-4">
                            <?php if (!empty($staff['passport_photo']) && file_exists('../' . $staff['passport_photo'])): ?>
                            <img src="../<?php echo $staff['passport_photo']; ?>" class="rounded-circle border shadow" width="100" height="100" style="object-fit: cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center text-white mx-auto shadow" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                            
                            <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($staff['email']); ?></p>
                            <p class="mb-0"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($staff['phone']); ?></p>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-secondary text-white rounded-0">
                            <i class="fas fa-info-circle me-2"></i>Quick Info
                        </div>
                        <div class="card-body">
                            <small>
                                <p class="mb-2"><strong>National ID:</strong> <?php echo htmlspecialchars($staff['national_id']); ?></p>
                                <p class="mb-2"><strong>KRA PIN:</strong> <?php echo htmlspecialchars($staff['kra_pin']); ?></p>
                                <p class="mb-2"><strong>NSSF:</strong> <?php echo htmlspecialchars($staff['nssf_number']); ?></p>
                                <p class="mb-0"><strong>NHIF:</strong> <?php echo htmlspecialchars($staff['nhif_number']); ?></p>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Form (Right) -->
                <div class="col-lg-9">
                    <form method="POST" id="editForm">
                        
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
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control rounded-0" name="start_date" required
                                               value="<?php echo $staff['start_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Probation End Date</label>
                                        <input type="date" class="form-control rounded-0" name="probation_end_date"
                                               value="<?php echo $staff['probation_end_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Work Location</label>
                                        <select class="form-select rounded-0" name="work_location">
                                            <option value="office" <?php echo ($staff['work_location'] == 'office') ? 'selected' : ''; ?>>Office</option>
                                            <option value="remote" <?php echo ($staff['work_location'] == 'remote') ? 'selected' : ''; ?>>Remote</option>
                                            <option value="hybrid" <?php echo ($staff['work_location'] == 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Working Hours</label>
                                        <input type="text" class="form-control rounded-0" name="working_hours" 
                                               value="<?php echo htmlspecialchars($staff['working_hours'] ?? '8:00 AM - 5:00 PM'); ?>">
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
                                
                                <h6 class="text-muted mb-3"><i class="fas fa-plus-circle me-2"></i>Allowances</h6>
                                
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
                                <i class="fas fa-minus-circle me-2"></i>Deductions
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">Select the deductions that apply to this employee.</p>
                                
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
                                                   <?php echo isset($existing_deductions[$deduction['deduction_code']]) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="ded_<?php echo $deduction['deduction_code']; ?>">
                                                <strong><?php echo htmlspecialchars($deduction['deduction_name']); ?></strong>
                                                <?php if ($deduction['percentage']): ?>
                                                <span class="badge bg-secondary ms-1"><?php echo $deduction['percentage']; ?>%</span>
                                                <?php endif; ?>
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
                                <div class="d-flex justify-content-between">
                                    <a href="staff_details.php?id=<?php echo $staff_id; ?>" class="btn btn-secondary rounded-0">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg rounded-0">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

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