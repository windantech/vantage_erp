<?php
session_start();
require '../database/conn.php';
require_once 'includes/enquiry_functions.php';

// Currency conversion rate
define('USD_TO_KES_RATE', 129);

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    die('Access Denied');
}

// Get parameters
$export_type = isset($_GET['type']) ? $_GET['type'] : 'excel';
$date_range = isset($_GET['range']) ? $_GET['range'] : 'this_month';
$staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Check permissions
$is_admin_user = is_admin($conn);
$is_mgr = is_manager($conn);
$is_dept_head = is_department_head($conn);
$current_staff_id = intval($_SESSION['login_id']);

// Determine access level
$access_level = 'staff';
if ($is_admin_user) $access_level = 'admin';
elseif ($is_mgr) $access_level = 'manager';
elseif ($is_dept_head) $access_level = 'department_head';

// Build permission filters
$register_filter = '';
$ticket_filter = '';

switch ($access_level) {
    case 'admin':
    case 'manager':
        if ($staff_filter) {
            $register_filter = " AND r.assigned_to = $staff_filter";
            $ticket_filter = " AND t.assigned_to = $staff_filter";
        }
        break;
    case 'department_head':
        $staff_ids = get_department_staff_ids($conn);
        if (!empty($staff_ids)) {
            $ids = implode(',', array_map('intval', $staff_ids));
            if ($staff_filter && in_array($staff_filter, $staff_ids)) {
                $register_filter = " AND r.assigned_to = $staff_filter";
                $ticket_filter = " AND t.assigned_to = $staff_filter";
            } else {
                $register_filter = " AND r.assigned_to IN ($ids)";
                $ticket_filter = " AND t.assigned_to IN ($ids)";
            }
        } else {
            $register_filter = " AND r.assigned_to = $current_staff_id";
            $ticket_filter = " AND t.assigned_to = $current_staff_id";
        }
        break;
    default:
        $register_filter = " AND r.assigned_to = $current_staff_id";
        $ticket_filter = " AND t.assigned_to = $current_staff_id";
}

// Fetch data
// --- VIRTUAL COURSES ---
$virtual_data = [];
$q = "SELECT r.entry_id, CONCAT(r.firstname, ' ', r.lastname) AS name, r.email, r.phone_number, 
      r.country, c.course AS course_name, r.datee AS date_registered,
      COALESCE(p.TransactionAmount, 0) AS amount_usd,
      ru.fullname AS assigned_to
      FROM register r
      LEFT JOIN course c ON (r.program = c.id OR r.program = c.course_id)
      LEFT JOIN dpo_payment p ON r.entry_id = p.app_id
      LEFT JOIN registered_users ru ON r.assigned_to = ru.id
      WHERE DATE(r.datee) BETWEEN '$date_from' AND '$date_to' $register_filter
      ORDER BY r.datee DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['amount_kes'] = $row['amount_usd'] * USD_TO_KES_RATE;
        $row['type'] = 'Virtual';
        $virtual_data[] = $row;
    }
}

// --- INTERNATIONAL EVENTS ---
$intl_data = [];
$q = "SELECT t.ticket_id AS entry_id, t.fullname AS name, t.email, t.phone_number,
      t.country, e.event_title AS course_name, t.date_sent AS date_registered,
      CASE WHEN t.status = 2 THEN t.amount ELSE 0 END AS amount_usd,
      ru.fullname AS assigned_to
      FROM ticket_congress t
      LEFT JOIN Event e ON t.event_id = e.event_id
      LEFT JOIN registered_users ru ON t.assigned_to = ru.id
      WHERE DATE(t.date_sent) BETWEEN '$date_from' AND '$date_to' $ticket_filter
      ORDER BY t.date_sent DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['amount_kes'] = $row['amount_usd'] * USD_TO_KES_RATE;
        $row['type'] = 'International';
        $intl_data[] = $row;
    }
}

// Combine data
$all_data = array_merge($virtual_data, $intl_data);

// Calculate totals
$total_virtual = count($virtual_data);
$total_intl = count($intl_data);
$total_revenue_usd = array_sum(array_column($all_data, 'amount_usd'));
$total_revenue_kes = $total_revenue_usd * USD_TO_KES_RATE;
$total_paid = count(array_filter($all_data, function($r) { return $r['amount_usd'] > 0; }));

