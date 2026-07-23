<?php
session_start();
require_once 'header.php';
require "../../function.php";
require_once 'includes/enquiry_functions.php';
require_once 'includes/email_log_functions.php';

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

if (!$type || !$id) {
    echo "<script>alert('Invalid request'); window.location.href='enquiry_dashboard.php';</script>";
    exit;
}

// Get staff list
$staff_list = get_staff_list($conn);

// Get enquiry sources
$sources = get_enquiry_sources($conn);

// Get courses for dropdown
$courses = [];
$courses_query = mysqli_query($conn, "SELECT id, course_id, course FROM course WHERE status = 1 ORDER BY course ASC");
if ($courses_query) {
    while ($row = mysqli_fetch_assoc($courses_query)) {
        $courses[] = $row;
    }
}

// Get events for dropdown
$events = [];
$events_query = mysqli_query($conn, "SELECT event_id, event_title FROM Event WHERE status = 1 ORDER BY start_on DESC LIMIT 50");
if ($events_query) {
    while ($row = mysqli_fetch_assoc($events_query)) {
        $events[] = $row;
    }
}

// Fetch enquiry data based on type
$enquiry = null;
$payment_amount = 0;
$is_paid = false;

switch ($type) {
    case 'enquiry':
        $id_escaped = mysqli_real_escape_string($conn, $id);
        $query = "SELECT e.*, es.name AS source_name, c.course AS program_name, ev.event_title AS event_name, ru.fullname AS assigned_name
                  FROM enquiries e
                  LEFT JOIN enquiry_sources es ON e.source_id = es.id
                  LEFT JOIN course c ON e.program_interest = c.id
                  LEFT JOIN Event ev ON e.event_interest = ev.event_id
                  LEFT JOIN registered_users ru ON e.assigned_to = ru.id
                  WHERE e.id = '$id_escaped' OR e.enquiry_ref = '$id_escaped'";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $enquiry = mysqli_fetch_assoc($result);
            $enquiry['reference'] = $enquiry['enquiry_ref'];
            $enquiry['fullname'] = trim($enquiry['firstname'] . ' ' . $enquiry['lastname']);
        }
        break;
        
    case 'register':
        $id_escaped = mysqli_real_escape_string($conn, $id);
        $query = "SELECT r.*, c.course AS program_name, c.price_usd, ru.fullname AS assigned_name
                  FROM register r
                  LEFT JOIN course c ON r.program = c.course_id
                  LEFT JOIN registered_users ru ON r.assigned_to = ru.id
                  WHERE r.entry_id = '$id_escaped' OR r.id = '$id_escaped'";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $enquiry = mysqli_fetch_assoc($result);
            $enquiry['reference'] = $enquiry['entry_id'];
            $enquiry['fullname'] = ucwords(strtolower($enquiry['firstname'] . ' ' . $enquiry['lastname']));
            $enquiry['interest_type'] = 'virtual';
            $enquiry['source_name'] = $enquiry['source'] ?? 'Website';
            
            // Get payment info
            $payment_query = "SELECT SUM(TransactionAmount) AS total_paid FROM dpo_payment 
                             WHERE email = '" . mysqli_real_escape_string($conn, $enquiry['email']) . "' 
                             AND purpose = '" . mysqli_real_escape_string($conn, $enquiry['program']) . "' 
                             AND status = 2";
            $payment_result = mysqli_query($conn, $payment_query);
            if ($payment_result && $payment_row = mysqli_fetch_assoc($payment_result)) {
                $payment_amount = floatval($payment_row['total_paid'] ?? 0);
            }
            $is_paid = $payment_amount > 0;
        }
        break;
        
    case 'ticket_congress':
        $id_escaped = mysqli_real_escape_string($conn, $id);
        $query = "SELECT t.*, e.event_title AS event_name, e.start_on, e.end_on, e.location AS event_location, e.early_amount, ru.fullname AS assigned_name
                  FROM ticket_congress t
                  LEFT JOIN Event e ON t.event_id = e.event_id
                  LEFT JOIN registered_users ru ON t.assigned_to = ru.id
                  WHERE t.ticket_id = '$id_escaped' OR t.id = '$id_escaped'";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $enquiry = mysqli_fetch_assoc($result);
            $enquiry['reference'] = $enquiry['ticket_id'];
            $enquiry['interest_type'] = 'international';
            $enquiry['source_name'] = 'Website';
            $enquiry['phone'] = $enquiry['phone_number'] ?? '';
            
            // Get payment info
            $payment_query = "SELECT SUM(TransactionAmount) AS total_paid FROM dpo_payment 
                             WHERE email = '" . mysqli_real_escape_string($conn, $enquiry['email']) . "' 
                             AND (app_id = '" . mysqli_real_escape_string($conn, $enquiry['ticket_id']) . "' 
                             OR app_id = '" . mysqli_real_escape_string($conn, $enquiry['id']) . "')
                             AND status = 2";
            $payment_result = mysqli_query($conn, $payment_query);
            if ($payment_result && $payment_row = mysqli_fetch_assoc($payment_result)) {
                $payment_amount = floatval($payment_row['total_paid'] ?? 0);
            }
            $is_paid = ($enquiry['status'] == 2) || ($payment_amount > 0);
        }
        break;
}

