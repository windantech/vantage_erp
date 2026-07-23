<?php
ob_start();
require_once 'header.php';
require_once 'includes/task_functions.php';

if (!isset($_SESSION['login_id'])) { header('Location: login.php'); exit; }
$current_user_id = intval($_SESSION['login_id']);
$user_role = tm_get_user_role($conn, $current_user_id);

// Only CEO, Admin, HOD, or Workstream Lead can create tasks
if (!in_array($user_role, ['ceo','admin','hod','workstream_lead'])) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Access denied.'];
    // header('Location: my_tasks.php'); exit;
}

$edit_mode = false;
$task = null;

if (isset($_GET['id'])) {
    $edit_mode = true;
    $task = tm_get_task($conn, intval($_GET['id']));
    if (!$task) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Task not found.'];
        header('Location: task_dashboard.php'); exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'strategy_year' => $_POST['strategy_year'] ?? date('Y'),
        'pillar_id' => !empty($_POST['pillar_id']) ? intval($_POST['pillar_id']) : null,
        'workstream_id' => !empty($_POST['workstream_id']) ? intval($_POST['workstream_id']) : null,
        'phase_id' => !empty($_POST['phase_id']) ? intval($_POST['phase_id']) : null,
        'task_title' => trim($_POST['task_title'] ?? ''),
        'task_description' => trim($_POST['task_description'] ?? ''),
        'deliverable' => trim($_POST['deliverable'] ?? ''),
        'evidence_requirement' => trim($_POST['evidence_requirement'] ?? ''),
        'owner_role' => trim($_POST['owner_role'] ?? ''),
        'owner_id' => intval($_POST['owner_id'] ?? 0),
        'priority' => $_POST['priority'] ?? 'Medium',
        'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
        'due_date' => $_POST['due_date'] ?? '',
        'cadence' => $_POST['cadence'] ?? 'None',
        'recurrence_rules' => trim($_POST['recurrence_rules'] ?? ''),
        'dependencies_other' => trim($_POST['dependencies_other'] ?? ''),
        'budget_kes' => !empty($_POST['budget_kes']) ? floatval($_POST['budget_kes']) : null,
        'kpi_target' => trim($_POST['kpi_target'] ?? ''),
        'kpi_impact_weight' => !empty($_POST['kpi_impact_weight']) ? intval($_POST['kpi_impact_weight']) : null,
        'notes' => trim($_POST['notes'] ?? ''),
        'status' => $_POST['status'] ?? 'Assigned',
    ];
    
    // Watchers
    if (!empty($_POST['watchers'])) {
        $data['watchers'] = json_encode(array_map('intval', $_POST['watchers']));
    }
    
    // Dependencies (task IDs)
    if (!empty($_POST['dependencies_tasks'])) {
        $data['dependencies_tasks'] = json_encode(array_map('intval', $_POST['dependencies_tasks']));
    }
    
    // Support required
    if (!empty($_POST['support_required'])) {
        $data['support_required'] = implode(',', $_POST['support_required']);
    }
    
    // Validation
    $errors = [];
    if (empty($data['task_title'])) $errors[] = 'Task title is required';
    if (empty($data['deliverable'])) $errors[] = 'Deliverable is required';
    if (empty($data['owner_id'])) $errors[] = 'Owner is required';
    if (empty($data['start_date'])) $errors[] = 'Start date is required';
    if (empty($data['due_date'])) $errors[] = 'Due date is required';
    if ($data['start_date'] > $data['due_date']) $errors[] = 'Due date must be after start date';
    
    if (empty($errors)) {
        if ($edit_mode) {
            $reason = trim($_POST['change_reason'] ?? '');
            $result = tm_update_task($conn, $task['id'], $data, $current_user_id, $reason);
            if ($result) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Task updated successfully'];
                header('Location: task_details.php?id=' . $task['id']); exit;
            } else {
                $errors[] = 'Failed to update task: ' . $conn->error;
            }
        } else {
            $new_id = tm_create_task($conn, $data, $current_user_id);
            if ($new_id) {
                // Handle file uploads for evidence
                if (!empty($_FILES['evidence_files']['name'][0])) {
                    $upload_dir = 'uploads/tasks/' . $new_id . '/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    
                    foreach ($_FILES['evidence_files']['name'] as $idx => $fname) {
                        if ($_FILES['evidence_files']['error'][$idx] === UPLOAD_ERR_OK) {
                            $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fname);
                            $dest = $upload_dir . $safe_name;
                            if (move_uploaded_file($_FILES['evidence_files']['tmp_name'][$idx], $dest)) {
                                tm_add_evidence($conn, $new_id, [
                                    'evidence_type' => 'file',
                                    'file_name' => $fname,
                                    'file_path' => $dest,
                                    'file_size' => $_FILES['evidence_files']['size'][$idx],
                                    'mime_type' => $_FILES['evidence_files']['type'][$idx],
                                ], $current_user_id);
                            }
                        }
                    }
                }
                
                // Notify owner if different from creator
                if ($data['owner_id'] != $current_user_id) {
                    $task_code = tm_get_task($conn, $new_id)['task_id'];
                    tm_create_notification($conn, $data['owner_id'], 'assignment',
                        "New Task Assigned: {$data['task_title']}",
                        "You have been assigned task $task_code: {$data['task_title']}. Due: {$data['due_date']}.",
                        $new_id
                    );
                }
                
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Task created successfully'];
                header('Location: task_details.php?id=' . $new_id); exit;
            } else {
                $errors[] = 'Failed to create task: ' . $conn->error;
            }
        }
    }
}

