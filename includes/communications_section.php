<?php
/**
 * Communications Section Component
 * 
 * Include this in your enquiry_details.php or client profile page
 * to display all emails sent and provide resend functionality.
 * 
 * USAGE:
 * <?php 
 * $source_type = 'register'; // or 'ticket_congress' or 'enquiry'
 * $source_id = $entry_id;    // or $ticket_id or enquiry id
 * $record_id = $id;          // primary key
 * include 'includes/communications_section.php'; 
 * ?>
 */

// Ensure required variables are set
if (!isset($source_type) || !isset($source_id)) {
    echo '<div class="alert alert-danger">Communications section requires $source_type and $source_id</div>';
    return;
}

// Include email log functions if not already included
if (!function_exists('get_email_logs')) {
    require_once __DIR__ . '/email_log_functions.php';
}

// Get email logs for this record
$email_logs = get_email_logs($conn, $source_type, $source_id, null, 100);
$email_summary = get_email_summary($conn, $source_type, $source_id);

// Define which email types can be resent based on source type
$resendable_emails = [
    'register' => ['welcome', 'admission_letter', 'invoice', 'receipt', 'moodle_credentials'],
    'ticket_congress' => ['welcome', 'admission_letter', 'invoice', 'receipt'],
    'enquiry' => ['followup', 'custom']
];

$allowed_resend = $resendable_emails[$source_type] ?? [];

if (!function_exists('get_attachment_public_url')) {
    /**
     * Resolve stored attachment paths to public URLs.
     * Supports receipts saved under admin/receipts and admin/includes/receipts.
     */
    function get_attachment_public_url($attachment_path) {
        $base_url = 'https://vantageafricaleaders.com';
        $path = trim((string) $attachment_path);
        if ($path === '') {
            return null;
        }

        // Already a full URL
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^\./+#', '', $path);

        // Convert absolute server path to web path when possible
        $public_root = '/home2/vantage/public_html/';
        if (strpos($path, $public_root) === 0) {
            $relative = ltrim(substr($path, strlen($public_root)), '/');
            return $base_url . '/' . $relative;
        }

        // Common relative paths from different execution directories
        if (strpos($path, 'admin/') === 0) {
            return $base_url . '/' . ltrim($path, '/');
        }

        if (strpos($path, 'includes/') === 0) {
            return $base_url . '/admin/' . ltrim($path, '/');
        }

        if (strpos($path, 'receipts/') === 0) {
            $admin_dir = dirname(__DIR__); // /admin
            $in_admin = $admin_dir . '/' . $path;
            $in_includes = __DIR__ . '/' . $path;

            // Prefer actual existing location to avoid broken links
            if (is_file($in_admin)) {
                return $base_url . '/admin/' . $path;
            }
            if (is_file($in_includes)) {
                return $base_url . '/admin/includes/' . $path;
            }

            // Fallback for environments where files are not present locally
            return $base_url . '/admin/' . $path;
        }

        return $base_url . '/' . ltrim($path, '/');
    }
}
?>

