<?php
session_start();
require "auth.php";
require "../../function.php";


if(!isset($_GET['request_id'])) {
    echo "<p class='text-danger'>Invalid request ID</p>";
    exit;
}

$request_id = mysqli_real_escape_string($conn, $_GET['request_id']);
$staff_id = $_SESSION['login_id'] ?? 1;

// Fetch request details
$query = "SELECT * FROM service_requests WHERE request_id = '$request_id' AND staff_id = '$staff_id'";
$result = mysqli_query($conn, $query);

if(!$result || mysqli_num_rows($result) == 0) {
    echo "<p class='text-danger'>Request not found or access denied</p>";
    exit;
}

$request = mysqli_fetch_assoc($result);

// Fetch comments
$comments_query = "SELECT * FROM request_comments WHERE request_id = '$request_id' ORDER BY date_commented ASC";
$comments_result = mysqli_query($conn, $comments_query);

// Status badge classes
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
    <!-- Request Header -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h5 class="mb-0">Request #<?php echo htmlspecialchars($request['request_id']); ?></h5>
            <small class="text-muted">Submitted on <?php echo date('d M Y, H:i', strtotime($request['date_submitted'])); ?></small>
        </div>
        <div class="col-md-6 text-end">
            <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($request['status']); ?></span>
            <span class="<?php echo $priority_class; ?> ms-2"><?php echo htmlspecialchars($request['priority']); ?></span>
        </div>
    </div>

    <hr>

    <!-- Request Details -->
    <div class="row mb-3">
        <div class="col-md-6">
            <strong>Request Type:</strong><br>
            <?php echo htmlspecialchars($request['request_type']); ?>
        </div>
        <div class="col-md-6">
            <strong>Department:</strong><br>
            <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <strong>Title:</strong><br>
            <?php echo htmlspecialchars($request['request_title']); ?>
        </div>
    </div>

    <?php if($request['amount']): ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <strong>Amount:</strong><br>
            KES <?php echo number_format($request['amount'], 2); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-12">
            <strong>Description:</strong><br>
            <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($request['description']); ?></p>
        </div>
    </div>

    <?php if($request['attachment']): ?>
    <div class="row mb-3">
        <div class="col-12">
            <strong>Attachment:</strong><br>
            <a href="uploads/service_requests/<?php echo htmlspecialchars($request['attachment']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download"></i> Download Attachment
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if($request['date_updated']): ?>
    <div class="row mb-3">
        <div class="col-12">
            <small class="text-muted">Last updated: <?php echo date('d M Y, H:i', strtotime($request['date_updated'])); ?></small>
        </div>
    </div>
    <?php endif; ?>

    <hr>

    <!-- Comments Section -->
    <div class="comments-section">
        <h6 class="mb-3">
            <i class="bi bi-chat-dots"></i> Activity Timeline & Comments
        </h6>

        <?php if(mysqli_num_rows($comments_result) > 0): ?>
        <div class="timeline">
            <?php while($comment = mysqli_fetch_assoc($comments_result)): 
                $comment_class = 'border-start border-3 ';
                $icon_class = '';
                
                switch($comment['commenter_type']) {
                    case 'Admin':
                        $comment_class .= 'border-primary';
                        $icon_class = 'bi-person-badge text-primary';
                        break;
                    case 'Staff':
                        $comment_class .= 'border-success';
                        $icon_class = 'bi-person text-success';
                        break;
                    case 'System':
                        $comment_class .= 'border-secondary';
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
        <p class="text-muted">No comments yet.</p>
        <?php endif; ?>
    </div>

    <hr>

    <!-- Add Staff Comment Form -->
    <div class="add-comment-section">
        <h6 class="mb-3">Add a Comment</h6>
        <form action="add_staff_comment.php" method="POST" id="commentForm">
            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
            <div class="mb-3">
                <textarea name="comment_text" class="form-control rounded-0" rows="3" required placeholder="Add your comment or update..."></textarea>
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-primary rounded-0">
                    <i class="bi bi-send"></i> Post Comment
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.timeline {
    position: relative;
}
.comment-item {
    padding-left: 1rem;
    margin-bottom: 1rem;
}
</style>

<script>
// Handle comment form submission
document.getElementById('commentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('add_staff_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            window.alert('Comment added successfully!');
            viewRequest(formData.get('request_id')); // Reload the details
        } else {
            window.alert('Failed to add comment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.alert('An error occurred while adding the comment');
    });
});
</script>

<?php
$conn->close();
?>