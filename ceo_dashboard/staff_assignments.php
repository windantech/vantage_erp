<?php
/**
 * Staff Assignments - Assign courses and events to staff members
 * Performance Management Module
 */

session_start();
require_once 'header.php';
require_once '../function.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

// Get selected staff (if any)
$selected_staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$selected_staff = null;
$staff_courses = [];
$staff_events = [];

// Get all staff with system access
$staff_query = mysqli_query($conn, "
    SELECT s.id, s.staff_id, s.full_name, s.job_title, s.corporate_email, s.system_user_id,
           d.department_name
    FROM staff s
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE s.system_access_granted = 1 AND s.system_user_id IS NOT NULL
    ORDER BY s.full_name ASC
");

$staff_list = [];
while ($row = mysqli_fetch_assoc($staff_query)) {
    $staff_list[] = $row;
    if ($selected_staff_id > 0 && $row['id'] == $selected_staff_id) {
        $selected_staff = $row;
    }
}

// If staff is selected, get their current assignments
if ($selected_staff && $selected_staff['system_user_id']) {
    $user_id = $selected_staff['system_user_id'];
    
    // Get all courses
    $courses_query = mysqli_query($conn, "SELECT course_id, course, status, assigned_to FROM course ORDER BY course ASC");
    while ($course = mysqli_fetch_assoc($courses_query)) {
        $assigned_ids = !empty($course['assigned_to']) ? explode(',', $course['assigned_to']) : [];
        $course['is_assigned'] = in_array($user_id, $assigned_ids);
        $staff_courses[] = $course;
    }
    
    // Get all events
    $events_query = mysqli_query($conn, "SELECT event_id, event_title, location, start_on, end_on, status, assigned_to FROM Event ORDER BY start_on DESC");
    while ($event = mysqli_fetch_assoc($events_query)) {
        $assigned_ids = !empty($event['assigned_to']) ? explode(',', $event['assigned_to']) : [];
        $event['is_assigned'] = in_array($user_id, $assigned_ids);
        $staff_events[] = $event;
    }
}
?>

<style>
.staff-card { transition: all 0.3s; cursor: pointer; }
.staff-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
.staff-card.selected { border: 2px solid #0d6efd; background: #f0f7ff; }
.assignment-table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; }
.assignment-table td { vertical-align: middle; }
.form-check-input:checked { background-color: #198754; border-color: #198754; }
.badge-active { background: #198754; }
.badge-inactive { background: #6c757d; }
.tab-content { min-height: 400px; }
.stats-badge { font-size: 0.75rem; padding: 0.35em 0.65em; }
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-tasks me-2"></i>Staff Assignments</h4>
                    <p class="text-muted mb-0">Assign courses and events to staff members</p>
                </div>
                <div>
                    <span class="badge bg-primary me-2"><?php echo count($staff_list); ?> Staff with Access</span>
                </div>
            </div>

            <div class="row">
                <!-- Staff List (Left Panel) -->
                <div class="col-md-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2"></i>Select Staff Member</h6>
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            <?php if (empty($staff_list)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No staff with system access found</p>
                                    <a href="staff_list.php" class="btn btn-sm btn-primary">Manage Staff</a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($staff_list as $staff): ?>
                                        <a href="?staff_id=<?php echo $staff['id']; ?>" 
                                           class="list-group-item list-group-item-action <?php echo ($selected_staff_id == $staff['id']) ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                                    <br>
                                                    <small class="<?php echo ($selected_staff_id == $staff['id']) ? 'text-white-50' : 'text-muted'; ?>">
                                                        <?php echo htmlspecialchars($staff['job_title'] ?? 'No title'); ?>
                                                        <?php if ($staff['department_name']): ?>
                                                            • <?php echo htmlspecialchars($staff['department_name']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <i class="fas fa-chevron-right"></i>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Assignments Panel (Right) -->
                <div class="col-md-8">
                    <?php if (!$selected_staff): ?>
                        <!-- No Staff Selected -->
                        <div class="card shadow-sm border-0">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-hand-pointer fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Select a Staff Member</h5>
                                <p class="text-muted mb-0">Choose a staff member from the left panel to manage their course and event assignments</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Staff Selected - Show Assignments -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($selected_staff['full_name']); ?></h5>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($selected_staff['corporate_email']); ?>
                                            • User ID: <?php echo $selected_staff['system_user_id']; ?>
                                        </small>
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="saveAssignments()">
                                        <i class="fas fa-save me-1"></i>Save Assignments
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Tabs -->
                                <ul class="nav nav-tabs" id="assignmentTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button">
                                            <i class="fas fa-laptop me-1"></i>Virtual Courses
                                            <span class="badge stats-badge bg-primary ms-1" id="coursesCount">
                                                <?php echo count(array_filter($staff_courses, fn($c) => $c['is_assigned'])); ?>
                                            </span>
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button">
                                            <i class="fas fa-globe me-1"></i>International Events
                                            <span class="badge stats-badge bg-danger ms-1" id="eventsCount">
                                                <?php echo count(array_filter($staff_events, fn($e) => $e['is_assigned'])); ?>
                                            </span>
                                        </button>
                                    </li>
                                </ul>

                                <!-- Tab Content -->
                                <div class="tab-content pt-3" id="assignmentTabsContent">
                                    <!-- Virtual Courses Tab -->
                                    <div class="tab-pane fade show active" id="courses" role="tabpanel">
                                        <div class="mb-3 d-flex justify-content-between align-items-center">
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-success me-1" onclick="selectAll('course')">
                                                    <i class="fas fa-check-double me-1"></i>Select All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll('course')">
                                                    <i class="fas fa-times me-1"></i>Deselect All
                                                </button>
                                            </div>
                                            <small class="text-muted"><?php echo count($staff_courses); ?> courses available</small>
                                        </div>
                                        
                                        <?php if (empty($staff_courses)): ?>
                                            <div class="text-center py-4">
                                                <p class="text-muted">No courses found</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                                <table class="table table-hover assignment-table mb-0">
                                                    <thead class="sticky-top">
                                                        <tr>
                                                            <th width="50">Assign</th>
                                                            <th>Course Name</th>
                                                            <th width="100">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($staff_courses as $course): ?>
                                                            <tr>
                                                                <td class="text-center">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input course-checkbox" type="checkbox" 
                                                                               value="<?php echo $course['course_id']; ?>"
                                                                               id="course_<?php echo $course['course_id']; ?>"
                                                                               <?php echo $course['is_assigned'] ? 'checked' : ''; ?>
                                                                               onchange="updateCount('course')">
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <label for="course_<?php echo $course['course_id']; ?>" class="mb-0 w-100" style="cursor: pointer;">
                                                                        <?php echo htmlspecialchars($course['course']); ?>
                                                                    </label>
                                                                </td>
                                                                <td>
                                                                    <?php if ($course['status'] == 1): ?>
                                                                        <span class="badge badge-active">Active</span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-inactive">Inactive</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- International Events Tab -->
                                    <div class="tab-pane fade" id="events" role="tabpanel">
                                        <div class="mb-3 d-flex justify-content-between align-items-center">
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-success me-1" onclick="selectAll('event')">
                                                    <i class="fas fa-check-double me-1"></i>Select All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll('event')">
                                                    <i class="fas fa-times me-1"></i>Deselect All
                                                </button>
                                            </div>
                                            <small class="text-muted"><?php echo count($staff_events); ?> events available</small>
                                        </div>
                                        
                                        <?php if (empty($staff_events)): ?>
                                            <div class="text-center py-4">
                                                <p class="text-muted">No events found</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                                <table class="table table-hover assignment-table mb-0">
                                                    <thead class="sticky-top">
                                                        <tr>
                                                            <th width="50">Assign</th>
                                                            <th>Event Title</th>
                                                            <th>Location</th>
                                                            <th>Date</th>
                                                            <th width="100">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($staff_events as $event): ?>
                                                            <tr>
                                                                <td class="text-center">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input event-checkbox" type="checkbox" 
                                                                               value="<?php echo $event['event_id']; ?>"
                                                                               id="event_<?php echo $event['event_id']; ?>"
                                                                               <?php echo $event['is_assigned'] ? 'checked' : ''; ?>
                                                                               onchange="updateCount('event')">
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <label for="event_<?php echo $event['event_id']; ?>" class="mb-0 w-100" style="cursor: pointer;">
                                                                        <?php echo htmlspecialchars($event['event_title']); ?>
                                                                    </label>
                                                                </td>
                                                                <td>
                                                                    <small><?php echo htmlspecialchars($event['location'] ?? '-'); ?></small>
                                                                </td>
                                                                <td>
                                                                    <small>
                                                                        <?php 
                                                                        if ($event['start_on']) {
                                                                            echo date('M d, Y', strtotime($event['start_on']));
                                                                        } else {
                                                                            echo '-';
                                                                        }
                                                                        ?>
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <?php if ($event['status'] == 1): ?>
                                                                        <span class="badge badge-active">Active</span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-inactive">Inactive</span>
                                                                    <?php endif; ?>
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
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
const staffId = <?php echo $selected_staff_id; ?>;
const systemUserId = <?php echo $selected_staff ? $selected_staff['system_user_id'] : 0; ?>;

// Update badge counts
function updateCount(type) {
    const checkboxes = document.querySelectorAll(`.${type}-checkbox:checked`);
    const countBadge = document.getElementById(`${type}sCount`);
    countBadge.textContent = checkboxes.length;
}

// Select all
function selectAll(type) {
    document.querySelectorAll(`.${type}-checkbox`).forEach(cb => cb.checked = true);
    updateCount(type);
}

// Deselect all
function deselectAll(type) {
    document.querySelectorAll(`.${type}-checkbox`).forEach(cb => cb.checked = false);
    updateCount(type);
}

// Save assignments
function saveAssignments() {
    if (!staffId || !systemUserId) {
        Swal.fire({icon: 'error', title: 'Error', text: 'No staff member selected'});
        return;
    }
    
    // Get selected courses
    const selectedCourses = [];
    document.querySelectorAll('.course-checkbox:checked').forEach(cb => {
        selectedCourses.push(cb.value);
    });
    
    // Get selected events
    const selectedEvents = [];
    document.querySelectorAll('.event-checkbox:checked').forEach(cb => {
        selectedEvents.push(cb.value);
    });
    
    // Show loading
    Swal.fire({
        title: 'Saving...',
        text: 'Updating assignments',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    // Send to server
    const formData = new FormData();
    formData.append('action', 'save_assignments');
    formData.append('staff_id', staffId);
    formData.append('system_user_id', systemUserId);
    formData.append('courses', JSON.stringify(selectedCourses));
    formData.append('events', JSON.stringify(selectedEvents));
    
    fetch('process_assignments.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message,
                confirmButtonColor: '#198754'
            });
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
            text: 'An error occurred while saving',
            confirmButtonColor: '#dc3545'
        });
    });
}
</script>

<?php require_once 'footer.php'; ?>