<?php
session_start();
require_once 'header.php';
require "../../function.php";
require_once 'includes/enquiry_functions.php';

// Check permissions - different levels see different reports
$is_admin_user = is_admin($conn);
$is_mgr = is_manager($conn);
$is_dept_head = is_department_head($conn);
$current_staff_id = isset($_SESSION['login_id']) ? intval($_SESSION['login_id']) : 0;

// Currency conversion rate (USD to KES)
define('USD_TO_KES_RATE', 129);

// Determine user's report access level
$access_level = 'staff'; // default
if ($is_admin_user) {
    $access_level = 'admin';
} elseif ($is_mgr) {
    $access_level = 'manager';
} elseif ($is_dept_head) {
    $access_level = 'department_head';
}

// Get filter parameters
$date_range = isset($_GET['range']) ? $_GET['range'] : 'this_month';
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : '';
$staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : '';

// Calculate date range
$today = date('Y-m-d');
$date_from = '';
$date_to = $today;

switch ($date_range) {
    case 'today':
        $date_from = $today;
        break;
    case 'yesterday':
        $date_from = date('Y-m-d', strtotime('-1 day'));
        $date_to = $date_from;
        break;
    case 'this_week':
        $date_from = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'last_week':
        $date_from = date('Y-m-d', strtotime('monday last week'));
        $date_to = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'this_month':
        $date_from = date('Y-m-01');
        break;
    case 'last_month':
        $date_from = date('Y-m-01', strtotime('first day of last month'));
        $date_to = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_quarter':
        $quarter = ceil(date('n') / 3);
        $date_from = date('Y-' . str_pad(($quarter - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
        break;
    case 'this_year':
        $date_from = date('Y-01-01');
        break;
    case 'custom':
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : $today;
        break;
}

// Get departments for filter
$departments = get_departments($conn);

// Get staff list based on permissions
$staff_list = get_accessible_staff_list($conn);

// ============================================
// FETCH REPORT DATA
// ============================================

// Build permission filters based on access level
$register_filter = '';
$ticket_filter = '';

switch ($access_level) {
    case 'admin':
    case 'manager':
        // Full access - no filter unless staff filter is applied
        if ($staff_filter) {
            $register_filter = " AND r.assigned_to = $staff_filter";
            $ticket_filter = " AND t.assigned_to = $staff_filter";
        }
        break;
    
    case 'department_head':
        // See all staff in their department
        $staff_ids = get_department_staff_ids($conn);
        if (!empty($staff_ids)) {
            $ids = implode(',', array_map('intval', $staff_ids));
            if ($staff_filter && in_array($staff_filter, $staff_ids)) {
                // Filter to specific staff within their department
                $register_filter = " AND r.assigned_to = $staff_filter";
                $ticket_filter = " AND t.assigned_to = $staff_filter";
            } else {
                // Show all department staff
                $register_filter = " AND r.assigned_to IN ($ids)";
                $ticket_filter = " AND t.assigned_to IN ($ids)";
            }
        } else {
            $register_filter = " AND r.assigned_to = $current_staff_id";
            $ticket_filter = " AND t.assigned_to = $current_staff_id";
        }
        break;
    
    case 'staff':
    default:
        // Staff only sees their own data
        $register_filter = " AND r.assigned_to = $current_staff_id";
        $ticket_filter = " AND t.assigned_to = $current_staff_id";
        break;
}

// --- VIRTUAL COURSES STATS ---
$virtual_total = 0;
$virtual_paid = 0;
$virtual_revenue = 0;
$virtual_new = 0;

$q = "SELECT COUNT(*) AS total FROM register r WHERE DATE(r.datee) BETWEEN '$date_from' AND '$date_to' $register_filter";
$res = mysqli_query($conn, $q);
if ($res && $row = mysqli_fetch_assoc($res)) $virtual_total = intval($row['total']);

$q = "SELECT COUNT(*) AS cnt FROM register r WHERE DATE(r.datee) = '$today' $register_filter";
$res = mysqli_query($conn, $q);
if ($res && $row = mysqli_fetch_assoc($res)) $virtual_new = intval($row['cnt']);

$q = "SELECT COUNT(DISTINCT r.id) AS paid, COALESCE(SUM(p.TransactionAmount), 0) AS revenue 
      FROM register r 
      INNER JOIN dpo_payment p ON r.entry_id = p.app_id 
      WHERE p.TransactionAmount > 0 AND DATE(r.datee) BETWEEN '$date_from' AND '$date_to' $register_filter";
$res = mysqli_query($conn, $q);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $virtual_paid = intval($row['paid']);
    $virtual_revenue = floatval($row['revenue']);
}

// --- INTERNATIONAL EVENTS STATS ---
$intl_total = 0;
$intl_paid = 0;
$intl_revenue = 0;
$intl_new = 0;

$q = "SELECT COUNT(*) AS total FROM ticket_congress t WHERE DATE(t.date_sent) BETWEEN '$date_from' AND '$date_to' $ticket_filter";
$res = mysqli_query($conn, $q);
if ($res && $row = mysqli_fetch_assoc($res)) $intl_total = intval($row['total']);

$q = "SELECT COUNT(*) AS cnt FROM ticket_congress t WHERE DATE(t.date_sent) = '$today' $ticket_filter";
$res = mysqli_query($conn, $q);
if ($res && $row = mysqli_fetch_assoc($res)) $intl_new = intval($row['cnt']);

$q = "SELECT COUNT(*) AS paid, COALESCE(SUM(t.amount), 0) AS revenue 
      FROM ticket_congress t 
      WHERE t.status = 2 AND DATE(t.date_sent) BETWEEN '$date_from' AND '$date_to' $ticket_filter";
$res = mysqli_query($conn, $q);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $intl_paid = intval($row['paid']);
    $intl_revenue = floatval($row['revenue']);
}

// --- COMBINED TOTALS ---
$total_enquiries = $virtual_total + $intl_total;
$total_paid = $virtual_paid + $intl_paid;
$total_revenue = $virtual_revenue + $intl_revenue;
$total_new_today = $virtual_new + $intl_new;
$conversion_rate = $total_enquiries > 0 ? round(($total_paid / $total_enquiries) * 100, 1) : 0;

// --- ENQUIRIES BY COURSE ---
$courses_data = [];
$q = "SELECT c.course AS name, COUNT(r.id) AS total,
      SUM(CASE WHEN p.TransactionAmount > 0 THEN 1 ELSE 0 END) AS paid,
      COALESCE(SUM(p.TransactionAmount), 0) AS revenue
      FROM register r
      LEFT JOIN course c ON (r.program = c.id OR r.program = c.course_id)
      LEFT JOIN dpo_payment p ON r.entry_id = p.app_id
      WHERE DATE(r.datee) BETWEEN '$date_from' AND '$date_to' $register_filter
      GROUP BY r.program, c.course
      ORDER BY total DESC
      LIMIT 10";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $courses_data[] = $row;
    }
}

