<?php
session_start();
require_once 'header.php';
require_once '../function.php';
require_once 'includes/payroll_functions.php';

$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;

if (!$period_id) {
    echo '<script>alert("Invalid period"); window.location.href="payroll_periods.php";</script>';
    exit;
}

// Get period details
$period = null;
$result = mysqli_query($conn, "SELECT * FROM payroll_periods WHERE id = $period_id LIMIT 1");
if ($result && mysqli_num_rows($result) > 0) {
    $period = mysqli_fetch_assoc($result);
}

if (!$period) {
    echo '<script>alert("Period not found"); window.location.href="payroll_periods.php";</script>';
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inputs'])) {
    $staff_id = intval($_POST['staff_id']);
    $entered_by = intval($_SESSION['login_id'] ?? 1);
    
    // Prepare values
    $days_worked = floatval($_POST['days_worked'] ?? 0) ?: 'NULL';
    $days_absent = floatval($_POST['days_absent'] ?? 0);
    $unpaid_leave_days = floatval($_POST['unpaid_leave_days'] ?? 0);
    $overtime_normal = floatval($_POST['overtime_normal'] ?? 0);
    $overtime_weekend = floatval($_POST['overtime_weekend'] ?? 0);
    $overtime_holiday = floatval($_POST['overtime_holiday'] ?? 0);
    $bonus = floatval($_POST['bonus'] ?? 0);
    $commission = floatval($_POST['commission'] ?? 0);
    $other_earnings = floatval($_POST['other_earnings'] ?? 0);
    $other_earnings_desc = mysqli_real_escape_string($conn, $_POST['other_earnings_description'] ?? '');
    $salary_advance = floatval($_POST['salary_advance'] ?? 0);
    $loan_deduction = floatval($_POST['loan_deduction'] ?? 0);
    $sacco_deduction = floatval($_POST['sacco_deduction'] ?? 0);
    $helb_deduction = floatval($_POST['helb_deduction'] ?? 0);
    $other_deductions = floatval($_POST['other_deductions'] ?? 0);
    $other_deductions_desc = mysqli_real_escape_string($conn, $_POST['other_deductions_description'] ?? '');
    $insurance_premium = floatval($_POST['insurance_premium'] ?? 0);
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    // Check if exists
    $existing = mysqli_query($conn, "SELECT id FROM payroll_inputs WHERE period_id = $period_id AND staff_id = $staff_id LIMIT 1");
    
    if ($existing && mysqli_num_rows($existing) > 0) {
        // Update
        $row = mysqli_fetch_assoc($existing);
        $sql = "UPDATE payroll_inputs SET
            days_worked = $days_worked,
            days_absent = $days_absent,
            unpaid_leave_days = $unpaid_leave_days,
            overtime_normal = $overtime_normal,
            overtime_weekend = $overtime_weekend,
            overtime_holiday = $overtime_holiday,
            bonus = $bonus,
            commission = $commission,
            other_earnings = $other_earnings,
            other_earnings_description = '$other_earnings_desc',
            salary_advance = $salary_advance,
            loan_deduction = $loan_deduction,
            sacco_deduction = $sacco_deduction,
            helb_deduction = $helb_deduction,
            other_deductions = $other_deductions,
            other_deductions_description = '$other_deductions_desc',
            insurance_premium = $insurance_premium,
            notes = '$notes',
            updated_by = $entered_by,
            updated_at = NOW()
            WHERE id = {$row['id']}";
    } else {
        // Insert
        $sql = "INSERT INTO payroll_inputs (
            period_id, staff_id, days_worked, days_absent, unpaid_leave_days,
            overtime_normal, overtime_weekend, overtime_holiday,
            bonus, commission, other_earnings, other_earnings_description,
            salary_advance, loan_deduction, sacco_deduction, helb_deduction, other_deductions, other_deductions_description,
            insurance_premium, notes, entered_by
        ) VALUES (
            $period_id, $staff_id, $days_worked, $days_absent, $unpaid_leave_days,
            $overtime_normal, $overtime_weekend, $overtime_holiday,
            $bonus, $commission, $other_earnings, '$other_earnings_desc',
            $salary_advance, $loan_deduction, $sacco_deduction, $helb_deduction, $other_deductions, '$other_deductions_desc',
            $insurance_premium, '$notes', $entered_by
        )";
    }
    
    if (mysqli_query($conn, $sql)) {
        $success_message = "Inputs saved successfully!";
    } else {
        $error_message = "Failed to save: " . mysqli_error($conn);
    }
}

