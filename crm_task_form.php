<?php
require_once 'header.php';
require_once 'includes/crm_tm_functions.php';

if (!isset($_SESSION['login_id'])) {
    echo '<script>window.location.href="login.php";</script>';
    exit;
}

$current_user_id = crm_tm_current_user_id();
$role = crm_tm_get_user_role($conn, $current_user_id);

$errors = [];
$success = false;

// For selects
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'pending';
    $assigned_to_user_id = (int) ($_POST['assigned_to_user_id'] ?? 0);
    $requesting_user_id = (int) ($_POST['requesting_user_id'] ?? 0);
    $department_id = (int) ($_POST['department_id'] ?? 0);
    $cross_department_flag = !empty($_POST['cross_department_flag']) ? 1 : 0;
    $start_date = $_POST['start_date'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $support_summary = trim((string) ($_POST['support_summary'] ?? ''));

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($assigned_to_user_id <= 0) {
        $errors[] = 'Please select an assignee.';
    }

    if (empty($errors)) {
        $data = [
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'status' => $status,
            'assigned_to_user_id' => $assigned_to_user_id,
            'requesting_user_id' => $requesting_user_id ?: null,
            'department_id' => $department_id ?: null,
            'cross_department_flag' => $cross_department_flag,
            'start_date' => $start_date ?: null,
            'due_date' => $due_date ?: null,
            'support_summary' => $support_summary ?: null,
            'hod_owner_id' => $role === 'hod' ? $current_user_id : null,
        ];

        $new_id = crm_tm_create_task($conn, $data, $current_user_id);
        if ($new_id) {
            if ($support_summary !== '') {
                crm_tm_add_requirement($conn, $new_id, $current_user_id, $support_summary);
            }
            echo '<script>window.location.href="crm_task_view.php?id=' . (int)$new_id . '";</script>';
            exit;
        } else {
            $errors[] = 'Failed to create task. Please try again.';
        }
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card shadow mb-4 rounded-0">
                        <div class="card-header bg_main rounded-0 py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">New CRM Task</h6>
                            <a href="crm_tasks_dashboard.php" class="btn btn-sm btn-outline-light">Back to CRM Tasks</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $e): ?>
                                            <li><?php echo htmlspecialchars($e); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Priority</label>
                                        <select name="priority" class="form-select">
                                            <?php foreach (['low','medium','high','critical'] as $p): ?>
                                                <option value="<?php echo $p; ?>" <?php echo (($_POST['priority'] ?? 'medium') === $p) ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($p); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Status</label>
                                        <select name="status" class="form-select">
                                            <?php
                                            $distributable_statuses = [
                                                'pending' => 'Assigned',
                                                'in_progress' => 'Started',
                                                'cancelled' => 'Cancelled',
                                                'completed' => 'Complete',
                                            ];
                                            $current_status = $_POST['status'] ?? 'pending';
                                            foreach ($distributable_statuses as $s => $label):
                                            ?>
                                                <option value="<?php echo $s; ?>" <?php echo $current_status === $s ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Department</label>
                                        <select name="department_id" class="form-select">
                                            <option value="">Not specified</option>
                                            <?php foreach ($departments as $d): ?>
                                                <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($_POST['department_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($d['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="cross_department_flag" id="crossDept"
                                                <?php echo !empty($_POST['cross_department_flag']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="crossDept">
                                                Cross-department task
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Assignee <span class="text-danger">*</span></label>
                                        <select name="assigned_to_user_id" class="form-select" required>
                                            <option value="">Select user</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)($_POST['assigned_to_user_id'] ?? $current_user_id) === (int)$u['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($u['fullname']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Requested By</label>
                                        <select name="requesting_user_id" class="form-select">
                                            <option value="">Not specified</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)($_POST['requesting_user_id'] ?? $current_user_id) === (int)$u['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($u['fullname']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Start Date</label>
                                        <input type="date" name="start_date" class="form-control"
                                               value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Due Date</label>
                                        <input type="date" name="due_date" class="form-control"
                                               value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label fw-semibold">What do they need to achieve this task?</label>
                                    <textarea name="support_summary" class="form-control" rows="2"
                                              placeholder="Resources, decisions, information, or support needed..."><?php echo htmlspecialchars($_POST['support_summary'] ?? ''); ?></textarea>
                                    <small class="text-muted">
                                        This will be saved on the task and also as the first requirement entry.
                                    </small>
                                </div>

                                <div class="mt-4 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Task
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>