<!-- Communications Section -->
<div class="card mb-4">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-envelope me-2"></i>Communications History
            <span class="badge bg-light text-dark ms-2"><?php echo $email_summary['total']; ?> emails</span>
        </h6>
        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#emailLogsList">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    
    <div class="card-body">
        <!-- Email Type Summary Cards -->
        <?php if (!empty($email_summary['by_type'])): ?>
        <div class="row g-2 mb-4">
            <?php foreach ($email_summary['by_type'] as $type => $info): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card border-<?php echo $info['color']; ?> h-100">
                    <div class="card-body p-2 text-center">
                        <i class="bi <?php echo $info['icon']; ?> text-<?php echo $info['color']; ?>" style="font-size: 1.5rem;"></i>
                        <div class="small text-muted"><?php echo $info['label']; ?></div>
                        <div class="fw-bold"><?php echo $info['count']; ?>x</div>
                        <?php if ($info['last_sent']): ?>
                        <div class="small text-muted"><?php echo date('d M Y', strtotime($info['last_sent'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Quick Resend Buttons -->
        <div class="mb-4">
            <h6 class="text-muted mb-2"><i class="bi bi-send me-1"></i>Quick Resend</h6>
            <div class="btn-group flex-wrap" role="group">
                <?php 
                $email_type_info = [
                    'welcome' => ['label' => 'Welcome Email', 'icon' => 'bi-envelope-heart', 'color' => 'success'],
                    'admission_letter' => ['label' => 'Admission Letter', 'icon' => 'bi-file-earmark-text', 'color' => 'primary'],
                    'invoice' => ['label' => 'Invoice', 'icon' => 'bi-receipt', 'color' => 'warning'],
                    'receipt' => ['label' => 'Receipt', 'icon' => 'bi-credit-card-2-front', 'color' => 'success'],
                    'moodle_credentials' => ['label' => 'Moodle Login', 'icon' => 'bi-key', 'color' => 'info'],
                ];
                
                foreach ($allowed_resend as $email_type):
                    $info = $email_type_info[$email_type] ?? ['label' => ucfirst($email_type), 'icon' => 'bi-envelope', 'color' => 'secondary'];
                    $was_sent = was_email_sent($conn, $source_type, $source_id, $email_type);
                ?>
                <button type="button" 
                        class="btn btn-outline-<?php echo $info['color']; ?> btn-sm m-1 resend-btn"
                        data-email-type="<?php echo $email_type; ?>"
                        data-source-type="<?php echo $source_type; ?>"
                        data-source-id="<?php echo $source_id; ?>"
                        data-record-id="<?php echo $record_id ?? ''; ?>"
                        <?php echo !$was_sent ? 'disabled title="Never sent before"' : ''; ?>>
                    <i class="bi <?php echo $info['icon']; ?> me-1"></i>
                    <?php echo $info['label']; ?>
                    <?php if ($was_sent): ?>
                    <i class="bi bi-arrow-repeat ms-1"></i>
                    <?php else: ?>
                    <i class="bi bi-x-circle ms-1"></i>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Email Logs Table -->
        <div class="collapse show" id="emailLogsList">
            <?php if (empty($email_logs)): ?>
            <div class="alert alert-light text-center">
                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                <p class="mb-0 mt-2">No emails have been sent yet.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th width="15%">Type</th>
                            <th width="25%">Subject</th>
                            <th width="20%">Recipient</th>
                            <th width="10%">Status</th>
                            <th width="15%">Date</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($email_logs as $log): 
                            $type_display = get_email_type_display($log['email_type']);
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $type_display['color']; ?>">
                                    <i class="bi <?php echo $type_display['icon']; ?> me-1"></i>
                                    <?php echo $type_display['label']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($log['subject'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($log['subject'] ?? 'No subject'); ?>
                                </span>
                                <?php if ($log['has_attachments']): ?>
                                <i class="bi bi-paperclip text-muted" title="Has attachments"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php echo htmlspecialchars($log['recipient_email']); ?>
                                    <?php if ($log['recipient_name']): ?>
                                    <br><span class="text-muted"><?php echo htmlspecialchars($log['recipient_name']); ?></span>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($log['status'] == 'sent'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Sent</span>
                                <?php elseif ($log['status'] == 'failed'): ?>
                                <span class="badge bg-danger" title="<?php echo htmlspecialchars($log['error_message'] ?? ''); ?>">
                                    <i class="bi bi-x-circle me-1"></i>Failed
                                </span>
                                <?php elseif ($log['status'] == 'pending'): ?>
                                <span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>
                                <?php else: ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($log['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php echo date('d M Y', strtotime($log['created_at'])); ?>
                                    <br>
                                    <span class="text-muted"><?php echo date('H:i', strtotime($log['created_at'])); ?></span>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($log['has_attachments'] && $log['attachment_paths']): ?>
                                    <?php 
                                    $attachments = is_array($log['attachment_paths']) ? $log['attachment_paths'] : json_decode($log['attachment_paths'], true);
                                    if (!empty($attachments)):
                                        $first_attachment = $attachments[0];
                                    ?>
                                    <?php $attachment_url = get_attachment_public_url($first_attachment); ?>
                                    <?php if (!empty($attachment_url)): ?>
                                    <?php if (($log['email_type'] ?? '') === 'invoice'): ?>
                                    <a href="<?php echo htmlspecialchars($attachment_url); ?>" 
                                       class="btn btn-outline-primary" 
                                       target="_blank" 
                                       title="Preview Invoice">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($attachment_url); ?>" 
                                       class="btn btn-outline-secondary" 
                                       target="_blank" 
                                       title="View Attachment">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($log['email_type'], $allowed_resend)): ?>
                                    <button type="button" 
                                            class="btn btn-outline-primary resend-btn"
                                            data-email-type="<?php echo $log['email_type']; ?>"
                                            data-source-type="<?php echo $source_type; ?>"
                                            data-source-id="<?php echo $source_id; ?>"
                                            data-record-id="<?php echo $record_id ?? ''; ?>"
                                            title="Resend">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Resend Confirmation Modal -->
<div class="modal fade" id="resendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope-arrow-up me-2"></i>Resend Email
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to resend the <strong id="resendEmailType"></strong>?</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    The email will be sent to: <strong id="resendRecipient"><?php echo htmlspecialchars($client_email ?? $email ?? ''); ?></strong>
                </div>
                <input type="hidden" id="resendEmailTypeValue">
                <input type="hidden" id="resendSourceType">
                <input type="hidden" id="resendSourceId">
                <input type="hidden" id="resendRecordId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmResendBtn">
                    <i class="bi bi-send me-1"></i>Send Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Resend Result Modal -->
<div class="modal fade" id="resendResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="resendResultHeader">
                <h5 class="modal-title" id="resendResultTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="resendResultBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resendModal = new bootstrap.Modal(document.getElementById('resendModal'));
    const resendResultModal = new bootstrap.Modal(document.getElementById('resendResultModal'));
    
    const emailTypeLabels = {
        'welcome': 'Welcome Email',
        'admission_letter': 'Admission Letter',
        'invoice': 'Invoice',
        'receipt': 'Payment Receipt',
        'moodle_credentials': 'Moodle Credentials',
        'reminder': 'Reminder',
        'followup': 'Follow-up Email'
    };
    
    // Handle resend button clicks
    document.querySelectorAll('.resend-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const emailType = this.dataset.emailType;
            const sourceType = this.dataset.sourceType;
            const sourceId = this.dataset.sourceId;
            const recordId = this.dataset.recordId;
            
            document.getElementById('resendEmailType').textContent = emailTypeLabels[emailType] || emailType;
            document.getElementById('resendEmailTypeValue').value = emailType;
            document.getElementById('resendSourceType').value = sourceType;
            document.getElementById('resendSourceId').value = sourceId;
            document.getElementById('resendRecordId').value = recordId;
            
            resendModal.show();
        });
    });
    
    // Handle confirm resend
    document.getElementById('confirmResendBtn').addEventListener('click', function() {
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
        btn.disabled = true;
        
        const emailType = document.getElementById('resendEmailTypeValue').value;
        const sourceType = document.getElementById('resendSourceType').value;
        const sourceId = document.getElementById('resendSourceId').value;
        const recordId = document.getElementById('resendRecordId').value;
        
        // Send AJAX request
        const formData = new FormData();
        formData.append('action', 'resend_email');
        formData.append('email_type', emailType);
        formData.append('source_type', sourceType);
        formData.append('source_id', sourceId);
        formData.append('record_id', recordId);
        
        fetch('includes/process_resend_email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resendModal.hide();
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            // Show result
            const resultHeader = document.getElementById('resendResultHeader');
            const resultTitle = document.getElementById('resendResultTitle');
            const resultBody = document.getElementById('resendResultBody');
            
            if (data.success) {
                resultHeader.className = 'modal-header bg-success text-white';
                resultTitle.innerHTML = '<i class="bi bi-check-circle me-2"></i>Email Sent Successfully';
                resultBody.innerHTML = `
                    <div class="text-center">
                        <i class="bi bi-envelope-check text-success" style="font-size: 3rem;"></i>
                        <p class="mt-3">${emailTypeLabels[emailType] || emailType} has been resent successfully.</p>
                    </div>
                `;
            } else {
                resultHeader.className = 'modal-header bg-danger text-white';
                resultTitle.innerHTML = '<i class="bi bi-x-circle me-2"></i>Failed to Send';
                resultBody.innerHTML = `
                    <div class="text-center">
                        <i class="bi bi-envelope-x text-danger" style="font-size: 3rem;"></i>
                        <p class="mt-3">Failed to send email.</p>
                        <p class="text-muted">${data.error || 'Unknown error occurred'}</p>
                    </div>
                `;
            }
            
            resendResultModal.show();
            
            // Reload page after success to update the logs
            if (data.success) {
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        })
        .catch(error => {
            resendModal.hide();
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            alert('Error: ' + error.message);
        });
    });
});
</script>