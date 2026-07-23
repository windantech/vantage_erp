<?php
/**
 * lead_dashboard.php  —  VASL Lead Intelligence
 *
 * Matches the ERP page shell (header.php, #content-wrapper, top_nav.php,
 * bg_main cards, border-left stat cards). Reads from the precomputed
 * lead_insights table with SERVER-SIDE pagination (handles 20k+ rows).
 *
 * AJAX endpoints (handled before header.php is included):
 *   ?ajax=rows         → DataTables server-side data (paginated/filtered/sorted)
 *   ?ajax=export       → stream full filtered set as XLSX (SpreadsheetML)
 *   POST action=update_followup → save lead_status/assigned_to/last_contact_date
 *   POST action=ai_suggest      → on-demand OpenAI suggestion (cached)
 *
 * Access control is enforced inside header.php (same as every ERP page).
 */

/* ---- AJAX endpoints must run BEFORE any template output -------------- */
$__ajax = $_GET['ajax'] ?? '';
$__post = $_POST['action'] ?? '';

if ($__ajax !== '' || $__post !== '') {
require_once '../../database/conn.php';              // adjust if your conn path differs
    require_once 'lead_helpers.php';
    require_once 'lead_ai.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
    while (ob_get_level()) { ob_end_clean(); }   // drop any buffered HTML

    /* ---------- Shared filter builder (used by rows + export) ---------- */
    $buildFilters = function(array $src): array {
        $where = ['1=1']; $params = []; $types = '';
        $map = [['source','source'],['country_norm','country'],
                ['lead_segment','segment'],['lead_status','status'],
                ['assigned_to','assigned']];
        foreach ($map as $pair) {
            $col = $pair[0]; $key = $pair[1];
            if (isset($src[$key]) && $src[$key] !== '') {
                $where[] = "$col = ?"; $params[] = $src[$key]; $types .= 's';
            }
        }
        $conv = $src['converted'] ?? '0';
        if ($conv === '0' || $conv === '1') {
            $where[] = "is_converted = ?"; $params[] = $conv; $types .= 's';
        }
        return [implode(' AND ', $where), $params, $types];
    };

    /* ================= DataTables server-side rows ================= */
    if ($__ajax === 'rows') {
        header('Content-Type: application/json');
        $conn->set_charset('utf8mb4');

        $draw   = (int)($_GET['draw'] ?? 1);
        $start  = max(0, (int)($_GET['start'] ?? 0));
        $length = (int)($_GET['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;
        $search = trim($_GET['search']['value'] ?? '');

        $f = $buildFilters($_GET);
        $whereSql = $f[0]; $params = $f[1]; $types = $f[2];

        // Free-text search across a few columns
        if ($search !== '') {
            $whereSql .= " AND (fullname LIKE ? OR email LIKE ? OR organization LIKE ?
                                OR country_norm LIKE ? OR position LIKE ? OR program_or_term LIKE ?)";
            $like = '%' . $search . '%';
            for ($i=0;$i<6;$i++){ $params[] = $like; $types .= 's'; }
        }

        // Sorting (whitelist columns by index → column name)
        $cols = ['lead_score','fullname','country_norm','organization','position',
                 'program_or_term','lead_segment','source','lead_status',
                 'assigned_to','last_contact_date'];
        $orderColIdx = (int)($_GET['order'][0]['column'] ?? 0);
        $orderDir    = (strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $orderCol    = $cols[$orderColIdx] ?? 'lead_score';

        // Total (filtered) count
        $cntSql = "SELECT COUNT(*) c FROM lead_insights WHERE $whereSql";
        $cst = $conn->prepare($cntSql);
        if ($types !== '') $cst->bind_param($types, ...$params);
        $cst->execute();
        $recordsFiltered = (int)($cst->get_result()->fetch_assoc()['c'] ?? 0);
        $cst->close();

        // Page of data
        $sql = "SELECT source, source_id, fullname, email, phone, country_norm,
                       organization, position, program_or_term, lead_segment,
                       lead_score, lead_status, assigned_to, last_contact_date,
                       is_converted, ai_suggestion
                FROM lead_insights
                WHERE $whereSql
                ORDER BY `$orderCol` $orderDir, lead_score DESC
                LIMIT ? OFFSET ?";
        $pTypes = $types . 'ii';
        $pParams = array_merge($params, [$length, $start]);
        $st = $conn->prepare($sql);
        $st->bind_param($pTypes, ...$pParams);
        $st->execute();
        $rs = $st->get_result();

        $segLabels = ['decision_maker'=>'Decision-makers','manager'=>'Managers',
                      'professional'=>'Professionals','individual'=>'Individual learners'];
        $esc = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

        $data = [];
        while ($r = $rs->fetch_assoc()) {
            $sc = (int)$r['lead_score'];
            $scCls = $sc>=40?'hot':($sc>=25?'warm':'cold');
            $segLbl = $segLabels[$r['lead_segment']] ?? $r['lead_segment'];
            $hasAi = !empty($r['ai_suggestion']);
            $data[] = [
                'score'   => '<span class="li-score '.$scCls.'">'.$sc.'</span>',
                'name'    => '<div class="li-name">'.$esc($r['fullname'] ?: '-').'</div>'
                           . '<div class="li-email">'.$esc($r['email']).'</div>',
                'country' => $esc($r['country_norm'] ?: '-'),
                'org'     => $esc($r['organization'] ?: '-'),
                'pos'     => $esc($r['position'] ?: '-'),
                'course'  => $esc($r['program_or_term'] ?: '-'),
                'segment' => '<span class="li-pill seg-'.$esc($r['lead_segment']).'">'.$esc($segLbl).'</span>',
                'source'  => '<span class="li-src '.$esc($r['source']).'">'
                           . ($r['source']==='virtual'?'Virtual':'Int\'l').'</span>',
                'status'  => $esc($r['lead_status'] ?: '-'),
                'assigned'=> $esc($r['assigned_to'] ?: '-'),
                'contact' => $esc($r['last_contact_date'] ?: '-'),
                'actions' => '<button class="btn btn-sm btn-outline-primary rounded-0 edit-btn" '
                           . 'data-source="'.$esc($r['source']).'" data-id="'.$esc($r['source_id']).'" '
                           . 'data-name="'.$esc($r['fullname']).'" data-status="'.$esc($r['lead_status']).'" '
                           . 'data-assigned="'.$esc($r['assigned_to']).'" data-contact="'.$esc($r['last_contact_date']).'">'
                           . '<i class="bi bi-pencil"></i></button> '
                           . '<button class="btn btn-sm '.($hasAi?'btn-success':'btn-outline-success').' rounded-0 ai-btn" '
                           . 'data-source="'.$esc($r['source']).'" data-id="'.$esc($r['source_id']).'" '
                           . 'data-name="'.$esc($r['fullname']).'" title="AI suggested message">'
                           . '<i class="bi bi-robot"></i></button>',
            ];
        }
        $st->close();

        $totalAll = (int)($conn->query("SELECT COUNT(*) c FROM lead_insights")
                          ->fetch_assoc()['c'] ?? 0);

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalAll,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
        exit;
    }

    /* ================= XLSX export (full filtered set) ================= */
    if ($__ajax === 'export') {
        $f = $buildFilters($_GET);
        $whereSql = $f[0]; $params = $f[1]; $types = $f[2];
        $sql = "SELECT lead_score, fullname, email, phone, country_norm, organization,
                       position, program_or_term, lead_segment, source, lead_status,
                       assigned_to, last_contact_date
                FROM lead_insights WHERE $whereSql
                ORDER BY lead_score DESC";
        $st = $conn->prepare($sql);
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="vasl_leads_'.date('Ymd_His').'.xls"');
        $x = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        echo "<?xml version=\"1.0\"?>\n";
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
           . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Leads"><Table>';
        $headers = ['Score','Name','Email','Phone','Country','Organization','Position',
                    'Course/Event','Segment','Source','Status','Assigned','Last Contact'];
        echo '<Row>';
        foreach ($headers as $hd) echo '<Cell><Data ss:Type="String">'.$x($hd).'</Data></Cell>';
        echo '</Row>';
        while ($r = $rs->fetch_assoc()) {
            echo '<Row>';
            echo '<Cell><Data ss:Type="Number">'.(int)$r['lead_score'].'</Data></Cell>';
            foreach (['fullname','email','phone','country_norm','organization','position',
                      'program_or_term','lead_segment','source','lead_status','assigned_to',
                      'last_contact_date'] as $fld) {
                echo '<Cell><Data ss:Type="String">'.$x($r[$fld]).'</Data></Cell>';
            }
            echo '</Row>';
        }
        echo '</Table></Worksheet></Workbook>';
        $st->close();
        exit;
    }

    /* ================= Update follow-up ================= */
    if ($__post === 'update_followup') {
        header('Content-Type: application/json');
        $source   = $_POST['source'] ?? '';
        $sourceId = $_POST['source_id'] ?? '';
        $status   = trim($_POST['lead_status'] ?? '');
        $assigned = trim($_POST['assigned_to'] ?? '');
        $contact  = trim($_POST['last_contact_date'] ?? '');

        if ($contact !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $contact)) {
            echo json_encode(['ok'=>false,'error'=>'Invalid date']); exit;
        }
        if (!in_array($source, ['virtual','international'], true)) {
            echo json_encode(['ok'=>false,'error'=>'Bad source']); exit;
        }
        if ($source === 'virtual') { $table='register'; $keyCol='entry_id'; }
        else { $table='ticket_congress'; $keyCol='ticket_id'; }

        $contactVal = ($contact === '') ? null : $contact;

        $st = $conn->prepare("UPDATE `$table` SET lead_status=?, assigned_to=?, last_contact_date=?
                              WHERE `$keyCol`=?");
        $st->bind_param('ssss', $status, $assigned, $contactVal, $sourceId);
        $ok = $st->execute(); $st->close();

        $st2 = $conn->prepare("UPDATE lead_insights SET lead_status=?, assigned_to=?, last_contact_date=?
                               WHERE source=? AND source_id=?");
        $st2->bind_param('sssss', $status, $assigned, $contactVal, $source, $sourceId);
        $st2->execute(); $st2->close();

        echo json_encode(['ok'=>(bool)$ok]); exit;
    }

    /* ================= AI suggestion (on-demand) ================= */
    if ($__post === 'ai_suggest') {
        header('Content-Type: application/json');
        $source   = $_POST['source'] ?? '';
        $sourceId = $_POST['source_id'] ?? '';
        $force    = !empty($_POST['force']);
        if (!in_array($source, ['virtual','international'], true)) {
            echo json_encode(['ok'=>false,'error'=>'Bad source']); exit;
        }
        $res = lead_ai_get_suggestion($conn, $source, $sourceId, $force);
        echo json_encode($res); exit;
    }

    exit;
}

/* ---------------------------------------------------------------------- */
/*  NORMAL PAGE RENDER                                                    */
/* ---------------------------------------------------------------------- */
require_once 'header.php';            // provides $conn, session, access control
require_once 'lead_helpers.php';
lead_ensure_schema($conn);

$login_type = $_SESSION['login_type'] ?? 0;
$segLabels  = ['decision_maker'=>'Decision-makers','manager'=>'Managers',
               'professional'=>'Professionals','individual'=>'Individual learners'];

/* --- Headline counts (cheap COUNT queries) --- */
function li_count(mysqli $conn, string $where = '1=1'): int {
    $r = $conn->query("SELECT COUNT(*) c FROM lead_insights WHERE $where");
    return (int)($r ? $r->fetch_assoc()['c'] : 0);
}
$totalLeads = li_count($conn, 'is_converted = 0');
$hotLeads   = li_count($conn, 'is_converted = 0 AND lead_score >= 40');
$converted  = li_count($conn, 'is_converted = 1');
$grandTotal = li_count($conn);
$baseRate   = $grandTotal ? round($converted / $grandTotal * 100, 1) : 0;

/* --- Filter dropdown options --- */
function li_distinct(mysqli $conn, string $col): array {
    $out = [];
    if ($res = $conn->query("SELECT DISTINCT $col v FROM lead_insights
                             WHERE $col IS NOT NULL AND $col <> '' ORDER BY $col")) {
        while ($x = $res->fetch_assoc()) $out[] = $x['v'];
    }
    return $out;
}
$countryOpts  = li_distinct($conn, 'country_norm');
$statusOpts   = li_distinct($conn, 'lead_status');
$assignedOpts = li_distinct($conn, 'assigned_to');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Lead Intelligence</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <a href="?ajax=export" class="btn btn-sm btn-light rounded-0 me-2" id="exportBtn">
                               <i class="bi bi-file-earmark-excel"></i> Export
                            </a>
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-2">
                                <i class="bi bi-arrow-repeat text-white"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body pb-0">
                    <!-- Stat cards -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Leads</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($totalLeads) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hot (score &ge; 40)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($hotLeads) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Converted</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($converted) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Conversion Rate</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $baseRate ?>%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <form method="get" id="filterForm" class="row mb-3 g-2">
                        <div class="col-md-2">
                            <select name="source" class="form-select rounded-0">
                                <option value="">All sources</option>
                                <option value="virtual"<?= ($_GET['source']??'')==='virtual'?' selected':'' ?>>Virtual</option>
                                <option value="international"<?= ($_GET['source']??'')==='international'?' selected':'' ?>>International</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="country" class="form-select rounded-0">
                                <option value="">All countries</option>
                                <?php foreach ($countryOpts as $o): ?>
                                    <option value="<?= h($o) ?>"<?= ($_GET['country']??'')===$o?' selected':'' ?>><?= h($o) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="segment" class="form-select rounded-0">
                                <option value="">All segments</option>
                                <?php foreach ($segLabels as $k=>$lbl): ?>
                                    <option value="<?= h($k) ?>"<?= ($_GET['segment']??'')===$k?' selected':'' ?>><?= h($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select rounded-0">
                                <option value="">All statuses</option>
                                <?php foreach ($statusOpts as $o): ?>
                                    <option value="<?= h($o) ?>"<?= ($_GET['status']??'')===$o?' selected':'' ?>><?= h($o) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="converted" class="form-select rounded-0">
                                <option value="0"<?= ($_GET['converted']??'0')==='0'?' selected':'' ?>>Leads only</option>
                                <option value="1"<?= ($_GET['converted']??'')==='1'?' selected':'' ?>>Converted only</option>
                                <option value="all"<?= ($_GET['converted']??'')==='all'?' selected':'' ?>>All</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn bg_main text-white rounded-0 w-100"><i class="bi bi-funnel"></i> Filter</button>
                        </div>
                    </form>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Score</th><th>Lead</th><th>Country</th><th>Organization</th>
                                    <th>Position</th><th>Course / Event</th><th>Segment</th>
                                    <th>Source</th><th>Status</th><th>Assigned</th>
                                    <th>Last Contact</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Follow-up modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content rounded-0">
    <div class="modal-header bg_main"><h6 class="modal-title text-white">Update Follow-up</h6>
      <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="f_source"><input type="hidden" id="f_id">
      <p class="text-muted small mb-3" id="f_name"></p>
      <div class="mb-2"><label class="form-label small">Lead status</label>
        <input id="f_status" class="form-control rounded-0" list="statusList"></div>
      <datalist id="statusList"><?php foreach ($statusOpts as $o): ?><option value="<?= h($o) ?>"><?php endforeach; ?></datalist>
      <div class="mb-2"><label class="form-label small">Assigned to</label>
        <input id="f_assigned" class="form-control rounded-0" list="assignedList"></div>
      <datalist id="assignedList"><?php foreach ($assignedOpts as $o): ?><option value="<?= h($o) ?>"><?php endforeach; ?></datalist>
      <div class="mb-2"><label class="form-label small">Last contact date</label>
        <input type="date" id="f_contact" class="form-control rounded-0"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Cancel</button>
      <button class="btn bg_main text-white rounded-0" id="saveFollowup">Save</button>
    </div>
  </div></div>
</div>

<!-- AI suggestion modal -->
<div class="modal fade" id="aiModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content rounded-0">
    <div class="modal-header bg_main">
      <h6 class="modal-title text-white"><i class="bi bi-robot me-1"></i>AI Suggested Approach</h6>
      <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="text-muted small mb-2" id="ai_name"></p>
      <div id="ai_loading" class="text-center py-4" style="display:none">
        <div class="spinner-border text-secondary"></div>
        <div class="small text-muted mt-2">Generating suggestion…</div>
      </div>
      <pre id="ai_text" class="p-3 bg-light rounded-0" style="white-space:pre-wrap;font-family:inherit;font-size:.9rem;display:none"></pre>
      <div id="ai_meta" class="small text-muted mt-2"></div>
      <div id="ai_error" class="alert alert-warning rounded-0" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline-secondary rounded-0" id="ai_regen"><i class="bi bi-arrow-repeat"></i> Regenerate</button>
      <button class="btn btn-outline-primary rounded-0" id="ai_copy"><i class="bi bi-clipboard"></i> Copy</button>
      <button class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Close</button>
    </div>
  </div></div>
</div>

<style>
.border-left-primary { border-left:.25rem solid #4e73df !important; }
.border-left-success { border-left:.25rem solid #1cc88a !important; }
.border-left-info    { border-left:.25rem solid #36b9cc !important; }
.border-left-warning { border-left:.25rem solid #f6c23e !important; }
.li-score{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:23px;
  border-radius:4px;font-weight:700;font-size:.78rem;color:#fff;}
.li-score.hot{background:#1cc88a;} .li-score.warm{background:#f6c23e;} .li-score.cold{background:#858796;}
.li-pill{display:inline-block;padding:.15rem .5rem;border-radius:1rem;font-size:.72rem;font-weight:600;}
.li-pill.seg-decision_maker{background:#e7f0ff;color:#4e73df;}
.li-pill.seg-manager{background:#e6f7ef;color:#1cc88a;}
.li-pill.seg-professional{background:#fef3e2;color:#dda20a;}
.li-pill.seg-individual{background:#eef1f5;color:#858796;}
.li-src{font-size:.72rem;font-weight:600;padding:.12rem .45rem;border-radius:3px;}
.li-src.virtual{background:#eef1f5;color:#5a5c69;} .li-src.international{background:#fdeef0;color:#c0395a;}
.li-name{font-weight:600;font-size:.86rem;} .li-email{color:#858796;font-size:.76rem;}
</style>

<?php require_once 'footer.php'; ?>

<script>
(function waitForJQuery(){
  if (typeof window.jQuery === 'undefined') { return setTimeout(waitForJQuery, 50); }
  jQuery(function($){
  function filterData(d){
    d.source    = $('select[name=source]').val();
    d.country   = $('select[name=country]').val();
    d.segment   = $('select[name=segment]').val();
    d.status    = $('select[name=status]').val();
    d.converted = $('select[name=converted]').val();
    return d;
  }

  var table = $('#dataTable').DataTable({
    serverSide: true,
    processing: true,
    pageLength: 25,
    lengthMenu: [10,25,50,100],
    order: [[0,'desc']],
    ajax: {
      url: 'lead_dashboard.php?ajax=rows',
      type: 'GET',
      data: filterData
    },
    columns: [
      {data:'score'}, {data:'name'}, {data:'country'}, {data:'org'},
      {data:'pos'}, {data:'course'}, {data:'segment'}, {data:'source'},
      {data:'status'}, {data:'assigned'}, {data:'contact'},
      {data:'actions', orderable:false, searchable:false}
    ],
    language:{ search:'', searchPlaceholder:'Search leads…', processing:'Loading…' }
  });

  $('#filterForm').on('submit', function(e){ e.preventDefault(); table.ajax.reload(); updateExportLink(); });

  function updateExportLink(){
    var p = $.param({
      ajax:'export',
      source:$('select[name=source]').val(), country:$('select[name=country]').val(),
      segment:$('select[name=segment]').val(), status:$('select[name=status]').val(),
      converted:$('select[name=converted]').val()
    });
    $('#exportBtn').attr('href','lead_dashboard.php?'+p);
  }
  updateExportLink();

  // Create modal instances once (Bootstrap 5.0 compatible)
  var editModal = new bootstrap.Modal(document.getElementById('editModal'));
  var aiModal   = new bootstrap.Modal(document.getElementById('aiModal'));

  // ---- Follow-up modal ----
  $('#dataTable tbody').on('click', '.edit-btn', function(){
    $('#f_source').val($(this).data('source')); $('#f_id').val($(this).data('id'));
    $('#f_name').text($(this).data('name')||'(no name)');
    $('#f_status').val($(this).data('status')); $('#f_assigned').val($(this).data('assigned'));
    $('#f_contact').val($(this).data('contact'));
    editModal.show();
  });
  $('#saveFollowup').on('click', function(){
    var btn=$(this); btn.prop('disabled',true);
    $.post('lead_dashboard.php',{action:'update_followup',
      source:$('#f_source').val(), source_id:$('#f_id').val(),
      lead_status:$('#f_status').val(), assigned_to:$('#f_assigned').val(),
      last_contact_date:$('#f_contact').val()},
      function(res){ btn.prop('disabled',false);
        if(res&&res.ok){ Swal.fire({icon:'success',title:'Saved',timer:1100,showConfirmButton:false});
          editModal.hide(); table.ajax.reload(null,false);}
        else Swal.fire({icon:'error',title:'Failed',text:(res&&res.error)||'Could not save'});
      },'json').fail(function(){btn.prop('disabled',false);
        Swal.fire({icon:'error',title:'Error',text:'Request failed'});});
  });

  // ---- AI suggestion modal ----
  var aiCtx = {source:'',id:''};
  function loadAi(force){
    $('#ai_loading').show(); $('#ai_text').hide(); $('#ai_error').hide(); $('#ai_meta').text('');
    $.post('lead_dashboard.php',{action:'ai_suggest',source:aiCtx.source,source_id:aiCtx.id,force:force?1:0},
      function(res){ $('#ai_loading').hide();
        if(res&&res.ok){ $('#ai_text').text(res.text).show();
          $('#ai_meta').text((res.cached?'Cached suggestion':'Newly generated')+(res.generated_at?(' · '+res.generated_at):''));
          if(!res.cached) table.ajax.reload(null,false); }
        else { $('#ai_error').text((res&&res.error)||'Could not generate').show(); }
      },'json').fail(function(){ $('#ai_loading').hide();
        $('#ai_error').text('Request failed').show(); });
  }
  $('#dataTable tbody').on('click', '.ai-btn', function(){
    aiCtx.source=$(this).data('source'); aiCtx.id=$(this).data('id');
    $('#ai_name').text($(this).data('name')||'(no name)');
    aiModal.show();
    loadAi(false);
  });
  $('#ai_regen').on('click', function(){ loadAi(true); });
  $('#ai_copy').on('click', function(){
    navigator.clipboard.writeText($('#ai_text').text()||'')
      .then(function(){ Swal.fire({icon:'success',title:'Copied',timer:900,showConfirmButton:false}); });
  });
  }); // jQuery ready
})(); // waitForJQuery
</script>