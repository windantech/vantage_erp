<?php
require_once 'header.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

// Get current user info - SIMPLE QUERY
$user_q = mysqli_query($conn, "SELECT * FROM registered_users WHERE id = $current_user_id LIMIT 1");
$current_user = mysqli_fetch_assoc($user_q);

// Get staff details separately
$user_department_id = null;
$user_department_name = '';
$job_title = '';
if ($current_user['staff_id']) {
    $staff_q = mysqli_query($conn, "SELECT department_id, job_title FROM staff WHERE id = " . intval($current_user['staff_id']) . " LIMIT 1");
    if ($staff_q && $staff_row = mysqli_fetch_assoc($staff_q)) {
        $user_department_id = $staff_row['department_id'];
        $job_title = $staff_row['job_title'] ?? '';
        
        // Get department name
        if ($user_department_id) {
            $dept_q = mysqli_query($conn, "SELECT department_name FROM departments WHERE id = " . intval($user_department_id) . " LIMIT 1");
            if ($dept_q && $dept_row = mysqli_fetch_assoc($dept_q)) {
                $user_department_name = strtolower($dept_row['department_name']);
            }
        }
    }
}

$user_role = $current_user['role'] ?? '';
$is_hod = (stripos($job_title, 'head') !== false) || 
          (stripos($user_role, 'hod') !== false) ||
          (stripos($user_role, 'admin') !== false);
$is_admin = (stripos($user_role, 'admin') !== false);

// Determine staff type based on department
$staff_type = 'both';
if (strpos($user_department_name, 'virtual') !== false || strpos($user_department_name, 'online') !== false) {
    $staff_type = 'virtual';
} elseif (strpos($user_department_name, 'international') !== false || strpos($user_department_name, 'event') !== false) {
    $staff_type = 'international';
}

if ($is_admin) {
    $staff_type = 'both';
}

// Get filter parameters
$view_mode = $_GET['view'] ?? 'personal';
$filter_period = $_GET['period'] ?? 'this_month';
$filter_course = $_GET['course_id'] ?? '';
$filter_event = $_GET['event_id'] ?? '';
$filter_staff = intval($_GET['staff_id'] ?? 0);
$custom_start = $_GET['start_date'] ?? '';
$custom_end = $_GET['end_date'] ?? '';

