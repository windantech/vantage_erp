<?php

/* ============================================================
 * Shared ERP lookups — previously supplied by an external function.php that
 * pages reached via "../../function.php". Re-homed here so every page that
 * includes function.php keeps these helpers. Guarded with function_exists so a
 * page-local copy can never double-declare.
 * ============================================================ */
if (!function_exists('check_course')) {
    function check_course($conn, $id) {
        $id = mysqli_real_escape_string($conn, (string)$id);
        $res = mysqli_query($conn, "SELECT course FROM course WHERE course_id = '$id' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) { return mysqli_fetch_assoc($res)['course']; }
        return $id;
    }
}
if (!function_exists('check_event')) {
    function check_event($conn, $event_id, $variable) {
        $event_id = (int)$event_id;
        $res = mysqli_query($conn, "SELECT * FROM `Event` WHERE event_id = $event_id LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return $row[$variable] ?? '';
    }
}
if (!function_exists('check_source')) {
    function check_source($id) {
        if ($id == 4) { return "Whatsapp"; }
        if ($id == 5) { return "Facebook"; }
        return "Any other source";
    }
}

/**
 * Commission Functions (optimized)
 * Calculation logic for Virtual (Intakes) and International (Events)
 *
 * Data Flow:
 * - Virtual: intake → course (price_usd) → register (intake_id) → dpo_payment (app_id = entry_id)
 * - International: Event (early_amount) → ticket_congress (event_id) → dpo_payment (app_id = ticket_id)
 *
 * REQUIRED INDEXES (run once) — these are what stop the "Sending data" stalls:
 *   ALTER TABLE dpo_payment     ADD INDEX idx_status_app (status, app_id);
 *   ALTER TABLE register        ADD INDEX idx_intake_entry (intake_id, entry_id);
 *   ALTER TABLE ticket_congress ADD INDEX idx_event_ticket (event_id, ticket_id);
 *   ALTER TABLE intake          ADD INDEX idx_course (course_id);
 *
 * IMPORTANT: intake.course_id and course.course_id MUST be the same column type.
 * If one is varchar and the other int, the LEFT JOIN course cannot use an index —
 * this is the root cause of the multi-hour "Sending data" queries.
 */


/* ============================================================
 * SHARED HELPERS
 * ============================================================ */

/**
 * Shared paid-per-app_id subquery.
 * Centralizes the aggregate so it's defined once and uses idx_status_app.
 */
function paidSubquery(): string {
    return "(SELECT app_id, SUM(TransactionAmount) AS total_paid
             FROM dpo_payment
             WHERE status = 2
             GROUP BY app_id)";
}

/**
 * Generic stats calculator shared by virtual + international.
 * Runs ONE query returning registered count, qualifying count, expected + collected.
 *
 * @param string $filterVal Pre-sanitized value: for varchar filters pass "'escaped'",
 *                          for int filters pass the intval directly.
 */
function computeCommissionStats(
    mysqli $conn,
    string $regTable,     // 'register' | 'ticket_congress'
    string $joinKey,      // 'entry_id' | 'ticket_id'
    string $filterCol,    // 'intake_id' | 'event_id'
    string $filterVal,    // already-safe value
    float  $unitFee,
    float  $minClientPayment
): array {
    $pay = paidSubquery();

    $q = mysqli_query($conn, "
        SELECT
            COUNT(x.id) AS total_registered,
            SUM(CASE WHEN COALESCE(pay.total_paid,0) >= $minClientPayment THEN 1 ELSE 0 END) AS qualifying_clients,
            COUNT(x.id) * $unitFee AS total_expected_fees,
            COALESCE(SUM(pay.total_paid),0) AS total_collected_fees
        FROM $regTable x
        LEFT JOIN $pay pay ON pay.app_id = x.$joinKey
        WHERE x.$filterCol = $filterVal
    ");

    if (!$q) {
        return ['error' => mysqli_error($conn)];
    }
    return mysqli_fetch_assoc($q);
}

/**
 * Shared result-builder: eligibility, commission math, and output array.
 * Eliminates the duplicated logic across the two calculators.
 */
function buildCommissionResult(
    $type, $source_id, $row, $stats,
    $unit_fee, $fee_collection_threshold, $client_payment_threshold, $currency
) {
    $total_registered     = intval($stats['total_registered']);
    $qualifying_clients   = intval($stats['qualifying_clients']);
    $total_expected_fees  = floatval($stats['total_expected_fees']);
    $total_collected_fees = floatval($stats['total_collected_fees']);

    $fee_collection_pct = $total_expected_fees > 0
        ? ($total_collected_fees / $total_expected_fees) * 100
        : 0;

    $min_clients_met    = $qualifying_clients >= $row['minimum_clients'];
    $fee_collection_met = $fee_collection_pct >= $fee_collection_threshold;
    $is_eligible        = $min_clients_met && $fee_collection_met;

    $commission_per_client = $unit_fee * ($row['commission_rate'] / 100);
    $commission_amount     = $is_eligible ? ($commission_per_client * $qualifying_clients) : 0;

    $notes = [];
    if (!$min_clients_met) {
        $notes[] = "Min clients not met: {$qualifying_clients}/{$row['minimum_clients']}";
    }
    if (!$fee_collection_met) {
        $notes[] = "Fee collection not met: " . number_format($fee_collection_pct, 1) . "%/{$fee_collection_threshold}%";
    }
    if ($is_eligible) {
        $notes[] = "All conditions met!";
    }

    return [
        'success' => true,
        'data' => [
            // Source info
            'commission_type' => $type,
            'source_id'       => $source_id,
            'source_name'     => $row['source_name'],
            'course_name'     => $row['course_name'] ?? null,

            // Staff info
            'staff_user_id'   => $row['staff_user_id'],
            'staff_name'      => $row['staff_name'],

            // Targets
            'minimum_clients_required' => intval($row['minimum_clients']),
            'fee_collection_threshold' => $fee_collection_threshold,
            'client_payment_threshold' => $client_payment_threshold,
            'commission_rate'          => floatval($row['commission_rate']),

            // Actuals
            'total_registered'     => $total_registered,
            'qualifying_clients'   => $qualifying_clients,
            'total_expected_fees'  => $total_expected_fees,
            'total_collected_fees' => $total_collected_fees,
            'fee_collection_pct'   => round($fee_collection_pct, 2),

            // Eligibility
            'min_clients_met'    => $min_clients_met,
            'fee_collection_met' => $fee_collection_met,
            'is_eligible'        => $is_eligible,
            'eligibility_notes'  => implode('; ', $notes),

            // Commission
            'unit_fee'             => $unit_fee,
            'commission_per_client'=> $commission_per_client,
            'commission_amount'    => $commission_amount,
            'currency'             => $currency
        ]
    ];
}


/* ============================================================
 * VIRTUAL (INTAKE) COMMISSION
 * ============================================================ */

/**
 * Calculate commission for a Virtual Intake
 *
 * Flow: intake.intake_id → register.intake_id → register.entry_id → dpo_payment.app_id
 *
 * @param mysqli $conn
 * @param int    $intake_id  Intake auto-increment id
 * @return array
 */
function calculateIntakeCommission($conn, $intake_id) {
    $intake_id = intval($intake_id);

    $fee_collection_threshold = floatval(getCommissionSetting($conn, 'virtual_fee_collection_threshold', '80'));
    $client_payment_threshold = floatval(getCommissionSetting($conn, 'virtual_client_payment_threshold', '80'));

    // Note: intake.id is auto-increment; intake.intake_id is the varchar used for register join
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

    $course_fee         = floatval($intake['course_fee']);
    $min_client_payment = $course_fee * ($client_payment_threshold / 100);
    $intake_code        = mysqli_real_escape_string($conn, $intake['intake_code']);

    $stats = computeCommissionStats(
        $conn, 'register', 'entry_id', 'intake_id',
        "'$intake_code'", $course_fee, $min_client_payment
    );
    if (isset($stats['error'])) {
        return ['success' => false, 'message' => 'Error calculating stats: ' . $stats['error']];
    }

    // Map to shared result shape
    $intake['source_name'] = $intake['intake_name'];
    return buildCommissionResult(
        'virtual', $intake_id, $intake, $stats,
        $course_fee, $fee_collection_threshold, $client_payment_threshold, 'USD'
    );
}


/* ============================================================
 * INTERNATIONAL (EVENT) COMMISSION
 * ============================================================ */

/**
 * Calculate commission for an International Event
 *
 * Flow: Event (early_amount) → ticket_congress (event_id) → dpo_payment (app_id = ticket_id)
 *
 * @param mysqli $conn
 * @param int    $event_id
 * @return array
 */
function calculateEventCommission($conn, $event_id) {
    $event_id = intval($event_id);

    $fee_collection_threshold = floatval(getCommissionSetting($conn, 'international_fee_collection_threshold', '90'));
    $client_payment_threshold = floatval(getCommissionSetting($conn, 'international_client_payment_threshold', '100'));

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

    $event_fee          = floatval($event['event_fee']);
    $min_client_payment = $event_fee * ($client_payment_threshold / 100); // 100% for international
    $currency           = $event['currency_code'] ?: 'USD';

    $stats = computeCommissionStats(
        $conn, 'ticket_congress', 'ticket_id', 'event_id',
        (string) $event_id, $event_fee, $min_client_payment
    );
    if (isset($stats['error'])) {
        return ['success' => false, 'message' => 'Error calculating stats: ' . $stats['error']];
    }

    // Map to shared result shape
    $event['source_name'] = $event['event_title'];
    $event['course_name'] = null;
    return buildCommissionResult(
        'international', $event_id, $event, $stats,
        $event_fee, $fee_collection_threshold, $client_payment_threshold, $currency
    );
}


/* ============================================================
 * SAVE COMMISSION RECORD
 * ============================================================ */

/**
 * Save commission calculation to database
 *
 * @param mysqli $conn
 * @param array  $data          Calculation data from a calculate*Commission function
 * @param int    $calculated_by User ID who performed calculation
 * @return array
 */
function saveCommissionRecord($conn, $data, $calculated_by) {
    $calculated_by = intval($calculated_by);

    $source_name       = mysqli_real_escape_string($conn, $data['source_name']);
    $staff_name        = mysqli_real_escape_string($conn, $data['staff_name']);
    $eligibility_notes = mysqli_real_escape_string($conn, $data['eligibility_notes']);
    $currency          = mysqli_real_escape_string($conn, $data['currency']);
    $type              = mysqli_real_escape_string($conn, $data['commission_type']);
    $source_id         = intval($data['source_id']);

    // Replace any existing record for this source
    mysqli_query($conn, "DELETE FROM commission_records WHERE commission_type = '$type' AND source_id = $source_id");

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
            {$data['total_registered']}, {$data['qualifying_clients']}, {$data['total_expected_fees']}, {$data['total_collected_fees']}, {$data['fee_collection_pct']},
            {$data['unit_fee']}, {$data['commission_per_client']}, {$data['commission_amount']}, '$currency',
            " . ($data['min_clients_met'] ? 1 : 0) . ", " . ($data['fee_collection_met'] ? 1 : 0) . ", " . ($data['is_eligible'] ? 1 : 0) . ", '$eligibility_notes',
            'draft', $calculated_by, NOW()
        )
    ";

    if (mysqli_query($conn, $query)) {
        return ['success' => true, 'record_id' => mysqli_insert_id($conn)];
    }
    return ['success' => false, 'message' => 'Failed to save: ' . mysqli_error($conn)];
}


/* ============================================================
 * QUALIFYING CLIENT LISTS
 * ============================================================ */

/**
 * Get list of qualifying clients for an intake (Virtual)
 *
 * @param mysqli $conn
 * @param int    $intake_id  Intake auto-increment id
 * @return array
 */
function getIntakeQualifyingClients($conn, $intake_id) {
    $intake_id        = intval($intake_id);
    $client_threshold = floatval(getCommissionSetting($conn, 'virtual_client_payment_threshold', '80'));

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
    $course_fee  = floatval($intake_data['price_usd']);
    $intake_code = mysqli_real_escape_string($conn, $intake_data['intake_code']);
    $min_payment = $course_fee * ($client_threshold / 100);
    $pay         = paidSubquery();

    $query = "
        SELECT
            r.id,
            r.entry_id,
            CONCAT(r.firstname, ' ', r.lastname) AS fullname,
            r.email,
            r.phone_number,
            r.datee AS registered_date,
            $course_fee AS course_fee,
            COALESCE(pay.total_paid, 0) AS total_paid,
            ROUND(COALESCE(pay.total_paid, 0) / $course_fee * 100, 1) AS payment_percentage
        FROM register r
        INNER JOIN $pay pay ON pay.app_id = r.entry_id
        WHERE r.intake_id = '$intake_code'
          AND pay.total_paid >= $min_payment
        ORDER BY pay.total_paid DESC
    ";

    $result  = mysqli_query($conn, $query);
    $clients = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $clients[] = $row;
        }
    }
    return $clients;
}

