<?php
/**
 * System Access Modal - Include this at the bottom of staff_details.php
 * Shows a button and modal to grant/manage system access
 * 
 * Required variables:
 * - $staff (array with staff data)
 * - $conn (database connection)
 * 
 * Place this include just before </body> or before footer.php
 */

// Only show for approved or active staff
$can_grant_access = in_array($staff['onboarding_status'], ['approved', 'active']);
$has_system_access = ($staff['system_access_granted'] ?? 0) == 1;
$corporate_email = $staff['corporate_email'] ?? '';
$system_role = $staff['system_role'] ?? 'staff';

// Get who granted access
$access_granted_by_name = '';
if (!empty($staff['system_access_granted_by'])) {
    $granter_q = mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = " . intval($staff['system_access_granted_by']));
    if ($granter_q && mysqli_num_rows($granter_q) > 0) {
        $access_granted_by_name = mysqli_fetch_assoc($granter_q)['fullname'];
    }
}

// Role options
$role_options = [
    'staff' => 'Staff',
    'hr' => 'HR',
    'finance' => 'Finance',
    'manager' => 'Manager',
    'admin' => 'Admin',
    'ceo' => 'CEO'
];
?>

<?php if ($can_grant_access): ?>

<!-- System Access Button - Add this where you want the button to appear -->
<div id="systemAccessButtonContainer">
    <?php if ($has_system_access): ?>
        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#systemAccessModal">
            <i class="fas fa-key me-1"></i>System Access <span class="badge bg-success ms-1">Active</span>
        </button>
    <?php else: ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#systemAccessModal">
            <i class="fas fa-user-plus me-1"></i>Grant System Access
        </button>
    <?php endif; ?>
</div>

<!-- System Access Modal -->
<div class="modal fade" id="systemAccessModal" tabindex="-1" aria-labelledby="systemAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header <?php echo $has_system_access ? 'bg-success text-white' : 'bg-primary text-white'; ?>">
                <h5 class="modal-title" id="systemAccessModalLabel">
                    <i class="fas fa-key me-2"></i>
                    <?php echo $has_system_access ? 'System Access Details' : 'Grant System Access'; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <?php if ($has_system_access): ?>
            <!-- Already has access - Show details -->
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="fas fa-check-circle fa-3x text-success"></i>
                    </div>
                    <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                    <p class="text-muted mb-0">Has ERP System Access</p>
                </div>
                
                <div class="bg-light rounded p-3 mb-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Login Email</label>
                            <div class="fw-semibold">
                                <i class="fas fa-envelope me-2 text-primary"></i>
                                <?php echo htmlspecialchars($corporate_email); ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">System Role</label>
                            <div class="fw-semibold">
                                <i class="fas fa-user-shield me-2 text-info"></i>
                                <?php echo htmlspecialchars($role_options[$system_role] ?? ucfirst($system_role)); ?>
                            </div>
                        </div>
                        <?php if (!empty($staff['system_access_granted_at'])): ?>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Granted On</label>
                            <div class="small">
                                <i class="fas fa-calendar me-2 text-secondary"></i>
                                <?php echo date('M d, Y \a\t h:i A', strtotime($staff['system_access_granted_at'])); ?>
                                <?php if ($access_granted_by_name): ?>
                                    by <?php echo htmlspecialchars($access_granted_by_name); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger" onclick="revokeSystemAccess()">
                    <i class="fas fa-ban me-1"></i>Revoke Access
                </button>
                <div>
                    <button type="button" class="btn btn-outline-warning" onclick="resetSystemPassword()">
                        <i class="fas fa-sync-alt me-1"></i>Reset Password
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
            
            <?php else: ?>
            <!-- No access - Show grant form -->
            <form id="grantSystemAccessForm">
                <input type="hidden" name="action" value="grant_access">
                <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="fas fa-user-plus fa-2x text-primary"></i>
                        </div>
                        <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($staff['full_name']); ?></h5>
                        <p class="text-muted small mb-0">Create ERP login account</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="corporate_email" class="form-label">
                            Corporate Email <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="corporate_email" name="corporate_email" 
                                   placeholder="e.g., <?php echo strtolower(str_replace(' ', '.', $staff['full_name'])); ?>@vantageafricaleaders.com" 
                                   required>
                        </div>
                        <div class="form-text">This will be the username for logging into the ERP</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="system_role" class="form-label">
                            System Role <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="system_role" name="system_role" required>
                            <?php foreach ($role_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Determines what modules they can access</div>
                    </div>
                    
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        A random password will be generated and sent to the email address above.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="grantAccessBtn">
                        <i class="fas fa-check me-1"></i>Create Account & Send Credentials
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Credentials Modal (shows if email fails) -->
<div class="modal fade" id="credentialsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Share Credentials Manually</h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    Account created but email could not be sent. Please share these credentials manually:
                </div>
                <div class="bg-light p-3 rounded border">
                    <div class="mb-2">
                        <strong>Portal:</strong> 
                        <a href="https://vantageafricaleaders.com/admin/" target="_blank">https://vantageafricaleaders.com/admin/</a>
                    </div>
                    <div class="mb-2">
                        <strong>Email:</strong> <span id="cred_email" class="user-select-all"></span>
                    </div>
                    <div>
                        <strong>Password:</strong> <code id="cred_password" class="fs-5 user-select-all"></code>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="copyCredentials()">
                    <i class="fas fa-copy me-1"></i>Copy to Clipboard
                </button>
                <button type="button" class="btn btn-primary" onclick="closeCredentialsAndReload()">
                    <i class="fas fa-check me-1"></i>Done
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Grant Access Form
document.getElementById('grantSystemAccessForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('grantAccessBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating Account...';
    btn.disabled = true;
    
    fetch('process_system_access.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close grant modal
            bootstrap.Modal.getInstance(document.getElementById('systemAccessModal')).hide();
            
            if (data.warning && data.credentials) {
                // Email failed - show credentials modal
                document.getElementById('cred_email').textContent = data.credentials.email;
                document.getElementById('cred_password').textContent = data.credentials.password;
                new bootstrap.Modal(document.getElementById('credentialsModal')).show();
            } else {
                // Success with email
                Swal.fire({
                    icon: 'success',
                    title: 'Account Created!',
                    html: 'Login credentials have been sent to:<br><strong>' + document.getElementById('corporate_email').value + '</strong>',
                    confirmButtonColor: '#198754'
                }).then(() => location.reload());
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred. Please try again.',
            confirmButtonColor: '#dc3545'
        });
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
});

