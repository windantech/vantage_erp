<?php
// bde_dashboard.php  (admin/bde_dashboard.php)
// Private BDE performance dashboard — phase 1 (illustrative / dummy data).
//
// Uses the SAME chrome as the enquiry dashboard: the root header.php (its left
// nav), top_nav.php and footer.php. The dashboard's own design system (ported
// from the v11 prototype, recoloured to a blue theme) is scoped under a single
// `.bde-app` container so it neither leaks into nor is overridden by the admin
// Bootstrap styles. The theme toggle flips a class on that container only.
session_start();
ob_start(); // buffer so we can Post/Redirect/Get after saving a BDO note
require_once 'header.php';   // enquiry/admin left nav + chrome + $conn
require_once 'includes/bde_metrics.php';
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }

// Which BDO are we viewing? Default = logged-in user; admins preview any BDO via ?as=<id>.
$bdo_id = (int) ($_SESSION['login_id'] ?? 0);
$bdo_is_admin = isset($role) && is_array($role) && in_array(777, $role);
if (isset($_GET['as']) && $bdo_is_admin) { $bdo_id = (int) $_GET['as']; }

// --- BDO notes: per-BDE messages (target + commission) the BDO writes, shown on the BDE dashboard ---
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bde_notes (
    bde_user_id INT PRIMARY KEY,
    target_note VARCHAR(600) NOT NULL DEFAULT '',
    commission_note VARCHAR(600) NOT NULL DEFAULT '',
    author_id INT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_note' && $bdo_is_admin) {
    $bid = (int) ($_POST['bde_user_id'] ?? 0);
    if ($bid > 0) {
        $tn = mysqli_real_escape_string($conn, mb_substr(trim((string) ($_POST['target_note'] ?? '')), 0, 600));
        $cn = mysqli_real_escape_string($conn, mb_substr(trim((string) ($_POST['commission_note'] ?? '')), 0, 600));
        $auth = (int) ($_SESSION['login_id'] ?? 0);
        @mysqli_query($conn, "INSERT INTO bde_notes (bde_user_id, target_note, commission_note, author_id)
            VALUES ($bid, '$tn', '$cn', $auth)
            ON DUPLICATE KEY UPDATE target_note='$tn', commission_note='$cn', author_id=$auth");
    }
    header('Location: bdo_dashboard.php?as=' . (int) $bdo_id); exit;
}

// --- Field visits: self-migrating table + log handler (a selling BDO logs their own visits) ---
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bde_visits (
    id INT AUTO_INCREMENT PRIMARY KEY, bde_user_id INT NOT NULL, visit_date DATE NOT NULL,
    client VARCHAR(160) NOT NULL DEFAULT '', organization VARCHAR(200) NOT NULL DEFAULT '',
    location VARCHAR(160) NOT NULL DEFAULT '', product VARCHAR(120) NOT NULL DEFAULT '',
    outcome ENUM('visited','interested','registered','no_show') NOT NULL DEFAULT 'visited',
    value DECIMAL(15,2) NOT NULL DEFAULT 0, contact_phone VARCHAR(50) NOT NULL DEFAULT '',
    followup_date DATE DEFAULT NULL, opportunity_for_dept VARCHAR(120) NOT NULL DEFAULT '',
    opportunity_note VARCHAR(255) NOT NULL DEFAULT '', notes VARCHAR(600) NOT NULL DEFAULT '',
    created_by INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_bde (bde_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'log_visit' && $bdo_id > 0) {
    $client = trim((string) ($_POST['client'] ?? ''));
    if ($client !== '') {
        $vd = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['visit_date'] ?? '')) ? $_POST['visit_date'] : date('Y-m-d');
        $fd = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['followup_date'] ?? '')) ? $_POST['followup_date'] : null;
        $oc = in_array(($_POST['outcome'] ?? 'visited'), ['visited', 'interested', 'registered', 'no_show'], true) ? $_POST['outcome'] : 'visited';
        $E = function ($k) use ($conn) { return mysqli_real_escape_string($conn, trim((string) ($_POST[$k] ?? ''))); };
        $val = (float) ($_POST['value'] ?? 0);
        $fdSql = $fd ? "'" . mysqli_real_escape_string($conn, $fd) . "'" : 'NULL';
        @mysqli_query($conn, "INSERT INTO bde_visits (bde_user_id, visit_date, client, organization, location, product, outcome, value, contact_phone, followup_date, opportunity_note, notes, created_by)
            VALUES ($bdo_id, '" . mysqli_real_escape_string($conn, $vd) . "', '" . $E('client') . "', '" . $E('organization') . "', '" . $E('location') . "', '" . $E('product') . "', '$oc', $val, '" . $E('contact_phone') . "', $fdSql, '" . $E('opportunity_note') . "', '" . $E('notes') . "', " . (int) ($_SESSION['login_id'] ?? 0) . ")");
    }
    header('Location: bdo_dashboard.php?as=' . (int) $bdo_id . '#pipeline'); exit;
}

// Analytics period (default = current calendar month).
$bdo_from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-01');
$bdo_to   = (isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))   ? $_GET['to']   : date('Y-m-d');

// Real department roll-up + department leadership mandate.
$bdo = function_exists('bdo_rollup') ? bdo_rollup($conn, $bdo_id, $bdo_from, $bdo_to) : null;
$bdo_mandate = ($bdo && $bdo['dept'] !== '') ? bde_mandate($bdo['dept']) : bde_mandate('');
$bdo_initials = '';
if ($bdo && $bdo['name'] !== '') { foreach (preg_split('/\s+/', trim($bdo['name'])) as $w) { if ($w !== '') { $bdo_initials .= strtoupper($w[0]); } } }
$bdo_initials = $bdo_initials !== '' ? substr($bdo_initials, 0, 2) : 'BD';

// Admin "View as" roster = everyone carrying a department-total target (the BDOs/HODs).
$bdo_people = [];
if ($bdo_is_admin) {
    $pq = @mysqli_query($conn, "SELECT DISTINCT t.scope_ref id, ru.fullname, t.product dept
        FROM bde_targets t JOIN registered_users ru ON ru.id = t.scope_ref
        WHERE t.scope_type='user' AND t.metric IN ('dept_revenue','dept_participants') AND ru.status=1 AND ru.fullname<>''
        ORDER BY t.product, ru.fullname");
    while ($pq && ($pr = mysqli_fetch_assoc($pq))) { $bdo_people[] = ['id' => (int) $pr['id'], 'name' => (string) $pr['fullname'], 'dept' => (string) $pr['dept']]; }
}
$bdo_current_listed = false;
foreach ($bdo_people as $p) { if ($p['id'] === $bdo_id) { $bdo_current_listed = true; break; } }

// Existing BDO notes for this team (to prefill the modal).
$bdo_notes = [];
if ($bdo && !empty($bdo['team'])) {
    $ids = array_filter(array_map(function ($t) { return (int) ($t['id'] ?? 0); }, $bdo['team']));
    if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        $nq = @mysqli_query($conn, "SELECT bde_user_id, target_note, commission_note FROM bde_notes WHERE bde_user_id IN ($in)");
        while ($nq && ($nr = mysqli_fetch_assoc($nq))) { $bdo_notes[(int) $nr['bde_user_id']] = ['t' => (string) $nr['target_note'], 'c' => (string) $nr['commission_note']]; }
    }
}

