<?php
session_start();
require_once 'header.php';
require_once '../function.php';

$success_message = '';
$error_message = '';
$current_user_id = intval($_SESSION['login_id'] ?? 1);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new recipient
    if (isset($_POST['add_recipient'])) {
        $recipient_name = mysqli_real_escape_string($conn, $_POST['recipient_name']);
        $recipient_code = mysqli_real_escape_string($conn, $_POST['recipient_code']);
        $recipient_type = mysqli_real_escape_string($conn, $_POST['recipient_type']);
        $deduction_type = mysqli_real_escape_string($conn, $_POST['deduction_type']);
        $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
        $bank_branch = mysqli_real_escape_string($conn, $_POST['bank_branch'] ?? '');
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
        $account_name = mysqli_real_escape_string($conn, $_POST['account_name'] ?? '');
        $paybill_number = mysqli_real_escape_string($conn, $_POST['paybill_number'] ?? '');
        $portal_url = mysqli_real_escape_string($conn, $_POST['portal_url'] ?? '');
        
        $sql = "INSERT INTO remittance_recipients (
            recipient_name, recipient_code, recipient_type, deduction_type,
            contact_person, phone, email, bank_name, bank_branch,
            account_number, account_name, paybill_number, portal_url, created_by
        ) VALUES (
            '$recipient_name', '$recipient_code', '$recipient_type', '$deduction_type',
            '$contact_person', '$phone', '$email', '$bank_name', '$bank_branch',
            '$account_number', '$account_name', '$paybill_number', '$portal_url', $current_user_id
        )";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "Recipient added successfully!";
        } else {
            $error_message = "Failed to add recipient: " . mysqli_error($conn);
        }
    }
    
    // Update recipient
    if (isset($_POST['update_recipient'])) {
        $id = intval($_POST['recipient_id']);
        $recipient_name = mysqli_real_escape_string($conn, $_POST['recipient_name']);
        $recipient_code = mysqli_real_escape_string($conn, $_POST['recipient_code']);
        $recipient_type = mysqli_real_escape_string($conn, $_POST['recipient_type']);
        $deduction_type = mysqli_real_escape_string($conn, $_POST['deduction_type']);
        $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
        $bank_branch = mysqli_real_escape_string($conn, $_POST['bank_branch'] ?? '');
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
        $account_name = mysqli_real_escape_string($conn, $_POST['account_name'] ?? '');
        $paybill_number = mysqli_real_escape_string($conn, $_POST['paybill_number'] ?? '');
        $portal_url = mysqli_real_escape_string($conn, $_POST['portal_url'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "UPDATE remittance_recipients SET
            recipient_name = '$recipient_name',
            recipient_code = '$recipient_code',
            recipient_type = '$recipient_type',
            deduction_type = '$deduction_type',
            contact_person = '$contact_person',
            phone = '$phone',
            email = '$email',
            bank_name = '$bank_name',
            bank_branch = '$bank_branch',
            account_number = '$account_number',
            account_name = '$account_name',
            paybill_number = '$paybill_number',
            portal_url = '$portal_url',
            is_active = $is_active
            WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "Recipient updated successfully!";
        } else {
            $error_message = "Failed to update: " . mysqli_error($conn);
        }
    }
}

// Get filter
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

// Build query
$where_sql = '';
if ($filter_type) {
    $filter_type_esc = mysqli_real_escape_string($conn, $filter_type);
    $where_sql = "WHERE recipient_type = '$filter_type_esc'";
}

// Get all recipients
$recipients = [];
$result = mysqli_query($conn, "SELECT * FROM remittance_recipients $where_sql ORDER BY recipient_type, recipient_name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recipients[] = $row;
    }
}

// Type labels and colors
$type_labels = [
    'statutory' => 'Statutory',
    'sacco' => 'SACCO',
    'bank' => 'Bank/Loan',
    'insurance' => 'Insurance',
    'pension' => 'Pension',
    'union' => 'Union',
    'other' => 'Other'
];

$type_colors = [
    'statutory' => 'danger',
    'sacco' => 'primary',
    'bank' => 'success',
    'insurance' => 'info',
    'pension' => 'warning',
    'union' => 'secondary',
    'other' => 'dark'
];

