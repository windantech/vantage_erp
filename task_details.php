<?php
ob_start();
require_once 'header.php';
require_once 'includes/task_functions.php';

if (!isset($_SESSION['login_id'])) { header('Location: login.php'); exit; }
$current_user_id = intval($_SESSION['login_id']);
$user_role = tm_get_user_role($conn, $current_user_id);

$task_id = intval($_GET['id'] ?? 0);
if (!$task_id) { header('Location: task_dashboard.php'); exit; }

$task = tm_get_task($conn, $task_id);
if (!$task) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Task not found'];
    header('Location: task_dashboard.php'); exit;
}

$is_owner = ($task['owner_id'] == $current_user_id);
$has_full = tm_has_full_access($conn, $current_user_id);
$is_overdue = ($task['status'] != 'Completed' && $task['status'] != 'Verified' && $task['status'] != 'Cancelled' && strtotime($task['due_date']) < strtotime('today'));
$days_overdue = $is_overdue ? floor((time() - strtotime($task['due_date'])) / 86400) : 0;

// Handle AJAX quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false];
    
    switch ($_POST['ajax_action']) {
        case 'update_status':
            $new_status = $_POST['status'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            
            // Check evidence requirement for completion
            if (in_array($new_status, ['Completed','Submitted for Review'])) {
                $require_evidence = tm_get_setting($conn, 'require_evidence_for_completion', '1');
                if ($require_evidence == '1' && !empty($task['evidence_requirement'])) {
                    $evidence = tm_get_evidence($conn, $task_id);
                    if (empty($evidence)) {
                        $response = ['success' => false, 'message' => 'Evidence is required before marking complete. Please upload evidence first.'];
                        break;
                    }
                }
            }
            
            tm_update_task($conn, $task_id, ['status' => $new_status], $current_user_id, $reason);
            $response = ['success' => true, 'message' => "Status updated to $new_status"];
            break;
            
        case 'update_progress':
            $pct = intval($_POST['progress_pct'] ?? 0);
            $note = trim($_POST['note'] ?? '');
            tm_update_task($conn, $task_id, ['progress_pct' => min(100, max(0, $pct))], $current_user_id);
            if ($note) tm_log_activity($conn, $task_id, 'comment', $note, null, null, null, $current_user_id);
            $response = ['success' => true, 'message' => 'Progress updated'];
            break;
            
        case 'add_comment':
            $comment = trim($_POST['comment'] ?? '');
            if ($comment) {
                tm_log_activity($conn, $task_id, 'comment', $comment, null, null, null, $current_user_id);
                $response = ['success' => true, 'message' => 'Comment added'];
            }
            break;
            
        case 'upload_evidence':
            if (!empty($_FILES['evidence_file']['name'])) {
                $upload_dir = 'uploads/tasks/' . $task_id . '/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $fname = $_FILES['evidence_file']['name'];
                $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fname);
                $dest = $upload_dir . $safe;
                
                if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $dest)) {
                    tm_add_evidence($conn, $task_id, [
                        'evidence_type' => 'file', 'file_name' => $fname, 'file_path' => $dest,
                        'file_size' => $_FILES['evidence_file']['size'], 'mime_type' => $_FILES['evidence_file']['type'],
                        'description' => trim($_POST['evidence_desc'] ?? '')
                    ], $current_user_id);
                    $response = ['success' => true, 'message' => 'Evidence uploaded'];
                }
            } elseif (!empty($_POST['evidence_link'])) {
                tm_add_evidence($conn, $task_id, [
                    'evidence_type' => 'link', 'link_url' => trim($_POST['evidence_link']),
                    'description' => trim($_POST['evidence_desc'] ?? '')
                ], $current_user_id);
                $response = ['success' => true, 'message' => 'Link added'];
            } elseif (!empty($_POST['evidence_note'])) {
                tm_add_evidence($conn, $task_id, [
                    'evidence_type' => 'note', 'note_text' => trim($_POST['evidence_note']),
                    'description' => trim($_POST['evidence_desc'] ?? '')
                ], $current_user_id);
                $response = ['success' => true, 'message' => 'Note added'];
            }
            break;
            
        case 'submit_overdue_explanation':
            $exp_data = [
                'task_id' => $task_id,
                'reason_category' => $conn->real_escape_string($_POST['reason_category'] ?? ''),
                'explanation' => $conn->real_escape_string(trim($_POST['explanation'] ?? '')),
                'corrective_action' => $conn->real_escape_string(trim($_POST['corrective_action'] ?? '')),
                'new_eta' => $conn->real_escape_string($_POST['new_eta'] ?? ''),
                'support_needed' => $conn->real_escape_string(trim($_POST['support_needed'] ?? '')),
                'submitted_by' => $current_user_id
            ];
            
            $conn->query("INSERT INTO tm_overdue_explanations (task_id, reason_category, explanation, corrective_action, new_eta, support_needed, submitted_by) 
                          VALUES ({$exp_data['task_id']}, '{$exp_data['reason_category']}', '{$exp_data['explanation']}', '{$exp_data['corrective_action']}', '{$exp_data['new_eta']}', '{$exp_data['support_needed']}', {$exp_data['submitted_by']})");
            
            tm_log_activity($conn, $task_id, 'overdue_explanation', "Overdue explanation submitted: {$exp_data['reason_category']}. New ETA: {$exp_data['new_eta']}", null, null, null, $current_user_id);
            $conn->query("UPDATE tm_tasks SET overdue_explanation_required = 0 WHERE id = $task_id");
            $response = ['success' => true, 'message' => 'Explanation submitted'];
            break;
    }
    
    echo json_encode($response); exit;
}

