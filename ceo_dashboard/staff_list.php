<?php
session_start();
require_once 'header.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : 0;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query
$where_conditions = [];

if ($status_filter) {
    $where_conditions[] = "s.onboarding_status = '$status_filter'";
} else {
    // Default view: show the live pipeline (active + still-onboarding); hide
    // rejected and inactive. They remain in the DB and appear when explicitly
    // chosen in the status filter.
    $where_conditions[] = "s.onboarding_status NOT IN ('rejected','inactive')";
}

if ($department_filter) {
    $where_conditions[] = "s.department_id = $department_filter";
}

if ($search) {
    $where_conditions[] = "(s.full_name LIKE '%$search%' OR s.email LIKE '%$search%' OR s.staff_id LIKE '%$search%' OR s.national_id LIKE '%$search%')";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get staff list - simplified query
$staff_list = [];
$query = "
    SELECT 
        s.id,
        s.staff_id,
        s.full_name,
        s.email,
        s.phone,
        s.national_id,
        s.job_title,
        s.passport_photo,
        s.department_id,
        s.employment_type,
        s.onboarding_status,
        s.created_at,
        d.department_name
    FROM staff s
    LEFT JOIN departments d ON s.department_id = d.id
    $where_clause
    ORDER BY s.created_at DESC
    LIMIT 100
";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Department name now comes from the JOIN above (no per-row query)
        $row['department_name'] = $row['department_name'] ?? '';
        // Calculate days
        $row['days_since_submission'] = floor((time() - strtotime($row['created_at'])) / 86400);
        $staff_list[] = $row;
    }
}

// Get departments for filter
$departments = [];
$dept_result = mysqli_query($conn, "SELECT id, department_name FROM departments WHERE status = 1 ORDER BY department_name");
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row;
    }
}

// Get status counts
$status_counts = [];
$count_result = mysqli_query($conn, "SELECT onboarding_status, COUNT(*) as count FROM staff GROUP BY onboarding_status");
if ($count_result) {
    while ($row = mysqli_fetch_assoc($count_result)) {
        $status_counts[$row['onboarding_status']] = $row['count'];
    }
}
$total_staff = array_sum($status_counts);
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-users-cog me-2"></i>Staff Onboarding Management</h4>
                    <p class="text-muted mb-0">Review and approve staff onboarding applications</p>
                </div>
                <a href="../staff_onboarding.php" target="_blank" class="btn btn-primary rounded-0">
                    <i class="fas fa-plus me-2"></i>New Staff Form
                </a>
            </div>
            
            <!-- Status Cards -->
            <div class="row mb-4">
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-0 shadow-sm rounded-0 h-100">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-primary"><?php echo $total_staff; ?></h3>
                            <small class="text-muted">Total Staff</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-0 shadow-sm rounded-0 h-100 border-start border-warning border-4">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-warning"><?php echo $status_counts['pending'] ?? 0; ?></h3>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-0 shadow-sm rounded-0 h-100 border-start border-info border-4">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-info"><?php echo $status_counts['under_review'] ?? 0; ?></h3>
                            <small class="text-muted">Under Review</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-0 shadow-sm rounded-0 h-100 border-start border-success border-4">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-success"><?php echo $status_counts['approved'] ?? 0; ?></h3>
                            <small class="text-muted">Approved</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-0 shadow-sm rounded-0 h-100 border-start border-primary border-4">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-primary"><?php echo $status_counts['active'] ?? 0; ?></h3>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="card border-0 shadow-sm rounded-0 h-100 border-start border-danger border-4">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-danger"><?php echo $status_counts['rejected'] ?? 0; ?></h3>
                            <small class="text-muted">Rejected</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Search</label>
                            <input type="text" class="form-control form-control-sm rounded-0" name="search" 
                                   placeholder="Name, email, ID..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Status</label>
                            <select class="form-select form-select-sm rounded-0" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="under_review" <?php echo $status_filter == 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Department</label>
                            <select class="form-select form-select-sm rounded-0" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-dark btn-sm rounded-0 w-100">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="staff_list.php" class="btn btn-outline-secondary btn-sm rounded-0 w-100">
                                <i class="fas fa-times me-1"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Staff Table -->
            <div class="card shadow-sm rounded-0">
                <div class="card-header bg-dark text-white rounded-0 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>Staff List</span>
                    <span class="badge bg-light text-dark"><?php echo count($staff_list); ?> records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">Photo</th>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staff_list)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        No staff records found
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($staff_list as $staff): ?>
                                <tr>
                                    <td>
                                        <?php if ($staff['passport_photo'] && file_exists('../' . $staff['passport_photo'])): ?>
                                        <img src="../<?php echo $staff['passport_photo']; ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                        <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($staff['staff_id']); ?></strong>
                                        <?php if ($staff['job_title']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($staff['job_title']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                        <br><small class="text-muted">ID: <?php echo htmlspecialchars($staff['national_id']); ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="fas fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($staff['email']); ?><br>
                                            <i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($staff['phone']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($staff['department_name']): ?>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($staff['department_name']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'bg-warning text-dark',
                                            'under_review' => 'bg-info',
                                            'approved' => 'bg-success',
                                            'active' => 'bg-primary',
                                            'rejected' => 'bg-danger',
                                            'inactive' => 'bg-secondary',
                                            'terminated' => 'bg-dark'
                                        ];
                                        $badge_class = $status_badges[$staff['onboarding_status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $staff['onboarding_status'])); ?>
                                        </span>
                                        <?php if ($staff['onboarding_status'] == 'pending' && $staff['days_since_submission'] > 3): ?>
                                        <br><small class="text-danger"><i class="fas fa-clock"></i> <?php echo $staff['days_since_submission']; ?> days</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($staff['created_at'])); ?>
                                            <br><?php echo date('h:i A', strtotime($staff['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="staff_details.php?id=<?php echo $staff['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary rounded-0" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (in_array($staff['onboarding_status'], ['pending', 'under_review'])): ?>
                                        <a href="staff_approve.php?id=<?php echo $staff['id']; ?>" 
                                           class="btn btn-sm btn-success rounded-0" title="Review & Approve">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <?php endif; ?>
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

<?php require_once 'footer.php'; ?>