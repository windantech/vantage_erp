<?php
ob_start();
require_once 'header.php';
require_once 'includes/task_functions.php';

if (!isset($_SESSION['login_id'])) { header('Location: login.php'); exit; }
$current_user_id = intval($_SESSION['login_id']);
if (!tm_has_full_access($conn, $current_user_id)) {
    $_SESSION['message'] = ['type'=>'danger','text'=>'Access denied.'];
    header('Location: task_dashboard.php'); exit;
}

$pillars = tm_get_pillars($conn);
$workstreams = tm_get_workstreams($conn);
$staff_list = tm_get_staff_list($conn);

// Lookups for auto-matching
$staff_by_name = []; foreach($staff_list as $s) $staff_by_name[strtolower(trim($s['fullname']))]=$s['id'];
$pillar_by_name = []; foreach($pillars as $p){ $pillar_by_name[strtolower(trim($p['pillar_name']))]=$p['id']; if($p['pillar_code']) $pillar_by_name[strtolower(trim($p['pillar_code']))]=$p['id']; }
$ws_by_name = []; foreach($workstreams as $w){ $ws_by_name[strtolower(trim($w['workstream_name']))]=$w['id']; if($w['workstream_code']) $ws_by_name[strtolower(trim($w['workstream_code']))]=$w['id']; }

