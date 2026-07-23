<?php
require_once 'header.php';
require "../../function.php";

// Assuming staff info is stored in session

$staff_id = $_SESSION['login_id'] ?? 1; // Replace with your session variable
$staff_name =$_SESSION['login_name'] ?? '';
$staff_email = $_SESSION['user_email'] ?? '';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- Service Requests Card -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">My Service Requests</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addRequestModal">
                                <i class="bi bi-plus-lg"></i> New Request
                            </button>
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Options -->
                <div class="card-body pb-0">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select id="statusFilter" class="form-select rounded-0">
                                <option value="">All Status</option>
                                <option value="Pending">Pending</option>
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
                                <option value="Office Supplies">Office Supplies</option>
                                <option value="IT Support">IT Support</option>
                                <option value="Maintenance Request">Maintenance Request</option>
                                <option value="Travel Request">Travel Request</option>
                                <option value="Advance Payment">Advance Payment</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php 
                    $sql = "SELECT * FROM service_requests WHERE staff_id = '$staff_id' ORDER BY date_submitted DESC";
                    $result = $conn->query($sql);
                    ?>
                    <div class="table-responsive overflow">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="nowrap">Request ID</th>
                                    <th class="nowrap">Type</th>
                                    <th class="nowrap">Title</th>
                                    <th class="nowrap">Amount</th>
                                    <th class="nowrap">Priority</th>
                                    <th class="nowrap">Status</th>
                                    <th class="nowrap">Date Submitted</th>
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
                                        echo "<td>" . htmlspecialchars($row['request_type']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['request_title']) . "</td>";
                                        echo "<td>" . ($row['amount'] ? 'KES ' . number_format($row['amount'], 2) : '-') . "</td>";
                                        echo "<td><span class='$priority_class'>" . htmlspecialchars($row['priority']) . "</span></td>";
                                        echo "<td><span class='$status_class'>" . htmlspecialchars($row['status']) . "</span></td>";
                                        echo "<td>" . date('d M Y H:i', strtotime($row['date_submitted'])) . "</td>";
                                        echo "<td>
                                                <button class='btn btn-sm btn-primary' onclick='viewRequest(" . $row['request_id'] . ")'>
                                                    <i class='bi bi-eye'></i> View
                                                </button>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='text-center'>No service requests found</td></tr>";
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

<!-- Add Request Modal -->
<div class="modal fade" id="addRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="addRequestModalLabel">
                    New Service Request
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_request">
                    
                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 10rem;">Request Type</span>
                        <select name="request_type" id="request_type" class="form-control rounded-0" required onchange="toggleAmountField()">
                            <option value="">Select Type</option>
                            <option value="Commission Payment" data-amount="true">Commission Payment</option>
                            <option value="Water Bills" data-amount="true">Water Bills</option>
                            <option value="Rent Payment" data-amount="true">Rent Payment</option>
                            <option value="Electricity Bills" data-amount="true">Electricity Bills</option>
                            <option value="Office Supplies" data-amount="true">Office Supplies</option>
                            <option value="IT Support" data-amount="false">IT Support</option>
                            <option value="Maintenance Request" data-amount="false">Maintenance Request</option>
                            <option value="Travel Request" data-amount="true">Travel Request</option>
                            <option value="Advance Payment" data-amount="true">Advance Payment</option>
                            <option value="Other" data-amount="false">Other</option>
                        </select>
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 10rem;">Request Title</span>
                        <input type="text" name="request_title" required class="form-control rounded-0" placeholder="Brief title for your request">
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 10rem;">Priority</span>
                        <select name="priority" class="form-control rounded-0" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="input-group mb-3" id="amountFieldd" style="display: blcok;">
                        <span class="input-group-text rounded-0 bg_main" style="width: 10rem;">Amount (KES)</span>
                        <input type="number" name="amount" step="0.01" class="form-control rounded-0" placeholder="0.00">
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 10rem;">Department</span>
                        <input type="text" name="department" class="form-control rounded-0" placeholder="Your department">
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text rounded-0 bg_main" style="width: 10rem;">Phone Number</span>
                        <input type="tel" name="staff_phone" required class="form-control rounded-0" placeholder="Your phone number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control rounded-0" rows="4" required placeholder="Provide detailed information about your request"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attachment (Optional)</label>
                        <input type="file" name="attachment" class="form-control rounded-0" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">Supported formats: PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                    </div>

                    <div class="w-100 d-flex">
                        <div class="w-50">
                            <button type="button" class="btn btn-danger rounded-0" data-bs-dismiss="modal" aria-label="Close">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <input type="submit" class="btn btn-success rounded-0" value="Submit Request">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Request Details Modal -->