// Reset Password
function resetSystemPassword() {
    Swal.fire({
        title: 'Reset Password?',
        text: 'A new password will be generated and emailed to the staff member.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-sync-alt me-1"></i>Yes, Reset It'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Resetting...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('staff_id', <?php echo $staff['id']; ?>);
            
            fetch('process_system_access.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    if (data.warning && data.credentials) {
                        bootstrap.Modal.getInstance(document.getElementById('systemAccessModal')).hide();
                        document.getElementById('cred_email').textContent = data.credentials.email;
                        document.getElementById('cred_password').textContent = data.credentials.password;
                        new bootstrap.Modal(document.getElementById('credentialsModal')).show();
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Reset!',
                            text: 'New credentials have been emailed.',
                            confirmButtonColor: '#198754'
                        });
                    }
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#dc3545'});
                }
            });
        }
    });
}

// Revoke Access
function revokeSystemAccess() {
    Swal.fire({
        title: 'Revoke System Access?',
        html: 'This will disable login for <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>.<br><small class="text-muted">They will no longer be able to access the ERP.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-ban me-1"></i>Yes, Revoke Access'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Revoking...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            const formData = new FormData();
            formData.append('action', 'revoke_access');
            formData.append('staff_id', <?php echo $staff['id']; ?>);
            
            fetch('process_system_access.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Access Revoked',
                        text: data.message,
                        confirmButtonColor: '#198754'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#dc3545'});
                }
            });
        }
    });
}

// Copy credentials to clipboard
function copyCredentials() {
    const email = document.getElementById('cred_email').textContent;
    const password = document.getElementById('cred_password').textContent;
    const text = `Portal: https://vantageafricaleaders.com/admin/\nEmail: ${email}\nPassword: ${password}`;
    
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Credentials copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

// Close credentials modal and reload
function closeCredentialsAndReload() {
    bootstrap.Modal.getInstance(document.getElementById('credentialsModal')).hide();
    location.reload();
}
</script>

<?php endif; ?>