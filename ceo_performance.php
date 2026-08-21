<?php
// bde_dashboard.php  (admin/bde_dashboard.php)
// Private BDE performance dashboard — phase 1 (illustrative / dummy data).
//
// Uses the SAME chrome as the enquiry dashboard: the root header.php (its left
// nav), top_nav.php and footer.php. The dashboard's own design system (ported
// from the v11 prototype, recoloured to a blue theme) is scoped under a single
// `.bde-app` container so it neither leaks into nor is overridden by the admin
// Bootstrap styles. The theme toggle flips a class on that container only.
session_start();
require_once 'header.php';   // enquiry/admin left nav + chrome + $conn
require_once 'includes/bde_metrics.php';
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }

// ---- Organization sales roll-up: the whole-org view across the SBUs (real, with reps per SBU). ----
$ceo_from = date('Y-m-01');
$ceo_to   = date('Y-m-d');
$ceo = bdm_rollup($conn, $ceo_from, $ceo_to);
$ceo_series = bde_daily_series($ceo['daily'] ?? [], $ceo_to);   // real month-to-date daily cleared KES

// ---- Learner journey: real counts from the Moodle LMS (separate DB vantage_elearning, mdl_ tables). ----
$lms = null;
$mconn = @mysqli_connect('localhost', 'vantage_elearning', 'Y)A)ilAZ!VYLPds1', 'vantage_elearning');
if ($mconn) {
    $mq = function ($sql) use ($mconn) { $r = @mysqli_query($mconn, $sql); if ($r && ($row = mysqli_fetch_row($r))) { return (int) $row[0]; } return null; };
    $enrolled  = $mq("SELECT COUNT(DISTINCT ue.userid) FROM mdl_user_enrolments ue JOIN mdl_user u ON u.id = ue.userid WHERE u.deleted = 0");
    $active    = $mq("SELECT COUNT(DISTINCT ue.userid) FROM mdl_user_enrolments ue JOIN mdl_user u ON u.id = ue.userid WHERE u.deleted = 0 AND u.lastaccess > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))");
    $completed = $mq("SELECT COUNT(DISTINCT userid) FROM mdl_course_completions WHERE timecompleted IS NOT NULL AND timecompleted > 0");
    $certified = $mq("SELECT COUNT(DISTINCT userid) FROM mdl_customcert_issues");
    // Completion signal: course-completion tracking is often off (mdl_course_completions empty), while
    // certificates ARE issued — so use whichever is populated as the single "Completed" stage. Keep the
    // funnel all-time & monotonic (Enrolled → Completed); Active (30d) is a separate engagement stat.
    // This-month headline (matches the dashboard's month filter).
    $fromU = "UNIX_TIMESTAMP('" . mysqli_real_escape_string($mconn, $ceo_from) . " 00:00:00')";
    $toU   = "UNIX_TIMESTAMP('" . mysqli_real_escape_string($mconn, $ceo_to) . " 23:59:59')";
    $enrMonth  = $mq("SELECT COUNT(DISTINCT ue.userid) FROM mdl_user_enrolments ue JOIN mdl_user u ON u.id = ue.userid WHERE u.deleted = 0 AND ue.timecreated BETWEEN $fromU AND $toU");
    $certMonth = $mq("SELECT COUNT(DISTINCT userid) FROM mdl_customcert_issues WHERE timecreated BETWEEN $fromU AND $toU");
    // Top courses by enrolment THIS MONTH — course names from the LMS, scoped to the month filter.
    $courses = [];
    $cq = @mysqli_query($mconn, "SELECT c.fullname nm, COUNT(DISTINCT ue.userid) n
        FROM mdl_user_enrolments ue JOIN mdl_enrol e ON e.id = ue.enrolid JOIN mdl_course c ON c.id = e.courseid
        JOIN mdl_user u ON u.id = ue.userid
        WHERE c.id > 1 AND u.deleted = 0 AND ue.timecreated BETWEEN $fromU AND $toU
        GROUP BY c.id, c.fullname ORDER BY n DESC LIMIT 5");
    while ($cq && ($cr = mysqli_fetch_assoc($cq))) { $courses[] = [(string) $cr['nm'], (int) $cr['n']]; }
    if ($enrMonth !== null || $certMonth !== null || !empty($courses)) {
        $lms = ['enrMonth' => $enrMonth, 'certMonth' => $certMonth, 'active' => $active, 'enrolledAll' => $enrolled,
            'monthLabel' => date('F Y', strtotime($ceo_to)), 'courses' => $courses];
    }
    @mysqli_close($mconn);
}

// ---- HR tab: render the real HR content natively (no iframe). Defensive against PHP 8.1 mysqli. ----
$hr = ['stats' => ['total'=>0,'active'=>0,'pending'=>0,'under_review'=>0,'approved'=>0,'suspended'=>0,'terminated'=>0,'rejected'=>0], 'by_dept' => [], 'staff' => [], 'payroll_pending' => 0, 'att_present' => 0, 'att_punches' => 0, 'payroll' => null, 'payslips' => [], 'clockins' => []];
try {
    $r = @mysqli_query($conn, "SELECT onboarding_status, COUNT(*) c FROM `staff` GROUP BY onboarding_status");
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $hr['stats'][(string) $row['onboarding_status']] = (int) $row['c'];
        $hr['stats']['total'] += (int) $row['c'];
    }
    $r = @mysqli_query($conn, "SELECT d.department_name, COUNT(s.id) c FROM `departments` d LEFT JOIN `staff` s ON d.id = s.department_id AND s.onboarding_status = 'active' GROUP BY d.id, d.department_name ORDER BY c DESC LIMIT 12");
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $hr['by_dept'][] = ['name' => (string) $row['department_name'], 'count' => (int) $row['c']];
    }
    $r = @mysqli_query($conn, "SELECT s.staff_id, s.full_name, s.email, s.phone, s.job_title, s.onboarding_status, s.created_at, d.department_name FROM `staff` s LEFT JOIN `departments` d ON s.department_id = d.id WHERE s.onboarding_status = 'active' ORDER BY s.created_at DESC LIMIT 200");
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $hr['staff'][] = [
            'staff_id' => (string) $row['staff_id'],
            'name'     => (string) $row['full_name'],
            'email'    => (string) $row['email'],
            'phone'    => (string) $row['phone'],
            'title'    => (string) ($row['job_title'] ?? ''),
            'dept'     => (string) ($row['department_name'] ?? ''),
            'status'   => (string) $row['onboarding_status'],
            'created'  => !empty($row['created_at']) ? date('M j, Y', strtotime($row['created_at'])) : '',
        ];
    }
    $r = @mysqli_query($conn, "SELECT COUNT(*) c FROM `payroll_periods` WHERE status = 'pending_approval'");
    if ($r && ($row = mysqli_fetch_assoc($r))) { $hr['payroll_pending'] = (int) $row['c']; }
    $r = @mysqli_query($conn, "SELECT COUNT(DISTINCT staff_id) present, COUNT(*) punches FROM `attendance_logs` WHERE DATE(punch_time) = CURDATE()");
    if ($r && ($row = mysqli_fetch_assoc($r))) { $hr['att_present'] = (int) $row['present']; $hr['att_punches'] = (int) $row['punches']; }
    $r = @mysqli_query($conn, "SELECT id, period_month, period_year, total_gross, total_net, total_employees, status FROM `payroll_periods` ORDER BY period_year DESC, period_month DESC LIMIT 1");
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $hr['payroll'] = ['id'=>(int)$row['id'],'month'=>(int)$row['period_month'],'year'=>(int)$row['period_year'],'gross'=>(float)$row['total_gross'],'net'=>(float)$row['total_net'],'employees'=>(int)$row['total_employees'],'status'=>(string)$row['status']];
        $pid = (int) $row['id'];
        $pr = @mysqli_query($conn, "SELECT staff_name, department_name, gross_pay, net_pay FROM `payroll_records` WHERE period_id = $pid ORDER BY staff_name");
        while ($pr && ($prow = mysqli_fetch_assoc($pr))) {
            $hr['payslips'][] = ['name'=>(string)$prow['staff_name'],'dept'=>(string)($prow['department_name'] ?? ''),'gross'=>(float)$prow['gross_pay'],'net'=>(float)$prow['net_pay']];
        }
    }
    $r = @mysqli_query($conn, "SELECT a.staff_id, s.full_name, a.punch_time FROM `attendance_logs` a LEFT JOIN `staff` s ON s.staff_id = a.staff_id WHERE DATE(a.punch_time) = CURDATE() ORDER BY a.punch_time ASC");
    $byp = [];
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        if (empty($row['punch_time'])) { continue; }
        $key = !empty($row['staff_id']) ? 'S' . $row['staff_id'] : 'N' . (string) ($row['full_name'] ?? '');
        $t = strtotime($row['punch_time']);
        $name = trim((string) ($row['full_name'] ?? '')) !== '' ? (string) $row['full_name'] : 'Unmapped device user';
        if (!isset($byp[$key])) { $byp[$key] = ['name'=>$name, 'in'=>$t, 'out'=>$t]; }
        else { if ($t < $byp[$key]['in']) { $byp[$key]['in'] = $t; } if ($t > $byp[$key]['out']) { $byp[$key]['out'] = $t; } }
    }
    foreach ($byp as $pp) {
        $late = ((int) date('Hi', $pp['in']) > 820);   // clock-in after 08:20 = late
        $hr['clockins'][] = ['name'=>$pp['name'], 'in'=>date('g:i A', $pp['in']), 'out'=>($pp['out'] > $pp['in']) ? date('g:i A', $pp['out']) : '—', 'late'=>$late];
    }
} catch (\Throwable $e) {
    error_log('CEO HR fetch: ' . $e->getMessage());
}

// ---- Finance tab: full native analytics (revenue, collection, expenses, payroll, statutory, commissions). All money stored USD; JS toggles USD/KES. ----
$q = function ($sql) use ($conn) { try { return @mysqli_query($conn, $sql); } catch (\Throwable $e) { return false; } };
$finance = [
    'rate' => 129.0,
    'years' => [],
    'rev' => ['months' => [], 'courses' => [], 'events' => []],
    'expenses' => ['total' => 0, 'count' => 0, 'by_cat' => []],
    'fees' => ['expected' => 0, 'collected' => 0, 'outstanding' => 0, 'clients' => 0, 'paid' => 0, 'partial' => 0],
    'payroll' => null,
    'statutory' => ['paye' => 0, 'nssf_emp' => 0, 'nssf_er' => 0, 'shif' => 0, 'housing' => 0],
    'disburse' => ['paid_n' => 0, 'paid_amt' => 0, 'pend_n' => 0, 'pend_amt' => 0, 'total_emp' => 0, 'total_net' => 0],
    'remit' => ['overdue' => 0, 'pending' => 0, 'paid' => 0, 'overdue_amt' => 0, 'pending_amt' => 0],
    'commission' => ['eligible' => 0, 'pending' => 0, 'approved' => 0, 'paid' => 0],
];
try {
    $res = $q("SELECT setting_value FROM `commission_settings` WHERE setting_key='commission_conversion_rate' LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res)) && (float) $row['setting_value'] > 0) { $finance['rate'] = (float) $row['setting_value']; }
    $rate = $finance['rate'] > 0 ? $finance['rate'] : 129.0;

    // ===== Revenue: monthly series (virtual/international/custom) — collected, expected, counts =====
    $months = [];
    $seed = function ($ym, $y, $m) use (&$months) {
        if (!isset($months[$ym])) { $months[$ym] = ['ym' => $ym, 'y' => (int) $y, 'm' => (int) $m, 'v' => 0, 'i' => 0, 'c' => 0, 'vexp' => 0, 'iexp' => 0, 'vn' => 0, 'in' => 0]; }
    };
    $res = $q("SELECT YEAR(d.datee) y, MONTH(d.datee) m, SUM(d.TransactionAmount) rev, COUNT(*) n, SUM(c.price_usd) exp
               FROM `dpo_payment` d JOIN `course` c ON d.purpose=c.course_id
               WHERE d.status=2 AND d.TransactionAmount>0 GROUP BY YEAR(d.datee), MONTH(d.datee)");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!$row['y']) { continue; }
        $ym = sprintf('%04d-%02d', $row['y'], $row['m']); $seed($ym, $row['y'], $row['m']);
        $months[$ym]['v'] = (float) $row['rev']; $months[$ym]['vn'] = (int) $row['n']; $months[$ym]['vexp'] = (float) $row['exp'];
    }
    $res = $q("SELECT YEAR(t.date_sent) y, MONTH(t.date_sent) m, SUM(t.amount) rev, COUNT(*) n, SUM(e.early_amount) exp
               FROM `ticket_congress` t LEFT JOIN `Event` e ON t.event_id=e.event_id
               WHERE t.status=2 AND t.amount>0 AND NOT EXISTS (SELECT 1 FROM `dpo_payment` dp WHERE dp.token=t.confirmation AND dp.status=2)
               GROUP BY YEAR(t.date_sent), MONTH(t.date_sent)");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!$row['y']) { continue; }
        $ym = sprintf('%04d-%02d', $row['y'], $row['m']); $seed($ym, $row['y'], $row['m']);
        $months[$ym]['i'] = (float) $row['rev']; $months[$ym]['in'] = (int) $row['n']; $months[$ym]['iexp'] = (float) $row['exp'];
    }
    $res = $q("SELECT YEAR(income_date) y, MONTH(income_date) m, SUM(amount) rev FROM `custom_income` WHERE amount>0 GROUP BY YEAR(income_date), MONTH(income_date)");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!$row['y']) { continue; }
        $ym = sprintf('%04d-%02d', $row['y'], $row['m']); $seed($ym, $row['y'], $row['m']);
        $months[$ym]['c'] = (float) $row['rev'];
    }
    ksort($months);
    $finance['rev']['months'] = array_values($months);
    $ys = [];
    foreach ($months as $mm) { $ys[$mm['y']] = true; }
    krsort($ys);
    $finance['years'] = array_map('strval', array_keys($ys));

    // Top courses / events per year (JS aggregates to selected year)
    $res = $q("SELECT YEAR(d.datee) y, c.course name, SUM(d.TransactionAmount) rev, COUNT(*) n
               FROM `dpo_payment` d JOIN `course` c ON d.purpose=c.course_id
               WHERE d.status=2 AND d.TransactionAmount>0 GROUP BY YEAR(d.datee), c.course");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!$row['y']) { continue; }
        $finance['rev']['courses'][] = ['y' => (int) $row['y'], 'name' => (string) (($row['name'] ?? '') !== '' ? $row['name'] : 'Unknown course'), 'rev' => (float) $row['rev'], 'n' => (int) $row['n']];
    }
    $res = $q("SELECT YEAR(t.date_sent) y, COALESCE(e.location,'Unknown Event') loc, SUM(t.amount) rev, COUNT(*) n
               FROM `ticket_congress` t LEFT JOIN `Event` e ON t.event_id=e.event_id
               WHERE t.status=2 AND t.amount>0 AND NOT EXISTS (SELECT 1 FROM `dpo_payment` dp WHERE dp.token=t.confirmation AND dp.status=2)
               GROUP BY YEAR(t.date_sent), e.location");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!$row['y']) { continue; }
        $finance['rev']['events'][] = ['y' => (int) $row['y'], 'loc' => (string) (($row['loc'] ?? '') !== '' ? $row['loc'] : 'Unknown Event'), 'rev' => (float) $row['rev'], 'n' => (int) $row['n']];
    }

    // ===== Expenses by category (USD) =====
    $res = $q("SELECT category, SUM(amount) t, COUNT(*) c FROM `expenses` GROUP BY category ORDER BY t DESC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $finance['expenses']['by_cat'][] = ['name' => (string) (($row['category'] ?? '') !== '' ? $row['category'] : 'Uncategorised'), 'amount' => (float) $row['t'], 'count' => (int) $row['c']];
        $finance['expenses']['total'] += (float) $row['t'];
        $finance['expenses']['count'] += (int) $row['c'];
    }

    // ===== Fee collection (virtual + international), USD =====
    $res = $q("SELECT COUNT(*) clients, COALESCE(SUM(fee),0) expected, COALESCE(SUM(paid),0) collected,
        SUM(CASE WHEN paid>=fee THEN 1 ELSE 0 END) paid_full, SUM(CASE WHEN paid>0 AND paid<fee THEN 1 ELSE 0 END) partial
      FROM (SELECT r.entry_id, c.price_usd fee, SUM(d.TransactionAmount) paid
            FROM `register` r JOIN `intake` i ON r.intake_id=i.intake_id JOIN `course` c ON i.course_id=c.course_id
            JOIN `dpo_payment` d ON d.app_id=r.entry_id AND d.status=2 GROUP BY r.entry_id, c.price_usd) v");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $finance['fees']['clients'] += (int) $row['clients']; $finance['fees']['expected'] += (float) $row['expected'];
        $finance['fees']['collected'] += (float) $row['collected']; $finance['fees']['paid'] += (int) $row['paid_full']; $finance['fees']['partial'] += (int) $row['partial'];
    }
    $res = $q("SELECT COUNT(*) clients, COALESCE(SUM(e.early_amount),0) expected, COALESCE(SUM(t.amount),0) collected,
        SUM(CASE WHEN t.amount>=e.early_amount THEN 1 ELSE 0 END) paid_full, SUM(CASE WHEN t.amount>0 AND t.amount<e.early_amount THEN 1 ELSE 0 END) partial
      FROM `ticket_congress` t JOIN `Event` e ON t.event_id=e.event_id WHERE t.status=2 AND t.amount>0");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $finance['fees']['clients'] += (int) $row['clients']; $finance['fees']['expected'] += (float) $row['expected'];
        $finance['fees']['collected'] += (float) $row['collected']; $finance['fees']['paid'] += (int) $row['paid_full']; $finance['fees']['partial'] += (int) $row['partial'];
    }
    $finance['fees']['outstanding'] = max(0, $finance['fees']['expected'] - $finance['fees']['collected']);

    // ===== Payroll latest period (KES → USD) + statutory split + disbursement =====
    if (!empty($hr['payroll'])) {
        $pp = $hr['payroll']; $pid = (int) $pp['id'];
        $finance['payroll'] = ['gross' => (float) $pp['gross'] / $rate, 'net' => (float) $pp['net'] / $rate, 'employees' => (int) $pp['employees'], 'month' => (int) $pp['month'], 'year' => (int) $pp['year'], 'status' => (string) $pp['status']];
        $res = $q("SELECT COALESCE(SUM(paye),0) paye, COALESCE(SUM(nssf_employee),0) nssf_emp, COALESCE(SUM(nssf_employer),0) nssf_er, COALESCE(SUM(shif_amount),0) shif, COALESCE(SUM(housing_levy_employee),0)+COALESCE(SUM(housing_levy_employer),0) housing FROM `payroll_records` WHERE period_id=$pid");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $finance['statutory'] = ['paye' => (float) $row['paye'] / $rate, 'nssf_emp' => (float) $row['nssf_emp'] / $rate, 'nssf_er' => (float) $row['nssf_er'] / $rate, 'shif' => (float) $row['shif'] / $rate, 'housing' => (float) $row['housing'] / $rate];
        }
        $res = $q("SELECT SUM(CASE WHEN pp.status='completed' THEN 1 ELSE 0 END) paid_n,
            SUM(CASE WHEN pp.status='completed' THEN pr.net_pay ELSE 0 END) paid_amt,
            SUM(CASE WHEN pp.status IS NULL OR pp.status!='completed' THEN 1 ELSE 0 END) pend_n,
            SUM(CASE WHEN pp.status IS NULL OR pp.status!='completed' THEN pr.net_pay ELSE 0 END) pend_amt,
            COUNT(*) total_emp, COALESCE(SUM(pr.net_pay),0) total_net
          FROM `payroll_records` pr LEFT JOIN `payroll_payments` pp ON pr.id=pp.payroll_record_id WHERE pr.period_id=$pid");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $finance['disburse'] = ['paid_n' => (int) $row['paid_n'], 'paid_amt' => (float) $row['paid_amt'] / $rate, 'pend_n' => (int) $row['pend_n'], 'pend_amt' => (float) $row['pend_amt'] / $rate, 'total_emp' => (int) $row['total_emp'], 'total_net' => (float) $row['total_net'] / $rate];
        }
    }

    // ===== Statutory remittances aging (KES → USD) =====
    $res = $q("SELECT SUM(CASE WHEN status!='paid' AND due_date>=CURDATE() THEN 1 ELSE 0 END) pending,
        SUM(CASE WHEN status!='paid' AND due_date<CURDATE() THEN 1 ELSE 0 END) overdue,
        SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) paid,
        SUM(CASE WHEN status!='paid' AND due_date>=CURDATE() THEN total_amount ELSE 0 END) pending_amt,
        SUM(CASE WHEN status!='paid' AND due_date<CURDATE() THEN total_amount ELSE 0 END) overdue_amt FROM `payroll_remittances`");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $finance['remit'] = ['overdue' => (int) $row['overdue'], 'pending' => (int) $row['pending'], 'paid' => (int) $row['paid'], 'overdue_amt' => (float) $row['overdue_amt'] / $rate, 'pending_amt' => (float) $row['pending_amt'] / $rate];
    }

    // ===== Commissions (USD) =====
    $res = $q("SELECT SUM(commission_amount) eligible,
        SUM(CASE WHEN status IN ('pending','pending_approval') THEN commission_amount ELSE 0 END) pending,
        SUM(CASE WHEN status='approved' THEN commission_amount ELSE 0 END) approved,
        SUM(CASE WHEN status='paid' THEN commission_amount ELSE 0 END) paid FROM `commission_records` WHERE is_eligible=1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $finance['commission'] = ['eligible' => (float) $row['eligible'], 'pending' => (float) $row['pending'], 'approved' => (float) $row['approved'], 'paid' => (float) $row['paid']];
    }
} catch (\Throwable $e) { error_log('CEO Finance fetch: ' . $e->getMessage()); }

