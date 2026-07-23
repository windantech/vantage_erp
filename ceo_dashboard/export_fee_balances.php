<?php
// ============================================
// PDF EXPORT via BROWSER PRINT DIALOG
// Generates print-ready HTML; browser's native print saves as PDF
// No PDF library dependencies - works on any server
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
// if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
//     die('Unauthorized access');
// }

require_once '../function.php';

// Ensure DB connection (adjust path if needed)
if (!isset($conn) || !$conn) {
  require_once '../../database/conn.php'; 
}

$usd_to_kes = 129;
$currency = isset($_GET['currency']) ? $_GET['currency'] : 'USD';
$department = isset($_GET['department']) ? $_GET['department'] : 'all';
$selected_item = isset($_GET['item']) ? $_GET['item'] : 'all';
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'all';
$balance_filter = isset($_GET['balance']) ? $_GET['balance'] : 'owing';

// ============================================
// BUILD DATA
// ============================================
$balances = [];

$payments = [];
$payment_counts = [];
$pay_query = mysqli_query($conn, "
    SELECT app_id, SUM(TransactionAmount) AS total_paid, COUNT(*) AS num_payments
    FROM dpo_payment
    WHERE status = 2
    GROUP BY app_id
    HAVING SUM(TransactionAmount) > 0
") or die(mysqli_error($conn));
while($p = mysqli_fetch_assoc($pay_query)) {
    $payments[$p['app_id']] = floatval($p['total_paid']);
    $payment_counts[$p['app_id']] = intval($p['num_payments']);
}

// ---- VIRTUAL ----
if ($department == 'all' || $department == 'virtual') {
    $virtual_where = "";
    if ($selected_item != 'all' && $department == 'virtual') {
        $virtual_where .= " AND c.course_id = '" . mysqli_real_escape_string($conn, $selected_item) . "'";
    }
    if ($selected_year != 'all') {
        $yr = (int)$selected_year;
        $virtual_where .= " AND r.datee >= '$yr-01-01 00:00:00' AND r.datee < '" . ($yr + 1) . "-01-01 00:00:00'";
    }
    
    $vq = mysqli_query($conn, "
        SELECT r.entry_id, r.firstname, r.lastname, r.email, r.phone_number, r.datee AS reg_date,
               c.course AS item_name, c.price_usd AS fee, c.course_id, i.description AS intake_name
        FROM register r
        INNER JOIN intake i ON r.intake_id = i.intake_id
        INNER JOIN course c ON i.course_id = c.course_id
        WHERE 1 $virtual_where
        ORDER BY r.datee DESC
    ") or die(mysqli_error($conn));
    
    $seen = [];
    while($row = mysqli_fetch_assoc($vq)) {
        $dk = strtolower(trim($row['email'])) . '_' . $row['course_id'];
        $tp = isset($payments[$row['entry_id']]) ? $payments[$row['entry_id']] : 0;
        $num_pay = isset($payment_counts[$row['entry_id']]) ? $payment_counts[$row['entry_id']] : 0;
        
        if ($tp <= 0) continue;
        
        if (isset($seen[$dk]) && $tp <= $seen[$dk]) continue;
        if (isset($seen[$dk])) {
            foreach ($balances as $k => $b) {
                if ($b['dk'] == $dk && $b['dept'] == 'Virtual') { unset($balances[$k]); break; }
            }
        }
        $seen[$dk] = $tp;
        
        $fee = floatval($row['fee']);
        $bal = $fee - $tp;
        $pct = $fee > 0 ? round(($tp / $fee) * 100, 1) : 0;
        
        if ($bal <= 0) $st = $bal < 0 ? 'Overpaid' : 'Fully Paid';
        else $st = 'Partial';
        
        if ($balance_filter == 'owing' && $bal <= 0) continue;
        if ($balance_filter == 'paid' && $bal != 0) continue;
        if ($balance_filter == 'overpaid' && $bal >= 0) continue;
        
        $balances[] = [
            'dk' => $dk, 'dept' => 'Virtual',
            'name' => $row['firstname'] . ' ' . $row['lastname'],
            'email' => $row['email'], 'phone' => $row['phone_number'],
            'item' => $row['item_name'],
            'fee' => $fee, 'paid' => $tp, 'balance' => $bal,
            'pct' => $pct, 'status' => $st,
            'date' => $row['reg_date'],
            'installments' => $num_pay
        ];
    }
}

// ---- INTERNATIONAL ----
if ($department == 'all' || $department == 'international') {
    $intl_where = "";
    if ($selected_item != 'all' && $department == 'international') {
        $intl_where .= " AND e.event_id = '" . mysqli_real_escape_string($conn, $selected_item) . "'";
    }
    if ($selected_year != 'all') {
        $yr = (int)$selected_year;
        $intl_where .= " AND tc.date_sent >= '$yr-01-01 00:00:00' AND tc.date_sent < '" . ($yr + 1) . "-01-01 00:00:00'";
    }
    
    $iq = mysqli_query($conn, "
        SELECT tc.id, tc.ticket_id, tc.fullname, tc.email, tc.phone_number, tc.date_sent AS reg_date,
               e.event_title AS item_name, e.early_amount AS fee, e.event_id
        FROM ticket_congress tc
        INNER JOIN Event e ON tc.event_id = e.event_id
        WHERE 1 $intl_where
        ORDER BY tc.date_sent DESC
    ") or die(mysqli_error($conn));
    
    $seen_i = [];
    while($row = mysqli_fetch_assoc($iq)) {
        $dk = strtolower(trim($row['email'])) . '_evt_' . $row['event_id'];
        $tp = isset($payments[$row['ticket_id']]) ? $payments[$row['ticket_id']] : 0;
        $num_pay = isset($payment_counts[$row['ticket_id']]) ? $payment_counts[$row['ticket_id']] : 0;
        
        if ($tp <= 0) continue;
        
        if (isset($seen_i[$dk]) && $tp <= $seen_i[$dk]) continue;
        if (isset($seen_i[$dk])) {
            foreach ($balances as $k => $b) {
                if ($b['dk'] == $dk && $b['dept'] == 'International') { unset($balances[$k]); break; }
            }
        }
        $seen_i[$dk] = $tp;
        
        $fee = floatval($row['fee']);
        $bal = $fee - $tp;
        $pct = $fee > 0 ? round(($tp / $fee) * 100, 1) : 0;
        
        if ($bal <= 0) $st = $bal < 0 ? 'Overpaid' : 'Fully Paid';
        else $st = 'Partial';
        
        if ($balance_filter == 'owing' && $bal <= 0) continue;
        if ($balance_filter == 'paid' && $bal != 0) continue;
        if ($balance_filter == 'overpaid' && $bal >= 0) continue;
        
        $balances[] = [
            'dk' => $dk, 'dept' => 'International',
            'name' => $row['fullname'],
            'email' => $row['email'], 'phone' => $row['phone_number'],
            'item' => $row['item_name'],
            'fee' => $fee, 'paid' => $tp, 'balance' => $bal,
            'pct' => $pct, 'status' => $st,
            'date' => $row['reg_date'],
            'installments' => $num_pay
        ];
    }
}

$balances = array_values($balances);

$multiplier = $currency == 'KES' ? $usd_to_kes : 1;
$curr_symbol = $currency == 'KES' ? 'KES' : 'USD';

$totalFee = 0;
$totalPaid = 0;
$totalBalance = 0;
foreach ($balances as $b) {
    $totalFee += $b['fee'] * $multiplier;
    $totalPaid += $b['paid'] * $multiplier;
    $totalBalance += $b['balance'] * $multiplier;
}

$reportTitle = 'Fee Balances Report';
if ($department != 'all') $reportTitle .= ' - ' . ucfirst($department);
if ($selected_year != 'all') $reportTitle .= ' - ' . $selected_year;

$filterLabels = [
    'owing' => 'Clients with Outstanding Balance',
    'paid'  => 'Fully Paid Clients',
    'overpaid' => 'Overpaid Clients',
    'all'   => 'All Paying Clients'
];
$filterLabel = isset($filterLabels[$balance_filter]) ? $filterLabels[$balance_filter] : 'All';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function money($n) { return number_format($n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo h($reportTitle); ?></title>
<style>
    @page {
        size: A4 landscape;
        margin: 10mm 8mm;
    }
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9pt;
        color: #212529;
        background: #fff;
        padding: 15px;
    }
    
    .print-controls {
        background: #1b4f72;
        color: #fff;
        padding: 12px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .print-controls h3 { font-size: 14pt; margin: 0; }
    .print-controls .btns { display: flex; gap: 10px; }
    
    .btn {
        padding: 8px 18px;
        background: #fff;
        color: #1b4f72;
        border: none;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        font-size: 10pt;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn:hover { background: #e8f0fe; }
    .btn-secondary { background: transparent; color: #fff; border: 1px solid #fff; }
    .btn-secondary:hover { background: rgba(255,255,255,0.15); }
    
    .header {
        text-align: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #1b4f72;
    }
    
    .header h1 { color: #1b4f72; font-size: 16pt; margin-bottom: 4px; }
    .header .subtitle { font-size: 10pt; color: #555; }
    
    .meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 9pt;
        color: #555;
    }
    
    .meta-item strong { color: #1b4f72; }
    
    .summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .summary-card {
        background: #f8f9fa;
        border-left: 4px solid #1b4f72;
        padding: 8px 12px;
        border-radius: 3px;
    }
    
    .summary-card .label {
        font-size: 8pt;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .summary-card .value {
        font-size: 12pt;
        font-weight: bold;
        color: #1b4f72;
        margin-top: 2px;
    }
    
    .summary-card.danger { border-left-color: #c0392b; }
    .summary-card.danger .value { color: #c0392b; }
    .summary-card.success { border-left-color: #27ae60; }
    .summary-card.success .value { color: #27ae60; }
    
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
    }
    
    thead { background: #1b4f72; color: #fff; }
    
    th {
        padding: 8px 6px;
        text-align: left;
        font-weight: 600;
        border: 1px solid #1b4f72;
    }
    
    td {
        padding: 6px;
        border: 1px solid #dee2e6;
        vertical-align: top;
    }
    
    tbody tr:nth-child(even) { background: #f8f9fa; }
    
    .num { text-align: right; white-space: nowrap; }
    .center { text-align: center; }
    
    .status {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 7.5pt;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-partial { background: #fff3cd; color: #856404; }
    .status-paid { background: #d4edda; color: #155724; }
    .status-overpaid { background: #d1ecf1; color: #0c5460; }
    
    tfoot {
        background: #212529;
        color: #fff;
        font-weight: bold;
    }
    
    tfoot td {
        padding: 8px 6px;
        border-color: #212529;
    }
    
    .footer {
        margin-top: 15px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
        text-align: center;
        font-size: 8pt;
        color: #888;
    }
    
    .no-data {
        text-align: center;
        padding: 40px;
        color: #888;
        font-style: italic;
    }
    
    @media print {
        body { padding: 0; }
        .print-controls, .no-print { display: none !important; }
        
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }
        .summary { page-break-after: avoid; }
    }
</style>
</head>
<body>

<div class="print-controls no-print">
    <h3>📄 Fee Balances Report - Ready to Save as PDF</h3>
    <div class="btns">
        <button class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <a href="javascript:history.back()" class="btn btn-secondary">← Back</a>
    </div>
</div>

<div class="header">
    <h1>VANTAGE AFRICA SCHOOL OF LEADERSHIP</h1>
    <div class="subtitle"><?php echo h($reportTitle); ?> (<?php echo $curr_symbol; ?>)</div>
</div>

<div class="meta">
    <div class="meta-item"><strong>Filter:</strong> <?php echo h($filterLabel); ?></div>
    <div class="meta-item"><strong>Currency:</strong> <?php echo $curr_symbol; ?></div>
    <div class="meta-item"><strong>Records:</strong> <?php echo count($balances); ?></div>
    <div class="meta-item"><strong>Generated:</strong> <?php echo date('d M Y, H:i'); ?></div>
</div>

<div class="summary">
    <div class="summary-card">
        <div class="label">Total Fees</div>
        <div class="value"><?php echo $curr_symbol . ' ' . money($totalFee); ?></div>
    </div>
    <div class="summary-card success">
        <div class="label">Total Paid</div>
        <div class="value"><?php echo $curr_symbol . ' ' . money($totalPaid); ?></div>
    </div>
    <div class="summary-card danger">
        <div class="label">Outstanding</div>
        <div class="value"><?php echo $curr_symbol . ' ' . money($totalBalance); ?></div>
    </div>
    <div class="summary-card">
        <div class="label">Collection Rate</div>
        <div class="value"><?php echo $totalFee > 0 ? round(($totalPaid / $totalFee) * 100, 1) : 0; ?>%</div>
    </div>
</div>

<?php if (count($balances) == 0): ?>
    <div class="no-data">No records match the selected filters.</div>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th style="width:3%">#</th>
            <th style="width:8%">Dept</th>
            <th style="width:13%">Client Name</th>
            <th style="width:15%">Email</th>
            <th style="width:9%">Phone</th>
            <th style="width:15%">Course / Event</th>
            <th style="width:8%" class="num">Fee</th>
            <th style="width:8%" class="num">Paid</th>
            <th style="width:8%" class="num">Balance</th>
            <th style="width:5%" class="center">Paid %</th>
            <th style="width:4%" class="center">Pmts</th>
            <th style="width:7%" class="center">Status</th>
            <th style="width:7%">Reg Date</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($balances as $idx => $b): 
        $fee = $b['fee'] * $multiplier;
        $paid = $b['paid'] * $multiplier;
        $bal = $b['balance'] * $multiplier;
        
        $statusClass = 'status-partial';
        if ($b['status'] == 'Fully Paid') $statusClass = 'status-paid';
        elseif ($b['status'] == 'Overpaid') $statusClass = 'status-overpaid';
    ?>
        <tr>
            <td class="center"><?php echo $idx + 1; ?></td>
            <td><?php echo h($b['dept']); ?></td>
            <td><?php echo h($b['name']); ?></td>
            <td style="word-break: break-all;"><?php echo h($b['email']); ?></td>
            <td><?php echo h($b['phone']); ?></td>
            <td><?php echo h($b['item']); ?></td>
            <td class="num"><?php echo money($fee); ?></td>
            <td class="num"><?php echo money($paid); ?></td>
            <td class="num"><strong><?php echo money($bal); ?></strong></td>
            <td class="center"><?php echo $b['pct']; ?>%</td>
            <td class="center"><?php echo $b['installments']; ?></td>
            <td class="center"><span class="status <?php echo $statusClass; ?>"><?php echo h($b['status']); ?></span></td>
            <td><?php echo $b['date'] ? date('d M Y', strtotime($b['date'])) : '-'; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="center">TOTALS</td>
            <td class="num"><?php echo money($totalFee); ?></td>
            <td class="num"><?php echo money($totalPaid); ?></td>
            <td class="num"><?php echo money($totalBalance); ?></td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>

<?php endif; ?>

<div class="footer">
    VASL Finance Module &middot; Generated <?php echo date('d M Y \a\t H:i'); ?> &middot; 
    Exchange Rate: 1 USD = <?php echo $usd_to_kes; ?> KES
</div>

<script>
    // Auto-trigger print dialog once page is fully loaded
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 500);
    });
</script>

</body>
</html>