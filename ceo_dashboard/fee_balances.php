<?php 
require_once 'header.php'; 
include '../function.php';

// Currency conversion rate
$usd_to_kes = 129;

// Get filter values
$department = isset($_GET['department']) ? $_GET['department'] : 'all'; // all, virtual, international
$selected_item = isset($_GET['item']) ? $_GET['item'] : 'all'; // course_id or event_id
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';
$balance_filter = isset($_GET['balance']) ? $_GET['balance'] : 'owing'; // all, owing, paid, overpaid

// ============================================
// GET AVAILABLE COURSES (from intakes)
// ============================================
$courses_query = mysqli_query($conn, "
    SELECT DISTINCT c.course_id, c.course, c.price_usd 
    FROM course c 
    INNER JOIN intake i ON c.course_id = i.course_id
    INNER JOIN register r ON i.intake_id = r.intake_id
    ORDER BY c.course ASC
") or die(mysqli_error($conn));

$courses = [];
while($row = mysqli_fetch_assoc($courses_query)) {
    $courses[] = $row;
}

// ============================================
// GET AVAILABLE EVENTS
// ============================================
$events_query = mysqli_query($conn, "
    SELECT DISTINCT e.event_id, e.event_title, e.early_amount 
    FROM Event e 
    INNER JOIN ticket_congress tc ON e.event_id = tc.event_id
    ORDER BY e.event_title ASC
") or die(mysqli_error($conn));

$events = [];
while($row = mysqli_fetch_assoc($events_query)) {
    $events[] = $row;
}

// ============================================
// GET AVAILABLE YEARS
// ============================================
$years_query = mysqli_query($conn, "
    SELECT DISTINCT yr FROM (
        SELECT YEAR(r.datee) AS yr FROM register r WHERE r.datee IS NOT NULL
        UNION
        SELECT YEAR(tc.date_sent) AS yr FROM ticket_congress tc WHERE tc.date_sent IS NOT NULL
    ) years_combined
    WHERE yr IS NOT NULL
    ORDER BY yr DESC
") or die(mysqli_error($conn));

$available_years = [];
while($row = mysqli_fetch_assoc($years_query)) {
    if($row['yr']) $available_years[] = $row['yr'];
}

// ============================================
// BUILD FEE BALANCE DATA
// ============================================
$balances = [];
$summary = [
    'total_expected' => 0,
    'total_paid' => 0,
    'total_balance' => 0,
    'total_clients' => 0,
    'fully_paid' => 0,
    'partial_paid' => 0,
    'overpaid' => 0
];

// Pre-load ALL payments grouped by app_id (handles installments - multiple transactions per client)
$all_payments = [];
$all_payment_counts = [];
$pay_query = mysqli_query($conn, "
    SELECT app_id, SUM(TransactionAmount) AS total_paid, COUNT(*) AS num_payments
    FROM dpo_payment
    WHERE status = 2
    GROUP BY app_id
    HAVING SUM(TransactionAmount) > 0
") or die(mysqli_error($conn));
while($p = mysqli_fetch_assoc($pay_query)) {
    $all_payments[$p['app_id']] = floatval($p['total_paid']);
    $all_payment_counts[$p['app_id']] = intval($p['num_payments']);
}

// ---- VIRTUAL COURSES ----
if ($department == 'all' || $department == 'virtual') {
    
    $virtual_where = "";
    if ($selected_item != 'all' && $department == 'virtual') {
        $virtual_where .= " AND c.course_id = '" . mysqli_real_escape_string($conn, $selected_item) . "'";
    }
    if ($selected_year != 'all') {
        $yr = (int)$selected_year;
        $virtual_where .= " AND r.datee >= '$yr-01-01 00:00:00' AND r.datee < '" . ($yr + 1) . "-01-01 00:00:00'";
    }
    
    $virtual_query = mysqli_query($conn, "
        SELECT 
            r.entry_id,
            r.firstname,
            r.lastname,
            r.email,
            r.phone_number,
            r.datee AS reg_date,
            c.course AS item_name,
            c.price_usd AS fee,
            c.course_id,
            i.intake_id,
            i.description AS intake_name,
            'Virtual' AS department
        FROM register r
        INNER JOIN intake i ON r.intake_id = i.intake_id
        INNER JOIN course c ON i.course_id = c.course_id
        WHERE 1 $virtual_where
        ORDER BY r.datee DESC
    ") or die(mysqli_error($conn));
    
    // Deduplicate by email + course_id (keep highest payment)
    $seen_virtual = [];
    while($row = mysqli_fetch_assoc($virtual_query)) {
        $dedup_key = strtolower(trim($row['email'])) . '_' . $row['course_id'];
        $total_paid = isset($all_payments[$row['entry_id']]) ? $all_payments[$row['entry_id']] : 0;
        $num_installments = isset($all_payment_counts[$row['entry_id']]) ? $all_payment_counts[$row['entry_id']] : 0;
        
        // Only show clients who have paid at least something (they are customers)
        if ($total_paid <= 0) continue;
        
        // Keep entry with highest payment
        if (isset($seen_virtual[$dedup_key])) {
            $existing_paid = $seen_virtual[$dedup_key]['paid'];
            if ($total_paid <= $existing_paid) continue;
            // Remove old entry from balances
            foreach ($balances as $k => $b) {
                if ($b['dedup_key'] == $dedup_key && $b['department'] == 'Virtual') {
                    unset($balances[$k]);
                    break;
                }
            }
        }
        $seen_virtual[$dedup_key] = ['paid' => $total_paid];
        
        $fee = floatval($row['fee']);
        $balance = $fee - $total_paid;
        $pct = $fee > 0 ? round(($total_paid / $fee) * 100, 1) : 0;
        
        // Determine status (only paid clients reach here)
        if ($balance <= 0) {
            $status = $balance < 0 ? 'Overpaid' : 'Fully Paid';
        } else {
            $status = 'Partial';
        }
        
        // Apply balance filter
        if ($balance_filter == 'owing' && $balance <= 0) continue;
        if ($balance_filter == 'paid' && $balance != 0) continue;
        if ($balance_filter == 'overpaid' && $balance >= 0) continue;
        
        $balances[] = [
            'dedup_key' => $dedup_key,
            'department' => 'Virtual',
            'fullname' => $row['firstname'] . ' ' . $row['lastname'],
            'email' => $row['email'],
            'phone' => $row['phone_number'],
            'item_name' => $row['item_name'],
            'intake_event' => $row['intake_name'],
            'fee' => $fee,
            'paid' => $total_paid,
            'balance' => $balance,
            'pct' => $pct,
            'status' => $status,
            'reg_date' => $row['reg_date'],
            'ref_id' => $row['entry_id'],
            'installments' => $num_installments
        ];
    }
}

// ---- INTERNATIONAL EVENTS ----
if ($department == 'all' || $department == 'international') {
    
    $intl_where = "";
    if ($selected_item != 'all' && $department == 'international') {
        $intl_where .= " AND e.event_id = '" . mysqli_real_escape_string($conn, $selected_item) . "'";
    }
    if ($selected_year != 'all') {
        $yr = (int)$selected_year;
        $intl_where .= " AND tc.date_sent >= '$yr-01-01 00:00:00' AND tc.date_sent < '" . ($yr + 1) . "-01-01 00:00:00'";
    }
    
    $intl_query = mysqli_query($conn, "
        SELECT 
            tc.id,
            tc.ticket_id,
            tc.fullname,
            tc.email,
            tc.phone_number,
            tc.date_sent AS reg_date,
            e.event_title AS item_name,
            e.early_amount AS fee,
            e.event_id,
            'International' AS department
        FROM ticket_congress tc
        INNER JOIN Event e ON tc.event_id = e.event_id
        WHERE 1 $intl_where
        ORDER BY tc.date_sent DESC
    ") or die(mysqli_error($conn));
    
    // Deduplicate by email + event_id
    $seen_intl = [];
    while($row = mysqli_fetch_assoc($intl_query)) {
        $dedup_key = strtolower(trim($row['email'])) . '_evt_' . $row['event_id'];
        $total_paid = isset($all_payments[$row['ticket_id']]) ? $all_payments[$row['ticket_id']] : 0;
        $num_installments = isset($all_payment_counts[$row['ticket_id']]) ? $all_payment_counts[$row['ticket_id']] : 0;
        
        // Only show clients who have paid at least something
        if ($total_paid <= 0) continue;
        
        if (isset($seen_intl[$dedup_key])) {
            $existing_paid = $seen_intl[$dedup_key]['paid'];
            if ($total_paid <= $existing_paid) continue;
            foreach ($balances as $k => $b) {
                if ($b['dedup_key'] == $dedup_key && $b['department'] == 'International') {
                    unset($balances[$k]);
                    break;
                }
            }
        }
        $seen_intl[$dedup_key] = ['paid' => $total_paid];
        
        $fee = floatval($row['fee']);
        $balance = $fee - $total_paid;
        $pct = $fee > 0 ? round(($total_paid / $fee) * 100, 1) : 0;
        
        // Only Partial, Fully Paid, or Overpaid (no zero-paid clients)
        if ($balance <= 0) {
            $status = $balance < 0 ? 'Overpaid' : 'Fully Paid';
        } else {
            $status = 'Partial';
        }
        
        if ($balance_filter == 'owing' && $balance <= 0) continue;
        if ($balance_filter == 'paid' && $balance != 0) continue;
        if ($balance_filter == 'overpaid' && $balance >= 0) continue;
        
        $balances[] = [
            'dedup_key' => $dedup_key,
            'department' => 'International',
            'fullname' => $row['fullname'],
            'email' => $row['email'],
            'phone' => $row['phone_number'],
            'item_name' => $row['item_name'],
            'intake_event' => $row['item_name'],
            'fee' => $fee,
            'paid' => $total_paid,
            'balance' => $balance,
            'pct' => $pct,
            'status' => $status,
            'reg_date' => $row['reg_date'],
            'ref_id' => $row['ticket_id'],
            'installments' => $num_installments
        ];
    }
}

// Re-index array
$balances = array_values($balances);

// Calculate summary
foreach ($balances as $b) {
    $summary['total_expected'] += $b['fee'];
    $summary['total_paid'] += $b['paid'];
    $summary['total_balance'] += $b['balance'];
    $summary['total_clients']++;
    
    if ($b['status'] == 'Fully Paid') $summary['fully_paid']++;
    elseif ($b['status'] == 'Partial') $summary['partial_paid']++;
    elseif ($b['status'] == 'Overpaid') $summary['overpaid']++;
}

$collection_rate = $summary['total_expected'] > 0 ? round(($summary['total_paid'] / $summary['total_expected']) * 100, 1) : 0;
?>

<style>
    .stat-card {
        border: none;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card .stat-value { font-size: 1.6rem; font-weight: 700; }
    .stat-card .stat-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-top: 4px; }
    
    .filter-section {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
    }
    
    .badge-virtual { background-color: #198754 !important; }
    .badge-international { background-color: #0dcaf0 !important; color: #000 !important; }
    
    .progress-thin { height: 6px; border-radius: 3px; }
    
    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .status-paid { background: #198754; }
    .status-partial { background: #ffc107; }
    .status-unpaid { background: #dc3545; }
    .status-overpaid { background: #0d6efd; }
    
    .collection-ring {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: conic-gradient(
            #198754 0% <?php echo $collection_rate; ?>%, 
            #e9ecef <?php echo $collection_rate; ?>% 100%
        );
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    .collection-ring-inner {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        font-weight: 700;
        color: #198754;
    }

    .currency-toggle { cursor: pointer; user-select: none; }
    .currency-toggle:hover { text-decoration: underline; }
</style>

<div class="container-fluid py-4">
    
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-wallet2 me-2"></i>Fee Balances Report</h4>
            <p class="text-muted mb-0">Track outstanding balances for paying customers across Virtual courses and International events</p>
        </div>
        <div class="d-flex gap-2">
            <span class="currency-toggle badge bg-dark fs-6 py-2 px-3" onclick="toggleCurrency()" title="Click to switch currency">
                Showing: <span id="currencyLabel">USD</span>
            </span>
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" id="filterForm" class="row g-3 align-items-end">
            
            <div class="col-md-2">
                <label class="form-label fw-semibold">Department</label>
                <select name="department" class="form-select" id="departmentFilter" onchange="updateItemFilter()">
                    <option value="all" <?php echo $department == 'all' ? 'selected' : ''; ?>>All Departments</option>
                    <option value="virtual" <?php echo $department == 'virtual' ? 'selected' : ''; ?>>Virtual Courses</option>
                    <option value="international" <?php echo $department == 'international' ? 'selected' : ''; ?>>International Events</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-semibold">Course / Event</label>
                <select name="item" class="form-select" id="itemFilter">
                    <option value="all">All Courses & Events</option>
                    
                    <!-- Virtual Courses -->
                    <optgroup label="Virtual Courses" id="courseOptions" style="<?php echo $department == 'international' ? 'display:none' : ''; ?>">
                        <?php foreach($courses as $c): ?>
                        <option value="<?php echo $c['course_id']; ?>" 
                            data-dept="virtual"
                            <?php echo ($selected_item == $c['course_id'] && $department == 'virtual') ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course']); ?> ($<?php echo number_format($c['price_usd'], 2); ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    
                    <!-- International Events -->
                    <optgroup label="International Events" id="eventOptions" style="<?php echo $department == 'virtual' ? 'display:none' : ''; ?>">
                        <?php foreach($events as $e): ?>
                        <option value="<?php echo $e['event_id']; ?>" 
                            data-dept="international"
                            <?php echo ($selected_item == $e['event_id'] && $department == 'international') ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['event_title']); ?> ($<?php echo number_format($e['early_amount'], 2); ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-semibold">Year</label>
                <select name="year" class="form-select">
                    <option value="all" <?php echo $selected_year == 'all' ? 'selected' : ''; ?>>All Years</option>
                    <?php foreach($available_years as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $selected_year == $yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-semibold">Balance Status</label>
                <select name="balance" class="form-select">
                    <option value="all" <?php echo $balance_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="owing" <?php echo $balance_filter == 'owing' ? 'selected' : ''; ?>>Owing Balance</option>
                    <option value="paid" <?php echo $balance_filter == 'paid' ? 'selected' : ''; ?>>Fully Paid</option>
                    <option value="overpaid" <?php echo $balance_filter == 'overpaid' ? 'selected' : ''; ?>>Overpaid</option>
                </select>
            </div>
            
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
                <a href="fee_balances.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="stat-card bg-white">
                <div class="stat-value text-primary"><?php echo $summary['total_clients']; ?></div>
                <div class="stat-label">Total Clients</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-white">
                <div class="stat-value">
                    <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $summary['total_expected']; ?>"><?php echo number_format($summary['total_expected'], 2); ?></span>
                </div>
                <div class="stat-label">Expected Fees</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-white">
                <div class="stat-value text-success">
                    <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $summary['total_paid']; ?>"><?php echo number_format($summary['total_paid'], 2); ?></span>
                </div>
                <div class="stat-label">Total Collected</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-white">
                <div class="stat-value text-danger">
                    <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $summary['total_balance']; ?>"><?php echo number_format($summary['total_balance'], 2); ?></span>
                </div>
                <div class="stat-label">Outstanding Balance</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-white">
                <div class="collection-ring">
                    <div class="collection-ring-inner"><?php echo $collection_rate; ?>%</div>
                </div>
                <div class="stat-label mt-2">Collection Rate</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-white">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><span class="status-dot status-paid"></span><small>Fully Paid</small></div>
                        <span class="fw-bold text-success"><?php echo $summary['fully_paid']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div><span class="status-dot status-partial"></span><small>Partial</small></div>
                        <span class="fw-bold text-warning"><?php echo $summary['partial_paid']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div><span class="status-dot status-overpaid"></span><small>Overpaid</small></div>
                        <span class="fw-bold text-info"><?php echo $summary['overpaid']; ?></span>
                    </div>
                </div>
                <div class="stat-label mt-2">Status Breakdown</div>
            </div>
        </div>
    </div>

    <!-- Balances Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Fee Balances (<?php echo count($balances); ?> records)</h5>
            <div>
                <input type="text" class="form-control form-control-sm" id="tableSearch" placeholder="Search name, email, course..." style="width: 280px;" onkeyup="filterTable()">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0" id="balancesTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Department</th>
                            <th>Client Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Course / Event</th>
                            <th class="text-end">Fee (USD)</th>
                            <th class="text-end">Paid (USD)</th>
                            <th class="text-end">Balance (USD)</th>
                            <th>Paid %</th>
                            <th class="text-center">Payments</th>
                            <th>Status</th>
                            <th>Reg. Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($balances) == 0): ?>
                        <tr>
                            <td colspan="13" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No paying customers found with the current filters.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php $row_num = 1; foreach($balances as $b): ?>
                        <tr class="<?php echo $b['status'] == 'Fully Paid' ? 'table-success' : ''; ?>" style="cursor:pointer;" onclick="../viewDetails('<?php echo $b['department'] == 'Virtual' ? 'register' : 'ticket_congress'; ?>', '<?php echo $b['ref_id']; ?>')">
                            <td><?php echo $row_num++; ?></td>
                            <td>
                                <span class="badge <?php echo $b['department'] == 'Virtual' ? 'badge-virtual' : 'badge-international'; ?>">
                                    <?php echo $b['department']; ?>
                                </span>
                            </td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($b['fullname']); ?></td>
                            <td><small><?php echo htmlspecialchars($b['email']); ?></small></td>
                            <td><small><?php echo htmlspecialchars($b['phone']); ?></small></td>
                            <td><?php echo htmlspecialchars($b['item_name']); ?></td>
                            <td class="text-end">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $b['fee']; ?>"><?php echo number_format($b['fee'], 2); ?></span>
                            </td>
                            <td class="text-end text-success">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $b['paid']; ?>"><?php echo number_format($b['paid'], 2); ?></span>
                            </td>
                            <td class="text-end fw-bold <?php echo $b['balance'] > 0 ? 'text-danger' : ($b['balance'] < 0 ? 'text-info' : 'text-success'); ?>">
                                <span class="currency-symbol"><?php echo $b['balance'] != 0 ? '$' : ''; ?></span><span class="amount" data-usd="<?php echo abs($b['balance']); ?>">
                                <?php 
                                    if ($b['balance'] == 0) echo 'PAID';
                                    elseif ($b['balance'] < 0) echo '-' . number_format(abs($b['balance']), 2);
                                    else echo number_format($b['balance'], 2);
                                ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress progress-thin flex-grow-1" style="width: 60px;">
                                        <div class="progress-bar <?php 
                                            echo $b['pct'] >= 100 ? 'bg-success' : ($b['pct'] >= 50 ? 'bg-warning' : 'bg-danger'); 
                                        ?>" style="width: <?php echo min($b['pct'], 100); ?>%"></div>
                                    </div>
                                    <small class="fw-bold"><?php echo $b['pct']; ?>%</small>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border" title="<?php echo $b['installments']; ?> payment(s) made">
                                    <?php echo $b['installments']; ?> <?php echo $b['installments'] > 1 ? 'installments' : 'payment'; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $badge_class = match($b['status']) {
                                    'Fully Paid' => 'bg-success',
                                    'Partial' => 'bg-warning text-dark',
                                    'Overpaid' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $b['status']; ?></span>
                            </td>
                            <td><small><?php echo $b['reg_date'] ? date('d M Y', strtotime($b['reg_date'])) : '-'; ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (count($balances) > 0): ?>
                    <tfoot class="table-dark">
                        <tr>
                            <td colspan="6" class="fw-bold">TOTALS (<?php echo count($balances); ?> paying clients)</td>
                            <td class="text-end fw-bold">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $summary['total_expected']; ?>"><?php echo number_format($summary['total_expected'], 2); ?></span>
                            </td>
                            <td class="text-end fw-bold text-success">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $summary['total_paid']; ?>"><?php echo number_format($summary['total_paid'], 2); ?></span>
                            </td>
                            <td class="text-end fw-bold text-danger">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $summary['total_balance']; ?>"><?php echo number_format($summary['total_balance'], 2); ?></span>
                            </td>
                            <td class="fw-bold"><?php echo $collection_rate; ?>%</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Currency toggle
let currentCurrency = 'USD';
const KES_RATE = <?php echo $usd_to_kes; ?>;

function toggleCurrency() {
    currentCurrency = currentCurrency === 'USD' ? 'KES' : 'USD';
    document.getElementById('currencyLabel').textContent = currentCurrency;
    
    document.querySelectorAll('.currency-symbol').forEach(el => {
        el.textContent = currentCurrency === 'USD' ? '$' : 'KES ';
    });
    
    document.querySelectorAll('.amount').forEach(el => {
        const usdVal = parseFloat(el.dataset.usd);
        if (isNaN(usdVal)) return;
        
        // Check if this is a "PAID" text
        if (el.textContent.trim() === 'PAID') return;
        
        const displayVal = currentCurrency === 'USD' ? usdVal : usdVal * KES_RATE;
        const isNegative = el.textContent.trim().startsWith('-');
        el.textContent = (isNegative ? '-' : '') + numberFormat(Math.abs(displayVal));
    });
}

function numberFormat(num) {
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Filter course/event dropdown based on department selection
function updateItemFilter() {
    const dept = document.getElementById('departmentFilter').value;
    const courseOpts = document.getElementById('courseOptions');
    const eventOpts = document.getElementById('eventOptions');
    const itemFilter = document.getElementById('itemFilter');
    
    // Reset selection
    itemFilter.value = 'all';
    
    if (dept === 'virtual') {
        courseOpts.style.display = '';
        eventOpts.style.display = 'none';
    } else if (dept === 'international') {
        courseOpts.style.display = 'none';
        eventOpts.style.display = '';
    } else {
        courseOpts.style.display = '';
        eventOpts.style.display = '';
    }
}

// Table search
function filterTable() {
    const searchText = document.getElementById('tableSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#balancesTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
}

// Export to Excel
function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('currency', currentCurrency);
    window.location.href = 'export_fee_balances.php?' + params.toString();
}

// View enquiry details
function viewDetails(type, id) {
    window.location.href = 'enquiry_details.php?type=' + type + '&id=' + id;
}
</script>

<?php require_once 'footer.php'; ?>