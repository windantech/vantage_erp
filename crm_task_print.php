<?php
require_once 'header.php';
require_once 'includes/crm_tm_functions.php';

if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = crm_tm_current_user_id();
$role = crm_tm_get_user_role($conn, $current_user_id);

// Single-task print (from view page)
if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $task_id = (int) $_GET['id'];
    $filters = [];
    $tasks = crm_tm_get_tasks($conn, $current_user_id, $filters);
    $task = null;
    foreach ($tasks as $t) {
        if ((int)$t['id'] === $task_id) {
            $task = $t;
            break;
        }
    }
    if (!$task) {
        echo 'Task not found or access denied.';
        exit;
    }
    $tasks = [$task];
} else {
    // List print using same filters as dashboard
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
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CRM Tasks Print</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
        body {
            font-size: 12px;
        }
        .table th, .table td {
            padding: 0.35rem 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <div class="d-flex justify-content-between align-items-center no-print mb-2">
            <h5 class="mb-0">CRM Tasks – Printable View</h5>
            <div>
                <button class="btn btn-sm btn-primary" onclick="window.print();">
                    Print
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="window.close();">
                    Close
                </button>
            </div>
        </div>
        <hr class="no-print">

        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Department</th>
                    <th>Assignee</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Start Date</th>
                    <th>Due Date</th>
                    <th>Progress</th>
                    <th>Support Needed</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tasks)): ?>
                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['task_code']); ?></td>
                            <td><?php echo htmlspecialchars($t['title']); ?></td>
                            <td><?php echo htmlspecialchars($t['department_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($t['assignee_name'] ?? ''); ?></td>
                            <td><?php echo ucfirst(str_replace('_',' ',$t['status'])); ?></td>
                            <td><?php echo ucfirst($t['priority']); ?></td>
                            <td>
                                <?php
                                if (!empty($t['start_date']) && $t['start_date'] !== '0000-00-00') {
                                    echo date('Y-m-d', strtotime($t['start_date']));
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($t['due_date']) && $t['due_date'] !== '0000-00-00') {
                                    echo date('Y-m-d', strtotime($t['due_date']));
                                }
                                ?>
                            </td>
                            <td><?php echo (int)$t['progress_pct']; ?>%</td>
                            <td><?php echo htmlspecialchars($t['support_summary'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted">No tasks to print.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

