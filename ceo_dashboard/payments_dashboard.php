<?php 
require_once 'header.php'; 
include '../function.php';

// Get selected year (default to 'all')
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';

// Currency conversion rate
$usd_to_kes = 129;

// ── Lightweight query helpers (no N+1, no fatal on failure) ──────────────────
function pd_rows($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if ($res === false) { error_log('[payments_dashboard.php] SQL failed: ' . mysqli_error($conn)); return []; }
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) { $out[] = $row; }
    if ($res instanceof mysqli_result) { mysqli_free_result($res); }
    return $out;
}
// Batch customer lookup — resolves name+phone for MANY emails in 2 queries total
// (register first, then ticket_congress; else " "). Returns [email => [name,phone]].
function pd_people($conn, array $emails) {
    $map = [];
    $emails = array_values(array_unique(array_filter($emails, fn($e) => $e !== null && $e !== '')));
    if (empty($emails)) return $map;
    foreach (array_chunk($emails, 500) as $chunk) {
        $in = implode(',', array_map(fn($e) => "'" . mysqli_real_escape_string($conn, $e) . "'", $chunk));
        foreach (pd_rows($conn, "SELECT email, firstname, lastname, phone_number FROM register WHERE email IN ($in)") as $r) {
            if (!isset($map[$r['email']])) {
                $map[$r['email']] = ['name' => ucfirst(strtolower($r['firstname'] . ' ' . $r['lastname'])), 'phone' => $r['phone_number']];
            }
        }
    }
    $missing = array_values(array_diff($emails, array_keys($map)));
    foreach (array_chunk($missing, 500) as $chunk) {
        $in = implode(',', array_map(fn($e) => "'" . mysqli_real_escape_string($conn, $e) . "'", $chunk));
        foreach (pd_rows($conn, "SELECT email, fullname, phone_number FROM ticket_congress WHERE email IN ($in)") as $r) {
            if (!isset($map[$r['email']])) {
                $map[$r['email']] = ['name' => ucfirst(strtolower($r['fullname'])), 'phone' => $r['phone_number']];
            }
        }
    }
    return $map;
}

