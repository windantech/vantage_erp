<?php
ob_start();
require_once 'header.php';
require_once 'includes/task_functions.php';

if (!isset($_SESSION['login_id'])) { header('Location: login.php'); exit; }
$current_user_id = intval($_SESSION['login_id']);
$user_role = tm_get_user_role($conn, $current_user_id);

// Get user name
$u_result = $conn->query("SELECT fullname FROM registered_users WHERE id = $current_user_id LIMIT 1");
$user_name = $u_result ? ($u_result->fetch_assoc()['fullname'] ?? 'User') : 'User';

// Filters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$view = $_GET['view'] ?? 'active'; // active, overdue, completed, all

$filters = ['owner_id' => $current_user_id];
$strategy_year = tm_get_setting($conn, 'strategy_year', date('Y'));
$filters['strategy_year'] = intval($_GET['year'] ?? $strategy_year);

if ($view === 'overdue') {
    $filters['is_overdue'] = true;
} elseif ($view === 'completed') {
    $filters['status'] = ['Completed', 'Verified'];
} elseif ($view === 'active') {
    $filters['status'] = ['Assigned', 'In Progress', 'Blocked', 'On Hold', 'Submitted for Review'];
}

if ($status_filter) $filters['status'] = $status_filter;
if ($priority_filter) $filters['priority'] = $priority_filter;

$tasks = tm_get_tasks($conn, $filters, 200);
$stats = tm_get_stats($conn, ['owner_id' => $current_user_id, 'strategy_year' => $filters['strategy_year']]);

// Notifications
$notifications = tm_get_notifications($conn, $current_user_id, true, 10);

// Overdue tasks needing explanation
$overdue_needing_explanation = [];
$r = $conn->query("SELECT id, task_id, task_title, due_date, DATEDIFF(CURDATE(), due_date) AS days_overdue 
    FROM tm_tasks WHERE owner_id = $current_user_id AND overdue_explanation_required = 1 
    AND status NOT IN ('Completed','Verified','Cancelled') ORDER BY due_date ASC");
if ($r) while ($row = $r->fetch_assoc()) $overdue_needing_explanation[] = $row;

// Upcoming deadlines (next 7 days)
$upcoming = [];
$r = $conn->query("SELECT id, task_id, task_title, due_date, priority, status, progress_pct,
    DATEDIFF(due_date, CURDATE()) AS days_until_due
    FROM tm_tasks WHERE owner_id = $current_user_id 
    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND status NOT IN ('Completed','Verified','Cancelled')
    ORDER BY due_date ASC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $upcoming[] = $row;

// Handle AJAX quick update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'quick_update') {
        $tid = intval($_POST['task_id'] ?? 0);
        // Verify ownership
        $check = $conn->query("SELECT owner_id FROM tm_tasks WHERE id = $tid LIMIT 1");
        if (!$check || !($row = $check->fetch_assoc()) || $row['owner_id'] != $current_user_id) {
            echo json_encode(['success' => false, 'message' => 'Not your task']); exit;
        }
        
        $updates = [];
        if (isset($_POST['status'])) $updates['status'] = $_POST['status'];
        if (isset($_POST['progress_pct'])) $updates['progress_pct'] = min(100, max(0, intval($_POST['progress_pct'])));
        
        if (!empty($updates)) {
            // Check evidence requirement for completion
            if (isset($updates['status']) && in_array($updates['status'], ['Completed','Submitted for Review'])) {
                $req_ev = tm_get_setting($conn, 'require_evidence_for_completion', '1');
                $task = tm_get_task($conn, $tid);
                if ($req_ev == '1' && !empty($task['evidence_requirement'])) {
                    $ev = tm_get_evidence($conn, $tid);
                    if (empty($ev)) {
                        echo json_encode(['success' => false, 'message' => 'Upload evidence before completing this task']); exit;
                    }
                }
            }
            
            $note = trim($_POST['note'] ?? '');
            tm_update_task($conn, $tid, $updates, $current_user_id, $note ?: null);
            if ($note) tm_log_activity($conn, $tid, 'comment', $note, null, null, null, $current_user_id);
        }
        echo json_encode(['success' => true, 'message' => 'Updated']); exit;
    }
    
    if ($_POST['ajax_action'] === 'mark_notification_read') {
        tm_mark_notification_read($conn, intval($_POST['notification_id']), $current_user_id);
        echo json_encode(['success' => true]); exit;
    }
}

