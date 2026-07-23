<?php
/**
 * Task Dashboard - Main Task Listing
 * Filtered view with stats, search, and table
 */
require_once 'header.php';
require_once 'includes/task_functions.php';

if (!isset($_SESSION['login_id'])) { header('Location: login.php'); exit; }
$current_user_id = intval($_SESSION['login_id']);
$user_role = tm_get_user_role($conn, $current_user_id);

// Build filters from GET params
$filters = [];
$strategy_year = tm_get_setting($conn, 'strategy_year', date('Y'));
$filters['strategy_year'] = intval($_GET['year'] ?? $strategy_year);
if (!empty($_GET['pillar_id'])) $filters['pillar_id'] = intval($_GET['pillar_id']);
if (!empty($_GET['workstream_id'])) $filters['workstream_id'] = intval($_GET['workstream_id']);
if (!empty($_GET['owner_id'])) $filters['owner_id'] = intval($_GET['owner_id']);
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
if (isset($_GET['overdue']) && $_GET['overdue']) $filters['is_overdue'] = true;

// Scope based on role
if ($user_role === 'staff') {
    $filters['owner_id'] = $current_user_id;
} elseif ($user_role === 'workstream_lead') {
    $led = tm_get_led_workstreams($conn, $current_user_id);
    if (!empty($led) && empty($filters['workstream_id'])) {
        $filters['workstream_ids'] = $led;
    }
}

// Get data
$tasks = tm_get_tasks($conn, $filters, 200);
$stats = tm_get_stats($conn, $filters);
$pillars = tm_get_pillars($conn);
$workstreams = tm_get_workstreams($conn);
$staff_list = tm_get_staff_list($conn);
$notif_count = tm_count_unread_notifications($conn, $current_user_id);

