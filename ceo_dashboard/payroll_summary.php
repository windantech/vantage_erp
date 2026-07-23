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
$current_user_id = intval($_SESSION['login_id'] ?? 1);

// Handle approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'hr_approve') {
        mysqli_query($conn, "UPDATE payroll_periods SET hr_prepared_by = $current_user_id, hr_prepared_at = NOW() WHERE id = $period_id");
        mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) VALUES ($period_id, 'hr_approved', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
        $success_message = "HR approval recorded.";
    }
    
    if ($action == 'finance_approve') {
        mysqli_query($conn, "UPDATE payroll_periods SET finance_approved_by = $current_user_id, finance_approved_at = NOW() WHERE id = $period_id");
        mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) VALUES ($period_id, 'finance_approved', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
        $success_message = "Finance approval recorded.";
    }
    
    if ($action == 'ceo_approve') {
        mysqli_query($conn, "UPDATE payroll_periods SET ceo_approved_by = $current_user_id, ceo_approved_at = NOW(), status = 'approved' WHERE id = $period_id");
        mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) VALUES ($period_id, 'ceo_approved', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
        $success_message = "CEO approval recorded. Payroll is now approved!";
    }
    
    if ($action == 'reject') {
        $reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        mysqli_query($conn, "UPDATE payroll_periods SET status = 'open', rejected_by = $current_user_id, rejected_at = NOW(), rejection_reason = '$reason',
                            hr_prepared_by = NULL, hr_prepared_at = NULL, finance_approved_by = NULL, finance_approved_at = NULL, ceo_approved_by = NULL, ceo_approved_at = NULL 
                            WHERE id = $period_id");
        mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, notes, ip_address) VALUES ($period_id, 'rejected', $current_user_id, '$reason', '{$_SERVER['REMOTE_ADDR']}')");
        $success_message = "Payroll rejected and sent back for corrections.";
    }
    
    // Refresh period data
    $result = mysqli_query($conn, "SELECT * FROM payroll_periods WHERE id = $period_id LIMIT 1");
    if ($result) {
        $period = mysqli_fetch_assoc($result);
    }
}

// Get payroll records
$records = [];
$rec_result = mysqli_query($conn, "SELECT * FROM payroll_records WHERE period_id = $period_id ORDER BY staff_name");
if ($rec_result) {
    while ($row = mysqli_fetch_assoc($rec_result)) {
        $records[] = $row;
    }
}

