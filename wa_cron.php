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

$res     = wa_run_due_scheduled($wa_conn, 5);
$replies = wa_run_due_replies($wa_conn, 20);   // send any batched replies whose window elapsed
$staleSecs = (int)wa_setting_get($wa_conn, 'unanswered_after_secs', '600');   // default 10 min
$sweep   = wa_run_unanswered_sweep($wa_conn, $staleSecs, 30);   // #11: never leave a customer on read
$fuHours = (int)wa_setting_get($wa_conn, 'followup_after_hours', '23');       // inside the 24h window
$follow  = wa_run_followups($wa_conn, $fuHours, 20);            // #14: one gentle nudge on quiet chats
echo json_encode(['scheduled' => $res, 'replies' => $replies, 'unanswered' => $sweep, 'followups' => $follow]);
