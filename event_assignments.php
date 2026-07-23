<?php
session_start();
require_once 'header.php';
require_once '../function.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

// Get commission settings
function getCommissionSetting($conn, $key, $default = '') {
    $key = mysqli_real_escape_string($conn, $key);
    $result = mysqli_query($conn, "SELECT setting_value FROM commission_settings WHERE setting_key = '$key'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

$fee_threshold = getCommissionSetting($conn, 'international_fee_collection_threshold', '90');
$client_threshold = getCommissionSetting($conn, 'international_client_payment_threshold', '100');

// Get all active events with stats
$events_query = mysqli_query($conn, "
    SELECT 
        e.event_id,
        e.event_title,
        e.location,
        e.start_on,
        e.end_on,
        e.early_amount,
        e.currency_code,
        e.status,
        e.assigned_to,
        e.minimum_clients,
        e.commission_rate,
        ru.fullname AS assigned_to_name
    FROM Event e
    LEFT JOIN registered_users ru ON e.assigned_to = ru.id
    WHERE e.status = 1
    ORDER BY e.start_on DESC
");

$events = [];
while ($row = mysqli_fetch_assoc($events_query)) {
    $event_id = intval($row['event_id']);
    $fee = floatval($row['early_amount']);
    $min_payment = $fee * ($client_threshold / 100); // 100% for international
    
    // Get registration stats with payment info using proper JOIN
    // Event → ticket_congress (via event_id) → dpo_payment (via ticket_id = app_id)
    $stats_q = mysqli_query($conn, "
        SELECT 
            COUNT(t.id) AS total_registered,
            SUM(CASE WHEN COALESCE(pay.total_paid, 0) >= $min_payment THEN 1 ELSE 0 END) AS qualifying_clients
        FROM ticket_congress t
        LEFT JOIN (
            SELECT app_id, SUM(TransactionAmount) AS total_paid
            FROM dpo_payment
            WHERE status = 2
            GROUP BY app_id
        ) pay ON pay.app_id = t.ticket_id
        WHERE t.event_id = $event_id
    ");
    
    if ($stats_q && $stats = mysqli_fetch_assoc($stats_q)) {
        $row['total_registered'] = intval($stats['total_registered']);
        $row['qualifying_clients'] = intval($stats['qualifying_clients']);
    } else {
        $row['total_registered'] = 0;
        $row['qualifying_clients'] = 0;
    }
    
    $events[] = $row;
}

// Get staff list
$staff_query = mysqli_query($conn, "
    SELECT ru.id, ru.fullname, s.job_title, d.department_name
    FROM registered_users ru
    LEFT JOIN staff s ON ru.staff_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
 
    ORDER BY ru.fullname ASC
");
$staff_list = [];
while ($row = mysqli_fetch_assoc($staff_query)) {
    $staff_list[] = $row;
}
?>

<style>
.event-card { transition: all 0.3s; border-left: 4px solid #dee2e6; }
.event-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
.event-card.assigned { border-left-color: #dc3545; }
.event-card.configured { border-left-color: #198754; }
.event-card.not-assigned { border-left-color: #ffc107; }
.stat-badge { font-size: 0.75rem; padding: 4px 8px; }
.commission-preview { background: #fdecea; border-radius: 8px; padding: 15px; }
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-globe me-2"></i>Event Assignments</h4>
                    <p class="text-muted mb-0">Assign staff and configure commission for international events</p>
                </div>
                <div>
                    <a href="commission_rules.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-cogs me-1"></i>Global Rules
                    </a>
                    <a href="intake_assignments.php" class="btn btn-outline-primary">
                        <i class="fas fa-laptop me-1"></i>Intake Assignments
                    </a>
                </div>
            </div>

            <!-- Rules Summary -->
            <div class="alert alert-danger mb-4" style="background: #fef2f2; border-color: #dc3545;">
                <i class="fas fa-info-circle me-2"></i>
                <strong>International Rules:</strong> 
                Fee Collection ≥ <?php echo $fee_threshold; ?>% | 
                Client qualifies if paid ≥ <?php echo $client_threshold; ?>% of event fee
            </div>

            <!-- Events Grid -->
            <div class="row">
                <?php if (empty($events)): ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Active Events</h5>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): 
                        $has_assignment = !empty($event['assigned_to']);
                        $has_config = ($event['minimum_clients'] > 0 && $event['commission_rate'] > 0);
                        $card_class = $has_config ? 'configured' : ($has_assignment ? 'assigned' : 'not-assigned');
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card shadow-sm border-0 event-card <?php echo $card_class; ?> h-100">
                                <div class="card-header bg-white py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($event['event_title']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?>
                                            </small>
                                        </div>
                                        <?php if ($has_config): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Ready</span>
                                        <?php elseif ($has_assignment): ?>
                                            <span class="badge bg-warning text-dark">Needs Config</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3 small">
                                        <div class="col-6">
                                            <span class="text-muted">Date:</span><br>
                                            <strong><?php echo $event['start_on'] ? date('M d, Y', strtotime($event['start_on'])) : '-'; ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted">Event Fee:</span><br>
                                            <strong><?php echo $event['currency_code'] ?? 'USD'; ?> <?php echo number_format($event['early_amount'] ?? 0, 2); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <!-- Stats -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-light text-dark stat-badge">
                                            <i class="fas fa-ticket-alt me-1"></i><?php echo $event['total_registered']; ?> Tickets
                                        </span>
                                        <span class="badge bg-success stat-badge">
                                            <i class="fas fa-check me-1"></i><?php echo $event['qualifying_clients']; ?> Paid Full
                                        </span>
                                        <?php if ($has_config): ?>
                                            <span class="badge bg-info stat-badge">
                                                Min: <?php echo $event['minimum_clients']; ?>
                                            </span>
                                            <span class="badge bg-danger stat-badge">
                                                <?php echo $event['commission_rate']; ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Assigned To -->
                                    <div class="mb-3">
                                        <small class="text-muted">Assigned To:</small>
                                        <?php if ($has_assignment): ?>
                                            <div class="d-flex align-items-center mt-1">
                                                <i class="fas fa-user-circle fa-lg text-danger me-2"></i>
                                                <strong><?php echo htmlspecialchars($event['assigned_to_name']); ?></strong>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted mt-1 fst-italic">Not assigned</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <button type="button" class="btn btn-danger btn-sm w-100" 
                                            onclick="openConfigModal(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                                        <i class="fas fa-edit me-1"></i>Configure
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<!-- Configuration Modal -->
<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-globe me-2"></i>Configure Event Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="configForm">
                    <input type="hidden" name="event_id" id="eventId">
                    <input type="hidden" name="action" value="save_event_assignment">
                    
                    <!-- Event Info -->
                    <div class="alert alert-light border mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Event:</strong> <span id="modalEventName"></span><br>
                                <strong>Location:</strong> <span id="modalLocation"></span>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong>Event Fee:</strong> <span id="modalCurrency"></span> <span id="modalEventFee"></span><br>
                                <strong>Registered:</strong> <span id="modalRegCount"></span> | 
                                <strong>Paid Full:</strong> <span id="modalQualifyCount"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Staff Assignment -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user me-1"></i>Assign Staff Member <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="assigned_to" id="assignedTo" required>
                            <option value="">-- Select Staff --</option>
                            <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>">
                                    <?php echo htmlspecialchars($staff['fullname']); ?>
                                    <?php if ($staff['job_title']): ?> - <?php echo htmlspecialchars($staff['job_title']); ?><?php endif; ?>
                                    <?php if ($staff['department_name']): ?> (<?php echo htmlspecialchars($staff['department_name']); ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only one person can be assigned per event</small>
                    </div>
                    
                    <!-- Commission Settings -->
                    <div class="bg-light rounded p-3 mb-4">
                        <h6 class="mb-3"><i class="fas fa-coins me-2"></i>Commission Settings</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    Minimum Clients <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" name="minimum_clients" id="minClients" 
                                       min="1" required placeholder="e.g., 50">
                                <small class="text-muted">Target number of fully paid clients</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    Commission Rate <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="commission_rate" id="commissionRate" 
                                           min="0.01" max="100" step="0.01" required placeholder="e.g., 10">
                                    <span class="input-group-text">% of fee</span>
                                </div>
                                <small class="text-muted">Percentage of event fee per qualifying client</small>
                            </div>
                        </div>
                        
                        <!-- Commission Preview -->
                        <div class="commission-preview mt-3">
                            <strong><i class="fas fa-calculator me-2"></i>Commission Preview:</strong>
                            <div id="commissionPreview" class="mt-2">Enter values to see calculation</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="saveConfig()">
                    <i class="fas fa-save me-1"></i>Save Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentEvent = null;

function openConfigModal(event) {
    currentEvent = event;
    
    document.getElementById('eventId').value = event.event_id;
    document.getElementById('modalEventName').textContent = event.event_title;
    document.getElementById('modalLocation').textContent = event.location || 'TBD';
    document.getElementById('modalCurrency').textContent = event.currency_code || 'USD';
    document.getElementById('modalEventFee').textContent = parseFloat(event.early_amount || 0).toFixed(2);
    document.getElementById('modalRegCount').textContent = event.total_registered || 0;
    document.getElementById('modalQualifyCount').textContent = event.qualifying_clients || 0;
    
    document.getElementById('assignedTo').value = event.assigned_to || '';
    document.getElementById('minClients').value = event.minimum_clients || '';
    document.getElementById('commissionRate').value = event.commission_rate || '';
    
    updatePreview();
    new bootstrap.Modal(document.getElementById('configModal')).show();
}

function updatePreview() {
    const fee = parseFloat(currentEvent?.early_amount || 0);
    const rate = parseFloat(document.getElementById('commissionRate').value || 0);
    const minClients = parseInt(document.getElementById('minClients').value || 0);
    const currentQualified = parseInt(currentEvent?.qualifying_clients || 0);
    const currency = currentEvent?.currency_code || 'USD';
    
    if (fee > 0 && rate > 0) {
        const perClient = fee * (rate / 100);
        let html = `<div>${currency} ${fee.toFixed(2)} × ${rate}% = <strong>${currency} ${perClient.toFixed(2)} per client</strong></div>`;
        
        if (minClients > 0) {
            const projectedMin = perClient * minClients;
            html += `<div class="mt-1">If ${minClients} clients: <strong>${currency} ${projectedMin.toFixed(2)}</strong></div>`;
        }
        
        if (currentQualified > 0) {
            const currentCommission = perClient * currentQualified;
            html += `<div class="mt-1 text-success">Current ${currentQualified} paid full: <strong>${currency} ${currentCommission.toFixed(2)}</strong></div>`;
        }
        
        document.getElementById('commissionPreview').innerHTML = html;
    } else {
        document.getElementById('commissionPreview').textContent = 'Enter values to see calculation';
    }
}

document.getElementById('commissionRate')?.addEventListener('input', updatePreview);
document.getElementById('minClients')?.addEventListener('input', updatePreview);

function saveConfig() {
    const form = document.getElementById('configForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch('process_commission.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    })
    .catch(error => {
        Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred' });
    });
}
</script>

<?php require_once 'footer.php'; ?>
