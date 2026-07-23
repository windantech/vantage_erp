<?php
session_start();
require_once '../database/conn.php'; 
require_once 'ceo_dashboard/commission_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

// Get current user name for logging
$user_q = mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = $current_user_id");
$current_user_name = ($user_q && $row = mysqli_fetch_assoc($user_q)) ? $row['fullname'] : 'Unknown';

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // ========================================
    // ACTION: Get Commission Details
    // ========================================
    if ($action === 'get_details') {
        $id = intval($_GET['id'] ?? 0);
        
        $record_q = mysqli_query($conn, "SELECT * FROM commission_records WHERE id = $id");
        if (!$record_q || mysqli_num_rows($record_q) == 0) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            exit;
        }
        
        $record = mysqli_fetch_assoc($record_q);
        
        // Get conversion rate
        $usd_to_kes = floatval(getCommissionSetting($conn, 'commission_conversion_rate', '129'));
        $commission_kes = $record['commission_amount'] * $usd_to_kes;
        $per_client_kes = $record['commission_per_client'] * $usd_to_kes;
        
        // Build HTML for details
        $html = '
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Source Information</h6>
                <table class="table table-sm">
                    <tr><td class="text-muted">Type:</td><td><span class="badge ' . ($record['commission_type'] == 'virtual' ? 'bg-primary' : 'bg-danger') . '">' . ucfirst($record['commission_type']) . '</span></td></tr>
                    <tr><td class="text-muted">Intake/Event:</td><td><strong>' . htmlspecialchars($record['source_name']) . '</strong></td></tr>
                    <tr><td class="text-muted">Staff:</td><td>' . htmlspecialchars($record['staff_name']) . '</td></tr>
                    <tr><td class="text-muted">Unit Fee:</td><td>' . $record['commission_currency'] . ' ' . number_format($record['unit_fee'], 2) . '</td></tr>
                    <tr><td class="text-muted">Commission Rate:</td><td>' . $record['commission_rate'] . '%</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Performance</h6>
                <table class="table table-sm">
                    <tr><td class="text-muted">Total Registered:</td><td>' . $record['total_registered'] . '</td></tr>
                    <tr><td class="text-muted">Qualifying Clients:</td><td><strong>' . $record['qualifying_clients'] . '</strong> / ' . $record['minimum_clients_required'] . ' min</td></tr>
                    <tr><td class="text-muted">Expected Fees:</td><td>' . $record['commission_currency'] . ' ' . number_format($record['total_expected_fees'], 2) . '</td></tr>
                    <tr><td class="text-muted">Collected Fees:</td><td>' . $record['commission_currency'] . ' ' . number_format($record['total_collected_fees'], 2) . '</td></tr>
                    <tr><td class="text-muted">Fee Collection:</td><td><span class="' . ($record['fee_collection_met'] ? 'text-success' : 'text-danger') . '">' . number_format($record['fee_collection_percentage'], 1) . '%</span> / ' . $record['fee_collection_threshold'] . '% min</td></tr>
                </table>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Eligibility</h6>
                <table class="table table-sm">
                    <tr><td class="text-muted">Min Clients Met:</td><td>' . ($record['min_clients_met'] ? '<span class="text-success"><i class="fas fa-check"></i> Yes</span>' : '<span class="text-danger"><i class="fas fa-times"></i> No</span>') . '</td></tr>
                    <tr><td class="text-muted">Fee Collection Met:</td><td>' . ($record['fee_collection_met'] ? '<span class="text-success"><i class="fas fa-check"></i> Yes</span>' : '<span class="text-danger"><i class="fas fa-times"></i> No</span>') . '</td></tr>
                    <tr><td class="text-muted">Is Eligible:</td><td>' . ($record['is_eligible'] ? '<span class="badge bg-success">ELIGIBLE</span>' : '<span class="badge bg-danger">NOT ELIGIBLE</span>') . '</td></tr>
                    <tr><td class="text-muted">Notes:</td><td>' . htmlspecialchars($record['eligibility_notes']) . '</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Commission</h6>
                <table class="table table-sm">
                    <tr><td class="text-muted">Per Client (USD):</td><td>' . $record['commission_currency'] . ' ' . number_format($record['commission_per_client'], 2) . '</td></tr>
                    <tr><td class="text-muted">Per Client (KES):</td><td>KES ' . number_format($per_client_kes, 2) . '</td></tr>
                    <tr><td class="text-muted">Total (USD):</td><td>' . $record['commission_currency'] . ' ' . number_format($record['commission_amount'], 2) . '</td></tr>
                    <tr><td class="text-muted"><strong>Total (KES):</strong></td><td><strong class="' . ($record['is_eligible'] ? 'text-success' : 'text-muted') . '">KES ' . number_format($commission_kes, 2) . '</strong></td></tr>
                    <tr><td class="text-muted">Status:</td><td><span class="badge bg-secondary">' . ucfirst(str_replace('_', ' ', $record['status'])) . '</span></td></tr>
                    <tr><td class="text-muted">Calculated:</td><td>' . date('M d, Y H:i', strtotime($record['calculated_at'])) . '</td></tr>
                </table>
                <div class="alert alert-light py-2 mb-0 mt-2">
                    <small><i class="fas fa-exchange-alt me-1"></i>Rate: 1 USD = KES ' . number_format($usd_to_kes, 2) . '</small>
                </div>
            </div>
        </div>';
        
        if ($record['status'] == 'paid') {
            $html .= '
            <hr>
            <div class="alert alert-success mb-0">
                <strong><i class="fas fa-check-circle me-2"></i>Payment Information</strong><br>
                <small>Paid on: ' . ($record['paid_at'] ? date('M d, Y H:i', strtotime($record['paid_at'])) : '-') . '</small>
            </div>';
        }
        
        if ($record['status'] == 'rejected' && $record['rejection_reason']) {
            $html .= '
            <hr>
            <div class="alert alert-danger mb-0">
                <strong><i class="fas fa-times-circle me-2"></i>Rejection Reason</strong><br>
                ' . htmlspecialchars($record['rejection_reason']) . '
            </div>';
        }
        
        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

