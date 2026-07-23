<?php
ob_start();
require_once 'header.php';
require_once 'includes/task_functions.php';

if (!isset($_SESSION['login_id'])) { header('Location: login.php'); exit; }
$current_user_id = intval($_SESSION['login_id']);
$user_role = tm_get_user_role($conn, $current_user_id);
$has_full = tm_has_full_access($conn, $current_user_id);

$staff_list = tm_get_staff_list($conn);
$ceo_id = tm_get_ceo_id($conn);

// Pre-select task if passed
$preselect_task_id = intval($_GET['task_id'] ?? 0);
$preselect_task = null;
if ($preselect_task_id) {
    $preselect_task = tm_get_task($conn, $preselect_task_id);
}

// ---- VIEW: Single support request detail ----
if (isset($_GET['view_id'])) {
    $sr_id = intval($_GET['view_id']);
    $sr = null;
    $r = $conn->query("SELECT sr.*, t.task_id AS task_code, t.task_title, t.owner_id AS task_owner_id,
                        req.fullname AS requester_name, req.email AS requester_email,
                        app.fullname AS approver_name,
                        hod_u.fullname AS hod_endorsed_by_name,
                        approved_u.fullname AS approved_by_name
                        FROM tm_support_requests sr 
                        JOIN tm_tasks t ON sr.task_id = t.id
                        LEFT JOIN registered_users req ON sr.requested_by = req.id
                        LEFT JOIN registered_users app ON sr.approver_id = app.id
                        LEFT JOIN registered_users hod_u ON sr.hod_endorsed_by = hod_u.id
                        LEFT JOIN registered_users approved_u ON sr.approved_by = approved_u.id
                        WHERE sr.id = $sr_id LIMIT 1");
    if ($r) $sr = $r->fetch_assoc();
    if (!$sr) { $_SESSION['message']=['type'=>'danger','text'=>'Not found']; header('Location: task_support_request.php'); exit; }
    
    // Attachments
    $attachments = [];
    $r = $conn->query("SELECT sa.*, u.fullname AS uploaded_by_name FROM tm_support_attachments sa LEFT JOIN registered_users u ON sa.uploaded_by = u.id WHERE sa.support_request_id = $sr_id ORDER BY sa.uploaded_at DESC");
    if ($r) while ($row = $r->fetch_assoc()) $attachments[] = $row;
    
    // Handle approval actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approval_action'])) {
        $action = $_POST['approval_action'];
        $notes = $conn->real_escape_string(trim($_POST['approval_notes'] ?? ''));
        
        if ($action === 'endorse') {
            $conn->query("UPDATE tm_support_requests SET hod_endorsement = 'Endorsed', hod_endorsed_by = $current_user_id, hod_endorsed_at = NOW(), hod_notes = '$notes' WHERE id = $sr_id");
            tm_log_activity($conn, $sr['task_id'], 'support_request', "Support request {$sr['request_id']} endorsed by HOD", null, null, null, $current_user_id);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Endorsed successfully'];
        } elseif ($action === 'approve') {
            $conn->query("UPDATE tm_support_requests SET approval_status = 'Approved', approved_by = $current_user_id, approved_at = NOW(), approval_notes = '$notes' WHERE id = $sr_id");
            tm_log_activity($conn, $sr['task_id'], 'support_request', "Support request {$sr['request_id']} approved", null, null, null, $current_user_id);
            
            // If extension approved, update task due date
            if ($sr['request_type'] === 'Extension' && $sr['requested_extension_date']) {
                $new_date = $sr['requested_extension_date'];
                tm_update_task($conn, $sr['task_id'], ['due_date' => $new_date], $current_user_id, "Extension approved via support request {$sr['request_id']}");
            }
            
            // Notify requester
            tm_create_notification($conn, $sr['requested_by'], 'support_decision', "Support Request Approved: {$sr['request_type']}", "Your {$sr['request_type']} request for task {$sr['task_code']} has been approved.", $sr['task_id'], $sr_id);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Approved'];
        } elseif ($action === 'reject') {
            $conn->query("UPDATE tm_support_requests SET approval_status = 'Rejected', approved_by = $current_user_id, approved_at = NOW(), approval_notes = '$notes' WHERE id = $sr_id");
            tm_log_activity($conn, $sr['task_id'], 'support_request', "Support request {$sr['request_id']} rejected: $notes", null, null, null, $current_user_id);
            tm_create_notification($conn, $sr['requested_by'], 'support_decision', "Support Request Rejected: {$sr['request_type']}", "Your {$sr['request_type']} request for task {$sr['task_code']} has been rejected. Reason: $notes", $sr['task_id'], $sr_id);
            $_SESSION['message'] = ['type' => 'warning', 'text' => 'Rejected'];
        } elseif ($action === 'need_info') {
            $conn->query("UPDATE tm_support_requests SET approval_status = 'Need Info', approval_notes = '$notes' WHERE id = $sr_id");
            tm_create_notification($conn, $sr['requested_by'], 'support_decision', "More Info Needed: {$sr['request_type']}", "More information requested for your {$sr['request_type']} support request: $notes", $sr['task_id'], $sr_id);
            $_SESSION['message'] = ['type' => 'info', 'text' => 'Requested more info'];
        } elseif ($action === 'fulfill') {
            $conn->query("UPDATE tm_support_requests SET fulfillment_status = 'Fulfilled', fulfilled_at = NOW(), fulfillment_notes = '$notes' WHERE id = $sr_id");
            tm_create_notification($conn, $sr['requested_by'], 'support_decision', "Support Fulfilled: {$sr['request_type']}", "Your {$sr['request_type']} request has been fulfilled.", $sr['task_id'], $sr_id);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Marked as fulfilled'];
        }
        
        header("Location: task_support_request.php?view_id=$sr_id"); exit;
    }
    
    $type_colors = ['Guidance'=>'info','Budget'=>'success','Tools'=>'primary','Extension'=>'warning','Staffing'=>'secondary','Remove Blocker'=>'danger'];
    $approval_colors = ['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger','Need Info'=>'info'];
    
    // Check if user can approve
    $can_approve = $has_full;
    // HOD can endorse
    $can_endorse = false;
    if (!$has_full && tm_is_hod($conn, $current_user_id)) {
        $can_endorse = true;
    }
    ?>
    
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
            <h4><i class="fas fa-hands-helping"></i> Support Request: <?=htmlspecialchars($sr['request_id'])?></h4>
            <a href="task_support_request.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> All Requests</a>
        </div>
        
        <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?=$_SESSION['message']['type']?> alert-dismissible fade show">
            <?=$_SESSION['message']['text']?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <span class="badge bg-<?=$type_colors[$sr['request_type']]??'secondary'?> fs-6"><?=$sr['request_type']?></span>
                                <span class="badge bg-<?=$approval_colors[$sr['approval_status']]??'secondary'?> fs-6"><?=$sr['approval_status']?></span>
                                <?php if ($sr['hod_endorsement'] !== 'N/A'): ?>
                                <span class="badge bg-<?=$sr['hod_endorsement']==='Endorsed'?'success':'warning'?>"">HOD: <?=$sr['hod_endorsement']?></span>
                                <?php endif; ?>
                                <?php if ($sr['fulfillment_status'] !== 'N/A'): ?>
                                <span class="badge bg-<?=$sr['fulfillment_status']==='Fulfilled'?'success':'info'?>"><?=$sr['fulfillment_status']?></span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?=date('d M Y H:i', strtotime($sr['created_at']))?></small>
                        </div>
                        
                        <p><strong>Linked Task:</strong> <a href="task_details.php?id=<?=$sr['task_id']?>"><?=htmlspecialchars($sr['task_code'])?> - <?=htmlspecialchars($sr['task_title'])?></a></p>
                        <p><strong>Requested By:</strong> <?=htmlspecialchars($sr['requester_name'])?></p>
                        
                        <hr>
                        <h6>Description</h6>
                        <p><?=nl2br(htmlspecialchars($sr['description']))?></p>
                        
                        <h6>Justification</h6>
                        <p><?=nl2br(htmlspecialchars($sr['justification']))?></p>
                        
                        <?php if ($sr['amount_kes']): ?>
                        <p><strong>Amount Requested:</strong> KES <?=number_format($sr['amount_kes'], 2)?></p>
                        <?php endif; ?>
                        
                        <?php if ($sr['requested_extension_date']): ?>
                        <p><strong>Requested New Due Date:</strong> <?=date('d M Y', strtotime($sr['requested_extension_date']))?></p>
                        <?php endif; ?>
                        
                        <?php if ($sr['approval_notes']): ?>
                        <hr><h6>Approver Notes</h6>
                        <p class="bg-light p-2 rounded"><?=nl2br(htmlspecialchars($sr['approval_notes']))?></p>
                        <small class="text-muted">By <?=htmlspecialchars($sr['approved_by_name'] ?? '-')?> on <?=$sr['approved_at'] ? date('d M Y H:i', strtotime($sr['approved_at'])) : '-'?></small>
                        <?php endif; ?>
                        
                        <?php if ($sr['hod_notes']): ?>
                        <hr><h6>HOD Notes</h6>
                        <p class="bg-light p-2 rounded"><?=nl2br(htmlspecialchars($sr['hod_notes']))?></p>
                        <small class="text-muted">By <?=htmlspecialchars($sr['hod_endorsed_by_name'] ?? '-')?></small>
                        <?php endif; ?>
                        
                        <!-- Attachments -->
                        <?php if (!empty($attachments)): ?>
                        <hr><h6>Attachments</h6>
                        <?php foreach ($attachments as $att): ?>
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-paperclip me-2"></i>
                            <a href="<?=htmlspecialchars($att['file_path'])?>" target="_blank"><?=htmlspecialchars($att['file_name'])?></a>
                            <small class="text-muted ms-2">(<?=round(($att['file_size']??0)/1024)?> KB by <?=htmlspecialchars($att['uploaded_by_name'])?>)</small>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Approval Actions -->
                <?php if ($sr['approval_status'] === 'Pending' && ($can_approve || $can_endorse)): ?>
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Actions</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Notes / Conditions</label>
                                <textarea name="approval_notes" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <?php if ($can_endorse && $sr['hod_endorsement'] === 'Pending'): ?>
                            <button type="submit" name="approval_action" value="endorse" class="btn btn-success d-block w-100 mb-2"><i class="fas fa-thumbs-up"></i> Endorse</button>
                            <?php endif; ?>
                            
                            <?php if ($can_approve): ?>
                            <button type="submit" name="approval_action" value="approve" class="btn btn-success d-block w-100 mb-2"><i class="fas fa-check"></i> Approve</button>
                            <button type="submit" name="approval_action" value="reject" class="btn btn-danger d-block w-100 mb-2"><i class="fas fa-times"></i> Reject</button>
                            <button type="submit" name="approval_action" value="need_info" class="btn btn-info d-block w-100 mb-2"><i class="fas fa-question"></i> Need More Info</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($sr['approval_status'] === 'Approved' && $sr['fulfillment_status'] !== 'Fulfilled' && $sr['fulfillment_status'] !== 'N/A' && $can_approve): ?>
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Fulfillment</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3"><textarea name="approval_notes" class="form-control" rows="2" placeholder="Fulfillment notes..."></textarea></div>
                            <button type="submit" name="approval_action" value="fulfill" class="btn btn-success d-block w-100"><i class="fas fa-check-double"></i> Mark Fulfilled</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; exit; } ?>

