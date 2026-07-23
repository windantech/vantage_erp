<?php
session_start();
require_once 'header.php';
require "../../function.php";
require_once 'includes/enquiry_functions.php';

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filter_date = isset($_GET['date']) ? $_GET['date'] : 'all';
$filter_staff = isset($_GET['staff']) ? $_GET['staff'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Check user permissions
$is_admin_user = is_admin($conn);
$is_dept_head = is_department_head($conn);
$current_staff_id = isset($_SESSION['login_id']) ? intval($_SESSION['login_id']) : 0;

// Get staff list based on user role (admin sees all, dept head sees their dept, staff sees themselves)
$staff_list = get_accessible_staff_list($conn);

// Determine if user can filter by staff
$can_filter_staff = $is_admin_user || $is_dept_head;

// Build query
$query = "SELECT f.*, ru.fullname AS staff_name, ru.email AS staff_email
          FROM enquiry_followups f 
          LEFT JOIN registered_users ru ON f.staff_id = ru.id 
          WHERE 1=1";

// Status filter
if ($filter_status == 'pending') {
    $query .= " AND f.is_completed = 0";
} elseif ($filter_status == 'completed') {
    $query .= " AND f.is_completed = 1";
}

// Date filter
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$week_end = date('Y-m-d', strtotime('+7 days'));

if ($filter_date == 'overdue') {
    $query .= " AND f.reminder_date < '$today' AND f.is_completed = 0";
} elseif ($filter_date == 'today') {
    $query .= " AND f.reminder_date = '$today'";
} elseif ($filter_date == 'tomorrow') {
    $query .= " AND f.reminder_date = '$tomorrow'";
} elseif ($filter_date == 'week') {
    $query .= " AND f.reminder_date BETWEEN '$today' AND '$week_end'";
} elseif ($filter_date == 'custom' && $date_from && $date_to) {
    $query .= " AND f.reminder_date BETWEEN '" . mysqli_real_escape_string($conn, $date_from) . "' AND '" . mysqli_real_escape_string($conn, $date_to) . "'";
}

// Staff filter - based on user permissions
if ($can_filter_staff) {
    // Admin or Dept Head can filter by specific staff
    if ($filter_staff) {
        $query .= " AND f.staff_id = " . intval($filter_staff);
    } else {
        // No filter selected - apply permission-based filtering
        $query .= get_followup_permission_sql($conn, 'f');
    }
} else {
    // Regular staff only sees their own followups
    $query .= " AND f.staff_id = $current_staff_id";
}

$query .= " ORDER BY f.reminder_date ASC, f.reminder_time ASC";

$result = mysqli_query($conn, $query);
$followups = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Get enquiry details
        $enquiry_name = '';
        $enquiry_email = '';
        $enquiry_phone = '';
        $enquiry_link = '';
        
        switch ($row['enquiry_type']) {
            case 'register':
                $eq = mysqli_query($conn, "SELECT CONCAT(firstname, ' ', lastname) AS name, email, phone_number AS phone, entry_id FROM register WHERE entry_id = '{$row['enquiry_id']}' LIMIT 1");
                if ($eq && $eqr = mysqli_fetch_assoc($eq)) {
                    $enquiry_name = $eqr['name'];
                    $enquiry_email = $eqr['email'];
                    $enquiry_phone = $eqr['phone'];
                    $enquiry_link = "enquiry_details.php?type=register&id=" . $eqr['entry_id'];
                }
                break;
            case 'ticket_congress':
                $eq = mysqli_query($conn, "SELECT fullname AS name, email, phone_number AS phone, ticket_id FROM ticket_congress WHERE ticket_id = '{$row['enquiry_id']}' LIMIT 1");
                if ($eq && $eqr = mysqli_fetch_assoc($eq)) {
                    $enquiry_name = $eqr['name'];
                    $enquiry_email = $eqr['email'];
                    $enquiry_phone = $eqr['phone'];
                    $enquiry_link = "enquiry_details.php?type=ticket_congress&id=" . $eqr['ticket_id'];
                }
                break;
            case 'enquiry':
                $eq = mysqli_query($conn, "SELECT CONCAT(firstname, ' ', lastname) AS name, email, phone, enquiry_ref FROM enquiries WHERE enquiry_ref = '{$row['enquiry_id']}' LIMIT 1");
                if ($eq && $eqr = mysqli_fetch_assoc($eq)) {
                    $enquiry_name = $eqr['name'];
                    $enquiry_email = $eqr['email'];
                    $enquiry_phone = $eqr['phone'];
                    $enquiry_link = "enquiry_details.php?type=enquiry&id=" . $eqr['enquiry_ref'];
                }
                break;
        }
        
        $row['enquiry_name'] = $enquiry_name;
        $row['enquiry_email'] = $enquiry_email;
        $row['enquiry_phone'] = $enquiry_phone;
        $row['enquiry_link'] = $enquiry_link;
        $followups[] = $row;
    }
}