<div class="modal fade" id="viewRequestModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="viewRequestModalLabel">
                    Request Details
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<?php
// Handle form submission
if(isset($_POST['action']) && $_POST['action'] == 'add_request'){
    $request_type = mysqli_real_escape_string($conn, $_POST['request_type']);
    $request_title = mysqli_real_escape_string($conn, $_POST['request_title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $staff_phone = mysqli_real_escape_string($conn, $_POST['staff_phone']);
    $amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? mysqli_real_escape_string($conn, $_POST['amount']) : NULL;
    $date_submitted = date('Y-m-d H:i:s');
    
    // Handle file upload
    $attachment = NULL;
    if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload_dir = 'uploads/service_requests/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $attachment = 'request_' . time() . '_' . uniqid() . '.' . $file_extension;
        move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment);
    }
    
    $insert_query = "INSERT INTO service_requests 
                    (staff_id, staff_name, staff_email, staff_phone, department, request_type, request_title, description, amount, priority, status, attachment, date_submitted) 
                    VALUES 
                    ('$staff_id', '$staff_name', '$staff_email', '$staff_phone', '$department', '$request_type', '$request_title', '$description', " . ($amount !== NULL ? "'$amount'" : "NULL") . ", '$priority', 'Pending', " . ($attachment !== NULL ? "'$attachment'" : "NULL") . ", '$date_submitted')";
    
    $stmt = mysqli_query($conn, $insert_query);
    
    if ($stmt) {
        $request_id = mysqli_insert_id($conn);
        
        // Add initial comment
        $comment_query = "INSERT INTO request_comments (request_id, commenter_name, commenter_email, commenter_type, comment_text, date_commented) 
                         VALUES ('$request_id', '$staff_name', '$staff_email', 'System', 'Request submitted and awaiting review.', '$date_submitted')";
        mysqli_query($conn, $comment_query);
        
        ?>
        <script>
            window.alert("Service request submitted successfully!");
            window.location.href="service_requests";
        </script>
        <?php
    } else {
        ?>
        <script>
            window.alert("Failed to submit request. Please try again.");
            window.location.href="service_requests";
        </script>
        <?php
    }
}

$conn->close();
?>

<script>
// Toggle amount field based on request type
function toggleAmountField() {
    const requestType = document.getElementById('request_type');
    const amountField = document.getElementById('amountField');
    const selectedOption = requestType.options[requestType.selectedIndex];
    const requiresAmount = selectedOption.getAttribute('data-amount') === 'true';
    
    if (requiresAmount) {
        amountField.style.display = 'flex';
        amountField.querySelector('input').required = true;
    } else {
        amountField.style.display = 'none';
        amountField.querySelector('input').required = false;
    }
}

// View request details
function viewRequest(requestId) {
    fetch(`get_request_details.php?request_id=${requestId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('requestDetailsContent').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
            modal.show();
        })
        .catch(error => console.error('Error:', error));
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
        const type = row.cells[1].textContent.toLowerCase();
        const status = row.cells[5].textContent.toLowerCase();
        
        const statusMatch = !statusFilter || status.includes(statusFilter);
        const typeMatch = !typeFilter || type.includes(typeFilter);
        
        row.style.display = (statusMatch && typeMatch) ? '' : 'none';
    }
}
</script>

<?php require_once 'footer.php'; ?>