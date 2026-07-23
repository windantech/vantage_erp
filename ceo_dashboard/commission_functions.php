<?php
/**
 * Commission Functions
 * Calculation logic for Virtual (Intakes) and International (Events)
 * 
 * Data Flow:
 * - Virtual: intake → course (price_usd) → register (intake_id) → dpo_payment (app_id = entry_id)
 * - International: Event (early_amount) → ticket_congress (event_id) → dpo_payment (app_id = ticket_id)
 * 
 * Fee Collection % = Only calculated against customers who have paid at least something (converted clients)
 */

/**
 * Get commission setting from database
 */
function getCommissionSetting($conn, $key, $default = '') {
    $key = mysqli_real_escape_string($conn, $key);
    $result = mysqli_query($conn, "SELECT setting_value FROM commission_settings WHERE setting_key = '$key' LIMIT 1");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Set commission setting in database
 */
function setCommissionSetting($conn, $key, $value, $user_id = null) {
    $key = mysqli_real_escape_string($conn, $key);
    $value = mysqli_real_escape_string($conn, $value);
    $user_clause = $user_id ? ", updated_by = $user_id" : "";
    return mysqli_query($conn, "
        INSERT INTO commission_settings (setting_key, setting_value" . ($user_id ? ", updated_by" : "") . ") 
        VALUES ('$key', '$value'" . ($user_id ? ", $user_id" : "") . ")
        ON DUPLICATE KEY UPDATE setting_value = '$value' $user_clause
    ");
}

/**
 * Calculate commission for a Virtual Intake
 * 
 * Flow: intake.intake_id → register.intake_id → register.entry_id → dpo_payment.app_id
 * 
 * Fee Collection % = Only against customers who have paid at least something (converted clients)
 * 
 * @param mysqli $conn Database connection
 * @param int $intake_id Intake ID (the auto-increment id, used to lookup intake_id varchar)
 * @return array Calculation results
 */
function calculateIntakeCommission($conn, $intake_id) {
    $intake_id = intval($intake_id);
    
    // Get global thresholds
    $fee_collection_threshold = floatval(getCommissionSetting($conn, 'virtual_fee_collection_threshold', '80'));
    $client_payment_threshold = floatval(getCommissionSetting($conn, 'virtual_client_payment_threshold', '80'));
    
    // Get intake details with course info
    $intake_q = mysqli_query($conn, "
        SELECT 
            i.id,
            i.intake_id AS intake_code,
            i.description AS intake_name,
            i.course_id,
            i.start_date,
            i.assigned_to,
            i.minimum_clients,
            i.commission_rate,
            c.course AS course_name,
            c.price_usd AS course_fee,
            ru.id AS staff_user_id,
            ru.fullname AS staff_name
        FROM intake i
        LEFT JOIN course c ON i.course_id = c.course_id
        LEFT JOIN registered_users ru ON i.assigned_to = ru.id
        WHERE i.id = $intake_id
    ");
    
    if (!$intake_q || mysqli_num_rows($intake_q) == 0) {
        return ['success' => false, 'message' => 'Intake not found'];
    }
    
    $intake = mysqli_fetch_assoc($intake_q);
    
    if (!$intake['assigned_to']) {
        return ['success' => false, 'message' => 'No staff assigned to this intake'];
    }
    
    if ($intake['minimum_clients'] <= 0 || $intake['commission_rate'] <= 0) {
        return ['success' => false, 'message' => 'Commission not configured for this intake'];
    }
    
    $course_fee = floatval($intake['course_fee']);
    $min_client_payment = $course_fee * ($client_payment_threshold / 100);
    
    // Use intake_code (intake.intake_id varchar) to join with register table
    $intake_code = mysqli_real_escape_string($conn, $intake['intake_code']);
    
    // Pre-load payment totals for this intake's registrations
    $payment_totals = [];
    $pay_q = mysqli_query($conn, "
        SELECT p.app_id, SUM(p.TransactionAmount) AS total_paid
        FROM dpo_payment p
        INNER JOIN register r ON p.app_id = r.entry_id
        WHERE r.intake_id = '$intake_code' AND p.status = 2
        GROUP BY p.app_id
    ");
    if ($pay_q) {
        while ($row = mysqli_fetch_assoc($pay_q)) {
            $payment_totals[$row['app_id']] = floatval($row['total_paid']);
        }
    }
    
    // Get all registrations for this intake
    $reg_q = mysqli_query($conn, "
        SELECT id, entry_id, email, firstname, lastname
        FROM register
        WHERE intake_id = '$intake_code'
    ");
    
    // Deduplicate by email - keep record with highest payment
    $unique_clients = [];
    while ($r = mysqli_fetch_assoc($reg_q)) {
        $email = strtolower(trim($r['email']));
        $paid = $payment_totals[$r['entry_id']] ?? 0;
        
        if (!isset($unique_clients[$email])) {
            $r['total_paid'] = $paid;
            $unique_clients[$email] = $r;
        } else {
            if ($paid > $unique_clients[$email]['total_paid']) {
                $r['total_paid'] = $paid;
                $unique_clients[$email] = $r;
            }
        }
    }
    
    // Calculate stats from deduplicated data
    $total_registered = 0;
    $converted_clients = 0; // Clients who paid something (> 0)
    $qualifying_clients = 0; // Clients who paid >= threshold
    $total_collected = 0;
    $total_expected_from_converted = 0;
    
    foreach ($unique_clients as $client) {
        $total_registered++;
        $paid = $client['total_paid'];
        
        if ($paid > 0) {
            $converted_clients++;
            $total_collected += $paid;
            $total_expected_from_converted += $course_fee;
            
            if ($paid >= $min_client_payment) {
                $qualifying_clients++;
            }
        }
    }
    
    // Total expected from ALL registered (for reference)
    $total_expected_fees = $total_registered * $course_fee;
    
    // Calculate fee collection percentage ONLY against converted clients (those who paid something)
    $fee_collection_pct = $total_expected_from_converted > 0 
        ? ($total_collected / $total_expected_from_converted) * 100 
        : 0;
    
    // Check eligibility
    $min_clients_met = $qualifying_clients >= $intake['minimum_clients'];
    $fee_collection_met = $fee_collection_pct >= $fee_collection_threshold;
    $is_eligible = $min_clients_met && $fee_collection_met;
    
    // Calculate commission
    $commission_per_client = $course_fee * ($intake['commission_rate'] / 100);
    $commission_amount = $is_eligible ? ($commission_per_client * $qualifying_clients) : 0;
    
    // Build eligibility notes
    $notes = [];
    if (!$min_clients_met) {
        $notes[] = "Min clients not met: {$qualifying_clients}/{$intake['minimum_clients']}";
    }
    if (!$fee_collection_met) {
        $notes[] = "Fee collection not met: " . number_format($fee_collection_pct, 1) . "%/{$fee_collection_threshold}% (from {$converted_clients} paying clients)";
    }
    if ($is_eligible) {
        $notes[] = "All conditions met!";
    }
    
    return [
        'success' => true,
        'data' => [
            // Source info
            'commission_type' => 'virtual',
            'source_id' => $intake_id,
            'source_name' => $intake['intake_name'],
            'course_name' => $intake['course_name'],
            
            // Staff info
            'staff_user_id' => $intake['staff_user_id'],
            'staff_name' => $intake['staff_name'],
            
            // Targets
            'minimum_clients_required' => intval($intake['minimum_clients']),
            'fee_collection_threshold' => $fee_collection_threshold,
            'client_payment_threshold' => $client_payment_threshold,
            'commission_rate' => floatval($intake['commission_rate']),
            
            // Actuals
            'total_registered' => $total_registered,
            'converted_clients' => $converted_clients,
            'qualifying_clients' => $qualifying_clients,
            'total_expected_fees' => $total_expected_fees,
            'total_expected_from_converted' => $total_expected_from_converted,
            'total_collected_fees' => $total_collected,
            'fee_collection_pct' => round($fee_collection_pct, 2),
            
            // Eligibility
            'min_clients_met' => $min_clients_met,
            'fee_collection_met' => $fee_collection_met,
            'is_eligible' => $is_eligible,
            'eligibility_notes' => implode('; ', $notes),
            
            // Commission
            'unit_fee' => $course_fee,
            'commission_per_client' => $commission_per_client,
            'commission_amount' => $commission_amount,
            'currency' => 'USD'
        ]
    ];
}

/**
 * Calculate commission for an International Event
 * 
 * Flow: Event (early_amount) → ticket_congress (event_id) → dpo_payment (app_id = ticket_id)
 * 
 * Fee Collection % = Only against customers who have paid at least something (converted clients)
 * 
 * @param mysqli $conn Database connection
 * @param int $event_id Event ID
 * @return array Calculation results
 */
function calculateEventCommission($conn, $event_id) {
    $event_id = intval($event_id);
    
    // Get global thresholds
    $fee_collection_threshold = floatval(getCommissionSetting($conn, 'international_fee_collection_threshold', '90'));
    $client_payment_threshold = floatval(getCommissionSetting($conn, 'international_client_payment_threshold', '100'));
    
    // Get event details
    $event_q = mysqli_query($conn, "
        SELECT 
            e.event_id,
            e.event_title,
            e.early_amount AS event_fee,
            e.currency_code,
            e.start_on,
            e.assigned_to,
            e.minimum_clients,
            e.commission_rate,
            ru.id AS staff_user_id,
            ru.fullname AS staff_name
        FROM Event e
        LEFT JOIN registered_users ru ON e.assigned_to = ru.id
        WHERE e.event_id = $event_id
    ");
    
    if (!$event_q || mysqli_num_rows($event_q) == 0) {
        return ['success' => false, 'message' => 'Event not found'];
    }
    
    $event = mysqli_fetch_assoc($event_q);
    
    if (!$event['assigned_to']) {
        return ['success' => false, 'message' => 'No staff assigned to this event'];
    }
    
    if ($event['minimum_clients'] <= 0 || $event['commission_rate'] <= 0) {
        return ['success' => false, 'message' => 'Commission not configured for this event'];
    }
    
    $event_fee = floatval($event['event_fee']);
    $min_client_payment = $event_fee * ($client_payment_threshold / 100); // 100% for international
    $currency = $event['currency_code'] ?: 'USD';
    
    // Pre-load payment totals for this event's tickets
    $payment_totals = [];
    $pay_q = mysqli_query($conn, "
        SELECT p.app_id, SUM(p.TransactionAmount) AS total_paid
        FROM dpo_payment p
        INNER JOIN ticket_congress t ON p.app_id = t.ticket_id
        WHERE t.event_id = $event_id AND p.status = 2
        GROUP BY p.app_id
    ");
    if ($pay_q) {
        while ($row = mysqli_fetch_assoc($pay_q)) {
            $payment_totals[$row['app_id']] = floatval($row['total_paid']);
        }
    }
    
    // Get all tickets for this event
    $ticket_q = mysqli_query($conn, "
        SELECT id, ticket_id, email, fullname
        FROM ticket_congress
        WHERE event_id = $event_id
    ");
    
    // Deduplicate by email - keep record with highest payment
    $unique_clients = [];
    while ($t = mysqli_fetch_assoc($ticket_q)) {
        $email = strtolower(trim($t['email']));
        $paid = $payment_totals[$t['ticket_id']] ?? 0;
        
        if (!isset($unique_clients[$email])) {
            $t['total_paid'] = $paid;
            $unique_clients[$email] = $t;
        } else {
            if ($paid > $unique_clients[$email]['total_paid']) {
                $t['total_paid'] = $paid;
                $unique_clients[$email] = $t;
            }
        }
    }
    
    // Calculate stats from deduplicated data
    $total_registered = 0;
    $converted_clients = 0; // Clients who paid something (> 0)
    $qualifying_clients = 0; // Clients who paid >= threshold (100% for international)
    $total_collected = 0;
    $total_expected_from_converted = 0;
    
    foreach ($unique_clients as $client) {
        $total_registered++;
        $paid = $client['total_paid'];
        
        if ($paid > 0) {
            $converted_clients++;
            $total_collected += $paid;
            $total_expected_from_converted += $event_fee;
            
            if ($paid >= $min_client_payment) {
                $qualifying_clients++;
            }
        }
    }
    
    // Total expected from ALL registered (for reference)
    $total_expected_fees = $total_registered * $event_fee;
    
    // Calculate fee collection percentage ONLY against converted clients (those who paid something)
    $fee_collection_pct = $total_expected_from_converted > 0 
        ? ($total_collected / $total_expected_from_converted) * 100 
        : 0;
    
    // Check eligibility
    $min_clients_met = $qualifying_clients >= $event['minimum_clients'];
    $fee_collection_met = $fee_collection_pct >= $fee_collection_threshold;
    $is_eligible = $min_clients_met && $fee_collection_met;
    
    // Calculate commission
    $commission_per_client = $event_fee * ($event['commission_rate'] / 100);
    $commission_amount = $is_eligible ? ($commission_per_client * $qualifying_clients) : 0;
    
    // Build eligibility notes
    $notes = [];
    if (!$min_clients_met) {
        $notes[] = "Min clients not met: {$qualifying_clients}/{$event['minimum_clients']}";
    }
    if (!$fee_collection_met) {
        $notes[] = "Fee collection not met: " . number_format($fee_collection_pct, 1) . "%/{$fee_collection_threshold}% (from {$converted_clients} paying clients)";
    }
    if ($is_eligible) {
        $notes[] = "All conditions met!";
    }
    
    return [
        'success' => true,
        'data' => [
            // Source info
            'commission_type' => 'international',
            'source_id' => $event_id,
            'source_name' => $event['event_title'],
            
            // Staff info
            'staff_user_id' => $event['staff_user_id'],
            'staff_name' => $event['staff_name'],
            
            // Targets
            'minimum_clients_required' => intval($event['minimum_clients']),
            'fee_collection_threshold' => $fee_collection_threshold,
            'client_payment_threshold' => $client_payment_threshold,
            'commission_rate' => floatval($event['commission_rate']),
            
            // Actuals
            'total_registered' => $total_registered,
            'converted_clients' => $converted_clients,
            'qualifying_clients' => $qualifying_clients,
            'total_expected_fees' => $total_expected_fees,
            'total_expected_from_converted' => $total_expected_from_converted,
            'total_collected_fees' => $total_collected,
            'fee_collection_pct' => round($fee_collection_pct, 2),
            
            // Eligibility
            'min_clients_met' => $min_clients_met,
            'fee_collection_met' => $fee_collection_met,
            'is_eligible' => $is_eligible,
            'eligibility_notes' => implode('; ', $notes),
            
            // Commission
            'unit_fee' => $event_fee,
            'commission_per_client' => $commission_per_client,
            'commission_amount' => $commission_amount,
            'currency' => $currency
        ]
    ];
}

/**
 * Save commission calculation to database
 * 
 * @param mysqli $conn Database connection
 * @param array $data Calculation data from calculateIntakeCommission or calculateEventCommission
 * @param int $calculated_by User ID who performed calculation
 * @return array Result
 */
function saveCommissionRecord($conn, $data, $calculated_by) {
    $calculated_by = intval($calculated_by);
    
    // Escape strings
    $source_name = mysqli_real_escape_string($conn, $data['source_name']);
    $staff_name = mysqli_real_escape_string($conn, $data['staff_name']);
    $eligibility_notes = mysqli_real_escape_string($conn, $data['eligibility_notes']);
    $currency = mysqli_real_escape_string($conn, $data['currency']);
    
    // Delete existing record if any
    $type = mysqli_real_escape_string($conn, $data['commission_type']);
    $source_id = intval($data['source_id']);
    
    mysqli_query($conn, "DELETE FROM commission_records WHERE commission_type = '$type' AND source_id = $source_id");
    
    // Insert new record (using existing table structure)
    $query = "
        INSERT INTO commission_records (
            commission_type, source_id, source_name,
            staff_user_id, staff_name,
            minimum_clients_required, fee_collection_threshold, client_payment_threshold, commission_rate,
            total_registered, qualifying_clients, total_expected_fees, total_collected_fees, fee_collection_percentage,
            unit_fee, commission_per_client, commission_amount, commission_currency,
            min_clients_met, fee_collection_met, is_eligible, eligibility_notes,
            status, calculated_by, calculated_at
        ) VALUES (
            '$type', $source_id, '$source_name',
            {$data['staff_user_id']}, '$staff_name',
            {$data['minimum_clients_required']}, {$data['fee_collection_threshold']}, {$data['client_payment_threshold']}, {$data['commission_rate']},
            {$data['total_registered']}, {$data['qualifying_clients']}, {$data['total_expected_from_converted']}, {$data['total_collected_fees']}, {$data['fee_collection_pct']},
            {$data['unit_fee']}, {$data['commission_per_client']}, {$data['commission_amount']}, '$currency',
            " . ($data['min_clients_met'] ? 1 : 0) . ", " . ($data['fee_collection_met'] ? 1 : 0) . ", " . ($data['is_eligible'] ? 1 : 0) . ", '$eligibility_notes',
            'draft', $calculated_by, NOW()
        )
    ";
    
    if (mysqli_query($conn, $query)) {
        return ['success' => true, 'record_id' => mysqli_insert_id($conn)];
    } else {
        return ['success' => false, 'message' => 'Failed to save: ' . mysqli_error($conn)];
    }
}

/**
 * Get list of qualifying clients for an intake (Virtual)
 * 
 * @param mysqli $conn Database connection
 * @param int $intake_id Intake ID (auto-increment id)
 * @return array List of qualifying clients
 */
function getIntakeQualifyingClients($conn, $intake_id) {
    $intake_id = intval($intake_id);
    $client_threshold = floatval(getCommissionSetting($conn, 'virtual_client_payment_threshold', '80'));
    
    // Get course fee and intake_code (varchar)
    $fee_q = mysqli_query($conn, "
        SELECT i.intake_id AS intake_code, c.price_usd 
        FROM intake i 
        INNER JOIN course c ON i.course_id = c.course_id 
        WHERE i.id = $intake_id
    ");
    
    if (!$fee_q || mysqli_num_rows($fee_q) == 0) {
        return [];
    }
    
    $intake_data = mysqli_fetch_assoc($fee_q);
    $course_fee = floatval($intake_data['price_usd']);
    $intake_code = mysqli_real_escape_string($conn, $intake_data['intake_code']);
    $min_payment = $course_fee * ($client_threshold / 100);
    
    // Pre-load payment totals
    $payment_totals = [];
    $pay_q = mysqli_query($conn, "
        SELECT p.app_id, SUM(p.TransactionAmount) AS total_paid
        FROM dpo_payment p
        INNER JOIN register r ON p.app_id = r.entry_id
        WHERE r.intake_id = '$intake_code' AND p.status = 2
        GROUP BY p.app_id
    ");
    if ($pay_q) {
        while ($row = mysqli_fetch_assoc($pay_q)) {
            $payment_totals[$row['app_id']] = floatval($row['total_paid']);
        }
    }
    
    // Get registrations
    $reg_q = mysqli_query($conn, "
        SELECT id, entry_id, firstname, lastname, email, phone_number, datee
        FROM register
        WHERE intake_id = '$intake_code'
        ORDER BY datee DESC
    ");
    
    // Deduplicate by email - keep record with highest payment
    $unique_clients = [];
    while ($r = mysqli_fetch_assoc($reg_q)) {
        $email = strtolower(trim($r['email']));
        $paid = $payment_totals[$r['entry_id']] ?? 0;
        
        if (!isset($unique_clients[$email])) {
            $r['total_paid'] = $paid;
            $unique_clients[$email] = $r;
        } else {
            if ($paid > $unique_clients[$email]['total_paid']) {
                $r['total_paid'] = $paid;
                $unique_clients[$email] = $r;
            }
        }
    }
    
    // Filter to qualifying clients only
    $clients = [];
    foreach ($unique_clients as $client) {
        if ($client['total_paid'] >= $min_payment) {
            $clients[] = [
                'id' => $client['id'],
                'entry_id' => $client['entry_id'],
                'fullname' => $client['firstname'] . ' ' . $client['lastname'],
                'email' => $client['email'],
                'phone_number' => $client['phone_number'],
                'registered_date' => $client['datee'],
                'course_fee' => $course_fee,
                'total_paid' => $client['total_paid'],
                'payment_percentage' => round(($client['total_paid'] / $course_fee) * 100, 1)
            ];
        }
    }
    
    // Sort by total_paid descending
    usort($clients, function($a, $b) {
        return $b['total_paid'] - $a['total_paid'];
    });
    
    return $clients;
}

/**
 * Get list of qualifying clients for an event (International)
 * 
 * @param mysqli $conn Database connection
 * @param int $event_id Event ID
 * @return array List of qualifying clients
 */
function getEventQualifyingClients($conn, $event_id) {
    $event_id = intval($event_id);
    $client_threshold = floatval(getCommissionSetting($conn, 'international_client_payment_threshold', '100'));
    
    // Get event fee
    $fee_q = mysqli_query($conn, "SELECT early_amount, currency_code FROM Event WHERE event_id = $event_id");
    
    if (!$fee_q || mysqli_num_rows($fee_q) == 0) {
        return [];
    }
    
    $event = mysqli_fetch_assoc($fee_q);
    $event_fee = floatval($event['early_amount']);
    $min_payment = $event_fee * ($client_threshold / 100);
    
    // Pre-load payment totals
    $payment_totals = [];
    $pay_q = mysqli_query($conn, "
        SELECT p.app_id, SUM(p.TransactionAmount) AS total_paid
        FROM dpo_payment p
        INNER JOIN ticket_congress t ON p.app_id = t.ticket_id
        WHERE t.event_id = $event_id AND p.status = 2
        GROUP BY p.app_id
    ");
    if ($pay_q) {
        while ($row = mysqli_fetch_assoc($pay_q)) {
            $payment_totals[$row['app_id']] = floatval($row['total_paid']);
        }
    }
    
    // Get tickets
    $ticket_q = mysqli_query($conn, "
        SELECT id, ticket_id, fullname, email, phone_number, date_sent
        FROM ticket_congress
        WHERE event_id = $event_id
        ORDER BY date_sent DESC
    ");
    
    // Deduplicate by email - keep record with highest payment
    $unique_clients = [];
    while ($t = mysqli_fetch_assoc($ticket_q)) {
        $email = strtolower(trim($t['email']));
        $paid = $payment_totals[$t['ticket_id']] ?? 0;
        
        if (!isset($unique_clients[$email])) {
            $t['total_paid'] = $paid;
            $unique_clients[$email] = $t;
        } else {
            if ($paid > $unique_clients[$email]['total_paid']) {
                $t['total_paid'] = $paid;
                $unique_clients[$email] = $t;
            }
        }
    }
    
    // Filter to qualifying clients only
    $clients = [];
    foreach ($unique_clients as $client) {
        if ($client['total_paid'] >= $min_payment) {
            $clients[] = [
                'id' => $client['id'],
                'ticket_id' => $client['ticket_id'],
                'fullname' => $client['fullname'],
                'email' => $client['email'],
                'phone_number' => $client['phone_number'],
                'registered_date' => $client['date_sent'],
                'event_fee' => $event_fee,
                'total_paid' => $client['total_paid'],
                'payment_percentage' => round(($client['total_paid'] / $event_fee) * 100, 1)
            ];
        }
    }
    
    // Sort by total_paid descending
    usort($clients, function($a, $b) {
        return $b['total_paid'] - $a['total_paid'];
    });
    
    return $clients;
}

/**
 * Get list of all paying clients for an intake (Virtual) - includes partial payments
 * 
 * @param mysqli $conn Database connection
 * @param int $intake_id Intake ID (auto-increment id)
 * @return array List of all clients who have paid something
 */
function getIntakePayingClients($conn, $intake_id) {
    $intake_id = intval($intake_id);
    
    // Get course fee and intake_code
    $fee_q = mysqli_query($conn, "
        SELECT i.intake_id AS intake_code, c.price_usd 
        FROM intake i 
        INNER JOIN course c ON i.course_id = c.course_id 
        WHERE i.id = $intake_id
    ");
    
    if (!$fee_q || mysqli_num_rows($fee_q) == 0) {
        return [];
    }
    
    $intake_data = mysqli_fetch_assoc($fee_q);
    $course_fee = floatval($intake_data['price_usd']);
    $intake_code = mysqli_real_escape_string($conn, $intake_data['intake_code']);
    
    // Pre-load payment totals
    $payment_totals = [];
    $pay_q = mysqli_query($conn, "
        SELECT p.app_id, SUM(p.TransactionAmount) AS total_paid
        FROM dpo_payment p
        INNER JOIN register r ON p.app_id = r.entry_id
        WHERE r.intake_id = '$intake_code' AND p.status = 2
        GROUP BY p.app_id
    ");
    if ($pay_q) {
        while ($row = mysqli_fetch_assoc($pay_q)) {
            $payment_totals[$row['app_id']] = floatval($row['total_paid']);
        }
    }
    
    // Get registrations
    $reg_q = mysqli_query($conn, "
        SELECT id, entry_id, firstname, lastname, email, phone_number, datee
        FROM register
        WHERE intake_id = '$intake_code'
        ORDER BY datee DESC
    ");
    
    // Deduplicate by email
    $unique_clients = [];
    while ($r = mysqli_fetch_assoc($reg_q)) {
        $email = strtolower(trim($r['email']));
        $paid = $payment_totals[$r['entry_id']] ?? 0;
        
        if (!isset($unique_clients[$email])) {
            $r['total_paid'] = $paid;
            $unique_clients[$email] = $r;
        } else {
            if ($paid > $unique_clients[$email]['total_paid']) {
                $r['total_paid'] = $paid;
                $unique_clients[$email] = $r;
            }
        }
    }
    
    // Filter to paying clients only (paid > 0)
    $clients = [];
    foreach ($unique_clients as $client) {
        if ($client['total_paid'] > 0) {
            $clients[] = [
                'id' => $client['id'],
                'entry_id' => $client['entry_id'],
                'fullname' => $client['firstname'] . ' ' . $client['lastname'],
                'email' => $client['email'],
                'phone_number' => $client['phone_number'],
                'registered_date' => $client['datee'],
                'course_fee' => $course_fee,
                'total_paid' => $client['total_paid'],
                'balance' => $course_fee - $client['total_paid'],
                'payment_percentage' => round(($client['total_paid'] / $course_fee) * 100, 1)
            ];
        }
    }
    
    // Sort by total_paid descending
    usort($clients, function($a, $b) {
        return $b['total_paid'] - $a['total_paid'];
    });
    
    return $clients;
}

/**
 * Get list of all paying clients for an event (International) - includes partial payments
 * 
 * @param mysqli $conn Database connection
 * @param int $event_id Event ID
 * @return array List of all clients who have paid something
 */
function getEventPayingClients($conn, $event_id) {
    $event_id = intval($event_id);
    
    // Get event fee
    $fee_q = mysqli_query($conn, "SELECT early_amount, currency_code FROM Event WHERE event_id = $event_id");
    
    if (!$fee_q || mysqli_num_rows($fee_q) == 0) {
        return [];
    }
    
    $event = mysqli_fetch_assoc($fee_q);
    $event_fee = floatval($event['early_amount']);
    
    // Pre-load payment totals
    $payment_totals = [];
    $pay_q = mysqli_query($conn, "
        SELECT p.app_id, SUM(p.TransactionAmount) AS total_paid
        FROM dpo_payment p
        INNER JOIN ticket_congress t ON p.app_id = t.ticket_id
        WHERE t.event_id = $event_id AND p.status = 2
        GROUP BY p.app_id
    ");
    if ($pay_q) {
        while ($row = mysqli_fetch_assoc($pay_q)) {
            $payment_totals[$row['app_id']] = floatval($row['total_paid']);
        }
    }
    
    // Get tickets
    $ticket_q = mysqli_query($conn, "
        SELECT id, ticket_id, fullname, email, phone_number, date_sent
        FROM ticket_congress
        WHERE event_id = $event_id
        ORDER BY date_sent DESC
    ");
    
    // Deduplicate by email
    $unique_clients = [];
    while ($t = mysqli_fetch_assoc($ticket_q)) {
        $email = strtolower(trim($t['email']));
        $paid = $payment_totals[$t['ticket_id']] ?? 0;
        
        if (!isset($unique_clients[$email])) {
            $t['total_paid'] = $paid;
            $unique_clients[$email] = $t;
        } else {
            if ($paid > $unique_clients[$email]['total_paid']) {
                $t['total_paid'] = $paid;
                $unique_clients[$email] = $t;
            }
        }
    }
    
    // Filter to paying clients only (paid > 0)
    $clients = [];
    foreach ($unique_clients as $client) {
        if ($client['total_paid'] > 0) {
            $clients[] = [
                'id' => $client['id'],
                'ticket_id' => $client['ticket_id'],
                'fullname' => $client['fullname'],
                'email' => $client['email'],
                'phone_number' => $client['phone_number'],
                'registered_date' => $client['date_sent'],
                'event_fee' => $event_fee,
                'total_paid' => $client['total_paid'],
                'balance' => $event_fee - $client['total_paid'],
                'payment_percentage' => round(($client['total_paid'] / $event_fee) * 100, 1)
            ];
        }
    }
    
    // Sort by total_paid descending
    usort($clients, function($a, $b) {
        return $b['total_paid'] - $a['total_paid'];
    });
    
    return $clients;
}