// Get summary counts (respecting permissions)
$count_overdue = 0;
$count_today = 0;
$count_week = 0;
$count_completed = 0;

// Use permission-based SQL filter
$staff_filter_sql = get_followup_permission_sql($conn, '');
// Remove table alias for this query since we're using enquiry_followups directly
$staff_filter_sql = str_replace('.staff_id', 'staff_id', $staff_filter_sql);

$counts_query = mysqli_query($conn, "SELECT 
    SUM(CASE WHEN reminder_date < CURDATE() AND is_completed = 0 THEN 1 ELSE 0 END) AS overdue,
    SUM(CASE WHEN reminder_date = CURDATE() AND is_completed = 0 THEN 1 ELSE 0 END) AS today,
    SUM(CASE WHEN reminder_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND is_completed = 0 THEN 1 ELSE 0 END) AS week,
    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS completed
    FROM enquiry_followups WHERE 1=1 $staff_filter_sql");

if ($counts_query && $counts = mysqli_fetch_assoc($counts_query)) {
    $count_overdue = intval($counts['overdue']);
    $count_today = intval($counts['today']);
    $count_week = intval($counts['week']);
    $count_completed = intval($counts['completed']);
}

// Session messages
$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<style>
.stat-card { border-radius: 10px; transition: all 0.2s; cursor: pointer; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.stat-card.active { border: 2px solid #0d6efd; }
.followup-card { border-radius: 8px; margin-bottom: 15px; transition: all 0.2s; }
.followup-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.followup-card.overdue { border-left: 4px solid #dc3545; }
.followup-card.today { border-left: 4px solid #ffc107; }
.followup-card.upcoming { border-left: 4px solid #0d6efd; }
.followup-card.completed { border-left: 4px solid #198754; opacity: 0.7; }
.time-badge { font-size: 0.75rem; padding: 3px 8px; }
.filter-btn { border-radius: 20px; }
.filter-btn.active { background-color: #0d6efd; color: white; }
.quick-action-btn { padding: 5px 10px; font-size: 0.8rem; }
.staff-badge { font-size: 0.75rem; }
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-calendar-check me-2"></i>Follow-ups Management</h4>
                    <p class="text-muted mb-0">
                        <?php if ($is_admin_user): ?>
                        Manage all follow-up tasks across the organization
                        <?php elseif ($is_dept_head): ?>
                        Manage follow-up tasks for your department
                        <?php else: ?>
                        Track and manage your follow-up tasks
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <a href="enquiry_dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $message['text']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <a href="?status=pending&date=overdue<?php echo $can_filter_staff && $filter_staff ? '&staff='.$filter_staff : ''; ?>" class="text-decoration-none">
                        <div class="card stat-card border-0 shadow-sm <?php echo ($filter_date == 'overdue') ? 'active' : ''; ?>" style="border-left: 4px solid #dc3545 !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Overdue</p>
                                        <h3 class="mb-0 text-danger"><?php echo $count_overdue; ?></h3>
                                    </div>
                                    <div class="text-danger fs-2"><i class="bi bi-exclamation-triangle"></i></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="?status=pending&date=today<?php echo $can_filter_staff && $filter_staff ? '&staff='.$filter_staff : ''; ?>" class="text-decoration-none">
                        <div class="card stat-card border-0 shadow-sm <?php echo ($filter_date == 'today') ? 'active' : ''; ?>" style="border-left: 4px solid #ffc107 !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Due Today</p>
                                        <h3 class="mb-0 text-warning"><?php echo $count_today; ?></h3>
                                    </div>
                                    <div class="text-warning fs-2"><i class="bi bi-clock"></i></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="?status=pending&date=week<?php echo $can_filter_staff && $filter_staff ? '&staff='.$filter_staff : ''; ?>" class="text-decoration-none">
                        <div class="card stat-card border-0 shadow-sm <?php echo ($filter_date == 'week') ? 'active' : ''; ?>" style="border-left: 4px solid #0d6efd !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">This Week</p>
                                        <h3 class="mb-0 text-primary"><?php echo $count_week; ?></h3>
                                    </div>
                                    <div class="text-primary fs-2"><i class="bi bi-calendar-week"></i></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="?status=completed&date=all<?php echo $can_filter_staff && $filter_staff ? '&staff='.$filter_staff : ''; ?>" class="text-decoration-none">
                        <div class="card stat-card border-0 shadow-sm <?php echo ($filter_status == 'completed') ? 'active' : ''; ?>" style="border-left: 4px solid #198754 !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Completed</p>
                                        <h3 class="mb-0 text-success"><?php echo $count_completed; ?></h3>
                                    </div>
                                    <div class="text-success fs-2"><i class="bi bi-check-circle"></i></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Date Range</label>
                            <select name="date" class="form-select form-select-sm" onchange="toggleCustomDate(this.value)">
                                <option value="all" <?php echo $filter_date == 'all' ? 'selected' : ''; ?>>All Dates</option>
                                <option value="overdue" <?php echo $filter_date == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="tomorrow" <?php echo $filter_date == 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="custom" <?php echo $filter_date == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="customDateFrom" style="display: <?php echo $filter_date == 'custom' ? 'block' : 'none'; ?>">
                            <label class="form-label small">From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2" id="customDateTo" style="display: <?php echo $filter_date == 'custom' ? 'block' : 'none'; ?>">
                            <label class="form-label small">To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                        </div>
                        <?php if ($can_filter_staff): ?>
                        <div class="col-md-2">
                            <label class="form-label small">Assigned To</label>
                            <select name="staff" class="form-select form-select-sm">
                                <option value="">All <?php echo $is_dept_head && !$is_admin_user ? 'Department ' : ''; ?>Staff</option>
                                <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $filter_staff == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['fullname']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Follow-ups List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($followups)): ?>
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-calendar-check fs-1 text-muted d-block mb-3"></i>
                            <p class="text-muted mb-0">No follow-ups found matching your criteria</p>
                        </div>
                    </div>
                    <?php else: ?>
                    
                    <?php 
                    // Group by date
                    $grouped = [];
                    foreach ($followups as $fu) {
                        $date_key = date('Y-m-d', strtotime($fu['reminder_date']));
                        if (!isset($grouped[$date_key])) {
                            $grouped[$date_key] = [];
                        }
                        $grouped[$date_key][] = $fu;
                    }
                    
                    foreach ($grouped as $date => $items):
                        $date_label = '';
                        $is_overdue = strtotime($date) < strtotime($today);
                        $is_today = ($date == $today);
                        $is_tomorrow = ($date == $tomorrow);
                        
                        if ($is_today) {
                            $date_label = 'Today';
                        } elseif ($is_tomorrow) {
                            $date_label = 'Tomorrow';
                        } elseif ($is_overdue) {
                            $date_label = date('l, d M Y', strtotime($date)) . ' (Overdue)';
                        } else {
                            $date_label = date('l, d M Y', strtotime($date));
                        }
                    ?>
                    <h6 class="text-muted mb-3 mt-4">
                        <i class="bi bi-calendar3 me-2"></i><?php echo $date_label; ?>
                        <span class="badge bg-secondary ms-2"><?php echo count($items); ?></span>
                    </h6>
                    
                    <?php foreach ($items as $fu): 
                        $card_class = 'upcoming';
                        if ($fu['is_completed']) {
                            $card_class = 'completed';
                        } elseif ($is_overdue) {
                            $card_class = 'overdue';
                        } elseif ($is_today) {
                            $card_class = 'today';
                        }
                    ?>
                    <div class="card followup-card shadow-sm <?php echo $card_class; ?>">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <?php if ($fu['is_completed']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg"></i></span>
                                            <?php elseif ($is_overdue): ?>
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-lg"></i></span>
                                            <?php elseif ($is_today): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i></span>
                                            <?php else: ?>
                                            <span class="badge bg-primary"><i class="bi bi-calendar"></i></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($fu['next_step']); ?></h6>
                                            <p class="mb-1 small">
                                                <a href="<?php echo $fu['enquiry_link']; ?>" class="text-decoration-none">
                                                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($fu['enquiry_name']); ?>
                                                </a>
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                <?php if ($fu['enquiry_email']): ?>
                                                <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($fu['enquiry_email']); ?>
                                                <?php endif; ?>
                                                <?php if ($fu['enquiry_phone']): ?>
                                                <i class="bi bi-telephone ms-2 me-1"></i><?php echo htmlspecialchars($fu['enquiry_phone']); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <?php if ($fu['action_taken']): ?>
                                    <p class="small mb-1"><strong>Action:</strong> <?php echo htmlspecialchars(substr($fu['action_taken'], 0, 50)) . (strlen($fu['action_taken']) > 50 ? '...' : ''); ?></p>
                                    <?php endif; ?>
                                    <?php if ($fu['client_response']): ?>
                                    <p class="small mb-0 text-muted"><strong>Response:</strong> <?php echo htmlspecialchars(substr($fu['client_response'], 0, 50)) . (strlen($fu['client_response']) > 50 ? '...' : ''); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2 text-center">
                                    <span class="badge time-badge bg-light text-dark">
                                        <i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($fu['reminder_time'])); ?>
                                    </span>
                                    <?php if ($is_overdue && !$fu['is_completed']): ?>
                                    <br><small class="text-danger"><?php echo ceil((strtotime($today) - strtotime($date)) / 86400); ?> days overdue</small>
                                    <?php endif; ?>
                                    
                                    <!-- Show Assigned Staff -->
                                    <div class="mt-2">
                                        <?php if ($fu['staff_name']): ?>
                                        <span class="badge staff-badge bg-info text-white" title="Assigned to">
                                            <i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars($fu['staff_name']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge staff-badge bg-secondary" title="Unassigned">
                                            <i class="bi bi-person me-1"></i>Unassigned
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <?php if (!$fu['is_completed']): ?>
                                    <form action="includes/process_enquiry.php" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="complete_followup">
                                        <input type="hidden" name="followup_id" value="<?php echo $fu['id']; ?>">
                                        <input type="hidden" name="redirect" value="followups.php?status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success quick-action-btn" title="Mark Complete">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-primary quick-action-btn" data-bs-toggle="modal" data-bs-target="#rescheduleModal<?php echo $fu['id']; ?>" title="Reschedule">
                                        <i class="bi bi-calendar-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($fu['enquiry_phone']): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $fu['enquiry_phone']); ?>" target="_blank" class="btn btn-sm btn-outline-success quick-action-btn" title="WhatsApp">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo $fu['enquiry_link']; ?>" class="btn btn-sm btn-outline-secondary quick-action-btn" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reschedule Modal -->
                    <div class="modal fade" id="rescheduleModal<?php echo $fu['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Reschedule Follow-up</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="includes/process_enquiry.php" method="POST">
                                    <input type="hidden" name="action" value="reschedule_followup">
                                    <input type="hidden" name="followup_id" value="<?php echo $fu['id']; ?>">
                                    <input type="hidden" name="redirect" value="followups.php?status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>">
                                    <div class="modal-body">
                                        <p class="text-muted mb-3">
                                            <strong><?php echo htmlspecialchars($fu['next_step']); ?></strong><br>
                                            Client: <?php echo htmlspecialchars($fu['enquiry_name']); ?>
                                        </p>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">New Date</label>
                                                <input type="date" name="new_date" class="form-control" required value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">New Time</label>
                                                <input type="time" name="new_time" class="form-control" value="<?php echo date('H:i', strtotime($fu['reminder_time'])); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Reschedule</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function toggleCustomDate(value) {
    document.getElementById('customDateFrom').style.display = value === 'custom' ? 'block' : 'none';
    document.getElementById('customDateTo').style.display = value === 'custom' ? 'block' : 'none';
}
</script>

<?php require_once 'footer.php'; ?>