// Load related data
$evidence = tm_get_evidence($conn, $task_id);
$activity = tm_get_activity($conn, $task_id);
$pillars = tm_get_pillars($conn);
$staff_list = tm_get_staff_list($conn);

// Dependencies
$dep_tasks = json_decode($task['dependencies_tasks'] ?? '[]', true) ?: [];
$dep_details = [];
if (!empty($dep_tasks)) {
    $ids = implode(',', array_map('intval', $dep_tasks));
    $r = $conn->query("SELECT id, task_id, task_title, status, due_date FROM tm_tasks WHERE id IN ($ids)");
    if ($r) while ($row = $r->fetch_assoc()) $dep_details[] = $row;
}

// Support requests for this task
$support_requests = [];
$r = $conn->query("SELECT sr.*, u.fullname AS requester_name FROM tm_support_requests sr LEFT JOIN registered_users u ON sr.requested_by = u.id WHERE sr.task_id = $task_id ORDER BY sr.created_at DESC");
if ($r) while ($row = $r->fetch_assoc()) $support_requests[] = $row;

// Overdue explanations
$overdue_explanations = [];
$r = $conn->query("SELECT oe.*, u.fullname AS submitted_by_name FROM tm_overdue_explanations oe LEFT JOIN registered_users u ON oe.submitted_by = u.id WHERE oe.task_id = $task_id ORDER BY oe.submitted_at DESC");
if ($r) while ($row = $r->fetch_assoc()) $overdue_explanations[] = $row;

