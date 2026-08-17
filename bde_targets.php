<?php
/**
 * bde_targets.php — admin screen to set BDE performance targets.
 *
 * Self-migrating: creates the `bde_targets` table on first load (CREATE TABLE IF NOT EXISTS),
 * so no separate DB step is needed after deploy. Lets an admin add / edit / delete targets,
 * scoped to a whole department (a default that applies to all its BDEs) or to a specific BDE
 * (an override). Compound targets (a count AND a revenue line) are just two rows.
 *
 * Phase 1: targets only. The BDE dashboard reads these to show progress; commission comes later.
 */
session_start();
ob_start(); // buffer so we can Post/Redirect/Get after header.php has already emitted chrome
require_once 'header.php'; // auth + $conn (vantage_crm) + $role + chrome/left-nav
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); } // live is 8.1+: don't let mysqli throw

$is_admin = isset($role) && is_array($role) && in_array(777, $role);
if (!$is_admin) { http_response_code(403); exit('Forbidden — admin only.'); }

// --- ensure the table exists -------------------------------------------------
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bde_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_type ENUM('department','user') NOT NULL DEFAULT 'department',
    scope_ref VARCHAR(120) NOT NULL,
    scope_label VARCHAR(160) NOT NULL DEFAULT '',
    product VARCHAR(120) NOT NULL DEFAULT '',
    metric VARCHAR(60) NOT NULL,
    metric_label VARCHAR(120) NOT NULL DEFAULT '',
    unit ENUM('count','KES','USD') NOT NULL DEFAULT 'count',
    target_value DECIMAL(15,2) NOT NULL DEFAULT 0,
    threshold_pct DECIMAL(5,2) DEFAULT NULL,
    period_year SMALLINT DEFAULT NULL,
    period_month TINYINT DEFAULT NULL,
    notes VARCHAR(255) NOT NULL DEFAULT '',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_scope (scope_type, scope_ref),
    KEY idx_period (period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$me = (int) ($_SESSION['login_id'] ?? 0);
$flash = ''; $flash_ok = true;
if (!empty($_SESSION['bt_flash'])) { $flash = (string) $_SESSION['bt_flash']; $flash_ok = !empty($_SESSION['bt_flash_ok']); unset($_SESSION['bt_flash'], $_SESSION['bt_flash_ok']); }

// known metrics (label + default unit) — the form offers these; 'other' allows a custom label
$METRICS = [
    'revenue'             => ['Revenue / fees collected', 'KES'],
    'paid_clients'        => ['Paid clients', 'count'],
    'active_users'        => ['Active paying users', 'count'],
    'paid_staff'          => ['Active paid staff', 'count'],
    'corporate_clients'   => ['Corporate clients', 'count'],
    'clients_per_country' => ['Clients per country', 'count'],
    'countries_qualifying'=> ['Qualifying countries', 'count'],
    'open_programmes'     => ['Open-programme participants', 'count'],
    'other'               => ['Other (set label)', 'count'],
];

// Virtual is course-based: each course is owned by a BDE and carries a 100% revenue target
// (70% is derived from the threshold). Seed data from the approved course sheet.
// [owner first-name, course code, course name, 100% revenue, 100% clients, fee/client]
$VIRTUAL_SEED = [
    ['Purity',   'SMC',            'Senior Management Course',               1600000, 50, 32000],
    ['Purity',   'PS',             'Public Speaking',                         882805, 35, 25223],
    ['MaryAnne', 'SLDP',           'Senior Leadership Development Programme', 1448720, 35, 41392],
    ['MaryAnne', 'SSP',            'Supervisory Skills Programme',            1008920, 40, 25223],
    ['Dorcas',   'PM',             'Project Management',                       882805, 35, 25223],
    ['Lucky',    'M&E',            'Monitoring & Evaluation',                 1267600, 40, 31690],
    ['Lucky',    'RM',             'Resource Mobilisation',                   1008920, 40, 25223],
    ['Joy',      'DATA ANALYSIS',  'Data Analysis',                            882805, 35, 25223],
    ['Joy',      'TOT',            'Training of Trainers',                     882805, 35, 25223],
    ['Rachael',  'PA',             'Practical Accounting',                     882805, 35, 25223],
    ['Rachael',  'ADVANCED EXCEL', 'Advanced Excel',                           756690, 30, 25223],
];
// name variants to resolve each owner — FULL name first (specific), first-name only as last resort.
// Resolution also prefers the Virtual department, so a loose first name can't grab the wrong person.
$OWNER_VARIANTS = [
    'Purity'   => ['Purity'],
    'MaryAnne' => ['Maryanne Owuor', 'Maryanne', 'Mary Anne'],
    'Dorcas'   => ['Dorcas Mukami', 'Dorcas'],
    'Lucky'    => ['Lucky Anindo', 'Lucky'],
    'Joy'      => ['Joy Kendi', 'Kendi'], // NOT bare 'Joy' — matches "Joyce Wanjiku"
    'Rachael'  => ['Rachael Wambui', 'Rachael', 'Rachel'],
];

// Digital Solutions is product-based and compound. Deconstruct the two KPI sheets into clean lines
// (department-scoped; each Digital BDE sees only their product via the dashboard's product filter):
// Eval360 (Austin) and 360 Appraisal (Ruth). [product, metric, metric_label, unit, target, threshold%, note]
$DIGITAL_SEED = [
    ['Eval360 · Individual',           'active_users',      'Active paying users',   'count', 100,     80,   '$29 each · sold to individual users'],
    ['Eval360 · Individual',           'revenue',           'Fees collected',        'KES',   350000,  80,   'At least Kshs 350,000 collected'],
    ['Eval360 · Corporate setup',      'corporate_clients', 'New corporate clients', 'count', 2,       null, 'Signed, fully paid & onboarded'],
    ['Eval360 · Corporate setup',      'revenue',           'Setup fee',             'KES',   900000,  null, 'Kshs 900,000 per client, paid in full'],
    ['Eval360 · Corporate maintenance','revenue',           'Maintenance fee',       'KES',   100000,  null, 'Kshs 100,000 per client / month × 12 · all active corporate clients'],
    ['360 Appraisal',                  'paid_staff',        'Active paid staff',     'count', 600,     80,   'Kshs 2,000 per staff'],
    ['360 Appraisal',                  'revenue',           'First-month fees',      'KES',   1200000, 80,   '100% = Kshs 1.2M · 80% = Kshs 960,000'],
];

// --- handle writes -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && @mysqli_query($conn, "DELETE FROM bde_targets WHERE id = $id")) { $flash = 'Target deleted.'; }
        else { $flash = 'Could not delete.'; $flash_ok = false; }
    } else if ($action === 'save') {
        $id         = (int) ($_POST['id'] ?? 0);
        $scope_type = ($_POST['scope_type'] ?? 'department') === 'user' ? 'user' : 'department';
        $scope_ref  = trim((string) ($_POST['scope_ref'] ?? ''));
        $product    = trim((string) ($_POST['product'] ?? ''));
        $metric     = trim((string) ($_POST['metric'] ?? ''));
        $metric_lbl = trim((string) ($_POST['metric_label'] ?? ''));
        $unit       = in_array(($_POST['unit'] ?? 'count'), ['count','KES','USD'], true) ? $_POST['unit'] : 'count';
        $value      = (float) ($_POST['target_value'] ?? 0);
        $threshold  = ($_POST['threshold_pct'] ?? '') === '' ? null : (float) $_POST['threshold_pct'];
        $recurring  = isset($_POST['recurring']);
        $py = $pm = null;
        if (!$recurring && preg_match('/^(\d{4})-(\d{2})$/', (string) ($_POST['period'] ?? ''), $m)) { $py = (int) $m[1]; $pm = (int) $m[2]; }
        // derive scope label for display
        $scope_label = '';
        if ($scope_type === 'department') {
            $d = (int) $scope_ref;
            $lq = @mysqli_query($conn, "SELECT department_name FROM departments WHERE id = $d LIMIT 1");
            if ($lq && ($lr = mysqli_fetch_assoc($lq))) { $scope_label = (string) $lr['department_name']; }
        } else {
            $u = (int) $scope_ref;
            $lq = @mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = $u LIMIT 1");
            if ($lq && ($lr = mysqli_fetch_assoc($lq))) { $scope_label = (string) $lr['fullname']; }
        }
        if (!isset($METRICS[$metric])) { $metric = 'other'; }
        if ($metric_lbl === '') { $metric_lbl = $METRICS[$metric][0] ?? $metric; }

        if ($scope_ref === '' || $metric === '' || $value <= 0) {
            $flash = 'Pick a scope, a metric, and a target value greater than zero.'; $flash_ok = false;
        } else {
            $sr = mysqli_real_escape_string($conn, $scope_ref);
            $sl = mysqli_real_escape_string($conn, $scope_label);
            $pr = mysqli_real_escape_string($conn, $product);
            $mt = mysqli_real_escape_string($conn, $metric);
            $ml = mysqli_real_escape_string($conn, $metric_lbl);
            $un = mysqli_real_escape_string($conn, $unit);
            $no = mysqli_real_escape_string($conn, trim((string) ($_POST['notes'] ?? '')));
            $thSql = $threshold === null ? 'NULL' : (float) $threshold;
            $pySql = $py === null ? 'NULL' : (int) $py;
            $pmSql = $pm === null ? 'NULL' : (int) $pm;
            if ($id > 0) {
                $ok = @mysqli_query($conn, "UPDATE bde_targets SET
                    scope_type='$scope_type', scope_ref='$sr', scope_label='$sl', product='$pr',
                    metric='$mt', metric_label='$ml', unit='$un', target_value=$value,
                    threshold_pct=$thSql, period_year=$pySql, period_month=$pmSql, notes='$no'
                    WHERE id = $id");
                $flash = $ok ? 'Target updated.' : 'Update failed.'; $flash_ok = (bool) $ok;
            } else {
                $ok = @mysqli_query($conn, "INSERT INTO bde_targets
                    (scope_type, scope_ref, scope_label, product, metric, metric_label, unit, target_value, threshold_pct, period_year, period_month, notes, created_by)
                    VALUES ('$scope_type','$sr','$sl','$pr','$mt','$ml','$un',$value,$thSql,$pySql,$pmSql,'$no',$me)");
                $flash = $ok ? 'Target added.' : 'Insert failed.'; $flash_ok = (bool) $ok;
            }
        }
    } else if ($action === 'seed_virtual') {
        // Replace all previously-seeded course rows, then insert fresh for EVERY account matching each
        // owner. Duplicate logins (e.g. two "Maryanne Owuor") therefore BOTH get the same targets.
        @mysqli_query($conn, "DELETE FROM bde_targets WHERE scope_type='user' AND metric='revenue' AND metric_label='Course revenue'");
        $seeded = 0; $unresolved = []; $resolved = [];
        foreach ($VIRTUAL_SEED as $row) {
            list($owner, $code, $cname, $rev, $clients, $fee) = $row;
            if (!isset($resolved[$owner])) {
                $accts = [];
                foreach (($OWNER_VARIANTS[$owner] ?? [$owner]) as $variant) {
                    $lk = mysqli_real_escape_string($conn, $variant);
                    $pat = '%' . str_replace(' ', '%', $lk) . '%'; // tolerate extra/odd spacing between name parts
                    // all accounts for this name (handles duplicate logins); data-account first for tidy display
                    $q = @mysqli_query($conn, "SELECT ru.id, ru.fullname
                        FROM registered_users ru
                        WHERE ru.fullname LIKE '$pat'
                        ORDER BY EXISTS(SELECT 1 FROM intake i JOIN register r ON r.intake_id = i.intake_id WHERE i.assigned_to = ru.id) DESC, ru.id");
                    while ($q && ($rr = mysqli_fetch_assoc($q))) { $accts[] = ['id' => (int) $rr['id'], 'name' => (string) $rr['fullname']]; }
                    if (!empty($accts)) { break; } // first variant that matches wins
                }
                $resolved[$owner] = $accts;
            }
            if (empty($resolved[$owner])) { if (!in_array($owner, $unresolved, true)) { $unresolved[] = $owner; } continue; }
            $product = "$cname ($code)";
            $prE = mysqli_real_escape_string($conn, $product);
            $noteE = mysqli_real_escape_string($conn, "100%: $clients clients × Kshs " . number_format($fee) . " · 70% qualifying");
            foreach ($resolved[$owner] as $acct) {
                $ownerId = (int) $acct['id']; $slE = mysqli_real_escape_string($conn, $acct['name']);
                $ok = @mysqli_query($conn, "INSERT INTO bde_targets
                    (scope_type, scope_ref, scope_label, product, metric, metric_label, unit, target_value, threshold_pct, notes, created_by)
                    VALUES ('user','$ownerId','$slE','$prE','revenue','Course revenue','KES'," . (float) $rev . ",70,'$noteE',$me)");
                if ($ok) { $seeded++; }
            }
        }
        $flash = "Seeded $seeded Virtual course target(s) across all matching accounts"
            . (empty($unresolved) ? '.' : ". Couldn't match owner(s): " . implode(', ', $unresolved) . " — send me their exact CRM names.");
        $flash_ok = $seeded > 0 || empty($unresolved);
    } else if ($action === 'seed_digital') {
        // resolve the Digital Solutions department
        $dq = @mysqli_query($conn, "SELECT id, department_name FROM departments WHERE department_name LIKE '%digital%' ORDER BY id LIMIT 1");
        $ddid = 0; $dlabel = 'Digital Solutions';
        if ($dq && ($drow = mysqli_fetch_assoc($dq))) { $ddid = (int) $drow['id']; $dlabel = (string) $drow['department_name']; }
        if ($ddid <= 0) {
            $flash = "Couldn't find a Digital Solutions department to attach these to — create it first, or tell me the name."; $flash_ok = false;
        } else {
            // replace: clear existing Digital department targets for these products (removes the old compound rows)
            @mysqli_query($conn, "DELETE FROM bde_targets WHERE scope_type='department' AND (product LIKE '%eval%' OR product LIKE '%appraisal%' OR product LIKE '%360%')");
            $slE = mysqli_real_escape_string($conn, $dlabel);
            $n = 0;
            foreach ($DIGITAL_SEED as $row) {
                list($product, $metric, $mlabel, $unit, $target, $thr, $note) = $row;
                $prE = mysqli_real_escape_string($conn, $product);
                $mE  = mysqli_real_escape_string($conn, $metric);
                $mlE = mysqli_real_escape_string($conn, $mlabel);
                $noE = mysqli_real_escape_string($conn, $note);
                $thSql = $thr === null ? 'NULL' : (float) $thr;
                $ok = @mysqli_query($conn, "INSERT INTO bde_targets
                    (scope_type, scope_ref, scope_label, product, metric, metric_label, unit, target_value, threshold_pct, notes, created_by)
                    VALUES ('department','$ddid','$slE','$prE','$mE','$mlE','$unit'," . (float) $target . ",$thSql,'$noE',$me)");
                if ($ok) { $n++; }
            }
            $flash = "Seeded $n Digital target(s) — Eval360 (Austin) & 360 Appraisal (Ruth), deconstructed into individual / corporate setup / maintenance.";
            $flash_ok = $n > 0;
        }
    }
    // Post/Redirect/Get: on a successful write, redirect so a browser refresh can't resubmit
    // the same target, and the form comes back empty ready for the next entry.
    if (in_array($action, ['save', 'delete', 'seed_virtual', 'seed_digital'], true) && $flash_ok && $flash !== '') {
        $_SESSION['bt_flash'] = $flash; $_SESSION['bt_flash_ok'] = true;
        header('Location: bde_targets.php'); exit;
    }
}

// --- data for the form + list ------------------------------------------------
$departments = [];
$dq = @mysqli_query($conn, "SELECT id, department_name FROM departments ORDER BY department_name");
while ($dq && ($dr = mysqli_fetch_assoc($dq))) { $departments[(int) $dr['id']] = (string) $dr['department_name']; }

$users = [];
$uq = @mysqli_query($conn, "SELECT ru.id, ru.fullname, COALESCE(d.department_name,'') dept
    FROM registered_users ru
    LEFT JOIN staff s ON ru.staff_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE ru.fullname <> '' ORDER BY ru.fullname");
while ($uq && ($ur = mysqli_fetch_assoc($uq))) { $users[(int) $ur['id']] = trim($ur['fullname'] . ($ur['dept'] !== '' ? ' — ' . $ur['dept'] : '')); }

// row being edited (prefill)
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $er = @mysqli_query($conn, "SELECT * FROM bde_targets WHERE id = $eid LIMIT 1");
    if ($er && ($edit = mysqli_fetch_assoc($er))) { /* prefill below */ }
}

// existing targets, newest first
$rows = [];
$lq = @mysqli_query($conn, "SELECT * FROM bde_targets ORDER BY scope_type, scope_label, product, metric");
while ($lq && ($lr = mysqli_fetch_assoc($lq))) { $rows[] = $lr; }

function fmt_period($y, $m) {
    if (!$y || !$m) { return 'Every month'; }
    return date('M Y', mktime(0, 0, 0, (int) $m, 1, (int) $y));
}
function fmt_value($v, $unit) {
    $n = (float) $v;
    $num = rtrim(rtrim(number_format($n, 2), '0'), '.');
    if ($unit === 'KES') { return 'Kshs ' . number_format($n, 0); }
    if ($unit === 'USD') { return '$' . number_format($n, 0); }
    return $num;
}
$ev = function ($k, $d = '') use ($edit) { return $edit && isset($edit[$k]) ? $edit[$k] : $d; };
?>
<section id="content-wrapper" class="d-flex flex-column">
  <div id="content">
    <?php require_once 'top_nav.php'; ?>

    <style>
      .bt-wrap{max-width:1120px;margin:18px auto;padding:0 16px;font:14px/1.5 system-ui,Segoe UI,Roboto,sans-serif;color:#0e1726}
      .bt-wrap h2{font-size:22px;font-weight:700;margin:0 0 4px}
      .bt-wrap .sub{color:#64748b;margin:0 0 18px}
      .bt-card{background:#fff;border:1px solid #e5e9f0;border-radius:12px;padding:18px 20px;margin-bottom:18px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
      .bt-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
      .bt-grid .full{grid-column:1/-1}
      .bt-field label{display:block;font-size:12px;font-weight:600;color:#475569;margin:0 0 4px;text-transform:uppercase;letter-spacing:.02em}
      .bt-field input,.bt-field select,.bt-field textarea{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff}
      .bt-field .hint{font-size:12px;color:#94a3b8;margin-top:3px}
      .bt-row{display:flex;align-items:center;gap:8px}
      .bt-btn{display:inline-block;border:0;border-radius:8px;padding:10px 18px;font-weight:600;font-size:14px;cursor:pointer}
      .bt-btn.primary{background:#2563eb;color:#fff}
      .bt-btn.ghost{background:#f1f5f9;color:#334155}
      .bt-btn.mini{padding:5px 10px;font-size:12px}
      .bt-btn.danger{background:#fee2e2;color:#b91c1c}
      table.bt-tbl{width:100%;border-collapse:collapse;font-size:13px}
      table.bt-tbl th{ text-align:left;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.03em;padding:8px 10px;border-bottom:2px solid #eef2f7}
      table.bt-tbl td{padding:9px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
      table.bt-tbl tr:hover td{background:#f8fafc}
      .pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600}
      .pill.dept{background:#e0edff;color:#1d4ed8}
      .pill.user{background:#e7f7ee;color:#137a43}
      .flash{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-weight:600}
      .flash.ok{background:#e7f7ee;color:#137a43}
      .flash.err{background:#fee2e2;color:#b91c1c}
      @media(max-width:640px){.bt-grid{grid-template-columns:1fr}}
    </style>

    <div class="bt-wrap">
      <h2>BDE Performance Targets</h2>
      <p class="sub">Set monthly targets per department (applies to all its BDEs) or per specific BDE (override). The dashboard reads these to show progress. Commission is a later phase.</p>

      <?php if ($flash !== ''): ?>
        <div class="flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash); ?></div>
      <?php endif; ?>

      <div class="bt-card">
        <form method="post">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?php echo (int) $ev('id', 0); ?>">
          <div class="bt-grid">
            <div class="bt-field">
              <label>Scope</label>
              <select name="scope_type" id="scopeType">
                <option value="department"<?php echo $ev('scope_type') === 'department' ? ' selected' : ''; ?>>Whole department (default)</option>
                <option value="user"<?php echo $ev('scope_type') === 'user' ? ' selected' : ''; ?>>Specific BDE (override)</option>
              </select>
            </div>
            <div class="bt-field">
              <label>Who</label>
              <select name="scope_ref" id="scopeRef">
                <optgroup label="Departments" id="optDepts">
                  <?php foreach ($departments as $id => $name): ?>
                    <option data-kind="department" value="<?php echo $id; ?>"<?php echo ($ev('scope_type') === 'department' && (int) $ev('scope_ref') === $id) ? ' selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="BDEs" id="optUsers">
                  <?php foreach ($users as $id => $name): ?>
                    <option data-kind="user" value="<?php echo $id; ?>"<?php echo ($ev('scope_type') === 'user' && (int) $ev('scope_ref') === $id) ? ' selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </div>

            <div class="bt-field">
              <label>Product / stream <span style="text-transform:none;color:#94a3b8">(optional)</span></label>
              <input type="text" name="product" value="<?php echo htmlspecialchars($ev('product')); ?>" placeholder="e.g. Eval360, 360 Appraisal, SMC">
            </div>
            <div class="bt-field">
              <label>Metric</label>
              <select name="metric" id="metricSel">
                <?php foreach ($METRICS as $k => $mv): ?>
                  <option value="<?php echo $k; ?>" data-unit="<?php echo $mv[1]; ?>"<?php echo $ev('metric') === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($mv[0]); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="bt-field">
              <label>Custom label <span style="text-transform:none;color:#94a3b8">(optional)</span></label>
              <input type="text" name="metric_label" value="<?php echo htmlspecialchars($ev('metric_label')); ?>" placeholder="shown on the dashboard">
            </div>
            <div class="bt-field">
              <label>Unit</label>
              <select name="unit" id="unitSel">
                <?php foreach (['count' => 'Count', 'KES' => 'Kshs (KES)', 'USD' => 'US$ (USD)'] as $u => $ul): ?>
                  <option value="<?php echo $u; ?>"<?php echo $ev('unit', 'count') === $u ? ' selected' : ''; ?>><?php echo $ul; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="bt-field">
              <label>Target value</label>
              <input type="number" name="target_value" step="0.01" min="0" value="<?php echo htmlspecialchars($ev('target_value')); ?>" placeholder="e.g. 600 or 1200000">
            </div>
            <div class="bt-field">
              <label>Qualifying threshold % <span style="text-transform:none;color:#94a3b8">(optional)</span></label>
              <input type="number" name="threshold_pct" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($ev('threshold_pct')); ?>" placeholder="e.g. 70 or 80">
            </div>

            <div class="bt-field">
              <label>Applies to</label>
              <div class="bt-row">
                <label style="font-weight:500;text-transform:none;letter-spacing:0;color:#334155;display:flex;align-items:center;gap:6px;margin:0">
                  <input type="checkbox" name="recurring" id="recurring" style="width:auto" <?php echo (!$edit || (empty($edit['period_year']))) ? 'checked' : ''; ?>> Every month (recurring)
                </label>
              </div>
            </div>
            <div class="bt-field">
              <label>Specific month</label>
              <input type="month" name="period" id="periodInput" value="<?php echo ($edit && $edit['period_year']) ? sprintf('%04d-%02d', $edit['period_year'], $edit['period_month']) : ''; ?>">
              <div class="hint">Leave "Every month" ticked for a standing target; pick a month to override just that month.</div>
            </div>

            <div class="bt-field full">
              <label>Notes <span style="text-transform:none;color:#94a3b8">(optional)</span></label>
              <input type="text" name="notes" value="<?php echo htmlspecialchars($ev('notes')); ?>" placeholder="e.g. Kshs 2,000 per staff; renewal gated at 80%">
            </div>
          </div>
          <div style="margin-top:16px;display:flex;gap:10px">
            <button class="bt-btn primary" type="submit"><?php echo $edit ? 'Update target' : 'Add target'; ?></button>
            <?php if ($edit): ?><a class="bt-btn ghost" href="bde_targets.php">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>

      <div class="bt-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;flex-wrap:wrap">
          <strong style="font-size:15px">Current targets (<?php echo count($rows); ?>)</strong>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <form method="post" onsubmit="return confirm('Auto-fill the 11 Virtual course revenue targets (100% + derived 70%)? Existing ones are skipped.')">
              <input type="hidden" name="action" value="seed_virtual">
              <button class="bt-btn ghost mini" type="submit">↧ Seed Virtual course targets</button>
            </form>
            <form method="post" onsubmit="return confirm('Deconstruct the Digital targets (Eval360 + 360 Appraisal) into clean lines? This REPLACES existing Digital department targets.')">
              <input type="hidden" name="action" value="seed_digital">
              <button class="bt-btn ghost mini" type="submit">↧ Seed Digital targets</button>
            </form>
          </div>
        </div>
        <?php if (empty($rows)): ?>
          <p style="color:#94a3b8;margin:8px 0">No targets yet. Add the first one above.</p>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table class="bt-tbl">
            <thead><tr>
              <th>Scope</th><th>Product</th><th>Metric</th><th>Target</th><th>Threshold</th><th>Period</th><th></th>
            </tr></thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><span class="pill <?php echo $r['scope_type'] === 'user' ? 'user' : 'dept'; ?>"><?php echo $r['scope_type'] === 'user' ? 'BDE' : 'Dept'; ?></span> <?php echo htmlspecialchars($r['scope_label'] ?: $r['scope_ref']); ?></td>
                  <td><?php echo htmlspecialchars($r['product'] ?: '—'); ?></td>
                  <td><?php echo htmlspecialchars($r['metric_label'] ?: $r['metric']); ?></td>
                  <td>
                    <strong><?php echo htmlspecialchars(fmt_value($r['target_value'], $r['unit'])); ?></strong>
                    <?php if ($r['threshold_pct'] !== null): $thpct = (float) $r['threshold_pct']; ?>
                      <div style="color:#64748b;font-size:11px"><?php echo htmlspecialchars(rtrim(rtrim(number_format($thpct, 2), '0'), '.')); ?>% → <?php echo htmlspecialchars(fmt_value((float) $r['target_value'] * $thpct / 100, $r['unit'])); ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $r['threshold_pct'] !== null ? htmlspecialchars(rtrim(rtrim(number_format((float) $r['threshold_pct'], 2), '0'), '.')) . '%' : '—'; ?></td>
                  <td><?php echo htmlspecialchars(fmt_period($r['period_year'], $r['period_month'])); ?></td>
                  <td style="white-space:nowrap;text-align:right">
                    <a class="bt-btn ghost mini" href="bde_targets.php?edit=<?php echo (int) $r['id']; ?>">Edit</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this target?')">
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                      <button class="bt-btn danger mini" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <script>
      (function(){
        var st=document.getElementById('scopeType'), sr=document.getElementById('scopeRef');
        var depts=document.getElementById('optDepts'), usersG=document.getElementById('optUsers');
        function syncScope(){
          var isUser=st.value==='user';
          depts.hidden=isUser; usersG.hidden=!isUser;
          // if current selection is from the hidden group, jump to the first visible option
          var cur=sr.options[sr.selectedIndex];
          if(cur && cur.getAttribute('data-kind')!==st.value){
            var g=isUser?usersG:depts; if(g.options.length) g.options[0].selected=true;
          }
        }
        st.addEventListener('change',syncScope); syncScope();

        // metric → default unit; recurring ↔ month exclusivity
        var msel=document.getElementById('metricSel'), usel=document.getElementById('unitSel');
        msel.addEventListener('change',function(){
          var u=this.options[this.selectedIndex].getAttribute('data-unit'); if(u) usel.value=u;
        });
        var rec=document.getElementById('recurring'), per=document.getElementById('periodInput');
        function syncPeriod(){ per.disabled=rec.checked; if(rec.checked) per.value=''; }
        rec.addEventListener('change',syncPeriod);
        per.addEventListener('input',function(){ if(per.value) rec.checked=false; });
        syncPeriod();
      })();
    </script>
  </div>
</section>

<?php require_once 'footer.php'; ?>