$priority_colors = ['Critical'=>'danger','High'=>'warning','Medium'=>'info','Low'=>'secondary'];
$status_colors = ['Draft'=>'secondary','Assigned'=>'primary','In Progress'=>'info','Blocked'=>'danger','On Hold'=>'warning','Submitted for Review'=>'purple','Completed'=>'success','Verified'=>'success','Cancelled'=>'dark'];
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h4 class="mb-0"><i class="fas fa-tasks"></i> Task Manager</h4>
        <div>
            <?php if ($notif_count > 0): ?>
            <span class="badge bg-danger me-2"><i class="fas fa-bell"></i> <?= $notif_count ?> notifications</span>
            <?php endif; ?>
            <?php if (in_array($user_role, ['ceo','admin','hod','workstream_lead'])): ?>
            <a href="task_form.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> New Task</a>
            <a href="task_import.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-import"></i> Import</a>
            <?php endif; ?>
            <?php if (tm_has_full_access($conn, $current_user_id)): ?>
            <a href="task_settings.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-cog"></i></a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-3">
        <div class="col"><div class="card bg-light"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['total']?></h4><small class="text-muted">Total</small></div></div></div>
        <div class="col"><div class="card bg-success text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=($stats['completed']??0)+($stats['verified']??0)?></h4><small>Done</small></div></div></div>
        <div class="col"><div class="card bg-info text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['in_progress']??0?></h4><small>In Progress</small></div></div></div>
        <div class="col"><div class="card bg-danger text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['overdue']?></h4><small>Overdue</small></div></div></div>
        <div class="col"><div class="card bg-warning"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['blocked']??0?></h4><small>Blocked</small></div></div></div>
        <div class="col"><div class="card border-primary"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$stats['completion_pct']?>%</h4><small class="text-muted">Complete</small></div></div></div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search tasks..." value="<?=htmlspecialchars($_GET['search']??'')?>">
                </div>
                <div class="col-md-2">
                    <select name="pillar_id" class="form-select form-select-sm">
                        <option value="">All Pillars</option>
                        <?php foreach($pillars as $p):?><option value="<?=$p['id']?>" <?=($_GET['pillar_id']??'')==$p['id']?'selected':''?>><?=htmlspecialchars($p['pillar_name'])?></option><?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="workstream_id" class="form-select form-select-sm">
                        <option value="">All Workstreams</option>
                        <?php foreach($workstreams as $w):?><option value="<?=$w['id']?>" <?=($_GET['workstream_id']??'')==$w['id']?'selected':''?>><?=htmlspecialchars($w['workstream_name'])?></option><?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-1">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Status</option>
                        <?php foreach(['Assigned','In Progress','Blocked','On Hold','Completed','Verified'] as $st):?>
                        <option value="<?=$st?>" <?=($_GET['status']??'')==$st?'selected':''?>><?=$st?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-1">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">Priority</option>
                        <?php foreach(['Critical','High','Medium','Low'] as $p):?>
                        <option value="<?=$p?>" <?=($_GET['priority']??'')==$p?'selected':''?>><?=$p?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <?php if ($user_role !== 'staff'): ?>
                <div class="col-md-2">
                    <select name="owner_id" class="form-select form-select-sm">
                        <option value="">All Owners</option>
                        <?php foreach($staff_list as $s):?><option value="<?=$s['id']?>" <?=($_GET['owner_id']??'')==$s['id']?'selected':''?>><?=htmlspecialchars($s['fullname'])?></option><?php endforeach;?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-1">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="overdue" value="1" <?=!empty($_GET['overdue'])?'checked':''?> id="chkOverdue"><label class="form-check-label small" for="chkOverdue">Overdue</label></div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                    <a href="task_dashboard.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tasks Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Task</th>
                            <th>Pillar</th>
                            <th>Owner</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $t): 
                            $t_overdue = ($t['computed_is_overdue'] ?? 0) == 1;
                            $t_days = $t['computed_days_overdue'] ?? 0;
                        ?>
                        <tr class="<?= $t_overdue ? 'table-danger' : '' ?>">
                            <td class="small"><?= htmlspecialchars($t['task_id']) ?></td>
                            <td>
                                <a href="task_details.php?id=<?=$t['id']?>" class="text-decoration-none fw-bold">
                                    <?= htmlspecialchars(mb_substr($t['task_title'], 0, 60)) ?><?= mb_strlen($t['task_title'])>60?'...':'' ?>
                                </a>
                                <?php if($t['cadence']!=='None'):?><small class="badge bg-light text-dark"><i class="fas fa-sync-alt"></i> <?=$t['cadence']?></small><?php endif;?>
                            </td>
                            <td>
                                <?php if($t['pillar_name']):?><span class="badge" style="background:<?=$t['pillar_color']??'#6c757d'?>;font-size:0.7em"><?=htmlspecialchars($t['pillar_name'])?></span><?php endif;?>
                            </td>
                            <td class="small"><?=htmlspecialchars($t['owner_name']??'-')?></td>
                            <td><span class="badge bg-<?=$priority_colors[$t['priority']]??'secondary'?>"><?=$t['priority']?></span></td>
                            <td class="small <?=$t_overdue?'text-danger fw-bold':''?>">
                                <?=date('d M Y',strtotime($t['due_date']))?>
                                <?php if($t_overdue):?><br><small><?=$t_days?>d late</small><?php endif;?>
                            </td>
                            <td style="min-width:80px">
                                <div class="progress" style="height:16px">
                                    <div class="progress-bar <?=$t['progress_pct']>=100?'bg-success':($t_overdue?'bg-danger':'bg-primary')?>" style="width:<?=$t['progress_pct']?>%">
                                        <small><?=$t['progress_pct']?>%</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-<?=$status_colors[$t['status']]??'secondary'?>" style="font-size:0.7em"><?=$t['status']?></span></td>
                            <td>
                                <a href="task_details.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="View"><i class="fas fa-eye"></i></a>
                                <?php if(tm_has_full_access($conn,$current_user_id) || $t['owner_id']==$current_user_id):?>
                                <a href="task_form.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif;?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($tasks)):?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No tasks found. <?=in_array($user_role,['ceo','admin'])?'<a href="task_import.php">Import tasks</a> or <a href="task_form.php">create one</a>.':''?></td></tr>
                        <?php endif;?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-muted small">
            Showing <?=count($tasks)?> tasks | Year: <?=$filters['strategy_year']?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>