if (!$enquiry) {
    echo "<script>alert('Enquiry not found'); window.location.href='enquiry_dashboard.php';</script>";
    exit;
}

// Set variables for communications section
$source_type = $type;
$source_id = $enquiry['reference'];
$record_id = $enquiry['id'] ?? null;
$client_email = $enquiry['email'] ?? '';

// Get follow-ups
$followups = get_followups($conn, $type, $enquiry['reference']);

// Get flags
$flags = get_flags($conn, $type, $enquiry['reference']);

// Session messages
$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<?php
/**
 * PAYMENT HISTORY QUERY
 * Add this code to enquiry_view.php after fetching the enquiry data
 * Shows ALL payments for the client matching the course/event
 */

// =============================================
// FETCH PAYMENT HISTORY FOR THIS CLIENT
// =============================================
$payment_history = [];
$total_paid = 0;
$amount_due = 0;
$balance = 0;
$payment_status = 'unpaid';

if ($enquiry && !empty($enquiry['email'])) {
    $email_escaped = mysqli_real_escape_string($conn, $enquiry['email']);
    
    // Determine purpose and amount due based on type
    $purpose_filter = "";
    
    if ($type == 'register' && !empty($enquiry['program'])) {
        $purpose_escaped = mysqli_real_escape_string($conn, $enquiry['program']);
        $purpose_filter = "AND purpose = '$purpose_escaped'";
        $amount_due = floatval($enquiry['price_usd'] ?? 0);
        
    } elseif ($type == 'ticket_congress' && !empty($enquiry['event_id'])) {
        $purpose_escaped = mysqli_real_escape_string($conn, $enquiry['event_id']);
        $purpose_filter = "AND purpose = '$purpose_escaped'";
        $amount_due = floatval($enquiry['early_amount'] ?? 0);
        
    } elseif ($type == 'enquiry') {
        // For enquiries, check program_interest or event_interest
        if (!empty($enquiry['program_interest'])) {
            $purpose_escaped = mysqli_real_escape_string($conn, $enquiry['program_interest']);
            $purpose_filter = "AND purpose = '$purpose_escaped'";
            $price_query = mysqli_query($conn, "SELECT price_usd FROM course WHERE id = '$purpose_escaped' LIMIT 1");
            if ($price_row = mysqli_fetch_assoc($price_query)) {
                $amount_due = floatval($price_row['price_usd']);
            }
        } elseif (!empty($enquiry['event_interest'])) {
            $purpose_escaped = mysqli_real_escape_string($conn, $enquiry['event_interest']);
            $purpose_filter = "AND purpose = '$purpose_escaped'";
            $price_query = mysqli_query($conn, "SELECT early_amount FROM Event WHERE event_id = '$purpose_escaped' LIMIT 1");
            if ($price_row = mysqli_fetch_assoc($price_query)) {
                $amount_due = floatval($price_row['early_amount']);
            }
        }
    }
    
    // Check if new columns exist
    $has_new_columns = false;
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM dpo_payment LIKE 'recorded_by'");
    if ($check_columns && mysqli_num_rows($check_columns) > 0) {
        $has_new_columns = true;
    }
    
    // Build query based on available columns
    if ($has_new_columns) {
        $payment_query = "SELECT 
                            
                            p.special_id,
                            p.token,
                            p.email,
                            p.TransactionAmount,
                            p.purpose,
                            p.status,
                            p.datee,
                            p.app_id,
                            p.comment,
                            p.recorded_by,
                            p.discount_amount,
                            p.discount_type,
                            p.discount_value,
                            p.currency_original,
                            p.amount_original,
                            ru.fullname AS recorded_by_name
                          FROM dpo_payment p
                          LEFT JOIN registered_users ru ON p.recorded_by = ru.id
                          WHERE p.email = '$email_escaped' 
                          AND p.status = 2 
                          $purpose_filter
                          ORDER BY p.datee DESC";
    } else {
        // Fallback query without new columns
        $payment_query = "SELECT 
                        
                            special_id,
                            token,
                            email,
                            TransactionAmount,
                            purpose,
                            status,
                            datee,
                            app_id,
                            comment,
                            NULL AS recorded_by,
                            NULL AS discount_amount,
                            NULL AS discount_type,
                            NULL AS discount_value,
                            'USD' AS currency_original,
                            NULL AS amount_original,
                            NULL AS recorded_by_name
                          FROM dpo_payment
                          WHERE email = '$email_escaped' 
                          AND status = 2 
                          $purpose_filter
                          ORDER BY datee DESC";
    }
    
    $payment_result = mysqli_query($conn, $payment_query);
    if ($payment_result) {
        while ($row = mysqli_fetch_assoc($payment_result)) {
            $payment_history[] = $row;
            $total_paid += floatval($row['TransactionAmount']);
        }
    }
    
    // Calculate balance
    $balance = $amount_due - $total_paid;
    
    // Determine payment status
    if ($total_paid > 0 && $balance <= 0) {
        $payment_status = 'paid';
    } elseif ($total_paid > 0) {
        $payment_status = 'partial';
    } else {
        $payment_status = 'unpaid';
    }
}
?>