// Get available years from both tables
$available_years = [];
foreach (pd_rows($conn, "
    SELECT DISTINCT YEAR(datee) as year FROM dpo_payment WHERE status = 2
    UNION
    SELECT DISTINCT YEAR(date_sent) as year FROM ticket_congress WHERE status = 2
    ORDER BY year DESC
") as $year_row) {
    if ($year_row['year']) { $available_years[] = $year_row['year']; }
}

// Build year filter condition
$year_filter_dpo = ($selected_year == 'all') ? '' : "AND YEAR(datee) = " . (int)$selected_year;
$year_filter_ticket = ($selected_year == 'all') ? '' : "AND YEAR(date_sent) = " . (int)$selected_year;

// Initialize arrays
$monthly_data = [];
$total_virtual = 0;
$total_international = 0;
$virtual_count = 0;
$international_count = 0;
$course_totals = [];
$event_totals = [];
$all_transactions = [];

// Seed month buckets exactly as the original did: it created a bucket for every
// paid DPO row and every paid ticket row *before* any course/dedup filtering.
foreach (pd_rows($conn, "SELECT DISTINCT DATE_FORMAT(datee, '%Y-%m') AS month FROM dpo_payment WHERE status = 2 $year_filter_dpo") as $r) {
    if ($r['month'] && !isset($monthly_data[$r['month']])) $monthly_data[$r['month']] = ['virtual' => 0, 'international' => 0];
}
foreach (pd_rows($conn, "SELECT DISTINCT DATE_FORMAT(date_sent, '%Y-%m') AS month FROM ticket_congress WHERE status = 2 $year_filter_ticket") as $r) {
    if ($r['month'] && !isset($monthly_data[$r['month']])) $monthly_data[$r['month']] = ['virtual' => 0, 'international' => 0];
}

// Virtual = DPO payments that map to a course. JOIN replaces the per-row course_check.
$virtual_rows = pd_rows($conn, "
    SELECT
        d.datee,
        d.email,
        d.token,
        d.TransactionAmount,
        c.course AS course_name,
        DATE_FORMAT(d.datee, '%Y-%m') as month
    FROM dpo_payment d
    JOIN course c ON d.purpose = c.course_id
    WHERE d.status = 2
    $year_filter_dpo
    ORDER BY d.datee DESC
");
$virtual_people = pd_people($conn, array_column($virtual_rows, 'email'));
foreach ($virtual_rows as $payment) {
    $amount      = floatval($payment['TransactionAmount']);
    $month       = $payment['month'];
    $course_name = $payment['course_name'];

    if(!isset($monthly_data[$month])) $monthly_data[$month] = ['virtual' => 0, 'international' => 0];

    $total_virtual += $amount;
    $virtual_count++;
    $monthly_data[$month]['virtual'] += $amount;

    if(!isset($course_totals[$course_name])) {
        $course_totals[$course_name] = ['revenue' => 0, 'count' => 0];
    }
    $course_totals[$course_name]['revenue'] += $amount;
    $course_totals[$course_name]['count']++;

    $all_transactions[] = [
        'date' => $payment['datee'],
        'email' => $payment['email'],
        'type' => 'Virtual',
        'name' => $course_name,
        'amount' => $amount,
        'confirmation' => $payment['token'],
        'fullname' => $virtual_people[$payment['email']]['name'] ?? ' ',
        'phone' => $virtual_people[$payment['email']]['phone'] ?? ' '
    ];
}

// International = ticket_congress rows NOT already recorded as a paid DPO payment.
// NOT EXISTS replaces the per-row dpo_check; LEFT JOIN Event replaces event_check.
foreach (pd_rows($conn, "
    SELECT
        t.id,
        t.fullname,
        t.email,
        t.phone_number,
        t.amount,
        t.confirmation,
        t.date_sent,
        COALESCE(e.location, 'Unknown Event') AS event_location,
        DATE_FORMAT(t.date_sent, '%Y-%m') as month
    FROM ticket_congress t
    LEFT JOIN Event e ON t.event_id = e.event_id
    WHERE t.status = 2
    AND NOT EXISTS (
        SELECT 1 FROM dpo_payment dp
        WHERE dp.token = t.confirmation AND dp.status = 2
    )
    $year_filter_ticket
    ORDER BY t.id DESC
") as $ticket) {
    $amount         = floatval($ticket['amount']);
    $month          = $ticket['month'];
    $event_location = $ticket['event_location'];

    if(!isset($monthly_data[$month])) $monthly_data[$month] = ['virtual' => 0, 'international' => 0];

    $total_international += $amount;
    $international_count++;
    $monthly_data[$month]['international'] += $amount;

    if(!isset($event_totals[$event_location])) {
        $event_totals[$event_location] = ['revenue' => 0, 'count' => 0];
    }
    $event_totals[$event_location]['revenue'] += $amount;
    $event_totals[$event_location]['count']++;

    $all_transactions[] = [
        'date' => $ticket['date_sent'],
        'email' => $ticket['email'],
        'type' => 'International',
        'name' => $event_location,
        'amount' => $amount,
        'confirmation' => $ticket['confirmation'],
        'fullname' => $ticket['fullname'],
        'phone' => $ticket['phone_number']
    ];
}

// Sort all transactions by date
usort($all_transactions, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$grand_total = $total_virtual + $total_international;
$virtual_percentage = $grand_total > 0 ? ($total_virtual / $grand_total) * 100 : 0;
$international_percentage = $grand_total > 0 ? ($total_international / $grand_total) * 100 : 0;

// Sort course and event totals
arsort($course_totals);
arsort($event_totals);
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid px-4 py-4  mt-5 pt-5">
            <!-- Page Header with Year Filter -->
            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                <h2 class="fw-bold">Financial Dashboard</h2>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted">Select Year:</span>
                    <select class="form-select" style="width: auto;" onchange="window.location.href='?year='+this.value">
                        <option value="all" <?php echo $selected_year == 'all' ? 'selected' : ''; ?>>All Years</option>
                        <?php foreach($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Currency Toggle -->
            <div class="mb-3">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="currency" id="currencyUSD" checked>
                    <label class="btn btn-outline-primary" for="currencyUSD" onclick="switchCurrency('USD')">USD ($)</label>
                    
                    <input type="radio" class="btn-check" name="currency" id="currencyKES">
                    <label class="btn btn-outline-primary" for="currencyKES" onclick="switchCurrency('KES')">KES (KSh)</label>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <!-- Total Revenue Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Total Revenue</p>
                                    <h3 class="fw-bold mb-0">
                                        <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $grand_total; ?>"><?php echo number_format($grand_total, 2); ?></span>
                                    </h3>
                                    <small class="text-muted"><?php echo $selected_year == 'all' ? 'All Time' : $selected_year; ?></small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-currency-dollar text-primary fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Virtual Revenue Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Virtual (Courses)</p>
                                    <h3 class="fw-bold mb-0">
                                        <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $total_virtual; ?>"><?php echo number_format($total_virtual, 2); ?></span>
                                    </h3>
                                    <small class="text-success"><?php echo number_format($virtual_percentage, 1); ?>% of total</small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-laptop text-success fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- International Revenue Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">International (Events)</p>
                                    <h3 class="fw-bold mb-0">
                                        <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $total_international; ?>"><?php echo number_format($total_international, 2); ?></span>
                                    </h3>
                                    <small class="text-info"><?php echo number_format($international_percentage, 1); ?>% of total</small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-globe text-info fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Transactions Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Total Transactions</p>
                                    <h3 class="fw-bold mb-0"><?php echo $virtual_count + $international_count; ?></h3>
                                    <small class="text-muted">V: <?php echo $virtual_count; ?> | I: <?php echo $international_count; ?></small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded">
                                    <i class="bi bi-receipt text-warning fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- Revenue Distribution Pie Chart -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="fw-bold mb-0">Revenue Distribution</h5>
                        </div>
                        <div class="card-body d-flex justify-content-center align-items-center">
                            <canvas id="revenueDistributionChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trend Chart -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="fw-bold mb-0">Monthly Revenue Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Tables Row -->
            <div class="row g-4 mb-4">
                <!-- Top Virtual Courses -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="fw-bold mb-0">Top Virtual Courses</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course</th>
                                            <th>Transactions</th>
                                            <th class="text-end">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $top_courses = array_slice($course_totals, 0, 5, true);
                                        if(empty($top_courses)): ?>
                                            <tr><td colspan="3" class="text-center text-muted">No virtual courses data</td></tr>
                                        <?php else:
                                        foreach($top_courses as $course => $data): 
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $data['count']; ?></span></td>
                                            <td class="text-end fw-bold">
                                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $data['revenue']; ?>"><?php echo number_format($data['revenue'], 2); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top International Events -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="fw-bold mb-0">Top International Events</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Location</th>
                                            <th>Transactions</th>
                                            <th class="text-end">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $top_events = array_slice($event_totals, 0, 5, true);
                                        if(empty($top_events)): ?>
                                            <tr><td colspan="3" class="text-center text-muted">No international events data</td></tr>
                                        <?php else:
                                        foreach($top_events as $location => $data): 
                                        ?>
                                        <tr>
                                            <td><i class="bi bi-geo-alt-fill text-info"></i> <?php echo htmlspecialchars($location); ?></td>
                                            <td><span class="badge bg-info"><?php echo $data['count']; ?></span></td>
                                            <td class="text-end fw-bold">
                                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $data['revenue']; ?>"><?php echo number_format($data['revenue'], 2); ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Tables with Tabs -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-transactions" type="button" role="tab">
                                        All Transactions
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="virtual-tab" data-bs-toggle="tab" data-bs-target="#virtual-transactions" type="button" role="tab">
                                        Virtual (Courses)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="international-tab" data-bs-toggle="tab" data-bs-target="#international-transactions" type="button" role="tab">
                                        International (Events)
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <!-- All Transactions Tab -->
                                <div class="tab-pane fade show active" id="all-transactions" role="tabpanel">
                                    <div class="table-responsive">
                                        <table  id="dataTable1" class="table table-hover table-bordered datatable-all">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-gray-200">Confirmation</th>
                                                    <th class="border-gray-200">Email</th>
                                                    <th class="border-gray-200">Fullname</th>
                                                    <th class="border-gray-200">Phone number</th>
                                                    <th class="border-gray-200">Date</th>
                                                    <th class="border-gray-200">Course/Event</th>
                                                    <th class="border-gray-200">Amount(USD)</th>
                                                    <th class="border-gray-200">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($all_transactions as $trans): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($trans['confirmation']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['fullname']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['phone']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i:s', strtotime($trans['date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['name']); ?></td>
                                                    <td>
                                                        <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $trans['amount']; ?>"><?php echo number_format($trans['amount'], 2); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if($trans['type'] == 'Virtual'): ?>
                                                            <span class="badge bg-success">Virtual</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">International</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th class="border-gray-200">Confirmation</th>
                                                    <th class="border-gray-200">Email</th>
                                                    <th class="border-gray-200">Fullname</th>
                                                    <th class="border-gray-200">Phone number</th>
                                                    <th class="border-gray-200">Date</th>
                                                    <th class="border-gray-200">Course/Event</th>
                                                    <th class="border-gray-200">Amount(USD)</th>
                                                    <th class="border-gray-200">Status</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- Virtual Transactions Tab -->
                                <div class="tab-pane fade" id="virtual-transactions" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="dataTable1" class="table table-hover table-bordered datatable-virtual">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-gray-200">Confirmation</th>
                                                    <th class="border-gray-200">Email</th>
                                                    <th class="border-gray-200">Fullname</th>
                                                    <th class="border-gray-200">Phone number</th>
                                                    <th class="border-gray-200">Date</th>
                                                    <th class="border-gray-200">Course</th>
                                                    <th class="border-gray-200">Amount(USD)</th>
                                                    <th class="border-gray-200">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $virtual_transactions = array_filter($all_transactions, function($t) {
                                                    return $t['type'] == 'Virtual';
                                                });
                                                foreach($virtual_transactions as $trans): 
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($trans['confirmation']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['fullname']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['phone']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i:s', strtotime($trans['date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['name']); ?></td>
                                                    <td>
                                                        <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $trans['amount']; ?>"><?php echo number_format($trans['amount'], 2); ?></span>
                                                    </td>
                                                    <td><span class="badge bg-success">Paid</span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th class="border-gray-200">Confirmation</th>
                                                    <th class="border-gray-200">Email</th>
                                                    <th class="border-gray-200">Fullname</th>
                                                    <th class="border-gray-200">Phone number</th>
                                                    <th class="border-gray-200">Date</th>
                                                    <th class="border-gray-200">Course</th>
                                                    <th class="border-gray-200">Amount(USD)</th>
                                                    <th class="border-gray-200">Status</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- International Transactions Tab -->
                                <div class="tab-pane fade" id="international-transactions" role="tabpanel">
                                    <div class="table-responsive">
                                        <table id="dataTable1" class="table table-hover table-bordered datatable-international">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-gray-200">Confirmation</th>
                                                    <th class="border-gray-200">Email</th>
                                                    <th class="border-gray-200">Fullname</th>
                                                    <th class="border-gray-200">Phone number</th>
                                                    <th class="border-gray-200">Date</th>
                                                    <th class="border-gray-200">Event</th>
                                                    <th class="border-gray-200">Amount(USD)</th>
                                                    <th class="border-gray-200">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $international_transactions = array_filter($all_transactions, function($t) {
                                                    return $t['type'] == 'International';
                                                });
                                                foreach($international_transactions as $trans): 
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($trans['confirmation']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['fullname']); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['phone']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i:s', strtotime($trans['date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($trans['name']); ?></td>
                                                    <td>
                                                        <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $trans['amount']; ?>"><?php echo number_format($trans['amount'], 2); ?></span>
                                                    </td>
                                                    <td><span class="badge bg-info">Paid</span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th class="border-gray-200">Confirmation</th>
                                                    <th class="border-gray-200">Email</th>
                                                    <th class="border-gray-200">Fullname</th>
                                                    <th class="border-gray-200">Phone number</th>
                                                    <th class="border-gray-200">Date</th>
                                                    <th class="border-gray-200">Event</th>
                                                    <th class="border-gray-200">Amount(USD)</th>
                                                    <th class="border-gray-200">Status</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const USD_TO_KES = <?php echo $usd_to_kes; ?>;
let currentCurrency = 'USD';
let distributionChart, trendChart;

// Currency switching function
function switchCurrency(currency) {
    currentCurrency = currency;
    const amounts = document.querySelectorAll('.amount');
    const symbols = document.querySelectorAll('.currency-symbol');
    
    amounts.forEach(amount => {
        const usdValue = parseFloat(amount.getAttribute('data-usd'));
        if (currency === 'KES') {
            const kesValue = usdValue * USD_TO_KES;
            amount.textContent = kesValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        } else {
            amount.textContent = usdValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    });
    
    symbols.forEach(symbol => {
        symbol.textContent = currency === 'KES' ? 'KSh' : '$';
    });
    
    // Update charts
    updateCharts();
}

function formatCurrency(value) {
    if (currentCurrency === 'KES') {
        return 'KSh' + (value * USD_TO_KES).toLocaleString();
    }
    return '$' + value.toLocaleString();
}

// Revenue Distribution Pie Chart
const distributionCtx = document.getElementById('revenueDistributionChart').getContext('2d');
distributionChart = new Chart(distributionCtx, {
    type: 'doughnut',
    data: {
        labels: ['Virtual (Courses)', 'International (Events)'],
        datasets: [{
            data: [<?php echo $total_virtual; ?>, <?php echo $total_international; ?>],
            backgroundColor: ['#198754', '#0dcaf0'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.parsed || 0;
                        let total = <?php echo $grand_total; ?>;
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return label + ': ' + formatCurrency(value) + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Monthly Trend Line Chart
const trendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthly_data); ?>;
const months = Object.keys(monthlyData).sort();
const virtualData = months.map(m => monthlyData[m].virtual);
const internationalData = months.map(m => monthlyData[m].international);

trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: months.map(m => {
            const date = new Date(m + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }),
        datasets: [
            {
                label: 'Virtual (Courses)',
                data: virtualData,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'International (Events)',
                data: internationalData,
                borderColor: '#0dcaf0',
                backgroundColor: 'rgba(13, 202, 240, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return formatCurrency(value);
                    }
                }
            }
        }
    }
});

function updateCharts() {
    distributionChart.update();
    trendChart.update();
}

// Initialize DataTables if available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
        $('.datatable-all, .datatable-virtual, .datatable-international').DataTable({
            order: [[4, 'desc']], // Sort by date column
            pageLength: 5,
            responsive: true
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>