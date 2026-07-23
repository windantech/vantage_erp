<?php
session_start();
require_once 'header.php';
require_once '../function.php';

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

if (!$period || $period['status'] != 'approved') {
    echo '<script>alert("Period not approved or not found"); window.location.href="payroll_periods.php";</script>';
    exit;
}

$success_message = '';
$error_message = '';
$current_user_id = intval($_SESSION['login_id'] ?? 1);

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['save_payment'])) {
        $payroll_record_id = intval($_POST['payroll_record_id']);
        $staff_id = intval($_POST['staff_id']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $amount = floatval($_POST['amount']);
        $transaction_reference = mysqli_real_escape_string($conn, $_POST['transaction_reference'] ?? '');
        $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
        
        // Bank details
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
        $bank_branch = mysqli_real_escape_string($conn, $_POST['bank_branch'] ?? '');
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
        $account_name = mysqli_real_escape_string($conn, $_POST['account_name'] ?? '');
        
        // M-Pesa details
        $mpesa_number = mysqli_real_escape_string($conn, $_POST['mpesa_number'] ?? '');
        $mpesa_name = mysqli_real_escape_string($conn, $_POST['mpesa_name'] ?? '');
        
        // Check if payment exists
        $existing = mysqli_query($conn, "SELECT id FROM payroll_payments WHERE period_id = $period_id AND staff_id = $staff_id LIMIT 1");
        
        if ($existing && mysqli_num_rows($existing) > 0) {
            $row = mysqli_fetch_assoc($existing);
            $sql = "UPDATE payroll_payments SET
                payment_method = '$payment_method',
                amount = $amount,
                bank_name = '$bank_name',
                bank_branch = '$bank_branch',
                account_number = '$account_number',
                account_name = '$account_name',
                mpesa_number = '$mpesa_number',
                mpesa_name = '$mpesa_name',
                transaction_reference = '$transaction_reference',
                transaction_date = '$transaction_date',
                status = 'completed',
                processed_by = $current_user_id,
                processed_at = NOW(),
                updated_at = NOW()
                WHERE id = {$row['id']}";
        } else {
            $sql = "INSERT INTO payroll_payments (
                period_id, staff_id, payroll_record_id, payment_method, amount,
                bank_name, bank_branch, account_number, account_name,
                mpesa_number, mpesa_name, transaction_reference, transaction_date,
                status, processed_by, processed_at
            ) VALUES (
                $period_id, $staff_id, $payroll_record_id, '$payment_method', $amount,
                '$bank_name', '$bank_branch', '$account_number', '$account_name',
                '$mpesa_number', '$mpesa_name', '$transaction_reference', '$transaction_date',
                'completed', $current_user_id, NOW()
            )";
        }
        
        if (mysqli_query($conn, $sql)) {
            // Update payroll record status
            mysqli_query($conn, "UPDATE payroll_records SET status = 'paid' WHERE id = $payroll_record_id");
            $success_message = "Payment recorded successfully!";
        } else {
            $error_message = "Failed to save: " . mysqli_error($conn);
        }
    }
    
    // Mark all as paid
    if (isset($_POST['mark_all_paid'])) {
        mysqli_query($conn, "UPDATE payroll_periods SET status = 'paid' WHERE id = $period_id");
        mysqli_query($conn, "INSERT INTO payroll_approval_log (period_id, action, performed_by, ip_address) VALUES ($period_id, 'paid', $current_user_id, '{$_SERVER['REMOTE_ADDR']}')");
        $success_message = "Payroll marked as paid!";
        
        // Refresh period
        $result = mysqli_query($conn, "SELECT * FROM payroll_periods WHERE id = $period_id LIMIT 1");
        $period = mysqli_fetch_assoc($result);
    }
}

