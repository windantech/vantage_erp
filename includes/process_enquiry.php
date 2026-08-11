<?php
session_start();
require '../../database/conn.php';
require_once 'enquiry_functions.php';

// Get action type
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Redirect URL (default to dashboard)
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '../enquiry_dashboard.php';

switch ($action) {
    
    // ==========================================
    // ADD NEW ENQUIRY
    // ==========================================
    case 'add':
        $data = [
            'email' => isset($_POST['email']) ? trim($_POST['email']) : null,
            'phone' => isset($_POST['phone']) ? trim($_POST['phone']) : null,
            'firstname' => isset($_POST['firstname']) ? trim($_POST['firstname']) : null,
            'lastname' => isset($_POST['lastname']) ? trim($_POST['lastname']) : null,
            'country' => isset($_POST['country']) ? trim($_POST['country']) : null,
            'organization' => isset($_POST['organization']) ? trim($_POST['organization']) : null,
            'position' => isset($_POST['position']) ? trim($_POST['position']) : null,
            'source_id' => isset($_POST['source_id']) ? intval($_POST['source_id']) : null,
            'interest_type' => isset($_POST['interest_type']) ? $_POST['interest_type'] : 'undecided',
            'program_interest' => isset($_POST['program_interest']) && $_POST['program_interest'] ? intval($_POST['program_interest']) : null,
            'event_interest' => isset($_POST['event_interest']) && $_POST['event_interest'] ? intval($_POST['event_interest']) : null,
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'assigned_to' => isset($_POST['assigned_to']) && $_POST['assigned_to'] ? intval($_POST['assigned_to']) : null,
            'notes' => isset($_POST['notes']) ? trim($_POST['notes']) : null
        ];
        
        $result = add_enquiry($conn, $data);
        
        if ($result['success']) {
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'Enquiry added successfully! Reference: ' . $result['enquiry_ref']
            ];
        } else {
            $_SESSION['message'] = [
                'type' => 'danger',
                'text' => 'Failed to add enquiry: ' . $result['error']
            ];
            
            // If duplicate found, provide more info
            if (isset($result['duplicate_table'])) {
                $_SESSION['message']['text'] .= ' (Duplicate found in ' . $result['duplicate_table'] . ': ' . $result['duplicate_id'] . ')';
            }
        }
        break;
    
    // ==========================================
    // UPDATE ENQUIRY
    // ==========================================
    case 'update':
        $enquiry_id = isset($_POST['enquiry_id']) ? intval($_POST['enquiry_id']) : 0;
        
        if (!$enquiry_id) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid enquiry ID'];
            break;
        }
        
        $data = [];
        $allowed_fields = ['firstname', 'lastname', 'email', 'phone', 'country', 'organization', 
                          'position', 'source_id', 'interest_type', 'program_interest', 'event_interest',
                          'priority', 'status', 'assigned_to', 'notes'];
        
        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = trim($_POST[$field]);
            }
        }
        
        $result = update_enquiry($conn, $enquiry_id, $data);
        
        if ($result['success']) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Enquiry updated successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update: ' . $result['error']];
        }
        break;
    
    // ==========================================
    // ADD FOLLOW-UP
    // ==========================================
    case 'add_followup':
        $data = [
            'enquiry_type' => isset($_POST['enquiry_type']) ? $_POST['enquiry_type'] : '',
            'enquiry_id' => isset($_POST['enquiry_id']) ? $_POST['enquiry_id'] : '',
            'staff_id' => isset( $_SESSION['login_id']) ?  $_SESSION['login_id'] : null,
            'action_taken' => isset($_POST['action_taken']) ? trim($_POST['action_taken']) : null,
            'client_response' => isset($_POST['client_response']) ? trim($_POST['client_response']) : null,
            'next_step' => isset($_POST['next_step']) ? trim($_POST['next_step']) : '',
            'reminder_date' => isset($_POST['reminder_date']) ? $_POST['reminder_date'] : '',
            'reminder_time' => isset($_POST['reminder_time']) ? $_POST['reminder_time'] : '09:00:00'
        ];
        
        $result = add_followup($conn, $data);
        
        if ($result['success']) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Follow-up added successfully!'];
            
            // Update status to 'contacted' if currently 'new'
            if ($data['enquiry_type'] == 'enquiry') {
                $check = mysqli_query($conn, "SELECT status FROM enquiries WHERE id = '{$data['enquiry_id']}' OR enquiry_ref = '{$data['enquiry_id']}'");
                if ($check && mysqli_num_rows($check) > 0) {
                    $current = mysqli_fetch_assoc($check);
                    if ($current['status'] == 'new') {
                        mysqli_query($conn, "UPDATE enquiries SET status = 'contacted' WHERE id = '{$data['enquiry_id']}' OR enquiry_ref = '{$data['enquiry_id']}'");
                    }
                }
            }
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to add follow-up: ' . $result['error']];
        }
        
        // Redirect back to details page if provided
        if (isset($_POST['enquiry_type']) && isset($_POST['enquiry_id'])) {
            $redirect = 'enquiry_details.php?type=' . $_POST['enquiry_type'] . '&id=' . $_POST['enquiry_id'];
        }
        break;
    
    // ==========================================
    // COMPLETE FOLLOW-UP
    // ==========================================
    case 'complete_followup':
        $followup_id = isset($_POST['followup_id']) ? intval($_POST['followup_id']) : 0;
        
        if (!$followup_id) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid follow-up ID'];
            break;
        }
        
        $result = complete_followup($conn, $followup_id);
        
        if ($result['success']) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Follow-up marked as completed!'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to complete follow-up: ' . $result['error']];
        }
        break;
    
    // ==========================================
    // RESCHEDULE FOLLOW-UP
    // ==========================================
    case 'reschedule_followup':
        $followup_id = isset($_POST['followup_id']) ? intval($_POST['followup_id']) : 0;
        $new_date = isset($_POST['reminder_date']) ? $_POST['reminder_date'] : '';
        $new_time = isset($_POST['reminder_time']) ? $_POST['reminder_time'] : '09:00:00';
        
        if (!$followup_id || !$new_date) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid reschedule request'];
            break;
        }
        
        $new_date = mysqli_real_escape_string($conn, $new_date);
        $new_time = mysqli_real_escape_string($conn, $new_time);
        
        $query = "UPDATE enquiry_followups SET reminder_date = '$new_date', reminder_time = '$new_time' WHERE id = $followup_id";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Follow-up rescheduled to ' . date('d M Y', strtotime($new_date))];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to reschedule: ' . mysqli_error($conn)];
        }
        break;
    
    // ==========================================
    // ADD FLAG
    // ==========================================
    case 'add_flag':
        $enquiry_type = isset($_POST['enquiry_type']) ? $_POST['enquiry_type'] : '';
        $enquiry_id = isset($_POST['enquiry_id']) ? $_POST['enquiry_id'] : '';
        $flag_type = isset($_POST['flag_type']) ? $_POST['flag_type'] : '';
        $flagged_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
        
        if (!$enquiry_type || !$enquiry_id || !$flag_type) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Missing required fields'];
            break;
        }
        
        $result = add_flag($conn, $enquiry_type, $enquiry_id, $flag_type, $flagged_by, $notes);
        
        if ($result['success']) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Flag added successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to add flag: ' . $result['error']];
        }
        
        // Redirect back to details page if provided
        if ($enquiry_type && $enquiry_id) {
            $redirect = 'enquiry_details.php?type=' . $enquiry_type . '&id=' . $enquiry_id;
        }
        break;
    
    // ==========================================
    // REMOVE FLAG
    // ==========================================
    case 'remove_flag':
        $enquiry_type = isset($_POST['enquiry_type']) ? $_POST['enquiry_type'] : '';
        $enquiry_id = isset($_POST['enquiry_id']) ? $_POST['enquiry_id'] : '';
        $flag_type = isset($_POST['flag_type']) ? $_POST['flag_type'] : '';
        
        if (!$enquiry_type || !$enquiry_id || !$flag_type) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Missing required fields'];
            break;
        }
        
        $result = remove_flag($conn, $enquiry_type, $enquiry_id, $flag_type);
        
        if ($result['success']) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Flag removed!'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to remove flag: ' . $result['error']];
        }
        break;
    
    // ==========================================
    // CONVERT ENQUIRY
    // ==========================================
    case 'convert':
        $enquiry_id = isset($_POST['enquiry_id']) ? intval($_POST['enquiry_id']) : 0;
        $convert_to = isset($_POST['convert_to']) ? $_POST['convert_to'] : '';
        
        if (!$enquiry_id || !$convert_to) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid conversion request'];
            break;
        }
        
        if ($convert_to == 'register') {
            $result = convert_to_register($conn, $enquiry_id);
            
            if ($result['success']) {
                $_SESSION['message'] = [
                    'type' => 'success', 
                    'text' => 'Enquiry converted to Virtual Course successfully! Entry ID: ' . $result['entry_id']
                ];
                $redirect = 'enquiry_details.php?type=register&id=' . $result['entry_id'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Conversion failed: ' . $result['error']];
            }
            
        } elseif ($convert_to == 'ticket_congress') {
            $result = convert_to_ticket_congress($conn, $enquiry_id);
            
            if ($result['success']) {
                $_SESSION['message'] = [
                    'type' => 'success', 
                    'text' => 'Enquiry converted to International Event successfully! Ticket ID: ' . $result['ticket_id']
                ];
                $redirect = 'enquiry_details.php?type=ticket_congress&id=' . $result['ticket_id'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => 'Conversion failed: ' . $result['error']];
            }
        }
        break;
    
    // ==========================================
    // UPDATE STATUS
    // ==========================================
    case 'update_status':
        $enquiry_type = isset($_POST['enquiry_type']) ? $_POST['enquiry_type'] : '';
        $enquiry_id = isset($_POST['enquiry_id']) ? $_POST['enquiry_id'] : '';
        $new_status = isset($_POST['status']) ? $_POST['status'] : '';
        
        if (!$enquiry_type || !$enquiry_id || !$new_status) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Missing required fields'];
            break;
        }
        
        $enquiry_id = mysqli_real_escape_string($conn, $enquiry_id);
        $new_status = mysqli_real_escape_string($conn, $new_status);
        
        $success = false;
        
        switch ($enquiry_type) {
            case 'enquiry':
                $query = "UPDATE enquiries SET status = '$new_status' WHERE id = '$enquiry_id' OR enquiry_ref = '$enquiry_id'";
                $success = mysqli_query($conn, $query);
                break;
            case 'register':
                $query = "UPDATE register SET lead_status = '$new_status' WHERE id = '$enquiry_id' OR entry_id = '$enquiry_id'";
                $success = mysqli_query($conn, $query);
                break;
            case 'ticket_congress':
                // Map status to ticket_congress status field
                $tc_status = ($new_status == 'qualified' || $new_status == 'converted') ? 2 : 1;
                $query = "UPDATE ticket_congress SET status = '$tc_status' WHERE id = '$enquiry_id' OR ticket_id = '$enquiry_id'";
                $success = mysqli_query($conn, $query);
                break;
        }
        
        if ($success) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Status updated successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update status'];
        }
        
        $redirect = 'enquiry_details.php?type=' . $enquiry_type . '&id=' . $enquiry_id;
        break;
    
    // ==========================================
    // UPDATE ENQUIRY (EDIT)
    // ==========================================
    case 'update_enquiry':
        $enquiry_type = isset($_POST['enquiry_type']) ? $_POST['enquiry_type'] : '';
        $enquiry_id = isset($_POST['enquiry_id']) ? mysqli_real_escape_string($conn, $_POST['enquiry_id']) : '';
        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
        
        if (!$enquiry_type || !$record_id) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Invalid update request'];
            break;
        }
        
        $success = false;
        
        switch ($enquiry_type) {
            case 'enquiry':
                // Update enquiries table
                $firstname = mysqli_real_escape_string($conn, $_POST['firstname'] ?? '');
                $lastname = mysqli_real_escape_string($conn, $_POST['lastname'] ?? '');
                $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
                $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
                $country = mysqli_real_escape_string($conn, $_POST['country'] ?? '');
                $organization = mysqli_real_escape_string($conn, $_POST['organization'] ?? '');
                $interest_type = mysqli_real_escape_string($conn, $_POST['interest_type'] ?? 'undecided');
                $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'medium');
                $program_interest = !empty($_POST['program_interest']) ? intval($_POST['program_interest']) : 'NULL';
                $event_interest = !empty($_POST['event_interest']) ? intval($_POST['event_interest']) : 'NULL';
                $source_id = !empty($_POST['source_id']) ? intval($_POST['source_id']) : 'NULL';
                $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
                
                $query = "UPDATE enquiries SET 
                    firstname = '$firstname',
                    lastname = '$lastname',
                    email = '$email',
                    phone = '$phone',
                    country = '$country',
                    organization = '$organization',
                    interest_type = '$interest_type',
                    priority = '$priority',
                    program_interest = $program_interest,
                    event_interest = $event_interest,
                    source_id = $source_id,
                    notes = '$notes',
                    updated_at = NOW()
                    WHERE id = $record_id";
                $success = mysqli_query($conn, $query);
                break;
                
            case 'register':
                // Update register table
                $firstname = mysqli_real_escape_string($conn, $_POST['firstname'] ?? '');
                $lastname = mysqli_real_escape_string($conn, $_POST['lastname'] ?? '');
                $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
                $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
                $country = mysqli_real_escape_string($conn, $_POST['country'] ?? '');
                $program = !empty($_POST['program']) ? intval($_POST['program']) : null;
                
                $query = "UPDATE register SET 
                    firstname = '$firstname',
                    lastname = '$lastname',
                    email = '$email',
                    phone_number = '$phone',
                    country = '$country'";
                if ($program !== null) {
                    $query .= ", program = $program";
                }
                $query .= " WHERE id = $record_id";
                $success = mysqli_query($conn, $query);
                break;
                
            case 'ticket_congress':
                // Update ticket_congress table
                $fullname = mysqli_real_escape_string($conn, $_POST['fullname'] ?? '');
                $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
                $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
                $country = mysqli_real_escape_string($conn, $_POST['country'] ?? '');
                $organization = mysqli_real_escape_string($conn, $_POST['organization'] ?? '');
                $position = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
                $event_id = !empty($_POST['event_id']) ? intval($_POST['event_id']) : null;
                
                $query = "UPDATE ticket_congress SET 
                    fullname = '$fullname',
                    email = '$email',
                    phone_number = '$phone',
                    country = '$country',
                    organization = '$organization',
                    position = '$position'";
                if ($event_id !== null) {
                    $query .= ", event_id = $event_id";
                }
                $query .= " WHERE id = $record_id";
                $success = mysqli_query($conn, $query);
                break;
        }
        
        if ($success) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Enquiry updated successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update: ' . mysqli_error($conn)];
        }
        break;
    
    // ==========================================
    // ASSIGN STAFF
    // ==========================================
    case 'assign':
        $enquiry_type = isset($_POST['enquiry_type']) ? $_POST['enquiry_type'] : '';
        $enquiry_id = isset($_POST['enquiry_id']) ? $_POST['enquiry_id'] : '';
        $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] ? intval($_POST['assigned_to']) : 'NULL';
        
        if (!$enquiry_type || !$enquiry_id) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Missing required fields'];
            break;
        }
        
        $enquiry_id = mysqli_real_escape_string($conn, $enquiry_id);
        
        $success = false;
        
        switch ($enquiry_type) {
            case 'enquiry':
                $query = "UPDATE enquiries SET assigned_to = $assigned_to WHERE id = '$enquiry_id' OR enquiry_ref = '$enquiry_id'";
                $success = mysqli_query($conn, $query);
                break;
            case 'register':
                $query = "UPDATE register SET assigned_to = $assigned_to WHERE id = '$enquiry_id' OR entry_id = '$enquiry_id'";
                $success = mysqli_query($conn, $query);
                break;
            case 'ticket_congress':
                $query = "UPDATE ticket_congress SET assigned_to = $assigned_to WHERE id = '$enquiry_id' OR ticket_id = '$enquiry_id'";
                $success = mysqli_query($conn, $query);
                break;
        }
        
        if ($success) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Assignment updated!'];
        } else {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Failed to update assignment'];
        }
        
        $redirect = 'enquiry_details.php?type=' . $enquiry_type . '&id=' . $enquiry_id;
        break;
    
    // ==========================================
    // UNKNOWN ACTION
    // ==========================================
    default:
        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Unknown action'];
        break;
}

// Close connection
$conn->close();

// Redirect - handle relative paths
if (strpos($redirect, '../') === false && strpos($redirect, 'http') === false) {
    $redirect = '../' . $redirect;
}

header("Location: " . $redirect);
exit;