// The whole department's logged field visits (every BDE + the BDO), for the Pipeline tab.
$bdo_dept_visits = [];
if ($bdo) {
    $vids = array_filter(array_map(function ($t) { return (int) ($t['id'] ?? 0); }, $bdo['team'] ?? []));
    $vids[] = $bdo_id;
    $vids = array_values(array_unique(array_filter($vids)));
    if (!empty($vids)) {
        $in = implode(',', array_map('intval', $vids));
        $vq = @mysqli_query($conn, "SELECT v.visit_date, v.client, v.organization, v.location, v.product, v.outcome, v.value, v.notes, v.followup_date, v.opportunity_note, COALESCE(ru.fullname,'') bde
            FROM bde_visits v LEFT JOIN registered_users ru ON ru.id = v.bde_user_id
            WHERE v.bde_user_id IN ($in) ORDER BY v.visit_date DESC, v.id DESC LIMIT 100");
        while ($vq && ($vr = mysqli_fetch_assoc($vq))) {
            $bdo_dept_visits[] = ['date' => (string) $vr['visit_date'], 'bde' => (string) $vr['bde'], 'client' => (string) $vr['client'],
                'org' => (string) $vr['organization'], 'location' => (string) $vr['location'], 'product' => (string) $vr['product'],
                'outcome' => (string) $vr['outcome'], 'value' => (float) $vr['value'], 'notes' => (string) $vr['notes'],
                'followup' => (string) ($vr['followup_date'] ?? ''), 'opportunity' => (string) ($vr['opportunity_note'] ?? '')];
        }
    }
}
?>
<section id="content-wrapper" class="d-flex flex-column">
  <div id="content">
    <?php require_once 'top_nav.php'; ?>

    <style>
    /* ===== BDE dashboard — all scoped under .bde-app (blue theme) ===== */
    .bde-app{
      --ground:#e9eef3; --surface:#ffffff; --surface2:#f3f6f9; --surface3:#e7edf2;
      --ink:#151d28; --ink2:#3b4756; --muted:#6a7886; --faint:#9aa8b5; --line:#dce4eb;
      /* status accent = green; brand action accent = orange; theme base = blue */
      --jade:#0e9e79; --jade-deep:#0a7a5e; --jade-soft:#e2f4ee;
      --brand:#ec6e2d; --brand-deep:#c85a1e; --brand-soft:#fdece1;
      --gold:#c98a1c; --gold-soft:#fbf0d8; --gold-line:#eecf94; --amber:#c67e12; --amber-soft:#fbeed6;
      --coral:#d6472f; --coral-soft:#fbe4df; --slate:#4f6f9c; --slate-soft:#e8eef6; --violet:#6f5fbf; --violet-soft:#efeafb;
      --sidebar1:#14232f; --sidebar2:#0c141c;
      --shadow:0 1px 2px rgba(21,29,40,.05),0 14px 30px rgba(21,29,40,.09); --shadow-sm:0 1px 2px rgba(21,29,40,.06),0 4px 12px rgba(21,29,40,.05);
      --radius:16px; --radius-sm:11px;
      background:var(--ground);color:var(--ink);font-size:14px;
      font-family:ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
      line-height:1.45;-webkit-font-smoothing:antialiased;
      max-width:none;margin:0;padding:80px 24px 44px;border-radius:0;min-height:100vh;box-sizing:border-box;
    }
    .bde-app.theme-dark{
      --ground:#0c1219; --surface:#161f2a; --surface2:#1d2833; --surface3:#212e3a; --ink:#eef3f7; --ink2:#c2cdd8; --muted:#8b9aa9; --faint:#63727f; --line:#28343f;
      --jade:#2ec39a; --jade-deep:#41d3ab; --jade-soft:#123027;
      --brand:#f2905a; --brand-deep:#e07640; --brand-soft:#2c1c12;
      --gold:#e2b158; --gold-soft:#2c2413; --gold-line:#4a3d1d; --amber:#e0a343; --amber-soft:#2c2413;
      --coral:#f0715a; --coral-soft:#331a16; --slate:#7d9dcb; --slate-soft:#182533; --violet:#9f90e0; --violet-soft:#20203a; --sidebar1:#111b25; --sidebar2:#0a1017;
      --shadow:0 1px 2px rgba(0,0,0,.32),0 18px 36px rgba(0,0,0,.4); --shadow-sm:0 1px 2px rgba(0,0,0,.3),0 6px 16px rgba(0,0,0,.3);
    }
    .bde-app .num{font-variant-numeric:tabular-nums;font-feature-settings:"tnum" 1}
    .bde-app *{box-sizing:border-box}
    .bde-app button,.bde-app input,.bde-app select,.bde-app textarea{font:inherit;color:inherit}
    .bde-app button{cursor:pointer} .bde-app [hidden]{display:none!important}

    .bde-app .bde-topbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 18px}
    .bde-app .brand{display:flex;align-items:center;gap:12px}
    .bde-app .brand .mark{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(150deg,var(--brand),var(--gold));color:#fff;font-weight:800;font-size:16px;letter-spacing:-.5px;box-shadow:0 8px 18px rgba(236,110,45,.35)}
    .bde-app .brand h1{font-size:16px;margin:0;letter-spacing:-.01em} .bde-app .brand p{font-size:11.5px;color:var(--muted);margin:2px 0 0}
    .bde-app .controls{margin-left:auto;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
    .bde-app .control{display:grid;gap:4px}
    .bde-app .control label{font-size:9.5px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:800}
    .bde-app .control select{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:8px 28px 8px 11px;font-size:13px;font-weight:650;appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--muted) 50%),linear-gradient(135deg,var(--muted) 50%,transparent 50%);background-position:calc(100% - 15px) 16px,calc(100% - 10px) 16px;background-size:5px 5px;background-repeat:no-repeat}
    .bde-app .control select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
    .bde-app .tbtn{border:1px solid var(--line);background:var(--surface2);border-radius:10px;padding:9px 13px;font-size:13px;font-weight:650;display:inline-flex;align-items:center;gap:7px;color:var(--ink)} .bde-app .tbtn:hover{border-color:var(--brand);color:var(--brand)}
    .bde-app .tbtn.solid{background:var(--brand);color:#fff;border-color:var(--brand)} .bde-app .tbtn.solid:hover{background:var(--brand-deep);color:#fff}
    .bde-app .profile-chip{display:flex;align-items:center;gap:10px;padding:5px 13px 5px 5px;border:1px solid var(--line);border-radius:12px;background:var(--surface2)}
    .bde-app .profile-chip .a{width:34px;height:34px;border-radius:9px;background:linear-gradient(150deg,var(--slate),#33507a);color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px}
    .bde-app .profile-chip b{font-size:13px;display:block;line-height:1.15} .bde-app .profile-chip span{font-size:11px;color:var(--muted)}
    .bde-app .tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 2px}
    .bde-app .tab{border:1px solid var(--line);background:var(--surface);border-radius:11px;padding:10px 15px;font-size:13px;font-weight:700;color:var(--muted);box-shadow:var(--shadow-sm);display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:color .15s,border-color .15s}
    .bde-app .tab svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .tab:hover{color:var(--ink);border-color:var(--brand)}
    .bde-app .tab.active{background:linear-gradient(120deg,var(--brand),var(--brand-deep));color:#fff;border-color:var(--brand);box-shadow:0 8px 18px rgba(236,110,45,.3)}
    .bde-app #workspace{display:grid;gap:18px;margin-top:18px}
    .bde-app .section-tag{display:flex;align-items:center;gap:12px;margin:8px 2px 0}
    .bde-app .section-tag h3{margin:0;font-size:16px;letter-spacing:-.01em} .bde-app .section-tag>span{font-size:12.5px;color:var(--muted)} .bde-app .section-tag .rule{flex:1;height:1px;background:linear-gradient(90deg,var(--line),transparent)}

    .bde-app .strategy{border-radius:var(--radius);background:linear-gradient(120deg,var(--sidebar1),#1c3a52);color:#fff;padding:20px 22px;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(240px,.7fr);gap:18px;align-items:center;box-shadow:var(--shadow)}
    .bde-app .strategy .eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:.16em;color:#9fd0ea;font-weight:800}
    .bde-app .strategy h2{font-size:20px;margin:6px 0 6px;letter-spacing:-.01em;line-height:1.25;color:#fff} .bde-app .strategy p{margin:0;font-size:12.5px;color:rgba(255,255,255,.82);line-height:1.5}
    .bde-app .strategy .focus{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:13px;padding:13px 15px} .bde-app .strategy .focus b{display:block;color:#ffd9a8;font-size:11px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px} .bde-app .strategy .focus span{font-size:12.5px;color:rgba(255,255,255,.9);line-height:1.5}

    .bde-app .card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px} .bde-app .card.tight{padding:14px}
    .bde-app .card h4{margin:0;font-size:15px;letter-spacing:-.01em;color:var(--ink)} .bde-app .card .sub{font-size:12px;color:var(--muted);margin:2px 0 0}
    .bde-app .chead{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:15px}
    .bde-app .chip{font-size:11px;font-weight:800;letter-spacing:.04em;padding:5px 11px;border-radius:999px;white-space:nowrap;align-self:center}
    .bde-app .chip.jade{color:var(--jade);background:var(--jade-soft)} .bde-app .chip.gold{color:var(--gold);background:var(--gold-soft)} .bde-app .chip.slate{color:var(--slate);background:var(--slate-soft)} .bde-app .chip.amber{color:var(--amber);background:var(--amber-soft)} .bde-app .chip.coral{color:var(--coral);background:var(--coral-soft)}
    .bde-app .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px} .bde-app .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    .bde-app .hero{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);gap:16px}
    .bde-app .pace-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;font-weight:750;font-size:12px;border:1px solid} .bde-app .pace-pill .dot{width:8px;height:8px;border-radius:50%}
    .bde-app .pg{color:var(--jade);background:var(--jade-soft);border-color:color-mix(in srgb,var(--jade) 30%,transparent)} .bde-app .pg .dot{background:var(--jade)}
    .bde-app .pa{color:var(--amber);background:var(--amber-soft);border-color:color-mix(in srgb,var(--amber) 32%,transparent)} .bde-app .pa .dot{background:var(--amber)}
    .bde-app .pr{color:var(--coral);background:var(--coral-soft);border-color:color-mix(in srgb,var(--coral) 32%,transparent)} .bde-app .pr .dot{background:var(--coral)}

    .bde-app .kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .bde-app .kpi{position:relative;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:15px;overflow:hidden;transition:transform .15s,box-shadow .15s}
    .bde-app .kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm)}
    .bde-app .kpi::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--brand);border-radius:var(--radius-sm) var(--radius-sm) 0 0}
    .bde-app .kpi .kicon{position:absolute;top:14px;right:14px;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:var(--brand-soft);color:var(--brand)} .bde-app .kpi .kicon svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .kpi .lab{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);font-weight:800;padding-right:34px}
    .bde-app .kpi .val{font-size:24px;font-weight:850;letter-spacing:-.02em;margin:10px 0 3px;line-height:1} .bde-app .kpi .meta{font-size:12px;color:var(--muted)}
    .bde-app .kpi .delta{font-size:11px;font-weight:700;margin-top:10px} .bde-app .kpi .delta .dic{font-weight:900;font-size:14px;display:inline-block;vertical-align:-1px;margin-right:1px} .bde-app .delta.up{color:var(--jade)} .bde-app .delta.down{color:var(--coral)} .bde-app .delta.flat{color:var(--brand)}

    .bde-app .prog .pl{font-size:13px;color:var(--muted);margin-top:2px} .bde-app .prog .pl b{color:var(--ink)}
    .bde-app .bar{height:14px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden;margin-top:14px;position:relative} .bde-app .bar .bf{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--coral),var(--amber) 55%,var(--jade));transition:width .6s cubic-bezier(.22,.61,.36,1)} .bde-app .bar .exp{position:absolute;top:-4px;bottom:-4px;width:2px;background:var(--ink2);opacity:.6}
    .bde-app .mini3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:15px}
    .bde-app .cm{background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px} .bde-app .cm span{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800} .bde-app .cm b{display:block;font-size:18px;font-weight:850;margin-top:5px;letter-spacing:-.02em} .bde-app .cm.gold b{color:var(--gold)}
    .bde-app .motiv{margin-top:15px;border-radius:var(--radius-sm);padding:14px;font-size:13px;line-height:1.5} .bde-app .motiv b{font-weight:800} .bde-app .chead + .motiv{margin-top:0}
    .bde-app .motiv.green{background:var(--slate-soft);color:var(--ink2);border:1px solid color-mix(in srgb,var(--slate) 30%,var(--line))} .bde-app .motiv.green b{color:var(--slate)}
    .bde-app .motiv.amber{background:var(--amber-soft);color:var(--ink2);border:1px solid var(--gold-line)} .bde-app .motiv.amber b{color:var(--amber)}
    .bde-app .motiv.red{background:var(--coral-soft);color:var(--ink2);border:1px solid color-mix(in srgb,var(--coral) 30%,var(--line))} .bde-app .motiv.red b{color:var(--coral)}

    .bde-app .chart{width:100%;height:200px;display:block} .bde-app .chart text{fill:var(--muted);font-size:10.5px} .bde-app .chart .grid{stroke:var(--line);stroke-width:1} .bde-app .chart .tline{stroke:var(--brand);stroke-dasharray:5 5;stroke-width:1.5} .bde-app .chart .area{fill:color-mix(in srgb,var(--brand) 14%,transparent)} .bde-app .chart .line{fill:none;stroke:var(--brand);stroke-width:3} .bde-app .chart .dot{fill:var(--surface);stroke:var(--brand);stroke-width:2}

    .bde-app .road-wrap{position:relative;margin:12px 4px 32px} .bde-app .road{height:16px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .road .rf{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--coral),var(--amber) 55%,var(--jade));transition:width .6s cubic-bezier(.22,.61,.36,1)}
    .bde-app .rmark{position:absolute;top:-2px;transform:translateX(-50%);text-align:center} .bde-app .rmark i{display:block;width:2px;height:22px;background:var(--faint);margin:0 auto;border-radius:2px} .bde-app .rmark span{font-size:10px;font-weight:800;color:var(--muted);margin-top:2px;display:block}
    .bde-app .nextstep{margin-top:12px;background:var(--slate-soft);border:1px solid color-mix(in srgb,var(--slate) 30%,var(--line));border-radius:var(--radius-sm);padding:13px;font-size:12.5px;color:var(--ink2);line-height:1.5} .bde-app .nextstep b{color:var(--slate)}

    .bde-app .list{display:grid;gap:9px}
    .bde-app .arow{display:grid;grid-template-columns:auto 1fr auto;gap:11px;align-items:start;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px}
    .bde-app .arow .pd{width:8px;height:8px;border-radius:50%;margin-top:6px;align-self:start} .bde-app .arow b{font-size:12.5px}.bde-app .arow p{margin:2px 0 0;font-size:11.5px;color:var(--muted)}
    .bde-app .arow .due{font-size:10px;font-weight:800;color:var(--muted);white-space:nowrap;background:var(--surface3);padding:4px 8px;border-radius:7px;border:1px solid var(--line);align-self:center}
    .bde-app .stage-chip{display:inline-block;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:8px;background:var(--slate-soft);color:var(--slate)}
    .bde-app .duec{display:inline-block;font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:8px}
    .bde-app .duec.hot{background:var(--coral-soft);color:var(--coral)} .bde-app .duec.soon{background:var(--amber-soft);color:var(--amber)} .bde-app .duec.cool{background:var(--slate-soft);color:var(--slate)}
    .bde-app .arow .abtn{align-self:center;font-size:10.5px;font-weight:800;padding:5px 12px;border-radius:8px;white-space:nowrap;border:0;cursor:pointer;transition:background .15s,color .15s}
    .bde-app .abtn.hot{background:var(--coral-soft);color:var(--coral)} .bde-app .abtn.hot:hover{background:var(--coral);color:#fff}
    .bde-app .abtn.warn{background:var(--amber-soft);color:var(--amber)} .bde-app .abtn.warn:hover{background:var(--amber);color:#fff}
    .bde-app .abtn.info{background:var(--slate-soft);color:var(--slate)} .bde-app .abtn.info:hover{background:var(--slate);color:#fff}
    .bde-app .table-wrap tbody tr:not(.me):hover td{background:color-mix(in srgb,var(--slate) 6%,var(--surface))}
    .bde-app .pd.red{background:var(--coral)}.bde-app .pd.amber{background:var(--amber)}.bde-app .pd.blue{background:var(--slate)}.bde-app .pd.green{background:var(--jade)}

    .bde-app .drivers{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .bde-app .driver{position:relative;overflow:hidden;background:color-mix(in srgb,var(--dacc,var(--brand)) 10%,var(--surface));border:0;border-radius:var(--radius-sm);padding:15px 15px 14px;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(16,40,64,.05);transition:transform .15s,box-shadow .15s}
    .bde-app .driver:hover{transform:translateY(-2px);box-shadow:0 8px 16px -10px rgba(16,40,64,.20)}
    .bde-app .driver .dtop{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
    .bde-app .driver .dicon{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;background:var(--surface);color:var(--dacc,var(--brand));box-shadow:0 1px 2px rgba(16,40,64,.06)}
    .bde-app .driver .dicon svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .driver .n{font-size:23px;font-weight:850;margin:1px 0;letter-spacing:-.02em;color:var(--ink)} .bde-app .driver b{font-size:13px;color:var(--ink)} .bde-app .driver small{color:var(--muted);font-size:11px;margin-top:1px}
    .bde-app .live{font-size:9px;font-weight:800;color:var(--jade);background:var(--jade-soft);padding:2px 6px;border-radius:5px;text-transform:uppercase;letter-spacing:.05em}

    .bde-app .funnel{display:grid;gap:10px} .bde-app .fr{display:grid;grid-template-columns:170px 1fr 60px;gap:11px;align-items:center} .bde-app .fr label{font-size:12px;font-weight:650}
    .bde-app .fbar{height:28px;background:var(--surface3);border:1px solid var(--line);border-radius:8px;overflow:hidden} .bde-app .fbar div{height:100%;background:linear-gradient(90deg,#2f5f9e,#4d8bd6);border-radius:8px;display:flex;align-items:center;padding-left:12px;color:#fff;font-size:11px;font-weight:800;font-variant-numeric:tabular-nums;box-shadow:0 1px 4px -1px rgba(47,95,158,.5);transition:width .5s ease} .bde-app .fr .cv{justify-self:end;background:var(--slate-soft);color:var(--slate);font-size:10.5px;font-weight:800;padding:3px 8px;border-radius:999px;font-variant-numeric:tabular-nums}
    .bde-app .src{display:grid;grid-template-columns:1fr 100px auto;gap:11px;align-items:center;padding:7px 0} .bde-app .src label{font-size:12px;font-weight:600} .bde-app .src .sb{height:10px;border-radius:6px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .src .sb div{height:100%;border-radius:6px;background:linear-gradient(90deg,#2f5f9e,#4d8bd6)} .bde-app .src b{justify-self:end;background:var(--slate-soft);color:var(--slate);font-size:10.5px;font-weight:800;padding:3px 8px;border-radius:999px;font-variant-numeric:tabular-nums}

    .bde-app .table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:var(--radius-sm)}
    .bde-app table{width:100%;border-collapse:collapse;min-width:720px;background:var(--surface)} .bde-app th,.bde-app td{text-align:left;padding:13px 15px;border-bottom:1px solid var(--line);font-size:12.5px;vertical-align:middle}
    .bde-app th{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);background:var(--surface2);font-weight:800} .bde-app tr:last-child td{border-bottom:0}
    .bde-app .prow{display:flex;align-items:center;gap:10px} .bde-app .prow .a{width:30px;height:30px;border-radius:8px;background:var(--slate);color:#fff;display:grid;place-items:center;font-size:10px;font-weight:850} .bde-app .prow b{display:block;font-size:12.5px}.bde-app .prow span{font-size:10.5px;color:var(--muted)}
    .bde-app tr.me{background:linear-gradient(90deg,color-mix(in srgb,#3a7bd5 16%,var(--surface)),color-mix(in srgb,#3a7bd5 5%,var(--surface)))} .bde-app tr.me td{background:transparent} .bde-app tr.me .a{background:linear-gradient(150deg,#3a7bd5,#2a5aa8)}
    .bde-app .mini-track{height:7px;border-radius:99px;background:var(--surface3);overflow:hidden;border:1px solid var(--line);min-width:70px;display:inline-block;vertical-align:middle} .bde-app .mini-track div{height:100%;border-radius:99px}
    .bde-app .sbadge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;padding:4px 9px;border-radius:999px} .bde-app .sbadge .dot{width:7px;height:7px;border-radius:50%}
    .bde-app .sg{color:var(--jade);background:var(--jade-soft)} .bde-app .sg .dot{background:var(--jade)} .bde-app .sa{color:var(--amber);background:var(--amber-soft)} .bde-app .sa .dot{background:var(--amber)} .bde-app .sr{color:var(--coral);background:var(--coral-soft)} .bde-app .sr .dot{background:var(--coral)}

    .bde-app .check{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px} .bde-app .check .sym{width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:14px;font-weight:900} .bde-app .check.ok .sym{background:var(--jade-soft);color:var(--jade)} .bde-app .check.no .sym{background:var(--coral-soft);color:var(--coral)} .bde-app .check b{font-size:12.5px} .bde-app .check small{font-size:10.5px;color:var(--muted);display:block;margin-top:1px} .bde-app .check .cv{font-size:13px;font-weight:850;font-variant-numeric:tabular-nums}
    .bde-app .audit{display:grid;grid-template-columns:auto 1fr;gap:11px;align-items:start;padding:11px 0;border-bottom:1px solid var(--line)} .bde-app .audit:last-child{border-bottom:0} .bde-app .audit .k{width:9px;height:9px;border-radius:50%;background:var(--slate);margin-top:5px} .bde-app .audit b{font-size:12.5px} .bde-app .audit p{margin:2px 0 0;font-size:11.5px;color:var(--muted)}
    .bde-app .steps3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px} .bde-app .stepbox{background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:14px} .bde-app .stepbox span{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800} .bde-app .stepbox b{display:block;font-size:15px;margin:6px 0 4px} .bde-app .stepbox .st{font-size:11.5px;font-weight:700}

    .bde-app .timeline{display:grid;gap:2px} .bde-app .time-row{display:grid;grid-template-columns:120px 1fr;gap:14px;padding:12px 0;border-bottom:1px solid var(--line)} .bde-app .time-row:last-child{border-bottom:0} .bde-app .time-row time{font-size:12px;font-weight:850;color:var(--brand)} .bde-app .time-row div{font-size:12.5px;color:var(--ink2)}
    .bde-app .principles{display:grid;grid-template-columns:repeat(3,1fr);gap:11px} .bde-app .principle{border-left:3px solid var(--brand);background:var(--surface2);border-radius:var(--radius-sm);padding:13px 15px} .bde-app .principle b{font-size:12.5px} .bde-app .principle p{font-size:11.5px;color:var(--muted);margin:4px 0 0;line-height:1.5}
    .bde-app .scorecard{display:grid;gap:11px} .bde-app .scr{display:grid;grid-template-columns:220px 1fr 48px;gap:12px;align-items:center} .bde-app .scr label{font-size:12px;font-weight:600} .bde-app .scr .sb{height:9px;border-radius:99px;background:var(--surface3);border:1px solid var(--line);overflow:hidden} .bde-app .scr .sb div{height:100%;border-radius:99px;background:linear-gradient(90deg,#2f5f9e,#4d8bd6)} .bde-app .scr b{font-size:12.5px;font-weight:800;text-align:right}

    .bde-app .form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px} .bde-app .field{display:grid;gap:5px} .bde-app .field.span2{grid-column:span 2}.bde-app .field.span4{grid-column:span 4}
    .bde-app .field label{font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:800}
    .bde-app .field input,.bde-app .field textarea{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-size:13px;width:100%} .bde-app .field textarea{min-height:82px;resize:vertical;line-height:1.5} .bde-app .field input:focus,.bde-app .field textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)} .bde-app .field input:hover,.bde-app .field textarea:hover{border-color:color-mix(in srgb,var(--brand) 35%,var(--line))} .bde-app .field input[type=number]{font-variant-numeric:tabular-nums;font-weight:650}
    .bde-app .form-sub{display:flex;align-items:center;gap:10px;margin:2px 2px 11px;font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);font-weight:800} .bde-app .form-sub i{color:var(--brand);font-style:normal;font-weight:800;letter-spacing:.03em} .bde-app .form-sub::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,var(--line),transparent)}
    .bde-app .report-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}
    .bde-app .report-preview{white-space:pre-wrap;background:var(--surface2);border:1px dashed var(--line);border-radius:12px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;line-height:1.6;min-height:130px;color:var(--ink2)}

    .bde-app .persband{display:grid;gap:16px;margin:2px 0}
    .bde-app .tgroup{padding-top:9px} .bde-app .tgroup + .tgroup{border-top:1px solid var(--line);margin-top:9px}
    .bde-app .tgroup-h{font-weight:800;font-size:13px;color:var(--ink)} .bde-app .tgroup-sub{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:2px 0 0}
    .bde-app .tlevels{display:flex;gap:10px;margin:8px 0 4px;flex-wrap:wrap}
    .bde-app .tlevel{flex:1;min-width:150px;background:var(--surface);border:1px solid var(--line);border-radius:9px;padding:8px 12px}
    .bde-app .tlevel .tl-cap{display:block;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:3px}
    .bde-app .tlevel b{font-size:16px;font-weight:800;color:var(--ink);letter-spacing:-.01em;line-height:1.1}
    .bde-app .tl-full{border-left:3px solid #0f7a43} .bde-app .tl-qual{border-left:3px solid var(--amber)}
    .bde-app .tl-full b{color:#0f7a43} .bde-app .tl-qual b{color:var(--amber)}
    .bde-app .pdrivers{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    @media(max-width:900px){.bde-app .pdrivers{grid-template-columns:1fr 1fr}}
    @media(max-width:560px){.bde-app .pdrivers{grid-template-columns:1fr}}
    .bde-app .bde-foot{font-size:11.5px;color:var(--muted);margin-top:14px;line-height:1.6} .bde-app .bde-foot code{background:var(--surface2);padding:1px 5px;border-radius:5px;border:1px solid var(--line)}

    @media(max-width:1000px){
      .bde-app .hero,.bde-app .grid-2,.bde-app .grid-3,.bde-app .strategy{grid-template-columns:1fr} .bde-app .kpis{grid-template-columns:1fr 1fr} .bde-app .drivers{grid-template-columns:repeat(2,1fr)} .bde-app .principles{grid-template-columns:1fr} .bde-app .form-grid{grid-template-columns:repeat(2,1fr)} .bde-app .field.span4{grid-column:span 2}
    }
    @media(max-width:560px){.bde-app{padding:12px 14px 40px} .bde-app .kpis,.bde-app .mini3,.bde-app .steps3,.bde-app .form-grid{grid-template-columns:1fr} .bde-app .field.span2,.bde-app .field.span4{grid-column:span 1} .bde-app .fr{grid-template-columns:110px 1fr 42px} .bde-app .scr{grid-template-columns:130px 1fr 40px}}
    @media(prefers-reduced-motion:reduce){.bde-app *{transition:none!important}}
    </style>

    <div class="bde-app" id="bdeApp">
      <header class="bde-topbar">
        <div class="brand"><div class="mark">VA</div><div><h1>Performance Command Centre</h1><p>Strategy → daily execution → verified revenue → commission → growth</p></div></div>
        <div class="controls">
          <?php if ($bdo_is_admin && !empty($bdo_people)): ?>
          <div class="control"><label>View as (admin)</label>
            <select id="viewAs">
              <?php if (!$bdo_current_listed && $bdo_id > 0): ?>
                <option value="<?php echo $bdo_id; ?>" selected>Currently viewing: <?php echo htmlspecialchars($bdo && $bdo['name'] !== '' ? $bdo['name'] : ('#' . $bdo_id)); ?></option>
              <?php endif; ?>
              <?php $curD = ''; foreach ($bdo_people as $p): if ($p['dept'] !== $curD): if ($curD !== '') echo '</optgroup>'; $curD = $p['dept']; ?>
                <optgroup label="<?php echo htmlspecialchars($curD !== '' ? $curD : 'Department'); ?>">
              <?php endif; ?>
                <option value="<?php echo $p['id']; ?>"<?php echo $p['id'] === $bdo_id ? ' selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?> (#<?php echo $p['id']; ?>)</option>
              <?php endforeach; if ($curD !== '') echo '</optgroup>'; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="control"><label>Analytics month</label><select id="periodSelect"></select></div>
          <button class="tbtn" id="themeBtn" type="button">🌙 Dark</button>
          <div class="profile-chip"><span class="a"><?php echo htmlspecialchars($bdo_initials); ?></span><div><b><?php echo htmlspecialchars($bdo && $bdo['name'] !== '' ? $bdo['name'] : 'BDO'); ?></b><span><?php echo htmlspecialchars($bdo ? (($bdo['title'] !== '' ? $bdo['title'] : 'BDO') . ($bdo['dept'] !== '' ? ' · ' . $bdo['dept'] : '')) : 'BDO'); ?></span></div></div>
        </div>
      </header>
      <nav class="tabs" aria-label="Dashboard sections">
        <button class="tab active" data-v="command"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Command Centre</button>
        <button class="tab" data-v="pipeline"><svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>Pipeline &amp; Conversion</button>
        <button class="tab" data-v="commission"><svg viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="6"/><path d="M8.5 13.5l-1.5 7 5-3 5 3-1.5-7"/></svg>Commission</button>
        <button class="tab" data-v="report"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/></svg>Daily Report</button>
        <button class="tab" data-v="strategy"><svg viewBox="0 0 24 24"><path d="M12 20v-6M6 20v-3M18 20v-10"/><circle cx="12" cy="11" r="1.6" fill="currentColor" stroke="none"/><circle cx="6" cy="14" r="1.6" fill="currentColor" stroke="none"/><circle cx="18" cy="7" r="1.6" fill="currentColor" stroke="none"/></svg>Strategy &amp; Scorecard</button>
      </nav>
      <main id="workspace"></main>
      <div class="bde-foot"></div>
    </div>

    <script>
    (() => {
      "use strict";
      const root=document.getElementById("bdeApp");
      const B={
        name:"Francisca Ing'aa", initials:"FI", title:"BDO — Virtual Department", dept:"Virtual", level:"Department command centre",
        target:11504875, actual:7920000, pipeline:21100000, collection:.87, forecast:11850000,
        mandate:"Convert every enquiry into a managed next step and every free session into a payment opportunity.",
        mandateText:"The Virtual Department wins through fast response, relationship building, strong free-session attendance, human calls for hot leads, accurate automation, payment guidance and disciplined CRM follow-up.",
        focus:"Call every hot lead and payment promise first; then protect free-session attendance and same-day CRM updates.",
        drivers:[["New enquiries",1260,"Monthly flow"],["Hot leads",184,"Human follow-up"],["Free-session attendance",318,"Qualified attendees"],["First payments",286,"Finance verified"],["CRM completeness","93%","Complete"]],
        funnel:[["Enquiries",1260],["Qualified",760],["Free-session registered",510],["Attended",318],["Payment commitment",302],["First payment",286]],
        sources:[["Meta ads",38],["WhatsApp",24],["Database",16],["Referrals",13],["Website / AI",9]],
        priorities:[
          ["Corporate L&D partner","Negotiation","KES 2.4M","Executive decision call","Today"],
          ["University staff cohort","Proposal","180 learners","Confirm sponsor list","Tomorrow"],
          ["Alumni employer network","Discovery","KES 900K","Book HR briefing","Friday"],
          ["Reactivated enquiry segment","Campaign","320 prospects","Run recovery sequence","Today"]
        ],
        dailyRhythm:[
          ["8:00–8:30","Review new enquiries, overnight AI conversations, payment commitments and overdue follow-ups."],
          ["8:30–9:00","Set daily first-payment, call, CRM and free-session targets per BDE."],
          ["9:00–10:30","Support calls to hot leads and payment commitments before generic follow-up."],
          ["10:30–1:00","Review qualification, free-session invitations and attendance confirmation."],
          ["2:00–4:45","Coach warm follow-up, objection handling, payment guidance and closing."],
          ["4:45–5:15","Check CRM completeness, review results and set the next-day priority list."]
        ],
        principles:[
          ["Every enquiry has an owner","No enquiry should remain unattended or outside the CRM."],
          ["Human calls for high intent","Hot, institutional and payment-ready leads cannot remain in automation only."],
          ["Payment is the result","Interest and attendance are pipeline indicators; cleared first payments are achieved performance."]
        ],
        team:[
          {name:"Purity Gatwiri",title:"BDE — Leadership Programmes",target:2200000,actual:1620000,pipeline:4600000,collection:.88,notes:"Strengthen SMC and SLDP attendee conversion."},
          {name:"Maryanne Nafula Owour",title:"Sales — Leadership",target:1300000,actual:940000,pipeline:2400000,collection:.84,notes:"Prioritize Friday payment promises."},
          {name:"Lucky Anindo",title:"BDE — Project-Based Courses",target:2200000,actual:1760000,pipeline:3900000,collection:.90,notes:"On pace; add a stretch target."},
          {name:"Dorcas Mukami Murithi",title:"Sales — Project Courses",target:1300000,actual:810000,pipeline:2050000,collection:.81,notes:"Needs a 7-day recovery plan."},
          {name:"Rachael Wambui Mwongela",title:"BDE — Data Analysis",target:2200000,actual:1850000,pipeline:4100000,collection:.91,notes:"Protect collection and follow-up."},
          {name:"Joy Kendi",title:"Sales — Data Analysis",target:2104875,actual:700000,pipeline:2000000,collection:.79,notes:"Immediate call and campaign-quality intervention."}
        ]
      };
<?php if ($bdo): ?>
      /* ---- real department roll-up override (live from the CRM) ---- */
      Object.assign(B, {
        name: <?php echo json_encode($bdo['name'] !== '' ? $bdo['name'] : 'BDO', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDO"'; ?>,
        initials: <?php echo json_encode($bdo_initials, JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BD"'; ?>,
        title: <?php echo json_encode(($bdo['title'] !== '' ? $bdo['title'] : 'BDO') . ($bdo['dept'] !== '' ? ' — ' . $bdo['dept'] : ''), JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDO"'; ?>,
        dept: <?php echo json_encode($bdo['dept'] !== '' ? $bdo['dept'] : 'Department', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"Department"'; ?>,
        metric: <?php echo json_encode($bdo['metric'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '"revenue"'; ?>,
        target: <?php echo (float) $bdo['target']; ?>,
        actual: <?php echo (float) $bdo['actual']; ?>,
        collection: <?php echo (float) $bdo['collection']; ?>,
        pipeline: <?php echo (float) $bdo['pipeline']; ?>,
        clients: <?php echo (int) $bdo['clients']; ?>,
        members: <?php echo (int) $bdo['members']; ?>,
        real: true,
        mandate: <?php echo json_encode($bdo_mandate['mission'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>,
        mandateText: <?php echo json_encode($bdo_mandate['detail'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>,
        focus: <?php echo json_encode($bdo_mandate['focus'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>
      });
      B.team = <?php echo json_encode($bdo['team'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.funnel = <?php echo json_encode(!empty($bdo['funnel']) ? $bdo['funnel'] : [['Leads', 0], ['Paid', 0]], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[["Leads",0],["Paid",0]]'; ?>;
      B.sources = <?php echo json_encode($bdo['sources'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.totalLeads = <?php echo (int) ($bdo['totalLeads'] ?? 0); ?>;
      B.paidClients = <?php echo (int) ($bdo['clients'] ?? 0); ?>;
      B.courses = <?php echo json_encode($bdo['courses'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.ownTarget = <?php echo (float) ($bdo['ownTarget'] ?? 0); ?>;
      B.ownActual = <?php echo (float) ($bdo['ownActual'] ?? 0); ?>;
      B.ownClients = <?php echo (int) ($bdo['ownClients'] ?? 0); ?>;
      B.ownLeads = <?php echo (int) ($bdo['ownLeads'] ?? 0); ?>;
      B.deptAlerts = <?php echo json_encode($bdo['deptAlerts'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.deptQuality = <?php echo json_encode($bdo['deptQuality'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.crossSbu = <?php echo json_encode($bdo['crossSbu'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.deptVisits = <?php echo json_encode($bdo_dept_visits ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.bdoName = <?php echo json_encode($bdo && $bdo['name'] !== '' ? $bdo['name'] : 'BDO', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDO"'; ?>;
      B.notes = <?php echo json_encode((object) $bdo_notes, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
      B.canNote = <?php echo $bdo_is_admin ? 'true' : 'false'; ?>;
      B.forecast = (function(){var dT=<?php echo (int) max(1, min((int) date('j', strtotime($bdo_to)), (int) date('t', strtotime($bdo_to)))); ?>,dim=<?php echo (int) date('t', strtotime($bdo_to)); ?>;return B.actual>0?Math.round(B.actual/dT*dim):B.actual;})();
<?php endif; ?>
      const periods=[{label:<?php echo json_encode(date('F Y', strtotime($bdo_to)), JSON_INVALID_UTF8_SUBSTITUTE) ?: '"This month"'; ?>,working:<?php echo (int) date('t', strtotime($bdo_to)); ?>,elapsed:<?php echo (int) max(1, min((int) date('j', strtotime($bdo_to)), (int) date('t', strtotime($bdo_to)))); ?>}];
      const state={p:0,view:"command"};

      const nf=new Intl.NumberFormat("en-KE",{maximumFractionDigits:0});
      const kMoney=v=>{const a=Math.abs(v||0);if(a>=1e6)return "KES "+(v/1e6).toFixed(2).replace(/\.00$/,"")+"M";if(a>=1e3)return "KES "+Math.round(v/1e3)+"K";return "KES "+nf.format(Math.round(v||0));};
      const pct=(v,d=1)=>(v*100).toFixed(d).replace(/\.0$/,"")+"%";
      const esc=s=>String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
      const el=id=>document.getElementById(id);
      const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
      const period=()=>periods[state.p];

      function pace(){const p=period();const expected=B.target*(p.elapsed/p.working);const ratio=expected?B.actual/expected:0;const status=ratio>=1?"green":ratio>=.85?"amber":"red";return {expected,ratio,status,label:status==="green"?"On pace":status==="amber"?"At risk":"Behind pace"};}
      const scol=s=>s==="green"?"var(--jade)":s==="amber"?"var(--amber)":"var(--coral)";
      function commission(){
        const s=B;const att=s.target?s.actual/s.target:0;
        const team80=s.team.filter(x=>x.actual/x.target>=.8).length;
        const balanced=team80>=Math.ceil(s.team.length*.67);
        const current=(att>=1?60000:att>=.8?30000:0);
        const atTarget=60000;
        const gates=[
          ["Department reaches 80%+",att>=.8,pct(att,0)],
          ["Balanced BDE performance (2/3 at 80%+)",balanced,team80+" of "+s.team.length],
          ["Collections verified (90%+)",s.collection>=.9,pct(s.collection,0)]
        ];
        const unlock=current?"Leadership band currently visible":"Reach 80% department attainment with balanced BDEs and 90% collection.";
        const rule="Departmental leadership incentive: KES 30,000 at 80–99% attainment and KES 60,000 at 100%+, released only when BDE performance is balanced and collections are verified. A 30% hold-back may apply until course and department gates are satisfied.";
        return {current,atTarget,gates,unlock,rule};
      }

      /* ---------- shared blocks ---------- */
      function strategyStrip(){return `<section class="strategy"><div><div class="eyebrow">Department leadership mandate</div><h2>${esc(B.mandate)}</h2><p>${esc(B.mandateText)}</p></div><div class="focus"><b>Today's strategic focus</b><span>${esc(B.focus)}</span></div></section>`;}

      function kpiBlock(){
        const att=B.actual/B.target;const c=commission();const team80=B.team.filter(t=>t.actual/t.target>=.8).length;
        const items=[
          ["Department target",kMoney(B.target),"Approved SBU target","flat","var(--slate)"],
          ["Cleared revenue",kMoney(B.actual),pct(att)+" attainment","up","var(--jade)"],
          ["Month-end forecast",kMoney(B.forecast),pct(B.forecast/B.target)+" projected","flat","var(--slate)"],
          ["Remaining to target",kMoney(Math.max(0,B.target-B.actual)),"target − collected","flat","var(--slate)"],
          ["Team at 80%+",team80+" / "+B.team.length,"Balanced performance","flat","var(--gold)"],
          ["Leadership incentive",kMoney(c.current),c.current>0?"Currently visible":"Not yet unlocked","flat","var(--amber)"]
        ];
        const dt={up:'<span class="dic">↗</span> Positive movement',down:'<span class="dic">↘</span> Below pace',flat:'<span class="dic">•</span> Live from CRM / Finance'};
        const kIcons=[
          '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg>',
          '<svg viewBox="0 0 24 24"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M12 3l9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M20 8H6a2 2 0 0 1 0-4h13v4M3 6v11a2 2 0 0 0 2 2h15V8"/><circle cx="16.5" cy="13.5" r="1.4" fill="currentColor" stroke="none"/></svg>',
          '<svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9M9 3h6"/></svg>'
        ];
        return `<div class="kpis">${items.map(([l,v,m,d,a],i)=>`<div class="kpi" style="--acc:${a}"><span class="kicon">${kIcons[i%kIcons.length]}</span><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div><div class="delta ${d}">${dt[d]}</div></div>`).join("")}</div>`;
      }

      function progressCard(){
        const p=period();const att=B.actual/B.target;const ps=pace();const daysLeft=Math.max(0,p.working-p.elapsed);
        const motiv=ps.status==="green"?"<b>Keep going:</b> You're at or above required pace. Protect collections, quality and stretch opportunities.":ps.status==="amber"?"<b>Close the gap:</b> You're near pace. Focus on the opportunities nearest to payment and remove today's biggest blocker.":"<b>Recover now:</b> The current pace will miss target. Start a quantified recovery plan today — not at month end.";
        return `<div class="card prog">
          <div class="chead"><h4>Progress to target</h4><span class="chip ${ps.status==="green"?"jade":ps.status==="amber"?"amber":"coral"} num">${pct(att)}</span></div>
          <div class="pl">Cleared revenue · <b class="num">${kMoney(B.actual)} / ${kMoney(B.target)}</b></div>
          <div class="bar"><div class="bf" style="width:${clamp(att*100,0,100)}%"></div><div class="exp" style="left:${clamp((p.elapsed/p.working)*100,0,100)}%"></div></div>
          <div class="mini3"><div class="cm"><span>Expected by today</span><b class="num">${kMoney(ps.expected)}</b></div><div class="cm"><span>Remaining gap</span><b class="num">${kMoney(Math.max(0,B.target-B.actual))}</b></div><div class="cm"><span>Days left</span><b class="num">${daysLeft}</b></div></div>
          <div class="motiv ${ps.status}">${motiv}</div>
        </div>`;
      }

      function trendSVG(target,actual,forecast){
        target=(target==null?B.target:target);actual=(actual==null?B.actual:actual);forecast=(forecast==null?B.forecast:forecast);const p=period();const frac=p.elapsed/p.working;const N=9;
        const pts=[];for(let i=0;i<N;i++){const x=i/(N-1);pts.push(x<=frac?actual*(x/Math.max(.01,frac)):actual+(forecast-actual)*((x-frac)/Math.max(.01,1-frac)));}
        const max=Math.max(target,forecast,...pts)*1.1;const w=560,h=200,pd=30;
        const P=pts.map((v,i)=>[pd+i*(w-2*pd)/(N-1),h-pd-v/max*(h-2*pd)]);
        const line=P.map((q,i)=>(i?"L":"M")+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ");
        const area=`M${P[0][0]},${h-pd} `+P.map(q=>"L"+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ")+` L${P[N-1][0]},${h-pd} Z`;
        const ty=h-pd-target/max*(h-2*pd);const tx=pd+frac*(w-2*pd);
        return `<svg class="chart" viewBox="0 0 ${w} ${h}" role="img" aria-label="Revenue pace and forecast">
          ${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*(h-2*pd)).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*(h-2*pd)).toFixed(1)}"/>`).join("")}
          <line class="tline" x1="${pd}" y1="${ty.toFixed(1)}" x2="${w-pd}" y2="${ty.toFixed(1)}"/><text x="${w-pd}" y="${(ty-6).toFixed(1)}" text-anchor="end">Target ${kMoney(target)}</text>
          <line x1="${tx.toFixed(1)}" y1="${pd}" x2="${tx.toFixed(1)}" y2="${h-pd}" stroke="var(--faint)" stroke-dasharray="3 3"/>
          <path class="area" d="${area}"/><path class="line" d="${line}"/>${P.map(q=>`<circle class="dot" cx="${q[0].toFixed(1)}" cy="${q[1].toFixed(1)}" r="3.5"/>`).join("")}
          <text x="${pd}" y="${h-8}">Start</text><text x="${tx.toFixed(1)}" y="${h-8}" text-anchor="middle">Today</text><text x="${w-pd}" y="${h-8}" text-anchor="end">Month end</text></svg>`;
      }

      function commissionMini(){
        const c=commission();const att=B.actual/B.target;const shown=clamp(att,0,1.2)/1.2;
        return `<div class="card">
          <div class="chead"><h4>Commission journey</h4><span class="chip ${c.current>0?"jade":"gold"}">${c.current>0?"Eligible":"Not yet unlocked"}</span></div>
          <div class="road-wrap"><div class="road"><div class="rf" style="width:${shown*100}%"></div></div><div class="rmark" style="left:66.6%"><i></i><span>80%</span></div><div class="rmark" style="left:83.3%"><i></i><span>100%</span></div><div class="rmark" style="left:100%"><i></i><span>120%</span></div></div>
          <div class="mini3"><div class="cm gold"><span>Estimate now</span><b class="num">${kMoney(c.current)}</b></div><div class="cm"><span>At target</span><b class="num">${kMoney(c.atTarget)}</b></div><div class="cm"><span>Extra available</span><b class="num">${kMoney(Math.max(0,c.atTarget-c.current))}</b></div></div>
          <div class="nextstep"><b>Next step:</b> ${esc(c.unlock)}</div>
        </div>`;
      }

      function actionsCard(){
        const list=[
          ["red","Review all red portfolios","Assign a named recovery action, owner and review time for every red BDE.","8:30 AM"],
          ["amber","Control the top opportunities","Review the top five opportunities per BDE and personally support high-value or stalled accounts.","Before noon"],
          ["blue","Check lead flow and CRM quality","Compare lead volume, quality, next actions and collection commitments against required pace.","3:30 PM"],
          ["green","Coach and recognize","Document one coaching action for weak performance and one recognition for strong performance.","Today"]
        ];
        return `<div class="card"><div class="chead"><h4>Today's action centre</h4><span class="chip coral">Action required</span></div><div class="list">${list.map(([c,b,p,d])=>`<div class="arow"><span class="pd ${c}"></span><div><b>${esc(b)}</b><p>${esc(p)}</p></div><span class="due">${esc(d)}</span></div>`).join("")}</div></div>`;
      }
      function driversCard(){
        const dAcc=["#3a7bd5","var(--brand)","var(--jade)","#2f8f88","var(--gold)"];
        const dIcons=[
          '<svg viewBox="0 0 24 24"><path d="M3 21h18M6 21V7l6-4 6 4v14"/><path d="M10 10h4M10 14h4"/></svg>',
          '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4M10.5 8.2l3.5 2-3.5 2z"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 14l2 2 4-4"/></svg>',
          '<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.4"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 5.4a3.4 3.4 0 0 1 0 5.2M20.5 20a5.5 5.5 0 0 0-3.6-5.2"/></svg>',
          '<svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-.6 3M20 5v6h-6"/></svg>'
        ];
        // Real department drivers from the roll-up.
        const att=B.target?B.actual/B.target:0;
        const team=(B.team||[]);const team80=team.filter(t=>t.target>0&&t.actual/t.target>=.8).length;
        const drivers=[
          ["Cleared revenue",kMoney(B.actual),pct(att)+" of target"],
          ["Remaining to target",kMoney(Math.max(0,B.target-B.actual)),"target − collected"],
          ["Paid clients",B.paidClients||0,"Finance-verified"],
          ["Leads (department)",B.totalLeads||0,"all channels"],
          ["Team at 80%+",team80+" / "+team.length,"balanced performance"],
          ["Collection",pct(B.collection||0,0),"fees settled vs expected"]
        ];
        return `<div class="card"><div class="chead"><h4>Execution drivers</h4><span class="chip slate">${esc(B.dept)}</span></div><div class="drivers">${drivers.map(([l,n,s],i)=>`<div class="driver" style="--dacc:${dAcc[i%dAcc.length]}"><div class="dtop"><span class="dicon">${dIcons[i%dIcons.length]}</span><span class="live">Live</span></div><div class="n num">${typeof n==="number"?nf.format(n):esc(n)}</div><b>${esc(l)}</b><small>${esc(s)}</small></div>`).join("")}</div></div>`;
      }
      function teamTable(){
        const avatarCols=["var(--slate)","var(--violet)","var(--coral)","#2f8f88","var(--gold)"];
        if(!B.team||!B.team.length){
          return `<div class="card"><div class="chead"><h4>Team members</h4><span class="chip slate">${esc(B.dept||"Department")}</span></div><p style="color:var(--muted);font-size:12.5px;margin:0;line-height:1.6">No BDEs resolved for this department. Choose a BDO from <b>View&nbsp;as</b> above (e.g. <b>Edwin Otieno</b> for Corporate) — the team rolls up from that department's BDEs. If you <em>are</em> on a BDO and it's still empty, that department's BDEs aren't yet linked to it in <code>bde_targets</code>.</p></div>`;
        }
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Target</th><th>Cleared</th><th>Achievement</th><th>Open pipeline</th><th>Status</th>${B.canNote?'<th style="width:96px">Note</th>':""}</tr></thead><tbody>${B.team.map((t,i)=>{const a=t.actual/t.target;const p=period();const exp=t.target*(p.elapsed/p.working);const st=t.actual>=exp?"green":t.actual>=exp*.85?"amber":"red";const lbl=st==="green"?"On pace":st==="amber"?"At risk":"Behind pace";const ini=t.name.split(/\s+/).map(x=>x[0]).slice(0,2).join("");return `<tr class="${t.me?"me":""}"><td><div class="prow"><span class="a"${t.me?"":` style="background:${avatarCols[i%avatarCols.length]}"`}>${ini}</span><div><b>${esc(t.name)}${t.me?" · you":""}</b><span>${esc(t.title)}</span></div></div></td><td class="num">${kMoney(t.target)}</td><td class="num">${kMoney(t.actual)}</td><td><span class="mini-track"><div style="width:${clamp(a*100,0,100)}%;background:${scol(st)}"></div></span> <b class="num" style="font-size:11.5px">${pct(a,0)}</b></td><td class="num">${t.pipeline>0?`${kMoney(t.pipeline)}<div style="font-size:9.5px;color:var(--muted);font-weight:600;margin-top:2px">expected · uncollected</div>`:`<span style="color:var(--faint)">—</span>`}</td><td><span class="sbadge s${st[0]}"><span class="dot"></span>${lbl}</span></td>${B.canNote?`<td>${t.id?`<button type="button" class="notebtn tbtn" data-bde="${t.id}" data-name="${esc(t.name)}" style="padding:5px 11px;font-size:11.5px;white-space:nowrap">✎ ${(B.notes&&B.notes[t.id]&&((((B.notes[t.id].t||"").trim())||((B.notes[t.id].c||"").trim())))?"Edit":"Add note")}</button>`:""}</td>`:""}</tr>`;}).join("")}</tbody></table></div></div>`;
      }

      function bdoTargetsCard(){
        const lvl=(cap,val,qual)=>`<div class="tlevel ${qual?"tl-qual":"tl-full"}"><span class="tl-cap">${cap}</span><b>${kMoney(val)}</b></div>`;
        const grp=(title,sub,levels)=>`<div class="tgroup"><div class="tgroup-h">${esc(title)}</div><div class="tgroup-sub">${esc(sub)}</div><div class="tlevels">${levels}</div></div>`;
        const floor=(+B.ownTarget)||0;const parts=[];
        if(floor>0) parts.push(grp("Corporate","Monthly revenue · your floor",lvl("100% target",floor,false)));
        (B.courses||[]).forEach(c=>parts.push(grp(c.name,"Course revenue",lvl("100% target",c.target,false)+(c.threshold>0?lvl("80% qualifying",c.threshold,true):""))));
        return `<div class="card"><div class="chead"><h4>Your targets</h4><span class="chip slate">Monthly</span></div>${parts.join("")}</div>`;
      }
      function personalDrivers(){
        const pt=(+B.ownTarget)||0;const pa=(+B.ownActual)||0;const att=pt?pa/pt:0;
        const dAcc=["var(--brand)","var(--jade)","#3a7bd5","var(--gold)"];
        const items=[["Cleared revenue",kMoney(pa),pct(att,0)+" of your target"],["Remaining to target",kMoney(Math.max(0,pt-pa)),"target − collected"],["Paid clients",nf.format(B.ownClients||0),"Finance-verified"],["Your leads",nf.format(B.ownLeads||0),"attributed to you"]];
        return `<div class="card"><div class="chead"><h4>Your execution drivers</h4><span class="chip slate">Personal</span></div><div class="pdrivers">${items.map(([l,n,s],i)=>`<div class="driver" style="--dacc:${dAcc[i%dAcc.length]}"><div class="dtop"><span class="live">You</span></div><div class="n num">${esc(n)}</div><b>${esc(l)}</b><small>${esc(s)}</small></div>`).join("")}</div></div>`;
      }
      function bdoPersonalSection(){
        const floor=(+B.ownTarget)||0;
        if(floor<=0 && (!B.courses||!B.courses.length)) return "";
        const pt=floor||(B.courses||[]).reduce((s,c)=>s+(+c.target||0),0);
        const pa=(+B.ownActual)||0;const att=pt?pa/pt:0;
        const st=att>=1?"jade":att>=.8?"amber":"coral";
        const p=period();const pf=pa>0?Math.round(pa/Math.max(.01,p.elapsed/p.working)):pa;const shown=clamp(att,0,1.2)/1.2;
        return `<div class="persband">
          <div class="section-tag"><h3>Your personal performance</h3><span>Your own target, tracked separately from the department</span><div class="rule"></div></div>
          <section class="hero">
            ${bdoTargetsCard()}
            <div class="card prog"><div class="chead"><h4>Progress to your target</h4><span class="chip ${st} num">${pct(att)}</span></div>
              <div class="pl">Your cleared · <b class="num">${kMoney(pa)} / ${kMoney(pt)}</b></div>
              <div class="bar"><div class="bf" style="width:${clamp(att*100,0,100)}%"></div></div>
              <div class="mini3"><div class="cm"><span>Cleared</span><b class="num">${kMoney(pa)}</b></div><div class="cm"><span>Remaining</span><b class="num">${kMoney(Math.max(0,pt-pa))}</b></div><div class="cm"><span>Paid clients</span><b class="num">${nf.format(B.ownClients||0)}</b></div></div>
            </div>
          </section>
          ${personalDrivers()}
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Your revenue pace &amp; forecast</h4><span class="chip jade">${kMoney(pf)} forecast</span></div>${trendSVG(pt,pa,pf)}<div style="font-size:11.5px;color:var(--muted);margin-top:10px">Your own cleared revenue vs your monthly target.</div></div>
            <div class="card"><div class="chead"><h4>Your commission</h4><span class="chip ${att>=.8?"jade":"gold"}">${att>=.8?"On track":"Not yet unlocked"}</span></div>
              <div class="road-wrap"><div class="road"><div class="rf" style="width:${shown*100}%"></div></div><div class="rmark" style="left:66.6%"><i></i><span>80%</span></div><div class="rmark" style="left:83.3%"><i></i><span>100%</span></div><div class="rmark" style="left:100%"><i></i><span>120%</span></div></div>
              <div class="mini3"><div class="cm"><span>Your cleared</span><b class="num">${kMoney(pa)}</b></div><div class="cm"><span>Your target</span><b class="num">${kMoney(pt)}</b></div><div class="cm"><span>Attainment</span><b class="num">${pct(att,0)}</b></div></div>
              <div class="nextstep"><b>Next step:</b> ${att>=1?"At or above your target — protect collections to keep your commission.":att>=.8?"Above 80% — clear outstanding fees to confirm eligibility.":"Reach 80% of your "+kMoney(pt)+" target, then clear fees to unlock."}</div>
            </div>
          </section>
        </div>`;
      }
      /* ---------- views ---------- */
      function vCommand(){
        const ps=pace();
        return `${strategyStrip()}
          <div class="section-tag"><h3>Department performance</h3><span>The whole ${esc(B.dept||"department")} — target, cleared revenue and pace across every BDE</span><div class="rule"></div></div>
          <section class="hero">
            <div class="card"><div class="chead"><h4>Department portfolio</h4><span class="pace-pill ${ps.status==="green"?"pg":ps.status==="amber"?"pa":"pr"}"><span class="dot"></span>${ps.label} · pace ${pct(ps.ratio,0)}</span></div>${kpiBlock()}</div>
            ${progressCard()}
          </section>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Revenue pace &amp; month-end forecast</h4><span class="chip jade">${kMoney(B.forecast)} forecast</span></div>${trendSVG()}<div style="font-size:11.5px;color:var(--muted);margin-top:10px">The forecast moves whenever stage, probability, payment date or cleared revenue changes.</div></div>
            ${commissionMini()}
          </section>
          <section class="grid-2">${actionsCard()}${driversCard()}</section>
          <div class="section-tag"><h3>BDE performance &amp; coaching</h3><span>Cleared = Finance-verified collected revenue · Open pipeline = expected from registrations, not yet collected</span><div class="rule"></div></div>
          ${teamTable()}
          ${bdoPersonalSection()}`;
      }

      function bdoVisitForm(){
        return `<div class="section-tag"><h3>Field visits &amp; opportunities</h3><span>Log a visit you're making — and see the whole department's visits below</span><div class="rule"></div></div>
          <div class="card"><div class="chead"><h4>Log a field visit</h4><span class="chip slate">${esc(B.bdoName||"You")}</span></div>
            <form method="post" action="bdo_dashboard.php?as=<?php echo (int) $bdo_id; ?>#pipeline">
              <input type="hidden" name="action" value="log_visit">
              <div class="form-grid">
                <div class="field"><label>Visit date</label><input type="date" name="visit_date" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="field span2"><label>Client / contact</label><input name="client" required placeholder="Name"></div>
                <div class="field"><label>Location</label><input name="location" placeholder="Town / area"></div>
                <div class="field span2"><label>Organization</label><input name="organization" placeholder="Company / institution"></div>
                <div class="field"><label>Product</label><input name="product" placeholder="e.g. MEAL / CPA"></div>
                <div class="field"><label>Outcome</label><select name="outcome"><option value="visited">Visited</option><option value="interested">Interested</option><option value="registered">Registered</option><option value="no_show">No-show</option></select></div>
                <div class="field"><label>Value (KES)</label><input name="value" type="number" inputmode="numeric" placeholder="0"></div>
                <div class="field"><label>Follow-up date</label><input type="date" name="followup_date"></div>
                <div class="field span4"><label>Opportunity <span style="text-transform:none;color:var(--muted)">(visible to every department for awareness)</span></label><input name="opportunity_note" placeholder="e.g. HR here wants staff appraisals for 40 staff"></div>
                <div class="field span4"><label>Notes</label><textarea name="notes" rows="2" placeholder="What happened, next step…"></textarea></div>
              </div>
              <div class="report-actions"><button class="tbtn solid" type="submit">Log visit</button></div>
            </form>
          </div>`;
      }
      function bdoVisitsTable(){
        if(!B.deptVisits||!B.deptVisits.length) return `<div class="card"><p style="color:var(--muted);font-size:12.5px;margin:0;line-height:1.6">No field visits logged in the department yet. Log one above, and your BDEs' visits will appear here too.</p></div>`;
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>Date</th><th>BDE</th><th>Client / org</th><th>Outcome</th><th>Value</th><th>Opportunity / next step</th><th>Follow-up</th></tr></thead><tbody>${B.deptVisits.map(v=>`<tr><td>${esc(v.date)}</td><td>${esc(v.bde||"—")}</td><td><b>${esc(v.client)}</b>${v.org?`<div style="font-size:10.5px;color:var(--muted)">${esc(v.org)}</div>`:""}</td><td><span class="stage-chip">${esc(v.outcome)}</span></td><td class="num">${v.value>0?kMoney(v.value):"—"}</td><td>${esc(v.opportunity||v.notes||"—")}</td><td>${esc(v.followup||"—")}</td></tr>`).join("")}</tbody></table></div></div>`;
      }
      function vPipeline(){
        const fmax=Math.max(1,...B.funnel.map(f=>f[1]));const smax=Math.max(1,...B.sources.map(s=>s[1]));
        return `
          <div class="section-tag"><h3>Department acquisition &amp; conversion</h3><span>Rolled up from every BDE in ${esc(B.dept||"the department")}</span><div class="rule"></div></div>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Acquisition &amp; conversion funnel</h4><span class="chip slate">Live funnel</span></div><div class="funnel">${B.funnel.map(([l,n],i)=>`<div class="fr"><label>${esc(l)}</label><div class="fbar"><div style="width:${Math.max(9,n/fmax*100)}%">${nf.format(n)}</div></div><span class="cv">${i?(B.funnel[i-1][1]>0?Math.round(n/B.funnel[i-1][1]*100)+"%":"—"):"100%"}</span></div>`).join("")}</div></div>
            <div class="card"><div class="chead"><h4>Lead-source contribution</h4><span class="chip slate">Live</span></div>${B.sources.length?B.sources.map(([n,v])=>`<div class="src"><label>${esc(n)}</label><div class="sb"><div style="width:${v/smax*100}%"></div></div><b>${nf.format(v)}</b></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:6px 2px">No leads attributed to this department yet.</p>'}</div>
          </section>
          <section class="grid-3">
            <div class="card"><div class="chead"><h4>Action alerts</h4><span class="chip ${(B.deptAlerts||[]).length?"coral":"jade"}">${(B.deptAlerts||[]).length?(B.deptAlerts.length+" flagged"):"all clear"}</span></div><div class="list">${(B.deptAlerts||[]).length?B.deptAlerts.map(a=>`<div class="arow"><span class="pd red"></span><div><b>${esc(a.name)}</b><p>${esc(a.text)}</p></div>${a.id?`<a class="abtn hot" href="bde_dashboard.php?as=${a.id}" target="_blank" rel="noopener" style="text-decoration:none;white-space:nowrap">Open →</a>`:""}</div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:6px 2px">Nothing needs action right now across the department.</p>'}</div></div>
            <div class="card"><div class="chead"><h4>Conversion-quality signals</h4><span class="chip ${(B.deptQuality||[]).length?"amber":"jade"}">${(B.deptQuality||[]).length?(B.deptQuality.length+" to review"):"healthy"}</span></div><div class="list">${(B.deptQuality||[]).length?B.deptQuality.map(x=>`<div class="arow"><span class="pd amber"></span><div><b>${esc(x.name)}</b><p>${esc(x.text)}</p></div></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:6px 2px">Conversion and collection across the department look healthy (or too little activity to flag).</p>'}</div></div>
            <div class="card"><div class="chead"><h4>Cross-SBU opportunities</h4><span class="chip slate">${(B.crossSbu||[]).length} shared</span></div><div class="list">${(B.crossSbu||[]).length?B.crossSbu.map(x=>`<div class="arow"><span class="pd blue"></span><div><b>${esc(x)}</b><p>Flagged on a field visit — for everyone's awareness.</p></div></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:6px 2px">No cross-SBU opportunities yet. When anyone flags one on a field visit, it shows here.</p>'}</div></div>
          </section>
          ${bdoVisitForm()}
          ${bdoVisitsTable()}`;
      }

      function vCommission(){
        const c=commission();const att=B.actual/B.target;const shown=clamp(att,0,1.2)/1.2;const met=c.gates.filter(g=>g[1]).length;
        const audit=[["Rule version","COMM-2026-09-v1","Effective-dated and locked after month close"],["Revenue source","Finance-cleared payments","Invoices and promises excluded"],["Ownership","CRM acquisition owner","Joint splits require prior written approval"],["Hold-back","Balance / support gate","Displayed separately from payable amount"],["Reversals","Refunds and credit notes","Recalculate and preserve audit history"]];
        return `
          <section class="hero">
            <div class="card">
              <div class="chead"><h4>Your transparent commission journey</h4><span class="chip ${c.current>0?"jade":"gold"}">${c.current>0?"Current estimate":"Locked"}</span></div>
              <div class="road-wrap"><div class="road"><div class="rf" style="width:${shown*100}%"></div></div><div class="rmark" style="left:66.6%"><i></i><span>80%</span></div><div class="rmark" style="left:83.3%"><i></i><span>100%</span></div><div class="rmark" style="left:100%"><i></i><span>120%</span></div></div>
              <div class="mini3"><div class="cm gold"><span>Estimated now</span><b class="num">${kMoney(c.current)}</b></div><div class="cm"><span>At target</span><b class="num">${kMoney(c.atTarget)}</b></div><div class="cm"><span>Additional earning</span><b class="num">${kMoney(Math.max(0,c.atTarget-c.current))}</b></div></div>
              <div class="nextstep"><b>How it's earned:</b> ${esc(c.rule)}</div>
            </div>
            <div class="card"><div class="chead"><h4>Next earning milestone</h4></div>
              <div style="font-size:30px;font-weight:850;letter-spacing:-.03em;margin:6px 0 4px" class="num">${kMoney(Math.max(0,B.target-B.actual))}</div>
              <div style="color:var(--muted);font-size:12.5px">remaining to the full revenue target</div>
              <div class="nextstep"><b>Recommended push:</b> ${esc(c.unlock)} Concentrate on verified opportunities nearest to payment rather than adding unqualified activity.</div>
            </div>
          </section>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Eligibility checklist</h4><span class="chip ${met===c.gates.length?"jade":"gold"}">${met} / ${c.gates.length} met</span></div><div class="list">${c.gates.map(([n,ok,v])=>`<div class="check ${ok?"ok":"no"}"><span class="sym">${ok?"✓":"✕"}</span><div><b>${esc(n)}</b><small>${ok?"Condition satisfied":"Not yet satisfied"}</small></div><span class="cv">${esc(v)}</span></div>`).join("")}</div></div>
            <div class="card"><div class="chead"><h4>Commission audit trail</h4><span class="chip slate">Traceable</span></div>${audit.map(r=>`<div class="audit"><span class="k"></span><div><b>${esc(r[0])}: ${esc(r[1])}</b><p>${esc(r[2])}</p></div></div>`).join("")}</div>
          </section>
          <div class="card"><div class="chead"><h4>Three-month consistency journey</h4><span class="chip slate">Month 2 of 3</span></div>
            <div class="steps3"><div class="stepbox"><span>Month 1</span><b>Target achieved</b><div class="st" style="color:var(--jade)">✓ Verified</div></div><div class="stepbox"><span>Month 2</span><b>${att>=1?"On track":"Recovery required"}</b><div class="st" style="color:${att>=1?"var(--jade)":"var(--amber)"}">${pct(att)} current attainment</div></div><div class="stepbox"><span>Month 3</span><b>Future period</b><div class="st" style="color:var(--slate)">Consistency reward pending</div></div></div>
          </div>`;
      }

      function vReport(){
        const p=period();
        const teamGreen=B.team.filter(t=>t.actual>=t.target*(p.elapsed/p.working)).length;
        const teamRed=B.team.filter(t=>t.actual<t.target*(p.elapsed/p.working)*.85).length;
        const fields=[
          ["Department daily revenue target","number",Math.round(B.target/p.working)],
          ["Actual cleared revenue today","number",Math.round(B.actual/p.elapsed)],
          ["BDEs on / above pace","number",teamGreen],
          ["Red portfolios","number",teamRed],
          ["Qualified pipeline value","number",B.pipeline],
          ["Meetings / demos / sessions today","number",6],
          ["Proposals / commitments moved","number",4],
          ["Collection commitments due","number",9],
          ["BDE performance and coaching actions","textarea",B.team.map(x=>`${x.name}: ${x.notes}`).join("\n")],
          ["Course / product recovery action","textarea","State target, actual, exact gap, owner, deadline and evidence required."],
          ["Marketing, CRM, AI and product issues","textarea","Lead-flow variance, data quality, automation failure and corrective action."],
          ["Executive support and tomorrow's priorities","textarea","Top opportunities, decisions required and the next-day departmental result."]
        ];
        const fieldHTML=f=>`<div class="field ${f[1]==="textarea"?"span2":""}"><label>${esc(f[0])}</label>${f[1]==="textarea"?`<textarea data-label="${esc(f[0])}">${esc(f[2])}</textarea>`:`<input data-label="${esc(f[0])}" type="number" value="${esc(f[2])}">`}</div>`;
        const nums=fields.filter(f=>f[1]==="number").map(fieldHTML).join("");
        const texts=fields.filter(f=>f[1]==="textarea").map(fieldHTML).join("");
        return `
          <div class="card"><div class="chead"><h4>BDO / HOD daily command report</h4><span class="chip jade">Auto-prefilled</span></div>
            <div id="reportForm">
              <div class="form-sub">Today's numbers <i>· auto-prefilled</i></div>
              <div class="form-grid">${nums}</div>
              <div class="form-sub" style="margin-top:18px">Your narrative <i>· the human judgement</i></div>
              <div class="form-grid">${texts}</div>
            </div>
            <div class="report-actions"><button class="tbtn solid" id="genReport" type="button">Generate report summary</button><button class="tbtn" id="dlReport" type="button">Download</button><button class="tbtn" id="clrReport" type="button">Clear narrative</button></div>
          </div>
          <div class="card"><div class="chead"><h4>Generated management summary</h4><span class="chip jade">Evidence-linked</span></div><div id="reportPreview" class="report-preview">Select "Generate report summary" to compile the dashboard data and your explanations.</div></div>
          <section class="grid-3">
            ${[["Automatic evidence","Revenue, payments, activity logs, opportunities, meetings, proposals and CRM completeness are system-calculated."],["Required human judgement","You explain why performance moved, what is blocked, what was learned and which support or decision is required."],["Manager workflow","Your supervisor reviews, comments, approves or returns the report and converts commitments into tracked actions."]].map(([a,b])=>`<div class="card"><h4>${esc(a)}</h4><p style="font-size:12.5px;color:var(--muted);margin:8px 0 0;line-height:1.5">${esc(b)}</p></div>`).join("")}
          </section>`;
      }

      function vStrategy(){
        const clamp2=x=>Math.max(0,Math.min(1,x||0));
        const att2=B.target?B.actual/B.target:0;
        const team=(B.team||[]);const team80=team.filter(t=>t.target>0&&t.actual/t.target>=.8).length;
        const score=[
          ["Departmental revenue vs target",`${kMoney(B.actual)} of ${kMoney(B.target)}`,clamp2(att2)],
          ["Balanced team performance",`${team80} of ${team.length} BDEs at 80%+`,clamp2(team.length?team80/team.length:0)],
          ["Collections quality",`${pct(B.collection||0,0)} of billed collected`,clamp2(B.collection||0)]
        ];
        return `
          <div class="card"><div class="chead"><h4>Role mandate</h4><span class="chip jade">Department leadership</span></div><div class="motiv green"><b>${esc(B.mandate)}</b><br>${esc(B.mandateText)}</div></div>
          <div class="card"><div class="chead"><h4>Non-negotiable operating principles</h4></div><div class="principles">${B.principles.map(([a,b])=>`<div class="principle"><b>${esc(a)}</b><p>${esc(b)}</p></div>`).join("")}</div></div>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Daily operating rhythm</h4></div><div class="timeline">${B.dailyRhythm.map(([t,x])=>`<div class="time-row"><time>${esc(t)}</time><div>${esc(x)}</div></div>`).join("")}</div></div>
            <div class="card"><div class="chead"><h4>Performance scorecard</h4><span class="chip jade">Live · your actuals</span></div><div class="scorecard">${score.map(([n,val,frac])=>`<div class="scr"><label>${esc(n)}<br><span style="font-size:10.5px;color:var(--muted);font-weight:600">${esc(val)}</span></label><div class="sb"><div style="width:${Math.round(frac*100)}%;background:linear-gradient(90deg,var(--brand),var(--brand-deep))"></div></div><b>${pct(frac,0)}</b></div>`).join("")}</div></div>
          </section>
          <section class="grid-3">
            <div class="card"><div class="chead"><h4>Green response</h4><span class="chip jade">At / above pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Protect quality, collections and client experience; pursue stretch opportunities and share winning practices.</p></div>
            <div class="card"><div class="chead"><h4>Amber response</h4><span class="chip amber">Near pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Agree corrective action within 24 hours, intensify senior support and concentrate on the nearest commercial next steps.</p></div>
            <div class="card"><div class="chead"><h4>Red response</h4><span class="chip coral">Below pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Create a quantified recovery plan, monitor daily and escalate decisions or resources before the gap becomes irreversible.</p></div>
          </section>`;
      }

      function openNoteModal(bid,name){
        var n=(B.notes&&B.notes[bid])||{t:"",c:""};
        var hasNote=((n.t||"").trim()||(n.c||"").trim());
        var ov=document.createElement("div");
        ov.style.cssText="position:fixed;inset:0;background:rgba(16,24,40,.5);display:flex;align-items:center;justify-content:center;z-index:9999;padding:20px";
        var ta="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px 12px;font:inherit;background:var(--surface2);color:var(--ink);resize:vertical";
        var lb="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px";
        ov.innerHTML='<form method="post" action="bdo_dashboard.php?as=<?php echo (int) $bdo_id; ?>" style="background:var(--surface);color:var(--ink);border-radius:16px;max-width:520px;width:100%;box-shadow:0 24px 60px rgba(16,24,40,.35);overflow:hidden">'
          +'<style>.nmta:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}.nmta:hover{border-color:color-mix(in srgb,var(--brand) 35%,var(--line))}</style>'
          +'<input type="hidden" name="action" value="save_note"><input type="hidden" name="bde_user_id" value="'+bid+'">'
          +'<div style="padding:18px 22px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center"><b style="font-size:15px">Message to '+esc(name)+'</b><span class="nclose" style="cursor:pointer;font-size:18px;color:var(--muted)">✕</span></div>'
          +'<div style="padding:18px 22px;display:grid;gap:14px">'
          +'<div><label style="'+lb+'">Message on their target</label><textarea name="target_note" rows="3" maxlength="600" class="nmta" style="'+ta+'" placeholder="e.g. Strong start — push the two hot accounts to close this week.">'+esc(n.t)+'</textarea></div>'
          +'<div><label style="'+lb+'">Message on their commission</label><textarea name="commission_note" rows="3" maxlength="600" class="nmta" style="'+ta+'" placeholder="e.g. You are 30 paid staff from the 80% threshold — clear those fees to unlock.">'+esc(n.c)+'</textarea></div>'
          +'</div>'
          +'<div style="padding:14px 22px;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:10px">'
          +(hasNote?'<button type="button" class="tbtn ndel" style="color:var(--coral);border-color:var(--coral)">🗑 Delete note</button>':'<span></span>')
          +'<div style="display:flex;gap:10px"><button type="button" class="tbtn nclose">Cancel</button><button type="submit" class="tbtn solid">Save message</button></div></div>'
          +'</form>';
        ov.addEventListener("click",function(e){
          if(e.target===ov||e.target.classList.contains("nclose")){ ov.remove(); return; }
          if(e.target.classList.contains("ndel")){ var f=ov.querySelector("form"); f.target_note.value=""; f.commission_note.value=""; f.submit(); }
        });
        document.addEventListener("keydown",function esc2(e){ if(e.key==="Escape"){ ov.remove(); document.removeEventListener("keydown",esc2); } });
        root.appendChild(ov); // inside .bde-app so the CSS vars (--surface, --ink…) resolve and it's opaque + themed
      }
      function render(){
        const v=state.view;
        el("workspace").innerHTML=v==="command"?vCommand():v==="pipeline"?vPipeline():v==="commission"?vCommission():v==="report"?vReport():vStrategy();
        if(v==="report")bindReport();
        root.querySelectorAll(".notebtn").forEach(b=>b.addEventListener("click",()=>openNoteModal(b.dataset.bde,b.dataset.name)));
      }
      function bindReport(){
        el("genReport").addEventListener("click",genReport);
        el("dlReport").addEventListener("click",()=>{genReport();const t=el("reportPreview").textContent;const b=new Blob([t],{type:"text/plain"});const a=document.createElement("a");a.href=URL.createObjectURL(b);a.download="Vantage_BDE_"+period().label.replace(/\s+/g,"_")+"_Report.txt";a.click();URL.revokeObjectURL(a.href);});
        el("clrReport").addEventListener("click",()=>root.querySelectorAll("#reportForm textarea").forEach(x=>x.value=""));
      }
      function genReport(){
        const lines=["VANTAGE AFRICA — BDE DAILY REPORT","Period: "+period().label,"Consultant: "+B.name+" | "+B.title+" · "+B.dept,""];
        root.querySelectorAll("#reportForm input,#reportForm textarea").forEach(x=>lines.push(x.dataset.label+": "+(x.value.trim()||"—")));
        const att=B.actual/B.target;lines.push("");lines.push("Dashboard position: "+kMoney(B.actual)+" cleared against "+kMoney(B.target)+" ("+pct(att)+").");
        lines.push("Qualified pipeline: "+kMoney(B.pipeline)+". Collection: "+pct(B.collection,0)+".");
        lines.push("Commission estimate: "+kMoney(commission().current)+".");
        lines.push("All figures subject to CRM evidence and Finance verification.");
        el("reportPreview").textContent=lines.join("\n");
      }

      el("periodSelect").innerHTML=periods.map((p,i)=>`<option value="${i}" ${i===state.p?"selected":""}>${p.label}</option>`).join("");
      el("periodSelect").addEventListener("change",e=>{state.p=+e.target.value;render();});
      // Admin "View as" → reload previewing that BDO (?as=<id>), keeping the date range.
      (function(){var va=el("viewAs");if(!va)return;va.addEventListener("change",function(){var u=new URL(window.location.href);u.searchParams.set("as",this.value);window.location.href=u.toString();});})();
      root.querySelectorAll(".tab[data-v]").forEach(a=>a.addEventListener("click",()=>{root.querySelectorAll(".tab").forEach(x=>x.classList.remove("active"));a.classList.add("active");state.view=a.dataset.v;render();}));
      el("themeBtn").addEventListener("click",()=>{const dark=root.classList.toggle("theme-dark");el("themeBtn").textContent=dark?"☀ Light":"🌙 Dark";});

      render();
    })();
    </script>
  </div>
</section>

<?php require_once 'footer.php'; ?>
