<?php
/**
 * cron_send_scheduled_emails.php
 * --------------------------------------------------------------------------
 * Processes due rows in `email_schedules` (created by email_scheduling.php),
 * expands each into recipients (register / ticket_congress), and sends the
 * chosen system_emails1 template via Brevo (send_mail_function).
 *
 * Mirrors the proven bulk-email cron: lock file, batching, placeholder
 * replacement, encoding cleanup, status tracking.
 *
 * Schedule (cPanel cron), every 5 minutes:
 *   php /home/USER/.../admin/cron/cron_send_scheduled_emails.php
 * Adjust the require paths below to match where you place this file.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* ---- How many emails to send per cron run (shared-hosting safe) ---- */
define('VASL_BATCH_PER_RUN', 80);
define('VASL_SEND_THROTTLE_US', 200000); // 0.2s between sends

/* ---- Logging (for testing) ----
 * Writes a readable log to scheduled-emails.log next to this file, AND echoes
 * to screen when run manually from CLI or with ?debug=1 in the browser.
 * Set VASL_VERBOSE to false to log per-run summaries only (quieter in production).
 */
define('VASL_LOG_FILE', __DIR__ . '/scheduled-emails.log');
define('VASL_VERBOSE', true);
$VASL_ECHO = (php_sapi_name() === 'cli') || isset($_GET['debug']);

function vasl_log($msg, $always = true) {
    global $VASL_ECHO;
    if (!$always && !VASL_VERBOSE) return;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    @file_put_contents(VASL_LOG_FILE, $line . "\n", FILE_APPEND);
    if ($VASL_ECHO) {
        echo $line . "\n";
        @ob_flush(); @flush();
    }
}

/* ---- Lock file: prevent overlapping cron runs ---- */
$lockFile = __DIR__ . '/scheduled-emails.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime > 900) {   // stale after 15 min
        unlink($lockFile);
    } else {
        vasl_log('Another run is in progress (lock held) — exiting.');
        exit;
    }
}
file_put_contents($lockFile, date('Y-m-d H:i:s'));
register_shutdown_function(function () use ($lockFile) {
    if (file_exists($lockFile)) unlink($lockFile);
});

vasl_log('=== Cron run started ===');

$baseDir = __DIR__;

/* ---- Dependencies (ADJUST these paths to your server layout) ---- */
require $baseDir . '/../../database/conn.php';
require_once $baseDir . '/../email_plugins/vendor/autoload.php';
require_once $baseDir . '/../email_plugins/email_function.php';

$conn->set_charset("utf8mb4");

/* ---- Decode a system_emails1 body (JSON-encoded HTML) to raw HTML ---- */
function vasl_decode_body($raw) {
    $decoded = json_decode($raw, true);
    if (is_string($decoded)) return $decoded;
    if (is_array($decoded) && isset($decoded['body'])) return $decoded['body'];
    return $raw; // already raw HTML
}

