<?php
session_start();
require_once 'header.php';
require_once '../function.php';

// Get filter parameters
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;
$report_type = isset($_GET['report']) ? $_GET['report'] : 'summary';

// Get available periods
$periods = [];
$per_result = mysqli_query($conn, "SELECT id, period_code, period_name FROM payroll_periods WHERE status IN ('processing', 'pending_approval', 'approved', 'paid', 'closed') ORDER BY period_year DESC, period_month DESC LIMIT 24");
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
$totals = [
    'gross_pay' => 0,
    'basic_salary' => 0,
    'total_allowances' => 0,
    'nssf_employee' => 0,
    'nssf_employer' => 0,
    'shif_amount' => 0,
    'housing_levy_employee' => 0,
    'housing_levy_employer' => 0,
    'paye' => 0,
    'total_deductions' => 0,
    'net_pay' => 0
];

if ($period_id) {
    $rec_result = mysqli_query($conn, "SELECT * FROM payroll_records WHERE period_id = $period_id ORDER BY staff_name");
    if ($rec_result) {
        while ($row = mysqli_fetch_assoc($rec_result)) {
            $records[] = $row;
            $totals['gross_pay'] += $row['gross_pay'];
            $totals['basic_salary'] += $row['basic_salary'];
            $totals['total_allowances'] += $row['total_allowances'];
            $totals['nssf_employee'] += $row['nssf_employee'];
            $totals['nssf_employer'] += $row['nssf_employer'];
            $totals['shif_amount'] += $row['shif_amount'];
            $totals['housing_levy_employee'] += $row['housing_levy_employee'];
            $totals['housing_levy_employer'] += $row['housing_levy_employer'];
            $totals['paye'] += $row['paye'];
            $totals['total_deductions'] += $row['total_deductions'];
            $totals['net_pay'] += $row['net_pay'];
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
                    <h4 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Payroll Reports</h4>
                    <p class="text-muted mb-0">Generate statutory returns and payroll summaries</p>
                </div>
                <?php if ($period_id && count($records) > 0): ?>
                <button class="btn btn-success rounded-0" onclick="printReport()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
                <?php endif; ?>
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
                            <label class="form-label small text-muted mb-1">Report Type</label>
                            <select class="form-select rounded-0" name="report" onchange="this.form.submit()">
                                <option value="summary" <?php echo ($report_type == 'summary') ? 'selected' : ''; ?>>Payroll Summary</option>
                                <option value="paye" <?php echo ($report_type == 'paye') ? 'selected' : ''; ?>>PAYE Report (KRA)</option>
                                <option value="nssf" <?php echo ($report_type == 'nssf') ? 'selected' : ''; ?>>NSSF Report</option>
                                <option value="shif" <?php echo ($report_type == 'shif') ? 'selected' : ''; ?>>SHIF Report</option>
                                <option value="housing" <?php echo ($report_type == 'housing') ? 'selected' : ''; ?>>Housing Levy Report</option>
                                <option value="bank" <?php echo ($report_type == 'bank') ? 'selected' : ''; ?>>Bank Schedule</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!$period_id): ?>
            <div class="alert alert-info rounded-0">
                <i class="fas fa-info-circle me-2"></i>Please select a pay period to generate reports.
            </div>
            
            <?php elseif (empty($records)): ?>
            <div class="alert alert-warning rounded-0">
                <i class="fas fa-exclamation-triangle me-2"></i>No payroll records found for this period.
            </div>
            
            <?php else: ?>
            
            <div id="reportContent">
            
            <?php if ($report_type == 'summary'): ?>
            <!-- PAYROLL SUMMARY REPORT -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-dark text-white rounded-0">
                    <i class="fas fa-file-alt me-2"></i>Payroll Summary - <?php echo htmlspecialchars($period['period_name']); ?>
                </div>
                <div class="card-body">
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="border rounded-0 p-3 text-center bg-light">
                                <small class="text-muted">Total Employees</small>
                                <h3 class="mb-0"><?php echo count($records); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-0 p-3 text-center bg-light">
                                <small class="text-muted">Total Gross Pay</small>
                                <h4 class="mb-0">KES <?php echo number_format($totals['gross_pay'], 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-0 p-3 text-center bg-light">
                                <small class="text-muted">Total Deductions</small>
                                <h4 class="mb-0 text-danger">KES <?php echo number_format($totals['total_deductions'], 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-0 p-3 text-center bg-primary text-white">
                                <small class="opacity-75">Total Net Pay</small>
                                <h4 class="mb-0">KES <?php echo number_format($totals['net_pay'], 2); ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deductions Breakdown -->
                    <h6 class="text-muted mb-3">Statutory Deductions Summary</h6>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="border-start border-danger border-4 ps-3">
                                <small class="text-muted">PAYE</small>
                                <h5>KES <?php echo number_format($totals['paye'], 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border-start border-primary border-4 ps-3">
                                <small class="text-muted">NSSF (Employee + Employer)</small>
                                <h5>KES <?php echo number_format($totals['nssf_employee'] + $totals['nssf_employer'], 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border-start border-success border-4 ps-3">
                                <small class="text-muted">SHIF</small>
                                <h5>KES <?php echo number_format($totals['shif_amount'], 2); ?></h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border-start border-warning border-4 ps-3">
                                <small class="text-muted">Housing Levy (Emp + Er)</small>
                                <h5>KES <?php echo number_format($totals['housing_levy_employee'] + $totals['housing_levy_employer'], 2); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Full Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Staff ID</th>
                                <th>Name</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Allowances</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">NSSF</th>
                                <th class="text-end">SHIF</th>
                                <th class="text-end">Housing</th>
                                <th class="text-end">PAYE</th>
                                <th class="text-end">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($records as $rec): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_code']); ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_name']); ?></td>
                                <td class="text-end"><?php echo number_format($rec['basic_salary'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['total_allowances'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['gross_pay'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['nssf_employee'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['shif_amount'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['housing_levy_employee'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['paye'], 2); ?></td>
                                <td class="text-end"><strong><?php echo number_format($rec['net_pay'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="3">TOTALS</th>
                                <th class="text-end"><?php echo number_format($totals['basic_salary'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['total_allowances'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['gross_pay'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['nssf_employee'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['shif_amount'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['housing_levy_employee'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['paye'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['net_pay'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'paye'): ?>
            <!-- PAYE REPORT -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-danger text-white rounded-0">
                    <i class="fas fa-landmark me-2"></i>PAYE Report (P10) - <?php echo htmlspecialchars($period['period_name']); ?>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Employer:</strong> Vantage Africa School of Leadership</p>
                            <p class="mb-1"><strong>KRA PIN:</strong> P000000000X</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Period:</strong> <?php echo htmlspecialchars($period['period_name']); ?></p>
                            <p class="mb-1"><strong>Employees:</strong> <?php echo count($records); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <h4 class="text-danger">Total PAYE: KES <?php echo number_format($totals['paye'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-danger">
                            <tr>
                                <th>#</th>
                                <th>KRA PIN</th>
                                <th>Employee Name</th>
                                <th class="text-end">Gross Pay</th>
                                <th class="text-end">Taxable Income</th>
                                <th class="text-end">Tax Before Relief</th>
                                <th class="text-end">Personal Relief</th>
                                <th class="text-end">Insurance Relief</th>
                                <th class="text-end">PAYE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($records as $rec): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($rec['kra_pin'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_name']); ?></td>
                                <td class="text-end"><?php echo number_format($rec['gross_pay'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['taxable_income'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['tax_before_relief'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['personal_relief'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['insurance_relief'], 2); ?></td>
                                <td class="text-end"><strong><?php echo number_format($rec['paye'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-danger">
                            <tr>
                                <th colspan="3">TOTALS</th>
                                <th class="text-end"><?php echo number_format($totals['gross_pay'], 2); ?></th>
                                <th class="text-end">-</th>
                                <th class="text-end">-</th>
                                <th class="text-end">-</th>
                                <th class="text-end">-</th>
                                <th class="text-end"><?php echo number_format($totals['paye'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'nssf'): ?>
            <!-- NSSF REPORT -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-primary text-white rounded-0">
                    <i class="fas fa-shield-alt me-2"></i>NSSF Contribution Report - <?php echo htmlspecialchars($period['period_name']); ?>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Employer:</strong> Vantage Africa School of Leadership</p>
                            <p class="mb-1"><strong>NSSF No:</strong> 00000000</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Period:</strong> <?php echo htmlspecialchars($period['period_name']); ?></p>
                            <p class="mb-1"><strong>Employees:</strong> <?php echo count($records); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <p class="mb-1">Employee: <strong>KES <?php echo number_format($totals['nssf_employee'], 2); ?></strong></p>
                            <p class="mb-1">Employer: <strong>KES <?php echo number_format($totals['nssf_employer'], 2); ?></strong></p>
                            <h4 class="text-primary">Total: KES <?php echo number_format($totals['nssf_employee'] + $totals['nssf_employer'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th>#</th>
                                <th>NSSF No</th>
                                <th>Employee Name</th>
                                <th>ID Number</th>
                                <th class="text-end">Gross Pay</th>
                                <th class="text-end">Employee (6%)</th>
                                <th class="text-end">Employer (6%)</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($records as $rec): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($rec['nssf_number'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_name']); ?></td>
                                <td>-</td>
                                <td class="text-end"><?php echo number_format($rec['gross_pay'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['nssf_employee'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['nssf_employer'], 2); ?></td>
                                <td class="text-end"><strong><?php echo number_format($rec['nssf_employee'] + $rec['nssf_employer'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-primary">
                            <tr>
                                <th colspan="4">TOTALS</th>
                                <th class="text-end"><?php echo number_format($totals['gross_pay'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['nssf_employee'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['nssf_employer'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['nssf_employee'] + $totals['nssf_employer'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'shif'): ?>
            <!-- SHIF REPORT -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-success text-white rounded-0">
                    <i class="fas fa-heartbeat me-2"></i>SHIF Contribution Report - <?php echo htmlspecialchars($period['period_name']); ?>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Employer:</strong> Vantage Africa School of Leadership</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Period:</strong> <?php echo htmlspecialchars($period['period_name']); ?></p>
                            <p class="mb-1"><strong>Employees:</strong> <?php echo count($records); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <h4 class="text-success">Total SHIF: KES <?php echo number_format($totals['shif_amount'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>#</th>
                                <th>NHIF No</th>
                                <th>Employee Name</th>
                                <th class="text-end">Gross Pay</th>
                                <th class="text-end">SHIF (2.75%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($records as $rec): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($rec['nhif_number'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_name']); ?></td>
                                <td class="text-end"><?php echo number_format($rec['gross_pay'], 2); ?></td>
                                <td class="text-end"><strong><?php echo number_format($rec['shif_amount'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="3">TOTALS</th>
                                <th class="text-end"><?php echo number_format($totals['gross_pay'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['shif_amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'housing'): ?>
            <!-- HOUSING LEVY REPORT -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-warning text-dark rounded-0">
                    <i class="fas fa-home me-2"></i>Affordable Housing Levy Report - <?php echo htmlspecialchars($period['period_name']); ?>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Employer:</strong> Vantage Africa School of Leadership</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Period:</strong> <?php echo htmlspecialchars($period['period_name']); ?></p>
                            <p class="mb-1"><strong>Employees:</strong> <?php echo count($records); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <p class="mb-1">Employee: <strong>KES <?php echo number_format($totals['housing_levy_employee'], 2); ?></strong></p>
                            <p class="mb-1">Employer: <strong>KES <?php echo number_format($totals['housing_levy_employer'], 2); ?></strong></p>
                            <h4 class="text-warning">Total: KES <?php echo number_format($totals['housing_levy_employee'] + $totals['housing_levy_employer'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-warning">
                            <tr>
                                <th>#</th>
                                <th>Staff ID</th>
                                <th>Employee Name</th>
                                <th class="text-end">Gross Pay</th>
                                <th class="text-end">Employee (1.5%)</th>
                                <th class="text-end">Employer (1.5%)</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($records as $rec): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_code']); ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_name']); ?></td>
                                <td class="text-end"><?php echo number_format($rec['gross_pay'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['housing_levy_employee'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($rec['housing_levy_employer'], 2); ?></td>
                                <td class="text-end"><strong><?php echo number_format($rec['housing_levy_employee'] + $rec['housing_levy_employer'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-warning">
                            <tr>
                                <th colspan="3">TOTALS</th>
                                <th class="text-end"><?php echo number_format($totals['gross_pay'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['housing_levy_employee'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['housing_levy_employer'], 2); ?></th>
                                <th class="text-end"><?php echo number_format($totals['housing_levy_employee'] + $totals['housing_levy_employer'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'bank'): ?>
            <!-- BANK SCHEDULE -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-dark text-white rounded-0">
                    <i class="fas fa-university me-2"></i>Bank Payment Schedule - <?php echo htmlspecialchars($period['period_name']); ?>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Company:</strong> Vantage Africa School of Leadership</p>
                            <p class="mb-1"><strong>Period:</strong> <?php echo htmlspecialchars($period['period_name']); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4>Total: KES <?php echo number_format($totals['net_pay'], 2); ?></h4>
                            <p class="mb-0"><?php echo count($records); ?> employees</p>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Staff ID</th>
                                <th>Employee Name</th>
                                <th>Bank</th>
                                <th>Branch</th>
                                <th>Account Number</th>
                                <th class="text-end">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($records as $rec): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_code']); ?></td>
                                <td><?php echo htmlspecialchars($rec['staff_name']); ?></td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td class="text-end"><strong><?php echo number_format($rec['net_pay'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="6">TOTAL</th>
                                <th class="text-end"><?php echo number_format($totals['net_pay'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>
            
            </div>
            
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
function printReport() {
    var content = document.getElementById('reportContent').innerHTML;
    var printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Payroll Report</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; font-size: 11px; }
                .table { font-size: 10px; }
                .card-header { padding: 8px 12px; }
                @media print { 
                    body { padding: 0; } 
                    .card { border: 1px solid #000 !important; }
                }
            </style>
        </head>
        <body>
            <div class="text-center mb-3">
                <h5>VANTAGE AFRICA SCHOOL OF LEADERSHIP</h5>
                <p class="mb-0">Payroll Report</p>
            </div>
            ${content}
            <div class="text-center mt-3">
                <small>Generated on <?php echo date('F d, Y \a\t h:i A'); ?></small>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(function() { printWindow.print(); }, 500);
}
</script>

<?php require_once 'footer.php'; ?>