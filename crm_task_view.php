<?php
require_once 'header.php';
require_once 'includes/crm_tm_functions.php';

if (!isset($_SESSION['login_id'])) {
    echo '<script>window.location.href="login.php";</script>';
    exit;
}

$current_user_id = crm_tm_current_user_id();
$role = crm_tm_get_user_role($conn, $current_user_id);

$task_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($task_id <= 0) {
    echo '<script>window.location.href="crm_tasks_dashboard.php";</script>';
    exit;
}

// Fetch task with basic joins
$sql = "
    SELECT t.*, u.fullname AS assignee_name, r.fullname AS creator_name,
           d.department_name
    FROM crm_tm_tasks t
    LEFT JOIN registered_users u ON u.id = t.assigned_to_user_id
    LEFT JOIN registered_users r ON r.id = t.created_by
    LEFT JOIN departments d ON d.id = t.department_id
    WHERE t.id = {$task_id}
    LIMIT 1
";
$res = $conn->query($sql);
$task = $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;

if (!$task) {
    echo '<script>alert("Task not found"); window.location.href="crm_tasks_dashboard.php";</script>';
    exit;
}

$is_assignee = ((int)($task['assigned_to_user_id'] ?? 0) === $current_user_id);
$is_requester = ((int)($task['requesting_user_id'] ?? 0) === $current_user_id);
$is_hod_role = ($role === 'hod');
$is_admin = ($role === 'admin');
$can_approve = $is_admin || $is_hod_role || $is_requester;
$is_assigner = ((int)($task['created_by'] ?? 0) === $current_user_id);
$can_approve_completion = $is_admin || $is_assigner;

$is_immutable_status = in_array($task['status'], ['completed', 'cancelled'], true);
$is_overdue = !empty($task['due_date'])
    && $task['due_date'] !== '0000-00-00'
    && strtotime($task['due_date']) < strtotime(date('Y-m-d'))
    && $task['status'] !== 'completed'
    && $task['status'] !== 'cancelled';

// Simple access check reusing crm_tm_get_tasks-style constraints
// Admin can see all; HOD and staff rely on crm_tm_get_tasks filters
$allowed = false;
if ($role === 'admin') {
    $allowed = true;
} else {
    $list = crm_tm_get_tasks($conn, $current_user_id, ['search' => $task['task_code']]);
    foreach ($list as $row) {
        if ((int) $row['id'] === $task_id) {
            $allowed = true;
            break;
        }
    }
}

if (!$allowed) {
    echo '<script>alert("You do not have access to this task."); window.location.href="crm_tasks_dashboard.php";</script>';
    exit;
}

