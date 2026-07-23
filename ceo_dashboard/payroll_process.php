<?php
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
$process_result = null;

// Handle process payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payroll'])) {
    $processed_by = intval($_SESSION['login_id'] ?? 1);
    
    $process_result = processFullPayroll($conn, $period_id, $processed_by);
    
    if ($process_result['success']) {
        $success_message = "Payroll processed successfully! {$process_result['processed']} employees calculated.";
        
        // Log action
        mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, notes, ip_address) 
                            VALUES ($period_id, 'processed', $processed_by, 'Processed {$process_result['processed']} employees', '{$_SERVER['REMOTE_ADDR']}')");
        
        // Refresh period data
        $result = mysqli_query($conn, "SELECT * FROM payroll_periods WHERE id = $period_id LIMIT 1");
        if ($result) {
            $period = mysqli_fetch_assoc($result);
        }
    } else {
        $error_message = "Processing completed with errors. {$process_result['processed']} succeeded, {$process_result['failed']} failed.";
    }
}

// Handle submit for approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_approval'])) {
    $submitted_by = intval($_SESSION['login_id'] ?? 1);
    
    mysqli_query($conn, "UPDATE payroll_periods SET status = 'pending_approval', hr_prepared_by = $submitted_by, hr_prepared_at = NOW() WHERE id = $period_id");
    mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) 
                        VALUES ($period_id, 'submitted', $submitted_by, '{$_SERVER['REMOTE_ADDR']}')");
    
    $success_message = "Payroll submitted for approval!";
    ?>
    <script>window.location.href="payroll_summary.php?period_id=<?php echo $period_id?>";</script>
    <?php
    // header("Location: payroll_summary.php?period_id=$period_id");
    // exit;
}

// Get eligible staff count
$staff_count = 0;
$count_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM staff WHERE onboarding_status = 'active' AND basic_salary > 0");
if ($count_result && $row = mysqli_fetch_assoc($count_result)) {
    $staff_count = $row['cnt'];
}

// Get processed records count
$processed_count = 0;
$proc_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payroll_records WHERE period_id = $period_id");
if ($proc_result && $row = mysqli_fetch_assoc($proc_result)) {
    $processed_count = $row['cnt'];
}

// Get inputs count
$inputs_count = 0;
$inp_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payroll_inputs WHERE period_id = $period_id");
if ($inp_result && $row = mysqli_fetch_assoc($inp_result)) {
    $inputs_count = $row['cnt'];
}