// --- ENQUIRIES BY EVENT ---
$events_data = [];
$q = "SELECT e.event_title AS name, COUNT(t.id) AS total,
      SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) AS paid,
      SUM(CASE WHEN t.status = 2 THEN t.amount ELSE 0 END) AS revenue
      FROM ticket_congress t
      LEFT JOIN Event e ON t.event_id = e.event_id
      WHERE DATE(t.date_sent) BETWEEN '$date_from' AND '$date_to' $ticket_filter
      GROUP BY t.event_id, e.event_title
      ORDER BY total DESC
      LIMIT 10";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $events_data[] = $row;
    }
}

// --- STAFF PERFORMANCE ---
$staff_performance = [];
$q = "SELECT ru.fullname AS staff_name, 
      COUNT(r.id) AS virtual_total,
      SUM(CASE WHEN p.TransactionAmount > 0 THEN 1 ELSE 0 END) AS virtual_paid
      FROM register r
      LEFT JOIN registered_users ru ON r.assigned_to = ru.id
      LEFT JOIN dpo_payment p ON r.entry_id = p.app_id
      WHERE DATE(r.datee) BETWEEN '$date_from' AND '$date_to' AND r.assigned_to IS NOT NULL $register_filter
      GROUP BY r.assigned_to, ru.fullname";