<?php
// ---- HANDLE: Create new support request ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    $task_id = intval($_POST['task_id'] ?? 0);
    $request_type = $conn->real_escape_string(trim($_POST['request_type'] ?? ''));
    $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $justification = $conn->real_escape_string(trim($_POST['justification'] ?? ''));
    $amount = !empty($_POST['amount_kes']) ? floatval($_POST['amount_kes']) : null;
    $ext_date = !empty($_POST['extension_date']) ? $conn->real_escape_string($_POST['extension_date']) : null;
    $approver_id = intval($_POST['approver_id'] ?? 0);
    
    $errors = [];
    if (!$task_id) $errors[] = 'Select a task';
    if (!$request_type) $errors[] = 'Select request type';
    if (!$description) $errors[] = 'Description is required';
    if (!$justification) $errors[] = 'Justification is required';
    if ($request_type === 'Budget' && !$amount) $errors[] = 'Amount is required for budget requests';
    if ($request_type === 'Extension' && !$ext_date) $errors[] = 'New due date is required for extension requests';
    
    if (empty($errors)) {
        $req_id = tm_generate_support_id($conn);
        $amount_sql = $amount ? $amount : 'NULL';
        $ext_sql = $ext_date ? "'$ext_date'" : 'NULL';
        $approver_sql = $approver_id > 0 ? $approver_id : ($ceo_id ?: 'NULL');
        
        // Determine if HOD endorsement needed
        $hod_endorsement = "'N/A'";
        $task = tm_get_task($conn, $task_id);
        if ($task && $task['workstream_id']) {
            $ws = $conn->query("SELECT hod_user_id FROM tm_workstreams WHERE id = {$task['workstream_id']} AND hod_user_id IS NOT NULL LIMIT 1");
            if ($ws && $ws->num_rows > 0) $hod_endorsement = "'Pending'";
        }
        
        $sql = "INSERT INTO tm_support_requests (request_id, task_id, request_type, description, justification, amount_kes, requested_extension_date, requested_by, hod_endorsement, approver_id)
                VALUES ('$req_id', $task_id, '$request_type', '$description', '$justification', $amount_sql, $ext_sql, $current_user_id, $hod_endorsement, $approver_sql)";
        
        if ($conn->query($sql)) {
            $sr_db_id = $conn->insert_id;
            
            // Upload attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = 'uploads/support/' . $sr_db_id . '/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                foreach ($_FILES['attachments']['name'] as $idx => $fname) {
                    if ($_FILES['attachments']['error'][$idx] === UPLOAD_ERR_OK) {
                        $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fname);
                        $dest = $upload_dir . $safe;
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$idx], $dest)) {
                            $fn_esc = $conn->real_escape_string($fname);
                            $dest_esc = $conn->real_escape_string($dest);
                            $sz = intval($_FILES['attachments']['size'][$idx]);
                            $mt = $conn->real_escape_string($_FILES['attachments']['type'][$idx]);
                            $conn->query("INSERT INTO tm_support_attachments (support_request_id, file_name, file_path, file_size, mime_type, uploaded_by)
                                          VALUES ($sr_db_id, '$fn_esc', '$dest_esc', $sz, '$mt', $current_user_id)");
                        }
                    }
                }
            }
            
            // Log on task
            tm_log_activity($conn, $task_id, 'support_request', "Support request created: $req_id ($request_type)", null, null, null, $current_user_id);
            
            // Notify approver
            if ($approver_sql !== 'NULL') {
                tm_create_notification($conn, $approver_sql, 'support_request', "New Support Request: $request_type", "Support request $req_id for task {$task['task_id']}: {$task['task_title']}. Type: $request_type.", $task_id, $sr_db_id);
            }
            
            // Notify HOD if endorsement needed
            if ($hod_endorsement === "'Pending'" && $task['workstream_id']) {
                $ws_row = $conn->query("SELECT hod_user_id FROM tm_workstreams WHERE id = {$task['workstream_id']} LIMIT 1")->fetch_assoc();
                if ($ws_row && $ws_row['hod_user_id']) {
                    tm_create_notification($conn, $ws_row['hod_user_id'], 'support_request', "Endorsement Needed: $request_type", "Support request $req_id needs your endorsement.", $task_id, $sr_db_id);
                }
            }
            
            $_SESSION['message'] = ['type' => 'success', 'text' => "Support request $req_id created successfully"];
            header("Location: task_support_request.php?view_id=$sr_db_id"); exit;
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

