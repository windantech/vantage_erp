<?php
// Start output buffering so later header()/redirect calls don't fail with
// "headers already sent" (this file prints HTML further down).
if (!ob_get_level()) { ob_start(); }
require_once '../../database/conn.php';


$role = array(0, 1, 2, 3, 4, 11, 22, 33, 44, 999, 888, 777, 555, 81, 82, 83);
if (in_array(81, $role)) {
    $_SESSION['login_type'] = 1;
} elseif (in_array(82, $role)) {
    $_SESSION['login_type'] = 2;
} elseif (in_array(83, $role)) {
    $_SESSION['login_type'] = 3;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vantage</title>

    <!-- Favicon -->
    <link href="../assets/img/favicon/favicon.ico?v=<?php echo date('his'); ?>" rel="icon">
    <!-- Favicon -->

    <!-- Custom fonts -->
    <link href="../assets/vendor/fontawesome-free/css/all.min.css?v=<?php echo date('his'); ?>" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Custom fonts -->

    <!-- Bootstrap 5.3 cdn -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
    <!-- Bootstrap 5.3 cdn -->

    <!-- Custom css -->
    <link href="../assets/css/bootstrap.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="../assets/css/main.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="../assets/css/summernote.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="../assets/summernote/summernote-bs4.min.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="../assets/dropzone/min/dropzone.min.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="assets/css/graphs.css?v=<?php echo date('his'); ?>" rel="stylesheet">

    <!-- table -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <!-- table -->
    <style>
    /* Style for opened dropdown inside sidebar */
    .sidebar .collapse-inner {
        background-color: #2c2f33 !important; /* dark background */
        border-left: 3px solid #4e73df;       /* primary accent line */
        padding: 0.5rem 0;
    }

    .sidebar .collapse-header {
        color: #bfc5d2 !important;
        font-weight: 600;
        padding-left: 1rem;
    }

    .sidebar .collapse-item {
        color: #d7d9df !important;
        padding: 0.5rem 1.25rem;
    }

    .sidebar .collapse-item:hover {
        background-color: #4e73df !important;
        color: #fff !important;
        border-radius: 0.35rem;
    }
</style>

</head>

<body class="overflow">


    <!-- Wrapper Start -->
    <div id="wrapper">
   <!-- Sidebar -->
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion overflow" id="accordionSidebar">

    <!-- Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-start" href="./">
        <img src="../assets/img/logo.png" style="width: 5rem;" alt="">
    </a>

    <hr class="sidebar-divider my-0">

    <!-- ===================== -->
    <!-- ACCOUNTING MODULE -->
    <!-- ===================== -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#accountingMenu">
            <i class="fas fa-calculator"></i>
            <span>Accounting</span>
        </a>
        <div id="accountingMenu" class="collapse" data-bs-parent="#accordionSidebar">
            <div class="collapse-inner rounded">

                <h6 class="collapse-header">Payments</h6>
                <a class="collapse-item" href="payments_dashboard?year=<?= date('Y') ?>">Dashboard</a>
                
                     <a class="collapse-item" href="fee_balances.php">Fee Balances</a>
                <a class="collapse-item" href="all_transaction">All Transactions</a>

                <h6 class="collapse-header">Finance</h6>
                <a class="collapse-item" href="income?year=<?= date('Y') ?>">Income</a>
                <a class="collapse-item" href="expenses?year=<?= date('Y') ?>">Expenses</a>

                <h6 class="collapse-header">Reports</h6>
                <a class="collapse-item" href="approve_requests">Service Requests</a>
            </div>
        </div>
    </li>

    <hr class="sidebar-divider">

    <!-- ===================== -->
    <!-- HUMAN RESOURCES MODULE -->
    <!-- ===================== -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#hrMenu">
            <i class="fas fa-users"></i>
            <span>Human Resources</span>
        </a>
        <div id="hrMenu" class="collapse" data-bs-parent="#accordionSidebar">
            <div class="collapse-inner rounded">

                <a class="collapse-item" href="hr_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>HR Dashboard
                </a>

                <h6 class="collapse-header">Staff</h6>
                <a class="collapse-item" href="staff_list.php">All Staff</a>
                <a class="collapse-item" href="staff_list.php?status=pending">Pending Onboarding</a>
                <a class="collapse-item" href="../staff_onboarding.php" target="_blank">New Staff Form</a>

                <h6 class="collapse-header">Attendance</h6>
                <a class="collapse-item" href="attendance_daily.php">Daily Attendance</a>
                <a class="collapse-item" href="attendance_report.php">Attendance Report</a>
                <a class="collapse-item" href="attendance_devices.php">Biometric Devices</a>

                <h6 class="collapse-header">Leave</h6>
                <a class="collapse-item" href="leave_requests.php">Leave Requests</a>
                <a class="collapse-item" href="leave_balances.php">Leave Balances</a>
                <a class="collapse-item" href="leave_calendar.php">Leave Calendar</a>
                <a class="collapse-item" href="leave_types.php">Leave Types</a>

                <h6 class="collapse-header">Payroll</h6>
                <a class="collapse-item" href="payroll_periods.php">Payroll Periods</a>
                <a class="collapse-item" href="payroll_settings.php">Payroll Settings</a>
                <a class="collapse-item" href="payroll_reports.php">Payroll Reports</a>
                <a class="collapse-item" href="payroll_remittances.php">Remittances</a>
                <a class="collapse-item" href="remittance_recipients.php">Recipients</a>

                <a class="collapse-item" href="payslips.php">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Payslips
                </a>

                <h6 class="collapse-header">Assets</h6>
                <a class="collapse-item" href="assets_list.php">All Assets</a>
                <a class="collapse-item" href="assets_assigned.php">Assigned Assets</a>
                <a class="collapse-item" href="assets_categories.php">Asset Categories</a>

            </div>
        </div>
    </li>

    <hr class="sidebar-divider">

    <!-- ===================== -->
    <!-- PERFORMANCE MODULE -->
    <!-- ===================== -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#performanceMenu">
            <i class="fas fa-chart-line"></i>
            <span>Performance</span>
        </a>
        <div id="performanceMenu" class="collapse" data-bs-parent="#accordionSidebar">
            <div class="collapse-inner rounded">

                <h6 class="collapse-header">Commissions</h6>
                <a class="collapse-item" href="commission_rules.php">Commission Rules</a>
                <a class="collapse-item" href="commission_reports.php">Commission Reports</a>

                <h6 class="collapse-header">Assignments</h6>
                <a class="collapse-item" href="intake_assignments.php">Virtual Assignments</a>
                <a class="collapse-item" href="event_assignments.php">International Assignments</a>

            </div>
        </div>
    </li>

</ul>
<!-- End Sidebar -->

