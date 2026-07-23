<?php
session_start();

require_once '../../database/conn.php'; 
if(!isset($_GET['request_id'])) {
    echo "<p class='text-danger'>Invalid request ID</p>";
    exit;
}

$request_id = mysqli_real_escape_string($conn, $_GET['request_id']);

// Fetch request details
$query = "SELECT * FROM service_requests WHERE request_id = '$request_id'";
$result = mysqli_query($conn, $query);

if(!$result || mysqli_num_rows($result) == 0) {
    echo "<p class='text-danger'>Request not found</p>";
    exit;
}

$request = mysqli_fetch_assoc($result);

// Fetch comments
$comments_query = "SELECT * FROM request_comments WHERE request_id = '$request_id' ORDER BY date_commented ASC";
$comments_result = mysqli_query($conn, $comments_query);

// Status badge
$status_class = '';
switch($request['status']) {
    case 'Pending': $status_class = 'badge bg-warning text-dark'; break;
    case 'In Progress': $status_class = 'badge bg-info'; break;
    case 'Completed': $status_class = 'badge bg-success'; break;
    case 'Rejected': $status_class = 'badge bg-danger'; break;
}

$priority_class = '';
switch($request['priority']) {
    case 'Low': $priority_class = 'badge bg-secondary'; break;
    case 'Medium': $priority_class = 'badge bg-primary'; break;
    case 'High': $priority_class = 'badge bg-warning text-dark'; break;
    case 'Urgent': $priority_class = 'badge bg-danger'; break;
}
?>

<div class="request-details">
    <!-- Header -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h5 class="mb-0">Request #<?php echo htmlspecialchars($request['request_id']); ?></h5>
            <small class="text-muted">Submitted: <?php echo date('d M Y, H:i', strtotime($request['date_submitted'])); ?></small>
        </div>
        <div class="col-md-6 text-end">
            <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($request['status']); ?></span>
            <span class="<?php echo $priority_class; ?> ms-2"><?php echo htmlspecialchars($request['priority']); ?></span>
        </div>
    </div>

    <hr>

    <!-- Staff Information -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <strong><i class="bi bi-person-badge"></i> Staff Information</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>Name:</strong> <?php echo htmlspecialchars($request['staff_name']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($request['staff_email']); ?>
                </div>
                <div class="col-md-6">
                    <strong>Phone:</strong> <?php echo htmlspecialchars($request['staff_phone'] ?? 'N/A'); ?><br>
                    <strong>Department:</strong> <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <strong><i class="bi bi-file-text"></i> Request Details</strong>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-md-6">
                    <strong>Request Type:</strong><br>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($request['request_type']); ?></span>
                </div>
                <?php if($request['amount']): ?>
                <div class="col-md-6">
                    <strong>Amount:</strong><br>
                    <span class="text-success fw-bold">KES <?php echo number_format($request['amount'], 2); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="row mb-2">
                <div class="col-12">
                    <strong>Title:</strong><br>
                    <?php echo htmlspecialchars($request['request_title']); ?>
                </div>
            </div>

            <div class="row mb-2">
                <div class="col-12">
                    <strong>Description:</strong><br>
                    <div class="p-2 bg-light rounded" style="white-space: pre-wrap;"><?php echo htmlspecialchars($request['description']); ?></div>
                </div>
            </div>

            <?php if($request['attachment']): ?>
            <div class="row">
                <div class="col-12">
                    <strong>Attachment:</strong><br>
                    <a href="uploads/service_requests/<?php echo htmlspecialchars($request['attachment']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download"></i> Download Attachment
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Timeline -->
    <?php if($request['date_updated'] || $request['completed_date']): ?>
    <div class="card mb-3">
        <div class="card-header bg-light">
            <strong><i class="bi bi-clock-history"></i> Timeline</strong>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                <li><i class="bi bi-circle-fill text-primary" style="font-size: 8px;"></i> Submitted: <?php echo date('d M Y, H:i', strtotime($request['date_submitted'])); ?></li>
                <?php if($request['date_updated']): ?>
                <li><i class="bi bi-circle-fill text-info" style="font-size: 8px;"></i> Last Updated: <?php echo date('d M Y, H:i', strtotime($request['date_updated'])); ?></li>
                <?php endif; ?>
                <?php if($request['completed_date']): ?>
                <li><i class="bi bi-circle-fill text-success" style="font-size: 8px;"></i> Completed: <?php echo date('d M Y, H:i', strtotime($request['completed_date'])); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Comments & Activity -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <strong><i class="bi bi-chat-dots"></i> Comments & Activity</strong>
        </div>
        <div class="card-body">
            <?php if(mysqli_num_rows($comments_result) > 0): ?>
            <div class="timeline">
                <?php while($comment = mysqli_fetch_assoc($comments_result)): 
                    $comment_class = '';
                    $icon_class = '';
                    
                    switch($comment['commenter_type']) {
                        case 'Admin':
                            $comment_class = 'border-start border-3 border-primary';
                            $icon_class = 'bi-shield-check text-primary';
                            break;
                        case 'Staff':
                            $comment_class = 'border-start border-3 border-success';
                            $icon_class = 'bi-person text-success';
                            break;
                        case 'System':
                            $comment_class = 'border-start border-3 border-secondary';
                            $icon_class = 'bi-info-circle text-secondary';
                            break;
                    }
                ?>
                <div class="comment-item mb-3 ps-3 <?php echo $comment_class; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong>
                            <i class="bi <?php echo $icon_class; ?>"></i>
                            <?php echo htmlspecialchars($comment['commenter_name']); ?>
                            <span class="badge bg-light text-dark ms-1"><?php echo htmlspecialchars($comment['commenter_type']); ?></span>
                        </strong>
                        <small class="text-muted"><?php echo date('d M Y, H:i', strtotime($comment['date_commented'])); ?></small>
                    </div>
                    <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0">No comments yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="d-flex justify-content-end gap-2">
        <button class="btn btn-success rounded-0" onclick="approveRequest(<?php echo $request['request_id']; ?>); bootstrap.Modal.getInstance(document.getElementById('viewDetailsModal')).hide();">
            <i class="bi bi-check-lg"></i> Approve
        </button>
        <button class="btn btn-danger rounded-0" onclick="rejectRequest(<?php echo $request['request_id']; ?>); bootstrap.Modal.getInstance(document.getElementById('viewDetailsModal')).hide();">
            <i class="bi bi-x-lg"></i> Reject
        </button>
        <button class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
            <i class="bi bi-arrow-left"></i> Close
        </button>
    </div>
</div>

<style>
.timeline {
    position: relative;
}
.comment-item {
    padding-left: 1rem;
}
</style>

<?php
$conn->close();
?>