<style>
.detail-card { border-radius: 10px; }
.detail-label { font-weight: 600; color: #6c757d; font-size: 0.85rem; text-transform: uppercase; }
.detail-value { font-size: 1rem; margin-bottom: 15px; }
.timeline { position: relative; padding-left: 30px; }
.timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
.timeline-item { position: relative; padding-bottom: 20px; }
.timeline-item::before { content: ''; position: absolute; left: -24px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: #0d6efd; border: 2px solid #fff; }
.timeline-item.completed::before { background: #198754; }
.timeline-item.overdue::before { background: #dc3545; }
.flag-btn { padding: 5px 10px; margin: 2px; border-radius: 20px; font-size: 0.8rem; }
.flag-btn.active { opacity: 1; }
.flag-btn:not(.active) { opacity: 0.5; }
.status-select { max-width: 200px; }
.back-btn { margin-bottom: 20px; }
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Back Button -->
            <a href="enquiry_dashboard.php" class="btn btn-outline-secondary back-btn">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
            
            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $message['text']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Left Column - Main Details -->
                <div class="col-lg-8">
                    <!-- Header Card -->
                    <div class="card detail-card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($enquiry['fullname']); ?></h4>
                                    <p class="text-muted mb-2">
                                        <span class="badge bg-<?php echo $type == 'enquiry' ? 'warning' : ($type == 'register' ? 'primary' : 'success'); ?>">
                                            <?php echo $type == 'enquiry' ? 'Enquiry' : ($type == 'register' ? 'Virtual' : 'International'); ?>
                                        </span>
                                        <span class="ms-2"><?php echo htmlspecialchars($enquiry['reference']); ?></span>
                                        
                                   
                                    </p>
                                </div>
                                <div class="text-end">
                                    <!-- Payment Status -->
                                    <?php if ($is_paid): ?>
                                    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                    <?php if ($payment_amount > 0): ?>
                                    <br><small class="text-muted">$<?php echo number_format($payment_amount, 2); ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-clock me-1"></i>Unpaid</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Info Card -->
                    <div class="card detail-card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Contact Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="detail-label">Email</p>
                                    <p class="detail-value">
                                        <?php if (!empty($enquiry['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($enquiry['email']); ?>">
                                            <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($enquiry['email']); ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="detail-label">Phone</p>
                                    <p class="detail-value">
                                        <?php if (!empty($enquiry['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($enquiry['phone']); ?>">
                                            <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($enquiry['phone']); ?>
                                        </a>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $enquiry['phone']); ?>" target="_blank" class="btn btn-sm btn-success ms-2">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="detail-label">Country</p>
                                    <p class="detail-value"><?php echo htmlspecialchars($enquiry['country'] ?? '-'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="detail-label">Organization</p>
                                    <p class="detail-value"><?php echo htmlspecialchars($enquiry['organization'] ?? '-'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="detail-label">Position</p>
                                    <p class="detail-value"><?php echo htmlspecialchars($enquiry['position'] ?? '-'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="detail-label">Source</p>
                                    <p class="detail-value">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($enquiry['source_name'] ?? 'Unknown'); ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Program/Event Details -->
                    <div class="card detail-card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="bi bi-<?php echo ($enquiry['interest_type'] ?? '') == 'virtual' ? 'laptop' : 'globe'; ?> me-2"></i>
                                <?php echo ($enquiry['interest_type'] ?? '') == 'virtual' ? 'Program Details' : 'Event Details'; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (($enquiry['interest_type'] ?? '') == 'virtual'): ?>
                                <p class="detail-label">Program</p>
                                <p class="detail-value fs-5"><?php echo htmlspecialchars($enquiry['program_name'] ?? '-'); ?></p>
                                <?php if (isset($enquiry['price_usd'])): ?>
                                <p class="detail-label">Price</p>
                                <p class="detail-value">$<?php echo number_format($enquiry['price_usd'], 2); ?></p>
                                <?php endif; ?>
                            <?php elseif (($enquiry['interest_type'] ?? '') == 'international'): ?>
                                <p class="detail-label">Event</p>
                                <p class="detail-value fs-5"><?php echo htmlspecialchars($enquiry['event_name'] ?? '-'); ?></p>
                                <?php if (isset($enquiry['start_on'])): ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p class="detail-label">Start Date</p>
                                        <p class="detail-value"><?php echo date('d M Y', strtotime($enquiry['start_on'])); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="detail-label">End Date</p>
                                        <p class="detail-value"><?php echo isset($enquiry['end_on']) ? date('d M Y', strtotime($enquiry['end_on'])) : '-'; ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="detail-label">Location</p>
                                        <p class="detail-value"><?php echo htmlspecialchars($enquiry['event_location'] ?? '-'); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">Interest type not yet determined</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- COMMUNICATIONS SECTION - EMAIL LOGS & RESEND -->
                    <!-- ============================================ -->
                    <?php include 'includes/communications_section.php'; ?>
                    
                    <!-- Follow-ups Timeline -->
                    <div class="card detail-card shadow-sm mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Follow-up History</h6>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addFollowupModal">
                                <i class="bi bi-plus-lg me-1"></i>Add Follow-up
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (count($followups) > 0): ?>
                            <div class="timeline">
                                <?php foreach ($followups as $fu): 
                                    $is_overdue = (!$fu['is_completed'] && strtotime($fu['reminder_date']) < strtotime('today'));
                                    $item_class = $fu['is_completed'] ? 'completed' : ($is_overdue ? 'overdue' : '');
                                ?>
                                <div class="timeline-item <?php echo $item_class; ?>">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo htmlspecialchars($fu['next_step']); ?></strong>
                                            <?php if ($is_overdue): ?>
                                            <span class="badge bg-danger ms-2">Overdue</span>
                                            <?php elseif ($fu['is_completed']): ?>
                                            <span class="badge bg-success ms-2">Completed</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?php echo date('d M Y', strtotime($fu['reminder_date'])); ?></small>
                                    </div>
                                    <?php if ($fu['action_taken']): ?>
                                    <p class="mb-1 mt-2"><small><strong>Action:</strong> <?php echo htmlspecialchars($fu['action_taken']); ?></small></p>
                                    <?php endif; ?>
                                    <?php if ($fu['client_response']): ?>
                                    <p class="mb-1"><small><strong>Response:</strong> <?php echo htmlspecialchars($fu['client_response']); ?></small></p>
                                    <?php endif; ?>
                                    <?php if (!$fu['is_completed']): ?>
                                    <form action="includes/process_enquiry.php" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="complete_followup">
                                        <input type="hidden" name="followup_id" value="<?php echo $fu['id']; ?>">
                                        <input type="hidden" name="enquiry_type" value="<?php echo $type; ?>">
                                        <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['reference']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success mt-2">
                                            <i class="bi bi-check-lg me-1"></i>Mark Complete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-clock fs-1 d-block mb-2"></i>
                                <p>No follow-ups recorded yet</p>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFollowupModal">
                                    <i class="bi bi-plus-lg me-1"></i>Add First Follow-up
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Actions & Quick Info -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card detail-card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#editEnquiryModal">
                                    <i class="bi bi-pencil me-2"></i>Edit Details
                                </button>
                                <?php
                                $type = isset($_GET['type']) ? $_GET['type'] : ''; ?>
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addFollowupModal">
                                    <i class="bi bi-calendar-plus me-2"></i>Add Follow-up
                                </button>
                                <a href="mailto:<?php echo htmlspecialchars($enquiry['email'] ?? ''); ?>" class="btn btn-outline-secondary <?php echo empty($enquiry['email']) ? 'disabled' : ''; ?>">
                                    <i class="bi bi-envelope me-2"></i>Send Email
                                </a>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $enquiry['phone'] ?? ''); ?>" target="_blank" class="btn btn-outline-success <?php echo empty($enquiry['phone']) ? 'disabled' : ''; ?>">
                                    <i class="bi bi-whatsapp me-2"></i>WhatsApp
                                </a>
                                <?php if ($type == 'enquiry'): ?>
                                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#convertModal">
                                    <i class="bi bi-arrow-right-circle me-2"></i>Convert to Full Record
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-success rounded-0" data-bs-toggle="modal" data-bs-target="#approvePaymentModal">
    <i class="bi bi-credit-card"></i> Record Payment
</button>
                                <form action="includes/process_add_moodle_user.php" method="POST" class="d-grid">
                                    <input type="hidden" name="enquiry_type" value="<?php echo htmlspecialchars($type); ?>">
                                    <input type="hidden" name="enquiry_id" value="<?php echo htmlspecialchars($enquiry['reference']); ?>">
                                    <input type="hidden" name="redirect" value="../enquiry_details.php?type=<?php echo urlencode($type); ?>&id=<?php echo urlencode($id); ?>">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-mortarboard me-2"></i>Add to E-learning
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assignment -->
                    <div class="card detail-card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-person-check me-2"></i>Assignment</h6>
                        </div>
                        <div class="card-body">
                            <form action="includes/process_enquiry.php" method="POST">
                                <input type="hidden" name="action" value="assign_staff">
                                <input type="hidden" name="enquiry_type" value="<?php echo $type; ?>">
                                <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['reference']; ?>">
                                <select name="staff_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" <?php echo ($enquiry['assigned_to'] ?? '') == $staff['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['fullname']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Flags -->
                    <div class="card detail-card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-flag me-2"></i>Flags</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $flag_types = ['high_potential', 'urgent', 'vip', 'needs_attention', 'cold_lead'];
                            $flag_colors = [
                                'high_potential' => 'success',
                                'urgent' => 'danger',
                                'vip' => 'warning',
                                'needs_attention' => 'info',
                                'cold_lead' => 'secondary'
                            ];
                            $flag_icons = [
                                'high_potential' => 'bi-star-fill',
                                'urgent' => 'bi-exclamation-triangle-fill',
                                'vip' => 'bi-crown-fill',
                                'needs_attention' => 'bi-eye-fill',
                                'cold_lead' => 'bi-snow'
                            ];
                            $current_flags = array_column($flags, 'flag_type');
                            ?>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($flag_types as $flag_type): 
                                    $is_active = in_array($flag_type, $current_flags);
                                ?>
                                <form action="includes/process_enquiry.php" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="<?php echo $is_active ? 'remove_flag' : 'add_flag'; ?>">
                                    <input type="hidden" name="enquiry_type" value="<?php echo $type; ?>">
                                    <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['reference']; ?>">
                                    <input type="hidden" name="flag_type" value="<?php echo $flag_type; ?>">
                                    <button type="submit" class="btn btn-outline-<?php echo $flag_colors[$flag_type]; ?> flag-btn <?php echo $is_active ? 'active' : ''; ?>">
                                        <i class="<?php echo $flag_icons[$flag_type]; ?> me-1"></i>
                                        <?php echo ucwords(str_replace('_', ' ', $flag_type)); ?>
                                    </button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                 <!-- Payment History Card -->
<!-- Requires: $payment_history, $total_paid, $amount_due, $balance, $payment_status -->
<div class="card detail-card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment History</h6>
        <div class="d-flex gap-2">
            <?php if ($balance > 0 && in_array($type, ['register', 'ticket_congress'])): ?>
            <button type="button"
                    class="btn btn-sm btn-warning rounded-0"
                    id="sendOutstandingInvoiceBtn"
                    data-source-type="<?php echo htmlspecialchars($type); ?>"
                    data-source-id="<?php echo htmlspecialchars($enquiry['reference']); ?>"
                    data-record-id="<?php echo htmlspecialchars($enquiry['id'] ?? ''); ?>"
                    data-balance="<?php echo number_format($balance, 2, '.', ''); ?>">
                <i class="bi bi-envelope-paper me-1"></i> Send Balance Invoice
            </button>
            <?php endif; ?>
            <button class="btn btn-sm btn-success rounded-0" data-bs-toggle="modal" data-bs-target="#approvePaymentModal">
                <i class="bi bi-plus-lg"></i> Record Payment
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Payment Summary -->
        <div class="row mb-3">
            <div class="col-4 text-center">
                <p class="detail-label mb-1">Amount Due</p>
                <p class="detail-value mb-0 fs-6"><strong>$<?php echo number_format($amount_due, 2); ?></strong></p>
            </div>
            <div class="col-4 text-center border-start border-end">
                <p class="detail-label mb-1">Total Paid</p>
                <p class="detail-value mb-0 fs-6 text-success"><strong>$<?php echo number_format($total_paid, 2); ?></strong></p>
            </div>
            <div class="col-4 text-center">
                <p class="detail-label mb-1">Balance</p>
                <p class="detail-value mb-0 fs-6 <?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                    <strong><?php echo $balance > 0 ? '$' . number_format($balance, 2) : 'PAID'; ?></strong>
                </p>
            </div>
        </div>
        
        <hr class="my-2">
        
        <!-- Payment Status Badge -->
        <div class="text-center mb-3">
            <?php if ($payment_status == 'paid'): ?>
                <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i> Fully Paid</span>
            <?php elseif ($payment_status == 'partial'): ?>
                <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-clock me-1"></i> Partial Payment</span>
            <?php else: ?>
                <span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle me-1"></i> Unpaid</span>
            <?php endif; ?>
        </div>
        
        <!-- Payment Records -->
        <?php if (count($payment_history) > 0): ?>
            <p class="detail-label mb-2">Payment Records (<?php echo count($payment_history); ?>)</p>
            <div class="payment-history-list" style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($payment_history as $index => $payment): 
                    $discount_amt = floatval($payment['discount_amount'] ?? 0);
                    $has_discount = $discount_amt > 0;
                ?>
                    <div class="payment-item border rounded p-2 mb-2 <?php echo $index === 0 ? 'border-success' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong class="text-success">$<?php echo number_format($payment['TransactionAmount'], 2); ?></strong>
                                <?php if ($has_discount): ?>
                                    <small class="text-muted ms-1">(incl. $<?php echo number_format($discount_amt, 2); ?> discount)</small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo date('d M Y', strtotime($payment['datee'])); ?></small>
                        </div>
                        
                        <div class="mt-1">
                            <!-- Confirmation Code -->
                            <small class="d-block">
                                <i class="bi bi-hash text-primary"></i>
                                <span class="text-muted">Ref:</span> 
                                <code class="bg-light px-1"><?php echo htmlspecialchars($payment['token'] ?? $payment['special_id'] ?? 'N/A'); ?></code>
                            </small>
                            
                            <!-- Payment Method -->
                            <small class="d-block">
                                <i class="bi bi-wallet2 text-primary"></i>
                                <span class="text-muted">Method:</span> 
                                <?php 
                                $method = $payment['token'] ?? '';
                                $method_name = explode('-', $method)[0] ?? 'Unknown';
                                $method_labels = [
                                    'MP' => 'M-Pesa',
                                    'TILL' => 'Till Number',
                                    'WU' => 'Western Union',
                                    'Mukuru' => 'Mukuru',
                                    'LocalRep' => 'Local Rep',
                                    'EQ' => 'Equity Bank',
                                    'EC' => 'Echo Bank',
                                    'AM' => 'Airtel Money',
                                    'Dahabsh' => 'Dahabshil',
                                    'DPO' => 'DPO',
                                    'Money' => 'Money Gram',
                                    'Ria' => 'Ria'
                                ];
                                echo $method_labels[$method_name] ?? $method_name;
                                ?>
                            </small>
                            
                            <!-- Original Currency if KES -->
                            <?php if (!empty($payment['currency_original']) && $payment['currency_original'] == 'KES' && !empty($payment['amount_original'])): ?>
                            <small class="d-block">
                                <i class="bi bi-currency-exchange text-primary"></i>
                                <span class="text-muted">Original:</span> 
                                KES <?php echo number_format($payment['amount_original'], 2); ?>
                            </small>
                            <?php endif; ?>
                            
                            <!-- Discount Info -->
                            <?php if ($has_discount): ?>
                            <small class="d-block text-success">
                                <i class="bi bi-tag text-success"></i>
                                <span>Discount:</span> 
                                $<?php echo number_format($discount_amt, 2); ?>
                                <?php if (!empty($payment['discount_type'])): ?>
                                    (<?php echo $payment['discount_type'] == 'percentage' ? $payment['discount_value'] . '%' : 'Fixed'; ?>)
                                <?php endif; ?>
                            </small>
                            <?php endif; ?>
                            
                            <!-- Recorded By -->
                            <?php if (!empty($payment['recorded_by_name'])): ?>
                            <small class="d-block">
                                <i class="bi bi-person text-primary"></i>
                                <span class="text-muted">By:</span> 
                                <?php echo htmlspecialchars($payment['recorded_by_name']); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-3">
                <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                <p class="text-muted mb-0 mt-2">No payment records found</p>
                <button class="btn btn-sm btn-outline-success mt-2" data-bs-toggle="modal" data-bs-target="#approvePaymentModal">
                    <i class="bi bi-plus-lg"></i> Record First Payment
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.payment-item {
    background-color: #fafafa;
    transition: all 0.2s ease;
}
.payment-item:hover {
    background-color: #f0f0f0;
}
.payment-item.border-success {
    background-color: #f0fff4;
}
.payment-history-list::-webkit-scrollbar {
    width: 6px;
}
.payment-history-list::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.payment-history-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}
.payment-history-list::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>
                </div>
            </div>
        </div>
    </div>
</section>
         <?php
                                $type = isset($_GET['type']) ? $_GET['type'] : ''; ?>
<!-- Add Follow-up Modal -->
<div class="modal fade" id="addFollowupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Add Follow-up</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="includes/process_enquiry.php" method="POST">
                <input type="hidden" name="action" value="add_followup">
                <input type="hidden" name="enquiry_type" value="<?php echo $type; ?>">
                <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['reference']; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Action Taken</label>
                            <textarea name="action_taken" class="form-control" rows="3" placeholder="What action did you take? (e.g., Called the client, sent email...)"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Client Response</label>
                            <textarea name="client_response" class="form-control" rows="3" placeholder="What did the client say?"></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Next Step <span class="text-danger">*</span></label>
                        <select name="next_step" class="form-select" required>
                            <option value="">Select next action...</option>
                            <option value="Send proposal">📄 Send proposal</option>
                            <option value="Schedule call">📞 Schedule call</option>
                            <option value="Send brochure">📋 Send brochure</option>
                            <option value="Follow up email">📧 Follow up email</option>
                            <option value="Arrange meeting">🤝 Arrange meeting</option>
                            <option value="Send invoice">💰 Send invoice</option>
                            <option value="Confirm payment">✅ Confirm payment</option>
                            <option value="Send reminder">⏰ Send reminder</option>
                            <option value="Final follow up">🔔 Final follow up</option>
                            <option value="Close - Won">🎉 Close - Won</option>
                            <option value="Close - Lost">❌ Close - Lost</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reminder Date <span class="text-danger">*</span></label>
                            <input type="date" name="reminder_date" class="form-control" required value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reminder Time</label>
                            <input type="time" name="reminder_time" class="form-control" value="09:00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Convert Modal (for enquiries only) -->
<?php if ($type == 'enquiry'): ?>
<div class="modal fade" id="convertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2"></i>Convert Enquiry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="includes/process_enquiry.php" method="POST">
                <input type="hidden" name="action" value="convert_enquiry">
                <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['id']; ?>">
                <div class="modal-body">
                    <p>Convert this enquiry to a full record. Choose the destination:</p>
                    <div class="d-grid gap-2">
                        <label class="btn btn-outline-primary">
                            <input type="radio" name="convert_to" value="register" class="me-2" required>
                            <i class="bi bi-laptop me-2"></i>Virtual Course (register)
                        </label>
                        <label class="btn btn-outline-success">
                            <input type="radio" name="convert_to" value="ticket_congress" class="me-2">
                            <i class="bi bi-globe me-2"></i>International Event (ticket_congress)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-right-circle me-1"></i>Convert</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Enquiry Modal -->
<div class="modal fade" id="editEnquiryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="includes/process_enquiry.php" method="POST">
                <input type="hidden" name="action" value="update_enquiry">
                <input type="hidden" name="enquiry_type" value="<?php echo $type; ?>">
                <input type="hidden" name="enquiry_id" value="<?php echo $enquiry['reference']; ?>">
                <input type="hidden" name="record_id" value="<?php echo $enquiry['id'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="row">
                        <?php if ($type == 'enquiry' || $type == 'register'): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="firstname" class="form-control" value="<?php echo htmlspecialchars($enquiry['firstname'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastname" class="form-control" value="<?php echo htmlspecialchars($enquiry['lastname'] ?? ''); ?>">
                        </div>
                        <?php else: ?>
                        <div class="col-12 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($enquiry['fullname'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($enquiry['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($enquiry['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($enquiry['country'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Organization</label>
                            <input type="text" name="organization" class="form-control" value="<?php echo htmlspecialchars($enquiry['organization'] ?? ''); ?>">
                        </div>
                        
                        <?php if ($type == 'enquiry'): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interest Type</label>
                            <select name="interest_type" class="form-select" onchange="toggleInterestFields(this.value)">
                                <option value="undecided" <?php echo ($enquiry['interest_type'] ?? '') == 'undecided' ? 'selected' : ''; ?>>Undecided</option>
                                <option value="virtual" <?php echo ($enquiry['interest_type'] ?? '') == 'virtual' ? 'selected' : ''; ?>>Virtual Course</option>
                                <option value="international" <?php echo ($enquiry['interest_type'] ?? '') == 'international' ? 'selected' : ''; ?>>International Event</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?php echo ($enquiry['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($enquiry['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($enquiry['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3" id="programField" style="display: <?php echo ($enquiry['interest_type'] ?? '') == 'virtual' ? 'block' : 'none'; ?>">
                            <label class="form-label">Program Interest</label>
                            <select name="program_interest" class="form-select">
                                <option value="">Select program...</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo ($enquiry['program_interest'] ?? '') == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mb-3" id="eventField" style="display: <?php echo ($enquiry['interest_type'] ?? '') == 'international' ? 'block' : 'none'; ?>">
                            <label class="form-label">Event Interest</label>
                            <select name="event_interest" class="form-select">
                                <option value="">Select event...</option>
                                <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['event_id']; ?>" <?php echo ($enquiry['event_interest'] ?? '') == $event['event_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['event_title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($enquiry['notes'] ?? ''); ?></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'approve_payment_modal.php'; ?>

<script>
function toggleInterestFields(type) {
    document.getElementById('programField').style.display = type === 'virtual' ? 'block' : 'none';
    document.getElementById('eventField').style.display = type === 'international' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const sendBtn = document.getElementById('sendOutstandingInvoiceBtn');
    if (!sendBtn) return;

    sendBtn.addEventListener('click', function() {
        const sourceType = this.dataset.sourceType || '';
        const sourceId = this.dataset.sourceId || '';
        const recordId = this.dataset.recordId || '';
        const balance = this.dataset.balance || '0.00';

        const originalText = sendBtn.innerHTML;

        const runPreview = () => {
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing preview...';

            const formData = new FormData();
            formData.append('action', 'preview');
            formData.append('source_type', sourceType);
            formData.append('source_id', sourceId);
            formData.append('record_id', recordId);

            fetch('includes/process_outstanding_invoice.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;

                if (!data.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Preview Failed',
                        text: data.message || 'Unable to generate invoice preview.'
                    });
                    return;
                }

                const previewUrl = data.preview_url || '';
                const draftFile = data.draft_file || '';
                const previewLinkHtml = previewUrl
                    ? '<a href="' + previewUrl + '" target="_blank" rel="noopener noreferrer">Open Invoice Preview</a>'
                    : 'Preview link unavailable';

                Swal.fire({
                    icon: 'info',
                    title: 'Invoice Preview Ready',
                    html: 'Please review before sending:<br><strong>' + previewLinkHtml + '</strong><br><br>Click <strong>Send Invoice</strong> once approved.',
                    showCancelButton: true,
                    confirmButtonText: 'Send Invoice',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        runSend(draftFile);
                    }
                });
            })
            .catch(error => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Unexpected error while preparing preview.'
                });
            });
        };

        const runSend = (draftFile) => {
            const originalText = sendBtn.innerHTML;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

            const formData = new FormData();
            formData.append('action', 'send');
            formData.append('source_type', sourceType);
            formData.append('source_id', sourceId);
            formData.append('record_id', recordId);
            if (draftFile) {
                formData.append('draft_file', draftFile);
            }

            fetch('includes/process_outstanding_invoice.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Invoice Sent',
                        text: data.message || 'Outstanding balance invoice sent successfully.'
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: data.message || 'Unable to send outstanding invoice.'
                    });
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Unexpected error while sending invoice.'
                });
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
            });
        };

        Swal.fire({
            icon: 'question',
            title: 'Generate outstanding balance invoice?',
            html: 'A draft invoice for <strong>$' + balance + '</strong> will be generated for preview first, then sent after your approval.',
            showCancelButton: true,
            confirmButtonText: 'Generate Preview',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                runPreview();
            }
        });
    });
});
</script>
<?php require_once 'footer.php'; ?>