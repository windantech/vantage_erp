<?php
ob_start();
require_once 'header.php';
require_once 'includes/task_functions.php';

if (!isset($_SESSION['login_id'])) { header('Location: login.php'); exit; }
$current_user_id = intval($_SESSION['login_id']);

if (!tm_has_full_access($conn, $current_user_id)) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Access denied.'];
    // header('Location: task_dashboard.php'); exit;
}

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Clear ALL buffered output (header.php HTML etc)
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    try {
        switch ($action) {
            case 'save_pillar':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['pillar_name'] ?? '');
                $code = trim($_POST['pillar_code'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $color = trim($_POST['color'] ?? '#0d6efd');
                $sort = intval($_POST['sort_order'] ?? 0);
                if (empty($name)) { $response = ['success' => false, 'message' => 'Pillar name is required']; break; }
                $n = $conn->real_escape_string($name); $c = $conn->real_escape_string($code);
                $d = $conn->real_escape_string($desc); $cl = $conn->real_escape_string($color);
                if ($id > 0) {
                    $sql = "UPDATE tm_pillars SET pillar_name='$n',pillar_code='$c',description='$d',color='$cl',sort_order=$sort WHERE id=$id";
                } else {
                    $sql = "INSERT INTO tm_pillars (pillar_name,pillar_code,description,color,sort_order,created_by) VALUES ('$n','$c','$d','$cl',$sort,$current_user_id)";
                }
                if ($conn->query($sql)) {
                    $response = ['success'=>true,'message'=>$id?'Pillar updated successfully':'Pillar created successfully'];
                } else {
                    $response = ['success'=>false,'message'=>'Database error: '.htmlspecialchars($conn->error)];
                }
                break;
                
            case 'toggle_pillar':
                $tid = intval($_POST['id']??0);
                if ($tid > 0 && $conn->query("UPDATE tm_pillars SET status=IF(status=1,0,1) WHERE id=$tid")) {
                    $response = ['success'=>true,'message'=>'Status toggled'];
                } else {
                    $response = ['success'=>false,'message'=>'Failed to toggle status'];
                }
                break;
            
            case 'save_workstream':
                $id = intval($_POST['id'] ?? 0);
                $pid = intval($_POST['pillar_id'] ?? 0);
                $name = trim($_POST['workstream_name'] ?? '');
                $code = trim($_POST['workstream_code'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $hod = intval($_POST['hod_user_id'] ?? 0);
                $sort = intval($_POST['sort_order'] ?? 0);
                if (empty($name)||$pid<=0) { $response=['success'=>false,'message'=>'Workstream name and pillar are required']; break; }
                $n=$conn->real_escape_string($name);$c=$conn->real_escape_string($code);$d=$conn->real_escape_string($desc);
                $hv = $hod>0?$hod:'NULL';
                if ($id > 0) {
                    $sql = "UPDATE tm_workstreams SET pillar_id=$pid,workstream_name='$n',workstream_code='$c',description='$d',hod_user_id=$hv,sort_order=$sort WHERE id=$id";
                } else {
                    $sql = "INSERT INTO tm_workstreams (pillar_id,workstream_name,workstream_code,description,hod_user_id,sort_order,created_by) VALUES ($pid,'$n','$c','$d',$hv,$sort,$current_user_id)";
                }
                if ($conn->query($sql)) {
                    $response = ['success'=>true,'message'=>$id?'Workstream updated successfully':'Workstream created successfully'];
                } else {
                    $response = ['success'=>false,'message'=>'Database error: '.htmlspecialchars($conn->error)];
                }
                break;
                
            case 'toggle_workstream':
                $tid = intval($_POST['id']??0);
                if ($tid > 0 && $conn->query("UPDATE tm_workstreams SET status=IF(status=1,0,1) WHERE id=$tid")) {
                    $response = ['success'=>true,'message'=>'Status toggled'];
                } else {
                    $response = ['success'=>false,'message'=>'Failed to toggle status'];
                }
                break;
            
            case 'save_phase':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['phase_name'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                $sort = intval($_POST['sort_order'] ?? 0);
                if (empty($name)) { $response=['success'=>false,'message'=>'Phase name is required']; break; }
                $n=$conn->real_escape_string($name);$d=$conn->real_escape_string($desc);
                if ($id > 0) {
                    $sql = "UPDATE tm_phases SET phase_name='$n',description='$d',sort_order=$sort WHERE id=$id";
                } else {
                    $sql = "INSERT INTO tm_phases (phase_name,description,sort_order) VALUES ('$n','$d',$sort)";
                }
                if ($conn->query($sql)) {
                    $response = ['success'=>true,'message'=>$id?'Phase updated successfully':'Phase created successfully'];
                } else {
                    $response = ['success'=>false,'message'=>'Database error: '.htmlspecialchars($conn->error)];
                }
                break;
            
            case 'save_settings':
                $keys = ['ceo_user_id','strategy_year','reminder_days_before','overdue_escalate_hod_days',
                         'overdue_escalate_ceo_days','overdue_daily_reminder','recurrence_create_days_before',
                         'require_evidence_for_completion','task_id_prefix','default_priority'];
                foreach ($keys as $k) {
                    if (isset($_POST[$k])) tm_update_setting($conn, $k, $_POST[$k], $current_user_id);
                }
                $response = ['success'=>true,'message'=>'Settings saved successfully'];
                break;
        }
    } catch (Exception $e) {
        $response = ['success'=>false,'message'=>'Server error: '.htmlspecialchars($e->getMessage())];
    }
    
    echo json_encode($response);
    exit;
}

// Load data
$staff_list = tm_get_staff_list($conn);
$settings = [];
$r = $conn->query("SELECT * FROM tm_settings"); if($r) while($row=$r->fetch_assoc()) $settings[$row['setting_key']]=$row;
$all_pillars = []; $r=$conn->query("SELECT * FROM tm_pillars ORDER BY sort_order,pillar_name"); if($r) while($row=$r->fetch_assoc()) $all_pillars[]=$row;
$all_workstreams = []; $r=$conn->query("SELECT w.*,p.pillar_name FROM tm_workstreams w LEFT JOIN tm_pillars p ON w.pillar_id=p.id ORDER BY p.sort_order,w.sort_order,w.workstream_name"); if($r) while($row=$r->fetch_assoc()) $all_workstreams[]=$row;
$all_phases = []; $r=$conn->query("SELECT * FROM tm_phases ORDER BY sort_order,phase_name"); if($r) while($row=$r->fetch_assoc()) $all_phases[]=$row;
?>

<div class="container-fluid px-4">
    <h4 class="mt-3 mb-3"><i class="fas fa-cogs"></i> Task Manager Settings</h4>
    
    <ul class="nav nav-tabs" id="settingsTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-general">General</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-pillars">Pillars <span class="badge bg-secondary"><?=count($all_pillars)?></span></a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-workstreams">Workstreams <span class="badge bg-secondary"><?=count($all_workstreams)?></span></a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-phases">Phases</a></li>
    </ul>
    
    <div class="tab-content mt-3">
        <!-- GENERAL SETTINGS -->
        <div class="tab-pane fade show active" id="tab-general">
            <div class="card">
                <div class="card-body">
                    <form id="settingsForm">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">ROLES</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">CEO User <span class="text-danger">*</span></label>
                                    <select name="ceo_user_id" class="form-select">
                                        <option value="">-- Select CEO --</option>
                                        <?php foreach($staff_list as $s): ?>
                                        <option value="<?=$s['id']?>" <?=($settings['ceo_user_id']['setting_value']??'')==$s['id']?'selected':''?>><?=htmlspecialchars($s['fullname'])?> (<?=htmlspecialchars($s['email'])?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <h6 class="text-muted mb-3 mt-4">GENERAL</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Strategy Year</label>
                                    <select name="strategy_year" class="form-select">
                                        <?php for($y=2025;$y<=2030;$y++): ?>
                                        <option value="<?=$y?>" <?=($settings['strategy_year']['setting_value']??'2026')==$y?'selected':''?>><?=$y?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Task ID Prefix</label>
                                    <input type="text" name="task_id_prefix" class="form-control" value="<?=htmlspecialchars($settings['task_id_prefix']['setting_value']??'TSK')?>" maxlength="10">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Default Priority</label>
                                    <select name="default_priority" class="form-select">
                                        <?php foreach(['Critical','High','Medium','Low'] as $p): ?>
                                        <option value="<?=$p?>" <?=($settings['default_priority']['setting_value']??'Medium')==$p?'selected':''?>><?=$p?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Require Evidence for Completion</label>
                                    <select name="require_evidence_for_completion" class="form-select">
                                        <option value="1" <?=($settings['require_evidence_for_completion']['setting_value']??'1')=='1'?'selected':''?>>Yes</option>
                                        <option value="0" <?=($settings['require_evidence_for_completion']['setting_value']??'1')=='0'?'selected':''?>>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">REMINDERS</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Reminder Days Before Due</label>
                                    <input type="text" name="reminder_days_before" class="form-control" value="<?=htmlspecialchars($settings['reminder_days_before']['setting_value']??'7,3,1,0')?>">
                                    <small class="text-muted">Comma-separated e.g., 7,3,1,0</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Daily Overdue Reminder</label>
                                    <select name="overdue_daily_reminder" class="form-select">
                                        <option value="1" <?=($settings['overdue_daily_reminder']['setting_value']??'1')=='1'?'selected':''?>>Yes</option>
                                        <option value="0" <?=($settings['overdue_daily_reminder']['setting_value']??'1')=='0'?'selected':''?>>No</option>
                                    </select>
                                </div>
                                <h6 class="text-muted mb-3 mt-4">ESCALATION</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Escalate to HOD After (days overdue)</label>
                                    <input type="number" name="overdue_escalate_hod_days" class="form-control" value="<?=htmlspecialchars($settings['overdue_escalate_hod_days']['setting_value']??'1')?>" min="1">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Escalate to CEO After (days overdue)</label>
                                    <input type="number" name="overdue_escalate_ceo_days" class="form-control" value="<?=htmlspecialchars($settings['overdue_escalate_ceo_days']['setting_value']??'3')?>" min="1">
                                </div>
                                <h6 class="text-muted mb-3 mt-4">RECURRING</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Create Next Occurrence (days before due)</label>
                                    <input type="number" name="recurrence_create_days_before" class="form-control" value="<?=htmlspecialchars($settings['recurrence_create_days_before']['setting_value']??'7')?>" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- PILLARS -->
        <div class="tab-pane fade" id="tab-pillars">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Strategic Pillars</h6>
                    <button class="btn btn-sm btn-primary" onclick="editPillar(0)"><i class="fas fa-plus"></i> Add Pillar</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Color</th><th>Name</th><th>Code</th><th>Workstreams</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach($all_pillars as $i=>$pil):
                            $wc=0; foreach($all_workstreams as $ws) if($ws['pillar_id']==$pil['id']) $wc++; ?>
                        <tr>
                            <td><?=$i+1?></td>
                            <td><span class="d-inline-block rounded-circle" style="width:20px;height:20px;background:<?=htmlspecialchars($pil['color'])?>"></span></td>
                            <td><strong><?=htmlspecialchars($pil['pillar_name'])?></strong></td>
                            <td><?=htmlspecialchars($pil['pillar_code']??'-')?></td>
                            <td><span class="badge bg-secondary"><?=$wc?></span></td>
                            <td><?=$pil['sort_order']?></td>
                            <td><span class="badge <?=$pil['status']?'bg-success':'bg-danger'?>" style="cursor:pointer" onclick="togglePillar(<?=$pil['id']?>)"><?=$pil['status']?'Active':'Inactive'?></span></td>
                            <td><button class="btn btn-sm btn-outline-primary" onclick='editPillar(<?=json_encode($pil)?>)'><i class="fas fa-edit"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($all_pillars)):?><tr><td colspan="8" class="text-center text-muted py-4">No pillars yet</td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- WORKSTREAMS -->
        <div class="tab-pane fade" id="tab-workstreams">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Workstreams</h6>
                    <button class="btn btn-sm btn-primary" onclick="editWorkstream(0)"><i class="fas fa-plus"></i> Add Workstream</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Name</th><th>Code</th><th>Pillar</th><th>Lead</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach($all_workstreams as $i=>$ws):
                            $ln='-'; if($ws['hod_user_id']) foreach($staff_list as $s) if($s['id']==$ws['hod_user_id']){$ln=$s['fullname'];break;} ?>
                        <tr>
                            <td><?=$i+1?></td>
                            <td><strong><?=htmlspecialchars($ws['workstream_name'])?></strong></td>
                            <td><?=htmlspecialchars($ws['workstream_code']??'-')?></td>
                            <td><span class="badge bg-info"><?=htmlspecialchars($ws['pillar_name']??'-')?></span></td>
                            <td><?=htmlspecialchars($ln)?></td>
                            <td><?=$ws['sort_order']?></td>
                            <td><span class="badge <?=$ws['status']?'bg-success':'bg-danger'?>" style="cursor:pointer" onclick="toggleWorkstream(<?=$ws['id']?>)"><?=$ws['status']?'Active':'Inactive'?></span></td>
                            <td><button class="btn btn-sm btn-outline-primary" onclick='editWorkstream(<?=json_encode($ws)?>)'><i class="fas fa-edit"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($all_workstreams)):?><tr><td colspan="8" class="text-center text-muted py-4">No workstreams yet</td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- PHASES -->
        <div class="tab-pane fade" id="tab-phases">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Phases (Optional)</h6>
                    <button class="btn btn-sm btn-primary" onclick="editPhase(0)"><i class="fas fa-plus"></i> Add Phase</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Name</th><th>Description</th><th>Order</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach($all_phases as $i=>$ph): ?>
                        <tr>
                            <td><?=$i+1?></td>
                            <td><strong><?=htmlspecialchars($ph['phase_name'])?></strong></td>
                            <td><?=htmlspecialchars($ph['description']??'-')?></td>
                            <td><?=$ph['sort_order']?></td>
                            <td><button class="btn btn-sm btn-outline-primary" onclick='editPhase(<?=json_encode($ph)?>)'><i class="fas fa-edit"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($all_phases)):?><tr><td colspan="5" class="text-center text-muted py-4">No phases yet</td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<div class="modal fade" id="pillarModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="pillarModalTitle">Add Pillar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="pillar_id" value="0">
        <div class="mb-3"><label class="form-label fw-bold">Pillar Name *</label><input type="text" id="pillar_name" class="form-control" maxlength="150"></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Code</label><input type="text" id="pillar_code" class="form-control" maxlength="20"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Color</label><input type="color" id="pillar_color" class="form-control form-control-color" value="#0d6efd"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Order</label><input type="number" id="pillar_sort" class="form-control" value="0"></div>
        </div>
        <div class="mb-3"><label class="form-label">Description</label><textarea id="pillar_desc" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="savePillar()">Save</button></div>
</div></div></div>

<div class="modal fade" id="workstreamModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="wsModalTitle">Add Workstream</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="ws_id" value="0">
        <div class="mb-3"><label class="form-label fw-bold">Pillar *</label>
            <select id="ws_pillar_id" class="form-select"><option value="">-- Select --</option>
            <?php foreach($all_pillars as $p):?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['pillar_name'])?></option><?php endforeach;?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label fw-bold">Workstream Name *</label><input type="text" id="ws_name" class="form-control" maxlength="150"></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Code</label><input type="text" id="ws_code" class="form-control" maxlength="20"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Order</label><input type="number" id="ws_sort" class="form-control" value="0"></div>
        </div>
        <div class="mb-3"><label class="form-label">Lead / HOD</label>
            <select id="ws_hod" class="form-select"><option value="">-- None --</option>
            <?php foreach($staff_list as $s):?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['fullname'])?></option><?php endforeach;?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Description</label><textarea id="ws_desc" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="saveWorkstream()">Save</button></div>