// ---- LIST VIEW: All support requests ----
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['approval'] ?? '';

$where = "1=1";
if (!$has_full) {
    // Staff see own requests, HODs see team requests
    if (tm_is_hod($conn, $current_user_id) || tm_is_workstream_lead($conn, $current_user_id)) {
        $led = tm_get_led_workstreams($conn, $current_user_id);
        if (!empty($led)) {
            $ws_ids = implode(',', array_map('intval', $led));
            $where .= " AND (sr.requested_by = $current_user_id OR t.workstream_id IN ($ws_ids) OR sr.approver_id = $current_user_id)";
        } else {
            $where .= " AND (sr.requested_by = $current_user_id OR sr.approver_id = $current_user_id)";
        }
    } else {
        $where .= " AND sr.requested_by = $current_user_id";
    }
}
if ($filter_type) $where .= " AND sr.request_type = '" . $conn->real_escape_string($filter_type) . "'";
if ($filter_status) $where .= " AND sr.approval_status = '" . $conn->real_escape_string($filter_status) . "'";

$all_requests = [];
$r = $conn->query("SELECT sr.*, t.task_id AS task_code, t.task_title, req.fullname AS requester_name
    FROM tm_support_requests sr
    JOIN tm_tasks t ON sr.task_id = t.id
    LEFT JOIN registered_users req ON sr.requested_by = req.id
    WHERE $where ORDER BY sr.created_at DESC LIMIT 100");
if ($r) while ($row = $r->fetch_assoc()) $all_requests[] = $row;

// Stats
$pending_count = 0; $approved_count = 0; $rejected_count = 0;
foreach ($all_requests as $sr) {
    if ($sr['approval_status'] === 'Pending') $pending_count++;
    elseif ($sr['approval_status'] === 'Approved') $approved_count++;
    elseif ($sr['approval_status'] === 'Rejected') $rejected_count++;
}

// Get my tasks for the create form
$my_tasks = [];
$r = $conn->query("SELECT id, task_id, task_title FROM tm_tasks WHERE owner_id = $current_user_id AND status NOT IN ('Completed','Verified','Cancelled') ORDER BY due_date ASC");
if ($r) while ($row = $r->fetch_assoc()) $my_tasks[] = $row;

$type_colors = ['Guidance'=>'info','Budget'=>'success','Tools'=>'primary','Extension'=>'warning','Staffing'=>'secondary','Remove Blocker'=>'danger'];
$approval_colors = ['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger','Need Info'=>'info'];
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h4><i class="fas fa-hands-helping"></i> Support Requests</h4>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fas fa-plus"></i> New Request</button>
    </div>
    
    <?php if (!empty($_SESSION['message'])): ?>
    <div class="alert alert-<?=$_SESSION['message']['type']?> alert-dismissible fade show"><?=$_SESSION['message']['text']?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['message']); endif; ?>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="row mb-3">
        <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$pending_count?></h4><small>Pending</small></div></div></div>
        <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$approved_count?></h4><small>Approved</small></div></div></div>
        <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=$rejected_count?></h4><small>Rejected</small></div></div></div>
        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2 text-center"><h4 class="mb-0"><?=count($all_requests)?></h4><small class="text-muted">Total</small></div></div></div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <?php foreach(['Guidance','Budget','Tools','Extension','Staffing','Remove Blocker'] as $rt):?>
                        <option value="<?=$rt?>" <?=$filter_type==$rt?'selected':''?>><?=$rt?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="approval" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach(['Pending','Approved','Rejected','Need Info'] as $as):?>
                        <option value="<?=$as?>" <?=$filter_status==$as?'selected':''?>><?=$as?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                    <a href="task_support_request.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Requests Table -->
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr><th>ID</th><th>Type</th><th>Task</th><th>Requested By</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($all_requests as $sr): ?>
                    <tr>
                        <td class="small"><?=htmlspecialchars($sr['request_id'])?></td>
                        <td><span class="badge bg-<?=$type_colors[$sr['request_type']]??'secondary'?>"><?=$sr['request_type']?></span></td>
                        <td class="small"><a href="task_details.php?id=<?=$sr['task_id']?>"><?=htmlspecialchars($sr['task_code'])?></a> <?=htmlspecialchars(mb_substr($sr['task_title'],0,30))?></td>
                        <td class="small"><?=htmlspecialchars($sr['requester_name'])?></td>
                        <td class="small"><?=$sr['amount_kes'] ? 'KES '.number_format($sr['amount_kes']) : ($sr['requested_extension_date'] ? date('d M', strtotime($sr['requested_extension_date'])) : '-')?></td>
                        <td><span class="badge bg-<?=$approval_colors[$sr['approval_status']]??'secondary'?>"><?=$sr['approval_status']?></span></td>
                        <td class="small"><?=date('d M Y', strtotime($sr['created_at']))?></td>
                        <td><a href="task_support_request.php?view_id=<?=$sr['id']?>" class="btn btn-sm btn-outline-primary py-0"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($all_requests)):?><tr><td colspan="8" class="text-center text-muted py-4">No support requests found</td></tr><?php endif;?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CREATE MODAL -->
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" enctype="multipart/form-data">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-hands-helping"></i> New Support Request</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Linked Task <span class="text-danger">*</span></label>
                <select name="task_id" class="form-select" required id="sr_task_id">
                    <option value="">-- Select Task --</option>
                    <?php foreach ($my_tasks as $mt): ?>
                    <option value="<?=$mt['id']?>" <?=$preselect_task_id==$mt['id']?'selected':''?>><?=$mt['task_id']?> - <?=htmlspecialchars(mb_substr($mt['task_title'],0,50))?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Request Type <span class="text-danger">*</span></label>
                <select name="request_type" class="form-select" required id="sr_type" onchange="toggleTypeFields()">
                    <option value="">-- Select --</option>
                    <option value="Guidance">Guidance / Clarification</option>
                    <option value="Budget">Budget</option>
                    <option value="Tools">Tools / Procurement</option>
                    <option value="Extension">Deadline Extension</option>
                    <option value="Staffing">Staffing</option>
                    <option value="Remove Blocker">Remove Blocker</option>
                </select>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">What Do You Need? <span class="text-danger">*</span></label>
            <textarea name="description" class="form-control" rows="3" required placeholder="Describe what support you need..."></textarea>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Why Is It Needed? (Justification) <span class="text-danger">*</span></label>
            <textarea name="justification" class="form-control" rows="3" required placeholder="Explain why this is needed and what risks/blockers it addresses..."></textarea>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3" id="amountField" style="display:none">
                <label class="form-label fw-bold">Amount (KES)</label>
                <input type="number" name="amount_kes" class="form-control" step="0.01" placeholder="Budget amount requested">
            </div>
            <div class="col-md-6 mb-3" id="extensionField" style="display:none">
                <label class="form-label fw-bold">New Due Date</label>
                <input type="date" name="extension_date" class="form-control">
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Approver</label>
            <select name="approver_id" class="form-select">
                <option value="">-- Auto (CEO) --</option>
                <?php foreach ($staff_list as $s): ?>
                <option value="<?=$s['id']?>" <?=$ceo_id==$s['id']?'selected':''?>><?=htmlspecialchars($s['fullname'])?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Defaults to CEO if not selected</small>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Attachments (quotes, proformas, docs)</label>
            <input type="file" name="attachments[]" class="form-control" multiple>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="create_request" value="1" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
    </div>
    </form>
</div></div></div>

<script>
function toggleTypeFields() {
    const type = document.getElementById('sr_type').value;
    document.getElementById('amountField').style.display = (type === 'Budget' || type === 'Tools') ? '' : 'none';
    document.getElementById('extensionField').style.display = type === 'Extension' ? '' : 'none';
}

// Auto-open modal if task preselected
<?php if ($preselect_task_id): ?>
document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('createModal')).show());
<?php endif; ?>
</script>
<?php require_once 'footer.php'; ?>