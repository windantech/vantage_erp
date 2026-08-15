<?php
require_once "auth.php";

// Prevent stale/cached admin responses (can cause blank pages after updates).
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vantage</title>

    <!-- Favicon -->
    <link href="assets/img/favicon/favicon.ico?v=<?php echo date('his'); ?>" rel="icon">
    <!-- Favicon -->

    <!-- Custom fonts -->
    <link href="assets/vendor/fontawesome-free/css/all.min.css?v=<?php echo date('his'); ?>" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
    <!-- Custom fonts -->

    <!-- Bootstrap 5.3 cdn -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
    <!-- Bootstrap 5.3 cdn -->

    <!-- Custom css -->
    <link href="assets/css/bootstrap.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="assets/css/main.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="assets/css/summernote.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="assets/summernote/summernote-bs4.min.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="assets/dropzone/min/dropzone.min.css?v=<?php echo date('his'); ?>" rel="stylesheet">
    <link href="assets/css/lead_forms.css?v=<?php echo date('his'); ?>" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/select2.min.css?v=<?php echo date('his'); ?>">
    <link rel="stylesheet" href="assets/css/select2-bootstrap4.min.css?v=<?php echo date('his'); ?>">

    <!-- Custom css -->

    <!-- table -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <!-- table -->
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
</head>

<body class="overflow">
    <!-- Wrapper Start -->
    <div id="wrapper">
        <!-- Sidebar -->
       <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion overflow" id="accordionSidebar">
    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-start" href="./">
        <img src="assets/img/logo.png" style="width: 5rem;" alt="Logo">
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item dash">
        <a class="nav-link" href="./">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- Nav Item - Performance (role dashboards).
         Only rendered while ON a performance-dashboard route, so it stays hidden
         from the shared sidebar on every other page. Links to the current one. -->
    <?php $perf_page = basename($_SERVER['SCRIPT_NAME']); if (in_array($perf_page, ['bde_dashboard.php', 'bdo_dashboard.php', 'bdm_dashboard.php', 'ceo_performance.php', 'bde_targets.php'], true)) { ?>
    <li class="nav-item<?php echo $perf_page !== 'bde_targets.php' ? ' active' : ''; ?>">
        <a class="nav-link" href="<?php echo $perf_page === 'bde_targets.php' ? 'bde_dashboard.php' : $perf_page; ?>">
            <i class="fas fa-fw fa-chart-line"></i>
            <span>Performance</span>
        </a>
    </li>
    <?php if (in_array(777, $role)) { ?>
    <li class="nav-item<?php echo $perf_page === 'bde_targets.php' ? ' active' : ''; ?>">
        <a class="nav-link" href="bde_targets.php">
            <i class="fas fa-fw fa-bullseye"></i>
            <span>BDE Targets</span>
        </a>
    </li>
    <?php } ?>
    <?php } ?>

    <!-- WhatsApp -->
    <?php if (in_array(44, $role)) { ?>
    <li class="nav-item whatsapp">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#whatsapp" data-bs-toggle="collapse" data-bs-target="#whatsapp" aria-expanded="true" aria-controls="whatsapp">
            <i class="bi bi-whatsapp"></i>
            <span>WhatsApp</span>
        </a>
        <div id="whatsapp" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="wa_inbox.php">Inbox</a>
                <a class="collapse-item" href="wa_contacts.php">Contacts</a>
                <a class="collapse-item" href="wa_canned.php">Quick Replies</a>
                <a class="collapse-item" href="wa_knowledge.php">Knowledge Base</a>
                <?php if (in_array(777, $role)) { ?>
                <a class="collapse-item" href="wa_broadcast.php">Broadcast</a>
                <a class="collapse-item" href="wa_broadcasts.php">Broadcast History</a>
                <a class="collapse-item" href="wa_templates.php">Templates</a>
                <a class="collapse-item" href="wa_assignments.php">Assignments</a>
                <a class="collapse-item" href="wa_insights.php">Insights</a>
                <a class="collapse-item" href="wa_settings.php">Settings</a>
                <?php } ?>
            </div>
        </div>
    </li>
    <?php } ?>


    <!-- Transactions -->
        <?php 
            if(in_array(1, $role)) {
            ?>
    <li class="nav-item transact">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#transactions" data-bs-toggle="collapse" data-bs-target="#transactions" aria-expanded="true" aria-controls="transactions">
            <i class="bi bi-cash-coin"></i>
            <span>Transactions</span>
        </a>
        
        <div id="transactions" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="all_transaction">All Transactions</a>
            </div>
          
        </div>
    </li>
  <?php } ?>
    <!-- Students -->
        <?php 
            if(in_array(0, $role)) {
            ?>
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#students" data-bs-toggle="collapse" data-bs-target="#students" aria-expanded="true" aria-controls="students">
            <i class="bi bi-backpack4"></i>
            <span>Students</span>
        </a>
        <div id="students" class="collapse" aria-labelledby="headingUtilities" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                      <?php 
            if(in_array(11, $role)) {
            ?>
                <a class="collapse-item" href="view_moodle_student">Moodle Client's</a>
                <?php 
            }
                   if(in_array(22, $role)) {
            ?>
                <a class="collapse-item" href="view_student_online?count=200">Virtual Courses</a>
                 <a class="collapse-item bi bi-dash-circle" href="call_request"><span>Call Request(Virtual Course)</span></a>
                <?php } ?>
                
            </div>
        </div>
    </li>
 <?php } ?>

