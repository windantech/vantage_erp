<?php 
require_once 'header.php'; 
include '../function.php';

// Get selected year and month (default to 'all')
$selected_year  = isset($_GET['year'])  ? $_GET['year']  : 'all';
$selected_month = isset($_GET['month']) ? $_GET['month'] : 'all';

// Currency conversion rate
$usd_to_kes = 129;

// ── Lightweight query helpers ────────────────────────────────────────────────
// Run a query and return all rows as an array. On failure it logs and returns []
// instead of killing the whole page (replaces the old "or die(mysqli_error())").
function inc_rows($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if ($res === false) { error_log('[income.php] SQL failed: ' . mysqli_error($conn)); return []; }
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) { $out[] = $row; }
    if ($res instanceof mysqli_result) { mysqli_free_result($res); }
    return $out;
}
// Batch customer lookup — resolves name+phone for MANY emails in 2 queries total,
// replacing the per-row check_name()/check_phone() (which each hit register then
// ticket_congress). Same rule: register wins; else ticket_congress; else " ".
// Returns [email => ['name'=>.., 'phone'=>..]].
function inc_people($conn, array $emails) {
    $map = [];
    $emails = array_values(array_unique(array_filter($emails, fn($e) => $e !== null && $e !== '')));
    if (empty($emails)) return $map;
    foreach (array_chunk($emails, 500) as $chunk) {
        $in = implode(',', array_map(fn($e) => "'" . mysqli_real_escape_string($conn, $e) . "'", $chunk));
        foreach (inc_rows($conn, "SELECT email, firstname, lastname, phone_number FROM register WHERE email IN ($in)") as $r) {
            if (!isset($map[$r['email']])) {
                $map[$r['email']] = [
                    'name'  => ucfirst(strtolower($r['firstname'] . ' ' . $r['lastname'])),
                    'phone' => $r['phone_number'],
                ];
            }
        }
    }
    $missing = array_values(array_diff($emails, array_keys($map)));
    foreach (array_chunk($missing, 500) as $chunk) {
        $in = implode(',', array_map(fn($e) => "'" . mysqli_real_escape_string($conn, $e) . "'", $chunk));
        foreach (inc_rows($conn, "SELECT email, fullname, phone_number FROM ticket_congress WHERE email IN ($in)") as $r) {
            if (!isset($map[$r['email']])) {
                $map[$r['email']] = [
                    'name'  => ucfirst(strtolower($r['fullname'])),
                    'phone' => $r['phone_number'],
                ];
            }
        }
    }
    return $map;
}

