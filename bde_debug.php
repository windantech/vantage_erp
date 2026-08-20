<?php
/**
 * bde_debug.php — admin-only diagnostic for BDE dashboard target/team matching.
 * Usage: bde_debug.php?as=<registered_users.id>
 * Prints how the viewed BDE's department resolves and which targets/team members match, so we can
 * see exactly why targets or the team table are empty. Read-only.
 */
session_start();
require_once 'header.php';
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }
if (!isset($role) || !is_array($role) || !in_array(777, $role)) { http_response_code(403); exit('Forbidden — admin only.'); }

$id = (int) ($_GET['as'] ?? ($_SESSION['login_id'] ?? 0));
$from = isset($_GET['from']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['from']) : date('Y-m-01');
$to   = isset($_GET['to'])   ? preg_replace('/[^0-9\-]/', '', (string) $_GET['to'])   : date('Y-m-d');
echo '<pre style="white-space:pre-wrap;font:13px/1.6 ui-monospace,Menlo,Consolas,monospace;padding:16px 20px;background:#fff;color:#0e1726">';
echo "BDE DEBUG — registered_users.id = $id\n=====================================\n\n";

$ru = null;
$r = @mysqli_query($conn, "SELECT id, fullname, email, status, user_type, department_id, staff_id FROM registered_users WHERE id = $id LIMIT 1");
if ($r && ($ru = mysqli_fetch_assoc($r))) {
    echo "-- registered_users row --\n";
    foreach ($ru as $k => $v) { echo "  " . str_pad($k, 15) . ": " . (string) $v . "\n"; }
    $did = (int) $ru['department_id'];
    $dr = @mysqli_query($conn, "SELECT department_name FROM departments WHERE id = $did LIMIT 1");
    echo "  " . str_pad('-> dept name', 15) . ": " . ($dr && ($d = mysqli_fetch_assoc($dr)) ? $d['department_name'] : '(department_id ' . $did . ' not found in departments)') . "\n";
} else {
    echo "!! No registered_users row for id $id\n";
}

if ($ru) {
    echo "\n-- staff record matched by email / name (where department may actually live) --\n";
    $em = mysqli_real_escape_string($conn, (string) ($ru['email'] ?? ''));
    $nm = mysqli_real_escape_string($conn, (string) ($ru['fullname'] ?? ''));
    $r = @mysqli_query($conn, "SELECT id, full_name, email, department_id, onboarding_status FROM staff WHERE email = '$em' OR full_name LIKE '%$nm%' LIMIT 8");
    $anyS = false;
    while ($r && ($sr = mysqli_fetch_assoc($r))) {
        $anyS = true; $sd = (int) $sr['department_id']; $dn = '';
        $dq = @mysqli_query($conn, "SELECT department_name FROM departments WHERE id = $sd LIMIT 1");
        if ($dq && ($d = mysqli_fetch_assoc($dq))) { $dn = $d['department_name']; }
        echo "  staff#{$sr['id']} \"{$sr['full_name']}\" email={$sr['email']} dept={$sr['department_id']} ($dn) status={$sr['onboarding_status']}\n";
    }
    if (!$anyS) { echo "  (no staff row matches this email or name)\n"; }
}

echo "\n-- all departments --\n";
$r = @mysqli_query($conn, "SELECT id, department_name FROM departments ORDER BY id");
$anyD = false;
while ($r && ($d = mysqli_fetch_assoc($r))) { $anyD = true; echo "  {$d['id']} = {$d['department_name']}\n"; }
if (!$anyD) { echo "  (none)\n"; }

echo "\n-- ALL department-scoped targets in bde_targets --\n";
$r = @mysqli_query($conn, "SELECT scope_ref, scope_label, product, metric, target_value FROM bde_targets WHERE scope_type='department' ORDER BY scope_ref, id");
$anyT = false;
while ($r && ($t = mysqli_fetch_assoc($r))) { $anyT = true; echo "  dept#{$t['scope_ref']} (\"{$t['scope_label']}\") | " . ($t['product'] ?: '—') . " | {$t['metric']} = {$t['target_value']}\n"; }
if (!$anyT) { echo "  (none)\n"; }

echo "\n-- user-scoped targets for THIS id ($id) --\n";
$r = @mysqli_query($conn, "SELECT scope_label, product, metric, target_value FROM bde_targets WHERE scope_type='user' AND scope_ref='$id' ORDER BY id");
$anyU = false;
while ($r && ($t = mysqli_fetch_assoc($r))) { $anyU = true; echo "  \"{$t['scope_label']}\" | " . ($t['product'] ?: '—') . " | {$t['metric']} = {$t['target_value']}\n"; }
if (!$anyU) { echo "  (none)\n"; }