<?php 
   if(in_array(2, $role)) {
            ?>
    <!-- Course -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#course" data-bs-toggle="collapse" data-bs-target="#course" aria-expanded="true" aria-controls="course">
            <i class="bi bi-journal-bookmark"></i>
            <span>Course</span>
        </a>
        <div id="course" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="list_courses">Assign Virtual Courses</a>
            </div>
        </div>
    </li>
    <?php }
    
    //  if(in_array(22, $role)) {
     ?>
     <li class="nav-item system_emails">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#system_emails" data-bs-toggle="collapse" data-bs-target="#system_emails"
                    aria-expanded="true" aria-controls="system_emails">
                    <i class="bi bi-people"></i>
                    <span>System Emails</span>
                </a>
                <div id="system_emails" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
                    <div class="bg_main py-2 m-0 collapse-inner rounded">
                        <a class="collapse-item bi bi-dash-circle" href="system_emails1"> System Emails</a>
                    </div>
                     <div class="bg_main py-2 m-0 collapse-inner rounded">
                        <a class="collapse-item bi bi-dash-circle" href="email_schedules"> Schedule Emails</a>
                    </div>
                </div>
            </li>
            
<?php 
// }
 if(in_array(3, $role)) {
?>
    <!-- Summit -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#summit" data-bs-toggle="collapse" data-bs-target="#summit" aria-expanded="true" aria-controls="summit">
            <i class="bi bi-folder"></i>
            <span>Int'l Training </span>
        </a>
        <div id="summit" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="post_events">All Training's</a>
            </div>
              <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="applicant_sumit">All Applicant's</a>
            </div>
             <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="paid_ticket">Paid Ticket</a>
            </div>
            
              <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="upload">Upload Image</a>
            </div>
             <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="company_nominations">Staff Nominations</a>
            </div>
        </div>
    </li>
<?php } ?>

<?php if (in_array(88, $role) || in_array(55, $role) || in_array(777, $role)) { ?>
    <!-- Corporate Trainings -->
    <li class="nav-item">
        <a class="nav-link" href="corporate_programs.php">
            <i class="bi bi-briefcase"></i>
            <span>Corporate Trainings</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="corporate_proposals.php">
            <i class="bi bi-inboxes"></i>
            <span>Corporate Proposals</span>
        </a>
    </li>
<?php } ?>

<?php if(in_array(4, $role)) {
?>
    <!-- Marketing -->
<li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#marketing" data-bs-toggle="collapse" data-bs-target="#marketing" aria-expanded="true" aria-controls="marketing">
            <i class="bi bi-folder-check"></i>
            <span>Marketing</span>
        </a>
        <div id="marketing" class="collapse" aria-labelledby="marketing" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                <!-- Sub-Marketing Dropdown -->
                <a class="collapse-item collapsed" href="#" data-toggle="collapse" data-target="#sub-marketing" data-bs-toggle="collapse" data-bs-target="#sub-marketing" aria-expanded="true" aria-controls="sub-marketing">
                    <i class="bi bi-arrow-right-circle"></i> Email
                </a>
                <div id="sub-marketing" class="collapse" aria-labelledby="sub-marketing" data-parent="#marketing" data-bs-parent="#marketing">
                    <div class="bg_main py-2 m-0 collapse-inner rounded">
                        <a class="collapse-item" href="bulk_mail">Compose Email</a>
                        <a class="collapse-item" href="send_mail">Composed Email</a>
                        <a class="collapse-item" href="sent_mail">Sent Emails</a>
                         <a class="collapse-item" href="scheduled_mails">Scheduled Email</a>
                          <a class="collapse-item" href="import_email_data">Import Email Data</a>
                    </div>
                </div>

                <!-- Sub-Marketing Two Dropdown -->
                <a class="collapse-item collapsed" href="#" data-toggle="collapse" data-target="#sub-marketing-tw" data-bs-toggle="collapse" data-bs-target="#sub-marketing-tw" aria-expanded="true" aria-controls="sub-marketing-two">
                    <i class="bi bi-arrow-right-circle"></i> SMS
                </a>
                <div id="sub-marketing-two" class="collapse" aria-labelledby="sub-marketing-two" data-parent="#marketing" data-bs-parent="#marketing">
                    <div class="bg_main py-2 m-0 collapse-inner rounded">
                   <a class="collapse-item" href="#">Compose SMS</a>
                        <a class="collapse-item" href="#">Composed SMS</a>
                        <a class="collapse-item" href="#">Sent SMS</a>
                         <a class="collapse-item" href="#">Scheduled SMS</a>
                          <a class="collapse-item" href="#">Import SMS data</a>
                    </div>
                </div>
            </div>
        </div>
    </li>
    <?php } ?>
    
                <!-- Heading -->
            <div class="sidebar-heading">
                Task Manager
            </div>

            <!-- Existing strategy task manager menu (unchanged) -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#taskManagerMenu">
                    <i class="fas fa-tasks"></i> Strategy Tasks
                </a>
                <div class="collapse" id="taskManagerMenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link" href="task_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_tasks.php">
                                <i class="fas fa-clipboard-list"></i> My Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="task_form.php">
                                <i class="fas fa-plus-circle"></i> Create Task
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="task_import.php">
                                <i class="fas fa-file-import"></i> Import Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="task_settings.php">
                                <i class="fas fa-cogs"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- New CRM Task Manager (separate module) -->
            <li class="nav-item">
                <a class="nav-link" href="crm_tasks_dashboard.php">
                    <i class="fas fa-tasks"></i>
                    <span>CRM Tasks</span>
                </a>
            </li>
                 <li class="nav-item service">
                <a class="nav-link" href="service_requests">
                <i class="bi bi-file-code"></i>
                    <span>Service Request</span>
                </a>
            </li>