// ---- AJAX HANDLERS ----
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action']==='parse_file') {
        if(empty($_FILES['import_file']['tmp_name'])){echo json_encode(['success'=>false,'message'=>'No file']);exit;}
        $file=$_FILES['import_file']['tmp_name'];
        $ext=strtolower(pathinfo($_FILES['import_file']['name'],PATHINFO_EXTENSION));
        $rows=[];$headers=[];
        
        if($ext==='csv'){
            if(($h=fopen($file,'r'))!==false){$headers=fgetcsv($h);$n=0;while(($d=fgetcsv($h))!==false&&$n<500){$rows[]=$d;$n++;}fclose($h);}
        } elseif(in_array($ext,['xlsx','xls'])){
            $rows=parse_xlsx_simple($file);if(!empty($rows))$headers=array_shift($rows);
        } else {echo json_encode(['success'=>false,'message'=>'Use CSV or XLSX']);exit;}
        
        if(empty($headers)){echo json_encode(['success'=>false,'message'=>'Cannot read headers']);exit;}
        
        $_SESSION['import_headers']=$headers;
        $_SESSION['import_rows']=$rows;
        $_SESSION['import_filename']=$_FILES['import_file']['name'];
        
        echo json_encode(['success'=>true,'headers'=>$headers,'row_count'=>count($rows),'preview'=>array_slice($rows,0,5),'suggested_mapping'=>auto_detect_columns($headers)]);
        exit;
    }
    
    if ($_POST['ajax_action']==='validate_import') {
        if(empty($_SESSION['import_rows'])){echo json_encode(['success'=>false,'message'=>'Re-upload file']);exit;}
        $mapping=json_decode($_POST['column_mapping'],true);
        $defaults=json_decode($_POST['defaults']??'{}',true);
        $rows=$_SESSION['import_rows'];
        $validated=[];$errors=[];$warnings=[];
        
        foreach($rows as $idx=>$row){
            $rn=$idx+2;$td=[];$re=[];$rw=[];
            foreach($mapping as $f=>$ci){if($ci===''||$ci===null)continue;$td[$f]=trim($row[intval($ci)]??'');}
            foreach($defaults as $f=>$dv){if(empty($td[$f])&&!empty($dv))$td[$f]=$dv;}
            if(empty($td['task_title']))continue;
            if(empty($td['deliverable']))$re[]="Missing deliverable";
            
            // Resolve owner
            if(!empty($td['owner_name'])){
                $ok=strtolower(trim($td['owner_name']));
                if(isset($staff_by_name[$ok]))$td['owner_id']=$staff_by_name[$ok];
                else{$rw[]="Owner '{$td['owner_name']}' not found";$td['owner_id']=$defaults['owner_id']??null;}
            }
            if(empty($td['owner_id']))$td['owner_id']=$defaults['owner_id']??null;
            if(empty($td['owner_id']))$re[]="No owner";
            
            // Resolve pillar/workstream
            if(!empty($td['pillar_name'])){$pk=strtolower(trim($td['pillar_name']));$td['pillar_id']=$pillar_by_name[$pk]??null;if(!$td['pillar_id'])$rw[]="Pillar not found";}
            if(!empty($td['workstream_name'])){$wk=strtolower(trim($td['workstream_name']));$td['workstream_id']=$ws_by_name[$wk]??null;if(!$td['workstream_id'])$rw[]="Workstream not found";}
            
            // Dates
            $td['start_date']=!empty($td['start_date'])?parse_date_flexible($td['start_date']):date('Y-m-d');
            if(!$td['start_date']){$re[]="Invalid start date";$td['start_date']=date('Y-m-d');}
            $td['due_date']=!empty($td['due_date'])?parse_date_flexible($td['due_date']):null;
            if(!$td['due_date'])$re[]="Missing/invalid due date";
            
            // Priority
            $vp=['Critical','High','Medium','Low'];
            if(!empty($td['priority'])){$td['priority']=ucfirst(strtolower(trim($td['priority'])));if(!in_array($td['priority'],$vp))$td['priority']=$defaults['priority']??'Medium';}
            else $td['priority']=$defaults['priority']??'Medium';
            
            // Cadence
            $vc=['None','Daily','Weekly','Bi-weekly','Monthly','Quarterly','Semi-annual','Annual','Custom'];
            if(!empty($td['cadence'])){$td['cadence']=ucfirst(strtolower(trim($td['cadence'])));if(!in_array($td['cadence'],$vc))$td['cadence']='None';}
            else $td['cadence']='None';
            
            $td['_row_num']=$rn;$td['_errors']=$re;$td['_warnings']=$rw;
            $td['strategy_year']=$defaults['strategy_year']??date('Y');
            $validated[]=$td;
            if(!empty($re))$errors[]="Row $rn: ".implode(', ',$re);
            if(!empty($rw))$warnings[]="Row $rn: ".implode(', ',$rw);
        }
        $_SESSION['import_validated']=$validated;
        $importable=count(array_filter($validated,fn($v)=>empty($v['_errors'])));
        echo json_encode(['success'=>true,'total_rows'=>count($validated),'importable'=>$importable,'error_count'=>count($validated)-$importable,'errors'=>$errors,'warnings'=>$warnings,'preview'=>array_slice($validated,0,10)]);
        exit;
    }
    
    if ($_POST['ajax_action']==='execute_import') {
        if(empty($_SESSION['import_validated'])){echo json_encode(['success'=>false,'message'=>'No data']);exit;}
        $validated=$_SESSION['import_validated'];
        $batch_id=tm_create_import_batch($conn,$_SESSION['import_filename']??'file',$current_user_id);
        $imported=0;$skipped=0;$ierr=[];
        
        foreach($validated as $td){
            if(!empty($td['_errors'])){$skipped++;continue;}
            $rn=$td['_row_num'];
            unset($td['_row_num'],$td['_errors'],$td['_warnings'],$td['owner_name'],$td['pillar_name'],$td['workstream_name']);
            $td['import_batch_id']=$batch_id;$td['import_row_number']=$rn;$td['status']='Assigned';
            
            $nid=tm_create_task($conn,$td,$current_user_id);
            if($nid){
                $imported++;
                if(($td['owner_id']??0)!=$current_user_id){
                    $t=tm_get_task($conn,$nid);
                    tm_create_notification($conn,$td['owner_id'],'assignment',"Task Assigned: {$td['task_title']}","Assigned {$t['task_id']}: {$td['task_title']}. Due: {$td['due_date']}.",$nid);
                }
            } else {$skipped++;$ierr[]="Row $rn: ".$conn->error;}
        }
        
        tm_update_import_batch($conn,$batch_id,['total_rows'=>count($validated),'imported_rows'=>$imported,'skipped_rows'=>$skipped,'error_rows'=>count($ierr),'errors_log'=>json_encode($ierr),'status'=>'Completed','completed_at'=>date('Y-m-d H:i:s')]);
        unset($_SESSION['import_headers'],$_SESSION['import_rows'],$_SESSION['import_validated'],$_SESSION['import_filename']);
        echo json_encode(['success'=>true,'imported'=>$imported,'skipped'=>$skipped,'errors'=>$ierr,'batch_id'=>$batch_id]);
        exit;
    }
}

