<?php
session_start();
require_once 'header.php';
require_once '../function.php';

$current_user_id = intval($_SESSION['login_id'] ?? 1);

// ============================================
// STAFF STATISTICS
// ============================================
$staff_stats = [
    'total' => 0,
    'active' => 0,
    'pending' => 0,
    'approved' => 0,
    'suspended' => 0,
    'terminated' => 0
];

$staff_result = mysqli_query($conn, "
    SELECT onboarding_status, COUNT(*) as count 
    FROM staff 
    GROUP BY onboarding_status
");
if ($staff_result) {
    while ($row = mysqli_fetch_assoc($staff_result)) {
        $status = $row['onboarding_status'];
        $staff_stats[$status] = $row['count'];
        $staff_stats['total'] += $row['count'];
    }
}

// Staff by Department
$staff_by_dept = [];
$dept_result = mysqli_query($conn, "
    SELECT d.department_name, COUNT(s.id) as count
    FROM departments d
    LEFT JOIN staff s ON d.id = s.department_id AND s.onboarding_status = 'active'
    GROUP BY d.id, d.department_name
    ORDER BY count DESC
    LIMIT 10
");
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $staff_by_dept[] = $row;
    }
}

// Staff by Gender
$staff_by_gender = ['Male' => 0, 'Female' => 0, 'Other' => 0];
$gender_result = mysqli_query($conn, "
    SELECT gender, COUNT(*) as count 
    FROM staff 
    WHERE onboarding_status = 'active'
    GROUP BY gender
");
if ($gender_result) {
    while ($row = mysqli_fetch_assoc($gender_result)) {
        $g = ucfirst($row['gender'] ?? 'Other');
        if (isset($staff_by_gender[$g])) {
            $staff_by_gender[$g] = $row['count'];
        } else {
            $staff_by_gender['Other'] += $row['count'];
        }
    }
}

// ============================================
// PAYROLL STATISTICS
// ============================================
$payroll_stats = [
    'current_period' => null,
    'total_gross' => 0,
    'total_net' => 0,
    'total_employees' => 0,
    'pending_approval' => 0,
    'ytd_gross' => 0,
    'ytd_net' => 0
];

// Current/Latest Period
$current_period = mysqli_query($conn, "
    SELECT * FROM payroll_periods 
    ORDER BY period_year DESC, period_month DESC 
    LIMIT 1
");
if ($current_period && $row = mysqli_fetch_assoc($current_period)) {
    $payroll_stats['current_period'] = $row;
    $payroll_stats['total_gross'] = $row['total_gross'];
    $payroll_stats['total_net'] = $row['total_net'];
    $payroll_stats['total_employees'] = $row['total_employees'];
}

// Pending Approval Count
$pending_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM payroll_periods WHERE status = 'pending_approval'");
if ($pending_result && $row = mysqli_fetch_assoc($pending_result)) {
    $payroll_stats['pending_approval'] = $row['count'];
}

// Year-to-Date Totals
$current_year = date('Y');
$ytd_result = mysqli_query($conn, "
    SELECT SUM(total_gross) as ytd_gross, SUM(total_net) as ytd_net
    FROM payroll_periods 
    WHERE period_year = $current_year AND status IN ('approved', 'paid', 'closed')
");
if ($ytd_result && $row = mysqli_fetch_assoc($ytd_result)) {
    $payroll_stats['ytd_gross'] = $row['ytd_gross'] ?? 0;
    $payroll_stats['ytd_net'] = $row['ytd_net'] ?? 0;
}

// Monthly Payroll Trend (Last 6 months)
$payroll_trend = [];
$trend_result = mysqli_query($conn, "
    SELECT period_name, total_gross, total_net, total_employees
    FROM payroll_periods 
    WHERE status IN ('approved', 'paid', 'closed')
    ORDER BY period_year DESC, period_month DESC
    LIMIT 6
");
if ($trend_result) {
    while ($row = mysqli_fetch_assoc($trend_result)) {
        $payroll_trend[] = $row;
    }
    $payroll_trend = array_reverse($payroll_trend);
}

// ============================================
// REMITTANCES STATISTICS
// ============================================
$remittance_stats = [
    'pending' => 0,
    'overdue' => 0,
    'pending_amount' => 0,
    'overdue_amount' => 0
];

$rem_result = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN status != 'paid' AND due_date >= CURDATE() THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status != 'paid' AND due_date >= CURDATE() THEN total_amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status != 'paid' AND due_date < CURDATE() THEN total_amount ELSE 0 END) as overdue_amount
    FROM payroll_remittances
");
if ($rem_result && $row = mysqli_fetch_assoc($rem_result)) {
    $remittance_stats = array_merge($remittance_stats, $row);
}

// ============================================
// UPCOMING EVENTS
// ============================================

// Birthdays This Month
$birthdays = [];
$current_month = date('m');
$bday_result = mysqli_query($conn, "
    SELECT full_name, date_of_birth, department_id,
           DAYOFMONTH(date_of_birth) as birth_day
    FROM staff 
    WHERE onboarding_status = 'active' 
    AND MONTH(date_of_birth) = $current_month
    ORDER BY DAYOFMONTH(date_of_birth)
    LIMIT 10
");
if ($bday_result) {
    while ($row = mysqli_fetch_assoc($bday_result)) {
        $birthdays[] = $row;
    }
}

// Work Anniversaries This Month
$anniversaries = [];
$anniv_result = mysqli_query($conn, "
    SELECT full_name, start_date,
           YEAR(CURDATE()) - YEAR(start_date) as years,
           DAYOFMONTH(start_date) as join_day
    FROM staff 
    WHERE onboarding_status = 'active' 
    AND MONTH(start_date) = $current_month
    AND YEAR(start_date) < YEAR(CURDATE())
    AND start_date IS NOT NULL
    ORDER BY DAYOFMONTH(start_date)
    LIMIT 10
");
if ($anniv_result) {
    while ($row = mysqli_fetch_assoc($anniv_result)) {
        $anniversaries[] = $row;
    }
}

// Contract Expirations (Next 30 days)
$expiring_contracts = [];
$contract_result = mysqli_query($conn, "
    SELECT full_name, contract_end_date, job_title
    FROM staff 
    WHERE onboarding_status = 'active' 
    AND contract_end_date IS NOT NULL
    AND contract_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY contract_end_date
    LIMIT 10
");
if ($contract_result) {
    while ($row = mysqli_fetch_assoc($contract_result)) {
        $expiring_contracts[] = $row;
    }
}

// ============================================
// RECENT ACTIVITIES
// ============================================
$recent_activities = [];
$activities_result = mysqli_query($conn, "
    (SELECT 'staff' as type, 'New Staff' as action, full_name as description, created_at as activity_date
     FROM staff ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'payroll' as type, action, CONCAT('Period #', period_id) as description, performed_at as activity_date
     FROM payroll_approval_log ORDER BY performed_at DESC LIMIT 5)
    ORDER BY activity_date DESC
    LIMIT 10
");
if ($activities_result) {
    while ($row = mysqli_fetch_assoc($activities_result)) {
        $recent_activities[] = $row;
    }
}

// ============================================
// PENDING ACTIONS
// ============================================
$pending_onboarding = 0;
$pending_ob_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM staff WHERE onboarding_status IN ('pending', 'under_review')");
if ($pending_ob_result && $row = mysqli_fetch_assoc($pending_ob_result)) {
    $pending_onboarding = $row['count'];
}

// Month names for display
$month_names = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                'July', 'August', 'September', 'October', 'November', 'December'];
$current_month_name = $month_names[intval(date('m'))];
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>HR Dashboard</h4>
                    <p class="text-muted mb-0">Overview of Human Resources metrics and activities</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark fs-6">
                        <i class="fas fa-calendar me-1"></i><?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </div>
            
            <!-- Quick Stats Row -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-primary h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Staff</h6>
                                    <h2 class="mb-0"><?php echo number_format($staff_stats['total']); ?></h2>
                                    <small class="text-success"><?php echo $staff_stats['active']; ?> active</small>
                                </div>
                                <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-success h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Monthly Payroll</h6>
                                    <h4 class="mb-0">KES <?php echo number_format($payroll_stats['total_net']); ?></h4>
                                    <small class="text-muted"><?php echo $payroll_stats['total_employees']; ?> employees</small>
                                </div>
                                <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                    <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-warning h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Pending Onboarding</h6>
                                    <h2 class="mb-0"><?php echo $pending_onboarding; ?></h2>
                                    <small class="text-warning">Awaiting approval</small>
                                </div>
                                <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                    <i class="fas fa-user-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card shadow-sm rounded-0 border-start border-4 border-danger h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Overdue Remittances</h6>
                                    <h2 class="mb-0 <?php echo ($remittance_stats['overdue'] > 0) ? 'text-danger' : ''; ?>"><?php echo intval($remittance_stats['overdue']); ?></h2>
                                    <small class="text-danger">KES <?php echo number_format($remittance_stats['overdue_amount']); ?></small>
                                </div>
                                <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                    <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Payroll Summary -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-dark text-white rounded-0 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-chart-line me-2"></i>Payroll Summary</span>
                            <a href="payroll_periods.php" class="btn btn-sm btn-outline-light rounded-0">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-4 text-center border-end">
                                    <small class="text-muted">Current Period</small>
                                    <h5 class="mb-0"><?php echo $payroll_stats['current_period']['period_name'] ?? 'N/A'; ?></h5>
                                    <span class="badge bg-<?php 
                                        $status = $payroll_stats['current_period']['status'] ?? '';
                                        echo ($status == 'paid') ? 'success' : (($status == 'approved') ? 'primary' : 'warning');
                                    ?>"><?php echo ucfirst($status); ?></span>
                                </div>
                                <div class="col-md-4 text-center border-end">
                                    <small class="text-muted">YTD Gross (<?php echo $current_year; ?>)</small>
                                    <h5 class="mb-0">KES <?php echo number_format($payroll_stats['ytd_gross']); ?></h5>
                                </div>
                                <div class="col-md-4 text-center">
                                    <small class="text-muted">YTD Net (<?php echo $current_year; ?>)</small>
                                    <h5 class="mb-0 text-success">KES <?php echo number_format($payroll_stats['ytd_net']); ?></h5>
                                </div>
                            </div>
                            
                            <?php if (!empty($payroll_trend)): ?>
                            <!-- Payroll Trend Chart -->
                            <canvas id="payrollChart" height="120"></canvas>
                            <?php else: ?>
                            <p class="text-muted text-center py-4">No payroll data available yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Staff by Department -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-primary text-white rounded-0">
                            <i class="fas fa-sitemap me-2"></i>Staff by Department
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($staff_by_dept)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Department</th>
                                            <th class="text-end">Active Staff</th>
                                            <th width="40%">Distribution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $max_count = max(array_column($staff_by_dept, 'count'));
                                        foreach ($staff_by_dept as $dept): 
                                            $percentage = ($max_count > 0) ? ($dept['count'] / $max_count) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                            <td class="text-end"><strong><?php echo $dept['count']; ?></strong></td>
                                            <td>
                                                <div class="progress rounded-0" style="height: 20px;">
                                                    <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%">
                                                        <?php echo $dept['count']; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center py-4">No department data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Remittances Status -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-secondary text-white rounded-0 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-landmark me-2"></i>Remittances Status</span>
                            <a href="payroll_remittances.php" class="btn btn-sm btn-outline-light rounded-0">Manage</a>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-6 border-end">
                                    <div class="py-3">
                                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                        <h3 class="mb-0"><?php echo intval($remittance_stats['pending']); ?></h3>
                                        <p class="text-muted mb-0">Pending</p>
                                        <small>KES <?php echo number_format($remittance_stats['pending_amount']); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="py-3">
                                        <i class="fas fa-exclamation-circle fa-2x text-danger mb-2"></i>
                                        <h3 class="mb-0 <?php echo ($remittance_stats['overdue'] > 0) ? 'text-danger' : ''; ?>">
                                            <?php echo intval($remittance_stats['overdue']); ?>
                                        </h3>
                                        <p class="text-muted mb-0">Overdue</p>
                                        <small class="text-danger">KES <?php echo number_format($remittance_stats['overdue_amount']); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Staff Status Breakdown -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-info text-white rounded-0">
                            <i class="fas fa-user-check me-2"></i>Staff Status
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-circle text-success me-2"></i>Active</span>
                                <strong><?php echo $staff_stats['active']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-circle text-warning me-2"></i>Pending</span>
                                <strong><?php echo $staff_stats['pending']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-circle text-primary me-2"></i>Approved</span>
                                <strong><?php echo $staff_stats['approved']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-circle text-secondary me-2"></i>Suspended</span>
                                <strong><?php echo $staff_stats['suspended']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-circle text-dark me-2"></i>Terminated</span>
                                <strong><?php echo $staff_stats['terminated']; ?></strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span><strong>Total</strong></span>
                                <strong><?php echo $staff_stats['total']; ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gender Distribution -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-purple text-white rounded-0" style="background-color: #6f42c1;">
                            <i class="fas fa-venus-mars me-2"></i>Gender Distribution
                        </div>
                        <div class="card-body">
                            <canvas id="genderChart" height="200"></canvas>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between small">
                                    <span><i class="fas fa-square text-primary me-1"></i>Male</span>
                                    <span><?php echo $staff_by_gender['Male']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span><i class="fas fa-square text-danger me-1"></i>Female</span>
                                    <span><?php echo $staff_by_gender['Female']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Birthdays This Month -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-warning text-dark rounded-0">
                            <i class="fas fa-birthday-cake me-2"></i>Birthdays in <?php echo $current_month_name; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($birthdays)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($birthdays as $bday): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-gift text-warning me-2"></i>
                                        <?php echo htmlspecialchars($bday['full_name']); ?>
                                    </div>
                                    <span class="badge bg-warning text-dark"><?php echo date('M d', strtotime($bday['date_of_birth'])); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted text-center py-3 mb-0">No birthdays this month</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Work Anniversaries -->
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-success text-white rounded-0">
                            <i class="fas fa-award me-2"></i>Work Anniversaries
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($anniversaries)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($anniversaries as $anniv): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-trophy text-success me-2"></i>
                                        <?php echo htmlspecialchars($anniv['full_name']); ?>
                                    </div>
                                    <span class="badge bg-success"><?php echo $anniv['years']; ?> year<?php echo ($anniv['years'] > 1) ? 's' : ''; ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted text-center py-3 mb-0">No anniversaries this month</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Expiring Contracts -->
                    <?php if (!empty($expiring_contracts)): ?>
                    <div class="card shadow-sm rounded-0 mb-4">
                        <div class="card-header bg-danger text-white rounded-0">
                            <i class="fas fa-file-contract me-2"></i>Expiring Contracts (30 days)
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($expiring_contracts as $contract): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($contract['full_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($contract['job_title']); ?></small>
                                    </div>
                                    <span class="badge bg-danger"><?php echo date('M d', strtotime($contract['contract_end_date'])); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-light rounded-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <a href="staff_list.php" class="btn btn-outline-primary rounded-0 w-100 py-3">
                                <i class="fas fa-users d-block mb-2 fa-2x"></i>
                                <small>Staff List</small>
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <a href="../staff_onboarding.php" target="_blank" class="btn btn-outline-success rounded-0 w-100 py-3">
                                <i class="fas fa-user-plus d-block mb-2 fa-2x"></i>
                                <small>New Staff</small>
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <a href="payroll_periods.php" class="btn btn-outline-warning rounded-0 w-100 py-3">
                                <i class="fas fa-calculator d-block mb-2 fa-2x"></i>
                                <small>Payroll</small>
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <a href="payslips.php" class="btn btn-outline-info rounded-0 w-100 py-3">
                                <i class="fas fa-file-invoice-dollar d-block mb-2 fa-2x"></i>
                                <small>Payslips</small>
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <a href="payroll_reports.php" class="btn btn-outline-secondary rounded-0 w-100 py-3">
                                <i class="fas fa-chart-bar d-block mb-2 fa-2x"></i>
                                <small>Reports</small>
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <a href="payroll_remittances.php" class="btn btn-outline-danger rounded-0 w-100 py-3">
                                <i class="fas fa-landmark d-block mb-2 fa-2x"></i>
                                <small>Remittances</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Payroll Trend Chart
<?php if (!empty($payroll_trend)): ?>
var ctx = document.getElementById('payrollChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($payroll_trend, 'period_name')); ?>,
        datasets: [{
            label: 'Gross Pay (KES)',
            data: <?php echo json_encode(array_map('floatval', array_column($payroll_trend, 'total_gross'))); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }, {
            label: 'Net Pay (KES)',
            data: <?php echo json_encode(array_map('floatval', array_column($payroll_trend, 'total_net'))); ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.5)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'KES ' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': KES ' + context.raw.toLocaleString();
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Gender Distribution Chart
var genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'doughnut',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [<?php echo $staff_by_gender['Male']; ?>, <?php echo $staff_by_gender['Female']; ?>],
            backgroundColor: ['rgba(54, 162, 235, 0.8)', 'rgba(220, 53, 69, 0.8)'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        cutout: '60%'
    }
});
</script>

<?php require_once 'footer.php'; ?>