// Deduction types
$deduction_types = [
    'Statutory' => ['PAYE', 'NSSF', 'SHIF', 'HOUSING_LEVY', 'HELB'],
    'Voluntary' => ['SACCO', 'LOAN', 'MORTGAGE', 'INSURANCE', 'PENSION', 'UNION_DUES', 'OTHER']
];
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-building me-2"></i>Remittance Recipients</h4>
                    <p class="text-muted mb-0">Manage SACCOs, banks, and other institutions where deductions are remitted</p>
                </div>
                <button class="btn btn-primary rounded-0" data-bs-toggle="modal" data-bs-target="#addRecipientModal">
                    <i class="fas fa-plus me-2"></i>Add Recipient
                </button>
            </div>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-0">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-0">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filter -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Filter by Type</label>
                            <select class="form-select rounded-0" name="type" onchange="this.form.submit()">
                                <option value="">-- All Types --</option>
                                <?php foreach ($type_labels as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($filter_type == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <a href="remittance_recipients.php" class="btn btn-outline-secondary rounded-0">
                                <i class="fas fa-sync me-1"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recipients Table -->
            <div class="card shadow-sm rounded-0">
                <div class="card-header bg-dark text-white rounded-0">
                    <i class="fas fa-list me-2"></i>Recipients (<?php echo count($recipients); ?>)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Deduction</th>
                                    <th>Bank/Paybill</th>
                                    <th>Account</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th width="100">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recipients)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No recipients found</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recipients as $rec): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($rec['recipient_name']); ?></strong>
                                        <?php if ($rec['recipient_code']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($rec['recipient_code']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $type_colors[$rec['recipient_type']] ?? 'secondary'; ?>">
                                            <?php echo $type_labels[$rec['recipient_type']] ?? $rec['recipient_type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($rec['deduction_type']); ?></td>
                                    <td>
                                        <?php if ($rec['bank_name']): ?>
                                        <?php echo htmlspecialchars($rec['bank_name']); ?>
                                        <?php if ($rec['bank_branch']): ?><br><small class="text-muted"><?php echo htmlspecialchars($rec['bank_branch']); ?></small><?php endif; ?>
                                        <?php elseif ($rec['paybill_number']): ?>
                                        Paybill: <?php echo htmlspecialchars($rec['paybill_number']); ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($rec['account_number'] ?: '-'); ?>
                                        <?php if ($rec['account_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($rec['account_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rec['contact_person'] || $rec['phone']): ?>
                                        <small>
                                            <?php echo htmlspecialchars($rec['contact_person']); ?>
                                            <?php if ($rec['phone']): ?><br><?php echo htmlspecialchars($rec['phone']); ?><?php endif; ?>
                                        </small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rec['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary rounded-0" 
                                                onclick='editRecipient(<?php echo json_encode($rec); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add Recipient Modal -->
<div class="modal fade" id="addRecipientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-0">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Remittance Recipient</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-0" name="recipient_name" required placeholder="e.g., Stima SACCO, Equity Bank">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Short Code</label>
                            <input type="text" class="form-control rounded-0" name="recipient_code" placeholder="e.g., STIMA">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Recipient Type <span class="text-danger">*</span></label>
                            <select class="form-select rounded-0" name="recipient_type" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($type_labels as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Deduction Type <span class="text-danger">*</span></label>
                            <select class="form-select rounded-0" name="deduction_type" required>
                                <option value="">-- Select --</option>
                                <optgroup label="Statutory">
                                    <option value="PAYE">PAYE</option>
                                    <option value="NSSF">NSSF</option>
                                    <option value="SHIF">SHIF</option>
                                    <option value="HOUSING_LEVY">Housing Levy</option>
                                    <option value="HELB">HELB</option>
                                </optgroup>
                                <optgroup label="Voluntary">
                                    <option value="SACCO">SACCO Contribution</option>
                                    <option value="LOAN">Loan Repayment</option>
                                    <option value="MORTGAGE">Mortgage</option>
                                    <option value="INSURANCE">Insurance Premium</option>
                                    <option value="PENSION">Pension Scheme</option>
                                    <option value="UNION_DUES">Union Dues</option>
                                    <option value="OTHER">Other</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-university me-2"></i>Bank/Payment Details</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control rounded-0" name="bank_name" placeholder="e.g., KCB Bank">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch</label>
                            <input type="text" class="form-control rounded-0" name="bank_branch" placeholder="e.g., Westlands">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control rounded-0" name="account_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Name</label>
                            <input type="text" class="form-control rounded-0" name="account_name">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">M-Pesa Paybill</label>
                            <input type="text" class="form-control rounded-0" name="paybill_number" placeholder="e.g., 123456">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Portal URL</label>
                            <input type="url" class="form-control rounded-0" name="portal_url" placeholder="https://...">
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-address-card me-2"></i>Contact Details</h6>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control rounded-0" name="contact_person">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control rounded-0" name="phone">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control rounded-0" name="email">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_recipient" class="btn btn-primary rounded-0">
                        <i class="fas fa-save me-1"></i>Save Recipient
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Recipient Modal -->
<div class="modal fade" id="editRecipientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-0">
            <form method="POST">
                <input type="hidden" name="recipient_id" id="edit_recipient_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Recipient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-0" name="recipient_name" id="edit_recipient_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Short Code</label>
                            <input type="text" class="form-control rounded-0" name="recipient_code" id="edit_recipient_code">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Recipient Type <span class="text-danger">*</span></label>
                            <select class="form-select rounded-0" name="recipient_type" id="edit_recipient_type" required>
                                <?php foreach ($type_labels as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Deduction Type <span class="text-danger">*</span></label>
                            <select class="form-select rounded-0" name="deduction_type" id="edit_deduction_type" required>
                                <optgroup label="Statutory">
                                    <option value="PAYE">PAYE</option>
                                    <option value="NSSF">NSSF</option>
                                    <option value="SHIF">SHIF</option>
                                    <option value="HOUSING_LEVY">Housing Levy</option>
                                    <option value="HELB">HELB</option>
                                </optgroup>
                                <optgroup label="Voluntary">
                                    <option value="SACCO">SACCO Contribution</option>
                                    <option value="LOAN">Loan Repayment</option>
                                    <option value="MORTGAGE">Mortgage</option>
                                    <option value="INSURANCE">Insurance Premium</option>
                                    <option value="PENSION">Pension Scheme</option>
                                    <option value="UNION_DUES">Union Dues</option>
                                    <option value="OTHER">Other</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-university me-2"></i>Bank/Payment Details</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control rounded-0" name="bank_name" id="edit_bank_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch</label>
                            <input type="text" class="form-control rounded-0" name="bank_branch" id="edit_bank_branch">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control rounded-0" name="account_number" id="edit_account_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Name</label>
                            <input type="text" class="form-control rounded-0" name="account_name" id="edit_account_name">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">M-Pesa Paybill</label>
                            <input type="text" class="form-control rounded-0" name="paybill_number" id="edit_paybill_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Portal URL</label>
                            <input type="url" class="form-control rounded-0" name="portal_url" id="edit_portal_url">
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-address-card me-2"></i>Contact Details</h6>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control rounded-0" name="contact_person" id="edit_contact_person">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control rounded-0" name="phone" id="edit_phone">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control rounded-0" name="email" id="edit_email">
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" checked>
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_recipient" class="btn btn-warning rounded-0">
                        <i class="fas fa-save me-1"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRecipient(data) {
    document.getElementById('edit_recipient_id').value = data.id;
    document.getElementById('edit_recipient_name').value = data.recipient_name || '';
    document.getElementById('edit_recipient_code').value = data.recipient_code || '';
    document.getElementById('edit_recipient_type').value = data.recipient_type || '';
    document.getElementById('edit_deduction_type').value = data.deduction_type || '';
    document.getElementById('edit_bank_name').value = data.bank_name || '';
    document.getElementById('edit_bank_branch').value = data.bank_branch || '';
    document.getElementById('edit_account_number').value = data.account_number || '';
    document.getElementById('edit_account_name').value = data.account_name || '';
    document.getElementById('edit_paybill_number').value = data.paybill_number || '';
    document.getElementById('edit_portal_url').value = data.portal_url || '';
    document.getElementById('edit_contact_person').value = data.contact_person || '';
    document.getElementById('edit_phone').value = data.phone || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_is_active').checked = data.is_active == 1;
    
    var modal = new bootstrap.Modal(document.getElementById('editRecipientModal'));
    modal.show();
}
</script>

<?php require_once 'footer.php'; ?>