// ========================================
// ACTION: Save Intake Assignment
// ========================================
if ($action === 'save_intake_assignment') {
    $intake_id = intval($_POST['intake_id'] ?? 0);
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    $minimum_clients = intval($_POST['minimum_clients'] ?? 0);
    $commission_rate = floatval($_POST['commission_rate'] ?? 0);
    
    // Validation
    if ($intake_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid intake ID']);
        exit;
    }
    
    if ($assigned_to <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a staff member']);
        exit;
    }
    
    if ($minimum_clients <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please enter minimum clients (must be > 0)']);
        exit;
    }
    
    if ($commission_rate <= 0 || $commission_rate > 100) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid commission rate (0.01-100)']);
        exit;
    }
    
    // Get intake details
    $intake_q = mysqli_query($conn, "SELECT id, course_id, description FROM intake WHERE id = $intake_id");
    if (!$intake_q || mysqli_num_rows($intake_q) == 0) {
        echo json_encode(['success' => false, 'message' => 'Intake not found']);
        exit;
    }
    $intake = mysqli_fetch_assoc($intake_q);
    
    // Get staff name for logging
    $staff_q = mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = $assigned_to");
    $staff_name = ($staff_q && $row = mysqli_fetch_assoc($staff_q)) ? $row['fullname'] : 'Unknown';
    
    // Update intake
    $update = mysqli_query($conn, "
        UPDATE intake SET 
            assigned_to = $assigned_to,
            minimum_clients = $minimum_clients,
            commission_rate = $commission_rate
        WHERE id = $intake_id
    ");
    
    if (!$update) {
        echo json_encode(['success' => false, 'message' => 'Failed to update intake: ' . mysqli_error($conn)]);
        exit;
    }
    
    // Also update course.assigned_to for course-level visibility (comma-separated list)
    if ($intake['course_id']) {
        $course_id = mysqli_real_escape_string($conn, $intake['course_id']);
        
        // Get current assigned_to from course
        $course_q = mysqli_query($conn, "SELECT assigned_to FROM course WHERE course_id = '$course_id'");
        if ($course_q && $course = mysqli_fetch_assoc($course_q)) {
            $current_assigned = trim($course['assigned_to'] ?? '');
            $assigned_ids = !empty($current_assigned) ? array_map('trim', explode(',', $current_assigned)) : [];
            $assigned_ids = array_filter($assigned_ids);
            
            // Add new staff if not already there
            if (!in_array($assigned_to, $assigned_ids) && !in_array((string)$assigned_to, $assigned_ids)) {
                $assigned_ids[] = $assigned_to;
                $new_assigned = implode(',', $assigned_ids);
                mysqli_query($conn, "UPDATE course SET assigned_to = '$new_assigned' WHERE course_id = '$course_id'");
            }
        }
    }
    
    // Log action
    $details = json_encode([
        'intake_id' => $intake_id,
        'intake_name' => $intake['description'],
        'staff_id' => $assigned_to,
        'staff_name' => $staff_name,
        'minimum_clients' => $minimum_clients,
        'commission_rate' => $commission_rate
    ]);
    $details_escaped = mysqli_real_escape_string($conn, $details);
    $user_name_escaped = mysqli_real_escape_string($conn, $current_user_name);
    
    mysqli_query($conn, "
        INSERT INTO commission_audit_log (action, entity_type, entity_id, details, performed_by, performed_by_name, created_at)
        VALUES ('intake_assigned', 'intake', $intake_id, '$details_escaped', $current_user_id, '$user_name_escaped', NOW())
    ");
    
    echo json_encode([
        'success' => true,
        'message' => "Intake assigned to $staff_name with $commission_rate% commission rate"
    ]);
    exit;
}

// ========================================
// ACTION: Save Event Assignment
// ========================================
if ($action === 'save_event_assignment') {
    $event_id = intval($_POST['event_id'] ?? 0);
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    $minimum_clients = intval($_POST['minimum_clients'] ?? 0);
    $commission_rate = floatval($_POST['commission_rate'] ?? 0);
    
    // Validation
    if ($event_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        exit;
    }
    
    if ($assigned_to <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a staff member']);
        exit;
    }
    
    if ($minimum_clients <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please enter minimum clients (must be > 0)']);
        exit;
    }
    
    if ($commission_rate <= 0 || $commission_rate > 100) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid commission rate (0.01-100)']);
        exit;
    }
    
    // Get event details
    $event_q = mysqli_query($conn, "SELECT event_id, event_title FROM Event WHERE event_id = $event_id");
    if (!$event_q || mysqli_num_rows($event_q) == 0) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    $event = mysqli_fetch_assoc($event_q);
    
    // Get staff name for logging
    $staff_q = mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = $assigned_to");
    $staff_name = ($staff_q && $row = mysqli_fetch_assoc($staff_q)) ? $row['fullname'] : 'Unknown';
    
    // Update event
    $update = mysqli_query($conn, "
        UPDATE Event SET 
            assigned_to = $assigned_to,
            minimum_clients = $minimum_clients,
            commission_rate = $commission_rate
        WHERE event_id = $event_id
    ");
    
    if (!$update) {
        echo json_encode(['success' => false, 'message' => 'Failed to update event: ' . mysqli_error($conn)]);
        exit;
    }
    
    // Log action
    $details = json_encode([
        'event_id' => $event_id,
        'event_title' => $event['event_title'],
        'staff_id' => $assigned_to,
        'staff_name' => $staff_name,
        'minimum_clients' => $minimum_clients,
        'commission_rate' => $commission_rate
    ]);
    $details_escaped = mysqli_real_escape_string($conn, $details);
    $user_name_escaped = mysqli_real_escape_string($conn, $current_user_name);
    
    mysqli_query($conn, "
        INSERT INTO commission_audit_log (action, entity_type, entity_id, details, performed_by, performed_by_name, created_at)
        VALUES ('event_assigned', 'event', $event_id, '$details_escaped', $current_user_id, '$user_name_escaped', NOW())
    ");
    
    echo json_encode([
        'success' => true,
        'message' => "Event assigned to $staff_name with $commission_rate% commission rate"
    ]);
    exit;
}

// ========================================
// ACTION: Calculate All Commissions
// ========================================
if ($action === 'calculate_all') {
    $intakes_calculated = 0;
    $events_calculated = 0;
    $errors = [];
    
    // Calculate for all configured intakes
    $intakes_q = mysqli_query($conn, "
        SELECT i.id, i.intake_id, i.description
        FROM intake i
        WHERE i.status = 1 
        AND i.assigned_to IS NOT NULL 
        AND i.assigned_to != ''
        AND i.minimum_clients > 0 
        AND i.commission_rate > 0
    ");
    
    while ($intake = mysqli_fetch_assoc($intakes_q)) {
        $result = calculateIntakeCommission($conn, $intake['id']);
        if ($result['success']) {
            $save_result = saveCommissionRecord($conn, $result['data'], $current_user_id);
            if ($save_result['success']) {
                $intakes_calculated++;
            } else {
                $errors[] = "Intake {$intake['description']}: " . $save_result['message'];
            }
        } else {
            $errors[] = "Intake {$intake['description']}: " . $result['message'];
        }
    }
    
    // Calculate for all configured events
    $events_q = mysqli_query($conn, "
        SELECT e.event_id, e.event_title
        FROM Event e
        WHERE e.status = 1 
        AND e.assigned_to IS NOT NULL 
        AND e.assigned_to != ''
        AND e.minimum_clients > 0 
        AND e.commission_rate > 0
    ");
    
    while ($event = mysqli_fetch_assoc($events_q)) {
        $result = calculateEventCommission($conn, $event['event_id']);
        if ($result['success']) {
            $save_result = saveCommissionRecord($conn, $result['data'], $current_user_id);
            if ($save_result['success']) {
                $events_calculated++;
            } else {
                $errors[] = "Event {$event['event_title']}: " . $save_result['message'];
            }
        } else {
            $errors[] = "Event {$event['event_title']}: " . $result['message'];
        }
    }
    
    $message = "Calculated $intakes_calculated intakes and $events_calculated events.";
    if (!empty($errors)) {
        $message .= " Errors: " . implode("; ", array_slice($errors, 0, 3));
    }
    
    // Log action
    mysqli_query($conn, "
        INSERT INTO commission_audit_log (action, entity_type, details, performed_by, performed_by_name, created_at)
        VALUES ('calculate_all', 'commission', 'Calculated $intakes_calculated intakes, $events_calculated events', $current_user_id, '" . mysqli_real_escape_string($conn, $current_user_name) . "', NOW())
    ");
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'intakes' => $intakes_calculated,
        'events' => $events_calculated
    ]);
    exit;
}

// ========================================
// ACTION: Recalculate Single Commission
// ========================================
if ($action === 'recalculate') {
    $type = $_POST['type'] ?? '';
    $source_id = intval($_POST['source_id'] ?? 0);
    
    if ($type === 'virtual') {
        // Find intake by source_id (which is intake.id)
        $result = calculateIntakeCommission($conn, $source_id);
    } elseif ($type === 'international') {
        $result = calculateEventCommission($conn, $source_id);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid commission type']);
        exit;
    }
    
    if ($result['success']) {
        $save_result = saveCommissionRecord($conn, $result['data'], $current_user_id);
        if ($save_result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Commission recalculated successfully',
                'data' => $result['data']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $save_result['message']]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit;
}

// ========================================
// ACTION: Update Commission Status
// ========================================
if ($action === 'update_status') {
    $record_id = intval($_POST['record_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
    
    $valid_statuses = ['draft', 'pending_approval', 'approved', 'rejected', 'paid'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Get current record
    $record_q = mysqli_query($conn, "SELECT * FROM commission_records WHERE id = $record_id");
    if (!$record_q || mysqli_num_rows($record_q) == 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }
    $record = mysqli_fetch_assoc($record_q);
    $old_status = $record['status'];
    
    // Update based on new status
    $update_fields = ["status = '$new_status'"];
    
    if ($new_status === 'approved') {
        $update_fields[] = "approved_by = $current_user_id";
        $update_fields[] = "approved_at = NOW()";
    } elseif ($new_status === 'rejected') {
        $update_fields[] = "rejected_by = $current_user_id";
        $update_fields[] = "rejected_at = NOW()";
        $update_fields[] = "rejection_reason = '$reason'";
    }
    
    $update_sql = implode(", ", $update_fields);
    $update = mysqli_query($conn, "UPDATE commission_records SET $update_sql WHERE id = $record_id");
    
    if ($update) {
        // Log action
        mysqli_query($conn, "
            INSERT INTO commission_audit_log (action, entity_type, entity_id, details, performed_by, performed_by_name, created_at)
            VALUES ('status_changed', 'commission_record', $record_id, 'Status: $old_status → $new_status', $current_user_id, '" . mysqli_real_escape_string($conn, $current_user_name) . "', NOW())
        ");
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    exit;
}

// ========================================
// ACTION: Mark as Paid
// ========================================
if ($action === 'mark_paid') {
    $record_id = intval($_POST['record_id'] ?? 0);
    $payment_reference = mysqli_real_escape_string($conn, $_POST['payment_reference'] ?? '');
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    // Get current record
    $record_q = mysqli_query($conn, "SELECT * FROM commission_records WHERE id = $record_id");
    if (!$record_q || mysqli_num_rows($record_q) == 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }
    $record = mysqli_fetch_assoc($record_q);
    
    if ($record['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Commission must be approved before marking as paid']);
        exit;
    }
    
    $update = mysqli_query($conn, "
        UPDATE commission_records SET 
            status = 'paid',
            paid_at = NOW(),
            payment_reference = '$payment_reference'
        WHERE id = $record_id
    ");
    
    if ($update) {
        // Log action
        $details = "Marked as paid. Reference: $payment_reference";
        if ($notes) $details .= ". Notes: $notes";
        
        mysqli_query($conn, "
            INSERT INTO commission_audit_log (action, entity_type, entity_id, details, performed_by, performed_by_name, created_at)
            VALUES ('marked_paid', 'commission_record', $record_id, '" . mysqli_real_escape_string($conn, $details) . "', $current_user_id, '" . mysqli_real_escape_string($conn, $current_user_name) . "', NOW())
        ");
        
        echo json_encode(['success' => true, 'message' => 'Commission marked as paid']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update record']);
    }
    exit;
}

// Unknown action
echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
exit;
?>