// Get available years from all sources
$available_years = [];
foreach (inc_rows($conn, "
    SELECT DISTINCT YEAR(datee) as year FROM dpo_payment WHERE status = 2
    UNION
    SELECT DISTINCT YEAR(date_sent) as year FROM ticket_congress WHERE status = 2
    UNION
    SELECT DISTINCT YEAR(income_date) as year FROM custom_income WHERE 1
    ORDER BY year DESC
") as $year_row) {
    if ($year_row['year']) { $available_years[] = $year_row['year']; }
}

// Month names for display
$month_names = [
    1=>'January', 2=>'February', 3=>'March',    4=>'April',
    5=>'May',     6=>'June',     7=>'July',      8=>'August',
    9=>'September',10=>'October',11=>'November', 12=>'December'
];

// Build year + month filter conditions per table
$year_filter_dpo    = ($selected_year  == 'all') ? '' : "AND YEAR(datee)       = " . (int)$selected_year;
$year_filter_ticket = ($selected_year  == 'all') ? '' : "AND YEAR(date_sent)   = " . (int)$selected_year;
$year_filter_custom = ($selected_year  == 'all') ? '' : "AND YEAR(income_date) = " . (int)$selected_year;

$month_filter_dpo    = ($selected_month == 'all') ? '' : "AND MONTH(datee)       = " . (int)$selected_month;
$month_filter_ticket = ($selected_month == 'all') ? '' : "AND MONTH(date_sent)   = " . (int)$selected_month;
$month_filter_custom = ($selected_month == 'all') ? '' : "AND MONTH(income_date) = " . (int)$selected_month;

$filter_dpo    = $year_filter_dpo    . ' ' . $month_filter_dpo;
$filter_ticket = $year_filter_ticket . ' ' . $month_filter_ticket;
$filter_custom = $year_filter_custom . ' ' . $month_filter_custom;

// Initialize totals
$total_collected          = 0;
$total_expected           = 0;
$total_balance            = 0;
$virtual_collected        = 0;
$virtual_expected         = 0;
$international_collected  = 0;
$international_expected   = 0;
$custom_income_total      = 0;
$monthly_data             = [];
$payment_details          = [];

// ── Virtual (Course) Payments ─────────────────────────────────────────────────
$dpo_rows = inc_rows($conn, "
    SELECT
        d.special_id,
        d.purpose,
        d.TransactionAmount,
        d.datee,
        d.email,
        d.token,
        c.course,
        c.price_usd,
        DATE_FORMAT(d.datee, '%Y-%m') as month
    FROM dpo_payment d
    JOIN course c ON d.purpose = c.course_id
    WHERE d.status = 2
    AND d.TransactionAmount > 0
    $filter_dpo
    ORDER BY d.datee DESC
");

$dpo_people = inc_people($conn, array_column($dpo_rows, 'email'));
foreach ($dpo_rows as $payment) {
    $amount_paid = floatval($payment['TransactionAmount']);

    $expected_amount = floatval($payment['price_usd']);
    $balance = $expected_amount - $amount_paid;
    $month   = $payment['month'];

    $virtual_collected += $amount_paid;
    $virtual_expected  += $expected_amount;

    if(!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['virtual_collected'=>0,'virtual_expected'=>0,'international_collected'=>0,'international_expected'=>0,'custom'=>0];
    }
    $monthly_data[$month]['virtual_collected'] += $amount_paid;
    $monthly_data[$month]['virtual_expected']  += $expected_amount;

    $payment_details[] = [
        'type'         => 'Virtual',
        'date'         => $payment['datee'],
        'email'        => $payment['email'],
        'fullname'     => $dpo_people[$payment['email']]['name'] ?? ' ',
        'phone'        => $dpo_people[$payment['email']]['phone'] ?? ' ',
        'item'         => $payment['course'],
        'expected'     => $expected_amount,
        'paid'         => $amount_paid,
        'balance'      => $balance,
        'confirmation' => $payment['token']
    ];
}
unset($dpo_rows);

// ── International (Event) Payments ───────────────────────────────────────────
// NOT EXISTS replaces the old per-row dpo_check query: keep a ticket only when its
// confirmation is NOT already recorded as a paid (status=2) dpo_payment. Same result,
// one query instead of one-per-ticket.
$ticket_rows = inc_rows($conn, "
    SELECT
        t.id,
        t.fullname,
        t.email,
        t.phone_number,
        t.event_id,
        t.amount,
        t.confirmation,
        t.date_sent,
        e.location,
        e.early_amount,
        DATE_FORMAT(t.date_sent, '%Y-%m') as month
    FROM ticket_congress t
    LEFT JOIN Event e ON t.event_id = e.event_id
    WHERE t.status = 2
    AND t.amount > 0
    AND NOT EXISTS (
        SELECT 1 FROM dpo_payment dp
        WHERE dp.token = t.confirmation AND dp.status = 2
    )
    $filter_ticket
    ORDER BY t.id DESC
");

foreach ($ticket_rows as $ticket) {
    $amount_paid     = floatval($ticket['amount']);
    $expected_amount = floatval($ticket['early_amount']);
    $balance = $expected_amount - $amount_paid;
    $month   = $ticket['month'];

    $international_collected += $amount_paid;
    $international_expected  += $expected_amount;

    if(!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['virtual_collected'=>0,'virtual_expected'=>0,'international_collected'=>0,'international_expected'=>0,'custom'=>0];
    }
    $monthly_data[$month]['international_collected'] += $amount_paid;
    $monthly_data[$month]['international_expected']  += $expected_amount;

    $payment_details[] = [
        'type'         => 'International',
        'date'         => $ticket['date_sent'],
        'email'        => $ticket['email'],
        'fullname'     => $ticket['fullname'],
        'phone'        => $ticket['phone_number'],
        'item'         => $ticket['location'],
        'expected'     => $expected_amount,
        'paid'         => $amount_paid,
        'balance'      => $balance,
        'confirmation' => $ticket['confirmation']
    ];
}
unset($ticket_rows);

// ── Custom Income ─────────────────────────────────────────────────────────────
foreach (inc_rows($conn, "
    SELECT
        income_id,
        income_source,
        amount,
        income_date,
        description,
        reference_number,
        DATE_FORMAT(income_date, '%Y-%m') as month
    FROM custom_income
    WHERE amount > 0 $filter_custom
    ORDER BY income_date DESC
") as $custom) {
    $amount = floatval($custom['amount']);

    $month = $custom['month'];
    $custom_income_total += $amount;

    if(!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['virtual_collected'=>0,'virtual_expected'=>0,'international_collected'=>0,'international_expected'=>0,'custom'=>0];
    }
    $monthly_data[$month]['custom'] += $amount;

    $payment_details[] = [
        'type'         => 'Custom',
        'date'         => $custom['income_date'],
        'email'        => '-',
        'fullname'     => $custom['income_source'],
        'phone'        => '-',
        'item'         => $custom['description'],
        'expected'     => $amount,
        'paid'         => $amount,
        'balance'      => 0,
        'confirmation' => $custom['reference_number']
    ];
}

// ── Totals ────────────────────────────────────────────────────────────────────
$total_collected = $virtual_collected + $international_collected + $custom_income_total;
$total_expected  = $virtual_expected  + $international_expected  + $custom_income_total;
$total_balance   = $total_expected - $total_collected;

usort($payment_details, fn($a,$b) => strtotime($b['date']) - strtotime($a['date']));
ksort($monthly_data);
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid px-4 py-4 mt-5 pt-5">
            <!-- Page Header with Filters -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Income Dashboard</h2>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomIncomeModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Custom Income
                    </button>

                    <!-- Year filter -->
                    <span class="text-muted">Year:</span>
                    <select class="form-select" style="width: auto;" id="yearSelect">
                        <option value="all" <?php echo $selected_year == 'all' ? 'selected' : ''; ?>>All Years</option>
                        <?php foreach($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Month filter -->
                    <span class="text-muted">Month:</span>
                    <select class="form-select" style="width: auto;" id="monthSelect">
                        <option value="all" <?php echo $selected_month == 'all' ? 'selected' : ''; ?>>All Months</option>
                        <?php foreach($month_names as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $selected_month == $num ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Currency Toggle -->
            <div class="mb-4">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="currency" id="currencyUSD" checked>
                    <label class="btn btn-outline-primary" for="currencyUSD" onclick="switchCurrency('USD')">USD ($)</label>
                    
                    <input type="radio" class="btn-check" name="currency" id="currencyKES">
                    <label class="btn btn-outline-primary" for="currencyKES" onclick="switchCurrency('KES')">KES (KSh)</label>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="text-muted mb-0">Total Collected</h6>
                                <i class="bi bi-cash-stack text-success fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $total_collected; ?>"><?php echo number_format($total_collected, 2); ?></span>
                            </h3>
                            <small class="text-muted">Total revenue received</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="text-muted mb-0">Expected Income</h6>
                                <i class="bi bi-target text-primary fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $total_expected; ?>"><?php echo number_format($total_expected, 2); ?></span>
                            </h3>
                            <small class="text-muted">Total potential revenue</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="text-muted mb-0">Uncollected</h6>
                                <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0 text-warning">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $total_balance; ?>"><?php echo number_format($total_balance, 2); ?></span>
                            </h3>
                            <small class="text-muted">Outstanding balances</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="text-muted mb-0">Collection Rate</h6>
                                <i class="bi bi-percent text-info fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0">
                                <?php echo $total_expected > 0 ? number_format(($total_collected / $total_expected) * 100, 1) : 0; ?>%
                            </h3>
                            <small class="text-muted">Revenue collection efficiency</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Income Breakdown by Source -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">Virtual (Courses)</h6>
                            <div class="mb-2">
                                <small class="text-muted">Collected:</small>
                                <div class="fw-bold"><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $virtual_collected; ?>"><?php echo number_format($virtual_collected, 2); ?></span></div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Expected:</small>
                                <div class="fw-bold"><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $virtual_expected; ?>"><?php echo number_format($virtual_expected, 2); ?></span></div>
                            </div>
                            <div>
                                <small class="text-muted">Balance:</small>
                                <div class="fw-bold text-warning"><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $virtual_expected - $virtual_collected; ?>"><?php echo number_format($virtual_expected - $virtual_collected, 2); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">International (Events)</h6>
                            <div class="mb-2">
                                <small class="text-muted">Collected:</small>
                                <div class="fw-bold"><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $international_collected; ?>"><?php echo number_format($international_collected, 2); ?></span></div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Expected:</small>
                                <div class="fw-bold"><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $international_expected; ?>"><?php echo number_format($international_expected, 2); ?></span></div>
                            </div>
                            <div>
                                <small class="text-muted">Balance:</small>
                                <div class="fw-bold text-warning"><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $international_expected - $international_collected; ?>"><?php echo number_format($international_expected - $international_collected, 2); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">Custom Income</h6>
                            <div class="mb-2">
                                <small class="text-muted">Total:</small>
                                <div class="fw-bold"><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $custom_income_total; ?>"><?php echo number_format($custom_income_total, 2); ?></span></div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Sources:</small>
                                <div class="fw-bold">Other Revenue</div>
                            </div>
                            <div>
                                <small class="text-muted">Balance:</small>
                                <div class="fw-bold text-success">$0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Trend Chart -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Monthly Income Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendChart" style="max-height: 400px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Transactions Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">All Income Transactions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="incomeTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Item</th>
                                    <th>Expected</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Confirmation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($payment_details as $detail): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($detail['date'])); ?></td>
                                    <td>
                                        <?php if($detail['type'] == 'Virtual'): ?>
                                            <span class="badge bg-success">Virtual</span>
                                        <?php elseif($detail['type'] == 'International'): ?>
                                            <span class="badge bg-info">International</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Custom</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($detail['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($detail['email']); ?></td>
                                    <td><?php echo htmlspecialchars($detail['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($detail['item']); ?></td>
                                    <td><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $detail['expected']; ?>"><?php echo number_format($detail['expected'], 2); ?></span></td>
                                    <td><span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $detail['paid']; ?>"><?php echo number_format($detail['paid'], 2); ?></span></td>
                                    <td>
                                        <span class="<?php echo $detail['balance'] > 0 ? 'text-warning' : 'text-success'; ?>">
                                            <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $detail['balance']; ?>"><?php echo number_format($detail['balance'], 2); ?></span>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($detail['confirmation']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add Custom Income Modal -->
<div class="modal fade" id="addCustomIncomeModal" tabindex="-1" aria-labelledby="addCustomIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCustomIncomeModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add Custom Income
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="customIncomeForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="incomeSource" class="form-label">Income Source <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="incomeSource" name="income_source" required placeholder="e.g., Consulting, Donations, Partnership">
                        </div>
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (USD) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="incomeDate" class="form-label">Income Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="incomeDate" name="income_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="paymentMethod" class="form-label">Payment Method</label>
                            <select class="form-select" id="paymentMethod" name="payment_method">
                                <option value="">Select method</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Wire Transfer">Wire Transfer</option>
                                <option value="Check">Check</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Cash">Cash</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="referenceNumber" class="form-label">Reference/Transaction Number</label>
                            <input type="text" class="form-control" id="referenceNumber" name="reference_number" placeholder="e.g., TRX-2024-001">
                        </div>
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Brief description of this income"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any additional information"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Income
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const USD_TO_KES = <?php echo $usd_to_kes; ?>;
let currentCurrency = 'USD';
let monthlyChart;

const monthlyData = <?php echo json_encode($monthly_data); ?>;

// ── Filter selects ────────────────────────────────────────────────────────────
document.getElementById('yearSelect').addEventListener('change', applyFilters);
document.getElementById('monthSelect').addEventListener('change', applyFilters);

function applyFilters() {
    const year  = document.getElementById('yearSelect').value;
    const month = document.getElementById('monthSelect').value;
    const params = new URLSearchParams();
    if (year  !== 'all') params.set('year',  year);
    if (month !== 'all') params.set('month', month);
    window.location.href = '?' + params.toString();
}

// ── Currency ──────────────────────────────────────────────────────────────────
function switchCurrency(currency) {
    currentCurrency = currency;
    document.querySelectorAll('.amount').forEach(el => {
        const usd = parseFloat(el.getAttribute('data-usd'));
        el.textContent = (currency === 'KES' ? usd * USD_TO_KES : usd)
            .toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    });
    document.querySelectorAll('.currency-symbol').forEach(el => {
        el.textContent = currency === 'KES' ? 'KSh' : '$';
    });
    updateChart();
}

// ── Monthly trend chart ───────────────────────────────────────────────────────
function initChart() {
    const ctx    = document.getElementById('monthlyTrendChart').getContext('2d');
    const labels = Object.keys(monthlyData);
    const totalExpected = labels.map(m =>
        monthlyData[m].virtual_expected + monthlyData[m].international_expected + monthlyData[m].custom
    );

    monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Virtual (Collected)',
                    data: labels.map(m => monthlyData[m].virtual_collected),
                    backgroundColor: 'rgba(40,167,69,.8)',
                    borderColor: 'rgba(40,167,69,1)',
                    borderWidth: 1
                },
                {
                    label: 'International (Collected)',
                    data: labels.map(m => monthlyData[m].international_collected),
                    backgroundColor: 'rgba(23,162,184,.8)',
                    borderColor: 'rgba(23,162,184,1)',
                    borderWidth: 1
                },
                {
                    label: 'Custom Income',
                    data: labels.map(m => monthlyData[m].custom),
                    backgroundColor: 'rgba(108,117,125,.8)',
                    borderColor: 'rgba(108,117,125,1)',
                    borderWidth: 1
                },
                {
                    label: 'Expected Total',
                    data: totalExpected,
                    type: 'line',
                    borderColor: 'rgba(255,193,7,1)',
                    backgroundColor: 'rgba(255,193,7,.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: { callback(v) {
                        return currentCurrency === 'KES'
                            ? 'KSh' + (v * USD_TO_KES).toLocaleString()
                            : '$'   + v.toLocaleString();
                    }}
                }
            },
            plugins: { tooltip: { callbacks: { label(ctx) {
                let lbl = (ctx.dataset.label || '') + ': ';
                const v = ctx.parsed.y;
                lbl += currentCurrency === 'KES'
                    ? 'KSh' + (v * USD_TO_KES).toLocaleString(undefined, {minimumFractionDigits:2})
                    : '$'   + v.toLocaleString(undefined, {minimumFractionDigits:2});
                return lbl;
            }}}}
        }
    });
}

function updateChart() {
    if (monthlyChart) monthlyChart.update();
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    initChart();

    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
        $('#incomeTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 5,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-2"lB>frtip',
            buttons: [{
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel me-1"></i> Export to Excel',
                className: 'btn btn-success btn-sm',
                title: 'Income_' + new Date().toISOString().slice(0, 10),
                exportOptions: {
                    columns: ':visible',
                    search: 'applied',
                    order: 'applied'
                }
            }]
        });
    }

    // Default datetime to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('incomeDate').value = now.toISOString().slice(0, 16);
});

// ── Save custom income ────────────────────────────────────────────────────────
document.getElementById('customIncomeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData  = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const origText  = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    fetch('save_custom_income.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Custom income added successfully!');
                bootstrap.Modal.getInstance(document.getElementById('addCustomIncomeModal')).hide();
                document.getElementById('customIncomeForm').reset();
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to save income'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
            }
        })
        .catch(() => {
            alert('An error occurred while saving. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
        });
});
</script>

<?php require_once 'footer.php'; ?>