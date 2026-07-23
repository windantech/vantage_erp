<?php
session_start();
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <?php
        $schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$schedule_id) {
            echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger rounded-0">Invalid schedule ID</div></div>';
            require_once 'footer.php';
            exit;
        }
        
        // Fetch schedule details
        $schedule = null;
        $result = mysqli_query($conn, "
            SELECT es.*, ru.fullname as created_by_name, se.subject as email_subject, se.body as email_body
            FROM email_schedules es 
            LEFT JOIN registered_users ru ON es.created_by = ru.id 
            LEFT JOIN system_emails1 se ON es.email_template_id = se.id
            WHERE es.id = $schedule_id
        ");
        if ($result && mysqli_num_rows($result) > 0) {
            $schedule = mysqli_fetch_assoc($result);
        }
        
        if (!$schedule) {
            echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger rounded-0">Schedule not found</div></div>';
            require_once 'footer.php';
            exit;
        }
        
        // Fetch send logs
        $logs = [];
        $logs_result = mysqli_query($conn, "
            SELECT * FROM email_schedule_logs 
            WHERE schedule_id = $schedule_id 
            ORDER BY sent_at DESC
        ");
        if ($logs_result) {
            while ($row = mysqli_fetch_assoc($logs_result)) {
                $logs[] = $row;
            }
        }
        
        $status_badges = [
            'pending' => '<span class="badge bg-warning text-dark fs-6"><i class="fas fa-clock me-1"></i> Pending</span>',
            'processing' => '<span class="badge bg-info fs-6"><i class="fas fa-spinner fa-spin me-1"></i> Processing</span>',
            'completed' => '<span class="badge bg-success fs-6"><i class="fas fa-check me-1"></i> Completed</span>',
            'failed' => '<span class="badge bg-danger fs-6"><i class="fas fa-times me-1"></i> Failed</span>',
            'cancelled' => '<span class="badge bg-secondary fs-6"><i class="fas fa-ban me-1"></i> Cancelled</span>'
        ];
        ?>
        
        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="email_schedules.php" class="btn btn-outline-secondary btn-sm rounded-0 mb-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Schedules
                    </a>
                    <h4 class="mb-0">
                        <i class="fas fa-calendar-check me-2"></i>Schedule #<?php echo $schedule_id; ?> Details
                    </h4>
                </div>
                <div>
                    <?php echo $status_badges[$schedule['status']] ?? $schedule['status']; ?>
                </div>
            </div>
            
            <div class="row">
                <!-- Schedule Info -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm rounded-0">
                        <div class="card-header bg_main rounded-0 py-3">
                            <h6 class="mb-0 text-white"><i class="fas fa-info-circle me-2"></i>Schedule Information</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td class="text-muted" width="40%">Type:</td>
                                    <td>
                                        <?php if ($schedule['email_type'] == 'virtual'): ?>
                                        <span class="badge bg-primary">Virtual Course</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">International Event</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Target:</td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($schedule['target_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email:</td>
                                    <td><span class="badge bg-secondary">Email <?php echo $schedule['email_number']; ?></span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Subject:</td>
                                    <td><?php echo htmlspecialchars($schedule['email_subject'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Filter:</td>
                                    <td>
                                        <?php 
                                        $filter_labels = [
                                            'all' => '<span class="badge bg-info">All Registrations</span>',
                                            'paid' => '<span class="badge bg-success">Paid Only</span>',
                                            'unpaid' => '<span class="badge bg-warning text-dark">Unpaid Only</span>'
                                        ];
                                        echo $filter_labels[$schedule['payment_filter']] ?? $schedule['payment_filter'];
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Scheduled:</td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($schedule['scheduled_date'])); ?>
                                        at <?php echo date('h:i A', strtotime($schedule['scheduled_time'])); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Created By:</td>
                                    <td><?php echo htmlspecialchars($schedule['created_by_name'] ?? 'System'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Created:</td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($schedule['created_at'])); ?></td>
                                </tr>
                                <?php if ($schedule['processed_at']): ?>
                                <tr>
                                    <td class="text-muted">Processed:</td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($schedule['processed_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($schedule['completed_at']): ?>
                                <tr>
                                    <td class="text-muted">Completed:</td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($schedule['completed_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="card shadow-sm rounded-0 mt-4">
                        <div class="card-header bg-dark text-white rounded-0">
                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h3 class="text-primary mb-0"><?php echo $schedule['total_recipients']; ?></h3>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-success mb-0"><?php echo $schedule['sent_count']; ?></h3>
                                    <small class="text-muted">Sent</small>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-danger mb-0"><?php echo $schedule['failed_count']; ?></h3>
                                    <small class="text-muted">Failed</small>
                                </div>
                            </div>
                            
                            <?php if ($schedule['total_recipients'] > 0): ?>
                            <div class="progress mt-3 rounded-0" style="height: 10px;">
                                <?php 
                                $sent_percent = ($schedule['sent_count'] / $schedule['total_recipients']) * 100;
                                $failed_percent = ($schedule['failed_count'] / $schedule['total_recipients']) * 100;
                                ?>
                                <div class="progress-bar bg-success" style="width: <?php echo $sent_percent; ?>%"></div>
                                <div class="progress-bar bg-danger" style="width: <?php echo $failed_percent; ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Send Logs -->
                <div class="col-lg-8">
                    <div class="card shadow-sm rounded-0">
                        <div class="card-header bg-secondary text-white rounded-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-list-alt me-2"></i>Send Logs</h6>
                            <span class="badge bg-light text-dark"><?php echo count($logs); ?> records</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>#</th>
                                            <th>Recipient</th>
                                            <th>Source</th>
                                            <th>Status</th>
                                            <th>Sent At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <?php if ($schedule['status'] == 'pending'): ?>
                                                <i class="fas fa-clock fa-2x mb-2 d-block"></i>
                                                Waiting to be processed...
                                                <?php else: ?>
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                No send logs yet
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($logs as $index => $log): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($log['recipient_name'] ?? 'N/A'); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['recipient_email']); ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $log['source_table']; ?><br>
                                                    <code><?php echo $log['source_id']; ?></code>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($log['status'] == 'sent'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check"></i> Sent</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times"></i> Failed</span>
                                                <?php if ($log['error_message']): ?>
                                                <br><small class="text-danger"><?php echo htmlspecialchars(substr($log['error_message'], 0, 50)); ?></small>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, h:i A', strtotime($log['sent_at'])); ?>
                                                </small>
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
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>