</div></div></div>

<div class="modal fade" id="phaseModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="phaseModalTitle">Add Phase</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="phase_id" value="0">
        <div class="mb-3"><label class="form-label fw-bold">Phase Name *</label><input type="text" id="phase_name" class="form-control" maxlength="100"></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea id="phase_desc" class="form-control" rows="2"></textarea></div>
        <div class="mb-3"><label class="form-label">Order</label><input type="number" id="phase_sort" class="form-control" value="0"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="savePhase()">Save</button></div>
</div></div></div>

<script>
function ajaxPost(data, cb) {
    const fd = new FormData(); for(const k in data) fd.append(k, data[k]);
    fetch(window.location.href,{method:'POST',body:fd}).then(r=>{
        const contentType = r.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return r.json();
        } else {
            // Response is not JSON (likely HTML error page)
            return r.text().then(text => {
                throw new Error('Server returned an unexpected response. Please try again.');
            });
        }
    }).then(res=>{
        showAlert(res.success?'success':'danger', res.message);
        if(res.success){if(cb)cb(res);else setTimeout(()=>location.reload(),800);}
    }).catch(e=>showAlert('danger', e.message));
}
function showAlert(t,m){const e=document.createElement('div');e.className=`alert alert-${t} alert-dismissible fade show position-fixed top-0 end-0 m-3`;e.style.zIndex='9999';e.innerHTML=`${m}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;document.body.appendChild(e);setTimeout(()=>e.remove(),3000);}
function saveSettings(){const f=document.getElementById('settingsForm');const d={ajax_action:'save_settings'};new FormData(f).forEach((v,k)=>d[k]=v);ajaxPost(d);}

function editPillar(d){
    const isEdit=d&&d.id;
    document.getElementById('pillarModalTitle').textContent=isEdit?'Edit Pillar':'Add Pillar';
    document.getElementById('pillar_id').value=isEdit?d.id:0;
    document.getElementById('pillar_name').value=isEdit?d.pillar_name:'';
    document.getElementById('pillar_code').value=isEdit?(d.pillar_code||''):'';
    document.getElementById('pillar_color').value=isEdit?(d.color||'#0d6efd'):'#0d6efd';
    document.getElementById('pillar_sort').value=isEdit?(d.sort_order||0):0;
    document.getElementById('pillar_desc').value=isEdit?(d.description||''):'';
    new bootstrap.Modal(document.getElementById('pillarModal')).show();
}
function savePillar(){ajaxPost({ajax_action:'save_pillar',id:document.getElementById('pillar_id').value,pillar_name:document.getElementById('pillar_name').value,pillar_code:document.getElementById('pillar_code').value,color:document.getElementById('pillar_color').value,sort_order:document.getElementById('pillar_sort').value,description:document.getElementById('pillar_desc').value});}
function togglePillar(id){if(confirm('Toggle status?'))ajaxPost({ajax_action:'toggle_pillar',id:id});}

function editWorkstream(d){
    const isEdit=d&&d.id;
    document.getElementById('wsModalTitle').textContent=isEdit?'Edit Workstream':'Add Workstream';
    document.getElementById('ws_id').value=isEdit?d.id:0;
    document.getElementById('ws_pillar_id').value=isEdit?d.pillar_id:'';
    document.getElementById('ws_name').value=isEdit?d.workstream_name:'';
    document.getElementById('ws_code').value=isEdit?(d.workstream_code||''):'';
    document.getElementById('ws_hod').value=isEdit?(d.hod_user_id||''):'';
    document.getElementById('ws_sort').value=isEdit?(d.sort_order||0):0;
    document.getElementById('ws_desc').value=isEdit?(d.description||''):'';
    new bootstrap.Modal(document.getElementById('workstreamModal')).show();
}
function saveWorkstream(){ajaxPost({ajax_action:'save_workstream',id:document.getElementById('ws_id').value,pillar_id:document.getElementById('ws_pillar_id').value,workstream_name:document.getElementById('ws_name').value,workstream_code:document.getElementById('ws_code').value,hod_user_id:document.getElementById('ws_hod').value,sort_order:document.getElementById('ws_sort').value,description:document.getElementById('ws_desc').value});}
function toggleWorkstream(id){if(confirm('Toggle status?'))ajaxPost({ajax_action:'toggle_workstream',id:id});}

function editPhase(d){
    const isEdit=d&&d.id;
    document.getElementById('phaseModalTitle').textContent=isEdit?'Edit Phase':'Add Phase';
    document.getElementById('phase_id').value=isEdit?d.id:0;
    document.getElementById('phase_name').value=isEdit?d.phase_name:'';
    document.getElementById('phase_desc').value=isEdit?(d.description||''):'';
    document.getElementById('phase_sort').value=isEdit?(d.sort_order||0):0;
    new bootstrap.Modal(document.getElementById('phaseModal')).show();
}
function savePhase(){ajaxPost({ajax_action:'save_phase',id:document.getElementById('phase_id').value,phase_name:document.getElementById('phase_name').value,description:document.getElementById('phase_desc').value,sort_order:document.getElementById('phase_sort').value});}
</script>
<?php require_once 'footer.php'; ?>