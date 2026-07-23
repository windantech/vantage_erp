<?php
require_once 'header.php';
require_once 'includes/crm_tm_functions.php';

if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = crm_tm_current_user_id();
$role = crm_tm_get_user_role($conn, $current_user_id);

// Collect filters from GET
$status = $_GET['status'] ?? '';
$department_id = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$assigned_to = isset($_GET['assigned_to']) ? (int) $_GET['assigned_to'] : 0;
$search = $_GET['search'] ?? '';

$filters = [];
if ($status !== '') {
    $filters['status'] = $status;
}
if ($department_id > 0) {
    $filters['department_id'] = $department_id;
}
if ($assigned_to > 0) {
    $filters['assigned_to'] = $assigned_to;
}
if ($search !== '') {
    $filters['search'] = $search;
}

$tasks = crm_tm_get_tasks($conn, $current_user_id, $filters);

// Last progress/feedback for overdue tasks (for dashboard display)
$overdue_task_ids = [];
foreach ($tasks as $t) {
    $due_ok = !empty($t['due_date']) && $t['due_date'] !== '0000-00-00';
    $past_due = $due_ok && strtotime($t['due_date']) < strtotime(date('Y-m-d'));
    $open = !in_array($t['status'] ?? '', ['completed', 'cancelled'], true);
    if ($past_due && $open) {
        $overdue_task_ids[] = (int) $t['id'];
    }
}
$last_updates_by_task = !empty($overdue_task_ids) ? crm_tm_get_last_task_updates($conn, $overdue_task_ids) : [];