// Helper functions
function auto_detect_columns($headers){
    $m=[];
    $p=['task_title'=>['task','title','activity','action item'],'deliverable'=>['deliverable','output','result','outcome'],
        'owner_name'=>['owner','responsible','assigned','person','staff','lead'],'pillar_name'=>['pillar','strategic pillar'],
        'workstream_name'=>['workstream','stream','program','department'],'start_date'=>['start','begin','from'],
        'due_date'=>['due','end','deadline','target date','complete by'],'priority'=>['priority','urgency'],
        'cadence'=>['cadence','frequency','recurring'],'budget_kes'=>['budget','cost','spend','kes'],
        'kpi_target'=>['kpi','target','metric'],'task_description'=>['scope','detail','notes'],
        'evidence_requirement'=>['evidence','proof'],'owner_role'=>['role','position'],'sn'=>['sn','#','no','serial','s/n']];
    foreach($headers as $i=>$h){$hl=strtolower(trim($h));foreach($p as $f=>$kws){foreach($kws as $kw){if(strpos($hl,$kw)!==false){if(!isset($m[$f]))$m[$f]=$i;break 2;}}}}
    return $m;
}

function parse_date_flexible($ds){
    $ds=trim($ds);if(empty($ds))return null;
    $fmts=['Y-m-d','d/m/Y','m/d/Y','d-m-Y','d.m.Y','Y/m/d'];
    foreach($fmts as $f){$d=DateTime::createFromFormat($f,$ds);if($d&&$d->format($f)===$ds)return $d->format('Y-m-d');}
    $ts=strtotime($ds);if($ts&&$ts>strtotime('2020-01-01'))return date('Y-m-d',$ts);
    if(is_numeric($ds)&&$ds>40000&&$ds<50000)return date('Y-m-d',($ds-25569)*86400);
    return null;
}

function parse_xlsx_simple($fp){
    $rows=[];$zip=new ZipArchive;if($zip->open($fp)!==true)return $rows;
    $strings=[];$ss=$zip->getFromName('xl/sharedStrings.xml');
    if($ss){$x=new SimpleXMLElement($ss);foreach($x->si as $si){$t='';if(isset($si->t))$t=(string)$si->t;elseif(isset($si->r))foreach($si->r as $r)$t.=(string)$r->t;$strings[]=$t;}}
    $sx=$zip->getFromName('xl/worksheets/sheet1.xml');if(!$sx){$zip->close();return $rows;}
    $s=new SimpleXMLElement($sx);
    foreach($s->sheetData->row as $row){$rd=[];$mc=0;
        foreach($row->c as $c){$ref=(string)$c['r'];$ci=col_idx(preg_replace('/[0-9]/','',$ref));$mc=max($mc,$ci);
        $v='';if(isset($c['t'])&&(string)$c['t']==='s'){$si=intval((string)$c->v);$v=$strings[$si]??'';}elseif(isset($c->v))$v=(string)$c->v;$rd[$ci]=$v;}
        $fl=[];for($i=0;$i<=$mc;$i++)$fl[]=$rd[$i]??'';$rows[]=$fl;}
    $zip->close();return $rows;
}
function col_idx($l){$r=0;for($i=0;$i<strlen($l);$i++)$r=$r*26+(ord(strtoupper($l[$i]))-ord('A'));return $r;}