// Determine which staff to show
$staff_ids = [];
if ($view_mode === 'personal' || (!$is_hod && !$is_admin)) {
    $staff_ids = [$current_user_id];
} elseif ($view_mode === 'department' && $is_hod && $user_department_id) {
    $dept_q = mysqli_query($conn, "
        SELECT ru.id FROM registered_users ru
        INNER JOIN staff s ON ru.staff_id = s.id
        WHERE s.department_id = $user_department_id AND ru.status = 1 LIMIT 50
    ");
    while ($row = mysqli_fetch_assoc($dept_q)) {
        $staff_ids[] = $row['id'];
    }
} elseif ($view_mode === 'all' && $is_admin) {
    $staff_ids = [];
} elseif ($filter_staff > 0 && ($is_hod || $is_admin)) {
    $staff_ids = [$filter_staff];
} else {
    $staff_ids = [$current_user_id];
}

// Calculate date range
$today = date('Y-m-d');
$start_date = date('Y-m-01');
$end_date = $today;

switch ($filter_period) {
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        break;
    case 'this_quarter':
        $quarter = ceil(date('n') / 3);
        $start_date = date('Y-' . str_pad(($quarter - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
        break;
    case 'all_time':
        $start_date = '2020-01-01';
        break;
    case 'custom':
        $start_date = $custom_start ?: date('Y-m-01');
        $end_date = $custom_end ?: $today;
        break;
}

// Get commission settings - simple queries
function getCommissionSetting($conn, $key, $default = '') {
    $key = mysqli_real_escape_string($conn, $key);
    $result = mysqli_query($conn, "SELECT setting_value FROM commission_settings WHERE setting_key = '$key' LIMIT 1");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

$usd_to_kes = floatval(getCommissionSetting($conn, 'commission_conversion_rate', '129'));
$virtual_fee_threshold = floatval(getCommissionSetting($conn, 'virtual_fee_collection_threshold', '80'));
$virtual_client_threshold = floatval(getCommissionSetting($conn, 'virtual_client_payment_threshold', '80'));
$intl_fee_threshold = floatval(getCommissionSetting($conn, 'international_fee_collection_threshold', '90'));
$intl_client_threshold = floatval(getCommissionSetting($conn, 'international_client_payment_threshold', '100'));

$staff_ids_str = !empty($staff_ids) ? implode(',', array_map('intval', $staff_ids)) : '0';

// ============================================
// PRE-LOAD PAYMENT DATA (SIMPLE QUERY)
// This avoids correlated subqueries later
// ============================================
$payment_totals = [];
$pay_q = mysqli_query($conn, "
    SELECT app_id, SUM(TransactionAmount) AS total_paid
    FROM dpo_payment 
    WHERE status = 2
    GROUP BY app_id
");
if ($pay_q) {
    while ($row = mysqli_fetch_assoc($pay_q)) {
        $payment_totals[$row['app_id']] = floatval($row['total_paid']);
    }
}

// ============================================
// INITIALIZE STATS
// ============================================
$virtual_stats = ['total_enquiries' => 0, 'converted' => 0, 'leads' => 0, 'invoiced' => 0, 'collected' => 0];
$intl_stats = ['total_enquiries' => 0, 'converted' => 0, 'leads' => 0, 'invoiced' => 0, 'collected' => 0];
$assigned_intakes = [];
$assigned_events = [];

// ============================================
// VIRTUAL DATA
// ============================================
if ($staff_type === 'virtual' || $staff_type === 'both') {
    
    // Step 1: Get intakes assigned to staff (SIMPLE)
    $intake_map = []; // intake_id => intake_data
    if (!empty($staff_ids) || ($view_mode === 'all' && $is_admin)) {
        $intake_where = ($view_mode === 'all' && $is_admin) ? "1=1" : "i.assigned_to IN ($staff_ids_str)";
        $intake_q = mysqli_query($conn, "
            SELECT i.id, i.intake_id, i.description, i.course_id, i.start_date, i.assigned_to,
                   i.minimum_clients, i.commission_rate
            FROM intake i
            WHERE $intake_where
            ORDER BY i.start_date DESC
            LIMIT 50
        ");
        while ($row = mysqli_fetch_assoc($intake_q)) {
            $intake_map[$row['intake_id']] = $row;
        }
    }
    
    if (!empty($intake_map)) {
        $intake_ids_quoted = array_map(function($id) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $id) . "'";
        }, array_keys($intake_map));
        $intake_filter_sql = "r.intake_id IN (" . implode(',', $intake_ids_quoted) . ")";
        
        // Step 2: Get course prices (SIMPLE)
        $course_prices = [];
        $course_q = mysqli_query($conn, "SELECT course_id, price_usd FROM course WHERE status = 1");
        while ($row = mysqli_fetch_assoc($course_q)) {
            $course_prices[$row['course_id']] = floatval($row['price_usd']);
        }
        
        // Step 3: Get registrations (SIMPLE - no subquery)
        $registrations = [];
        $reg_q = mysqli_query($conn, "
            SELECT r.id, r.entry_id, r.intake_id, r.firstname, r.lastname, r.email, r.phone_number, r.datee
            FROM register r
            WHERE $intake_filter_sql
            AND r.datee BETWEEN '$start_date' AND '$end_date 23:59:59'
            ORDER BY r.datee DESC
            LIMIT 1000
        ");
        while ($row = mysqli_fetch_assoc($reg_q)) {
            $registrations[] = $row;
        }
        
        // Step 4: DEDUPLICATE by email + intake_id
        // Keep the record with the highest payment (or first if no payments)
        $unique_enquiries = []; // key = email|intake_id
        foreach ($registrations as $reg) {
            $email = strtolower(trim($reg['email']));
            $intake_id = $reg['intake_id'];
            $key = $email . '|' . $intake_id;
            $paid = $payment_totals[$reg['entry_id']] ?? 0;
            
            if (!isset($unique_enquiries[$key])) {
                $reg['total_paid'] = $paid;
                $unique_enquiries[$key] = $reg;
            } else {
                // Keep the one with higher payment
                if ($paid > $unique_enquiries[$key]['total_paid']) {
                    $reg['total_paid'] = $paid;
                    $unique_enquiries[$key] = $reg;
                }
            }
        }
        
        // Step 5: Calculate stats from deduplicated data
        foreach ($unique_enquiries as $reg) {
            $intake_id = $reg['intake_id'];
            $paid = $reg['total_paid'];
            
            $intake_data = $intake_map[$intake_id] ?? null;
            $course_id = $intake_data['course_id'] ?? '';
            $price_usd = $course_prices[$course_id] ?? 0;
            
            // Apply course filter if set
            if ($filter_course && $course_id != $filter_course) {
                continue;
            }
            
            $virtual_stats['total_enquiries']++;
            if ($paid > 0) {
                $virtual_stats['converted']++;
                $virtual_stats['invoiced'] += $price_usd * $usd_to_kes;
                $virtual_stats['collected'] += $paid * $usd_to_kes;
            } else {
                $virtual_stats['leads']++;
            }
        }
    }
    
    // Step 6: Get assigned intakes with details - ALL
    if (!empty($staff_ids)) {
        // Get all assigned intakes by start_date
        $top_intakes_q = mysqli_query($conn, "
            SELECT i.id, i.intake_id, i.description, i.start_date, i.minimum_clients, i.commission_rate, i.course_id
            FROM intake i
            WHERE i.assigned_to IN ($staff_ids_str)
            ORDER BY i.start_date DESC
            LIMIT 50
        ");
        
        while ($intake_row = mysqli_fetch_assoc($top_intakes_q)) {
            $intake_id_str = $intake_row['intake_id'];
            $course_id = $intake_row['course_id'];
            
            // Get course info
            $course_name = '';
            $price_usd = 0;
            $course_q = mysqli_query($conn, "SELECT course, price_usd FROM course WHERE course_id = '" . mysqli_real_escape_string($conn, $course_id) . "' LIMIT 1");
            if ($course_q && $c = mysqli_fetch_assoc($course_q)) {
                $course_name = $c['course'];
                $price_usd = floatval($c['price_usd']);
            }
            
            // Get registrations for this intake (SIMPLE)
            $reg_q = mysqli_query($conn, "
                SELECT id, entry_id, email, firstname, lastname, phone_number, datee
                FROM register 
                WHERE intake_id = '" . mysqli_real_escape_string($conn, $intake_id_str) . "'
                ORDER BY datee DESC
                LIMIT 500
            ");
            
            // Deduplicate by email for this intake
            $unique_clients = []; // key = email
            while ($r = mysqli_fetch_assoc($reg_q)) {
                $email = strtolower(trim($r['email']));
                $paid = $payment_totals[$r['entry_id']] ?? 0;
                
                if (!isset($unique_clients[$email])) {
                    $r['total_paid'] = $paid;
                    $unique_clients[$email] = $r;
                } else {
                    // Keep the one with higher payment
                    if ($paid > $unique_clients[$email]['total_paid']) {
                        $r['total_paid'] = $paid;
                        $unique_clients[$email] = $r;
                    }
                }
            }
            
            $total_registered = 0;
            $converted_clients = 0;
            $lead_clients = 0;
            $qualifying_clients = 0;
            $total_collected = 0;
            $total_expected_from_converted = 0;
            
            foreach ($unique_clients as $client) {
                $total_registered++;
                $paid = $client['total_paid'];
                
                if ($paid > 0) {
                    $converted_clients++;
                    $total_collected += $paid;
                    $total_expected_from_converted += $price_usd;
                    
                    if ($paid >= ($price_usd * $virtual_client_threshold / 100)) {
                        $qualifying_clients++;
                    }
                } else {
                    $lead_clients++;
                }
            }
            
            // Get commission record (SIMPLE)
            $earned_commission = 0;
            $is_eligible = 0;
            $commission_status = '';
            $comm_q = mysqli_query($conn, "
                SELECT commission_amount, is_eligible, status 
                FROM commission_records 
                WHERE commission_type = 'virtual' AND source_id = " . intval($intake_row['id']) . "
                LIMIT 1
            ");
            if ($comm_q && $comm_row = mysqli_fetch_assoc($comm_q)) {
                $earned_commission = floatval($comm_row['commission_amount']);
                $is_eligible = $comm_row['is_eligible'];
                $commission_status = $comm_row['status'];
            }
            
            // Calculate percentages
            $fee_collection_pct = $total_expected_from_converted > 0 ? ($total_collected / $total_expected_from_converted) * 100 : 0;
            $clients_needed = max(0, $intake_row['minimum_clients'] - $qualifying_clients);
            $fee_needed = max(0, $virtual_fee_threshold - $fee_collection_pct);
            $total_invoiced = $converted_clients * $price_usd;
            $outstanding = $total_invoiced - $total_collected;
            
            $assigned_intakes[] = [
                'id' => $intake_row['id'],
                'intake_id' => $intake_id_str,
                'description' => $intake_row['description'],
                'start_date' => $intake_row['start_date'],
                'minimum_clients' => $intake_row['minimum_clients'],
                'commission_rate' => $intake_row['commission_rate'],
                'course' => $course_name,
                'price_usd' => $price_usd,
                'total_registered' => $total_registered,
                'converted_clients' => $converted_clients,
                'lead_clients' => $lead_clients,
                'qualifying_clients' => $qualifying_clients,
                'total_collected' => $total_collected,
                'total_expected_from_converted' => $total_expected_from_converted,
                'fee_collection_pct' => $fee_collection_pct,
                'clients_needed' => $clients_needed,
                'fee_needed' => $fee_needed,
                'total_invoiced' => $total_invoiced,
                'outstanding' => $outstanding,
                'earned_commission' => $earned_commission,
                'is_eligible' => $is_eligible,
                'commission_status' => $commission_status
            ];
        }
    }
}

// ============================================
// INTERNATIONAL DATA
// ============================================
if ($staff_type === 'international' || $staff_type === 'both') {
    
    // Step 1: Get events assigned to staff (SIMPLE)
    $event_map = [];
    if (!empty($staff_ids) || ($view_mode === 'all' && $is_admin)) {
        $event_where = ($view_mode === 'all' && $is_admin) ? "1=1" : "e.assigned_to IN ($staff_ids_str)";
        $event_q = mysqli_query($conn, "
            SELECT e.event_id, e.event_title, e.start_on, e.early_amount, e.minimum_clients, e.commission_rate, e.assigned_to
            FROM Event e
            WHERE $event_where
            ORDER BY e.start_on DESC
            LIMIT 50
        ");
        while ($row = mysqli_fetch_assoc($event_q)) {
            $event_map[$row['event_id']] = $row;
        }
    }
    
    if (!empty($event_map)) {
        $event_ids = array_keys($event_map);
        $event_filter_sql = "t.event_id IN (" . implode(',', array_map('intval', $event_ids)) . ")";
        
        // Step 2: Get ticket registrations (SIMPLE)
        $tickets = [];
        $ticket_q = mysqli_query($conn, "
            SELECT t.id, t.ticket_id, t.event_id, t.fullname, t.email, t.phone_number, t.date_sent
            FROM ticket_congress t
            WHERE $event_filter_sql
            AND t.date_sent BETWEEN '$start_date' AND '$end_date 23:59:59'
            ORDER BY t.date_sent DESC
            LIMIT 1000
        ");
        while ($row = mysqli_fetch_assoc($ticket_q)) {
            $tickets[] = $row;
        }
        
        // Step 3: DEDUPLICATE by email + event_id
        $unique_tickets = []; // key = email|event_id
        foreach ($tickets as $ticket) {
            $email = strtolower(trim($ticket['email']));
            $event_id = $ticket['event_id'];
            $key = $email . '|' . $event_id;
            $paid = $payment_totals[$ticket['ticket_id']] ?? 0;
            
            if (!isset($unique_tickets[$key])) {
                $ticket['total_paid'] = $paid;
                $unique_tickets[$key] = $ticket;
            } else {
                // Keep the one with higher payment
                if ($paid > $unique_tickets[$key]['total_paid']) {
                    $ticket['total_paid'] = $paid;
                    $unique_tickets[$key] = $ticket;
                }
            }
        }
        
        // Step 4: Calculate stats from deduplicated data
        foreach ($unique_tickets as $ticket) {
            $event_id = $ticket['event_id'];
            $paid = $ticket['total_paid'];
            
            $event_data = $event_map[$event_id] ?? null;
            $early_amount = floatval($event_data['early_amount'] ?? 0);
            
            // Apply event filter if set
            if ($filter_event && $event_id != $filter_event) {
                continue;
            }
            
            $intl_stats['total_enquiries']++;
            if ($paid > 0) {
                $intl_stats['converted']++;
                $intl_stats['invoiced'] += $early_amount * $usd_to_kes;
                $intl_stats['collected'] += $paid * $usd_to_kes;
            } else {
                $intl_stats['leads']++;
            }
        }
    }
    
    // Step 5: Get assigned events with details - ALL
    if (!empty($staff_ids)) {
        $top_events_q = mysqli_query($conn, "
            SELECT e.event_id, e.event_title, e.start_on, e.minimum_clients, e.commission_rate, e.early_amount
            FROM Event e
            WHERE e.assigned_to IN ($staff_ids_str)
            ORDER BY e.start_on DESC
            LIMIT 50
        ");
        
        while ($event_row = mysqli_fetch_assoc($top_events_q)) {
            $event_id = $event_row['event_id'];
            $early_amount = floatval($event_row['early_amount']);
            
            // Get tickets for this event (SIMPLE)
            $ticket_q = mysqli_query($conn, "
                SELECT id, ticket_id, email, fullname, phone_number, date_sent
                FROM ticket_congress 
                WHERE event_id = " . intval($event_id) . "
                ORDER BY date_sent DESC
                LIMIT 500
            ");
            
            // Deduplicate by email for this event
            $unique_clients = []; // key = email
            while ($t = mysqli_fetch_assoc($ticket_q)) {
                $email = strtolower(trim($t['email']));
                $paid = $payment_totals[$t['ticket_id']] ?? 0;
                
                if (!isset($unique_clients[$email])) {
                    $t['total_paid'] = $paid;
                    $unique_clients[$email] = $t;
                } else {
                    // Keep the one with higher payment
                    if ($paid > $unique_clients[$email]['total_paid']) {
                        $t['total_paid'] = $paid;
                        $unique_clients[$email] = $t;
                    }
                }
            }
            
            $total_registered = 0;
            $converted_clients = 0;
            $lead_clients = 0;
            $qualifying_clients = 0;
            $total_collected = 0;
            $total_expected_from_converted = 0;
            
            foreach ($unique_clients as $client) {
                $total_registered++;
                $paid = $client['total_paid'];
                
                if ($paid > 0) {
                    $converted_clients++;
                    $total_collected += $paid;
                    $total_expected_from_converted += $early_amount;
                    
                    if ($paid >= $early_amount) {
                        $qualifying_clients++;
                    }
                } else {
                    $lead_clients++;
                }
            }
            
            // Get commission record (SIMPLE)
            $earned_commission = 0;
            $is_eligible = 0;
            $commission_status = '';
            $comm_q = mysqli_query($conn, "
                SELECT commission_amount, is_eligible, status 
                FROM commission_records 
                WHERE commission_type = 'international' AND source_id = " . intval($event_id) . "
                LIMIT 1
            ");
            if ($comm_q && $comm_row = mysqli_fetch_assoc($comm_q)) {
                $earned_commission = floatval($comm_row['commission_amount']);
                $is_eligible = $comm_row['is_eligible'];
                $commission_status = $comm_row['status'];
            }
            
            // Calculate percentages
            $fee_collection_pct = $total_expected_from_converted > 0 ? ($total_collected / $total_expected_from_converted) * 100 : 0;
            $clients_needed = max(0, $event_row['minimum_clients'] - $qualifying_clients);
            $fee_needed = max(0, $intl_fee_threshold - $fee_collection_pct);
            $total_invoiced = $converted_clients * $early_amount;
            $outstanding = $total_invoiced - $total_collected;
            
            $assigned_events[] = [
                'event_id' => $event_id,
                'event_title' => $event_row['event_title'],
                'start_on' => $event_row['start_on'],
                'minimum_clients' => $event_row['minimum_clients'],
                'commission_rate' => $event_row['commission_rate'],
                'early_amount' => $early_amount,
                'total_registered' => $total_registered,
                'converted_clients' => $converted_clients,
                'lead_clients' => $lead_clients,
                'qualifying_clients' => $qualifying_clients,
                'total_collected' => $total_collected,
                'total_expected_from_converted' => $total_expected_from_converted,
                'fee_collection_pct' => $fee_collection_pct,
                'clients_needed' => $clients_needed,
                'fee_needed' => $fee_needed,
                'total_invoiced' => $total_invoiced,
                'outstanding' => $outstanding,
                'earned_commission' => $earned_commission,
                'is_eligible' => $is_eligible,
                'commission_status' => $commission_status
            ];
        }
    }
}

// ============================================
// CLIENTS WITH BALANCES (DEDUPLICATED)
// ============================================
$intake_clients_data = [];
$event_clients_data = [];

// Only load if we have intakes/events
if (($staff_type === 'virtual' || $staff_type === 'both') && !empty($assigned_intakes)) {
    foreach ($assigned_intakes as $intake) {
        $intake_id = $intake['intake_id'];
        $price_usd = $intake['price_usd'];
        
        // Get clients for this intake (SIMPLE)
        $clients_q = mysqli_query($conn, "
            SELECT id, entry_id, firstname, lastname, email, phone_number, datee
            FROM register
            WHERE intake_id = '" . mysqli_real_escape_string($conn, $intake_id) . "'
            ORDER BY datee DESC
            LIMIT 200
        ");
        
        // Deduplicate by email
        $unique_clients = [];
        while ($c = mysqli_fetch_assoc($clients_q)) {
            $email = strtolower(trim($c['email']));
            $paid = $payment_totals[$c['entry_id']] ?? 0;
            
            if (!isset($unique_clients[$email])) {
                $unique_clients[$email] = [
                    'id' => $c['id'],
                    'entry_id' => $c['entry_id'],
                    'intake_id' => $intake_id,
                    'firstname' => $c['firstname'],
                    'lastname' => $c['lastname'],
                    'email' => $c['email'],
                    'phone' => $c['phone_number'],
                    'datee' => $c['datee'],
                    'course_fee' => $price_usd,
                    'amount_paid' => $paid,
                    'balance' => $price_usd - $paid,
                    'source_table' => 'register',
                    'reference' => $c['entry_id']
                ];
            } else {
                // Keep the one with higher payment
                if ($paid > $unique_clients[$email]['amount_paid']) {
                    $unique_clients[$email] = [
                        'id' => $c['id'],
                        'entry_id' => $c['entry_id'],
                        'intake_id' => $intake_id,
                        'firstname' => $c['firstname'],
                        'lastname' => $c['lastname'],
                        'email' => $c['email'],
                        'phone' => $c['phone_number'],
                        'datee' => $c['datee'],
                        'course_fee' => $price_usd,
                        'amount_paid' => $paid,
                        'balance' => $price_usd - $paid,
                        'source_table' => 'register',
                        'reference' => $c['entry_id']
                    ];
                }
            }
        }
        
        // Sort by balance descending
        usort($unique_clients, function($a, $b) {
            return $b['balance'] - $a['balance'];
        });
        
        $intake_clients_data[$intake_id] = array_values($unique_clients);
    }
}

if (($staff_type === 'international' || $staff_type === 'both') && !empty($assigned_events)) {
    foreach ($assigned_events as $event) {
        $event_id = $event['event_id'];
        $early_amount = $event['early_amount'];
        
        // Get clients for this event (SIMPLE)
        $clients_q = mysqli_query($conn, "
            SELECT id, ticket_id, fullname, email, phone_number, date_sent
            FROM ticket_congress
            WHERE event_id = " . intval($event_id) . "
            ORDER BY date_sent DESC
            LIMIT 200
        ");
        
        // Deduplicate by email
        $unique_clients = [];
        while ($c = mysqli_fetch_assoc($clients_q)) {
            $email = strtolower(trim($c['email']));
            $paid = $payment_totals[$c['ticket_id']] ?? 0;
            
            if (!isset($unique_clients[$email])) {
                $unique_clients[$email] = [
                    'id' => $c['id'],
                    'ticket_id' => $c['ticket_id'],
                    'event_id' => $event_id,
                    'client_name' => $c['fullname'],
                    'email' => $c['email'],
                    'phone' => $c['phone_number'],
                    'date_sent' => $c['date_sent'],
                    'event_fee' => $early_amount,
                    'amount_paid' => $paid,
                    'balance' => $early_amount - $paid,
                    'source_table' => 'ticket_congress',
                    'reference' => $c['ticket_id']
                ];
            } else {
                // Keep the one with higher payment
                if ($paid > $unique_clients[$email]['amount_paid']) {
                    $unique_clients[$email] = [
                        'id' => $c['id'],
                        'ticket_id' => $c['ticket_id'],
                        'event_id' => $event_id,
                        'client_name' => $c['fullname'],
                        'email' => $c['email'],
                        'phone' => $c['phone_number'],
                        'date_sent' => $c['date_sent'],
                        'event_fee' => $early_amount,
                        'amount_paid' => $paid,
                        'balance' => $early_amount - $paid,
                        'source_table' => 'ticket_congress',
                        'reference' => $c['ticket_id']
                    ];
                }
            }
        }
        
        // Sort by balance descending
        usort($unique_clients, function($a, $b) {
            return $b['balance'] - $a['balance'];
        });
        
        $event_clients_data[$event_id] = array_values($unique_clients);
    }
}

// ============================================
// COMBINED TOTALS
// ============================================
$total_enquiries = $virtual_stats['total_enquiries'] + $intl_stats['total_enquiries'];
$total_converted = $virtual_stats['converted'] + $intl_stats['converted'];
$total_leads = $virtual_stats['leads'] + $intl_stats['leads'];
$total_invoiced = $virtual_stats['invoiced'] + $intl_stats['invoiced'];
$total_collected = $virtual_stats['collected'] + $intl_stats['collected'];
$total_outstanding = $total_invoiced - $total_collected;

$conversion_rate = $total_enquiries > 0 ? ($total_converted / $total_enquiries) * 100 : 0;
$fee_collection_pct = $total_invoiced > 0 ? ($total_collected / $total_invoiced) * 100 : 0;

// ============================================
// COMMISSION EARNED (SIMPLE)
// ============================================
$commission_earned_usd = 0;
$commission_pending_usd = 0;
$commission_paid_usd = 0;

if (!empty($staff_ids)) {
    $comm_q = mysqli_query($conn, "
        SELECT 
            SUM(CASE WHEN is_eligible = 1 THEN commission_amount ELSE 0 END) AS total_earned,
            SUM(CASE WHEN is_eligible = 1 AND status IN ('draft', 'pending_approval', 'approved') THEN commission_amount ELSE 0 END) AS pending,
            SUM(CASE WHEN is_eligible = 1 AND status = 'paid' THEN commission_amount ELSE 0 END) AS paid
        FROM commission_records
        WHERE staff_user_id IN ($staff_ids_str)
    ");
    if ($comm_q && $row = mysqli_fetch_assoc($comm_q)) {
        $commission_earned_usd = floatval($row['total_earned']);
        $commission_pending_usd = floatval($row['pending']);
        $commission_paid_usd = floatval($row['paid']);
    }
}

$commission_earned_kes = $commission_earned_usd * $usd_to_kes;
$commission_pending_kes = $commission_pending_usd * $usd_to_kes;
$commission_paid_kes = $commission_paid_usd * $usd_to_kes;

// ============================================
// FOLLOW-UPS (SIMPLIFIED - separate queries)
// ============================================
$followups_overdue = [];
$followups_today = [];
$followups_upcoming = [];
$followup_counts = ['overdue' => 0, 'today' => 0, 'upcoming' => 0];
$followups_by_intake = [];
$followups_by_event = [];

if (!empty($staff_ids)) {
    // Get followups (SIMPLE)
    $followups_q = mysqli_query($conn, "
        SELECT id, enquiry_type, enquiry_id, next_step, reminder_date, reminder_time, action_taken, is_completed, created_at
        FROM enquiry_followups
        WHERE staff_id IN ($staff_ids_str)
        AND is_completed = 0
        AND reminder_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY reminder_date ASC, reminder_time ASC
        LIMIT 50
    ");
    
    while ($fu = mysqli_fetch_assoc($followups_q)) {
        // Get client details based on type (SIMPLE separate queries)
        $client_name = 'Unknown';
        $client_email = '';
        $client_phone = '';
        $program_name = '';
        $intake_id = null;
        $event_id = null;
        
        if ($fu['enquiry_type'] === 'register') {
            $reg_q = mysqli_query($conn, "
                SELECT r.firstname, r.lastname, r.email, r.phone_number, r.intake_id, i.description
                FROM register r
                LEFT JOIN intake i ON r.intake_id = i.intake_id
                WHERE r.entry_id = '" . mysqli_real_escape_string($conn, $fu['enquiry_id']) . "'
                LIMIT 1
            ");
            if ($reg_q && $r = mysqli_fetch_assoc($reg_q)) {
                $client_name = $r['firstname'] . ' ' . $r['lastname'];
                $client_email = $r['email'];
                $client_phone = $r['phone_number'];
                $program_name = $r['description'];
                $intake_id = $r['intake_id'];
            }
        } elseif ($fu['enquiry_type'] === 'ticket_congress') {
            $ticket_q = mysqli_query($conn, "
                SELECT t.fullname, t.email, t.phone_number, t.event_id, e.event_title
                FROM ticket_congress t
                LEFT JOIN Event e ON t.event_id = e.event_id
                WHERE t.ticket_id = '" . mysqli_real_escape_string($conn, $fu['enquiry_id']) . "'
                LIMIT 1
            ");
            if ($ticket_q && $t = mysqli_fetch_assoc($ticket_q)) {
                $client_name = $t['fullname'];
                $client_email = $t['email'];
                $client_phone = $t['phone_number'];
                $program_name = $t['event_title'];
                $event_id = $t['event_id'];
            }
        }
        
        $fu['client_name'] = $client_name;
        $fu['client_email'] = $client_email;
        $fu['client_phone'] = $client_phone;
        $fu['program_name'] = $program_name;
        $fu['intake_id'] = $intake_id;
        $fu['event_id'] = $event_id;
        
        $reminder_date = $fu['reminder_date'];
        $today_date = date('Y-m-d');
        
        if ($reminder_date < $today_date) {
            $followups_overdue[] = $fu;
            $followup_counts['overdue']++;
        } elseif ($reminder_date == $today_date) {
            $followups_today[] = $fu;
            $followup_counts['today']++;
        } else {
            $followups_upcoming[] = $fu;
            $followup_counts['upcoming']++;
        }
        
        // Group by intake
        if ($intake_id) {
            if (!isset($followups_by_intake[$intake_id])) {
                $followups_by_intake[$intake_id] = ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'items' => []];
            }
            if ($reminder_date < $today_date) {
                $followups_by_intake[$intake_id]['overdue']++;
            } elseif ($reminder_date == $today_date) {
                $followups_by_intake[$intake_id]['today']++;
            } else {
                $followups_by_intake[$intake_id]['upcoming']++;
            }
            $followups_by_intake[$intake_id]['items'][] = $fu;
        }
        
        // Group by event
        if ($event_id) {
            if (!isset($followups_by_event[$event_id])) {
                $followups_by_event[$event_id] = ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'items' => []];
            }
            if ($reminder_date < $today_date) {
                $followups_by_event[$event_id]['overdue']++;
            } elseif ($reminder_date == $today_date) {
                $followups_by_event[$event_id]['today']++;
            } else {
                $followups_by_event[$event_id]['upcoming']++;
            }
            $followups_by_event[$event_id]['items'][] = $fu;
        }
    }
}

// ============================================
// MOTIVATIONAL METRICS
// ============================================
$closest_to_commission = null;
$closest_clients_needed = PHP_INT_MAX;
$closest_fee_needed = PHP_INT_MAX;

foreach ($assigned_intakes as $intake) {
    if (!$intake['is_eligible'] && $intake['clients_needed'] < $closest_clients_needed) {
        $closest_clients_needed = $intake['clients_needed'];
        $closest_fee_needed = $intake['fee_needed'];
        $closest_to_commission = [
            'type' => 'virtual',
            'name' => $intake['description'] ?: $intake['course'],
            'clients_needed' => $intake['clients_needed'],
            'fee_needed' => $intake['fee_needed'],
            'qualifying' => $intake['qualifying_clients'],
            'minimum' => $intake['minimum_clients'],
            'fee_pct' => $intake['fee_collection_pct'],
            'target_fee' => $virtual_fee_threshold
        ];
    }
}

foreach ($assigned_events as $event) {
    if (!$event['is_eligible'] && $event['clients_needed'] < $closest_clients_needed) {
        $closest_clients_needed = $event['clients_needed'];
        $closest_fee_needed = $event['fee_needed'];
        $closest_to_commission = [
            'type' => 'international',
            'name' => $event['event_title'],
            'clients_needed' => $event['clients_needed'],
            'fee_needed' => $event['fee_needed'],
            'qualifying' => $event['qualifying_clients'],
            'minimum' => $event['minimum_clients'],
            'fee_pct' => $event['fee_collection_pct'],
            'target_fee' => $intl_fee_threshold
        ];
    }
}

// ============================================
// FILTER DROPDOWNS (SIMPLE)
// ============================================
$courses_list = [];
if ($staff_type === 'virtual' || $staff_type === 'both') {
    $course_list_q = mysqli_query($conn, "SELECT course_id, course FROM course WHERE status = 1 ORDER BY course LIMIT 100");
    while ($row = mysqli_fetch_assoc($course_list_q)) {
        $courses_list[] = $row;
    }
}

$events_list = [];
if ($staff_type === 'international' || $staff_type === 'both') {
    $event_list_q = mysqli_query($conn, "SELECT event_id, event_title FROM Event WHERE status = 1 ORDER BY event_title LIMIT 100");
    while ($row = mysqli_fetch_assoc($event_list_q)) {
        $events_list[] = $row;
    }
}

// Get staff list for HOD/Admin
$all_staff = [];
if ($is_hod || $is_admin) {
    $staff_q = mysqli_query($conn, "
        SELECT ru.id, ru.fullname
        FROM registered_users ru
        WHERE ru.status = 1
        ORDER BY ru.fullname
        LIMIT 100
    ");
    while ($row = mysqli_fetch_assoc($staff_q)) {
        $all_staff[] = $row;
    }
}
?>

<style>
.stat-card {
    border-radius: 12px;
    transition: all 0.3s;
    border: none;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
}
.stat-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.progress-thick {
    height: 20px;
    border-radius: 10px;
}
.progress-thin {
    height: 8px;
    border-radius: 4px;
}
.commission-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
}
.motivation-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border-radius: 12px;
    color: white;
}
.motivation-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}
.goal-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.goal-circle .number {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
}
.goal-circle .label {
    font-size: 0.8rem;
    opacity: 0.9;
}
.client-row {
    transition: background 0.2s;
    cursor: pointer;
}
.client-row:hover {
    background: #e3f2fd !important;
}
.balance-danger { color: #dc3545; font-weight: 600; }
.balance-warning { color: #ffc107; font-weight: 600; }
.balance-success { color: #198754; font-weight: 600; }
.dept-badge {
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 20px;
}
.followup-row-overdue {
    border-left: 4px solid #dc3545;
}
.followup-row-today {
    border-left: 4px solid #ffc107;
}
.followup-row-upcoming {
    border-left: 4px solid #0dcaf0;
}
.table-danger {
    --bs-table-bg: #fff5f5;
}
.table-warning {
    --bs-table-bg: #fffbeb;
}
.goal-circle-sm {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255,193,7,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.goal-circle-sm .number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #856404;
}
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 1;
}
.clickable-row {
    cursor: pointer;
}
.clickable-row:hover {
    background-color: #e8f4f8 !important;
}
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">
                        <i class="fas fa-chart-line me-2"></i>My Performance Dashboard
                        <?php if ($staff_type === 'virtual'): ?>
                            <span class="badge bg-primary dept-badge ms-2">Virtual Courses</span>
                        <?php elseif ($staff_type === 'international'): ?>
                            <span class="badge bg-danger dept-badge ms-2">International Events</span>
                        <?php endif; ?>
                    </h4>
                    <p class="text-muted mb-0">
                        <?php echo date('M d', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?>
                        | <?php echo htmlspecialchars($current_user['fullname']); ?>
                    </p>
                </div>
                <div>
                    <?php if ($is_hod): ?>
                        <a href="?view=department&period=<?php echo $filter_period; ?>" class="btn btn-primary me-2">
                            <i class="fas fa-users me-1"></i>View Department
                        </a>
                    <?php endif; ?>
                    <?php if ($is_admin): ?>
                        <a href="?view=all&period=<?php echo $filter_period; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-building me-1"></i>View All
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label small">Time Period</label>
                            <select class="form-select form-select-sm" name="period" onchange="this.form.submit()">
                                <option value="this_week" <?php echo $filter_period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $filter_period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="this_quarter" <?php echo $filter_period === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                                <option value="all_time" <?php echo $filter_period === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                            </select>
                        </div>
                        
                        <?php if ($staff_type === 'virtual' || $staff_type === 'both'): ?>
                        <div class="col-md-3">
                            <label class="form-label small">Course</label>
                            <select class="form-select form-select-sm" name="course_id" onchange="this.form.submit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses_list as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>" <?php echo $filter_course == $course['course_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($staff_type === 'international' || $staff_type === 'both'): ?>
                        <div class="col-md-3">
                            <label class="form-label small">Event</label>
                            <select class="form-select form-select-sm" name="event_id" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php foreach ($events_list as $event): ?>
                                    <option value="<?php echo $event['event_id']; ?>" <?php echo $filter_event == $event['event_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['event_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Commission Summary -->
            <div class="card commission-card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row align-items-center text-center">
                        <div class="col-md-3 border-end border-light">
                            <p class="mb-1 opacity-75 small">TOTAL EARNED</p>
                            <h3 class="mb-0">KES <?php echo number_format($commission_earned_kes, 0); ?></h3>
                        </div>
                        <div class="col-md-3 border-end border-light">
                            <p class="mb-1 opacity-75 small">PENDING</p>
                            <h3 class="mb-0">KES <?php echo number_format($commission_pending_kes, 0); ?></h3>
                        </div>
                        <div class="col-md-3 border-end border-light">
                            <p class="mb-1 opacity-75 small">PAID OUT</p>
                            <h3 class="mb-0">KES <?php echo number_format($commission_paid_kes, 0); ?></h3>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1 opacity-75 small">CONVERSION RATE</p>
                            <h3 class="mb-0"><?php echo number_format($conversion_rate, 1); ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Virtual Intakes -->
            <?php if ($staff_type === 'virtual' || $staff_type === 'both'): ?>
            <div class="row">
                <?php 
                $intake_count = 0;
                foreach ($assigned_intakes as $intake): 
                    $intake_count++;
                    
                    $intake_id = $intake['intake_id'];
                    $clients = $intake_clients_data[$intake_id] ?? [];
                    $intake_followups = $followups_by_intake[$intake_id] ?? ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'items' => []];
                    
                    $client_progress = $intake['minimum_clients'] > 0 ? min(($intake['qualifying_clients'] / $intake['minimum_clients']) * 100, 100) : 0;
                    $fee_progress = $virtual_fee_threshold > 0 ? min(($intake['fee_collection_pct'] / $virtual_fee_threshold) * 100, 100) : 0;
                ?>
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($intake['description'] ?: $intake['course']); ?></h5>
                                    <small class="opacity-75"><?php echo $intake['start_date'] ? date('M Y', strtotime($intake['start_date'])) : ''; ?> | <?php echo $intake['course']; ?></small>
                                </div>
                                <?php if ($intake['is_eligible']): ?>
                                    <span class="badge bg-success fs-6">
                                        <i class="fas fa-check-circle me-1"></i>Commission: KES <?php echo number_format($intake['earned_commission'] * $usd_to_kes, 0); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <?php if ($intake['clients_needed'] > 0): ?>
                            <div class="alert alert-warning py-2 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="goal-circle-sm me-3">
                                        <span class="number"><?php echo $intake['clients_needed']; ?></span>
                                    </div>
                                    <div>
                                        <strong><i class="fas fa-fire me-1"></i><?php echo $intake['clients_needed']; ?> more client<?php echo $intake['clients_needed'] > 1 ? 's' : ''; ?> to go!</strong>
                                        <?php if ($intake['fee_needed'] > 0): ?>
                                            <br><small>Also need <?php echo number_format($intake['fee_needed'], 0); ?>% more fee collection</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php elseif ($intake['is_eligible']): ?>
                            <div class="alert alert-success py-2 mb-3">
                                <i class="fas fa-trophy me-2"></i><strong>Commission Earned!</strong> You've met all requirements.
                            </div>
                            <?php endif; ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0 text-primary"><?php echo $intake['total_registered']; ?></h4>
                                        <small class="text-muted">Unique</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0 text-success"><?php echo $intake['converted_clients']; ?></h4>
                                        <small class="text-muted">Converted</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0 text-warning"><?php echo $intake['lead_clients']; ?></h4>
                                        <small class="text-muted">Leads</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 <?php echo ($intake_followups['overdue'] > 0) ? 'border-danger' : ''; ?>">
                                        <h4 class="mb-0 <?php echo ($intake_followups['overdue'] > 0) ? 'text-danger' : 'text-info'; ?>">
                                            <?php echo $intake_followups['overdue'] + $intake_followups['today']; ?>
                                        </h4>
                                        <small class="text-muted">Follow-ups</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small><strong>Qualifying Clients:</strong> <?php echo $intake['qualifying_clients']; ?> / <?php echo $intake['minimum_clients'] ?: '-'; ?></small>
                                    <small><?php echo number_format($client_progress, 0); ?>%</small>
                                </div>
                                <div class="progress progress-thin mb-2">
                                    <div class="progress-bar <?php echo $client_progress >= 100 ? 'bg-success' : 'bg-primary'; ?>" style="width: <?php echo $client_progress; ?>%"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-1">
                                    <small><strong>Fee Collection:</strong> <?php echo number_format($intake['fee_collection_pct'], 1); ?>% / <?php echo $virtual_fee_threshold; ?>%</small>
                                    <small><?php echo number_format($fee_progress, 0); ?>%</small>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar <?php echo $fee_progress >= 100 ? 'bg-success' : 'bg-warning'; ?>" style="width: <?php echo $fee_progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="row text-center mb-3 small">
                                <div class="col-4">
                                    <span class="text-muted">Invoiced</span><br>
                                    <strong>$<?php echo number_format($intake['total_invoiced'], 0); ?></strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted">Collected</span><br>
                                    <strong class="text-success">$<?php echo number_format($intake['total_collected'], 0); ?></strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted">Outstanding</span><br>
                                    <strong class="text-danger">$<?php echo number_format($intake['outstanding'], 0); ?></strong>
                                </div>
                            </div>
                            
                            <?php if (!empty($intake_followups['items'])): ?>
                            <div class="border-top pt-3 mb-3">
                                <h6 class="mb-2">
                                    <i class="fas fa-bell me-1 text-warning"></i>Follow-ups
                                    <?php if ($intake_followups['overdue'] > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $intake_followups['overdue']; ?> overdue</span>
                                    <?php endif; ?>
                                </h6>
                                <div class="list-group list-group-flush small">
                                    <?php 
                                    $shown = 0;
                                    foreach ($intake_followups['items'] as $fu): 
                                        if ($shown >= 3) break;
                                        $shown++;
                                        $is_overdue = $fu['reminder_date'] < date('Y-m-d');
                                        $is_today = $fu['reminder_date'] == date('Y-m-d');
                                    ?>
                                        <div class="list-group-item px-0 py-2 <?php echo $is_overdue ? 'text-danger' : ($is_today ? 'text-warning' : ''); ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($fu['client_name']); ?></strong>
                                                    <br><small><?php echo htmlspecialchars($fu['next_step']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <small><?php echo date('M d', strtotime($fu['reminder_date'])); ?></small>
                                                    <?php if ($fu['client_phone']): ?>
                                                        <br><a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $fu['client_phone']); ?>" class="btn btn-sm btn-outline-success py-0 px-1" target="_blank"><i class="fab fa-whatsapp"></i></a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="accordion" id="clientsAccordion<?php echo $intake_count; ?>">
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-0" type="button" data-bs-toggle="collapse" data-bs-target="#clients<?php echo $intake_count; ?>">
                                            <i class="fas fa-users me-2"></i>View All Enquiries & Balances (<?php echo count($clients); ?> unique)
                                        </button>
                                    </h2>
                                    <div id="clients<?php echo $intake_count; ?>" class="accordion-collapse collapse">
                                        <div class="accordion-body p-0">
                                            <?php if (!empty($clients)): ?>
                                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                                <table class="table table-sm table-hover mb-0 small">
                                                    <thead class="table-light sticky-top">
                                                        <tr>
                                                            <th>Client</th>
                                                            <th class="text-end">Fee</th>
                                                            <th class="text-end">Paid</th>
                                                            <th class="text-end">Balance</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($clients as $client): 
                                                            $balance_class = $client['balance'] > 0 ? ($client['amount_paid'] == 0 ? 'balance-danger' : 'balance-warning') : 'balance-success';
                                                            $paid_pct = $client['course_fee'] > 0 ? ($client['amount_paid'] / $client['course_fee']) * 100 : 0;
                                                        ?>
                                                            <tr class="clickable-row" 
                                                                data-href="enquiry_details.php?type=<?php echo htmlspecialchars($client['source_table']); ?>&id=<?php echo htmlspecialchars($client['reference']); ?>"
                                                                style="cursor: pointer;">
                                                                <td>
                                                                    <i class="fas fa-external-link-alt text-muted me-1" style="font-size: 0.7rem;"></i>
                                                                    <?php echo htmlspecialchars($client['firstname'] . ' ' . $client['lastname']); ?>
                                                                    <?php if ($client['phone']): ?>
                                                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $client['phone']); ?>" class="ms-1" target="_blank" onclick="event.stopPropagation();"><i class="fab fa-whatsapp text-success"></i></a>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end">$<?php echo number_format($client['course_fee'], 0); ?></td>
                                                                <td class="text-end text-success">$<?php echo number_format($client['amount_paid'], 0); ?></td>
                                                                <td class="text-end <?php echo $balance_class; ?>">$<?php echo number_format($client['balance'], 0); ?></td>
                                                                <td>
                                                                    <?php if ($client['amount_paid'] == 0): ?>
                                                                        <span class="badge bg-danger">Lead</span>
                                                                    <?php elseif ($paid_pct >= $virtual_client_threshold): ?>
                                                                        <span class="badge bg-success">OK</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning text-dark"><?php echo number_format($paid_pct, 0); ?>%</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php else: ?>
                                                <p class="text-muted text-center py-3 mb-0">No clients registered yet.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($assigned_intakes)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No intakes assigned to you yet.
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- International Events -->
            <?php if ($staff_type === 'international' || $staff_type === 'both'): ?>
            <div class="row">
                <?php 
                $event_count = 0;
                foreach ($assigned_events as $event): 
                    $event_count++;
                    
                    $event_id = $event['event_id'];
                    $clients = $event_clients_data[$event_id] ?? [];
                    $event_followups = $followups_by_event[$event_id] ?? ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'items' => []];
                    
                    $client_progress = $event['minimum_clients'] > 0 ? min(($event['qualifying_clients'] / $event['minimum_clients']) * 100, 100) : 0;
                    $fee_progress = $intl_fee_threshold > 0 ? min(($event['fee_collection_pct'] / $intl_fee_threshold) * 100, 100) : 0;
                ?>
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-danger text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($event['event_title']); ?></h5>
                                    <small class="opacity-75"><?php echo $event['start_on'] ? date('M Y', strtotime($event['start_on'])) : ''; ?></small>
                                </div>
                                <?php if ($event['is_eligible']): ?>
                                    <span class="badge bg-success fs-6">
                                        <i class="fas fa-check-circle me-1"></i>Commission: KES <?php echo number_format($event['earned_commission'] * $usd_to_kes, 0); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <?php if ($event['clients_needed'] > 0): ?>
                            <div class="alert alert-warning py-2 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="goal-circle-sm me-3">
                                        <span class="number"><?php echo $event['clients_needed']; ?></span>
                                    </div>
                                    <div>
                                        <strong><i class="fas fa-fire me-1"></i><?php echo $event['clients_needed']; ?> more client<?php echo $event['clients_needed'] > 1 ? 's' : ''; ?> to go!</strong>
                                        <?php if ($event['fee_needed'] > 0): ?>
                                            <br><small>Also need <?php echo number_format($event['fee_needed'], 0); ?>% more fee collection</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php elseif ($event['is_eligible']): ?>
                            <div class="alert alert-success py-2 mb-3">
                                <i class="fas fa-trophy me-2"></i><strong>Commission Earned!</strong> You've met all requirements.
                            </div>
                            <?php endif; ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0 text-danger"><?php echo $event['total_registered']; ?></h4>
                                        <small class="text-muted">Unique</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0 text-success"><?php echo $event['converted_clients']; ?></h4>
                                        <small class="text-muted">Converted</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2">
                                        <h4 class="mb-0 text-warning"><?php echo $event['lead_clients']; ?></h4>
                                        <small class="text-muted">Leads</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 <?php echo ($event_followups['overdue'] > 0) ? 'border-danger' : ''; ?>">
                                        <h4 class="mb-0 <?php echo ($event_followups['overdue'] > 0) ? 'text-danger' : 'text-info'; ?>">
                                            <?php echo $event_followups['overdue'] + $event_followups['today']; ?>
                                        </h4>
                                        <small class="text-muted">Follow-ups</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small><strong>Qualifying Clients:</strong> <?php echo $event['qualifying_clients']; ?> / <?php echo $event['minimum_clients'] ?: '-'; ?></small>
                                    <small><?php echo number_format($client_progress, 0); ?>%</small>
                                </div>
                                <div class="progress progress-thin mb-2">
                                    <div class="progress-bar <?php echo $client_progress >= 100 ? 'bg-success' : 'bg-danger'; ?>" style="width: <?php echo $client_progress; ?>%"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-1">
                                    <small><strong>Fee Collection:</strong> <?php echo number_format($event['fee_collection_pct'], 1); ?>% / <?php echo $intl_fee_threshold; ?>%</small>
                                    <small><?php echo number_format($fee_progress, 0); ?>%</small>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar <?php echo $fee_progress >= 100 ? 'bg-success' : 'bg-warning'; ?>" style="width: <?php echo $fee_progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="row text-center mb-3 small">
                                <div class="col-4">
                                    <span class="text-muted">Invoiced</span><br>
                                    <strong>$<?php echo number_format($event['total_invoiced'], 0); ?></strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted">Collected</span><br>
                                    <strong class="text-success">$<?php echo number_format($event['total_collected'], 0); ?></strong>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted">Outstanding</span><br>
                                    <strong class="text-danger">$<?php echo number_format($event['outstanding'], 0); ?></strong>
                                </div>
                            </div>
                            
                            <?php if (!empty($event_followups['items'])): ?>
                            <div class="border-top pt-3 mb-3">
                                <h6 class="mb-2">
                                    <i class="fas fa-bell me-1 text-warning"></i>Follow-ups
                                    <?php if ($event_followups['overdue'] > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $event_followups['overdue']; ?> overdue</span>
                                    <?php endif; ?>
                                </h6>
                                <div class="list-group list-group-flush small">
                                    <?php 
                                    $shown = 0;
                                    foreach ($event_followups['items'] as $fu): 
                                        if ($shown >= 3) break;
                                        $shown++;
                                        $is_overdue = $fu['reminder_date'] < date('Y-m-d');
                                        $is_today = $fu['reminder_date'] == date('Y-m-d');
                                    ?>
                                        <div class="list-group-item px-0 py-2 <?php echo $is_overdue ? 'text-danger' : ($is_today ? 'text-warning' : ''); ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($fu['client_name']); ?></strong>
                                                    <br><small><?php echo htmlspecialchars($fu['next_step']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <small><?php echo date('M d', strtotime($fu['reminder_date'])); ?></small>
                                                    <?php if ($fu['client_phone']): ?>
                                                        <br><a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $fu['client_phone']); ?>" class="btn btn-sm btn-outline-success py-0 px-1" target="_blank"><i class="fab fa-whatsapp"></i></a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="accordion" id="eventClientsAccordion<?php echo $event_count; ?>">
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 px-0" type="button" data-bs-toggle="collapse" data-bs-target="#eventClients<?php echo $event_count; ?>">
                                            <i class="fas fa-users me-2"></i>View All Enquiries & Balances (<?php echo count($clients); ?> unique)
                                        </button>
                                    </h2>
                                    <div id="eventClients<?php echo $event_count; ?>" class="accordion-collapse collapse">
                                        <div class="accordion-body p-0">
                                            <?php if (!empty($clients)): ?>
                                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                                <table class="table table-sm table-hover mb-0 small">
                                                    <thead class="table-light sticky-top">
                                                        <tr>
                                                            <th>Client</th>
                                                            <th class="text-end">Fee</th>
                                                            <th class="text-end">Paid</th>
                                                            <th class="text-end">Balance</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($clients as $client): 
                                                            $balance_class = $client['balance'] > 0 ? ($client['amount_paid'] == 0 ? 'balance-danger' : 'balance-warning') : 'balance-success';
                                                            $paid_pct = $client['event_fee'] > 0 ? ($client['amount_paid'] / $client['event_fee']) * 100 : 0;
                                                        ?>
                                                            <tr class="clickable-row" 
                                                                data-href="enquiry_details.php?type=<?php echo htmlspecialchars($client['source_table']); ?>&id=<?php echo htmlspecialchars($client['reference']); ?>"
                                                                style="cursor: pointer;">
                                                                <td>
                                                                    <i class="fas fa-external-link-alt text-muted me-1" style="font-size: 0.7rem;"></i>
                                                                    <?php echo htmlspecialchars($client['client_name']); ?>
                                                                    <?php if ($client['phone']): ?>
                                                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $client['phone']); ?>" class="ms-1" target="_blank" onclick="event.stopPropagation();"><i class="fab fa-whatsapp text-success"></i></a>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end">$<?php echo number_format($client['event_fee'], 0); ?></td>
                                                                <td class="text-end text-success">$<?php echo number_format($client['amount_paid'], 0); ?></td>
                                                                <td class="text-end <?php echo $balance_class; ?>">$<?php echo number_format($client['balance'], 0); ?></td>
                                                                <td>
                                                                    <?php if ($client['amount_paid'] == 0): ?>
                                                                        <span class="badge bg-danger">Lead</span>
                                                                    <?php elseif ($paid_pct >= $intl_client_threshold): ?>
                                                                        <span class="badge bg-success">OK</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning text-dark"><?php echo number_format($paid_pct, 0); ?>%</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php else: ?>
                                                <p class="text-muted text-center py-3 mb-0">No clients registered yet.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($assigned_events)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No events assigned to you yet.
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<!-- JavaScript for clickable rows -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make table rows clickable
    document.querySelectorAll('.clickable-row').forEach(function(row) {
        row.addEventListener('click', function() {
            var href = this.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>