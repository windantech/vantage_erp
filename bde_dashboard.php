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
require_once 'header.php';   // enquiry/admin left nav + chrome + $conn
require_once 'includes/bde_metrics.php';
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); } // live is 8.1+: a bad query must fail soft, not throw & blank the page

// Real data (phase 1): the BDE sees their OWN attributed figures. Admins can preview any
// person's real numbers with ?as=<registered_users.id>. Date range is all-time for this first
// slice (period filtering comes next), so the figure matches the raw attribution query.
$bde_ru_id = (int) ($_SESSION['login_id'] ?? 0);
if (isset($_GET['as']) && isset($role) && is_array($role) && in_array(777, $role)) {
    $bde_ru_id = (int) $_GET['as'];
}
// Date range filter (calendar). Defaults to THIS MONTH (targets are monthly).
// ?from=YYYY-MM-DD&to=YYYY-MM-DD scopes it explicitly.
$bde_today   = date('Y-m-d');
$bde_started = function_exists('bde_active_since') ? bde_active_since($conn, $bde_ru_id) : '';
$bde_join    = $bde_started !== '' ? $bde_started : '2020-01-01'; // fallback if no activity yet
$bde_month_start = date('Y-m-01');
$bde_from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['from'])) ? $_GET['from'] : $bde_month_start;
$bde_to   = (isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['to']))   ? $_GET['to']   : $bde_today;
if ($bde_from > $bde_to) { $t = $bde_from; $bde_from = $bde_to; $bde_to = $t; }
$bde_presets = [
    'month' => ['label' => 'This month',    'from' => $bde_month_start, 'to' => $bde_today],
    'last'  => ['label' => 'Last month',    'from' => date('Y-m-01', strtotime('first day of last month')), 'to' => date('Y-m-t', strtotime('last month'))],
    'ytd'   => ['label' => 'This year',     'from' => date('Y-01-01'), 'to' => $bde_today],
    'since' => ['label' => 'Since they joined' . ($bde_started !== '' ? ' · ' . date('M Y', strtotime($bde_started)) : ''), 'from' => $bde_join, 'to' => $bde_today],
    'all'   => ['label' => 'All time',      'from' => '2020-01-01', 'to' => $bde_today],
];
$bde_active_preset = 'custom';
foreach ($bde_presets as $pk => $pv) { if ($pv['from'] === $bde_from && $pv['to'] === $bde_to) { $bde_active_preset = $pk; break; } }

// Pace math for the month containing $bde_to — CALENDAR days (enquiries come on weekends too).
$paceRef = strtotime($bde_to);
$mEnd    = strtotime(date('Y-m-t', $paceRef));
$mToday  = min(strtotime($bde_to), $mEnd);
$bde_wk_total   = (int) date('t', $paceRef);              // days in the month
$bde_wk_elapsed = min($bde_wk_total, (int) date('j', $mToday)); // day-of-month reached
$bde_pace_label = date('F Y', $paceRef);

// Month-to-date daily cleared-revenue trajectory + linear forecast, for the pace chart.
$bde_daily = ($bde_ru_id > 0 && function_exists('bde_daily_revenue'))
    ? bde_daily_revenue($conn, $bde_ru_id, date('Y-m-01', $paceRef), $bde_to) : [];
$bde_dim = (int) date('t', $paceRef);
$bde_dom = max(1, min($bde_dim, (int) date('j', $mToday)));
$bde_cum_series = []; $bde_cum_dates = []; $bde_day_amt = []; $bde_cum = 0.0;
$pMonth = (int) date('n', $paceRef); $pYear = (int) date('Y', $paceRef);
for ($d = 1; $d <= $bde_dom; $d++) {
    $dt = date('Y-m-', $paceRef) . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
    $dayAmt = (float) ($bde_daily[$dt] ?? 0);
    $bde_cum += $dayAmt;
    $bde_day_amt[] = round($dayAmt);
    $bde_cum_series[] = round($bde_cum);
    $bde_cum_dates[] = date('M j', mktime(0, 0, 0, $pMonth, $d, $pYear));
}
$bde_forecast_kes = $bde_dom > 0 ? $bde_cum * ($bde_dim / $bde_dom) : 0.0;