// Priority colors
$priority_colors = ['Critical'=>'danger','High'=>'warning','Medium'=>'info','Low'=>'secondary'];
$status_colors = ['Draft'=>'secondary','Assigned'=>'primary','In Progress'=>'info','Blocked'=>'danger','On Hold'=>'warning','Submitted for Review'=>'purple','Completed'=>'success','Verified'=>'success','Cancelled'=>'dark'];
?>

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mt-3 mb-3">
        <div>
            <h5 class="mb-1">
                <span class="text-muted"><?= htmlspecialchars($task['task_id']) ?></span>
                <?= htmlspecialchars($task['task_title']) ?>
            </h5>
            <div>
                <span class="badge bg-<?= $priority_colors[$task['priority']] ?? 'secondary' ?>"><?= $task['priority'] ?></span>
                <span class="badge bg-<?= $status_colors[$task['status']] ?? 'secondary' ?>"><?= $task['status'] ?></span>
                <?php if ($is_overdue): ?>
                <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> <?= $days_overdue ?> days overdue</span>
                <?php endif; ?>
                <?php if ($task['pillar_name']): ?>
                <span class="badge" style="background:<?= $task['pillar_color'] ?? '#6c757d' ?>"><?= htmlspecialchars($task['pillar_name']) ?></span>
                <?php endif; ?>
                <?php if ($task['workstream_name']): ?>
                <span class="badge bg-outline-secondary border"><?= htmlspecialchars($task['workstream_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <?php if ($has_full || $is_owner): ?>
            <a href="task_form.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
            <?php endif; ?>
            <a href="task_dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
    
    <?php if ($is_overdue && $is_owner && $task['overdue_explanation_required']): ?>
    <!-- Overdue Alert -->
    <div class="alert alert-danger">
        <strong><i class="fas fa-exclamation-triangle"></i> This task is overdue!</strong> You must provide an explanation and corrective action before continuing.
        <button class="btn btn-sm btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#overdueModal">Provide Explanation</button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- LEFT: Main Content -->
        <div class="col-md-8">
            <!-- Summary -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Owner:</strong> <?= htmlspecialchars($task['owner_name'] ?? 'Unassigned') ?> <?= $task['owner_role'] ? '(' . htmlspecialchars($task['owner_role']) . ')' : '' ?></p>
                            <p><strong>Start:</strong> <?= date('d M Y', strtotime($task['start_date'])) ?></p>
                            <p><strong>Due:</strong> <span class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>"><?= date('d M Y', strtotime($task['due_date'])) ?></span></p>
                            <?php if ($task['cadence'] !== 'None'): ?>
                            <p><strong>Cadence:</strong> <?= $task['cadence'] ?> <?= $task['recurrence_rules'] ? '(' . htmlspecialchars($task['recurrence_rules']) . ')' : '' ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if ($task['budget_kes']): ?>
                            <p><strong>Budget:</strong> KES <?= number_format($task['budget_kes'], 2) ?></p>
                            <?php endif; ?>
                            <?php if ($task['kpi_target']): ?>
                            <p><strong>KPI:</strong> <?= htmlspecialchars($task['kpi_target']) ?></p>
                            <?php endif; ?>
                            <p><strong>Progress:</strong></p>
                            <div class="progress mb-2" style="height:20px">
                                <div class="progress-bar <?= $task['progress_pct']>=100?'bg-success':($is_overdue?'bg-danger':'bg-primary') ?>" style="width:<?= $task['progress_pct'] ?>%"><?= $task['progress_pct'] ?>%</div>
                            </div>
                        </div>
                    </div>
                    <?php if ($task['task_description']): ?>
                    <hr><p><strong>Description:</strong></p><p><?= nl2br(htmlspecialchars($task['task_description'])) ?></p>
                    <?php endif; ?>
                    <hr>
                    <p><strong>Deliverable:</strong></p><p><?= nl2br(htmlspecialchars($task['deliverable'])) ?></p>
                    <?php if ($task['evidence_requirement']): ?>
                    <p><strong>Evidence Required:</strong></p><p class="text-muted"><?= nl2br(htmlspecialchars($task['evidence_requirement'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions (for owner) -->
            <?php if ($is_owner || $has_full): ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-bolt"></i> Quick Update</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label small fw-bold">Status</label>
                            <select id="quickStatus" class="form-select form-select-sm">
                                <?php foreach(['Assigned','In Progress','Blocked','On Hold','Submitted for Review','Completed'] as $st): ?>
                                <option value="<?=$st?>" <?=$task['status']==$st?'selected':''?>><?=$st?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label small fw-bold">Progress %</label>
                            <input type="range" id="quickProgress" class="form-range" min="0" max="100" step="5" value="<?=$task['progress_pct']?>" oninput="document.getElementById('progLabel').textContent=this.value+'%'">
                            <span id="progLabel" class="small"><?=$task['progress_pct']?>%</span>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label small fw-bold">Note</label>
                            <input type="text" id="quickNote" class="form-control form-control-sm" placeholder="Quick update note...">
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2" onclick="quickUpdate()"><i class="fas fa-save"></i> Save Update</button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Evidence Section -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0"><i class="fas fa-paperclip"></i> Evidence & Attachments (<?= count($evidence) ?>)</h6>
                    <?php if ($is_owner || $has_full): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#evidenceModal"><i class="fas fa-plus"></i> Add</button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($evidence)): ?>
                    <p class="text-muted text-center py-3">No evidence uploaded yet</p>
                    <?php else: foreach ($evidence as $ev): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <?php if ($ev['evidence_type'] === 'file'): ?>
                            <i class="fas fa-file text-primary"></i> <a href="<?= htmlspecialchars($ev['file_path']) ?>" target="_blank"><?= htmlspecialchars($ev['file_name']) ?></a>
                            <small class="text-muted">(<?= round(($ev['file_size']??0)/1024) ?> KB)</small>
                            <?php elseif ($ev['evidence_type'] === 'link'): ?>
                            <i class="fas fa-link text-info"></i> <a href="<?= htmlspecialchars($ev['link_url']) ?>" target="_blank"><?= htmlspecialchars($ev['link_url']) ?></a>
                            <?php else: ?>
                            <i class="fas fa-sticky-note text-warning"></i> <?= htmlspecialchars(substr($ev['note_text'],0,100)) ?>
                            <?php endif; ?>
                            <?php if ($ev['description']): ?><br><small class="text-muted"><?= htmlspecialchars($ev['description']) ?></small><?php endif; ?>
                        </div>
                        <small class="text-muted"><?= htmlspecialchars($ev['uploaded_by_name']) ?><br><?= date('d M H:i', strtotime($ev['uploaded_at'])) ?></small>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            
            <!-- Activity Feed -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0"><i class="fas fa-history"></i> Activity Log</h6>
                </div>
                <div class="card-body" style="max-height:400px;overflow-y:auto">
                    <!-- Add comment -->
                    <div class="d-flex mb-3">
                        <input type="text" id="newComment" class="form-control form-control-sm me-2" placeholder="Add a comment...">
                        <button class="btn btn-sm btn-outline-primary" onclick="addComment()">Post</button>
                    </div>
                    <?php foreach ($activity as $act): 
                        $icon = match($act['activity_type']) {
                            'comment' => 'comment text-primary',
                            'status_change' => 'exchange-alt text-success',
                            'priority_change' => 'flag text-warning',
                            'date_change' => 'calendar-alt text-info',
                            'owner_change' => 'user-edit text-purple',
                            'evidence_upload' => 'paperclip text-success',
                            'escalation' => 'exclamation-triangle text-danger',
                            'overdue_explanation' => 'file-alt text-warning',
                            default => 'info-circle text-muted'
                        };
                    ?>
                    <div class="d-flex mb-2 pb-2 border-bottom">
                        <div class="me-3"><i class="fas fa-<?= $icon ?>"></i></div>
                        <div class="flex-grow-1">
                            <div class="small"><?= nl2br(htmlspecialchars($act['description'])) ?></div>
                            <?php if ($act['reason']): ?><div class="small text-muted fst-italic">Reason: <?= htmlspecialchars($act['reason']) ?></div><?php endif; ?>
                            <div class="small text-muted"><?= htmlspecialchars($act['performed_by_name'] ?? 'System') ?> • <?= date('d M Y H:i', strtotime($act['performed_at'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($activity)): ?><p class="text-muted text-center">No activity yet</p><?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- RIGHT: Sidebar -->
        <div class="col-md-4">
            <!-- Dependencies -->
            <?php if (!empty($dep_details) || $task['dependencies_other']): ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-project-diagram"></i> Dependencies</h6></div>
                <div class="card-body">
                    <?php foreach ($dep_details as $dep): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <a href="task_details.php?id=<?= $dep['id'] ?>" class="small"><?= $dep['task_id'] ?>: <?= htmlspecialchars(substr($dep['task_title'],0,40)) ?></a>
                        <span class="badge bg-<?= $status_colors[$dep['status']] ?? 'secondary' ?> small"><?= $dep['status'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($task['dependencies_other']): ?>
                    <hr><p class="small text-muted"><?= nl2br(htmlspecialchars($task['dependencies_other'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Support Requests -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0"><i class="fas fa-hands-helping"></i> Support Requests</h6>
                    <?php if ($is_owner || $has_full): ?>
                    <a href="task_support_request.php?task_id=<?= $task['id'] ?>" class="btn btn-sm btn-outline-warning">Request</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($support_requests)): ?>
                    <p class="text-muted small text-center">No support requests</p>
                    <?php else: foreach ($support_requests as $sr): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-info small"><?= $sr['request_type'] ?></span>
                            <span class="badge bg-<?= $sr['approval_status']==='Approved'?'success':($sr['approval_status']==='Rejected'?'danger':'warning') ?>"><?= $sr['approval_status'] ?></span>
                        </div>
                        <p class="small mb-0 mt-1"><?= htmlspecialchars(substr($sr['description'],0,80)) ?>...</p>
                        <small class="text-muted"><?= date('d M', strtotime($sr['created_at'])) ?></small>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            
            <!-- Overdue Explanations -->
            <?php if (!empty($overdue_explanations)): ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-file-alt"></i> Overdue Explanations</h6></div>
                <div class="card-body">
                    <?php foreach ($overdue_explanations as $oe): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <span class="badge bg-warning"><?= $oe['reason_category'] ?></span>
                        <span class="badge bg-<?= $oe['review_status']==='Acknowledged'?'success':'secondary' ?>"><?= $oe['review_status'] ?></span>
                        <p class="small mt-1 mb-0"><?= htmlspecialchars(substr($oe['explanation'],0,100)) ?></p>
                        <small class="text-muted">New ETA: <?= date('d M Y', strtotime($oe['new_eta'])) ?> | <?= htmlspecialchars($oe['submitted_by_name']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Meta Info -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Details</h6></div>
                <div class="card-body small">
                    <p><strong>Created:</strong> <?= date('d M Y H:i', strtotime($task['created_at'])) ?></p>
                    <p><strong>Updated:</strong> <?= date('d M Y H:i', strtotime($task['updated_at'])) ?></p>
                    <?php if ($task['import_batch_id']): ?><p><strong>Import Batch:</strong> #<?= $task['import_batch_id'] ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Evidence Upload Modal -->
<div class="modal fade" id="evidenceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add Evidence</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <ul class="nav nav-pills mb-3">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#evFile">File</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#evLink">Link</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#evNote">Note</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="evFile"><input type="file" id="ev_file" class="form-control"></div>
            <div class="tab-pane fade" id="evLink"><input type="url" id="ev_link" class="form-control" placeholder="https://..."></div>
            <div class="tab-pane fade" id="evNote"><textarea id="ev_note" class="form-control" rows="3" placeholder="Evidence note..."></textarea></div>
        </div>
        <div class="mt-3"><input type="text" id="ev_desc" class="form-control" placeholder="Description (optional)"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="uploadEvidence()">Upload</button></div>
</div></div></div>

<!-- Overdue Explanation Modal -->
<div class="modal fade" id="overdueModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-danger text-white"><h5 class="modal-title">Overdue Explanation Required</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label fw-bold">Reason Category *</label>
            <select id="oe_reason" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach(['Waiting Approval','Budget Delay','Capacity','External Delay','Unclear Scope','Tools/Resources','Staffing','Other'] as $rc): ?>
                <option value="<?=$rc?>"><?=$rc?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label fw-bold">Explanation *</label><textarea id="oe_explanation" class="form-control" rows="3" required></textarea></div>
        <div class="mb-3"><label class="form-label fw-bold">Corrective Action *</label><textarea id="oe_corrective" class="form-control" rows="2" required></textarea></div>
        <div class="mb-3"><label class="form-label fw-bold">New ETA *</label><input type="date" id="oe_eta" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Support Needed</label><textarea id="oe_support" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" onclick="submitOverdueExplanation()">Submit</button></div>
</div></div></div>

<script>
function ajaxPost(data, cb) {
    const fd = new FormData(); for(const k in data) fd.append(k, data[k]);
    fetch(window.location.href,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        if(res.success){if(cb)cb(res);else location.reload();}
        else alert(res.message||'Error');
    });
}

function quickUpdate() {
    const status = document.getElementById('quickStatus').value;
    const progress = document.getElementById('quickProgress').value;
    const note = document.getElementById('quickNote').value;
    
    // Update status first, then progress
    ajaxPost({ajax_action:'update_status',status:status,reason:note||'Quick update'}, ()=>{
        ajaxPost({ajax_action:'update_progress',progress_pct:progress,note:note});
    });
}

function addComment() {
    const c = document.getElementById('newComment').value.trim();
    if(!c) return;
    ajaxPost({ajax_action:'add_comment',comment:c});
}

function uploadEvidence() {
    const fd = new FormData();
    fd.append('ajax_action','upload_evidence');
    fd.append('evidence_desc',document.getElementById('ev_desc').value);
    
    const file = document.getElementById('ev_file').files[0];
    const link = document.getElementById('ev_link').value;
    const note = document.getElementById('ev_note').value;
    
    if(file) fd.append('evidence_file',file);
    else if(link) fd.append('evidence_link',link);
    else if(note) fd.append('evidence_note',note);
    else {alert('Add a file, link, or note');return;}
    
    fetch(window.location.href,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        if(res.success)location.reload(); else alert(res.message);
    });
}

function submitOverdueExplanation() {
    ajaxPost({
        ajax_action:'submit_overdue_explanation',
        reason_category:document.getElementById('oe_reason').value,
        explanation:document.getElementById('oe_explanation').value,
        corrective_action:document.getElementById('oe_corrective').value,
        new_eta:document.getElementById('oe_eta').value,
        support_needed:document.getElementById('oe_support').value
    });
}
</script>
<?php require_once 'footer.php'; ?>