$res = mysqli_query($conn, $q);
$staff_temp = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $name = $row['staff_name'] ?: 'Unassigned';
        $staff_temp[$name] = [
            'name' => $name,
            'virtual_total' => intval($row['virtual_total']),
            'virtual_paid' => intval($row['virtual_paid']),
            'intl_total' => 0,
            'intl_paid' => 0
        ];
    }
}

$q = "SELECT ru.fullname AS staff_name, 
      COUNT(t.id) AS intl_total,
      SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) AS intl_paid
      FROM ticket_congress t
      LEFT JOIN registered_users ru ON t.assigned_to = ru.id
      WHERE DATE(t.date_sent) BETWEEN '$date_from' AND '$date_to' AND t.assigned_to IS NOT NULL $ticket_filter
      GROUP BY t.assigned_to, ru.fullname";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $name = $row['staff_name'] ?: 'Unassigned';
        if (isset($staff_temp[$name])) {
            $staff_temp[$name]['intl_total'] = intval($row['intl_total']);
            $staff_temp[$name]['intl_paid'] = intval($row['intl_paid']);
        } else {
            $staff_temp[$name] = [
                'name' => $name,
                'virtual_total' => 0,
                'virtual_paid' => 0,
                'intl_total' => intval($row['intl_total']),
                'intl_paid' => intval($row['intl_paid'])
            ];
        }
    }
}

foreach ($staff_temp as $s) {
    $s['total'] = $s['virtual_total'] + $s['intl_total'];
    $s['paid'] = $s['virtual_paid'] + $s['intl_paid'];
    $s['conversion'] = $s['total'] > 0 ? round(($s['paid'] / $s['total']) * 100, 1) : 0;
    $staff_performance[] = $s;
}
usort($staff_performance, function($a, $b) { return $b['total'] - $a['total']; });

// --- DAILY TREND (Last 30 days or date range) ---
$daily_trend = [];
$trend_days = min(30, (strtotime($date_to) - strtotime($date_from)) / 86400 + 1);
$trend_start = date('Y-m-d', strtotime("-" . ($trend_days - 1) . " days", strtotime($date_to)));

$q = "SELECT DATE(datee) AS day, COUNT(*) AS count FROM register r 
      WHERE DATE(datee) BETWEEN '$trend_start' AND '$date_to' $register_filter
      GROUP BY DATE(datee)";
$res = mysqli_query($conn, $q);
$virtual_by_day = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $virtual_by_day[$row['day']] = intval($row['count']);
    }
}

$q = "SELECT DATE(date_sent) AS day, COUNT(*) AS count FROM ticket_congress t 
      WHERE DATE(date_sent) BETWEEN '$trend_start' AND '$date_to' $ticket_filter
      GROUP BY DATE(date_sent)";
$res = mysqli_query($conn, $q);
$intl_by_day = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $intl_by_day[$row['day']] = intval($row['count']);
    }
}

// Build trend array
for ($i = 0; $i < $trend_days; $i++) {
    $day = date('Y-m-d', strtotime("+$i days", strtotime($trend_start)));
    $daily_trend[] = [
        'date' => $day,
        'label' => date('M d', strtotime($day)),
        'virtual' => isset($virtual_by_day[$day]) ? $virtual_by_day[$day] : 0,
        'international' => isset($intl_by_day[$day]) ? $intl_by_day[$day] : 0
    ];
}