if ($ru) {
    $did = (int) $ru['department_id'];
    echo "\n-- team: registered_users with department_id = $did (status=1) --\n";
    $r = @mysqli_query($conn, "SELECT id, fullname, status FROM registered_users WHERE department_id = $did AND status = 1 ORDER BY fullname");
    $n = 0;
    while ($r && ($m = mysqli_fetch_assoc($r))) { $n++; echo "  #{$m['id']} {$m['fullname']}\n"; }
    echo "  => $n member(s)\n";

    echo "\n-- accounts sharing this name (duplicate check) --\n";
    $nm = mysqli_real_escape_string($conn, (string) $ru['fullname']);
    $r = @mysqli_query($conn, "SELECT id, fullname, department_id, status FROM registered_users WHERE fullname LIKE '%$nm%' ORDER BY id");
    while ($r && ($m = mysqli_fetch_assoc($r))) { echo "  #{$m['id']} \"{$m['fullname']}\" dept={$m['department_id']} status={$m['status']}\n"; }
}

if ($ru) {
    $id2 = (int) $ru['id'];
    echo "\n-- lead sources: their ENQUIRIES (source_id → enquiry_sources) --\n";
    $r = @mysqli_query($conn, "SELECT COALESCE(es.name, CONCAT('id=', e.source_id)) src, COUNT(*) n
        FROM enquiries e LEFT JOIN enquiry_sources es ON es.id = e.source_id
        WHERE e.assigned_to = $id2 GROUP BY e.source_id ORDER BY n DESC");
    $any = false; while ($r && ($x = mysqli_fetch_assoc($r))) { $any = true; echo "  {$x['src']}: {$x['n']}\n"; }
    if (!$any) { echo "  (no enquiries assigned to them)\n"; }

    echo "\n-- lead sources: their REGISTER rows (register.source) --\n";
    $iq = @mysqli_query($conn, "SELECT intake_id FROM intake WHERE assigned_to = $id2");
    $ii = []; while ($iq && ($x = mysqli_fetch_assoc($iq))) { $ii[] = "'" . mysqli_real_escape_string($conn, (string) $x['intake_id']) . "'"; }
    if (!empty($ii)) {
        $in = implode(',', $ii);
        $r = @mysqli_query($conn, "SELECT COALESCE(es.name, CONCAT('code=', r.source)) src, COUNT(*) n
            FROM register r LEFT JOIN enquiry_sources es ON es.id = r.source
            WHERE r.intake_id IN ($in) GROUP BY r.source ORDER BY n DESC");
        $any = false; while ($r && ($x = mysqli_fetch_assoc($r))) { $any = true; echo "  {$x['src']}: {$x['n']}\n"; }
        if (!$any) { echo "  (no register rows)\n"; }
    } else { echo "  (no intakes assigned)\n"; }

    echo "\n-- lead sources: WhatsApp chats assigned to them (wa_conversations) --\n";
    $r = @mysqli_query($conn, "SELECT COUNT(*) n, MIN(created_at) first, MAX(created_at) last FROM wa_conversations WHERE assigned_user_id = $id2");
    if ($r && ($x = mysqli_fetch_assoc($r))) { echo "  total: {$x['n']}" . ($x['n'] > 0 ? " (from {$x['first']} to {$x['last']})" : '') . "\n"; }

    echo "\n-- enquiry_sources lookup table --\n";
    $r = @mysqli_query($conn, "SELECT id, name FROM enquiry_sources ORDER BY id");
    while ($r && ($x = mysqli_fetch_assoc($r))) { echo "  {$x['id']} = {$x['name']}\n"; }
}

if ($ru) {
    $id2 = (int) $ru['id'];
    echo "\n\n=== EVENTS / TRAININGS (how International + Corporate revenue is attributed) ===\n";
    echo "period: $from .. $to\n";

    echo "\n-- (A) events the DASHBOARD attributes to this BDE (Event.assigned_to = $id2, current logic) --\n";
    $r = @mysqli_query($conn, "SELECT event_id, event_title, assigned_to, status FROM Event WHERE assigned_to = $id2 ORDER BY event_id DESC LIMIT 30");
    $codeEv = []; while ($r && ($e = mysqli_fetch_assoc($r))) { $codeEv[(string) $e['event_id']] = $e; }
    if (!$codeEv) { echo "  (none — the dashboard attributes NO events to this BDE)\n"; }
    foreach ($codeEv as $e) { echo "  event#{$e['event_id']} \"" . substr((string) $e['event_title'], 0, 55) . "\" assigned_to='{$e['assigned_to']}' status={$e['status']}\n"; }

    echo "\n-- (B) events a ROBUST match finds (FIND_IN_SET — handles '6,' and multi-assignee lists) --\n";
    $r = @mysqli_query($conn, "SELECT event_id, event_title, assigned_to, status FROM Event WHERE FIND_IN_SET('$id2', REPLACE(assigned_to,' ','')) > 0 ORDER BY event_id DESC LIMIT 30");
    $robEv = []; while ($r && ($e = mysqli_fetch_assoc($r))) { $robEv[(string) $e['event_id']] = $e; }
    if (!$robEv) { echo "  (none)\n"; }
    foreach ($robEv as $e) { echo "  event#{$e['event_id']} \"" . substr((string) $e['event_title'], 0, 55) . "\" assigned_to='{$e['assigned_to']}' status={$e['status']}\n"; }
    if (count($robEv) > count($codeEv)) { echo "  !! ROBUST match finds MORE events than the dashboard — attribution is MISSING some (assigned_to is a comma-list varchar).\n"; }

    $allEv = $codeEv + $robEv;
    foreach ($allEv as $evid => $e) {
        $evidE = mysqli_real_escape_string($conn, (string) $evid);
        echo "\n-- tickets for event#$evid \"" . substr((string) $e['event_title'], 0, 45) . "\" --\n";
        $r = @mysqli_query($conn, "SELECT status, COUNT(*) n, SUM(CAST(amount AS DECIMAL(12,2))) amt, MIN(date_sent) mn, MAX(date_sent) mx FROM ticket_congress WHERE event_id = '$evidE' GROUP BY status ORDER BY status");
        $any = false; while ($r && ($t = mysqli_fetch_assoc($r))) { $any = true; echo "  status={$t['status']}: {$t['n']} tickets, amount=" . number_format((float) $t['amt']) . " ({$t['mn']} .. {$t['mx']})\n"; }
        if (!$any) { echo "  (no ticket_congress rows for this event)\n"; }
        $r = @mysqli_query($conn, "SELECT COUNT(*) n, SUM(status=2) paid, SUM(CASE WHEN status=2 THEN CAST(amount AS DECIMAL(12,2)) ELSE 0 END) paidamt FROM ticket_congress WHERE event_id='$evidE' AND date_sent BETWEEN '$from 00:00:00' AND '$to 23:59:59'");
        if ($r && ($t = mysqli_fetch_assoc($r))) { echo "  -> in period $from..$to: {$t['n']} tickets, {$t['paid']} PAID (status=2), paid amount=" . number_format((float) $t['paidamt']) . "\n"; }
    }

    echo "\n-- (C) tickets assigned DIRECTLY to this BDE (ticket_congress.assigned_to = $id2) — the dashboard does NOT read this column --\n";
    $r = @mysqli_query($conn, "SELECT COUNT(*) n, SUM(status=2) paid, MIN(date_sent) mn, MAX(date_sent) mx FROM ticket_congress WHERE assigned_to = $id2");
    if ($r && ($t = mysqli_fetch_assoc($r))) { echo "  total {$t['n']} tickets, {$t['paid']} paid, ({$t['mn']} .. {$t['mx']})\n  If this is non-zero while (A) is empty, trainings are attributed at the TICKET level and the dashboard is missing them.\n"; }

    echo "\n-- (D) recent trainings (any event with ticket activity in the last 30 days) + who they're assigned to --\n";
    $r = @mysqli_query($conn, "SELECT e.event_id, e.event_title, e.assigned_to, COUNT(tc.id) tickets, SUM(tc.status=2) paid, MAX(tc.date_sent) last_ticket
        FROM Event e JOIN ticket_congress tc ON tc.event_id = e.event_id
        WHERE tc.date_sent >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY e.event_id ORDER BY last_ticket DESC LIMIT 15");
    $any = false; while ($r && ($e = mysqli_fetch_assoc($r))) { $any = true; echo "  event#{$e['event_id']} \"" . substr((string) $e['event_title'], 0, 45) . "\" assigned_to='{$e['assigned_to']}' — {$e['tickets']} tickets, {$e['paid']} paid, last {$e['last_ticket']}\n"; }
    if (!$any) { echo "  (no training/ticket activity in the last 30 days)\n"; }
}

if ($ru) {
    $id2 = (int) $ru['id'];
    echo "\n\n=== WHATSAPP / PRODUCT ASSIGNMENTS (is this person a rep of a specific course/training?) ===\n";

    echo "\n-- (E1) COURSES they are a rep of (course.assigned_to comma-list) --\n";
    $soleCourse = 0; $coCourse = 0;
    $r = @mysqli_query($conn, "SELECT course_id, course, assigned_to FROM course WHERE FIND_IN_SET('$id2', REPLACE(assigned_to,' ','')) > 0 ORDER BY id DESC LIMIT 40");
    $anyC = false;
    while ($r && ($c = mysqli_fetch_assoc($r))) {
        $anyC = true;
        $list = array_values(array_filter(array_map('trim', explode(',', (string) $c['assigned_to'])), 'strlen'));
        $sole = (count($list) === 1);
        if ($sole) { $soleCourse++; } else { $coCourse++; }
        echo "  course {$c['course_id']} \"" . substr((string) $c['course'], 0, 50) . "\" assigned_to='{$c['assigned_to']}' — " . ($sole ? "SOLE rep" : "CO-rep (" . count($list) . " people)") . "\n";
    }
    if (!$anyC) { echo "  (not a rep of any course)\n"; }

    echo "\n-- (E2) EVENTS they are a rep of (Event.assigned_to comma-list) — SOLE vs CO --\n";
    $soleEv = 0; $coEv = 0;
    $r = @mysqli_query($conn, "SELECT event_id, event_title, assigned_to FROM Event WHERE FIND_IN_SET('$id2', REPLACE(assigned_to,' ','')) > 0 ORDER BY event_id DESC LIMIT 40");
    $anyE = false;
    while ($r && ($e = mysqli_fetch_assoc($r))) {
        $anyE = true;
        $list = array_values(array_filter(array_map('trim', explode(',', (string) $e['assigned_to'])), 'strlen'));
        $sole = (count($list) === 1);
        if ($sole) { $soleEv++; } else { $coEv++; }
        echo "  event#{$e['event_id']} \"" . substr((string) $e['event_title'], 0, 45) . "\" assigned_to='{$e['assigned_to']}' — " . ($sole ? "SOLE rep" : "CO-rep (" . count($list) . " people)") . "\n";
    }
    if (!$anyE) { echo "  (not a rep of any event)\n"; }

    echo "\n-- (E3) MANUAL WhatsApp overrides where THEY are the primary rep (wa_course_owner) --\n";
    $r = @mysqli_query($conn, "SELECT ref_type, ref_id, updated_at FROM wa_course_owner WHERE user_id = $id2 ORDER BY updated_at DESC");
    $anyO = false; $nOverride = 0;
    while ($r && ($o = mysqli_fetch_assoc($r))) { $anyO = true; $nOverride++; echo "  {$o['ref_type']} #{$o['ref_id']} (set {$o['updated_at']})\n"; }
    if (!$anyO) { echo "  (no manual WhatsApp primary-rep overrides)\n"; }

    echo "\n-- VERDICT --\n";
    $ownsSole = ($soleCourse + $soleEv) > 0;
    $ownsCoOnly = !$ownsSole && ($coCourse + $coEv) > 0;
    if ($ownsSole) {
        echo "  ✔ PERSONAL SELLER — they are the SOLE rep on {$soleCourse} course(s) + {$soleEv} event(s).\n";
        echo "    → Treat like Edwin: give them their own target and a Personal section. Their attributed revenue is genuinely theirs.\n";
    } elseif ($ownsCoOnly || $nOverride > 0) {
        echo "  ~ CO-ASSIGNEE ONLY — they share every course/event with others ({$coCourse} co-course(s), {$coEv} co-event(s), {$nOverride} override(s)).\n";
        echo "    → Their attributed revenue is SHARED with teammates (Event.assigned_to is a comma-list), so it is ALSO counted on those teammates' rows — risk of double-counting the department total.\n";
        echo "    → Likely a manager tagged on team programmes, NOT a distinct personal product. Keep the neutral 'combined dept target' row rather than splitting.\n";
    } else {
        echo "  ✖ NO course/event rep assignment found — any attributed revenue must come from intakes/enquiries directly assigned to them (see sections above).\n";
    }
}

echo '</pre>';
require_once 'footer.php';
