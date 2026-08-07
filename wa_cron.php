<?php
/**
 * Cron runner for scheduled broadcasts. Place at the ERP root; call every few
 * minutes from the server's cron:
 *
 *   * / 5 * * * *  curl -s "https://<erp-domain>/wa_cron.php?token=<WA_CRON_TOKEN>" >/dev/null
 *
 * Runs WITHOUT auth.php (no session) — uses wa_db.php for the connection.
 * Gated by WA_CRON_TOKEN (falls back to WA_VERIFY_TOKEN if that's blank).
 */
require_once __DIR__ . '/includes/wa_db.php';        // $wa_conn
require_once __DIR__ . '/includes/wa_functions.php';
wa_use_nairobi_time($wa_conn);

header('Content-Type: application/json; charset=utf-8');

$expected = (defined('WA_CRON_TOKEN') && WA_CRON_TOKEN !== '') ? WA_CRON_TOKEN
          : (defined('WA_VERIFY_TOKEN') ? WA_VERIFY_TOKEN : '');
$given = (string)($_GET['token'] ?? '');

// A token must be configured AND match. Never run open to the world.
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

ignore_user_abort(true);
@set_time_limit(300);

// A fatal here used to produce a blank page or a bare 500 with no clue why, because
// the JSON content-type is already sent and production hides errors. Report it
// instead — in the response AND in the error log.
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) { return; }
    error_log('[wa_cron] FATAL: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['ok' => false, 'fatal' => $e['message'], 'at' => $e['file'] . ':' . $e['line']]);
});

// The parallel broadcast sender needs ext-curl. Say so plainly rather than dying
// on an undefined curl_multi_init().
$missing = [];
foreach (['curl_multi_init', 'curl_init', 'json_encode'] as $fn) {
    if (!function_exists($fn)) { $missing[] = $fn; }
}
if ($missing) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'PHP extension missing', 'undefined' => $missing]);
    exit;
}

// Run each subsystem in isolation: one broken step must not take down the whole
// run, and whichever step fails is named in the output.
$out = [];
$step = function ($name, callable $fn) use (&$out) {
    try {
        $out[$name] = $fn();
    } catch (\Throwable $e) {
        $out[$name] = [
            'error' => get_class($e) . ': ' . $e->getMessage(),
            'at'    => $e->getFile() . ':' . $e->getLine(),
        ];
        error_log('[wa_cron] ' . $name . ' failed: ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
};

$step('scheduled',  function () use ($wa_conn) { return wa_run_due_scheduled($wa_conn, 5); });
$step('replies',    function () use ($wa_conn) { return wa_run_due_replies($wa_conn, 20); });   // batched replies whose window elapsed
$step('unanswered', function () use ($wa_conn) {                                                // #11: never leave a customer on read
    $staleSecs = (int) wa_setting_get($wa_conn, 'unanswered_after_secs', '120');                 // default 2 min
    return wa_run_unanswered_sweep($wa_conn, $staleSecs, 50);
});
$step('followups',  function () use ($wa_conn) {                                                // #14: one gentle nudge on quiet chats
    $fuHours = (int) wa_setting_get($wa_conn, 'followup_after_hours', '23');                     // inside the 24h window
    return wa_run_followups($wa_conn, $fuHours, 20);
});
$step('payments',   function () use ($wa_conn) { return wa_run_payment_confirms($wa_conn, 20); }); // #15: confirm completed payments
$step('onsite',     function () use ($wa_conn) {                                                // in-person leads the AI cannot close
    $mins = (int) wa_setting_get($wa_conn, 'onsite_escalate_after_mins', '60');
    return wa_run_onsite_escalation($wa_conn, $mins, 50);
});
// LAST: drain large broadcasts (up to ~45s) so a big send never delays live customer chats.
$step('bcast_queue', function () use ($wa_conn) { return wa_run_broadcast_queue($wa_conn, 45, 150); });

echo json_encode(['ok' => true] + $out);
