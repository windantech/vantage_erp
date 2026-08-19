<?php
/**
 * Which registration states silence the AI.
 *
 *   php includes/wa_enroll_gate_test.php
 *
 * Drives the real wa_enroll_owns_chat()/wa_enroll_active() against a recording
 * driver, so the assertions are on the SQL that actually runs.
 *
 * The bug this pins: a session in 'offered' used to silence the AI, while the form
 * itself deferred that same message back to the AI. Both sides deferred, the
 * customer was answered by nobody, and nothing ever moved the session on — one
 * contact went five messages and seven days without a reply.
 */
$ROW = null; $SQL = [];
if (!function_exists('mysqli_query')) {
    function mysqli_query($c, $sql) { $GLOBALS['SQL'][] = preg_replace('/\s+/', ' ', trim($sql)); return true; }
    function mysqli_fetch_assoc($r) { $v = $GLOBALS['ROW']; $GLOBALS['ROW'] = null; return $v; }
    function mysqli_real_escape_string($c, $s) { return addslashes((string)$s); }
    function mysqli_error($c) { return ''; }
}
require_once __DIR__ . '/wa_enroll.php';

$failures = 0; $checks = 0;
function check($label, $expected, $actual) {
    global $failures, $checks;
    $checks++;
    $ok = ($expected === $actual);
    if (!$ok) { $failures++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("\n        expected %s\n        got      %s\n",
                             var_export($expected, true), var_export($actual, true)));
}
function owns($status) {
    $GLOBALS['SQL'] = [];
    $GLOBALS['ROW'] = $status === null ? null : ['id' => 1, 'status' => $status, 'step' => 0];
    return wa_enroll_owns_chat('FAKE', 1);
}

echo "=== which registration states silence the AI ===\n\n";

check("'offered' does NOT silence the AI",   false, owns('offered'));
check("'collecting' silences it",             true, owns('collecting'));
check("'confirm' silences it",                true, owns('confirm'));
check('no session at all',                   false, owns(null));
check("'done' does not silence it",          false, owns('done'));
check("'cancelled' does not silence it",     false, owns('cancelled'));

echo "\n-- abandoned sessions are closed, not left open for ever --\n";
owns('offered');
$expire = '';
foreach ($GLOBALS['SQL'] as $q) { if (stripos($q, 'UPDATE wa_enroll_sessions') === 0) { $expire = $q; } }
check('an expiry UPDATE runs', true, $expire !== '');
check('it only touches open sessions', true, strpos($expire, "status IN ('offered','collecting','confirm')") !== false);
check('it ages off by updated_at',     true, strpos($expire, 'updated_at <') !== false);
check('it uses the configured window', true,
    strpos($expire, 'INTERVAL ' . WA_ENROLL_STALE_HOURS . ' HOUR') !== false);
check('it cancels rather than deletes', true, strpos($expire, "SET status = 'cancelled'") !== false);
check('it is scoped to this contact',  true, strpos($expire, 'contact_id = 1') !== false);

echo "\n-- the gate the AI actually uses --\n";
$fn = file_get_contents(__DIR__ . '/wa_functions.php');
check('wa_maybe_ai_answer checks owns_chat', true,
    strpos($fn, 'wa_enroll_owns_chat($conn, (int)$contact[\'id\'])') !== false);
check('and no longer silences on any active session', 0,
    preg_match('/wa_enroll_active\(\$conn, \(int\)\$contact\[.id.\]\)/', $fn));
// The webhook must still give the form first refusal — in 'offered' it may yet
// consume a "yes, register me here".
$inb = file_get_contents(__DIR__ . '/wa_inbound.php');
check('the webhook still offers the form first refusal', true,
    strpos($inb, 'wa_enroll_active($conn, $contactId)') !== false);

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