// ---- Admin tab: native analytics (service requests + intake/event assignments) ----
$admin = ['req' => ['pending' => 0, 'progress' => 0, 'completed' => 0, 'rejected' => 0, 'pending_amt' => 0, 'list' => []], 'intakes' => [], 'events' => []];
try {
    $res = $q("SELECT SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) pending,
        SUM(CASE WHEN status='In Progress' THEN 1 ELSE 0 END) progress,
        SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) completed,
        SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) rejected,
        SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END) pending_amt FROM `service_requests`");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $admin['req']['pending'] = (int) $row['pending']; $admin['req']['progress'] = (int) $row['progress'];
        $admin['req']['completed'] = (int) $row['completed']; $admin['req']['rejected'] = (int) $row['rejected']; $admin['req']['pending_amt'] = (float) $row['pending_amt'];
    }
    $res = $q("SELECT request_id, request_title, request_type, status, priority, amount, staff_name, date_submitted FROM `service_requests` ORDER BY CASE status WHEN 'Pending' THEN 1 WHEN 'In Progress' THEN 2 WHEN 'Completed' THEN 3 ELSE 4 END, date_submitted DESC LIMIT 60");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $admin['req']['list'][] = ['id' => (int) $row['request_id'], 'title' => (string) $row['request_title'], 'type' => (string) ($row['request_type'] ?? ''), 'status' => (string) $row['status'], 'priority' => (string) ($row['priority'] ?? ''), 'amount' => (float) $row['amount'], 'staff' => (string) ($row['staff_name'] ?? ''), 'date' => !empty($row['date_submitted']) ? date('M j, Y', strtotime($row['date_submitted'])) : ''];
    }
    $res = $q("SELECT i.id, i.description, i.start_date, i.minimum_clients, i.commission_rate, i.assigned_to, ru.fullname assignee, c.course,
        (SELECT COUNT(*) FROM `register` r WHERE r.intake_id=i.intake_id) registered,
        (SELECT COUNT(DISTINCT r2.entry_id) FROM `register` r2 JOIN `dpo_payment` d ON d.app_id=r2.entry_id AND d.status=2 WHERE r2.intake_id=i.intake_id) paying
      FROM `intake` i LEFT JOIN `course` c ON i.course_id=c.course_id LEFT JOIN `registered_users` ru ON i.assigned_to=ru.id
      WHERE i.status=1 ORDER BY i.start_date DESC LIMIT 40");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $configured = ((int) $row['minimum_clients'] > 0 && (float) $row['commission_rate'] > 0);
        $admin['intakes'][] = ['name' => (string) (($row['description'] ?? '') !== '' ? $row['description'] : ($row['course'] ?? 'Intake')), 'assignee' => (string) ($row['assignee'] ?? ''), 'registered' => (int) $row['registered'], 'paying' => (int) $row['paying'], 'date' => !empty($row['start_date']) ? date('M j, Y', strtotime((string) $row['start_date'])) : '', 'ts' => !empty($row['start_date']) ? (int) strtotime((string) $row['start_date']) : 0, 'state' => $configured ? 'ready' : (!empty($row['assigned_to']) ? 'config' : 'unassigned')];
    }
    $res = $q("SELECT e.event_id, e.event_title, e.location, e.start_on, e.minimum_clients, e.commission_rate, e.assigned_to, ru.fullname assignee,
        (SELECT COUNT(*) FROM `ticket_congress` t WHERE t.event_id=e.event_id) registered,
        (SELECT COUNT(*) FROM `ticket_congress` t WHERE t.event_id=e.event_id AND t.status=2 AND t.amount>0) paying
      FROM `Event` e LEFT JOIN `registered_users` ru ON e.assigned_to=ru.id WHERE e.status=1 ORDER BY e.start_on DESC LIMIT 40");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $configured = ((int) $row['minimum_clients'] > 0 && (float) $row['commission_rate'] > 0);
        $admin['events'][] = ['name' => (string) (($row['event_title'] ?? '') !== '' ? $row['event_title'] : 'Event'), 'location' => (string) ($row['location'] ?? ''), 'assignee' => (string) ($row['assignee'] ?? ''), 'registered' => (int) $row['registered'], 'paying' => (int) $row['paying'], 'date' => !empty($row['start_on']) ? date('M j, Y', strtotime((string) $row['start_on'])) : '', 'ts' => !empty($row['start_on']) ? (int) strtotime((string) $row['start_on']) : 0, 'state' => $configured ? 'ready' : (!empty($row['assigned_to']) ? 'config' : 'unassigned')];
    }
} catch (\Throwable $e) { error_log('CEO Admin fetch: ' . $e->getMessage()); }

