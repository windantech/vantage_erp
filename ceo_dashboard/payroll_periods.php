<?php
session_start();
require_once 'header.php';
require_once '../function.php';
require_once 'includes/payroll_functions.php';

$success_message = '';
$error_message = '';
$current_user_id = intval($_SESSION['login_id'] ?? 1);

// Handle create new period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_period'])) {
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    
    $result = createPayrollPeriod($conn, $month, $year, $current_user_id);
    
    if ($result['success']) {
        $success_message = "Payroll period created successfully!";
        mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) 
                            VALUES ({$result['period_id']}, 'created', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
    } else {
        $error_message = $result['error'];
    }
}

// Handle Push to Expenses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['push_to_expenses'])) {
    $period_id = intval($_POST['period_id']);
    
    // Get period details
    $period_result = mysqli_query($conn, "SELECT * FROM payroll_periods WHERE id = $period_id LIMIT 1");
    $period = mysqli_fetch_assoc($period_result);
    
    if ($period && $period['status'] == 'paid') {
        // Check if already posted
        $already_posted = false;
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM payroll_periods LIKE 'expenses_posted'");
        if (mysqli_num_rows($check_col) > 0) {
            if ($period['expenses_posted'] == 1) {
                $already_posted = true;
                $error_message = "This payroll has already been posted to expenses.";
            }
        }
        
        if (!$already_posted) {
            $expense_date = $period['payment_date'] ?: $period['end_date'];
            $expense_date_formatted = date('Y-m-d', strtotime($expense_date));
            $period_name = $period['period_name'];
            $recorded_by = $current_user_id;
            $category = 'Salaries & Wages';
            $expenses_created = 0;
            
            // Exchange rate KES to USD
            $exchange_rate = 129;
            
            mysqli_begin_transaction($conn);
            
            try {
                // 1. POST NET PAY (Total salaries disbursed to employees)
                $net_pay_kes = floatval($period['total_net']);
                $net_pay_usd = round($net_pay_kes / $exchange_rate, 2);
                
                if ($net_pay_kes > 0) {
                    $expense_name = mysqli_real_escape_string($conn, "$period_name - Staff Net Salaries");
                    $description = mysqli_real_escape_string($conn, "Net salaries paid to " . intval($period['total_employees']) . " employees for $period_name (KES " . number_format($net_pay_kes, 2) . " @ rate $exchange_rate)");
                    $notes = mysqli_real_escape_string($conn, "Auto-posted from Payroll System - Period ID: $period_id | KES " . number_format($net_pay_kes, 2));
                    
                    $sql = "INSERT INTO expenses (category, expense_name, amount, expense_date, description, payment_method, vendor_supplier, notes, recorded_by) 
                            VALUES ('$category', '$expense_name', $net_pay_usd, '$expense_date_formatted', '$description', 'Bank Transfer', 'Staff Payroll', '$notes', $recorded_by)";
                    
                    if (mysqli_query($conn, $sql)) {
                        $expense_id = mysqli_insert_id($conn);
                        mysqli_query($conn, "INSERT INTO payroll_expense_records (period_id, expense_id, expense_type, description, amount) 
                                            VALUES ($period_id, $expense_id, 'net_pay', '$expense_name', $net_pay_kes)");
                        $expenses_created++;
                    }
                }
                
                // 2. POST REMITTANCES (PAYE, NSSF, SHIF, Housing Levy, etc.)
                $remittances_result = mysqli_query($conn, "
                    SELECT pr.*, rr.recipient_name 
                    FROM payroll_remittances pr 
                    LEFT JOIN remittance_recipients rr ON pr.recipient_id = rr.id
                    WHERE pr.period_id = $period_id AND pr.status = 'paid'
                ");
                
                if ($remittances_result) {
                    while ($rem = mysqli_fetch_assoc($remittances_result)) {
                        $rem_amount_kes = floatval($rem['total_amount']);
                        $rem_amount_usd = round($rem_amount_kes / $exchange_rate, 2);
                        
                        $rem_type = $rem['remittance_type'];
                        $rem_name = $rem['remittance_name'];
                        $vendor = $rem['recipient_name'] ?: $rem_name;
                        $payment_ref = $rem['payment_reference'] ?: '';
                        $payment_method = $rem['payment_method'] ?: 'Bank Transfer';
                        
                        $expense_name = mysqli_real_escape_string($conn, "$period_name - $rem_name");
                        $description = mysqli_real_escape_string($conn, "$rem_name remittance for $period_name (KES " . number_format($rem_amount_kes, 2) . " @ rate $exchange_rate)");
                        $vendor = mysqli_real_escape_string($conn, $vendor);
                        $payment_ref = mysqli_real_escape_string($conn, $payment_ref);
                        $payment_method = mysqli_real_escape_string($conn, $payment_method);
                        $notes = mysqli_real_escape_string($conn, "Auto-posted from Payroll System - Period ID: $period_id | KES " . number_format($rem_amount_kes, 2));
                        
                        // Map remittance type to expense type
                        $expense_type = strtolower($rem_type);
                        
                        $sql = "INSERT INTO expenses (category, expense_name, amount, expense_date, description, payment_method, reference_number, vendor_supplier, notes, recorded_by) 
                                VALUES ('$category', '$expense_name', $rem_amount_usd, '$expense_date_formatted', '$description', '$payment_method', " . 
                                ($payment_ref ? "'$payment_ref'" : "NULL") . ", '$vendor', '$notes', $recorded_by)";
                        
                        if (mysqli_query($conn, $sql)) {
                            $expense_id = mysqli_insert_id($conn);
                            mysqli_query($conn, "INSERT INTO payroll_expense_records (period_id, expense_id, expense_type, description, amount) 
                                                VALUES ($period_id, $expense_id, '$expense_type', '$expense_name', $rem_amount_kes)");
                            $expenses_created++;
                        }
                    }
                }
                
                // 3. Mark period as expenses posted
                mysqli_query($conn, "UPDATE payroll_periods SET expenses_posted = 1, expenses_posted_by = $current_user_id, expenses_posted_at = NOW() WHERE id = $period_id");
                
                // Log action
                mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, notes, performed_by, ip_address) 
                                    VALUES ($period_id, 'expenses_posted', 'Posted $expenses_created expense records', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
                
                mysqli_commit($conn);
                $success_message = "Successfully posted $expenses_created expense records to the expenses system!";
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_message = "Failed to post expenses: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Period must be fully paid before posting to expenses.";
    }
}

// Handle open period
if (isset($_GET['action']) && $_GET['action'] == 'open' && isset($_GET['id'])) {
    $period_id = intval($_GET['id']);
    mysqli_query($conn, "UPDATE payroll_periods SET status = 'open' WHERE id = $period_id AND status = 'draft'");
    mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) 
                        VALUES ($period_id, 'opened', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
    $success_message = "Period opened for input.";
}

// Handle close period
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['id'])) {
    $period_id = intval($_GET['id']);
    mysqli_query($conn, "UPDATE payroll_periods SET status = 'closed' WHERE id = $period_id");
    mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) 
                        VALUES ($period_id, 'closed', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
    $success_message = "Period closed.";
}

// Get all periods with payment status
$periods = [];
$result = mysqli_query($conn, "
    SELECT pp.*, 
           hr.fullname as hr_name,
           fin.fullname as finance_name,
           ceo.fullname as ceo_name,
           exp_user.fullname as expenses_posted_by_name
    FROM payroll_periods pp
    LEFT JOIN registered_users hr ON pp.hr_prepared_by = hr.id
    LEFT JOIN registered_users fin ON pp.finance_approved_by = fin.id
    LEFT JOIN registered_users ceo ON pp.ceo_approved_by = ceo.id
    LEFT JOIN registered_users exp_user ON pp.expenses_posted_by = exp_user.id
    ORDER BY pp.period_year DESC, pp.period_month DESC
    LIMIT 24
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Check payment completion status
        $period_id = $row['id'];
        
        // Check employee payments
        $pay_check = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid FROM payroll_records WHERE period_id = $period_id");
        $pay_status = mysqli_fetch_assoc($pay_check);
        $row['employees_total'] = $pay_status['total'] ?? 0;
        $row['employees_paid'] = $pay_status['paid'] ?? 0;
        $row['employees_all_paid'] = ($pay_status['total'] > 0 && $pay_status['total'] == $pay_status['paid']);
        
        // Check remittances
        $rem_check = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid FROM payroll_remittances WHERE period_id = $period_id");
        $rem_status = mysqli_fetch_assoc($rem_check);
        $row['remittances_total'] = $rem_status['total'] ?? 0;
        $row['remittances_paid'] = $rem_status['paid'] ?? 0;
        $row['remittances_all_paid'] = ($rem_status['total'] == 0 || $rem_status['total'] == $rem_status['paid']);
        
        // Can push to expenses?
        $row['can_push_expenses'] = ($row['status'] == 'paid' && $row['employees_all_paid'] && $row['remittances_all_paid'] && empty($row['expenses_posted']));
        $row['expenses_already_posted'] = !empty($row['expenses_posted']);
        
        $periods[] = $row;
    }
}

// Status badges
$status_badges = [
    'draft' => '<span class="badge bg-secondary">Draft</span>',
    'open' => '<span class="badge bg-info">Open for Input</span>',
    'processing' => '<span class="badge bg-warning text-dark">Processing</span>',
    'pending_approval' => '<span class="badge bg-primary">Pending Approval</span>',
    'approved' => '<span class="badge bg-success">Approved</span>',
    'paid' => '<span class="badge bg-success"><i class="fas fa-check"></i> Paid</span>',
    'closed' => '<span class="badge bg-dark">Closed</span>'
];
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Payroll Periods</h4>
                    <p class="text-muted mb-0">Create and manage monthly payroll periods</p>
                </div>
                <button class="btn btn-primary rounded-0" data-bs-toggle="modal" data-bs-target="#createPeriodModal">
                    <i class="fas fa-plus me-2"></i>New Period
                </button>
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
            
            <!-- Periods Table -->
            <div class="card shadow-sm rounded-0">
                <div class="card-header bg-dark text-white rounded-0">
                    <i class="fas fa-list me-2"></i>Payroll Periods
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Period</th>
                                    <th>Date Range</th>
                                    <th>Status</th>
                                    <th class="text-end">Employees</th>
                                    <th class="text-end">Gross Pay</th>
                                    <th class="text-end">Net Pay</th>
                                    <th>Approval</th>
                                    <th>Payment Status</th>
                                    <th width="180">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($periods)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                                        No payroll periods found. Create one to get started.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($periods as $period): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($period['period_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo $period['period_code']; ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('M d', strtotime($period['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($period['end_date'])); ?>
                                        </small>
                                    </td>
                                    <td><?php echo $status_badges[$period['status']] ?? $period['status']; ?></td>
                                    <td class="text-end"><?php echo number_format($period['total_employees']); ?></td>
                                    <td class="text-end">
                                        <?php if ($period['total_gross'] > 0): ?>
                                        <strong>KES <?php echo number_format($period['total_gross'], 2); ?></strong>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($period['total_net'] > 0): ?>
                                        <strong class="text-success">KES <?php echo number_format($period['total_net'], 2); ?></strong>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($period['hr_prepared_at']): ?>
                                            <span class="text-success" title="HR: <?php echo $period['hr_name']; ?>"><i class="fas fa-check-circle"></i> HR</span>
                                            <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-circle"></i> HR</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($period['finance_approved_at']): ?>
                                            <span class="text-success ms-2" title="Finance: <?php echo $period['finance_name']; ?>"><i class="fas fa-check-circle"></i> FIN</span>
                                            <?php else: ?>
                                            <span class="text-muted ms-2"><i class="fas fa-circle"></i> FIN</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($period['ceo_approved_at']): ?>
                                            <span class="text-success ms-2" title="CEO: <?php echo $period['ceo_name']; ?>"><i class="fas fa-check-circle"></i> CEO</span>
                                            <?php else: ?>
                                            <span class="text-muted ms-2"><i class="fas fa-circle"></i> CEO</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($period['status'] == 'paid' || $period['status'] == 'closed'): ?>
                                        <small>
                                            <!-- Employee Payments -->
                                            <?php if ($period['employees_all_paid']): ?>
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Salaries</span>
                                            <?php elseif ($period['employees_total'] > 0): ?>
                                            <span class="text-warning"><i class="fas fa-clock"></i> <?php echo $period['employees_paid']; ?>/<?php echo $period['employees_total']; ?></span>
                                            <?php endif; ?>
                                            
                                            <!-- Remittances -->
                                            <?php if ($period['remittances_total'] > 0): ?>
                                            <br>
                                            <?php if ($period['remittances_all_paid']): ?>
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Remittances</span>
                                            <?php else: ?>
                                            <span class="text-warning"><i class="fas fa-clock"></i> <?php echo $period['remittances_paid']; ?>/<?php echo $period['remittances_total']; ?></span>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <!-- Expenses Posted -->
                                            <?php if ($period['expenses_already_posted']): ?>
                                            <br><span class="text-primary"><i class="fas fa-file-invoice-dollar"></i> Expenses ✓</span>
                                            <?php endif; ?>
                                        </small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($period['status'] == 'draft'): ?>
                                        <a href="?action=open&id=<?php echo $period['id']; ?>" class="btn btn-sm btn-info rounded-0" title="Open for Input">
                                            <i class="fas fa-folder-open"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($period['status'], ['open', 'processing'])): ?>
                                        <a href="payroll_inputs.php?period_id=<?php echo $period['id']; ?>" class="btn btn-sm btn-primary rounded-0" title="Enter Inputs">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="payroll_process.php?period_id=<?php echo $period['id']; ?>" class="btn btn-sm btn-warning rounded-0" title="Process Payroll">
                                            <i class="fas fa-calculator"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($period['status'], ['processing', 'pending_approval', 'approved', 'paid', 'closed'])): ?>
                                        <a href="payroll_summary.php?period_id=<?php echo $period['id']; ?>" class="btn btn-sm btn-success rounded-0" title="View Summary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($period['status'] == 'approved'): ?>
                                        <a href="payroll_payments.php?period_id=<?php echo $period['id']; ?>" class="btn btn-sm btn-dark rounded-0" title="Process Payments">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($period['status'] == 'paid'): ?>
                                        <a href="payroll_remittances.php?period_id=<?php echo $period['id']; ?>" class="btn btn-sm btn-secondary rounded-0" title="Remittances">
                                            <i class="fas fa-landmark"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <!-- Push to Expenses Button -->
                                        <?php if ($period['can_push_expenses']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-0" title="Push to Expenses" 
                                                onclick="confirmPushExpenses(<?php echo $period['id']; ?>, '<?php echo addslashes($period['period_name']); ?>', <?php echo $period['total_net']; ?>)">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </button>
                                        <?php elseif ($period['expenses_already_posted']): ?>
                                        <span class="btn btn-sm btn-outline-secondary rounded-0 disabled" title="Already Posted">
                                            <i class="fas fa-check"></i>
                                        </span>
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
            
            <!-- Workflow Guide -->
            <div class="card shadow-sm rounded-0 mt-4">
                <div class="card-header bg-light rounded-0">
                    <i class="fas fa-info-circle me-2"></i>Payroll Workflow
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between text-center">
                        <div class="flex-fill">
                            <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">1</div>
                            <p class="mb-0 small"><strong>Create Period</strong><br>Draft</p>
                        </div>
                        <div class="flex-fill">
                            <i class="fas fa-arrow-right text-muted"></i>
                        </div>
                        <div class="flex-fill">
                            <div class="rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">2</div>
                            <p class="mb-0 small"><strong>Open & Input</strong><br>Enter Variables</p>
                        </div>
                        <div class="flex-fill">
                            <i class="fas fa-arrow-right text-muted"></i>
                        </div>
                        <div class="flex-fill">
                            <div class="rounded-circle bg-warning text-dark d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">3</div>
                            <p class="mb-0 small"><strong>Process</strong><br>Calculate</p>
                        </div>
                        <div class="flex-fill">
                            <i class="fas fa-arrow-right text-muted"></i>
                        </div>
                        <div class="flex-fill">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">4</div>
                            <p class="mb-0 small"><strong>Approve</strong><br>HR → FIN → CEO</p>
                        </div>
                        <div class="flex-fill">
                            <i class="fas fa-arrow-right text-muted"></i>
                        </div>
                        <div class="flex-fill">
                            <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">5</div>
                            <p class="mb-0 small"><strong>Pay</strong><br>Salaries + Remit</p>
                        </div>
                        <div class="flex-fill">
                            <i class="fas fa-arrow-right text-muted"></i>
                        </div>
                        <div class="flex-fill">
                            <div class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">6</div>
                            <p class="mb-0 small"><strong>Post</strong><br>To Expenses</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Create Period Modal -->
<div class="modal fade" id="createPeriodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Create Payroll Period</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Month <span class="text-danger">*</span></label>
                            <select class="form-select rounded-0" name="month" required>
                                <?php 
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                          'July', 'August', 'September', 'October', 'November', 'December'];
                                $current_month = date('n');
                                foreach ($months as $i => $m): 
                                ?>
                                <option value="<?php echo $i + 1; ?>" <?php echo ($i + 1 == $current_month) ? 'selected' : ''; ?>>
                                    <?php echo $m; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year <span class="text-danger">*</span></label>
                            <select class="form-select rounded-0" name="year" required>
                                <?php 
                                $current_year = date('Y');
                                for ($y = $current_year - 1; $y <= $current_year + 1; $y++): 
                                ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == $current_year) ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_period" class="btn btn-primary rounded-0">
                        <i class="fas fa-plus me-1"></i>Create Period
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Push to Expenses Confirmation Modal -->
<div class="modal fade" id="pushExpensesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <form method="POST">
                <input type="hidden" name="period_id" id="expensesPeriodId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Push to Expenses</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to post the following payroll expenses:</p>
                    
                    <div class="alert alert-info rounded-0">
                        <strong>Period:</strong> <span id="expensesPeriodName"></span><br>
                        <strong>Net Salaries (KES):</strong> KES <span id="expensesNetPayKES"></span><br>
                        <strong>Net Salaries (USD):</strong> USD <span id="expensesNetPayUSD"></span>
                        <br><small class="text-muted">Exchange Rate: 1 USD = 129 KES</small>
                    </div>
                    
                    <p class="mb-2">This will create expense records for:</p>
                    <ul>
                        <li><strong>Staff Net Salaries</strong> (Total net pay)</li>
                        <li><strong>PAYE</strong> (Tax remittance to KRA)</li>
                        <li><strong>NSSF</strong> (Employee + Employer contributions)</li>
                        <li><strong>SHIF</strong> (Health insurance)</li>
                        <li><strong>Housing Levy</strong> (Employee + Employer)</li>
                        <li><strong>Other remittances</strong> (SACCO, HELB, Loans, etc.)</li>
                    </ul>
                    
                    <div class="alert alert-warning rounded-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> All amounts will be converted to <strong>USD</strong> (÷ 129) before posting. Original KES amounts are stored in the description for reference.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="push_to_expenses" class="btn btn-success rounded-0">
                        <i class="fas fa-check me-1"></i>Confirm & Post to Expenses (USD)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmPushExpenses(periodId, periodName, netPayKES) {
    var exchangeRate = 129;
    var netPayUSD = (netPayKES / exchangeRate).toFixed(2);
    
    document.getElementById('expensesPeriodId').value = periodId;
    document.getElementById('expensesPeriodName').textContent = periodName;
    document.getElementById('expensesNetPayKES').textContent = netPayKES.toLocaleString('en-KE', {minimumFractionDigits: 2});
    document.getElementById('expensesNetPayUSD').textContent = parseFloat(netPayUSD).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    var modal = new bootstrap.Modal(document.getElementById('pushExpensesModal'));
    modal.show();
}
</script>

<?php require_once 'footer.php'; ?>