// --- SOURCE BREAKDOWN ---
$source_data = [];
$q = "SELECT 
      CASE r.source 
        WHEN 1 THEN 'Website' 
        WHEN 4 THEN 'WhatsApp' 
        WHEN 5 THEN 'Facebook' 
        WHEN 6 THEN 'Referral'
        ELSE 'Other' 
      END AS source_name,
      COUNT(*) AS total
      FROM register r
      WHERE DATE(r.datee) BETWEEN '$date_from' AND '$date_to' $register_filter
      GROUP BY r.source
      ORDER BY total DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $source_data[] = $row;
    }
}

// --- FOLLOWUP STATS ---
$followup_filter = get_followup_permission_sql($conn, 'f');
$followup_completed = 0;
$followup_pending = 0;
$followup_overdue = 0;

$q = "SELECT 
      SUM(CASE WHEN f.is_completed = 1 AND DATE(f.completed_at) BETWEEN '$date_from' AND '$date_to' THEN 1 ELSE 0 END) AS completed,
      SUM(CASE WHEN f.is_completed = 0 AND f.reminder_date >= CURDATE() THEN 1 ELSE 0 END) AS pending,
      SUM(CASE WHEN f.is_completed = 0 AND f.reminder_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
      FROM enquiry_followups f WHERE 1=1 $followup_filter";
$res = mysqli_query($conn, $q);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $followup_completed = intval($row['completed']);
    $followup_pending = intval($row['pending']);
    $followup_overdue = intval($row['overdue']);
}

// Format currency - convert USD to KES
function format_currency($amount_usd, $show_usd = false) {
    $amount_kes = $amount_usd * USD_TO_KES_RATE;
    if ($show_usd) {
        return 'KES ' . number_format($amount_kes, 2) . ' (USD ' . number_format($amount_usd, 2) . ')';
    }
    return 'KES ' . number_format($amount_kes, 2);
}

// Format currency in USD only
function format_usd($amount) {
    return 'USD ' . number_format($amount, 2);
}

// Date range label
$range_labels = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'this_week' => 'This Week',
    'last_week' => 'Last Week',
    'this_month' => 'This Month',
    'last_month' => 'Last Month',
    'this_quarter' => 'This Quarter',
    'this_year' => 'This Year',
    'custom' => date('M d', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to))
];
$current_range_label = $range_labels[$date_range] ?? 'Custom';
?>