// Load dropdowns
$pillars = tm_get_pillars($conn);
$workstreams = tm_get_workstreams($conn);
$phases = tm_get_phases($conn);
$staff_list = tm_get_staff_list($conn);
$default_priority = tm_get_setting($conn, 'default_priority', 'Medium');
$strategy_year = tm_get_setting($conn, 'strategy_year', date('Y'));

// For dependencies dropdown, get existing tasks
$existing_tasks = [];
$r = $conn->query("SELECT id, task_id, task_title FROM tm_tasks WHERE status NOT IN ('Cancelled') ORDER BY task_id LIMIT 500");
if ($r) while ($row = $r->fetch_assoc()) $existing_tasks[] = $row;
?>

<div class="container-fluid px-4">
    <h4 class="mt-3 mb-3">
        <i class="fas fa-<?= $edit_mode ? 'edit' : 'plus-circle' ?>"></i> 
        <?= $edit_mode ? 'Edit Task: ' . htmlspecialchars($task['task_id']) : 'Create New Task' ?>
    </h4>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="taskForm">
        <div class="row">
            <!-- LEFT COLUMN -->
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Task Details</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Task Title <span class="text-danger">*</span></label>
                            <input type="text" name="task_title" class="form-control" required maxlength="500"
                                   value="<?= htmlspecialchars($task['task_title'] ?? $_POST['task_title'] ?? '') ?>"
                                   placeholder="Start with a verb: e.g., Develop marketing strategy for Q2">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="task_description" class="form-control" rows="3" placeholder="Scope, steps, acceptance criteria..."><?= htmlspecialchars($task['task_description'] ?? $_POST['task_description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Deliverable / What 'Done' Looks Like <span class="text-danger">*</span></label>
                            <textarea name="deliverable" class="form-control" rows="2" required placeholder="Describe what must be delivered..."><?= htmlspecialchars($task['deliverable'] ?? $_POST['deliverable'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Evidence Requirement</label>
                            <textarea name="evidence_requirement" class="form-control" rows="2" placeholder="What must be attached to complete: e.g., signed document, screenshot, report PDF..."><?= htmlspecialchars($task['evidence_requirement'] ?? $_POST['evidence_requirement'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Classification</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Strategy Year</label>
                                <select name="strategy_year" class="form-select">
                                    <?php for($y=2025;$y<=2030;$y++): ?>
                                    <option value="<?=$y?>" <?=($task['strategy_year']??$strategy_year)==$y?'selected':''?>><?=$y?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Pillar</label>
                                <select name="pillar_id" id="pillar_id" class="form-select" onchange="filterWorkstreams()">
                                    <option value="">-- None --</option>
                                    <?php foreach($pillars as $p): ?>
                                    <option value="<?=$p['id']?>" <?=($task['pillar_id']??'')==$p['id']?'selected':''?>><?=htmlspecialchars($p['pillar_name'])?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Workstream</label>
                                <select name="workstream_id" id="workstream_id" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach($workstreams as $w): ?>
                                    <option value="<?=$w['id']?>" data-pillar="<?=$w['pillar_id']?>" <?=($task['workstream_id']??'')==$w['id']?'selected':''?>><?=htmlspecialchars($w['workstream_name'])?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Phase</label>
                                <select name="phase_id" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach($phases as $ph): ?>
                                    <option value="<?=$ph['id']?>" <?=($task['phase_id']??'')==$ph['id']?'selected':''?>><?=htmlspecialchars($ph['phase_name'])?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">KPI / Target</label>
                                <input type="text" name="kpi_target" class="form-control" value="<?=htmlspecialchars($task['kpi_target']??$_POST['kpi_target']??'')?>" placeholder="Which KPI this drives">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">KPI Weight (1-5)</label>
                                <input type="number" name="kpi_impact_weight" class="form-control" min="1" max="5" value="<?=htmlspecialchars($task['kpi_impact_weight']??'')?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Dependencies & Budget</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Blocking Tasks (predecessors)</label>
                                <select name="dependencies_tasks[]" class="form-select" multiple size="4">
                                    <?php 
                                    $dep_tasks = $task ? json_decode($task['dependencies_tasks'] ?? '[]', true) : [];
                                    foreach($existing_tasks as $et): 
                                        if ($edit_mode && $et['id'] == $task['id']) continue;
                                    ?>
                                    <option value="<?=$et['id']?>" <?=in_array($et['id'],$dep_tasks??[])?'selected':''?>><?=$et['task_id']?> - <?=htmlspecialchars(substr($et['task_title'],0,60))?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Hold Ctrl to select multiple</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Other Dependencies</label>
                                <textarea name="dependencies_other" class="form-control" rows="4" placeholder="Systems, approvals, vendors needed..."><?=htmlspecialchars($task['dependencies_other']??'')?></textarea>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Budget (KES)</label>
                                <input type="number" name="budget_kes" class="form-control" step="0.01" value="<?=htmlspecialchars($task['budget_kes']??'')?>" placeholder="Estimated spend">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Support Required</label>
                                <?php 
                                $sup = explode(',', $task['support_required'] ?? '');
                                foreach(['Guidance','Budget','Extension','Tools','Staffing'] as $s): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="support_required[]" value="<?=$s?>" <?=in_array($s,$sup)?'checked':''?>>
                                    <label class="form-check-label"><?=$s?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!$edit_mode): ?>
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Attachments (Optional)</h6></div>
                    <div class="card-body">
                        <input type="file" name="evidence_files[]" class="form-control" multiple>
                        <small class="text-muted">Upload supporting documents, evidence files</small>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($edit_mode): ?>
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Change Reason</h6></div>
                    <div class="card-body">
                        <textarea name="change_reason" class="form-control" rows="2" placeholder="Briefly explain what changed and why..."><?=htmlspecialchars($_POST['change_reason']??'')?></textarea>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- RIGHT COLUMN -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Ownership & Priority</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Owner <span class="text-danger">*</span></label>
                            <select name="owner_id" class="form-select" required>
                                <option value="">-- Select Owner --</option>
                                <?php foreach($staff_list as $s): ?>
                                <option value="<?=$s['id']?>" <?=($task['owner_id']??'')==$s['id']?'selected':''?>><?=htmlspecialchars($s['fullname'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Owner Role Label</label>
                            <input type="text" name="owner_role" class="form-control" value="<?=htmlspecialchars($task['owner_role']??'')?>" placeholder="e.g., Sales Lead, HR Manager">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <?php foreach(['Critical','High','Medium','Low'] as $p): ?>
                                <option value="<?=$p?>" <?=($task['priority']??$default_priority)==$p?'selected':''?>><?=$p?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($edit_mode): ?>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach(['Draft','Assigned','In Progress','Blocked','On Hold','Submitted for Review','Completed','Verified','Cancelled'] as $st): ?>
                                <option value="<?=$st?>" <?=($task['status']??'Assigned')==$st?'selected':''?>><?=$st?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Schedule</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required value="<?=htmlspecialchars($task['start_date']??date('Y-m-d'))?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" required value="<?=htmlspecialchars($task['due_date']??'')?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cadence / Frequency</label>
                            <select name="cadence" class="form-select" id="cadenceSelect" onchange="toggleRecurrence()">
                                <?php foreach(['None','Daily','Weekly','Bi-weekly','Monthly','Quarterly','Semi-annual','Annual','Custom'] as $cad): ?>
                                <option value="<?=$cad?>" <?=($task['cadence']??'None')==$cad?'selected':''?>><?=$cad?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="recurrenceRulesDiv" style="display:none">
                            <label class="form-label">Recurrence Details</label>
                            <input type="text" name="recurrence_rules" class="form-control" value="<?=htmlspecialchars($task['recurrence_rules']??'')?>" placeholder="e.g., every 2 weeks on Monday">
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Watchers</h6></div>
                    <div class="card-body">
                        <select name="watchers[]" class="form-select" multiple size="4">
                            <?php 
                            $w_list = $task ? json_decode($task['watchers'] ?? '[]', true) : [];
                            foreach($staff_list as $s): ?>
                            <option value="<?=$s['id']?>" <?=in_array($s['id'],$w_list??[])?'selected':''?>><?=htmlspecialchars($s['fullname'])?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Notified on changes</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?=htmlspecialchars($task['notes']??'')?></textarea>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> <?= $edit_mode ? 'Update Task' : 'Create Task' ?>
                    </button>
                    <a href="<?= $edit_mode ? 'task_details.php?id='.$task['id'] : 'task_dashboard.php' ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function filterWorkstreams() {
    const pid = document.getElementById('pillar_id').value;
    const ws = document.getElementById('workstream_id');
    Array.from(ws.options).forEach(opt => {
        if (opt.value === '') return;
        opt.style.display = (!pid || opt.dataset.pillar === pid) ? '' : 'none';
    });
    // Reset if current selection is hidden
    if (ws.selectedOptions[0] && ws.selectedOptions[0].style.display === 'none') ws.value = '';
}

function toggleRecurrence() {
    const cad = document.getElementById('cadenceSelect').value;
    document.getElementById('recurrenceRulesDiv').style.display = cad === 'None' ? 'none' : '';
}

// Init on load
filterWorkstreams();
toggleRecurrence();
</script>
<?php require_once 'footer.php'; ?>