<?php
 if(in_array(33, $role)) {
?>
    <!-- Divider -->
    <hr class="sidebar-divider">
     <li class="nav-item lead_forms">
                <a class="nav-link" href="lead_forms">
                <i class="bi bi-file-code"></i>
                    <span>Lead Forms</span>
                </a>
            </li>
            
                <li class="nav-item intake">
                <a class="nav-link" href="intake">
                <i class="bi bi-file-code"></i>
                    <span>Intake</span>
                </a>
            </li>
        <?php }
         if(in_array(55, $role) || in_array(66, $role) || in_array(777, $role)) {
        ?>
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Academic Programs</div>
    <li class="nav-item">
        <a class="nav-link" href="academic_programs.php">
            <i class="fas fa-graduation-cap"></i>
            <span>Academic programs</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="free_sessions.php">
            <i class="fas fa-gift"></i>
            <span>Free sessions</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="program_ticket_applicants.php">
            <i class="fas fa-ticket-alt"></i>
            <span>Program Ticket Applicants</span>
        </a>
    </li>
        <?php }
         if(in_array(44, $role)) {
        ?>
    <!-- Enquiry Management Heading -->
    <div class="sidebar-heading">Enquiry Management</div>

    <!-- Enquiries -->
    <li class="nav-item enquiries">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#enquiries" data-bs-toggle="collapse" data-bs-target="#enquiries" aria-expanded="true" aria-controls="enquiries">
            <i class="bi bi-arrow-down-up"></i>
            <span>Enquiries</span>
        </a>
        <div id="enquiries" class="collapse" aria-labelledby="enquiries" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item bi bi-dash-circle" href="get_in_touch"><span>Get In Touch</span></a>
                <a class="collapse-item bi bi-dash-circle" href="call_request"><span>Call Request(Virtual Course)</span></a>
               
                <a class="collapse-item bi bi-dash-circle" href="loaded_data"><span>Loaded Data</span></a>
            </div>
        </div>
    </li>
    
     
    <?php } 
      if(in_array(999, $role)) {
    ?>

   <div class="sidebar-heading">Reports </div>
   
        <!-- Reports -->
   <li class="nav-item reports">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#reports" data-bs-toggle="collapse" data-bs-target="#reports"
                    aria-expanded="true" aria-controls="reports">
                    <i class="bi bi-journals"></i>
                    <span>Reports</span>
                </a>
                <div id="reports" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
                    <div class="bg_main py-2 m-0 collapse-inner rounded">
                        <a class="collapse-item bi bi-dash-circle" href="task_reports">
                            <span>Task Reports</span>
                        </a>
                        <a class="collapse-item bi bi-dash-circle" href="virtual_reports">
                            <span>Virtual Reports</span>
                        </a>
                        <a class="collapse-item bi bi-dash-circle" href="international_reports">
                            <span>International Reports</span>
                        </a>
                    </div>
                </div>
            </li>
    
    <?php 
      }
        if(in_array(777, $role)) {
      ?>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Settings Heading -->
    <div class="sidebar-heading">Settings</div>

    <!-- Users -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#users" data-bs-toggle="collapse" data-bs-target="#users" aria-expanded="true" aria-controls="users">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>
        <div id="users" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar" data-bs-parent="#accordionSidebar">
            <div class="bg_main py-2 m-0 collapse-inner rounded">
                <a class="collapse-item" href="users">All Users</a>
                <a class="collapse-item" href="create_role?action=get_user">Assign Roles</a>
                 <a class="collapse-item" href="create_user">Create User</a>
            </div>
        </div>
    </li>
    
     
            
    <?php } ?>
</ul>

        <!-- End of Sidebar -->