<?php
session_start();
require_once 'header.php';
require_once '../function.php';

// Get filter parameters
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

// Get available periods
$periods = [];
$per_result = mysqli_query($conn, "SELECT id, period_code, period_name FROM payroll_periods WHERE status IN ('approved', 'paid', 'closed') ORDER BY period_year DESC, period_month DESC LIMIT 12");
if ($per_result) {
    while ($row = mysqli_fetch_assoc($per_result)) {
        $periods[] = $row;
    }
}

// Auto-select latest period if none selected
if (!$period_id && !empty($periods)) {
    $period_id = $periods[0]['id'];
}

// Get period details
$period = null;
if ($period_id) {
    $result = mysqli_query($conn, "SELECT * FROM payroll_periods WHERE id = $period_id LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $period = mysqli_fetch_assoc($result);
    }
}

// Get payroll records for selected period
$records = [];
if ($period_id) {
    $rec_result = mysqli_query($conn, "SELECT * FROM payroll_records WHERE period_id = $period_id ORDER BY staff_name");
    if ($rec_result) {
        while ($row = mysqli_fetch_assoc($rec_result)) {
            $records[] = $row;
        }
    }
}

// Get selected payslip
$selected_record = null;
if ($staff_id && $period_id) {
    foreach ($records as $rec) {
        if ($rec['staff_id'] == $staff_id) {
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
                    <h4 class="mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Payslips</h4>
                    <p class="text-muted mb-0">View and print employee payslips</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Pay Period</label>
                            <select class="form-select rounded-0" name="period_id" onchange="this.form.submit()">
                                <option value="">-- Select Period --</option>
                                <?php foreach ($periods as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($period_id == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['period_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Employee</label>
                            <select class="form-select rounded-0" name="staff_id" onchange="this.form.submit()">
                                <option value="">-- All Employees --</option>
                                <?php foreach ($records as $rec): ?>
                                <option value="<?php echo $rec['staff_id']; ?>" <?php echo ($staff_id == $rec['staff_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rec['staff_name']); ?> (<?php echo $rec['staff_code']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <?php if ($period_id): ?>
                            <a href="?period_id=<?php echo $period_id; ?>" class="btn btn-outline-secondary rounded-0">
                                <i class="fas fa-sync me-1"></i>Reset
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!$period_id): ?>
            <div class="alert alert-info rounded-0">
                <i class="fas fa-info-circle me-2"></i>Please select a pay period to view payslips.
            </div>
            
            <?php elseif ($selected_record): ?>
            <!-- Single Payslip View -->
            <div class="card shadow-sm rounded-0 mb-4" id="payslipCard">
                <div class="card-header bg-dark text-white rounded-0 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-alt me-2"></i>Payslip - <?php echo htmlspecialchars($period['period_name']); ?></span>
                    <button class="btn btn-sm btn-light rounded-0" onclick="printPayslip()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
                <div class="card-body" id="payslipContent">
                    <!-- Company Header -->
                    <div class="text-center border-bottom pb-3 mb-3">
                        <h4 class="mb-1">VANTAGE AFRICA SCHOOL OF LEADERSHIP</h4>
                        <p class="text-muted mb-0">P.O. Box 12345, Nairobi, Kenya</p>
                        <h5 class="mt-3 mb-0">PAYSLIP - <?php echo strtoupper($period['period_name']); ?></h5>
                    </div>
                    
                    <!-- Employee Details -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" width="40%">Employee Name:</td><td><strong><?php echo htmlspecialchars($selected_record['staff_name']); ?></strong></td></tr>
                                <tr><td class="text-muted">Staff ID:</td><td><?php echo htmlspecialchars($selected_record['staff_code']); ?></td></tr>
                                <tr><td class="text-muted">Department:</td><td><?php echo htmlspecialchars($selected_record['department_name'] ?? '-'); ?></td></tr>
                                <tr><td class="text-muted">Job Title:</td><td><?php echo htmlspecialchars($selected_record['job_title'] ?? '-'); ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" width="40%">KRA PIN:</td><td><?php echo htmlspecialchars($selected_record['kra_pin'] ?? '-'); ?></td></tr>
                                <tr><td class="text-muted">NSSF No:</td><td><?php echo htmlspecialchars($selected_record['nssf_number'] ?? '-'); ?></td></tr>
                                <tr><td class="text-muted">NHIF No:</td><td><?php echo htmlspecialchars($selected_record['nhif_number'] ?? '-'); ?></td></tr>
                                <tr><td class="text-muted">Pay Period:</td><td><?php echo htmlspecialchars($period['period_name']); ?></td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Earnings -->
                        <div class="col-md-6">
                            <div class="card rounded-0 mb-3">
                                <div class="card-header bg-success text-white py-2 rounded-0">
                                    <strong>EARNINGS</strong>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <tr><td>Basic Salary</td><td class="text-end"><?php echo number_format($selected_record['basic_salary'], 2); ?></td></tr>
                                        <?php if ($selected_record['house_allowance'] > 0): ?>
                                        <tr><td>House Allowance</td><td class="text-end"><?php echo number_format($selected_record['house_allowance'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['transport_allowance'] > 0): ?>
                                        <tr><td>Transport Allowance</td><td class="text-end"><?php echo number_format($selected_record['transport_allowance'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['other_allowances'] > 0): ?>
                                        <tr><td>Other Allowances</td><td class="text-end"><?php echo number_format($selected_record['other_allowances'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['overtime_amount'] > 0): ?>
                                        <tr><td>Overtime</td><td class="text-end"><?php echo number_format($selected_record['overtime_amount'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['bonus'] > 0): ?>
                                        <tr><td>Bonus</td><td class="text-end"><?php echo number_format($selected_record['bonus'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['commission'] > 0): ?>
                                        <tr><td>Commission</td><td class="text-end"><?php echo number_format($selected_record['commission'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <tr class="table-success"><td><strong>GROSS PAY</strong></td><td class="text-end"><strong>KES <?php echo number_format($selected_record['gross_pay'], 2); ?></strong></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Deductions -->
                        <div class="col-md-6">
                            <div class="card rounded-0 mb-3">
                                <div class="card-header bg-danger text-white py-2 rounded-0">
                                    <strong>DEDUCTIONS</strong>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <tr><td>NSSF</td><td class="text-end"><?php echo number_format($selected_record['nssf_employee'], 2); ?></td></tr>
                                        <tr><td>SHIF</td><td class="text-end"><?php echo number_format($selected_record['shif_amount'], 2); ?></td></tr>
                                        <tr><td>Housing Levy</td><td class="text-end"><?php echo number_format($selected_record['housing_levy_employee'], 2); ?></td></tr>
                                        <tr><td>PAYE</td><td class="text-end"><?php echo number_format($selected_record['paye'], 2); ?></td></tr>
                                        <?php if ($selected_record['salary_advance'] > 0): ?>
                                        <tr><td>Salary Advance</td><td class="text-end"><?php echo number_format($selected_record['salary_advance'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['loan_deduction'] > 0): ?>
                                        <tr><td>Loan Repayment</td><td class="text-end"><?php echo number_format($selected_record['loan_deduction'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['sacco_deduction'] > 0): ?>
                                        <tr><td>SACCO</td><td class="text-end"><?php echo number_format($selected_record['sacco_deduction'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($selected_record['helb_deduction'] > 0): ?>
                                        <tr><td>HELB</td><td class="text-end"><?php echo number_format($selected_record['helb_deduction'], 2); ?></td></tr>
                                        <?php endif; ?>
                                        <tr class="table-danger"><td><strong>TOTAL DEDUCTIONS</strong></td><td class="text-end"><strong>KES <?php echo number_format($selected_record['total_deductions'], 2); ?></strong></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Net Pay -->
                    <div class="card rounded-0 bg-primary text-white">
                        <div class="card-body py-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="mb-0">NET PAY</h5>
                                </div>
                                <div class="col text-end">
                                    <h3 class="mb-0">KES <?php echo number_format($selected_record['net_pay'], 2); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tax Breakdown -->
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <strong>Tax Calculation:</strong> 
                            Taxable Income: KES <?php echo number_format($selected_record['taxable_income'], 2); ?> | 
                            Tax Before Relief: KES <?php echo number_format($selected_record['tax_before_relief'], 2); ?> | 
                            Personal Relief: KES <?php echo number_format($selected_record['personal_relief'], 2); ?> | 
                            Insurance Relief: KES <?php echo number_format($selected_record['insurance_relief'], 2); ?>
                        </small>
                    </div>
                    
                    <!-- Footer -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <small class="text-muted">
                            This is a computer-generated payslip. Generated on <?php echo date('F d, Y \a\t h:i A'); ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- List of Payslips -->
            <div class="card shadow-sm rounded-0">
                <div class="card-header bg-dark text-white rounded-0">
                    <i class="fas fa-list me-2"></i>Payslips for <?php echo htmlspecialchars($period['period_name']); ?> (<?php echo count($records); ?> employees)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th class="text-end">Gross Pay</th>
                                    <th class="text-end">Deductions</th>
                                    <th class="text-end">Net Pay</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No payroll records found for this period</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($records as $rec): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rec['staff_code']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($rec['staff_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($rec['department_name'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format($rec['gross_pay'], 2); ?></td>
                                    <td class="text-end text-danger"><?php echo number_format($rec['total_deductions'], 2); ?></td>
                                    <td class="text-end"><strong class="text-success"><?php echo number_format($rec['net_pay'], 2); ?></strong></td>
                                    <td>
                                        <a href="?period_id=<?php echo $period_id; ?>&staff_id=<?php echo $rec['staff_id']; ?>" class="btn btn-sm btn-primary rounded-0">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
function printPayslip() {
    var content = document.getElementById('payslipContent').innerHTML;
    var printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Payslip</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; font-size: 12px; }
                .table { font-size: 11px; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>${content}</body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(function() { printWindow.print(); }, 500);
}
</script>

<?php require_once 'footer.php'; ?>