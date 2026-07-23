<?php
session_start();
require_once 'header.php';
require_once '../function.php';

$success_message = '';
$error_message = '';
$current_user_id = intval($_SESSION['login_id'] ?? 1);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Generate remittances for a period
    if (isset($_POST['generate_remittances'])) {
        $period_id = intval($_POST['period_id']);
        
        // Get period details
        $period_result = mysqli_query($conn, "SELECT * FROM payroll_periods WHERE id = $period_id LIMIT 1");
        $period = mysqli_fetch_assoc($period_result);
        
        if ($period) {
            // Calculate due date (9th of following month)
            $next_month = date('Y-m-d', strtotime($period['end_date'] . ' +9 days'));
            $due_date = date('Y-m-09', strtotime($next_month));
            
            // Get totals from payroll records
            $totals_result = mysqli_query($conn, "
                SELECT 
                    SUM(paye) as total_paye,
                    SUM(nssf_employee) as nssf_employee,
                    SUM(nssf_employer) as nssf_employer,
                    SUM(shif_amount) as total_shif,
                    SUM(housing_levy_employee) as housing_employee,
                    SUM(housing_levy_employer) as housing_employer,
                    SUM(sacco_deduction) as total_sacco,
                    SUM(helb_deduction) as total_helb,
                    SUM(loan_deduction) as total_loan,
                    SUM(salary_advance) as total_advance,
                    SUM(other_deductions) as total_other,
                    COUNT(*) as emp_count
                FROM payroll_records 
                WHERE period_id = $period_id
            ");
            $totals = mysqli_fetch_assoc($totals_result);
            
            $generated = 0;
            
            // Get recipients for each deduction type
            $recipients = [];
            $rec_result = mysqli_query($conn, "SELECT * FROM remittance_recipients WHERE is_active = 1");
            while ($row = mysqli_fetch_assoc($rec_result)) {
                $recipients[$row['deduction_type']] = $row;
            }
            
            // PAYE
            if ($totals['total_paye'] > 0) {
                $paye = $totals['total_paye'];
                $rec_id = isset($recipients['PAYE']) ? $recipients['PAYE']['id'] : 'NULL';
                mysqli_query($conn, "
                    INSERT INTO payroll_remittances (period_id, recipient_id, remittance_type, remittance_name, employee_amount, employer_amount, total_amount, employee_count, due_date, created_by)
                    VALUES ($period_id, $rec_id, 'PAYE', 'PAYE (KRA)', $paye, 0, $paye, {$totals['emp_count']}, '$due_date', $current_user_id)
                    ON DUPLICATE KEY UPDATE total_amount = $paye, employee_amount = $paye
                ");
                $generated++;
            }
            
            // NSSF
            $nssf_emp = $totals['nssf_employee'] ?? 0;
            $nssf_er = $totals['nssf_employer'] ?? 0;
            $nssf_total = $nssf_emp + $nssf_er;
            if ($nssf_total > 0) {
                $rec_id = isset($recipients['NSSF']) ? $recipients['NSSF']['id'] : 'NULL';
                mysqli_query($conn, "
                    INSERT INTO payroll_remittances (period_id, recipient_id, remittance_type, remittance_name, employee_amount, employer_amount, total_amount, employee_count, due_date, created_by)
                    VALUES ($period_id, $rec_id, 'NSSF', 'NSSF', $nssf_emp, $nssf_er, $nssf_total, {$totals['emp_count']}, '$due_date', $current_user_id)
                    ON DUPLICATE KEY UPDATE total_amount = $nssf_total, employee_amount = $nssf_emp, employer_amount = $nssf_er
                ");
                $generated++;
            }
            
            // SHIF
            $shif = $totals['total_shif'] ?? 0;
            if ($shif > 0) {
                $rec_id = isset($recipients['SHIF']) ? $recipients['SHIF']['id'] : 'NULL';
                mysqli_query($conn, "
                    INSERT INTO payroll_remittances (period_id, recipient_id, remittance_type, remittance_name, employee_amount, employer_amount, total_amount, employee_count, due_date, created_by)
                    VALUES ($period_id, $rec_id, 'SHIF', 'SHIF (SHA)', $shif, 0, $shif, {$totals['emp_count']}, '$due_date', $current_user_id)
                    ON DUPLICATE KEY UPDATE total_amount = $shif, employee_amount = $shif
                ");
                $generated++;
            }
            
            // Housing Levy
            $housing_emp = $totals['housing_employee'] ?? 0;
            $housing_er = $totals['housing_employer'] ?? 0;
            $housing_total = $housing_emp + $housing_er;
            if ($housing_total > 0) {
                $rec_id = isset($recipients['HOUSING_LEVY']) ? $recipients['HOUSING_LEVY']['id'] : 'NULL';
                mysqli_query($conn, "
                    INSERT INTO payroll_remittances (period_id, recipient_id, remittance_type, remittance_name, employee_amount, employer_amount, total_amount, employee_count, due_date, created_by)
                    VALUES ($period_id, $rec_id, 'HOUSING_LEVY', 'Housing Levy', $housing_emp, $housing_er, $housing_total, {$totals['emp_count']}, '$due_date', $current_user_id)
                    ON DUPLICATE KEY UPDATE total_amount = $housing_total, employee_amount = $housing_emp, employer_amount = $housing_er
                ");
                $generated++;
            }
            
            // HELB
            $helb = $totals['total_helb'] ?? 0;
            if ($helb > 0) {
                $rec_id = isset($recipients['HELB']) ? $recipients['HELB']['id'] : 'NULL';
                // Count employees with HELB
                $helb_count_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payroll_records WHERE period_id = $period_id AND helb_deduction > 0");
                $helb_count = mysqli_fetch_assoc($helb_count_result)['cnt'];
                mysqli_query($conn, "
                    INSERT INTO payroll_remittances (period_id, recipient_id, remittance_type, remittance_name, employee_amount, employer_amount, total_amount, employee_count, due_date, created_by)
                    VALUES ($period_id, $rec_id, 'HELB', 'HELB', $helb, 0, $helb, $helb_count, '$due_date', $current_user_id)
                    ON DUPLICATE KEY UPDATE total_amount = $helb, employee_amount = $helb
                ");
                $generated++;
            }
            
            // SACCO
            $sacco = $totals['total_sacco'] ?? 0;
            if ($sacco > 0) {
                $rec_id = isset($recipients['SACCO']) ? $recipients['SACCO']['id'] : 'NULL';
                $sacco_count_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payroll_records WHERE period_id = $period_id AND sacco_deduction > 0");
                $sacco_count = mysqli_fetch_assoc($sacco_count_result)['cnt'];
                mysqli_query($conn, "
                    INSERT INTO payroll_remittances (period_id, recipient_id, remittance_type, remittance_name, employee_amount, employer_amount, total_amount, employee_count, due_date, created_by)
                    VALUES ($period_id, $rec_id, 'SACCO', 'SACCO Contributions', $sacco, 0, $sacco, $sacco_count, '$due_date', $current_user_id)
                    ON DUPLICATE KEY UPDATE total_amount = $sacco, employee_amount = $sacco
                ");
                $generated++;
            }
            
            // Loan Deductions
            $loan = $totals['total_loan'] ?? 0;
            if ($loan > 0) {
                $rec_id = isset($recipients['LOAN']) ? $recipients['LOAN']['id'] : 'NULL';
                $loan_count_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payroll_records WHERE period_id = $period_id AND loan_deduction > 0");
                $loan_count = mysqli_fetch_assoc($loan_count_result)['cnt'];
                mysqli_query($conn, "
                    INSERT INTO payroll_remittances (period_id, recipient_id, remittance_type, remittance_name, employee_amount, employer_amount, total_amount, employee_count, due_date, created_by)
                    VALUES ($period_id, $rec_id, 'LOAN', 'Loan Repayments', $loan, 0, $loan, $loan_count, '$due_date', $current_user_id)
                    ON DUPLICATE KEY UPDATE total_amount = $loan, employee_amount = $loan
                ");
                $generated++;
            }
            
            $success_message = "Generated $generated remittances for " . $period['period_name'];
        }
    }
    
    // Record payment
    if (isset($_POST['record_payment'])) {
        $remittance_id = intval($_POST['remittance_id']);
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $payment_reference = mysqli_real_escape_string($conn, $_POST['payment_reference']);
        $payment_amount = floatval($_POST['payment_amount']);
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
        $receipt_number = mysqli_real_escape_string($conn, $_POST['receipt_number'] ?? '');
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        
        // Handle receipt upload
        $receipt_file = '';
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == 0) {
            $upload_dir = '../uploads/remittances/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
            $file_name = 'receipt_' . $remittance_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $file_path)) {
                $receipt_file = 'uploads/remittances/' . $file_name;
            }
        }
        
        $receipt_sql = $receipt_file ? ", receipt_file = '$receipt_file'" : "";
        
        $sql = "UPDATE payroll_remittances SET 
            status = 'paid',
            payment_date = '$payment_date',
            payment_method = '$payment_method',
            payment_reference = '$payment_reference',
            payment_amount = $payment_amount,
            bank_name = '$bank_name',
            receipt_number = '$receipt_number',
            notes = '$notes',
            paid_by = $current_user_id,
            paid_at = NOW()
            $receipt_sql
            WHERE id = $remittance_id";
        
        if (mysqli_query($conn, $sql)) {
            mysqli_query($conn, "
                INSERT INTO payroll_remittance_log (remittance_id, action, old_status, new_status, notes, performed_by, ip_address)
                VALUES ($remittance_id, 'payment_recorded', 'pending', 'paid', 'Ref: $payment_reference', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')
            ");
            $success_message = "Payment recorded successfully!";
        } else {
            $error_message = "Failed to record payment: " . mysqli_error($conn);
        }
    }
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_period = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;

// Build query
$where_clauses = [];
if ($filter_status) {
    if ($filter_status == 'overdue') {
        $where_clauses[] = "pr.due_date < CURDATE() AND pr.status != 'paid'";
    } else {
        $where_clauses[] = "pr.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
    }
}
if ($filter_type) {
    $where_clauses[] = "pr.remittance_type = '" . mysqli_real_escape_string($conn, $filter_type) . "'";
}
if ($filter_period) {
    $where_clauses[] = "pr.period_id = $filter_period";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get remittances
$remittances = [];
$rem_result = mysqli_query($conn, "
    SELECT pr.*, pp.period_name, pp.period_code,
           rr.recipient_name as recipient, rr.bank_name as rec_bank, rr.account_number as rec_account, rr.paybill_number,
           CASE 
               WHEN pr.status = 'paid' THEN 'paid'
               WHEN pr.due_date < CURDATE() AND pr.status != 'paid' THEN 'overdue'
               ELSE pr.status
           END as display_status
    FROM payroll_remittances pr
    JOIN payroll_periods pp ON pr.period_id = pp.id
    LEFT JOIN remittance_recipients rr ON pr.recipient_id = rr.id
    $where_sql
    ORDER BY pr.due_date DESC, pr.remittance_type
    LIMIT 100
");
if ($rem_result) {
    while ($row = mysqli_fetch_assoc($rem_result)) {
        $remittances[] = $row;
    }
}

// Get summary stats
$stats = ['pending' => 0, 'overdue' => 0, 'paid' => 0, 'pending_amount' => 0, 'overdue_amount' => 0];
$stats_result = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN status != 'paid' AND due_date >= CURDATE() THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status != 'paid' AND due_date >= CURDATE() THEN total_amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN total_amount ELSE 0 END) as overdue_amount
    FROM payroll_remittances
");
if ($stats_result && $row = mysqli_fetch_assoc($stats_result)) {
    $stats = array_merge($stats, $row);
}

// Get periods for dropdown
$periods = [];
$per_result = mysqli_query($conn, "SELECT id, period_name FROM payroll_periods WHERE status IN ('processing', 'pending_approval', 'approved', 'paid', 'closed') ORDER BY period_year DESC, period_month DESC LIMIT 24");
if ($per_result) {
    while ($row = mysqli_fetch_assoc($per_result)) {
        $periods[] = $row;
    }
}

// Status badges
$status_badges = [
    'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
    'processing' => '<span class="badge bg-info">Processing</span>',
    'paid' => '<span class="badge bg-success">Paid</span>',
    'overdue' => '<span class="badge bg-danger">Overdue</span>',
    'partially_paid' => '<span class="badge bg-secondary">Partial</span>'
];

// Type colors
$type_colors = [
    'PAYE' => 'danger',
    'NSSF' => 'primary',
    'SHIF' => 'success',
    'HOUSING_LEVY' => 'warning',
    'HELB' => 'info',
    'SACCO' => 'primary',
    'LOAN' => 'dark',
    'MORTGAGE' => 'secondary',
    'INSURANCE' => 'info',
    'OTHER' => 'secondary'
];
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-landmark me-2"></i>Payroll Remittances</h4>
                    <p class="text-muted mb-0">Track all deduction payments - PAYE, NSSF, SHIF, SACCOs, Loans, etc.</p>
                </div>
                <div>
                    <a href="remittance_recipients.php" class="btn btn-outline-secondary rounded-0 me-2">
                        <i class="fas fa-building me-1"></i>Recipients
                    </a>
                    <button class="btn btn-primary rounded-0" data-bs-toggle="modal" data-bs-target="#generateModal">
                        <i class="fas fa-plus me-2"></i>Generate Remittances
                    </button>
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
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-danger h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Overdue</h6>
                            <h3 class="text-danger mb-1"><?php echo intval($stats['overdue']); ?></h3>
                            <small>KES <?php echo number_format($stats['overdue_amount'], 2); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-warning h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h3 class="text-warning mb-1"><?php echo intval($stats['pending']); ?></h3>
                            <small>KES <?php echo number_format($stats['pending_amount'], 2); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-success h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Paid</h6>
                            <h3 class="text-success mb-1"><?php echo intval($stats['paid']); ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-primary h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Total Outstanding</h6>
                            <h3 class="text-primary mb-0">KES <?php echo number_format($stats['pending_amount'] + $stats['overdue_amount'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Period</label>
                            <select class="form-select rounded-0" name="period_id" onchange="this.form.submit()">
                                <option value="">-- All Periods --</option>
                                <?php foreach ($periods as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($filter_period == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['period_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Status</label>
                            <select class="form-select rounded-0" name="status" onchange="this.form.submit()">
                                <option value="">-- All --</option>
                                <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="overdue" <?php echo ($filter_status == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                                <option value="paid" <?php echo ($filter_status == 'paid') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Type</label>
                            <select class="form-select rounded-0" name="type" onchange="this.form.submit()">
                                <option value="">-- All --</option>
                                <optgroup label="Statutory">
                                    <option value="PAYE" <?php echo ($filter_type == 'PAYE') ? 'selected' : ''; ?>>PAYE</option>
                                    <option value="NSSF" <?php echo ($filter_type == 'NSSF') ? 'selected' : ''; ?>>NSSF</option>
                                    <option value="SHIF" <?php echo ($filter_type == 'SHIF') ? 'selected' : ''; ?>>SHIF</option>
                                    <option value="HOUSING_LEVY" <?php echo ($filter_type == 'HOUSING_LEVY') ? 'selected' : ''; ?>>Housing Levy</option>
                                    <option value="HELB" <?php echo ($filter_type == 'HELB') ? 'selected' : ''; ?>>HELB</option>
                                </optgroup>
                                <optgroup label="Voluntary">
                                    <option value="SACCO" <?php echo ($filter_type == 'SACCO') ? 'selected' : ''; ?>>SACCO</option>
                                    <option value="LOAN" <?php echo ($filter_type == 'LOAN') ? 'selected' : ''; ?>>Loan</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a href="payroll_remittances.php" class="btn btn-outline-secondary rounded-0">
                                <i class="fas fa-sync me-1"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Remittances Table -->
            <div class="card shadow-sm rounded-0">
                <div class="card-header bg-dark text-white rounded-0">
                    <i class="fas fa-list me-2"></i>Remittances (<?php echo count($remittances); ?>)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Period</th>
                                    <th>Type</th>
                                    <th>Recipient</th>
                                    <th class="text-center">Employees</th>
                                    <th class="text-end">Employee</th>
                                    <th class="text-end">Employer</th>
                                    <th class="text-end">Total</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Payment Ref</th>
                                    <th width="100">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($remittances)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No remittances found. Generate from a payroll period to get started.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($remittances as $rem): ?>
                                <?php 
                                    $is_overdue = ($rem['due_date'] < date('Y-m-d') && $rem['status'] != 'paid');
                                    $display_status = $is_overdue ? 'overdue' : $rem['status'];
                                ?>
                                <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($rem['period_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $type_colors[$rem['remittance_type']] ?? 'secondary'; ?>">
                                            <?php echo htmlspecialchars($rem['remittance_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($rem['remittance_name']); ?></small>
                                        <?php if ($rem['rec_bank']): ?>
                                        <br><small class="text-muted"><?php echo $rem['rec_bank']; ?> - <?php echo $rem['rec_account']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $rem['employee_count']; ?></td>
                                    <td class="text-end"><?php echo number_format($rem['employee_amount'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($rem['employer_amount'], 2); ?></td>
                                    <td class="text-end"><strong><?php echo number_format($rem['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($rem['due_date'])); ?>
                                        <?php if ($is_overdue): ?>
                                        <br><small class="text-danger"><?php echo abs(floor((strtotime($rem['due_date']) - time()) / 86400)); ?> days late</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $status_badges[$display_status] ?? $display_status; ?></td>
                                    <td>
                                        <?php if ($rem['payment_reference']): ?>
                                        <small><?php echo htmlspecialchars($rem['payment_reference']); ?></small>
                                        <?php if ($rem['receipt_file']): ?>
                                        <br><a href="../<?php echo $rem['receipt_file']; ?>" target="_blank" class="small text-success"><i class="fas fa-file"></i> Receipt</a>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rem['status'] != 'paid'): ?>
                                        <button class="btn btn-sm btn-success rounded-0" 
                                                onclick="openPaymentModal(<?php echo $rem['id']; ?>, '<?php echo addslashes($rem['remittance_name']); ?>', <?php echo $rem['total_amount']; ?>, '<?php echo addslashes($rem['period_name']); ?>')">
                                            <i class="fas fa-check"></i> Pay
                                        </button>
                                        <?php else: ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i></span>
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
</section>

<!-- Generate Modal -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-0">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-cogs me-2"></i>Generate Remittances</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Payroll Period <span class="text-danger">*</span></label>
                        <select class="form-select rounded-0" name="period_id" required>
                            <option value="">-- Select Period --</option>
                            <?php foreach ($periods as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['period_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-info rounded-0 mb-0">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            This will generate remittances for:<br>
                            <strong>Statutory:</strong> PAYE, NSSF, SHIF, Housing Levy, HELB<br>
                            <strong>Voluntary:</strong> SACCO, Loans
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_remittances" class="btn btn-primary rounded-0">
                        <i class="fas fa-cogs me-1"></i>Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-0">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="remittance_id" id="paymentRemittanceId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-money-check me-2"></i>Record Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info rounded-0">
                        <strong id="paymentType">PAYE</strong> - <span id="paymentPeriod">January 2026</span>
                        <br>Amount: <strong>KES <span id="paymentAmount">0.00</span></strong>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control rounded-0" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount Paid <span class="text-danger">*</span></label>
                            <input type="number" class="form-control rounded-0" name="payment_amount" id="paymentAmountInput" required step="0.01">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select rounded-0" name="payment_method" required>
                                <option value="">-- Select --</option>
                                <option value="iTax">iTax (Online)</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="RTGS">RTGS</option>
                                <option value="EFT">EFT</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Portal">Online Portal</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control rounded-0" name="bank_name">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Reference <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-0" name="payment_reference" required placeholder="PRN / Transaction ID">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" class="form-control rounded-0" name="receipt_number">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Upload Receipt</label>
                        <input type="file" class="form-control rounded-0" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control rounded-0" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="record_payment" class="btn btn-success rounded-0">
                        <i class="fas fa-save me-1"></i>Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPaymentModal(id, type, amount, period) {
    document.getElementById('paymentRemittanceId').value = id;
    document.getElementById('paymentType').textContent = type;
    document.getElementById('paymentPeriod').textContent = period;
    document.getElementById('paymentAmount').textContent = amount.toLocaleString('en-KE', {minimumFractionDigits: 2});
    document.getElementById('paymentAmountInput').value = amount;
    
    var modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}
</script>

<?php require_once 'footer.php'; ?>