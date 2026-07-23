<?php
session_start();
require_once 'header.php';
require "../../function.php";
require_once 'includes/enquiry_functions.php';

// Get dashboard statistics
$stats = get_dashboard_stats($conn);

// Get staff list for filters
$staff_list = get_staff_list($conn);

// Get enquiry sources
$sources = get_enquiry_sources($conn);

// ============================================
// Build course/event filter lists - ONLY what the current user can access
// ============================================
$my_courses = get_staff_courses($conn);   // 'all' or array of course_id values
$my_events  = get_staff_events($conn);    // 'all' or array of event_id values

// Build the course dropdown list (Virtual)
$filter_course_list = [];
if ($my_courses === 'all') {
    $res = mysqli_query($conn, "SELECT course_id, course FROM course WHERE status = 1 ORDER BY course ASC");
    while ($res && $r = mysqli_fetch_assoc($res)) $filter_course_list[] = $r;
} elseif (!empty($my_courses)) {
    $ids = implode(',', array_map('intval', $my_courses));
    $res = mysqli_query($conn, "SELECT course_id, course FROM course WHERE status = 1 AND course_id IN ($ids) ORDER BY course ASC");
    while ($res && $r = mysqli_fetch_assoc($res)) $filter_course_list[] = $r;
}

// Build the event dropdown list (International)
$filter_event_list = [];
if ($my_events === 'all') {
    $res = mysqli_query($conn, "SELECT event_id, event_title, start_on FROM Event WHERE status = 1 ORDER BY start_on DESC");
    while ($res && $r = mysqli_fetch_assoc($res)) $filter_event_list[] = $r;
} elseif (!empty($my_events)) {
    $ids = implode(',', array_map('intval', $my_events));
    $res = mysqli_query($conn, "SELECT event_id, event_title, start_on FROM Event WHERE status = 1 AND event_id IN ($ids) ORDER BY start_on DESC");
    while ($res && $r = mysqli_fetch_assoc($res)) $filter_event_list[] = $r;
}

