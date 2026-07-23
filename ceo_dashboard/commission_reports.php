<?php
require_once 'header.php';
require_once 'commission_functions.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all'; // all, virtual, international
$filter_status = $_GET['status'] ?? 'all'; // all, pending, approved, paid
$filter_staff = intval($_GET['staff'] ?? 0);

// Build WHERE clause
$where_clauses = [];
if ($filter_type !== 'all') {
    $where_clauses[] = "cr.commission_type = '" . mysqli_real_escape_string($conn, $filter_type) . "'";
}
if ($filter_status !== 'all') {
    $where_clauses[] = "cr.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
if ($filter_staff > 0) {
    $where_clauses[] = "cr.staff_user_id = $filter_staff";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get all commission records
$records_query = mysqli_query($conn, "
    SELECT cr.*
    FROM commission_records cr
    $where_sql
    ORDER BY cr.calculated_at DESC
");

$records = [];
$total_eligible = 0;
$total_pending = 0;
$total_approved = 0;
$total_paid = 0;

if ($records_query) {
    while ($row = mysqli_fetch_assoc($records_query)) {
        $records[] = $row;
        
        if ($row['is_eligible']) {
            $total_eligible += $row['commission_amount'];
            
            switch ($row['status']) {
                case 'pending':
                case 'pending_approval':
                    $total_pending += $row['commission_amount'];
                    break;
                case 'approved':
                    $total_approved += $row['commission_amount'];
                    break;
                case 'paid':
                    $total_paid += $row['commission_amount'];
                    break;
            }
        }
    }
}

// Get staff list for filter
$staff_query = mysqli_query($conn, "
    SELECT DISTINCT cr.staff_user_id, cr.staff_name
    FROM commission_records cr
    ORDER BY cr.staff_name ASC
");
$staff_list = [];
while ($row = mysqli_fetch_assoc($staff_query)) {
    $staff_list[] = $row;
}

// Get global settings
$virtual_fee_threshold = getCommissionSetting($conn, 'virtual_fee_collection_threshold', '80');
$virtual_client_threshold = getCommissionSetting($conn, 'virtual_client_payment_threshold', '80');
$intl_fee_threshold = getCommissionSetting($conn, 'international_fee_collection_threshold', '90');
$intl_client_threshold = getCommissionSetting($conn, 'international_client_payment_threshold', '100');
$usd_to_kes_rate = floatval(getCommissionSetting($conn, 'commission_conversion_rate', '129'));

// Convert totals to KES
$total_eligible_kes = $total_eligible * $usd_to_kes_rate;
$total_pending_kes = $total_pending * $usd_to_kes_rate;
$total_approved_kes = $total_approved * $usd_to_kes_rate;
$total_paid_kes = $total_paid * $usd_to_kes_rate;
?>

<style>
.stat-card {
    border-radius: 10px;
    transition: all 0.3s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-card .stat-value {
    font-size: 1.5rem;
    font-weight: bold;
}
.commission-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 0.85rem;
}
.badge-eligible { background: #198754; }
.badge-not-eligible { background: #dc3545; }
.badge-pending { background: #ffc107; color: #000; }
.badge-approved { background: #0d6efd; }
.badge-paid { background: #198754; }
.badge-draft { background: #6c757d; }
.progress-thin {
    height: 6px;
    border-radius: 3px;
}
.type-badge-virtual { background: #0d6efd; }
.type-badge-international { background: #dc3545; }
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Commission Reports</h4>
                    <p class="text-muted mb-0">View and manage staff commission disbursements</p>
                </div>
                <div>
                    <button type="button" class="btn btn-success me-2" onclick="calculateAllCommissions()">
                        <i class="fas fa-calculator me-1"></i>Calculate All
                    </button>
                    <a href="commission_rules.php" class="btn btn-outline-secondary">
                        <i class="fas fa-cogs me-1"></i>Settings
                    </a>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Total Eligible</p>
                                    <p class="stat-value text-dark mb-0">KES <?php echo number_format($total_eligible_kes, 2); ?></p>
                                    <small class="text-muted">USD <?php echo number_format($total_eligible, 2); ?></small>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-coins fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Pending Approval</p>
                                    <p class="stat-value text-warning mb-0">KES <?php echo number_format($total_pending_kes, 2); ?></p>
                                    <small class="text-muted">USD <?php echo number_format($total_pending, 2); ?></small>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-clock fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Approved</p>
                                    <p class="stat-value text-info mb-0">KES <?php echo number_format($total_approved_kes, 2); ?></p>
                                    <small class="text-muted">USD <?php echo number_format($total_approved, 2); ?></small>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Paid Out</p>
                                    <p class="stat-value text-success mb-0">KES <?php echo number_format($total_paid_kes, 2); ?></p>
                                    <small class="text-muted">USD <?php echo number_format($total_paid, 2); ?></small>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-money-bill-wave fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conversion Rate Info -->
            <div class="alert alert-light border mb-4 py-2">
                <small><i class="fas fa-exchange-alt me-2"></i>Conversion Rate: <strong>1 USD = KES <?php echo number_format($usd_to_kes_rate, 2); ?></strong></small>
            </div>

            <!-- Filters -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small">Type</label>
                            <select class="form-select form-select-sm" name="type">
                                <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="virtual" <?php echo $filter_type == 'virtual' ? 'selected' : ''; ?>>Virtual (Intakes)</option>
                                <option value="international" <?php echo $filter_type == 'international' ? 'selected' : ''; ?>>International (Events)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="draft" <?php echo $filter_status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="pending_approval" <?php echo $filter_status == 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Staff</label>
                            <select class="form-select form-select-sm" name="staff">
                                <option value="0">All Staff</option>
                                <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?php echo $staff['staff_user_id']; ?>" <?php echo $filter_staff == $staff['staff_user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['staff_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm me-2">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            <a href="commission_reports.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Commission Records Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Commission Records</h6>
                        <span class="badge bg-secondary"><?php echo count($records); ?> records</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($records)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Commission Records</h5>
                            <p class="text-muted">Click "Calculate All" to generate commission records from configured intakes/events</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover commission-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Intake/Event</th>
                                        <th>Staff</th>
                                        <th class="text-center">Registered</th>
                                        <th class="text-center">Qualified</th>
                                        <th class="text-center">Fee Collection</th>
                                        <th class="text-end">Commission</th>
                                        <th class="text-center">Eligible</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): 
                                        $is_virtual = $record['commission_type'] == 'virtual';
                                        $fee_threshold = $is_virtual ? $virtual_fee_threshold : $intl_fee_threshold;
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge <?php echo $is_virtual ? 'type-badge-virtual' : 'type-badge-international'; ?>">
                                                    <?php echo $is_virtual ? 'Virtual' : 'International'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['source_name']); ?></strong>
                                                <br><small class="text-muted">
                                                    <?php echo $record['commission_currency']; ?> <?php echo number_format($record['unit_fee'], 2); ?> × <?php echo $record['commission_rate']; ?>%
                                                </small>
                                            </td>
                                            <td>
                                                <i class="fas fa-user-circle text-secondary me-1"></i>
                                                <?php echo htmlspecialchars($record['staff_name']); ?>
                                            </td>
                                            <td class="text-center"><?php echo $record['total_registered']; ?></td>
                                            <td class="text-center">
                                                <strong class="<?php echo $record['min_clients_met'] ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $record['qualifying_clients']; ?>
                                                </strong>
                                                <small class="text-muted">/ <?php echo $record['minimum_clients_required']; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div style="width: 60px;">
                                                        <div class="progress progress-thin">
                                                            <div class="progress-bar <?php echo $record['fee_collection_met'] ? 'bg-success' : 'bg-danger'; ?>" 
                                                                 style="width: <?php echo min($record['fee_collection_percentage'], 100); ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <span class="ms-2 small <?php echo $record['fee_collection_met'] ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo number_format($record['fee_collection_percentage'], 1); ?>%
                                                    </span>
                                                </div>
                                                <small class="text-muted">min <?php echo $fee_threshold; ?>%</small>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($record['is_eligible']): 
                                                    $commission_kes = $record['commission_amount'] * $usd_to_kes_rate;
                                                ?>
                                                    <strong class="text-success">
                                                        KES <?php echo number_format($commission_kes, 2); ?>
                                                    </strong>
                                                    <br><small class="text-muted">USD <?php echo number_format($record['commission_amount'], 2); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($record['is_eligible']): ?>
                                                    <span class="badge badge-eligible">
                                                        <i class="fas fa-check me-1"></i>Yes
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-not-eligible">
                                                        <i class="fas fa-times me-1"></i>No
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $status_class = 'badge-draft';
                                                $status_text = ucfirst($record['status']);
                                                switch ($record['status']) {
                                                    case 'pending_approval':
                                                        $status_class = 'badge-pending';
                                                        $status_text = 'Pending';
                                                        break;
                                                    case 'approved':
                                                        $status_class = 'badge-approved';
                                                        break;
                                                    case 'paid':
                                                        $status_class = 'badge-paid';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                            onclick="viewDetails(<?php echo $record['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($record['is_eligible']): ?>
                                                        <?php if ($record['status'] == 'draft'): ?>
                                                            <button type="button" class="btn btn-outline-warning btn-sm" 
                                                                    onclick="submitForApproval(<?php echo $record['id']; ?>)" title="Submit for Approval">
                                                                <i class="fas fa-paper-plane"></i>
                                                            </button>
                                                        <?php elseif ($record['status'] == 'pending_approval'): ?>
                                                            <button type="button" class="btn btn-outline-success btn-sm" 
                                                                    onclick="approveCommission(<?php echo $record['id']; ?>)" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                    onclick="rejectCommission(<?php echo $record['id']; ?>)" title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php elseif ($record['status'] == 'approved'): ?>
                                                            <button type="button" class="btn btn-outline-success btn-sm" 
                                                                    onclick="markAsPaid(<?php echo $record['id']; ?>)" title="Mark as Paid">
                                                                <i class="fas fa-money-bill-wave"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            onclick="recalculate(<?php echo $record['id']; ?>, '<?php echo $record['commission_type']; ?>', <?php echo $record['source_id']; ?>)" 
                                                            title="Recalculate">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Commission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Mark as Paid</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" name="record_id" id="paymentRecordId">
                    <div class="mb-3">
                        <label class="form-label">Payment Reference</label>
                        <input type="text" class="form-control" name="payment_reference" placeholder="e.g., Payroll Dec 2025, Bank Transfer #123">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Any additional notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="confirmPayment()">
                    <i class="fas fa-check me-1"></i>Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Calculate all commissions
function calculateAllCommissions() {
    Swal.fire({
        title: 'Calculate All Commissions?',
        text: 'This will calculate/recalculate commissions for all configured intakes and events.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Calculate'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Calculating...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch('process_commission.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=calculate_all'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Done!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            });
        }
    });
}

// View details
function viewDetails(recordId) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
    
    fetch('process_commission.php?action=get_details&id=' + recordId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('detailsContent').innerHTML = data.html;
            } else {
                document.getElementById('detailsContent').innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        });
}

// Submit for approval
function submitForApproval(recordId) {
    updateStatus(recordId, 'pending_approval', 'Submit for Approval?', 'This will submit the commission for approval.');
}

// Approve commission
function approveCommission(recordId) {
    updateStatus(recordId, 'approved', 'Approve Commission?', 'This will approve the commission for payment.');
}

// Reject commission
function rejectCommission(recordId) {
    Swal.fire({
        title: 'Reject Commission?',
        input: 'textarea',
        inputLabel: 'Reason for rejection',
        inputPlaceholder: 'Enter reason...',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Reject'
    }).then((result) => {
        if (result.isConfirmed) {
            performStatusUpdate(recordId, 'rejected', result.value);
        }
    });
}

// Mark as paid
function markAsPaid(recordId) {
    document.getElementById('paymentRecordId').value = recordId;
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function confirmPayment() {
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);
    formData.append('action', 'mark_paid');
    
    Swal.fire({
        title: 'Processing...',
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
                title: 'Marked as Paid!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    });
}

// Generic status update
function updateStatus(recordId, newStatus, title, text) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes'
    }).then((result) => {
        if (result.isConfirmed) {
            performStatusUpdate(recordId, newStatus);
        }
    });
}

function performStatusUpdate(recordId, newStatus, reason = '') {
    Swal.fire({
        title: 'Processing...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    fetch('process_commission.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_status&record_id=${recordId}&status=${newStatus}&reason=${encodeURIComponent(reason)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    });
}

// Recalculate single record
function recalculate(recordId, type, sourceId) {
    Swal.fire({
        title: 'Recalculate?',
        text: 'This will recalculate the commission based on current data.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Recalculate'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Calculating...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch('process_commission.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=recalculate&type=${type}&source_id=${sourceId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Recalculated!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            });
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>