$strategy_year = tm_get_setting($conn, 'strategy_year', date('Y'));
?>

<div class="container-fluid px-4">
    <h4 class="mt-3 mb-3"><i class="fas fa-file-import"></i> Import Tasks from Excel/CSV</h4>
    
    <!-- Step Indicator -->
    <div class="d-flex justify-content-center mb-4">
        <?php foreach([1=>'Upload',2=>'Map Columns',3=>'Validate',4=>'Defaults',5=>'Import'] as $n=>$label): ?>
        <?php if($n>1):?><div class="border-top mx-2" style="width:40px"></div><?php endif;?>
        <span class="badge <?=$n==1?'bg-primary':'bg-secondary'?> rounded-pill px-3 py-2 step-badge" data-step="<?=$n?>"><?=$n?>. <?=$label?></span>
        <?php endforeach;?>
    </div>
    
    <!-- STEP 1: Upload -->
    <div class="card step-card" id="step1">
        <div class="card-body text-center py-5">
            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
            <h5>Upload Your Strategy File</h5>
            <p class="text-muted">Supported: .xlsx or .csv (max 500 rows)</p>
            <div class="row justify-content-center"><div class="col-md-6">
                <input type="file" id="importFile" class="form-control mb-3" accept=".csv,.xlsx,.xls">
                <button class="btn btn-primary" onclick="uploadFile()"><i class="fas fa-upload"></i> Upload & Parse</button>
            </div></div>
            <div id="uploadProgress" class="mt-3" style="display:none"><div class="spinner-border text-primary"></div><p class="text-muted mt-2">Parsing...</p></div>
        </div>
    </div>
    
    <!-- STEP 2: Map Columns -->
    <div class="card step-card" id="step2" style="display:none">
        <div class="card-header d-flex justify-content-between"><h6 class="mb-0">Map Your Columns</h6><span class="badge bg-info" id="rowCountBadge"></span></div>
        <div class="card-body">
            <p class="text-muted mb-3">Match columns to task fields. Auto-detected where possible.</p>
            <div class="row" id="mappingFields"></div>
            <h6 class="mt-3">Preview (first 5 rows)</h6>
            <div class="table-responsive"><table class="table table-sm table-bordered" id="previewTable"></table></div>
            <div class="text-end mt-3">
                <button class="btn btn-secondary" onclick="goToStep(1)">Back</button>
                <button class="btn btn-primary" onclick="goToStep(4)">Next: Defaults</button>
            </div>
        </div>
    </div>
    
    <!-- STEP 4: Defaults -->
    <div class="card step-card" id="step4" style="display:none">
        <div class="card-header"><h6 class="mb-0">Set Default Values</h6></div>
        <div class="card-body">
            <p class="text-muted">Applied to rows missing these values:</p>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Priority</label>
                    <select id="default_priority" class="form-select">
                        <option value="Critical">Critical</option><option value="High">High</option>
                        <option value="Medium" selected>Medium</option><option value="Low">Low</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Owner</label>
                    <select id="default_owner" class="form-select">
                        <option value="">-- None --</option>
                        <?php foreach($staff_list as $s):?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['fullname'])?></option><?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Strategy Year</label>
                    <select id="default_year" class="form-select">
                        <?php for($y=2025;$y<=2030;$y++):?><option value="<?=$y?>" <?=$strategy_year==$y?'selected':''?>><?=$y?></option><?php endfor;?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Cadence</label>
                    <select id="default_cadence" class="form-select">
                        <option value="None" selected>None</option><option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option><option value="Weekly">Weekly</option>
                    </select>
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn btn-secondary" onclick="goToStep(2)">Back</button>
                <button class="btn btn-primary" onclick="validateImport()"><i class="fas fa-check-circle"></i> Validate</button>
            </div>
        </div>
    </div>
    
    <!-- STEP 3: Validation Results -->
    <div class="card step-card" id="step3" style="display:none">
        <div class="card-header"><h6 class="mb-0">Validation Results</h6></div>
        <div class="card-body">
            <div class="row mb-3" id="validationStats"></div>
            <div id="validationErrors"></div>
            <div id="validationWarnings"></div>
            <div class="text-end mt-3">
                <button class="btn btn-secondary" onclick="goToStep(4)">Back: Fix Defaults</button>
                <button class="btn btn-success" id="btnExecuteImport" onclick="executeImport()"><i class="fas fa-database"></i> Import Tasks</button>
            </div>
        </div>
    </div>
    
    <!-- STEP 5: Results -->
    <div class="card step-card" id="step5" style="display:none">
        <div class="card-body text-center py-5" id="importResults"></div>
    </div>