<style>
.stat-card { border-radius: 12px; transition: all 0.3s; }
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
.chart-container { position: relative; height: 300px; }
.table-performance th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; }
.progress { height: 8px; border-radius: 4px; }
.filter-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.metric-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
.metric-value { font-size: 1.8rem; font-weight: 700; }
.trend-up { color: #10b981; }
.trend-down { color: #ef4444; }
.card-header-custom { background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); border-bottom: none; }
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Reports & Analytics</h4>
                    <p class="text-muted mb-0">
                        <?php echo $current_range_label; ?> 
                        <span class="text-secondary">| <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></span>
                        <?php if ($access_level == 'staff'): ?>
                        <span class="badge bg-info ms-2">My Reports</span>
                        <?php elseif ($access_level == 'department_head'): ?>
                        <span class="badge bg-primary ms-2">Department Reports</span>
                        <?php elseif ($access_level == 'admin' || $access_level == 'manager'): ?>
                        <span class="badge bg-success ms-2">Organization Reports</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="reports_export.php?type=excel&range=<?php echo $date_range; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&staff=<?php echo $staff_filter; ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                    </a>
                    <a href="reports_export.php?type=pdf&range=<?php echo $date_range; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&staff=<?php echo $staff_filter; ?>" class="btn btn-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                    </a>
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <a href="enquiry_dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Currency Note -->
            <div class="alert alert-info alert-dismissible fade show py-2 mb-4" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <small>All amounts are converted from USD to KES at rate of <strong>1 USD = <?php echo USD_TO_KES_RATE; ?> KES</strong></small>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>

            <!-- Filters -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Date Range</label>
                            <select name="range" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                                <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="yesterday" <?php echo $date_range == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="this_week" <?php echo $date_range == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="last_week" <?php echo $date_range == 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                                <option value="this_month" <?php echo $date_range == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="last_month" <?php echo $date_range == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="this_quarter" <?php echo $date_range == 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                <option value="this_year" <?php echo $date_range == 'this_year' ? 'selected' : ''; ?>>This Year</option>
                                <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="customFrom" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>">
                            <label class="form-label small fw-semibold">From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2" id="customTo" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>">
                            <label class="form-label small fw-semibold">To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                        </div>
                        <?php if ($access_level == 'admin' || $access_level == 'manager' || $access_level == 'department_head'): ?>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Staff</label>
                            <select name="staff" class="form-select form-select-sm">
                                <option value=""><?php echo $access_level == 'department_head' ? 'All Dept Staff' : 'All Staff'; ?></option>
                                <?php foreach ($staff_list as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $staff_filter == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['fullname']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-funnel me-1"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="metric-label text-muted mb-1">Total Enquiries</p>
                                    <h2 class="metric-value mb-1"><?php echo number_format($total_enquiries); ?></h2>
                                    <small class="text-success"><i class="bi bi-plus-circle me-1"></i><?php echo $total_new_today; ?> today</small>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-people fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="metric-label text-muted mb-1">Total Revenue</p>
                                    <h2 class="metric-value mb-1"><?php echo format_currency($total_revenue); ?></h2>
                                    <small class="text-muted"><?php echo number_format($total_paid); ?> paid</small>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-currency-dollar fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="metric-label text-muted mb-1">Conversion Rate</p>
                                    <h2 class="metric-value mb-1"><?php echo $conversion_rate; ?>%</h2>
                                    <small class="text-muted"><?php echo $total_paid; ?> of <?php echo $total_enquiries; ?></small>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-arrow-repeat fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="metric-label text-muted mb-1">Follow-ups</p>
                                    <h2 class="metric-value mb-1"><?php echo $followup_pending + $followup_overdue; ?></h2>
                                    <small class="<?php echo $followup_overdue > 0 ? 'text-danger' : 'text-muted'; ?>">
                                        <?php echo $followup_overdue; ?> overdue
                                    </small>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-calendar-check fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Virtual vs International Breakdown -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header card-header-custom py-3">
                            <h6 class="mb-0"><i class="bi bi-laptop me-2 text-primary"></i>Virtual Courses</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h3 class="text-primary mb-0"><?php echo number_format($virtual_total); ?></h3>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-success mb-0"><?php echo number_format($virtual_paid); ?></h3>
                                    <small class="text-muted">Paid</small>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-info mb-0"><?php echo format_currency($virtual_revenue); ?></h3>
                                    <small class="text-muted">Revenue</small>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <?php $vp = $virtual_total > 0 ? ($virtual_paid / $virtual_total) * 100 : 0; ?>
                                <div class="progress-bar bg-success" style="width: <?php echo $vp; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo round($vp, 1); ?>% conversion rate</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header card-header-custom py-3">
                            <h6 class="mb-0"><i class="bi bi-globe me-2 text-danger"></i>International Events</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h3 class="text-primary mb-0"><?php echo number_format($intl_total); ?></h3>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-success mb-0"><?php echo number_format($intl_paid); ?></h3>
                                    <small class="text-muted">Paid</small>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-info mb-0"><?php echo format_currency($intl_revenue); ?></h3>
                                    <small class="text-muted">Revenue</small>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <?php $ip = $intl_total > 0 ? ($intl_paid / $intl_total) * 100 : 0; ?>
                                <div class="progress-bar bg-danger" style="width: <?php echo $ip; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo round($ip, 1); ?>% conversion rate</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- Daily Trend Chart -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header card-header-custom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Enquiry Trend</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Source Breakdown -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header card-header-custom py-3">
                            <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Lead Sources</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="sourceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row g-4 mb-4">
                <!-- Top Courses -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header card-header-custom py-3">
                            <h6 class="mb-0"><i class="bi bi-book me-2"></i>Top Courses</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-performance">
                                        <tr>
                                            <th>Course</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center">Paid</th>
                                            <th class="text-end">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($courses_data)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No data available</td></tr>
                                        <?php else: ?>
                                        <?php foreach ($courses_data as $c): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($c['name'] ?: 'Unknown'); ?></td>
                                            <td class="text-center"><?php echo number_format($c['total']); ?></td>
                                            <td class="text-center"><span class="badge bg-success"><?php echo number_format($c['paid']); ?></span></td>
                                            <td class="text-end"><?php echo format_currency($c['revenue']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Events -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header card-header-custom py-3">
                            <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Top Events</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-performance">
                                        <tr>
                                            <th>Event</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center">Paid</th>
                                            <th class="text-end">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($events_data)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No data available</td></tr>
                                        <?php else: ?>
                                        <?php foreach ($events_data as $e): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($e['name'] ?: 'Unknown'); ?></td>
                                            <td class="text-center"><?php echo number_format($e['total']); ?></td>
                                            <td class="text-center"><span class="badge bg-success"><?php echo number_format($e['paid']); ?></span></td>
                                            <td class="text-end"><?php echo format_currency($e['revenue']); ?></td>
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

            <!-- Staff Performance -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header card-header-custom py-3">
                            <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Staff Performance</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-performance">
                                        <tr>
                                            <th>Staff Member</th>
                                            <th class="text-center">Virtual</th>
                                            <th class="text-center">International</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center">Converted</th>
                                            <th class="text-center">Conversion Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staff_performance)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No data available</td></tr>
                                        <?php else: ?>
                                        <?php foreach ($staff_performance as $sp): ?>
                                        <tr>
                                            <td>
                                                <i class="bi bi-person-circle me-2 text-muted"></i>
                                                <?php echo htmlspecialchars($sp['name']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $sp['virtual_total']; ?>
                                                <small class="text-success">(<?php echo $sp['virtual_paid']; ?> paid)</small>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $sp['intl_total']; ?>
                                                <small class="text-success">(<?php echo $sp['intl_paid']; ?> paid)</small>
                                            </td>
                                            <td class="text-center fw-semibold"><?php echo $sp['total']; ?></td>
                                            <td class="text-center"><span class="badge bg-success"><?php echo $sp['paid']; ?></span></td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <div class="progress flex-grow-1" style="width: 60px; height: 6px;">
                                                        <div class="progress-bar <?php echo $sp['conversion'] >= 50 ? 'bg-success' : ($sp['conversion'] >= 25 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                             style="width: <?php echo $sp['conversion']; ?>%"></div>
                                                    </div>
                                                    <span class="small fw-semibold"><?php echo $sp['conversion']; ?>%</span>
                                                </div>
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

        </div>
    </div>
</section>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleCustomDates(value) {
    document.getElementById('customFrom').style.display = value === 'custom' ? 'block' : 'none';
    document.getElementById('customTo').style.display = value === 'custom' ? 'block' : 'none';
}

// Trend Chart
const trendData = <?php echo json_encode($daily_trend); ?>;
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendData.map(d => d.label),
        datasets: [
            {
                label: 'Virtual',
                data: trendData.map(d => d.virtual),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'International',
                data: trendData.map(d => d.international),
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Source Chart
const sourceData = <?php echo json_encode($source_data); ?>;
const sourceCtx = document.getElementById('sourceChart').getContext('2d');
new Chart(sourceCtx, {
    type: 'doughnut',
    data: {
        labels: sourceData.map(s => s.source_name),
        datasets: [{
            data: sourceData.map(s => s.total),
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d', '#0dcaf0']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php require_once 'footer.php'; ?>