// ---- Reports tab: monthly trends (virtual + international) + per-location breakdown. Money stored USD. ----
$reports = ['virtual' => ['months' => []], 'international' => ['months' => [], 'loc' => []], 'corporate' => ['months' => []]];
try {
    $seed = [];
    for ($i = 5; $i >= 0; $i--) { $t = strtotime(date('Y-m-01') . " -$i month"); $seed[date('Y-m', $t)] = ['label' => date('M', $t), 'enq' => 0, 'cli' => 0, 'collected' => 0, 'due' => 0]; }
    $since = date('Y-m-01', strtotime(date('Y-m-01') . ' -5 month'));

    // ---- VIRTUAL months ----
    $vm = $seed;
    $res = $q("SELECT DATE_FORMAT(datee,'%Y-%m') ym, COUNT(*) enq, SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) cli FROM `register` WHERE datee>='$since' GROUP BY DATE_FORMAT(datee,'%Y-%m')");
    while ($res && ($row = mysqli_fetch_assoc($res))) { if (isset($vm[$row['ym']])) { $vm[$row['ym']]['enq'] = (int) $row['enq']; $vm[$row['ym']]['cli'] = (int) $row['cli']; } }
    $res = $q("SELECT DATE_FORMAT(dp.datee,'%Y-%m') ym, SUM(CASE WHEN dp.status=2 THEN dp.TransactionAmount ELSE 0 END) collected, SUM(c.price_usd) due FROM `dpo_payment` dp JOIN `course` c ON dp.purpose=c.course_id WHERE dp.datee>='$since' GROUP BY DATE_FORMAT(dp.datee,'%Y-%m')");
    while ($res && ($row = mysqli_fetch_assoc($res))) { if (isset($vm[$row['ym']])) { $vm[$row['ym']]['collected'] = (float) $row['collected']; $vm[$row['ym']]['due'] = (float) $row['due']; } }
    $reports['virtual']['months'] = array_values($vm);

    // ---- INTERNATIONAL months ----
    $im = $seed;
    $res = $q("SELECT DATE_FORMAT(ulf.date_applied,'%Y-%m') ym, COUNT(*) enq FROM `user_lead_forms` ulf WHERE ulf.date_applied>='$since' GROUP BY DATE_FORMAT(ulf.date_applied,'%Y-%m')");
    while ($res && ($row = mysqli_fetch_assoc($res))) { if (isset($im[$row['ym']])) { $im[$row['ym']]['enq'] = (int) $row['enq']; } }
    $res = $q("SELECT DATE_FORMAT(tc.date_sent,'%Y-%m') ym, COUNT(*) cli FROM `ticket_congress` tc WHERE tc.status=2 AND tc.date_sent>='$since' GROUP BY DATE_FORMAT(tc.date_sent,'%Y-%m')");
    while ($res && ($row = mysqli_fetch_assoc($res))) { if (isset($im[$row['ym']])) { $im[$row['ym']]['cli'] = (int) $row['cli']; } }
    $res = $q("SELECT DATE_FORMAT(tc.date_sent,'%Y-%m') ym, SUM(tc.amount) collected FROM `ticket_congress` tc WHERE tc.status=2 AND tc.amount>0 AND tc.date_sent>='$since' AND NOT EXISTS (SELECT 1 FROM `dpo_payment` dp WHERE dp.token=tc.confirmation AND dp.status=2) GROUP BY DATE_FORMAT(tc.date_sent,'%Y-%m')");
    while ($res && ($row = mysqli_fetch_assoc($res))) { if (isset($im[$row['ym']])) { $im[$row['ym']]['collected'] = (float) $row['collected']; } }
    $reports['international']['months'] = array_values($im);

    // ---- INTERNATIONAL per-location: revenue (collected) + fee balance (paid tickets * early - collected). Source = ticket_congress (event_config is unused). ----
    $loc = [];
    $res = $q("SELECT e.location loc, COALESCE(e.early_amount,0) ea,
               COUNT(CASE WHEN tc.status=2 AND tc.amount>0 THEN tc.id END) paidn,
               COALESCE(SUM(CASE WHEN tc.status=2 THEN tc.amount ELSE 0 END),0) collected
               FROM `ticket_congress` tc INNER JOIN `Event` e ON tc.event_id=e.event_id
               GROUP BY e.location, e.early_amount");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $k = (string) (($row['loc'] ?? '') !== '' ? $row['loc'] : 'Unknown');
        if (!isset($loc[$k])) { $loc[$k] = ['label' => $k, 'revenue' => 0, 'balance' => 0]; }
        $loc[$k]['revenue'] += (float) $row['collected'];
        $loc[$k]['balance'] += max(0, ((int) $row['paidn'] * (float) $row['ea']) - (float) $row['collected']);
    }
    usort($loc, function ($a, $b) { return ($b['revenue'] + $b['balance']) <=> ($a['revenue'] + $a['balance']); });
    $reports['international']['loc'] = array_slice(array_values($loc), 0, 10);

    // ---- CORPORATE months (proposals = enquiries, status=won = clients, corporate-event payments = fee) ----
    $cm = $seed;
    $res = $q("SELECT DATE_FORMAT(submitted_at,'%Y-%m') ym, COUNT(*) enq, SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) cli FROM `corporate_proposals` WHERE submitted_at>='$since' GROUP BY DATE_FORMAT(submitted_at,'%Y-%m')");
    while ($res && ($row = mysqli_fetch_assoc($res))) { if (isset($cm[$row['ym']])) { $cm[$row['ym']]['enq'] = (int) $row['enq']; $cm[$row['ym']]['cli'] = (int) $row['cli']; } }
    $res = $q("SELECT DATE_FORMAT(tc.date_sent,'%Y-%m') ym, SUM(tc.amount) collected FROM `ticket_congress` tc WHERE tc.status=2 AND tc.amount>0 AND tc.date_sent>='$since' AND tc.event_id IN (SELECT event_id FROM `corporate_programs`) GROUP BY DATE_FORMAT(tc.date_sent,'%Y-%m')");
    while ($res && ($row = mysqli_fetch_assoc($res))) { if (isset($cm[$row['ym']])) { $cm[$row['ym']]['collected'] = (float) $row['collected']; } }
    $reports['corporate']['months'] = array_values($cm);
} catch (\Throwable $e) { error_log('CEO Reports fetch: ' . $e->getMessage()); }
?>
<section id="content-wrapper" class="d-flex flex-column">
  <div id="content">
    <?php require_once 'top_nav.php'; ?>

    <style>
    /* ===== BDE dashboard — all scoped under .bde-app (blue theme) ===== */
    .bde-app{
      --ground:#e9eef3; --surface:#ffffff; --surface2:#f3f6f9; --surface3:#e7edf2;
      --ink:#151d28; --ink2:#3b4756; --muted:#6a7886; --faint:#9aa8b5; --line:#dce4eb;
      /* status accent = green; brand action accent = orange; theme base = blue */
      --jade:#0e9e79; --jade-deep:#0a7a5e; --jade-soft:#e2f4ee;
      --brand:#ec6e2d; --brand-deep:#c85a1e; --brand-soft:#fdece1;
      --gold:#c98a1c; --gold-soft:#fbf0d8; --gold-line:#eecf94; --amber:#c67e12; --amber-soft:#fbeed6;
      --coral:#d6472f; --coral-soft:#fbe4df; --slate:#4f6f9c; --slate-soft:#e8eef6; --violet:#6f5fbf; --violet-soft:#efeafb;
      --sidebar1:#14232f; --sidebar2:#0c141c;
      --shadow:0 1px 2px rgba(21,29,40,.05),0 14px 30px rgba(21,29,40,.09); --shadow-sm:0 1px 2px rgba(21,29,40,.06),0 4px 12px rgba(21,29,40,.05);
      --radius:16px; --radius-sm:11px;
      background:var(--ground);color:var(--ink);font-size:14px;
      font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
      line-height:1.45;-webkit-font-smoothing:antialiased;
      max-width:none;margin:0;padding:80px 24px 44px;border-radius:0;width:100%;min-height:100vh;
    }
    .bde-app.theme-dark{
      --ground:#0c1219; --surface:#161f2a; --surface2:#1d2833; --surface3:#212e3a; --ink:#eef3f7; --ink2:#c2cdd8; --muted:#8b9aa9; --faint:#63727f; --line:#28343f;
      --jade:#2ec39a; --jade-deep:#41d3ab; --jade-soft:#123027;
      --brand:#f2905a; --brand-deep:#e07640; --brand-soft:#2c1c12;
      --gold:#e2b158; --gold-soft:#2c2413; --gold-line:#4a3d1d; --amber:#e0a343; --amber-soft:#2c2413;
      --coral:#f0715a; --coral-soft:#331a16; --slate:#7d9dcb; --slate-soft:#182533; --violet:#9f90e0; --violet-soft:#20203a; --sidebar1:#111b25; --sidebar2:#0a1017;
      --shadow:0 1px 2px rgba(0,0,0,.32),0 18px 36px rgba(0,0,0,.4); --shadow-sm:0 1px 2px rgba(0,0,0,.3),0 6px 16px rgba(0,0,0,.3);
    }
    .bde-app .num{font-variant-numeric:tabular-nums;font-feature-settings:"tnum" 1}
    .bde-app *{box-sizing:border-box}
    .bde-app button,.bde-app input,.bde-app select,.bde-app textarea{font:inherit;color:inherit}
    .bde-app button{cursor:pointer} .bde-app [hidden]{display:none!important}

    .bde-app .bde-topbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 18px}
    .bde-app .brand{display:flex;align-items:center;gap:12px}
    .bde-app .brand .mark{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(150deg,var(--brand),var(--gold));color:#fff;font-weight:800;font-size:16px;letter-spacing:-.5px;box-shadow:0 8px 18px rgba(236,110,45,.35)}
    .bde-app .brand h1{font-size:16px;margin:0;letter-spacing:-.01em} .bde-app .brand p{font-size:11.5px;color:var(--muted);margin:2px 0 0}
    .bde-app .controls{margin-left:auto;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
    .bde-app .control{display:grid;gap:4px}
    .bde-app .control label{font-size:9.5px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:800}
    .bde-app .control select{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:8px 28px 8px 11px;font-size:13px;font-weight:650;appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--muted) 50%),linear-gradient(135deg,var(--muted) 50%,transparent 50%);background-position:calc(100% - 15px) 16px,calc(100% - 10px) 16px;background-size:5px 5px;background-repeat:no-repeat}
    .bde-app .control select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
    .bde-app .tbtn{border:1px solid var(--line);background:var(--surface2);border-radius:10px;padding:9px 13px;font-size:13px;font-weight:650;display:inline-flex;align-items:center;gap:7px;color:var(--ink)} .bde-app .tbtn:hover{border-color:var(--brand);color:var(--brand)}
    .bde-app .tbtn.solid{background:var(--brand);color:#fff;border-color:var(--brand)} .bde-app .tbtn.solid:hover{background:var(--brand-deep);color:#fff}
    .bde-app .profile-chip{display:flex;align-items:center;gap:10px;padding:5px 13px 5px 5px;border:1px solid var(--line);border-radius:12px;background:var(--surface2)}
    .bde-app .profile-chip .a{width:34px;height:34px;border-radius:9px;background:linear-gradient(150deg,var(--slate),#33507a);color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px}
    .bde-app .profile-chip b{font-size:13px;display:block;line-height:1.15} .bde-app .profile-chip span{font-size:11px;color:var(--muted)}
    .bde-app .tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 2px}
    .bde-app .tab{border:1px solid var(--line);background:var(--surface);border-radius:11px;padding:10px 15px;font-size:13px;font-weight:700;color:var(--muted);box-shadow:var(--shadow-sm);display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:color .15s,border-color .15s}
    .bde-app .tab svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .tab:hover{color:var(--ink);border-color:var(--brand)}
    .bde-app .tab.active{background:linear-gradient(120deg,var(--brand),var(--brand-deep));color:#fff;border-color:var(--brand);box-shadow:0 8px 18px rgba(236,110,45,.3)}
    .bde-app #workspace{display:grid;gap:18px;margin-top:18px}
    .bde-app .section-tag{display:flex;align-items:center;gap:12px;margin:8px 2px 0}
    .bde-app .section-tag h3{margin:0;font-size:16px;letter-spacing:-.01em} .bde-app .section-tag>span{font-size:12.5px;color:var(--muted)} .bde-app .section-tag .rule{flex:1;height:1px;background:linear-gradient(90deg,var(--line),transparent)}

    .bde-app .strategy{border-radius:var(--radius);background:linear-gradient(120deg,var(--sidebar1),#1c3a52);color:#fff;padding:20px 22px;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(240px,.7fr);gap:18px;align-items:center;box-shadow:var(--shadow)}
    .bde-app .strategy .eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:.16em;color:#9fd0ea;font-weight:800}
    .bde-app .strategy h2{font-size:20px;margin:6px 0 6px;letter-spacing:-.01em;line-height:1.25;color:#fff} .bde-app .strategy p{margin:0;font-size:12.5px;color:rgba(255,255,255,.82);line-height:1.5}
    .bde-app .strategy .focus{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:13px;padding:13px 15px} .bde-app .strategy .focus b{display:block;color:#ffd9a8;font-size:11px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px} .bde-app .strategy .focus span{font-size:12.5px;color:rgba(255,255,255,.9);line-height:1.5}

    .bde-app .card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px} .bde-app .card.tight{padding:14px}
    .bde-app .card h4{margin:0;font-size:15px;letter-spacing:-.01em;color:var(--ink)} .bde-app .card .sub{font-size:12px;color:var(--muted);margin:2px 0 0}
    .bde-app .chead{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:15px}
    .bde-app .chip{font-size:11px;font-weight:800;letter-spacing:.04em;padding:5px 11px;border-radius:999px;white-space:nowrap;align-self:center}
    .bde-app .chip.jade{color:var(--jade);background:var(--jade-soft)} .bde-app .chip.gold{color:var(--gold);background:var(--gold-soft)} .bde-app .chip.slate{color:var(--slate);background:var(--slate-soft)} .bde-app .chip.amber{color:var(--amber);background:var(--amber-soft)} .bde-app .chip.coral{color:var(--coral);background:var(--coral-soft)}
    .bde-app .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px} .bde-app .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    .bde-app .kpis4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .bde-app .grid-rev{display:grid;grid-template-columns:1fr 2fr;gap:16px}
    .bde-app .curtoggle{display:inline-flex;border:1px solid var(--line);border-radius:8px;overflow:hidden}
    .bde-app .curtoggle button{border:0;background:var(--surface2);color:var(--muted);padding:6px 15px;font-size:12px;font-weight:800;cursor:pointer;letter-spacing:.02em}
    .bde-app .curtoggle button.on{background:var(--brand);color:#fff}
    .bde-app .finsel{background:var(--surface2);border:1px solid var(--line);color:var(--ink);border-radius:8px;padding:6px 10px;font-size:12.5px;font-weight:700}
    .bde-app .hero{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);gap:16px}
    .bde-app .pace-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;font-weight:750;font-size:12px;border:1px solid} .bde-app .pace-pill .dot{width:8px;height:8px;border-radius:50%}
    .bde-app .pg{color:var(--jade);background:var(--jade-soft);border-color:color-mix(in srgb,var(--jade) 30%,transparent)} .bde-app .pg .dot{background:var(--jade)}
    .bde-app .pa{color:var(--amber);background:var(--amber-soft);border-color:color-mix(in srgb,var(--amber) 32%,transparent)} .bde-app .pa .dot{background:var(--amber)}
    .bde-app .pr{color:var(--coral);background:var(--coral-soft);border-color:color-mix(in srgb,var(--coral) 32%,transparent)} .bde-app .pr .dot{background:var(--coral)}

    .bde-app .kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .bde-app .kpi{position:relative;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:15px;overflow:hidden;transition:transform .15s,box-shadow .15s}
    .bde-app .kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
    .bde-app .kpi::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--acc,var(--brand));border-radius:var(--radius-sm) var(--radius-sm) 0 0}
    .bde-app .kpi .kicon{position:absolute;top:14px;right:14px;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:var(--brand-soft);color:var(--brand)} .bde-app .kpi .kicon svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .kpi .lab{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);font-weight:800;padding-right:34px}
    .bde-app .kpi .val{font-size:24px;font-weight:850;letter-spacing:-.02em;margin:10px 0 3px;line-height:1} .bde-app .kpi .meta{font-size:12px;color:var(--muted)}
    .bde-app .kpi .delta{font-size:11px;font-weight:700;margin-top:10px} .bde-app .kpi .delta .dic{font-weight:900;font-size:14px;display:inline-block;vertical-align:-1px;margin-right:1px} .bde-app .delta.up{color:var(--jade)} .bde-app .delta.down{color:var(--coral)} .bde-app .delta.flat{color:var(--brand)}

    .bde-app .prog .pl{font-size:13px;color:var(--muted);margin-top:2px} .bde-app .prog .pl b{color:var(--ink)}
    .bde-app .bar{height:14px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden;margin-top:14px;position:relative} .bde-app .bar .bf{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--coral),var(--amber) 55%,var(--jade));transition:width .6s cubic-bezier(.22,.61,.36,1)} .bde-app .bar .exp{position:absolute;top:-4px;bottom:-4px;width:2px;background:var(--ink2);opacity:.6}
    .bde-app .mini3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:15px}
    .bde-app .cm{background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px} .bde-app .cm span{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800} .bde-app .cm b{display:block;font-size:18px;font-weight:850;margin-top:5px;letter-spacing:-.02em} .bde-app .cm.gold b{color:var(--gold)}
    .bde-app .motiv{margin-top:15px;border-radius:var(--radius-sm);padding:14px;font-size:13px;line-height:1.5} .bde-app .motiv b{font-weight:800} .bde-app .chead + .motiv{margin-top:0}
    .bde-app .motiv.green{background:var(--slate-soft);color:var(--ink2);border:1px solid color-mix(in srgb,var(--slate) 30%,var(--line))} .bde-app .motiv.green b{color:var(--slate)}
    .bde-app .motiv.amber{background:var(--amber-soft);color:var(--ink2);border:1px solid var(--gold-line)} .bde-app .motiv.amber b{color:var(--amber)}
    .bde-app .motiv.red{background:var(--coral-soft);color:var(--ink2);border:1px solid color-mix(in srgb,var(--coral) 30%,var(--line))} .bde-app .motiv.red b{color:var(--coral)}

    .bde-app .chart{width:100%;height:200px;display:block} .bde-app .chart text{fill:var(--muted);font-size:10.5px} .bde-app .chart .grid{stroke:var(--line);stroke-width:1} .bde-app .chart .tline{stroke:var(--brand);stroke-dasharray:5 5;stroke-width:1.5} .bde-app .chart .area{fill:color-mix(in srgb,var(--brand) 14%,transparent)} .bde-app .chart .line{fill:none;stroke:var(--brand);stroke-width:3} .bde-app .chart .dot{fill:var(--surface);stroke:var(--brand);stroke-width:2}

    .bde-app .road-wrap{position:relative;margin:12px 4px 32px} .bde-app .road{height:16px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .road .rf{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--coral),var(--amber) 55%,var(--jade));transition:width .6s cubic-bezier(.22,.61,.36,1)}
    .bde-app .rmark{position:absolute;top:-2px;transform:translateX(-50%);text-align:center} .bde-app .rmark i{display:block;width:2px;height:22px;background:var(--faint);margin:0 auto;border-radius:2px} .bde-app .rmark span{font-size:10px;font-weight:800;color:var(--muted);margin-top:2px;display:block}
    .bde-app .nextstep{margin-top:12px;background:var(--slate-soft);border:1px solid color-mix(in srgb,var(--slate) 30%,var(--line));border-radius:var(--radius-sm);padding:13px;font-size:12.5px;color:var(--ink2);line-height:1.5} .bde-app .nextstep b{color:var(--slate)}

    .bde-app .list{display:grid;gap:9px}
    .bde-app .arow{display:grid;grid-template-columns:auto 1fr auto;gap:11px;align-items:start;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px}
    .bde-app .arow .pd{width:8px;height:8px;border-radius:50%;margin-top:6px;align-self:start} .bde-app .arow b{font-size:12.5px}.bde-app .arow p{margin:2px 0 0;font-size:11.5px;color:var(--muted)}
    .bde-app .arow .due{font-size:10px;font-weight:800;color:var(--muted);white-space:nowrap;background:var(--surface3);padding:4px 8px;border-radius:7px;border:1px solid var(--line);align-self:center}
    .bde-app .stage-chip{display:inline-block;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:8px;background:var(--slate-soft);color:var(--slate)}
    .bde-app .duec{display:inline-block;font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:8px}
    .bde-app .duec.hot{background:var(--coral-soft);color:var(--coral)} .bde-app .duec.soon{background:var(--amber-soft);color:var(--amber)} .bde-app .duec.cool{background:var(--slate-soft);color:var(--slate)}
    .bde-app .arow .abtn{align-self:center;font-size:10.5px;font-weight:800;padding:5px 12px;border-radius:8px;white-space:nowrap;border:0;cursor:pointer;transition:background .15s,color .15s}
    .bde-app .abtn.hot{background:var(--coral-soft);color:var(--coral)} .bde-app .abtn.hot:hover{background:var(--coral);color:#fff}
    .bde-app .abtn.warn{background:var(--amber-soft);color:var(--amber)} .bde-app .abtn.warn:hover{background:var(--amber);color:#fff}
    .bde-app .abtn.info{background:var(--slate-soft);color:var(--slate)} .bde-app .abtn.info:hover{background:var(--slate);color:#fff}
    .bde-app .table-wrap tbody tr:not(.me):hover td{background:color-mix(in srgb,var(--slate) 6%,var(--surface))}
    .bde-app .segmented{display:inline-flex;border:1px solid var(--line);border-radius:9px;overflow:hidden;background:var(--surface2)} .bde-app .seg{border:0;background:transparent;padding:6px 12px;font-size:11px;font-weight:750;color:var(--muted);cursor:pointer} .bde-app .seg.on{background:var(--brand);color:#fff}
    .bde-app .legend{display:flex;gap:16px;margin-top:10px;font-size:11.5px;color:var(--muted);font-weight:700} .bde-app .lg{display:inline-flex;align-items:center;gap:6px} .bde-app .lg i{width:11px;height:11px;border-radius:3px;display:inline-block}
    .bde-app .tvp-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px} .bde-app .tvp{background:var(--surface2);border:1px solid var(--line);border-radius:11px;padding:13px} .bde-app .tvp-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;gap:8px} .bde-app .tvp-top b{font-size:12.5px} .bde-app .tvp-sub{font-size:10.5px;color:var(--muted);margin-top:7px;font-variant-numeric:tabular-nums}
    .bde-app .track2{height:9px;border-radius:6px;background:var(--surface3);overflow:hidden;border:1px solid var(--line)} .bde-app .fill2{height:100%;border-radius:6px}
    .bde-app .pd.red{background:var(--coral)}.bde-app .pd.amber{background:var(--amber)}.bde-app .pd.blue{background:var(--slate)}.bde-app .pd.green{background:var(--jade)}

    .bde-app .drivers{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .bde-app .driver{position:relative;overflow:hidden;background:color-mix(in srgb,var(--dacc,var(--brand)) 10%,var(--surface));border:0;border-radius:var(--radius-sm);padding:15px 15px 14px;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(16,40,64,.05);transition:transform .15s,box-shadow .15s}
    .bde-app .driver:hover{transform:translateY(-2px);box-shadow:0 8px 16px -10px rgba(16,40,64,.20)}
    .bde-app .driver .dtop{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
    .bde-app .driver .dicon{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;background:var(--surface);color:var(--dacc,var(--brand));box-shadow:0 1px 2px rgba(16,40,64,.06)}
    .bde-app .driver .dicon svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .driver .n{font-size:23px;font-weight:850;margin:1px 0;letter-spacing:-.02em;color:var(--ink)} .bde-app .driver b{font-size:13px;color:var(--ink)} .bde-app .driver small{color:var(--muted);font-size:11px;margin-top:1px}
    .bde-app .live{font-size:9px;font-weight:800;color:var(--jade);background:var(--jade-soft);padding:2px 6px;border-radius:5px;text-transform:uppercase;letter-spacing:.05em}

    .bde-app .funnel{display:grid;gap:10px} .bde-app .fr{display:grid;grid-template-columns:170px 1fr 60px;gap:11px;align-items:center} .bde-app .fr label{font-size:12px;font-weight:650}
    .bde-app .fbar{height:28px;background:var(--surface3);border:1px solid var(--line);border-radius:8px;overflow:hidden} .bde-app .fbar div{height:100%;background:linear-gradient(90deg,#2f5f9e,#4d8bd6);border-radius:8px;display:flex;align-items:center;padding-left:12px;color:#fff;font-size:11px;font-weight:800;font-variant-numeric:tabular-nums;box-shadow:0 1px 4px -1px rgba(47,95,158,.5);transition:width .5s ease} .bde-app .fr .cv{justify-self:end;background:var(--slate-soft);color:var(--slate);font-size:10.5px;font-weight:800;padding:3px 8px;border-radius:999px;font-variant-numeric:tabular-nums}
    .bde-app .src{display:grid;grid-template-columns:1fr 100px auto;gap:11px;align-items:center;padding:7px 0} .bde-app .src label{font-size:12px;font-weight:600} .bde-app .src .sb{height:10px;border-radius:6px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .src .sb div{height:100%;border-radius:6px;background:linear-gradient(90deg,#2f5f9e,#4d8bd6)} .bde-app .src b{justify-self:end;background:var(--slate-soft);color:var(--slate);font-size:10.5px;font-weight:800;padding:3px 8px;border-radius:999px;font-variant-numeric:tabular-nums}

    .bde-app .table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:var(--radius-sm)}
    .bde-app table{width:100%;border-collapse:collapse;min-width:720px;background:var(--surface)} .bde-app th,.bde-app td{text-align:left;padding:13px 15px;border-bottom:1px solid var(--line);font-size:12.5px;vertical-align:middle}
    .bde-app th{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);background:var(--surface2);font-weight:800} .bde-app tr:last-child td{border-bottom:0}
    .bde-app .prow{display:flex;align-items:center;gap:10px} .bde-app .prow .a{width:30px;height:30px;border-radius:8px;background:var(--slate);color:#fff;display:grid;place-items:center;font-size:10px;font-weight:850} .bde-app .prow b{display:block;font-size:12.5px}.bde-app .prow span{font-size:10.5px;color:var(--muted)}
    .bde-app tr.me{background:linear-gradient(90deg,color-mix(in srgb,#3a7bd5 16%,var(--surface)),color-mix(in srgb,#3a7bd5 5%,var(--surface)))} .bde-app tr.me td{background:transparent} .bde-app tr.me .a{background:linear-gradient(150deg,#3a7bd5,#2a5aa8)}
    .bde-app .mini-track{height:7px;border-radius:99px;background:var(--surface3);overflow:hidden;border:1px solid var(--line);min-width:70px;display:inline-block;vertical-align:middle} .bde-app .mini-track div{height:100%;border-radius:99px}
    .bde-app .sbadge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;padding:4px 9px;border-radius:999px} .bde-app .sbadge .dot{width:7px;height:7px;border-radius:50%}
    .bde-app .sg{color:var(--jade);background:var(--jade-soft)} .bde-app .sg .dot{background:var(--jade)} .bde-app .sa{color:var(--amber);background:var(--amber-soft)} .bde-app .sa .dot{background:var(--amber)} .bde-app .sr{color:var(--coral);background:var(--coral-soft)} .bde-app .sr .dot{background:var(--coral)}

    .bde-app .check{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px} .bde-app .check .sym{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:14px;font-weight:900} .bde-app .check.ok .sym{background:var(--jade-soft);color:var(--jade)} .bde-app .check.no .sym{background:var(--coral-soft);color:var(--coral)} .bde-app .check b{font-size:12.5px} .bde-app .check small{font-size:10.5px;color:var(--muted);display:block;margin-top:1px} .bde-app .check .cv{font-size:13px;font-weight:850;font-variant-numeric:tabular-nums}
    .bde-app .audit{display:grid;grid-template-columns:auto 1fr;gap:11px;align-items:start;padding:11px 0;border-bottom:1px solid var(--line)} .bde-app .audit:last-child{border-bottom:0} .bde-app .audit .k{width:9px;height:9px;border-radius:50%;background:var(--slate);margin-top:5px} .bde-app .audit b{font-size:12.5px} .bde-app .audit p{margin:2px 0 0;font-size:11.5px;color:var(--muted)}
    .bde-app .steps3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px} .bde-app .stepbox{background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:14px} .bde-app .stepbox span{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800} .bde-app .stepbox b{display:block;font-size:15px;margin:6px 0 4px} .bde-app .stepbox .st{font-size:11.5px;font-weight:700}

    .bde-app .timeline{display:grid;gap:2px} .bde-app .time-row{display:grid;grid-template-columns:120px 1fr;gap:14px;padding:12px 0;border-bottom:1px solid var(--line)} .bde-app .time-row:last-child{border-bottom:0} .bde-app .time-row time{font-size:12px;font-weight:850;color:var(--brand)} .bde-app .time-row div{font-size:12.5px;color:var(--ink2)}
    .bde-app .principles{display:grid;grid-template-columns:repeat(3,1fr);gap:11px} .bde-app .principle{border-left:3px solid var(--brand);background:var(--surface2);border-radius:var(--radius-sm);padding:13px 15px} .bde-app .principle b{font-size:12.5px} .bde-app .principle p{font-size:11.5px;color:var(--muted);margin:4px 0 0;line-height:1.5}
    .bde-app .scorecard{display:grid;gap:11px} .bde-app .scr{display:grid;grid-template-columns:220px 1fr 48px;gap:12px;align-items:center} .bde-app .scr label{font-size:12px;font-weight:600} .bde-app .scr .sb{height:9px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .scr .sb div{height:100%;border-radius:99px;background:linear-gradient(90deg,#2f5f9e,#4d8bd6)} .bde-app .scr b{font-size:12.5px;font-weight:800;text-align:right}

    .bde-app .form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px} .bde-app .field{display:grid;gap:5px} .bde-app .field.span2{grid-column:span 2}.bde-app .field.span4{grid-column:span 4}
    .bde-app .field label{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800}
    .bde-app .field input,.bde-app .field textarea{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-size:13px;width:100%} .bde-app .field textarea{min-height:82px;resize:vertical;line-height:1.5} .bde-app .field input:focus,.bde-app .field textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)} .bde-app .field input:hover,.bde-app .field textarea:hover{border-color:color-mix(in srgb,var(--brand) 35%,var(--line))} .bde-app .field input[type=number]{font-variant-numeric:tabular-nums;font-weight:650}
    .bde-app .form-sub{display:flex;align-items:center;gap:10px;margin:2px 2px 11px;font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);font-weight:800} .bde-app .form-sub i{color:var(--brand);font-style:normal;font-weight:800;letter-spacing:.03em} .bde-app .form-sub::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,var(--line),transparent)}
    .bde-app .report-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}
    .bde-app .report-preview{white-space:pre-wrap;background:var(--surface2);border:1px dashed var(--line);border-radius:12px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;line-height:1.6;min-height:130px;color:var(--ink2)}

    .bde-app .bde-foot{font-size:11.5px;color:var(--muted);margin-top:14px;line-height:1.6} .bde-app .bde-foot code{background:var(--surface2);padding:1px 5px;border-radius:5px;border:1px solid var(--line)}

    @media(max-width:1000px){
      .bde-app .hero,.bde-app .grid-2,.bde-app .grid-3,.bde-app .strategy,.bde-app .grid-rev{grid-template-columns:1fr} .bde-app .kpis,.bde-app .kpis4{grid-template-columns:1fr 1fr} .bde-app .drivers{grid-template-columns:repeat(2,1fr)} .bde-app .principles{grid-template-columns:1fr} .bde-app .form-grid{grid-template-columns:repeat(2,1fr)} .bde-app .field.span4{grid-column:span 2}
    }
    @media(max-width:560px){.bde-app{padding:12px 14px 40px} .bde-app .kpis,.bde-app .kpis4,.bde-app .mini3,.bde-app .steps3,.bde-app .form-grid{grid-template-columns:1fr} .bde-app .field.span2,.bde-app .field.span4{grid-column:span 1} .bde-app .fr{grid-template-columns:110px 1fr 42px} .bde-app .scr{grid-template-columns:130px 1fr 40px}}
    @media(prefers-reduced-motion:reduce){.bde-app *{transition:none!important}}
    .bde-app .ops-modal{position:fixed;inset:0;background:rgba(10,20,30,.55);z-index:2000;display:none;align-items:flex-start;justify-content:center;padding:5vh 16px}
    .bde-app .ops-modal.open{display:flex}
    .bde-app .ops-modal-box{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);width:min(820px,100%);max-height:86vh;display:flex;flex-direction:column;overflow:hidden}
    .bde-app .ops-modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--line)}
    .bde-app .ops-modal-head h4{margin:0;font-size:15px}
    .bde-app .ops-modal-body{padding:14px 18px;overflow:auto}
    </style>

    <div class="bde-app" id="bdeApp">
      <header class="bde-topbar">
        <div class="brand"><div class="mark">VA</div><div><h1>CEO Performance Overview</h1><p>Whole organization → departments → every person, on one screen</p></div></div>
        <div class="controls">
          <div class="control"><label>Role view</label><select id="roleSelect">
            <option value="ceo">CEO</option>
            <option value="bdm">BDM</option>
            <option value="bdo">BDO</option>
            <option value="bde">BDE</option>
          </select></div>
          <div class="control" id="deptControl" style="display:none"><label>Department</label><select id="deptSelect"></select></div>
          <div class="control" id="empControl" style="display:none"><label>Employee</label><select id="empSelect"></select></div>
          <div class="control"><label>Analytics month</label><select id="periodSelect"></select></div>
          <button class="tbtn" id="themeBtn" type="button">🌙 Dark</button>
          <div class="profile-chip"><span class="a">BK</span><div><b>Chief Executive</b><span>Whole organization</span></div></div>
        </div>
      </header>
      <nav class="tabs" aria-label="Dashboard sections" id="tabNav">
        <button class="tab active" data-v="command"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Organization</button>
        <button class="tab" data-v="people"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 5.4a3.4 3.4 0 0 1 0 5.2M20.5 20a5.5 5.5 0 0 0-3.6-5.2"/></svg>Departments &amp; People</button>
        <button class="tab" data-v="pipeline"><svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>Pipeline &amp; Conversion</button>
        <button class="tab" data-v="reports"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Analytics</button>
        <button class="tab" data-v="hr"><svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>HR</button>
        <button class="tab" data-v="finance"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>Finance</button>
        <button class="tab" data-v="admin"><svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M9 9h6M9 13h6M9 17h4"/></svg>Admin</button>
      </nav>
      <main id="workspace"></main>
      <div class="ops-modal" id="opsModal"><div class="ops-modal-box"><div class="ops-modal-head"><h4 id="opsModalTitle"></h4><button type="button" class="tbtn" data-close>✕ Close</button></div><div class="ops-modal-body" id="opsModalBody"></div></div></div>
    </div>

    <script>
    (() => {
      "use strict";
      const root=document.getElementById("bdeApp");
      const HR = <?php echo json_encode($hr, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}'; ?>;
      const FIN = <?php echo json_encode($finance, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}'; ?>;
      const ADM = <?php echo json_encode($admin, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}'; ?>;
      const REP = <?php echo json_encode($reports, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}'; ?>;
      const B={
        name:"Office of the CEO", initials:"VA", title:"Chief Executive Officer", dept:"Whole organization", level:"Executive",
        bdmName:<?php echo json_encode($ceo['name'] ?: 'Business Development Manager', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDM"'; ?>, bdmInitials:<?php echo json_encode($ceo['initials'] ?: 'MO', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"MO"'; ?>,
        target:<?php echo (float) $ceo['target']; ?>, actual:<?php echo (float) $ceo['actual']; ?>, forecast:<?php echo (float) $ceo['forecast']; ?>, pipeline:<?php echo (float) $ceo['pipeline']; ?>, collection:<?php echo (float) $ceo['collection']; ?>,
        clearedKes:<?php echo (float) ($ceo['clearedKes'] ?? 0); ?>, clients:<?php echo (int) $ceo['clients']; ?>, totalLeads:<?php echo (int) $ceo['totalLeads']; ?>,
        intl:<?php echo json_encode($ceo['intl'] ?: null, JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null'; ?>,
        personalTarget:<?php echo (float) $ceo['personalTarget']; ?>, personalActual:<?php echo (float) $ceo['personalActual']; ?>, personalPipeline:<?php echo (float) $ceo['personalPipeline']; ?>,
        mandate:"Make growth systematic across every SBU while protecting balanced performance, collections and margin.",
        mandateText:"The CEO oversees consolidated revenue, qualified pipeline, every SBU's attainment, collections, HR, finance and statutory health — intervening where a unit is at risk before month-end.",
        focus:"Correct the weakest SBU, unblock the highest-value accounts, and ensure every HOD carries an evidence-based forecast and recovery action.",
        funnel:<?php echo json_encode(!empty($ceo['funnel']) ? $ceo['funnel'] : [['Leads', 0], ['Paid clients', 0]], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[["Leads",0],["Paid clients",0]]'; ?>,
        sources:<?php echo json_encode($ceo['sources'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        alerts:<?php echo json_encode($ceo['alerts'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        crossSbu:<?php echo json_encode($ceo['crossSbu'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        lms:<?php echo json_encode($lms, JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null'; ?>,
        dailyCum:<?php echo json_encode($ceo_series['cum'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        dailyAmt:<?php echo json_encode($ceo_series['amt'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        dailyDates:<?php echo json_encode($ceo_series['dates'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        daysInMonth:<?php echo (int) $ceo_series['dim']; ?>, dayToday:<?php echo (int) $ceo_series['dom']; ?>,
        dailyRhythm:[
          ["8:00–8:30","Review consolidated revenue, the weakest SBU, strategic accounts, RFPs, collections and overdue actions."],
          ["8:30–9:00","Set the day's SBU recovery, strategic-account and personal-revenue outcomes."],
          ["9:00–11:00","Call and coach HODs; personally advance the highest-value blocked accounts."],
          ["11:00–1:00","Audit SBU forecasts, proposals, tenders and cross-SBU opportunities."],
          ["2:00–4:30","Strategic-account meetings, negotiations and executive coordination."],
          ["4:30–5:15","Confirm every SBU next action, update CRM and submit the consolidated report."]
        ],
        principles:[
          ["Balanced growth beats a single hero SBU","Strong results in one department must never mask serious underperformance in another."],
          ["Every forecast is evidence-based","No HOD forecast without stage, value, probability, owner and a dated next action."],
          ["Lead by intervention, not observation","Move blocked high-value deals and correct weak SBUs before month-end, not after."]
        ],
        sbus:<?php echo json_encode($ceo['sbus'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>
      };
      const periods=[{label:<?php echo json_encode(date('F Y', strtotime($ceo_to)), JSON_INVALID_UTF8_SUBSTITUTE) ?: '"This month"'; ?>,working:<?php echo (int) date('t', strtotime($ceo_to)); ?>,elapsed:<?php echo (int) max(1, min((int) date('j', strtotime($ceo_to)), (int) date('t', strtotime($ceo_to)))); ?>}];
      const state={p:0,view:"command",role:"ceo",dept:0,emp:0,finYear:"all",finCur:"USD"};

      const nf=new Intl.NumberFormat("en-KE",{maximumFractionDigits:0});
      const kMoney=v=>{const a=Math.abs(v||0);if(a>=1e6)return "KES "+(v/1e6).toFixed(2).replace(/\.00$/,"")+"M";if(a>=1e3)return "KES "+Math.round(v/1e3)+"K";return "KES "+nf.format(Math.round(v||0));};
      // SBUs/reps are metric-aware: International is client-based, the rest KES.
      const sbuActual=d=>d.kes?kMoney(d.actual):nf.format(Math.round(d.actual||0))+" clients";
      const sbuTarget=d=>d.kes?kMoney(d.target):nf.format(Math.round(d.target||0))+" clients";
      const liveSbus=()=>B.sbus.filter(d=>!d.placeholder);
      const rAttn=r=>(+r.target>0)?(r.actual/r.target):0;
      // Real sales-commission liability owed to marketers (from the Finance data, stored USD → KES).
      function commPayableKes(){const c=(typeof FIN!=="undefined"&&FIN.commission)||{};const rate=(typeof FIN!=="undefined"&&FIN.rate)||1;const owed=Math.max(0,(+c.pending||0)+(+c.approved||0));return owed*rate;}
      function commEligibleKes(){const c=(typeof FIN!=="undefined"&&FIN.commission)||{};const rate=(typeof FIN!=="undefined"&&FIN.rate)||1;return Math.max(0,(+c.eligible||0))*rate;}
      const repActual=r=>r.kes===false?nf.format(Math.round(r.actual||0))+" clients":kMoney(r.actual);
      const repTarget=r=>r.kes===false?nf.format(Math.round(r.target||0))+" clients":kMoney(r.target);
      // Deterministic per-person avatar colour (same name → same colour, different names differ).
      const avCols=["var(--slate)","var(--violet)","#2f8f88","var(--brand)","var(--gold)","#4d8bd6","var(--coral)","#7a5cc0"];
      const avColor=n=>{let h=0;const s=String(n||"");for(let i=0;i<s.length;i++){h=(h*31+s.charCodeAt(i))>>>0;}return avCols[h%avCols.length];};
      const pct=(v,d=1)=>(v*100).toFixed(d).replace(/\.0$/,"")+"%";
      const esc=s=>String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
      const el=id=>document.getElementById(id);
      const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
      const period=()=>periods[state.p];

      function pace(){const p=period();const expected=B.target*(p.elapsed/p.working);const ratio=expected?B.actual/expected:0;const status=ratio>=1?"green":ratio>=.85?"amber":"red";return {expected,ratio,status,label:status==="green"?"On pace":status==="amber"?"At risk":"Behind pace"};}
      const scol=s=>s==="green"?"var(--jade)":s==="amber"?"var(--amber)":"var(--coral)";
      function commission(){
        const s=B;const orgAtt=s.target>0?s.actual/s.target:0;const personal=s.personalActual;
        const live=liveSbus();const nS=live.length||1;const need=Math.max(1,Math.ceil(nS*0.8));
        const personalComm=personal>=7500000?150000:personal>=6000000?120000:personal>=5000000?90000:personal>=4000000?60000:0;
        const sbus80=live.filter(d=>(+d.attn)>=.8).length;
        const noneBelow50=live.every(d=>(+d.attn)>=.5);
        const leadership=orgAtt>=1.1?125000:orgAtt>=1?100000:orgAtt>=.9?75000:orgAtt>=.8?50000:0;
        const gated=sbus80>=need&&s.collection>=.9&&noneBelow50;
        const current=gated?personalComm+leadership:Math.round(personalComm*.7);
        const atTarget=90000+100000;
        const gates=[
          ["Organization reaches 80%+",orgAtt>=.8,pct(orgAtt,0)],
          [`At least ${need} of ${nS} SBUs at 80%+`,sbus80>=need,sbus80+" of "+nS],
          ["No SBU below 50%",noneBelow50,live.filter(d=>(+d.attn)<.5).length+" below"],
          ["Organization collection at 90%+",s.collection>=.9,pct(s.collection,0)],
          ["Personal strategic sales (KES 4M+)",personal>=4000000,kMoney(personal)]
        ];
        const unlock=gated?"Organization leadership gate unlocked":"Complete the balanced-SBU and 90% collection gates.";
        const rule="Personal strategic-acquisition commission plus an organization-wide leadership commission, with a 30% leadership hold-back until the balanced-SBU and collection gates are satisfied.";
        return {current,atTarget,gates,unlock,rule};
      }

      /* ---------- shared blocks ---------- */
      function strategyStrip(){return `<section class="strategy"><div><div class="eyebrow">Enterprise intervention dashboard</div><h2>See the entire organization clearly and intervene where leadership, resources or decisions will change the result.</h2><p>The CEO dashboard consolidates revenue, forecasts, staff performance, strategic accounts, commissions, collections, product readiness and the few decisions requiring executive attention.</p></div><div class="focus"><b>Today's strategic focus</b><span>Protect organization-wide revenue, resolve the biggest bottleneck and support the opportunities with the greatest strategic value.</span></div></section>`;}

      function kpiBlock(){
        const att=B.target>0?B.actual/B.target:0;const kesN=liveSbus().filter(d=>d.kes).length;
        const payable=commPayableKes();const eligible=commEligibleKes();
        const intl=B.intl;const intlCell=intl?`${nf.format(Math.round(intl.actual))} / ${nf.format(Math.round(intl.target))} clients`:"—";
        const items=[
          ["Organization target (KES SBUs)",kMoney(B.target),kesN+" revenue SBU"+(kesN===1?"":"s"),"flat","var(--slate)"],
          ["Cleared revenue",kMoney(B.actual),pct(att)+" attainment","up","var(--jade)"],
          ["Month-end forecast",kMoney(B.forecast),(B.target>0?pct(B.forecast/B.target):"0%")+" projected","flat","var(--slate)"],
          ["International (clients)",intlCell,intl?pct(intl.attn,0)+" of target":"not resolved","up","var(--violet)"],
          ["Collection rate",pct(B.collection,0),"across all SBUs","flat","var(--brand)"],
          ["Commission to pay",kMoney(payable),"owed to marketers · "+kMoney(eligible)+" eligible","flat","var(--amber)"]
        ];
        const dt={up:'<span class="dic">↗</span> Positive movement',down:'<span class="dic">↘</span> Below pace',flat:'<span class="dic">•</span> Live from CRM / Finance'};
        const kIcons=[
          '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg>',
          '<svg viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M12 3l9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M20 8H6a2 2 0 0 1 0-4h13v4M3 6v11a2 2 0 0 0 2 2h15V8"/><circle cx="16.5" cy="13.5" r="1.4" fill="currentColor" stroke="none"/></svg>',
          '<svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9M9 3h6"/></svg>'
        ];
        return `<div class="kpis">${items.map(([l,v,m,d,a],i)=>`<div class="kpi" style="--acc:${a}"><span class="kicon">${kIcons[i%kIcons.length]}</span><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div><div class="delta ${d}">${dt[d]}</div></div>`).join("")}</div>`;
      }

      function progressCard(){
        const p=period();const att=B.actual/B.target;const ps=pace();const daysLeft=Math.max(0,p.working-p.elapsed);
        const motiv=ps.status==="green"?"<b>On track:</b> The organization is at or above required pace. Protect collections and margin, and back the SBUs pursuing stretch targets.":ps.status==="amber"?"<b>Watch:</b> The organization is near pace. The nearest-to-close accounts and the weakest SBU need attention to hold the month.":"<b>Behind pace:</b> At the current run-rate the organization will miss target. The weakest SBUs need intervention now to close the gap before month-end.";
        return `<div class="card prog">
          <div class="chead"><h4>Progress to target</h4><span class="chip ${ps.status==="green"?"jade":ps.status==="amber"?"amber":"coral"} num">${pct(att)}</span></div>
          <div class="pl">Cleared revenue · <b class="num">${kMoney(B.actual)} / ${kMoney(B.target)}</b></div>
          <div class="bar"><div class="bf" style="width:${clamp(att*100,0,100)}%"></div><div class="exp" style="left:${clamp((p.elapsed/p.working)*100,0,100)}%"></div></div>
          <div class="mini3"><div class="cm"><span>Expected by today</span><b class="num">${kMoney(ps.expected)}</b></div><div class="cm"><span>Remaining gap</span><b class="num">${kMoney(Math.max(0,B.target-B.actual))}</b></div><div class="cm"><span>Days left</span><b class="num">${daysLeft}</b></div></div>
          <div class="motiv ${ps.status}">${motiv}</div>
        </div>`;
      }

      function trendSVG(){
        // Real month-to-date cleared revenue, built day-by-day from actual payments (hover a day for its amount).
        const series=(B.dailyCum&&B.dailyCum.length)?B.dailyCum:[0];
        const dim=Math.max(2,B.daysInMonth||30);const dayT=Math.max(1,Math.min(dim,B.dayToday||series.length));
        const target=B.target||0;const cur=series[series.length-1]||0;const w=560,h=200,pd=34;
        const max=Math.max(target,cur,...series,1)*1.12;
        const X=day=>pd+(day-1)/(dim-1)*(w-2*pd);const Y=v=>h-pd-(v/max)*(h-2*pd);
        const A=series.map((v,i)=>[X(i+1),Y(v)]);
        const aLine=A.map((q,i)=>(i?"L":"M")+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ");
        const aArea=`M${A[0][0].toFixed(1)},${(h-pd).toFixed(1)} `+A.map(q=>"L"+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ")+` L${A[A.length-1][0].toFixed(1)},${(h-pd).toFixed(1)} Z`;
        const ty=Y(target),tx=X(dayT);
        return `<svg class="chart" viewBox="0 0 ${w} ${h}" role="img" aria-label="Month-to-date cleared revenue vs target">
          ${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*(h-2*pd)).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*(h-2*pd)).toFixed(1)}"/>`).join("")}
          <line class="tline" x1="${pd}" y1="${ty.toFixed(1)}" x2="${w-pd}" y2="${ty.toFixed(1)}"/><text x="${w-pd}" y="${(ty-6).toFixed(1)}" text-anchor="end">Target ${kMoney(target)}</text>
          <line x1="${tx.toFixed(1)}" y1="${pd}" x2="${tx.toFixed(1)}" y2="${h-pd}" stroke="var(--faint)" stroke-dasharray="3 3"/>
          <path class="area" d="${aArea}"/><path class="line" d="${aLine}"/>
          ${A.map((q,i)=>{const dAmt=(B.dailyAmt&&B.dailyAmt[i])||0;const dLbl=(B.dailyDates&&B.dailyDates[i])||("Day "+(i+1));return `<circle cx="${q[0].toFixed(1)}" cy="${q[1].toFixed(1)}" r="2.6" fill="var(--brand)"/><circle cx="${q[0].toFixed(1)}" cy="${q[1].toFixed(1)}" r="10" fill="transparent" style="cursor:pointer"><title>${esc(dLbl)}: ${kMoney(dAmt)} cleared that day  (${kMoney(series[i])} so far)</title></circle>`;}).join("")}
          <circle cx="${tx.toFixed(1)}" cy="${Y(cur).toFixed(1)}" r="4.5" fill="var(--brand)" stroke="#fff" stroke-width="1.5"/><text x="${tx.toFixed(1)}" y="${Math.max(pd+10,Y(cur)-9).toFixed(1)}" text-anchor="middle" style="font-weight:800;fill:var(--ink)">Now ${kMoney(cur)}</text>
          <text x="${pd}" y="${h-8}">Day 1</text><text x="${tx.toFixed(1)}" y="${h-8}" text-anchor="middle">Today (day ${dayT})</text><text x="${w-pd}" y="${h-8}" text-anchor="end">Month end</text></svg>`;
      }

      function actionsCard(){
        // Generated from the live data: real SBU gaps, collection, staff, commission and escalations.
        const s=orgStats();const per=period();const need=per.elapsed/per.working;
        const behind=liveSbus().filter(d=>(+d.attn)<need).sort((a,b)=>((+a.attn)||0)-((+b.attn)||0));
        const alertN=(B.alerts||[]).reduce((a,x)=>a+((+x.n)||0),0);
        const list=[];
        behind.slice(0,2).forEach(d=>list.push(["red","Recover "+d.name+" — "+pct((+d.attn)||0,0)+" attained","Weakest SBU under pace. Require a 7-day recovery forecast and a named opportunity list from "+(d.leader||"the HOD")+".","Today"]));
        const conv=(+B.totalLeads>0)?((+B.clients||0)/(+B.totalLeads)):0;
        if((+B.totalLeads)>0&&conv<0.3) list.push(["amber","Lift conversion — "+pct(conv,0)+" of leads convert","Only "+nf.format((+B.clients)||0)+" of "+nf.format((+B.totalLeads)||0)+" leads became paying clients. Tighten follow-up on qualified leads to raise the rate.","This week"]);
        if(s.below80>0) list.push(["amber","Support "+s.below80+" staff below 80%","of "+s.total+" BDEs are under target — coach the recoverable ones, reassign or replace the rest.","This week"]);
        if(s.commNow>0) list.push(["blue","Clear "+kMoney(s.commNow)+" commission owed","Pending + approved marketer commission is due — approve for payroll.","Before payroll"]);
        if(alertN>0) list.push(["blue",alertN+" WhatsApp chats awaiting reply","Escalated conversations across the SBUs need a human response.","Today","waescal"]);
        if(behind.length===0) list.push(["green","Protect balanced performance","Every SBU is at or above required pace — protect collections, quality and stretch.","Ongoing"]);
        if(list.length===0) list.push(["green","No critical interventions","The organization is on pace with collections and staffing healthy.","—"]);
        const riskLabel={red:"Critical",amber:"High",blue:"Watch",green:"Positive"};
        const riskChip={red:"coral",amber:"amber",blue:"slate",green:"jade"};
        return `<div class="card"><div class="chead"><h4>Risk → action</h4><span class="chip ${list.some(x=>x[0]==="red")?"coral":"slate"}">Interventions</span></div><div class="list">${list.slice(0,6).map(([c,b,p,d,mk])=>`<div class="arow"${mk?` data-modal="${mk}" style="cursor:pointer"`:""}><span class="pd ${c}"></span><div><b>${esc(b)}${mk?' <span style="color:var(--brand);font-weight:800">→</span>':''}</b><p>${esc(p)} · <b style="font-weight:800">${esc(d)}</b></p></div><span class="chip ${riskChip[c]}">${riskLabel[c]}</span></div>`).join("")}</div></div>`;
      }
      function decisionsCard(){
        // Executive-decision tracking isn't captured in the CRM yet — no fabricated items.
        return `<div class="card"><div class="chead"><h4>Decisions requiring executive attention</h4><span class="chip slate">Not tracked in CRM</span></div><p style="color:var(--muted);font-size:12.5px;margin:0;line-height:1.6">Pricing approvals, resource allocation, partnership sign-offs and go/no-go calls aren't captured in the CRM yet. When an approvals/decisions workflow exists, pending items will list here automatically.</p></div>`;
      }
      function teamTable(){
        const avatarCols=["var(--slate)","#2f8f88","var(--brand)","var(--violet)","var(--gold)"];
        const p=period();
        // Ranked by attainment (best → worst); the Academic placeholder sinks to the bottom.
        const ordered=B.sbus.map((d,i)=>({d,i})).sort((A,B)=>((A.d.placeholder?1:0)-(B.d.placeholder?1:0))||(((+B.d.attn)||0)-((+A.d.attn)||0)));
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>SBU</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Projected</th><th>Collection</th><th>Status / response</th></tr></thead><tbody>${ordered.map(({d,i})=>{const ini=d.name.split(/\s+/).map(x=>x[0]).slice(0,2).join("");if(d.placeholder){return `<tr style="opacity:.6"><td><div class="prow"><span class="a" style="background:var(--faint)">${ini}</span><div><b>${esc(d.name)}</b><span>${esc(d.leader)}</span></div></div></td><td class="num" colspan="5" style="color:var(--muted)">Not yet configured in the CRM</td><td><span class="chip slate">Placeholder</span></td></tr>`;}const a=(+d.attn)||0;const exp=d.target*(p.elapsed/p.working);const st=d.actual>=exp?"green":d.actual>=exp*.85?"amber":"red";const lbl=st==="green"?"On pace":st==="amber"?"At risk":"Behind pace";const resp=st==="red"?"Recovery plan + daily monitoring":st==="amber"?"Corrective action within 24h":"Protect quality; pursue stretch";return `<tr><td><div class="prow"><span class="a" style="background:${avatarCols[i%avatarCols.length]}">${ini}</span><div><b><span data-scope="bdo-${i}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(d.name)}</span></b><span>${esc(d.leader)}${d.kes?"":" · clients"}</span></div></div></td><td class="num">${sbuTarget(d)}</td><td class="num">${sbuActual(d)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td class="num">${d.kes?kMoney(d.forecast):nf.format(Math.round(d.forecast))+" clients"}</td><td class="num">${pct(d.collection,0)}</td><td><span class="sbadge s${st[0]}"><span class="dot"></span>${lbl}</span><div style="font-size:10.5px;color:var(--muted);margin-top:5px">${resp}</div></td></tr>`;}).join("")}</tbody></table></div></div>`;
      }

      /* ---------- executive master view (BDM request) ---------- */
      function execRevenueBreakdown(){
        const shortName={"International":"Int'l","Virtual":"Virtual","Corporate":"Corporate","Digital Solutions":"Digital","Academic":"Academic"};
        const data=liveSbus().map(d=>({name:shortName[d.name]||d.name,attn:(+d.attn)||0,tLab:sbuTarget(d),aLab:sbuActual(d)}));
        const maxA=Math.max(1.1,...data.map(d=>d.attn))*1.12;
        const w=680,h=286,pd=42,base=h-pd-26,plot=base-pd,step=(w-2*pd)/Math.max(1,data.length),bw=38,gap=14;
        const bars=data.map((d,i)=>{const cx=pd+step*i+step/2;const xT=cx-bw-gap/2,xA=cx+gap/2;const th=1/maxA*plot;const ah=Math.max(3,d.attn/maxA*plot);const st=d.attn>=1?"green":d.attn>=.7?"amber":"red";return `<g>
          <rect x="${xT.toFixed(1)}" y="${(base-th).toFixed(1)}" width="${bw}" height="${th.toFixed(1)}" rx="4" fill="var(--slate)"/>
          <rect x="${xA.toFixed(1)}" y="${(base-ah).toFixed(1)}" width="${bw}" height="${ah.toFixed(1)}" rx="4" fill="${scol(st)}"/>
          <text x="${(xT+bw/2).toFixed(1)}" y="${(base-th-7).toFixed(1)}" text-anchor="middle" style="font-size:10px;font-weight:700;fill:var(--slate)">${d.tLab}</text>
          <text x="${(xA+bw/2).toFixed(1)}" y="${(base-ah-7).toFixed(1)}" text-anchor="middle" style="font-size:10px;font-weight:800;fill:${scol(st)}">${d.aLab}</text>
          <text x="${cx.toFixed(1)}" y="${(base+16).toFixed(1)}" text-anchor="middle" style="font-weight:700">${esc(d.name)}</text>
          <text x="${cx.toFixed(1)}" y="${(base+30).toFixed(1)}" text-anchor="middle" style="font-size:10px;fill:var(--muted)">${pct(d.attn,0)} of target</text></g>`;}).join("");
        return `<div class="card"><div class="chead"><div><h4>Cleared vs target — by SBU</h4><p>How far each SBU has cleared toward its own target. International is in clients, the rest in KES.</p></div></div>
          <svg class="chart" viewBox="0 0 ${w} ${h}" style="height:286px" role="img" aria-label="Cleared versus target by SBU">${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*plot).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*plot).toFixed(1)}"/>`).join("")}${bars}</svg>
          <div class="legend"><span class="lg"><i style="background:var(--slate)"></i>Target</span><span class="lg"><i style="background:var(--jade)"></i>Cleared &nbsp;<span style="color:var(--muted)">· colour = pace</span></span></div></div>`;
      }
      function execTargetProgress(){
        const p=period();
        const rows=liveSbus().map(d=>{const a=(+d.attn)||0;const exp=d.target*(p.elapsed/p.working);const st=d.actual>=exp?"green":d.actual>=exp*.85?"amber":"red";return `<div class="tvp"><div class="tvp-top"><b>${esc(d.name)}</b><span class="chip ${st==="green"?"jade":st==="amber"?"amber":"coral"}">${pct(a,0)}</span></div><div class="track2"><div class="fill2" style="width:${clamp(a*100,0,100)}%;background:${scol(st)}"></div></div><div class="tvp-sub">${sbuActual(d)} / ${sbuTarget(d)}</div></div>`;}).join("");
        return `<div class="card"><div class="chead"><div><h4>Target vs actual — by department</h4><p>Closed revenue against each departmental quota.</p></div><span class="chip slate">Colour = pace</span></div><div class="tvp-grid">${rows}</div></div>`;
      }
      function execTopDeals(){
        // Real cross-SBU opportunities flagged on field visits (bde_visits.opportunity_note).
        const rows=(B.crossSbu||[]);
        if(!rows.length) return `<div class="card"><div class="chead"><h4>Cross-SBU opportunities</h4><span class="chip slate">From field visits</span></div><p style="color:var(--muted);font-size:12.5px;margin:0;line-height:1.6">No opportunities flagged from field visits yet. As BDOs and BDEs log field visits with opportunity notes, the highest-value ones surface here. A structured strategic-deals pipeline (value · stage · owner) needs a deals table — not yet in the CRM.</p></div>`;
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>#</th><th>Opportunity flagged on a field visit</th></tr></thead><tbody>${rows.map((x,i)=>`<tr><td class="num">${i+1}</td><td>${esc(x)}</td></tr>`).join("")}</tbody></table></div></div>`;
      }

      /* ---------- views ---------- */
      /* ---------- CEO performance analytics (workbook additions) ---------- */
      function orgStats(){
        const per=period();const daysLeft=Math.max(0,per.working-per.elapsed);
        const bdes=allPeople().filter(p=>p.role==="BDE");
        const at100=bdes.filter(p=>rAttn(p)>=1).length;
        const below80=bdes.filter(p=>rAttn(p)<.8).length;
        const earners=bdes.filter(p=>rAttn(p)>=.8).length;
        const sorted=[...bdes].sort((a,b)=>rAttn(b)-rAttn(a));
        const top=sorted[0]||null,low=sorted[sorted.length-1]||null;
        const live=liveSbus();
        const worst=[...live].sort((a,b)=>((+a.attn)||0)-((+b.attn)||0))[0]||null;
        const gap=Math.max(0,B.target-B.actual);
        const commNow=commPayableKes();
        const commAtTarget=commEligibleKes();
        const sbusOnPace=live.filter(d=>(+d.attn)>=(per.elapsed/per.working)).length;
        return {daysLeft,at100,below80,earners,top,low,worst,gap,commNow,commAtTarget,sbusOnPace,liveN:live.length,total:bdes.length,dailyReq:daysLeft?gap/daysLeft:0};
      }
      function countsStrip(){
        const s=orgStats();
        const ic={ok:'<svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>',warn:'<svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',coin:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4"/></svg>',pace:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/></svg>'};
        const tiles=[
          ["Staff at 100%+",s.at100+" / "+s.total,"target achieved","var(--jade)",ic.ok],
          ["Staff below 80%",s.below80+" / "+s.total,"need support","var(--coral)",ic.warn],
          ["Commission earners",s.earners+" / "+s.total,"past the 80% gate","var(--gold)",ic.coin],
          ["SBUs on pace",s.sbusOnPace+" / "+s.liveN,"at / above required pace","var(--slate)",ic.pace]
        ];
        return `<div class="card"><div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">${tiles.map(([l,v,m,a,icn])=>`<div style="background:var(--surface2);border:1px solid var(--line);border-left:3px solid ${a};border-radius:11px;padding:15px 17px"><div style="display:flex;align-items:center;justify-content:space-between;gap:8px"><span style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800">${l}</span><span style="flex:0 0 auto;color:${a};display:grid;place-items:center">${icn.replace('<svg','<svg style="width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"')}</span></div><b class="num" style="display:block;font-size:22px;color:var(--ink);line-height:1.1;margin:6px 0 2px">${v}</b><div style="font-size:11px;color:var(--muted)">${m}</div></div>`).join("")}</div></div>`;
      }
      function interventionCentre(){
        const s=orgStats();const per=period();const need=per.elapsed/per.working;
        const behind=liveSbus().filter(d=>(+d.attn)<need).sort((a,b)=>((+a.attn)||0)-((+b.attn)||0));
        const lowPeople=allPeople().filter(p=>p.role==="BDE"&&rAttn(p)<.8).sort((a,b)=>rAttn(a)-rAttn(b)).slice(0,6);
        const ans=[
          ["SBUs behind pace",behind.length+" of "+s.liveN,behind.length?behind.map(d=>d.name).join(" · "):"all on pace","var(--coral)"],
          ["Top performer",s.top?esc(s.top.name):"—",s.top?pct(rAttn(s.top),0)+" · "+esc(s.top.sbu):"","var(--jade)"],
          ["Staff needing support",s.below80+" people","below 80% of target","var(--violet)"],
          ["Commission to pay",kMoney(s.commNow),"owed now · "+kMoney(s.commAtTarget)+" eligible","var(--amber)"],
          ["Daily revenue required",kMoney(s.dailyReq),s.daysLeft+" working days left","var(--slate)"],
          ["Org attainment",pct(B.target>0?B.actual/B.target:0,0),"of the KES target","var(--brand)"]
        ];
        const sev=a=>a>=.8?"var(--jade)":a>=.5?"var(--amber)":"var(--coral)";
        const avPal=["var(--slate)","var(--violet)","#2f8f88","var(--brand)","var(--gold)","#4d8bd6"];
        const icItem=(nm,sub,a,av)=>{const c=sev(a);return `<div style="display:flex;align-items:center;gap:11px;padding:9px 11px;border-radius:10px;background:var(--surface2);border:1px solid var(--line)">`
          +`<span style="flex:0 0 auto;width:32px;height:32px;border-radius:9px;background:${av};color:#fff;display:grid;place-items:center;font-weight:800;font-size:11px">${esc(pInitials(nm))}</span>`
          +`<div style="flex:1;min-width:0"><div style="font-weight:700;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(nm)}</div><div style="font-size:10.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(sub)}</div><div style="height:4px;border-radius:99px;background:var(--surface3);margin-top:6px;overflow:hidden"><div style="width:${clamp(a*100,3,100)}%;height:100%;background:${c}"></div></div></div>`
          +`<b class="num" style="flex:0 0 auto;font-size:13.5px;color:${c}">${pct(a,0)}</b></div>`;};
        const sbuList=behind.length?behind.map((d,i)=>icItem(d.name,d.leader,(+d.attn)||0,avPal[i%avPal.length])).join(""):`<div style="color:var(--muted);font-size:12.5px;padding:10px 0">Every SBU is at or above required pace.</div>`;
        const pplList=lowPeople.length?lowPeople.map((p)=>icItem(p.name,p.sbu,rAttn(p),avColor(p.name))).join(""):`<div style="color:var(--muted);font-size:12.5px;padding:10px 0">No staff below 80% of target.</div>`;
        const head='display:flex;align-items:center;gap:7px;font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800;margin-bottom:10px';
        return `<div class="card"><div class="chead"><h4>Intervention Centre</h4><span class="chip coral">What needs you</span></div>
          <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">${ans.map(([l,v,sub,a])=>`<div style="background:var(--surface2);border:1px solid var(--line);border-left:3px solid ${a};border-radius:11px;padding:14px 16px"><span style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800">${l}</span><b class="num" style="display:block;font-size:19px;margin:6px 0 3px;color:var(--ink);line-height:1.15">${v}</b><div style="font-size:11px;color:var(--muted)">${sub}</div></div>`).join("")}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:18px">
            <div><div style="${head}">SBUs behind pace <span class="chip coral">${behind.length}</span></div><div style="display:flex;flex-direction:column;gap:8px">${sbuList}</div></div>
            <div><div style="${head}">Lowest-performing staff <span class="chip coral">${lowPeople.length}</span></div><div style="display:flex;flex-direction:column;gap:8px">${pplList}</div></div>
          </div></div>`;
      }
      function staffRanking(){
        const bdes=allPeople().filter(p=>p.role==="BDE").sort((a,b)=>rAttn(b)-rAttn(a));
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>#</th><th>Staff member</th><th>Department</th><th>Target</th><th>Cleared</th><th>Score</th><th>Status</th></tr></thead><tbody>${bdes.map((p,i)=>{const a=rAttn(p);const pc=paceOf(p);const rowbg=i===0?"background:var(--jade-soft)":i===bdes.length-1?"background:var(--coral-soft)":"";return `<tr style="${rowbg}"><td class="num">${i+1}</td><td><div class="prow"><span class="a" style="background:${avColor(p.name)}">${esc(pInitials(p.name))}</span><div><b><span data-scope="${p.key}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(p.name)}</span></b><span>${esc(p.title||"BDE")}</span></div></div></td><td>${esc(p.sbu)}</td><td class="num">${repTarget(p)}</td><td class="num">${repActual(p)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(pc.st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td><span class="sbadge s${pc.st[0]}"><span class="dot"></span>${pc.label}</span></td></tr>`;}).join("")}</tbody></table></div></div>`;
      }
      function productMix(){
        const cols=["#4d8bd6","var(--jade)","var(--brand)","var(--violet)","var(--gold)","var(--slate)"];
        const live=liveSbus();
        const lines=live.map((d,i)=>[d.name,(d.kes?d.actual:(d.kesActual||0)),cols[i%cols.length]]);
        const total=lines.reduce((a,l)=>a+l[1],0)||1;
        const cli=live.map((d,i)=>[d.name,(+d.clients)||0,cols[i%cols.length]]);const cliMax=Math.max(1,...cli.map(c=>c[1]));
        const subHead='font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800;margin:0 0 8px';
        const subHead2=subHead+';margin-top:18px;padding-top:16px;border-top:1px solid var(--line)';
        const g='grid-template-columns:132px 1fr auto';
        return `<div class="card"><div class="chead"><div><h4>Revenue &amp; clients by SBU</h4><p style="font-size:11.5px;color:var(--muted);margin:2px 0 0">Where the cleared revenue and paying clients came from this period</p></div></div>
          <div style="${subHead}">Cleared revenue share</div>${lines.map(([n,v,c])=>`<div class="src" style="${g}"><label>${esc(n)}</label><div class="sb"><div style="width:${v/total*100}%;background:${c}"></div></div><b>${pct(v/total,0)}</b></div>`).join("")}
          <div style="${subHead2}">Paid clients by SBU</div>${cli.map(([n,v,c])=>`<div class="src" style="${g}"><label>${esc(n)}</label><div class="sb"><div style="width:${v/cliMax*100}%;background:${c}"></div></div><b>${nf.format(v)}</b></div>`).join("")}
          <div style="font-size:11px;color:var(--muted);margin-top:12px">International contributes clients, not KES, so it's excluded from the revenue split.</div></div>`;
      }
      function learnerJourney(){
        const L=B.lms||{};const courses=L.courses||[];
        if(courses.length===0&&L.enrMonth==null) return `<div class="card"><div class="chead"><h4>Learning · eLearning platform</h4><span class="chip slate">LMS</span></div><p style="color:var(--muted);font-size:12.5px;margin:0;line-height:1.6">Live data from the eLearning platform isn't reachable right now.</p></div>`;
        const stat=(l,v,c,tip)=>`<div title="${esc(tip||"")}" style="flex:1;background:var(--surface2);border:1px solid var(--line);border-left:3px solid ${c};border-radius:10px;padding:11px 13px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:800">${l}</div><b class="num" style="display:block;font-size:20px;margin-top:3px;color:var(--ink)">${v}</b></div>`;
        const stats=`<div style="display:flex;gap:10px;margin-bottom:14px">${stat("New enrolments",nf.format((+L.enrMonth)||0),"var(--brand)","New course enrolments this month")}${stat("Certificates issued",nf.format((+L.certMonth)||0),"var(--jade)","Certificates issued this month")}${stat("Active learners",nf.format((+L.active)||0),"var(--slate)","Learners who accessed the LMS in the last 30 days")}</div>`;
        const cmax=Math.max(1,...courses.map(c=>c[1]));
        const courseBlock=courses.length?`<div style="font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800;margin:22px 0 10px;padding-top:16px;border-top:1px solid var(--line)">Courses enrolled this month</div>${courses.map(([nm,n])=>`<div class="src"><label style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(nm)}</label><div class="sb"><div style="width:${n/cmax*100}%"></div></div><b>${nf.format(n)}</b></div>`).join("")}`:`<p style="color:var(--muted);font-size:12.5px;margin:0">No new course enrolments this month.</p>`;
        return `<div class="card"><div class="chead"><div><h4>Learning · eLearning platform</h4><p style="font-size:11.5px;color:var(--muted);margin:2px 0 0">This ${esc(L.monthLabel||"month")}${L.enrolledAll!=null?" · "+nf.format(L.enrolledAll)+" enrolled all-time":""}</p></div><span class="chip slate">Live</span></div>${stats}${courseBlock}</div>`;
      }

      function vCommand(){
        const ps=pace();
        return `${strategyStrip()}
          <section class="hero">
            <div class="card"><div class="chead"><h4>Enterprise scorecard</h4><span class="pace-pill ${ps.status==="green"?"pg":ps.status==="amber"?"pa":"pr"}"><span class="dot"></span>${ps.label} · pace ${pct(ps.ratio,0)}</span></div>${kpiBlock()}</div>
            ${progressCard()}
          </section>

          <div class="section-tag"><h3>At a glance</h3><span>Headcount performance across the organization</span><div class="rule"></div></div>
          ${countsStrip()}

          <div class="section-tag"><h3>Intervention Centre — what needs you</h3><span>Plain answers: the gap, the people and the number to hit</span><div class="rule"></div></div>
          ${interventionCentre()}

          <div class="section-tag"><h3>SBU performance</h3><span>The whole picture — click any SBU to drill into that department</span><div class="rule"></div></div>
          ${teamTable()}

          <div class="section-tag"><h3>Revenue trajectory &amp; strategic accounts</h3><span>Month-end forecast and the highest-value open accounts company-wide</span><div class="rule"></div></div>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Revenue pace &amp; month-end forecast</h4><span class="chip jade">${kMoney(B.forecast)} forecast</span></div>${trendSVG()}<div style="font-size:11.5px;color:var(--muted);margin-top:10px">The forecast moves whenever stage, probability, payment date or cleared revenue changes.</div></div>
            ${execRevenueBreakdown()}
          </section>
          ${execTopDeals()}

          <div class="section-tag"><h3>Revenue mix &amp; learner journey</h3><span>Cleared revenue share by SBU, and how learners move through the LMS</span><div class="rule"></div></div>
          <section class="grid-2">
            ${productMix()}
            ${learnerJourney()}
          </section>

          <div class="section-tag"><h3>Where to intervene</h3><span>The few things that need leadership, resources or a decision — now</span><div class="rule"></div></div>
          <section class="grid-2">
            ${actionsCard()}
            ${decisionsCard()}
          </section>`;
      }

      function vPipeline(){
        const fmax=Math.max(1,B.funnel[0][1]);const smax=Math.max(1,...B.sources.map(s=>s[1]));
        const alerts=B.alerts||[];
        const sHTML=B.sources.length?B.sources.map(([n,v])=>`<div class="src"><label>${esc(n)}</label><div class="sb"><div style="width:${v/smax*100}%"></div></div><b>${nf.format(v)}</b></div>`).join(""):`<p style="color:var(--muted);font-size:12.5px;margin:0">No lead-source data across the SBUs this period.</p>`;
        const aHTML=alerts.length?alerts.map(a=>`<div class="arow"><span class="pd red"></span><div><b>${esc(a.text||((a.n||0)+" unread WhatsApp chats to reply"))}</b><p>${esc(a.name||"")}${a.sbu?" · "+esc(a.sbu):""}</p></div><span class="due">${nf.format(a.n||0)}</span></div>`).join(""):`<p style="color:var(--muted);font-size:12.5px;margin:0">No unread WhatsApp chats awaiting reply across the SBUs.</p>`;
        return `
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Acquisition &amp; conversion funnel</h4><span class="chip slate">Live · all SBUs</span></div><div class="funnel">${B.funnel.map(([l,n],i)=>`<div class="fr"><label>${esc(l)}</label><div class="fbar"><div style="width:${Math.max(9,n/fmax*100)}%">${nf.format(n)}</div></div><span class="cv">${i?Math.round(n/Math.max(1,B.funnel[i-1][1])*100)+"%":"100%"}</span></div>`).join("")}</div><div style="font-size:11px;color:var(--muted);margin-top:8px">Consolidated leads → paid clients across every SBU.</div></div>
            <div class="card"><div class="chead"><h4>Lead-source contribution</h4><span class="chip slate">Leads by source</span></div>${sHTML}</div>
          </section>
          <div class="card"><div class="chead"><h4>Unread WhatsApp chats awaiting reply</h4><span class="chip ${alerts.length?"coral":"jade"}">${alerts.length} ${alerts.length===1?"person":"people"}</span></div><div class="list">${aHTML}</div><div style="font-size:11px;color:var(--muted);margin-top:8px">Open WhatsApp conversations assigned to each rep with an unanswered message.</div></div>`;
      }

      function vReport(){
        const p=period();
        const sbusGreen=B.sbus.filter(d=>d.actual>=d.target*(p.elapsed/p.working)).length;
        const fields=[
          ["Organization daily revenue target","number",Math.round(B.target/p.working)],
          ["Actual cleared revenue today","number",Math.round(B.actual/p.elapsed)],
          ["SBUs at 80%+ pace","number",sbusGreen],
          ["Strategic-account meetings","number",4],
          ["BDM personal revenue MTD","number",B.personalActual],
          ["Consolidated qualified pipeline","number",B.pipeline],
          ["Proposals / tenders at risk","number",3],
          ["Collections requiring escalation","number",7],
          ["SBU performance summary","textarea",B.sbus.map(d=>`${d.name}: ${kMoney(d.actual)} / ${kMoney(d.target)}; forecast ${kMoney(d.forecast)}`).join("\n")],
          ["Strategic accounts and blocked deals","textarea","Account, value, stage, owner, blocker, executive action and next date."],
          ["HOD coaching / recovery decisions","textarea","Named HOD, issue, action, deadline and review point."],
          ["CEO decisions required","textarea","Budget, pricing, executive access, technology, legal, payment or capacity decision."]
        ];
        const fieldHTML=f=>`<div class="field ${f[1]==="textarea"?"span2":""}"><label>${esc(f[0])}</label>${f[1]==="textarea"?`<textarea data-label="${esc(f[0])}">${esc(f[2])}</textarea>`:`<input data-label="${esc(f[0])}" type="number" value="${esc(f[2])}">`}</div>`;
        const nums=fields.filter(f=>f[1]==="number").map(fieldHTML).join("");
        const texts=fields.filter(f=>f[1]==="textarea").map(fieldHTML).join("");
        return `
          <div class="card"><div class="chead"><h4>BDM consolidated commercial report</h4><span class="chip jade">Auto-prefilled</span></div>
            <div id="reportForm">
              <div class="form-sub">Today's numbers <i>· auto-prefilled</i></div>
              <div class="form-grid">${nums}</div>
              <div class="form-sub" style="margin-top:18px">Your narrative <i>· the human judgement</i></div>
              <div class="form-grid">${texts}</div>
            </div>
            <div class="report-actions"><button class="tbtn solid" id="genReport" type="button">Generate report summary</button><button class="tbtn" id="dlReport" type="button">Download</button><button class="tbtn" id="clrReport" type="button">Clear narrative</button></div>
          </div>
          <div class="card"><div class="chead"><h4>Generated management summary</h4><span class="chip jade">Evidence-linked</span></div><div id="reportPreview" class="report-preview">Select "Generate report summary" to compile the dashboard data and your explanations.</div></div>
          <section class="grid-3">
            ${[["Automatic evidence","Revenue, payments, activity logs, opportunities, meetings, proposals and CRM completeness are system-calculated."],["Required human judgement","You explain why performance moved, what is blocked, what was learned and which support or decision is required."],["Manager workflow","Your supervisor reviews, comments, approves or returns the report and converts commitments into tracked actions."]].map(([a,b])=>`<div class="card"><h4>${esc(a)}</h4><p style="font-size:12.5px;color:var(--muted);margin:8px 0 0;line-height:1.5">${esc(b)}</p></div>`).join("")}
          </section>`;
      }

      function vStrategy(){
        return `
          <div class="card"><div class="chead"><h4>Role mandate</h4><span class="chip jade">Commercial command</span></div><div class="motiv green"><b>${esc(B.mandate)}</b><br>${esc(B.mandateText)}</div></div>
          <div class="card"><div class="chead"><h4>Non-negotiable operating principles</h4></div><div class="principles">${B.principles.map(([a,b])=>`<div class="principle"><b>${esc(a)}</b><p>${esc(b)}</p></div>`).join("")}</div></div>
          <div class="card"><div class="chead"><h4>Daily operating rhythm</h4></div><div class="timeline">${B.dailyRhythm.map(([t,x])=>`<div class="time-row"><time>${esc(t)}</time><div>${esc(x)}</div></div>`).join("")}</div></div>
          <section class="grid-3">
            <div class="card"><div class="chead"><h4>Green response</h4><span class="chip jade">At / above pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Protect quality, collections and client experience; pursue stretch opportunities and share winning practices.</p></div>
            <div class="card"><div class="chead"><h4>Amber response</h4><span class="chip amber">Near pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Agree corrective action within 24 hours, intensify senior support and concentrate on the nearest commercial next steps.</p></div>
            <div class="card"><div class="chead"><h4>Red response</h4><span class="chip coral">Below pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Create a quantified recovery plan, monitor daily and escalate decisions or resources before the gap becomes irreversible.</p></div>
          </section>`;
      }

      /* ---------- people model + drill-down (CEO can open anyone) ---------- */
      const pInitials=n=>String(n||"").trim().split(/\s+/).map(x=>x[0]).slice(0,2).join("").toUpperCase();
      function allPeople(){
        const list=[{key:"bdm",role:"BDM",name:B.bdmName,ini:B.bdmInitials,sbu:"All SBUs",target:B.target,actual:B.actual,pipeline:B.pipeline,collection:B.collection,forecast:B.forecast,kes:true}];
        B.sbus.forEach((s,si)=>{
          if(s.placeholder)return;
          list.push({key:"bdo-"+si,role:"BDO",name:s.leader,ini:pInitials(s.leader),sbu:s.name,target:s.target,actual:s.actual,pipeline:s.pipeline,collection:s.collection,forecast:s.forecast,sbuIndex:si,kes:s.kes});
          (s.reps||[]).forEach((r,ri)=>list.push({key:"bde-"+si+"-"+ri,role:"BDE",name:r.name,ini:pInitials(r.name),sbu:s.name,title:r.title,target:r.target,actual:r.actual,pipeline:r.pipeline,collection:r.collection,units:r.clients,kes:r.kes}));
        });
        return list;
      }
      function personByKey(k){return allPeople().find(p=>p.key===k)||null;}
      function paceOf(p){const per=period();const exp=p.target*(per.elapsed/per.working);const ratio=exp?p.actual/exp:0;const st=p.actual>=exp?"green":p.actual>=exp*.85?"amber":"red";return {ratio,st,label:st==="green"?"On pace":st==="amber"?"At risk":"Behind pace"};}

      function vPeople(){
        const people=allPeople();
        const leaders=people.filter(p=>p.role==="BDO").sort((a,b)=>rAttn(b)-rAttn(a));
        const lead=leaders.map((p,i)=>{const a=rAttn(p);const pc=paceOf(p);return `<tr><td class="num">${i+1}</td><td><div class="prow"><span class="a" data-scope="${p.key}" style="cursor:pointer">${esc(p.ini)}</span><div><b><span data-scope="${p.key}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(p.name)}</span></b><span>${esc(p.sbu)}</span></div></div></td><td><span class="chip slate">${p.role}</span></td><td class="num">${repTarget(p)}</td><td class="num">${repActual(p)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(pc.st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td class="num">${kMoney(p.pipeline)}</td><td><span class="sbadge s${pc.st[0]}"><span class="dot"></span>${pc.label}</span></td></tr>`;}).join("");
        return `
          <div class="section-tag"><h3>Leadership scorecard</h3><span>Department heads (BDOs), ranked by attainment — click anyone to open their view</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>#</th><th>Person</th><th>Role</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Pipeline</th><th>Status</th></tr></thead><tbody>${lead}</tbody></table></div></div>
          <div class="section-tag"><h3>Org-wide staff ranking</h3><span>Every executive scored — click a name to open their view; top and bottom highlighted</span><div class="rule"></div></div>
          ${staffRanking()}`;
      }

      function vPerson(p){
        const a=rAttn(p);const pc=paceOf(p);
        const kpis=[
          ["Target",repTarget(p),"Approved target","var(--slate)"],
          ["Cleared revenue",repActual(p),pct(a)+" of target","var(--jade)"],
          ["Qualified pipeline",kMoney(p.pipeline),(p.pipeline/p.target).toFixed(1)+"× coverage","var(--slate)"],
          ["Collection",pct(p.collection,0),"cleared vs invoiced","var(--brand)"]
        ];
        if(p.role==="BDE"&&p.units!=null)kpis.push(["Volume",nf.format(p.units),"units this period","var(--slate)"]);
        else kpis.push(["Month-end forecast",kMoney(p.forecast||p.actual),"projected","var(--gold)"]);
        const kpiRow=`<div class="kpis">${kpis.map(([l,v,m,ac])=>`<div class="kpi" style="--acc:${ac}"><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div></div>`).join("")}</div>`;
        let extra="";
        if(p.role==="BDM"){extra=`<div class="section-tag"><h3>SBU performance</h3><span>Consolidated across all departments</span><div class="rule"></div></div>${teamTable()}`;}
        else if(p.role==="BDO"&&p.sbuIndex!=null){const reps=(B.sbus[p.sbuIndex].reps||[]);const rows=reps.map((r,ri)=>{const ra=rAttn(r);const rp=paceOf(r);return `<tr><td><div class="prow"><span class="a" style="background:${avColor(r.name)}">${esc(pInitials(r.name))}</span><div><b><span data-scope="bde-${p.sbuIndex}-${ri}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(r.name)}</span></b><span>${esc(r.title||"BDE")}</span></div></div></td><td class="num">${repTarget(r)}</td><td class="num">${repActual(r)}</td><td><span class="mini-track"><div style="width:${clamp(ra*100,0,100)}%;background:${scol(rp.st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(ra,0)}</b></td><td class="num">${kMoney(r.pipeline)}</td><td><span class="sbadge s${rp.st[0]}"><span class="dot"></span>${rp.label}</span></td></tr>`;}).join("");extra=`<div class="section-tag"><h3>${esc(p.sbu)} team</h3><span>Executives reporting to ${esc(p.name)} — click to drill in</span><div class="rule"></div></div><div class="card tight"><div class="table-wrap"><table><thead><tr><th>Executive</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Pipeline</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;}
        else{extra=`<div class="card"><div class="chead"><h4>Notes</h4><span class="chip slate">Prototype</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Individual field activity, deals and daily reports appear here once live data is wired. For now this shows ${esc(p.name)}'s headline numbers scoped from the organization dataset.</p></div>`;}
        return `
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
            <button class="tbtn" data-scope="org" type="button">← Back to organization</button>
            <div class="prow"><span class="a" style="background:linear-gradient(150deg,var(--brand),var(--gold))">${esc(p.ini)}</span><div><b>${esc(p.name)}</b><span>${p.role} · ${esc(p.sbu)}</span></div></div>
            <span class="pace-pill ${pc.st==="green"?"pg":pc.st==="amber"?"pa":"pr"}" style="margin-left:auto"><span class="dot"></span>${pc.label} · pace ${pct(pc.ratio,0)}</span>
          </div>
          ${kpiRow}
          ${extra}`;
      }

      /* ---------- role view: CEO opens any role's full dashboard (like the prototype) ---------- */
      function applyScope(key){
        if(key==="org"||key==="ceo"){state.role="ceo";state.view="command";}
        else if(key==="bdm"){state.role="bdm";}
        else if(key.indexOf("bdo-")===0){state.role="bdo";state.dept=+key.split("-")[1]||0;}
        else if(key.indexOf("bde-")===0){const a=key.split("-");state.role="bde";state.dept=+a[1]||0;state.emp=+a[2]||0;}
      }
      function roleBanner(ini,name,sub,pc){
        return `<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
          <button class="tbtn" data-scope="org" type="button">← Back to CEO view</button>
          <div class="prow"><span class="a" style="background:linear-gradient(150deg,var(--brand),var(--gold))">${esc(ini)}</span><div><b>${esc(name)}</b><span>${esc(sub)}</span></div></div>
          <span class="pace-pill ${pc.st==="green"?"pg":pc.st==="amber"?"pa":"pr"}" style="margin-left:auto"><span class="dot"></span>${pc.label} · pace ${pct(pc.ratio,0)}</span>
        </div>`;
      }
      function kpiRow(items){return `<div class="kpis">${items.map(([l,v,m,a])=>`<div class="kpi" style="--acc:${a}"><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div></div>`).join("")}</div>`;}
      function repsTable(d,si){
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>Executive</th><th>Target</th><th>Cleared</th><th>Attainment</th><th>Pipeline</th><th>Collection</th><th>Status</th></tr></thead><tbody>${(d.reps||[]).map((r,ri)=>{const a=rAttn(r);const pc=paceOf(r);return `<tr><td><div class="prow"><span class="a" style="background:${avColor(r.name)}">${esc(pInitials(r.name))}</span><div><b><span data-scope="bde-${si}-${ri}" style="cursor:pointer;text-decoration:underline;text-underline-offset:2px">${esc(r.name)}</span></b><span>${esc(r.title||"BDE")}</span></div></div></td><td class="num">${repTarget(r)}</td><td class="num">${repActual(r)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(pc.st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td class="num">${kMoney(r.pipeline)}</td><td class="num">${pct(r.collection,0)}</td><td><span class="sbadge s${pc.st[0]}"><span class="dot"></span>${pc.label}</span></td></tr>`;}).join("")}</tbody></table></div></div>`;
      }
      function viewBDM(){
        const ps=pace();const att=B.target>0?B.actual/B.target:0;const c=commission();const live=liveSbus();const sbus80=live.filter(d=>(+d.attn)>=.8).length;
        const kpis=[
          ["Organization target (KES SBUs)",kMoney(B.target),live.filter(d=>d.kes).length+" revenue SBUs","var(--slate)"],
          ["Cleared revenue",kMoney(B.actual),pct(att)+" attainment","var(--jade)"],
          ["Month-end forecast",kMoney(B.forecast),(B.target>0?pct(B.forecast/B.target):"0%")+" projected","var(--slate)"],
          ["BDM personal sales",kMoney(B.personalActual),(B.personalTarget>0?pct(B.personalActual/B.personalTarget)+" of "+kMoney(B.personalTarget):"no personal target"),"var(--slate)"],
          ["SBUs at 80%+",sbus80+" / "+live.length,"Balanced-SBU gate","var(--gold)"],
          ["Commission estimate",kMoney(c.current),"Personal + leadership","var(--amber)"]
        ];
        return roleBanner(B.bdmInitials,B.bdmName,"BDM · All SBUs",{st:ps.status,label:ps.label,ratio:ps.ratio})
          +kpiRow(kpis)
          +`<div class="section-tag"><h3>SBU performance</h3><span>Consolidated across all departments — click a SBU to drill in</span><div class="rule"></div></div>${teamTable()}${execRevenueBreakdown()}`
          +`<div class="section-tag"><h3>Top strategic deals</h3><span>Highest-value open deals company-wide</span><div class="rule"></div></div>${execTopDeals()}`
          +`<div class="section-tag"><h3>Revenue pace &amp; today's execution</h3><span>Month-end trajectory and the interventions in play</span><div class="rule"></div></div><section class="grid-2"><div class="card"><div class="chead"><h4>Revenue pace &amp; forecast</h4><span class="chip jade">${kMoney(B.forecast)} forecast</span></div>${trendSVG()}</div>${actionsCard()}</section>`;
      }
      function viewBDO(){
        const si=state.dept;const d=B.sbus[si]||B.sbus[0];
        if(d.placeholder){return roleBanner(pInitials(d.name),d.name,"SBU not yet configured",{st:"amber",label:"Placeholder",ratio:0})+`<div class="card"><p style="color:var(--muted);font-size:12.5px;margin:0">${esc(d.name)} isn't set up in the CRM yet — no BDO, targets or reps to show.</p></div>`;}
        const pc=paceOf(d);const att=(+d.attn)||0;
        const reps=d.reps||[];const team80=reps.filter(r=>rAttn(r)>=.8).length;
        const kpis=[
          ["Department target",sbuTarget(d),"Approved SBU target","var(--slate)"],
          ["Cleared "+(d.kes?"revenue":"clients"),sbuActual(d),pct(att)+" attainment","var(--jade)"],
          ["Month-end forecast",d.kes?kMoney(d.forecast):nf.format(Math.round(d.forecast))+" clients",(d.target>0?pct(d.forecast/d.target):"0%")+" projected","var(--slate)"],
          ["Qualified pipeline",kMoney(d.pipeline),"expected · uncollected","var(--slate)"],
          ["Team at 80%+",team80+" / "+reps.length,"Balanced performance","var(--gold)"],
          ["Collection",pct(d.collection,0),"Finance-cleared","var(--brand)"]
        ];
        return roleBanner(pInitials(d.leader),d.leader,"BDO · "+d.name+" department",pc)
          +kpiRow(kpis)
          +`<div class="section-tag"><h3>Team performance</h3><span>Executives in ${esc(d.name)} — click to open a person</span><div class="rule"></div></div>${repsTable(d,si)}`
          +`<div class="section-tag"><h3>Where to focus today</h3><span>Department interventions</span><div class="rule"></div></div>${actionsCard()}`;
      }
      function viewBDE(){
        const d=B.sbus[state.dept]||B.sbus[0];const reps=d.reps||[];
        if(!reps.length){return roleBanner("—",d.leader||d.name,"No individuals in "+d.name,{st:"amber",label:"None",ratio:0})+`<div class="card"><p style="color:var(--muted);font-size:12.5px;margin:0">No individual executives are resolved for ${esc(d.name)} yet — pick another department, or view the BDO.</p></div>`;}
        const r=reps[state.emp]||reps[0];const pc=paceOf(r);const att=rAttn(r);const per=period();const daysLeft=Math.max(0,per.working-per.elapsed);
        const kpis=[
          ["Monthly target",repTarget(r),"Approved personal target","var(--slate)"],
          ["Cleared revenue",repActual(r),pct(att)+" of target","var(--jade)"],
          ["Volume achieved",nf.format(r.units||0),"units this period","var(--slate)"],
          ["Qualified pipeline",kMoney(r.pipeline),(r.pipeline/r.target).toFixed(1)+"× coverage","var(--slate)"],
          ["Collection",pct(r.collection,0),"cleared vs invoiced","var(--brand)"],
          ["Daily pace needed",kMoney(daysLeft?Math.max(0,(r.target-r.actual)/daysLeft):0),daysLeft+" working days left","var(--amber)"]
        ];
        return roleBanner(pInitials(r.name),r.name,(r.title||"BDE")+" · "+d.name,pc)
          +kpiRow(kpis)
          +`<div class="section-tag"><h3>Today's priorities</h3><span>The nearest commercial next steps</span><div class="rule"></div></div>${actionsCard()}`
          +`<div class="card" style="margin-top:2px"><div class="chead"><h4>Notes</h4><span class="chip slate">Prototype</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Field activity, deals and the daily report for ${esc(r.name)} appear here once live data is wired. Figures shown are scoped from the organization dataset.</p></div>`;
      }
      function roleView(){return state.role==="bdm"?viewBDM():state.role==="bdo"?viewBDO():viewBDE();}

      function populateDept(){if((B.sbus[state.dept]||{}).placeholder){const f=B.sbus.findIndex(d=>!d.placeholder);state.dept=f<0?0:f;}el("deptSelect").innerHTML=B.sbus.map((d,i)=>d.placeholder?"":`<option value="${i}" ${i===state.dept?"selected":""}>${esc(d.name)}</option>`).join("");}
      function populateEmp(){const reps=(B.sbus[state.dept]||B.sbus[0]||{}).reps||[];if(state.emp>=reps.length)state.emp=0;el("empSelect").innerHTML=reps.length?reps.map((r,i)=>`<option value="${i}" ${i===state.emp?"selected":""}>${esc(r.name)}</option>`).join(""):'<option value="0">— no individuals —</option>';}
      function syncControls(){
        el("roleSelect").value=state.role;
        el("deptControl").style.display=(state.role==="bdo"||state.role==="bde")?"":"none";
        el("empControl").style.display=(state.role==="bde")?"":"none";
        el("tabNav").style.display=(state.role==="ceo")?"":"none";
        if(state.role==="bdo"||state.role==="bde")populateDept();
        if(state.role==="bde")populateEmp();
        root.querySelectorAll("#tabNav .tab").forEach(t=>t.classList.toggle("active",t.dataset.v===state.view));
      }
      /* ---------- back-office tabs: native analytics (HR / Finance / Admin) ---------- */
      function hrDonut(segments,centerTop,centerBot){
        const total=segments.reduce((a,s)=>a+s[1],0)||1;
        const cx=68,cy=68,R=52,sw=20,C=2*Math.PI*R;let off=0;
        const arcs=segments.map(s=>{const dash=s[1]/total*C;const el=`<circle cx="${cx}" cy="${cy}" r="${R}" fill="none" stroke="${s[2]}" stroke-width="${sw}" stroke-dasharray="${dash.toFixed(2)} ${(C-dash).toFixed(2)}" stroke-dashoffset="${(-off).toFixed(2)}" transform="rotate(-90 ${cx} ${cy})" stroke-linecap="butt"/>`;off+=dash;return el;}).join("");
        return `<svg viewBox="0 0 136 136" style="width:132px;height:132px;display:block">${arcs||`<circle cx="${cx}" cy="${cy}" r="${R}" fill="none" stroke="var(--surface3)" stroke-width="${sw}"/>`}<text x="${cx}" y="${cy-1}" text-anchor="middle" style="font-size:32px;font-weight:850;fill:var(--ink)">${centerTop}</text><text x="${cx}" y="${cy+18}" text-anchor="middle" style="font-size:9.5px;fill:var(--muted);text-transform:uppercase;letter-spacing:.1em;font-weight:800">${centerBot}</text></svg>`;
      }
      function vHR(){
        const totalActive=HR.stats.active||HR.staff.length||0;
        const palette=["var(--brand)","var(--jade)","var(--slate)","var(--violet)","var(--gold)","#2f8f88","var(--coral)","#4d8bd6"];
        const segs=HR.by_dept.map((d,i)=>[d.name,d.count,palette[i%palette.length]]);
        const legend=HR.by_dept.length?HR.by_dept.map((d,i)=>`<div style="display:flex;align-items:center;gap:9px;padding:4px 0;border-bottom:1px solid var(--line)"><span style="width:11px;height:11px;border-radius:3px;background:${palette[i%palette.length]};flex:0 0 auto"></span><span style="flex:1;font-size:12.5px">${esc(d.name)}</span><b class="num" style="font-size:13px">${nf.format(d.count)}</b></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:0">No data.</p>';
        const deptCard=`<div class="card"><div class="chead"><h4>Staff by department</h4><span class="chip slate">Live</span></div><div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap"><div style="flex:0 0 auto;margin:0 auto">${hrDonut(segs,nf.format(totalActive),"active staff")}</div><div style="flex:1;min-width:180px">${legend}</div></div></div>`;
        const clk=HR.clockins||[];const lateN=clk.filter(c=>c.late).length;const onTime=Math.max(0,clk.length-lateN);const otPct=clk.length?Math.round(onTime/clk.length*100):0;
        const attCard=`<div class="card" style="cursor:pointer" data-modal="clockins"><div class="chead"><h4>Attendance today</h4><span class="chip" style="background:var(--brand);color:#fff;cursor:pointer">View list →</span></div><div class="mini3"><div class="cm"><span>Present</span><b class="num">${nf.format(HR.att_present||0)}</b></div><div class="cm"><span>On time</span><b class="num" style="color:var(--jade)">${nf.format(onTime)}</b></div><div class="cm"><span>Late</span><b class="num" style="color:${lateN?"var(--coral)":"var(--ink)"}">${nf.format(lateN)}</b></div></div><div style="margin-top:16px"><div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:6px"><span>On-time rate</span><b style="color:var(--ink)">${otPct}%</b></div><div style="height:11px;border-radius:99px;background:var(--surface3);overflow:hidden;display:flex">${clk.length?`<div style="width:${otPct}%;background:var(--jade)"></div><div style="flex:1;background:var(--coral)"></div>`:''}</div></div><div style="font-size:11px;color:var(--muted);margin-top:12px">${nf.format(HR.att_punches||0)} total punches today · click for the full list.</div></div>`;
        const p=HR.payroll;const ded=p?Math.max(0,p.gross-p.net):0;const netPct=p&&p.gross?Math.round(p.net/p.gross*100):0;
        const payCard=`<div class="card" ${p?'style="cursor:pointer" data-modal="payslips"':''}><div class="chead"><h4>Payroll — latest period</h4>${p?'<span class="chip" style="background:var(--brand);color:#fff;cursor:pointer">View payslips →</span>':''}</div>`+(p?`<div class="mini3"><div class="cm"><span>Gross</span><b class="num">${kMoney(p.gross)}</b></div><div class="cm"><span>Net</span><b class="num" style="color:var(--jade)">${kMoney(p.net)}</b></div><div class="cm"><span>Employees</span><b class="num">${nf.format(p.employees)}</b></div></div><div style="margin-top:16px"><div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:6px"><span>Net take-home</span><b style="color:var(--ink)">${netPct}% · ${kMoney(ded)} deductions</b></div><div style="height:11px;border-radius:99px;background:var(--surface3);overflow:hidden;display:flex"><div style="width:${netPct}%;background:var(--jade)"></div><div style="flex:1;background:var(--amber)"></div></div></div>`:'<p style="color:var(--muted);font-size:12.5px;margin:0">No payroll period yet.</p>')+`</div>`;
        const statusChip=st=>{const m={active:"jade",approved:"jade",pending:"amber",under_review:"slate",suspended:"coral",terminated:"coral",rejected:"coral"};return `<span class="chip ${m[st]||"slate"}">${esc(String(st).replace(/_/g," "))}</span>`;};
        const rows=HR.staff.length?HR.staff.map(pp=>`<tr><td><b>${esc(pp.staff_id)}</b></td><td>${esc(pp.name)}<div style="font-size:11px;color:var(--muted)">${esc(pp.title||"—")}</div></td><td>${esc(pp.email)}<div style="font-size:11px;color:var(--muted)">${esc(pp.phone)}</div></td><td>${esc(pp.dept||"—")}</td><td>${statusChip(pp.status)}</td><td class="num">${esc(pp.created)}</td></tr>`).join(""):'<tr><td colspan="6" style="text-align:center;color:var(--muted)">No staff found.</td></tr>';
        return `
          <div class="section-tag"><h3>Human Resources</h3><span>${nf.format(totalActive)} active staff · attendance and payroll, live</span><div class="rule"></div></div>
          <section class="grid-3">${deptCard}${attCard}${payCard}</section>
          <div class="section-tag"><h3>Active staff</h3><span>${nf.format(HR.staff.length)} people · newest first</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>Staff ID</th><th>Name</th><th>Contact</th><th>Department</th><th>Status</th><th>Submitted</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
      }
      const finPalette=["var(--brand)","var(--jade)","var(--slate)","var(--violet)","var(--gold)","#2f8f88","var(--coral)","#4d8bd6"];
      const MON=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
      // money helpers — all stored USD; toggle multiplies by rate for KES
      function fcur(){return state.finCur==="KES"?FIN.rate:1;}
      function fsym(){return state.finCur==="KES"?"KSh ":"$";}
      function fmoney(usd){const v=(usd||0)*fcur();const a=Math.abs(v);if(a>=1e6)return fsym()+(v/1e6).toFixed(2).replace(/\.00$/,"")+"M";if(a>=1e3)return fsym()+Math.round(v/1e3)+"K";return fsym()+nf.format(Math.round(v));}
      function fmoneyFull(usd){const v=(usd||0)*fcur();return fsym()+v.toLocaleString("en-US",{minimumFractionDigits:2,maximumFractionDigits:2});}
      function fbar(label,usd,maxUsd,color){return `<div><div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px"><span style="color:var(--muted)">${label}</span><b>${fmoney(usd)}</b></div><div style="height:9px;border-radius:99px;background:var(--surface3)"><div style="width:${clamp(maxUsd?usd/maxUsd*100:0,0,100)}%;height:100%;border-radius:99px;background:${color}"></div></div></div>`;}
      const pctOf=(a,b)=>b>0?Math.round(a/b*100):0;
      // aggregate the revenue series for the selected year
      function revAgg(){
        const yr=state.finYear;
        const ms=FIN.rev.months.filter(m=>yr==="all"||String(m.y)===String(yr));
        let v=0,i=0,c=0,vexp=0,iexp=0,vn=0,ic=0;
        ms.forEach(m=>{v+=m.v;i+=m.i;c+=m.c;vexp+=m.vexp;iexp+=m.iexp;vn+=m.vn;ic+=m.in;});
        return {months:ms,v:v,i:i,c:c,vexp:vexp,iexp:iexp,vn:vn,ic:ic,total:v+i+c,coll:v+i,exp:vexp+iexp};
      }
      function revTop(list,key){
        const yr=state.finYear,agg={};
        list.filter(x=>yr==="all"||String(x.y)===String(yr)).forEach(x=>{const k=x[key];if(!agg[k])agg[k]={name:k,rev:0,n:0};agg[k].rev+=x.rev;agg[k].n+=x.n;});
        return Object.values(agg).sort((a,b)=>b.rev-a.rev).slice(0,5);
      }
      // multi-series area+line SVG (Virtual vs International), currency-aware
      function revTrendSVG(months){
        if(!months.length)return '<p style="color:var(--muted);font-size:12.5px;margin:16px 4px">No revenue recorded in this period.</p>';
        const W=980,H=300,padL=66,padR=18,padT=16,padB=44,iw=W-padL-padR,ih=H-padT-padB,mul=fcur();
        const vs=months.map(m=>m.v*mul),is=months.map(m=>m.i*mul),n=months.length;
        const rawMax=Math.max(1,...vs,...is);
        const niceMax=(function(x){const p=Math.pow(10,Math.floor(Math.log10(x)));const u=x/p;const f=u<=1?1:u<=2?2:u<=5?5:10;return f*p;})(rawMax);
        const xAt=idx=>padL+(n===1?iw/2:iw*idx/(n-1)),yAt=val=>padT+ih-(val/niceMax)*ih;
        const short=v=>{const a=Math.abs(v);if(a>=1e6)return fsym()+(v/1e6).toFixed(1).replace(/\.0$/,"")+"M";if(a>=1e3)return fsym()+Math.round(v/1e3)+"K";return fsym()+Math.round(v);};
        const path=(arr,close)=>{let d=arr.map((v,idx)=>(idx?"L":"M")+xAt(idx).toFixed(1)+" "+yAt(v).toFixed(1)).join(" ");if(close)d+=" L"+xAt(n-1).toFixed(1)+" "+(padT+ih)+" L"+xAt(0).toFixed(1)+" "+(padT+ih)+" Z";return d;};
        let grid="";for(let g=0;g<=4;g++){const yy=padT+ih*g/4,val=niceMax*(1-g/4);grid+=`<line x1="${padL}" y1="${yy.toFixed(1)}" x2="${W-padR}" y2="${yy.toFixed(1)}" stroke="var(--line)" stroke-width="1"/><text x="${padL-8}" y="${(yy+4).toFixed(1)}" text-anchor="end" style="font-size:10px;fill:var(--muted)">${short(val)}</text>`;}
        const step=Math.max(1,Math.ceil(n/12));let xl="";months.forEach((m,idx)=>{if(idx%step===0||idx===n-1){const lab=MON[m.m-1]+(state.finYear==="all"?" '"+String(m.y).slice(2):"");xl+=`<text x="${xAt(idx).toFixed(1)}" y="${H-padB+18}" text-anchor="middle" style="font-size:10px;fill:var(--muted)">${lab}</text>`;}});
        const dots=(arr,color)=>arr.map((v,idx)=>`<circle cx="${xAt(idx).toFixed(1)}" cy="${yAt(v).toFixed(1)}" r="2.6" fill="${color}"/>`).join("");
        return `<svg viewBox="0 0 ${W} ${H}" style="width:100%;height:auto;display:block" preserveAspectRatio="xMidYMid meet">${grid}
          <path d="${path(is,true)}" fill="var(--brand)" opacity="0.10"/><path d="${path(vs,true)}" fill="var(--jade)" opacity="0.12"/>
          <path d="${path(is,false)}" fill="none" stroke="var(--brand)" stroke-width="2.5"/><path d="${path(vs,false)}" fill="none" stroke="var(--jade)" stroke-width="2.5"/>
          ${dots(is,"var(--brand)")}${dots(vs,"var(--jade)")}${xl}</svg>`;
      }
      function vFinance(){
        const F=FIN,exp=F.expenses,fee=F.fees,rem=F.remit,com=F.commission,pay=F.payroll,st=F.statutory,dis=F.disburse;
        const r=revAgg();
        const yrLabel=state.finYear==="all"?"All time":state.finYear;
        // ---- controls ----
        const yopts=['<option value="all"'+(state.finYear==="all"?" selected":"")+'>All years</option>'].concat(F.years.map(y=>`<option value="${y}"${String(state.finYear)===String(y)?" selected":""}>${y}</option>`)).join("");
        const controls=`<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:6px">
          <div class="section-tag" style="margin:0;flex:1;min-width:240px"><h3>Financial dashboard</h3><span>Revenue, collection, cost and obligations — ${esc(yrLabel)}</span></div>
          <div style="display:flex;gap:10px;align-items:center">
            <div class="curtoggle"><button data-fincur="USD" class="${state.finCur==="USD"?"on":""}">USD $</button><button data-fincur="KES" class="${state.finCur==="KES"?"on":""}">KES</button></div>
            <select id="finYear" class="finsel">${yopts}</select>
          </div></div><div class="rule" style="margin:0 0 4px"></div>`;
        // ---- KPI row ----
        const kIco={rev:'<svg viewBox="0 0 24 24"><path d="M3 7l3-3h12l3 3v12H3z"/><path d="M3 7h18"/><path d="M15 12h3"/></svg>',virt:'<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="12" rx="1"/><path d="M8 20h8M12 16v4"/></svg>',intl:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.7 2.6 15.3 0 18M12 3c-2.6 2.7-2.6 15.3 0 18"/></svg>',txn:'<svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>'};
        const kpi=(l,v,m,a,ic)=>`<div class="kpi" style="--acc:${a}"><span class="kicon" style="color:${a};background:var(--surface3)">${ic}</span><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div></div>`;
        const kpiRowRev=`<div class="kpis4">
          ${kpi("Total Revenue",fmoneyFull(r.total),esc(yrLabel),"var(--brand)",kIco.rev)}
          ${kpi("Virtual (Courses)",fmoneyFull(r.v),pctOf(r.v,r.total)+"% of total","var(--jade)",kIco.virt)}
          ${kpi("International (Events)",fmoneyFull(r.i),pctOf(r.i,r.total)+"% of total","var(--slate)",kIco.intl)}
          ${kpi("Total Transactions",nf.format(r.vn+r.ic),"V: "+nf.format(r.vn)+" &nbsp;|&nbsp; I: "+nf.format(r.ic),"var(--gold)",kIco.txn)}
        </div>`;
        // ---- distribution donut + monthly trend ----
        const dsegs=[["Virtual (Courses)",r.v,"var(--jade)"],["International (Events)",r.i,"var(--brand)"]].concat(r.c>0?[["Custom income",r.c,"var(--slate)"]]:[]).filter(s=>s[1]>0);
        const dlegend=(r.total>0?[["Virtual (Courses)",r.v,"var(--jade)"],["International (Events)",r.i,"var(--brand)"]].concat(r.c>0?[["Custom income",r.c,"var(--slate)"]]:[]):[]).map(s=>`<div style="display:flex;align-items:center;gap:9px;padding:5px 0;border-bottom:1px solid var(--line)"><span style="width:11px;height:11px;border-radius:3px;background:${s[2]};flex:0 0 auto"></span><span style="flex:1;font-size:12.5px">${s[0]}</span><b class="num" style="font-size:12.5px">${fmoney(s[1])}</b><span style="font-size:11px;color:var(--muted);width:38px;text-align:right">${pctOf(s[1],r.total)}%</span></div>`).join("")||'<p style="color:var(--muted);font-size:12.5px;margin:0">No revenue.</p>';
        const distCard=`<div class="card"><div class="chead"><h4>Revenue distribution</h4><span class="chip slate">${esc(yrLabel)}</span></div><div style="display:flex;flex-direction:column;align-items:center;gap:14px"><div>${hrDonut(dsegs,fmoney(r.total).replace(fsym(),""),(state.finCur==="KES"?"KSh":"USD")+" total")}</div><div style="width:100%">${dlegend}</div></div></div>`;
        const trendCard=`<div class="card"><div class="chead"><h4>Monthly revenue trend</h4><div style="display:flex;gap:14px;font-size:11px"><span style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:3px;background:var(--jade);border-radius:2px"></span>Virtual</span><span style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:3px;background:var(--brand);border-radius:2px"></span>International</span></div></div>${revTrendSVG(r.months)}</div>`;
        // ---- top courses / events ----
        const tc=revTop(F.rev.courses,"name"),te=revTop(F.rev.events,"loc");
        const courseRows=tc.length?tc.map(c=>`<tr><td><b>${esc(c.name)}</b></td><td><span class="chip slate">${nf.format(c.n)}</span></td><td class="num">${fmoney(c.rev)}</td></tr>`).join(""):'<tr><td colspan="3" style="text-align:center;color:var(--muted)">No virtual course revenue.</td></tr>';
        const eventRows=te.length?te.map(e=>`<tr><td><b>${esc(e.name)}</b></td><td><span class="chip slate">${nf.format(e.n)}</span></td><td class="num">${fmoney(e.rev)}</td></tr>`).join(""):'<tr><td colspan="3" style="text-align:center;color:var(--muted)">No international event revenue.</td></tr>';
        const topCourses=`<div class="card tight"><div class="chead" style="padding:14px 16px 0"><h4>Top virtual courses</h4><span class="chip slate">Top 5</span></div><div class="table-wrap"><table><thead><tr><th>Course</th><th>Txns</th><th>Revenue</th></tr></thead><tbody>${courseRows}</tbody></table></div></div>`;
        const topEvents=`<div class="card tight"><div class="chead" style="padding:14px 16px 0"><h4>Top international events</h4><span class="chip slate">Top 5</span></div><div class="table-wrap"><table><thead><tr><th>Location</th><th>Txns</th><th>Revenue</th></tr></thead><tbody>${eventRows}</tbody></table></div></div>`;
        // ---- collection (income.php) ----
        const collRate=r.exp>0?Math.round(r.coll/r.exp*100):0,uncoll=Math.max(0,r.exp-r.coll);
        const collCard=`<div class="card"><div class="chead"><h4>Collection — collected vs expected</h4><span class="chip ${collRate>=80?'jade':collRate>=50?'amber':'coral'}">${collRate}% collected</span></div><div class="mini3"><div class="cm"><span>Collected</span><b class="num" style="color:var(--jade)">${fmoney(r.coll)}</b></div><div class="cm"><span>Expected</span><b class="num">${fmoney(r.exp)}</b></div><div class="cm"><span>Uncollected</span><b class="num" style="color:${uncoll>0?'var(--amber)':'var(--ink)'}">${fmoney(uncoll)}</b></div></div><div style="margin-top:16px"><div style="height:11px;border-radius:99px;background:var(--surface3);overflow:hidden;display:flex"><div style="width:${clamp(collRate,0,100)}%;background:var(--jade)"></div><div style="flex:1;background:var(--amber)"></div></div></div><div style="font-size:11px;color:var(--muted);margin-top:10px">Course fees vs price · event tickets vs early-bird · ${esc(yrLabel)}</div></div>`;
        // ---- expenses donut + net position ----
        const totalMul=1;
        const esegs=exp.by_cat.slice(0,8).map((d,i)=>[d.name,d.amount,finPalette[i%finPalette.length]]);
        const elegend=exp.by_cat.length?exp.by_cat.slice(0,8).map((d,i)=>`<div style="display:flex;align-items:center;gap:9px;padding:4px 0;border-bottom:1px solid var(--line)"><span style="width:11px;height:11px;border-radius:3px;background:${finPalette[i%finPalette.length]};flex:0 0 auto"></span><span style="flex:1;font-size:12.5px">${esc(d.name)}</span><b class="num" style="font-size:12.5px">${fmoney(d.amount)}</b></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:0">No expenses recorded.</p>';
        const expDonut=`<div class="card" ${exp.by_cat.length?'style="cursor:pointer" data-modal="expcat"':''}><div class="chead"><h4>Expenses by category</h4>${exp.by_cat.length?'<span class="chip" style="background:var(--brand);color:#fff;cursor:pointer">All →</span>':'<span class="chip slate">Live</span>'}</div><div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap"><div style="flex:0 0 auto;margin:0 auto">${hrDonut(esegs,fmoney(exp.total).replace(fsym(),""),(state.finCur==="KES"?"KSh":"USD")+" spent")}</div><div style="flex:1;min-width:180px">${elegend}</div></div><div style="font-size:11px;color:var(--muted);margin-top:10px">${nf.format(exp.count)} transactions across ${nf.format(exp.by_cat.length)} categories (all-time)</div></div>`;
        const netAll=r.total-exp.total,inOutMax=Math.max(r.total,exp.total,1);
        const posCard=`<div class="card"><div class="chead"><h4>Net position</h4><span class="chip ${netAll>=0?'jade':'coral'}">${netAll>=0?'Surplus':'Deficit'}</span></div><div style="font-size:28px;font-weight:850;line-height:1.1;color:${netAll>=0?'var(--jade)':'var(--coral)'}">${fmoney(netAll)}</div><div style="font-size:11px;color:var(--muted);margin:4px 0 14px">Revenue (${esc(yrLabel)}) minus expenses (all-time)</div><div style="display:flex;flex-direction:column;gap:10px">${fbar("Revenue",r.total,inOutMax,"var(--jade)")}${fbar("Expenses",exp.total,inOutMax,"var(--coral)")}</div></div>`;
        // ---- payroll + statutory + disbursement ----
        const payCard=pay?`<div class="card" style="cursor:pointer" data-modal="payslips"><div class="chead"><h4>Payroll — latest</h4><span class="chip" style="background:var(--brand);color:#fff;cursor:pointer">Payslips →</span></div><div class="mini3"><div class="cm"><span>Gross</span><b class="num">${fmoney(pay.gross)}</b></div><div class="cm"><span>Net</span><b class="num" style="color:var(--jade)">${fmoney(pay.net)}</b></div><div class="cm"><span>Staff</span><b class="num">${nf.format(pay.employees)}</b></div></div><div style="font-size:11px;color:var(--muted);margin-top:12px">Period ${pay.month}/${pay.year} · ${esc(String(pay.status||'—').replace(/_/g,' '))}</div></div>`:`<div class="card"><div class="chead"><h4>Payroll — latest</h4><span class="chip slate">—</span></div><p style="color:var(--muted);font-size:12.5px;margin:0">No payroll period yet.</p></div>`;
        const statTotal=st.paye+st.nssf_emp+st.nssf_er+st.shif+st.housing;
        const statCard=`<div class="card"><div class="chead"><h4>Statutory deductions</h4><span class="chip slate">${fmoney(statTotal)}</span></div><div class="mini3" style="grid-template-columns:1fr 1fr"><div class="cm"><span>PAYE (KRA)</span><b class="num">${fmoney(st.paye)}</b></div><div class="cm"><span>SHIF</span><b class="num">${fmoney(st.shif)}</b></div><div class="cm"><span>NSSF (emp+er)</span><b class="num">${fmoney(st.nssf_emp+st.nssf_er)}</b></div><div class="cm"><span>Housing levy</span><b class="num">${fmoney(st.housing)}</b></div></div><div style="font-size:11px;color:var(--muted);margin-top:12px">Latest payroll period · remitted to KRA / NSSF / SHA</div></div>`;
        const disPct=dis.total_emp?Math.round(dis.paid_n/dis.total_emp*100):0;
        const disCard=`<div class="card"><div class="chead"><h4>Payroll disbursement</h4>${dis.pend_n?`<span class="chip amber">${nf.format(dis.pend_n)} pending</span>`:'<span class="chip jade">All paid</span>'}</div><div class="mini3"><div class="cm"><span>Paid</span><b class="num" style="color:var(--jade)">${nf.format(dis.paid_n)}</b></div><div class="cm"><span>Pending</span><b class="num" style="color:${dis.pend_n?'var(--amber)':'var(--ink)'}">${nf.format(dis.pend_n)}</b></div><div class="cm"><span>Net pay</span><b class="num">${fmoney(dis.total_net)}</b></div></div><div style="margin-top:14px"><div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:6px"><span>Disbursed</span><b style="color:var(--ink)">${disPct}% · ${fmoney(dis.paid_amt)}</b></div><div style="height:10px;border-radius:99px;background:var(--surface3);overflow:hidden;display:flex"><div style="width:${clamp(disPct,0,100)}%;background:var(--jade)"></div><div style="flex:1;background:var(--amber)"></div></div></div></div>`;
        // ---- remittance aging + commissions ----
        const remOut=rem.pending_amt+rem.overdue_amt;
        const remCard=`<div class="card"><div class="chead"><h4>Remittance aging</h4>${rem.overdue?`<span class="chip coral">${nf.format(rem.overdue)} overdue</span>`:'<span class="chip jade">On track</span>'}</div><div class="mini3"><div class="cm"><span>Overdue</span><b class="num" style="color:${rem.overdue?'var(--coral)':'var(--ink)'}">${nf.format(rem.overdue)}</b></div><div class="cm"><span>Pending</span><b class="num">${nf.format(rem.pending)}</b></div><div class="cm"><span>Paid</span><b class="num" style="color:var(--jade)">${nf.format(rem.paid)}</b></div></div><div style="font-size:11px;color:var(--muted);margin-top:12px">Outstanding: <b style="color:${remOut>0?'var(--amber)':'var(--ink)'}">${fmoney(remOut)}</b> · overdue ${fmoney(rem.overdue_amt)}</div></div>`;
        const feeR=fee.expected?Math.round(fee.collected/fee.expected*100):0;
        const feeCard=`<div class="card"><div class="chead"><h4>Client fee balances</h4><span class="chip slate">${nf.format(fee.clients)} clients</span></div><div class="mini3"><div class="cm"><span>Collected</span><b class="num" style="color:var(--jade)">${fmoney(fee.collected)}</b></div><div class="cm"><span>Expected</span><b class="num">${fmoney(fee.expected)}</b></div><div class="cm"><span>Outstanding</span><b class="num" style="color:${fee.outstanding>0?'var(--amber)':'var(--ink)'}">${fmoney(fee.outstanding)}</b></div></div><div style="display:flex;gap:8px;margin-top:12px"><span class="chip jade">${nf.format(fee.paid)} fully paid</span><span class="chip amber">${nf.format(fee.partial)} partial</span><span class="chip slate">${feeR}% collected</span></div></div>`;
        const comCard=`<div class="card"><div class="chead"><h4>Sales commissions</h4><span class="chip slate">Eligible ${fmoney(com.eligible)}</span></div><div class="mini3"><div class="cm"><span>Pending</span><b class="num" style="color:var(--amber)">${fmoney(com.pending)}</b></div><div class="cm"><span>Approved</span><b class="num">${fmoney(com.approved)}</b></div><div class="cm"><span>Paid</span><b class="num" style="color:var(--jade)">${fmoney(com.paid)}</b></div></div><div style="font-size:11px;color:var(--muted);margin-top:12px">Marketer commissions on cleared revenue</div></div>`;
        return `
          ${controls}
          ${kpiRowRev}
          <div class="section-tag"><h3>Revenue mix &amp; trend</h3><span>Where the money comes from, month by month</span><div class="rule"></div></div>
          <section class="grid-rev">${distCard}${trendCard}</section>
          <div class="section-tag"><h3>Top earners</h3><span>Highest-grossing courses and events — ${esc(yrLabel)}</span><div class="rule"></div></div>
          <section class="grid-2">${topCourses}${topEvents}</section>
          <div class="section-tag"><h3>Collection &amp; balances</h3><span>Collected against expected, and outstanding client fees</span><div class="rule"></div></div>
          <section class="grid-2">${collCard}${feeCard}</section>
          <div class="section-tag"><h3>Spend &amp; net position</h3><span>Expenses by category and the bottom line</span><div class="rule"></div></div>
          <section class="grid-2">${expDonut}${posCard}</section>
          <div class="section-tag"><h3>Payroll &amp; statutory</h3><span>Staff cost, statutory deductions and disbursement status</span><div class="rule"></div></div>
          <section class="grid-3">${payCard}${statCard}${disCard}</section>
          <div class="section-tag"><h3>Obligations &amp; commissions</h3><span>Statutory remittance aging and sales commissions</span><div class="rule"></div></div>
          <section class="grid-2">${remCard}${comCard}</section>`;
      }
      function vAdmin(){
        const A=ADM,rq=A.req,total=rq.pending+rq.progress+rq.completed+rq.rejected;
        const rlist=[["Pending",rq.pending,"var(--amber)"],["In progress",rq.progress,"var(--slate)"],["Completed",rq.completed,"var(--jade)"],["Rejected",rq.rejected,"var(--coral)"]];
        const rsegs=rlist.filter(s=>s[1]>0);
        const rlegend=total?rlist.map(s=>`<div style="display:flex;align-items:center;gap:9px;padding:4px 0;border-bottom:1px solid var(--line)"><span style="width:11px;height:11px;border-radius:3px;background:${s[2]};flex:0 0 auto"></span><span style="flex:1;font-size:12.5px">${s[0]}</span><b class="num" style="font-size:13px">${nf.format(s[1])}</b></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:0">No requests.</p>';
        const reqDonut=`<div class="card"><div class="chead"><h4>Requests by status</h4><span class="chip slate">${nf.format(total)} total</span></div><div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap"><div style="flex:0 0 auto;margin:0 auto">${hrDonut(rsegs,nf.format(total),"requests")}</div><div style="flex:1;min-width:180px">${rlegend}</div></div></div>`;
        const pendCard=`<div class="card" ${rq.list.length?'style="cursor:pointer" data-modal="requests"':''}><div class="chead"><h4>Open requests</h4>${rq.list.length?'<span class="chip" style="background:var(--brand);color:#fff;cursor:pointer">View all →</span>':'<span class="chip slate">Live</span>'}</div><div class="mini3"><div class="cm"><span>Pending</span><b class="num" style="color:var(--amber)">${nf.format(rq.pending)}</b></div><div class="cm"><span>In progress</span><b class="num">${nf.format(rq.progress)}</b></div><div class="cm"><span>Completed</span><b class="num" style="color:var(--jade)">${nf.format(rq.completed)}</b></div></div><div style="font-size:11px;color:var(--muted);margin-top:12px">Pending value: <b style="color:var(--ink)">${kMoney(rq.pending_amt)}</b> · click for the full queue</div></div>`;
        const stChip=st=>{const m={Pending:"amber","In Progress":"slate",Completed:"jade",Rejected:"coral"};return `<span class="chip ${m[st]||'slate'}">${esc(st)}</span>`;};
        const reqRows=rq.list.length?rq.list.slice(0,8).map(r=>`<tr><td><b>${esc(r.title)}</b><div style="font-size:11px;color:var(--muted)">${esc(r.type||'—')}</div></td><td>${esc(r.staff||'—')}</td><td class="num">${r.amount>0?kMoney(r.amount):'—'}</td><td>${stChip(r.status)}</td><td class="num">${esc(r.date)}</td></tr>`).join(""):'<tr><td colspan="5" style="text-align:center;color:var(--muted)">No requests.</td></tr>';
        const stateChip=s=>s==="ready"?'<span class="chip jade">Ready</span>':s==="config"?'<span class="chip amber">Needs config</span>':'<span class="chip slate">Unassigned</span>';
        const intakeRows=A.intakes.length?A.intakes.slice().sort((a,b)=>(b.ts||0)-(a.ts||0)).map(it=>`<tr><td><b>${esc(it.name)}</b></td><td class="num">${esc(it.date||'—')}</td><td>${esc(it.assignee||'—')}</td><td class="num">${nf.format(it.registered)}</td><td class="num">${nf.format(it.paying)}</td><td>${stateChip(it.state)}</td></tr>`).join(""):'<tr><td colspan="6" style="text-align:center;color:var(--muted)">No active intakes.</td></tr>';
        const eventRows=A.events.length?A.events.slice().sort((a,b)=>(b.ts||0)-(a.ts||0)).map(ev=>`<tr><td><b>${esc(ev.name)}</b>${ev.location?`<div style="font-size:11px;color:var(--muted)">${esc(ev.location)}</div>`:''}</td><td class="num">${esc(ev.date||'—')}</td><td>${esc(ev.assignee||'—')}</td><td class="num">${nf.format(ev.registered)}</td><td class="num">${nf.format(ev.paying)}</td><td>${stateChip(ev.state)}</td></tr>`).join(""):'<tr><td colspan="6" style="text-align:center;color:var(--muted)">No active events.</td></tr>';
        return `
          <div class="section-tag"><h3>Admin &amp; Requests</h3><span>Service requests, course intakes and event assignments — live</span><div class="rule"></div></div>
          <section class="grid-2">${pendCard}${reqDonut}</section>
          <div class="section-tag"><h3>Recent requests</h3><span>Newest first · open queue on top</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>Request</th><th>Requester</th><th>Amount</th><th>Status</th><th>Submitted</th></tr></thead><tbody>${reqRows}</tbody></table></div></div>
          <div class="section-tag"><h3>Course intake assignments</h3><span>Active virtual intakes (still enrolling) · newest start first</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>Intake</th><th>Starts</th><th>Assignee</th><th>Registered</th><th>Paying</th><th>Setup</th></tr></thead><tbody>${intakeRows}</tbody></table></div></div>
          <div class="section-tag"><h3>Event assignments</h3><span>Active international events (still open) · newest start first</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>Event</th><th>Starts</th><th>Assignee</th><th>Tickets</th><th>Paying</th><th>Setup</th></tr></thead><tbody>${eventRows}</tbody></table></div></div>`;
      }

      /* ---------- detail modals (attendance clock-ins, payroll payslips) ---------- */
      function openOpsModal(title, html){ el("opsModalTitle").innerHTML=title; el("opsModalBody").innerHTML=html; el("opsModal").classList.add("open"); }
      function closeOpsModal(){ el("opsModal").classList.remove("open"); }
      function showClockins(){
        const rows=HR.clockins.length?HR.clockins.map(c=>`<tr><td><b>${esc(c.name)}</b></td><td class="num" style="${c.late?"color:var(--coral);font-weight:800":""}">${esc(c.in)}${c.late?' <span class="chip coral" style="font-size:9px;padding:2px 6px;margin-left:4px">Late</span>':''}</td><td class="num">${esc(c.out)}</td></tr>`).join(""):'<tr><td colspan="3" style="text-align:center;color:var(--muted)">No clock-ins recorded today.</td></tr>';
        openOpsModal("Clocked in today · "+nf.format(HR.clockins.length)+" staff",`<div class="table-wrap"><table><thead><tr><th>Staff</th><th>Clock in</th><th>Clock out</th></tr></thead><tbody>${rows}</tbody></table></div>`);
      }
      function showPayslips(){
        const rows=HR.payslips.length?HR.payslips.map(p=>`<tr><td><b>${esc(p.name)}</b></td><td>${esc(p.dept||"—")}</td><td class="num">${kMoney(p.gross)}</td><td class="num">${kMoney(p.net)}</td></tr>`).join(""):'<tr><td colspan="4" style="text-align:center;color:var(--muted)">No payslips for this period.</td></tr>';
        const per=HR.payroll?(" · period "+HR.payroll.month+"/"+HR.payroll.year):"";
        openOpsModal("Payslips"+per+" · "+nf.format(HR.payslips.length)+" staff",`<div class="table-wrap"><table><thead><tr><th>Staff</th><th>Department</th><th>Gross</th><th>Net</th></tr></thead><tbody>${rows}</tbody></table></div>`);
      }
      function showExpcat(){
        const rows=FIN.expenses.by_cat.length?FIN.expenses.by_cat.map(c=>`<tr><td><b>${esc(c.name)}</b></td><td class="num">${nf.format(c.count)}</td><td class="num">${fmoney(c.amount)}</td></tr>`).join(""):'<tr><td colspan="3" style="text-align:center;color:var(--muted)">No expenses.</td></tr>';
        openOpsModal("Expenses by category · "+fmoney(FIN.expenses.total),`<div class="table-wrap"><table><thead><tr><th>Category</th><th>Txns</th><th>Amount</th></tr></thead><tbody>${rows}</tbody></table></div>`);
      }
      function showRequests(){
        const stChip=st=>{const m={Pending:"amber","In Progress":"slate",Completed:"jade",Rejected:"coral"};return `<span class="chip ${m[st]||'slate'}">${esc(st)}</span>`;};
        const rows=ADM.req.list.length?ADM.req.list.map(r=>`<tr><td><b>${esc(r.title)}</b><div style="font-size:11px;color:var(--muted)">${esc(r.type||'—')}${r.priority?' · '+esc(r.priority):''}</div></td><td>${esc(r.staff||'—')}</td><td class="num">${r.amount>0?kMoney(r.amount):'—'}</td><td>${stChip(r.status)}</td><td class="num">${esc(r.date)}</td></tr>`).join(""):'<tr><td colspan="5" style="text-align:center;color:var(--muted)">No requests.</td></tr>';
        openOpsModal("Service requests · "+nf.format(ADM.req.list.length)+" shown",`<div class="table-wrap"><table><thead><tr><th>Request</th><th>Requester</th><th>Amount</th><th>Status</th><th>Submitted</th></tr></thead><tbody>${rows}</tbody></table></div>`);
      }
      function showWaEscal(){
        const alerts=(B.alerts||[]);
        const total=alerts.reduce((a,x)=>a+((+x.n)||0),0);
        if(!alerts.length){openOpsModal("WhatsApp chats awaiting reply",`<p style="color:var(--muted);padding:14px">No unread escalated chats across the SBUs right now.</p>`);return;}
        const avPal=["var(--slate)","var(--violet)","#2f8f88","var(--brand)","var(--gold)","#4d8bd6","var(--coral)"];let ai=0;
        const bySbu={};alerts.forEach(a=>{const s=a.sbu||"—";(bySbu[s]=bySbu[s]||[]).push(a);});
        const order=Object.keys(bySbu).sort((a,b)=>bySbu[b].reduce((s,x)=>s+((+x.n)||0),0)-bySbu[a].reduce((s,x)=>s+((+x.n)||0),0));
        const body=order.map(sbu=>{const g=bySbu[sbu].slice().sort((a,b)=>((+b.n)||0)-((+a.n)||0));const sub=g.reduce((s,x)=>s+((+x.n)||0),0);
          return `<tr style="background:var(--surface2)"><td colspan="2"><b>${esc(sbu)}</b></td><td class="num"><b>${nf.format(sub)}</b></td></tr>`
            +g.map(p=>`<tr><td style="width:26px"></td><td><div class="prow"><span class="a" style="background:${avColor(p.name)}">${esc(pInitials(p.name||"—"))}</span><div><b>${esc(p.name||"—")}</b></div></div></td><td class="num">${nf.format((+p.n)||0)}</td></tr>`).join("");
        }).join("");
        openOpsModal("WhatsApp chats awaiting reply · "+nf.format(total)+" unread",`<div class="table-wrap"><table><thead><tr><th>SBU</th><th>Person</th><th>Unread</th></tr></thead><tbody>${body}</tbody></table></div><p style="font-size:11.5px;color:var(--muted);margin:12px 4px 0">Open WhatsApp conversations escalated to each person with an unanswered message.</p>`);
      }

      /* ---------- Reports tab: native grouped-bar charts (virtual + international) ---------- */
      function barsSVG(labels,series,fmt,empty){
        if(!labels.length||!series.some(s=>s.vals.some(v=>v))) return `<div style="display:flex;align-items:center;justify-content:center;min-height:180px;color:var(--muted);font-size:13.5px;font-weight:500;text-align:center;padding:20px">${esc(empty||"No data in this period.")}</div>`;
        const W=920,H=340,padL=74,padR=18,padT=34,padB=54,iw=W-padL-padR,ih=H-padT-padB;
        const rawMax=Math.max(1,...series.flatMap(s=>s.vals.map(v=>Math.abs(v||0))));
        const niceMax=(function(x){const p=Math.pow(10,Math.floor(Math.log10(x)));const u=x/p;const f=u<=1?1:u<=2?2:u<=5?5:10;return f*p;})(rawMax);
        const n=labels.length,gw=iw/n,ns=series.length,bw=Math.max(12,Math.min(56,(gw*0.72)/ns));
        let grid="";for(let g=0;g<=4;g++){const yy=padT+ih*g/4,val=niceMax*(1-g/4);grid+=`<line x1="${padL}" y1="${yy.toFixed(1)}" x2="${W-padR}" y2="${yy.toFixed(1)}" stroke="var(--line)" stroke-width="${g===4?1.5:1}"/><text x="${padL-10}" y="${(yy+4).toFixed(1)}" text-anchor="end" style="font-size:12px;fill:var(--muted);font-weight:600">${fmt(val)}</text>`;}
        let bars="",xl="";
        labels.forEach((lab,i)=>{const cx=padL+gw*i+gw/2,groupW=bw*ns;
          series.forEach((s,si)=>{const v=Math.max(0,s.vals[i]||0),x=cx-groupW/2+si*bw,h=(v/niceMax)*ih,y=padT+ih-h,bx=x+(bw-4)/2;
            bars+=`<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${(bw-4).toFixed(1)}" height="${Math.max(0,h).toFixed(1)}" rx="3" fill="${s.color}"><title>${esc(lab)} · ${esc(s.name)}: ${fmt(v)}</title></rect>`;
            if(v>0)bars+=`<text x="${bx.toFixed(1)}" y="${(y-7).toFixed(1)}" text-anchor="middle" style="font-size:11.5px;font-weight:800;fill:${s.color}">${fmt(v)}</text>`;});
          xl+=`<text x="${cx.toFixed(1)}" y="${H-padB+22}" text-anchor="middle" style="font-size:12.5px;font-weight:700;fill:var(--ink)">${esc(lab)}</text>`;});
        return `<svg viewBox="0 0 ${W} ${H}" style="width:100%;height:auto;display:block">${grid}${bars}${xl}</svg>`;
      }
      function repLegend(series){return `<div style="display:flex;gap:16px;font-size:12.5px;font-weight:600;flex-wrap:wrap">${series.map(s=>`<span style="display:flex;align-items:center;gap:7px"><span style="width:13px;height:13px;border-radius:3px;background:${s.color}"></span>${esc(s.name)}</span>`).join("")}</div>`;}
      function vReports(){
        const V=REP.virtual||{months:[]},I=REP.international||{months:[],loc:[]},C=REP.corporate||{months:[]};
        const cnt=v=>nf.format(Math.round(v||0));
        const vm=V.months||[],im=I.months||[],cm=C.months||[],locs=(I.loc||[]).slice(0,8),short=s=>{s=String(s||"—");return s.length>12?s.slice(0,11)+"…":s;};
        const chart=(title,chip,series,vals,fmt,legend,empty)=>`<div class="card"><div class="chead"><h4>${title}</h4>${chip}</div>${legend}${barsSVG(vals,series,fmt,empty)}</div>`;
        const vEnq=chart("Virtual · enquiries vs clients","",[{name:"Enquiries",color:"var(--brand)",vals:vm.map(m=>m.enq)},{name:"Clients (paid)",color:"var(--jade)",vals:vm.map(m=>m.cli)}],vm.map(m=>m.label),cnt,repLegend([{name:"Enquiries",color:"var(--brand)"},{name:"Clients (paid)",color:"var(--jade)"}]),"No enquiries in this period.");
        const vMon=chart("Virtual · fee collected vs balance","",[{name:"Collected",color:"var(--jade)",vals:vm.map(m=>m.collected)},{name:"Balance",color:"var(--amber)",vals:vm.map(m=>Math.max(0,(m.due||0)-(m.collected||0)))}],vm.map(m=>m.label),fmoney,repLegend([{name:"Collected",color:"var(--jade)"},{name:"Balance",color:"var(--amber)"}]),"No fee collected in this period.");
        const iEnq=chart("International · leads vs customers","",[{name:"Leads",color:"var(--brand)",vals:im.map(m=>m.enq)},{name:"Customers",color:"var(--jade)",vals:im.map(m=>m.cli)}],im.map(m=>m.label),cnt,repLegend([{name:"Leads",color:"var(--brand)"},{name:"Customers",color:"var(--jade)"}]),"No leads in this period.");
        const iMon=chart("International · fee collected","",[{name:"Collected",color:"var(--jade)",vals:im.map(m=>m.collected)}],im.map(m=>m.label),fmoney,repLegend([{name:"Collected",color:"var(--jade)"}]),"No fee collected in this period.");
        const iRev=chart("International · revenue by location",`<span class="chip slate">Top ${locs.length}</span>`,[{name:"Revenue",color:"var(--brand)",vals:locs.map(l=>l.revenue)}],locs.map(l=>short(l.label)),fmoney,"","No revenue by location yet.");
        const iBal=chart("International · fee balance by location",`<span class="chip slate">Top ${locs.length}</span>`,[{name:"Balance",color:"var(--amber)",vals:locs.map(l=>l.balance)}],locs.map(l=>short(l.label)),fmoney,"","No outstanding balances.");
        const cEnq=chart("Corporate · enquiries vs won","",[{name:"Enquiries",color:"var(--brand)",vals:cm.map(m=>m.enq)},{name:"Won",color:"var(--jade)",vals:cm.map(m=>m.cli)}],cm.map(m=>m.label),cnt,repLegend([{name:"Enquiries",color:"var(--brand)"},{name:"Won",color:"var(--jade)"}]),"No corporate enquiries in this period.");
        const cMon=chart("Corporate · fee collected","",[{name:"Collected",color:"var(--jade)",vals:cm.map(m=>m.collected)}],cm.map(m=>m.label),fmoney,repLegend([{name:"Collected",color:"var(--jade)"}]),"No fee collected in this period.");
        return `
          <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div class="section-tag" style="margin:0;flex:1;min-width:240px"><h3>Analytics</h3><span>Enrolment &amp; revenue trends by department — last 6 months</span></div>
            <div class="curtoggle"><button data-fincur="USD" class="${state.finCur==="USD"?"on":""}">USD $</button><button data-fincur="KES" class="${state.finCur==="KES"?"on":""}">KES</button></div>
          </div><div class="rule" style="margin:0 0 4px"></div>
          <div class="section-tag"><h3>Virtual (courses)</h3><span>Online course enrolment and fees</span><div class="rule"></div></div>
          <section class="grid-2">${vEnq}${vMon}</section>
          <div class="section-tag"><h3>International (events)</h3><span>Event leads, customers, fees and geographic spread</span><div class="rule"></div></div>
          <section class="grid-2">${iEnq}${iMon}</section>
          <section class="grid-2">${iRev}${iBal}</section>
          <div class="section-tag"><h3>Corporate (trainings)</h3><span>Corporate proposals, wins and training fees</span><div class="rule"></div></div>
          <section class="grid-2">${cEnq}${cMon}</section>`;
      }

      function render(){
        if(state.role==="ceo"){const v=state.view;el("workspace").innerHTML=v==="command"?vCommand():v==="people"?vPeople():v==="hr"?vHR():v==="finance"?vFinance():v==="admin"?vAdmin():v==="reports"?vReports():vPipeline();}
        else{el("workspace").innerHTML=roleView();}
        syncControls();
        root.querySelectorAll("[data-scope]").forEach(x=>x.addEventListener("click",()=>{applyScope(x.getAttribute("data-scope"));render();window.scrollTo({top:0,behavior:"smooth"});}));
        root.querySelectorAll("[data-modal]").forEach(x=>x.addEventListener("click",()=>{const m=x.getAttribute("data-modal");if(m==="clockins")showClockins();else if(m==="payslips")showPayslips();else if(m==="expcat")showExpcat();else if(m==="requests")showRequests();else if(m==="waescal")showWaEscal();}));
        const fySel=el("finYear");if(fySel)fySel.addEventListener("change",e=>{state.finYear=e.target.value;render();});
        root.querySelectorAll("[data-fincur]").forEach(x=>x.addEventListener("click",()=>{state.finCur=x.getAttribute("data-fincur");render();}));
      }
      function bindReport(){
        el("genReport").addEventListener("click",genReport);
        el("dlReport").addEventListener("click",()=>{genReport();const t=el("reportPreview").textContent;const b=new Blob([t],{type:"text/plain"});const a=document.createElement("a");a.href=URL.createObjectURL(b);a.download="Vantage_BDE_"+period().label.replace(/\s+/g,"_")+"_Report.txt";a.click();URL.revokeObjectURL(a.href);});
        el("clrReport").addEventListener("click",()=>root.querySelectorAll("#reportForm textarea").forEach(x=>x.value=""));
      }
      function genReport(){
        const lines=["VANTAGE AFRICA — BDE DAILY REPORT","Period: "+period().label,"Consultant: "+B.name+" | "+B.title+" · "+B.dept,""];
        root.querySelectorAll("#reportForm input,#reportForm textarea").forEach(x=>lines.push(x.dataset.label+": "+(x.value.trim()||"—")));
        const att=B.actual/B.target;lines.push("");lines.push("Dashboard position: "+kMoney(B.actual)+" cleared against "+kMoney(B.target)+" ("+pct(att)+").");
        lines.push("Qualified pipeline: "+kMoney(B.pipeline)+". Collection: "+pct(B.collection,0)+".");
        lines.push("Commission estimate: "+kMoney(commission().current)+".");
        lines.push("All figures subject to CRM evidence and Finance verification.");
        el("reportPreview").textContent=lines.join("\n");
      }

      el("roleSelect").addEventListener("change",e=>{state.role=e.target.value;if(state.role==="ceo")state.view="command";render();window.scrollTo({top:0,behavior:"smooth"});});
      el("deptSelect").addEventListener("change",e=>{state.dept=+e.target.value;state.emp=0;render();});
      el("empSelect").addEventListener("change",e=>{state.emp=+e.target.value;render();});
      el("periodSelect").innerHTML=periods.map((p,i)=>`<option value="${i}" ${i===state.p?"selected":""}>${p.label}</option>`).join("");
      el("periodSelect").addEventListener("change",e=>{state.p=+e.target.value;render();});
      root.querySelectorAll("#tabNav .tab[data-v]").forEach(a=>a.addEventListener("click",()=>{state.view=a.dataset.v;render();}));
      el("themeBtn").addEventListener("click",()=>{const dark=root.classList.toggle("theme-dark");el("themeBtn").textContent=dark?"☀ Light":"🌙 Dark";});
      el("opsModal").addEventListener("click",e=>{ if(e.target.id==="opsModal" || (e.target.closest && e.target.closest("[data-close]"))) closeOpsModal(); });

      render();
    })();
    </script>
  </div>
</section>

<?php require_once 'footer.php'; ?>