/* ---- Clean subject/body the same way the proven cron does ---- */
function vasl_clean_subject($s) {
    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    $s = preg_replace('/\x{FFFD}/u', '-', $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    return preg_replace('/\s{2,}/', ' ', trim($s));
}
function vasl_clean_body($b) {
    $b = mb_convert_encoding($b, 'UTF-8', 'UTF-8');
    return preg_replace('/\x{FFFD}/u', '', $b);
}

/* ---- Build the recipient query for a schedule (mirrors email_scheduling.php) ---- */
function vasl_recipient_sql($type, $target_id, $payment_filter) {
    $target_id = (int)$target_id;
    if ($type === 'virtual') {
        // register links a course via `program` (varchar holding the course id), not course_id
        if ($payment_filter === 'all') {
            return "SELECT r.email, MIN(r.firstname) AS firstname
                    FROM register r
                    WHERE r.program = '$target_id' AND r.email IS NOT NULL AND r.email != ''
                    GROUP BY r.email";
        } elseif ($payment_filter === 'paid') {
            return "SELECT r.email, MIN(r.firstname) AS firstname
                    FROM register r
                    INNER JOIN dpo_payment p ON r.entry_id = p.app_id
                    WHERE r.program = '$target_id' AND r.email IS NOT NULL AND r.email != ''
                    AND p.TransactionAmount > 0
                    GROUP BY r.email";
        } else { // unpaid
            return "SELECT r.email, MIN(r.firstname) AS firstname
                    FROM register r
                    LEFT JOIN dpo_payment p ON r.entry_id = p.app_id
                    WHERE r.program = '$target_id' AND r.email IS NOT NULL AND r.email != ''
                    AND (p.id IS NULL OR p.TransactionAmount IS NULL OR p.TransactionAmount = 0)
                    GROUP BY r.email";
        }
    } else { // international
        $sql = "SELECT t.email, MIN(t.firstname) AS firstname
                FROM ticket_congress t
                WHERE t.event_id = $target_id AND t.email IS NOT NULL AND t.email != ''";
        if ($payment_filter === 'paid')   $sql .= " AND t.status = 2";
        if ($payment_filter === 'unpaid') $sql .= " AND (t.status != 2 OR t.status IS NULL)";
        $sql .= " GROUP BY t.email";
        return $sql;
    }
}

/* ====================================================================== */
/* 1. Claim DUE schedules (pending and time has arrived)                  */
/*    TEST MODE: ?run=ID forces one specific schedule to run now,          */
/*    ignoring the due-time check and re-activating it if it had failed.   */
/* ====================================================================== */
$now = date('Y-m-d H:i:s');

$forceId = isset($_GET['run']) ? (int)$_GET['run'] : 0;

if ($forceId > 0) {
    // reset the chosen schedule so it can run again, then select just it
    $conn->query("UPDATE email_schedules SET status='pending', sent_count=0 WHERE id=$forceId");
    vasl_log("TEST MODE: forcing schedule #$forceId to run now (reset to pending, sent_count=0).");
    $due = $conn->query("SELECT * FROM email_schedules WHERE id=$forceId LIMIT 1");
} else {
    $due = $conn->query("
        SELECT * FROM email_schedules
        WHERE status IN ('pending','processing')
          AND CONCAT(scheduled_date, ' ', LPAD(TIME(scheduled_time),8,'0')) <= '" . $conn->real_escape_string($now) . "'
        ORDER BY scheduled_date ASC, scheduled_time ASC
        LIMIT 5
    ");
}

if (!$due) { vasl_log('ERROR: due-schedules query failed: ' . $conn->error); error_log("Scheduled-email cron query error: " . $conn->error); exit; }
if ($due->num_rows === 0) { vasl_log('No schedules are due right now. Nothing to send.'); exit; } // nothing to do

vasl_log('Found ' . $due->num_rows . ' schedule(s) to process' . ($forceId ? " (test run of #$forceId)." : ' (max 5 per run).'));

$sentThisRun = 0;

while ($sched = $due->fetch_assoc()) {
    if ($sentThisRun >= VASL_BATCH_PER_RUN) break;

    $scheduleId   = (int)$sched['id'];
    $type         = $sched['email_type'];
    $target_id    = (int)$sched['target_id'];
    $templateId   = (int)$sched['email_template_id'];
    $payment      = $sched['payment_filter'];
    $alreadySent  = (int)$sched['sent_count'];

    vasl_log("Schedule #$scheduleId: type=$type, target=$target_id ('" . $sched['target_name'] . "'), template=$templateId, filter=$payment, already sent=$alreadySent/" . (int)$sched['total_recipients'] . ".");

    /* mark processing so a concurrent run won't re-claim from scratch */
    $conn->query("UPDATE email_schedules SET status='processing' WHERE id=$scheduleId AND status='pending'");

    /* fetch the template (subject + JSON body). Select attachment defensively:
       some installs of system_emails1 have no `attachment` column. */
    $tpl = $conn->query("SELECT subject, body FROM system_emails1 WHERE id=$templateId LIMIT 1");
    if (!$tpl) {
        $conn->query("UPDATE email_schedules SET status='failed' WHERE id=$scheduleId");
        vasl_log("  -> FAILED: template query error: " . $conn->error);
        error_log("Schedule #$scheduleId template query error: " . $conn->error);
        continue;
    }
    if ($tpl->num_rows === 0) {
        $conn->query("UPDATE email_schedules SET status='failed' WHERE id=$scheduleId");
        vasl_log("  -> FAILED: no row in system_emails1 with id=$templateId. Marked failed.");
        error_log("Schedule #$scheduleId: template $templateId not found.");
        continue;
    }
    $tplRow      = $tpl->fetch_assoc();
    $subjectBase = vasl_clean_subject(!empty($tplRow['subject']) ? $tplRow['subject'] : 'Vantage Africa - Important Update');
    $bodyBase    = vasl_decode_body($tplRow['body']);

    /* optional attachment column (only if it exists and points to a real file) */
    $attachment = [];
    $att = @$conn->query("SELECT attachment FROM system_emails1 WHERE id=$templateId LIMIT 1");
    if ($att && $att->num_rows) {
        $aRow = $att->fetch_assoc();
        if (!empty($aRow['attachment']) && is_file($aRow['attachment'])) $attachment = [$aRow['attachment']];
    }

    if (trim(strip_tags($bodyBase)) === '') {
        $conn->query("UPDATE email_schedules SET status='failed' WHERE id=$scheduleId");
        vasl_log("  -> FAILED: template body is empty after decode. Marked failed.");
        error_log("Schedule #$scheduleId: empty body.");
        continue;
    }
    vasl_log("  Template loaded: subject=\"" . $subjectBase . "\", body " . strlen($bodyBase) . " chars" . (empty($attachment) ? "" : ", 1 attachment") . ".");

    /* fetch recipients, skipping the ones already sent (offset by sent_count) */
    $recSql = vasl_recipient_sql($type, $target_id, $payment);
    $recSql .= " ORDER BY email ASC LIMIT " . (int)$alreadySent . ", " . (VASL_BATCH_PER_RUN - $sentThisRun);

    $recips = $conn->query($recSql);
    if (!$recips) {
        $conn->query("UPDATE email_schedules SET status='failed' WHERE id=$scheduleId");
        vasl_log("  -> FAILED: recipient query error: " . $conn->error);
        error_log("Schedule #$scheduleId recipient query error: " . $conn->error);
        continue;
    }

    /* no more recipients beyond what we've sent -> this schedule is complete */
    if ($recips->num_rows === 0) {
        $conn->query("UPDATE email_schedules SET status='completed' WHERE id=$scheduleId");
        vasl_log("  -> COMPLETED: no more recipients beyond $alreadySent already sent.");
        continue;
    }
    vasl_log("  Fetched " . $recips->num_rows . " recipient(s) to send in this run.");

    while ($r = $recips->fetch_assoc()) {
        if ($sentThisRun >= VASL_BATCH_PER_RUN) break;

        $email = trim(strtolower($r['email']));
        $name  = isset($r['firstname']) ? $r['firstname'] : '';

        if (strpos($email, '@') === false) {     // invalid email, count as processed
            $alreadySent++;
            $conn->query("UPDATE email_schedules SET sent_count=$alreadySent WHERE id=$scheduleId");
            vasl_log("    skip (invalid email): '$email'", false);
            continue;
        }

        /* personalise body */
        $body = str_replace(
            ['$name', '{name}', '{{name}}', '{firstname}', '{{firstname}}', '$firstname'],
            htmlspecialchars($name),
            $bodyBase
        );
        $body = str_replace(['{email}', '{{email}}'], urlencode($email), $body);
        $body = vasl_clean_body($body);

        $ok = false;
        try {
            $ok = send_mail_function($email, $body, $subjectBase, $attachment);
        } catch (Exception $e) {
            vasl_log("    ERROR sending to $email: " . $e->getMessage());
            error_log("Schedule #$scheduleId send error for $email: " . $e->getMessage());
        }

        vasl_log("    " . ($ok ? "sent" : "FAILED") . " -> $email", false);

        /* advance the cursor whether or not a single send failed, so one bad
           address can't stall the whole schedule; failures are logged */
        $alreadySent++;
        $sentThisRun++;
        $conn->query("UPDATE email_schedules SET sent_count=$alreadySent WHERE id=$scheduleId");

        usleep(VASL_SEND_THROTTLE_US);
    }

    /* if we've now reached/passed the known total, mark complete */
    $totalKnown = (int)$sched['total_recipients'];
    if ($totalKnown > 0 && $alreadySent >= $totalKnown) {
        $conn->query("UPDATE email_schedules SET status='completed' WHERE id=$scheduleId");
        vasl_log("  -> COMPLETED: $alreadySent/$totalKnown sent.");
    } else {
        vasl_log("  -> PAUSED: $alreadySent" . ($totalKnown ? "/$totalKnown" : "") . " sent so far; will resume next run.");
    }
}

vasl_log("=== Cron run finished. Sent $sentThisRun email(s) this run. ===");

$conn->close();
?>