// Export based on type
if ($export_type == 'excel') {
    // Excel Export (CSV format for simplicity)
    $filename = 'enquiry_report_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Report Header
    fputcsv($output, ['ENQUIRY REPORT']);
    fputcsv($output, ['Date Range:', $date_from . ' to ' . $date_to]);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Exchange Rate:', '1 USD = ' . USD_TO_KES_RATE . ' KES']);
    fputcsv($output, []);
    
    // Summary
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Enquiries', $total_virtual + $total_intl]);
    fputcsv($output, ['Virtual Courses', $total_virtual]);
    fputcsv($output, ['International Events', $total_intl]);
    fputcsv($output, ['Total Paid', $total_paid]);
    fputcsv($output, ['Total Revenue (USD)', number_format($total_revenue_usd, 2)]);
    fputcsv($output, ['Total Revenue (KES)', number_format($total_revenue_kes, 2)]);
    fputcsv($output, []);
    
    // Detail Header
    fputcsv($output, ['DETAILED DATA']);
    fputcsv($output, [
        'Reference ID', 'Type', 'Name', 'Email', 'Phone', 'Country', 
        'Course/Event', 'Date Registered', 'Amount (USD)', 'Amount (KES)', 'Assigned To'
    ]);
    
    // Data rows
    foreach ($all_data as $row) {
        fputcsv($output, [
            $row['entry_id'],
            $row['type'],
            $row['name'],
            $row['email'],
            $row['phone_number'],
            $row['country'],
            $row['course_name'],
            $row['date_registered'],
            number_format($row['amount_usd'], 2),
            number_format($row['amount_kes'], 2),
            $row['assigned_to']
        ]);
    }
    
    fclose($output);
    exit;
    
} elseif ($export_type == 'pdf') {
    // PDF Export using HTML to PDF approach
    $filename = 'enquiry_report_' . date('Y-m-d_His') . '.pdf';
    
    // Check if TCPDF or DOMPDF is available, otherwise use HTML
    // For simplicity, we'll generate HTML that can be printed as PDF
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Enquiry Report - <?php echo $date_from; ?> to <?php echo $date_to; ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { margin: 0; color: #333; }
            .header p { margin: 5px 0; color: #666; }
            .summary { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
            .summary-item { text-align: center; }
            .summary-item .value { font-size: 24px; font-weight: bold; color: #0d6efd; }
            .summary-item .label { color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #0d6efd; color: white; }
            tr:nth-child(even) { background: #f9f9f9; }
            .text-right { text-align: right; }
            .badge { padding: 3px 8px; border-radius: 3px; font-size: 10px; }
            .badge-virtual { background: #0d6efd; color: white; }
            .badge-intl { background: #dc3545; color: white; }
            .footer { margin-top: 20px; text-align: center; color: #666; font-size: 10px; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 5px;">
                Print / Save as PDF
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; cursor: pointer; border-radius: 5px; margin-left: 10px;">
                Close
            </button>
        </div>
        
        <div class="header">
            <h1>ENQUIRY REPORT</h1>
            <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
            <p><strong>Generated:</strong> <?php echo date('M d, Y H:i:s'); ?></p>
            <p><strong>Exchange Rate:</strong> 1 USD = <?php echo USD_TO_KES_RATE; ?> KES</p>
        </div>
        
        <div class="summary">
            <h3 style="margin-top: 0;">Summary</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="value"><?php echo number_format($total_virtual + $total_intl); ?></div>
                    <div class="label">Total Enquiries</div>
                </div>
                <div class="summary-item">
                    <div class="value"><?php echo number_format($total_paid); ?></div>
                    <div class="label">Total Paid</div>
                </div>
                <div class="summary-item">
                    <div class="value">KES <?php echo number_format($total_revenue_kes, 2); ?></div>
                    <div class="label">Total Revenue</div>
                </div>
            </div>
            <div class="summary-grid" style="margin-top: 15px;">
                <div class="summary-item">
                    <div class="value" style="color: #0d6efd;"><?php echo number_format($total_virtual); ?></div>
                    <div class="label">Virtual Courses</div>
                </div>
                <div class="summary-item">
                    <div class="value" style="color: #dc3545;"><?php echo number_format($total_intl); ?></div>
                    <div class="label">International Events</div>
                </div>
                <div class="summary-item">
                    <div class="value" style="color: #198754;"><?php echo $total_virtual + $total_intl > 0 ? round(($total_paid / ($total_virtual + $total_intl)) * 100, 1) : 0; ?>%</div>
                    <div class="label">Conversion Rate</div>
                </div>
            </div>
        </div>
        
        <h3>Detailed Data</h3>
        <table>
            <thead>
                <tr>
                    <th>Ref ID</th>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Course/Event</th>
                    <th>Date</th>
                    <th class="text-right">Amount (USD)</th>
                    <th class="text-right">Amount (KES)</th>
                    <th>Assigned To</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_data)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: #666;">No data available for the selected period</td>
                </tr>
                <?php else: ?>
                <?php foreach ($all_data as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['entry_id']); ?></td>
                    <td>
                        <span class="badge <?php echo $row['type'] == 'Virtual' ? 'badge-virtual' : 'badge-intl'; ?>">
                            <?php echo $row['type']; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($row['date_registered'])); ?></td>
                    <td class="text-right"><?php echo number_format($row['amount_usd'], 2); ?></td>
                    <td class="text-right"><?php echo number_format($row['amount_kes'], 2); ?></td>
                    <td><?php echo htmlspecialchars($row['assigned_to']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #e9ecef; font-weight: bold;">
                    <td colspan="6">TOTAL</td>
                    <td class="text-right"><?php echo number_format($total_revenue_usd, 2); ?></td>
                    <td class="text-right"><?php echo number_format($total_revenue_kes, 2); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="footer">
            <p>Generated by Enquiry Management System | <?php echo date('Y'); ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>