// Unopened WhatsApp enquiries assigned to this BDE — powers the real "chats awaiting reply" alert + modal.
// Chats ESCALATED to this BDE that are still unread — the ones genuinely needing their reply
// (not every AI-handled chat). Count is the true total; the list is capped for the modal.
$bde_unread_chats = []; $bde_unread_count = 0;
if ($bde_ru_id > 0) {
    $waWhere = "assigned_user_id = $bde_ru_id AND status = 'open' AND escalated = 1 AND (last_read_at IS NULL OR last_message_at > last_read_at)";
    $cq = @mysqli_query($conn, "SELECT COUNT(*) n FROM wa_conversations WHERE $waWhere");
    if ($cq && ($cr = mysqli_fetch_assoc($cq))) { $bde_unread_count = (int) $cr['n']; }
    $uq = @mysqli_query($conn, "SELECT conv.id cid, COALESCE(NULLIF(wc.profile_name,''), wc.wa_id) nm, wc.wa_id phone, conv.last_message_at lm
        FROM wa_conversations conv LEFT JOIN wa_contacts wc ON wc.id = conv.contact_id
        WHERE conv.assigned_user_id = $bde_ru_id AND conv.status = 'open' AND conv.escalated = 1
        AND (conv.last_read_at IS NULL OR conv.last_message_at > conv.last_read_at)
        ORDER BY conv.last_message_at DESC LIMIT 40");
    while ($uq && ($ur = mysqli_fetch_assoc($uq))) {
        $bde_unread_chats[] = ['cid' => (int) $ur['cid'], 'name' => (string) $ur['nm'], 'phone' => (string) $ur['phone'], 'when' => !empty($ur['lm']) ? date('M j, H:i', strtotime((string) $ur['lm'])) : ''];
    }
}

// Repeat organizations (2+ sign-ups) = expansion / corporate opportunity — for Cross-SBU card.
$bde_repeat_orgs = [];
if ($bde_ru_id > 0) {
    $iq = @mysqli_query($conn, "SELECT intake_id FROM intake WHERE assigned_to = $bde_ru_id");
    $ii = []; while ($iq && ($x = mysqli_fetch_assoc($iq))) { $ii[] = "'" . mysqli_real_escape_string($conn, (string) $x['intake_id']) . "'"; }
    if (!empty($ii)) {
        $in = implode(',', $ii);
        $oq = @mysqli_query($conn, "SELECT r.organization org, COUNT(*) n FROM register r
            WHERE r.intake_id IN ($in) AND r.organization IS NOT NULL AND TRIM(r.organization) <> ''
            GROUP BY r.organization HAVING n >= 2 ORDER BY n DESC LIMIT 8");
        while ($oq && ($orr = mysqli_fetch_assoc($oq))) { $bde_repeat_orgs[] = ['org' => (string) $orr['org'], 'n' => (int) $orr['n']]; }
    }
}

// Payment promises from chats: clients whose inbound WhatsApp message mentioned paying.
$bde_promises = [];
if ($bde_ru_id > 0) {
    $pq = @mysqli_query($conn, "SELECT cv.id cid, COALESCE(NULLIF(wc.profile_name,''), wc.wa_id) nm, wc.wa_id phone, MAX(m.wa_timestamp) lastp
        FROM wa_messages m
        JOIN wa_contacts wc ON wc.id = m.contact_id
        JOIN wa_conversations cv ON cv.contact_id = wc.id AND cv.assigned_user_id = $bde_ru_id
        WHERE m.direction = 'inbound' AND (
            m.body LIKE '%will pay%' OR m.body LIKE '%i will send%' OR m.body LIKE '%send the money%'
            OR m.body LIKE '%pay tomorrow%' OR m.body LIKE '%pay today%' OR m.body LIKE '%make the payment%'
            OR m.body LIKE '%i''ll pay%' OR m.body LIKE '%ill pay%' OR m.body LIKE '%mpesa%' OR m.body LIKE '%paying today%')
        GROUP BY wc.id ORDER BY lastp DESC LIMIT 40");
    while ($pq && ($pr = mysqli_fetch_assoc($pq))) {
        $bde_promises[] = ['cid' => (int) $pr['cid'], 'name' => (string) $pr['nm'], 'phone' => (string) $pr['phone'],
            'when' => !empty($pr['lastp']) ? ('said ' . date('M j', is_numeric($pr['lastp']) ? (int) $pr['lastp'] : strtotime((string) $pr['lastp']))) : ''];
    }
}

// Unpaid, gone-quiet leads (registered, no cleared payment, no recent contact) — the follow-up list.
$bde_quiet_leads = [];
if ($bde_ru_id > 0) {
    $iq = @mysqli_query($conn, "SELECT intake_id FROM intake WHERE assigned_to = $bde_ru_id");
    $ii = []; while ($iq && ($x = mysqli_fetch_assoc($iq))) { $ii[] = "'" . mysqli_real_escape_string($conn, (string) $x['intake_id']) . "'"; }
    if (!empty($ii)) {
        $in = implode(',', $ii);
        $qq = @mysqli_query($conn, "SELECT r.firstname, r.lastname, r.phone_number, r.program, r.last_contact_date
            FROM register r
            WHERE r.intake_id IN ($in)
              AND r.entry_id NOT IN (SELECT app_id FROM dpo_payment WHERE status = 2)
              AND (r.last_contact_date IS NULL OR r.last_contact_date = '0000-00-00' OR r.last_contact_date < (CURDATE() - INTERVAL 7 DAY))
            ORDER BY r.datee DESC LIMIT 40");
        while ($qq && ($qr = mysqli_fetch_assoc($qq))) {
            $nm = trim(((string) ($qr['firstname'] ?? '')) . ' ' . ((string) ($qr['lastname'] ?? '')));
            $lc = (string) ($qr['last_contact_date'] ?? '');
            $bde_quiet_leads[] = [
                'name' => $nm !== '' ? $nm : '(no name)',
                'phone' => (string) ($qr['phone_number'] ?? ''),
                'prog' => (string) ($qr['program'] ?? ''),
                'when' => ($lc !== '' && $lc !== '0000-00-00') ? ('last contact ' . date('M j', strtotime($lc))) : 'never contacted',
            ];
        }
    }
}
$bde_metrics = $bde_ru_id > 0 ? bde_fetch_metrics($conn, $bde_ru_id, $bde_from, $bde_to) : null;
$bdeInitials = 'AA';
if ($bde_metrics && $bde_metrics['name'] !== '') {
    $parts = preg_split('/\s+/', trim($bde_metrics['name']));
    $bdeInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
$bde_team = ($bde_ru_id > 0 && function_exists('bde_team_metrics')) ? bde_team_metrics($conn, $bde_ru_id, $bde_from, $bde_to) : [];
$bde_tp = ($bde_metrics && function_exists('bde_targets_progress')) ? bde_targets_progress($conn, $bde_ru_id, $bde_metrics['dept'], $bde_from, $bde_to) : null;

// admin-only "View as" roster (so any BDE can be previewed by name without typing ?as=<id>)
$bde_is_admin = isset($role) && is_array($role) && in_array(777, $role);
$bde_people = []; $bde_current_listed = false; $seenP = [];
if ($bde_is_admin) {
    // Live employees (same allowlist as ceo_dashboard/staff_list.php — hides rejected/inactive/blank),
    // mapped to their login by email OR staff_id (staff_id is sparsely populated). Dedupe by login id.
    $pq = @mysqli_query($conn, "SELECT ru.id, COALESCE(NULLIF(ru.fullname,''), s.full_name) fullname, COALESCE(d.department_name,'') dept
        FROM staff s
        JOIN registered_users ru ON (ru.email COLLATE utf8mb4_general_ci = s.email COLLATE utf8mb4_general_ci OR ru.staff_id = s.id) AND ru.status = 1
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.onboarding_status IN ('pending','under_review','approved','active')
        ORDER BY d.department_name, fullname");
    while ($pq && ($pr = mysqli_fetch_assoc($pq))) {
        $pid = (int) $pr['id']; if (isset($seenP[$pid])) { continue; } $seenP[$pid] = true;
        $bde_people[] = ['id' => $pid, 'name' => (string) $pr['fullname'], 'dept' => (string) $pr['dept']];
    }
    // Fallback so the picker is never empty: if the staff↔login link is too sparse, list active accounts.
    if (count($bde_people) < 5) {
        $bde_people = []; $seenP = [];
        $pq = @mysqli_query($conn, "SELECT ru.id, ru.fullname, COALESCE(d.department_name,'') dept
            FROM registered_users ru LEFT JOIN staff s ON ru.staff_id = s.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE ru.status = 1 AND ru.fullname <> '' ORDER BY d.department_name, ru.fullname");
        while ($pq && ($pr = mysqli_fetch_assoc($pq))) {
            $pid = (int) $pr['id']; if (isset($seenP[$pid])) { continue; } $seenP[$pid] = true;
            $bde_people[] = ['id' => $pid, 'name' => (string) $pr['fullname'], 'dept' => (string) $pr['dept']];
        }
    }
    foreach ($bde_people as $p) { if ($p['id'] === $bde_ru_id) { $bde_current_listed = true; break; } }
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
      max-width:none;margin:0;padding:80px 24px 44px;border-radius:0;min-height:100vh;
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
    .bde-app .control input[type=date]{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:7px 10px;font-size:12.5px;font-weight:650;color:var(--ink);font-family:inherit}
    .bde-app .control input[type=date]:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
    .bde-app .daterange{display:flex;align-items:center;gap:6px}
    .bde-app .daterange span{color:var(--muted);font-size:13px;font-weight:700}
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
    .bde-app .section-tag{display:flex;align-items:baseline;gap:12px;margin:8px 2px 0}
    .bde-app .section-tag h3{margin:0;font-size:16px;letter-spacing:-.01em} .bde-app .section-tag>span{font-size:12.5px;color:var(--muted)} .bde-app .section-tag .rule{flex:1;height:1px;align-self:center;background:linear-gradient(90deg,var(--line),transparent)}

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
    .bde-app .kpi{position:relative;background:var(--surface2);border:1px solid var(--line);border-radius:var(--radius-sm);padding:15px;overflow:hidden;cursor:default}
    .bde-app .kpi::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--acc,var(--brand));border-radius:var(--radius-sm) var(--radius-sm) 0 0}
    /* ---- scoreboard: clean white cards (colour lives in the icon + number) ---- */
    .bde-app .results{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .bde-app .result{position:relative;border-radius:16px;padding:20px 22px;border:1px solid var(--line);background:var(--surface);overflow:hidden;cursor:default}
    .bde-app .result .ricon{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;background:var(--acc,var(--brand));color:#fff;margin-bottom:15px;box-shadow:0 10px 22px -9px var(--acc,var(--brand))}
    .bde-app .result .ricon svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .bde-app .result .rlab{font-size:11px;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);font-weight:800}
    .bde-app .result .rval{font-size:38px;font-weight:850;letter-spacing:-.025em;line-height:1.02;margin:6px 0 5px;color:var(--acc,var(--brand))}
    .bde-app .result .rmeta{font-size:13.5px;color:var(--muted)}
    @media(max-width:820px){.bde-app .results{grid-template-columns:1fr}}
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
    .bde-app .field select{background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:10px 34px 10px 12px;font-size:13px;width:100%;font-weight:650;color:var(--ink);appearance:none;-webkit-appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--muted) 50%),linear-gradient(135deg,var(--muted) 50%,transparent 50%);background-position:calc(100% - 16px) 18px,calc(100% - 11px) 18px;background-size:5px 5px;background-repeat:no-repeat;cursor:pointer}
    .bde-app .field select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
    .bde-app .field select:hover{border-color:color-mix(in srgb,var(--brand) 35%,var(--line))}
    .bde-app .form-sub{display:flex;align-items:center;gap:10px;margin:2px 2px 11px;font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);font-weight:800} .bde-app .form-sub i{color:var(--brand);font-style:normal;font-weight:800;letter-spacing:.03em} .bde-app .form-sub::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,var(--line),transparent)}
    .bde-app .report-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}
    .bde-app .report-preview{white-space:pre-wrap;background:var(--surface2);border:1px dashed var(--line);border-radius:12px;padding:14px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;line-height:1.6;min-height:130px;color:var(--ink2)}

    .bde-app .bde-foot{font-size:11.5px;color:var(--muted);margin-top:14px;line-height:1.6} .bde-app .bde-foot code{background:var(--surface2);padding:1px 5px;border-radius:5px;border:1px solid var(--line)}

    @media(max-width:1000px){
      .bde-app .hero,.bde-app .grid-2,.bde-app .grid-3,.bde-app .strategy{grid-template-columns:1fr} .bde-app .kpis{grid-template-columns:1fr 1fr} .bde-app .drivers{grid-template-columns:repeat(2,1fr)} .bde-app .principles{grid-template-columns:1fr} .bde-app .form-grid{grid-template-columns:repeat(2,1fr)} .bde-app .field.span4{grid-column:span 2}
    }
    @media(max-width:560px){.bde-app{padding:12px 14px 40px} .bde-app .kpis,.bde-app .mini3,.bde-app .steps3,.bde-app .form-grid{grid-template-columns:1fr} .bde-app .field.span2,.bde-app .field.span4{grid-column:span 1} .bde-app .fr{grid-template-columns:110px 1fr 42px} .bde-app .scr{grid-template-columns:130px 1fr 40px}}
    @media(prefers-reduced-motion:reduce){.bde-app *{transition:none!important}}
    /* Targets view */
    .bde-app .tsum{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:16px;box-shadow:var(--shadow-sm)}
    .bde-app .tsum .pl{font-size:14px;color:var(--ink)}
    .bde-app .ttrack{height:9px;border-radius:999px;background:var(--surface2);overflow:hidden;border:1px solid var(--line)}
    .bde-app .tfill{height:100%;border-radius:999px;transition:width .5s}
    .bde-app .tgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
    .bde-app .tcard{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:15px 16px;box-shadow:var(--shadow-sm)}
    .bde-app .tcard-h{display:flex;align-items:center;gap:8px;margin-bottom:8px}
    .bde-app .tcard-h b{font-size:13.5px;color:var(--ink);line-height:1.3}
    .bde-app .pill2{flex:none;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.03em;padding:3px 8px;border-radius:999px;background:var(--surface2);color:var(--muted);border:1px solid var(--line)}
    .bde-app .tbig{font-size:22px;font-weight:800;color:var(--ink);letter-spacing:-.01em}
    .bde-app .tbig span{font-size:12px;font-weight:700;color:var(--muted)}
    .bde-app .tmeta{font-size:12px;color:var(--ink);margin-top:6px}
    .bde-app .tnote{font-size:11.5px;color:var(--muted);margin-top:10px;padding-top:8px;border-top:1px dashed var(--line)}
    .bde-app .tlevels{display:flex;gap:10px;margin:12px 0 10px}
    .bde-app .tlevel{flex:1;background:var(--surface2);border:1px solid var(--line);border-radius:10px;padding:9px 11px}
    .bde-app .tlevel .tl-cap{display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:4px}
    .bde-app .tlevel b{font-size:16.5px;font-weight:800;color:var(--ink);letter-spacing:-.01em;line-height:1.1}
    .bde-app .tl-full{border-left:3px solid var(--brand)}
    .bde-app .tl-qual{border-left:3px solid var(--amber)}
    .bde-app .tprog{margin-top:2px}
    .bde-app .ttrack{position:relative}
    .bde-app .tmark{position:absolute;top:-2px;bottom:-2px;width:2px;background:var(--ink);opacity:.45}
    .bde-app .tprog-b{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--ink);margin-top:6px}
    .bde-app .tprog-none{font-size:12px;color:var(--muted);margin-top:4px;font-style:italic}
    .amodal-ov{position:fixed;inset:0;background:rgba(16,24,40,.5);display:flex;align-items:center;justify-content:center;z-index:9999;padding:20px}
    .amodal{background:#fff;border-radius:16px;max-width:560px;width:100%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(16,24,40,.3);overflow:hidden;font:14px/1.5 system-ui,Segoe UI,Roboto,sans-serif;color:#0e1726}
    .amodal-h{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #eef2f7}
    .amodal-h h4{margin:0;font-size:16px;font-weight:800}
    .amodal-x{border:0;background:#f1f5f9;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:15px;color:#475569}
    .amodal-b{overflow-y:auto;padding:8px 12px}
    .amrow{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 10px;border-bottom:1px solid #f1f5f9}
    .amrow:last-child{border-bottom:0}
    .amrow b{font-size:13.5px;color:#0e1726;display:block}
    .amrow small{font-size:11.5px;color:#64748b}
    .amwhen{font-size:11px;color:#94a3b8;white-space:nowrap}
    .amodal-f{padding:12px 20px;border-top:1px solid #eef2f7;display:flex;justify-content:flex-end;gap:8px}
    .bde-app .tgroup{padding-top:12px}
    .bde-app .tgroup + .tgroup{border-top:1px solid var(--line);margin-top:12px}
    .bde-app .tgroup-h{font-weight:800;font-size:13.5px;color:var(--ink);margin-bottom:2px}
    .bde-app .tmetric{padding-top:10px}
    .bde-app .tmetric + .tmetric{border-top:1px solid var(--line);margin-top:10px}
    .bde-app .tmetric-h{display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap}
    .bde-app .tmetric-l{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
    .bde-app .tchip{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:3px 9px;border-radius:999px;white-space:nowrap}
    .bde-app .tchip-count{background:#e0edff;color:#1d4ed8}
    .bde-app .tchip-money{background:#e3f6ec;color:#0f7a43}
    .bde-app .tl-count b{color:#1d4ed8}
    .bde-app .tl-count.tl-full{border-left-color:#1d4ed8}
    .bde-app .tl-money b{color:#0f7a43}
    .bde-app .tl-money.tl-full{border-left-color:#0f7a43}
    </style>

    <div class="bde-app" id="bdeApp">
      <header class="bde-topbar">
        <div class="brand"><div class="mark">VA</div><div><h1>Performance Command Centre</h1><p>Strategy → daily execution → verified revenue → commission → growth</p></div></div>
        <div class="controls">
          <?php if ($bde_is_admin && !empty($bde_people)): ?>
          <div class="control"><label>View as (admin)</label>
            <select id="viewAs">
              <?php if (!$bde_current_listed && $bde_ru_id > 0): ?>
                <option value="<?php echo $bde_ru_id; ?>" selected>Currently viewing: <?php echo htmlspecialchars($bde_metrics && $bde_metrics['name'] !== '' ? $bde_metrics['name'] : ('#' . $bde_ru_id)); ?></option>
              <?php endif; ?>
              <?php $curDept = ''; foreach ($bde_people as $p): if ($p['dept'] !== $curDept): if ($curDept !== '') echo '</optgroup>'; $curDept = $p['dept']; ?>
                <optgroup label="<?php echo htmlspecialchars($curDept !== '' ? $curDept : 'Unassigned'); ?>">
              <?php endif; ?>
                <option value="<?php echo $p['id']; ?>"<?php echo $p['id'] === $bde_ru_id ? ' selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?> (#<?php echo $p['id']; ?>)</option>
              <?php endforeach; if ($curDept !== '') echo '</optgroup>'; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="control"><label>Period</label>
            <select id="periodPreset">
              <?php foreach ($bde_presets as $pk => $pv): ?>
                <option value="<?php echo $pk; ?>" data-from="<?php echo $pv['from']; ?>" data-to="<?php echo $pv['to']; ?>"<?php echo $bde_active_preset === $pk ? ' selected' : ''; ?>><?php echo htmlspecialchars($pv['label']); ?></option>
              <?php endforeach; ?>
              <option value="custom"<?php echo $bde_active_preset === 'custom' ? ' selected' : ''; ?>>Custom…</option>
            </select>
          </div>
          <div class="control" id="customWrap"<?php echo $bde_active_preset === 'custom' ? '' : ' style="display:none"'; ?>><label>Range</label><div class="daterange"><input type="date" id="fromDate" value="<?php echo htmlspecialchars($bde_from); ?>"><span>→</span><input type="date" id="toDate" value="<?php echo htmlspecialchars($bde_to); ?>"></div></div>
          <select id="periodSelect" style="display:none"></select>
          <button class="tbtn" id="themeBtn" type="button">🌙 Dark</button>
          <div class="profile-chip"><span class="a"><?php echo htmlspecialchars($bdeInitials); ?></span><div><b><?php echo htmlspecialchars($bde_metrics && $bde_metrics['name'] !== '' ? $bde_metrics['name'] : 'BDE'); ?></b><span><?php echo htmlspecialchars($bde_metrics ? (($bde_metrics['title'] !== '' ? $bde_metrics['title'] : 'BDE') . ' · ' . $bde_metrics['mandate']['tag']) : 'BDE · Digital Solutions'); ?></span></div></div>
        </div>
      </header>
      <nav class="tabs" aria-label="Dashboard sections">
        <button class="tab active" data-v="command"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Command Centre</button>
        <button class="tab" data-v="pipeline"><svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>Pipeline &amp; Conversion</button>
        <button class="tab" data-v="visits"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>Field Visits</button>
        <button class="tab" data-v="commission"><svg viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="6"/><path d="M8.5 13.5l-1.5 7 5-3 5 3-1.5-7"/></svg>Commission</button>
        <button class="tab" data-v="report"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/></svg>Daily Report</button>
        <button class="tab" data-v="strategy"><svg viewBox="0 0 24 24"><path d="M12 20v-6M6 20v-3M18 20v-10"/><circle cx="12" cy="11" r="1.6" fill="currentColor" stroke="none"/><circle cx="6" cy="14" r="1.6" fill="currentColor" stroke="none"/><circle cx="18" cy="7" r="1.6" fill="currentColor" stroke="none"/></svg>Strategy &amp; Scorecard</button>
      </nav>
      <main id="workspace"></main>
    </div>

    <script>
    (() => {
      "use strict";
      const root=document.getElementById("bdeApp");
      const B={
        name:"Austin Abere", initials:"AA", title:"BDE — Eval360", dept:"Digital Solutions", deptLeader:"Alein Kawinzi Kagunza",
        target:2150000, actual:1370000, pipeline:8900000, collection:.95, crm:98, units:84, unitTarget:100, corporateClients:1, maintenance:100000, forecast:2300000,
        mandate:"Turn Eval360 and 360 Appraisal into visible, trusted and fast-growing recurring-revenue solutions.",
        mandateText:"Growth requires product mastery, direct organization engagement, aggressive demonstrations, RFP intelligence, digital demand generation, reliable self-onboarding, strong adoption and proactive maintenance and renewals.",
        focus:"Move qualified organizations into demos and paid onboarding while protecting product readiness and recurring revenue.",
        drivers:[["Qualified organizations",74,"Active pipeline"],["Demos completed",21,"This month"],["RFPs assessed",18,"100% within 24h"],["Active paid users",412,"Across products"],["Renewals visible","100%","Within 60 days"]],
        funnel:[["Organizations identified",240],["Decision-makers reached",138],["Discovery",74],["Demo",45],["Proposal / pilot",26],["Paid onboarding",9]],
        sources:[["Direct organization outreach",32],["Digital campaigns",26],["RFP / procurement",18],["Professional platforms",14],["Cross-SBU referrals",10]],
        priorities:[
          ["Regional NGO Consortium","Demo scheduled","KES 900K","Prepare tailored Eval360 demo","Tomorrow"],
          ["Manufacturing Group","Proposal","420 staff","Confirm procurement route","Today"],
          ["Government Planning Unit","RFP qualified","KES 4.2M","Complete bid / no-bid review","Today"],
          ["SME Founder Network","Campaign","180 staff potential","Schedule group briefing","Friday"]
        ],
        dailyRhythm:[
          ["8:00–8:30","Review enquiries, RFP alerts, demos, onboarding issues, product defects and renewals."],
          ["8:30–9:00","Set decision-maker, demo, proposal, content, onboarding and revenue targets."],
          ["9:00–11:00","Call priority organizations, champions, procurement and payment contacts."],
          ["11:00–1:00","Discovery, RFP qualification, demo preparation and tailored value assets."],
          ["2:00–4:00","Run demos, executive briefings, proposals, pilots and onboarding support."],
          ["4:00–5:15","Follow up decisions, fix friction, update CRM and prepare next-day accounts."]
        ],
        principles:[
          ["Sell outcomes, not screens","Connect the product to institutional performance, reporting, decisions, development and accountability."],
          ["Every serious account reaches a tailored demo","A demo without discovery, decision-makers and a next step is only activity."],
          ["Onboarding friction is a revenue risk","Self-service and automated journeys must be tested repeatedly and corrected quickly."]
        ],
        team:[
          {name:"Austin Abere",title:"BDE — Eval360",target:2150000,actual:1370000,pipeline:8900000,collection:.95,units:84,me:true,notes:"Corporate setup pipeline strong; individual users need 16 more."},
          {name:"Ruth Ngari",title:"BDE — 360 Appraisal",target:1200000,actual:1020000,pipeline:5700000,collection:.92,units:510,notes:"30 paid staff to reach the 80% commission threshold."}
        ],
        visitGoal:60,
        visits:[
          {date:"2026-09-15",client:"Grace Wanjiru",org:"Nairobi Women's SACCO",location:"Nairobi CBD",product:"Eval360",outcome:"registered",value:180000,notes:"Signed up 24 staff for Eval360; onboarding next week."},
          {date:"2026-09-15",client:"Peter Otieno",org:"Rift Valley Dairies",location:"Nakuru",product:"360 Appraisal",outcome:"interested",value:120000,notes:"Wants a tailored demo for HR before committing."},
          {date:"2026-09-14",client:"Amina Yusuf",org:"Coastal Youth Trust",location:"Mombasa",product:"M&E System",outcome:"visited",value:0,notes:"Introductory meeting; decision-maker travelling, follow up Fri."},
          {date:"2026-09-14",client:"James Kariuki",org:"Summit Manufacturing",location:"Thika",product:"360 Appraisal",outcome:"interested",value:420000,notes:"420 staff; routing through procurement."},
          {date:"2026-09-13",client:"Dr. Nafula",org:"Western Health Network",location:"Kakamega",product:"Data Analysis",outcome:"registered",value:95000,notes:"Paid for the data-analysis package on the spot."},
          {date:"2026-09-13",client:"Brian Mutua",org:"AgriConnect Co-op",location:"Machakos",product:"Eval360",outcome:"visited",value:0,notes:"Gatekeeper meeting; needs board approval."},
          {date:"2026-09-12",client:"Sarah Chebet",org:"Highlands SACCO",location:"Eldoret",product:"Eval360",outcome:"interested",value:150000,notes:"Comparing us with a competitor; send value doc."},
          {date:"2026-09-12",client:"Kevin Odhiambo",org:"Lakeside Traders Assoc.",location:"Kisumu",product:"360 Appraisal",outcome:"registered",value:110000,notes:"Group briefing converted to a paid cohort of 18."},
          {date:"2026-09-11",client:"Lucy Wambui",org:"Metro Property Ltd",location:"Nairobi Westlands",product:"M&E System",outcome:"visited",value:0,notes:"Cold visit; captured contact, scheduled discovery call."}
        ]
      };
<?php if ($bde_metrics): ?>
      /* ---- real data override: name, revenue, funnel, sources, collection, units, mandate, commission ---- */
      Object.assign(B, {
        name: <?php echo json_encode($bde_metrics['name'] !== '' ? $bde_metrics['name'] : 'BDE', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDE"'; ?>,
        initials: <?php echo json_encode($bdeInitials, JSON_INVALID_UTF8_SUBSTITUTE) ?: '"AA"'; ?>,
        title: <?php echo json_encode($bde_metrics['title'] !== '' ? $bde_metrics['title'] : 'BDE', JSON_INVALID_UTF8_SUBSTITUTE) ?: '"BDE"'; ?>,
        dept: <?php echo json_encode($bde_metrics['mandate']['tag'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>,
        actual: <?php echo (float) $bde_metrics['revenue_kes']; ?>,
        collection: <?php echo (float) $bde_metrics['collection_rate']; ?>,
        units: <?php echo (int) $bde_metrics['units']; ?>,
        funnel: <?php echo json_encode($bde_metrics['funnel'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        sources: <?php echo json_encode($bde_metrics['sources'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>,
        mandate: <?php echo json_encode($bde_metrics['mandate']['mission'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>,
        mandateText: <?php echo json_encode($bde_metrics['mandate']['detail'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>,
        focus: <?php echo json_encode($bde_metrics['mandate']['focus'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>,
        real: true,
        revenueUsd: <?php echo (float) $bde_metrics['revenue_usd']; ?>,
        expectedUsd: <?php echo (float) $bde_metrics['expected_usd']; ?>,
        totalRegs: <?php echo (int) $bde_metrics['total_regs']; ?>,
        paidClients: <?php echo (int) $bde_metrics['paid_clients']; ?>,
        stale: <?php echo (int) $bde_metrics['stale']; ?>,
        totalLeads: <?php echo (int) ($bde_metrics['total_leads'] ?? 0); ?>,
        contacted: <?php echo (int) ($bde_metrics['contacted'] ?? 0); ?>,
        waOpen: <?php echo (int) ($bde_metrics['wa_open'] ?? 0); ?>,
        waUnread: <?php echo (int) ($bde_metrics['wa_unread'] ?? 0); ?>,
        commissionKes: <?php echo (float) $bde_metrics['commission_kes']; ?>
      });
      B.team = <?php echo json_encode(!empty($bde_team) ? $bde_team : [['name' => ($bde_metrics['name'] !== '' ? $bde_metrics['name'] : 'You'), 'title' => ($bde_metrics['title'] !== '' ? $bde_metrics['title'] : 'BDE'), 'actual' => (float) $bde_metrics['revenue_kes'], 'clients' => (int) $bde_metrics['paid_clients'], 'me' => true]], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
<?php if ($bde_tp): ?>
      /* ---- real targets (from bde_targets) + progress ---- */
      B.targets = <?php echo json_encode($bde_tp['rows'], JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.targetTotal = <?php echo (float) $bde_tp['revenue_target']; ?>;
      B.targetActual = <?php echo (float) $bde_tp['revenue_actual']; ?>;
      // replace the demo target with the real monthly revenue target so pace/attainment are honest
      if (B.targetTotal > 0) { B.target = B.targetTotal; B.actual = B.targetActual; }
<?php endif; ?>
      // real calendar-day pace for the selected month
      B.periodTotal = <?php echo (int) $bde_wk_total; ?>;
      B.periodElapsed = <?php echo (int) $bde_wk_elapsed; ?>;
      B.periodLabel = <?php echo json_encode($bde_pace_label, JSON_INVALID_UTF8_SUBSTITUTE) ?: '""'; ?>;
      // real outstanding pipeline = expected revenue on their registrations not yet collected (KES)
      B.pipelineKes = <?php echo (float) (max(0.0, ((float) $bde_metrics['expected_usd'] - (float) $bde_metrics['revenue_usd'])) * (function_exists('bde_usd_to_kes') ? bde_usd_to_kes($conn) : 129.0)); ?>;
      // real commission from the engine (commission_records): eligible (KES) + paid to date (KES)
      B.commissionPaidKes = <?php echo (float) (((float) ($bde_metrics['commission_paid_usd'] ?? 0)) * (function_exists('bde_usd_to_kes') ? bde_usd_to_kes($conn) : 129.0)); ?>;
      // real month-to-date daily cumulative revenue (KES) + linear month-end forecast
      B.dailyCum = <?php echo json_encode($bde_cum_series, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.dailyAmt = <?php echo json_encode($bde_day_amt, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.dailyDates = <?php echo json_encode($bde_cum_dates, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.daysInMonth = <?php echo (int) $bde_dim; ?>;
      B.dayToday = <?php echo (int) $bde_dom; ?>;
      B.forecast = <?php echo (float) $bde_forecast_kes; ?>;
      B.unreadChats = <?php echo json_encode($bde_unread_chats, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.unreadCount = <?php echo (int) $bde_unread_count; ?>;
      B.quietLeads = <?php echo json_encode($bde_quiet_leads, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.promises = <?php echo json_encode($bde_promises, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
      B.repeatOrgs = <?php echo json_encode($bde_repeat_orgs, JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
<?php endif; ?>
      const periods=[{label:"July 2026",working:23,elapsed:23},{label:"August 2026",working:21,elapsed:21},{label:"September 2026",working:22,elapsed:13},{label:"October 2026",working:23,elapsed:6}];
      const _views=["command","targets","pipeline","visits","commission","report","strategy"];
      const _hv=(location.hash||"").replace("#","");
      const state={p:2,view:_views.indexOf(_hv)>=0?_hv:"command"};

      const nf=new Intl.NumberFormat("en-KE",{maximumFractionDigits:0});
      const kMoney=v=>{const a=Math.abs(v||0);if(a>=1e6)return "KES "+(v/1e6).toFixed(2).replace(/\.00$/,"")+"M";if(a>=1e3)return "KES "+Math.round(v/1e3)+"K";return "KES "+nf.format(Math.round(v||0));};
      const pct=(v,d=1)=>(v*100).toFixed(d).replace(/\.0$/,"")+"%";
      const esc=s=>String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
      const el=id=>document.getElementById(id);
      const fmtDate=s=>{const d=new Date(s+"T00:00:00");return isNaN(d.getTime())?s:d.toLocaleDateString("en-GB",{day:"2-digit",month:"short",year:"numeric"});};
      const todayStr=()=>{const d=new Date();return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0");};
      const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
      const period=()=>({working:B.periodTotal||22, elapsed:Math.min(B.periodElapsed||1,B.periodTotal||22), label:B.periodLabel||(periods[state.p]&&periods[state.p].label)||""});

      function pace(){const p=period();const expected=B.target*(p.elapsed/p.working);const ratio=expected?B.actual/expected:0;const status=ratio>=1?"green":ratio>=.85?"amber":"red";return {expected,ratio,status,label:status==="green"?"On pace":status==="amber"?"At risk":"Behind pace"};}
      const scol=s=>s==="green"?"var(--jade)":s==="amber"?"var(--amber)":"var(--coral)";
      function commission(){
        const s=B;const iRate=s.units>=150?.10:s.units>=125?.075:s.units>=100?.05:0;
        const iUnlocked=iRate>0&&s.actual>=350000&&s.corporateClients>=1;
        const cUnlocked=s.corporateClients>=2&&s.units>=80&&s.actual>=280000;
        const individual=iUnlocked?Math.min(s.actual,600000)*iRate:0;
        const setup=cUnlocked?s.corporateClients*900000*.03:0;
        const maint=cUnlocked?(s.maintenance||0)*.025:0;
        const bonus=(s.units>=100&&s.actual>=350000&&s.corporateClients>=2)?25000:0;
        const current=individual+setup+maint+bonus;
        const atTarget=350000*.05+2*900000*.03+100000*.025+25000;
        const gates=[
          ["100 active paying users",s.units>=100,s.units+" users"],
          ["KES 350,000 individual collections",s.actual>=350000,kMoney(s.actual)],
          ["2 fully paid corporate setups",s.corporateClients>=2,s.corporateClients+" client"+(s.corporateClients===1?"":"s")],
          ["Maintenance current (90%+)",s.collection>=.9,pct(s.collection,0)]
        ];
        const unlock=bonus?"Balanced performance bonus unlocked":(cUnlocked||iUnlocked)?"One stream unlocked — complete the balance":"Meet the individual and corporate support thresholds to unlock.";
        return {current,atTarget,gates,unlock};
      }

      /* ---------- shared blocks ---------- */
      function strategyStrip(){return `<section class="strategy"><div><div class="eyebrow">Personal performance mandate</div><h2>${esc(B.mandate)}</h2><p>${esc(B.mandateText)}</p></div><div class="focus"><b>Today's strategic focus</b><span>${esc(B.focus)}</span></div></section>`;}
      function realOutcomes(){
        const conv=B.totalRegs?(B.paidClients||0)/B.totalRegs:0;
        const icMoney='<svg viewBox="0 0 24 24"><path d="M3 7l3-3h12l3 3v12H3z"/><path d="M3 7h18"/><path d="M15 12h3"/></svg>';
        const icUsers='<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 5.4a3.4 3.4 0 0 1 0 5.2M20.5 20a5.5 5.5 0 0 0-3.6-5.2"/></svg>';
        const icConv='<svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>';
        const card=(l,v,m,a,ic)=>`<div class="result" style="--acc:${a}"><div class="ricon">${ic}</div><div class="rlab">${l}</div><div class="rval num">${v}</div><div class="rmeta">${m}</div></div>`;
        return `<div class="section-tag" style="margin-top:6px"><h3>Your scoreboard</h3><span>Cleared revenue, paying clients and conversion — live</span><div class="rule"></div></div>
          <section class="results">
            ${card("Cleared revenue",kMoney(B.actual||0),(B.revenueUsd!=null?("$"+nf.format(B.revenueUsd)+" settled"):"settled payments"),"var(--jade)",icMoney)}
            ${card("Paid clients",nf.format(B.paidClients||0),"of "+nf.format(B.totalRegs||0)+" leads",'var(--slate)',icUsers)}
            ${card("Conversion",pct(conv,0),"leads → paid clients","var(--brand)",icConv)}
          </section>`;
      }

      function kpiBlock(){
        const p=period();const hasRev=B.target>0;const att=hasRev?B.actual/B.target:0;const daysLeft=Math.max(0,p.working-p.elapsed);
        const dailyNeed=(hasRev&&daysLeft)?Math.max(0,(B.target-B.actual)/daysLeft):0;const pipe=B.pipelineKes||0;
        const items=[
          ["Monthly target",hasRev?kMoney(B.target):"—",hasRev?"All your targets combined":"Count-based target","flat","var(--slate)"],
          ["Cleared revenue",kMoney(B.actual),hasRev?pct(att)+" of target":"collected this period","up","var(--jade)"],
          ["Paid clients",nf.format(B.paidClients||0),"of "+nf.format(B.totalRegs||0)+" leads","flat","var(--slate)"],
          ["Outstanding pipeline",kMoney(pipe),hasRev?(pipe/B.target).toFixed(1)+"× target coverage":"expected, still to collect","up","var(--slate)"],
          ["Commission (eligible)",kMoney(B.commissionKes||0),"from the commission engine","flat","var(--gold)"],
          ["Daily pace needed",hasRev?kMoney(dailyNeed):"—",daysLeft+" days left this month","flat","var(--amber)"]
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
        const p=period();const att=B.target>0?B.actual/B.target:0;const ps=pace();const daysLeft=Math.max(0,p.working-p.elapsed);
        const motiv=ps.status==="green"?"<b>Keep going:</b> You're at or above required pace. Protect collections, quality and stretch opportunities.":ps.status==="amber"?"<b>Close the gap:</b> You're near pace. Focus on the opportunities nearest to payment and remove today's biggest blocker.":"<b>Recover now:</b> The current pace will miss target. Start a quantified recovery plan today — not at month end.";
        return `<div class="card prog">
          <div class="chead"><h4>Progress to target</h4><span class="chip ${ps.status==="green"?"jade":ps.status==="amber"?"amber":"coral"} num">${pct(att)}</span></div>
          <div class="pl">Cleared revenue · <b class="num">${kMoney(B.actual)} / ${kMoney(B.target)}</b></div>
          <div class="bar"><div class="bf" style="width:${clamp(att*100,0,100)}%"></div><div class="exp" style="left:${clamp((p.elapsed/p.working)*100,0,100)}%"></div></div>
          <div class="mini3"><div class="cm"><span>Expected by today</span><b class="num">${kMoney(ps.expected)}</b></div><div class="cm"><span>Remaining gap</span><b class="num">${kMoney(Math.max(0,B.target-B.actual))}</b></div><div class="cm"><span>Days left</span><b class="num">${daysLeft}</b><small style="display:block;font-size:9.5px;color:var(--muted);font-weight:600">to end of ${esc(period().label||"month")}</small></div></div>
          <div class="motiv ${ps.status}">${motiv}</div>
        </div>`;
      }

      function trendSVG(){
        const series=(B.dailyCum&&B.dailyCum.length)?B.dailyCum:[0];
        const dim=Math.max(2,B.daysInMonth||30);
        const dayT=Math.max(1,Math.min(dim,B.dayToday||series.length));
        const target=B.target||0;
        const cur=series[series.length-1]||0;
        const w=560,h=200,pd=34;
        const max=Math.max(target,cur,...series,1)*1.12;
        const X=day=>pd+(day-1)/(dim-1)*(w-2*pd);      // day 1..dim → x
        const Y=v=>h-pd-(v/max)*(h-2*pd);
        const A=series.map((v,i)=>[X(i+1),Y(v)]);       // actual, day 1..dayT
        const aLine=A.map((q,i)=>(i?"L":"M")+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ");
        const aArea=`M${A[0][0].toFixed(1)},${(h-pd).toFixed(1)} `+A.map(q=>"L"+q[0].toFixed(1)+","+q[1].toFixed(1)).join(" ")+` L${A[A.length-1][0].toFixed(1)},${(h-pd).toFixed(1)} Z`;
        const ty=Y(target),tx=X(dayT);
        return `<svg class="chart" viewBox="0 0 ${w} ${h}" role="img" aria-label="Month-to-date cleared revenue vs target">
          ${[0,.25,.5,.75,1].map(t=>`<line class="grid" x1="${pd}" y1="${(pd+t*(h-2*pd)).toFixed(1)}" x2="${w-pd}" y2="${(pd+t*(h-2*pd)).toFixed(1)}"/>`).join("")}
          <line class="tline" x1="${pd}" y1="${ty.toFixed(1)}" x2="${w-pd}" y2="${ty.toFixed(1)}"/><text x="${w-pd}" y="${(ty-6).toFixed(1)}" text-anchor="end">Target ${kMoney(target)}</text>
          <line x1="${tx.toFixed(1)}" y1="${pd}" x2="${tx.toFixed(1)}" y2="${h-pd}" stroke="var(--faint)" stroke-dasharray="3 3"/>
          <path class="area" d="${aArea}"/><path class="line" d="${aLine}"/>
          ${A.map((q,i)=>{const dAmt=(B.dailyAmt&&B.dailyAmt[i])||0;const dLbl=(B.dailyDates&&B.dailyDates[i])||("Day "+(i+1));return `<circle cx="${q[0].toFixed(1)}" cy="${q[1].toFixed(1)}" r="2.6" fill="var(--brand)"/><circle cx="${q[0].toFixed(1)}" cy="${q[1].toFixed(1)}" r="10" fill="transparent" style="cursor:pointer"><title>${esc(dLbl)}: ${kMoney(dAmt)} cleared that day  (${kMoney(series[i])} so far)</title></circle>`;}).join("")}
          <circle cx="${tx.toFixed(1)}" cy="${Y(cur).toFixed(1)}" r="4.5" fill="var(--brand)" stroke="#fff" stroke-width="1.5"/><text x="${tx.toFixed(1)}" y="${Math.max(pd+10,Y(cur)-9).toFixed(1)}" text-anchor="middle" style="font-weight:800;fill:var(--ink)">Now ${kMoney(cur)}</text>
          <text x="${pd}" y="${h-8}">Day 1</text><text x="${tx.toFixed(1)}" y="${h-8}" text-anchor="middle">Today (day ${dayT})</text><text x="${w-pd}" y="${h-8}" text-anchor="end">Month end</text></svg>`;
      }

      function commissionMini(){
        const elig=B.commissionKes||0;const paid=B.commissionPaidKes||0;const att=B.target>0?B.actual/B.target:0;const shown=clamp(att,0,1.2)/1.2;
        return `<div class="card">
          <div class="chead"><h4>Commission</h4><span class="chip ${elig>0?"jade":"gold"}">${elig>0?"Eligible":"Not yet eligible"}</span></div>
          <div class="road-wrap"><div class="road"><div class="rf" style="width:${shown*100}%"></div></div><div class="rmark" style="left:66.6%"><i></i><span>80%</span></div><div class="rmark" style="left:83.3%"><i></i><span>100%</span></div><div class="rmark" style="left:100%"><i></i><span>120%</span></div></div>
          <div class="mini3"><div class="cm gold"><span>Eligible now</span><b class="num">${kMoney(elig)}</b></div><div class="cm"><span>Paid to date</span><b class="num">${kMoney(paid)}</b></div><div class="cm"><span>Target attainment</span><b class="num">${pct(att,0)}</b></div></div>
          <div class="nextstep"><b>From the CRM commission engine.</b> Full per-target commission projection comes with the commission phase.</div>
        </div>`;
      }

      function actionsCard(){
        const list=[
          ["red","Call payment-ready prospects","Complete all overdue payment promises and record the outcome.","Before 10:30"],
          ["red","Progress demos and onboarding","Confirm discovery, decision-makers, demo objective, proposal and onboarding issue status.","Today"],
          ["amber","Move priority opportunities","Every hot or institutional lead must have a dated commercial next step.","Today"],
          ["blue","Protect CRM evidence","Update calls, meetings, objections, proposal status and payment evidence.","Before report"],
          ["green","Create tomorrow's advantage","Prepare the top five prospects, decision-makers or account exceptions for the next day.","4:45 PM"]
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
        const F=B.funnel||[];const fv=i=>F[i]?F[i][1]:0;
        const drivers=[
          ["Total leads",nf.format(B.totalLeads||0),"Enquiries assigned to you"],
          ["Paid clients",nf.format(B.paidClients||0),(B.totalLeads?pct((B.paidClients||0)/B.totalLeads,0)+" of leads converted":"cleared & paid")],
          ["Collection rate",(B.collection!=null?pct(B.collection,0):"—"),"Fees settled vs expected"],
          ["WhatsApp to reply",nf.format(B.waUnread||0),(B.waOpen?"unread · of "+nf.format(B.waOpen)+" open chats":"unread chats")],
          ["Commission (eligible)",(B.commissionKes?kMoney(B.commissionKes):"KES 0"),"From the commission engine"]
        ];
        return `<div class="card"><div class="chead"><h4>Execution drivers</h4><span class="chip slate">${esc(B.dept||"")}</span></div><div class="drivers">${drivers.map(([l,v,s],i)=>`<div class="driver" style="--dacc:${dAcc[i%dAcc.length]}"><div class="dtop"><span class="dicon">${dIcons[i%dIcons.length]}</span><span class="live">Live</span></div><div class="n num">${v}</div><b>${esc(l)}</b><small>${esc(s)}</small></div>`).join("")}</div></div>`;
      }
      function teamTable(){
        const avatarCols=["var(--slate)","var(--violet)","var(--coral)","#2f8f88","var(--gold)"];
        return `<div class="card tight"><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Cleared revenue</th><th>Paid clients</th><th>vs Target</th></tr></thead><tbody>${B.team.map((t,i)=>{
          const ini=t.name.split(/\s+/).map(x=>x[0]).slice(0,2).join("");
          const hasT=(t.target||0)>0;const att=hasT?(t.actual||0)/t.target:null;
          const st=att==null?"":att>=1?"green":att>=0.7?"amber":"red";
          const vs=att==null?'<span style="color:var(--muted)">no target set</span>'
            :`<span class="mini-track"><div style="width:${clamp(att*100,0,100)}%;background:${scol(st)}"></div></span> <b class="num" style="font-size:11.5px;color:${scol(st)}">${pct(att,0)}</b><div style="font-size:10px;color:var(--muted)">of ${kMoney(t.target)}</div>`;
          return `<tr class="${t.me?"me":""}"><td><div class="prow"><span class="a"${t.me?"":` style="background:${avatarCols[i%avatarCols.length]}"`}>${ini}</span><div><b>${esc(t.name)}${t.me?" · you":""}</b><span>${esc(t.title||"BDE")}</span></div></div></td><td class="num">${kMoney(t.actual||0)}</td><td class="num">${t.clients!=null?nf.format(t.clients):"—"}</td><td>${vs}</td></tr>`;}).join("")}</tbody></table></div></div>`;
      }

      /* ---------- views ---------- */
      function vCommand(){
        const ps=pace();
        return `${strategyStrip()}
          ${realOutcomes()}
          <section class="hero">
            ${targetsCard()}
            ${progressCard()}
          </section>
          <div class="card"><div class="chead"><h4>My portfolio</h4><span class="pace-pill ${ps.status==="green"?"pg":ps.status==="amber"?"pa":"pr"}"><span class="dot"></span>${ps.label} · pace ${pct(ps.ratio,0)}</span></div>${kpiBlock()}</div>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Revenue this month vs target</h4><span class="chip jade">${kMoney(B.actual)} cleared</span></div>${trendSVG()}<div style="font-size:11.5px;color:var(--muted);margin-top:10px"><b style="color:var(--brand)">Line</b> = cleared revenue building up day-by-day this month (hover a day for that day's amount) · <b>Target</b> line = your monthly goal.</div></div>
            ${commissionMini()}
          </section>
          <section class="grid-2">${actionsCard()}${driversCard()}</section>
          <div class="section-tag"><h3>Your team</h3><span>Your department, ranked by cleared revenue · each measured against their own target</span><div class="rule"></div></div>
          ${teamTable()}`;
      }

      function vPipeline(){
        const fmax=Math.max(1,...B.funnel.map(f=>f[1]));const smax=Math.max(1,...B.sources.map(s=>s[1]));
        const dept=(B.dept||"").toLowerCase();const isCorp=/corporate/.test(dept);
        const nChat=(B.unreadCount||0), nQuiet=(B.quietLeads||[]).length, nProm=(B.promises||[]).length;
        const stale=[];
        if(nChat>0) stale.push({t:`${nChat} WhatsApp chat${nChat>1?"s":""} escalated to you`,p:"Chats routed to you for a human reply — respond today.",c:"red",act:"chats"});
        if(nProm>0) stale.push({t:`${nProm} client${nProm>1?"s":""} promised to pay in chat`,p:"They said they'd pay on WhatsApp — confirm and close.",c:"amber",act:"promises"});
        if(nQuiet>0) stale.push({t:`${nQuiet} unpaid lead${nQuiet>1?"s":""} gone quiet`,p:"Registered, not yet paid, no recent contact — follow up.",c:"amber",act:"quiet"});
        if(isCorp) stale.push({t:`Proposals awaiting a confirmed review date`,p:"Corporate proposals need a scheduled review.",c:"amber",act:""});
        // Conversion-quality — derived from this BDE's real numbers.
        const quality=[];
        const leadN=(B.totalLeads||B.totalRegs||0);const convR=leadN?((B.paidClients||0)/leadN):0;
        if(leadN>5&&convR<0.4) quality.push(`Low conversion — only ${pct(convR,0)} of ${nf.format(leadN)} leads have paid`);
        if(B.collection!=null&&B.collection>0&&B.collection<0.7) quality.push(`Collection at ${pct(B.collection,0)} — fees expected but not fully in`);
        if((B.quietLeads||[]).length>0) quality.push(`${(B.quietLeads||[]).length} unpaid leads gone quiet — pipeline cooling`);
        if((B.unreadCount||0)>3) quality.push(`${B.unreadCount} escalated chats unanswered — slow replies cost conversions`);
        if((B.sources||[]).length===1) quality.push(`All leads from one channel (${B.sources[0][0]}) — diversify sources`);
        // Cross-SBU / expansion — organizations sending multiple people (real, from registrations).
        const cross=(B.repeatOrgs||[]).map(o=>`${o.org} — ${o.n} sign-ups`);
        const showPriorities=/digital/i.test(B.dept||"");
        return `
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Acquisition &amp; conversion funnel</h4><span class="chip slate">Live funnel</span></div><div class="funnel">${B.funnel.map(([l,n],i)=>`<div class="fr"><label>${esc(l)}</label><div class="fbar"><div style="width:${Math.max(9,n/fmax*100)}%">${nf.format(n)}</div></div><span class="cv">${i?(B.funnel[i-1][1]>0?Math.round(n/B.funnel[i-1][1]*100)+"%":"—"):"100%"}</span></div>`).join("")}</div></div>
            <div class="card"><div class="chead"><h4>Lead-source contribution</h4><span class="chip slate">Leads by source</span></div>${B.sources.length?B.sources.map(([n,v])=>`<div class="src"><label>${esc(n)}</label><div class="sb"><div style="width:${v/smax*100}%"></div></div><b>${nf.format(v)}</b></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:0">No lead-source data yet.</p>'}</div>
          </section>
          ${showPriorities ? `<div class="section-tag"><h3>Priority opportunity control</h3><span>No important opportunity may exist only in email, WhatsApp, a notebook or memory</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>Account / opportunity</th><th>Stage</th><th>Value / volume</th><th>Next action</th><th>Due</th></tr></thead><tbody>${B.priorities.map(r=>{const dc=r[4]==="Today"?"hot":r[4]==="Tomorrow"?"soon":"cool";return `<tr><td><b>${esc(r[0])}</b></td><td><span class="stage-chip">${esc(r[1])}</span></td><td class="num">${esc(r[2])}</td><td>${esc(r[3])}</td><td><span class="duec ${dc}">${esc(r[4])}</span></td></tr>`;}).join("")}</tbody></table></div></div>` : ""}
          <section class="grid-3">
            <div class="card"><div class="chead"><h4>Action alerts</h4><span class="chip ${stale.length?"coral":"jade"}">${stale.length?stale.length+" flagged":"all clear"}</span></div><div class="list">${stale.length?stale.map(a=>`<div class="arow"><span class="pd ${a.c}"></span><div><b>${esc(a.t)}</b><p>${esc(a.p)}</p></div>${a.act?`<span class="abtn hot" data-alert="${a.act}" style="cursor:pointer">Open</span>`:""}</div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:6px 2px">Nothing needs action right now — no unread chats or quiet unpaid leads.</p>'}</div></div>
            <div class="card"><div class="chead"><h4>Conversion-quality signals</h4><span class="chip ${quality.length?"amber":"jade"}">${quality.length?quality.length+" to review":"healthy"}</span></div><div class="list">${quality.length?quality.map(x=>`<div class="arow"><span class="pd amber"></span><div><b>${esc(x)}</b><p>Derived from your live conversion, collection and response numbers.</p></div></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:6px 2px">Conversion, collection and response times all look healthy.</p>'}</div></div>
            <div class="card"><div class="chead"><h4>Expansion opportunities</h4><span class="chip slate">${cross.length} org${cross.length===1?"":"s"}</span></div><div class="list">${cross.length?cross.map(x=>`<div class="arow"><span class="pd blue"></span><div><b>${esc(x)}</b><p>Multiple sign-ups from one organization — pitch a corporate/bulk deal.</p></div></div>`).join(""):'<p style="color:var(--muted);font-size:12.5px;margin:6px 2px">No organizations with repeat sign-ups yet.</p>'}</div></div>
          </section>`;
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
              <div class="nextstep"><b>How it's earned:</b> individual-tier commission, 3% corporate setup, 2.5% maintenance and a KES 25,000 balanced bonus when both individual and corporate targets are met.</div>
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
        const fields=[
          ["Daily revenue target","number",Math.round(B.target/p.working)],
          ["Actual cleared revenue today","number",Math.round(B.actual/p.elapsed)],
          ["New enquiries / accounts","number",38],
          ["Qualified leads","number",19],
          ["Calls / meaningful conversations","number",14],
          ["Demos / sessions run","number",3],
          ["Proposals / payment links sent","number",7],
          ["Payments / activations today","number",5],
          ["Top opportunities and next actions","textarea","1. Priority organization — decision call tomorrow\n2. Payment promise — follow up 10:00 AM\n3. Demo prospect — send tailored agenda"],
          ["Marketing / automation / product observation","textarea","Best source, weakest source, an AI issue, a broken link, onboarding friction or a message that converted."],
          ["What worked and what prevented conversion","textarea","Record evidence and learning, not general narration."],
          ["Support required and tomorrow's plan","textarea","Named support owner, deadline, top five prospects and tomorrow's target."]
        ];
        const fieldHTML=f=>`<div class="field ${f[1]==="textarea"?"span2":""}"><label>${esc(f[0])}</label>${f[1]==="textarea"?`<textarea data-label="${esc(f[0])}">${esc(f[2])}</textarea>`:`<input data-label="${esc(f[0])}" type="number" value="${esc(f[2])}">`}</div>`;
        const nums=fields.filter(f=>f[1]==="number").map(fieldHTML).join("");
        const texts=fields.filter(f=>f[1]==="textarea").map(fieldHTML).join("");
        return `
          <div class="card"><div class="chead"><h4>BDE daily execution report</h4><span class="chip jade">Auto-prefilled</span></div>
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
        const score=[["Revenue / qualifying volume",35],["Pipeline and conversion",20],["CRM and forecast quality",15],["Strategic execution",10],["Marketing / channel learning",10],["Client experience and reporting",10]];
        return `
          <div class="card"><div class="chead"><h4>Role mandate</h4><span class="chip jade">Personal execution</span></div><div class="motiv green"><b>${esc(B.mandate)}</b><br>${esc(B.mandateText)}</div></div>
          <div class="card"><div class="chead"><h4>Non-negotiable operating principles</h4></div><div class="principles">${B.principles.map(([a,b])=>`<div class="principle"><b>${esc(a)}</b><p>${esc(b)}</p></div>`).join("")}</div></div>
          <section class="grid-2">
            <div class="card"><div class="chead"><h4>Daily operating rhythm</h4></div><div class="timeline">${B.dailyRhythm.map(([t,x])=>`<div class="time-row"><time>${esc(t)}</time><div>${esc(x)}</div></div>`).join("")}</div></div>
            <div class="card"><div class="chead"><h4>Performance scorecard</h4></div><div class="scorecard">${score.map(([n,w])=>`<div class="scr"><label>${esc(n)}</label><div class="sb"><div style="width:${w/35*100}%"></div></div><b>${w}%</b></div>`).join("")}</div></div>
          </section>
          <section class="grid-3">
            <div class="card"><div class="chead"><h4>Green response</h4><span class="chip jade">At / above pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Protect quality, collections and client experience; pursue stretch opportunities and share winning practices.</p></div>
            <div class="card"><div class="chead"><h4>Amber response</h4><span class="chip amber">Near pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Agree corrective action within 24 hours, intensify senior support and concentrate on the nearest commercial next steps.</p></div>
            <div class="card"><div class="chead"><h4>Red response</h4><span class="chip coral">Below pace</span></div><p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.55">Create a quantified recovery plan, monitor daily and escalate decisions or resources before the gap becomes irreversible.</p></div>
          </section>`;
      }

      /* ---------- field visit tracker (visuals; real data wired later) ---------- */
      const VISIT_PRODUCTS=["Eval360","360 Appraisal","Data Analysis","M&E System"];
      const VISIT_OUTCOMES=[["visited","Visited","slate"],["interested","Interested","amber"],["registered","Registered","jade"]];
      function visitStats(){
        const v=B.visits||[];
        const visited=v.length;
        const interested=v.filter(x=>x.outcome==="interested").length;
        const registered=v.filter(x=>x.outcome==="registered").length;
        return {visited,interested,registered,conv:visited?registered/visited:0};
      }
      function vVisits(){
        const s=visitStats();
        const outChip=o=>{const m=VISIT_OUTCOMES.find(x=>x[0]===o)||["","",""];return `<span class="chip ${m[2]}">${m[1]}</span>`;};
        const kpis=[
          ["Clients visited",nf.format(s.visited),"of "+nf.format(B.visitGoal)+" monthly goal","var(--slate)"],
          ["Interested",nf.format(s.interested),"in active follow-up","var(--amber)"],
          ["Registered",nf.format(s.registered),"auto-confirmed from payments","var(--jade)"],
          ["Conversion rate",pct(s.conv,0),"visited → registered","var(--brand)"]
        ];
        const kpiRow=`<div class="kpis">${kpis.map(([l,v,m,a])=>`<div class="kpi" style="--acc:${a}"><div class="lab">${l}</div><div class="val num">${v}</div><div class="meta">${m}</div></div>`).join("")}</div>`;
        const rows=(B.visits||[]).slice().sort((a,b)=>b.date.localeCompare(a.date)).map(x=>`<tr>
              <td class="num">${esc(fmtDate(x.date))}</td>
              <td><b>${esc(x.client)}</b><span style="display:block;font-size:11px;color:var(--muted)">${esc(x.org)}</span></td>
              <td>${esc(x.location)}</td>
              <td><span class="stage-chip">${esc(x.product)}</span></td>
              <td>${outChip(x.outcome)}</td>
              <td class="num">${x.value?kMoney(x.value):"&#8212;"}</td></tr>`).join("");
        const prodOpts=VISIT_PRODUCTS.map(p=>`<option>${esc(p)}</option>`).join("");
        const outOpts=VISIT_OUTCOMES.map(o=>`<option value="${o[0]}">${o[1]}</option>`).join("");
        return `
          <div class="section-tag"><h3>Field visit tracker</h3><span>Log every client you visit in the field — visits roll up to your department (BDO) and the BDM</span><div class="rule"></div></div>
          ${kpiRow}
          <div class="card"><div class="chead"><h4>Log a field visit</h4><span class="chip slate">Quick entry</span></div>
            <div class="form-grid">
              <div class="field"><label>Client / contact person</label><input id="vf_client" type="text" placeholder="e.g. Grace Wanjiru"></div>
              <div class="field"><label>Organization</label><input id="vf_org" type="text" placeholder="e.g. Nairobi Women's SACCO"></div>
              <div class="field"><label>Location / area</label><input id="vf_loc" type="text" placeholder="e.g. Nairobi CBD"></div>
              <div class="field"><label>Product of interest</label><select id="vf_prod">${prodOpts}</select></div>
              <div class="field"><label>Outcome</label><select id="vf_out">${outOpts}</select></div>
              <div class="field"><label>Potential value (KES)</label><input id="vf_val" type="number" min="0" placeholder="0"></div>
              <div class="field"><label>Visit date</label><input id="vf_date" type="date" value="${todayStr()}"></div>
              <div class="field span2"><label>Notes</label><textarea id="vf_notes" placeholder="What was discussed, the next step, any blockers…"></textarea></div>
            </div>
            <div class="report-actions"><button class="tbtn solid" id="vf_save" type="button">Log visit</button><button class="tbtn" id="vf_clear" type="button">Clear</button></div>
            <div style="font-size:11.5px;color:var(--muted);margin-top:8px"><b>Tip:</b> log visits the same day while the details are fresh. "Registered" is auto-confirmed against Finance-verified payments once real data is connected — you only log the visit. Every field visit is visible to your BDO and the BDM.</div>
          </div>
          <div class="section-tag"><h3>My recent field visits</h3><span>${nf.format((B.visits||[]).length)} logged</span><div class="rule"></div></div>
          <div class="card tight"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Client / organization</th><th>Location</th><th>Product</th><th>Outcome</th><th>Potential value</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
      }
      function bindVisits(){
        const save=el("vf_save"); if(!save)return;
        save.addEventListener("click",()=>{
          const client=el("vf_client").value.trim();
          if(!client){el("vf_client").focus();return;}
          B.visits.push({
            date:el("vf_date").value||todayStr(),
            client,
            org:el("vf_org").value.trim(),
            location:el("vf_loc").value.trim(),
            product:el("vf_prod").value,
            outcome:el("vf_out").value,
            value:parseInt(el("vf_val").value,10)||0,
            notes:el("vf_notes").value.trim()
          });
          render();
        });
        const clr=el("vf_clear");
        if(clr)clr.addEventListener("click",()=>["vf_client","vf_org","vf_loc","vf_val","vf_notes"].forEach(id=>{const e=el(id);if(e)e.value="";}));
      }

      // "Your targets" card (sits beside Progress to target). Grouped by product, no nested cards.
      function targetsCard(){
        const rows=B.targets||[];
        if(!rows.length){
          return `<div class="card"><div class="chead"><h4>Your targets</h4><span class="chip slate">None set</span></div><p style="color:var(--muted);font-size:12.5px;margin:0">No targets set yet — an admin can add them under <b>BDE Targets</b>.</p></div>`;
        }
        const fmtV=(v,unit)=>unit==="count"?nf.format(Math.round(v||0)):kMoney(v||0);
        const order=[], groups={};
        rows.forEach(r=>{ const k=(r.product&&r.product.trim())?r.product:r.metric_label; if(!groups[k]){groups[k]=[];order.push(k);} groups[k].push(r); });
        const metricLine=r=>{
          const isCount=r.unit==="count";
          const tcls=isCount?"tl-count":"tl-money";
          const cword=r.metric==="active_users"?"Users":r.metric==="paid_staff"?"Staff":(r.metric==="corporate_clients"||/client/i.test(r.metric_label))?"Clients":"Number";
          const chip=isCount?`<span class="tchip tchip-count"># ${cword}</span>`:"";
          const t70=r.threshold_pct!=null?`<div class="tlevel tl-qual ${tcls}"><span class="tl-cap">${(+r.threshold_pct)}% qualifying</span><b>${fmtV(r.threshold_value,r.unit)}</b></div>`:"";
          const levels=`<div class="tlevels"><div class="tlevel tl-full ${tcls}"><span class="tl-cap">100% target</span><b>${fmtV(r.target,r.unit)}</b></div>${t70}</div>`;
          // achieved/progress lives in the portfolio + Progress-to-target box, so keep the target card
          // to just the goal structure (100% + qualifying line).
          return `<div class="tmetric"><div class="tmetric-h"><span class="tmetric-l">${esc(r.metric_label)}</span>${chip}</div>${levels}</div>`;
        };
        const body=order.map(k=>`<div class="tgroup"><div class="tgroup-h">${esc(k)}</div>${groups[k].map(metricLine).join("")}</div>`).join("");
        return `<div class="card"><div class="chead"><h4>Your targets</h4><span class="chip slate">Monthly</span></div>${body}</div>`;
      }
      function openAlertModal(act){
        let title="", list=[];
        if(act==="chats"){title="Chats escalated to you";list=(B.unreadChats||[]).map(c=>({a:c.name||c.phone,b:c.phone||"",w:c.when||"",cid:c.cid}));}
        else if(act==="promises"){title="Clients who promised to pay";list=(B.promises||[]).map(c=>({a:c.name||c.phone,b:c.phone||"",w:c.when||"",cid:c.cid}));}
        else if(act==="quiet"){title="Unpaid leads gone quiet";list=(B.quietLeads||[]).map(c=>({a:c.name,b:[c.phone,c.prog].filter(Boolean).join(" · "),w:c.when||""}));}
        else return;
        const rows=list.length?list.map(r=>{
          const meta=`<div><b>${esc(r.a)}</b>${(r.b||r.w)?`<small>${esc([r.b,r.w].filter(Boolean).join(" · "))}</small>`:""}</div>`;
          const right=r.cid?`<a class="abtn hot" href="wa_thread.php?id=${r.cid}" target="_blank" rel="noopener" style="text-decoration:none;white-space:nowrap">Open chat →</a>`:`<span class="amwhen">${esc(r.w)}</span>`;
          return `<div class="amrow">${meta}${right}</div>`;}).join(""):'<p style="color:#94a3b8;padding:14px">Nothing here right now.</p>';
        const ov=document.createElement("div");ov.className="amodal-ov";
        ov.innerHTML=`<div class="amodal"><div class="amodal-h"><h4>${esc(title)} (${list.length})</h4><button class="amodal-x" aria-label="Close">✕</button></div><div class="amodal-b">${rows}</div></div>`;
        ov.addEventListener("click",e=>{if(e.target===ov||e.target.classList.contains("amodal-x"))ov.remove();});
        document.addEventListener("keydown",function esc2(e){if(e.key==="Escape"){ov.remove();document.removeEventListener("keydown",esc2);}});
        document.body.appendChild(ov);
      }
      function render(){
        const v=state.view;
        el("workspace").innerHTML=v==="command"?vCommand():v==="pipeline"?vPipeline():v==="visits"?vVisits():v==="commission"?vCommission():v==="report"?vReport():vStrategy();
        if(v==="report")bindReport();
        if(v==="visits")bindVisits();
      }
      function bindReport(){
        el("genReport").addEventListener("click",genReport);
        el("dlReport").addEventListener("click",()=>{genReport();const t=el("reportPreview").textContent;const b=new Blob([t],{type:"text/plain"});const a=document.createElement("a");a.href=URL.createObjectURL(b);a.download="Vantage_BDE_"+period().label.replace(/\s+/g,"_")+"_Report.txt";a.click();URL.revokeObjectURL(a.href);});
        el("clrReport").addEventListener("click",()=>root.querySelectorAll("#reportForm textarea").forEach(x=>x.value=""));
      }
      function genReport(){
        const lines=["VANTAGE AFRICA — BDE DAILY REPORT","Period: "+period().label,"Consultant: "+B.name+" | "+B.title+" · "+B.dept,""];
        root.querySelectorAll("#reportForm input,#reportForm textarea").forEach(x=>lines.push(x.dataset.label+": "+(x.value.trim()||"—")));
        const att=B.target>0?B.actual/B.target:0;lines.push("");lines.push("Dashboard position: "+kMoney(B.actual)+" cleared against "+kMoney(B.target)+" ("+pct(att)+").");
        lines.push("Outstanding pipeline: "+kMoney(B.pipelineKes||0)+". Collection: "+pct(B.collection,0)+".");
        lines.push("Commission (eligible): "+kMoney(B.commissionKes||0)+".");
        lines.push("All figures subject to CRM evidence and Finance verification.");
        el("reportPreview").textContent=lines.join("\n");
      }

      el("periodSelect").innerHTML=periods.map((p,i)=>`<option value="${i}" ${i===state.p?"selected":""}>${p.label}</option>`).join("");
      el("periodSelect").addEventListener("change",e=>{state.p=+e.target.value;render();});
      // Period presets → reload with the preset's range; "Custom…" reveals the date inputs.
      var pp=el("periodPreset");
      if(pp) pp.addEventListener("change",function(){
        if(this.value==="custom"){var cw=el("customWrap"); if(cw) cw.style.display=""; var fd=el("fromDate"); if(fd) fd.focus(); return;}
        var opt=this.options[this.selectedIndex], p=new URLSearchParams(location.search);
        p.set("from",opt.getAttribute("data-from")); p.set("to",opt.getAttribute("data-to"));
        location.search=p.toString();
      });
      // Custom From/To → reload with ?from&to (server re-scopes the real metrics), keeping ?as=
      ["fromDate","toDate"].forEach(function(id){var d=el(id); if(d) d.addEventListener("change",function(){
        var p=new URLSearchParams(location.search), f=el("fromDate").value, t=el("toDate").value;
        if(f) p.set("from",f); else p.delete("from"); if(t) p.set("to",t); else p.delete("to");
        location.search=p.toString();
      });});
      // Admin "View as" → reload previewing that BDE (?as=<id>), keeping the date range.
      var va=el("viewAs");
      if(va) va.addEventListener("change",function(){var p=new URLSearchParams(location.search);p.set("as",this.value);location.search=p.toString();});
      // Action-alert "Open" buttons → open the list modal (delegated, survives re-render).
      root.addEventListener("click",function(e){var b=e.target.closest("[data-alert]");if(b){e.preventDefault();openAlertModal(b.getAttribute("data-alert"));}});
      root.querySelectorAll(".tab[data-v]").forEach(a=>a.addEventListener("click",()=>{root.querySelectorAll(".tab").forEach(x=>x.classList.remove("active"));a.classList.add("active");state.view=a.dataset.v;try{history.replaceState(null,"","#"+a.dataset.v);}catch(e){location.hash=a.dataset.v;}render();}));
      // Restore the active tab on load (so a reload / browser-back keeps the tab you were on).
      root.querySelectorAll(".tab").forEach(x=>x.classList.toggle("active",x.dataset.v===state.view));
      el("themeBtn").addEventListener("click",()=>{const dark=root.classList.toggle("theme-dark");el("themeBtn").textContent=dark?"☀ Light":"🌙 Dark";});

      render();
    })();
    </script>
  </div>
</section>

<?php require_once 'footer.php'; ?>
