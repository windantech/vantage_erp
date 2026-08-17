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

echo '</pre>';
require_once 'footer.php';