// Handle POST for updates and requirements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Once completed or cancelled, block ALL modifications (no new updates or requirements)
    if ($is_immutable_status) {
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
    if (isset($_POST['update_status'])) {
        $allowed_statuses = $can_approve
            ? ['pending','in_progress','on_hold','pending_approval','completed','cancelled']
            : ['pending','in_progress','on_hold'];
        $new_status = $_POST['new_status'] ?? '';
        // When overdue, only approvers can change status
        if ($is_overdue && !$can_approve) {
            echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
            exit;
        }
        if (in_array($new_status, $allowed_statuses, true)) {
            $label = ucfirst(str_replace('_', ' ', $new_status));
            crm_tm_update_task_status(
                $conn,
                $task_id,
                $current_user_id,
                $new_status,
                null,
                'Status updated to ' . $label . '.'
            );
        }
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
    if (isset($_POST['update_priority']) && $can_approve) {
        $new_priority = $_POST['new_priority'] ?? '';
        crm_tm_update_task_priority($conn, $task_id, $current_user_id, $new_priority);
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
    if (isset($_POST['mark_complete']) && $is_assignee && !$is_overdue) {
        crm_tm_update_task_status(
            $conn,
            $task_id,
            $current_user_id,
            'pending_approval',
            100,
            'Assignee marked task as completed (awaiting assigner approval).'
        );
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
    if (isset($_POST['hod_approve']) && $can_approve_completion) {
        crm_tm_update_task_status(
            $conn,
            $task_id,
            $current_user_id,
            'completed',
            100,
            'Assigner approved task completion.'
        );
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
    if (isset($_POST['hod_reject']) && $can_approve) {
        $reason_raw = (string) ($_POST['hod_reject_reason'] ?? '');
        $reason_sanitized = crm_tm_sanitize_rich_text($reason_raw);
        $reason = crm_tm_rich_text_has_content($reason_sanitized)
            ? $reason_sanitized
            : 'Sent back to assignee for further work.';
        crm_tm_update_task_status(
            $conn,
            $task_id,
            $current_user_id,
            'in_progress',
            null,
            $reason
        );
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
    if (isset($_POST['add_update'])) {
        $message_raw = (string) ($_POST['message'] ?? '');
        $message_sanitized = crm_tm_sanitize_rich_text($message_raw);
        $progress_pct = $_POST['progress_pct'] !== '' ? (int) $_POST['progress_pct'] : null;
        $update_type = $_POST['update_type'] ?? 'comment';
        if (crm_tm_rich_text_has_content($message_sanitized)) {
            if ($progress_pct !== null) {
                if ($progress_pct < 0) {
                    $progress_pct = 0;
                } elseif ($progress_pct > 100) {
                    $progress_pct = 100;
                }
            }
            crm_tm_add_task_update($conn, $task_id, $current_user_id, $update_type, $message_sanitized, $progress_pct);
        }
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
    if (isset($_POST['add_requirement'])) {
        $req_raw = (string) ($_POST['requirement_text'] ?? '');
        $req_sanitized = crm_tm_sanitize_rich_text($req_raw);
        if (crm_tm_rich_text_has_content($req_sanitized)) {
            crm_tm_add_requirement($conn, $task_id, $current_user_id, $req_sanitized);
        }
        echo '<script>window.location.href="crm_task_view.php?id=' . (int)$task_id . '";</script>';
        exit;
    }
}

// Load updates
$updates = [];
$res_upd = $conn->query("
    SELECT u.*, ru.fullname AS user_name
    FROM crm_tm_task_updates u
    LEFT JOIN registered_users ru ON ru.id = u.user_id
    WHERE u.task_id = {$task_id}
    ORDER BY u.created_at DESC
");
if ($res_upd) {
    while ($row = $res_upd->fetch_assoc()) {
        $updates[] = $row;
    }
}

// Load requirements
$requirements = [];
$res_req = $conn->query("
    SELECT r.*, u.fullname AS requested_by_name
    FROM crm_tm_task_requirements r
    LEFT JOIN registered_users u ON u.id = r.requested_by_user_id
    WHERE r.task_id = {$task_id}
    ORDER BY r.created_at DESC
");
if ($res_req) {
    while ($row = $res_req->fetch_assoc()) {
        $requirements[] = $row;
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <i class="fas fa-tasks"></i>
                        <?php echo htmlspecialchars($task['task_code']); ?>
                    </h4>
                    <div class="text-muted">
                        <?php echo htmlspecialchars($task['title']); ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="crm_tasks_dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to CRM Tasks
                    </a>
                    <a href="crm_task_print.php?id=<?php echo $task_id; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-print"></i> Print
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4 rounded-0">
                        <div class="card-header bg_main rounded-0 py-2 text-white">
                            <strong>Task Details</strong>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Status</dt>
                                <dd class="col-sm-8">
                                    <?php
                                    $status_label = ucfirst(str_replace('_',' ',$task['status']));
                                    $status_class = 'secondary';
                                    if ($task['status'] === 'in_progress') {
                                        $status_class = 'info';
                                    } elseif ($task['status'] === 'pending_approval') {
                                        $status_class = 'warning';
                                    } elseif ($task['status'] === 'completed') {
                                        $status_class = 'success';
                                    } elseif ($task['status'] === 'cancelled') {
                                        $status_class = 'dark';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-4">Priority</dt>
                                <dd class="col-sm-8">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-<?php echo $task['priority'] === 'critical' ? 'danger' : ($task['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <?php if ($can_approve && !$is_immutable_status): ?>
                                            <form method="post" class="d-flex align-items-center gap-1">
                                                <select name="new_priority" class="form-select form-select-sm">
                                                    <?php foreach (['low','medium','high','critical'] as $p): ?>
                                                        <option value="<?php echo $p; ?>" <?php echo $task['priority'] === $p ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst($p); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="update_priority" class="btn btn-sm btn-outline-secondary">
                                                    Update
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </dd>

                                <dt class="col-sm-4">Department</dt>
                                <dd class="col-sm-8">
                                    <?php echo htmlspecialchars($task['department_name'] ?? '—'); ?>
                                </dd>

                                <dt class="col-sm-4">Assignee</dt>
                                <dd class="col-sm-8">
                                    <?php echo htmlspecialchars($task['assignee_name'] ?? '—'); ?>
                                </dd>

                                <dt class="col-sm-4">Created By</dt>
                                <dd class="col-sm-8">
                                    <?php echo htmlspecialchars($task['creator_name'] ?? '—'); ?>
                                </dd>

                                <dt class="col-sm-4">Dates</dt>
                                <dd class="col-sm-8">
                                    <?php
                                    $start = (!empty($task['start_date']) && $task['start_date'] !== '0000-00-00') ? date('M j, Y', strtotime($task['start_date'])) : '—';
                                    $due = (!empty($task['due_date']) && $task['due_date'] !== '0000-00-00') ? date('M j, Y', strtotime($task['due_date'])) : '—';
                                    echo "Start: {$start}<br>Due: {$due}";
                                    ?>
                                </dd>

                                <dt class="col-sm-4">Progress</dt>
                                <dd class="col-sm-8">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?php echo (int)$task['progress_pct']; ?>%;"
                                                 aria-valuenow="<?php echo (int)$task['progress_pct']; ?>"
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <span class="small fw-semibold"><?php echo (int)$task['progress_pct']; ?>%</span>
                                    </div>
                                </dd>

                                <dt class="col-sm-4">Needs Support?</dt>
                                <dd class="col-sm-8">
                                    <?php if ((int)$task['needs_support'] === 1): ?>
                                        <span class="badge bg-warning text-dark">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">No</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>

                            <?php if (!empty($task['description'])): ?>
                                <hr>
                                <h6 class="fw-semibold">Description</h6>
                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($task['support_summary'])): ?>
                                <hr>
                                <h6 class="fw-semibold">What is needed to achieve this task</h6>
                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($task['support_summary'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4 rounded-0">
                        <div class="card-header bg_main rounded-0 py-2 text-white">
                            <strong>Requirements</strong>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($requirements)): ?>
                                <ul class="list-group mb-3">
                                    <?php foreach ($requirements as $r): ?>
                                        <li class="list-group-item small">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($r['requested_by_name'] ?? ''); ?></strong>
                                                    <span class="text-muted">(
                                                        <?php echo date('M j, Y H:i', strtotime($r['created_at'])); ?>
                                                        )</span>
                                                </div>
                                                <div>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst($r['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <?php echo crm_tm_render_rich_text((string) ($r['requirement_text'] ?? '')); ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-3">No specific requirements logged yet.</p>
                            <?php endif; ?>

                            <?php if (!$is_immutable_status): ?>
                                <form method="post">
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold">Add Requirement / Support Needed</label>
                                        <textarea name="requirement_text" class="form-control form-control-sm js-summernote" rows="2"></textarea>
                                    </div>
                                    <button type="submit" name="add_requirement" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus-circle"></i> Add Requirement
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-sm mb-4 rounded-0">
                            <div class="card-header bg_main rounded-0 py-2 text-white d-flex justify-content-between align-items-center">
                            <strong>Progress & Feedback</strong>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (!$is_immutable_status): ?>
                                    <form method="post" class="m-0 d-flex align-items-center gap-1">
                                        <label class="form-label form-label-sm mb-0 small">Status:</label>
                                        <select name="new_status" class="form-select form-select-sm" <?php echo ($is_overdue && !$can_approve) ? 'disabled' : ''; ?>>
                                            <?php
                                            $status_options = $can_approve
                                                ? ['pending','in_progress','on_hold','pending_approval','completed','cancelled']
                                                : ['pending','in_progress','on_hold'];
                                            foreach ($status_options as $opt) {
                                                ?>
                                                <option value="<?php echo $opt; ?>" <?php echo $task['status'] === $opt ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst(str_replace('_', ' ', $opt)); ?>
                                                </option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-sm btn-outline-light" <?php echo ($is_overdue && !$can_approve) ? 'disabled' : ''; ?>>
                                            Change
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($is_assignee && in_array($task['status'], ['pending','in_progress','on_hold'], true) && !$is_overdue && !$is_immutable_status): ?>
                                    <form method="post" class="m-0">
                                        <button type="submit" name="mark_complete" class="btn btn-sm btn-light">
                                            <i class="fas fa-check-circle"></i> Mark as Finished (Send to assigner)
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($can_approve_completion && $task['status'] === 'pending_approval' && !$is_immutable_status): ?>
                                    <form method="post" class="m-0 d-flex gap-2 align-items-center">
                                        <button type="submit" name="hod_approve" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Approve & Close
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!$is_immutable_status): ?>
                                <form method="post" class="mb-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label small">Update Type</label>
                                            <select name="update_type" class="form-select form-select-sm">
                                                <option value="comment">Comment</option>
                                                <option value="status_change">Status change</option>
                                                <option value="support">Support note</option>
                                                <option value="hod_note">HOD note</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Progress (%)</label>
                                            <input type="number" name="progress_pct" class="form-control form-control-sm" min="0" max="100" value="">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Message</label>
                                            <textarea name="message" class="form-control form-control-sm js-summernote" rows="2" required></textarea>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-end">
                                        <button type="submit" name="add_update" class="btn btn-sm btn-primary">
                                            <i class="fas fa-paper-plane"></i> Add Update
                                        </button>
                                    </div>
                                </form>

                                <hr>
                            <?php endif; ?>

                            <?php if ($can_approve && $task['status'] === 'pending_approval'): ?>
                                <div class="alert alert-warning py-2 small">
                                    This task has been marked as finished by the assignee and is waiting for assigner approval.
                                </div>
                                <form method="post" class="mb-3">
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold">Send back to assignee (optional note)</label>
                                        <textarea name="hod_reject_reason" class="form-control form-control-sm js-summernote" rows="2"
                                                  placeholder="Explain what still needs to be done or corrected..."></textarea>
                                    </div>
                                    <button type="submit" name="hod_reject" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-undo"></i> Send Back to In Progress
                                    </button>
                                </form>
                                <hr>
                            <?php endif; ?>

                            <?php if (!empty($updates)): ?>
                                <div class="list-group small">
                                    <?php foreach ($updates as $u): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($u['user_name'] ?? ''); ?></strong>
                                                    <span class="badge bg-light text-muted border ms-1">
                                                        <?php echo ucfirst(str_replace('_',' ',$u['update_type'])); ?>
                                                    </span>
                                                </div>
                                                <div class="text-muted">
                                                    <?php echo date('M j, Y H:i', strtotime($u['created_at'])); ?>
                                                </div>
                                            </div>
                                            <?php if ($u['progress_pct'] !== null): ?>
                                                <div class="mt-1">
                                                    Progress: <strong><?php echo (int)$u['progress_pct']; ?>%</strong>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <?php echo crm_tm_render_rich_text((string) ($u['message'] ?? '')); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No updates yet. Use the form above to add the first progress update.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || typeof jQuery.fn.summernote !== 'function') {
            return;
        }

        jQuery('.js-summernote').summernote({
            height: 120,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link']],
                ['view', ['codeview']]
            ]
        });
    });
</script>