// Get sample calculations (first 5)
$sample_records = [];
$sample_result = mysqli_query($conn, "
    SELECT * FROM payroll_records 
    WHERE period_id = $period_id 
    ORDER BY staff_name 
    LIMIT 5
");
if ($sample_result) {
    while ($row = mysqli_fetch_assoc($sample_result)) {
        $sample_records[] = $row;
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
                    <h4 class="mb-0"><i class="fas fa-calculator me-2"></i>Process Payroll</h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($period['period_name']); ?></p>
                </div>
            </div>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-0">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-warning alert-dismissible fade show rounded-0">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                <?php if ($process_result && !empty($process_result['errors'])): ?>
                <ul class="mt-2 mb-0">
                    <?php foreach (array_slice($process_result['errors'], 0, 5) as $err): ?>
                    <li><small><?php echo htmlspecialchars($err); ?></small></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Stats Cards -->
                <div class="col-md-3 mb-4">
                    <div class="card shadow-sm rounded-0 h-100 border-start border-4 border-primary">
                        <div class="card-body text-center">
                            <h2 class="text-primary mb-1"><?php echo $staff_count; ?></h2>
                            <small class="text-muted">Eligible Staff</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card shadow-sm rounded-0 h-100 border-start border-4 border-info">
                        <div class="card-body text-center">
                            <h2 class="text-info mb-1"><?php echo $inputs_count; ?></h2>
                            <small class="text-muted">Staff with Inputs</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card shadow-sm rounded-0 h-100 border-start border-4 border-success">
                        <div class="card-body text-center">
                            <h2 class="text-success mb-1"><?php echo $processed_count; ?></h2>
                            <small class="text-muted">Processed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card shadow-sm rounded-0 h-100 border-start border-4 border-warning">
                        <div class="card-body text-center">
                            <h2 class="text-warning mb-1">KES <?php echo number_format($period['total_net']); ?></h2>
                            <small class="text-muted">Total Net Pay</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Process Action -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-warning text-dark rounded-0">
                    <i class="fas fa-cog me-2"></i>Payroll Processing
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5>Run Payroll Calculation</h5>
                            <p class="text-muted mb-0">
                                This will calculate gross pay, statutory deductions (NSSF, SHIF, Housing Levy, PAYE), 
                                and net pay for all <strong><?php echo $staff_count; ?></strong> active staff members.
                            </p>
                            <?php if ($processed_count > 0): ?>
                            <p class="text-warning mb-0 mt-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong><?php echo $processed_count; ?></strong> records already exist. Running again will recalculate and update them.
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <form method="POST" class="d-inline">
                                <button type="submit" name="process_payroll" class="btn btn-warning btn-lg rounded-0"
                                        onclick="return confirm('This will calculate payroll for all active staff. Continue?');">
                                    <i class="fas fa-play me-2"></i>Process Payroll
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($processed_count > 0): ?>
            <!-- Summary -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-success text-white rounded-0">
                    <i class="fas fa-chart-pie me-2"></i>Payroll Summary
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Gross</h6>
                            <h4>KES <?php echo number_format($period['total_gross'], 2); ?></h4>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Deductions</h6>
                            <h4 class="text-danger">KES <?php echo number_format($period['total_deductions'], 2); ?></h4>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Total Net Pay</h6>
                            <h4 class="text-success">KES <?php echo number_format($period['total_net'], 2); ?></h4>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted">Employer Costs</h6>
                            <h4 class="text-info">KES <?php echo number_format($period['total_employer_costs'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sample Records -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-dark text-white rounded-0 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>Sample Records (First 5)</span>
                    <a href="payroll_summary.php?period_id=<?php echo $period_id; ?>" class="btn btn-sm btn-light rounded-0">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th class="text-end">Basic</th>
                                    <th class="text-end">Allowances</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">NSSF</th>
                                    <th class="text-end">SHIF</th>
                                    <th class="text-end">PAYE</th>
                                    <th class="text-end">Net Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sample_records as $rec): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($rec['staff_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo $rec['staff_code']; ?></small>
                                    </td>
                                    <td class="text-end"><?php echo number_format($rec['basic_salary']); ?></td>
                                    <td class="text-end"><?php echo number_format($rec['total_allowances']); ?></td>
                                    <td class="text-end"><strong><?php echo number_format($rec['gross_pay']); ?></strong></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['nssf_employee']); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['shif_amount']); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['paye']); ?></td>
                                    <td class="text-end"><strong class="text-success"><?php echo number_format($rec['net_pay']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Submit for Approval -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Ready to Submit?</h5>
                            <p class="text-muted mb-0">Once submitted, payroll will go through approval workflow: HR → Finance → CEO</p>
                        </div>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="submit_approval" class="btn btn-primary btn-lg rounded-0"
                                    onclick="return confirm('Submit payroll for approval?');">
                                <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Navigation -->
            <div class="d-flex justify-content-between mb-4">
                <a href="payroll_inputs.php?period_id=<?php echo $period_id; ?>" class="btn btn-outline-secondary rounded-0">
                    <i class="fas fa-arrow-left me-2"></i>Back to Inputs
                </a>
                <?php if ($processed_count > 0): ?>
                <a href="payroll_summary.php?period_id=<?php echo $period_id; ?>" class="btn btn-success rounded-0">
                    <i class="fas fa-eye me-2"></i>View Full Summary
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>