// Calculate totals for statutory deductions
$totals = [
    'nssf_employee' => 0,
    'nssf_employer' => 0,
    'shif' => 0,
    'housing_levy_employee' => 0,
    'housing_levy_employer' => 0,
    'paye' => 0
];
foreach ($records as $rec) {
    $totals['nssf_employee'] += $rec['nssf_employee'];
    $totals['nssf_employer'] += $rec['nssf_employer'];
    $totals['shif'] += $rec['shif_amount'];
    $totals['housing_levy_employee'] += $rec['housing_levy_employee'];
    $totals['housing_levy_employer'] += $rec['housing_levy_employer'];
    $totals['paye'] += $rec['paye'];
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
                    <h4 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Payroll Summary</h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($period['period_name']); ?></p>
                </div>
                <div>
                    <?php if ($period['status'] == 'approved'): ?>
                    <a href="payroll_payments.php?period_id=<?php echo $period_id; ?>" class="btn btn-success rounded-0">
                        <i class="fas fa-money-bill-wave me-2"></i>Process Payments
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-0">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Approval Status -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-primary text-white rounded-0">
                    <i class="fas fa-clipboard-check me-2"></i>Approval Workflow
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <!-- HR -->
                        <div class="col-md-4">
                            <div class="<?php echo $period['hr_prepared_at'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="fas <?php echo $period['hr_prepared_at'] ? 'fa-check-circle' : 'fa-circle'; ?> fa-3x mb-2"></i>
                                <h6>HR Approval</h6>
                                <?php if ($period['hr_prepared_at']): ?>
                                <small>Approved <?php echo date('M d, Y H:i', strtotime($period['hr_prepared_at'])); ?></small>
                                <?php else: ?>
                                <form method="POST" class="mt-2">
                                    <input type="hidden" name="action" value="hr_approve">
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-0">Approve as HR</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Finance -->
                        <div class="col-md-4">
                            <div class="<?php echo $period['finance_approved_at'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="fas <?php echo $period['finance_approved_at'] ? 'fa-check-circle' : 'fa-circle'; ?> fa-3x mb-2"></i>
                                <h6>Finance Approval</h6>
                                <?php if ($period['finance_approved_at']): ?>
                                <small>Approved <?php echo date('M d, Y H:i', strtotime($period['finance_approved_at'])); ?></small>
                                <?php elseif ($period['hr_prepared_at']): ?>
                                <form method="POST" class="mt-2">
                                    <input type="hidden" name="action" value="finance_approve">
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-0">Approve as Finance</button>
                                </form>
                                <?php else: ?>
                                <small>Waiting for HR</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- CEO -->
                        <div class="col-md-4">
                            <div class="<?php echo $period['ceo_approved_at'] ? 'text-success' : 'text-muted'; ?>">
                                <i class="fas <?php echo $period['ceo_approved_at'] ? 'fa-check-circle' : 'fa-circle'; ?> fa-3x mb-2"></i>
                                <h6>CEO Approval</h6>
                                <?php if ($period['ceo_approved_at']): ?>
                                <small>Approved <?php echo date('M d, Y H:i', strtotime($period['ceo_approved_at'])); ?></small>
                                <?php elseif ($period['finance_approved_at']): ?>
                                <form method="POST" class="mt-2">
                                    <input type="hidden" name="action" value="ceo_approve">
                                    <button type="submit" class="btn btn-sm btn-success rounded-0">Final Approval (CEO)</button>
                                </form>
                                <?php else: ?>
                                <small>Waiting for Finance</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$period['ceo_approved_at'] && $period['hr_prepared_at']): ?>
                    <hr>
                    <div class="text-center">
                        <button class="btn btn-outline-danger btn-sm rounded-0" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fas fa-times me-1"></i>Reject & Return for Corrections
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 bg-primary text-white">
                        <div class="card-body">
                            <h6 class="opacity-75">Total Gross Pay</h6>
                            <h4>KES <?php echo number_format($period['total_gross'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 bg-danger text-white">
                        <div class="card-body">
                            <h6 class="opacity-75">Total Deductions</h6>
                            <h4>KES <?php echo number_format($period['total_deductions'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 bg-success text-white">
                        <div class="card-body">
                            <h6 class="opacity-75">Total Net Pay</h6>
                            <h4>KES <?php echo number_format($period['total_net'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 bg-info text-white">
                        <div class="card-body">
                            <h6 class="opacity-75">Employer Costs</h6>
                            <h4>KES <?php echo number_format($period['total_employer_costs'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statutory Deductions Summary -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-secondary text-white rounded-0">
                    <i class="fas fa-landmark me-2"></i>Statutory Deductions Summary
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col">
                            <h6 class="text-muted">PAYE (KRA)</h6>
                            <h5>KES <?php echo number_format($totals['paye'], 2); ?></h5>
                        </div>
                        <div class="col">
                            <h6 class="text-muted">NSSF (Employee)</h6>
                            <h5>KES <?php echo number_format($totals['nssf_employee'], 2); ?></h5>
                        </div>
                        <div class="col">
                            <h6 class="text-muted">NSSF (Employer)</h6>
                            <h5>KES <?php echo number_format($totals['nssf_employer'], 2); ?></h5>
                        </div>
                        <div class="col">
                            <h6 class="text-muted">SHIF</h6>
                            <h5>KES <?php echo number_format($totals['shif'], 2); ?></h5>
                        </div>
                        <div class="col">
                            <h6 class="text-muted">Housing Levy</h6>
                            <h5>KES <?php echo number_format($totals['housing_levy_employee'] + $totals['housing_levy_employer'], 2); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Records -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-dark text-white rounded-0">
                    <i class="fas fa-table me-2"></i>Payroll Details (<?php echo count($records); ?> employees)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="payrollTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Department</th>
                                    <th class="text-end">Basic</th>
                                    <th class="text-end">Allowances</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">NSSF</th>
                                    <th class="text-end">SHIF</th>
                                    <th class="text-end">Housing</th>
                                    <th class="text-end">PAYE</th>
                                    <th class="text-end">Other Ded.</th>
                                    <th class="text-end">Net Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $rec): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($rec['staff_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo $rec['staff_code']; ?></small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($rec['department_name'] ?? '-'); ?></small></td>
                                    <td class="text-end"><?php echo number_format($rec['basic_salary']); ?></td>
                                    <td class="text-end"><?php echo number_format($rec['total_allowances']); ?></td>
                                    <td class="text-end"><strong><?php echo number_format($rec['gross_pay']); ?></strong></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['nssf_employee']); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['shif_amount']); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['housing_levy_employee']); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['paye']); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['total_other_deductions']); ?></td>
                                    <td class="text-end"><strong class="text-success"><?php echo number_format($rec['net_pay']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th colspan="2">TOTALS</th>
                                    <th class="text-end"><?php echo number_format(array_sum(array_column($records, 'basic_salary'))); ?></th>
                                    <th class="text-end"><?php echo number_format(array_sum(array_column($records, 'total_allowances'))); ?></th>
                                    <th class="text-end"><?php echo number_format($period['total_gross']); ?></th>
                                    <th class="text-end"><?php echo number_format($totals['nssf_employee']); ?></th>
                                    <th class="text-end"><?php echo number_format($totals['shif']); ?></th>
                                    <th class="text-end"><?php echo number_format($totals['housing_levy_employee']); ?></th>
                                    <th class="text-end"><?php echo number_format($totals['paye']); ?></th>
                                    <th class="text-end"><?php echo number_format(array_sum(array_column($records, 'total_other_deductions'))); ?></th>
                                    <th class="text-end"><?php echo number_format($period['total_net']); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
                <input type="hidden" name="action" value="reject">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Payroll</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control rounded-0" name="rejection_reason" rows="4" required
                                  placeholder="Please provide details..."></textarea>
                    </div>
                    <div class="alert alert-warning rounded-0 mb-0">
                        <small>This will clear all approvals and send the payroll back for corrections.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-0">Reject Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>