/**
 * Get list of qualifying clients for an event (International)
 *
 * @param mysqli $conn
 * @param int    $event_id
 * @return array
 */
function getEventQualifyingClients($conn, $event_id) {
    $event_id         = intval($event_id);
    $client_threshold = floatval(getCommissionSetting($conn, 'international_client_payment_threshold', '100'));

    $fee_q = mysqli_query($conn, "SELECT early_amount, currency_code FROM Event WHERE event_id = $event_id");
    if (!$fee_q || mysqli_num_rows($fee_q) == 0) {
        return [];
    }

    $event       = mysqli_fetch_assoc($fee_q);
    $event_fee   = floatval($event['early_amount']);
    $min_payment = $event_fee * ($client_threshold / 100);
    $pay         = paidSubquery();

    $query = "
        SELECT
            t.id,
            t.ticket_id,
            t.fullname,
            t.email,
            t.phone_number,
            t.date_sent AS registered_date,
            $event_fee AS event_fee,
            COALESCE(pay.total_paid, 0) AS total_paid,
            ROUND(COALESCE(pay.total_paid, 0) / $event_fee * 100, 1) AS payment_percentage
        FROM ticket_congress t
        INNER JOIN $pay pay ON pay.app_id = t.ticket_id
        WHERE t.event_id = $event_id
          AND pay.total_paid >= $min_payment
        ORDER BY pay.total_paid DESC
    ";

    $result  = mysqli_query($conn, $query);
    $clients = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $clients[] = $row;
        }
    }
    return $clients;
}