$priority_colors = ['Critical'=>'danger','High'=>'warning','Medium'=>'info','Low'=>'secondary'];
$status_colors = ['Assigned'=>'primary','In Progress'=>'info','Blocked'=>'danger','On Hold'=>'warning','Submitted for Review'=>'purple','Completed'=>'success','Verified'=>'success'];
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h4 class="mb-0"><i class="fas fa-clipboard-list"></i> My Tasks</h4>
        <small class="text-muted">Welcome, <?= htmlspecialchars($user_name) ?></small>
    </div>
    
    <!-- Overdue Alert -->
    <?php if (!empty($overdue_needing_explanation)): ?>
    <div class="alert alert-danger">
        <strong><i class="fas fa-exclamation-triangle"></i> <?= count($overdue_needing_explanation) ?> overdue task(s) need your explanation:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($overdue_needing_explanation as $ot): ?>
            <li>
                <a href="task_details.php?id=<?= $ot['id'] ?>" class="text-danger fw-bold"><?= htmlspecialchars($ot['task_id']) ?>: <?= htmlspecialchars($ot['task_title']) ?></a>
                — <?= $ot['days_overdue'] ?> day(s) overdue
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="row mb-3">
        <div class="col"><div class="card bg-light"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['total']?></h4><small class="text-muted">Total</small></div></div></div>
        <div class="col"><div class="card bg-success text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=($stats['completed']??0)+($stats['verified']??0)?></h4><small>Done</small></div></div></div>
        <div class="col"><div class="card bg-info text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['in_progress']??0?></h4><small>In Progress</small></div></div></div>
        <div class="col"><div class="card bg-danger text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['overdue']?></h4><small>Overdue</small></div></div></div>
        <div class="col"><div class="card border-primary"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['completion_pct']?>%</h4><small class="text-muted">Complete</small></div></div></div>
    </div>
    
    <div class="row">
        <!-- LEFT: Tasks -->
        <div class="col-md-8">
            <!-- View Tabs -->
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item"><a class="nav-link <?=$view==='active'?'active':''?>" href="?view=active">Active</a></li>
                <li class="nav-item"><a class="nav-link <?=$view==='overdue'?'active':''?> <?=$stats['overdue']>0?'text-danger':''?>" href="?view=overdue">Overdue <?php if($stats['overdue']>0):?><span class="badge bg-danger"><?=$stats['overdue']?></span><?php endif;?></a></li>
                <li class="nav-item"><a class="nav-link <?=$view==='completed'?'active':''?>" href="?view=completed">Completed</a></li>
                <li class="nav-item"><a class="nav-link <?=$view==='all'?'active':''?>" href="?view=all">All</a></li>
            </ul>
            
            <!-- Tasks List -->
            <?php if (empty($tasks)): ?>
            <div class="card"><div class="card-body text-center text-muted py-5">
                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                <h5>No tasks in this view</h5>
            </div></div>
            <?php else: ?>
            <?php foreach ($tasks as $t): 
                $t_overdue = ($t['computed_is_overdue'] ?? 0) == 1;
                $t_days = $t['computed_days_overdue'] ?? 0;
                $due_label = date('d M', strtotime($t['due_date']));
                if ($t_overdue) $due_label .= " ($t_days d late)";
                elseif (strtotime($t['due_date']) == strtotime('today')) $due_label = 'Today';
                elseif (strtotime($t['due_date']) == strtotime('tomorrow')) $due_label = 'Tomorrow';
            ?>
            <div class="card mb-2 <?= $t_overdue ? 'border-danger' : '' ?>" id="task-card-<?=$t['id']?>">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-<?=$priority_colors[$t['priority']]??'secondary'?> me-2" style="font-size:0.65em"><?=$t['priority']?></span>
                                <a href="task_details.php?id=<?=$t['id']?>" class="fw-bold text-decoration-none">
                                    <?= htmlspecialchars($t['task_title']) ?>
                                </a>
                            </div>
                            <div class="small text-muted">
                                <span class="me-3"><i class="fas fa-hashtag"></i> <?=$t['task_id']?></span>
                                <?php if ($t['pillar_name']): ?><span class="me-3"><i class="fas fa-flag"></i> <?=htmlspecialchars($t['pillar_name'])?></span><?php endif; ?>
                                <span class="me-3 <?=$t_overdue?'text-danger fw-bold':''?>"><i class="fas fa-calendar"></i> <?=$due_label?></span>
                                <?php if ($t['cadence'] !== 'None'): ?><span class="me-3"><i class="fas fa-sync-alt"></i> <?=$t['cadence']?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end" style="min-width:200px">
                            <!-- Quick status change -->
                            <select class="form-select form-select-sm d-inline-block" style="width:130px;font-size:0.75em" onchange="quickStatus(<?=$t['id']?>, this.value)">
                                <?php foreach(['Assigned','In Progress','Blocked','On Hold','Submitted for Review','Completed'] as $st): ?>
                                <option value="<?=$st?>" <?=$t['status']==$st?'selected':''?>><?=$st?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Progress bar -->
                    <div class="d-flex align-items-center mt-1">
                        <div class="progress flex-grow-1 me-2" style="height:8px">
                            <div class="progress-bar <?=$t['progress_pct']>=100?'bg-success':($t_overdue?'bg-danger':'bg-primary')?>" style="width:<?=$t['progress_pct']?>%"></div>
                        </div>
                        <small class="text-muted" style="width:35px"><?=$t['progress_pct']?>%</small>
                        <div class="btn-group btn-group-sm ms-2">
                            <a href="task_details.php?id=<?=$t['id']?>" class="btn btn-outline-secondary py-0" title="Details"><i class="fas fa-eye"></i></a>
                            <a href="task_support_request.php?task_id=<?=$t['id']?>" class="btn btn-outline-warning py-0" title="Request Support"><i class="fas fa-hands-helping"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- RIGHT: Sidebar -->
        <div class="col-md-4">
            <!-- Upcoming Deadlines -->
            <?php if (!empty($upcoming)): ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-clock text-warning"></i> Due This Week</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($upcoming as $u): 
                            $dl = $u['days_until_due'] == 0 ? 'Today' : ($u['days_until_due'] == 1 ? 'Tomorrow' : "In {$u['days_until_due']} days");
                        ?>
                        <li class="list-group-item d-flex justify-content-between py-2">
                            <div>
                                <a href="task_details.php?id=<?=$u['id']?>" class="small text-decoration-none"><?=htmlspecialchars(mb_substr($u['task_title'],0,35))?></a>
                                <br><small class="text-muted"><?=$u['progress_pct']?>% done</small>
                            </div>
                            <span class="badge bg-<?=$u['days_until_due']<=1?'danger':'warning'?> align-self-center"><?=$dl?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-bell text-primary"></i> Notifications <span class="badge bg-danger"><?=count($notifications)?></span></h6></div>
                <div class="card-body p-0" style="max-height:300px;overflow-y:auto">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($notifications as $n): 
                            $icon = match($n['notification_type']) {
                                'reminder' => 'clock text-warning',
                                'overdue' => 'exclamation-triangle text-danger',
                                'escalation' => 'arrow-up text-danger',
                                'assignment' => 'user-plus text-primary',
                                'support_decision' => 'check-circle text-success',
                                default => 'info-circle text-muted'
                            };
                        ?>
                        <li class="list-group-item py-2">
                            <div class="d-flex">
                                <i class="fas fa-<?=$icon?> me-2 mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="small fw-bold"><?=htmlspecialchars($n['subject'])?></div>
                                    <small class="text-muted"><?=date('d M H:i', strtotime($n['created_at']))?></small>
                                </div>
                                <button class="btn btn-sm btn-link text-muted p-0" onclick="dismissNotification(<?=$n['id']?>)" title="Dismiss"><i class="fas fa-times"></i></button>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Links -->
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Quick Actions</h6></div>
                <div class="card-body">
                    <a href="task_dashboard.php" class="btn btn-sm btn-outline-primary d-block mb-2"><i class="fas fa-tachometer-alt"></i> Full Dashboard</a>
                    <a href="task_support_request.php" class="btn btn-sm btn-outline-warning d-block mb-2"><i class="fas fa-hands-helping"></i> New Support Request</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function quickStatus(taskId, status) {
    const fd = new FormData();
    fd.append('ajax_action', 'quick_update');
    fd.append('task_id', taskId);
    fd.append('status', status);
    
    fetch(window.location.href, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if (res.success) {
            // Flash green on the card
            const card = document.getElementById('task-card-' + taskId);
            card.style.transition = 'background 0.3s';
            card.style.background = '#d4edda';
            setTimeout(() => { card.style.background = ''; }, 1000);
            if (status === 'Completed') setTimeout(() => location.reload(), 1200);
        } else {
            alert(res.message);
            location.reload();
        }
    });
}

function dismissNotification(id) {
    const fd = new FormData();
    fd.append('ajax_action', 'mark_notification_read');
    fd.append('notification_id', id);
    fetch(window.location.href, {method:'POST', body:fd}).then(() => location.reload());
}
</script>
<?php require_once 'footer.php'; ?>