// Build the intake dropdown list (Virtual) - intakes belong to courses
$filter_intake_list = [];
if ($my_courses === 'all') {
    $res = mysqli_query($conn, "SELECT i.intake_id, i.description, c.course
                                FROM intake i
                                JOIN course c ON c.course_id = i.course_id
                                ORDER BY i.date_created DESC");
    while ($res && $r = mysqli_fetch_assoc($res)) $filter_intake_list[] = $r;
} elseif (!empty($my_courses)) {
    $ids = implode(',', array_map('intval', $my_courses));
    $res = mysqli_query($conn, "SELECT i.intake_id, i.description, c.course
                                FROM intake i
                                JOIN course c ON c.course_id = i.course_id
                                WHERE i.course_id IN ($ids)
                                ORDER BY i.date_created DESC");
    while ($res && $r = mysqli_fetch_assoc($res)) $filter_intake_list[] = $r;
}

// Get filter values from GET parameters
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'interest_type' => isset($_GET['interest_type']) ? $_GET['interest_type'] : '',
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'priority' => isset($_GET['priority']) ? $_GET['priority'] : '',
    'assigned_to' => isset($_GET['assigned_to']) ? $_GET['assigned_to'] : '',
    'is_paid' => isset($_GET['is_paid']) ? $_GET['is_paid'] : '',
    'source_table' => isset($_GET['source_table']) ? $_GET['source_table'] : '',
    'program_interest' => isset($_GET['program_interest']) ? intval($_GET['program_interest']) : '',
    'event_interest' => isset($_GET['event_interest']) ? intval($_GET['event_interest']) : '',
    'intake' => isset($_GET['intake']) ? $_GET['intake'] : '',
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'created_at',
    'sort_dir' => isset($_GET['sort_dir']) ? $_GET['sort_dir'] : 'DESC'
];

// Limit records - default 250, can be changed via dropdown
$record_limit = isset($_GET['limit']) ? intval($_GET['limit']) : 250;
if ($record_limit < 50) $record_limit = 50;
if ($record_limit > 1000) $record_limit = 1000;

// Get enquiries with limit
$result = get_all_enquiries($conn, $filters, 1, $record_limit);
$enquiries = $result['data'];
$total_available = $result['total'];

// Get today's and overdue follow-ups for notifications
$todays_followups = get_todays_followups($conn);
$overdue_followups = get_overdue_followups($conn);
?>

<style>
.stat-card { border-radius: 10px; transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-5px); }
.stat-icon { font-size: 2.5rem; opacity: 0.8; }
.stat-number { font-size: 2rem; font-weight: bold; }
.notification-badge { position: absolute; top: -5px; right: -5px; font-size: 0.7rem; }
.flag-badge { font-size: 0.7rem; padding: 2px 6px; margin-right: 3px; }
.table-row-clickable { cursor: pointer; }
.table-row-clickable:hover { background-color: #f8f9fa; }
.priority-high { border-left: 4px solid #dc3545; }
.priority-medium { border-left: 4px solid #ffc107; }
.priority-low { border-left: 4px solid #6c757d; }
.notification-panel { max-height: 300px; overflow-y: auto; }
.filter-section { background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-kanban me-2"></i>Enquiry Dashboard</h4>
                    <p class="text-muted mb-0">Manage all enquiries from one place </p>
                </div>
                <div class="d-flex gap-2">

                    <!-- Back Button -->
                    <a href="staff_performance.php" class="btn btn-outline-secondary back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Performance  Dashboard
                    </a>
                    <!-- Follow-ups Link -->
                    <a href="followups.php" class="btn btn-outline-warning position-relative">
                        <i class="bi bi-calendar-check me-1"></i>Follow-ups
                        <?php if (count($overdue_followups) > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo count($overdue_followups); ?>
                        </span>
                        <?php endif; ?>
                    </a>

                    <!-- Notifications Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if (count($todays_followups) + count($overdue_followups) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                <?php echo count($todays_followups) + count($overdue_followups); ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px;">
                            <div class="p-3 bg-light border-bottom"><strong>Notifications</strong></div>
                            <div class="notification-panel">
                                <?php if (count($overdue_followups) > 0): ?>
                                    <div class="px-3 py-2 bg-danger bg-opacity-10">
                                        <small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Overdue (<?php echo count($overdue_followups); ?>)</small>
                                    </div>
                                    <?php foreach (array_slice($overdue_followups, 0, 5) as $fu): ?>
                                    <a href="enquiry_details.php?type=<?php echo $fu['enquiry_type']; ?>&id=<?php echo $fu['enquiry_id']; ?>" class="dropdown-item py-2 border-bottom">
                                        <div class="d-flex justify-content-between">
                                            <small class="fw-bold"><?php echo htmlspecialchars($fu['next_step']); ?></small>
                                            <small class="text-danger"><?php echo $fu['days_overdue']; ?> days overdue</small>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (count($todays_followups) > 0): ?>
                                    <div class="px-3 py-2 bg-warning bg-opacity-10">
                                        <small class="text-warning fw-bold"><i class="bi bi-clock me-1"></i>Due Today (<?php echo count($todays_followups); ?>)</small>
                                    </div>
                                    <?php foreach (array_slice($todays_followups, 0, 5) as $fu): ?>
                                    <a href="enquiry_details.php?type=<?php echo $fu['enquiry_type']; ?>&id=<?php echo $fu['enquiry_id']; ?>" class="dropdown-item py-2 border-bottom">
                                        <small class="fw-bold"><?php echo htmlspecialchars($fu['next_step']); ?></small>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (count($todays_followups) + count($overdue_followups) == 0): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="bi bi-check-circle fs-3 d-block mb-2"></i>No pending follow-ups
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (count($todays_followups) + count($overdue_followups) > 0): ?>
                            <div class="p-2 border-top text-center">
                                <a href="followups.php" class="small">View all follow-ups</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEnquiryModal">
                        <i class="bi bi-plus-lg me-1"></i> New Enquiry
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="mb-1 opacity-75">Total Enquiries</p>
                                    <h3 class="stat-number mb-0"><?php echo number_format($stats['total_enquiries']); ?></h3>
                                    <small class="opacity-75"><?php echo $stats['new_today']; ?> new today</small>
                                </div>
                                <div class="stat-icon"><i class="bi bi-people"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="mb-1 opacity-75">Virtual Courses</p>
                                    <h3 class="stat-number mb-0"><?php echo number_format($stats['by_type']['virtual'] ?? 0); ?></h3>
                                    <small class="opacity-75">Online programs</small>
                                </div>
                                <div class="stat-icon"><i class="bi bi-laptop"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="mb-1 opacity-75">International Events</p>
                                    <h3 class="stat-number mb-0"><?php echo number_format($stats['by_type']['international'] ?? 0); ?></h3>
                                    <small class="opacity-75">Physical events</small>
                                </div>
                                <div class="stat-icon"><i class="bi bi-globe"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="mb-1 opacity-75">Payments</p>
                                    <h3 class="stat-number mb-0"><?php echo number_format($stats['paid_count']); ?></h3>
                                    <small class="opacity-75"><?php echo $stats['unpaid_count']; ?> pending</small>
                                </div>
                                <div class="stat-icon"><i class="bi bi-credit-card"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Follow-up Alert -->
            <?php if (count($overdue_followups) > 0): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <strong><?php echo count($overdue_followups); ?> overdue follow-up(s)</strong> require immediate attention!
            </div>
            <?php endif; ?>

            <!-- Filters Section -->
            <div class="filter-section">
                <form method="GET" action="" id="filterForm">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, email, phone..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Type</label>
                            <select name="interest_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="virtual" <?php echo $filters['interest_type'] == 'virtual' ? 'selected' : ''; ?>>Virtual</option>
                                <option value="international" <?php echo $filters['interest_type'] == 'international' ? 'selected' : ''; ?>>International</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="new" <?php echo $filters['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="contacted" <?php echo $filters['status'] == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                <option value="qualified" <?php echo $filters['status'] == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                                <option value="converted" <?php echo $filters['status'] == 'converted' ? 'selected' : ''; ?>>Converted</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Payment</label>
                            <select name="is_paid" class="form-select">
                                <option value="">All</option>
                                <option value="paid" <?php echo $filters['is_paid'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="unpaid" <?php echo $filters['is_paid'] == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Assigned To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">All Staff</option>
                                <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $filters['assigned_to'] == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['fullname']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (!empty($filter_course_list)): ?>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Course (Virtual)</label>
                            <select name="program_interest" class="form-select">
                                <option value="">All My Courses</option>
                                <?php foreach ($filter_course_list as $c): ?>
                                <option value="<?php echo $c['course_id']; ?>" <?php echo $filters['program_interest'] == $c['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['course']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($filter_event_list)): ?>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Event (International)</label>
                            <select name="event_interest" class="form-select">
                                <option value="">All My Events</option>
                                <?php foreach ($filter_event_list as $ev): ?>
                                <option value="<?php echo $ev['event_id']; ?>" <?php echo $filters['event_interest'] == $ev['event_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ev['event_title']); ?> (<?php echo $ev['start_on']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($filter_intake_list)): ?>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Intake (Virtual)</label>
                            <select name="intake" class="form-select">
                                <option value="">All Intakes</option>
                                <?php foreach ($filter_intake_list as $ik): ?>
                                <option value="<?php echo htmlspecialchars($ik['intake_id']); ?>" <?php echo $filters['intake'] == $ik['intake_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ik['description'] . ' — ' . $ik['course']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <a href="enquiry_dashboard.php" class="small text-muted"><i class="bi bi-x-circle me-1"></i>Clear filters</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Enquiries Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 fw-bold text-uppercase d-inline">All Enquiries</h6>
                        <small class="text-muted ms-2">(Showing <?php echo count($enquiries); ?> of <?php echo number_format($total_available); ?>)</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <!-- Records Limit Dropdown -->
                        <select id="recordLimit" class="form-select form-select-sm" style="width: auto;" onchange="changeRecordLimit(this.value)">
                            <option value="50" <?php echo $record_limit == 50 ? 'selected' : ''; ?>>50 records</option>
                            <option value="100" <?php echo $record_limit == 100 ? 'selected' : ''; ?>>100 records</option>
                            <option value="250" <?php echo $record_limit == 250 ? 'selected' : ''; ?>>250 records</option>
                            <option value="500" <?php echo $record_limit == 500 ? 'selected' : ''; ?>>500 records</option>
                            <option value="1000" <?php echo $record_limit == 1000 ? 'selected' : ''; ?>>1000 records</option>
                        </select>
                        <button onclick="exportTableToExcel()" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-file-spreadsheet me-1"></i>Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="enquiriesTable" width="100%">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Reference</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Type</th>
                                    <th>Program/Event</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Source</th>
                                    <th>Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($enquiries) > 0):
                                    foreach ($enquiries as $row):
                                        $priority_class = 'priority-' . $row['priority'];
                                ?>
                                <tr class="table-row-clickable <?php echo $priority_class; ?>" data-href="enquiry_details.php?type=<?php echo $row['source_table']; ?>&id=<?php echo $row['reference']; ?>" style="cursor: pointer;" onclick="rowClick(event, this)">
                                    <td class="ps-3">
                                        <strong><?php echo htmlspecialchars($row['reference']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php if ($row['source_table'] == 'enquiry'): ?>
                                                <span class="badge bg-secondary">Enquiry</span>
                                            <?php elseif ($row['source_table'] == 'register'): ?>
                                                <span class="badge bg-success">Virtual</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">International</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(trim($row['fullname'])); ?></strong>
                                        <?php if (!empty($row['flags'])): ?>
                                        <br>
                                        <?php foreach ($row['flags'] as $flag):
                                            $flag_info = get_flag_display($flag);
                                        ?>
                                        <span class="badge bg-<?php echo $flag_info['color']; ?> flag-badge">
                                            <i class="<?php echo $flag_info['icon']; ?>"></i> <?php echo $flag_info['label']; ?>
                                        </span>
                                        <?php endforeach; endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['email']): ?>
                                        <!--<small><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($row['email']); ?></small><br>-->
                                        <?php endif; ?>
                                        <?php if ($row['phone']): ?>
                                        <small><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($row['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['interest_type'] == 'virtual'): ?>
                                            <span class="badge bg-success-subtle text-success">Virtual</span>
                                        <?php elseif ($row['interest_type'] == 'international'): ?>
                                            <span class="badge bg-info-subtle text-info">International</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">Undecided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Show program name for virtual, event name for international
                                        if ($row['interest_type'] == 'virtual' || $row['source_table'] == 'register') {
                                            $display_name = $row['program_name'] ?: '-';
                                        } else {
                                            $display_name = $row['event_name'] ?: '-';
                                        }
                                        echo htmlspecialchars(strlen($display_name) > 30 ? substr($display_name, 0, 30) . '...' : $display_name);
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = ['new'=>'primary','contacted'=>'info','qualified'=>'warning','proposal_sent'=>'secondary','negotiating'=>'dark','converted'=>'success','lost'=>'danger','enrolled'=>'success'];
                                        $status_color = $status_colors[$row['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['is_paid']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                            <?php if ($row['amount_paid'] > 0): ?>
                                            <br><small class="text-muted">$<?php echo number_format($row['amount_paid'], 2); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($row['source_name'] ?? '-'); ?></small></td>
                                    <td data-order="<?php echo strtotime($row['created_at']); ?>">
                                        <small><?php echo date('d M Y', strtotime($row['created_at'])); ?></small><br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center action-cell">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="enquiry_details.php?type=<?php echo $row['source_table']; ?>&id=<?php echo $row['reference']; ?>"><i class="bi bi-eye me-2"></i>View</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="openFollowupModal('<?php echo $row['source_table']; ?>', '<?php echo $row['reference']; ?>'); return false;"><i class="bi bi-calendar-plus me-2"></i>Follow-up</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="openFlagModal('<?php echo $row['source_table']; ?>', '<?php echo $row['reference']; ?>'); return false;"><i class="bi bi-flag me-2"></i>Flag</a></li>
                                                <?php if ($row['source_table'] == 'enquiry'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-success" href="#" onclick="openConvertModal(<?php echo $row['record_id']; ?>, '<?php echo $row['interest_type']; ?>'); return false;"><i class="bi bi-arrow-right-circle me-2"></i>Convert</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                                        <p class="text-muted">No enquiries found</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Add Enquiry Modal -->
<div class="modal fade" id="addEnquiryModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg_main text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Enquiry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="includes/process_enquiry.php" method="POST" id="addEnquiryForm">
                    <input type="hidden" name="action" value="add">

                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-2"></i><strong>Minimal Entry:</strong> Only email OR phone is required.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+254...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="firstname" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastname" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Source <span class="text-danger">*</span></label>
                            <select name="source_id" class="form-select" required>
                                <option value="">Select source...</option>
                                <?php foreach ($sources as $source): ?>
                                <option value="<?php echo $source['id']; ?>"><?php echo htmlspecialchars($source['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Interest Type</label>
                            <select name="interest_type" id="interestType" class="form-select" onchange="toggleInterestFields()">
                                <option value="undecided">Undecided</option>
                                <option value="virtual">Virtual Course</option>
                                <option value="international">International Event</option>
                            </select>
                        </div>
                        <div class="col-12" id="programField" style="display: none;">
                            <label class="form-label">Program Interest</label>
                            <select name="program_interest" class="form-select">
                                <option value="">Select program...</option>
                                <?php
                                $courses = mysqli_query($conn, "SELECT id, course FROM course WHERE status = 1 ORDER BY course ASC");
                                if ($courses) while ($course = mysqli_fetch_assoc($courses)):
                                ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12" id="eventField" style="display: none;">
                            <label class="form-label">Event Interest</label>
                            <select name="event_interest" class="form-select">
                                <option value="">Select event...</option>
                                <?php
                                $events = mysqli_query($conn, "SELECT event_id, event_title, start_on, location FROM Event ORDER BY start_on DESC");
                                if ($events) while ($event = mysqli_fetch_assoc($events)):
                                ?>
                                <option value="<?php echo $event['event_id']; ?>"><?php echo htmlspecialchars($event['event_title']); ?> (<?php echo $event['start_on']; ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <select name="country" class="form-select">
                                <option value="">Select...</option>
                                <option value="Kenya">Kenya</option>
                                <option value="Uganda">Uganda</option>
                                <option value="Tanzania">Tanzania</option>
                                <option value="Rwanda">Rwanda</option>
                                <option value="Ethiopia">Ethiopia</option>
                                <option value="Nigeria">Nigeria</option>
                                <option value="South Africa">South Africa</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Organization</label>
                            <input type="text" name="organization" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Not assigned</option>
                                <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['fullname']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Enquiry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Follow-up Modal -->
<div class="modal fade" id="followupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg_main text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Add Follow-up</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="includes/process_enquiry.php" method="POST">
                    <input type="hidden" name="action" value="add_followup">
                    <input type="hidden" name="enquiry_type" id="followup_enquiry_type">
                    <input type="hidden" name="enquiry_id" id="followup_enquiry_id">

                    <div class="mb-3">
                        <label class="form-label">Action Taken</label>
                        <textarea name="action_taken" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Client Response</label>
                        <textarea name="client_response" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Next Step <span class="text-danger">*</span></label>
                        <select name="next_step" class="form-select" required>
                            <option value="">Select...</option>
                            <option value="Send proposal">Send proposal</option>
                            <option value="Schedule call">Schedule call</option>
                            <option value="Follow up email">Follow up email</option>
                            <option value="Send invoice">Send invoice</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Reminder Date <span class="text-danger">*</span></label>
                            <input type="date" name="reminder_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" name="reminder_time" class="form-control" value="09:00">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Flag Modal -->
<div class="modal fade" id="flagModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg_main text-white">
                <h5 class="modal-title"><i class="bi bi-flag me-2"></i>Add Flag</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="includes/process_enquiry.php" method="POST">
                    <input type="hidden" name="action" value="add_flag">
                    <input type="hidden" name="enquiry_type" id="flag_enquiry_type">
                    <input type="hidden" name="enquiry_id" id="flag_enquiry_id">

                    <div class="mb-3">
                        <label class="form-label">Flag Type <span class="text-danger">*</span></label>
                        <select name="flag_type" class="form-select" required>
                            <option value="">Select...</option>
                            <option value="high_potential">⭐ High Potential</option>
                            <option value="urgent">⚠️ Urgent</option>
                            <option value="vip">💎 VIP</option>
                            <option value="needs_attention">🔔 Needs Attention</option>
                            <option value="cold_lead">❄️ Cold Lead</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Flag</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Convert Modal -->
<div class="modal fade" id="convertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2"></i>Convert Enquiry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="includes/process_enquiry.php" method="POST">
                    <input type="hidden" name="action" value="convert">
                    <input type="hidden" name="enquiry_id" id="convert_enquiry_id">
                    <input type="hidden" name="convert_to" id="convert_to">

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>This will create a full record.
                    </div>
                    <p id="convertMessage"></p>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Convert Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
// Change record limit - reload page with new limit
function changeRecordLimit(limit) {
    var url = new URL(window.location.href);
    url.searchParams.set('limit', limit);
    window.location.href = url.toString();
}

$(document).ready(function() {
    // Initialize DataTable
    var table = $('#enquiriesTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[8, 'desc']], // Sort by date column descending
        language: {
            search: "",
            searchPlaceholder: "Search in table...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ enquiries",
            infoEmpty: "No enquiries found",
            infoFiltered: "(filtered from _MAX_ total)",
            paginate: {
                first: '<i class="bi bi-chevron-double-left"></i>',
                last: '<i class="bi bi-chevron-double-right"></i>',
                next: '<i class="bi bi-chevron-right"></i>',
                previous: '<i class="bi bi-chevron-left"></i>'
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        columnDefs: [
            { orderable: false, targets: [9] } // Disable sorting on Actions column
        ]
    });

    // Row click to navigate - using event delegation on table body
    $('#enquiriesTable tbody').on('click', 'tr', function(e) {
        // Don't navigate if clicking on action cell, dropdown, button or link
        if ($(e.target).closest('.action-cell').length > 0 ||
            $(e.target).closest('.dropdown').length > 0 ||
            $(e.target).closest('button').length > 0 ||
            $(e.target).closest('a').length > 0) {
            return;
        }

        var href = $(this).attr('data-href');
        if (href) {
            window.location.href = href;
        }
    });

    // Add pointer cursor to clickable rows
    $('#enquiriesTable tbody tr[data-href]').css('cursor', 'pointer');
});

function toggleInterestFields() {
    var type = document.getElementById('interestType').value;
    document.getElementById('programField').style.display = type === 'virtual' ? 'block' : 'none';
    document.getElementById('eventField').style.display = type === 'international' ? 'block' : 'none';
}

// Row click handler
function rowClick(event, row) {
    // Don't navigate if clicking on action cell, dropdown, button or link
    var target = event.target;
    if (target.closest('.action-cell') ||
        target.closest('.dropdown') ||
        target.closest('button') ||
        target.closest('a')) {
        return;
    }

    var href = row.getAttribute('data-href');
    if (href) {
        window.location.href = href;
    }
}

function openFollowupModal(type, id) {
    document.getElementById('followup_enquiry_type').value = type;
    document.getElementById('followup_enquiry_id').value = id;
    new bootstrap.Modal(document.getElementById('followupModal')).show();
}

function openFlagModal(type, id) {
    document.getElementById('flag_enquiry_type').value = type;
    document.getElementById('flag_enquiry_id').value = id;
    new bootstrap.Modal(document.getElementById('flagModal')).show();
}

function openConvertModal(id, interestType) {
    if (interestType === 'undecided') {
        alert('Please set the interest type before converting.');
        return;
    }
    document.getElementById('convert_enquiry_id').value = id;
    var convertTo = interestType === 'virtual' ? 'register' : 'ticket_congress';
    document.getElementById('convert_to').value = convertTo;
    document.getElementById('convertMessage').innerHTML = 'Convert to: <strong>' + convertTo + '</strong>';
    new bootstrap.Modal(document.getElementById('convertModal')).show();
}

function exportTableToExcel() {
    var table = $('#enquiriesTable').DataTable();

    // Get all data (not just visible)
    var allData = table.rows().data().toArray();

    // Extract headers
    var headers = [];
    $('#enquiriesTable thead tr th').each(function(index) {
        if (index < 9) { // Exclude Actions column
            headers.push($(this).text().trim());
        }
    });

    // Convert data to clean format
    var cleanData = [];
    allData.forEach(function(row) {
        var cleanRow = [];
        for (var i = 0; i < 9; i++) { // Exclude Actions column
            var cell = $("<div>").html(row[i]).text().trim();
            cleanRow.push(cell);
        }
        cleanData.push(cleanRow);
    });

    // Add headers
    cleanData.unshift(headers);

    // Create workbook and export
    var worksheet = XLSX.utils.aoa_to_sheet(cleanData);
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, worksheet, "Enquiries");
    XLSX.writeFile(wb, "enquiries_" + Date.now() + ".xlsx");
}

document.getElementById('addEnquiryForm').addEventListener('submit', function(e) {
    var email = this.querySelector('input[name="email"]').value;
    var phone = this.querySelector('input[name="phone"]').value;
    if (!email && !phone) {
        e.preventDefault();
        alert('Please provide either email or phone.');
    }
});
</script>

<?php require_once 'footer.php'; ?>