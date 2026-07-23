<?php 
require_once 'header.php'; 
include '../function.php';

// Get selected year and month (default to 'all')
$selected_year  = isset($_GET['year'])  ? $_GET['year']  : 'all';
$selected_month = isset($_GET['month']) ? $_GET['month'] : 'all';

// Currency conversion rate
$usd_to_kes = 129;

// Run a query and return all rows; on failure log and return [] (no page crash / raw error leak).
function exp_rows($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if ($res === false) { error_log('[expenses.php] SQL failed: ' . mysqli_error($conn)); return []; }
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) { $out[] = $row; }
    if ($res instanceof mysqli_result) { mysqli_free_result($res); }
    return $out;
}

// Get available years from expenses table
$available_years = [];
foreach (exp_rows($conn, "
    SELECT DISTINCT YEAR(expense_date) as year
    FROM expenses
    WHERE 1
    ORDER BY year DESC
") as $year_row) {
    if ($year_row['year']) { $available_years[] = $year_row['year']; }
}

// Build year + month filter condition
$year_filter  = ($selected_year  == 'all') ? '' : "AND YEAR(expense_date)  = " . (int)$selected_year;
$month_filter = ($selected_month == 'all') ? '' : "AND MONTH(expense_date) = " . (int)$selected_month;
$date_filter  = $year_filter . ' ' . $month_filter;

// Month names for display
$month_names = [
    1=>'January', 2=>'February', 3=>'March',    4=>'April',
    5=>'May',     6=>'June',     7=>'July',      8=>'August',
    9=>'September',10=>'October',11=>'November', 12=>'December'
];

// Get expense categories totals
$category_totals = [];
foreach (exp_rows($conn, "
    SELECT
        category,
        SUM(amount) as total_amount,
        COUNT(*) as count
    FROM expenses
    WHERE 1 $date_filter
    GROUP BY category
    ORDER BY total_amount DESC
") as $cat) {
    $category_totals[$cat['category']] = [
        'total' => floatval($cat['total_amount']),
        'count' => intval($cat['count'])
    ];
}

// Get all expenses
$all_expenses = [];
$monthly_data = [];
$total_expenses = 0;

foreach (exp_rows($conn, "
    SELECT
        expense_id,
        category,
        expense_name,
        amount,
        expense_date,
        description,
        payment_method,
        reference_number,
        vendor_supplier,
        notes,
        recorded_by,
        DATE_FORMAT(expense_date, '%Y-%m') as month
    FROM expenses
    WHERE 1 $date_filter
    ORDER BY expense_date DESC
") as $expense) {
    $amount   = floatval($expense['amount']);
    $month    = $expense['month'];
    $category = $expense['category'];
    
    $total_expenses += $amount;
    
    if(!isset($monthly_data[$month])) {
        $monthly_data[$month] = [];
    }
    if(!isset($monthly_data[$month][$category])) {
        $monthly_data[$month][$category] = 0;
    }
    $monthly_data[$month][$category] += $amount;
    
    $all_expenses[] = [
        'expense_id'       => $expense['expense_id'],
        'category'         => $expense['category'],
        'expense_name'     => $expense['expense_name'],
        'amount'           => $amount,
        'expense_date'     => $expense['expense_date'],
        'description'      => $expense['description'],
        'payment_method'   => $expense['payment_method'],
        'reference_number' => $expense['reference_number'],
        'vendor_supplier'  => $expense['vendor_supplier'],
        'notes'            => $expense['notes'],
        'recorded_by'      => $expense['recorded_by']
    ];
}

// Calculate average monthly expense
$months_count        = count($monthly_data);
$avg_monthly_expense = $months_count > 0 ? $total_expenses / $months_count : 0;
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid px-4 py-4 mt-5 pt-5">
            <!-- Page Header with Filters -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Expenses Dashboard</h2>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Expense
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
                                <h6 class="text-muted mb-0">Total Expenses</h6>
                                <i class="bi bi-wallet2 text-danger fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0 text-danger">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $total_expenses; ?>"><?php echo number_format($total_expenses, 2); ?></span>
                            </h3>
                            <small class="text-muted">Total spent</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="text-muted mb-0">Avg Monthly</h6>
                                <i class="bi bi-graph-up text-info fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0">
                                <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $avg_monthly_expense; ?>"><?php echo number_format($avg_monthly_expense, 2); ?></span>
                            </h3>
                            <small class="text-muted">Average per month</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="text-muted mb-0">Categories</h6>
                                <i class="bi bi-tags text-warning fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0">
                                <?php echo count($category_totals); ?>
                            </h3>
                            <small class="text-muted">Expense categories</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="text-muted mb-0">Transactions</h6>
                                <i class="bi bi-receipt text-secondary fs-4"></i>
                            </div>
                            <h3 class="fw-bold mb-0">
                                <?php echo count($all_expenses); ?>
                            </h3>
                            <small class="text-muted">Total expenses</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Breakdown -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Expenses by Category</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Top Categories</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php 
                                $count = 0;
                                foreach($category_totals as $category => $data): 
                                    if($count >= 5) break;
                                    $percentage = $total_expenses > 0 ? ($data['total'] / $total_expenses) * 100 : 0;
                                ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold"><?php echo htmlspecialchars($category); ?></span>
                                        <span class="badge bg-danger">
                                            <span class="currency-symbol">$</span><span class="amount" data-usd="<?php echo $data['total']; ?>"><?php echo number_format($data['total'], 2); ?></span>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $data['count']; ?> transactions (<?php echo number_format($percentage, 1); ?>%)</small>
                                </div>
                                <?php 
                                    $count++;
                                endforeach; 
                                ?>
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
                            <h5 class="mb-0">Monthly Expenses Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendChart" style="max-height: 400px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Expenses Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">All Expenses</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="expensesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Expense Name</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 0;
                                foreach ($all_expenses as $expense):
                                    if ($count++ >= 5000) break;
                                ?>
                                <tr>
                                    <td><?= date('Y-m-d H:i', strtotime($expense['expense_date'])); ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($expense['category']); ?></span></td>
                                    <td><?= htmlspecialchars($expense['expense_name']); ?></td>
                                    <td class="text-danger fw-bold">
                                        <span class="currency-symbol">$</span>
                                        <span class="amount" data-usd="<?= $expense['amount']; ?>">
                                            <?= number_format($expense['amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($expense['payment_method'] ?: '-'); ?></td>
                                    <td><?= htmlspecialchars($expense['reference_number'] ?: '-'); ?></td>
                                    <td>
                                        <small>
                                            <?= htmlspecialchars(substr($expense['description'], 0, 100)); ?>
                                            <?= strlen($expense['description']) > 100 ? '...' : ''; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewExpense(<?= $expense['expense_id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
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

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="addExpenseModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add New Expense
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="expenseForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select category</option>
                                <option value="Salaries & Wages">Salaries & Wages</option>
                                <option value="Rent & Utilities">Rent & Utilities</option>
                                <option value="Marketing & Advertising">Marketing & Advertising</option>
                                <option value="Office Supplies">Office Supplies</option>
                                <option value="Equipment & Technology">Equipment & Technology</option>
                                <option value="Travel & Transportation">Travel & Transportation</option>
                                <option value="Professional Services">Professional Services</option>
                                <option value="Insurance">Insurance</option>
                                <option value="Training & Development">Training & Development</option>
                                <option value="Subscriptions & Software">Subscriptions & Software</option>
                                <option value="Maintenance & Repairs">Maintenance & Repairs</option>
                                <option value="Taxes & Licenses">Taxes & Licenses</option>
                                <option value="Communication">Communication</option>
                                <option value="Bank Fees">Bank Fees</option>
                                <option value="Miscellaneous">Miscellaneous</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="expenseName" class="form-label">Expense Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="expenseName" name="expense_name" required placeholder="e.g., Office Rent Payment">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (USD) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="expenseDate" class="form-label">Expense Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="expenseDate" name="expense_date" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="paymentMethod" class="form-label">Payment Method</label>
                            <select class="form-select" id="paymentMethod" name="payment_method">
                                <option value="">Select method</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Check">Check</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="vendorSupplier" class="form-label">Vendor/Supplier</label>
                            <input type="text" class="form-control" id="vendorSupplier" name="vendor_supplier" placeholder="e.g., ABC Company Ltd">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="referenceNumber" class="form-label">Reference/Invoice Number</label>
                            <input type="text" class="form-control" id="referenceNumber" name="reference_number" placeholder="e.g., INV-2024-001">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Brief description of this expense"></textarea>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any additional information"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save me-2"></i>Save Expense
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
let categoryChart, monthlyChart;

const categoryData = <?php echo json_encode($category_totals); ?>;
const monthlyData  = <?php echo json_encode($monthly_data); ?>;

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
    updateCharts();
}

// ── Category chart ────────────────────────────────────────────────────────────
function initCategoryChart() {
    const ctx    = document.getElementById('categoryChart').getContext('2d');
    const labels = Object.keys(categoryData);
    const data   = labels.map(cat => categoryData[cat].total);
    const colors = [
        'rgba(220,53,69,.8)','rgba(255,193,7,.8)','rgba(13,110,253,.8)',
        'rgba(25,135,84,.8)','rgba(108,117,125,.8)','rgba(255,99,132,.8)',
        'rgba(54,162,235,.8)','rgba(255,206,86,.8)','rgba(75,192,192,.8)',
        'rgba(153,102,255,.8)'
    ];
    categoryChart = new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
                tooltip: { callbacks: { label(ctx) {
                    const pct = ((ctx.parsed / ctx.dataset.data.reduce((a,b)=>a+b,0))*100).toFixed(1);
                    const val = currentCurrency==='KES'
                        ? 'KSh'+(ctx.parsed*USD_TO_KES).toLocaleString(undefined,{minimumFractionDigits:2})
                        : '$'+ctx.parsed.toLocaleString(undefined,{minimumFractionDigits:2});
                    return ctx.label+': '+val+' ('+pct+'%)';
                }}}
            }
        }
    });
}

// ── Monthly trend chart ───────────────────────────────────────────────────────
function initMonthlyChart() {
    const ctx        = document.getElementById('monthlyTrendChart').getContext('2d');
    const months     = Object.keys(monthlyData).sort();
    const categories = [...new Set(Object.values(monthlyData).flatMap(m => Object.keys(m)))];
    const colors     = [
        'rgba(220,53,69,.8)','rgba(255,193,7,.8)','rgba(13,110,253,.8)',
        'rgba(25,135,84,.8)','rgba(108,117,125,.8)','rgba(255,99,132,.8)',
        'rgba(54,162,235,.8)','rgba(255,206,86,.8)','rgba(75,192,192,.8)',
        'rgba(153,102,255,.8)'
    ];
    const datasets = categories.map((cat, i) => ({
        label: cat,
        data: months.map(m => monthlyData[m][cat] || 0),
        backgroundColor: colors[i % colors.length],
        borderColor:     colors[i % colors.length],
        borderWidth: 1
    }));
    monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: months, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { callback(v) {
                    return currentCurrency==='KES' ? 'KSh'+(v*USD_TO_KES).toLocaleString() : '$'+v.toLocaleString();
                }}}
            },
            plugins: { tooltip: { callbacks: { label(ctx) {
                let lbl = (ctx.dataset.label||'')+': ';
                const v = ctx.parsed.y;
                lbl += currentCurrency==='KES'
                    ? 'KSh'+(v*USD_TO_KES).toLocaleString(undefined,{minimumFractionDigits:2})
                    : '$'+v.toLocaleString(undefined,{minimumFractionDigits:2});
                return lbl;
            }}}}
        }
    });
}

function updateCharts() {
    if (categoryChart) categoryChart.update();
    if (monthlyChart)  monthlyChart.update();
}

function viewExpense(id) {
    alert('View expense details for ID: ' + id);
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    initCategoryChart();
    initMonthlyChart();

    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
        $('#expensesTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 5,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-2"lB>frtip',
            buttons: [{
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel me-1"></i> Export to Excel',
                className: 'btn btn-success btn-sm',
                title: 'Expenses_' + new Date().toISOString().slice(0, 10),
                exportOptions: {
                    columns: ':not(:last-child)',
                    search: 'applied',
                    order: 'applied'
                }
            }]
        });
    }

    // Default datetime to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('expenseDate').value = now.toISOString().slice(0, 16);
});

// ── Save expense ──────────────────────────────────────────────────────────────
document.getElementById('expenseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData  = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const origText  = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    fetch('save_expense.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Expense added successfully!');
                bootstrap.Modal.getInstance(document.getElementById('addExpenseModal')).hide();
                document.getElementById('expenseForm').reset();
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to save expense'));
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