// Get active staff
$staff_list = [];
$staff_result = mysqli_query($conn, "
    SELECT s.id, s.staff_id, s.full_name, s.job_title, s.basic_salary, d.department_name,
           pi.id as input_id, pi.overtime_normal, pi.overtime_weekend, pi.overtime_holiday,
           pi.bonus, pi.commission, pi.other_earnings, pi.salary_advance, pi.loan_deduction,
           pi.sacco_deduction, pi.helb_deduction, pi.other_deductions
    FROM staff s
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN payroll_inputs pi ON s.id = pi.staff_id AND pi.period_id = $period_id
    WHERE s.onboarding_status = 'active' AND s.basic_salary > 0
    ORDER BY s.full_name
");
if ($staff_result) {
    while ($row = mysqli_fetch_assoc($staff_result)) {
        $staff_list[] = $row;
    }
}

// Get selected staff details for editing
$selected_staff = null;
$selected_inputs = null;
if (isset($_GET['staff_id'])) {
    $staff_id = intval($_GET['staff_id']);
    
    $s_result = mysqli_query($conn, "
        SELECT s.*, d.department_name 
        FROM staff s 
        LEFT JOIN departments d ON s.department_id = d.id 
        WHERE s.id = $staff_id LIMIT 1
    ");
    if ($s_result && mysqli_num_rows($s_result) > 0) {
        $selected_staff = mysqli_fetch_assoc($s_result);
    }
    
    $i_result = mysqli_query($conn, "SELECT * FROM payroll_inputs WHERE period_id = $period_id AND staff_id = $staff_id LIMIT 1");
    if ($i_result && mysqli_num_rows($i_result) > 0) {
        $selected_inputs = mysqli_fetch_assoc($i_result);
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
                    <a href="payroll_periods.php" class="btn btn-outline-secondary btn-sm rounded-0 mb-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Periods
                    </a>
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Payroll Inputs</h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($period['period_name']); ?></p>
                </div>
                <div>
                    <a href="payroll_process.php?period_id=<?php echo $period_id; ?>" class="btn btn-warning rounded-0">
                        <i class="fas fa-calculator me-2"></i>Process Payroll
                    </a>
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
                <!-- Staff List -->
                <div class="col-lg-4">
                    <div class="card shadow-sm rounded-0">
                        <div class="card-header bg-dark text-white rounded-0">
                            <i class="fas fa-users me-2"></i>Staff (<?php echo count($staff_list); ?>)
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            <div class="list-group list-group-flush">
                                <?php foreach ($staff_list as $staff): ?>
                                <a href="?period_id=<?php echo $period_id; ?>&staff_id=<?php echo $staff['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo (isset($_GET['staff_id']) && $_GET['staff_id'] == $staff['id']) ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                            <br><small class="<?php echo (isset($_GET['staff_id']) && $_GET['staff_id'] == $staff['id']) ? '' : 'text-muted'; ?>">
                                                <?php echo htmlspecialchars($staff['job_title'] ?? $staff['department_name'] ?? '-'); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small>KES <?php echo number_format($staff['basic_salary']); ?></small>
                                            <?php if ($staff['input_id']): ?>
                                            <br><span class="badge bg-success">Input ✓</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Input Form -->
                <div class="col-lg-8">
                    <?php if ($selected_staff): ?>
                    <form method="POST">
                        <input type="hidden" name="staff_id" value="<?php echo $selected_staff['id']; ?>">
                        
                        <!-- Staff Info -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-header bg-primary text-white rounded-0">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($selected_staff['full_name']); ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo $selected_staff['staff_id']; ?></span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">Department</small>
                                        <p class="mb-0"><strong><?php echo htmlspecialchars($selected_staff['department_name'] ?? '-'); ?></strong></p>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Job Title</small>
                                        <p class="mb-0"><strong><?php echo htmlspecialchars($selected_staff['job_title'] ?? '-'); ?></strong></p>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Basic Salary</small>
                                        <p class="mb-0"><strong class="text-success">KES <?php echo number_format($selected_staff['basic_salary'], 2); ?></strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overtime -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-header bg-info text-white rounded-0">
                                <i class="fas fa-clock me-2"></i>Overtime Hours
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Normal Overtime (1.5x)</label>
                                        <input type="number" class="form-control rounded-0" name="overtime_normal" 
                                               step="0.5" min="0" value="<?php echo $selected_inputs['overtime_normal'] ?? 0; ?>">
                                        <small class="text-muted">Hours</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Weekend Overtime (2x)</label>
                                        <input type="number" class="form-control rounded-0" name="overtime_weekend" 
                                               step="0.5" min="0" value="<?php echo $selected_inputs['overtime_weekend'] ?? 0; ?>">
                                        <small class="text-muted">Hours</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Holiday Overtime (2x)</label>
                                        <input type="number" class="form-control rounded-0" name="overtime_holiday" 
                                               step="0.5" min="0" value="<?php echo $selected_inputs['overtime_holiday'] ?? 0; ?>">
                                        <small class="text-muted">Hours</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Earnings -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-header bg-success text-white rounded-0">
                                <i class="fas fa-plus-circle me-2"></i>Additional Earnings (KES)
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Bonus</label>
                                        <input type="number" class="form-control rounded-0" name="bonus" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['bonus'] ?? 0; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Commission</label>
                                        <input type="number" class="form-control rounded-0" name="commission" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['commission'] ?? 0; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Other Earnings</label>
                                        <input type="number" class="form-control rounded-0" name="other_earnings" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['other_earnings'] ?? 0; ?>">
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Other Earnings Description</label>
                                    <input type="text" class="form-control rounded-0" name="other_earnings_description" 
                                           value="<?php echo htmlspecialchars($selected_inputs['other_earnings_description'] ?? ''); ?>"
                                           placeholder="e.g., Airtime reimbursement">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Deductions -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-header bg-danger text-white rounded-0">
                                <i class="fas fa-minus-circle me-2"></i>Additional Deductions (KES)
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Salary Advance</label>
                                        <input type="number" class="form-control rounded-0" name="salary_advance" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['salary_advance'] ?? 0; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Loan Deduction</label>
                                        <input type="number" class="form-control rounded-0" name="loan_deduction" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['loan_deduction'] ?? 0; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">SACCO Deduction</label>
                                        <input type="number" class="form-control rounded-0" name="sacco_deduction" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['sacco_deduction'] ?? 0; ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">HELB Deduction</label>
                                        <input type="number" class="form-control rounded-0" name="helb_deduction" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['helb_deduction'] ?? 0; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Other Deductions</label>
                                        <input type="number" class="form-control rounded-0" name="other_deductions" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['other_deductions'] ?? 0; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Insurance Premium</label>
                                        <input type="number" class="form-control rounded-0" name="insurance_premium" 
                                               step="0.01" min="0" value="<?php echo $selected_inputs['insurance_premium'] ?? 0; ?>">
                                        <small class="text-muted">For insurance relief</small>
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Other Deductions Description</label>
                                    <input type="text" class="form-control rounded-0" name="other_deductions_description" 
                                           value="<?php echo htmlspecialchars($selected_inputs['other_deductions_description'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="card shadow-sm rounded-0 mb-4">
                            <div class="card-body">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control rounded-0" name="notes" rows="2"><?php echo htmlspecialchars($selected_inputs['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Submit -->
                        <div class="d-flex justify-content-end gap-2 mb-4">
                            <a href="?period_id=<?php echo $period_id; ?>" class="btn btn-secondary rounded-0">Cancel</a>
                            <button type="submit" name="save_inputs" class="btn btn-primary rounded-0">
                                <i class="fas fa-save me-2"></i>Save Inputs
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <div class="card shadow-sm rounded-0">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
                            <h5>Select a Staff Member</h5>
                            <p class="text-muted">Click on a staff member from the list to enter their monthly inputs.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>