// Get payroll records with payment status
$records = [];
$rec_result = mysqli_query($conn, "
    SELECT pr.*, pp.id as payment_id, pp.payment_method, pp.transaction_reference, pp.transaction_date, pp.status as payment_status,
           pp.bank_name, pp.account_number, pp.mpesa_number
    FROM payroll_records pr
    LEFT JOIN payroll_payments pp ON pr.id = pp.payroll_record_id
    WHERE pr.period_id = $period_id
    ORDER BY pr.staff_name
");
if ($rec_result) {
    while ($row = mysqli_fetch_assoc($rec_result)) {
        $records[] = $row;
    }
}

// Count paid vs unpaid
$paid_count = 0;
$unpaid_count = 0;
$paid_amount = 0;
$unpaid_amount = 0;
foreach ($records as $rec) {
    if ($rec['payment_status'] == 'completed') {
        $paid_count++;
        $paid_amount += $rec['net_pay'];
    } else {
        $unpaid_count++;
        $unpaid_amount += $rec['net_pay'];
    }
}

// Get selected record for payment entry
$selected_record = null;
if (isset($_GET['record_id'])) {
    $record_id = intval($_GET['record_id']);
    foreach ($records as $rec) {
        if ($rec['id'] == $record_id) {
            $selected_record = $rec;
            break;
        }
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
                    <a href="payroll_summary.php?period_id=<?php echo $period_id; ?>" class="btn btn-outline-secondary btn-sm rounded-0 mb-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Summary
                    </a>
                    <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Process Payments</h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($period['period_name']); ?></p>
                </div>
                <?php if ($unpaid_count == 0 && $period['status'] == 'approved'): ?>
                <form method="POST">
                    <button type="submit" name="mark_all_paid" class="btn btn-success rounded-0" onclick="return confirm('Mark this payroll as fully paid?');">
                        <i class="fas fa-check-double me-2"></i>Mark Payroll as Paid
                    </button>
                </form>
                <?php endif; ?>
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
            
            <!-- Status Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-success">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Paid</h6>
                            <h4 class="text-success"><?php echo $paid_count; ?> <small class="fs-6">employees</small></h4>
                            <small>KES <?php echo number_format($paid_amount, 2); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-warning">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h4 class="text-warning"><?php echo $unpaid_count; ?> <small class="fs-6">employees</small></h4>
                            <small>KES <?php echo number_format($unpaid_amount, 2); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-primary">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Total Employees</h6>
                            <h4 class="text-primary"><?php echo count($records); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-info">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Total Net Pay</h6>
                            <h4 class="text-info">KES <?php echo number_format($period['total_net'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Staff List -->
                <div class="col-lg-5">
                    <div class="card shadow-sm rounded-0">
                        <div class="card-header bg-dark text-white rounded-0">
                            <i class="fas fa-users me-2"></i>Employees
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Name</th>
                                        <th class="text-end">Net Pay</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $rec): ?>
                                    <tr class="<?php echo (isset($_GET['record_id']) && $_GET['record_id'] == $rec['id']) ? 'table-primary' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($rec['staff_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $rec['staff_code']; ?></small>
                                        </td>
                                        <td class="text-end">
                                            <strong>KES <?php echo number_format($rec['net_pay']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($rec['payment_status'] == 'completed'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Paid</span>
                                            <br><small class="text-muted"><?php echo ucfirst($rec['payment_method']); ?></small>
                                            <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?period_id=<?php echo $period_id; ?>&record_id=<?php echo $rec['id']; ?>" 
                                               class="btn btn-sm <?php echo $rec['payment_status'] == 'completed' ? 'btn-outline-secondary' : 'btn-primary'; ?> rounded-0">
                                                <i class="fas fa-<?php echo $rec['payment_status'] == 'completed' ? 'eye' : 'money-bill'; ?>"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Form -->
                <div class="col-lg-7">
                    <?php if ($selected_record): ?>
                    <div class="card shadow-sm rounded-0">
                        <div class="card-header bg-primary text-white rounded-0">
                            <i class="fas fa-credit-card me-2"></i>Record Payment
                        </div>
                        <div class="card-body">
                            <!-- Employee Info -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5><?php echo htmlspecialchars($selected_record['staff_name']); ?></h5>
                                    <p class="text-muted mb-0"><?php echo $selected_record['staff_code']; ?> | <?php echo htmlspecialchars($selected_record['department_name'] ?? '-'); ?></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted">Net Pay Amount</small>
                                    <h3 class="text-success mb-0">KES <?php echo number_format($selected_record['net_pay'], 2); ?></h3>
                                </div>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="payroll_record_id" value="<?php echo $selected_record['id']; ?>">
                                <input type="hidden" name="staff_id" value="<?php echo $selected_record['staff_id']; ?>">
                                <input type="hidden" name="amount" value="<?php echo $selected_record['net_pay']; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-select rounded-0" name="payment_method" id="paymentMethod" required onchange="togglePaymentFields()">
                                            <option value="">-- Select --</option>
                                            <option value="bank" <?php echo ($selected_record['payment_method'] ?? '') == 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                                            <option value="mpesa" <?php echo ($selected_record['payment_method'] ?? '') == 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                                            <option value="cash" <?php echo ($selected_record['payment_method'] ?? '') == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="cheque" <?php echo ($selected_record['payment_method'] ?? '') == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control rounded-0" name="transaction_date" required
                                               value="<?php echo $selected_record['transaction_date'] ?? date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <!-- Bank Fields -->
                                <div id="bankFields" style="display: none;">
                                    <h6 class="text-muted mb-3"><i class="fas fa-university me-2"></i>Bank Details</h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Bank Name</label>
                                            <input type="text" class="form-control rounded-0" name="bank_name" 
                                                   value="<?php echo htmlspecialchars($selected_record['bank_name'] ?? ''); ?>"
                                                   placeholder="e.g., Equity Bank">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Branch</label>
                                            <input type="text" class="form-control rounded-0" name="bank_branch"
                                                   placeholder="e.g., Westlands">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" class="form-control rounded-0" name="account_number"
                                                   value="<?php echo htmlspecialchars($selected_record['account_number'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" class="form-control rounded-0" name="account_name"
                                                   value="<?php echo htmlspecialchars($selected_record['staff_name']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- M-Pesa Fields -->
                                <div id="mpesaFields" style="display: none;">
                                    <h6 class="text-muted mb-3"><i class="fas fa-mobile-alt me-2"></i>M-Pesa Details</h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">M-Pesa Number</label>
                                            <input type="text" class="form-control rounded-0" name="mpesa_number"
                                                   value="<?php echo htmlspecialchars($selected_record['mpesa_number'] ?? ''); ?>"
                                                   placeholder="e.g., 0712345678">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Registered Name</label>
                                            <input type="text" class="form-control rounded-0" name="mpesa_name"
                                                   value="<?php echo htmlspecialchars($selected_record['staff_name']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Transaction Reference</label>
                                    <input type="text" class="form-control rounded-0" name="transaction_reference"
                                           value="<?php echo htmlspecialchars($selected_record['transaction_reference'] ?? ''); ?>"
                                           placeholder="e.g., TXN123456789 or M-Pesa code">
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="?period_id=<?php echo $period_id; ?>" class="btn btn-secondary rounded-0">Cancel</a>
                                    <button type="submit" name="save_payment" class="btn btn-success rounded-0">
                                        <i class="fas fa-save me-2"></i>Save Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($period['status'] == 'paid'): ?>
                    <div class="card shadow-sm rounded-0">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4 class="text-success">Payroll Fully Paid!</h4>
                            <p class="text-muted">All employees have been paid for <?php echo htmlspecialchars($period['period_name']); ?></p>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <div class="card shadow-sm rounded-0">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
                            <h5>Select an Employee</h5>
                            <p class="text-muted">Click on an employee from the list to record their payment.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function togglePaymentFields() {
    const method = document.getElementById('paymentMethod').value;
    document.getElementById('bankFields').style.display = (method === 'bank') ? 'block' : 'none';
    document.getElementById('mpesaFields').style.display = (method === 'mpesa') ? 'block' : 'none';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePaymentFields();
});
</script>

<?php require_once 'footer.php'; ?>