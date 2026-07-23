<?php
require_once 'header.php';


// Assuming admin info is stored in session


$staff_id = $_SESSION['login_id'] ?? 1; // Replace with your session variable
$admin_name =$_SESSION['login_name'] ?? '';
$admin_email = $_SESSION['user_email'] ?? '';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Service Request Approvals</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="card-body pb-0">
                    <div class="row mb-3">
                        <?php
                        // Count by status without crashing if a query fails (PHP 8 fatals on fetch(false)).
                        $sr_status_count = function ($conn, $status) {
                            $r = mysqli_query($conn, "SELECT COUNT(*) AS count FROM service_requests WHERE status='" . mysqli_real_escape_string($conn, $status) . "'");
                            return $r ? (int) (mysqli_fetch_assoc($r)['count'] ?? 0) : 0;
                        };
                        $pending_count     = $sr_status_count($conn, 'Pending');
                        $in_progress_count = $sr_status_count($conn, 'In Progress');
                        $completed_count   = $sr_status_count($conn, 'Completed');
                        ?>
                        <div class="col-md-3">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_count; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">In Progress</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $in_progress_count; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completed_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select id="statusFilter" class="form-select rounded-0">
                                <option value="">All Status</option>
                                <option value="Pending" selected>Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="typeFilter" class="form-select rounded-0">
                                <option value="">All Types</option>
                                <option value="Commission Payment">Commission Payment</option>
                                <option value="Water Bills">Water Bills</option>
                                <option value="Rent Payment">Rent Payment</option>
                                <option value="Electricity Bills">Electricity Bills</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php 
                    $sql = "SELECT sr.*, 
                            (SELECT COUNT(*) FROM request_comments WHERE request_id = sr.request_id) as comment_count
                            FROM service_requests sr 
                            ORDER BY 
                                CASE 
                                    WHEN sr.status = 'Pending' THEN 1
                                    WHEN sr.status = 'In Progress' THEN 2
                                    WHEN sr.status = 'Completed' THEN 3
                                    WHEN sr.status = 'Rejected' THEN 4
                                END,
                                sr.date_submitted DESC";
                    $result = $conn->query($sql);
                    ?>
                    <div class="table-responsive overflow">
                        <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="nowrap">Request ID</th>
                                    <th class="nowrap">Staff Name</th>
                                    <th class="nowrap">Type</th>
                                    <th class="nowrap">Title</th>
                                    <th class="nowrap">Amount</th>
                                    <th class="nowrap">Priority</th>
                                    <th class="nowrap">Status</th>
                                    <th class="nowrap">Date</th>
                                    <th class="nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $status_class = '';
                                        switch($row['status']) {
                                            case 'Pending': $status_class = 'badge bg-warning text-dark'; break;
                                            case 'In Progress': $status_class = 'badge bg-info'; break;
                                            case 'Completed': $status_class = 'badge bg-success'; break;
                                            case 'Rejected': $status_class = 'badge bg-danger'; break;
                                        }
                                        
                                        $priority_class = '';
                                        switch($row['priority']) {
                                            case 'Low': $priority_class = 'badge bg-secondary'; break;
                                            case 'Medium': $priority_class = 'badge bg-primary'; break;
                                            case 'High': $priority_class = 'badge bg-warning text-dark'; break;
                                            case 'Urgent': $priority_class = 'badge bg-danger'; break;
                                        }
                                        
                                        echo "<tr>";
                                        echo "<td>#" . htmlspecialchars($row['request_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['staff_name']) . "<br><small class='text-muted'>" . htmlspecialchars($row['staff_email']) . "</small></td>";
                                        echo "<td>" . htmlspecialchars($row['request_type']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['request_title']) . "</td>";
                                        echo "<td>" . ($row['amount'] ? 'KES ' . number_format($row['amount'], 2) : '-') . "</td>";
                                        echo "<td><span class='$priority_class'>" . htmlspecialchars($row['priority']) . "</span></td>";
                                        echo "<td><span class='$status_class'>" . htmlspecialchars($row['status']) . "</span></td>";
                                        echo "<td>" . date('d M Y', strtotime($row['date_submitted'])) . "</td>";
                                        echo "<td class='nowrap'>
                                                <button class='btn btn-sm btn-info' onclick='viewDetails(" . $row['request_id'] . ")' title='View Details'>
                                                    <i class='bi bi-eye'></i>
                                                </button>
                                                <button class='btn btn-sm btn-success' onclick='approveRequest(" . $row['request_id'] . ")' title='Approve'>
                                                    <i class='bi bi-check-lg'></i>
                                                </button>
                                                <button class='btn btn-sm btn-danger' onclick='rejectRequest(" . $row['request_id'] . ")' title='Reject'>
                                                    <i class='bi bi-x-lg'></i>
                                                </button>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='9' class='text-center'>No service requests found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2">
                <h5 class="modal-title text-uppercase">Request Details</h5>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal"></i>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg-success text-white rounded-0 p-2">
                <h5 class="modal-title text-uppercase">Approve Request</h5>
                <i class="bi bi-x-lg btn p-0 text-white" data-bs-dismiss="modal"></i>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="approveForm">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" id="approve_request_id">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> A confirmation code will be automatically generated for this request.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="new_status" class="form-control rounded-0" required>
                            <option value="In Progress">Mark as In Progress</option>
                            <option value="Completed">Mark as Completed</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comment / Approval Note</label>
                        <textarea name="comment" class="form-control rounded-0" rows="4" required placeholder="Enter approval comments, instructions, or notes..."></textarea>
                    </div>

                    <div class="w-100 d-flex">
                        <div class="w-50">
                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="submit" class="btn btn-success rounded-0">
                                <i class="bi bi-check-lg"></i> Approve
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg-danger text-white rounded-0 p-2">
                <h5 class="modal-title text-uppercase">Reject Request</h5>
                <i class="bi bi-x-lg btn p-0 text-white" data-bs-dismiss="modal"></i>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" id="reject_request_id">

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Please provide a reason for rejection.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection</label>
                        <textarea name="comment" class="form-control rounded-0" rows="4" required placeholder="Explain why this request is being rejected..."></textarea>
                    </div>

                    <div class="w-100 d-flex">
                        <div class="w-50">
                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="submit" class="btn btn-danger rounded-0">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Handle form submissions
if(isset($_POST['action'])){
    $action = $_POST['action'];
    $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $date_updated = date('Y-m-d H:i:s');
    
    if($action == 'approve'){
        $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
        
        // Generate confirmation code
        $confirmation_code = 'CONF-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        
        // Update request status
        $update_query = "UPDATE service_requests 
                        SET status = '$new_status', 
                            date_updated = '$date_updated',
                            " . ($new_status == 'Completed' ? "completed_date = '$date_updated'," : "") . "
                            description = CONCAT(description, '\n\nConfirmation Code: $confirmation_code')
                        WHERE request_id = '$request_id'";
        
        $result = mysqli_query($conn, $update_query);
        
        if($result){
            // Add admin comment
            $comment_with_code = $comment . "\n\nConfirmation Code: " . $confirmation_code;
            $comment_query = "INSERT INTO request_comments 
                            (request_id, commenter_name, commenter_email, commenter_type, comment_text, date_commented) 
                            VALUES 
                            ('$request_id', '$admin_name', '$admin_email', 'Admin', '$comment_with_code', '$date_updated')";
            mysqli_query($conn, $comment_query);
            
            ?>
            <script>
                window.alert("Request approved successfully!\nConfirmation Code: <?php echo $confirmation_code; ?>");
                window.location.href="approve_requests";
            </script>
            <?php
        }
    } 
    elseif($action == 'reject'){
        // Update request to rejected
        $update_query = "UPDATE service_requests 
                        SET status = 'Rejected', 
                            date_updated = '$date_updated'
                        WHERE request_id = '$request_id'";
        
        $result = mysqli_query($conn, $update_query);
        
        if($result){
            // Add rejection comment
            $comment_query = "INSERT INTO request_comments 
                            (request_id, commenter_name, commenter_email, commenter_type, comment_text, date_commented) 
                            VALUES 
                            ('$request_id', '$admin_name', '$admin_email', 'Admin', 'REJECTED: $comment', '$date_updated')";
            mysqli_query($conn, $comment_query);
            
            ?>
            <script>
                window.alert("Request rejected successfully!");
                window.location.href="approve_requests";
            </script>
            <?php
        }
    }
}

$conn->close();
?>

<script>
// View request details
function viewDetails(requestId) {
    fetch(`get_admin_request_details.php?request_id=${requestId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detailsContent').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            modal.show();
        })
        .catch(error => console.error('Error:', error));
}

// Approve request
function approveRequest(requestId) {
    document.getElementById('approve_request_id').value = requestId;
    const modal = new bootstrap.Modal(document.getElementById('approveModal'));
    modal.show();
}

// Reject request
function rejectRequest(requestId) {
    document.getElementById('reject_request_id').value = requestId;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}

// Filter functionality
document.getElementById('statusFilter').addEventListener('change', filterTable);
document.getElementById('typeFilter').addEventListener('change', filterTable);

function filterTable() {
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value.toLowerCase();
    const table = document.getElementById('dataTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        if(row.cells.length < 2) continue;
        
        const type = row.cells[2].textContent.toLowerCase();
        const status = row.cells[6].textContent.toLowerCase();
        
        const statusMatch = !statusFilter || status.includes(statusFilter);
        const typeMatch = !typeFilter || type.includes(typeFilter);
        
        row.style.display = (statusMatch && typeMatch) ? '' : 'none';
    }
}

// Auto-filter pending on load
window.addEventListener('load', function() {
    filterTable();
});
</script>

<style>
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
</style>

<?php require_once 'footer.php'; ?>