// Status visualization data (including derived "overdue")
$status_keys = ['pending','in_progress','on_hold','pending_approval','overdue','completed','cancelled'];
$status_labels = [
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'on_hold' => 'On Hold',
    'pending_approval' => 'Pending Approval',
    'overdue' => 'Overdue',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$summary_by_status = array_fill_keys($status_keys, 0);
$summary_by_dept_status = [];

if ($role === 'admin') {
    // Global status counts (with derived overdue)
    $resSum = $conn->query("
        SELECT
            CASE
                WHEN due_date IS NOT NULL
                     AND due_date <> '0000-00-00'
                     AND due_date < CURDATE()
                     AND status <> 'completed'
                     AND status <> 'cancelled'
                THEN 'overdue'
                ELSE status
            END AS status_key,
            COUNT(*) AS total
        FROM crm_tm_tasks
        GROUP BY status_key
    ");
    if ($resSum) {
        while ($row = $resSum->fetch_assoc()) {
            $st = $row['status_key'] ?? '';
            if (isset($summary_by_status[$st])) {
                $summary_by_status[$st] = (int) $row['total'];
            }
        }
    }

    // Status by department (with derived overdue)
    $resDept = $conn->query("
        SELECT
            COALESCE(d.department_name, 'Unassigned') AS dept_name,
            CASE
                WHEN t.due_date IS NOT NULL
                     AND t.due_date <> '0000-00-00'
                     AND t.due_date < CURDATE()
                     AND t.status <> 'completed'
                     AND t.status <> 'cancelled'
                THEN 'overdue'
                ELSE t.status
            END AS status_key,
            COUNT(*) AS total
        FROM crm_tm_tasks t
        LEFT JOIN departments d ON d.id = t.department_id
        GROUP BY d.id, dept_name, status_key
        ORDER BY dept_name ASC
    ");
    if ($resDept) {
        while ($row = $resDept->fetch_assoc()) {
            $deptName = $row['dept_name'];
            $st = $row['status_key'];
            if (!isset($summary_by_dept_status[$deptName])) {
                $summary_by_dept_status[$deptName] = array_fill_keys($status_keys, 0);
            }
            if (isset($summary_by_dept_status[$deptName][$st])) {
                $summary_by_dept_status[$deptName][$st] = (int) $row['total'];
            }
        }
    }
} elseif ($role === 'hod') {
    // HOD: focus on their department tasks
    $dept_id = crm_tm_get_user_department_id($conn, $current_user_id);
    if ($dept_id) {
        $resSum = $conn->query("
            SELECT
                CASE
                    WHEN t.due_date IS NOT NULL
                         AND t.due_date <> '0000-00-00'
                         AND t.due_date < CURDATE()
                         AND t.status <> 'completed'
                         AND t.status <> 'cancelled'
                    THEN 'overdue'
                    ELSE t.status
                END AS status_key,
                COUNT(*) AS total
            FROM crm_tm_tasks t
            WHERE t.department_id = {$dept_id}
            GROUP BY status_key
        ");
        if ($resSum) {
            while ($row = $resSum->fetch_assoc()) {
                $st = $row['status_key'] ?? '';
                if (isset($summary_by_status[$st])) {
                    $summary_by_status[$st] = (int) $row['total'];
                }
            }
        }
    }
} else {
    // Staff: summarize only tasks in current view (assigned to / requested by them)
    foreach ($tasks as $t) {
        $st = $t['status'] ?? '';
        if (isset($summary_by_status[$st])) {
            $summary_by_status[$st]++;
        }
    }
}

// For filters
$departments = [];
$res = $conn->query("SELECT id, department_name FROM departments WHERE status = 1 ORDER BY department_name");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$users = [];
$res2 = $conn->query("SELECT id, fullname FROM registered_users ORDER BY fullname");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-1"><i class="fas fa-tasks"></i> CRM Tasks</h4>
                    <small class="text-muted">
                        Role:
                        <?php if ($role === 'admin'): ?>
                            Super User (Admin) – viewing all CRM tasks.
                        <?php elseif ($role === 'hod'): ?>
                            HOD – viewing tasks for your department and your own tasks.
                        <?php else: ?>
                            Staff – viewing tasks assigned to or requested by you.
                        <?php endif; ?>
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="crm_task_form.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> New Task
                    </a>
                    <a href="crm_task_print.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-print"></i> Print
                    </a>
                </div>
            </div>

            <?php if ($role === 'admin' || $role === 'hod' || $role === 'staff'): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm rounded-0">
                            <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 text-uppercase small text-muted">
                                    <?php if ($role === 'admin'): ?>
                                        Overall Task Status Overview
                                    <?php elseif ($role === 'hod'): ?>
                                        Department Task Status Overview
                                    <?php else: ?>
                                        Your Task Status Overview
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="card-body py-3">
                                <div class="row g-2">
                                    <?php
                                    $status_badges = [
                                        'pending' => 'secondary',
                                        'in_progress' => 'info',
                                        'on_hold' => 'dark',
                                        'pending_approval' => 'warning',
                                        'overdue' => 'danger',
                                        'completed' => 'success',
                                        'cancelled' => 'secondary',
                                    ];
                                    $total_all = array_sum($summary_by_status);
                                    foreach ($status_keys as $st):
                                        $count = $summary_by_status[$st];
                                        $percent = $total_all > 0 ? round(($count / $total_all) * 100) : 0;
                                    ?>
                                        <div class="col-6 col-md-4 col-lg-2">
                                            <div class="border rounded-2 p-2 text-center small">
                                                <div class="text-muted mb-1"><?php echo $status_labels[$st]; ?></div>
                                                <div class="fw-bold fs-6"><?php echo $count; ?></div>
                                                <?php if ($total_all > 0): ?>
                                                    <div class="progress mt-1" style="height:4px;">
                                                        <div class="progress-bar bg-<?php echo $status_badges[$st]; ?>" role="progressbar"
                                                             style="width: <?php echo $percent; ?>%;"></div>
                                                    </div>
                                                    <div class="text-muted mt-1"><?php echo $percent; ?>%</div>
                                                <?php else: ?>
                                                    <div class="text-muted mt-1">0%</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($role === 'admin' && !empty($summary_by_dept_status)): ?>
                                    <hr class="my-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="m-0 small text-muted text-uppercase">Status by Department</h6>
                                        <small class="text-muted">Snapshot of all CRM tasks grouped by department and status.</small>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="small">Department</th>
                                                    <?php foreach ($status_keys as $st): ?>
                                                        <th class="small text-center"><?php echo $status_labels[$st]; ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($summary_by_dept_status as $deptName => $row): ?>
                                                    <tr>
                                                        <td class="small"><?php echo htmlspecialchars($deptName); ?></td>
                                                        <?php foreach ($status_keys as $st): ?>
                                                            <td class="text-center small">
                                                                <?php echo (int)($row[$st] ?? 0); ?>
                                                            </td>
                                                        <?php endforeach; ?>
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

            <div class="card shadow mb-4 rounded-0">
                        <div class="card-header bg_main rounded-0 py-3">
                    <h6 class="m-0 font-weight-bold text-white text-uppercase">Tasks</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="crm_tasks_dashboard.php" class="row g-2 mb-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <?php foreach (['pending','in_progress','on_hold','pending_approval','overdue','completed','cancelled'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_',' ',$s)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Department</label>
                            <select name="department_id" class="form-select form-select-sm">
                                <option value="0">All</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo $department_id === (int)$d['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Assignee</label>
                            <select name="assigned_to" class="form-select form-select-sm">
                                <option value="0">All</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo (int)$u['id']; ?>" <?php echo $assigned_to === (int)$u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Search</label>
                            <input type="text" name="search" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search); ?>" placeholder="Task code or title">
                        </div>
                        <div class="col-md-1 d-flex gap-1">
                            <button class="btn btn-primary btn-sm" type="submit" title="Apply filters">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="submit" title="Search">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive overflow">
                        <table class="table table-striped table-bordered align-middle" id="crmTasksTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th>Department</th>
                                    <th>Assignee</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due</th>
                                    <th>Progress</th>
                                    <th>Last feedback (overdue)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($tasks)): ?>
                                    <?php foreach ($tasks as $t): ?>
                                        <tr>
                                            <td class="nowrap"><?php echo htmlspecialchars($t['task_code']); ?></td>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width: 260px;">
                                                    <?php echo htmlspecialchars($t['title']); ?>
                                                </div>
                                                <?php if (!empty($t['support_summary'])): ?>
                                                    <div class="small text-muted text-truncate" style="max-width: 260px;">
                                                        Needs: <?php echo htmlspecialchars($t['support_summary']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="nowrap">
                                                <?php echo htmlspecialchars($t['department_name'] ?? '—'); ?>
                                            </td>
                                            <td class="nowrap">
                                                <?php echo htmlspecialchars($t['assignee_name'] ?? '—'); ?>
                                            </td>
                                            <td class="nowrap">
                                                <?php
                                                $is_overdue = !empty($t['due_date'])
                                                    && $t['due_date'] !== '0000-00-00'
                                                    && strtotime($t['due_date']) < strtotime(date('Y-m-d'))
                                                    && $t['status'] !== 'completed'
                                                    && $t['status'] !== 'cancelled';
                                                $effective_status = $is_overdue ? 'overdue' : $t['status'];

                                                $status_label = ucfirst(str_replace('_',' ',$effective_status));
                                                $status_class = 'secondary';
                                                if ($effective_status === 'in_progress') {
                                                    $status_class = 'info';
                                                } elseif ($effective_status === 'pending_approval') {
                                                    $status_class = 'warning';
                                                } elseif ($effective_status === 'overdue') {
                                                    $status_class = 'danger';
                                                } elseif ($effective_status === 'completed') {
                                                    $status_class = 'success';
                                                } elseif ($effective_status === 'cancelled') {
                                                    $status_class = 'dark';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $status_label; ?>
                                                </span>
                                            </td>
                                            <td class="nowrap">
                                                <span class="badge bg-<?php echo $t['priority'] === 'critical' ? 'danger' : ($t['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($t['priority']); ?>
                                                </span>
                                            </td>
                                            <td class="nowrap">
                                                <?php
                                                if (!empty($t['due_date']) && $t['due_date'] !== '0000-00-00') {
                                                    echo date('M j, Y', strtotime($t['due_date']));
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                            <td class="nowrap">
                                                <div class="small fw-semibold"><?php echo (int)$t['progress_pct']; ?>%</div>
                                            </td>
                                            <td class="small" style="max-width: 220px;">
                                                <?php
                                                if ($is_overdue && isset($last_updates_by_task[(int)$t['id']])):
                                                    $lu = $last_updates_by_task[(int)$t['id']];
                                                ?>
                                                    <div class="text-muted">
                                                        <?php if ($lu['progress_pct'] !== null): ?>
                                                            <span class="fw-semibold"><?php echo (int)$lu['progress_pct']; ?>%</span>
                                                            <?php if ($lu['message'] !== ''): ?> · <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ($lu['message'] !== ''): ?>
                                                            <?php echo htmlspecialchars(strlen($lu['message']) > 80 ? substr($lu['message'], 0, 80) . '…' : $lu['message']); ?>
                                                        <?php endif; ?>
                                                        <div class="mt-1"><?php echo !empty($lu['created_at']) ? date('M j, Y H:i', strtotime($lu['created_at'])) : '—'; ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="nowrap">
                                                <a href="crm_task_view.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            No CRM tasks found for the selected filters.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableEl = document.getElementById('crmTasksTable');
        if (tableEl && window.jQuery && jQuery.fn.DataTable) {
            jQuery(tableEl).DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                searching: false
            });
        }
    });
</script>

<?php require_once 'footer.php'; ?>