</div>

<script>
let fileHeaders = [];
let suggestedMapping = {};

function goToStep(n) {
    document.querySelectorAll('.step-card').forEach(c => c.style.display = 'none');
    document.getElementById('step' + n).style.display = '';
    document.querySelectorAll('.step-badge').forEach(b => {
        b.className = b.className.replace('bg-primary', 'bg-secondary');
        if (parseInt(b.dataset.step) === n) b.className = b.className.replace('bg-secondary', 'bg-primary');
    });
}

function uploadFile() {
    const file = document.getElementById('importFile').files[0];
    if (!file) { alert('Select a file first'); return; }
    
    document.getElementById('uploadProgress').style.display = '';
    const fd = new FormData();
    fd.append('ajax_action', 'parse_file');
    fd.append('import_file', file);
    
    fetch(window.location.href, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        document.getElementById('uploadProgress').style.display = 'none';
        if (!res.success) { alert(res.message); return; }
        
        fileHeaders = res.headers;
        suggestedMapping = res.suggested_mapping;
        document.getElementById('rowCountBadge').textContent = res.row_count + ' rows found';
        
        // Build mapping dropdowns
        const fields = [
            {key:'task_title',label:'Task Title *',required:true},
            {key:'deliverable',label:'Deliverable *',required:true},
            {key:'owner_name',label:'Owner Name'},
            {key:'pillar_name',label:'Pillar'},
            {key:'workstream_name',label:'Workstream'},
            {key:'start_date',label:'Start Date'},
            {key:'due_date',label:'Due Date *',required:true},
            {key:'priority',label:'Priority'},
            {key:'cadence',label:'Cadence'},
            {key:'budget_kes',label:'Budget (KES)'},
            {key:'kpi_target',label:'KPI Target'},
            {key:'task_description',label:'Description'},
            {key:'evidence_requirement',label:'Evidence Requirement'},
            {key:'owner_role',label:'Owner Role'},
            {key:'sn',label:'Serial Number'},
        ];
        
        let html = '';
        fields.forEach(f => {
            const suggested = suggestedMapping[f.key];
            html += `<div class="col-md-4 mb-2">
                <label class="form-label ${f.required?'fw-bold':''}">${f.label}</label>
                <select class="form-select form-select-sm mapping-select" data-field="${f.key}">
                    <option value="">-- Skip --</option>`;
            fileHeaders.forEach((h, i) => {
                html += `<option value="${i}" ${suggested===i?'selected':''}>${h}</option>`;
            });
            html += `</select></div>`;
        });
        document.getElementById('mappingFields').innerHTML = html;
        
        // Build preview table
        let thtml = '<thead><tr>';
        fileHeaders.forEach(h => thtml += `<th class="small">${h}</th>`);
        thtml += '</tr></thead><tbody>';
        res.preview.forEach(row => {
            thtml += '<tr>';
            row.forEach(cell => thtml += `<td class="small">${cell||'-'}</td>`);
            thtml += '</tr>';
        });
        thtml += '</tbody>';
        document.getElementById('previewTable').innerHTML = thtml;
        
        goToStep(2);
    }).catch(e => { document.getElementById('uploadProgress').style.display='none'; alert('Error: '+e.message); });
}

function getMapping() {
    const m = {};
    document.querySelectorAll('.mapping-select').forEach(s => {
        if (s.value !== '') m[s.dataset.field] = s.value;
    });
    return m;
}

function getDefaults() {
    return {
        priority: document.getElementById('default_priority').value,
        owner_id: document.getElementById('default_owner').value,
        strategy_year: document.getElementById('default_year').value,
        cadence: document.getElementById('default_cadence').value,
    };
}

function validateImport() {
    const fd = new FormData();
    fd.append('ajax_action', 'validate_import');
    fd.append('column_mapping', JSON.stringify(getMapping()));
    fd.append('defaults', JSON.stringify(getDefaults()));
    
    fetch(window.location.href,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if (!res.success) { alert(res.message); return; }
        
        let stats = `
            <div class="col-md-3"><div class="card bg-light"><div class="card-body text-center">
                <h3>${res.total_rows}</h3><small class="text-muted">Total Rows</small>
            </div></div></div>
            <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center">
                <h3>${res.importable}</h3><small>Ready to Import</small>
            </div></div></div>
            <div class="col-md-3"><div class="card ${res.error_count?'bg-danger text-white':'bg-light'}"><div class="card-body text-center">
                <h3>${res.error_count}</h3><small>Errors (will skip)</small>
            </div></div></div>
            <div class="col-md-3"><div class="card ${res.warnings.length?'bg-warning':'bg-light'}"><div class="card-body text-center">
                <h3>${res.warnings.length}</h3><small>Warnings</small>
            </div></div></div>`;
        document.getElementById('validationStats').innerHTML = stats;
        
        let errHtml = '';
        if (res.errors.length) {
            errHtml = '<div class="alert alert-danger"><strong>Errors (rows will be skipped):</strong><ul class="mb-0 small">';
            res.errors.forEach(e => errHtml += `<li>${e}</li>`);
            errHtml += '</ul></div>';
        }
        document.getElementById('validationErrors').innerHTML = errHtml;
        
        let warnHtml = '';
        if (res.warnings.length) {
            warnHtml = '<div class="alert alert-warning"><strong>Warnings:</strong><ul class="mb-0 small">';
            res.warnings.slice(0, 20).forEach(w => warnHtml += `<li>${w}</li>`);
            if (res.warnings.length > 20) warnHtml += `<li>...and ${res.warnings.length-20} more</li>`;
            warnHtml += '</ul></div>';
        }
        document.getElementById('validationWarnings').innerHTML = warnHtml;
        
        document.getElementById('btnExecuteImport').disabled = res.importable === 0;
        goToStep(3);
    }).catch(e => alert('Error: '+e.message));
}

function executeImport() {
    if (!confirm('Import all valid rows? This cannot be undone.')) return;
    
    document.getElementById('btnExecuteImport').disabled = true;
    document.getElementById('btnExecuteImport').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
    
    const fd = new FormData();
    fd.append('ajax_action', 'execute_import');
    
    fetch(window.location.href,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if (!res.success) { alert(res.message); return; }
        
        let html = `<i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h4>Import Complete!</h4>
            <div class="row justify-content-center mt-3">
                <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h3>${res.imported}</h3><small>Imported</small></div></div></div>
                <div class="col-md-3"><div class="card bg-secondary text-white"><div class="card-body"><h3>${res.skipped}</h3><small>Skipped</small></div></div></div>
            </div>`;
        
        if (res.errors.length) {
            html += '<div class="alert alert-warning mt-3 text-start"><strong>Import Errors:</strong><ul class="mb-0 small">';
            res.errors.forEach(e => html += `<li>${e}</li>`);
            html += '</ul></div>';
        }
        
        html += `<div class="mt-4">
            <a href="task_dashboard.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Go to Dashboard</a>
            <a href="task_import.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-file-import"></i> Import More</a>
        </div>`;
        
        document.getElementById('importResults').innerHTML = html;
        goToStep(5);
    }).catch(e => alert('Error: '+e.message));
}
</script>
<?php require_once 'footer.php'; ?>